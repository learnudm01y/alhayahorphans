package org.alhayah.sponsorships;

import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

/**
 * WorkManager Worker للفحص الدوري للبيانات المنتظرة للمزامنة
 * يعمل كل 15 دقيقة - معزول تماماً عن BackgroundUploadWorker (الملفات)
 *
 * المهمة: فحص قاعدة البيانات → إذا وُجدت بيانات منتظرة → بدء DataSyncForegroundService
 */
public class DataSyncWorker extends Worker {
    private static final String TAG = "DataSyncWorker";

    public DataSyncWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🔄 DataSyncWorker - Periodic check for pending data");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            Context context = getApplicationContext();
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            // ✨ CRITICAL FIX: إعادة تعيين البيانات الفاشلة قبل الفحص
            int resetCount = dbHelper.resetFailedData();
            if (resetCount > 0) {
                Log.d(TAG, "🔄 Reset " + resetCount + " failed items to pending");
            }

            // فحص عدد البيانات المنتظرة
            int pendingCount = dbHelper.getPendingDataCount();

            Log.d(TAG, "📊 Total pending data count: " + pendingCount);

            if (pendingCount > 0) {
                Log.d(TAG, "✅ Found " + pendingCount + " pending data items - starting DataSyncForegroundService");

                // بدء الخدمة
                Intent serviceIntent = new Intent(context, DataSyncForegroundService.class);

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    context.startForegroundService(serviceIntent);
                } else {
                    context.startService(serviceIntent);
                }

                Log.d(TAG, "🚀 DataSyncForegroundService started successfully");
            } else {
                Log.d(TAG, "ℹ️ No pending data - skipping sync service");
            }

            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "✅ DataSyncWorker completed successfully");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "❌ DataSyncWorker failed with exception", e);

            // إعادة المحاولة في حالة الفشل
            return Result.retry();
        }
    }
}
