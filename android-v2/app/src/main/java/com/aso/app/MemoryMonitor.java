package com.aso.app;

import android.app.ActivityManager;
import android.content.Context;
import android.os.Debug;
import android.util.Log;

/**
 * مراقب الذاكرة لمنع OOM والتعطل
 */
public class MemoryMonitor {
    private static final String TAG = "MemoryMonitor";
    private static MemoryMonitor instance;
    private Context context;

    // حدود الذاكرة
    private long maxMemory;
    private long warningThreshold;
    private long criticalThreshold;

    private MemoryMonitor(Context context) {
        this.context = context.getApplicationContext();

        // الحصول على حد الذاكرة المتاح
        Runtime runtime = Runtime.getRuntime();
        maxMemory = runtime.maxMemory();

        // تحديد العتبات
        warningThreshold = (long) (maxMemory * 0.75); // 75%
        criticalThreshold = (long) (maxMemory * 0.85); // 85%

        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "🧠 MEMORY MONITOR INITIALIZED");
        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "📊 Max Memory: " + formatSize(maxMemory));
        Log.e(TAG, "⚠️  Warning at: " + formatSize(warningThreshold) + " (75%)");
        Log.e(TAG, "🚨 Critical at: " + formatSize(criticalThreshold) + " (85%)");
        Log.e(TAG, "═══════════════════════════════════════════════════");
    }

    public static synchronized MemoryMonitor getInstance(Context context) {
        if (instance == null) {
            instance = new MemoryMonitor(context);
        }
        return instance;
    }

    /**
     * فحص حالة الذاكرة
     */
    public MemoryStatus checkMemory() {
        Runtime runtime = Runtime.getRuntime();

        long totalMemory = runtime.totalMemory();
        long freeMemory = runtime.freeMemory();
        long usedMemory = totalMemory - freeMemory;

        // حساب النسبة المئوية
        int usedPercent = (int) ((usedMemory * 100) / maxMemory);

        MemoryStatus status = new MemoryStatus();
        status.maxMemory = maxMemory;
        status.totalMemory = totalMemory;
        status.usedMemory = usedMemory;
        status.freeMemory = freeMemory;
        status.usedPercent = usedPercent;

        // تحديد مستوى الخطر
        if (usedMemory >= criticalThreshold) {
            status.level = MemoryLevel.CRITICAL;
            Log.e(TAG, "🚨🚨🚨 CRITICAL MEMORY: " + usedPercent + "% used!");
            forceCleanup();
        } else if (usedMemory >= warningThreshold) {
            status.level = MemoryLevel.WARNING;
            Log.w(TAG, "⚠️ WARNING: Memory at " + usedPercent + "%");
            softCleanup();
        } else {
            status.level = MemoryLevel.NORMAL;
        }

        return status;
    }

    /**
     * تنظيف خفيف للذاكرة
     */
    private void softCleanup() {
        Log.d(TAG, "🧹 Soft cleanup...");
        System.gc();
    }

    /**
     * تنظيف قوي للذاكرة
     */
    private void forceCleanup() {
        Log.e(TAG, "🧹🧹🧹 FORCE CLEANUP!");

        // تنظيف متعدد المراحل
        System.gc();

        try {
            Thread.sleep(100);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }

        System.runFinalization();
        System.gc();

        logMemoryStatus();
    }

    /**
     * عرض حالة الذاكرة التفصيلية
     */
    public void logMemoryStatus() {
        Runtime runtime = Runtime.getRuntime();

        long totalMemory = runtime.totalMemory();
        long freeMemory = runtime.freeMemory();
        long usedMemory = totalMemory - freeMemory;
        long availableMemory = maxMemory - usedMemory;

        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "📊 MEMORY STATUS:");
        Log.d(TAG, "   Max:       " + formatSize(maxMemory));
        Log.d(TAG, "   Total:     " + formatSize(totalMemory));
        Log.d(TAG, "   Used:      " + formatSize(usedMemory) + " (" + ((usedMemory * 100) / maxMemory) + "%)");
        Log.d(TAG, "   Free:      " + formatSize(freeMemory));
        Log.d(TAG, "   Available: " + formatSize(availableMemory));
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Native memory
        Debug.MemoryInfo memoryInfo = new Debug.MemoryInfo();
        Debug.getMemoryInfo(memoryInfo);
        Log.d(TAG, "📊 NATIVE MEMORY:");
        Log.d(TAG, "   Native Heap: " + formatSize(memoryInfo.nativePss * 1024L));
        Log.d(TAG, "   Dalvik Heap: " + formatSize(memoryInfo.dalvikPss * 1024L));
        Log.d(TAG, "   Total PSS:   " + formatSize(memoryInfo.getTotalPss() * 1024L));
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    /**
     * التحقق قبل عملية كبيرة
     */
    public boolean canAllocate(long requiredBytes) {
        Runtime runtime = Runtime.getRuntime();
        long availableMemory = maxMemory - (runtime.totalMemory() - runtime.freeMemory());

        // نحتاج ضعف المساحة للأمان
        boolean canAllocate = availableMemory > (requiredBytes * 2);

        if (!canAllocate) {
            Log.e(TAG, "⚠️ Cannot allocate " + formatSize(requiredBytes));
            Log.e(TAG, "   Available: " + formatSize(availableMemory));
            Log.e(TAG, "   Required:  " + formatSize(requiredBytes * 2) + " (with buffer)");
        }

        return canAllocate;
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

    /**
     * مستوى الذاكرة
     */
    public enum MemoryLevel {
        NORMAL,
        WARNING,
        CRITICAL
    }

    /**
     * حالة الذاكرة
     */
    public static class MemoryStatus {
        public long maxMemory;
        public long totalMemory;
        public long usedMemory;
        public long freeMemory;
        public int usedPercent;
        public MemoryLevel level;

        @Override
        public String toString() {
            return "MemoryStatus{" +
                    "usedPercent=" + usedPercent +
                    "%, level=" + level +
                    '}';
        }
    }
}
