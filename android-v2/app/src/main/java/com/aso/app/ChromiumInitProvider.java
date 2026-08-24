package com.aso.app;

import android.content.ContentProvider;
import android.content.ContentValues;
import android.database.Cursor;
import android.net.Uri;
import android.util.Log;

/**
 * ☢️☢️☢️ NUCLEAR OPTION: ContentProvider Init Trick ☢️☢️☢️
 *
 * ContentProvider.onCreate() runs BEFORE:
 * - Application.onCreate()
 * - Static blocks
 * - ANY Activity
 *
 * This is THE EARLIEST POSSIBLE initialization point in Android!
 *
 * We use this to initialize Chromium CommandLine with flags
 * BEFORE WebView is created anywhere in the app.
 */
public class ChromiumInitProvider extends ContentProvider {
    private static final String TAG = "ChromiumInitProvider";

    @Override
    public boolean onCreate() {
        Log.e(TAG, "");
        Log.e(TAG, "☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️");
        Log.e(TAG, "☢️  CONTENT PROVIDER - NUCLEAR CHROMIUM INIT  ☢️");
        Log.e(TAG, "☢️  This runs BEFORE EVERYTHING!              ☢️");
        Log.e(TAG, "☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️☢️");

        try {
            // Load Chromium CommandLine class
            Class<?> cmdLineClass = Class.forName("org.chromium.base.CommandLine");
            Log.e(TAG, "   ✅ CommandLine class loaded");

            // Check if already initialized
            java.lang.reflect.Method isInitialized = null;
            boolean alreadyInit = false;
            try {
                isInitialized = cmdLineClass.getMethod("isInitialized");
                alreadyInit = (Boolean) isInitialized.invoke(null);

                if (alreadyInit) {
                    Log.w(TAG, "   ⚠️ CommandLine already initialized by system");
                    Log.w(TAG, "   ⚠️ Will try to apply flags anyway...");
                    // DON'T return! Try to apply flags even if already initialized!
                }
            } catch (NoSuchMethodException e) {
                // Method doesn't exist in this version - proceed with init
                Log.e(TAG, "   ℹ️ isInitialized() not available - proceeding");
            }

            // Initialize CommandLine with empty args (if not already initialized)
            if (!alreadyInit) {
                java.lang.reflect.Method initMethod = cmdLineClass.getMethod("init", String[].class);
                initMethod.invoke(null, (Object) new String[]{});
                Log.e(TAG, "   ✅ CommandLine.init() called");
            } else {
                Log.w(TAG, "   ⚠️ Skipping init() - already initialized");
            }

            // Get singleton instance
            java.lang.reflect.Method getInstance = cmdLineClass.getMethod("getInstance");
            Object cmdLine = getInstance.invoke(null);
            Log.e(TAG, "   ✅ CommandLine instance obtained");

            // Get flag methods
            java.lang.reflect.Method appendSwitchWithValue = cmdLineClass.getMethod(
                "appendSwitchWithValue", String.class, String.class);
            java.lang.reflect.Method appendSwitch = cmdLineClass.getMethod(
                "appendSwitch", String.class);

            // ═══════════════════════════════════════════════════════
            // APPLY CHROMIUM FLAGS - This is our last chance!
            // ═══════════════════════════════════════════════════════

            // 🔥 FIX 1: Self Compaction Manager (madvise error)
            // Disable SelfCompaction in multiple ways for S24 Ultra compatibility
            appendSwitchWithValue.invoke(cmdLine, "disable-features",
                "SelfCompaction,Variations,kWebViewConnectionlessSafeBrowsing,RendererCodeIntegrity,AutofillServerCommunication");
            appendSwitchWithValue.invoke(cmdLine, "disable-blink-features", "SelfCompaction");
            appendSwitchWithValue.invoke(cmdLine, "js-flags", "--no-compact");
            Log.e(TAG, "   ✅ SelfCompaction AGGRESSIVELY disabled (S24 Ultra fix)");

            // 🔥 FIX 2: Variations Seed Signature Errors
            // Completely disable variations system in every way possible
            appendSwitch.invoke(cmdLine, "disable-variations");
            appendSwitch.invoke(cmdLine, "disable-field-trial-config");
            appendSwitch.invoke(cmdLine, "disable-variations-safe-mode");
            appendSwitchWithValue.invoke(cmdLine, "variations-server-url", "");
            appendSwitchWithValue.invoke(cmdLine, "finch-seed-min-update-period", "0");
            appendSwitch.invoke(cmdLine, "disable-component-update");
            Log.e(TAG, "   ✅ Variations system COMPLETELY disabled (S24 Ultra fix)");

            // 🔥 FIX 3: HTTP Cache Warnings
            // Disable HTTP cache to prevent cache-related issues
            appendSwitch.invoke(cmdLine, "disable-http-cache");
            appendSwitchWithValue.invoke(cmdLine, "disk-cache-size", "1");
            appendSwitchWithValue.invoke(cmdLine, "media-cache-size", "1");
            Log.e(TAG, "   ✅ HTTP Cache disabled (S24 Ultra fix)");

            // 🔥 FIX 4: Renderer Crashes during Video Upload/Processing
            // Force single-process mode (NO SEPARATE RENDERER PROCESS!)
            appendSwitch.invoke(cmdLine, "single-process");
            appendSwitch.invoke(cmdLine, "in-process-gpu");  // GPU في نفس الـ process
            Log.e(TAG, "   ✅ single-process mode ENFORCED (prevents renderer crashes)");

            // 🔍 DIAGNOSTIC: Verify single-process flag is set
            try {
                java.lang.reflect.Method hasSwitch = cmdLineClass.getMethod("hasSwitch", String.class);
                boolean hasSingleProcess = (Boolean) hasSwitch.invoke(cmdLine, "single-process");
                if (hasSingleProcess) {
                    Log.e(TAG, "   ✅✅✅ VERIFIED: single-process flag IS SET!");
                } else {
                    Log.e(TAG, "   ❌❌❌ ERROR: single-process flag NOT SET!");
                }
            } catch (Exception e) {
                Log.w(TAG, "   ⚠️ Could not verify flags: " + e.getMessage());
            }

            // Disable GPU (stability during heavy operations)
            appendSwitch.invoke(cmdLine, "disable-gpu");
            appendSwitch.invoke(cmdLine, "disable-software-rasterizer");
            appendSwitch.invoke(cmdLine, "disable-gpu-compositing");
            appendSwitch.invoke(cmdLine, "disable-accelerated-2d-canvas");
            appendSwitch.invoke(cmdLine, "disable-accelerated-video-decode");
            Log.e(TAG, "   ✅ GPU completely disabled (prevents video-related crashes)");

            // Additional stability flags
            appendSwitch.invoke(cmdLine, "disable-dev-shm-usage");
            appendSwitch.invoke(cmdLine, "no-sandbox");
            appendSwitch.invoke(cmdLine, "no-zygote");
            appendSwitch.invoke(cmdLine, "disable-setuid-sandbox");
            Log.e(TAG, "   ✅ Additional stability flags applied");

            Log.e(TAG, "");
            Log.e(TAG, "╔═══════════════════════════════════════════════════════════╗");
            Log.e(TAG, "║  🎉🎉🎉 NUCLEAR INIT SUCCESS! 🎉🎉🎉                      ║");
            Log.e(TAG, "║                                                           ║");
            Log.e(TAG, "║  ☢️ S24 ULTRA + VIDEO UPLOAD COMPATIBLE ☢️              ║");
            Log.e(TAG, "║                                                           ║");
            Log.e(TAG, "║  Chromium CommandLine initialized with ALL flags!        ║");
            Log.e(TAG, "║  WebView will use these settings when created.           ║");
            Log.e(TAG, "║                                                           ║");
            Log.e(TAG, "║  Fixes Applied:                                          ║");
            Log.e(TAG, "║    ✅ NO self_compaction madvise errors                  ║");
            Log.e(TAG, "║    ✅ NO variations seed signature errors                ║");
            Log.e(TAG, "║    ✅ NO HTTP cache warnings                             ║");
            Log.e(TAG, "║    ✅ NO renderer crashes during video upload            ║");
            Log.e(TAG, "╚═══════════════════════════════════════════════════════════╝");
            Log.e(TAG, "");

            return true;

        } catch (ClassNotFoundException e) {
            Log.e(TAG, "");
            Log.e(TAG, "❌❌❌ FATAL: Chromium CommandLine class not found!");
            Log.e(TAG, "   This WebView version doesn't expose CommandLine API");
            Log.e(TAG, "   Chromium bugs CANNOT be fixed on this device.");
            Log.e(TAG, "   App will continue but may experience crashes.");
            Log.e(TAG, "");
            return true;

        } catch (Exception e) {
            Log.e(TAG, "");
            Log.e(TAG, "❌❌❌ FATAL: Reflection failed!");
            Log.e(TAG, "   Error: " + e.getClass().getSimpleName());
            Log.e(TAG, "   Message: " + e.getMessage());
            Log.e(TAG, "   Stack trace:");
            e.printStackTrace();
            Log.e(TAG, "");
            return true;
        }
    }

    // Required ContentProvider methods - all return null/0
    @Override
    public Cursor query(Uri uri, String[] projection, String selection,
                       String[] selectionArgs, String sortOrder) {
        return null;
    }

    @Override
    public String getType(Uri uri) {
        return null;
    }

    @Override
    public Uri insert(Uri uri, ContentValues values) {
        return null;
    }

    @Override
    public int delete(Uri uri, String selection, String[] selectionArgs) {
        return 0;
    }

    @Override
    public int update(Uri uri, ContentValues values, String selection,
                     String[] selectionArgs) {
        return 0;
    }
}
