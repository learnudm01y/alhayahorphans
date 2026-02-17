package com.aso.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.os.Build;
import android.util.Log;
import androidx.core.app.NotificationCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.util.List;

// ═══════════════════════════════════════════════════════════════════
// 🔌 UploadServicePlugin - Single Entry Point for File Uploads
//
// Architecture:
// JavaScript → UploadService.addFileToQueue() → FileSyncWorker orchestrator
//
// The plugin is ONLY responsible for:
// 1. Validating parameters from JavaScript
// 2. Reading the file (NO BASE64 - direct file:// URI only)
// 3. Saving to SQLite database
// 4. Scheduling FileSyncWorker (the actual upload orchestrator)
//
// ALL file uploads are handled by FileSyncWorker (Java side only)
// NO duplicate code, NO Base64, NO conflicting logic paths
// ═══════════════════════════════════════════════════════════════════

@CapacitorPlugin(name = "UploadService")
public class UploadServicePlugin extends Plugin {
    private static final String TAG = "UploadServicePlugin";
    private static final String CHANNEL_ID = "upload_notifications";
    private static final String CHANNEL_NAME = "رفع الملفات";
    private static int notificationId = 1000;

    // ✨ NEW: Static reference to plugin instance for broadcasting events
    private static UploadServicePlugin instance;

    @Override
    public void load() {
        super.load();

        // Store instance for static access
        instance = this;

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  🔌 UploadServicePlugin.load() - PLUGIN LOADED               ║");
        android.util.Log.e(TAG, "║  ✅ JavaScript can now call: UploadService.addFileToQueue()  ║");
        android.util.Log.e(TAG, "║  ✅ All uploads handled by FileSyncWorker (Java only)        ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");

        createNotificationChannel();
    }

    // ═══════════════════════════════════════════════════════════════════
    // ✨ NEW: Static method to notify JavaScript of upload status changes
    // Called by FileSyncWorker after upload success/failure
    // ═══════════════════════════════════════════════════════════════════
    public static void notifyUploadStatusChanged(long fileId, String status, String error) {
        if (instance != null) {
            android.util.Log.e(TAG, "📡 Broadcasting upload status to JavaScript: fileId=" + fileId + ", status=" + status);

            JSObject data = new JSObject();
            data.put("fileId", fileId);
            data.put("status", status);
            if (error != null) {
                data.put("error", error);
            }

            instance.notifyListeners("uploadStatusChanged", data);
        } else {
            android.util.Log.w(TAG, "⚠️ Cannot notify JavaScript - plugin instance not available");
        }
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_DEFAULT
            );
            channel.setDescription("إشعارات رفع الملفات للخادم");

