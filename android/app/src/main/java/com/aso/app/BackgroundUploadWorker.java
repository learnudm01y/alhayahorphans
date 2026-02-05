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
        Log.d(TAG, "🚀🚀🚀 بدء BackgroundUploadWorker 🚀🚀🚀");
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
     * رفع الملف إلى السيرفر
     */
    private boolean uploadFile(UploadDatabaseHelper.UploadItem item) {
        HttpURLConnection connection = null;

        try {
            byte[] fileBytes = null;

            // التحقق من نوع البيانات - Base64 أو ملف فيزيائي
            if (item.filePath.startsWith("data:")) {
                // Base64 - استخراج البيانات
                Log.d(TAG, "📦 معالجة Base64 للملف: " + item.fileName);
                String[] parts = item.filePath.split(",");
                if (parts.length == 2) {
                    fileBytes = android.util.Base64.decode(parts[1], android.util.Base64.DEFAULT);
                } else {
                    Log.e(TAG, "❌ صيغة Base64 غير صحيحة");
                    return false;
                }
            } else {
                // ملف فيزيائي - استخدام getFilesDir للمسار الكامل
                File file = new File(context.getFilesDir(), item.filePath);
                if (!file.exists()) {
                    Log.e(TAG, "❌ الملف غير موجود: " + file.getAbsolutePath());
                    return false;
                }

                Log.d(TAG, "✅ تم العثور على الملف: " + file.getAbsolutePath());
                FileInputStream fis = new FileInputStream(file);
                fileBytes = new byte[(int) file.length()];
                fis.read(fileBytes);
                fis.close();
                Log.d(TAG, "✅ تم قراءة " + fileBytes.length + " bytes من الملف");
            }

            if (fileBytes == null || fileBytes.length == 0) {
                Log.e(TAG, "❌ بيانات الملف فارغة");
                return false;
            }

            // إنشاء الاتصال
            URL url = new URL(item.apiUrl);
            connection = (HttpURLConnection) url.openConnection();
            connection.setDoOutput(true);
            connection.setRequestMethod("POST");
            connection.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + BOUNDARY);
            connection.setRequestProperty("Accept", "application/json");
            connection.setConnectTimeout(30000); // 30 ثانية
            connection.setReadTimeout(60000); // 60 ثانية

            // كتابة البيانات
            OutputStream outputStream = connection.getOutputStream();

            // بداية النموذج
            writeFormField(outputStream, "sponsorship_id", String.valueOf(item.photoId));

            // كتابة الملف من البايتات
            writeFileFieldFromBytes(outputStream, "file", item.fileName, fileBytes, item.fileType);

            // نهاية النموذج
            outputStream.write(("--" + BOUNDARY + "--\r\n").getBytes());
            outputStream.flush();
            outputStream.close();

            // قراءة الاستجابة
            int responseCode = connection.getResponseCode();
            Log.d(TAG, "رمز الاستجابة: " + responseCode + " للملف: " + item.fileName);

            if (responseCode >= 200 && responseCode < 300) {
                return true;
            } else {
                String errorMsg = "فشل الرفع - رمز: " + responseCode;
                dbHelper.updateFileStatus(item.id, item.status, errorMsg);
                return false;
            }

        } catch (IOException e) {
            Log.e(TAG, "خطأ في رفع الملف " + item.fileName + ": " + e.getMessage(), e);
            dbHelper.updateFileStatus(item.id, item.status, e.getMessage());
            return false;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
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
