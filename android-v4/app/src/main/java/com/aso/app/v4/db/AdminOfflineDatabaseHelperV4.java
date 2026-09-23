package com.aso.app.v4.db;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import org.json.JSONArray;
import org.json.JSONObject;

/**
 * admin_offline_v4.db — offline data store for admin panel parity (Doc/03 §2).
 *
 * Separate from sync_v4.db because this is aggregated READ-ONLY admin data,
 * not operational sync state. Tables:
 *  - admin_dashboard_snapshot_v4
 *  - admin_reports_source_v4
 *  - permissions_manifest_v4
 *  - file_index_v4
 */
public class AdminOfflineDatabaseHelperV4 extends SQLiteOpenHelper {
    private static final String TAG = "AdminOfflineDB_V4";
    private static final String DATABASE_NAME = "admin_offline_v4.db";
    private static final int DATABASE_VERSION = 2;

    private static AdminOfflineDatabaseHelperV4 instance;

    public static synchronized AdminOfflineDatabaseHelperV4 getInstance(Context context) {
        if (instance == null) {
            instance = new AdminOfflineDatabaseHelperV4(context.getApplicationContext());
        }
        return instance;
    }

    private AdminOfflineDatabaseHelperV4(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        db.execSQL(
            "CREATE TABLE admin_dashboard_snapshot_v4 ("
                + "id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "snapshot_key TEXT NOT NULL UNIQUE, "
                + "snapshot_value_json TEXT NOT NULL, "
                + "generated_at INTEGER NOT NULL, "
                + "fetched_at INTEGER NOT NULL"
                + ")"
        );

        db.execSQL(
            "CREATE TABLE admin_reports_source_v4 ("
                + "id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "report_type TEXT NOT NULL, "
                + "row_key TEXT NOT NULL, "
                + "row_data_json TEXT NOT NULL, "
                + "last_updated_at INTEGER NOT NULL, "
                + "fetched_at INTEGER NOT NULL, "
                + "UNIQUE(report_type, row_key)"
                + ")"
        );
        db.execSQL("CREATE INDEX idx_reports_type ON admin_reports_source_v4(report_type)");

        db.execSQL(
            "CREATE TABLE permissions_manifest_v4 ("
                + "id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "user_id INTEGER NOT NULL, "
                + "manifest_json TEXT NOT NULL, "
                + "signature TEXT NOT NULL, "
                + "issued_at INTEGER NOT NULL, "
                + "expires_at INTEGER NOT NULL, "
                + "fetched_at INTEGER NOT NULL"
                + ")"
        );

        db.execSQL(
            "CREATE TABLE file_index_v4 ("
                + "id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "client_uuid TEXT NOT NULL UNIQUE, "
                + "file_id TEXT, "
                + "table_name TEXT, "
                + "person_type TEXT, "
                + "identity_number TEXT, "
                + "person_name TEXT, "
                + "stored_file_name TEXT, "
                + "file_path TEXT, "
                + "file_type TEXT, "
                + "source TEXT NOT NULL, "
                + "source_id INTEGER, "
                + "status TEXT NOT NULL DEFAULT 'active', "
                + "cache_priority TEXT NOT NULL DEFAULT 'on_demand', "
                + "local_cache_path TEXT, "
                + "cached_at INTEGER, "
                + "last_synced_at INTEGER, "
                + "fetched_at INTEGER NOT NULL"
                + ")"
        );
        db.execSQL("CREATE INDEX idx_file_index_identity ON file_index_v4(identity_number)");
        db.execSQL("CREATE INDEX idx_file_index_table ON file_index_v4(table_name, status)");

        db.execSQL(
            "CREATE TABLE admin_sync_state ("
                + "key TEXT PRIMARY KEY, "
                + "value TEXT"
                + ")"
        );

        Log.i(TAG, "admin_offline_v4.db created");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        // v1→v2: Doc/11 profile-photo always-local columns (add only, never drop).
        if (oldVersion < 2) {
            try {
                db.execSQL("ALTER TABLE file_index_v4 ADD COLUMN cache_priority TEXT NOT NULL DEFAULT 'on_demand'");
            } catch (Exception ignored) {}
            try {
                db.execSQL("ALTER TABLE file_index_v4 ADD COLUMN local_cache_path TEXT");
            } catch (Exception ignored) {}
            try {
                db.execSQL("ALTER TABLE file_index_v4 ADD COLUMN cached_at INTEGER");
            } catch (Exception ignored) {}
            Log.i(TAG, "upgraded to v2 (cache_priority columns)");
        }
        // Never drop v3 databases.
    }

    // ---------------------------------------------------------------
    // Dashboard snapshot
    // ---------------------------------------------------------------

    public void saveDashboardSnapshot(String snapshotKey, String snapshotJson, long generatedAt) {
        SQLiteDatabase db = getWritableDatabase();
        ContentValues cv = new ContentValues();
        cv.put("snapshot_key", snapshotKey);
        cv.put("snapshot_value_json", snapshotJson);
        cv.put("generated_at", generatedAt);
        cv.put("fetched_at", System.currentTimeMillis());
        db.insertWithOnConflict("admin_dashboard_snapshot_v4", null, cv,
            SQLiteDatabase.CONFLICT_REPLACE);
    }

