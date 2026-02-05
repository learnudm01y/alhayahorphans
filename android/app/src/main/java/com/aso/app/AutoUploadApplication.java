package com.aso.app;

import android.app.Application;
import android.util.Log;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import java.util.concurrent.TimeUnit;

/**
 * Application class - يبدأ تلقائياً عند تشغيل التطبيق
 * يقوم بتهيئة نظام الرفع عبر WorkManager مع مراقبة دورية
 */
public class AutoUploadApplication extends Application {
    private static final String TAG = "AutoUploadApp";

    @Override
    public void onCreate() {
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  🔥🔥🔥 APPLICATION STARTING - AutoUploadApplication       🔥🔥🔥  ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");

        super.onCreate();
        android.util.Log.e(TAG, "✅ [1/5] super.onCreate() completed");

        // تهيئة المكونات
        android.util.Log.e(TAG, "📝 [2/5] Initializing UploadDatabaseHelper...");
        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(this);
        android.util.Log.e(TAG, "✅ [2/5] UploadDatabaseHelper ready");

        android.util.Log.e(TAG, "📝 [3/5] Initializing UploadTaskScheduler...");
        UploadTaskScheduler scheduler = UploadTaskScheduler.getInstance(this);
        android.util.Log.e(TAG, "✅ [3/5] UploadTaskScheduler ready");

        android.util.Log.e(TAG, "📝 [4/5] Initializing WorkManager...");
        WorkManager workManager = WorkManager.getInstance(this);
        android.util.Log.e(TAG, "✅ [4/5] WorkManager ready");

        // إعادة تعيين الملفات التي كانت قيد الرفع
        android.util.Log.e(TAG, "🔄 Resetting uploading files...");
        dbHelper.resetUploadingFiles();
        android.util.Log.e(TAG, "✅ Files reset complete");

        // جدولة أي ملفات معلقة - ستبدأ فوراً
        int pendingCount = dbHelper.getPendingFilesCount();
        android.util.Log.e(TAG, "📊 Pending files count: " + pendingCount);
        if (pendingCount > 0) {
            android.util.Log.e(TAG, "📦 Found " + pendingCount + " pending files - scheduling immediate upload");
            scheduler.scheduleUploadTask();
            android.util.Log.e(TAG, "✅ Upload task scheduled");
        } else {
            android.util.Log.e(TAG, "ℹ️  No pending files - no upload needed");
        }

        // إضافة مراقبة دورية كل 15 دقيقة
        android.util.Log.e(TAG, "📝 [5/5] Setting up periodic check (15 min)...");
        PeriodicWorkRequest periodicUploadWork =
            new PeriodicWorkRequest.Builder(
                BackgroundUploadWorker.class,
                15, TimeUnit.MINUTES)
            .addTag("periodic_upload_check")
            .build();

        workManager.enqueueUniquePeriodicWork(
            "periodic_upload_check",
            ExistingPeriodicWorkPolicy.KEEP,
            periodicUploadWork
        );
        android.util.Log.e(TAG, "✅ [5/5] Periodic check scheduled");

        // بدء مراقبة حالة الإنترنت - رفع تلقائي عند عودة الاتصال
        android.util.Log.e(TAG, "📝 [6/6] Starting Network Monitor...");
        NetworkMonitor networkMonitor = NetworkMonitor.getInstance(this);
        networkMonitor.startMonitoring();
        android.util.Log.e(TAG, "✅ [6/6] Network Monitor active - auto-upload on reconnect");

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  ✅✅✅ APPLICATION READY - Upload System Active         ✅✅✅  ║");
        android.util.Log.e(TAG, "║  🌐🌐🌐 Network Monitor: Auto-upload on reconnect      🌐🌐🌐  ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");
    }
}
