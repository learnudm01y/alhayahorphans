package com.aso.app;

import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.os.SystemClock;
import android.util.Log;

/**
 * ✨ AlarmManager لرفع الملفات بشكل دوري
 *
 * لماذا AlarmManager؟
 * - أقوى من WorkManager عندما التطبيق مغلق تماماً
 * - يعمل حتى في وضع Doze Mode (مع setExactAndAllowWhileIdle)
 * - فحص كل 10 ثوانٍ لضمان رفع سريع
 */
public class UploadAlarmReceiver extends BroadcastReceiver {
    private static final String TAG = "UploadAlarmReceiver";
    private static final long SYNC_INTERVAL_MS = 10 * 1000; // 10 ثوانٍ

    @Override
    public void onReceive(Context context, Intent intent) {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "⏰ UploadAlarmReceiver triggered - Checking for files");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);

            // فحص عدد الملفات المعلقة
            int pendingCount = dbHelper.getPendingFilesCount();
            Log.d(TAG, "📊 Total pending files: " + pendingCount);

            if (pendingCount > 0) {
                Log.d(TAG, "✅ Found " + pendingCount + " files - starting UploadForegroundService");

                Intent serviceIntent = new Intent(context, UploadForegroundService.class);

                try {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        context.startForegroundService(serviceIntent);
                    } else {
                        context.startService(serviceIntent);
                    }
                    Log.d(TAG, "🚀 UploadForegroundService started from AlarmManager");
                } catch (IllegalStateException | SecurityException e) {
                    // Android 12+ ForegroundServiceStartNotAllowedException
                    Log.e(TAG, "⚠️ Cannot start FGS from background: " + e.getMessage());
                    Log.d(TAG, "🔄 Using WorkManager fallback...");

                    // Fallback: Use WorkManager which is allowed from background
                    UploadTaskScheduler scheduler = UploadTaskScheduler.getInstance(context);
                    scheduler.scheduleUploadTask();
                    Log.d(TAG, "✅ Upload scheduled via WorkManager");
                }
            } else {
                Log.d(TAG, "ℹ️  No pending files - skipping upload");
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
        Log.d(TAG, "🔧 Starting AlarmManager for file upload");

        AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
        if (alarmManager == null) {
            Log.e(TAG, "❌ AlarmManager not available");
            return;
        }

        Intent intent = new Intent(context, UploadAlarmReceiver.class);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
                context,
                12348, // رقم فريد مختلف عن Data Sync
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
            Log.d(TAG, "✅ AlarmManager scheduled with setExactAndAllowWhileIdle (Doze-compatible)");
        } else {
            // Android < 6.0
            alarmManager.setExact(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    triggerTime,
                    pendingIntent
            );
            Log.d(TAG, "✅ AlarmManager scheduled with setExact");
        }

        Log.d(TAG, "⏰ Next alarm in 10 seconds (aggressive upload check)");
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

        Intent intent = new Intent(context, UploadAlarmReceiver.class);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
                context,
                12348,
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
}
