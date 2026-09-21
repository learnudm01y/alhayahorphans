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

            // ✨ CRITICAL ARCHITECTURE FIX 1: Real Ping Check
            // لا نثق بـ Android، يجب أن نتأكد من وجود إنترنت حقيقي قبل عمل أي شيء!
            if (!com.aso.app.InternetUtils.isInternetActuallyAvailable(context)) {
                Log.e(TAG, "❌ False alarm! Network is connected but no ACTUAL internet access (Ping failed). Aborting sync.");
                return Result.retry();
            }

            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            // ✨ CRITICAL ARCHITECTURE FIX: Trigger Unified Master Sync Chain
            // This runs: DataSyncForegroundService -> DriveStatusWorker -> SponsorshipSyncWorker -> PrepareUploadsWorker -> ChunkedUploadWorker
            Log.d(TAG, "📦 Scheduling Unified Master Sync Chain on network reconnect");
            com.aso.app.SyncOrchestrator.scheduleMasterSyncOnReconnect(context);

            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "✅ NetworkConnectedWorker unified sync scheduled successfully");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ NetworkConnectedWorker failed", e);
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

        OneTimeWorkRequest.Builder workRequestBuilder = new OneTimeWorkRequest.Builder(NetworkConnectedWorker.class)
                .setConstraints(constraints)
                .addTag("network_connected_sync");
                
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            workRequestBuilder.setExpedited(androidx.work.OutOfQuotaPolicy.RUN_AS_NON_EXPEDITED_WORK_REQUEST);
        }

        OneTimeWorkRequest workRequest = workRequestBuilder.build();

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
}
