package com.aso.app;

import android.Manifest;
import android.app.AlarmManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.webkit.WebView;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.getcapacitor.BridgeActivity;
import org.alhayah.sponsorships.JavaScriptBridge;
import org.alhayah.sponsorships.DataSyncNetworkMonitor;
// ✨ CRITICAL: استيراد Plugins للتسجيل
import com.aso.app.UploadServicePlugin;
import com.aso.app.GoogleDriveUploadPlugin;
import com.aso.app.IndexedDBBridge;

public class MainActivity extends BridgeActivity {
    private static final String TAG = "MainActivity";
    private static final int PERMISSION_REQUEST_CODE = 100;
    
    // ✨ ActivityResultLauncher for exact alarms (Android 12+)
    private ActivityResultLauncher<Intent> exactAlarmLauncher;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // 🔥 FIRST LOG - قبل كل شيء للتأكد من التحميل
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "🔥🔥🔥 MainActivity.onCreate() - v11:00 FINAL 🔥🔥🔥");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // ✨ Register launcher before super.onCreate()
        exactAlarmLauncher = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(),
            result -> {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
                    if (alarmManager != null && alarmManager.canScheduleExactAlarms()) {
                        android.util.Log.e(TAG, "✅ Exact alarms permission granted!");
                    } else {
                        android.util.Log.e(TAG, "⚠️ Exact alarms still not granted");
                    }
                }
            }
        );

        // ✨ CRITICAL: تسجيل Plugins قبل super.onCreate() في Capacitor 6.x
        registerPlugin(UploadServicePlugin.class);
        registerPlugin(GoogleDriveUploadPlugin.class);
        registerPlugin(IndexedDBBridge.class);
        registerPlugin(SponsorshipFolderManager.class);  // ✨ NEW: إدارة مجلدات المكفولين
        registerPlugin(org.alhayah.sponsorships.BackgroundSyncPlugin.class);
        android.util.Log.e(TAG, "✅ Plugins registered BEFORE super.onCreate()");

        super.onCreate(savedInstanceState);

        // ✨ REQUEST ALL REQUIRED PERMISSIONS (Android 13+)
        requestAllPermissions();

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

        // 🆕 بدء مراقبة الإنترنت لـ Data Sync (Offline-First)
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🌐🌐🌐 STARTING DATA SYNC NETWORK MONITOR 🌐🌐🌐");
        try {
            DataSyncNetworkMonitor.getInstance(this).startMonitoring();
            android.util.Log.e(TAG, "✅✅✅ Network monitoring active - will auto-sync on reconnect");
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌❌❌ FAILED to start network monitor", e);
        }

        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "✅ MainActivity - All systems registered:");
        android.util.Log.e(TAG, "   📁 Files: UploadServicePlugin");
        android.util.Log.e(TAG, "   📊 Data: BackgroundSyncPlugin");
        android.util.Log.e(TAG, "   🌉 Bridge: window.AndroidBridge ← ACTIVE!");
        android.util.Log.e(TAG, "   🌐 Monitor: DataSyncNetworkMonitor ← ACTIVE!");
        android.util.Log.e(TAG, "   🔐 Permissions: Requested on startup");
    }

    /**
     * ✨ طلب جميع الأذونات المطلوبة للعمل في الخلفية
     */
    private void requestAllPermissions() {
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  🔐 REQUESTING CRITICAL PERMISSIONS                          ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");

        // 1. POST_NOTIFICATIONS (Android 13+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED) {
                android.util.Log.e(TAG, "⚠️ POST_NOTIFICATIONS not granted - requesting...");
                ActivityCompat.requestPermissions(this,
                    new String[]{Manifest.permission.POST_NOTIFICATIONS},
                    PERMISSION_REQUEST_CODE);
            } else {
                android.util.Log.e(TAG, "✅ POST_NOTIFICATIONS already granted");
            }
        } else {
            android.util.Log.e(TAG, "✅ POST_NOTIFICATIONS not required (Android < 13)");
        }

        // 2. SCHEDULE_EXACT_ALARM (Android 12+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
            if (alarmManager != null && !alarmManager.canScheduleExactAlarms()) {
                android.util.Log.e(TAG, "⚠️ SCHEDULE_EXACT_ALARM not granted - requesting...");
                android.util.Log.e(TAG, "🔔 Opening system settings for exact alarms...");
                
                Intent intent = new Intent(Settings.ACTION_REQUEST_SCHEDULE_EXACT_ALARM);
                intent.setData(Uri.parse("package:" + getPackageName()));
                exactAlarmLauncher.launch(intent);
            } else {
                android.util.Log.e(TAG, "✅ SCHEDULE_EXACT_ALARM already granted");
            }
        } else {
            android.util.Log.e(TAG, "✅ SCHEDULE_EXACT_ALARM not required (Android < 12)");
        }

        // 3. Battery Optimization (Optional but recommended)
        requestBatteryOptimizationExemption();

        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
    }

    /**
     * طلب تعطيل Battery Optimization لضمان عمل الخدمات في الخلفية
     */
    private void requestBatteryOptimizationExemption() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            android.os.PowerManager pm = (android.os.PowerManager) getSystemService(Context.POWER_SERVICE);
            if (pm != null && !pm.isIgnoringBatteryOptimizations(getPackageName())) {
                android.util.Log.e(TAG, "⚠️ Battery optimization is ON - this may kill background services");
                android.util.Log.e(TAG, "💡 Recommend user to disable it from Settings > Battery");
                
                // Optional: Show dialog to request exemption
                try {
                    Intent intent = new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                    intent.setData(Uri.parse("package:" + getPackageName()));
                    startActivity(intent);
                    android.util.Log.e(TAG, "🔋 Opened battery optimization settings");
                } catch (Exception e) {
                    android.util.Log.e(TAG, "⚠️ Could not open battery settings: " + e.getMessage());
                }
            } else {
                android.util.Log.e(TAG, "✅ Battery optimization is DISABLED - services will run smoothly");
            }
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        
        if (requestCode == PERMISSION_REQUEST_CODE) {
            for (int i = 0; i < permissions.length; i++) {
                if (grantResults[i] == PackageManager.PERMISSION_GRANTED) {
                    android.util.Log.e(TAG, "✅ Permission granted: " + permissions[i]);
                } else {
                    android.util.Log.e(TAG, "❌ Permission denied: " + permissions[i]);
                }
            }
        }
    }
}
