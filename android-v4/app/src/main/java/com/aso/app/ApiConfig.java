package com.aso.app;

import android.content.Context;
import android.content.SharedPreferences;

public class ApiConfig {
    public static final String BASE_URL = "https://alhayahorphans.org";

    // جميع المسارات الآن تحت بادئة /api/mobile/v4 (نسخ كامل لخطوط الربط)
    public static final String UPLOAD_FILE_URL = BASE_URL + "/api/mobile/v4/upload-file";
    public static final String LOGIN_URL = BASE_URL + "/api/mobile/v4/login";
    public static final String SPONSORSHIPS_URL = BASE_URL + "/api/mobile/v4/sync/sponsorships";
    public static final String SYNC_URL = BASE_URL + "/api/mobile/v4/sync";
    public static final String UPLOAD_SYNC_URL = BASE_URL + "/api/mobile/v4/sync/upload";

    /**
     * يحوّل مسار نقطة نهاية (نسبياً بعد /api) إلى نسخة mobile/v4.
     * يُستخدم لإعادة كتابة نقاط نهاية الطابور القديمة المخزّنة في قواعد البيانات.
     * المسارات غير المعروفة تُعاد كما هي (سلوك مطابق لما قبل التوجيه).
     */
    public static String toV4(String endpoint) {
        if (endpoint == null || endpoint.isEmpty()) {
            return endpoint;
        }
        String p = endpoint;
        if (p.startsWith("/api/")) {
            p = p.substring(4);
        } else if (p.startsWith("api/")) {
            p = p.substring(3);
        }
        if (!p.startsWith("/")) {
            p = "/" + p;
        }
        if (p.contains("/mobile/v4/")) {
            return p;
        }
        if (p.startsWith("/mobile/")) {
            return "/mobile/v4" + p.substring(7);
        }
        if (p.startsWith("/sync/") || p.startsWith("/uploads/") || p.startsWith("/upload-")
                || p.startsWith("/civil-registry/") || p.startsWith("/registration/") || p.startsWith("/photos/")
                || p.startsWith("/retry-rclone-upload") || p.startsWith("/server-actions")
                || p.startsWith("/verify-password") || p.startsWith("/login") || p.startsWith("/logout")
                || p.startsWith("/health") || p.startsWith("/refresh-token") || p.startsWith("/sponsorship")) {
            return "/mobile/v4" + p;
        }
        return endpoint;
    }

    public static String getBaseUrl(Context context) {
        SharedPreferences prefs = context.getSharedPreferences("api_config", Context.MODE_PRIVATE);
        return prefs.getString("api_base_url", BASE_URL);
    }

    public static void setBaseUrl(Context context, String url) {
        SharedPreferences prefs = context.getSharedPreferences("api_config", Context.MODE_PRIVATE);
        prefs.edit().putString("api_base_url", url).apply();
    }

    public static String getUploadFileUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/v4/upload-file";
    }

    public static String getLoginUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/v4/login";
    }

    public static String getSponsorshipsUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/v4/sync/sponsorships";
    }

    public static String getSyncUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/v4/sync";
    }

    public static String getUploadSyncUrl(Context context) {
        return getBaseUrl(context) + "/api/mobile/v4/sync/upload";
    }
}
