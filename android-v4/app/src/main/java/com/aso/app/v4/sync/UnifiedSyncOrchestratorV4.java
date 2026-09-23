package com.aso.app.v4.sync;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import com.aso.app.ApiConfig;
import com.aso.app.v4.db.SyncDatabaseHelperV4;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.List;

/**
 * Single coordinator for ALL v4 sync traffic. Replaces the two v3 engines:
 *   - SponsorshipSyncWorker  (com.aso.app)
 *   - DataSyncWorker         (org.alhayah.sponsorships)
 *
 * Phases (state machine — each runs once per cycle, no duplicate requests):
 *   1. ensureDeviceRegistered  → POST /api/mobile/v4/device/register
 *   2. drainOutbox              → POST /api/mobile/v4/sync/actions  (batch)
 *   3. pullIncremental          → GET  /api/mobile/v4/sync/pull?since=
 *   4. pullAdminOffline         → GET  /api/mobile/v4/admin/*  (dashboard,
 *                                 reports, permissions, file index) — cached
 *                                 in admin_offline_v4.db, failure is non-fatal.
 *
 * Dual-Run: this class ONLY touches sync_v4.db + admin_offline_v4.db.
 * It never opens sponsorships_data.db / related_data.db / data_sync.db / upload_queue.db.
 */
public final class UnifiedSyncOrchestratorV4 {
    private static final String TAG = "UnifiedSyncV4";
    private static final int OUTBOX_BATCH = 50;
    private static final int HTTP_TIMEOUT_MS = 60_000;

    private static final String STATE_LAST_PULL = "last_pull_at";

    private UnifiedSyncOrchestratorV4() {}

    // ---------------------------------------------------------------
    // Public entry — full cycle
    // ---------------------------------------------------------------

    /** Runs one full sync cycle. Returns null on success, error message on failure. */
    public static String runFullCycle(Context context) {
        Log.i(TAG, "━━━ v4 sync cycle start ━━━");
        try {
            String token = getAuthToken(context);
            if (token == null || token.isEmpty()) {
                return "no_auth_token";
            }
            String deviceId = DeviceIdentityManagerV4.getDeviceId(context);
            String baseUrl = normalizeBaseUrl(ApiConfig.getBaseUrl(context));

            // Phase 1 — device handshake (once ever, then cheap health ping)
            try {
                ensureDeviceRegistered(context, baseUrl, token, deviceId);
            } catch (Exception e) {
                Log.w(TAG, "device register failed (continuing): " + e.getMessage());
            }

            // Phase 1b — remote config flags (Phase 7 cutover). Non-fatal.
            try {
                RemoteConfigManagerV4.fetchAndApply(context, baseUrl, token, deviceId);
            } catch (Exception e) {
                Log.w(TAG, "remote config flags failed (continuing): " + e.getMessage());
            }

            // Phase 2 — drain outbox (upload local edits)
            String drainError = drainOutbox(context, baseUrl, token, deviceId);
            if (drainError != null) {
                return drainError; // keep outbox for retry; pull still safe but skip for clarity
            }

            // Phase 3 — incremental pull (download server changes)
            String pullError = pullIncremental(context, baseUrl, token, deviceId);
            if (pullError != null) {
                return pullError;
            }

            // Phase 4 — admin offline data (dashboard / reports / permissions / file index)
            // Failure here must not fail the whole cycle — admin data is read-only cache.
            try {
                pullAdminOffline(context, baseUrl, token, deviceId);
            } catch (Exception e) {
                Log.w(TAG, "admin offline pull failed (continuing): " + e.getMessage());
            }

            // Phase 4b — profile photos always-local (Doc/11). Non-fatal.
            try {
                ProfilePhotoSyncManagerV4.downloadPendingProfilePhotos(
                    context, baseUrl, token, deviceId);
            } catch (Exception e) {
                Log.w(TAG, "profile photo sync failed (continuing): " + e.getMessage());
            }

            Log.i(TAG, "━━━ v4 sync cycle OK ━━━");
            return null;
        } catch (Exception e) {
            Log.e(TAG, "sync cycle crashed", e);
            return e.getMessage() != null ? e.getMessage() : "unknown_error";
        }
    }

    // ---------------------------------------------------------------
    // Phase 1 — device registry
    // ---------------------------------------------------------------

