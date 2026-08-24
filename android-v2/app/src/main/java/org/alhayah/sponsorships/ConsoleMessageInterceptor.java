package org.alhayah.sponsorships;

import android.util.Log;
import android.webkit.ConsoleMessage;
import android.webkit.WebChromeClient;

import org.json.JSONObject;

/**
 * يستمع لرسائل console.log من JavaScript
 * ويستخرج رسائل حفظ البيانات لإرسالها للمزامنة
 */
public class ConsoleMessageInterceptor extends WebChromeClient {
    private static final String TAG = "ConsoleInterceptor";
    private android.content.Context context;

    public ConsoleMessageInterceptor(android.content.Context context) {
        this.context = context;
    }

    @Override
    public boolean onConsoleMessage(ConsoleMessage consoleMessage) {
        String message = consoleMessage.message();

        // البحث عن رسالة حفظ الكفالة في IndexedDB
        // sync-service.js:1217 ✅ Sponsorship saved to IndexedDB with bank_accounts: 1
        if (message != null && message.contains("Sponsorship saved to IndexedDB")) {
            Log.e(TAG, "🎯 Detected IndexedDB save from console.log!");
            Log.e(TAG, "📝 Console message: " + message);

            // هنا نحتاج لاستخراج البيانات من السطر التالي
            // لكن console.log لا يعطينا البيانات نفسها

            // الحل: نستمع لرسالة خاصة بنا!
        }

        // البحث عن رسالة custom للمزامنة
        // مثال: console.log('SYNC_DATA:' + JSON.stringify({...}))
        if (message != null && message.startsWith("SYNC_DATA:")) {
            try {
                String jsonData = message.substring("SYNC_DATA:".length());
                Log.e(TAG, "");
                Log.e(TAG, "═══════════════════════════════════════════════");
                Log.e(TAG, "🔥 SYNC_DATA detected from console.log!");
                Log.e(TAG, "═══════════════════════════════════════════════");
                Log.d(TAG, "📊 Data: " + jsonData);

                JSONObject data = new JSONObject(jsonData);
                String dataType = data.optString("dataType", "sponsorship");
                String dataJson = data.optString("dataJson", "{}");
                String endpoint = data.optString("endpoint", "/api/mobile/sponsorships/sync");

                // إضافة للـ queue
                DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);
                long id = dbHelper.addDataToQueue(dataType, dataJson, endpoint);

                if (id > 0) {
                    Log.e(TAG, "✅ Data added to queue: ID=" + id);
                    DataSyncForegroundService.startSync(context);
                    Log.e(TAG, "🚀 DataSyncForegroundService started!");
                }

                Log.e(TAG, "═══════════════════════════════════════════════");
                Log.e(TAG, "");

            } catch (Exception e) {
                Log.e(TAG, "❌ Error processing SYNC_DATA", e);
            }
        }

        // السماح للرسالة بالظهور في الـ console العادي
        return super.onConsoleMessage(consoleMessage);
    }
}
