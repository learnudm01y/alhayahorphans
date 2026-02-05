package org.alhayah.sponsorships;

import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.PowerManager;
import android.provider.Settings;
import android.util.Log;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

@CapacitorPlugin(name = "BackgroundSync")
public class BackgroundSyncPlugin extends Plugin {
    private static final String TAG = "BackgroundSyncPlugin";

    /**
     * إضافة بيانات جديدة لقائمة المزامنة (من JavaScript)
     */
    @PluginMethod
    public void addDataToQueue(PluginCall call) {
        try {
            String dataType = call.getString("dataType");
            String dataJson = call.getString("dataJson");
            String endpoint = call.getString("endpoint");

            if (dataType == null || dataJson == null || endpoint == null) {
                call.reject("Missing required parameters: dataType, dataJson, endpoint");
                return;
            }

            Context context = getContext();
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            long id = dbHelper.addDataToQueue(dataType, dataJson, endpoint);

            if (id > 0) {
                Log.d(TAG, "✅ Data added to queue: ID=" + id);

                JSObject result = new JSObject();
                result.put("success", true);
                result.put("id", id);
                result.put("message", "تمت إضافة البيانات لقائمة المزامنة");
                call.resolve(result);
            } else {
                call.reject("فشل في إضافة البيانات");
            }

        } catch (Exception e) {
            Log.e(TAG, "Failed to add data to queue", e);
            call.reject("خطأ في إضافة البيانات: " + e.getMessage());
        }
    }

    /**
     * بدء خدمة المزامنة يدوياً
     */
    @PluginMethod
    public void startService(PluginCall call) {
        try {
            Context context = getContext();
            Intent serviceIntent = new Intent(context, DataSyncForegroundService.class);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(serviceIntent);
            } else {
                context.startService(serviceIntent);
            }

            Log.d(TAG, "DataSyncForegroundService started");

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("message", "تم بدء خدمة المزامنة في الخلفية");
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to start service", e);
            call.reject("فشل في بدء الخدمة: " + e.getMessage());
        }
    }

    /**
     * الحصول على إحصائيات المزامنة
     */
    @PluginMethod
    public void getSyncStatus(PluginCall call) {
        try {
            Context context = getContext();
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            int pending = dbHelper.getPendingDataCount();
            int uploaded = dbHelper.getUploadedDataCount();
            int failed = dbHelper.getFailedDataCount();

            JSObject result = new JSObject();
            result.put("pending", pending);
            result.put("uploaded", uploaded);
            result.put("failed", failed);
            result.put("total", pending + uploaded + failed);

            Log.d(TAG, "📊 Status: Pending=" + pending + ", Uploaded=" + uploaded + ", Failed=" + failed);

            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to get sync status", e);
            call.reject("فشل في قراءة الحالة: " + e.getMessage());
        }
    }

    /**
     * إعادة محاولة البيانات الفاشلة
     */
    @PluginMethod
    public void retryFailedData(PluginCall call) {
        try {
            Context context = getContext();
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            int count = dbHelper.retryFailedData();

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("retried", count);
            result.put("message", "تمت إعادة " + count + " عنصر فاشل إلى قائمة الانتظار");

            Log.d(TAG, "🔄 Retried " + count + " failed items");

            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to retry failed data", e);
            call.reject("فشل في إعادة المحاولة: " + e.getMessage());
        }
    }

    /**
     * حذف البيانات المُزامنة بنجاح
     */
    @PluginMethod
    public void clearCompletedData(PluginCall call) {
        try {
            Context context = getContext();
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            int count = dbHelper.clearCompletedData();

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("cleared", count);
            result.put("message", "تم حذف " + count + " عنصر مكتمل");

            Log.d(TAG, "🗑️ Cleared " + count + " completed items");

            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to clear completed data", e);
            call.reject("فشل في الحذف: " + e.getMessage());
        }
    }


    @PluginMethod
    public void stopService(PluginCall call) {
        try {
            Context context = getContext();
            Intent serviceIntent = new Intent(context, DataSyncForegroundService.class);
            context.stopService(serviceIntent);

            Log.d(TAG, "DataSyncForegroundService stopped");

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("message", "تم إيقاف خدمة المزامنة");
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to stop service", e);
            call.reject("فشل في إيقاف الخدمة: " + e.getMessage());
        }
    }

    /**
     * تحديث شريط التقدم (Legacy - للتوافق مع الكود القديم)
     * الآن يستخدم DataSyncForegroundService بدلاً من BackgroundSyncService
     */
    @PluginMethod
    public void updateProgress(PluginCall call) {
        try {
            // هذه الوظيفة الآن للتوافق مع الكود القديم فقط
            // DataSyncForegroundService يدير التقدم تلقائياً من خلال Database

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("message", "DataSyncForegroundService يدير التقدم تلقائياً");
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "updateProgress (legacy)", e);
            call.reject("updateProgress is now automatic");
        }
    }

    /**
     * تعيين وضع التحميل الدائري (Legacy)
     */
    @PluginMethod
    public void setIndeterminate(PluginCall call) {
        try {
            JSObject result = new JSObject();
            result.put("success", true);
            result.put("message", "DataSyncForegroundService يدير الإشعارات تلقائياً");
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "setIndeterminate (legacy)", e);
            call.reject("setIndeterminate is now automatic");
        }
    }

    /**
     * إظهار إشعار الاكتمال (Legacy)
     */
    @PluginMethod
    public void showComplete(PluginCall call) {
        try {
            JSObject result = new JSObject();
            result.put("success", true);
            result.put("message", "DataSyncForegroundService يدير الإشعارات تلقائياً");
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "showComplete (legacy)", e);
            call.reject("showComplete is now automatic");
        }
    }

    @PluginMethod
    public void isIgnoringBatteryOptimizations(PluginCall call) {
        try {
            Context context = getContext();
            PowerManager pm = (PowerManager) context.getSystemService(Context.POWER_SERVICE);

            boolean isIgnoring = false;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                isIgnoring = pm.isIgnoringBatteryOptimizations(context.getPackageName());
            } else {
                isIgnoring = true; // قبل Android M لا توجد قيود
            }

            JSObject result = new JSObject();
            result.put("isIgnoring", isIgnoring);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to check battery optimization", e);
            call.reject("فشل في التحقق من تحسين البطارية: " + e.getMessage());
        }
    }

    @PluginMethod
    public void requestIgnoreBatteryOptimizations(PluginCall call) {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                Context context = getContext();
                Intent intent = new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                intent.setData(Uri.parse("package:" + context.getPackageName()));
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                context.startActivity(intent);

                JSObject result = new JSObject();
                result.put("success", true);
                result.put("message", "تم فتح إعدادات البطارية");
                call.resolve(result);
            } else {
                JSObject result = new JSObject();
                result.put("success", true);
                result.put("message", "لا حاجة لإعدادات البطارية في هذا الإصدار");
                call.resolve(result);
            }

        } catch (Exception e) {
            Log.e(TAG, "Failed to request battery optimization exemption", e);
            call.reject("فشل في طلب استثناء البطارية: " + e.getMessage());
        }
    }
}
