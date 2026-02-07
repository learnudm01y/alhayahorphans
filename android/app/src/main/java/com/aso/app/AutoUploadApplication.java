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

        // ✨ CRITICAL: بدء AlarmManager للمزامنة القوية (يعمل حتى بعد إغلاق التطبيق)
        android.util.Log.e(TAG, "⏰ Starting AlarmManager for persistent sync...");
        org.alhayah.sponsorships.DataSyncAlarmReceiver.startAlarmManager(this);
        android.util.Log.e(TAG, "✅ DataSyncAlarmReceiver active (10-second checks)");

        // ✨ CRITICAL: بدء UploadAlarmReceiver لرفع الملفات بقوة (كل 10 ثوانٍ)
        android.util.Log.e(TAG, "⏰ Starting UploadAlarmReceiver for file uploads...");
        com.aso.app.UploadAlarmReceiver.startAlarmManager(this);
        android.util.Log.e(TAG, "✅ UploadAlarmReceiver active (10-second aggressive checks)");

        // ✨ CRITICAL: بدء NetworkConnectedWorker - يُطلق فوراً عند عودة الإنترنت
        android.util.Log.e(TAG, "🌐 Starting NetworkConnectedWorker...");
        org.alhayah.sponsorships.NetworkConnectedWorker.scheduleWork(this);
        android.util.Log.e(TAG, "✅ NetworkConnectedWorker active (triggers on internet connect)");

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  ✅✅✅ APPLICATION READY - All Systems Active          ✅✅✅  ║");
        android.util.Log.e(TAG, "║  📁 Files: UploadAlarm (10s) + ForegroundService + WorkManager║");
        android.util.Log.e(TAG, "║  📊 Data: DataSyncAlarm (10s) + ForegroundService + WorkMgr   ║");
        android.util.Log.e(TAG, "║  🌐 NetworkConnectedWorker: Triggers on internet connect      ║");
        android.util.Log.e(TAG, "║  ⏰ AlarmManager: Every 10 sec (even when closed) - Doze OK   ║");
        android.util.Log.e(TAG, "║  🔌 Boot Receiver: Auto-start on device reboot                ║");
        android.util.Log.e(TAG, "║  📂 Folder Rename: Database tracking prevents duplicates      ║");
        android.util.Log.e(TAG, "║  ⚡⚡⚡ IMMEDIATE SYNC (5 sec) when data saved!        ⚡⚡⚡  ║");
        android.util.Log.e(TAG, "║  🚀🚀🚀 WORKS EVEN WHEN APP IS FULLY CLOSED!          🚀🚀🚀  ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");
    }
}
