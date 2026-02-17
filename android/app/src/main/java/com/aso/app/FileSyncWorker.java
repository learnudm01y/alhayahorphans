package com.aso.app;

import android.content.Context;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.work.Constraints;
import androidx.work.ExistingWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import java.io.File;
import java.util.List;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * 🎯 Single Sync Orchestrator - The ONLY place where file uploads happen
 *
 * Architecture:
 * - One Worker instance processes the entire upload queue serially
 * - No ForegroundService bloat - WorkManager handles everything
 * - OkHttp streaming for efficient bandwidth
 * - Circuit breaker with exponential backoff for retries
 * - NO BASE64 - file:// URIs only
 *
 * Integration:
 * JavaScript → UploadService.addFileToQueue() → FileSyncWorker.scheduleImmediateSync()
 * NetworkMonitor → onNetworkAvailable() → FileSyncWorker.scheduleImmediateSync()
 *
 * @version 2.0 - Fixed to use existing UploadDatabaseHelper methods
 * @date 2026-02-14
 */
public class FileSyncWorker extends Worker {
    private static final String TAG = "FileSyncWorker";
    private static final String WORK_NAME = "file_sync_orchestrator";

    // ✅ Prevents concurrent schedule calls AND calls during doWork
    private static final AtomicBoolean isScheduling = new AtomicBoolean(false);
    private static final AtomicBoolean isWorking = new AtomicBoolean(false);

    private UploadDatabaseHelper dbHelper;
    private Context context;

