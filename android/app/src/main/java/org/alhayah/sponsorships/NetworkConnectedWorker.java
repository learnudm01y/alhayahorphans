package org.alhayah.sponsorships;

import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.work.Constraints;
import androidx.work.ExistingWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

/**
 * ✨ CRITICAL: NetworkConnectedWorker - يُطلق فوراً عند عودة الإنترنت
 * 
 * لماذا هذا الحل؟
 * - CONNECTIVITY_ACTION deprecated منذ Android 7.0 ولا يعمل مع التطبيقات المغلقة
 * - WorkManager مع NetworkConstraint يعمل حتى عند إغلاق التطبيق
 * - Android system يُطلق Worker تلقائياً عند اتصال الإنترنت
 * 
 * كيف يعمل؟
 * 1. يُجدول Worker مع constraint: NetworkType.CONNECTED
 * 2. عند عودة الإنترنت، Android يُطلق Worker تلقائياً
 * 3. Worker يعيد تعيين البيانات الفاشلة ويبدأ المزامنة
 * 4. بعد الانتهاء، يُجدول نفسه مرة أخرى (self-scheduling)
 */
public class NetworkConnectedWorker extends Worker {
    private static final String TAG = "NetworkConnectedWorker";
    private static final String WORK_NAME = "network_connected_sync";

    public NetworkConnectedWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🌐 INTERNET CONNECTED - NetworkConnectedWorker triggered");
        Log.d(TAG, "ℹ️  App state: POSSIBLY CLOSED (WorkManager)");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            Context context = getApplicationContext();
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            // ✨ إعادة تعيين جميع البيانات الفاشلة
            int resetCount = dbHelper.resetFailedData();
            if (resetCount > 0) {
                Log.d(TAG, "🔄 Reset " + resetCount + " failed items to pending");
            }

            // فحص عدد البيانات المنتظرة
            int pendingCount = dbHelper.getPendingDataCount();
            Log.d(TAG, "📊 Total pending data count: " + pendingCount);

            if (pendingCount > 0) {
                Log.d(TAG, "✅ Found " + pendingCount + " pending items - starting DataSyncForegroundService");

                // بدء الخدمة
                Intent serviceIntent = new Intent(context, DataSyncForegroundService.class);

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    context.startForegroundService(serviceIntent);
                } else {
                    context.startService(serviceIntent);
                }

                Log.d(TAG, "🚀 DataSyncForegroundService started from CLOSED app (WorkManager)");
            } else {
                Log.d(TAG, "ℹ️  No pending data - skipping sync");
            }

            // ✅ إعادة جدولة Worker للمرة القادمة
            scheduleNextWork(context);

            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "✅ NetworkConnectedWorker completed successfully");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ NetworkConnectedWorker failed", e);
            
            // إعادة جدولة Worker حتى في حالة الفشل
            scheduleNextWork(getApplicationContext());
            
            return Result.failure();
        }
    }

    /**
     * ✨ جدولة Worker - يُستدعى من AutoUploadApplication وبعد كل تنفيذ
     */
    public static void scheduleWork(Context context) {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🔧 Scheduling NetworkConnectedWorker");

        // ✨ CRITICAL: NetworkType.CONNECTED - يُطلق فوراً عند اتصال الإنترنت
        Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

        OneTimeWorkRequest workRequest = new OneTimeWorkRequest.Builder(NetworkConnectedWorker.class)
                .setConstraints(constraints)
                .addTag("network_connected_sync")
                .build();

        // استبدال أي work سابق
        WorkManager.getInstance(context).enqueueUniqueWork(
                WORK_NAME,
                ExistingWorkPolicy.REPLACE,
                workRequest
        );

        Log.d(TAG, "✅ NetworkConnectedWorker scheduled");
        Log.d(TAG, "⏳ Will trigger IMMEDIATELY when internet connects");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    /**
     * ✨ إعادة جدولة Worker بعد التنفيذ (self-scheduling)
     */
    private static void scheduleNextWork(Context context) {
        Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

        OneTimeWorkRequest workRequest = new OneTimeWorkRequest.Builder(NetworkConnectedWorker.class)
                .setConstraints(constraints)
                .addTag("network_connected_sync")
                .build();

        WorkManager.getInstance(context).enqueueUniqueWork(
                WORK_NAME,
                ExistingWorkPolicy.REPLACE,
                workRequest
        );

        Log.d(TAG, "🔄 NetworkConnectedWorker re-scheduled for next internet connection");
    }
}
