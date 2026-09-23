package com.aso.app;

import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.webkit.WebView;
import com.getcapacitor.Bridge;

/**
 * ══════════════════════════════════════════════════════════════════════════
 * 🌉 UploadStatusBridge - قنطرة قوية لـ Real-Time Sync بين Java و JavaScript
 * ══════════════════════════════════════════════════════════════════════════
 *
 * المشكلة الأصلية:
 * - Capacitor events لا تصل دائماً إلى JavaScript
 * - IndexedDB لا يتم تحديثه بعد نجاح الرفع
 * - العداد (#stat-files) لا يتحدث
 * - حالة الملف تبقى "معلق" بدلاً من "تم الرفع"
 *
 * الحل:
 * ✅ تنفيذ JavaScript مباشرة في WebView (evaluateJavascript)
 * ✅ تحديث IndexedDB مباشرة من Java
 * ✅ تحديث UI elements مباشرة
 * ✅ إرسال CustomEvent للـ listeners
 *
 * يتم استدعاؤها من:
 * - FileSyncWorker.doWork() عند نجاح/فشل الرفع
 * - UploadForegroundService عند التحديثات
 *
 * @version 3.0 - NUCLEAR OPTION: Direct JavaScript Execution
 * @date 2026-02-15
 */
public class UploadStatusBridge {
    private static final String TAG = "UploadStatusBridge";
    private static MainActivity mainActivity;
    private static Handler mainHandler = new Handler(Looper.getMainLooper());

    /**
     * تسجيل MainActivity للوصول إلى WebView
     */
    public static void registerActivity(MainActivity activity) {
        mainActivity = activity;
        Log.e(TAG, "✅ MainActivity registered - Bridge ready!");
    }

    /**
     * ════════════════════════════════════════════════════════════════════
     * 📡 إرسال تحديث حالة الرفع مباشرة إلى JavaScript
     * ════════════════════════════════════════════════════════════════════
     *
     * يتم تنفيذ العمليات التالية في WebView:
     * 1. تحديث IndexedDB (FileStorageDB)
     * 2. تحديث العداد (#stat-files)
     * 3. تحديث حالة الملف في القائمة (pending → completed)
     * 4. إرسال CustomEvent للـ listeners
     */
    public static void notifyUploadComplete(final long fileId, final String status, final String error) {
        Log.e(TAG, "");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "🌉 UploadStatusBridge.notifyUploadComplete() CALLED");
        Log.e(TAG, "   📝 File ID: " + fileId);
        Log.e(TAG, "   📊 Status: " + status);
        if (error != null) {
            Log.e(TAG, "   ❌ Error: " + error);
        }
        Log.e(TAG, "   🧵 Current thread: " + Thread.currentThread().getName());
        Log.e(TAG, "   🏭 MainActivity: " + (mainActivity != null ? "✅ REGISTERED" : "❌ NOT REGISTERED"));
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        if (mainActivity == null) {
            Log.e(TAG, "❌❌❌ CRITICAL: MainActivity not registered - cannot send to JavaScript!");
            Log.e(TAG, "   Make sure MainActivity.onCreate() calls UploadStatusBridge.registerActivity(this)");
            return;
        }

        Log.e(TAG, "📤 Posting JavaScript execution to main thread...");

