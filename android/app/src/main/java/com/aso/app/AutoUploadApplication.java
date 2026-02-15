package com.aso.app;

import android.app.Application;
import android.util.Log;
import androidx.work.Configuration;
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
        android.util.Log.e(TAG, "╔═══════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║                                                               ║");
        android.util.Log.e(TAG, "║   🚨🚨🚨   v22:56 - FILE SIZE FIX   🚨🚨🚨                   ║");
        android.util.Log.e(TAG, "║                                                               ║");
        android.util.Log.e(TAG, "║   🔥 DATA PERSISTENCE FIX + WORKER CLEANUP! 🔥                ║");
        android.util.Log.e(TAG, "║                                                               ║");
        android.util.Log.e(TAG, "╚═══════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "📋 v22:51 Critical Fixes:");
        android.util.Log.e(TAG, "   � REMOVED deleteAllData() - IndexedDB now PERSISTS!");
        android.util.Log.e(TAG, "   🧹 cancelAllWork() on startup - clean Workers");
        android.util.Log.e(TAG, "   🔍 Enhanced logging - see EXACTLY what happens");
        android.util.Log.e(TAG, "   ✅ User login will PERSIST across app restarts!");
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "⏰ App Start Time: " + new java.text.SimpleDateFormat("HH:mm:ss.SSS", java.util.Locale.US).format(new java.util.Date()));
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "ℹ️ Chromium flags already applied by ChromiumInitProvider");
        android.util.Log.e(TAG, "ℹ️ ContentProvider runs BEFORE Application.onCreate()");
        android.util.Log.e(TAG, "ℹ️ IF YOU DON'T SEE ☢️ CONTENT PROVIDER LOGS ← OLD APK!");
        android.util.Log.e(TAG, "");

        super.onCreate();
        android.util.Log.e(TAG, "✅ [1/8] super.onCreate() completed");

        // ═══════════════════════════════════════════════════════════════
        // 🚨 CRITICAL: تهيئة WorkManager يدوياً (MANUAL INITIALIZATION)
        // ═══════════════════════════════════════════════════════════════

        android.util.Log.e(TAG, "📝 [2/8] Manually initializing WorkManager...");
        WorkManager workManager = null;
        try {
            // إنشاء Configuration مخصص لـ WorkManager
            Configuration config = new Configuration.Builder()
                .setMinimumLoggingLevel(android.util.Log.INFO)
                .build();

            // تهيئة WorkManager يدوياً
            WorkManager.initialize(getApplicationContext(), config);
            android.util.Log.e(TAG, "✅ WorkManager manually initialized with custom config");

            // الآن يمكن الحصول على instance
            workManager = WorkManager.getInstance(getApplicationContext());
            android.util.Log.e(TAG, "✅ [2/8] WorkManager instance obtained successfully");

        } catch (IllegalStateException e) {
            // WorkManager ممكن يكون مُهيأ مسبقاً
            android.util.Log.w(TAG, "⚠️  WorkManager already initialized, getting instance...");
            try {
                workManager = WorkManager.getInstance(getApplicationContext());
                android.util.Log.e(TAG, "✅ [2/8] WorkManager instance obtained (was already initialized)");
            } catch (Exception ex) {
                android.util.Log.e(TAG, "❌ FATAL: Cannot get WorkManager instance!", ex);
                throw new RuntimeException("WorkManager initialization failed completely", ex);
            }
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ WorkManager initialization failed: " + e.getMessage(), e);
            android.util.Log.e(TAG, "🚨 APP CANNOT CONTINUE WITHOUT WORKMANAGER!");
            throw new RuntimeException("WorkManager initialization failed", e);
        }

        // ═══════════════════════════════════════════════════════════════
        // نظام رفع الملفات (Files Upload System)
        // FileSyncWorker يعمل بمفرده - لا حاجة لإلغاء Workers!
        // ═══════════════════════════════════════════════════════════════

        android.util.Log.e(TAG, "📤 [3/8] File Upload System: FileSyncWorker ONLY");
        android.util.Log.e(TAG, "   ✅ NO cancelAllWork() - Workers تستمر بشكل طبيعي");
        android.util.Log.e(TAG, "   ✅ ExistingWorkPolicy.REPLACE يمنع التكرار");

        android.util.Log.e(TAG, "📝 [4/8] Initializing UploadDatabaseHelper (FILES)...");
        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(this);
        android.util.Log.e(TAG, "✅ [4/8] UploadDatabaseHelper ready");

        // ✅ SMART RESUME LOGIC instead of resetUploadingFiles()
        android.util.Log.e(TAG, "🔄 [5/8] Checking upload state...");
        android.content.SharedPreferences prefs = getSharedPreferences("upload_state", MODE_PRIVATE);
        long lastShutdownTime = prefs.getLong("last_shutdown", 0);
        long now = System.currentTimeMillis();
        long timeSinceShutdown = now - lastShutdownTime;

        // If shutdown was more than 5 minutes ago, assume crash
        if (timeSinceShutdown > 5 * 60 * 1000 || lastShutdownTime == 0) {
            android.util.Log.w(TAG, "⚠️ App crashed or first launch - resetting stuck uploads (time since shutdown: " + (timeSinceShutdown / 1000) + "s)");
            dbHelper.resetUploadingFiles();
        } else {
            // Normal restart - RESUME!
            android.util.Log.e(TAG, "✅ Clean restart detected (time since shutdown: " + (timeSinceShutdown / 1000) + "s) - RESUMING uploads!");
            // DON'T reset - files will resume from where they stopped
        }

        // Mark current startup time for next check
        prefs.edit().putLong("last_shutdown", now).apply();

        // جدولة أي ملفات معلقة - FileSyncWorker سيتولى الرفع
        int totalFilesCount = dbHelper.getPendingFilesCount();
        android.util.Log.e(TAG, "📊 Files to upload (pending + uploading): " + totalFilesCount);

        if (totalFilesCount > 0) {
            android.util.Log.e(TAG, "📦 Found " + totalFilesCount + " files - calling FileSyncWorker.scheduleImmediateSync()");
            try {
                FileSyncWorker.scheduleImmediateSync(getApplicationContext());
                android.util.Log.e(TAG, "✅ FileSyncWorker scheduled - upload will start now!");
            } catch (Exception e) {
                android.util.Log.e(TAG, "❌ Failed to schedule FileSyncWorker: " + e.getMessage(), e);
            }
        } else {
            android.util.Log.e(TAG, "ℹ️  No files to upload");
        }

        // إضافة مراقبة دورية لملفات كل 15 دقيقة (لمعالجة الملفات المعلقة)
        android.util.Log.e(TAG, "📝 [6/8] Setting up FILES periodic check (15 min)...");
        PeriodicWorkRequest periodicUploadWork =
            new PeriodicWorkRequest.Builder(
                FileSyncWorker.class,  // ✅ استخدام FileSyncWorker الجديد مع OkHttp streaming
                15, TimeUnit.MINUTES)
            .addTag("periodic_upload_check")
            .build();

        workManager.enqueueUniquePeriodicWork(
            "periodic_upload_check",
            ExistingPeriodicWorkPolicy.KEEP,
            periodicUploadWork
        );
        android.util.Log.e(TAG, "✅ [6/8] FILES periodic check scheduled");

        // بدء مراقبة حالة الإنترنت للملفات
        android.util.Log.e(TAG, "📝 [7/8] Starting Network Monitor (FILES)...");
        NetworkMonitor networkMonitor = NetworkMonitor.getInstance(this);
        networkMonitor.startMonitoring();
        android.util.Log.e(TAG, "✅ [7/8] Network Monitor active (FILES)");

        // ═══════════════════════════════════════════════════════════════
        // نظام مزامنة البيانات (Data Sync System) - معزول تماماً
        // ═══════════════════════════════════════════════════════════════

        android.util.Log.e(TAG, "📝 [8/8] Initializing DataSyncDatabaseHelper (DATA)...");
        DataSyncDatabaseHelper dataSyncDbHelper = DataSyncDatabaseHelper.getInstance(this);
        android.util.Log.e(TAG, "✅ [8/8] DataSyncDatabaseHelper ready");

        // إضافة مراقبة دورية للبيانات كل 15 دقيقة (منفصلة عن الملفات)
        android.util.Log.e(TAG, "📝 Setting up DATA periodic check (15 min)...");
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
        android.util.Log.e(TAG, "✅ DATA periodic check scheduled");

        // بدء مراقبة حالة الإنترنت للبيانات (منفصلة عن الملفات)
        android.util.Log.e(TAG, "🌐 Starting DataSyncNetworkMonitor...");
        DataSyncNetworkMonitor dataSyncNetworkMonitor = DataSyncNetworkMonitor.getInstance(this);
        dataSyncNetworkMonitor.startMonitoring();
        android.util.Log.e(TAG, "✅ DataSyncNetworkMonitor active");

        // ❌ DISABLED TEMPORARILY - Testing WorkManager-only approach (Step 4)
        // Reason: AlarmManager polling every 10s causes battery drain, ANR, duplicate workers
        // android.util.Log.e(TAG, "⏰ Starting AlarmManager for persistent sync...");
        // org.alhayah.sponsorships.DataSyncAlarmReceiver.startAlarmManager(this);
        // android.util.Log.e(TAG, "✅ DataSyncAlarmReceiver active (10-second checks)");
        android.util.Log.w(TAG, "⚠️ DataSyncAlarmReceiver DISABLED - using WorkManager only");

        // ❌ DISABLED TEMPORARILY - Testing WorkManager-only approach (Step 4)
        // Reason: AlarmManager polling every 10s causes battery drain, ANR, duplicate workers
        // android.util.Log.e(TAG, "⏰ Starting UploadAlarmReceiver for file uploads...");
        // com.aso.app.UploadAlarmReceiver.startAlarmManager(this);
        // android.util.Log.e(TAG, "✅ UploadAlarmReceiver active (10-second aggressive checks)");
        android.util.Log.w(TAG, "⚠️ UploadAlarmReceiver DISABLED - using WorkManager only");

        // ✨ CRITICAL: بدء NetworkConnectedWorker - يُطلق فوراً عند عودة الإنترنت
        android.util.Log.e(TAG, "🌐 Starting NetworkConnectedWorker...");
        org.alhayah.sponsorships.NetworkConnectedWorker.scheduleWork(this);
        android.util.Log.e(TAG, "✅ NetworkConnectedWorker active (triggers on internet connect)");

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  ✅ v22:56 APPLICATION READY - File Size Calculation Fixed  ║");
        android.util.Log.e(TAG, "║  📁 Files: FileSyncWorker (manual + periodic 15min)           ║");
        android.util.Log.e(TAG, "║  📊 Data: DataSyncWorker (periodic 15min)                     ║");
        android.util.Log.e(TAG, "║  🌐 NetworkMonitor: Auto-triggers on internet connect        ║");
        android.util.Log.e(TAG, "║  ❌ NO cancelAllWork() - Workers persist properly             ║");
        android.util.Log.e(TAG, "║  ❌ NO BackgroundUploadWorker/UploadTaskScheduler (deleted)   ║");
        android.util.Log.e(TAG, "║  🎯 Single Upload Orchestrator: FileSyncWorker ONLY           ║");
        android.util.Log.e(TAG, "║  🌐 Default API: https://alhayahorphans.org/api/mobile/...    ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");
    }
}
