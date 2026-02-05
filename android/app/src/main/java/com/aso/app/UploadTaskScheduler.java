package com.aso.app;

import android.content.Context;
import android.util.Log;
import androidx.work.BackoffPolicy;
import androidx.work.Constraints;
import androidx.work.NetworkType;
import androidx.work.ExistingWorkPolicy;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.OneTimeWorkRequest;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import androidx.work.WorkInfo;
import androidx.work.WorkRequest;
import androidx.work.OutOfQuotaPolicy;
import com.google.common.util.concurrent.ListenableFuture;
import java.util.List;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.TimeUnit;

/**
 * جدولة مهام رفع الملفات في الخلفية
 * يدير WorkManager ويضمن استمرار الرفع حتى عند إعادة تشغيل الجهاز
 */
public class UploadTaskScheduler {
    private static final String TAG = "UploadTaskScheduler";
    private static final String UNIQUE_WORK_NAME = "BackgroundUploadWork";

    private Context context;
    private WorkManager workManager;
    private UploadDatabaseHelper dbHelper;

    private static UploadTaskScheduler instance;

    public static synchronized UploadTaskScheduler getInstance(Context context) {
        if (instance == null) {
            instance = new UploadTaskScheduler(context.getApplicationContext());
        }
        return instance;
    }

    private UploadTaskScheduler(Context context) {
        this.context = context;
        this.workManager = WorkManager.getInstance(context);
        this.dbHelper = UploadDatabaseHelper.getInstance(context);
    }