        // يجب تنفيذ JavaScript على main thread - استخدام postDelayed للتأكد من جاهزية WebView
        mainHandler.postDelayed(() -> {
            Log.e(TAG, "🎯 Main thread handler executed - starting JavaScript injection");

            try {
                Bridge bridge = mainActivity.getBridge();
                Log.e(TAG, "   🌉 Bridge: " + (bridge != null ? "✅ Available" : "❌ NULL"));

                if (bridge == null) {
                    Log.e(TAG, "❌❌❌ CRITICAL: Bridge not available!");
                    return;
                }

                WebView webView = bridge.getWebView();
                Log.e(TAG, "   🌐 WebView: " + (webView != null ? "✅ Available" : "❌ NULL"));

                if (webView == null) {
                    Log.e(TAG, "❌❌❌ CRITICAL: WebView not available!");
                    return;
                }

                Log.e(TAG, "🚀 WebView ready - building JavaScript code...");

                // ════════════════════════════════════════════════════════
                // بناء JavaScript code للتنفيذ المباشر
                // ✅ استخدام SyncService بدلاً من FileStorageDB
                // ════════════════════════════════════════════════════════
                StringBuilder jsCode = new StringBuilder();

                jsCode.append("(function() {");
                jsCode.append("  console.log('🌉 JAVA → JS: Upload status update received!');");
                jsCode.append("  console.log('  File ID: ").append(fileId).append("');");
                jsCode.append("  console.log('  Status: ").append(status).append("');");

                // 1️⃣ Try SyncService first (used by upload.html)
                jsCode.append("  if (typeof SyncService !== 'undefined' && SyncService.dbGet && SyncService.dbPut) {");
                jsCode.append("    console.log('✅ Using SyncService to update file status');");
                jsCode.append("    SyncService.dbGet('files', ").append(fileId).append(")");
                jsCode.append("      .then(file => {");
                jsCode.append("        if (!file) {");
                jsCode.append("          console.error('❌ File not found in IndexedDB: ").append(fileId).append("');");
                jsCode.append("          return Promise.reject('File not found');");
                jsCode.append("        }");
                jsCode.append("        console.log('📄 File found:', file);");
                jsCode.append("        file.uploaded = ").append(status.equals("completed") ? "true" : "false").append(";");
                jsCode.append("        return SyncService.dbPut('files', file);");
                jsCode.append("      })");
                jsCode.append("      .then(() => {");
                jsCode.append("        console.log('✅ File status updated in IndexedDB');");
                jsCode.append("        return SyncService.dbGetAll('files');");
                jsCode.append("      })");
                jsCode.append("      .then(allFiles => {");

                // 2️⃣ تحديث الإحصائيات
                jsCode.append("        const pending = allFiles.filter(f => !f.uploaded).length;");
                jsCode.append("        const uploaded = allFiles.filter(f => f.uploaded).length;");
                jsCode.append("        console.log('📊 Stats - Pending: ' + pending + ', Uploaded: ' + uploaded);");

                // تحديث جميع عناصر الإحصائيات الممكنة
                jsCode.append("        ['stat-files', 'statPending', 'pending-files-count'].forEach(id => {");
                jsCode.append("          const el = document.getElementById(id);");
                jsCode.append("          if (el) {");
                jsCode.append("            el.textContent = pending;");
                jsCode.append("            el.classList.add('stats-flash');");
                jsCode.append("            setTimeout(() => el.classList.remove('stats-flash'), 500);");
                jsCode.append("            console.log('✅ Updated #' + id + ' = ' + pending);");
                jsCode.append("          }");
                jsCode.append("        });");

                jsCode.append("        ['statUploaded', 'stat-uploaded'].forEach(id => {");
                jsCode.append("          const el = document.getElementById(id);");
                jsCode.append("          if (el) {");
                jsCode.append("            el.textContent = uploaded;");
                jsCode.append("            console.log('✅ Updated #' + id + ' = ' + uploaded);");
                jsCode.append("          }");
                jsCode.append("        });");

                // 3️⃣ تحديث حالة الملف في القائمة
                jsCode.append("        const fileElements = document.querySelectorAll('[data-file-id=\"").append(fileId).append("\"]');");
                jsCode.append("        console.log('🔍 Found ' + fileElements.length + ' file element(s) to update');");
                jsCode.append("        fileElements.forEach(el => {");
                jsCode.append("          const statusSpan = el.querySelector('.file-status');");
                jsCode.append("          if (statusSpan) {");
                jsCode.append("            statusSpan.textContent = '").append(status.equals("completed") ? "تم الرفع" : status).append("';");
                jsCode.append("            statusSpan.className = 'file-status ").append(status.equals("completed") ? "uploaded" : status).append("';");
                jsCode.append("            console.log('✅ Updated file element UI for file ").append(fileId).append("');");
                jsCode.append("          }");
                jsCode.append("        });");

                // 4️⃣ إرسال CustomEvent
                jsCode.append("        window.dispatchEvent(new CustomEvent('fileUploadStatusUpdated', {");
                jsCode.append("          detail: { fileId: ").append(fileId).append(", status: '").append(status).append("', pending: pending, uploaded: uploaded }");
                jsCode.append("        }));");
                jsCode.append("        console.log('📡 Dispatched fileUploadStatusUpdated event');");

                jsCode.append("      })");
                jsCode.append("      .catch(err => console.error('❌ SyncService update failed:', err));");

                // Fallback to FileStorageDB if SyncService not available
                jsCode.append("  } else if (typeof FileStorageDB !== 'undefined') {");
                jsCode.append("    console.log('⚠️ SyncService not available - using FileStorageDB fallback');");
                jsCode.append("    FileStorageDB.updateFileStatus(").append(fileId).append(", '").append(status).append("')");
                jsCode.append("      .then(() => FileStorageDB.getStats())");
                jsCode.append("      .then(stats => {");
                jsCode.append("        const statElements = ['stat-files', 'pending-files-count', 'files-counter'];");
                jsCode.append("        statElements.forEach(id => {");
                jsCode.append("          const el = document.getElementById(id);");
                jsCode.append("          if (el) el.textContent = stats.pending;");
                jsCode.append("        });");
                jsCode.append("        const fileElements = document.querySelectorAll('[data-file-id=\"").append(fileId).append("\"]');");
                jsCode.append("        fileElements.forEach(el => {");
                jsCode.append("          const statusSpan = el.querySelector('.file-status');");
                jsCode.append("          if (statusSpan) {");
                jsCode.append("            statusSpan.textContent = '").append(status.equals("completed") ? "تم الرفع" : status).append("';");
                jsCode.append("            statusSpan.className = 'file-status ").append(status).append("';");
                jsCode.append("          }");
                jsCode.append("        });");
                jsCode.append("      })");
                jsCode.append("      .catch(err => console.error('❌ FileStorageDB update failed:', err));");
                jsCode.append("  } else {");
                jsCode.append("    console.error('❌ Neither SyncService nor FileStorageDB available!');");
                jsCode.append("  }");

                jsCode.append("})();");

                // تنفيذ الكود في WebView
                final String finalCode = jsCode.toString();
                Log.e(TAG, "📜 JavaScript code ready - length: " + finalCode.length() + " chars");
                Log.e(TAG, "🚀 Calling webView.evaluateJavascript() NOW...");

                webView.evaluateJavascript(finalCode, result -> {
                    Log.e(TAG, "");
                    Log.e(TAG, "✅✅✅ JavaScript callback received!");
                    if (result != null && !result.equals("null")) {
                        Log.e(TAG, "   📄 Result: " + result);
                    } else {
                        Log.e(TAG, "   ℹ️ Result: null (normal for void functions)");
                    }
                    Log.e(TAG, "");
                });

                Log.e(TAG, "✅ evaluateJavascript() call completed - waiting for callback...");

            } catch (Exception e) {
                Log.e(TAG, "");
                Log.e(TAG, "❌❌❌ EXCEPTION in JavaScript execution:");
                Log.e(TAG, "   Error: " + e.getClass().getSimpleName() + ": " + e.getMessage());
                e.printStackTrace();
                Log.e(TAG, "");
            }
        }, 100); // 100ms delay للتأكد من جاهزية WebView

