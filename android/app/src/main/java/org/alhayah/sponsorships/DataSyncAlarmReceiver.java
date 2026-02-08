package org.alhayah.sponsorships;

import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.os.SystemClock;
import android.util.Log;

/**
 * ✨ CRITICAL: AlarmManager للمزامنة الدورية القوية
 *
 * لماذا AlarmManager؟
 * - أقوى من WorkManager في Android < 12
 * - يعمل حتى في وضع Doze Mode (مع setExactAndAllowWhileIdle)
 * - لا يتأثر بإغلاق التطبيق من Recent Apps
 *
 * المهمة:
 * - فحص البيانات المعلقة كل 15 دقيقة
 * - إعادة تعيين البيانات الفاشلة
 * - بدء DataSyncForegroundService
 */
public class DataSyncAlarmReceiver extends BroadcastReceiver {
    private static final String TAG = "DataSyncAlarmReceiver";
    private static final long SYNC_INTERVAL_MS = 10 * 1000; // ✨ 10 ثوانٍ - للفحص السريع
    private static final long IMMEDIATE_TRIGGER_MS = 5 * 1000; // 5 ثوانٍ للتشغيل الفوري

    @Override
    public void onReceive(Context context, Intent intent) {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "⏰ AlarmManager triggered - Periodic sync check");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            // ✨ إعادة تعيين البيانات الفاشلة
            int resetCount = dbHelper.resetFailedData();
            if (resetCount > 0) {
                Log.d(TAG, "🔄 Reset " + resetCount + " failed items to pending");
            }

            // فحص عدد البيانات المنتظرة
            int pendingCount = dbHelper.getPendingDataCount();
            Log.d(TAG, "📊 Total pending data count: " + pendingCount);

            if (pendingCount > 0) {
                Log.d(TAG, "✅ Found " + pendingCount + " items - starting DataSyncForegroundService");

                Intent serviceIntent = new Intent(context, DataSyncForegroundService.class);

                try {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        context.startForegroundService(serviceIntent);
                    } else {
                        context.startService(serviceIntent);
                    }
                    Log.d(TAG, "🚀 DataSyncForegroundService started from AlarmManager");
                } catch (IllegalStateException | SecurityException e) {
                    // Android 12+ ForegroundServiceStartNotAllowedException
                    Log.e(TAG, "⚠️ Cannot start FGS from background: " + e.getMessage());
                    Log.d(TAG, "🔄 Using WorkManager fallback...");

                    // Fallback: Use DataSyncWorker
                    androidx.work.OneTimeWorkRequest syncWork =
                        new androidx.work.OneTimeWorkRequest.Builder(DataSyncWorker.class)
                            .addTag("alarm_fallback_sync")
                            .build();
                    androidx.work.WorkManager.getInstance(context).enqueue(syncWork);
                    Log.d(TAG, "✅ Sync scheduled via WorkManager");
                }
            } else {
                Log.d(TAG, "ℹ️  No pending data - skipping sync");
            }

            // ✅ إعادة جدولة المنبه التالي
            scheduleNextAlarm(context);

            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "✅ AlarmManager check completed");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        } catch (Exception e) {
            Log.e(TAG, "❌ AlarmManager check failed", e);
            // إعادة جدولة المنبه حتى في حالة الفشل
            scheduleNextAlarm(context);
        }
    }

    /**
     * ✨ بدء AlarmManager - يُستدعى من AutoUploadApplication
     */
    public static void startAlarmManager(Context context) {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🔧 Starting AlarmManager for data sync");

        AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
        if (alarmManager == null) {
            Log.e(TAG, "❌ AlarmManager not available");
            return;
        }

        Intent intent = new Intent(context, DataSyncAlarmReceiver.class);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
                context,
                12346, // رقم فريد مختلف عن WorkManager
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        // إلغاء أي منبهات سابقة
        alarmManager.cancel(pendingIntent);

        // جدولة المنبه التالي
        long triggerTime = SystemClock.elapsedRealtime() + SYNC_INTERVAL_MS;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            // Android 6.0+ - يعمل حتى في Doze Mode
            alarmManager.setExactAndAllowWhileIdle(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    triggerTime,
                    pendingIntent
            );
            Log.d(TAG, "✅ AlarmManager scheduled with setExactAndAllowWhileIdle (works in Doze)");
        } else {
            // Android < 6.0
            alarmManager.setExact(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    triggerTime,
                    pendingIntent
            );
            Log.d(TAG, "✅ AlarmManager scheduled with setExact");
        }

        Log.d(TAG, "⏰ Next alarm in 10 seconds (aggressive retry)");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    /**
     * ✨ جدولة المنبه التالي بعد تنفيذ الفحص
     */
    private static void scheduleNextAlarm(Context context) {
        AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
        if (alarmManager == null) {
            return;
        }

        Intent intent = new Intent(context, DataSyncAlarmReceiver.class);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
                context,
                12346,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        long triggerTime = SystemClock.elapsedRealtime() + SYNC_INTERVAL_MS;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            alarmManager.setExactAndAllowWhileIdle(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    triggerTime,
                    pendingIntent
            );
        } else {
            alarmManager.setExact(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    triggerTime,
                    pendingIntent
            );
        }

        Log.d(TAG, "🔄 Next alarm scheduled in 10 seconds");
    }

    /**
     * ✨ CRITICAL: جدولة alarm فوري عند حفظ البيانات أو الفشل
     * يُستدعى من JavaScriptBridge بعد حفظ البيانات مباشرة
     */
    public static void scheduleImmediateCheck(Context context) {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "⚡ Scheduling IMMEDIATE alarm (5 sec) for new data");

        AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
        if (alarmManager == null) {
            Log.e(TAG, "❌ AlarmManager not available");
            return;
        }

        Intent intent = new Intent(context, DataSyncAlarmReceiver.class);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
                context,
                12347, // رقم فريد مختلف للـ immediate alarm
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        // جدولة alarm بعد 5 ثوانٍ فقط
        long triggerTime = SystemClock.elapsedRealtime() + IMMEDIATE_TRIGGER_MS;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            alarmManager.setExactAndAllowWhileIdle(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    triggerTime,
                    pendingIntent
            );
            Log.d(TAG, "✅ Immediate alarm scheduled in 5 seconds (Doze-compatible)");
        } else {
            alarmManager.setExact(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    triggerTime,
                    pendingIntent
            );
            Log.d(TAG, "✅ Immediate alarm scheduled in 5 seconds");
        }

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }
}
