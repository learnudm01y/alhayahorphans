package com.aso.app.v4.db;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

/**
 * Unified local database for android-v4 sync engine.
 * Replaces the four v3 databases (sponsorships_data, related_data, data_sync)
 * for all NEW v4 writes. v3 databases remain untouched (read-only after cutover).
 *
 * Tables:
 *  - outbox_local: pending operations (created offline, awaiting server)
 *  - records_v4:   local mirror of server records keyed by client_uuid
 *  - sync_state:   last_pull timestamps per entity type
 */
public class SyncDatabaseHelperV4 extends SQLiteOpenHelper {
    private static final String TAG = "SyncDB_V4";
    private static final String DATABASE_NAME = "sync_v4.db";
    private static final int DATABASE_VERSION = 1;

    private static SyncDatabaseHelperV4 instance;

    public static synchronized SyncDatabaseHelperV4 getInstance(Context context) {
        if (instance == null) {
            instance = new SyncDatabaseHelperV4(context.getApplicationContext());
        }
        return instance;
    }

    private SyncDatabaseHelperV4(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        db.execSQL(
            "CREATE TABLE outbox_local ("
                + "id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "client_uuid TEXT NOT NULL UNIQUE, "
                + "idempotency_key TEXT NOT NULL UNIQUE, "
                + "entity_type TEXT NOT NULL, "
                + "operation TEXT NOT NULL DEFAULT 'upsert', "
                + "payload_json TEXT NOT NULL, "
                + "status TEXT NOT NULL DEFAULT 'pending', "
                + "attempts INTEGER NOT NULL DEFAULT 0, "
                + "last_error TEXT, "
                + "created_at_device INTEGER NOT NULL, "
                + "updated_at_device INTEGER NOT NULL"
                + ")"
        );
        db.execSQL("CREATE INDEX idx_outbox_status ON outbox_local(status, created_at_device)");
        db.execSQL("CREATE INDEX idx_outbox_entity ON outbox_local(entity_type)");

        db.execSQL(
            "CREATE TABLE records_v4 ("
                + "client_uuid TEXT PRIMARY KEY, "
                + "entity_type TEXT NOT NULL, "
                + "server_id INTEGER, "
                + "payload_json TEXT NOT NULL, "
                + "sync_status TEXT NOT NULL DEFAULT 'synced', "
                + "needs_review INTEGER NOT NULL DEFAULT 0, "
                + "updated_at INTEGER NOT NULL, "
                + "server_updated_at TEXT"
                + ")"
        );
        db.execSQL("CREATE INDEX idx_records_entity ON records_v4(entity_type, updated_at)");

        db.execSQL(
            "CREATE TABLE sync_state ("
                + "key TEXT PRIMARY KEY, "
                + "value TEXT"
                + ")"
        );

        Log.i(TAG, "sync_v4.db created");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        // v4 is new — no legacy upgrade path. Never drop v3 databases.
    }

    // ---------------------------------------------------------------
    // Outbox
    // ---------------------------------------------------------------

    public long enqueue(String clientUuid, String idempotencyKey, String entityType,
                        String operation, String payloadJson) {
        SQLiteDatabase db = getWritableDatabase();
        long now = System.currentTimeMillis();
        ContentValues cv = new ContentValues();
        cv.put("client_uuid", clientUuid);
        cv.put("idempotency_key", idempotencyKey);
        cv.put("entity_type", entityType);
        cv.put("operation", operation);
        cv.put("payload_json", payloadJson);
        cv.put("status", "pending");
        cv.put("attempts", 0);
        cv.put("created_at_device", now);
        cv.put("updated_at_device", now);
        long id = db.insertWithOnConflict("outbox_local", null, cv, SQLiteDatabase.CONFLICT_REPLACE);
        Log.d(TAG, "enqueued " + entityType + "/" + clientUuid + " → outbox id=" + id);
        return id;
    }

    public List<OutboxItem> getPendingBatch(int limit) {
        SQLiteDatabase db = getReadableDatabase();
        List<OutboxItem> items = new ArrayList<>();
        Cursor c = db.rawQuery(
            "SELECT id, client_uuid, idempotency_key, entity_type, operation, payload_json, attempts "
                + "FROM outbox_local WHERE status = 'pending' "
                + "ORDER BY created_at_device ASC LIMIT ?",
            new String[]{String.valueOf(limit)}
        );
        try {
            while (c.moveToNext()) {
                OutboxItem item = new OutboxItem();
                item.id = c.getLong(0);
                item.clientUuid = c.getString(1);
                item.idempotencyKey = c.getString(2);
                item.entityType = c.getString(3);
                item.operation = c.getString(4);
                item.payloadJson = c.getString(5);
                item.attempts = c.getInt(6);
                items.add(item);
            }
        } finally {
            c.close();
        }
        return items;
    }

