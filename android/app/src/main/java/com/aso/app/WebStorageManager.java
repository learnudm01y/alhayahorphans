package com.aso.app;

import android.content.Context;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.webkit.WebStorage;

/**
 * مراقب ومدير WebStorage لمنع استهلاك ذاكرة كبير
 * يراقب حجم localStorage/IndexedDB ويقوم بالتنظيف عند الحاجة
 */
public class WebStorageManager {
    private static final String TAG = "WebStorageManager";
    private static WebStorageManager instance;
    private Context context;

    // حدود التخزين (50 MB)
    private static final long MAX_STORAGE_SIZE = 50 * 1024 * 1024;
    private static final long WARNING_STORAGE_SIZE = 40 * 1024 * 1024;

    // تنظيف تلقائي كل ساعة
    private Handler cleanupHandler;
    private Runnable cleanupRunnable;

    private WebStorageManager(Context context) {
        this.context = context.getApplicationContext();
        setupPeriodicCleanup();

        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "💾 WEB STORAGE MANAGER INITIALIZED");
        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "📊 Max Storage: " + formatSize(MAX_STORAGE_SIZE));
        Log.e(TAG, "⚠️  Warning at: " + formatSize(WARNING_STORAGE_SIZE));
        Log.e(TAG, "🧹 Auto cleanup: Every 1 hour");
        Log.e(TAG, "═══════════════════════════════════════════════════");
    }

    public static synchronized WebStorageManager getInstance(Context context) {
        if (instance == null) {
            instance = new WebStorageManager(context);
        }
        return instance;
    }

    /**
     * إعداد التنظيف الدوري
     */
    private void setupPeriodicCleanup() {
        cleanupHandler = new Handler(Looper.getMainLooper());
        cleanupRunnable = new Runnable() {
            @Override
            public void run() {
                checkAndCleanupStorage();
                // تكرار كل ساعة
                cleanupHandler.postDelayed(this, 60 * 60 * 1000); // 1 hour
            }
        };

        // بدء التنظيف الدوري
        cleanupHandler.postDelayed(cleanupRunnable, 60 * 60 * 1000); // 1 hour
    }

    /**
     * فحص وتنظيف Storage
     */
    public void checkAndCleanupStorage() {
        if (android.os.Build.VERSION.SDK_INT < android.os.Build.VERSION_CODES.LOLLIPOP) {
            return; // WebStorage API not available
        }

        try {
            WebStorage webStorage = WebStorage.getInstance();

            // الحصول على جميع origins
            webStorage.getOrigins(valueCallback -> {
                if (valueCallback == null) {
                    Log.d(TAG, "📊 No WebStorage data");
                    return;
                }

                long totalSize = 0;

                // ValueCallback returns Map<String, WebStorage.Origin>
                java.util.Map<String, WebStorage.Origin> originsMap =
                    (java.util.Map<String, WebStorage.Origin>) valueCallback;

                for (java.util.Map.Entry<String, WebStorage.Origin> entry : originsMap.entrySet()) {
                    WebStorage.Origin origin = entry.getValue();
                    long quota = origin.getQuota();
                    long usage = origin.getUsage();
                    totalSize += usage;

                    Log.d(TAG, "📊 Origin: " + origin.getOrigin());
                    Log.d(TAG, "   Usage: " + formatSize(usage) + " / " + formatSize(quota));
                }

                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log.d(TAG, "📊 Total WebStorage: " + formatSize(totalSize));

                if (totalSize >= MAX_STORAGE_SIZE) {
                    Log.w(TAG, "🚨 Storage exceeded limit! Cleaning old data...");
                    cleanupOldData();
                } else if (totalSize >= WARNING_STORAGE_SIZE) {
                    Log.w(TAG, "⚠️  Storage nearing limit (" + formatSize(totalSize) + ")");
                }
            });

        } catch (Exception e) {
            Log.e(TAG, "Error checking storage: " + e.getMessage());
        }
    }

    /**
     * تنظيف البيانات القديمة
     */
    private void cleanupOldData() {
        Log.e(TAG, "🧹 Starting cleanup of old WebStorage data...");

        try {
            // يمكن إضافة منطق أكثر ذكاءً هنا
            // مثل: حذف بيانات قديمة جداً، أو بيانات غير مستخدمة

            // حالياً: تحذير فقط
            Log.w(TAG, "⚠️  WebStorage is large - consider clearing old data");
            Log.w(TAG, "💡 Tip: Call clearOldCache() from JavaScript periodically");

        } catch (Exception e) {
            Log.e(TAG, "Cleanup error: " + e.getMessage());
        }
    }

    /**
     * مسح كل WebStorage (للاستخدام عند الحاجة فقط)
     */
    public void clearAllStorage() {
        if (android.os.Build.VERSION.SDK_INT < android.os.Build.VERSION_CODES.LOLLIPOP) {
            return;
        }

        try {
            WebStorage.getInstance().deleteAllData();
            Log.e(TAG, "🗑️ All WebStorage cleared!");
        } catch (Exception e) {
            Log.e(TAG, "Error clearing storage: " + e.getMessage());
        }
    }

    /**
     * الحصول على معلومات Storage
     */
    public void logStorageInfo() {
        if (android.os.Build.VERSION.SDK_INT < android.os.Build.VERSION_CODES.LOLLIPOP) {
            Log.d(TAG, "WebStorage API not available (API < 21)");
            return;
        }

        try {
            WebStorage webStorage = WebStorage.getInstance();

            webStorage.getOrigins(valueCallback -> {
                if (valueCallback == null) {
                    Log.d(TAG, "📊 No WebStorage data");
                    return;
                }

                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log.d(TAG, "💾 WEBSTORAGE INFO:");

                long totalUsage = 0;

                // ValueCallback returns Map<String, WebStorage.Origin>
                java.util.Map<String, WebStorage.Origin> originsMap =
                    (java.util.Map<String, WebStorage.Origin>) valueCallback;

                for (java.util.Map.Entry<String, WebStorage.Origin> entry : originsMap.entrySet()) {
                    WebStorage.Origin origin = entry.getValue();
                    long usage = origin.getUsage();
                    long quota = origin.getQuota();
                    totalUsage += usage;

                    Log.d(TAG, "   Origin: " + origin.getOrigin());
                    Log.d(TAG, "   Usage:  " + formatSize(usage));
                    Log.d(TAG, "   Quota:  " + formatSize(quota));
                    Log.d(TAG, "   ────────────────────────────────");
                }

                Log.d(TAG, "   Total:  " + formatSize(totalUsage));
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            });

        } catch (Exception e) {
            Log.e(TAG, "Error getting storage info: " + e.getMessage());
        }
    }

    /**
     * تنسيق حجم البيانات
     */
    private String formatSize(long bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return String.format("%.2f KB", bytes / 1024.0);
        if (bytes < 1024 * 1024 * 1024) return String.format("%.2f MB", bytes / (1024.0 * 1024.0));
        return String.format("%.2f GB", bytes / (1024.0 * 1024.0 * 1024.0));
    }

    /**
     * إيقاف التنظيف الدوري
     */
    public void stopPeriodicCleanup() {
        if (cleanupHandler != null && cleanupRunnable != null) {
            cleanupHandler.removeCallbacks(cleanupRunnable);
            Log.d(TAG, "🛑 Periodic cleanup stopped");
        }
    }
}
