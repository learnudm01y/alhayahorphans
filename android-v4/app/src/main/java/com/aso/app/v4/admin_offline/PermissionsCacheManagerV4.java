package com.aso.app.v4.admin_offline;

import android.content.Context;
import android.util.Log;

import com.aso.app.v4.db.AdminOfflineDatabaseHelperV4;

import org.json.JSONArray;
import org.json.JSONObject;

/**
 * Local mirror of spatie roles/permissions (Doc/03 §4).
 *
 * - Server signs the manifest with HMAC; we store signature as-is.
 * - After expires_at (24h from issuance by default), SENSITIVE operations
 *   (delete, permission edits) are restricted; read ops remain available.
 * - No parallel permission system — same permission names as spatie.
 */
public final class PermissionsCacheManagerV4 {
    private static final String TAG = "PermsCacheV4";
    private static final long EXPIRY_WARNING_MS = 6L * 60 * 60 * 1000; // warn < 6h

    private PermissionsCacheManagerV4() {}

    public static void saveManifest(Context context, int userId, JSONObject manifest,
                                    String signature) {
        AdminOfflineDatabaseHelperV4 db = AdminOfflineDatabaseHelperV4.getInstance(context);
        long issuedAt = manifest.optLong("issued_at_ms", System.currentTimeMillis());
        long expiresAt = manifest.optLong("expires_at_ms",
            System.currentTimeMillis() + 24L * 60 * 60 * 1000);
        try {
            db.savePermissionsManifest(userId, manifest.toString(), signature,
                issuedAt, expiresAt);
            Log.i(TAG, "manifest saved for user " + userId
                + " perms=" + (manifest.optJSONArray("permissions") != null
                    ? manifest.optJSONArray("permissions").length() : 0));
        } catch (Exception e) {
            Log.e(TAG, "save failed", e);
        }
    }

    public static JSONObject getManifest(Context context, int userId) {
        return AdminOfflineDatabaseHelperV4.getInstance(context)
            .getPermissionsManifest(userId);
    }

    public static boolean isExpired(Context context, int userId) {
        return AdminOfflineDatabaseHelperV4.getInstance(context)
            .isManifestExpired(userId);
    }

    public static boolean isNearExpiry(Context context, int userId) {
        JSONObject m = getManifest(context, userId);
        if (m == null) return true;
        long expires = m.optLong("expires_at_ms", 0);
        long remaining = expires - System.currentTimeMillis();
        return remaining < EXPIRY_WARNING_MS;
    }

    /**
     * @return true when the user holds the permission (offline check).
     * When manifest is missing/expired, read permissions fail-closed for
     * sensitive ops only — callers decide via canPerformSensitive().
     */
    public static boolean hasPermission(Context context, int userId, String permission) {
        JSONObject m = getManifest(context, userId);
        if (m == null) return false;
        JSONArray perms = m.optJSONArray("permissions");
        if (perms == null) return false;
        for (int i = 0; i < perms.length(); i++) {
            if (permission.equals(perms.optString(i, ""))) return true;
        }
        return false;
    }

    public static boolean hasRole(Context context, int userId, String role) {
        JSONObject m = getManifest(context, userId);
        if (m == null) return false;
        JSONArray roles = m.optJSONArray("roles");
        if (roles == null) return false;
        for (int i = 0; i < roles.length(); i++) {
            if (role.equals(roles.optString(i, ""))) return true;
        }
        return false;
    }

    /**
     * Sensitive ops (delete / edit permissions) require BOTH the permission
     * AND a non-expired manifest (Doc/03 §4).
     */
    public static boolean canPerformSensitive(Context context, int userId, String permission) {
        if (isExpired(context, userId)) {
            Log.w(TAG, "sensitive op blocked — manifest expired for user " + userId);
            return false;
        }
        return hasPermission(context, userId, permission);
    }
}
