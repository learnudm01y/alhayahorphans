package org.alhayah.sponsorships;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

/**
 * BroadcastReceiver للاستماع لحدث BOOT_COMPLETED
 * يبدأ خدمة المزامنة تلقائياً عند إعادة تشغيل الجهاز
 * معزول تماماً عن UploadBootReceiver (الملفات)
 *
 * يتطلب صلاحية RECEIVE_BOOT_COMPLETED في AndroidManifest.xml
 */
public class DataSyncBootReceiver extends BroadcastReceiver {
    private static final String TAG = "DataSyncBootReceiver";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "🔌 BOOT_COMPLETED received - Device rebooted");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            try {
                DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

                // ✨ إعادة تعيين البيانات الفاشلة قبل الفحص
                int resetCount = dbHelper.resetFailedData();
                if (resetCount > 0) {
                    Log.d(TAG, "🔄 Reset " + resetCount + " failed items to pending after boot");
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

                    Log.d(TAG, "🚀 DataSyncForegroundService started after boot");
                } else {
                    Log.d(TAG, "ℹ️ No pending data - skipping sync service");
                }

                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log.d(TAG, "✅ DataSyncBootReceiver completed");
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            } catch (Exception e) {
                Log.e(TAG, "❌ Failed to handle BOOT_COMPLETED", e);
            }
        }
    }
}