    static void ensureDeviceRegistered(Context context, String baseUrl, String token,
                                       String deviceId) throws Exception {
        if (DeviceIdentityManagerV4.isRegisteredOnServer(context)) {
            return;
        }
        JSONObject body = new JSONObject();
        body.put("device_id", deviceId);
        body.put("device_label", DeviceIdentityManagerV4.getDeviceLabel(context));
        body.put("app_version", DeviceIdentityManagerV4.getAppVersion(context));
        body.put("health_status", "healthy");

        HttpResponse resp = httpPost(baseUrl + "/api/mobile/v4/device/register",
            body.toString(), token, deviceId, null);
        if (resp.code >= 200 && resp.code < 300) {
            DeviceIdentityManagerV4.markRegistered(context);
            Log.i(TAG, "device registered: " + deviceId);
        } else if (resp.code == 401) {
            throw new SecurityException("Unauthorized during device register");
        } else {
            Log.w(TAG, "device register HTTP " + resp.code + ": " + resp.body);
        }
    }

    // ---------------------------------------------------------------
    // Phase 2 — outbox drain
    // ---------------------------------------------------------------

    static String drainOutbox(Context context, String baseUrl, String token, String deviceId) {
        SyncDatabaseHelperV4 db = SyncDatabaseHelperV4.getInstance(context);
        int pending = db.getPendingCount();
        if (pending == 0) {
            Log.d(TAG, "outbox empty");
            return null;
        }
        Log.i(TAG, "draining outbox: " + pending + " pending");

        List<SyncDatabaseHelperV4.OutboxItem> batch = SyncOutboxManagerV4.peekBatch(context);
        if (batch.isEmpty()) return null;

        try {
            JSONArray actions = new JSONArray();
            for (SyncDatabaseHelperV4.OutboxItem item : batch) {
                JSONObject a = new JSONObject();
                a.put("idempotency_key", item.idempotencyKey);
                a.put("entity_type", item.entityType);
                a.put("client_uuid", item.clientUuid);
                a.put("payload", new JSONObject(item.payloadJson));
                actions.put(a);
            }
            JSONObject body = new JSONObject();
            body.put("actions", actions);

            HttpResponse resp = httpPost(baseUrl + "/api/mobile/v4/sync/actions",
                body.toString(), token, deviceId, null);

            if (resp.code == 401) return "unauthorized";
            if (resp.code < 200 || resp.code >= 300) {
                // Server-side failure: mark attempts, keep pending for next cycle.
                for (SyncDatabaseHelperV4.OutboxItem item : batch) {
                    SyncOutboxManagerV4.markFailed(context, item.id,
                        "HTTP " + resp.code + " " + safeSnippet(resp.body));
                }
                return "outbox_http_" + resp.code;
            }

            // Parse per-action results and ack each outbox row.
            JSONObject json = new JSONObject(resp.body);
            JSONArray results = json.optJSONArray("results");
            if (results != null) {
                for (int i = 0; i < results.length() && i < batch.size(); i++) {
                    JSONObject r = results.getJSONObject(i);
                    SyncDatabaseHelperV4.OutboxItem item = batch.get(i);
                    if (r.optBoolean("success", false)) {
                        ConflictResolverV4.absorbServerResult(context, item.clientUuid,
                            item.entityType, item.payloadJson, r);
                        SyncOutboxManagerV4.markApplied(context, item.id);
                    } else {
                        SyncOutboxManagerV4.markFailed(context, item.id,
                            r.optString("message", "server_failure"));
                    }
                }
            } else {
                // No detailed results — treat whole batch as applied if HTTP 2xx.
                for (SyncDatabaseHelperV4.OutboxItem item : batch) {
                    SyncOutboxManagerV4.markApplied(context, item.id);
                }
            }

            int stillPending = db.getPendingCount();
            if (stillPending > 0) {
                Log.i(TAG, "outbox still has " + stillPending + " (will continue next cycle)");
            }
            return null;
        } catch (Exception e) {
            Log.e(TAG, "drain failed — leaving outbox pending", e);
            // Network/parse failure: DO NOT delete. Idempotency makes retry safe.
            return "drain_error: " + e.getMessage();
        }
    }

    // ---------------------------------------------------------------
    // Phase 3 — incremental pull
    // ---------------------------------------------------------------

