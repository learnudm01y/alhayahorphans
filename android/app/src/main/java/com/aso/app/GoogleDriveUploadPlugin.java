package com.aso.app;

import android.content.Context;
import android.content.Intent;
import android.util.Log;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Plugin لإدارة رفع الملفات إلى Google Drive في الخلفية
 * يعيد تشغيل خدمة الرفع ويعالج الملفات العالقة
 */
@CapacitorPlugin(name = "GoogleDriveUpload")
public class GoogleDriveUploadPlugin extends Plugin {
    private static final String TAG = "GoogleDriveUploadPlugin";

    /**
     * تفعيل/إعادة تشغيل خدمة رفع الملفات في الخلفية
     * يُستدعى من JavaScript عند الضغط على زر "رفع الملفات إلى Google Drive"
     */
    @PluginMethod
    public void startBackgroundUpload(PluginCall call) {
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  🔄 startBackgroundUpload() - تفعيل رفع Google Drive        ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        Log.e(TAG, "⏱️  Timestamp: " + System.currentTimeMillis());
        Log.e(TAG, "🧵 Thread: " + Thread.currentThread().getName());

        try {
            Context context = getContext();

            // 1. التحقق من عدد الملفات المعلقة
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);
            int pendingCount = dbHelper.getPendingFilesCount();

            Log.e(TAG, "📊 عدد الملفات المعلقة: " + pendingCount);

            if (pendingCount == 0) {
                Log.e(TAG, "✅ لا توجد ملفات معلقة للرفع");

                JSObject ret = new JSObject();
                ret.put("success", true);
                ret.put("message", "لا توجد ملفات معلقة للرفع");
                ret.put("pendingFiles", 0);
                call.resolve(ret);
                return;
            }

            // 2. بدء خدمة الرفع في الخلفية
            Log.e(TAG, "🚀 بدء UploadForegroundService...");
            Log.e(TAG, "   ├─ عدد الملفات: " + pendingCount);
            Log.e(TAG, "   └─ Service سيعمل حتى عند إغلاق التطبيق");

            SyncOrchestrator.scheduleUpload(context);
            Log.e(TAG, "✅ SyncOrchestrator.scheduleUpload() called");

            Log.e(TAG, "✅ تم بدء خدمة الرفع بنجاح");
            Log.e(TAG, "📤 الخدمة ستحاول رفع جميع الملفات المعلقة");

            // 3. إرجاع النتيجة للـ JavaScript
            JSObject ret = new JSObject();
            ret.put("success", true);
            ret.put("message", "تم تفعيل خدمة الرفع في الخلفية");
            ret.put("pendingFiles", pendingCount);
            ret.put("serviceStarted", true);

            call.resolve(ret);

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في تفعيل خدمة الرفع: " + e.getMessage());
            e.printStackTrace();

            JSObject ret = new JSObject();
            ret.put("success", false);
            ret.put("message", "فشل تفعيل خدمة الرفع: " + e.getMessage());
            call.reject("فشل تفعيل خدمة الرفع", e);
        }
    }

    /**
     * الحصول على حالة خدمة الرفع وعدد الملفات المعلقة
     */
    @PluginMethod
    public void getUploadStatus(PluginCall call) {
        Log.e(TAG, "📊 getUploadStatus() - الحصول على حالة الرفع");

        try {
            Context context = getContext();
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);

            int pendingCount = dbHelper.getPendingFilesCount();
            int uploadedCount = dbHelper.getUploadedFilesCount();
            int failedCount = dbHelper.getFailedFilesCount();

            Log.e(TAG, "📊 إحصائيات الرفع:");
            Log.e(TAG, "   ├─ معلق: " + pendingCount);
            Log.e(TAG, "   ├─ مرفوع: " + uploadedCount);
            Log.e(TAG, "   └─ فشل: " + failedCount);

            JSObject ret = new JSObject();
            ret.put("success", true);
            ret.put("pendingFiles", pendingCount);
            ret.put("uploadedFiles", uploadedCount);
            ret.put("failedFiles", failedCount);
            ret.put("totalFiles", pendingCount + uploadedCount + failedCount);

            call.resolve(ret);

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في الحصول على الحالة: " + e.getMessage());
            call.reject("فشل الحصول على الحالة", e);
        }
    }

    /**
     * إعادة محاولة رفع الملفات الفاشلة
     */
    @PluginMethod
    public void retryFailedUploads(PluginCall call) {
        Log.e(TAG, "🔄 retryFailedUploads() - إعادة محاولة رفع الملفات الفاشلة");

        try {
            Context context = getContext();
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);

            // إعادة تعيين حالة الملفات الفاشلة إلى pending
            int retriedCount = dbHelper.resetFailedFiles();

            Log.e(TAG, "🔄 تم إعادة " + retriedCount + " ملف إلى قائمة الانتظار");

            if (retriedCount > 0) {
                // بدء الخدمة لرفعها
                SyncOrchestrator.scheduleUpload(context);

                Log.e(TAG, "✅ تم بدء خدمة الرفع لإعادة المحاولة");
            }

            JSObject ret = new JSObject();
            ret.put("success", true);
            ret.put("message", "تم إعادة محاولة " + retriedCount + " ملف");
            ret.put("retriedFiles", retriedCount);

            call.resolve(ret);

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في إعادة المحاولة: " + e.getMessage());
            call.reject("فشل إعادة المحاولة", e);
        }
    }
}
