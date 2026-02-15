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

                // جدولة FileSyncWorker (بدلاً من UploadTaskScheduler القديم)
                Log.d(TAG, "🚀 Calling FileSyncWorker.scheduleImmediateSync()...");
                FileSyncWorker.scheduleImmediateSync(context);
                Log.d(TAG, "✅ FileSyncWorker scheduled - سيبدأ بمعالجة الملفات المعلقة");

            } catch (Exception e) {
                Log.e(TAG, "❌ خطأ في إعادة جدولة المهام: " + e.getMessage(), e);
            }
        }
    }
}