    static String pullIncremental(Context context, String baseUrl, String token, String deviceId) {
        SyncDatabaseHelperV4 db = SyncDatabaseHelperV4.getInstance(context);
        try {
            String since = db.getState(STATE_LAST_PULL);
            String url = baseUrl + "/api/mobile/v4/sync/pull";
            if (since != null && !since.isEmpty()) {
                url += "?since=" + java.net.URLEncoder.encode(since, "UTF-8");
            }

            HttpResponse resp = httpGet(url, token, deviceId);
            if (resp.code == 401) return "unauthorized";
            if (resp.code < 200 || resp.code >= 300) {
                return "pull_http_" + resp.code;
            }

            JSONObject json = new JSONObject(resp.body);
            JSONObject data = json.optJSONObject("data");
            if (data != null) {
                String[] tables = {"data", "re_people", "dead_people",
                    "additional_deceased", "guardian_bank_accounts", "sponsorships"};
                for (String table : tables) {
                    JSONArray rows = data.optJSONArray(table);
                    if (rows == null) continue;
                    for (int i = 0; i < rows.length(); i++) {
                        JSONObject row = rows.getJSONObject(i);
                        String clientUuid = row.optString("client_uuid", null);
                        if (clientUuid == null || clientUuid.isEmpty()) {
                            clientUuid = SyncDatabaseHelperV4.newClientUuid();
                        }
                        boolean needsReview = row.optBoolean("needs_review", false);
                        Integer serverId = row.has("id") && !row.isNull("id")
                            ? row.getInt("id") : null;
                        db.upsertRecord(clientUuid, table, serverId, row.toString(), needsReview);
                    }
                    if (rows.length() > 0) {
                        Log.d(TAG, "pulled " + rows.length() + " rows → " + table);
                    }
                }
            }

            String serverTime = json.optString("server_time", null);
            if (serverTime != null && !serverTime.isEmpty()) {
                db.setState(STATE_LAST_PULL, serverTime);
            }
            return null;
        } catch (Exception e) {
            Log.e(TAG, "pull failed", e);
            return "pull_error: " + e.getMessage();
        }
    }

    // ---------------------------------------------------------------
    // Phase 4 — admin offline data (Doc/03 §2)
    // ---------------------------------------------------------------

    /**
     * Pulls dashboard snapshot, reports source, permissions manifest, and
     * file index into admin_offline_v4.db. Incremental via since= cursor.
     * Never throws — caller wraps in try/catch so core sync stays green.
     */
    static void pullAdminOffline(Context context, String baseUrl, String token, String deviceId)
            throws Exception {
        String since = com.aso.app.v4.admin_offline.AdminOfflineDataStoreV4
            .getLastAdminPull(context);

        // 4a) Dashboard snapshot
        try {
            HttpResponse dash = httpGet(baseUrl + "/api/mobile/v4/admin/dashboard-snapshot",
                token, deviceId);
            if (dash.code == 200) {
                JSONObject dj = new JSONObject(dash.body);
                if (dj.optBoolean("success", false)) {
                    JSONObject snapshot = dj.optJSONObject("snapshot");
                    if (snapshot != null) {
                        String key = "dashboard_" + System.currentTimeMillis();
                        com.aso.app.v4.admin_offline.AdminOfflineDataStoreV4
                            .saveDashboardSnapshot(context, key, snapshot,
                                System.currentTimeMillis());
                        Log.d(TAG, "admin dashboard snapshot saved");
                    }
                }
            }
        } catch (Exception e) {
            Log.w(TAG, "dashboard pull failed: " + e.getMessage());
        }

        // 4b) Reports source (incremental)
        try {
            String url = baseUrl + "/api/mobile/v4/admin/reports-source";
            if (since != null && !since.isEmpty()) {
                url += "?since=" + java.net.URLEncoder.encode(since, "UTF-8");
            }
            HttpResponse rep = httpGet(url, token, deviceId);
            if (rep.code == 200) {
                JSONObject rj = new JSONObject(rep.body);
                if (rj.optBoolean("success", false)) {
                    JSONObject reports = rj.optJSONObject("reports");
                    if (reports != null) {
                        com.aso.app.v4.admin_offline.AdminOfflineDataStoreV4
                            .ingestReports(context, reports);
                    }
                }
            }
        } catch (Exception e) {
            Log.w(TAG, "reports pull failed: " + e.getMessage());
        }

        // 4c) Permissions manifest (always fresh — short-lived HMAC)
        try {
            HttpResponse perm = httpGet(baseUrl + "/api/mobile/v4/admin/permissions-manifest",
                token, deviceId);
            if (perm.code == 200) {
                JSONObject pj = new JSONObject(perm.body);
                if (pj.optBoolean("success", false)) {
                    JSONObject manifest = pj.optJSONObject("manifest");
                    String signature = pj.optString("signature", "");
                    if (manifest != null) {
                        int userId = manifest.optInt("user_id", -1);
                        if (userId > 0) {
                            com.aso.app.v4.admin_offline.PermissionsCacheManagerV4
                                .saveManifest(context, userId, manifest, signature);
                            Log.d(TAG, "permissions manifest cached for user " + userId);
                        }
                    }
                }
            }
        } catch (Exception e) {
            Log.w(TAG, "permissions pull failed: " + e.getMessage());
        }

        // 4d) Advance admin cursor
        String serverTime = java.text.DateFormat
            .getDateTimeInstance().format(new java.util.Date());
        com.aso.app.v4.admin_offline.AdminOfflineDataStoreV4
            .setLastAdminPull(context, serverTime);
        Log.i(TAG, "admin offline phase complete");
    }

