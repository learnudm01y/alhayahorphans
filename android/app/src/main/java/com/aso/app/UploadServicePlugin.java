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

@CapacitorPlugin(name = "UploadService")
public class UploadServicePlugin extends Plugin {
    private static final String TAG = "UploadServicePlugin";
    private static final String CHANNEL_ID = "upload_notifications";
    private static final String CHANNEL_NAME = "رفع الملفات";
    private static int notificationId = 1000;

    private static UploadServicePlugin instance;

    @Override
    public void load() {
        super.load();
        instance = this;
        createNotificationChannel();
    }

    public static void notifyUploadStatusChanged(long fileId, String status, String error) {
        if (instance != null) {
            JSObject data = new JSObject();
            data.put("fileId", fileId);
            data.put("status", status);
            if (error != null) {
                data.put("error", error);
            }
            instance.notifyListeners("uploadStatusChanged", data);
        }
    }

    /**
     * [SmartMedia] إشعار تغيّر حالة المعالجة المحلية للواجهة (قيد التجهيز /
     * تم التحسين / فشل). لا يُرسل أي بيانات حساسة.
     */
    public static void notifyMediaStatusChanged(long fileId, String stage, String message,
                                                long originalSize, long processedSize, double ratio) {
        if (instance != null) {
            JSObject data = new JSObject();
            data.put("fileId", fileId);
            data.put("stage", stage);
            data.put("message", message);
            data.put("originalSize", originalSize);
            data.put("processedSize", processedSize);
            data.put("ratio", ratio);
            instance.notifyListeners("mediaStatusChanged", data);
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
        } catch (Exception e) {
            Log.e(TAG, "فشل إظهار الإشعار: " + e.getMessage());
        }
    }

