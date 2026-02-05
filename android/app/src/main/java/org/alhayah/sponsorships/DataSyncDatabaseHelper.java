package org.alhayah.sponsorships;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;

/**
 * قاعدة بيانات SQLite لحفظ حالة البيانات المنتظرة للمزامنة
 * معزولة تماماً عن قاعدة بيانات الملفات (UploadDatabaseHelper)
 *
 * يحفظ: localStorage data, IndexedDB records, JSON objects
 */
public class DataSyncDatabaseHelper extends SQLiteOpenHelper {
    private static final String TAG = "DataSyncDatabaseHelper";

    // Singleton instance
    private static DataSyncDatabaseHelper instance;

    // Database info
    private static final String DATABASE_NAME = "data_sync.db";
    private static final int DATABASE_VERSION = 1;

    // Table name
    private static final String TABLE_SYNC_QUEUE = "sync_queue";

    // Columns
    private static final String COL_ID = "id";
    private static final String COL_DATA_TYPE = "data_type"; // e.g., "sponsorship", "payment", "orphan"
    private static final String COL_DATA_JSON = "data_json"; // JSON string
    private static final String COL_ENDPOINT = "endpoint"; // API endpoint to sync to
    private static final String COL_STATUS = "status"; // pending, uploading, uploaded, failed
    private static final String COL_RETRY_COUNT = "retry_count"; // Number of retry attempts
    private static final String COL_ERROR_MESSAGE = "error_message"; // Last error message
    private static final String COL_CREATED_AT = "created_at"; // Timestamp
    private static final String COL_UPLOADED_AT = "uploaded_at"; // Timestamp

    // Status values
    public static final String STATUS_PENDING = "pending";
    public static final String STATUS_UPLOADING = "uploading";
    public static final String STATUS_UPLOADED = "uploaded";
    public static final String STATUS_FAILED = "failed";

    // Max retry attempts
    public static final int MAX_RETRY_ATTEMPTS = 3;

    private DataSyncDatabaseHelper(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
        Log.d(TAG, "DataSyncDatabaseHelper created - معزول تماماً عن نظام الملفات");
    }

    /**
     * Singleton pattern - نفس نمط UploadDatabaseHelper لكن منفصل
     */
    public static synchronized DataSyncDatabaseHelper getInstance(Context context) {
        if (instance == null) {
            instance = new DataSyncDatabaseHelper(context.getApplicationContext());
        }
        return instance;
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        String createTable = "CREATE TABLE " + TABLE_SYNC_QUEUE + " ("
                + COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT, "
                + COL_DATA_TYPE + " TEXT NOT NULL, "
                + COL_DATA_JSON + " TEXT NOT NULL, "
                + COL_ENDPOINT + " TEXT NOT NULL, "
                + COL_STATUS + " TEXT DEFAULT '" + STATUS_PENDING + "', "
                + COL_RETRY_COUNT + " INTEGER DEFAULT 0, "
                + COL_ERROR_MESSAGE + " TEXT, "
                + COL_CREATED_AT + " INTEGER NOT NULL, "
                + COL_UPLOADED_AT + " INTEGER"
                + ")";

        db.execSQL(createTable);
        Log.d(TAG, "✅ Database created: sync_queue table ready");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        db.execSQL("DROP TABLE IF EXISTS " + TABLE_SYNC_QUEUE);
        onCreate(db);
        Log.d(TAG, "⚠️ Database upgraded from v" + oldVersion + " to v" + newVersion);
    }

    /**
     * إضافة بيانات جديدة للقائمة (من JavaScript)
     */
    public long addDataToQueue(String dataType, String dataJson, String endpoint) {
        SQLiteDatabase db = getWritableDatabase();

        ContentValues values = new ContentValues();
        values.put(COL_DATA_TYPE, dataType);
        values.put(COL_DATA_JSON, dataJson);
        values.put(COL_ENDPOINT, endpoint);
        values.put(COL_STATUS, STATUS_PENDING);
        values.put(COL_RETRY_COUNT, 0);
        values.put(COL_CREATED_AT, System.currentTimeMillis());

        long id = db.insert(TABLE_SYNC_QUEUE, null, values);

        if (id > 0) {
            Log.d(TAG, "✅ Data added to queue: ID=" + id + ", Type=" + dataType + ", Endpoint=" + endpoint);
        } else {
            Log.e(TAG, "❌ Failed to add data to queue");
        }

        return id;
    }