    public JSONObject getLatestDashboardSnapshot() {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery(
            "SELECT snapshot_value_json, fetched_at FROM admin_dashboard_snapshot_v4 "
                + "ORDER BY fetched_at DESC LIMIT 1", null);
        try {
            if (c.moveToFirst()) {
                JSONObject out = new JSONObject(c.getString(0));
                out.put("fetched_at", c.getLong(1));
                return out;
            }
        } catch (Exception e) {
            Log.e(TAG, "dashboard parse failed", e);
        } finally {
            c.close();
        }
        return null;
    }

    // ---------------------------------------------------------------
    // Reports source
    // ---------------------------------------------------------------

    public void saveReportRow(String reportType, String rowKey, String rowJson, long lastUpdatedAt) {
        SQLiteDatabase db = getWritableDatabase();
        ContentValues cv = new ContentValues();
        cv.put("report_type", reportType);
        cv.put("row_key", rowKey);
        cv.put("row_data_json", rowJson);
        cv.put("last_updated_at", lastUpdatedAt);
        cv.put("fetched_at", System.currentTimeMillis());
        db.insertWithOnConflict("admin_reports_source_v4", null, cv, SQLiteDatabase.CONFLICT_REPLACE);
    }

    public JSONArray queryReportRows(String reportType, String search) {
        SQLiteDatabase db = getReadableDatabase();
        JSONArray out = new JSONArray();
        String sql = "SELECT row_data_json FROM admin_reports_source_v4 WHERE report_type = ?";
        String[] args;
        if (search != null && !search.isEmpty()) {
            sql += " AND row_data_json LIKE ?";
            args = new String[]{reportType, "%" + search + "%"};
        } else {
            args = new String[]{reportType};
        }
        sql += " ORDER BY last_updated_at DESC LIMIT 2000";

        Cursor c = db.rawQuery(sql, args);
        try {
            while (c.moveToNext()) {
                try {
                    out.put(new JSONObject(c.getString(0)));
                } catch (Exception ignored) {}
            }
        } finally {
            c.close();
        }
        return out;
    }

    public int countReportRows(String reportType) {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery(
            "SELECT COUNT(*) FROM admin_reports_source_v4 WHERE report_type=?",
            new String[]{reportType});
        try {
            return c.moveToFirst() ? c.getInt(0) : 0;
        } finally {
            c.close();
        }
    }

    public long getReportsFetchedAt(String reportType) {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery(
            "SELECT MAX(fetched_at) FROM admin_reports_source_v4 WHERE report_type=?",
            new String[]{reportType});
        try {
            return c.moveToFirst() && !c.isNull(0) ? c.getLong(0) : 0L;
        } finally {
            c.close();
        }
    }

    // ---------------------------------------------------------------
    // Permissions manifest
    // ---------------------------------------------------------------

    public void savePermissionsManifest(int userId, String manifestJson, String signature,
                                        long issuedAt, long expiresAt) {
        SQLiteDatabase db = getWritableDatabase();
        db.delete("permissions_manifest_v4", "user_id=?", new String[]{String.valueOf(userId)});
        ContentValues cv = new ContentValues();
        cv.put("user_id", userId);
        cv.put("manifest_json", manifestJson);
        cv.put("signature", signature);
        cv.put("issued_at", issuedAt);
        cv.put("expires_at", expiresAt);
        cv.put("fetched_at", System.currentTimeMillis());
        db.insert("permissions_manifest_v4", null, cv);
    }

    public JSONObject getPermissionsManifest(int userId) {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery(
            "SELECT manifest_json, signature, issued_at, expires_at, fetched_at "
                + "FROM permissions_manifest_v4 WHERE user_id=? LIMIT 1",
            new String[]{String.valueOf(userId)});
        try {
            if (c.moveToFirst()) {
                JSONObject out = new JSONObject(c.getString(0));
                out.put("signature", c.getString(1));
                out.put("issued_at_ms", c.getLong(2));
                out.put("expires_at_ms", c.getLong(3));
                out.put("fetched_at_ms", c.getLong(4));
                return out;
            }
        } catch (Exception e) {
            Log.e(TAG, "manifest parse failed", e);
        } finally {
            c.close();
        }
        return null;
    }

    public boolean isManifestExpired(int userId) {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery(
            "SELECT expires_at FROM permissions_manifest_v4 WHERE user_id=? LIMIT 1",
            new String[]{String.valueOf(userId)});
        try {
            if (c.moveToFirst()) {
                return c.getLong(0) < System.currentTimeMillis();
            }
            return true; // no manifest = expired/absent
        } finally {
            c.close();
        }
    }

    // ---------------------------------------------------------------
    // File index
    // ---------------------------------------------------------------

