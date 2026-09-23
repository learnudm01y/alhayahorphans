package com.aso.app.v4.admin_offline;

import android.content.Context;
import android.util.Log;

import com.aso.app.v4.db.AdminOfflineDatabaseHelperV4;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.Iterator;

/**
 * Facade over admin_offline_v4.db for all admin screens.
 * Screens never touch the SQLite helper directly — all reads/writes go through here.
 */
public final class AdminOfflineDataStoreV4 {
    private static final String TAG = "AdminOfflineStoreV4";
    public static final String STATE_LAST_ADMIN_PULL = "last_admin_pull_at";

    private AdminOfflineDataStoreV4() {}

    private static AdminOfflineDatabaseHelperV4 db(Context context) {
        return AdminOfflineDatabaseHelperV4.getInstance(context);
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public static void saveDashboardSnapshot(Context context, String key, JSONObject snapshot,
                                             long generatedAt) {
        db(context).saveDashboardSnapshot(key, snapshot.toString(), generatedAt);
        Log.d(TAG, "dashboard snapshot saved: " + key);
    }

    public static JSONObject getDashboardSnapshot(Context context) {
        return db(context).getLatestDashboardSnapshot();
    }

    // ---------------------------------------------------------------
    // Reports source
    // ---------------------------------------------------------------

    /**
     * Ingest server reports payload:
     * { sponsorships: [...], files: [...], bank_accounts: [...] }
     */
    public static void ingestReports(Context context, JSONObject reports) {
        if (reports == null) return;
        long now = System.currentTimeMillis();
        Iterator<String> keys = reports.keys();
        while (keys.hasNext()) {
            String type = keys.next();
            JSONArray rows = reports.optJSONArray(type);
            if (rows == null) continue;
            for (int i = 0; i < rows.length(); i++) {
                try {
                    JSONObject row = rows.getJSONObject(i);
                    String rowKey = row.optString("client_uuid", null);
                    if (rowKey == null || rowKey.isEmpty()) {
                        rowKey = "id-" + row.optLong("id", (long) i);
                    }
                    long updatedAt = now;
                    String ua = row.optString("updated_at", null);
                    if (ua != null && !ua.isEmpty()) {
                        try {
                            updatedAt = java.text.DateFormat
                                .getDateTimeInstance().parse(ua).getTime();
                        } catch (Exception ignored) {}
                    }
                    db(context).saveReportRow(type, rowKey, row.toString(), updatedAt);
                } catch (Exception e) {
                    Log.w(TAG, "skip bad report row: " + e.getMessage());
                }
            }
            Log.d(TAG, "ingested " + rows.length() + " report rows → " + type);
        }
    }

    public static JSONArray queryReportRows(Context context, String reportType, String search) {
        return db(context).queryReportRows(reportType, search);
    }

    public static int countReportRows(Context context, String reportType) {
        return db(context).countReportRows(reportType);
    }

    public static long getReportsFetchedAt(Context context, String reportType) {
        return db(context).getReportsFetchedAt(reportType);
    }

    // ---------------------------------------------------------------
    // File index
    // ---------------------------------------------------------------

    public static void upsertFileIndexEntry(Context context, JSONObject entry) {
        try {
            String clientUuid = entry.optString("client_uuid", null);
            if (clientUuid == null || clientUuid.isEmpty()) {
                clientUuid = com.aso.app.v4.db.SyncDatabaseHelperV4.newClientUuid();
            }
            Integer sourceId = entry.has("source_id") && !entry.isNull("source_id")
                ? entry.getInt("source_id") : null;
            Long lastSynced = entry.has("last_synced_at") && !entry.isNull("last_synced_at")
                ? entry.getLong("last_synced_at") : System.currentTimeMillis();
            String cachePriority = entry.optString("cache_priority", "on_demand");
            String localCachePath = entry.has("local_cache_path") && !entry.isNull("local_cache_path")
                ? entry.optString("local_cache_path", null) : null;
            Long cachedAt = entry.has("cached_at") && !entry.isNull("cached_at")
                ? entry.getLong("cached_at") : null;
            db(context).upsertFileIndex(
                clientUuid,
                optNullableString(entry, "file_id"),
                optNullableString(entry, "table_name"),
                optNullableString(entry, "person_type"),
                optNullableString(entry, "identity_number"),
                optNullableString(entry, "person_name"),
                optNullableString(entry, "stored_file_name"),
                optNullableString(entry, "file_path"),
                optNullableString(entry, "file_type"),
                entry.optString("source", "file_id_registry"),
                sourceId,
                entry.optString("status", "active"),
                lastSynced,
                cachePriority,
                localCachePath,
                cachedAt
            );
        } catch (Exception e) {
            Log.w(TAG, "file index upsert failed: " + e.getMessage());
        }
    }

    public static JSONArray queryFileIndex(Context context, String search) {
        return db(context).queryFileIndex(search);
    }

    public static int countFileIndex(Context context) {
        return db(context).countFileIndex();
    }

    // ---------------------------------------------------------------
    // Sync state
    // ---------------------------------------------------------------

    public static String getLastAdminPull(Context context) {
        return db(context).getState(STATE_LAST_ADMIN_PULL);
    }

    public static void setLastAdminPull(Context context, String serverTime) {
        db(context).setState(STATE_LAST_ADMIN_PULL, serverTime);
    }

    private static String optNullableString(JSONObject o, String key) {
        return o.has(key) && !o.isNull(key) ? o.optString(key, null) : null;
    }
}
