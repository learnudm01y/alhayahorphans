package com.aso.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;
import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;
import org.alhayah.sponsorships.DataSyncDatabaseHelper;
import org.json.JSONObject;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.List;

/**
 * WorkManager Worker to process and upload the offline action queue sequentially.
 * This runs as part of the Unified Master Sync Chain to prevent parallel data upload/download collisions.
 */
public class DataSyncQueueWorker extends Worker {
    private static final String TAG = "DataSyncQueueWorker";
    private final DataSyncDatabaseHelper dbHelper;
    private final Context context;

    public DataSyncQueueWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
        this.context = context;
        this.dbHelper = DataSyncDatabaseHelper.getInstance(context);
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🔄 DataSyncQueueWorker - Starting sequential offline data upload queue");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            // Check internet connectivity
            if (!InternetUtils.isInternetActuallyAvailable(context)) {
                Log.w(TAG, "No actual internet access - aborting data queue upload");
                return Result.retry();
            }

            // Reset failed items so we can retry them
            int resetCount = dbHelper.resetFailedData();
            if (resetCount > 0) {
                Log.d(TAG, "🔄 Reset " + resetCount + " failed data items to pending");
            }

            int successCount = 0;
            int failureCount = 0;
            java.util.Set<Long> sessionFailedIds = new java.util.HashSet<>();

