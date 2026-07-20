package com.aso.app;

import android.content.Context;
import android.content.SharedPreferences;

public class ApiConfig {
    public static final String BASE_URL = "http://10.0.2.2:8000";
    public static final String UPLOAD_FILE_URL = BASE_URL + "/api/mobile/upload-file";
    public static final String LOGIN_URL = BASE_URL + "/api/mobile/login";
    public static final String SPONSORSHIPS_URL = BASE_URL + "/api/mobile/sponsorships";
    public static final String SYNC_URL = BASE_URL + "/api/mobile/sync";
    public static final String UPLOAD_SYNC_URL = BASE_URL + "/api/mobile/upload-sync";

    public static String getBaseUrl(Context context) {
        SharedPreferences prefs = context.getSharedPreferences("api_config", Context.MODE_PRIVATE);
        return prefs.getString("api_base_url", BASE_URL);
    }

    public static void setBaseUrl(Context context, String url) {
        SharedPreferences prefs = context.getSharedPreferences("api_config", Context.MODE_PRIVATE);
        prefs.edit().putString("api_base_url", url).apply();
    }

    public static String getUploadFileUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/upload-file";
    }

    public static String getLoginUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/login";
    }

    public static String getSponsorshipsUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/sponsorships";
    }

    public static String getSyncUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/sync";
    }

    public static String getUploadSyncUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/upload-sync";
    }
}
