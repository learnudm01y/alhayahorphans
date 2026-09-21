package com.aso.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.os.Build;
import android.util.Log;
import androidx.annotation.NonNull;

/**
 * مراقب شبكة موحد - يجمع وظائف NetworkMonitor و DataSyncNetworkMonitor
 * 
 * يراقب حالة الإنترنت ويبدأ المزامنة عند عودة الاتصال:
 * 1. رفع الملفات المعلقة (عبر SyncOrchestrator)
 * 2. مزامنة البيانات (عبر DataSyncForegroundService)
 * 
 * يستخدم:
 * - wasOffline flag لمنع التكرار عند انتقال offline → online
 * - throttle 5 ثوانٍ كطبقة حماية إضافية
 * - NetworkCallback للإصدارات الحديثة (7.0+)
 * - BroadcastReceiver للإصدارات القديمة
 */
public class UnifiedNetworkMonitor {
    private static final String TAG = "UnifiedNetworkMonitor";
    private static UnifiedNetworkMonitor instance;

    private Context context;
    private ConnectivityManager connectivityManager;
    private NetworkCallback networkCallback;
    private BroadcastReceiver legacyReceiver;
    private boolean isNetworkAvailable = false;
    private boolean isMonitoring = false;
    private long lastTriggerTime = 0;
    private static final long THROTTLE_INTERVAL_MS = 5000; // 5 ثوانٍ

    private UnifiedNetworkMonitor(Context context) {
        this.context = context.getApplicationContext();
        this.connectivityManager = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
    }

    public static synchronized UnifiedNetworkMonitor getInstance(Context context) {
        if (instance == null) {
            instance = new UnifiedNetworkMonitor(context.getApplicationContext());
        }
        return instance;
    }

    public void startMonitoring() {
        if (isMonitoring) {
            Log.d(TAG, "⚠️ المراقبة نشطة بالفعل");
            return;
        }

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🔍 بدء المراقبة الموحدة للشبكة");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            isNetworkAvailable = checkNetworkAvailability();
            Log.d(TAG, "📊 حالة الإنترنت الحالية: " + (isNetworkAvailable ? "متصل" : "غير متصل"));

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                startModernMonitoring();
            } else {
                startLegacyMonitoring();
            }

            isMonitoring = true;
            Log.d(TAG, "✅ تم تفعيل المراقبة الموحدة بنجاح");
        } catch (Exception e) {
            Log.e(TAG, "❌ فشل بدء المراقبة: " + e.getMessage(), e);
        }
    }

    public void stopMonitoring() {
        if (!isMonitoring) return;

        Log.d(TAG, "⏸️ إيقاف المراقبة الموحدة");

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                if (networkCallback != null) {
                    connectivityManager.unregisterNetworkCallback(networkCallback);
                    networkCallback = null;
                }
            } else {
                if (legacyReceiver != null) {
                    context.unregisterReceiver(legacyReceiver);
                    legacyReceiver = null;
                }
            }
            isMonitoring = false;
            Log.d(TAG, "✅ تم إيقاف المراقبة");
        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في إيقاف المراقبة: " + e.getMessage(), e);
        }
    }

    private void startModernMonitoring() {
        networkCallback = new NetworkCallback();
        NetworkRequest networkRequest = new NetworkRequest.Builder()
                .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                .addCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
                .build();
        connectivityManager.registerNetworkCallback(networkRequest, networkCallback);
        Log.d(TAG, "📡 NetworkCallback مسجل (Android 7.0+)");
    }

    private void startLegacyMonitoring() {
        legacyReceiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context ctx, Intent intent) {
                if (ConnectivityManager.CONNECTIVITY_ACTION.equals(intent.getAction())) {
                    if (checkNetworkAvailability()) {
                        onInternetConnected();
                    }
                }
            }
        };
        IntentFilter filter = new IntentFilter(ConnectivityManager.CONNECTIVITY_ACTION);
        context.registerReceiver(legacyReceiver, filter);
        Log.d(TAG, "📡 Legacy BroadcastReceiver مسجل (Android < 7.0)");
    }

    private boolean checkNetworkAvailability() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                Network network = connectivityManager.getActiveNetwork();
                if (network == null) return false;
                NetworkCapabilities capabilities = connectivityManager.getNetworkCapabilities(network);
                return capabilities != null &&
                       capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
                       capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);
            } else {
                android.net.NetworkInfo networkInfo = connectivityManager.getActiveNetworkInfo();
                return networkInfo != null && networkInfo.isConnected();
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في فحص الشبكة: " + e.getMessage());
            return false;
        }
    }

    public boolean isNetworkAvailable() {
        return isNetworkAvailable;
    }

    private void onInternetConnected() {
        // Throttling: تجاهل الاستدعاءات المتكررة خلال 5 ثوانٍ
        long currentTime = System.currentTimeMillis();
        if (currentTime - lastTriggerTime < THROTTLE_INTERVAL_MS) {
            Log.d(TAG, "⏩ تجاهل حدث اتصال متكرر (mute throttle)");
            return;
        }
        lastTriggerTime = currentTime;

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🌐 عاد الاتصال بالإنترنت - بدء المزامنة الموحدة");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            // بدء مزامنة البيانات (DataSyncForegroundService)
            org.alhayah.sponsorships.DataSyncForegroundService.startSync(context);
            Log.d(TAG, "✅ DataSyncForegroundService.startSync() تم استدعاؤه");

            // بدء سلسلة المزامنة الرئيسية (رفع ملفات + تنزيل كفالات)
            SyncOrchestrator.scheduleMasterSyncOnReconnect(context);
            Log.d(TAG, "✅ SyncOrchestrator.scheduleMasterSyncOnReconnect() تم استدعاؤه");
        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في معالجة عودة الإنترنت: " + e.getMessage(), e);
        }

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    private class NetworkCallback extends ConnectivityManager.NetworkCallback {
        @Override
        public void onAvailable(@NonNull Network network) {
            super.onAvailable(network);
            boolean wasOffline = !isNetworkAvailable;
            isNetworkAvailable = true;
            Log.d(TAG, "📡 onAvailable()");

            if (wasOffline) {
                Log.d(TAG, "🔥 انتقال offline → online - بدء المزامنة");
                onInternetConnected();
            }
        }

        @Override
        public void onLost(@NonNull Network network) {
            super.onLost(network);
            isNetworkAvailable = false;
            Log.d(TAG, "📡 onLost() - فقدان الشبكة");
        }

        @Override
        public void onCapabilitiesChanged(@NonNull Network network, @NonNull NetworkCapabilities networkCapabilities) {
            super.onCapabilitiesChanged(network, networkCapabilities);
            boolean hasInternet = networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
                                networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);
            if (hasInternet != isNetworkAvailable) {
                isNetworkAvailable = hasInternet;
            }
        }

        @Override
        public void onUnavailable() {
            super.onUnavailable();
            isNetworkAvailable = false;
        }
    }
}
