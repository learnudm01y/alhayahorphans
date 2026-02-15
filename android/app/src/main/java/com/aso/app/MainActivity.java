package com.aso.app;

import android.os.Bundle;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;
import org.alhayah.sponsorships.JavaScriptBridge;
// ✨ CRITICAL: استيراد Plugins للتسجيل
import com.aso.app.UploadServicePlugin;
import com.aso.app.GoogleDriveUploadPlugin;
import com.aso.app.IndexedDBBridge;

public class MainActivity extends BridgeActivity {
    private static final String TAG = "MainActivity";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // 🔥 FIRST LOG - قبل كل شيء للتأكد من التحميل
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "🔥🔥🔥 MainActivity.onCreate() - APK v22:56 🔥🔥🔥");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // ✨ CRITICAL: تسجيل Plugins قبل super.onCreate() في Capacitor 6.x
        registerPlugin(UploadServicePlugin.class);
        registerPlugin(GoogleDriveUploadPlugin.class);
        registerPlugin(IndexedDBBridge.class);
        registerPlugin(SponsorshipFolderManager.class);  // ✨ NEW: إدارة مجلدات المكفولين
        registerPlugin(NativeCameraPlugin.class);  // ✨ NEW: Native Camera بدون Base64!
        registerPlugin(org.alhayah.sponsorships.BackgroundSyncPlugin.class);
        android.util.Log.e(TAG, "✅ Plugins registered BEFORE super.onCreate()");

        super.onCreate(savedInstanceState);

        // ⚡ Static block in AutoUploadApplication already applied Chromium fixes
        android.util.Log.e(TAG, "✅ Chromium flags applied by AutoUploadApplication static block");

        // 🔧 WebView optimization AFTER it's created by Capacitor
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🔧🔧🔧 WEBVIEW OPTIMIZATION 🔧🔧🔧");
        try {
            WebView webView = getBridge().getWebView();
            android.webkit.WebSettings webSettings = webView.getSettings();

            // � DISABLE GPU - Force software rendering for stability!
            // CRITICAL: Don't use LAYER_TYPE_HARDWARE - causes GPU crashes!
            webView.setLayerType(android.view.View.LAYER_TYPE_NONE, null);
            android.util.Log.e(TAG, "🚫 GPU rendering DISABLED - using software/none layer");

            // 🧹 تنظيف كامل للـ Cache
            webView.clearCache(true);
            webView.clearHistory();
            webSettings.setCacheMode(android.webkit.WebSettings.LOAD_NO_CACHE);

            // ✅ تفعيل DomStorage (ضروري لـ localStorage/IndexedDB)
            // لكن مع تحديد quota لمنع استهلاك ذاكرة كبير
            webSettings.setDomStorageEnabled(true);
            webSettings.setDatabaseEnabled(true);

            // تحديد حد للـ storage (50 MB)
            if (android.os.Build.VERSION.SDK_INT < android.os.Build.VERSION_CODES.KITKAT) {
                webSettings.setDatabasePath(getApplicationContext().getDir("databases", android.content.Context.MODE_PRIVATE).getPath());
            }

            // ✅ تفعيل JavaScript (ضروري)
            webSettings.setJavaScriptEnabled(true);

            // 🖼️ تحميل الصور فقط عند الحاجة
            webSettings.setLoadsImagesAutomatically(true);

            // 🚫 تعطيل ميزات غير ضرورية
            webSettings.setGeolocationEnabled(false);
            webSettings.setSaveFormData(false);
            webSettings.setSavePassword(false);

            // ⚡ تحسين الأداء
            webSettings.setRenderPriority(android.webkit.WebSettings.RenderPriority.HIGH);

            // 🧠 إدارة ذكية للذاكرة
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.KITKAT) {
                // تفعيل معالجة memory pressure
                webView.getSettings().setMixedContentMode(android.webkit.WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
            }

            // 🗑️ تنظيف دوري للذاكرة
            webView.setOnLongClickListener(v -> {
                System.gc();
                return false;
            });

            android.util.Log.e(TAG, "✅ WebView optimized - Memory leaks prevented");
            android.util.Log.e(TAG, "✅ Cache: DISABLED");
            android.util.Log.e(TAG, "✅ DomStorage: ENABLED (50 MB quota)");
            android.util.Log.e(TAG, "✅ Database: ENABLED");
            android.util.Log.e(TAG, "🚫 Hardware Acceleration: DISABLED (stability fix)");
            android.util.Log.e(TAG, "✅ JavaScript: ENABLED");
        } catch (Exception e) {
            android.util.Log.e(TAG, "⚠️ WebView optimization failed: " + e.getMessage());
        }

        // 🔄 إضافة listener لإدارة الذاكرة
        android.util.Log.e(TAG, "🧠 Setting up memory management listener...");
        registerComponentCallbacks(new android.content.ComponentCallbacks() {
            @Override
            public void onConfigurationChanged(android.content.res.Configuration newConfig) {}

            @Override
            public void onLowMemory() {
                android.util.Log.e(TAG, "⚠️⚠️⚠️ LOW MEMORY WARNING - Cleaning up...");
                try {
                    WebView webView = getBridge().getWebView();

                    // تحرير ذاكرة WebView
                    webView.freeMemory();

                    // مسح Cache (لكن ليس Storage!)
                    webView.clearCache(true);

                    // تنظيف عام
                    System.gc();

                    android.util.Log.e(TAG, "✅ Memory cleaned (Cache cleared, Storage preserved)");
                } catch (Exception e) {
                    android.util.Log.e(TAG, "⚠️ Memory cleanup failed: " + e.getMessage());
                }
            }
        });

        // ✨ JavaScript Bridge - ربط مباشر بين JS و Java ✨
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🌉🌉🌉 ADDING JAVASCRIPT BRIDGES 🌉🌉🌉");
        try {
            WebView webView = getBridge().getWebView();

            // Bridge للـ Data Sync
            JavaScriptBridge jsBridge = new JavaScriptBridge(this);
            webView.addJavascriptInterface(jsBridge, "AndroidBridge");
            android.util.Log.e(TAG, "✅ window.AndroidBridge - Data Sync ← ACTIVE!");

            // Bridge للـ Camera
            CameraBridge cameraBridge = new CameraBridge(this);
            webView.addJavascriptInterface(cameraBridge, "CameraBridge");
            android.util.Log.e(TAG, "✅ window.CameraBridge - Video Recording ← ACTIVE!");

            // تسجيل WebView في CameraMemoryManager
            CameraMemoryManager cameraMemMgr = CameraMemoryManager.getInstance(this);
            cameraMemMgr.registerWebView(webView);
            android.util.Log.e(TAG, "✅ WebView registered in CameraMemoryManager");

        } catch (Exception e) {
            android.util.Log.e(TAG, "❌❌❌ FAILED to add JavaScript Bridges", e);
        }

        // 🆕 DataSyncNetworkMonitor - ALREADY STARTED in AutoUploadApplication!
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🌐 Data Sync Network Monitor:");
        android.util.Log.e(TAG, "   ✅ Already started in AutoUploadApplication.onCreate()");
        android.util.Log.e(TAG, "   ✅ No need to start again - runs globally in Application context");

        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "✅ MainActivity - All systems registered:");
        android.util.Log.e(TAG, "   📁 Files: UploadServicePlugin");
        android.util.Log.e(TAG, "   📊 Data: BackgroundSyncPlugin");
        android.util.Log.e(TAG, "   🌉 Bridge: window.AndroidBridge ← ACTIVE!");
        android.util.Log.e(TAG, "   📹 Camera: window.CameraBridge ← ACTIVE!");
        android.util.Log.e(TAG, "   🌐 Monitor: DataSyncNetworkMonitor ← ACTIVE (from Application)!");
        android.util.Log.e(TAG, "   🧠 Memory: MemoryMonitor ← ACTIVE!");
        android.util.Log.e(TAG, "   💾 Storage: WebStorageManager ← ACTIVE!");
        android.util.Log.e(TAG, "   📹 CameraMemory: CameraMemoryManager ← ACTIVE!");

        // 🧠 بدء مراقبة الذاكرة
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🧠🧠🧠 STARTING MEMORY MONITOR 🧠🧠🧠");
        try {
            MemoryMonitor memoryMonitor = MemoryMonitor.getInstance(this);
            memoryMonitor.logMemoryStatus();
            android.util.Log.e(TAG, "✅ MemoryMonitor initialized successfully");
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ MemoryMonitor failed: " + e.getMessage(), e);
        }

        // 💾 بدء مراقبة WebStorage
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "💾💾💾 STARTING WEBSTORAGE MANAGER 💾💾💾");
        try {
            WebStorageManager storageManager = WebStorageManager.getInstance(this);
            storageManager.logStorageInfo();
            android.util.Log.e(TAG, "✅ WebStorageManager initialized successfully");
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ WebStorageManager failed: " + e.getMessage(), e);
        }

        // 📹 بدء مراقبة ذاكرة الكاميرا
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "📹📹📹 STARTING CAMERA MEMORY MANAGER 📹📹📹");
        try {
            CameraMemoryManager cameraMemMgr = CameraMemoryManager.getInstance(this);
            cameraMemMgr.logMemoryStatus();
            android.util.Log.e(TAG, "✅ CameraMemoryManager initialized successfully");
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ CameraMemoryManager failed: " + e.getMessage(), e);
        }
    }
}
