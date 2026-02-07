package org.alhayah.sponsorships;

import android.content.Context;
import android.util.Log;
import android.webkit.JavascriptInterface;

import org.json.JSONObject;

/**
 * جسر JavaScript ← → Java
 * يسمح لـ JavaScript باستدعاء Java مباشرة
 */
public class JavaScriptBridge {
    private static final String TAG = "JavaScriptBridge";
    private Context context;

    public JavaScriptBridge(Context context) {
        this.context = context;
    }

    /**
     * استدعاء من JavaScript عند حفظ بيانات جديدة في IndexedDB
     *
     * مثال الاستخدام من JavaScript:
     * window.AndroidBridge.onDataSaved(JSON.stringify({
     *   dataType: 'sponsorship',
     *   dataJson: {...},
     *   endpoint: '/api/mobile/sponsorships/sync'
     * }));
     */
    @JavascriptInterface
    public void onDataSaved(String jsonData) {
        try {
            Log.e(TAG, "");
            Log.e(TAG, "═══════════════════════════════════════════════════════════");
            Log.e(TAG, "🔥🔥🔥 JavaScriptBridge.onDataSaved() CALLED 🔥🔥🔥");
            Log.e(TAG, "═══════════════════════════════════════════════════════════");
            Log.d(TAG, "📥 Data received from JavaScript");
            Log.d(TAG, "📊 JSON Data: " + jsonData);

            JSONObject data = new JSONObject(jsonData);
            String dataType = data.optString("dataType", "unknown");
            String dataJson = data.optString("dataJson", "{}");
            String endpoint = data.optString("endpoint", "/api/mobile/sync");

            Log.d(TAG, "📋 Data Type: " + dataType);
            Log.d(TAG, "🌐 Endpoint: " + endpoint);

            // تحويل البيانات لتنسيق Laravel API
            // API يتوقع: { "sponsorship_id": 123, "updates": {...} }
            JSONObject sponsorshipData = new JSONObject(dataJson);
            int sponsorshipId = sponsorshipData.optInt("id", 0);

            if (sponsorshipId == 0) {
                Log.e(TAG, "❌ Invalid sponsorship_id");
                return;
            }

            // بناء payload للـ API
            JSONObject payload = new JSONObject();
            payload.put("sponsorship_id", sponsorshipId);
            payload.put("updates", sponsorshipData); // كل البيانات كـ updates

            String formattedJson = payload.toString();
            Log.d(TAG, "📦 Formatted payload: " + formattedJson.substring(0, Math.min(200, formattedJson.length())) + "...");

            // إضافة للـ queue مباشرة
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);
            long id = dbHelper.addDataToQueue(dataType, formattedJson, endpoint);

            if (id > 0) {
                Log.e(TAG, "✅ Data added to sync queue: ID=" + id);

                // بدء الخدمة للمزامنة فوراً
                Log.e(TAG, "🚀 Starting DataSyncForegroundService...");
                DataSyncForegroundService.startSync(context);
                Log.e(TAG, "✅ DataSyncForegroundService started!");

                // ⚡ جدولة AlarmManager فوري (5 ثوانٍ) للتأكد من الرفع حتى عند إغلاق التطبيق
                Log.e(TAG, "⚡ Scheduling immediate AlarmManager for background retry...");
                DataSyncAlarmReceiver.scheduleImmediateCheck(context);
                Log.e(TAG, "✅ Immediate alarm scheduled (will trigger in 5 sec even if app closed)");
            } else {
                Log.e(TAG, "❌ Failed to add data to queue");
            }
            Log.e(TAG, "═══════════════════════════════════════════════════════════");
            Log.e(TAG, "");

        } catch (Exception e) {
            Log.e(TAG, "❌❌❌ ERROR in onDataSaved ❌❌❌", e);
        }
    }

    /**
     * استدعاء من JavaScript للحصول على حالة المزامنة
     */
    @JavascriptInterface
    public String getSyncStatus() {
        try {
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);

            int pending = dbHelper.getPendingDataCount();
            int uploaded = dbHelper.getUploadedDataCount();
            int failed = dbHelper.getFailedDataCount();

            JSONObject status = new JSONObject();
            status.put("pending", pending);
            status.put("uploaded", uploaded);
            status.put("failed", failed);
            status.put("total", pending + uploaded + failed);

            Log.d(TAG, "📊 Sync status: " + status.toString());
            return status.toString();

        } catch (Exception e) {
            Log.e(TAG, "Error getting sync status", e);
            return "{\"error\":\"" + e.getMessage() + "\"}";
        }
    }

    /**
     * استدعاء من JavaScript لإعادة محاولة البيانات الفاشلة
     */
    @JavascriptInterface
    public String retryFailed() {
        try {
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);
            int count = dbHelper.resetFailedData();

            // بدء الخدمة للمزامنة
            if (count > 0) {
                DataSyncForegroundService.startSync(context);
            }

            JSONObject result = new JSONObject();
            result.put("success", true);
            result.put("retried", count);

            Log.d(TAG, "🔄 Retried " + count + " failed items");
            return result.toString();

        } catch (Exception e) {
            Log.e(TAG, "Error retrying failed data", e);
            return "{\"error\":\"" + e.getMessage() + "\"}";
        }
    }

    /**
     * حفظ API token و base URL من JavaScript إلى SharedPreferences
     * يُستدعى بعد تسجيل الدخول مباشرة
     */
    @JavascriptInterface
    public void saveAuthData(String token, String baseUrl) {
        try {
            Log.e(TAG, "");
            Log.e(TAG, "🔐🔐🔐 saveAuthData() CALLED 🔐🔐🔐");
            Log.d(TAG, "📥 Token: " + (token != null ? token.substring(0, Math.min(20, token.length())) + "..." : "null"));
            Log.d(TAG, "🌐 Base URL (raw): " + baseUrl);

            // إزالة /api من نهاية الرابط إذا كان موجود
            if (baseUrl != null && baseUrl.endsWith("/api")) {
                baseUrl = baseUrl.substring(0, baseUrl.length() - 4);
                Log.d(TAG, "🔧 Base URL (cleaned): " + baseUrl);
            }

            android.content.SharedPreferences prefs = context.getSharedPreferences("auth_prefs", android.content.Context.MODE_PRIVATE);
            android.content.SharedPreferences.Editor editor = prefs.edit();
            editor.putString("api_token", token);
            editor.putString("api_base_url", baseUrl != null ? baseUrl : "https://alhayahorphans.org");
            editor.apply();

            Log.e(TAG, "✅ Auth data saved to SharedPreferences");
            Log.e(TAG, "   📡 Token: " + (token != null ? token.substring(0, Math.min(15, token.length())) + "..." : "null"));
            Log.e(TAG, "   🌐 Base URL: " + (baseUrl != null ? baseUrl : "https://alhayahorphans.org"));
            Log.e(TAG, "");

        } catch (Exception e) {
            Log.e(TAG, "❌ Error saving auth data", e);
        }
    }
}
