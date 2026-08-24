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
        String action = intent != null ? intent.getAction() : null;
        boolean isBoot = Intent.ACTION_BOOT_COMPLETED.equals(action);
        boolean isUpdate = "android.intent.action.MY_PACKAGE_REPLACED".equals(action);

        if (!isBoot && !isUpdate) return;

        Log.d(TAG, "📱 " + (isBoot ? "إعادة تشغيل الجهاز" : "تحديث التطبيق") + " - إنعاش مهام الرفع");

        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);

            // بعد إعادة التشغيل لا يمكن أن يكون هناك عامل جارٍ فعلاً، فالتحرير
            // الشامل آمن هنا — على عكس استدعائه أثناء عمل التطبيق.
            dbHelper.resetUploadingFiles();
            dbHelper.reclaimStaleProcessing();

            UploadTaskScheduler scheduler = UploadTaskScheduler.getInstance(context);
            scheduler.startImmediateUpload();
            // إعادة تثبيت المهمة الدورية: WorkManager يُبقيها عبر إعادة التشغيل
            // عادةً، لكن KEEP تجعل الاستدعاء آمناً ومجانياً إن كانت موجودة.
            scheduler.schedulePeriodicUploadSweep();

            Log.d(TAG, "✅ أُعيدت جدولة الرفع بعد الإقلاع");

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في إعادة جدولة المهام: " + e.getMessage(), e);
        }
    }
}
