package com.aso.app;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private static final String TAG = "MainActivity";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔═══════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  🚀 MainActivity.onCreate() STARTED                        ║");
        android.util.Log.e(TAG, "╚═══════════════════════════════════════════════════════════╝");

        super.onCreate(savedInstanceState);
        android.util.Log.e(TAG, "✅ [1/3] super.onCreate() completed");

        // تسجيل Plugin رفع الملفات
        android.util.Log.e(TAG, "📝 [2/3] Registering UploadServicePlugin...");
        registerPlugin(UploadServicePlugin.class);
        android.util.Log.e(TAG, "✅ [2/3] UploadServicePlugin registered successfully");

        // تسجيل IndexedDBBridge للحصول على أسماء الجمعيات والمكفولين
        android.util.Log.e(TAG, "📝 [3/3] Registering IndexedDBBridge...");
        registerPlugin(IndexedDBBridge.class);
        android.util.Log.e(TAG, "✅ [3/3] IndexedDBBridge registered successfully");

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔═══════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  ✅ MainActivity READY - Plugin Available in JavaScript   ║");
        android.util.Log.e(TAG, "╚═══════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");
    }
}
