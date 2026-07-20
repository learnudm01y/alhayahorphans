package org.alhayah.sponsorships;

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

/**
 * مراقب اتصال الإنترنت لنظام مزامنة البيانات
 * معزول تماماً عن NetworkMonitor (الملفات)
 *
 * يراقب حالة الإنترنت ويبدأ DataSyncForegroundService فور عودة الاتصال
 */
public class DataSyncNetworkMonitor {
    private static final String TAG = "DataSyncNetworkMonitor";
    private static DataSyncNetworkMonitor instance;

    private Context context;
    private ConnectivityManager connectivityManager;
    private NetworkCallback networkCallback;
    private BroadcastReceiver legacyReceiver;
    private boolean isMonitoring = false;
    private long lastInternetConnectedTime = 0;
    private static final long THROTTLE_INTERVAL_MS = 5000; // 5 ثوانٍ

    private DataSyncNetworkMonitor(Context context) {
        this.context = context.getApplicationContext();
        this.connectivityManager = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
    }

    /**
     * Singleton pattern
     */
    public static synchronized DataSyncNetworkMonitor getInstance(Context context) {
        if (instance == null) {
            instance = new DataSyncNetworkMonitor(context);
        }
        return instance;
    }

    /**
     * بدء المراقبة
     */
    public void startMonitoring() {
        if (isMonitoring) {
            Log.d(TAG, "⚠️ Already monitoring network");
            return;
        }

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🌐 DataSyncNetworkMonitor - Starting network monitoring");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            // استخدام NetworkCallback (Android 7.0+)
            startModernMonitoring();
        } else {
            // استخدام BroadcastReceiver (قبل Android 7.0)
            startLegacyMonitoring();
        }

        isMonitoring = true;
        Log.d(TAG, "✅ Network monitoring started successfully");
    }

    /**
     * إيقاف المراقبة
     */
    public void stopMonitoring() {
        if (!isMonitoring) {
            return;
        }

        Log.d(TAG, "🛑 Stopping network monitoring");

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
        Log.d(TAG, "✅ Network monitoring stopped");
    }

    /**
     * NetworkCallback للإصدارات الحديثة (Android 7.0+)
     */
    private void startModernMonitoring() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            networkCallback = new NetworkCallback();

            NetworkRequest networkRequest = new NetworkRequest.Builder()
                    .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                    .build();

            connectivityManager.registerNetworkCallback(networkRequest, networkCallback);

            Log.d(TAG, "📡 Modern NetworkCallback registered (Android 7.0+)");
        }
    }

    /**
     * BroadcastReceiver للإصدارات القديمة (قبل Android 7.0)
     */
    private void startLegacyMonitoring() {
        legacyReceiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context context, Intent intent) {
                if (ConnectivityManager.CONNECTIVITY_ACTION.equals(intent.getAction())) {
                    if (isInternetAvailable()) {
                        Log.d(TAG, "🌐 Internet CONNECTED (legacy receiver)");
                        onInternetConnected();
                    } else {
                        Log.d(TAG, "📴 Internet DISCONNECTED (legacy receiver)");
                    }
                }
            }
        };

        IntentFilter filter = new IntentFilter(ConnectivityManager.CONNECTIVITY_ACTION);
        context.registerReceiver(legacyReceiver, filter);

        Log.d(TAG, "📡 Legacy BroadcastReceiver registered (Android < 7.0)");
    }

    /**
     * NetworkCallback للإصدارات الحديثة
     */
    private class NetworkCallback extends ConnectivityManager.NetworkCallback {
        @Override
        public void onAvailable(Network network) {
            Log.d(TAG, "🌐 Internet CONNECTED (NetworkCallback)");
            onInternetConnected();
        }

        @Override
        public void onLost(Network network) {
            Log.d(TAG, "📴 Internet DISCONNECTED (NetworkCallback)");
        }
    }

    /**
     * يُستدعى عند عودة الإنترنت - يبدأ خدمة المزامنة
     * ✨ CRITICAL FIX: إعادة تعيين البيانات الفاشلة إلى pending قبل بدء المزامنة
     * ✅ مع throttling لمنع الاستدعاءات المتكررة
     */
    private void onInternetConnected() {
        // ✅ Throttling: تجاهل الاستدعاءات المتكررة خلال 5 ثوانٍ
        long currentTime = System.currentTimeMillis();
        if (currentTime - lastInternetConnectedTime < THROTTLE_INTERVAL_MS) {
            Log.d(TAG, "⏩ Ignoring duplicate internet connect event (throttled)");
            return;
        }
        lastInternetConnectedTime = currentTime;

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "✅ Internet connection restored - resetting failed data");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            Log.d(TAG, "🚀 Restored network connection - triggering Unified Master Sync Chain via WorkManager");
            com.aso.app.SyncOrchestrator.scheduleMasterSyncOnReconnect(context);
        } catch (Exception e) {
            Log.e(TAG, "❌ Failed to start master sync chain", e);
        }
    }

    /**
     * فحص حالة الإنترنت
     */
    public boolean isInternetAvailable() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Network network = connectivityManager.getActiveNetwork();
            if (network == null) return false;

            NetworkCapabilities capabilities = connectivityManager.getNetworkCapabilities(network);
            return capabilities != null &&
                   capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
        } else {
            android.net.NetworkInfo networkInfo = connectivityManager.getActiveNetworkInfo();
            return networkInfo != null && networkInfo.isConnected();
        }
    }
}
