package com.aso.app;

import android.app.Activity;
import android.content.Intent;
import android.util.Log;
import androidx.activity.result.ActivityResult;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.ActivityCallback;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * ═══════════════════════════════════════════════════════════════════
 * 📹 NativeCameraPlugin - Native Camera Access
 *
 * يفتح CameraActivity مباشرة بدون أي معالجة JavaScript!
 *
 * JavaScript:
 *   NativeCamera.recordVideo({
 *     sponsorshipId: 123,
 *     apiUrl: "...",
 *     authToken: "...",
 *     personName: "محمد",
 *     associationName: "الحياة"
 *   })
 *
 * Java:
 *   → يفتح CameraActivity
 *   → الكاميرا تفتح مباشرة
 *   → الملف يُحفظ في storage (file:// URI)
 *   → يُضاف لقاعدة البيانات
 *   → FileSyncWorker يرفعه
 *
 * ✅ لا Base64!
 * ✅ لا Capacitor bridge overhead!
 * ✅ لا JavaScript memory issues!
 * ═══════════════════════════════════════════════════════════════════
 */
@CapacitorPlugin(name = "NativeCamera")
public class NativeCameraPlugin extends Plugin {

    private static final String TAG = "NativeCameraPlugin";
    private static final int REQUEST_VIDEO_CAPTURE = 2001;

    @Override
    public void load() {
        super.load();
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  📹 NativeCameraPlugin - LOADED                               ║");
        Log.e(TAG, "║  ✅ JavaScript can call: NativeCamera.recordVideo()          ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        Log.e(TAG, "");
    }

    @PluginMethod
    public void recordVideo(PluginCall call) {
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  📹 recordVideo() CALLED FROM JAVASCRIPT                      ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");

        // استخراج المعاملات
        Integer sponsorshipId = call.getInt("sponsorshipId");
        String apiUrl = call.getString("apiUrl");
        String authToken = call.getString("authToken");
        String personName = call.getString("personName");
        String associationName = call.getString("associationName");

        Log.e(TAG, "📊 Parameters:");
        Log.e(TAG, "   sponsorshipId: " + sponsorshipId);
        Log.e(TAG, "   personName: " + personName);
        Log.e(TAG, "   associationName: " + associationName);

        // التحقق من المعاملات
        if (sponsorshipId == null || apiUrl == null) {
            Log.e(TAG, "❌ معاملات ناقصة!");
            call.reject("معاملات ناقصة: sponsorshipId و apiUrl مطلوبان");
            return;
        }

        // إنشاء Intent لفتح CameraActivity
        Intent intent = new Intent(getContext(), CameraActivity.class);
        intent.putExtra("sponsorshipId", sponsorshipId);
        intent.putExtra("apiUrl", apiUrl);
        intent.putExtra("authToken", authToken != null ? authToken : "");
        intent.putExtra("personName", personName != null ? personName : "");
        intent.putExtra("associationName", associationName != null ? associationName : "");

        Log.e(TAG, "🚀 فتح CameraActivity...");

        // فتح Activity
        startActivityForResult(call, intent, "handleCameraResult");
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * 🔙 معالجة نتيجة CameraActivity
     *
     * يتم استدعاؤها تلقائياً عند إغلاق CameraActivity
     * ═══════════════════════════════════════════════════════════════════
     */
    @ActivityCallback
    private void handleCameraResult(PluginCall call, ActivityResult result) {
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  🔙 Camera Activity Result                                    ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        Log.e(TAG, "📊 Result Code: " + result.getResultCode());

        if (result.getResultCode() == Activity.RESULT_OK) {
            Log.e(TAG, "✅ تم التصوير بنجاح!");

            Intent data = result.getData();
            JSObject response = new JSObject();
            response.put("success", true);
            response.put("message", "تم تصوير وجدولة رفع الفيديو بنجاح");

            // إضافة معلومات الملف من CameraActivity
            if (data != null) {
                int sponsorshipId = data.getIntExtra("sponsorshipId", 0);
                String fileName = data.getStringExtra("fileName");
                String fileType = data.getStringExtra("fileType");

                response.put("sponsorshipId", sponsorshipId);
                response.put("fileName", fileName);
                response.put("fileType", fileType);

                Log.e(TAG, "📋 File Info: sponsorshipId=" + sponsorshipId + ", fileName=" + fileName);
            }

            call.resolve(response);
        } else {
            Log.e(TAG, "❌ تم إلغاء التصوير أو حدث خطأ");
            call.reject("تم إلغاء التصوير");
        }

        Log.e(TAG, "");
    }
}
