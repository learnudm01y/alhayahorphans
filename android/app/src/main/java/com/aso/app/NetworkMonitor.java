package com.aso.app;

import android.content.Context;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.os.Build;
import android.util.Log;
import androidx.annotation.NonNull;

/**
 * مراقب حالة الإنترنت - يكتشف تلقائيًا عودة الاتصال بالإنترنت
 * ويقوم برفع الملفات المعلقة من قاعدة البيانات المحلية
 */
public class NetworkMonitor {
    private static final String TAG = "NetworkMonitor";
    private static NetworkMonitor instance;

    private Context context;
    private ConnectivityManager connectivityManager;
    private NetworkCallback networkCallback;
    private boolean isNetworkAvailable = false;
    private boolean isMonitoring = false;
    private UploadTaskScheduler uploadScheduler;
    private UploadDatabaseHelper dbHelper;

    /**
     * الحصول على Instance واحدة (Singleton)
     */
    public static synchronized NetworkMonitor getInstance(Context context) {
        if (instance == null) {
            instance = new NetworkMonitor(context.getApplicationContext());
        }
        return instance;
    }

    private NetworkMonitor(Context context) {
        this.context = context;
        this.connectivityManager = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
        this.uploadScheduler = UploadTaskScheduler.getInstance(context);
        this.dbHelper = UploadDatabaseHelper.getInstance(context);

        Log.d(TAG, "✅ NetworkMonitor تم إنشاؤه");
    }

