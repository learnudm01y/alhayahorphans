package com.aso.app;

import android.app.Application;
import android.util.Log;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import java.util.concurrent.TimeUnit;

// استيراد مكونات نظام البيانات المنفصل
import org.alhayah.sponsorships.DataSyncDatabaseHelper;
import org.alhayah.sponsorships.DataSyncNetworkMonitor;
import org.alhayah.sponsorships.DataSyncWorker;

/**
 * Application class - يبدأ تلقائياً عند تشغيل التطبيق
 * يقوم بتهيئة نظامين منفصلين:
 * 1. نظام رفع الملفات (UploadForegroundService)
 * 2. نظام مزامنة البيانات (DataSyncForegroundService)
 */
public class AutoUploadApplication extends Application {
    private static final String TAG = "AutoUploadApp";

    @Override
    public void onCreate() {
        // 🚨 ABSOLUTE FIRST LOG - before everything
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨");
        android.util.Log.e(TAG, "🚨 APP STARTED - AutoUploadApplication v10:10 🚨");
        android.util.Log.e(TAG, "🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨🚨");
        android.util.Log.e(TAG, "");

        super.onCreate();
        android.util.Log.e(TAG, "✅ [1/8] super.onCreate() completed");

        // ═══════════════════════════════════════════════════════════════
        // نظام رفع الملفات (Files Upload System)
        // ═══════════════════════════════════════════════════════════════

        android.util.Log.e(TAG, "📝 [2/8] Initializing UploadDatabaseHelper (FILES)...");
        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(this);
        android.util.Log.e(TAG, "✅ [2/8] UploadDatabaseHelper ready");

        android.util.Log.e(TAG, "📝 [3/8] Initializing UploadTaskScheduler...");
        UploadTaskScheduler scheduler = UploadTaskScheduler.getInstance(this);
        android.util.Log.e(TAG, "✅ [3/8] UploadTaskScheduler ready");

        android.util.Log.e(TAG, "📝 [4/8] Initializing WorkManager...");
        WorkManager workManager = WorkManager.getInstance(this);
        android.util.Log.e(TAG, "✅ [4/8] WorkManager ready");

        // إعادة تعيين الملفات التي كانت قيد الرفع
        android.util.Log.e(TAG, "🔄 Resetting uploading files...");
        dbHelper.resetUploadingFiles();
        android.util.Log.e(TAG, "✅ Files reset complete");

        // جدولة أي ملفات معلقة - ستبدأ فوراً
        int pendingFilesCount = dbHelper.getPendingFilesCount();
        android.util.Log.e(TAG, "📊 Pending files count: " + pendingFilesCount);
        if (pendingFilesCount > 0) {
            android.util.Log.e(TAG, "📦 Found " + pendingFilesCount + " pending files - scheduling immediate upload");
            scheduler.scheduleUploadTask();
            android.util.Log.e(TAG, "✅ Upload task scheduled");
        } else {
            android.util.Log.e(TAG, "ℹ️  No pending files - no upload needed");
        }

        // إضافة مراقبة دورية لملفات كل 15 دقيقة
        android.util.Log.e(TAG, "📝 [5/8] Setting up FILES periodic check (15 min)...");
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
        android.util.Log.e(TAG, "✅ [5/8] FILES periodic check scheduled");

        // بدء مراقبة حالة الإنترنت للملفات
        android.util.Log.e(TAG, "📝 [6/8] Starting Network Monitor (FILES)...");
        NetworkMonitor networkMonitor = NetworkMonitor.getInstance(this);
        networkMonitor.startMonitoring();
        android.util.Log.e(TAG, "✅ [6/8] Network Monitor active (FILES)");

        // ═══════════════════════════════════════════════════════════════
        // نظام مزامنة البيانات (Data Sync System) - معزول تماماً
        // ═══════════════════════════════════════════════════════════════

        android.util.Log.e(TAG, "📝 [7/8] Initializing DataSyncDatabaseHelper (DATA)...");
        DataSyncDatabaseHelper dataSyncDbHelper = DataSyncDatabaseHelper.getInstance(this);
        android.util.Log.e(TAG, "✅ [7/8] DataSyncDatabaseHelper ready");

        // إضافة مراقبة دورية للبيانات كل 15 دقيقة (منفصلة عن الملفات)
        android.util.Log.e(TAG, "📝 [8/8] Setting up DATA periodic check (15 min)...");
        PeriodicWorkRequest periodicDataSyncWork =
            new PeriodicWorkRequest.Builder(
                DataSyncWorker.class,
                15, TimeUnit.MINUTES)
            .addTag("periodic_data_sync_check")
            .build();

        workManager.enqueueUniquePeriodicWork(
            "periodic_data_sync_check",
            ExistingPeriodicWorkPolicy.KEEP,
            periodicDataSyncWork
        );
        android.util.Log.e(TAG, "✅ [8/8] DATA periodic check scheduled");

        // بدء مراقبة حالة الإنترنت للبيانات (منفصلة عن الملفات)
        android.util.Log.e(TAG, "🌐 Starting DataSyncNetworkMonitor...");
        DataSyncNetworkMonitor dataSyncNetworkMonitor = DataSyncNetworkMonitor.getInstance(this);
        dataSyncNetworkMonitor.startMonitoring();
        android.util.Log.e(TAG, "✅ DataSyncNetworkMonitor active");

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  ✅✅✅ APPLICATION READY - Both Systems Active          ✅✅✅  ║");
        android.util.Log.e(TAG, "║  📁 Files Upload System: UploadForegroundService              ║");
        android.util.Log.e(TAG, "║  📊 Data Sync System: DataSyncForegroundService               ║");
        android.util.Log.e(TAG, "║  🌐🌐🌐 Network Monitor: Auto-upload on reconnect      🌐🌐🌐  ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");
    }
}
