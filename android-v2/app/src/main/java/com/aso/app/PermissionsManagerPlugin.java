package com.aso.app;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.PowerManager;
import android.provider.Settings;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

import android.util.Log;

/**
 * 📋 PermissionsManagerPlugin - إدارة متسلسلة لجميع صلاحيات التطبيق
 *
 * الهدف: طلب جميع الصلاحيات الضرورية بشكل متسلسل عند تسجيل الدخول
 * لمنع مشاكل ForegroundServiceStartNotAllowedException وغيرها
 *
 * الصلاحيات المطلوبة:
 * 1. POST_NOTIFICATIONS (Android 13+)
 * 2. CAMERA
 * 3. RECORD_AUDIO
 * 4. READ_EXTERNAL_STORAGE (Android < 13)
 * 5. WRITE_EXTERNAL_STORAGE (Android < 13)
 * 6. READ_MEDIA_IMAGES (Android 13+)
 * 7. READ_MEDIA_VIDEO (Android 13+)
 * 8. BATTERY_OPTIMIZATION (خاص)
 *
 * v1.0 - فبراير 2026
 */
@CapacitorPlugin(
    name = "PermissionsManager",
    permissions = {
        @Permission(
            strings = {
                Manifest.permission.CAMERA,
                Manifest.permission.RECORD_AUDIO
            },
            alias = "camera_audio"
        ),
        @Permission(
            strings = {
                Manifest.permission.READ_EXTERNAL_STORAGE,
                Manifest.permission.WRITE_EXTERNAL_STORAGE
            },
            alias = "storage"
        ),
        @Permission(
            strings = {
                Manifest.permission.POST_NOTIFICATIONS
            },
            alias = "notifications"
        )
    }
)
public class PermissionsManagerPlugin extends Plugin {
    private static final String TAG = "PermissionsManager";

    // Request codes للصلاحيات المختلفة
    private static final int REQUEST_NOTIFICATION_PERMISSION = 1001;
    private static final int REQUEST_CAMERA_PERMISSION = 1002;
    private static final int REQUEST_AUDIO_PERMISSION = 1003;
    private static final int REQUEST_STORAGE_PERMISSION = 1004;
    private static final int REQUEST_BATTERY_OPTIMIZATION = 1005;

    private PluginCall savedCall;

    @Override
    public void load() {
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  🔐 PermissionsManagerPlugin - تم التحميل بنجاح             ║");
        Log.e(TAG, "║  ✅ JavaScript: PermissionsManager.checkAllPermissions()     ║");
        Log.e(TAG, "║  ✅ JavaScript: PermissionsManager.requestPermission()       ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        Log.e(TAG, "");
    }

    /**
     * 🔍 فحص جميع الصلاحيات وإرجاع حالة كل واحدة
     */
    @PluginMethod
    public void checkAllPermissions(PluginCall call) {
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "🔍 checkAllPermissions() - فحص جميع الصلاحيات");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        JSObject result = new JSObject();

        // 1. إشعارات (Android 13+)
        boolean notificationsGranted = checkNotificationPermission();
        result.put("notifications", notificationsGranted);
        Log.e(TAG, "📱 Notifications: " + (notificationsGranted ? "✅ ممنوحة" : "❌ مرفوضة"));

        // 2. الكاميرا
        boolean cameraGranted = checkCameraPermission();
        result.put("camera", cameraGranted);
        Log.e(TAG, "📷 Camera: " + (cameraGranted ? "✅ ممنوحة" : "❌ مرفوضة"));

        // 3. الصوت
        boolean audioGranted = checkAudioPermission();
        result.put("audio", audioGranted);
        Log.e(TAG, "🎤 Audio: " + (audioGranted ? "✅ ممنوحة" : "❌ مرفوضة"));

        // 4. التخزين (حسب إصدار الأندرويد)
        boolean storageGranted = checkStoragePermission();
        result.put("storage", storageGranted);
        Log.e(TAG, "💾 Storage: " + (storageGranted ? "✅ ممنوحة" : "❌ مرفوضة"));

        // 5. تحسين البطارية
        boolean batteryOptimization = checkBatteryOptimization();
        result.put("batteryOptimization", batteryOptimization);
        Log.e(TAG, "🔋 Battery Optimization: " + (batteryOptimization ? "✅ معطل" : "⚠️ مفعل"));

        // حساب إجمالي الصلاحيات
        int totalPermissions = 5;
        int grantedPermissions = 0;
        if (notificationsGranted) grantedPermissions++;
        if (cameraGranted) grantedPermissions++;
        if (audioGranted) grantedPermissions++;
        if (storageGranted) grantedPermissions++;
        if (batteryOptimization) grantedPermissions++;

        result.put("allGranted", grantedPermissions == totalPermissions);
        result.put("grantedCount", grantedPermissions);
        result.put("totalCount", totalPermissions);
        result.put("androidVersion", Build.VERSION.SDK_INT);

        Log.e(TAG, "");
        Log.e(TAG, "📊 النتيجة: " + grantedPermissions + "/" + totalPermissions + " صلاحيات ممنوحة");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        call.resolve(result);
    }

