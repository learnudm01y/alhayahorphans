package com.aso.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Build;
import android.util.Log;
import androidx.annotation.NonNull;
import androidx.core.app.NotificationCompat;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import java.io.File;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.UUID;

public class ChunkedUploadWorker extends Worker {
    private static final String TAG = "ChunkedUploadWorker";
    private static final int CHUNK_SIZE = 2 * 1024 * 1024; // 2MB chunks for better speed and stability
    private static final String CHANNEL_ID = "UploadChannel";
    private static final int NOTIFICATION_ID = 1001;

    public ChunkedUploadWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
        createNotificationChannel();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "File Uploads",
                NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("Shows progress of file uploads");
            NotificationManager manager = getApplicationContext().getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private void showUploadNotification(String title, String message, int progress, int maxProgress) {
        NotificationCompat.Builder builder = new NotificationCompat.Builder(getApplicationContext(), CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_menu_upload)
            .setContentTitle(title)
            .setContentText(message)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setProgress(maxProgress, progress, false);

        NotificationManager manager = (NotificationManager) getApplicationContext().getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.notify(NOTIFICATION_ID, builder.build());
        }
    }

    private void clearNotification() {
        NotificationManager manager = (NotificationManager) getApplicationContext().getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.cancel(NOTIFICATION_ID);
        }
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.i(TAG, "Starting chunked upload worker to process queue");

        if (!InternetUtils.isInternetActuallyAvailable(getApplicationContext())) {
            Log.w(TAG, "No actual internet access (Ping failed). Aborting chunked upload sync.");
            return Result.retry();
        }

        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getApplicationContext());

        try {
            SharedPreferences prefs = getApplicationContext().getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
            String token = prefs.getString("api_token", "");
            String baseUrl = ApiConfig.getBaseUrl(getApplicationContext());

            if (token == null || token.isEmpty()) {
                Log.e(TAG, "No auth token available in auth_prefs, trying capacitor prefs...");
                token = getApplicationContext().getSharedPreferences("capacitor", Context.MODE_PRIVATE)
                    .getString("auth_token", "");
                if (token == null || token.isEmpty()) {
                    Log.e(TAG, "Still no auth token available, aborting");
                    return Result.failure();
                }
            }

            int processed = 0;
            int success = 0;

            // ✨ NEW: Fetch offline inbox BEFORE processing to avoid re-uploading files that actually finished
            try {
                okhttp3.OkHttpClient client = new okhttp3.OkHttpClient.Builder()
                    .connectTimeout(30, java.util.concurrent.TimeUnit.SECONDS)
                    .readTimeout(30, java.util.concurrent.TimeUnit.SECONDS)
                    .build();
                org.json.JSONArray ackIds = new org.json.JSONArray();
                okhttp3.Request request = new okhttp3.Request.Builder()
                        .url(baseUrl + "/api/uploads/offline-inbox")
                        .header("Authorization", "Bearer " + token)
                        .header("Accept", "application/json")
                        .get()
                        .build();
                try (okhttp3.Response response = client.newCall(request).execute()) {
                    if (response.isSuccessful() && response.body() != null) {
                        org.json.JSONObject jsonResponse = new org.json.JSONObject(response.body().string());
                        if (jsonResponse.optBoolean("success", false)) {
                            org.json.JSONArray dataArray = jsonResponse.optJSONArray("data");
                            if (dataArray != null && dataArray.length() > 0) {
                                for (int i = 0; i < dataArray.length(); i++) {
                                    org.json.JSONObject fileObj = dataArray.getJSONObject(i);
                                    int id = fileObj.optInt("id");
                                    String fName = fileObj.optString("file_name");
                                    String status = fileObj.optString("status");
                                    UploadDatabaseHelper.UploadItem item = dbHelper.getFileByName(fName);
                                    if (item != null && "completed".equals(status)) {
                                        dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                                    }
                                    ackIds.put(id);
                                }
                            }
                        }
                    }
                }
                if (ackIds.length() > 0) {
                    org.json.JSONObject ackObj = new org.json.JSONObject();
                    ackObj.put("ids", ackIds);
                    okhttp3.RequestBody ackBody = okhttp3.RequestBody.create(ackObj.toString(), okhttp3.MediaType.parse("application/json; charset=utf-8"));
                    okhttp3.Request ackRequest = new okhttp3.Request.Builder()
                            .url(baseUrl + "/api/uploads/offline-inbox/ack")
                            .header("Authorization", "Bearer " + token)
                            .header("Accept", "application/json")
                            .post(ackBody)
                            .build();
                    client.newCall(ackRequest).execute().close();
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to check offline inbox in ChunkedUploadWorker", e);
            }

            while (true) {
                UploadDatabaseHelper.UploadItem nextFile = dbHelper.getNextPendingFile();

                if (nextFile == null) {
                    Log.d(TAG, "✅ Finished processing all pending files");
                    break;
                }

                processed++;
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log.d(TAG, "📤 Processing file #" + processed + ": " + nextFile.fileName);
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

                dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_UPLOADING, null);

                // Use the item's token if available
                String uploadToken = (nextFile.authToken != null && !nextFile.authToken.isEmpty()) ? nextFile.authToken : token;

                int totalPending = dbHelper.getPendingFilesCount() + 1; // +1 because current is already 'uploading'
                String notificationTitle = "Uploading file " + processed + "/" + totalPending;

                boolean uploaded = processFile(nextFile, uploadToken, baseUrl, notificationTitle);

                if (uploaded) {
                    dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PROCESSING_SERVER, null);
                    success++;
                    Log.d(TAG, "✅✅✅ Successfully uploaded to server, awaiting Drive processing! (" + success + "/" + processed + ")");
                } else {
                    // ✨ NEW: Do not increment retry if internet was lost during upload
                    if (!InternetUtils.isInternetActuallyAvailable(getApplicationContext())) {
                        Log.e(TAG, "🚫 Internet disconnected mid-upload! Pausing without penalizing retries.");
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, "Network disconnected");
                        break;
                    }

                    dbHelper.incrementRetryCount(nextFile.id);
                    if (nextFile.retryCount >= 30) {
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_FAILED, "Failed after 30 retries");
                        Log.e(TAG, "❌❌❌ Failed after 30 retries");
                    } else {
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, null);
                        Log.w(TAG, "⚠️ Failed - Will retry later (" + nextFile.retryCount + "/30)");
                    }
                }
            }

            clearNotification();
            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "Worker failed completely", e);
            clearNotification();
            return Result.retry(); // Reschedule if worker crashes entirely
        }
    }

    private boolean processFile(UploadDatabaseHelper.UploadItem item, String token, String baseUrl, String notifTitle) {
        try {
            String filePath = item.filePath;
            String fileName = item.fileName;
            String fileType = item.fileType;
            if (fileType == null) {
                fileType = "application/octet-stream";
            }
            long sponsorshipId = item.photoId; // photoId holds sponsorship_id mapping

            if (filePath == null || fileName == null) {
                Log.e(TAG, "Missing required parameters for item " + item.id);
                return false;
            }

            if (filePath.startsWith("file://")) {
                filePath = filePath.substring(7);
            }

            long fileSize = 0;
            Uri uri = null;
            if (filePath.startsWith("content://")) {
                uri = Uri.parse(filePath);
                try (InputStream sizeStream = getApplicationContext().getContentResolver().openInputStream(uri)) {
                    if (sizeStream == null) return false;
                    long actualSize = 0;
                    byte[] sizeBuffer = new byte[8192];
                    int sizeRead;
                    while ((sizeRead = sizeStream.read(sizeBuffer)) != -1) {
                        actualSize += sizeRead;
                    }
                    fileSize = actualSize;
                }
            } else {
                File file = new File(getApplicationContext().getFilesDir(), filePath);
                if (!file.exists()) {
                    file = new File(filePath);
                }
                if (file.exists()) {
                    fileSize = file.length();
                    uri = Uri.fromFile(file);
                }
            }

            if (fileSize <= 0 || uri == null) {
                Log.e(TAG, "File not found or empty: " + filePath);
                return false;
            }

            long totalChunks = (long) Math.ceil((double) fileSize / CHUNK_SIZE);
            String uploadId = "upload_" + item.id;
            String chunkUrl = baseUrl + "/api/mobile/upload-chunk";
            String statusUrl = baseUrl + "/api/mobile/upload-status/" + uploadId;

            // Query server for already received chunks
            java.util.Set<Integer> receivedChunks = new java.util.HashSet<>();
            try {
                URL sUrl = new URL(statusUrl);
                HttpURLConnection sConn = (HttpURLConnection) sUrl.openConnection();
                sConn.setRequestMethod("GET");
                sConn.setRequestProperty("Authorization", "Bearer " + token);
                sConn.setConnectTimeout(30000);
                sConn.setReadTimeout(30000);

                if (sConn.getResponseCode() == 200) {
                    try (InputStream is = sConn.getInputStream()) {
                        java.util.Scanner scanner = new java.util.Scanner(is).useDelimiter("\\A");
                        String response = scanner.hasNext() ? scanner.next() : "";
                        org.json.JSONObject json = new org.json.JSONObject(response);
                        org.json.JSONArray chunksArr = json.optJSONArray("received_chunks");
                        if (chunksArr != null) {
                            for (int i = 0; i < chunksArr.length(); i++) {
                                receivedChunks.add(chunksArr.getInt(i));
                            }
                        }
                    }
                }
                sConn.disconnect();
            } catch (Exception e) {
                Log.w(TAG, "Could not fetch upload status, assuming 0 chunks received", e);
            }

            try (InputStream fileStream = getApplicationContext().getContentResolver().openInputStream(uri)) {
                if (fileStream == null) {
                    Log.e(TAG, "Cannot open input stream for: " + uri);
                    return false;
                }

                byte[] buffer = new byte[CHUNK_SIZE];
                int chunkIndex = 0;
                int bytesRead;

                while ((bytesRead = fileStream.read(buffer)) != -1) {
                    if (receivedChunks.contains(chunkIndex)) {
                        Log.d(TAG, "Skipping chunk " + (chunkIndex + 1) + " (already on server)");
                        chunkIndex++;
                        continue;
                    }

                    Log.d(TAG, "Uploading chunk " + (chunkIndex + 1) + " of " + totalChunks);
                    showUploadNotification(notifTitle, fileName + " (" + (chunkIndex + 1) + "/" + totalChunks + " parts)", chunkIndex + 1, (int)totalChunks);

                    boolean chunkSuccess = uploadChunkData(chunkUrl, token, uploadId, chunkIndex, totalChunks, fileName, fileType, sponsorshipId, buffer, bytesRead);
                    
                    if (!chunkSuccess) {
                        Log.e(TAG, "Chunk " + chunkIndex + " failed.");
                        return false;
                    }

                    chunkIndex++;
                }
            }

            return true;

        } catch (Exception e) {
            Log.e(TAG, "Chunked upload failed for item " + item.id, e);
            return false;
        }
    }

    private boolean uploadChunkData(String urlString, String token, String uploadId, int chunkIndex, long totalChunks, String fileName, String fileType, long sponsorshipId, byte[] buffer, int bytesRead) {
        HttpURLConnection conn = null;
        try {
            URL url = new URL(urlString);
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("Content-Type", "application/octet-stream");
            conn.setRequestProperty("X-Upload-Id", uploadId);
            conn.setRequestProperty("X-Chunk-Index", String.valueOf(chunkIndex));
            conn.setRequestProperty("X-Total-Chunks", String.valueOf(totalChunks));
            conn.setRequestProperty("X-File-Name", fileName);
            conn.setRequestProperty("X-File-Type", fileType);
            conn.setRequestProperty("X-Sponsorship-Id", String.valueOf(sponsorshipId));
            conn.setRequestProperty("Bypass-Tunnel-Reminder", "true");
            conn.setDoOutput(true);
            conn.setConnectTimeout(60000);
            conn.setReadTimeout(120000);

            try (OutputStream os = conn.getOutputStream()) {
                os.write(buffer, 0, bytesRead);
                os.flush();
            }

            int responseCode = conn.getResponseCode();
            if (responseCode >= 400) {
                Log.e(TAG, "Chunk HTTP Error " + responseCode);
                return false;
            }
            return true;
        } catch (Exception e) {
            Log.e(TAG, "Chunk network exception", e);
            return false;
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
    }
}
