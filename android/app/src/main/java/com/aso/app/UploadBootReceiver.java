package com.aso.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.util.Log;

/**
 * استقبال حدث إعادة تشغيل الجهاز وإعادة جدولة مهام الرفع
 */
public class UploadBootReceiver extends BroadcastReceiver {
    private static final String TAG = "UploadBootReceiver";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
            Log.d(TAG, "📱 تم إعادة تشغيل الجهاز - إعادة جدولة مهام الرفع");

            try {
                // الحصول على مدير قاعدة البيانات
                UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);

                // إعادة تعيين الملفات التي كانت قيد الرفع
                dbHelper.resetUploadingFiles();

                // جدولة مهمة الرفع (استخدام WorkManager الآمن بدلاً من Foreground Service)
                UploadTaskScheduler scheduler = UploadTaskScheduler.getInstance(context);
                scheduler.scheduleUploadTask();

                // ✨ إعادة تفعيل AlarmManager
                UploadAlarmReceiver.startAlarmManager(context);

                Log.d(TAG, "✅ تمت إعادة جدولة مهام الرفع - النظام يعمل");

            } catch (Exception e) {
                Log.e(TAG, "❌ خطأ في إعادة جدولة المهام: " + e.getMessage(), e);
            }
        }
    }
}