    /**
     * بدء مراقبة حالة الشبكة
     */
    public void startMonitoring() {
        if (isMonitoring) {
            Log.d(TAG, "⚠️ المراقبة نشطة بالفعل");
            return;
        }

        Log.d(TAG, "");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🔍🔍🔍 بدء مراقبة حالة الإنترنت 🔍🔍🔍");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            // فحص الحالة الحالية للإنترنت
            isNetworkAvailable = checkNetworkAvailability();
            Log.d(TAG, "📊 حالة الإنترنت الحالية: " + (isNetworkAvailable ? "متصل ✅" : "غير متصل ❌"));

            // إنشاء Network Callback
            networkCallback = new NetworkCallback();

            // طلب مراقبة جميع أنواع الشبكات
            NetworkRequest networkRequest = new NetworkRequest.Builder()
                .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                .addCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
                .build();

            // تسجيل Callback
            connectivityManager.registerNetworkCallback(networkRequest, networkCallback);

            isMonitoring = true;
            Log.d(TAG, "✅ تم تفعيل المراقبة بنجاح");
            Log.d(TAG, "🎯 سيتم رفع الملفات تلقائيًا عند عودة الإنترنت");
            Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log.d(TAG, "");

        } catch (Exception e) {
            Log.e(TAG, "❌ فشل بدء المراقبة: " + e.getMessage(), e);
        }
    }

    /**
     * إيقاف مراقبة حالة الشبكة
     */
    public void stopMonitoring() {
        if (!isMonitoring) {
            return;
        }

        Log.d(TAG, "⏸️ إيقاف مراقبة الإنترنت");

        try {
            if (networkCallback != null) {
                connectivityManager.unregisterNetworkCallback(networkCallback);
                networkCallback = null;
            }
            isMonitoring = false;
            Log.d(TAG, "✅ تم إيقاف المراقبة");
        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في إيقاف المراقبة: " + e.getMessage(), e);
        }
    }

    /**
     * فحص حالة الإنترنت الحالية
     */
    private boolean checkNetworkAvailability() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                Network network = connectivityManager.getActiveNetwork();
                if (network == null) {
                    return false;
                }

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

    /**
     * الحصول على حالة الإنترنت الحالية
     */
    public boolean isNetworkAvailable() {
        return isNetworkAvailable;
    }

    /**
     * معالجة حالة عودة الإنترنت - رفع الملفات المعلقة
     */
    private void handleNetworkAvailable() {
        Log.d(TAG, "");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🌐🌐🌐 تم الاتصال بالإنترنت! 🌐🌐🌐");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            // إعادة تعيين الملفات الفاشلة إلى pending
            int resetCount = dbHelper.resetFailedFiles();
            if (resetCount > 0) {
                Log.d(TAG, "🔄 تم إعادة تعيين " + resetCount + " ملف فاشل للمحاولة مرة أخرى");
            }

            // فحص وجود ملفات معلقة
            int pendingCount = dbHelper.getPendingFilesCount();
            Log.d(TAG, "📊 عدد الملفات المعلقة: " + pendingCount);

            if (pendingCount > 0) {
                Log.d(TAG, "");
                Log.d(TAG, "🔥🔥🔥 جدولة رفع " + pendingCount + " ملف معلق! 🔥🔥🔥");
                Log.d(TAG, "");

                // ═══════════════════════════════════════════════════════════════════
                // 🔄 Single Sync Orchestrator - استخدام FileSyncWorker بدلاً من ForegroundService
                // ═══════════════════════════════════════════════════════════════════
                // FileSyncWorker سيعالج الطابور بالتسلسل (ملف واحد في كل مرة)
                // ForegroundService يُستخدم فقط للملفات الكبيرة >= 10MB
                // ExistingWorkPolicy.KEEP يمنع تكرار العمل
                // ═══════════════════════════════════════════════════════════════════

                try {
                    // جدولة Worker للمعالجة الفورية
                    com.aso.app.FileSyncWorker.scheduleImmediateSync(context);
                    Log.d(TAG, "✅ FileSyncWorker scheduled - الرفع سيبدأ فوراً");
                    Log.d(TAG, "   ✅ UniqueWork policy يمنع تكرار Workers");
                    Log.d(TAG, "   ✅ معالجة تسلسلية (ملف واحد في كل مرة)");
                } catch (Exception workerError) {
                    Log.e(TAG, "❌ فشل جدولة FileSyncWorker: " + workerError.getMessage(), workerError);
                }
            } else {
                Log.d(TAG, "ℹ️  لا توجد ملفات معلقة للرفع");
            }

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في معالجة عودة الإنترنت: " + e.getMessage(), e);
        }

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "");
    }

    /**
     * معالجة حالة انقطاع الإنترنت
     */
    private void handleNetworkLost() {
        Log.d(TAG, "");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "❌❌❌ انقطع الاتصال بالإنترنت ❌❌❌");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "ℹ️  الملفات الجديدة ستبقى في قاعدة البيانات");
        Log.d(TAG, "ℹ️  سيتم رفعها تلقائيًا عند عودة الإنترنت");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "");
    }

    /**
     * Network Callback - استجابة لتغيرات حالة الشبكة
     */
    private class NetworkCallback extends ConnectivityManager.NetworkCallback {

        @Override
        public void onAvailable(@NonNull Network network) {
            super.onAvailable(network);

            boolean wasOffline = !isNetworkAvailable;
            isNetworkAvailable = true;

            Log.d(TAG, "📡 onAvailable() - شبكة متاحة");

            // إذا كنا غير متصلين من قبل، نرفع الملفات
            if (wasOffline) {
                handleNetworkAvailable();
            }
        }

        @Override
        public void onLost(@NonNull Network network) {
            super.onLost(network);

            isNetworkAvailable = false;
            Log.d(TAG, "📡 onLost() - فقدان الشبكة");
            handleNetworkLost();
        }

        @Override
        public void onCapabilitiesChanged(@NonNull Network network, @NonNull NetworkCapabilities networkCapabilities) {
            super.onCapabilitiesChanged(network, networkCapabilities);

            boolean hasInternet = networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
                                networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);

            boolean wasOffline = !isNetworkAvailable;
            isNetworkAvailable = hasInternet;

            if (hasInternet && wasOffline) {
                Log.d(TAG, "📡 onCapabilitiesChanged() - الإنترنت متاح الآن");
                handleNetworkAvailable();
            } else if (!hasInternet && !wasOffline) {
                Log.d(TAG, "📡 onCapabilitiesChanged() - الإنترنت غير متاح");
                handleNetworkLost();
            }
        }

        @Override
        public void onUnavailable() {
            super.onUnavailable();
            isNetworkAvailable = false;
            Log.d(TAG, "📡 onUnavailable() - الشبكة غير متاحة");
        }
    }
}