            NotificationManager manager = getContext().getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
                android.util.Log.e(TAG, "✅ تم إنشاء قناة الإشعارات");
            }
        }
    }

    private void showNotification(String title, String message, boolean isSuccess) {
        try {
            NotificationManager manager = (NotificationManager) getContext().getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager == null) return;

            int icon = isSuccess ? android.R.drawable.stat_sys_upload_done : android.R.drawable.stat_sys_warning;

            NotificationCompat.Builder builder = new NotificationCompat.Builder(getContext(), CHANNEL_ID)
                .setSmallIcon(icon)
                .setContentTitle(title)
                .setContentText(message)
                .setPriority(NotificationCompat.PRIORITY_DEFAULT)
                .setAutoCancel(true);

            manager.notify(notificationId++, builder.build());
            android.util.Log.e(TAG, "📢 إشعار: " + title + " - " + message);
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ فشل إظهار الإشعار: " + e.getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 📤 MAIN ENTRY POINT - addFileToQueue()
    //
    // JavaScript Calling Pattern:
    // await UploadService.addFileToQueue({
    //   filePath: 'file:///path/to/file.jpg',  // file:// URI (NO BASE64!)
    //   fileName: 'photo.jpg',
    //   fileType: 'image/jpeg',
    //   photoId: 123,
    //   apiUrl: 'https://api.example.com/upload',
    //   authToken: 'Bearer xxx',
    //   associationName: 'Alhayah',
    //   personName: 'John Doe'
    // })
    //
    // Returns:
    // { success: true, fileId: 42, queued: true }
    // ═══════════════════════════════════════════════════════════════════

    @PluginMethod
    public void addFileToQueue(PluginCall call) {
        try {
            // Extract parameters
            String filePath = call.getString("filePath");
            String fileName = call.getString("fileName");
            String fileType = call.getString("fileType", "application/octet-stream");
            Integer photoId = call.getInt("photoId");
            String apiUrl = call.getString("apiUrl");
            Integer indexedDbId = call.getInt("indexedDbId");
            String authToken = call.getString("authToken", "");
            String associationName = call.getString("associationName");
            String personName = call.getString("personName");

            // Default values
            if (associationName == null || associationName.isEmpty()) {
                associationName = "General";
            }
            if (personName == null || personName.isEmpty()) {
                personName = "unknown";
            }

            // Validate parameters
            if (filePath == null || filePath.isEmpty() ||
                fileName == null || fileName.isEmpty() ||
                photoId == null ||
                apiUrl == null || apiUrl.isEmpty()) {

                showNotification("خطأ في الرفع", "معاملات ناقصة", false);
                call.reject("معاملات ناقصة");
                return;
            }

            // Validate file path
            String actualFilePath;
            if (filePath.startsWith("file://")) {
                actualFilePath = filePath.substring(7);
            } else if (filePath.startsWith("/")) {
                actualFilePath = filePath;
            } else {
                call.reject("مسار ملف غير صحيح");
                return;
            }

            java.io.File sourceFile = new java.io.File(actualFilePath);
            if (!sourceFile.exists()) {
                call.reject("الملف غير موجود");
                return;
            }

            long fileSize = sourceFile.length();

            // ✅ NO COPY! File already saved in Documents by CameraActivity
            // This dramatically improves save speed (from 7s to instant!)

            // Save auth token
            if (authToken != null && !authToken.isEmpty()) {
                getContext().getSharedPreferences("capacitor", android.content.Context.MODE_PRIVATE)
                        .edit()
                        .putString("auth_token", authToken)
                        .apply();
            }

            // Save to database
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            long fileId = dbHelper.addFileToQueue(
                actualFilePath,
                fileName,
                fileType,
                photoId,
                apiUrl,
                authToken,
                associationName,
                personName
            );

            if (fileId <= 0) {
                android.util.Log.e(TAG, "❌ Failed to save to database!");
                showNotification("خطأ ❌", "فشل حفظ الملف", false);
                call.reject("فشل الحفظ");
                return;
            }

            android.util.Log.e(TAG, "✅ Saved to SQLite with Queue ID: " + fileId);

            // Save IndexedDB mapping if provided
            if (indexedDbId != null && indexedDbId > 0) {
                dbHelper.saveIndexedDbMapping(fileId, indexedDbId);
                android.util.Log.e(TAG, "📊 Mapping saved: SQLite ID=" + fileId + " → IndexedDB ID=" + indexedDbId);
            }

            // ═══════════════════════════════════════════════════════════════════
            // ✅ FILE SAVED - Return immediately to JavaScript
            //
            // FileSyncWorker will handle the actual upload in background!
            // JavaScript doesn't need to wait for upload completion.
            // ═══════════════════════════════════════════════════════════════════

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("fileId", fileId);
            result.put("queued", true);
            result.put("message", "ملف في قائمة الانتظار - الرفع سيبدأ تلقائياً");
            call.resolve(result);

            android.util.Log.e(TAG, "✅ JavaScript response sent immediately");
            android.util.Log.e(TAG, "📤 Scheduling FileSyncWorker for background upload...");
            android.util.Log.e(TAG, "🔍🔍🔍 DIAGNOSTIC: About to call FileSyncWorker.scheduleImmediateSync()...");

            try {
                // ═══════════════════════════════════════════════════════════════════
                // Schedule FileSyncWorker - The Single Sync Orchestrator
                //
                // This is the ONLY place where we trigger the upload!
                // ExistingWorkPolicy.KEEP prevents duplicate workers.
                // ═══════════════════════════════════════════════════════════════════
                android.util.Log.e(TAG, "🚀🚀🚀 CALLING FileSyncWorker.scheduleImmediateSync() NOW!");
                FileSyncWorker.scheduleImmediateSync(getContext());
                android.util.Log.e(TAG, "✅ FileSyncWorker.scheduleImmediateSync() RETURNED successfully");
                android.util.Log.e(TAG, "   ✅ UniqueWork policy = KEEP (no duplicates)");
                android.util.Log.e(TAG, "   ✅ Serial processing (one file at a time)");
                android.util.Log.e(TAG, "   ✅ Circuit breaker (max 3 retries)");
            } catch (Exception workerError) {
                android.util.Log.e(TAG, "⚠️  Failed to schedule FileSyncWorker: " + workerError.getMessage());
                // Non-fatal - file is in queue, upload will work manually later
            }

            android.util.Log.e(TAG, "");
            android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
            android.util.Log.e(TAG, "║  ✅ ADD TO QUEUE COMPLETE - Waiting for FileSyncWorker        ║");
            android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
            android.util.Log.e(TAG, "");

        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ Exception: " + e.getMessage(), e);
            showNotification("خطأ في الرفع", e.getMessage(), false);
            call.reject("خطأ: " + e.getMessage());
        }
    }

    /**
     * ✨ NEW: Get upload statistics (pending, completed, failed files count)
     * Called from JavaScript to update UI
     */
    @PluginMethod
    public void getUploadStats(PluginCall call) {
        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());

            int pending = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PENDING).size();
            int uploading = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_UPLOADING).size();
            int completed = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_COMPLETED).size();
            int failed = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_FAILED).size();

            JSObject result = new JSObject();
            result.put("pending", pending);
            result.put("uploading", uploading);
            result.put("completed", completed);
            result.put("failed", failed);
            result.put("total", pending + uploading + completed + failed);

            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "❌ Error getting upload stats: " + e.getMessage());
            call.reject("Error: " + e.getMessage());
        }
    }

    /**
     * ✨ NEW: Get list of files by status
     */
    @PluginMethod
    public void getFilesByStatus(PluginCall call) {
        try {
            String status = call.getString("status", UploadDatabaseHelper.STATUS_PENDING);
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());

            List<UploadDatabaseHelper.UploadItem> items = dbHelper.getFilesByStatus(status);

            com.getcapacitor.JSArray filesArray = new com.getcapacitor.JSArray();
            for (UploadDatabaseHelper.UploadItem item : items) {
                JSObject fileObj = new JSObject();
                fileObj.put("id", item.id);
                fileObj.put("fileName", item.fileName);
                fileObj.put("fileType", item.fileType);
                fileObj.put("photoId", item.photoId);
                fileObj.put("status", item.status);
                fileObj.put("retryCount", item.retryCount);
                fileObj.put("errorMessage", item.errorMessage);
                fileObj.put("createdAt", item.createdAt);
                filesArray.put(fileObj);
            }

            JSObject result = new JSObject();
            result.put("files", filesArray);
            result.put("count", items.size());

            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "❌ Error getting files: " + e.getMessage());
            call.reject("Error: " + e.getMessage());
        }
    }

    /**
     * Utility: Format file size for display
     */
    private String formatFileSize(long bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return String.format("%.2f KB", bytes / 1024.0);
        if (bytes < 1024 * 1024 * 1024) return String.format("%.2f MB", bytes / (1024.0 * 1024.0));
        return String.format("%.2f GB", bytes / (1024.0 * 1024.0 * 1024.0));
    }
}
