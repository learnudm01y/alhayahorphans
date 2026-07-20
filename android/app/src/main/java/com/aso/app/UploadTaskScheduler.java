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

    // ✅ لا حد أقصى - الملفات الضخمة مدعومة عبر streaming
    // الملفات تُقرأ chunk by chunk (8 KB) - لا يتم تحميلها كاملة في الذاكرة
    private static final long CHUNK_SIZE = 8192; // 8 KB
    private static final long LARGE_FILE_THRESHOLD = 100 * 1024 * 1024; // 100 MB
    private static final long HUGE_FILE_THRESHOLD = 1024L * 1024 * 1024; // 1 GB

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
        // ✅ Lazy initialization - WorkManager يُهيأ عند الحاجة فقط
        // لا نستدعيه في constructor لتجنب IllegalStateException
        this.dbHelper = UploadDatabaseHelper.getInstance(context);
        Log.d(TAG, "UploadTaskScheduler created - WorkManager will be lazy-loaded");
    }

    /**
     * الحصول على WorkManager بشكل آمن (Lazy Initialization)
     */
    private WorkManager getWorkManager() {
        if (workManager == null) {
            try {
                workManager = WorkManager.getInstance(context);
                Log.d(TAG, "WorkManager lazy-initialized successfully");
            } catch (IllegalStateException e) {
                Log.e(TAG, "🚨 WorkManager not initialized! App cannot function!", e);
                throw new RuntimeException("WorkManager is not initialized. Check your Application.onCreate()", e);
            }
        }
        return workManager;
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
            OneTimeWorkRequest uploadWorkRequest = new OneTimeWorkRequest.Builder(ChunkedUploadWorker.class)
                .setBackoffCriteria(
                    BackoffPolicy.EXPONENTIAL,
                    WorkRequest.MIN_BACKOFF_MILLIS,
                    TimeUnit.MILLISECONDS
                )
                .addTag("upload_task")
                .addTag("immediate_upload_" + System.currentTimeMillis())
                .build();

            // جدولة المهمة - APPEND_OR_REPLACE لضمان البدء الفوري دون مقاطعة العمل الحالي
            getWorkManager().enqueueUniqueWork(
                UNIQUE_WORK_NAME, // اسم فريد لكل مهمة
                ExistingWorkPolicy.KEEP,
                uploadWorkRequest
            );

            // جدولة DriveStatusWorker لفحص حالة الملفات
            OneTimeWorkRequest driveStatusWorkRequest = new OneTimeWorkRequest.Builder(DriveStatusWorker.class).build();
            getWorkManager().enqueueUniqueWork(
                "DriveStatusProcessor",
                ExistingWorkPolicy.KEEP,
                driveStatusWorkRequest
            );

            Log.d(TAG, "✅✅✅ تمت جدولة المهمة - WorkManager سيبدأ فوراً! ✅✅✅");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "");

        } catch (Exception e) {
            Log.e(TAG, "خطأ في جدولة مهمة الرفع: " + e.getMessage(), e);
        }
    }

    /**
     * بدء فوري للرفع - تشغيل BackgroundUploadWorker مباشرة
     * يعتمد الآن على WorkManager لضمان عدم إغلاق النظام للمهمة عند الخروج من التطبيق
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
            Log.d(TAG, "⚡⚡⚡ بدء رفع فوري مباشر الآن باستخدام WorkManager! ⚡⚡⚡");
            Log.d(TAG, "📦 عدد الملفات: " + pendingCount);
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            // إعادة تعيين الملفات قيد الرفع لتكون معلقة ليتم التقاطها
            dbHelper.resetUploadingFiles();

            Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

            OneTimeWorkRequest uploadWorkRequest = new OneTimeWorkRequest.Builder(ChunkedUploadWorker.class)
                .setConstraints(constraints)
                .setExpedited(OutOfQuotaPolicy.RUN_AS_NON_EXPEDITED_WORK_REQUEST) // مهم جداً للبدء الفوري وعدم القتل
                .setBackoffCriteria(
                    BackoffPolicy.EXPONENTIAL,
                    WorkRequest.MIN_BACKOFF_MILLIS,
                    TimeUnit.MILLISECONDS
                )
                .addTag("upload_task_immediate")
                .build();

            // استخدام REPLACE لضمان بدء المهمة فوراً دون تعليق بسبب مهمة سابقة
            getWorkManager().enqueueUniqueWork(
                UNIQUE_WORK_NAME + "_immediate",
                ExistingWorkPolicy.REPLACE,
                uploadWorkRequest
            );

            Log.d(TAG, "✅ تم إسناد مهمة الرفع الفوري إلى WorkManager بنجاح");

        } catch (Exception e) {
            Log.e(TAG, "خطأ في startImmediateUpload: " + e.getMessage(), e);
        }
    }

    /**
     * رفع ملف مباشرة - نسخة محسّنة مع STREAMING لمنع OOM
     * ✅ يستخدم BufferedInputStream للملفات الكبيرة
     * ✅ يقرأ البيانات على شكل chunks بدلاً من تحميلها كاملة
     * ✅ يدعم الملفات حتى 500 MB بدون مشاكل ذاكرة
     */
    private boolean uploadFileDirect(UploadDatabaseHelper.UploadItem item) {
        java.net.HttpURLConnection connection = null;
        java.io.InputStream inputStream = null;
        java.io.OutputStream outputStream = null;

        try {
            long fileSize = 0;
            String boundary = "----WebKitFormBoundary" + System.currentTimeMillis();

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
                    Log.e(TAG, "💡 Base64 يستهلك ذاكرة = حجم الملف × 1.5");
                    Log.e(TAG, "💡 استخدم Capacitor Filesystem.writeFile() ثم أرسل file:// URI");
                    return false;
                }

                // 🧠 فحص الذاكرة قبل فك Base64
                MemoryMonitor memMonitor = MemoryMonitor.getInstance(context);
                if (!memMonitor.canAllocate(fileSize)) {
                    Log.e(TAG, "🚨 لا توجد ذاكرة كافية لفك Base64!");
                    memMonitor.logMemoryStatus();
                    return false;
                }

                inputStream = new java.io.ByteArrayInputStream(
                    android.util.Base64.decode(parts[1], android.util.Base64.DEFAULT)
                );

            } else {
                // ✅ ملف فيزيائي - استخدام streaming للملفات الكبيرة
                java.io.File file = new java.io.File(context.getFilesDir(), item.filePath);
                if (!file.exists()) {
                    Log.e(TAG, "❌ الملف غير موجود: " + file.getAbsolutePath());
                    return false;
                }

                fileSize = file.length();
                Log.d(TAG, "✅ ملف موجود: " + file.getAbsolutePath());
                Log.d(TAG, "📊 حجم الملف: " + formatFileSize(fileSize));

                // 🧠 فحص الذاكرة للملفات الضخمة جداً
                if (fileSize > HUGE_FILE_THRESHOLD) { // > 1 GB
                    Log.e(TAG, "🚀🚀🚀 HUGE FILE DETECTED: " + formatFileSize(fileSize));
                    Log.e(TAG, "⚡ Will use ultra-efficient streaming (no memory load)");

                    MemoryMonitor memMonitor = MemoryMonitor.getInstance(context);
                    memMonitor.logMemoryStatus();
                }

                // ✅ استخدام BufferedInputStream للملفات الكبيرة (أفضل للذاكرة)
                inputStream = new java.io.BufferedInputStream(
                    new java.io.FileInputStream(file),
                    8192 // buffer 8 KB
                );
            }

            // 🌐 إنشاء الاتصال HTTP
            Log.d(TAG, "🌐 إنشاء اتصال HTTP: " + item.apiUrl);
            java.net.URL url = new java.net.URL(item.apiUrl);
            connection = (java.net.HttpURLConnection) url.openConnection();
            connection.setDoOutput(true);
            connection.setDoInput(true);
            connection.setRequestMethod("POST");
            connection.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);

            // تمديد المهلة للملفات الكبيرة (5 دقائق)
            connection.setConnectTimeout(60000);
            connection.setReadTimeout(300000); // 5 دقائق للملفات الكبيرة

            // تفعيل chunked streaming mode لمنع OOM
            connection.setChunkedStreamingMode(8192); // 8 KB chunks

            Log.d(TAG, "✅ الاتصال جاهز - بدء الرفع باستخدام streaming...");

            // 📤 كتابة البيانات باستخدام streaming
            outputStream = new java.io.BufferedOutputStream(connection.getOutputStream(), 8192);

            // حقل sponsorship_id
            writeFormField(outputStream, boundary, "sponsorship_id", String.valueOf(item.photoId));

            // بداية حقل الملف
            outputStream.write(("--" + boundary + "\r\n").getBytes("UTF-8"));
            outputStream.write(("Content-Disposition: form-data; name=\"file\"; filename=\"" + item.fileName + "\"\r\n").getBytes("UTF-8"));
            outputStream.write(("Content-Type: " + item.fileType + "\r\n\r\n").getBytes("UTF-8"));

            // 🚀 نسخ الملف باستخدام streaming (chunks 8 KB)
            byte[] buffer = new byte[8192];
            int bytesRead;
            long totalBytesRead = 0;
            int progressPercent = 0;

            while ((bytesRead = inputStream.read(buffer)) != -1) {
                outputStream.write(buffer, 0, bytesRead);
                totalBytesRead += bytesRead;

                // عرض التقدم كل 10%
                int newProgress = (int) ((totalBytesRead * 100) / fileSize);
                if (newProgress >= progressPercent + 10) {
                    progressPercent = newProgress;
                    Log.d(TAG, "📤 تقدم الرفع: " + progressPercent + "% (" + formatFileSize(totalBytesRead) + " / " + formatFileSize(fileSize) + ")");
                }
            }

            outputStream.write("\r\n".getBytes("UTF-8"));

            // نهاية multipart
            outputStream.write(("--" + boundary + "--\r\n").getBytes("UTF-8"));
            outputStream.flush();

            Log.d(TAG, "✅ تم رفع " + formatFileSize(totalBytesRead) + " بنجاح");

            // قراءة الاستجابة
            int responseCode = connection.getResponseCode();
            Log.d(TAG, "📡 رمز الاستجابة: " + responseCode);

            // قراءة رسالة الاستجابة
            if (responseCode >= 200 && responseCode < 300) {
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
            }

            return (responseCode >= 200 && responseCode < 300);

        } catch (OutOfMemoryError oom) {
            Log.e(TAG, "🚨🚨🚨 OUT OF MEMORY! الملف كبير جداً!", oom);
            Log.e(TAG, "💡 نصيحة: قلل حجم الفيديو أو جودته قبل الرفع");
            return false;

        } catch (java.io.IOException e) {
            Log.e(TAG, "❌ خطأ في الرفع: " + e.getMessage(), e);
            return false;

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ غير متوقع: " + e.getMessage(), e);
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
     * كتابة حقل نموذج في multipart/form-data
     */
    private void writeFormField(java.io.OutputStream out, String boundary, String name, String value) throws java.io.IOException {
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

            OneTimeWorkRequest uploadWorkRequest = new OneTimeWorkRequest.Builder(ChunkedUploadWorker.class)
                .setConstraints(constraints)
                .addTag("upload_task")
                .build();

            // استخدام KEEP للإبقاء على المهمة الحالية
            WorkManager.getInstance(context).enqueueUniqueWork(
                "UploadQueueProcessor",
                ExistingWorkPolicy.REPLACE,
                uploadWorkRequest
            );

            // Schedule DriveStatusWorker to run alongside
            OneTimeWorkRequest driveStatusWorkRequest = new OneTimeWorkRequest.Builder(DriveStatusWorker.class)
                .setConstraints(constraints)
                .build();

            WorkManager.getInstance(context).enqueueUniqueWork(
                "DriveStatusProcessor",
                ExistingWorkPolicy.REPLACE,
                driveStatusWorkRequest
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
            getWorkManager().cancelUniqueWork(UNIQUE_WORK_NAME);
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
            ListenableFuture<List<WorkInfo>> future = getWorkManager().getWorkInfosForUniqueWork(UNIQUE_WORK_NAME);
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
            ListenableFuture<List<WorkInfo>> future = getWorkManager().getWorkInfosForUniqueWork(UNIQUE_WORK_NAME);
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
