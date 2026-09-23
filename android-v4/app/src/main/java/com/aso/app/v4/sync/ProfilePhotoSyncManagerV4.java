package com.aso.app.v4.sync;

import android.content.Context;
import android.util.Log;

import com.aso.app.v4.admin_offline.AdminOfflineDataStoreV4;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * Doc/11 — الصور الشخصية محلياً دائماً / باقي الوثائق on-demand فقط.
 *
 * يُستدعى في كل دورة UnifiedSyncOrchestratorV4 بعد Phase 4 (pullAdminOffline).
 * يحمّل فقط cache_priority='profile_photo' AND local_cache_path IS NULL.
 * لا يفتح أي قاعدة بيانات قديمة — يلمس admin_offline_v4.db فقط.
 */
public final class ProfilePhotoSyncManagerV4 {
    private static final String TAG = "ProfilePhotoSyncV4";
    private static final int HTTP_TIMEOUT_MS = 30_000;
    private static final int MAX_PER_CYCLE = 40;

    private ProfilePhotoSyncManagerV4() {}

    /**
     * يُحمّل الصور الشخصية المعلّقة. يُستدعى بعد pullAdminOffline في runFullCycle.
     * الفشل هنا لا يُفشل الدورة — صور شخصية فقط.
     */
    public static void downloadPendingProfilePhotos(Context context, String baseUrl,
                                                    String token, String deviceId) {
        try {
            JSONArray pending = queryPendingProfilePhotos(context);
            if (pending == null || pending.length() == 0) {
                return;
            }
            Log.i(TAG, "pending profile photos: " + pending.length());
            int downloaded = 0;
            for (int i = 0; i < pending.length() && downloaded < MAX_PER_CYCLE; i++) {
                try {
                    JSONObject entry = pending.getJSONObject(i);
                    String clientUuid = entry.optString("client_uuid", null);
                    String filePath = entry.optString("file_path", null);
                    if (clientUuid == null || clientUuid.isEmpty() || filePath == null || filePath.isEmpty()) {
                        continue;
                    }
                    String localPath = downloadAndStore(context, baseUrl, token, deviceId, filePath, clientUuid);
                    if (localPath != null) {
                        markCached(context, clientUuid, localPath);
                        downloaded++;
                    }
                } catch (Exception e) {
                    Log.w(TAG, "photo download failed (continuing): " + e.getMessage());
                }
            }
            Log.i(TAG, "downloaded " + downloaded + " profile photos");
        } catch (Exception e) {
            Log.w(TAG, "downloadPendingProfilePhotos failed: " + e.getMessage());
        }
    }

    private static JSONArray queryPendingProfilePhotos(Context context) {
        // عبر الـ facade — لا نلمس SQLite مباشرة
        JSONArray all = AdminOfflineDataStoreV4.queryFileIndex(context, "");
        JSONArray out = new JSONArray();
        if (all == null) return out;
        for (int i = 0; i < all.length(); i++) {
            try {
                JSONObject row = all.getJSONObject(i);
                String priority = row.optString("cache_priority", "on_demand");
                String localPath = row.optString("local_cache_path", null);
                if ("profile_photo".equals(priority)
                        && (localPath == null || localPath.isEmpty())) {
                    out.put(row);
                }
            } catch (Exception ignored) {}
        }
        return out;
    }

    private static String downloadAndStore(Context context, String baseUrl, String token,
                                           String deviceId, String remotePath, String clientUuid) {
        HttpURLConnection conn = null;
        InputStream in = null;
        FileOutputStream out = null;
        try {
            String url = resolveUrl(baseUrl, remotePath);
            conn = (HttpURLConnection) new URL(url).openConnection();
            conn.setConnectTimeout(HTTP_TIMEOUT_MS);
            conn.setReadTimeout(HTTP_TIMEOUT_MS);
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("X-Device-Id", deviceId);
            conn.setRequestProperty("Accept", "*/*");
            int code = conn.getResponseCode();
            if (code != 200) {
                Log.w(TAG, "HTTP " + code + " for " + remotePath);
                return null;
            }
            File dir = new File(context.getCacheDir(), "v4_profile_photos");
            if (!dir.exists() && !dir.mkdirs()) {
                Log.w(TAG, "cannot create cache dir");
                return null;
            }
            File file = new File(dir, clientUuid + ".img");
            in = conn.getInputStream();
            out = new FileOutputStream(file);
            byte[] buf = new byte[8192];
            int n;
            while ((n = in.read(buf)) > 0) {
                out.write(buf, 0, n);
            }
            out.flush();
            return file.getAbsolutePath();
        } catch (Exception e) {
            Log.w(TAG, "download failed: " + e.getMessage());
            return null;
        } finally {
            try { if (out != null) out.close(); } catch (Exception ignored) {}
            try { if (in != null) in.close(); } catch (Exception ignored) {}
            if (conn != null) conn.disconnect();
        }
    }

    private static String resolveUrl(String baseUrl, String remotePath) {
        if (remotePath.startsWith("http://") || remotePath.startsWith("https://")) {
            return remotePath;
        }
        if (remotePath.startsWith("/")) {
            return baseUrl + remotePath;
        }
        return baseUrl + "/" + remotePath;
    }

    private static void markCached(Context context, String clientUuid, String localPath) {
        try {
            // إعادة استخدام upsert موجود — نقرأ الصف ثم نعيد كتابته مع local_cache_path/cached_at
            JSONArray all = AdminOfflineDataStoreV4.queryFileIndex(context, clientUuid);
            if (all != null && all.length() > 0) {
                JSONObject entry = all.getJSONObject(0);
                entry.put("local_cache_path", localPath);
                entry.put("cached_at", System.currentTimeMillis());
                AdminOfflineDataStoreV4.upsertFileIndexEntry(context, entry);
            }
        } catch (Exception e) {
            Log.w(TAG, "markCached failed: " + e.getMessage());
        }
    }
}
