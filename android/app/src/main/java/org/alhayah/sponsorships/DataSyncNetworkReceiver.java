package org.alhayah.sponsorships;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.os.Build;
import android.util.Log;

/**
 * ✨ CRITICAL: BroadcastReceiver مسجل في Manifest
 * يعمل حتى عندما يكون التطبيق مغلقاً تماماً (ليس في Recent Apps)
 * 
 * التسجيل:
 * - في AndroidManifest.xml مباشرة (ليس programmatically)
 * - يُطلق عند تغيير حالة الشبكة (CONNECTIVITY_ACTION)
 * 
 * الوظيفة:
 * - اكتشاف عودة الإنترنت
 * - إعادة تعيين البيانات الفاشلة
 * - بدء DataSyncForegroundService
 */
public class DataSyncNetworkReceiver extends BroadcastReceiver {
    private static final String TAG = "DataSyncNetworkRcvr";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null || context == null) {
            return;
        }

        String action = intent.getAction();
        if (action == null) {
            return;
        }

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "📡 Network state changed - action: " + action);

        // فحص حالة الإنترنت
        if (isNetworkConnected(context)) {
            onInternetConnected(context);
        } else {
            Log.d(TAG, "❌ Internet not available - skipping sync");
        }

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    /**
     * فحص حالة الاتصال بالإنترنت
     */
    private boolean isNetworkConnected(Context context) {
        try {
            ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) {
                return false;
            }

            NetworkInfo activeNetwork = cm.getActiveNetworkInfo();
            return activeNetwork != null && activeNetwork.isConnectedOrConnecting();

        } catch (Exception e) {
            Log.e(TAG, "❌ Error checking network state", e);
            return false;
        }
    }

    /**
     * ✨ يُستدعى عند عودة الإنترنت - حتى لو كان التطبيق مغلقاً
     */
    private void onInternetConnected(Context context) {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🌐 INTERNET CONNECTED - Starting background sync");
        Log.d(TAG, "ℹ️  App state: Possibly CLOSED (Manifest receiver)");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            Log.d(TAG, "🚀 Network reconnected - triggering Unified Master Sync Chain via WorkManager");
            com.aso.app.SyncOrchestrator.scheduleMasterSyncOnReconnect(context);
        } catch (Exception e) {
            Log.e(TAG, "❌ Failed to start master sync chain", e);
        }
    }
}