    public int getPendingCount() {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery("SELECT COUNT(*) FROM outbox_local WHERE status='pending'", null);
        try {
            return c.moveToFirst() ? c.getInt(0) : 0;
        } finally {
            c.close();
        }
    }

    public void markApplied(long id) {
        SQLiteDatabase db = getWritableDatabase();
        ContentValues cv = new ContentValues();
        cv.put("status", "applied");
        cv.put("updated_at_device", System.currentTimeMillis());
        db.update("outbox_local", cv, "id=?", new String[]{String.valueOf(id)});
        // Server has acknowledged — safe to purge the row.
        db.delete("outbox_local", "id=?", new String[]{String.valueOf(id)});
    }

    public void markFailed(long id, String error) {
        SQLiteDatabase db = getWritableDatabase();
        ContentValues cv = new ContentValues();
        cv.put("attempts", getAttempts(db, id) + 1);
        cv.put("last_error", error != null && error.length() > 200 ? error.substring(0, 200) : error);
        cv.put("updated_at_device", System.currentTimeMillis());
        db.update("outbox_local", cv, "id=?", new String[]{String.valueOf(id)});
    }

    private int getAttempts(SQLiteDatabase db, long id) {
        Cursor c = db.rawQuery("SELECT attempts FROM outbox_local WHERE id=?",
            new String[]{String.valueOf(id)});
        try {
            return c.moveToFirst() ? c.getInt(0) : 0;
        } finally {
            c.close();
        }
    }

    // ---------------------------------------------------------------
    // Local records mirror
    // ---------------------------------------------------------------

    public void upsertRecord(String clientUuid, String entityType, Integer serverId,
                             String payloadJson, boolean needsReview) {
        SQLiteDatabase db = getWritableDatabase();
        ContentValues cv = new ContentValues();
        cv.put("client_uuid", clientUuid);
        cv.put("entity_type", entityType);
        if (serverId != null) cv.put("server_id", serverId);
        cv.put("payload_json", payloadJson);
        cv.put("sync_status", needsReview ? "conflict" : "synced");
        cv.put("needs_review", needsReview ? 1 : 0);
        cv.put("updated_at", System.currentTimeMillis());
        db.insertWithOnConflict("records_v4", null, cv, SQLiteDatabase.CONFLICT_REPLACE);
    }

    public JSONArray queryRecords(String entityType, String search) {
        SQLiteDatabase db = getReadableDatabase();
        List<String> args = new ArrayList<>();
        String sql = "SELECT client_uuid, server_id, payload_json, needs_review, updated_at "
            + "FROM records_v4 WHERE entity_type = ?";
        args.add(entityType);
        if (search != null && !search.isEmpty()) {
            sql += " AND payload_json LIKE ?";
            args.add("%" + search + "%");
        }
        sql += " ORDER BY updated_at DESC LIMIT 500";

        JSONArray out = new JSONArray();
        Cursor c = db.rawQuery(sql, args.toArray(new String[0]));
        try {
            while (c.moveToNext()) {
                try {
                    JSONObject row = new JSONObject();
                    row.put("client_uuid", c.getString(0));
                    row.put("server_id", c.isNull(1) ? JSONObject.NULL : c.getInt(1));
                    row.put("payload", new JSONObject(c.getString(2)));
                    row.put("needs_review", c.getInt(3) == 1);
                    row.put("updated_at", c.getLong(4));
                    out.put(row);
                } catch (Exception ignored) {}
            }
        } finally {
            c.close();
        }
        return out;
    }

    // ---------------------------------------------------------------
    // Sync state (last pull cursor)
    // ---------------------------------------------------------------

    public String getState(String key) {
        SQLiteDatabase db = getReadableDatabase();
        Cursor c = db.rawQuery("SELECT value FROM sync_state WHERE key=?", new String[]{key});
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
        db.insertWithOnConflict("sync_state", null, cv, SQLiteDatabase.CONFLICT_REPLACE);
    }

    // ---------------------------------------------------------------
    // Outbox item DTO
    // ---------------------------------------------------------------

    public static class OutboxItem {
        public long id;
        public String clientUuid;
        public String idempotencyKey;
        public String entityType;
        public String operation;
        public String payloadJson;
        public int attempts;
    }

    public static String newClientUuid() {
        return UUID.randomUUID().toString();
    }
}