    @PluginMethod
    public void addFileToQueue(PluginCall call) {
        try {
            String filePath = call.getString("filePath");
            String fileName = call.getString("fileName");
            String fileType = call.getString("fileType", "application/octet-stream");
            Integer photoId = call.getInt("photoId"); // This is actually the sponsorship ID
            String apiUrl = call.getString("apiUrl");
            Integer indexedDbId = call.getInt("indexedDbId");
            String authToken = call.getString("authToken", "");
            
            // Try to lookup from SponsorshipsDatabaseHelper
            String associationName = "General";
            String personName = "unknown";
            if (photoId != null && photoId > 0) {
                com.aso.app.SponsorshipsDatabaseHelper sponsorshipsDb = com.aso.app.SponsorshipsDatabaseHelper.getInstance(getContext());
                String spDataStr = sponsorshipsDb.getSponsorship(photoId);
                if (spDataStr != null && !spDataStr.isEmpty()) {
                    org.json.JSONObject spData = new org.json.JSONObject(spDataStr);
                    associationName = spData.optString("association_name", "General");
                    String orphanName = spData.optString("orphan_name", "");
                    if (orphanName.isEmpty()) {
                        orphanName = spData.optString("person_name", "unknown");
                    }
                    personName = orphanName;
                }
            }

            if (filePath == null || filePath.isEmpty() ||
                fileName == null || fileName.isEmpty() ||
                photoId == null ||
                apiUrl == null || apiUrl.isEmpty()) {

                showNotification("خطأ في الرفع", "معاملات ناقصة", false);
                call.reject("معاملات ناقصة");
                return;
            }

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

            if (authToken != null && !authToken.isEmpty()) {
                getContext().getSharedPreferences("capacitor", android.content.Context.MODE_PRIVATE)
                        .edit()
                        .putString("auth_token", authToken)
                        .apply();
            }

            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            long fileId = SmartMediaProcessor.queueForUpload(
                getContext(),
                dbHelper,
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
                showNotification("خطأ ❌", "فشل حفظ الملف", false);
                call.reject("فشل الحفظ");
                return;
            }

            if (indexedDbId != null && indexedDbId > 0) {
                dbHelper.saveIndexedDbMapping(fileId, indexedDbId);
            }

            // تحديد رسالة الحالة: قيد المعالجة المحلية أم في طابور الرفع.
            UploadDatabaseHelper.UploadItem queuedItem = dbHelper.getFileById(fileId);
            boolean isProcessing = queuedItem != null
                    && UploadDatabaseHelper.STATUS_PROCESSING.equals(queuedItem.status);

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("fileId", fileId);
            result.put("queued", true);
            result.put("status", queuedItem != null ? queuedItem.status : "pending");
            result.put("message", isProcessing
                    ? "جاري تجهيز الملف (الضغط الذكي) - سيُرفع تلقائياً"
                    : "ملف في قائمة الانتظار - الرفع سيبدأ تلقائياً");
            call.resolve(result);

            if (!isProcessing) {
                UploadTaskScheduler.getInstance(getContext()).startImmediateUpload();
            }

        } catch (Exception e) {
            showNotification("خطأ في الرفع", e.getMessage(), false);
            call.reject("خطأ: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getUploadStats(PluginCall call) {
        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());

            int pending = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PENDING).size();
            int processing = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PROCESSING).size();
            int uploadPending = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_UPLOAD_PENDING).size();
            int uploading = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_UPLOADING).size();
            int completed = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_COMPLETED).size();
            int failed = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_FAILED).size();
            int processingServer = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PROCESSING_SERVER).size();

            JSObject result = new JSObject();
            result.put("pending", pending);
            result.put("processing", processing);
            result.put("upload_pending", uploadPending);
            result.put("uploading", uploading);
            result.put("completed", completed);
            result.put("failed", failed);
            result.put("processing_server", processingServer);
            result.put("total", pending + processing + uploadPending + uploading + completed + failed + processingServer);

            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Error getting upload stats: " + e.getMessage());
            call.reject("Error: " + e.getMessage());
        }
    }

    /**
     * [SmartMedia] إحصائيات الضغط الذكي (اختيارية): عدد الملفات المحسّنة
     * وإجمالي الحجم قبل/بعد والتوفير.
     */
    @PluginMethod
    public void getCompressionStats(PluginCall call) {
        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            List<UploadDatabaseHelper.UploadItem> processedItems =
                    dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_COMPLETED);

            int optimizedCount = 0;
            long before = 0;
            long after = 0;
            for (UploadDatabaseHelper.UploadItem item : processedItems) {
                if (item.compressionEnabled && item.originalSize > 0) {
                    optimizedCount++;
                    before += item.originalSize;
                    after += item.processedSize > 0 ? item.processedSize : item.originalSize;
                }
            }

            JSObject result = new JSObject();
            result.put("optimizedCount", optimizedCount);
            result.put("originalTotal", before);
            result.put("processedTotal", after);
            result.put("savedTotal", Math.max(0, before - after));
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Error getting compression stats: " + e.getMessage());
            call.reject("Error: " + e.getMessage());
        }
    }

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
                fileObj.put("associationName", item.associationName);
                fileObj.put("personName", item.personName);
                fileObj.put("filePath", item.filePath);
                fileObj.put("compressionEnabled", item.compressionEnabled);
                fileObj.put("compressionType", item.compressionType);
                fileObj.put("compressionRatio", item.compressionRatio);
                fileObj.put("originalSize", item.originalSize);
                fileObj.put("processedSize", item.processedSize);
                filesArray.put(fileObj);
            }

            JSObject result = new JSObject();
            result.put("files", filesArray);
            result.put("count", items.size());

            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Error getting files: " + e.getMessage());
            call.reject("Error: " + e.getMessage());
        }
    }

    private String formatFileSize(long bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return String.format("%.2f KB", bytes / 1024.0);
        if (bytes < 1024 * 1024 * 1024) return String.format("%.2f MB", bytes / (1024.0 * 1024.0));
        return String.format("%.2f GB", bytes / (1024.0 * 1024.0 * 1024.0));
    }

    @PluginMethod
    public void resetProcessingFiles(PluginCall call) {
        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            
            // Get all processing files
            List<UploadDatabaseHelper.UploadItem> processingItems = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PROCESSING_SERVER);
            
            for (UploadDatabaseHelper.UploadItem item : processingItems) {
                // Reset to pending
                dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_PENDING, "");
            }
            
            // Trigger the worker to start immediately
            UploadTaskScheduler.getInstance(getContext()).startImmediateUpload();

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("restoredCount", processingItems.size());
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Error resetting processing files: " + e.getMessage());
            call.reject("Error: " + e.getMessage());
        }
    }

    @PluginMethod
    public void retryFailedUploads(PluginCall call) {
        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            
            // Get all failed files
            List<UploadDatabaseHelper.UploadItem> failedItems = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_FAILED);
            
            for (UploadDatabaseHelper.UploadItem item : failedItems) {
                // Reset to pending and clear error message
                dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_PENDING, "");
                // Reset retry count to 0 directly using execSQL
                dbHelper.getWritableDatabase().execSQL("UPDATE upload_queue SET retry_count = 0 WHERE id = " + item.id);
            }
            
            // Trigger the worker to start immediately
            UploadTaskScheduler.getInstance(getContext()).startImmediateUpload();

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("restoredCount", failedItems.size());
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Error retrying failed uploads: " + e.getMessage());
            call.reject("Error: " + e.getMessage());
        }
    }

    @PluginMethod
    public void updateFileStatus(PluginCall call) {
        String fileName = call.getString("fileName");
        String status = call.getString("status");
        
        if (fileName == null || status == null) {
            call.reject("fileName and status are required");
            return;
        }

        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            UploadDatabaseHelper.UploadItem item = dbHelper.getFileByName(fileName);
            
            if (item != null) {
                dbHelper.updateFileStatus(item.id, status, null);
                
                JSObject result = new JSObject();
                result.put("success", true);
                call.resolve(result);
            } else {
                call.reject("File not found: " + fileName);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error updating file status: " + e.getMessage());
            call.reject("Error updating file status: " + e.getMessage());
        }
    }

    @PluginMethod
    public void resolveContentUrl(PluginCall call) {
        String uriString = call.getString("uri");
        if (uriString == null) {
            call.reject("URI is required");
            return;
        }

        if (!uriString.startsWith("content://")) {
            JSObject result = new JSObject();
            result.put("path", uriString);
            call.resolve(result);
            return;
        }

        try {
            android.net.Uri uri = android.net.Uri.parse(uriString);
            java.io.InputStream is = getContext().getContentResolver().openInputStream(uri);
            if (is == null) {
                call.reject("Cannot open InputStream");
                return;
            }

            java.io.File cacheDir = getContext().getCacheDir();
            String fileName = "preview_" + System.currentTimeMillis();
            
            android.database.Cursor cursor = getContext().getContentResolver().query(uri, null, null, null, null);
            if (cursor != null && cursor.moveToFirst()) {
                int nameIndex = cursor.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME);
                if (nameIndex != -1) {
                    String dbFileName = cursor.getString(nameIndex);
                    if (dbFileName != null && !dbFileName.isEmpty()) {
                        fileName = System.currentTimeMillis() + "_" + dbFileName;
                    }
                }
                cursor.close();
            }

            String mimeType = getContext().getContentResolver().getType(uri);
            if (!fileName.contains(".")) {
                if ("image/jpeg".equals(mimeType) || "image/jpg".equals(mimeType)) fileName += ".jpg";
                else if ("image/png".equals(mimeType)) fileName += ".png";
                else if ("video/mp4".equals(mimeType)) fileName += ".mp4";
                else fileName += ".jpg"; // fallback
            }

            java.io.File tempFile = new java.io.File(cacheDir, fileName);
            java.io.OutputStream os = new java.io.FileOutputStream(tempFile);
            byte[] buffer = new byte[8192];
            int bytesRead;
            while ((bytesRead = is.read(buffer)) != -1) {
                os.write(buffer, 0, bytesRead);
            }
            os.close();
            is.close();

            JSObject result = new JSObject();
            result.put("path", tempFile.getAbsolutePath());
            call.resolve(result);

        } catch (Exception e) {
            call.reject("Exception resolving URI: " + e.getMessage());
        }
    }
}