    public FileSyncWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
        this.context = context;
        this.dbHelper = UploadDatabaseHelper.getInstance(context);
    }

    @NonNull
    @Override
    public Result doWork() {
        // ✅ CRITICAL: Set isWorking flag to prevent schedule calls during upload
        if (!isWorking.compareAndSet(false, true)) {
            Log.w(TAG, "Worker already running - skipping");
            return Result.success();
        }

        try {
            if (!isNetworkAvailable()) {
                return Result.retry();
            }

            Log.e(TAG, "ℹ️ Running as background WorkManager task (Android 15 compatible)");
            Log.e(TAG, "ℹ️ For large files, consider using WiFi or keeping app in foreground");

            // ═══════════════════════════════════════════════════════════════════
            // Get all pending files from database
            // ═══════════════════════════════════════════════════════════════════
            List<UploadDatabaseHelper.UploadItem> pendingItems = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PENDING);

            if (pendingItems == null || pendingItems.isEmpty()) {
                return Result.success();
            }

            Log.d(TAG, "Found " + pendingItems.size() + " pending uploads");

            int successCount = 0;
            int failureCount = 0;
            int skippedCount = 0;

            // ═══════════════════════════════════════════════════════════════════
            // Process each file serially
            // ═══════════════════════════════════════════════════════════════════
            for (int i = 0; i < pendingItems.size(); i++) {
                UploadDatabaseHelper.UploadItem item = pendingItems.get(i);

                Log.d(TAG, "Processing " + (i + 1) + "/" + pendingItems.size() + ": " + item.fileName);

                // ═══════════════════════════════════════════════════════════════════
                // Circuit Breaker Check
                // ═══════════════════════════════════════════════════════════════════
                if (!dbHelper.shouldRetry(item)) {
                    Log.w(TAG, "Circuit breaker - skipping item " + item.id);
                    skippedCount++;
                    continue;
                }

                // ═══════════════════════════════════════════════════════════════════
                // Verify file exists (support both file:// and content:// URIs)
                // ═══════════════════════════════════════════════════════════════════
                long fileSize = 0;
                boolean fileExists = false;

                if (item.filePath.startsWith("content://")) {
                    // content:// URI - use ContentResolver
                    try {
                        android.net.Uri uri = android.net.Uri.parse(item.filePath);
                        android.database.Cursor cursor = context.getContentResolver().query(uri, null, null, null, null);
                        if (cursor != null && cursor.moveToFirst()) {
                            int sizeIndex = cursor.getColumnIndex(android.provider.OpenableColumns.SIZE);
                            if (sizeIndex != -1) {
                                fileSize = cursor.getLong(sizeIndex);
                            }
                            cursor.close();
                            fileExists = true;
                            Log.e(TAG, "📱 content:// URI verified - Size: " + (fileSize / 1024.0 / 1024.0) + " MB");
                        } else {
                            Log.e(TAG, "❌ content:// URI not accessible");
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "❌ Failed to query content:// URI: " + e.getMessage());
                    }
                } else {
                    // Regular file path
                    File file = new File(item.filePath);
                    fileExists = file.exists();
                    if (fileExists) {
                        fileSize = file.length();
                        Log.e(TAG, "📁 File path verified - Size: " + (fileSize / 1024.0 / 1024.0) + " MB");
                    } else {
                        Log.e(TAG, "❌ File not found: " + file.getAbsolutePath());
                    }
                }

                if (!fileExists) {
                    Log.e(TAG, "❌ File does not exist - marking as failed");
                    dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_FAILED, "File not found or inaccessible");
                    failureCount++;
                    continue;
                }

                // ═══════════════════════════════════════════════════════════════════
                // Show notification + Mark as uploading
                // ═══════════════════════════════════════════════════════════════════════
                showUploadNotification("Uploading file " + (i + 1) + "/" + pendingItems.size(), item.fileName, (int)((i * 100.0) / pendingItems.size()));
                dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_UPLOADING, null);

                // ═══════════════════════════════════════════════════════════════════
                // Attempt upload
                // ═══════════════════════════════════════════════════════════════════
                Log.e(TAG, "🚀 Starting OkHttp upload...");
                boolean success = uploadFileWithOkHttp(item);

                if (success) {
                    Log.e(TAG, "✅ Upload successful!");
                    successCount++;

                    // ✅✅✅ CRITICAL: Update status to COMPLETED
                    Log.e(TAG, "✅✅✅ UPDATING STATUS TO COMPLETED");
                    Log.e(TAG, "   File ID: " + item.id);
                    Log.e(TAG, "   File Name: " + item.fileName);
                    Log.e(TAG, "   Status: COMPLETED");

                    dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_COMPLETED, null);

                    Log.e(TAG, "✅✅✅ STATUS UPDATE EXECUTED - Verifying...");
                    // Verify the update worked
                    UploadDatabaseHelper.UploadItem updatedItem = dbHelper.getFileById(item.id);
                    if (updatedItem != null) {
                        Log.e(TAG, "   Current status in DB: " + updatedItem.status);
                        if ("completed".equals(updatedItem.status)) {
                            Log.e(TAG, "   ✅✅✅ VERIFIED: Status is COMPLETED in database!");
                        } else {
                            Log.e(TAG, "   ❌❌❌ ERROR: Status was NOT updated! Still: " + updatedItem.status);
                        }
                    } else {
                        Log.e(TAG, "   ❌❌❌ ERROR: Could not retrieve file from database!");
                    }

                    // ✨ Notify JavaScript about successful upload
                    try {
                        UploadStatusBridge.notifyUploadComplete(item.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                    } catch (Exception e) {
                        PendingStatusUpdateHelper.addPendingUpdate(
                            context,
                            item.id,
                            UploadDatabaseHelper.STATUS_COMPLETED,
                            null
                        );
                    }

                    try {
                        UploadServicePlugin.notifyUploadStatusChanged(item.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                    } catch (Exception ignored) {
                    }

                    // Delete the local file after successful upload (only for regular files)
                    if (!item.filePath.startsWith("content://")) {
                        File localFile = new File(item.filePath);
                        if (localFile.exists()) {
                            localFile.delete();
                        }
                    }

                } else {
                    failureCount++;
                    dbHelper.incrementRetryCount(item.id);

                    // If max retries exceeded, mark as failed permanently
                    if (item.retryCount >= 2) { // 0, 1, 2 = 3 attempts
                        String errorMsg = "Exceeded max retry attempts (3)";
                        dbHelper.updateFileStatus(
                            item.id,
                            UploadDatabaseHelper.STATUS_FAILED,
                            errorMsg
                        );
                        Log.e(TAG, "🚫 Circuit breaker activated - marked as failed");

                        // ✨ Notify JavaScript about permanent failure
                        try {
                            // 🌉 Try direct bridge first
                            UploadStatusBridge.notifyUploadComplete(item.id, UploadDatabaseHelper.STATUS_FAILED, errorMsg);
                            Log.e(TAG, "🌉 JavaScript notified via DIRECT bridge (failure)");
                        } catch (Exception e) {
                            Log.w(TAG, "⚠️ Direct bridge failed: " + e.getMessage());

                            // ✅ FALLBACK: Save pending update
                            PendingStatusUpdateHelper.addPendingUpdate(
                                context,
                                item.id,
                                UploadDatabaseHelper.STATUS_FAILED,
                                errorMsg
                            );
                            Log.e(TAG, "💾 Failure update saved as PENDING");
                        }

                        try {
                            // Legacy notification (keep for compatibility)
                            UploadServicePlugin.notifyUploadStatusChanged(item.id, UploadDatabaseHelper.STATUS_FAILED, errorMsg);
                            Log.e(TAG, "📡 JavaScript notified via legacy Capacitor event");
                        } catch (Exception ignored) {
                        }
                    } else {
                        Log.e(TAG, "🔄 Will retry later (attempt " + (item.retryCount + 2) + "/3)");
                        // Status remains PENDING for future retry
                    }
                }

                // Small delay between files to avoid network congestion
                try {
                    Thread.sleep(500);
                } catch (InterruptedException e) {
                    Log.w(TAG, "Sleep interrupted");
                }
            }

            // ═══════════════════════════════════════════════════════════════════
            // Summary
            // ═══════════════════════════════════════════════════════════════════
            Log.d(TAG, "Upload session: Success=" + successCount + ", Failed=" + failureCount + ", Skipped=" + skippedCount);

            // Show completion notification
            if (successCount > 0) {
                showUploadNotification(
                    "✅ Upload Complete",
                    successCount + " file(s) uploaded",
                    100
                );
                new android.os.Handler(android.os.Looper.getMainLooper()).postDelayed(() -> {
                    cancelUploadNotification();
                }, 2000);
            }

            // Reschedule if there are still pending files
            int remainingPending = dbHelper.getPendingFilesCount();
            if (remainingPending > 0) {
                scheduleRetrySync(calculateBackoffMinutes(failureCount));
            }

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ Worker failed with exception: " + e.getMessage(), e);
            e.printStackTrace();

            // Reschedule for retry
            scheduleRetrySync(5); // 5 minutes backoff
            return Result.retry();
        } finally {
            // ✅ CRITICAL: Release isWorking flag to allow future schedules
            isWorking.set(false);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // OkHttp Upload - Direct streaming (NO BASE64!)
    // ═══════════════════════════════════════════════════════════════════

    private boolean uploadFileWithOkHttp(UploadDatabaseHelper.UploadItem item) {
        Log.e(TAG, "📤 uploadFileWithOkHttp() started");
        Log.e(TAG, "   filePath: " + item.filePath);
        Log.e(TAG, "   fileName: " + item.fileName);
        Log.e(TAG, "   fileType: " + item.fileType);
        Log.e(TAG, "   apiUrl: " + item.apiUrl);

        try {
            // Support both file:// and content:// URIs
            okhttp3.RequestBody fileBody;
            long fileSize = 0;

            if (item.filePath.startsWith("content://")) {
                // content:// URI - use ContentResolver with STREAMING (no memory load!)
                Log.e(TAG, "📱 Using ContentResolver for content:// URI");
                android.net.Uri uri = android.net.Uri.parse(item.filePath);

                // ✅ FIX: Calculate ACTUAL file size by reading stream
                // MediaStore may return 0 immediately after recording!
                Log.e(TAG, "");
                Log.e(TAG, "🔍🔍🔍 STEP 1: Calculating actual file size by reading stream...");
                Log.e(TAG, "   URI: " + uri.toString());

                long startTime = System.currentTimeMillis();
                try (java.io.InputStream sizeStream = context.getContentResolver().openInputStream(uri)) {
                    if (sizeStream == null) {
                        Log.e(TAG, "❌❌❌ CRITICAL: Cannot open stream to calculate size!");
                        Log.e(TAG, "   This means the file doesn't exist or we lack permission");
                        return false;
                    }

                    Log.e(TAG, "✅ Stream opened successfully, reading file...");
                    long actualSize = 0;
                    byte[] sizeBuffer = new byte[8192];
                    int sizeRead;
                    int chunks = 0;

                    while ((sizeRead = sizeStream.read(sizeBuffer)) != -1) {
                        actualSize += sizeRead;
                        chunks++;

                        // Log every 1MB
                        if (actualSize % (1024 * 1024) == 0) {
                            Log.d(TAG, "   Read " + (actualSize / 1024.0 / 1024.0) + " MB so far...");
                        }
                    }

                    long calcTime = System.currentTimeMillis() - startTime;
                    fileSize = actualSize;

                    Log.e(TAG, "");
                    Log.e(TAG, "✅✅✅ File size calculation COMPLETE:");
                    Log.e(TAG, "   Size: " + fileSize + " bytes");
                    Log.e(TAG, "   Size (MB): " + (fileSize / 1024.0 / 1024.0));
                    Log.e(TAG, "   Chunks read: " + chunks);
                    Log.e(TAG, "   Time taken: " + calcTime + " ms");
                    Log.e(TAG, "");

                    if (fileSize == 0) {
                        Log.e(TAG, "❌❌❌ CRITICAL ERROR: File is EMPTY (0 bytes)!");
                        Log.e(TAG, "   Cannot upload empty file!");
                        Log.e(TAG, "   This will cause 'expected 0 bytes but received X' error");
                        return false;
                    }

                    if (fileSize < 1024) {
                        Log.w(TAG, "⚠️⚠️⚠️ WARNING: File is very small (" + fileSize + " bytes)");
                        Log.w(TAG, "   This might indicate an incomplete recording");
                    }
                } catch (Exception e) {
                    Log.e(TAG, "❌ Error calculating file size: " + e.getMessage());
                    return false;
                }

                // Make final copy for use in anonymous inner class
                final long finalFileSize = fileSize;
                final android.net.Uri finalUri = uri;

                Log.e(TAG, "");
                Log.e(TAG, "🔍🔍🔍 STEP 2: Creating OkHttp RequestBody with calculated size...");
                Log.e(TAG, "   contentLength() will return: " + finalFileSize + " bytes");
                Log.e(TAG, "   This is what OkHttp will send as Content-Length header");
                Log.e(TAG, "");

                // Create streaming RequestBody (NO memory loading!)
                fileBody = new okhttp3.RequestBody() {
                    @Override
                    public okhttp3.MediaType contentType() {
                        return okhttp3.MediaType.parse(item.fileType);
                    }

                    @Override
                    public long contentLength() {
                        Log.e(TAG, "📏 contentLength() called - returning: " + finalFileSize);
                        return finalFileSize;
                    }

                    @Override
                    public void writeTo(okio.BufferedSink sink) throws java.io.IOException {
                        Log.e(TAG, "");
                        Log.e(TAG, "🔍🔍🔍 STEP 3: writeTo() called - Starting to stream file to server...");
                        Log.e(TAG, "   Opening second stream for upload...");

                        long writeStartTime = System.currentTimeMillis();

                        try (java.io.InputStream inputStream = context.getContentResolver().openInputStream(finalUri)) {
                            if (inputStream == null) {
                                Log.e(TAG, "❌❌❌ CRITICAL: Cannot open InputStream in writeTo()!");
                                throw new java.io.IOException("Failed to open InputStream from URI");
                            }

                            Log.e(TAG, "✅ Stream opened in writeTo(), beginning upload...");

                            // Stream file in chunks (8KB buffer)
                            byte[] buffer = new byte[8192];
                            int bytesRead;
                            long totalBytesRead = 0;
                            int writeChunks = 0;

                            while ((bytesRead = inputStream.read(buffer)) != -1) {
                                sink.write(buffer, 0, bytesRead);
                                totalBytesRead += bytesRead;
                                writeChunks++;

                                // Log progress every 1MB
                                if (totalBytesRead % (1024 * 1024) == 0) {
                                    Log.d(TAG, "   Uploaded " + (totalBytesRead / 1024.0 / 1024.0) + " MB so far...");
                                }
                            }

                            long writeTime = System.currentTimeMillis() - writeStartTime;

                            Log.e(TAG, "");
                            Log.e(TAG, "✅✅✅ writeTo() COMPLETE:");
                            Log.e(TAG, "   Total bytes written: " + totalBytesRead);
                            Log.e(TAG, "   Expected (contentLength): " + finalFileSize);
                            Log.e(TAG, "   Match: " + (totalBytesRead == finalFileSize ? "YES ✅" : "NO ❌ MISMATCH!"));
                            Log.e(TAG, "   Chunks written: " + writeChunks);
                            Log.e(TAG, "   Upload time: " + writeTime + " ms");
                            Log.e(TAG, "");

                            if (totalBytesRead != finalFileSize) {
                                Log.e(TAG, "❌❌❌ CRITICAL ERROR: Size MISMATCH!");
                                Log.e(TAG, "   This will cause 'expected X bytes but received Y' error!");
                                Log.e(TAG, "   Expected: " + finalFileSize);
                                Log.e(TAG, "   Actual: " + totalBytesRead);
                            }
                        }
                    }
                };

            } else {
                // Regular file path
                Log.e(TAG, "📁 Using File for regular path");
                File file = new File(item.filePath);

                if (!file.exists()) {
                    Log.e(TAG, "❌ File does not exist: " + item.filePath);
                    return false;
                }

                fileSize = file.length();

                // ✅ CRITICAL: Wait if file is still being written (size 0)
                if (fileSize == 0) {
                    Log.e(TAG, "⚠️ File size is 0! Waiting for file to be written...");
                    for (int i = 0; i < 10; i++) {
                        try {
                            Thread.sleep(200); // Wait 200ms
                            fileSize = file.length();
                            if (fileSize > 0) {
                                Log.e(TAG, "✅ File written after " + ((i + 1) * 200) + "ms. Size: " + (fileSize / 1024.0 / 1024.0) + " MB");
                                break;
                            }
                        } catch (InterruptedException e) {
                            Thread.currentThread().interrupt();
                            break;
                        }
                    }

                    if (fileSize == 0) {
                        Log.e(TAG, "❌❌❌ File is still 0 bytes after waiting 2 seconds!");
                        Log.e(TAG, "   This indicates the file was not saved correctly.");
                        return false;
                    }
                }

                Log.e(TAG, "📊 File size: " + (fileSize / 1024 / 1024) + " MB");

                fileBody = okhttp3.RequestBody.create(
                    file,
                    okhttp3.MediaType.parse(item.fileType)
                );
            }

            // Create OkHttp client
            okhttp3.OkHttpClient client = new okhttp3.OkHttpClient.Builder()
                .connectTimeout(60, TimeUnit.SECONDS)
                .readTimeout(180, TimeUnit.SECONDS)
                .writeTimeout(180, TimeUnit.SECONDS)
                .build();

            // Create multipart body
            okhttp3.MultipartBody requestBody = new okhttp3.MultipartBody.Builder()
                .setType(okhttp3.MultipartBody.FORM)
                .addFormDataPart("files[]", item.fileName, fileBody)
                .addFormDataPart("record_number", String.valueOf(item.photoId))
                .addFormDataPart("person_id", String.valueOf(item.photoId))
                .build();

            // Get auth token from database (NOT from SharedPreferences!)
            String authToken = item.authToken;

            // Fallback: if token not in DB, try SharedPreferences
            if (authToken == null || authToken.isEmpty()) {
                Log.e(TAG, "⚠️ authToken not in DB, trying SharedPreferences...");
                authToken = context.getSharedPreferences("capacitor", Context.MODE_PRIVATE)
                    .getString("auth_token", "");
            }

            // Build request
            okhttp3.Request.Builder requestBuilder = new okhttp3.Request.Builder()
                .url(item.apiUrl)
                .post(requestBody);

            if (!authToken.isEmpty()) {
                requestBuilder.addHeader("Authorization", "Bearer " + authToken);
                Log.e(TAG, "✅ Authorization header added");
            } else {
                Log.e(TAG, "⚠️ No authToken available!");
            }

            okhttp3.Request request = requestBuilder.build();

            // Execute request
            Log.e(TAG, "");
            Log.e(TAG, "🔍🔍🔍 STEP 4: Executing OkHttp request...");
            Log.e(TAG, "   URL: " + item.apiUrl);
            Log.e(TAG, "   Method: POST");
            Log.e(TAG, "   Content-Type: multipart/form-data");
            Log.e(TAG, "   Authorization: " + (!authToken.isEmpty() ? "Bearer [present]" : "[MISSING]"));
            Log.e(TAG, "   File parameter: files[]");
            Log.e(TAG, "   File name: " + item.fileName);
            Log.e(TAG, "   Additional params: record_number=" + item.photoId + ", person_id=" + item.photoId);
            Log.e(TAG, "");
            Log.e(TAG, "⏳ Waiting for server response...");

            long requestStartTime = System.currentTimeMillis();
            okhttp3.Response response = client.newCall(request).execute();
            long requestTime = System.currentTimeMillis() - requestStartTime;

            boolean success = response.isSuccessful();
            Log.e(TAG, "");
            Log.e(TAG, "📡 SERVER RESPONSE RECEIVED:");
            Log.e(TAG, "   Response code: " + response.code() + " " + response.message());
            Log.e(TAG, "   Success: " + (success ? "YES ✅" : "NO ❌"));
            Log.e(TAG, "   Response time: " + requestTime + " ms");
            Log.e(TAG, "");

            if (success) {
                Log.e(TAG, "✅ Server accepted the file");
            } else {
                Log.e(TAG, "❌ Server returned error: " + response.code());
                try {
                    String errorBody = response.body() != null ? response.body().string() : "No body";
                    Log.e(TAG, "   Error details: " + errorBody.substring(0, Math.min(200, errorBody.length())));
                } catch (Exception ignored) {
                }
            }

            response.close();
            return success;

        } catch (Exception e) {
            Log.e(TAG, "❌ OkHttp upload failed: " + e.getMessage(), e);
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Notification
    // ═══════════════════════════════════════════════════════════════════

    private void showUploadNotification(String title, String message, int progress) {
        try {
            android.app.NotificationManager notificationManager =
                (android.app.NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);

            // Create notification channel (required for Android 8+)
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                String channelId = "file_upload_channel";
                android.app.NotificationChannel channel = new android.app.NotificationChannel(
                    channelId,
                    "File Uploads",
                    android.app.NotificationManager.IMPORTANCE_LOW
                );
                channel.setDescription("Background file upload notifications");
                notificationManager.createNotificationChannel(channel);
            }

            // Build notification
            android.app.Notification.Builder builder;
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                builder = new android.app.Notification.Builder(context, "file_upload_channel");
            } else {
                builder = new android.app.Notification.Builder(context);
            }

            builder.setContentTitle(title)
                   .setContentText(message)
                   .setSmallIcon(android.R.drawable.stat_sys_upload)
                   .setProgress(100, progress, false)
                   .setOngoing(true);

            notificationManager.notify(9001, builder.build());

            Log.d(TAG, "📲 Notification shown: " + title);
        } catch (Exception e) {
            Log.w(TAG, "⚠️ Failed to show notification: " + e.getMessage());
        }
    }

    private void cancelUploadNotification() {
        try {
            android.app.NotificationManager notificationManager =
                (android.app.NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
            notificationManager.cancel(9001);
            Log.d(TAG, "🔕 Notification cancelled");
        } catch (Exception e) {
            Log.w(TAG, "⚠️ Failed to cancel notification: " + e.getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Worker Scheduling
    // ═══════════════════════════════════════════════════════════════════

    public static void scheduleImmediateSync(Context context) {
        // ✅ CRITICAL: Don't schedule if worker is currently working!
        if (isWorking.get()) {
            return; // Silent skip - worker already processing
        }

        // ✅ Deduplication Lock
        if (!isScheduling.compareAndSet(false, true)) {
            return; // Already scheduling
        }

        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);
            if (dbHelper.getPendingFilesCount() == 0) {
                return; // No files
            }

            Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

            OneTimeWorkRequest uploadWork = new OneTimeWorkRequest.Builder(FileSyncWorker.class)
                .setConstraints(constraints)
                .addTag("file_upload_sync")
                .build();

            WorkManager.getInstance(context)
                .enqueueUniqueWork(
                    WORK_NAME,
                    ExistingWorkPolicy.KEEP,
                    uploadWork
                );

        } finally {
            isScheduling.set(false);
        }
    }

    private void scheduleRetrySync(long delayMinutes) {
        Log.e(TAG, "⏰ Scheduling retry in " + delayMinutes + " minutes...");

        Constraints constraints = new Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build();

        OneTimeWorkRequest retryWork = new OneTimeWorkRequest.Builder(FileSyncWorker.class)
            .setConstraints(constraints)
            .setInitialDelay(delayMinutes, TimeUnit.MINUTES)
            .addTag("file_upload_sync_retry")
            .build();

        WorkManager.getInstance(context)
            .enqueueUniqueWork(
                WORK_NAME + "_retry",
                ExistingWorkPolicy.KEEP,
                retryWork
            );

        Log.e(TAG, "✅ Retry work scheduled");
    }

    private long calculateBackoffMinutes(int failureCount) {
        // Exponential backoff: 1, 2, 4, 8, 16 minutes max
        long delayMinutes = Math.min(1L << failureCount, 60);
        return delayMinutes;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * Create ForegroundInfo for Long-Running Upload Service
     *
     * CRITICAL: This prevents Android from killing the worker during
     * large file uploads. Shows a notification to the user.
     * ═══════════════════════════════════════════════════════════════════
     */
    @NonNull
    private androidx.work.ForegroundInfo createForegroundInfo() {
        String CHANNEL_ID = "file_upload_foreground";
        int NOTIFICATION_ID = 1001;

        // Create notification channel (Android 8.0+)
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
            android.app.NotificationChannel channel = new android.app.NotificationChannel(
                CHANNEL_ID,
                "رفع الملفات",
                android.app.NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("رفع الملفات للخادم");

            android.app.NotificationManager manager =
                context.getSystemService(android.app.NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }

        // Create notification
        android.app.Notification notification = new androidx.core.app.NotificationCompat.Builder(context, CHANNEL_ID)
            .setContentTitle("رفع الملفات")
            .setContentText("جارٍ رفع الملفات للخادم...")
            .setSmallIcon(android.R.drawable.stat_sys_upload)
            .setOngoing(true) // Cannot be dismissed
            .setPriority(androidx.core.app.NotificationCompat.PRIORITY_LOW)
            .build();

        Log.e(TAG, "✅ Foreground notification created - Worker won't be killed!");

        // ✅ CRITICAL FIX: Android 14+ requires foregroundServiceType
        if (android.os.Build.VERSION.SDK_INT >= 34) { // Android 14 (API 34)
            Log.e(TAG, "📱 Android 14+ detected - adding FOREGROUND_SERVICE_TYPE_DATA_SYNC");
            return new androidx.work.ForegroundInfo(
                NOTIFICATION_ID,
                notification,
                android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC
            );
        } else {
            Log.e(TAG, "📱 Android < 14 - standard ForegroundInfo");
            return new androidx.work.ForegroundInfo(NOTIFICATION_ID, notification);
        }
    }

    private boolean isNetworkAvailable() {
        try {
            android.net.ConnectivityManager cm =
                (android.net.ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);

            if (cm == null) return false;

            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.M) {
                android.net.Network network = cm.getActiveNetwork();
                if (network == null) return false;

                android.net.NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
                return capabilities != null &&
                       capabilities.hasCapability(android.net.NetworkCapabilities.NET_CAPABILITY_INTERNET);
            } else {
                android.net.NetworkInfo networkInfo = cm.getActiveNetworkInfo();
                return networkInfo != null && networkInfo.isConnected();
            }
        } catch (Exception e) {
            Log.w(TAG, "Failed to check network: " + e.getMessage());
            return false;
        }
    }
}
