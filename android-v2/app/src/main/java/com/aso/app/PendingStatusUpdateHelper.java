package com.aso.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;
import org.json.JSONArray;
import org.json.JSONObject;

/**
 * ══════════════════════════════════════════════════════════════════════════
 * 📡 PendingStatusUpdateHelper - نظام التحديثات المؤجلة
 * ══════════════════════════════════════════════════════════════════════════
 *
 * المشكلة:
 * عندما يرفع FileSyncWorker ملفاً بنجاح والتطبيق مغلق، لا يتم تحديث IndexedDB
 * لأن UploadStatusBridge يحتاج WebView نشط.
 *
 * الحل:
 * حفظ التحديثات المؤجلة في SharedPreferences عندما WebView غير متاح،
 * ثم تطبيقها تلقائياً عند فتح التطبيق.
 *
 * الاستخدام:
 * 1. FileSyncWorker: عند النجاح → addPendingUpdate()
 * 2. MainActivity: عند onCreate() → processPendingUpdates()
 * 3. JavaScript: يتلقى التحديثات ويحدث IndexedDB
 *
 * @version 1.0
 * @date 2026-02-16
 */
public class PendingStatusUpdateHelper {
    private static final String TAG = "PendingStatusUpdate";
    private static final String PREFS_NAME = "pending_upload_status_updates";
    private static final String KEY_UPDATES_JSON = "updates_json_array";

    /**
     * إضافة تحديث مؤجل للحفظ
     * يُستخدم عندما WebView غير متاح (التطبيق مغلق)
     */
    public static void addPendingUpdate(Context context, long fileId, String status, String error) {
        try {
            SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
            String existingJson = prefs.getString(KEY_UPDATES_JSON, "[]");

            JSONArray updates = new JSONArray(existingJson);

            // إنشاء كائن التحديث
            JSONObject update = new JSONObject();
            update.put("fileId", fileId);
            update.put("status", status);
            update.put("error", error != null ? error : "");
            update.put("timestamp", System.currentTimeMillis());

            updates.put(update);

            // حفظ التحديثات المحدثة
            prefs.edit()
                .putString(KEY_UPDATES_JSON, updates.toString())
                .apply();

            Log.e(TAG, "✅ تم حفظ تحديث مؤجل:");
            Log.e(TAG, "   📄 File ID: " + fileId);
            Log.e(TAG, "   📊 Status: " + status);
            Log.e(TAG, "   📝 Total pending: " + updates.length());

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في حفظ التحديث المؤجل: " + e.getMessage(), e);
        }
    }

    /**
     * معالجة جميع التحديثات المؤجلة
     * يُستخدم في MainActivity.onCreate() لتطبيق التحديثات المتراكمة
     */
    public static void processPendingUpdates(Context context) {
        try {
            SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
            String updatesJson = prefs.getString(KEY_UPDATES_JSON, "[]");

            JSONArray updates = new JSONArray(updatesJson);

            if (updates.length() == 0) {
                Log.d(TAG, "ℹ️  لا توجد تحديثات مؤجلة");
                return;
            }

            Log.e(TAG, "");
            Log.e(TAG, "╔═══════════════════════════════════════════════════════════╗");
            Log.e(TAG, "║  📡 معالجة التحديثات المؤجلة (Pending Status Updates)  ║");
            Log.e(TAG, "╚═══════════════════════════════════════════════════════════╝");
            Log.e(TAG, "   📝 عدد التحديثات: " + updates.length());
            Log.e(TAG, "");

            // تطبيق كل تحديث
            for (int i = 0; i < updates.length(); i++) {
                JSONObject update = updates.getJSONObject(i);
                long fileId = update.getLong("fileId");
                String status = update.getString("status");
                String error = update.optString("error", null);

                Log.e(TAG, "🔄 تطبيق تحديث [" + (i + 1) + "/" + updates.length() + "]:");
                Log.e(TAG, "   📄 File ID: " + fileId);
                Log.e(TAG, "   📊 Status: " + status);

                // إرسال التحديث عبر UploadStatusBridge
                // الآن WebView متاح لأننا في MainActivity
                UploadStatusBridge.notifyUploadComplete(fileId, status, error);

                Log.e(TAG, "   ✅ تم الإرسال عبر UploadStatusBridge");
            }

            // حذف التحديثات المعالجة
            prefs.edit()
                .remove(KEY_UPDATES_JSON)
                .apply();

            Log.e(TAG, "");
            Log.e(TAG, "✅✅✅ تم معالجة جميع التحديثات المؤجلة بنجاح!");
            Log.e(TAG, "");

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في معالجة التحديثات المؤجلة: " + e.getMessage(), e);
            e.printStackTrace();
        }
    }

    /**
     * الحصول على عدد التحديثات المؤجلة
     */
    public static int getPendingUpdatesCount(Context context) {
        try {
            SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
            String updatesJson = prefs.getString(KEY_UPDATES_JSON, "[]");
            JSONArray updates = new JSONArray(updatesJson);
            return updates.length();
        } catch (Exception e) {
            return 0;
        }
    }

    /**
     * حذف جميع التحديثات المؤجلة (للتنظيف)
     */
    public static void clearPendingUpdates(Context context) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .clear()
            .apply();
        Log.e(TAG, "🗑️ تم حذف جميع التحديثات المؤجلة");
    }
}
