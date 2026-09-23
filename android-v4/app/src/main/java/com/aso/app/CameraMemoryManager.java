package com.aso.app;

import android.app.ActivityManager;
import android.content.Context;
import android.os.Debug;
import android.util.Log;
import android.webkit.WebView;

/**
 * مدير ذاكرة خاص بالتصوير والفيديو
 * يقوم بتنظيف الذاكرة بشكل استباقي قبل/أثناء/بعد التصوير
 */
public class CameraMemoryManager {
    private static final String TAG = "CameraMemoryManager";
    private static CameraMemoryManager instance;
    private Context context;
    private WebView webView;

    // استراتيجية تنظيف قبل التصوير
    private static final long MIN_FREE_MEMORY_FOR_CAMERA = 150 * 1024 * 1024; // 150 MB
    private static final long CRITICAL_CAMERA_THRESHOLD = 100 * 1024 * 1024;  // 100 MB

    private CameraMemoryManager(Context context) {
        this.context = context.getApplicationContext();

        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "📹 CAMERA MEMORY MANAGER INITIALIZED");
        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "🎯 Min Free for Camera: " + formatSize(MIN_FREE_MEMORY_FOR_CAMERA));
        Log.e(TAG, "🚨 Critical Threshold: " + formatSize(CRITICAL_CAMERA_THRESHOLD));
        Log.e(TAG, "═══════════════════════════════════════════════════");
    }

    public static synchronized CameraMemoryManager getInstance(Context context) {
        if (instance == null) {
            instance = new CameraMemoryManager(context);
        }
        return instance;
    }

    /**
     * تسجيل WebView لتنظيفه عند الحاجة
     */
    public void registerWebView(WebView webView) {
        this.webView = webView;
        Log.d(TAG, "✅ WebView registered for camera memory management");
    }

    /**
     * تنظيف استباقي قبل بدء التصوير
     * يُستدعى من JavaScript قبل فتح Camera
     */
    public boolean prepareForRecording() {
        Log.e(TAG, "");
        Log.e(TAG, "📹📹📹 PREPARING FOR VIDEO RECORDING 📹📹📹");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // 1. فحص الذاكرة الحالية
        Runtime runtime = Runtime.getRuntime();
        long maxMemory = runtime.maxMemory();
        long totalMemory = runtime.totalMemory();
        long freeMemory = runtime.freeMemory();
        long usedMemory = totalMemory - freeMemory;
        long availableMemory = maxMemory - usedMemory;

        Log.e(TAG, "📊 BEFORE CLEANUP:");
        Log.e(TAG, "   Max Memory:       " + formatSize(maxMemory));
        Log.e(TAG, "   Used Memory:      " + formatSize(usedMemory));
        Log.e(TAG, "   Available Memory: " + formatSize(availableMemory));

        // 2. إذا الذاكرة المتاحة كافية، لا داعي للتنظيف العميق
        if (availableMemory >= MIN_FREE_MEMORY_FOR_CAMERA) {
            Log.e(TAG, "✅ Sufficient memory available - ready for recording");
            return true;
        }

        // 3. تنظيف خفيف
        Log.w(TAG, "⚠️  Low memory - performing soft cleanup...");
        softCleanup();

        // 4. فحص مرة أخرى
        availableMemory = runtime.maxMemory() - (runtime.totalMemory() - runtime.freeMemory());
        Log.e(TAG, "📊 AFTER SOFT CLEANUP: " + formatSize(availableMemory));

        if (availableMemory >= MIN_FREE_MEMORY_FOR_CAMERA) {
            Log.e(TAG, "✅ Soft cleanup successful - ready for recording");
            return true;
        }

        // 5. تنظيف عميق (aggressive)
        Log.w(TAG, "🚨 Critical memory - performing AGGRESSIVE cleanup...");
        aggressiveCleanup();

        // 6. فحص نهائي
        availableMemory = runtime.maxMemory() - (runtime.totalMemory() - runtime.freeMemory());
        Log.e(TAG, "📊 AFTER AGGRESSIVE CLEANUP: " + formatSize(availableMemory));

        if (availableMemory >= CRITICAL_CAMERA_THRESHOLD) {
            Log.e(TAG, "✅ Aggressive cleanup successful - recording possible");
            return true;
        }

        // 7. فشل - لا توجد ذاكرة كافية
        Log.e(TAG, "❌❌❌ INSUFFICIENT MEMORY - CANNOT START RECORDING");
        Log.e(TAG, "❌ Required: " + formatSize(CRITICAL_CAMERA_THRESHOLD));
        Log.e(TAG, "❌ Available: " + formatSize(availableMemory));
        return false;
    }

    /**
     * تنظيف خفيف - Cache فقط
     */
    private void softCleanup() {
        try {
            // WebView Cache
            if (webView != null) {
                webView.clearCache(true);
                Log.d(TAG, "   🧹 WebView cache cleared");
            }

            // System GC
            System.gc();
            Log.d(TAG, "   🧹 System.gc() called");

            // Sleep قصير للسماح للـ GC بالعمل
            Thread.sleep(100);

        } catch (Exception e) {
            Log.e(TAG, "Soft cleanup error: " + e.getMessage());
        }
    }

    /**
     * تنظيف عميق - كل شيء ما عدا localStorage
     */
    private void aggressiveCleanup() {
        try {
            // 1. WebView cleanup
            if (webView != null) {
                webView.clearCache(true);
                webView.clearHistory();
                webView.freeMemory();
                Log.d(TAG, "   🧹🧹 WebView fully cleaned");
            }

            // 2. MemoryMonitor cleanup
            MemoryMonitor memMonitor = MemoryMonitor.getInstance(context);
            // لا نستخدم forceCleanup() لأنها private - نستدعي checkMemory() بدلاً
            memMonitor.checkMemory();
            Log.d(TAG, "   🧹🧹 MemoryMonitor cleanup triggered");

            // 3. Multiple GC calls
            for (int i = 0; i < 3; i++) {
                System.gc();
                System.runFinalization();
                Thread.sleep(50);
            }
            Log.d(TAG, "   🧹🧹 Multiple GC rounds completed");

        } catch (Exception e) {
            Log.e(TAG, "Aggressive cleanup error: " + e.getMessage());
        }
    }

    /**
     * تنظيف بعد انتهاء التصوير
     */
    public void onRecordingFinished() {
        Log.e(TAG, "");
        Log.e(TAG, "📹 RECORDING FINISHED - Cleanup");
        softCleanup();
        logMemoryStatus();
    }

    /**
     * إيقاف التصوير بسبب ذاكرة منخفضة
     */
    public void onLowMemoryDuringRecording() {
        Log.e(TAG, "");
        Log.e(TAG, "🚨🚨🚨 LOW MEMORY DURING RECORDING! 🚨🚨🚨");
        Log.e(TAG, "⚠️  Camera should stop recording immediately!");

        // تنظيف فوري
        softCleanup();
    }

    /**
     * الحصول على معلومات الذاكرة
     */
    public void logMemoryStatus() {
        Runtime runtime = Runtime.getRuntime();
        long maxMemory = runtime.maxMemory();
        long totalMemory = runtime.totalMemory();
        long freeMemory = runtime.freeMemory();
        long usedMemory = totalMemory - freeMemory;
        long availableMemory = maxMemory - usedMemory;

        // Native memory (optional)
        Debug.MemoryInfo memInfo = new Debug.MemoryInfo();
        Debug.getMemoryInfo(memInfo);

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "📊 CAMERA MEMORY STATUS:");
        Log.d(TAG, "   Heap - Max:       " + formatSize(maxMemory) + " (largeHeap)");
        Log.d(TAG, "   Heap - Used:      " + formatSize(usedMemory));
        Log.d(TAG, "   Heap - Available: " + formatSize(availableMemory));
        Log.d(TAG, "   Native - Total:   " + formatSize(memInfo.getTotalPss() * 1024L));
        Log.d(TAG, "   Native - Private: " + formatSize(memInfo.getTotalPrivateDirty() * 1024L));

        // Camera readiness
        if (availableMemory >= MIN_FREE_MEMORY_FOR_CAMERA) {
            Log.d(TAG, "   ✅ READY for video recording");
        } else if (availableMemory >= CRITICAL_CAMERA_THRESHOLD) {
            Log.w(TAG, "   ⚠️  LOW memory - short videos only");
        } else {
            Log.e(TAG, "   ❌ CRITICAL - recording may fail!");
        }
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    /**
     * تحقق هل يمكن تسجيل فيديو بمدة معينة
     * @param durationMinutes المدة المتوقعة بالدقائق
     * @return true إذا كانت الذاكرة كافية
     */
    public boolean canRecordVideo(int durationMinutes) {
        Runtime runtime = Runtime.getRuntime();
        long availableMemory = runtime.maxMemory() - (runtime.totalMemory() - runtime.freeMemory());

        // تقدير استهلاك الذاكرة: ~30 MB لكل دقيقة (1080p)
        long estimatedMemory = durationMinutes * 30L * 1024 * 1024;

        Log.d(TAG, "📹 Video Recording Estimate:");
        Log.d(TAG, "   Duration: " + durationMinutes + " minutes");
        Log.d(TAG, "   Estimated Memory: " + formatSize(estimatedMemory));
        Log.d(TAG, "   Available Memory: " + formatSize(availableMemory));

        if (availableMemory >= estimatedMemory + MIN_FREE_MEMORY_FOR_CAMERA) {
            Log.d(TAG, "   ✅ Can record " + durationMinutes + " minute video");
            return true;
        } else {
            Log.w(TAG, "   ⚠️  Insufficient memory for " + durationMinutes + " minute video");
            return false;
        }
    }

    /**
     * تنسيق حجم الذاكرة
     */
    private String formatSize(long bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return String.format("%.2f KB", bytes / 1024.0);
        if (bytes < 1024 * 1024 * 1024) return String.format("%.2f MB", bytes / (1024.0 * 1024.0));
        return String.format("%.2f GB", bytes / (1024.0 * 1024.0 * 1024.0));
    }
}