    /**
     * 🙏 طلب صلاحية محددة
     */
    @PluginMethod
    public void requestPermission(PluginCall call) {
        String permissionType = call.getString("permission");

        if (permissionType == null) {
            call.reject("يجب تحديد نوع الصلاحية");
            return;
        }

        Log.e(TAG, "");
        Log.e(TAG, "🙏 requestPermission() - طلب صلاحية: " + permissionType);

        savedCall = call;

        switch (permissionType) {
            case "notifications":
                requestNotificationPermission();
                break;

            case "camera":
                requestCameraPermission();
                break;

            case "audio":
                requestAudioPermission();
                break;

            case "storage":
                requestStoragePermission();
                break;

            case "batteryOptimization":
                requestBatteryOptimization();
                break;

            default:
                call.reject("نوع صلاحية غير معروف: " + permissionType);
                savedCall = null;
                break;
        }
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🔍 دوال فحص الصلاحيات
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private boolean checkNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) { // Android 13+
            return ContextCompat.checkSelfPermission(
                getContext(),
                Manifest.permission.POST_NOTIFICATIONS
            ) == PackageManager.PERMISSION_GRANTED;
        }
        return true; // لا نحتاج صلاحية في أندرويد أقل من 13
    }

    private boolean checkCameraPermission() {
        return ContextCompat.checkSelfPermission(
            getContext(),
            Manifest.permission.CAMERA
        ) == PackageManager.PERMISSION_GRANTED;
    }

    private boolean checkAudioPermission() {
        return ContextCompat.checkSelfPermission(
            getContext(),
            Manifest.permission.RECORD_AUDIO
        ) == PackageManager.PERMISSION_GRANTED;
    }

    private boolean checkStoragePermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) { // Android 13+
            // نحتاج READ_MEDIA_IMAGES و READ_MEDIA_VIDEO
            boolean imagesGranted = ContextCompat.checkSelfPermission(
                getContext(),
                Manifest.permission.READ_MEDIA_IMAGES
            ) == PackageManager.PERMISSION_GRANTED;

            boolean videoGranted = ContextCompat.checkSelfPermission(
                getContext(),
                Manifest.permission.READ_MEDIA_VIDEO
            ) == PackageManager.PERMISSION_GRANTED;

            return imagesGranted && videoGranted;
        } else {
            // Android < 13: نحتاج READ_EXTERNAL_STORAGE و WRITE_EXTERNAL_STORAGE
            boolean readGranted = ContextCompat.checkSelfPermission(
                getContext(),
                Manifest.permission.READ_EXTERNAL_STORAGE
            ) == PackageManager.PERMISSION_GRANTED;

            boolean writeGranted = ContextCompat.checkSelfPermission(
                getContext(),
                Manifest.permission.WRITE_EXTERNAL_STORAGE
            ) == PackageManager.PERMISSION_GRANTED;

            return readGranted && writeGranted;
        }
    }

    private boolean checkBatteryOptimization() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            PowerManager powerManager = (PowerManager) getContext().getSystemService(Activity.POWER_SERVICE);
            return powerManager.isIgnoringBatteryOptimizations(getContext().getPackageName());
        }
        return true; // لا نحتاج في أندرويد أقل من 6
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 🙏 دوال طلب الصلاحيات
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            Log.e(TAG, "📱 طلب صلاحية الإشعارات (Android 13+)");

            if (checkNotificationPermission()) {
                Log.e(TAG, "✅ الصلاحية ممنوحة بالفعل");
                JSObject result = new JSObject();
                result.put("granted", true);
                result.put("permission", "notifications");
                savedCall.resolve(result);
                savedCall = null;
            } else {
                Log.e(TAG, "⏳ طلب الصلاحية من المستخدم...");
                ActivityCompat.requestPermissions(
                    getActivity(),
                    new String[]{Manifest.permission.POST_NOTIFICATIONS},
                    REQUEST_NOTIFICATION_PERMISSION
                );
            }
        } else {
            Log.e(TAG, "✅ الإشعارات ممنوحة تلقائياً (Android < 13)");
            JSObject result = new JSObject();
            result.put("granted", true);
            result.put("permission", "notifications");
            savedCall.resolve(result);
            savedCall = null;
        }
    }

    private void requestCameraPermission() {
        Log.e(TAG, "📷 طلب صلاحية الكاميرا");

        if (checkCameraPermission()) {
            Log.e(TAG, "✅ الصلاحية ممنوحة بالفعل");
            JSObject result = new JSObject();
            result.put("granted", true);
            result.put("permission", "camera");
            savedCall.resolve(result);
            savedCall = null;
        } else {
            Log.e(TAG, "⏳ طلب الصلاحية من المستخدم...");
            ActivityCompat.requestPermissions(
                getActivity(),
                new String[]{Manifest.permission.CAMERA},
                REQUEST_CAMERA_PERMISSION
            );
        }
    }

    private void requestAudioPermission() {
        Log.e(TAG, "🎤 طلب صلاحية الصوت");

        if (checkAudioPermission()) {
            Log.e(TAG, "✅ الصلاحية ممنوحة بالفعل");
            JSObject result = new JSObject();
            result.put("granted", true);
            result.put("permission", "audio");
            savedCall.resolve(result);
            savedCall = null;
        } else {
            Log.e(TAG, "⏳ طلب الصلاحية من المستخدم...");
            ActivityCompat.requestPermissions(
                getActivity(),
                new String[]{Manifest.permission.RECORD_AUDIO},
                REQUEST_AUDIO_PERMISSION
            );
        }
    }

    private void requestStoragePermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            Log.e(TAG, "💾 طلب صلاحيات الوسائط (Android 13+)");

            if (checkStoragePermission()) {
                Log.e(TAG, "✅ الصلاحية ممنوحة بالفعل");
                JSObject result = new JSObject();
                result.put("granted", true);
                result.put("permission", "storage");
                savedCall.resolve(result);
                savedCall = null;
            } else {
                Log.e(TAG, "⏳ طلب الصلاحية من المستخدم...");
                ActivityCompat.requestPermissions(
                    getActivity(),
                    new String[]{
                        Manifest.permission.READ_MEDIA_IMAGES,
                        Manifest.permission.READ_MEDIA_VIDEO
                    },
                    REQUEST_STORAGE_PERMISSION
                );
            }
        } else {
            Log.e(TAG, "💾 طلب صلاحيات التخزين (Android < 13)");

            if (checkStoragePermission()) {
                Log.e(TAG, "✅ الصلاحية ممنوحة بالفعل");
                JSObject result = new JSObject();
                result.put("granted", true);
                result.put("permission", "storage");
                savedCall.resolve(result);
                savedCall = null;
            } else {
                Log.e(TAG, "⏳ طلب الصلاحية من المستخدم...");
                ActivityCompat.requestPermissions(
                    getActivity(),
                    new String[]{
                        Manifest.permission.READ_EXTERNAL_STORAGE,
                        Manifest.permission.WRITE_EXTERNAL_STORAGE
                    },
                    REQUEST_STORAGE_PERMISSION
                );
            }
        }
    }

    private void requestBatteryOptimization() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Log.e(TAG, "🔋 طلب تعطيل تحسين البطارية");

            PowerManager powerManager = (PowerManager) getContext().getSystemService(Activity.POWER_SERVICE);

            if (!powerManager.isIgnoringBatteryOptimizations(getContext().getPackageName())) {
                try {
                    Intent intent = new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                    intent.setData(Uri.parse("package:" + getContext().getPackageName()));
                    getActivity().startActivityForResult(intent, REQUEST_BATTERY_OPTIMIZATION);

                    Log.e(TAG, "✅ تم فتح شاشة تحسين البطارية");
                } catch (Exception e) {
                    Log.e(TAG, "❌ خطأ في فتح شاشة تحسين البطارية: " + e.getMessage());

                    // نرجع نتيجة فورية في حالة الخطأ
                    JSObject result = new JSObject();
                    result.put("granted", false);
                    result.put("permission", "batteryOptimization");
                    result.put("error", e.getMessage());
                    savedCall.resolve(result);
                    savedCall = null;
                }
            } else {
                Log.e(TAG, "✅ تحسين البطارية معطل بالفعل");
                JSObject result = new JSObject();
                result.put("granted", true);
                result.put("permission", "batteryOptimization");
                savedCall.resolve(result);
                savedCall = null;
            }
        } else {
            Log.e(TAG, "✅ تحسين البطارية غير مطلوب (Android < 6)");
            JSObject result = new JSObject();
            result.put("granted", true);
            result.put("permission", "batteryOptimization");
            savedCall.resolve(result);
            savedCall = null;
        }
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 📞 Callback بعد طلب الصلاحيات من المستخدم
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    @Override
    public void handleRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.handleRequestPermissionsResult(requestCode, permissions, grantResults);

        if (savedCall == null) {
            Log.e(TAG, "⚠️ savedCall is null - تم فقدان السياق");
            return;
        }

        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "📞 handleRequestPermissionsResult() - استلام نتيجة الصلاحية");
        Log.e(TAG, "   Request Code: " + requestCode);
        Log.e(TAG, "   Permissions: " + String.join(", ", permissions));
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        JSObject result = new JSObject();
        boolean granted = false;
        String permissionType = "";

        switch (requestCode) {
            case REQUEST_NOTIFICATION_PERMISSION:
                permissionType = "notifications";
                granted = grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED;
                Log.e(TAG, "📱 نتيجة صلاحية الإشعارات: " + (granted ? "✅ ممنوحة" : "❌ مرفوضة"));
                break;

            case REQUEST_CAMERA_PERMISSION:
                permissionType = "camera";
                granted = grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED;
                Log.e(TAG, "📷 نتيجة صلاحية الكاميرا: " + (granted ? "✅ ممنوحة" : "❌ مرفوضة"));
                break;

            case REQUEST_AUDIO_PERMISSION:
                permissionType = "audio";
                granted = grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED;
                Log.e(TAG, "🎤 نتيجة صلاحية الصوت: " + (granted ? "✅ ممنوحة" : "❌ مرفوضة"));
                break;

            case REQUEST_STORAGE_PERMISSION:
                permissionType = "storage";
                // نحتاج كل الصلاحيات ممنوحة
                granted = true;
                for (int i = 0; i < grantResults.length; i++) {
                    if (grantResults[i] != PackageManager.PERMISSION_GRANTED) {
                        granted = false;
                        Log.e(TAG, "❌ صلاحية " + permissions[i] + " مرفوضة");
                        break;
                    }
                }
                Log.e(TAG, "💾 نتيجة صلاحيات التخزين: " + (granted ? "✅ جميعها ممنوحة" : "❌ بعضها مرفوض"));
                break;

            default:
                Log.e(TAG, "⚠️ Request code غير معروف: " + requestCode);
                permissionType = "unknown";
                granted = false;
                break;
        }

        result.put("granted", granted);
        result.put("permission", permissionType);

        Log.e(TAG, "✅ إرسال النتيجة إلى JavaScript:");
        Log.e(TAG, "   Permission: " + permissionType);
        Log.e(TAG, "   Granted: " + granted);
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        savedCall.resolve(result);
        savedCall = null;
    }

    @Override
    protected void handleOnActivityResult(int requestCode, int resultCode, Intent data) {
        super.handleOnActivityResult(requestCode, resultCode, data);

        if (requestCode == REQUEST_BATTERY_OPTIMIZATION && savedCall != null) {
            // نفحص إذا تم تعطيل تحسين البطارية
            boolean granted = checkBatteryOptimization();

            Log.e(TAG, "🔋 نتيجة تحسين البطارية: " + (granted ? "✅ معطل" : "⚠️ مفعل"));

            JSObject result = new JSObject();
            result.put("granted", granted);
            result.put("permission", "batteryOptimization");

            savedCall.resolve(result);
            savedCall = null;
        }
    }
}