        Log.e(TAG, "✅ Handler.postDelayed() scheduled - will execute in 100ms");
    }

    /**
     * ════════════════════════════════════════════════════════════════════
     * 🔄 تحديث الإحصائيات فقط (بدون file محدد)
     * ════════════════════════════════════════════════════════════════════
     */
    public static void notifyStatsUpdate() {
        if (mainActivity == null) {
            return;
        }

        mainHandler.post(() -> {
            try {
                Bridge bridge = mainActivity.getBridge();
                if (bridge == null || bridge.getWebView() == null) {
                    return;
                }

                String jsCode = "(function() {" +
                    "  if (typeof FileStorageDB !== 'undefined') {" +
                    "    FileStorageDB.getStats().then(stats => {" +
                    "      const statElements = ['stat-files', 'pending-files-count', 'files-counter'];" +
                    "      statElements.forEach(id => {" +
                    "        const el = document.getElementById(id);" +
                    "        if (el) el.textContent = stats.pending;" +
                    "      });" +
                    "      window.dispatchEvent(new CustomEvent('uploadStatsUpdated', { detail: stats }));" +
                    "    });" +
                    "  }" +
                    "})();";

                bridge.getWebView().evaluateJavascript(jsCode, null);
            } catch (Exception e) {
                Log.w(TAG, "Failed to update stats: " + e.getMessage());
            }
        });
    }

    /**
     * ════════════════════════════════════════════════════════════════════
     * [SmartMedia] إبلاغ الواجهة بأن ملفاً اكتملت معالجته المحلية.
     *
     * تحديث IndexedDB ذري قدر الإمكان: المسار والحجم يُستبدلان بالنسخة
     * النهائية حتى لا يشير IndexedDB إلى ملف محذوف/مؤقت (مواصفات البند 27).
     * ════════════════════════════════════════════════════════════════════
     */
    public static void notifyFileProcessed(final long fileId, final String newPath,
                                           final long originalSize, final long processedSize,
                                           final boolean compressed, final double ratio,
                                           final String stage) {
        if (mainActivity == null) {
            Log.d(TAG, "[SmartMedia] MainActivity not registered — native DB holds final state");
            return;
        }

        mainHandler.postDelayed(() -> {
            try {
                Bridge bridge = mainActivity.getBridge();
                if (bridge == null || bridge.getWebView() == null) {
                    return;
                }

                String jsCode = "(function() {" +
                    "  if (typeof SyncService !== 'undefined' && SyncService.dbGet && SyncService.dbPut) {" +
                    "    SyncService.dbGet('files', " + fileId + ")" +
                    "      .then(file => {" +
                    "        if (!file) return Promise.reject('not found');" +
                    "        if (file.path !== undefined && '" + newPath + "') file.path = '" + newPath + "';" +
                    "        if (file.filePath !== undefined) file.filePath = '" + newPath + "';" +
                    "        if (file.size !== undefined) file.size = " + processedSize + ";" +
                    "        file.compressionEnabled = " + compressed + ";" +
                    "        file.originalSize = " + originalSize + ";" +
                    "        file.processedSize = " + processedSize + ";" +
                    "        file.compressionRatio = " + ratio + ";" +
                    "        file.uploaded = false;" +
                    "        return SyncService.dbPut('files', file);" +
                    "      })" +
                    "      .then(() => {" +
                    "        window.dispatchEvent(new CustomEvent('fileMediaProcessed', {" +
                    "          detail: { fileId: " + fileId + ", stage: '" + stage +
                    "', originalSize: " + originalSize + ", processedSize: " + processedSize +
                    ", compressed: " + compressed + ", ratio: " + ratio + " }" +
                    "        }));" +
                    "      })" +
                    "      .catch(err => console.error('fileMediaProcessed IndexedDB update skipped:', err));" +
                    "  } else {" +
                    "    window.dispatchEvent(new CustomEvent('fileMediaProcessed', {" +
                    "      detail: { fileId: " + fileId + ", stage: '" + stage +
                    "', originalSize: " + originalSize + ", processedSize: " + processedSize +
                    ", compressed: " + compressed + ", ratio: " + ratio + " }" +
                    "    }));" +
                    "  }" +
                    "})();";

                bridge.getWebView().evaluateJavascript(jsCode, null);
            } catch (Exception e) {
                Log.w(TAG, "[SmartMedia] notifyFileProcessed failed: " + e.getMessage());
            }
        }, 100);
    }
}
