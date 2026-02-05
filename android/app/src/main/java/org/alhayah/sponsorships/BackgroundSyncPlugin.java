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

    @PluginMethod
    public void startService(PluginCall call) {
        try {
            Context context = getContext();
            Intent serviceIntent = new Intent(context, BackgroundSyncService.class);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(serviceIntent);
            } else {
                context.startService(serviceIntent);
            }

            Log.d(TAG, "Background sync service started");

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("message", "تم بدء خدمة المزامنة في الخلفية");
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to start service", e);
            call.reject("فشل في بدء الخدمة: " + e.getMessage());
        }
    }

    @PluginMethod
    public void stopService(PluginCall call) {
        try {
            Context context = getContext();
            Intent serviceIntent = new Intent(context, BackgroundSyncService.class);
            context.stopService(serviceIntent);

            Log.d(TAG, "Background sync service stopped");

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
     * Update progress bar in notification
     */
    @PluginMethod
    public void updateProgress(PluginCall call) {
        try {
            int progress = call.getInt("progress", 0);
            int max = call.getInt("max", 100);
            String status = call.getString("status", "جاري المزامنة...");

            Context context = getContext();
            Intent intent = new Intent(context, BackgroundSyncService.class);
            intent.setAction(BackgroundSyncService.ACTION_UPDATE_PROGRESS);
            intent.putExtra(BackgroundSyncService.EXTRA_PROGRESS, progress);
            intent.putExtra(BackgroundSyncService.EXTRA_MAX, max);
            intent.putExtra(BackgroundSyncService.EXTRA_STATUS, status);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent);
            } else {
                context.startService(intent);
            }

            Log.d(TAG, "Progress updated: " + progress + "/" + max);

            JSObject result = new JSObject();
            result.put("success", true);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to update progress", e);
            call.reject("فشل في تحديث التقدم: " + e.getMessage());
        }
    }

    /**
     * Set notification to indeterminate (circular loading) mode
     */
    @PluginMethod
    public void setIndeterminate(PluginCall call) {
        try {
            String message = call.getString("message", "جاري المزامنة...");

            Context context = getContext();
            Intent intent = new Intent(context, BackgroundSyncService.class);
            intent.setAction(BackgroundSyncService.ACTION_SET_INDETERMINATE);
            intent.putExtra(BackgroundSyncService.EXTRA_MESSAGE, message);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent);
            } else {
                context.startService(intent);
            }

            Log.d(TAG, "Set indeterminate: " + message);

            JSObject result = new JSObject();
            result.put("success", true);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to set indeterminate", e);
            call.reject("فشل في تعيين الوضع الدائري: " + e.getMessage());
        }
    }

    /**
     * Show completion notification
     */
    @PluginMethod
    public void showComplete(PluginCall call) {
        try {
            String message = call.getString("message", "اكتملت المزامنة بنجاح");

            Context context = getContext();
            Intent intent = new Intent(context, BackgroundSyncService.class);
            intent.setAction(BackgroundSyncService.ACTION_SHOW_COMPLETE);
            intent.putExtra(BackgroundSyncService.EXTRA_MESSAGE, message);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent);
            } else {
                context.startService(intent);
            }

            Log.d(TAG, "Show complete: " + message);

            JSObject result = new JSObject();
            result.put("success", true);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "Failed to show complete", e);
            call.reject("فشل في إظهار إشعار الاكتمال: " + e.getMessage());
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