    // ---------------------------------------------------------------
    // HTTP helpers
    // ---------------------------------------------------------------

    static String normalizeBaseUrl(String baseUrl) {
        if (baseUrl == null || baseUrl.isEmpty()) {
            baseUrl = ApiConfig.BASE_URL;
        }
        if (baseUrl.endsWith("/")) {
            baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
        }
        return baseUrl;
    }

    private static String getAuthToken(Context context) {
        SharedPreferences prefs = context.getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
        return prefs.getString("api_token", "");
    }

    static HttpResponse httpPost(String urlStr, String jsonBody, String token,
                                 String deviceId, String idempotencyKey) throws Exception {
        HttpURLConnection conn = null;
        try {
            conn = (HttpURLConnection) new URL(urlStr).openConnection();
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(HTTP_TIMEOUT_MS);
            conn.setReadTimeout(HTTP_TIMEOUT_MS);
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("X-Device-Id", deviceId);
            conn.setRequestProperty("X-Sync-Source", "app_v4");
            if (idempotencyKey != null) {
                conn.setRequestProperty("X-Idempotency-Key", idempotencyKey);
            }
            byte[] data = jsonBody.getBytes(StandardCharsets.UTF_8);
            conn.setFixedLengthStreamingMode(data.length);
            OutputStream os = conn.getOutputStream();
            try {
                os.write(data);
                os.flush();
            } finally {
                os.close();
            }
            return readResponse(conn);
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    static HttpResponse httpGet(String urlStr, String token, String deviceId) throws Exception {
        HttpURLConnection conn = null;
        try {
            conn = (HttpURLConnection) new URL(urlStr).openConnection();
            conn.setRequestMethod("GET");
            conn.setConnectTimeout(HTTP_TIMEOUT_MS);
            conn.setReadTimeout(HTTP_TIMEOUT_MS);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("X-Device-Id", deviceId);
            conn.setRequestProperty("X-Sync-Source", "app_v4");
            return readResponse(conn);
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    private static HttpResponse readResponse(HttpURLConnection conn) throws Exception {
        int code = conn.getResponseCode();
        java.io.InputStream is = (code >= 200 && code < 400)
            ? conn.getInputStream() : conn.getErrorStream();
        StringBuilder sb = new StringBuilder();
        if (is != null) {
            BufferedReader reader = new BufferedReader(new InputStreamReader(is, StandardCharsets.UTF_8));
            try {
                String line;
                while ((line = reader.readLine()) != null) sb.append(line);
            } finally {
                reader.close();
            }
        }
        HttpResponse r = new HttpResponse();
        r.code = code;
        r.body = sb.toString();
        return r;
    }

    private static String safeSnippet(String s) {
        if (s == null) return "";
        return s.length() > 120 ? s.substring(0, 120) : s;
    }

    static class HttpResponse {
        int code;
        String body;
    }
}
