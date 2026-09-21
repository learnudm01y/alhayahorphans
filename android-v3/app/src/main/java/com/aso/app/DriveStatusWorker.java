package com.aso.app;

import android.content.Context;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.IOException;
import java.util.List;
import java.util.concurrent.TimeUnit;

import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

public class DriveStatusWorker extends Worker {
    private static final String TAG = "DriveStatusWorker";
    private final UploadDatabaseHelper dbHelper;
    private final OkHttpClient client;

    public DriveStatusWorker(@NonNull Context context, @NonNull WorkerParameters workerParams) {
        super(context, workerParams);
        dbHelper = UploadDatabaseHelper.getInstance(context);
        client = new OkHttpClient.Builder()
                .connectTimeout(30, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .build();
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.d(TAG, "🔍 Fetching Offline Inbox for File Statuses...");
        try {
            String baseUrl = ApiConfig.getBaseUrl(getApplicationContext());
            String token = getApplicationContext().getSharedPreferences("auth_prefs", Context.MODE_PRIVATE)
                    .getString("api_token", "");

            if (token == null || token.isEmpty()) {
                token = getApplicationContext().getSharedPreferences("capacitor", Context.MODE_PRIVATE)
                      .getString("auth_token", "");
            }

            if (token == null || token.isEmpty()) {
                Log.e(TAG, "❌ No auth token found!");
                return Result.success();
            }

            // 1. Fetch the Offline Inbox
            Request request = new Request.Builder()
                    .url(baseUrl + "/api/uploads/offline-inbox")
                    .header("Authorization", "Bearer " + token)
                    .header("Accept", "application/json")
                    .get()
                    .build();

            JSONArray ackIds = new JSONArray();

            try (Response response = client.newCall(request).execute()) {
                if (!response.isSuccessful()) {
                    Log.e(TAG, "❌ Server returned error fetching inbox: " + response.code());
                    return Result.success(); // Don't block chain on network error
                }

                String responseBody = response.body().string();
                JSONObject jsonResponse = new JSONObject(responseBody);
                
                if (jsonResponse.optBoolean("success", false)) {
                    JSONArray dataArray = jsonResponse.optJSONArray("data");
                    if (dataArray != null && dataArray.length() > 0) {
                        Log.d(TAG, "📦 Found " + dataArray.length() + " offline status updates.");
                        
                        for (int i = 0; i < dataArray.length(); i++) {
                            JSONObject fileObj = dataArray.getJSONObject(i);
                            int id = fileObj.optInt("id");
                            String fileName = fileObj.optString("file_name");
                            String status = fileObj.optString("status");
                            String errorMessage = fileObj.optString("error_message", null);
                            
                            Log.d(TAG, "📥 Processing Inbox Item: " + fileName + " -> " + status);
                            
                            // Find local file item by name to get its internal ID
                            UploadDatabaseHelper.UploadItem localItem = dbHelper.getFileByName(fileName);
                            
                            if (localItem != null) {
                                if ("completed".equals(status)) {
                                    dbHelper.updateFileStatus(localItem.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                                    try {
                                        UploadServicePlugin.notifyUploadStatusChanged(localItem.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                                    } catch(Exception ignored){}
                                } else if ("failed".equals(status)) {
                                    dbHelper.updateFileStatus(localItem.id, UploadDatabaseHelper.STATUS_FAILED, errorMessage != null ? errorMessage : "Failed on server side.");
                                    try {
                                        UploadServicePlugin.notifyUploadStatusChanged(localItem.id, UploadDatabaseHelper.STATUS_FAILED, errorMessage != null ? errorMessage : "Failed on server side.");
                                    } catch(Exception ignored){}
                                }
                            } else {
                                Log.w(TAG, "⚠️ Received status for unknown file: " + fileName);
                            }
                            
                            // Collect ID to ACK regardless of if we found it locally
                            ackIds.put(id);
                        }
                    } else {
                        Log.d(TAG, "ℹ️ No offline updates found in inbox.");
                    }
                }
            }

            // 2. Acknowledge and Delete Processed Inbox Items
            if (ackIds.length() > 0) {
                Log.d(TAG, "📤 ACKing " + ackIds.length() + " processed items...");
                JSONObject ackObj = new JSONObject();
                ackObj.put("ids", ackIds);
                
                RequestBody ackBody = RequestBody.create(
                        ackObj.toString(),
                        MediaType.parse("application/json; charset=utf-8")
                );
                
                Request ackRequest = new Request.Builder()
                        .url(baseUrl + "/api/uploads/offline-inbox/ack")
                        .header("Authorization", "Bearer " + token)
                        .header("Accept", "application/json")
                        .post(ackBody)
                        .build();
                        
                try (Response ackResponse = client.newCall(ackRequest).execute()) {
                    if (ackResponse.isSuccessful()) {
                        Log.d(TAG, "✅ Successfully ACKed offline statuses.");
                    } else {
                        Log.e(TAG, "❌ Failed to ACK offline statuses: " + ackResponse.code());
                    }
                }
            }

            // 3. Fallback Check: Are there any files still stuck on processing locally for too long?
            // Optional: Reschedule check if we still have processing files
            List<UploadDatabaseHelper.UploadItem> remaining = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PROCESSING_SERVER);
            if (!remaining.isEmpty()) {
                Log.d(TAG, "⏳ Still " + remaining.size() + " files processing on server...");
            }

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ Failed to process offline inbox", e);
            return Result.success(); // Proceed chain even on error
        }
    }
}
