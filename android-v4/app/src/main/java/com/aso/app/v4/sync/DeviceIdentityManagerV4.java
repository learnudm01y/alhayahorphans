package com.aso.app.v4.sync;

import android.content.Context;
import android.content.SharedPreferences;
import android.provider.Settings;
import android.util.Log;

import java.util.UUID;

/**
 * Stable per-install device identity used for:
 *  - X-Device-Id header on every v4 API call
 *  - device_registry_v4 registration
 *  - sync_origin_device_id column on server records
 *
 * Never rotates on app update. Cleared only on explicit user logout+reset.
 */
public final class DeviceIdentityManagerV4 {
    private static final String TAG = "DeviceIdentityV4";
    private static final String PREFS = "device_identity_v4";
    private static final String KEY_DEVICE_ID = "device_id";
    private static final String KEY_REGISTERED = "server_registered";

    private DeviceIdentityManagerV4() {}

    private static SharedPreferences prefs(Context context) {
        return context.getApplicationContext().getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    public static String getDeviceId(Context context) {
        SharedPreferences p = prefs(context);
        String id = p.getString(KEY_DEVICE_ID, null);
        if (id == null || id.isEmpty()) {
            // Prefer Android ID as a stable seed, fall back to random UUID.
            String seed;
            try {
                seed = Settings.Secure.getString(
                    context.getContentResolver(), Settings.Secure.ANDROID_ID);
            } catch (Exception e) {
                seed = null;
            }
            if (seed == null || seed.isEmpty() || "9774d56d682e549c".equals(seed)) {
                id = "dev-" + UUID.randomUUID();
            } else {
                id = "dev-" + seed;
            }
            id = id.length() > 64 ? id.substring(0, 64) : id;
            p.edit().putString(KEY_DEVICE_ID, id).apply();
            Log.i(TAG, "Generated device id: " + id);
        }
        return id;
    }

    public static String getDeviceLabel(Context context) {
        try {
            return android.os.Build.MANUFACTURER + " " + android.os.Build.MODEL;
        } catch (Exception e) {
            return "unknown";
        }
    }

    public static String getAppVersion(Context context) {
        try {
            return context.getPackageManager()
                .getPackageInfo(context.getPackageName(), 0).versionName;
        } catch (Exception e) {
            return "unknown";
        }
    }

    public static boolean isRegisteredOnServer(Context context) {
        return prefs(context).getBoolean(KEY_REGISTERED, false);
    }

    public static void markRegistered(Context context) {
        prefs(context).edit().putBoolean(KEY_REGISTERED, true).apply();
    }

    public static void resetRegistration(Context context) {
        prefs(context).edit().putBoolean(KEY_REGISTERED, false).apply();
    }
}
