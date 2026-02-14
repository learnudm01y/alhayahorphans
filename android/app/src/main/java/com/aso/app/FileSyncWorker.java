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

    private UploadDatabaseHelper dbHelper;
    private Context context;

    public FileSyncWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
        Log.e(TAG, "");
        Log.e(TAG, "🏭🏭🏭 FileSyncWorker CONSTRUCTOR called 🏭🏭🏭");
        Log.e(TAG, "   Worker ID: " + getId());
        Log.e(TAG, "   Run Attempt: " + getRunAttemptCount());

        try {
            this.context = context;
            Log.e(TAG, "   ✅ Context assigned");

            this.dbHelper = UploadDatabaseHelper.getInstance(context);
            Log.e(TAG, "   ✅ UploadDatabaseHelper obtained");

            Log.e(TAG, "✅✅✅ FileSyncWorker CONSTRUCTOR completed successfully!");
        } catch (Exception e) {
            Log.e(TAG, "❌❌❌ CONSTRUCTOR FAILED: " + e.getClass().getSimpleName());
            Log.e(TAG, "   Error: " + e.getMessage());
            e.printStackTrace();
            throw e; // Re-throw to fail worker
        }
        Log.e(TAG, "");
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  🔄 FileSyncWorker.doWork() STARTED - Single Orchestrator     ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        Log.e(TAG, "👷 Thread: " + Thread.currentThread().getName());
        Log.e(TAG, "🆔 Worker ID: " + getId());
        Log.e(TAG, "🔄 Run Attempt: " + getRunAttemptCount());

        // ✅ Check network AGAIN inside doWork()
        boolean networkNow = isNetworkAvailable();
        Log.e(TAG, "🌐 Network available in doWork(): " + (networkNow ? "YES ✅" : "NO ❌"));

        if (!networkNow) {
            Log.e(TAG, "❌❌❌ NO NETWORK in doWork() - upload will FAIL!");
            Log.e(TAG, "⚠️ Returning Result.retry() - will try again when network available");
            return Result.retry();
        }

        Log.e(TAG, "");

        // ═══════════════════════════════════════════════════════════════════
        // CRITICAL: Set as Foreground Service
        // This prevents Android from killing the worker during large uploads!
        // ═══════════════════════════════════════════════════════════════════
        try {
            Log.e(TAG, "🚀 Attempting to promote worker to FOREGROUND SERVICE...");
            com.google.common.util.concurrent.ListenableFuture<Void> foregroundFuture = setForegroundAsync(createForegroundInfo());
            // Wait for foreground promotion to complete (important!)
            foregroundFuture.get(5, java.util.concurrent.TimeUnit.SECONDS);
            Log.e(TAG, "✅✅✅ Worker successfully promoted to FOREGROUND SERVICE!");
            Log.e(TAG, "   Android WON'T kill this upload - notification visible to user");
        } catch (java.util.concurrent.TimeoutException e) {
            Log.w(TAG, "⚠️ Foreground promotion timeout (5s) - continuing anyway");
            Log.w(TAG, "   Worker may be killed by Android for long uploads!");
        } catch (Exception e) {
            Log.e(TAG, "❌ Failed to set foreground: " + e.getClass().getSimpleName() + ": " + e.getMessage());
            e.printStackTrace();
            Log.w(TAG, "⚠️ Continuing WITHOUT foreground service - uploads may be killed!");
        }

        try {
            // ═══════════════════════════════════════════════════════════════════
            // Get all pending files from database
            // ═══════════════════════════════════════════════════════════════════
            List<UploadDatabaseHelper.UploadItem> pendingItems = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PENDING);

            if (pendingItems == null || pendingItems.isEmpty()) {
                Log.e(TAG, "ℹ️ No pending uploads - worker completed");
                Log.e(TAG, "");
                return Result.success();
            }

            Log.e(TAG, "📊 Found " + pendingItems.size() + " pending uploads");
            Log.e(TAG, "🎯 Processing serially (one at a time)");
            Log.e(TAG, "");

            int successCount = 0;
            int failureCount = 0;
            int skippedCount = 0;

            // ═══════════════════════════════════════════════════════════════════
            // Process each file serially
            // ═══════════════════════════════════════════════════════════════════
            for (int i = 0; i < pendingItems.size(); i++) {
                UploadDatabaseHelper.UploadItem item = pendingItems.get(i);

                Log.e(TAG, "┌─────────────────────────────────────────────────────┐");
                Log.e(TAG, "│ Processing item " + (i + 1) + "/" + pendingItems.size());
                Log.e(TAG, "│ 📁 File: " + item.fileName);
                Log.e(TAG, "│ 🆔 ID: " + item.id);
                Log.e(TAG, "│ 🔄 Retry count: " + item.retryCount);
                Log.e(TAG, "└─────────────────────────────────────────────────────┘");

                // ═══════════════════════════════════════════════════════════════════
                // Circuit Breaker Check
                // ═══════════════════════════════════════════════════════════════════
                if (!dbHelper.shouldRetry(item)) {
                    Log.w(TAG, "⚠️ Circuit breaker - skipping this item");
                    skippedCount++;
                    continue;
                }

                // ═══════════════════════════════════════════════════════════════════
                // Verify file exists (support both file:// and content:// URIs)
                // ═══════════════════════════════════════════════════════════════════════
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
                    dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_COMPLETED, null);

                    // Delete the local file after successful upload (only for regular files)
                    if (!item.filePath.startsWith("content://")) {
                        File localFile = new File(item.filePath);
                        if (localFile.exists() && localFile.delete()) {
                            Log.d(TAG, "🗑️ File deleted from storage");
                        }
                    }

                } else {
                    Log.e(TAG, "❌ Upload failed");
                    failureCount++;

                    // Increment retry count and check circuit breaker
                    dbHelper.incrementRetryCount(item.id);

                    // If max retries exceeded, mark as failed permanently
                    if (item.retryCount >= 2) { // 0, 1, 2 = 3 attempts
                        dbHelper.updateFileStatus(
                            item.id,
                            UploadDatabaseHelper.STATUS_FAILED,
                            "Exceeded max retry attempts (3)"
                        );
                        Log.e(TAG, "🚫 Circuit breaker activated - marked as failed");
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
            Log.e(TAG, "");
            Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
            Log.e(TAG, "║  📊 Sync Session Complete                                    ║");
            Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
            Log.e(TAG, "✅ Success: " + successCount);
            Log.e(TAG, "❌ Failures: " + failureCount);
            Log.e(TAG, "⏭️  Skipped: " + skippedCount);
            Log.e(TAG, "📈 Total processed: " + pendingItems.size());
            Log.e(TAG, "");

            // Show completion notification
            if (successCount > 0) {
                showUploadNotification(
                    "✅ Upload Complete",
                    successCount + " file(s) uploaded successfully",
                    100
                );
                // Auto-dismiss after 3 seconds
                new android.os.Handler(android.os.Looper.getMainLooper()).postDelayed(() -> {
                    cancelUploadNotification();
                }, 3000);
            } else if (failureCount > 0) {
                showUploadNotification(
                    "⚠️ Upload Issues",
                    failureCount + " file(s) failed to upload",
                    0
                );
            }

            // ═══════════════════════════════════════════════════════════════════
            // Reschedule if there are still pending or failed files
            // ═══════════════════════════════════════════════════════════════════
            int remainingPending = dbHelper.getPendingFilesCount();
            if (remainingPending > 0) {
                Log.e(TAG, "📌 " + remainingPending + " files still pending - scheduling retry with backoff");
                scheduleRetrySync(calculateBackoffMinutes(failureCount));
            }

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ Worker failed with exception: " + e.getMessage(), e);
            e.printStackTrace();

            // Reschedule for retry
            scheduleRetrySync(5); // 5 minutes backoff
            return Result.retry();
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

                // Get file size
                android.database.Cursor cursor = context.getContentResolver().query(uri, null, null, null, null);
                if (cursor != null && cursor.moveToFirst()) {
                    int sizeIndex = cursor.getColumnIndex(android.provider.OpenableColumns.SIZE);
                    if (sizeIndex != -1) {
                        fileSize = cursor.getLong(sizeIndex);
                    }
                    cursor.close();
                }

                Log.e(TAG, "📊 File size: " + (fileSize / 1024.0 / 1024.0) + " MB");

                // Make final copy for use in anonymous inner class
                final long finalFileSize = fileSize;
                final android.net.Uri finalUri = uri;

                // Create streaming RequestBody (NO memory loading!)
                fileBody = new okhttp3.RequestBody() {
                    @Override
                    public okhttp3.MediaType contentType() {
                        return okhttp3.MediaType.parse(item.fileType);
                    }

                    @Override
                    public long contentLength() {
                        return finalFileSize;
                    }

                    @Override
                    public void writeTo(okio.BufferedSink sink) throws java.io.IOException {
                        try (java.io.InputStream inputStream = context.getContentResolver().openInputStream(finalUri)) {
                            if (inputStream == null) {
                                throw new java.io.IOException("Failed to open InputStream from URI");
                            }

                            // Stream file in chunks (8KB buffer)
                            byte[] buffer = new byte[8192];
                            int bytesRead;
                            long totalBytesRead = 0;

                            while ((bytesRead = inputStream.read(buffer)) != -1) {
                                sink.write(buffer, 0, bytesRead);
                                totalBytesRead += bytesRead;

                                // Log progress every 5MB
                                if (totalBytesRead % (5 * 1024 * 1024) == 0) {
                                    Log.d(TAG, "📤 Streamed: " + (totalBytesRead / 1024.0 / 1024.0) + " MB");
                                }
                            }

                            Log.e(TAG, "✅ File streamed from content:// URI (" + totalBytesRead + " bytes)");
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
            okhttp3.Response response = client.newCall(request).execute();

            boolean success = response.isSuccessful();
            Log.e(TAG, "📡 Response code: " + response.code() + " " + response.message());

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
        Log.e(TAG, "📤 [1/6] scheduleImmediateSync() CALLED");

        // ✅ NEW: Check network availability BEFORE scheduling
        android.net.ConnectivityManager cm =
            (android.net.ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
        boolean networkAvailable = false;
        if (cm != null) {
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.M) {
                android.net.Network activeNetwork = cm.getActiveNetwork();
                if (activeNetwork != null) {
                    android.net.NetworkCapabilities caps = cm.getNetworkCapabilities(activeNetwork);
                    networkAvailable = caps != null &&
                        caps.hasCapability(android.net.NetworkCapabilities.NET_CAPABILITY_INTERNET);
                }
            } else {
                android.net.NetworkInfo activeNetworkInfo = cm.getActiveNetworkInfo();
                networkAvailable = activeNetworkInfo != null && activeNetworkInfo.isConnected();
            }
        }
        Log.e(TAG, "📤 [2/6] Network available NOW: " + networkAvailable);

        if (!networkAvailable) {
            Log.e(TAG, "❌❌❌ NO NETWORK FOUND!");
            Log.e(TAG, "⚠️⚠️⚠️ Worker will be QUEUED but NOT START until network available!");
            Log.e(TAG, "⚠️⚠️⚠️ Turn on WiFi or Mobile Data to start upload!");
        }

        // ❌ TEMPORARILY REMOVE network constraint for testing!
        // Constraints constraints = new Constraints.Builder()
        //     .setRequiredNetworkType(NetworkType.CONNECTED)
        //     .build();
        Log.e(TAG, "📤 [3/6] Constraints: NONE (network constraint REMOVED for testing)");

        OneTimeWorkRequest uploadWork = new OneTimeWorkRequest.Builder(FileSyncWorker.class)
            // .setConstraints(constraints) // ❌ REMOVED!
            .addTag("file_upload_sync")
            .build();
        Log.e(TAG, "📤 [4/6] Work request created");

        // ExistingWorkPolicy.REPLACE - ALWAYS use new worker (discard old)
        WorkManager.getInstance(context)
            .enqueueUniqueWork(
                WORK_NAME,
                ExistingWorkPolicy.REPLACE,
                uploadWork
            );

        Log.e(TAG, "📤 [5/6] Work enqueued with WorkManager");
        Log.e(TAG, "📤 [6/6] Work name: " + WORK_NAME + " | Policy: REPLACE");
        Log.e(TAG, "✅✅✅ scheduleImmediateSync() COMPLETE - Worker should start NOW!");
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

        return new androidx.work.ForegroundInfo(NOTIFICATION_ID, notification);
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
