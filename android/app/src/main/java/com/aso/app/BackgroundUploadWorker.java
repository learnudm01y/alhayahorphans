package com.aso.app;

import android.content.Context;
import android.util.Log;
import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;
import androidx.work.ForegroundInfo;
import androidx.work.Data;
import androidx.core.app.NotificationCompat;
import android.app.NotificationManager;
import android.app.NotificationChannel;

import java.io.File;
import java.io.FileInputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.List;

/**
 * Worker للعمل في الخلفية لرفع الملفات
 * يعمل بشكل مستقل عن حالة التطبيق والصفحات
 */
public class BackgroundUploadWorker extends Worker {
    private static final String TAG = "BackgroundUploadWorker";
    private static final int MAX_RETRY_COUNT = 3;
    private static final String BOUNDARY = "----WebKitFormBoundary7MA4YWxkTrZu0gW";
    private static final int CHUNK_SIZE = 8192;

    private UploadDatabaseHelper dbHelper;
    private Context context;

    public BackgroundUploadWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
        this.context = context;
        this.dbHelper = UploadDatabaseHelper.getInstance(context);
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.d(TAG, "");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🚀🚀🚀 بدء BackgroundUploadWorker (DEPRECATED) 🚀🚀🚀");
        Log.e(TAG, "⚠️⚠️⚠️ WARNING: This worker does NOT support content:// URIs!");
        Log.e(TAG, "⚠️⚠️⚠️ Should use FileSyncWorker instead for Native Camera!");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "⏰ الوقت: " + System.currentTimeMillis());
        Log.d(TAG, "🔧 Worker ID: " + getId());
        Log.d(TAG, "🔄 Run Attempt: " + getRunAttemptCount());
        Log.d(TAG, "⚡ Expedited: " + (android.os.Build.VERSION.SDK_INT >= 31 && getForegroundInfoAsync().isDone()));
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // تفعيل Foreground Service للعمل المستمر
        try {
            setForegroundAsync(getForegroundInfo());
            Log.d(TAG, "🛡️ تم تفعيل Foreground Service - الرفع لن يتوقف");
        } catch (Exception e) {
            Log.w(TAG, "⚠️ لم يتم تفعيل Foreground Service: " + e.getMessage());
        }

        try {
            // إعادة تعيين الملفات التي كانت قيد الرفع
            dbHelper.resetUploadingFiles();

            int pendingCount = dbHelper.getPendingFilesCount();
            Log.d(TAG, "📊 عدد الملفات المعلقة: " + pendingCount);
            Log.d(TAG, "");

            if (pendingCount == 0) {
                Log.d(TAG, "⚠️ لا توجد ملفات للرفع - إنهاء Worker");
                return Result.success();
            }

            // معالجة الملفات المعلقة
            boolean hasMoreFiles = true;
            int processedCount = 0;
            int successCount = 0;
            int failedCount = 0;

            while (hasMoreFiles) {
                // الحصول على الملف التالي
                UploadDatabaseHelper.UploadItem nextFile = dbHelper.getNextPendingFile();

                if (nextFile == null) {
                    hasMoreFiles = false;
                    Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                    Log.d(TAG, "✅✅✅ لا توجد ملفات معلقة أخرى ✅✅✅");
                    Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                    break;
                }

                // التحقق من الإيقاف
                if (isStopped()) {
                    Log.d(TAG, "⚠️ تم إيقاف Worker - إعادة الملفات إلى معلق");
                    dbHelper.resetUploadingFiles();
                    return Result.retry();
                }

                processedCount++;
                Log.d(TAG, "");
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log.d(TAG, "📤 معالجة الملف #" + processedCount);
                Log.d(TAG, "   ├─ الاسم: " + nextFile.fileName);
                Log.d(TAG, "   ├─ المعرف: " + nextFile.id);
                Log.d(TAG, "   ├─ Photo ID: " + nextFile.photoId);
                Log.d(TAG, "   └─ API: " + nextFile.apiUrl);
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

                // تحديث الحالة إلى قيد الرفع
                dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_UPLOADING, null);

                // تحديث الإشعار
                updateNotification(nextFile.fileName, dbHelper.getPendingFilesCount());

                // محاولة رفع الملف
                boolean uploadSuccess = uploadFile(nextFile);

                if (uploadSuccess) {
                    // نجح الرفع
                    dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                    successCount++;
                    Log.d(TAG, "");
                    Log.d(TAG, "✅✅✅ تم رفع الملف بنجاح! ✅✅✅");
                    Log.d(TAG, "   ├─ الملف: " + nextFile.fileName);
                    Log.d(TAG, "   ├─ النجاحات: " + successCount);
                    Log.d(TAG, "   └─ المتبقي: " + (dbHelper.getPendingFilesCount() - 1));
                    Log.d(TAG, "");

                    // حذف الملف من القرص المحلي (اختياري)
                    deleteLocalFile(nextFile.filePath);

                } else {
                    // فشل الرفع
                    dbHelper.incrementRetryCount(nextFile.id);

                    if (nextFile.retryCount >= MAX_RETRY_COUNT) {
                        // تجاوز الحد الأقصى للمحاولات
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_FAILED,
                                                 "تجاوز الحد الأقصى للمحاولات");
                        failedCount++;
                        Log.e(TAG, "");
                        Log.e(TAG, "❌❌❌ فشل رفع الملف بعد " + MAX_RETRY_COUNT + " محاولات ❌❌❌");
                        Log.e(TAG, "   ├─ الملف: " + nextFile.fileName);
                        Log.e(TAG, "   └─ الفشل: " + failedCount);
                        Log.e(TAG, "");
                    } else {
                        // إعادة إلى معلق للمحاولة مرة أخرى
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, null);
                        Log.w(TAG, "⚠️ فشل رفع الملف - محاولة " + (nextFile.retryCount + 1) + "/" + MAX_RETRY_COUNT + ": " + nextFile.fileName);
                    }
                }

                // توقف قصير بين الملفات
                try {
                    Thread.sleep(500);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    return Result.retry();
                }
            }

            // حذف الملفات المكتملة من قاعدة البيانات
            dbHelper.deleteCompletedFiles();

            Log.d(TAG, "");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "📊 ملخص العملية:");
            Log.d(TAG, "   ├─ معالج: " + processedCount);
            Log.d(TAG, "   ├─ نجح: " + successCount + " ✅");
            Log.d(TAG, "   ├─ فشل: " + failedCount + " ❌");
            Log.d(TAG, "   └─ متبقي: " + dbHelper.getPendingFilesCount());
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "");
            dbHelper.deleteCompletedFiles();

            Log.d(TAG, "📊 ملخص العملية - معالج: " + processedCount + ", نجح: " + successCount + ", فشل: " + failedCount);

            // التحقق من وجود ملفات متبقية
            int remainingFiles = dbHelper.getPendingFilesCount();
            if (remainingFiles > 0) {
                Log.d(TAG, "⚠️ يوجد " + remainingFiles + " ملفات متبقية - ستتم المحاولة لاحقاً");
                return Result.retry();
            }

            // إيقاف الخدمة المقدمة
            stopForegroundService();

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ أثناء معالجة الملفات: " + e.getMessage(), e);
            dbHelper.resetUploadingFiles();
            return Result.retry();
        }
    }

    /**
     * رفع الملف إلى السيرفر - نسخة محسّنة مع STREAMING لمنع OOM
     * ✅ يستخدم BufferedInputStream للملفات الكبيرة
     * ✅ يقرأ البيانات على شكل chunks بدلاً من تحميلها كاملة
     * ✅ يدعم الملفات حتى 500 MB بدون مشاكل ذاكرة
     */
    private boolean uploadFile(UploadDatabaseHelper.UploadItem item) {
        HttpURLConnection connection = null;
        java.io.InputStream inputStream = null;
        OutputStream outputStream = null;

        try {
            long fileSize = 0;
            String boundary = BOUNDARY + System.currentTimeMillis();

            // 🔍 تحديد نوع المصدر وحجم الملف
            if (item.filePath.startsWith("data:")) {
                // ⚠️ Base64 - للملفات الصغيرة فقط (< 10 MB)
                Log.d(TAG, "📦 معالجة Base64 للملف: " + item.fileName);
                String[] parts = item.filePath.split(",");
                if (parts.length != 2) {
                    Log.e(TAG, "❌ صيغة Base64 غير صحيحة");
                    return false;
                }

                // تقدير حجم الملف من Base64
                fileSize = (parts[1].length() * 3L) / 4L;
                Log.d(TAG, "📊 حجم الملف المقدّر: " + formatFileSize(fileSize));

                if (fileSize > 10 * 1024 * 1024) {
                    Log.e(TAG, "❌ Base64 كبير جداً (" + formatFileSize(fileSize) + ")!");
                    Log.e(TAG, "💡 نصيحة: احفظ الملف فيزيائياً بدلاً من Base64");
                    return false;
                }

                inputStream = new java.io.ByteArrayInputStream(
                    android.util.Base64.decode(parts[1], android.util.Base64.DEFAULT)
                );

            } else if (item.filePath.startsWith("content://")) {
                // ✅ content:// URI - استخدام ContentResolver
                Log.d(TAG, "📱 معالجة content:// URI: " + item.filePath);
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

                Log.d(TAG, "📊 حجم الملف: " + formatFileSize(fileSize));

                // Open stream through ContentResolver
                inputStream = new java.io.BufferedInputStream(
                    context.getContentResolver().openInputStream(uri),
                    CHUNK_SIZE
                );

                if (inputStream == null) {
                    Log.e(TAG, "❌ فشل فتح content:// URI");
                    return false;
                }

            } else {
                // ✅ ملف فيزيائي - استخدام streaming للملفات الكبيرة
                File file = new File(context.getFilesDir(), item.filePath);
                if (!file.exists()) {
                    Log.e(TAG, "❌ الملف غير موجود: " + file.getAbsolutePath());
                    return false;
                }

                fileSize = file.length();
                Log.d(TAG, "✅ ملف موجود: " + file.getAbsolutePath());
                Log.d(TAG, "📊 حجم الملف: " + formatFileSize(fileSize));

                // 🚀 استخدام BufferedInputStream للملفات الكبيرة (أفضل للذاكرة)
                inputStream = new java.io.BufferedInputStream(
                    new FileInputStream(file),
                    CHUNK_SIZE // استخدام CHUNK_SIZE من الفصل
                );
            }

            // 🌐 إنشاء الاتصال HTTP
            Log.d(TAG, "🌐 إنشاء اتصال HTTP: " + item.apiUrl);
            URL url = new URL(item.apiUrl);
            connection = (HttpURLConnection) url.openConnection();
            connection.setDoOutput(true);
            connection.setDoInput(true);
            connection.setRequestMethod("POST");
            connection.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);
            connection.setRequestProperty("Accept", "application/json");

            // ⏱️ تمديد المهلة للملفات الكبيرة
            connection.setConnectTimeout(60000);  // دقيقة واحدة للاتصال
            connection.setReadTimeout(300000);    // 5 دقائق للقراءة (للملفات الكبيرة)

            // 🚀 تفعيل chunked streaming mode لمنع OOM
            connection.setChunkedStreamingMode(CHUNK_SIZE);

            Log.d(TAG, "✅ الاتصال جاهز - بدء الرفع باستخدام streaming...");

            // 📤 كتابة البيانات باستخدام streaming
            outputStream = new java.io.BufferedOutputStream(
                connection.getOutputStream(),
                CHUNK_SIZE
            );

            // حقل sponsorship_id
            writeFormFieldOptimized(outputStream, boundary, "sponsorship_id", String.valueOf(item.photoId));

            // بداية حقل الملف
            outputStream.write(("--" + boundary + "\r\n").getBytes("UTF-8"));
            outputStream.write(("Content-Disposition: form-data; name=\"file\"; filename=\"" + item.fileName + "\"\r\n").getBytes("UTF-8"));
            outputStream.write(("Content-Type: " + (item.fileType != null ? item.fileType : "application/octet-stream") + "\r\n\r\n").getBytes("UTF-8"));

            // 🚀 نسخ الملف باستخدام streaming (chunks)
            byte[] buffer = new byte[CHUNK_SIZE];
            int bytesRead;
            long totalBytesRead = 0;
            int progressPercent = 0;
            long lastLogTime = System.currentTimeMillis();

            while ((bytesRead = inputStream.read(buffer)) != -1) {
                outputStream.write(buffer, 0, bytesRead);
                totalBytesRead += bytesRead;

                // عرض التقدم كل 10% أو كل 5 ثواني
                int newProgress = (int) ((totalBytesRead * 100) / fileSize);
                long currentTime = System.currentTimeMillis();

                if (newProgress >= progressPercent + 10 || currentTime - lastLogTime >= 5000) {
                    progressPercent = newProgress;
                    lastLogTime = currentTime;
                    Log.d(TAG, "📤 تقدم رفع " + item.fileName + ": " + progressPercent + "% (" +
                          formatFileSize(totalBytesRead) + " / " + formatFileSize(fileSize) + ")");

                    // تحديث الإشعار بالتقدم
                    updateNotificationWithProgress(item.fileName, progressPercent,
                                                   dbHelper.getPendingFilesCount());
                }

                // التحقق من الإيقاف
                if (isStopped()) {
                    Log.w(TAG, "⚠️ تم إيقاف Worker أثناء الرفع");
                    return false;
                }
            }

            outputStream.write("\r\n".getBytes("UTF-8"));

            // نهاية multipart
            outputStream.write(("--" + boundary + "--\r\n").getBytes("UTF-8"));
            outputStream.flush();

            Log.d(TAG, "✅ تم رفع " + formatFileSize(totalBytesRead) + " بنجاح");

            // قراءة الاستجابة
            int responseCode = connection.getResponseCode();
            Log.d(TAG, "📡 رمز الاستجابة: " + responseCode + " للملف: " + item.fileName);

            // قراءة رسالة الاستجابة للتأكيد
            if (responseCode >= 200 && responseCode < 300) {
                try {
                    java.io.BufferedReader reader = new java.io.BufferedReader(
                        new java.io.InputStreamReader(connection.getInputStream())
                    );
                    String line;
                    StringBuilder response = new StringBuilder();
                    while ((line = reader.readLine()) != null) {
                        response.append(line);
                    }
                    reader.close();
                    Log.d(TAG, "✅ استجابة السيرفر: " + response.toString());
                } catch (Exception e) {
                    Log.w(TAG, "⚠️ خطأ في قراءة الاستجابة: " + e.getMessage());
                }
                return true;
            } else {
                String errorMsg = "فشل الرفع - رمز: " + responseCode;
                Log.e(TAG, errorMsg);
                dbHelper.updateFileStatus(item.id, item.status, errorMsg);
                return false;
            }

        } catch (OutOfMemoryError oom) {
            Log.e(TAG, "🚨🚨🚨 OUT OF MEMORY! الملف كبير جداً!", oom);
            Log.e(TAG, "💡 نصيحة: قلل حجم الفيديو أو جودته قبل الرفع");
            dbHelper.updateFileStatus(item.id, item.status, "Out of Memory - ملف كبير جداً");
            return false;

        } catch (IOException e) {
            Log.e(TAG, "❌ خطأ في رفع الملف " + item.fileName + ": " + e.getMessage(), e);
            dbHelper.updateFileStatus(item.id, item.status, e.getMessage());
            return false;

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ غير متوقع: " + e.getMessage(), e);
            dbHelper.updateFileStatus(item.id, item.status, e.getMessage());
            return false;

        } finally {
            // تنظيف الموارد
            try {
                if (inputStream != null) inputStream.close();
                if (outputStream != null) outputStream.close();
                if (connection != null) connection.disconnect();
            } catch (Exception e) {
                Log.e(TAG, "⚠️ خطأ في إغلاق الموارد: " + e.getMessage());
            }

            // استدعاء garbage collector لتحرير الذاكرة
            System.gc();
        }
    }

    /**
     * كتابة حقل نموذج محسّن
     */
    private void writeFormFieldOptimized(OutputStream out, String boundary, String name, String value)
            throws IOException {
        out.write(("--" + boundary + "\r\n").getBytes("UTF-8"));
        out.write(("Content-Disposition: form-data; name=\"" + name + "\"\r\n\r\n").getBytes("UTF-8"));
        out.write((value + "\r\n").getBytes("UTF-8"));
    }

    /**
     * تنسيق حجم الملف للعرض
     */
    private String formatFileSize(long bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return String.format("%.2f KB", bytes / 1024.0);
        if (bytes < 1024 * 1024 * 1024) return String.format("%.2f MB", bytes / (1024.0 * 1024.0));
        return String.format("%.2f GB", bytes / (1024.0 * 1024.0 * 1024.0));
    }

    /**
     * تحديث الإشعار مع نسبة التقدم
     */
    private void updateNotificationWithProgress(String currentFile, int progress, int remainingCount) {
        try {
            android.app.NotificationManager notificationManager =
                (android.app.NotificationManager) context.getSystemService(android.content.Context.NOTIFICATION_SERVICE);

            String channelId = "upload_service_channel";
            android.app.Notification notification = new NotificationCompat.Builder(context, channelId)
                .setContentTitle("جاري رفع: " + currentFile)
                .setContentText(progress + "% - متبقي: " + remainingCount + " ملف")
                .setSmallIcon(android.R.drawable.stat_sys_upload)
                .setProgress(100, progress, false)
                .build();

            notificationManager.notify(1, notification);
        } catch (Exception e) {
            Log.e(TAG, "خطأ في تحديث الإشعار: " + e.getMessage());
        }
    }

    /**
     * كتابة حقل نصي في النموذج
     */
    private void writeFormField(OutputStream out, String name, String value) throws IOException {
        out.write(("--" + BOUNDARY + "\r\n").getBytes());
        out.write(("Content-Disposition: form-data; name=\"" + name + "\"\r\n").getBytes());
        out.write("\r\n".getBytes());
        out.write((value + "\r\n").getBytes());
    }

    /**
     * كتابة حقل ملف في النموذج
     */
    private void writeFileField(OutputStream out, String name, String fileName, File file) throws IOException {
        out.write(("--" + BOUNDARY + "\r\n").getBytes());
        out.write(("Content-Disposition: form-data; name=\"" + name + "\"; filename=\"" + fileName + "\"\r\n").getBytes());
        out.write(("Content-Type: image/jpeg\r\n").getBytes());
        out.write("\r\n".getBytes());

        FileInputStream fileInputStream = new FileInputStream(file);
        byte[] buffer = new byte[CHUNK_SIZE];
        int bytesRead;

        while ((bytesRead = fileInputStream.read(buffer)) != -1) {
            out.write(buffer, 0, bytesRead);
        }

        fileInputStream.close();
        out.write("\r\n".getBytes());
    }

    /**
     * كتابة حقل ملف من البايتات مباشرة (Base64)
     */
    private void writeFileFieldFromBytes(OutputStream out, String name, String fileName, byte[] fileBytes, String fileType) throws IOException {
        out.write(("--" + BOUNDARY + "\r\n").getBytes());
        out.write(("Content-Disposition: form-data; name=\"" + name + "\"; filename=\"" + fileName + "\"\r\n").getBytes());
        out.write(("Content-Type: " + (fileType != null ? fileType : "application/octet-stream") + "\r\n").getBytes());
        out.write("\r\n".getBytes());

        // كتابة البايتات مباشرة
        out.write(fileBytes);

        out.write("\r\n".getBytes());
    }

    /**
     * حذف الملف المحلي بعد الرفع الناجح
     */
    private void deleteLocalFile(String filePath) {
        try {
            File file = new File(filePath);
            if (file.exists()) {
                if (file.delete()) {
                    Log.d(TAG, "🗑️ تم حذف الملف المحلي: " + filePath);
                } else {
                    Log.w(TAG, "⚠️ فشل حذف الملف المحلي: " + filePath);
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "خطأ في حذف الملف المحلي: " + e.getMessage());
        }
    }

    /**
     * تحديث الإشعار
     */
    private void updateNotification(String currentFile, int remainingCount) {
        try {
            // تحديث إشعار Foreground Service
            android.app.NotificationManager notificationManager =
                (android.app.NotificationManager) context.getSystemService(android.content.Context.NOTIFICATION_SERVICE);

            String channelId = "upload_service_channel";
            android.app.Notification notification = new NotificationCompat.Builder(context, channelId)
                .setContentTitle("جاري رفع الملفات")
                .setContentText("رفع: " + currentFile + " (متبقي: " + remainingCount + ")")
                .setSmallIcon(android.R.drawable.stat_sys_upload)
                .setProgress(0, 0, true)
                .build();

            notificationManager.notify(1, notification);
        } catch (Exception e) {
            Log.e(TAG, "خطأ في تحديث الإشعار: " + e.getMessage());
        }
    }

    /**
     * إيقاف الخدمة المقدمة
     */
    private void stopForegroundService() {
        try {
            android.app.NotificationManager notificationManager =
                (android.app.NotificationManager) context.getSystemService(android.content.Context.NOTIFICATION_SERVICE);
            notificationManager.cancel(1);
            Log.d(TAG, "✅ تم إيقاف Foreground Service");
        } catch (Exception e) {
            Log.e(TAG, "خطأ في إيقاف الخدمة: " + e.getMessage());
        }
    }

    @NonNull
    @Override
    public ForegroundInfo getForegroundInfo() {
        // إنشاء ForegroundInfo مباشرة بدون BackgroundUploadService
        android.app.NotificationManager notificationManager =
            (android.app.NotificationManager) context.getSystemService(android.content.Context.NOTIFICATION_SERVICE);

        String channelId = "upload_service_channel";
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
            android.app.NotificationChannel channel = new android.app.NotificationChannel(
                channelId,
                "Background Upload Service",
                android.app.NotificationManager.IMPORTANCE_LOW
            );
            notificationManager.createNotificationChannel(channel);
        }

        android.app.Notification notification = new NotificationCompat.Builder(context, channelId)
            .setContentTitle("جاري تهيئة الرفع...")
            .setSmallIcon(android.R.drawable.stat_sys_upload)
            .build();

        return new ForegroundInfo(1, notification);
    }
}