            while (true) {
                List<DataSyncDatabaseHelper.DataSyncItem> pendingItems = dbHelper.getPendingData();
                
                // Filter out items that already failed in this session to prevent infinite loops
                java.util.List<DataSyncDatabaseHelper.DataSyncItem> activeQueue = new java.util.ArrayList<>();
                for (DataSyncDatabaseHelper.DataSyncItem item : pendingItems) {
                    if (!sessionFailedIds.contains(item.id)) {
                        activeQueue.add(item);
                    }
                }

                if (activeQueue.isEmpty()) {
                    Log.d(TAG, "✅ No more pending data items to process");
                    break;
                }

                Log.d(TAG, "📊 Processing " + activeQueue.size() + " offline data items");

                for (DataSyncDatabaseHelper.DataSyncItem item : activeQueue) {
                    // Check if we still have network
                    if (!InternetUtils.isInternetActuallyAvailable(context)) {
                        Log.w(TAG, "Network lost mid-queue! Pausing.");
                        return Result.retry();
                    }

                    // Mark as uploading directly
                    if ("failed".equals(item.status)) {
                        dbHelper.markAsUploading(item.id);
                    }

                    Log.d(TAG, "Syncing item ID=" + item.id + ", Type=" + item.dataType);
                    boolean success = syncDataItem(item);

                    if (success) {
                        successCount++;
                    } else {
                        failureCount++;
                        sessionFailedIds.add(item.id);
                    }

                    // Throttle slightly between requests
                    Thread.sleep(300);
                }
            }

            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "📊 Data Sync Session Completed: Success=" + successCount + ", Failures=" + failureCount);
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            if (failureCount > 0 && successCount == 0) {
                return Result.retry();
            }

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ DataSyncQueueWorker failed with exception", e);
            return Result.failure();
        }
    }

    private boolean syncDataItem(DataSyncDatabaseHelper.DataSyncItem item) {
        dbHelper.markAsUploading(item.id);

        HttpURLConnection conn = null;
        try {
            SharedPreferences prefs = context.getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
            String token = prefs.getString("api_token", "");
            String baseUrl = prefs.getString("api_base_url", ApiConfig.BASE_URL);

            if (token.isEmpty()) {
                Log.e(TAG, "❌ No API token available");
                dbHelper.markAsFailed(item.id, "No API token");
                return false;
            }

            if (baseUrl.endsWith("/")) {
                baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
            }
            if (!baseUrl.endsWith("/api")) {
                baseUrl = baseUrl + "/api";
            }

            String fullUrl = baseUrl + item.endpoint;
            Log.d(TAG, "🌐 POST " + fullUrl);

            // Sync the action method (DataSyncForegroundService always uses POST)
            URL url = new URL(fullUrl);
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Bypass-Tunnel-Reminder", "true");
            conn.setDoOutput(true);
            conn.setConnectTimeout(30000);
            conn.setReadTimeout(30000);

            try (OutputStream os = conn.getOutputStream()) {
                os.write(item.dataJson.getBytes("UTF-8"));
                os.flush();
            }

            int responseCode = conn.getResponseCode();
            Log.d(TAG, "Server responded with HTTP " + responseCode + " for endpoint: " + item.endpoint);

            if (responseCode >= 200 && responseCode < 300) {
                // If the item represents a new sponsorship/orphan creation, check if we need to update IDs
                try (BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
                    StringBuilder response = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) {
                        response.append(line);
                    }
                    JSONObject json = new JSONObject(response.toString());
                    JSONObject requestData = new JSONObject(item.dataJson);
                    
                    int localId = requestData.optInt("id", 0);
                    
                    // Enforce local DB consistency mapping server-side IDs if returned
                    if (json.optBoolean("success", false) && json.has("data")) {
                        JSONObject data = json.optJSONObject("data");
                        if (data != null && data.has("id")) {
                            int serverId = data.optInt("id");
                            
                            if (localId > 0 && localId != serverId) {
                                Log.d(TAG, "🔗 Mapping local ID " + localId + " to server ID " + serverId);
                                updateLocalSponsorshipId(localId, serverId);
                            }
                        }
                    }
                } catch (Exception e) {
                    Log.w(TAG, "Non-critical: could not parse server response for ID mapping", e);
                }

                dbHelper.markAsUploaded(item.id);
                return true;
            } else {
                dbHelper.markAsFailed(item.id, "HTTP " + responseCode);
                return false;
            }

        } catch (Exception e) {
            Log.e(TAG, "❌ Failed to sync item " + item.id, e);
            dbHelper.markAsFailed(item.id, e.getMessage());
            return false;
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
    }

    private void updateLocalSponsorshipId(int localId, int serverId) {
        try {
            SponsorshipsDatabaseHelper sponsorshipsDb = SponsorshipsDatabaseHelper.getInstance(context);
            synchronized (SponsorshipsDatabaseHelper.class) {
                String existingDataStr = sponsorshipsDb.getSponsorship(localId);
                if (existingDataStr != null) {
                    JSONObject existingData = new JSONObject(existingDataStr);
                    existingData.put("id", serverId);
                    
                    android.database.sqlite.SQLiteDatabase db = sponsorshipsDb.getWritableDatabase();
                    android.content.ContentValues cv = new android.content.ContentValues();
                    cv.put("id", serverId);
                    cv.put("json_payload", existingData.toString());
                    
                    // Save under new server ID
                    db.insertWithOnConflict("sponsorships", null, cv, android.database.sqlite.SQLiteDatabase.CONFLICT_REPLACE);
                    
                    // Delete old local ID
                    db.delete("sponsorships", "id = ?", new String[]{String.valueOf(localId)});
                    
                    // Map any pending files to the new server ID in UploadDatabaseHelper
                    UploadDatabaseHelper uploadDb = UploadDatabaseHelper.getInstance(context);
                    android.database.sqlite.SQLiteDatabase dbUpload = uploadDb.getWritableDatabase();
                    android.content.ContentValues cvUpload = new android.content.ContentValues();
                    cvUpload.put("photo_id", serverId);
                    
                    // Also update api_url to point to new server id
                    dbUpload.update("upload_queue", cvUpload, "photo_id = ?", new String[]{String.valueOf(localId)});
                    
                    // Send broadcast for real-time UI refresh
                    android.content.Intent updateIntent = new android.content.Intent("com.aso.app.REALTIME_UPDATE");
                    updateIntent.putExtra("sponsorship_id", serverId);
                    context.sendBroadcast(updateIntent);
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to update local ID " + localId + " to server ID " + serverId, e);
        }
    }
}
