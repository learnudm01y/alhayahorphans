package com.aso.app;

import android.os.Bundle;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;
import org.alhayah.sponsorships.JavaScriptBridge;

public class MainActivity extends BridgeActivity {
    private static final String TAG = "MainActivity";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // 🔥 FIRST LOG - قبل كل شيء للتأكد من التحميل
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "🔥🔥🔥 MainActivity.onCreate() - NEW APK v10:08 🔥🔥🔥");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        super.onCreate(savedInstanceState);

        // تسجيل Plugins
        registerPlugin(UploadServicePlugin.class);
        registerPlugin(GoogleDriveUploadPlugin.class);
        registerPlugin(IndexedDBBridge.class);
        registerPlugin(org.alhayah.sponsorships.BackgroundSyncPlugin.class);

        // ✨ JavaScript Bridge - ربط مباشر بين JS و Java ✨
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🌉🌉🌉 ADDING JAVASCRIPT BRIDGE 🌉🌉🌉");
        try {
            WebView webView = getBridge().getWebView();
            JavaScriptBridge jsBridge = new JavaScriptBridge(this);
            webView.addJavascriptInterface(jsBridge, "AndroidBridge");
            android.util.Log.e(TAG, "✅✅✅ window.AndroidBridge is NOW AVAILABLE!");
            android.util.Log.e(TAG, "🎯 JavaScript can call: window.AndroidBridge.onDataSaved(...)");
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌❌❌ FAILED to add AndroidBridge", e);
        }
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "✅ MainActivity - All systems registered:");
        android.util.Log.e(TAG, "   📁 Files: UploadServicePlugin");
        android.util.Log.e(TAG, "   📊 Data: BackgroundSyncPlugin");
        android.util.Log.e(TAG, "   🌉 Bridge: window.AndroidBridge ← ACTIVE!");
    }
}
