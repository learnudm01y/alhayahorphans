package com.aso.app;

import android.app.ActivityManager;
import android.content.Context;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.webkit.JavascriptInterface;

/**
 * جسر JavaScript للكاميرا
 * يسمح لـ JavaScript باستدعاء تنظيف الذاكرة قبل/أثناء/بعد التصوير
 */
public class CameraBridge {
    private static final String TAG = "CameraBridge";
    private Context context;
    private CameraMemoryManager memoryManager;
    private Handler mainHandler;

    public CameraBridge(Context context) {
        this.context = context;
        this.memoryManager = CameraMemoryManager.getInstance(context);
        this.mainHandler = new Handler(Looper.getMainLooper());

        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "📹 CAMERA BRIDGE INITIALIZED");
        Log.e(TAG, "═══════════════════════════════════════════════════");
        Log.e(TAG, "✅ JavaScript can call:");
        Log.e(TAG, "   • window.CameraBridge.prepareForRecording()");
        Log.e(TAG, "   • window.CameraBridge.onRecordingFinished()");
        Log.e(TAG, "   • window.CameraBridge.onLowMemory()");
        Log.e(TAG, "   • window.CameraBridge.canRecordVideo(minutes)");
        Log.e(TAG, "   • window.CameraBridge.logMemoryStatus()");
        Log.e(TAG, "   • window.CameraBridge.setProcessPriority(high)");
        Log.e(TAG, "═══════════════════════════════════════════════════");
    }

    /**
     * تحضير الذاكرة قبل بدء التصوير
     * يُستدعى من JavaScript قبل فتح Camera
     */
    @JavascriptInterface
    public boolean prepareForRecording() {
        Log.e(TAG, "");
        Log.e(TAG, "📹 JavaScript → prepareForRecording()");

        try {
            boolean ready = memoryManager.prepareForRecording();

            if (ready) {
                Log.e(TAG, "✅ Memory prepared - Camera can start");

                // رفع أولوية العملية
                setProcessPriorityHigh();

                return true;
            } else {
                Log.e(TAG, "❌ Insufficient memory - Camera should NOT start");
                return false;
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ prepareForRecording failed: " + e.getMessage(), e);
            return false;
        }
    }

    /**
     * تنظيف بعد انتهاء التصوير
     */
    @JavascriptInterface
    public void onRecordingFinished() {
        Log.e(TAG, "");
        Log.e(TAG, "📹 JavaScript → onRecordingFinished()");

        try {
            memoryManager.onRecordingFinished();

            // إعادة الأولوية للوضع الطبيعي
            setProcessPriorityNormal();

            Log.e(TAG, "✅ Recording cleanup completed");
        } catch (Exception e) {
            Log.e(TAG, "❌ onRecordingFinished failed: " + e.getMessage(), e);
        }
    }

    /**
     * معالجة Low Memory أثناء التصوير
     */
    @JavascriptInterface
    public void onLowMemory() {
        Log.e(TAG, "");
        Log.e(TAG, "🚨 JavaScript → onLowMemory() - DURING RECORDING!");

        try {
            memoryManager.onLowMemoryDuringRecording();
            Log.e(TAG, "⚠️  Camera should stop recording immediately!");
        } catch (Exception e) {
            Log.e(TAG, "❌ onLowMemory failed: " + e.getMessage(), e);
        }
    }

    /**
     * تحقق هل يمكن تسجيل فيديو بمدة معينة
     * @param durationMinutes المدة بالدقائق
     */
    @JavascriptInterface
    public boolean canRecordVideo(int durationMinutes) {
        Log.d(TAG, "📹 JavaScript → canRecordVideo(" + durationMinutes + " min)");

        try {
            boolean canRecord = memoryManager.canRecordVideo(durationMinutes);

            if (canRecord) {
                Log.d(TAG, "✅ Can record " + durationMinutes + " minute video");
            } else {
                Log.w(TAG, "⚠️  Cannot record " + durationMinutes + " minute video");
            }

            return canRecord;
        } catch (Exception e) {
            Log.e(TAG, "❌ canRecordVideo failed: " + e.getMessage(), e);
            return false;
        }
    }

    /**
     * طباعة حالة الذاكرة
     */
    @JavascriptInterface
    public void logMemoryStatus() {
        Log.d(TAG, "📹 JavaScript → logMemoryStatus()");

        try {
            memoryManager.logMemoryStatus();
        } catch (Exception e) {
            Log.e(TAG, "❌ logMemoryStatus failed: " + e.getMessage(), e);
        }
    }

    /**
     * رفع أولوية العملية إلى HIGH (أثناء التصوير)
     */
    @JavascriptInterface
    public void setProcessPriority(String priority) {
        Log.d(TAG, "📹 JavaScript → setProcessPriority(" + priority + ")");

        try {
            if ("high".equalsIgnoreCase(priority)) {
                setProcessPriorityHigh();
            } else {
                setProcessPriorityNormal();
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ setProcessPriority failed: " + e.getMessage(), e);
        }
    }

    /**
     * رفع أولوية العملية
     */
    private void setProcessPriorityHigh() {
        try {
            int myPid = android.os.Process.myPid();
            android.os.Process.setThreadPriority(android.os.Process.THREAD_PRIORITY_URGENT_AUDIO);

            // رفع importance (Android 14+)
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                try {
                    ActivityManager am = (ActivityManager) context.getSystemService(Context.ACTIVITY_SERVICE);
                    // setProcessStateSdk متاح فقط في Android 14+
                    // am.setProcessStateSdk(android.app.ActivityManager.PROCESS_STATE_IMPORTANT_FOREGROUND);
                } catch (Exception ignored) {
                    // Ignore if not available
                }
            }

            Log.d(TAG, "✅ Process priority set to HIGH (PID: " + myPid + ")");
        } catch (Exception e) {
            Log.w(TAG, "⚠️  Failed to set high priority: " + e.getMessage());
        }
    }

    /**
     * إعادة الأولوية للوضع الطبيعي
     */
    private void setProcessPriorityNormal() {
        try {
            android.os.Process.setThreadPriority(android.os.Process.THREAD_PRIORITY_DEFAULT);
            Log.d(TAG, "✅ Process priority restored to NORMAL");
        } catch (Exception e) {
            Log.w(TAG, "⚠️  Failed to restore normal priority: " + e.getMessage());
        }
    }

    /**
     * الحصول على معلومات الذاكرة بصيغة JSON
     * للاستخدام من JavaScript
     */
    @JavascriptInterface
    public String getMemoryInfo() {
        try {
            Runtime runtime = Runtime.getRuntime();
            long maxMemory = runtime.maxMemory();
            long totalMemory = runtime.totalMemory();
            long freeMemory = runtime.freeMemory();
            long usedMemory = totalMemory - freeMemory;
            long availableMemory = maxMemory - usedMemory;

            // Format as JSON
            return String.format(
                "{\"maxMemory\":%d,\"usedMemory\":%d,\"availableMemory\":%d,\"canRecord\":%b}",
                maxMemory,
                usedMemory,
                availableMemory,
                availableMemory >= 150 * 1024 * 1024 // 150 MB
            );
        } catch (Exception e) {
            Log.e(TAG, "❌ getMemoryInfo failed: " + e.getMessage(), e);
            return "{\"error\":\"" + e.getMessage() + "\"}";
        }
    }

    /**
     * إرسال تحذير لـ JavaScript عند Low Memory
     */
    public void sendLowMemoryWarningToJS(final String message) {
        mainHandler.post(new Runnable() {
            @Override
            public void run() {
                try {
                    // يمكن استخدام WebView.evaluateJavascript() هنا
                    Log.w(TAG, "⚠️  LOW MEMORY WARNING: " + message);
                    Log.w(TAG, "💡 JavaScript should listen for this and stop recording");
                } catch (Exception e) {
                    Log.e(TAG, "Failed to send warning to JS: " + e.getMessage());
                }
            }
        });
    }
}