    /**
     * الحصول على جميع البيانات المنتظرة (pending)
     */
    public List<DataSyncItem> getPendingData() {
        List<DataSyncItem> items = new ArrayList<>();
        SQLiteDatabase db = getReadableDatabase();

        Cursor cursor = db.query(
            TABLE_SYNC_QUEUE,
            null,
            COL_STATUS + " = ? AND " + COL_RETRY_COUNT + " < ?",
            new String[]{STATUS_PENDING, String.valueOf(MAX_RETRY_ATTEMPTS)},
            null, null,
            COL_CREATED_AT + " ASC" // الأقدم أولاً
        );

        while (cursor.moveToNext()) {
            items.add(cursorToItem(cursor));
        }

        cursor.close();
        Log.d(TAG, "📋 Found " + items.size() + " pending data items");
        return items;
    }

    /**
     * تحديث حالة البيانات إلى "uploading"
     */
    public void markAsUploading(long id) {
        SQLiteDatabase db = getWritableDatabase();

        ContentValues values = new ContentValues();
        values.put(COL_STATUS, STATUS_UPLOADING);

        int rows = db.update(TABLE_SYNC_QUEUE, values, COL_ID + " = ?", new String[]{String.valueOf(id)});
        Log.d(TAG, "🔄 Marked as uploading: ID=" + id + " (rows=" + rows + ")");
    }

    /**
     * تحديث حالة البيانات إلى "uploaded" (نجحت المزامنة)
     */
    public void markAsUploaded(long id) {
        SQLiteDatabase db = getWritableDatabase();

        ContentValues values = new ContentValues();
        values.put(COL_STATUS, STATUS_UPLOADED);
        values.put(COL_UPLOADED_AT, System.currentTimeMillis());

        int rows = db.update(TABLE_SYNC_QUEUE, values, COL_ID + " = ?", new String[]{String.valueOf(id)});
        Log.d(TAG, "✅ Marked as uploaded: ID=" + id + " (rows=" + rows + ")");
    }

    /**
     * تحديث حالة البيانات إلى "failed" وزيادة عداد المحاولات
     */
    public void markAsFailed(long id, String errorMessage) {
        SQLiteDatabase db = getWritableDatabase();

        // قراءة retry_count الحالي
        Cursor cursor = db.query(TABLE_SYNC_QUEUE, new String[]{COL_RETRY_COUNT},
                                 COL_ID + " = ?", new String[]{String.valueOf(id)},
                                 null, null, null);

        int retryCount = 0;
        if (cursor.moveToFirst()) {
            retryCount = cursor.getInt(0);
        }
        cursor.close();

        ContentValues values = new ContentValues();
        values.put(COL_STATUS, STATUS_FAILED);
        values.put(COL_RETRY_COUNT, retryCount + 1);
        values.put(COL_ERROR_MESSAGE, errorMessage);

        int rows = db.update(TABLE_SYNC_QUEUE, values, COL_ID + " = ?", new String[]{String.valueOf(id)});

        Log.e(TAG, "❌ Marked as failed: ID=" + id + ", Retry=" + (retryCount + 1) + "/" + MAX_RETRY_ATTEMPTS +
                   ", Error=" + errorMessage);
    }