    public void upsertFileIndex(String clientUuid, String fileId, String tableName, String personType,
                                String identityNumber, String personName, String storedFileName,
                                String filePath, String fileType, String source, Integer sourceId,
                                String status, Long lastSyncedAt) {
        upsertFileIndex(clientUuid, fileId, tableName, personType, identityNumber, personName,
            storedFileName, filePath, fileType, source, sourceId, status, lastSyncedAt,
            "on_demand", null, null);
    }

    public void upsertFileIndex(String clientUuid, String fileId, String tableName, String personType,
                                String identityNumber, String personName, String storedFileName,
                                String filePath, String fileType, String source, Integer sourceId,
                                String status, Long lastSyncedAt, String cachePriority,
                                String localCachePath, Long cachedAt) {
        SQLiteDatabase db = getWritableDatabase();
        ContentValues cv = new ContentValues();
        cv.put("client_uuid", clientUuid);
        if (fileId != null) cv.put("file_id", fileId);
        if (tableName != null) cv.put("table_name", tableName);
        if (personType != null) cv.put("person_type", personType);
        if (identityNumber != null) cv.put("identity_number", identityNumber);
        if (personName != null) cv.put("person_name", personName);
        if (storedFileName != null) cv.put("stored_file_name", storedFileName);
        if (filePath != null) cv.put("file_path", filePath);
        if (fileType != null) cv.put("file_type", fileType);
        cv.put("source", source);
        if (sourceId != null) cv.put("source_id", sourceId);
        cv.put("status", status != null ? status : "active");
        cv.put("cache_priority", cachePriority != null ? cachePriority : "on_demand");
        if (localCachePath != null) cv.put("local_cache_path", localCachePath);
        if (cachedAt != null) cv.put("cached_at", cachedAt);
        if (lastSyncedAt != null) cv.put("last_synced_at", lastSyncedAt);
        cv.put("fetched_at", System.currentTimeMillis());
        db.insertWithOnConflict("file_index_v4", null, cv, SQLiteDatabase.CONFLICT_REPLACE);
    }

    public JSONArray queryFileIndex(String search) {
        SQLiteDatabase db = getReadableDatabase();
        JSONArray out = new JSONArray();
        String sql = "SELECT client_uuid, file_id, table_name, person_type, identity_number, "
            + "person_name, stored_file_name, file_path, file_type, status, last_synced_at, "
            + "cache_priority, local_cache_path, cached_at "
            + "FROM file_index_v4 WHERE status='active'";
        String[] args = new String[0];
        if (search != null && !search.isEmpty()) {
            sql += " AND (identity_number LIKE ? OR person_name LIKE ? OR stored_file_name LIKE ?)";
            String s = "%" + search + "%";
            args = new String[]{s, s, s};
        }
        sql += " ORDER BY last_synced_at DESC LIMIT 500";

        Cursor c = db.rawQuery(sql, args);
        try {
            while (c.moveToNext()) {
                try {
                    JSONObject row = new JSONObject();
                    row.put("client_uuid", c.getString(0));
                    row.put("file_id", c.isNull(1) ? JSONObject.NULL : c.getString(1));
                    row.put("table_name", c.isNull(2) ? JSONObject.NULL : c.getString(2));
                    row.put("person_type", c.isNull(3) ? JSONObject.NULL : c.getString(3));
                    row.put("identity_number", c.isNull(4) ? JSONObject.NULL : c.getString(4));
                    row.put("person_name", c.isNull(5) ? JSONObject.NULL : c.getString(5));
                    row.put("stored_file_name", c.isNull(6) ? JSONObject.NULL : c.getString(6));
                    row.put("file_path", c.isNull(7) ? JSONObject.NULL : c.getString(7));
                    row.put("file_type", c.isNull(8) ? JSONObject.NULL : c.getString(8));
                    row.put("status", c.getString(9));
                    row.put("last_synced_at", c.isNull(10) ? JSONObject.NULL : c.getLong(10));
                    row.put("cache_priority", c.isNull(11) ? "on_demand" : c.getString(11));
                    row.put("local_cache_path", c.isNull(12) ? JSONObject.NULL : c.getString(12));
                    row.put("cached_at", c.isNull(13) ? JSONObject.NULL : c.getLong(13));
                    out.put(row);
                } catch (Exception ignored) {}
            }
        } finally {
            c.close();
        }
        return out;
    }

    public int countFileIndex() {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery(
            "SELECT COUNT(*) FROM file_index_v4 WHERE status='active'", null);
        try {
            return c.moveToFirst() ? c.getInt(0) : 0;
        } finally {
            c.close();
        }
    }

    // ---------------------------------------------------------------
    // Admin sync state (last admin pull cursor)
    // ---------------------------------------------------------------

    public String getState(String key) {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery("SELECT value FROM admin_sync_state WHERE key=?",
            new String[]{key});
        try {
            return c.moveToFirst() ? c.getString(0) : null;
        } finally {
            c.close();
        }
    }

    public void setState(String key, String value) {
        SQLiteDatabase db = getWritableDatabase();
        ContentValues cv = new ContentValues();
        cv.put("key", key);
        cv.put("value", value);
        db.insertWithOnConflict("admin_sync_state", null, cv, SQLiteDatabase.CONFLICT_REPLACE);
    }
}