    /**
     * جدولة مهمة رفع الملفات - تبدأ فوراً بدون قيود
     */
    public void scheduleUploadTask() {
        Log.d(TAG, "");
        Log.d(TAG, "🎯🎯🎯 scheduleUploadTask تم استدعاؤها! 🎯🎯🎯");

        try {
            // التحقق من وجود ملفات معلقة
            int pendingCount = dbHelper.getPendingFilesCount();
            Log.d(TAG, "📊 عدد الملفات المعلقة في DB: " + pendingCount);

            if (pendingCount == 0) {
                Log.d(TAG, "لا توجد ملفات معلقة للرفع");
                return;
            }

            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "🔥🔥🔥 جدولة رفع فوري لـ " + pendingCount + " ملف 🔥🔥🔥");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            // إنشاء طلب مهمة بدون قيود للبدء الفوري
            OneTimeWorkRequest uploadWorkRequest = new OneTimeWorkRequest.Builder(BackgroundUploadWorker.class)
                .setBackoffCriteria(
                    BackoffPolicy.EXPONENTIAL,
                    WorkRequest.MIN_BACKOFF_MILLIS,
                    TimeUnit.MILLISECONDS
                )
                .addTag("upload_task")
                .addTag("immediate_upload_" + System.currentTimeMillis())
                .build();

            // جدولة المهمة - REPLACE لضمان البدء الفوري
            workManager.enqueueUniqueWork(
                UNIQUE_WORK_NAME + "_" + System.currentTimeMillis(), // اسم فريد لكل مهمة
                ExistingWorkPolicy.REPLACE,
                uploadWorkRequest
            );

            Log.d(TAG, "✅✅✅ تمت جدولة المهمة - WorkManager سيبدأ فوراً! ✅✅✅");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "");

        } catch (Exception e) {
            Log.e(TAG, "خطأ في جدولة مهمة الرفع: " + e.getMessage(), e);
        }
    }

    /**
     * بدء فوري للرفع - تشغيل BackgroundUploadWorker مباشرة في thread منفصل
     * هذا يضمن بدء الرفع فوراً بدون انتظار WorkManager
     */
    public void startImmediateUpload() {
        Log.d(TAG, "");
        Log.d(TAG, "⚡⚡⚡ startImmediateUpload تم استدعاؤها! ⚡⚡⚡");

        try {
            int pendingCount = dbHelper.getPendingFilesCount();
            Log.d(TAG, "📊 فحص الملفات المعلقة: " + pendingCount);

            if (pendingCount == 0) {
                Log.d(TAG, "⚠️ لا توجد ملفات للرفع الفوري");
                return;
            }

            Log.d(TAG, "");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "⚡⚡⚡ بدء رفع فوري مباشر الآن! ⚡⚡⚡");
            Log.d(TAG, "📦 عدد الملفات: " + pendingCount);
            Log.d(TAG, "🔥 تشغيل في thread منفصل للرفع الفوري");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            // تشغيل في thread منفصل لعدم حجب UI
            new Thread(() -> {
                try {
                    Log.d(TAG, "🚀 Thread بدأ - معالجة الملفات المعلقة...");

                    // إنشاء instance من BackgroundUploadWorker وتشغيله
                    UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);

                    // إعادة تعيين الملفات قيد الرفع
                    dbHelper.resetUploadingFiles();

                    int processed = 0;
                    int success = 0;

                    // معالجة كل الملفات المعلقة
                    while (true) {
                        UploadDatabaseHelper.UploadItem nextFile = dbHelper.getNextPendingFile();

                        if (nextFile == null) {
                            Log.d(TAG, "✅ انتهت معالجة جميع الملفات");
                            break;
                        }

                        processed++;
                        Log.d(TAG, "");
                        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                        Log.d(TAG, "📤 معالجة ملف #" + processed + ": " + nextFile.fileName);
                        Log.d(TAG, "   ├─ ID: " + nextFile.id);
                        Log.d(TAG, "   ├─ Photo ID: " + nextFile.photoId);
                        Log.d(TAG, "   └─ API: " + nextFile.apiUrl);
                        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

                        // تحديث الحالة
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_UPLOADING, null);

                        // محاولة رفع الملف
                        boolean uploaded = uploadFileDirect(nextFile);

                        if (uploaded) {
                            dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                            success++;
                            Log.d(TAG, "✅✅✅ نجح رفع الملف! (" + success + "/" + processed + ")");
                        } else {
                            dbHelper.incrementRetryCount(nextFile.id);
                            if (nextFile.retryCount >= 3) {
                                dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_FAILED, "فشل بعد 3 محاولات");
                                Log.e(TAG, "❌❌❌ فشل رفع الملف بعد 3 محاولات");
                            } else {
                                dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, null);
                                Log.w(TAG, "⚠️ فشل - سيتم المحاولة مرة أخرى");
                            }
                        }

                        Thread.sleep(500); // توقف قصير بين الملفات
                    }

                    Log.d(TAG, "");
                    Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                    Log.d(TAG, "📊 ملخص الرفع الفوري:");
                    Log.d(TAG, "   ├─ معالج: " + processed);
                    Log.d(TAG, "   ├─ نجح: " + success + " ✅");
                    Log.d(TAG, "   └─ فشل: " + (processed - success) + " ❌");
                    Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

                    // حذف الملفات المكتملة
                    dbHelper.deleteCompletedFiles();

                } catch (Exception e) {
                    Log.e(TAG, "❌ خطأ في الرفع الفوري: " + e.getMessage(), e);
                }
            }).start();

            Log.d(TAG, "✅ تم بدء thread الرفع الفوري");

        } catch (Exception e) {
            Log.e(TAG, "خطأ في startImmediateUpload: " + e.getMessage(), e);
        }
    }

    /**
     * رفع ملف مباشرة - نسخة مبسطة من BackgroundUploadWorker.uploadFile
     */
    private boolean uploadFileDirect(UploadDatabaseHelper.UploadItem item) {
        java.net.HttpURLConnection connection = null;

        try {
            byte[] fileBytes = null;

            // معالجة Base64
            if (item.filePath.startsWith("data:")) {
                Log.d(TAG, "📦 استخراج بيانات Base64...");
                String[] parts = item.filePath.split(",");
                if (parts.length == 2) {
                    fileBytes = android.util.Base64.decode(parts[1], android.util.Base64.DEFAULT);
                    Log.d(TAG, "✅ تم فك Base64 - حجم: " + fileBytes.length + " bytes");
                } else {
                    Log.e(TAG, "❌ صيغة Base64 خاطئة");
                    return false;
                }
            } else {
                // ملف فيزيائي - استخدام getFilesDir للمسار الكامل
                java.io.File file = new java.io.File(context.getFilesDir(), item.filePath);
                if (!file.exists()) {
                    Log.e(TAG, "❌ الملف غير موجود: " + file.getAbsolutePath());
                    return false;
                }
                Log.d(TAG, "✅ تم العثور على الملف: " + file.getAbsolutePath());
                fileBytes = new byte[(int) file.length()];
                java.io.FileInputStream fis = new java.io.FileInputStream(file);
                fis.read(fileBytes);
                fis.close();
                Log.d(TAG, "✅ تم قراءة " + fileBytes.length + " bytes من الملف");
            }

            if (fileBytes == null || fileBytes.length == 0) {
                Log.e(TAG, "❌ بيانات الملف فارغة");
                return false;
            }

            // إنشاء الاتصال
            Log.d(TAG, "🌐 إنشاء اتصال HTTP: " + item.apiUrl);
            java.net.URL url = new java.net.URL(item.apiUrl);
            connection = (java.net.HttpURLConnection) url.openConnection();
            connection.setDoOutput(true);
            connection.setRequestMethod("POST");
            connection.setRequestProperty("Content-Type", "multipart/form-data; boundary=----WebKitFormBoundary");
            connection.setConnectTimeout(30000);
            connection.setReadTimeout(60000);

            // كتابة البيانات
            java.io.OutputStream out = connection.getOutputStream();
            String boundary = "----WebKitFormBoundary";

            // حقل sponsorship_id
            out.write(("--" + boundary + "\r\n").getBytes());
            out.write(("Content-Disposition: form-data; name=\"sponsorship_id\"\r\n\r\n").getBytes());
            out.write((item.photoId + "\r\n").getBytes());

            // حقل الملف
            out.write(("--" + boundary + "\r\n").getBytes());
            out.write(("Content-Disposition: form-data; name=\"file\"; filename=\"" + item.fileName + "\"\r\n").getBytes());
            out.write(("Content-Type: " + item.fileType + "\r\n\r\n").getBytes());
            out.write(fileBytes);
            out.write("\r\n".getBytes());

            // نهاية
            out.write(("--" + boundary + "--\r\n").getBytes());
            out.flush();
            out.close();

            // قراءة الاستجابة
            int responseCode = connection.getResponseCode();
            Log.d(TAG, "📡 رمز الاستجابة: " + responseCode);

            return (responseCode >= 200 && responseCode < 300);

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في الرفع: " + e.getMessage(), e);
            return false;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    /**
     * إعادة جدولة مهمة الرفع (استبدال المهمة الحالية)
     */
    public void rescheduleUploadTask() {
        try {
            int pendingCount = dbHelper.getPendingFilesCount();

            if (pendingCount == 0) {
                Log.d(TAG, "لا توجد ملفات معلقة - إلغاء المهمة");
                cancelUploadTask();
                return;
            }

            Log.d(TAG, "🔄 إعادة جدولة رفع " + pendingCount + " ملف");

            Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

            OneTimeWorkRequest uploadWorkRequest = new OneTimeWorkRequest.Builder(BackgroundUploadWorker.class)
                .setConstraints(constraints)
                .addTag("upload_task")
                .build();

            // استخدام REPLACE لاستبدال المهمة الحالية
            workManager.enqueueUniqueWork(
                UNIQUE_WORK_NAME,
                ExistingWorkPolicy.REPLACE,
                uploadWorkRequest
            );

            Log.d(TAG, "✅ تمت إعادة جدولة مهمة الرفع بنجاح");

        } catch (Exception e) {
            Log.e(TAG, "خطأ في إعادة جدولة مهمة الرفع: " + e.getMessage(), e);
        }
    }

    /**
     * إلغاء مهمة الرفع
     */
    public void cancelUploadTask() {
        try {
            workManager.cancelUniqueWork(UNIQUE_WORK_NAME);
            Log.d(TAG, "❌ تم إلغاء مهمة الرفع");
        } catch (Exception e) {
            Log.e(TAG, "خطأ في إلغاء مهمة الرفع: " + e.getMessage(), e);
        }
    }

    /**
     * التحقق من حالة مهمة الرفع
     */
    public boolean isUploadTaskRunning() {
        try {
            ListenableFuture<List<WorkInfo>> future = workManager.getWorkInfosForUniqueWork(UNIQUE_WORK_NAME);
            List<WorkInfo> workInfoList = future.get();

            for (WorkInfo workInfo : workInfoList) {
                WorkInfo.State state = workInfo.getState();
                if (state == WorkInfo.State.RUNNING || state == WorkInfo.State.ENQUEUED) {
                    return true;
                }
            }

            return false;

        } catch (ExecutionException | InterruptedException e) {
            Log.e(TAG, "خطأ في التحقق من حالة المهمة: " + e.getMessage(), e);
            return false;
        }
    }

    /**
     * الحصول على حالة مهمة الرفع
     */
    public String getUploadTaskStatus() {
        try {
            ListenableFuture<List<WorkInfo>> future = workManager.getWorkInfosForUniqueWork(UNIQUE_WORK_NAME);
            List<WorkInfo> workInfoList = future.get();

            if (workInfoList.isEmpty()) {
                return "لا توجد مهمة";
            }

            WorkInfo workInfo = workInfoList.get(0);
            WorkInfo.State state = workInfo.getState();

            switch (state) {
                case ENQUEUED:
                    return "في الانتظار";
                case RUNNING:
                    return "قيد التشغيل";
                case SUCCEEDED:
                    return "نجحت";
                case FAILED:
                    return "فشلت";
                case BLOCKED:
                    return "محظورة";
                case CANCELLED:
                    return "ملغاة";
                default:
                    return "غير معروف";
            }

        } catch (ExecutionException | InterruptedException e) {
            Log.e(TAG, "خطأ في الحصول على حالة المهمة: " + e.getMessage(), e);
            return "خطأ";
        }
    }

    /**
     * إعادة محاولة الملفات الفاشلة
     */
    public void retryFailedFiles() {
        try {
            // إعادة تعيين الملفات الفاشلة إلى معلق
            dbHelper.retryFailedFiles();

            // جدولة مهمة جديدة
            scheduleUploadTask();

            Log.d(TAG, "🔄 تمت إعادة جدولة الملفات الفاشلة");

        } catch (Exception e) {
            Log.e(TAG, "خطأ في إعادة محاولة الملفات الفاشلة: " + e.getMessage(), e);
        }
    }

    /**
     * الحصول على إحصائيات الرفع
     */
    public UploadStats getUploadStats() {
        try {
            UploadStats stats = new UploadStats();

            stats.pendingCount = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_PENDING).size();
            stats.uploadingCount = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_UPLOADING).size();
            stats.completedCount = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_COMPLETED).size();
            stats.failedCount = dbHelper.getFilesByStatus(UploadDatabaseHelper.STATUS_FAILED).size();
            stats.totalCount = stats.pendingCount + stats.uploadingCount + stats.completedCount + stats.failedCount;
            stats.isTaskRunning = isUploadTaskRunning();
            stats.taskStatus = getUploadTaskStatus();

            return stats;

        } catch (Exception e) {
            Log.e(TAG, "خطأ في الحصول على الإحصائيات: " + e.getMessage(), e);
            return new UploadStats();
        }
    }

    /**
     * فئة الإحصائيات
     */
    public static class UploadStats {
        public int pendingCount = 0;
        public int uploadingCount = 0;
        public int completedCount = 0;
        public int failedCount = 0;
        public int totalCount = 0;
        public boolean isTaskRunning = false;
        public String taskStatus = "غير معروف";

        @Override
        public String toString() {
            return "UploadStats{" +
                    "total=" + totalCount +
                    ", pending=" + pendingCount +
                    ", uploading=" + uploadingCount +
                    ", completed=" + completedCount +
                    ", failed=" + failedCount +
                    ", taskRunning=" + isTaskRunning +
                    ", taskStatus='" + taskStatus + '\'' +
                    '}';
        }
    }
}