    /**
     * إعادة محاولة البيانات الفاشلة (تحويلها إلى pending)
     */
    public int retryFailedData() {
        SQLiteDatabase db = getWritableDatabase();

        ContentValues values = new ContentValues();
        values.put(COL_STATUS, STATUS_PENDING);
        values.put(COL_RETRY_COUNT, 0);
        values.put(COL_ERROR_MESSAGE, (String) null);

        int rows = db.update(TABLE_SYNC_QUEUE, values,
                            COL_STATUS + " = ?",
                            new String[]{STATUS_FAILED});

        Log.d(TAG, "🔄 Retrying " + rows + " failed data items");
        return rows;
    }

    /**
     * حذف البيانات المُرفعة بنجاح
     */
    public int clearCompletedData() {
        SQLiteDatabase db = getWritableDatabase();

        int rows = db.delete(TABLE_SYNC_QUEUE,
                            COL_STATUS + " = ?",
                            new String[]{STATUS_UPLOADED});

        Log.d(TAG, "🗑️ Cleared " + rows + " uploaded data items");
        return rows;
    }

    /**
     * الحصول على عدد البيانات المنتظرة
     */
    public int getPendingDataCount() {
        SQLiteDatabase db = getReadableDatabase();

        Cursor cursor = db.rawQuery(
            "SELECT COUNT(*) FROM " + TABLE_SYNC_QUEUE +
            " WHERE " + COL_STATUS + " = ? AND " + COL_RETRY_COUNT + " < ?",
            new String[]{STATUS_PENDING, String.valueOf(MAX_RETRY_ATTEMPTS)}
        );

        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();

        return count;
    }

    /**
     * الحصول على عدد البيانات المُرفعة
     */
    public int getUploadedDataCount() {
        SQLiteDatabase db = getReadableDatabase();

        Cursor cursor = db.rawQuery(
            "SELECT COUNT(*) FROM " + TABLE_SYNC_QUEUE + " WHERE " + COL_STATUS + " = ?",
            new String[]{STATUS_UPLOADED}
        );

        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();

        return count;
    }

    /**
     * الحصول على عدد البيانات الفاشلة
     */
    public int getFailedDataCount() {
        SQLiteDatabase db = getReadableDatabase();

        Cursor cursor = db.rawQuery(
            "SELECT COUNT(*) FROM " + TABLE_SYNC_QUEUE + " WHERE " + COL_STATUS + " = ?",
            new String[]{STATUS_FAILED}
        );

        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();

        return count;
    }

    /**
     * تحويل Cursor إلى DataSyncItem
     */
    private DataSyncItem cursorToItem(Cursor cursor) {
        DataSyncItem item = new DataSyncItem();
        item.id = cursor.getLong(cursor.getColumnIndexOrThrow(COL_ID));
        item.dataType = cursor.getString(cursor.getColumnIndexOrThrow(COL_DATA_TYPE));
        item.dataJson = cursor.getString(cursor.getColumnIndexOrThrow(COL_DATA_JSON));
        item.endpoint = cursor.getString(cursor.getColumnIndexOrThrow(COL_ENDPOINT));
        item.status = cursor.getString(cursor.getColumnIndexOrThrow(COL_STATUS));
        item.retryCount = cursor.getInt(cursor.getColumnIndexOrThrow(COL_RETRY_COUNT));

        int errorIdx = cursor.getColumnIndexOrThrow(COL_ERROR_MESSAGE);
        item.errorMessage = cursor.isNull(errorIdx) ? null : cursor.getString(errorIdx);

        item.createdAt = cursor.getLong(cursor.getColumnIndexOrThrow(COL_CREATED_AT));

        int uploadedIdx = cursor.getColumnIndexOrThrow(COL_UPLOADED_AT);
        item.uploadedAt = cursor.isNull(uploadedIdx) ? 0 : cursor.getLong(uploadedIdx);

        return item;
    }

    /**
     * DataSyncItem - كائن يمثل عنصر واحد في قائمة المزامنة
     */
    public static class DataSyncItem {
        public long id;
        public String dataType;
        public String dataJson;
        public String endpoint;
        public String status;
        public int retryCount;
        public String errorMessage;
        public long createdAt;
        public long uploadedAt;
    }
}
