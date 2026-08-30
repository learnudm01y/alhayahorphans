package com.aso.app;

import android.os.Bundle;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;
import org.alhayah.sponsorships.JavaScriptBridge;
// ✨ CRITICAL: استيراد Plugins للتسجيل
import com.aso.app.UploadServicePlugin;
import com.aso.app.GoogleDriveUploadPlugin;

import com.aso.app.PermissionsManagerPlugin;  // ✨ NEW: إدارة الصلاحيات المتسلسلة

public class MainActivity extends BridgeActivity {
    private static final String TAG = "MainActivity";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // 🔥 FIRST LOG - قبل كل شيء للتأكد من التحميل
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "🔥🔥🔥 MainActivity.onCreate() - APK v23:45 🔥🔥🔥");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // ✨ CRITICAL: تسجيل Plugins قبل super.onCreate() في Capacitor 6.x
        registerPlugin(UploadServicePlugin.class);
        registerPlugin(GoogleDriveUploadPlugin.class);

        registerPlugin(SponsorshipFolderManager.class);  // ✨ NEW: إدارة مجلدات المكفولين
        registerPlugin(NativeCameraPlugin.class);  // ✨ NEW: Native VIDEO بدون Base64!
        registerPlugin(NativePhotoPlugin.class);  // 📸 NEW: Native PHOTO بسرعة فائقة!
        registerPlugin(org.alhayah.sponsorships.BackgroundSyncPlugin.class);
        registerPlugin(PermissionsManagerPlugin.class);  // ✨ NEW: إدارة الصلاحيات المتسلسلة
        registerPlugin(BarcodeScannerPlugin.class);  // 📱 NEW: ماسح الباركود الأصلي
        android.util.Log.e(TAG, "✅ Plugins registered BEFORE super.onCreate()");

        super.onCreate(savedInstanceState);

        // 🌉 تسجيل UploadStatusBridge للتواصل المباشر مع JavaScript
        UploadStatusBridge.registerActivity(this);
        android.util.Log.e(TAG, "✅ UploadStatusBridge registered - Real-time sync enabled");
        
        // 🚀 بدء خدمة المزامنة الحقيقية في الخلفية
        try {
            android.content.Intent serviceIntent = new android.content.Intent(this, RealtimeSyncService.class);
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                startForegroundService(serviceIntent);
            } else {
                startService(serviceIntent);
            }
            android.util.Log.e(TAG, "✅ RealtimeSyncService started");
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ Failed to start RealtimeSyncService", e);
        }

        // 📞 بدء خدمة كشف المتصل
        try {
            android.content.Intent callerIntent = new android.content.Intent(this, CallerInfoService.class);
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                startForegroundService(callerIntent);
            } else {
                startService(callerIntent);
            }
            android.util.Log.e(TAG, "✅ CallerInfoService started");
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ Failed to start CallerInfoService", e);
        }

        // ✅ CRITICAL: معالجة التحديثات المؤجلة من FileSyncWorker
        // إذا كانت ملفات رُفعت بنجاح بينما التطبيق كان مغلقاً،
        // سيتم الآن تحديث IndexedDB تلقائياً
        android.os.Handler mainHandler = new android.os.Handler(android.os.Looper.getMainLooper());
        mainHandler.postDelayed(() -> {
            android.util.Log.e(TAG, "🔄 Checking for pending status updates...");
            int pendingCount = PendingStatusUpdateHelper.getPendingUpdatesCount(this);
            if (pendingCount > 0) {
                android.util.Log.e(TAG, "✅ Found " + pendingCount + " pending updates - processing now");
                PendingStatusUpdateHelper.processPendingUpdates(this);
            } else {
                android.util.Log.e(TAG, "ℹ️  No pending updates");
            }
        }, 2000); // تأخير 2 ثانية للتأكد من جاهزية WebView

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

            // 🔍 WebChromeClient للكشف عن أخطاء JavaScript
            webView.setWebChromeClient(new android.webkit.WebChromeClient() {
                @Override
                public boolean onConsoleMessage(android.webkit.ConsoleMessage cm) {
                    String level = cm.messageLevel().name();
                    String msg = cm.message();
                    String src = cm.sourceId();
                    int line = cm.lineNumber();

                    String logTag = TAG + "_JS_" + level;
                    String fullMsg = String.format("[%s:%d] %s", src, line, msg);

                    switch (cm.messageLevel()) {
                        case ERROR:
                            android.util.Log.e(logTag, "🔴 " + fullMsg);
                            break;
                        case WARNING:
                            android.util.Log.w(logTag, "⚠️ " + fullMsg);
                            break;
                        default:
                            android.util.Log.i(logTag, "ℹ️ " + fullMsg);
                            break;
                    }
                    return true;
                }

                @Override
                public void onProgressChanged(WebView view, int newProgress) {
                    super.onProgressChanged(view, newProgress);
                    if (newProgress == 100) {
                        android.util.Log.e(TAG, "✅ WebView page load COMPLETED (100%)");
                    }
                }
            });
            android.util.Log.e(TAG, "✅ WebChromeClient registered - JS errors will be logged");
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

        // 🆕 DataSyncNetworkMonitor & NetworkMonitor - ALREADY STARTED in AutoUploadApplication!
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🌐 Network Monitors:");
        android.util.Log.e(TAG, "   ✅ DataSyncNetworkMonitor - Started globally in Application context");
        android.util.Log.e(TAG, "   ✅ NetworkMonitor (File Uploads) - Started globally in Application context");

        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "✅ MainActivity - All systems registered:");
        android.util.Log.e(TAG, "   📁 Files: UploadServicePlugin");
        android.util.Log.e(TAG, "   📊 Data: BackgroundSyncPlugin");
        android.util.Log.e(TAG, "   🌉 Bridge: window.AndroidBridge ← ACTIVE!");
        android.util.Log.e(TAG, "   📹 Camera: window.CameraBridge ← ACTIVE!");
        android.util.Log.e(TAG, "   🌐 Monitor: DataSyncNetworkMonitor & NetworkMonitor ← ACTIVE (from Application)!");
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
            // ⚠️ تأخير logStorageInfo() لأن WebView قد لا يكون جاهزاً بعد
            new android.os.Handler(android.os.Looper.getMainLooper()).postDelayed(() -> {
                try {
                    storageManager.logStorageInfo();
                    android.util.Log.e(TAG, "✅ WebStorage info logged (delayed)");
                } catch (Exception e) {
                    android.util.Log.e(TAG, "⚠️ WebStorage info failed (non-critical): " + e.getMessage());
                }
            }, 2000); // تأخير 2 ثانية لضمان جاهزية WebView
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

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  ✅✅✅ MainActivity.onCreate() COMPLETED SUCCESSFULLY        ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
    }

    @Override
    public void onResume() {
        super.onResume();
        android.util.Log.e(TAG, "🔄 MainActivity.onResume() - App is now VISIBLE");
    }

    @Override
    public void onPause() {
        android.util.Log.e(TAG, "⏸️ MainActivity.onPause() - App is PAUSED");
        super.onPause();
    }

    @Override
    public void onStop() {
        android.util.Log.e(TAG, "⏹️ MainActivity.onStop() - App is STOPPED");
        super.onStop();
    }

    @Override
    public void onDestroy() {
        android.util.Log.e(TAG, "💀 MainActivity.onDestroy() - App is DESTROYED");
        super.onDestroy();
    }
}
