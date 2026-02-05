package org.alhayah.sponsorships;

import android.os.Bundle;
import android.util.Log;
import com.getcapacitor.BridgeActivity;
import com.aso.app.UploadServicePlugin;

public class MainActivity extends BridgeActivity {
    private static final String TAG = "MainActivity";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // تسجيل نظام الرفع الموحد فقط
        registerPlugin(UploadServicePlugin.class);

        super.onCreate(savedInstanceState);

        Log.d(TAG, "✅ MainActivity - نظام الرفع الموحد عبر WorkManager جاهز");
    }
}
