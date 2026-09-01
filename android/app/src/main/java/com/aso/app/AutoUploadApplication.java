package com.aso.app;

import android.app.Application;
import android.content.SharedPreferences;
import android.util.Log;
import androidx.work.Configuration;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import java.util.concurrent.TimeUnit;
import org.alhayah.sponsorships.DataSyncDatabaseHelper;
import org.alhayah.sponsorships.DataSyncWorker;
import org.alhayah.sponsorships.NetworkConnectedWorker;

public class AutoUploadApplication extends Application {
    private static final String TAG = "AutoUploadApp";

    @Override
    public void onCreate() {
        super.onCreate();

        WorkManager workManager = null;
        try {
            Configuration config = new Configuration.Builder()
                .setMinimumLoggingLevel(android.util.Log.INFO)
                .build();
            WorkManager.initialize(getApplicationContext(), config);
            workManager = WorkManager.getInstance(getApplicationContext());
        } catch (IllegalStateException e) {
            workManager = WorkManager.getInstance(getApplicationContext());
        }

        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(this);

        // ⚠️ كان هنا شرط زمني معطوب: "last_shutdown" يُكتب عند كل إقلاع، لا عند
        // الإغلاق، فالمفتاح يحمل زمن آخر تشغيل. النتيجة أن التحرير كان يُعطَّل
        // تحديداً على الأجهزة النشِطة التي تفتح التطبيق كثيراً — وهي أكثر
        // الأجهزة عرضةً لتعليق الملفات. أُزيل الشرط: التحرير الآن زمني بحت
        // داخل reclaimStaleUploads (لا يمسّ إلا ما توقّف فعلاً).
        SharedPreferences prefs = getSharedPreferences("upload_state", MODE_PRIVATE);
        prefs.edit().putLong("last_start", System.currentTimeMillis()).apply();

        int reclaimed = dbHelper.reclaimStaleUploads() + dbHelper.reclaimStaleProcessing();
        if (reclaimed > 0) {
            Log.w(TAG, "♻️ أُعيد " + reclaimed + " ملف عالق إلى طابور الرفع عند الإقلاع");
        }

        int filesCount = dbHelper.getPendingFilesCount();
        if (filesCount > 0) {
            com.aso.app.UploadTaskScheduler.getInstance(getApplicationContext()).startImmediateUpload();
        }

        com.aso.app.UploadTaskScheduler.getInstance(getApplicationContext()).scheduleUploadTask();

        // شبكة الأمان الأخيرة: مهمة دورية تُنعش الطابور حتى لو لم يقع أي حدث.
        com.aso.app.UploadTaskScheduler.getInstance(getApplicationContext()).schedulePeriodicUploadSweep();

        DataSyncDatabaseHelper dataSyncDbHelper = DataSyncDatabaseHelper.getInstance(this);

        PeriodicWorkRequest periodicDataCheck =
            new PeriodicWorkRequest.Builder(DataSyncWorker.class, 15, TimeUnit.MINUTES)
                .addTag("periodic_data_sync_check")
                .build();
        workManager.enqueueUniquePeriodicWork(
            "periodic_data_sync_check",
            ExistingPeriodicWorkPolicy.KEEP,
            periodicDataCheck
        );

        // 🚀 أتمتة المزامنة بالكامل - تحميل الكفالات والبيانات بشكل دوري ذاتي
        PeriodicWorkRequest periodicSponsorshipsSync =
            new PeriodicWorkRequest.Builder(com.aso.app.SponsorshipSyncWorker.class, 15, TimeUnit.MINUTES)
                .addTag("periodic_sponsorships_sync")
                .build();
        workManager.enqueueUniquePeriodicWork(
            "periodic_sponsorships_sync",
            ExistingPeriodicWorkPolicy.KEEP,
            periodicSponsorshipsSync
        );

        // 🚀 تشغيل مزامنة لمرة واحدة فورا عند إقلاع التطبيق
        androidx.work.OneTimeWorkRequest immediateSponsorshipSync = 
            new androidx.work.OneTimeWorkRequest.Builder(com.aso.app.SponsorshipSyncWorker.class).build();
        workManager.enqueueUniqueWork(
            "FullSyncWork", // Same name as in JS Plugin to prevent duplicates!
            androidx.work.ExistingWorkPolicy.REPLACE,
            immediateSponsorshipSync
        );

        NetworkConnectedWorker.scheduleWork(this);

        // ✨ CRITICAL FIX: Start DataSyncNetworkMonitor to listen for internet connectivity changes
        // This ensures the app can sync in the background when internet returns without opening the app.
        org.alhayah.sponsorships.DataSyncNetworkMonitor.getInstance(this).startMonitoring();

        // 🆕 Start File Network Monitor to handle file upload auto-retries on internet reconnect
        com.aso.app.NetworkMonitor.getInstance(this).startMonitoring();
    }
}
