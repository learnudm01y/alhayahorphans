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
    private Context context;

    // Database info
    private static final String DATABASE_NAME = "data_sync.db";
    private static final int DATABASE_VERSION = 2;

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
    private static final String COL_PROCESSING_STARTED_AT = "processing_started_at"; // When upload started

    // Status values
    public static final String STATUS_PENDING = "pending";
    public static final String STATUS_UPLOADING = "uploading";
    public static final String STATUS_UPLOADED = "uploaded";
    public static final String STATUS_FAILED = "failed";

    // Max retry attempts
    public static final int MAX_RETRY_ATTEMPTS = 3;

    private DataSyncDatabaseHelper(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
        this.context = context.getApplicationContext();
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
                + COL_UPLOADED_AT + " INTEGER, "
                + COL_PROCESSING_STARTED_AT + " INTEGER"
                + ")";

        db.execSQL(createTable);
        Log.d(TAG, "✅ Database created: sync_queue table ready");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        if (oldVersion < 2) {
            db.execSQL("ALTER TABLE " + TABLE_SYNC_QUEUE + " ADD COLUMN " + COL_PROCESSING_STARTED_AT + " INTEGER");
            Log.d(TAG, "✅ Database upgraded from v" + oldVersion + " to v" + newVersion + ": added processing_started_at");
        }
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
     * الحصول على جميع البيانات المنتظرة (pending + failed بشرط retry < 3)
     */
    public List<DataSyncItem> getPendingData() {
        List<DataSyncItem> items = new ArrayList<>();
        SQLiteDatabase db = getReadableDatabase();

        // البيانات المعلقة = pending أو failed بشرط أن retry < 3
        Cursor cursor = db.query(
            TABLE_SYNC_QUEUE,
            null,
            "(" + COL_STATUS + " = ? OR " + COL_STATUS + " = ?) AND " + COL_RETRY_COUNT + " < ?",
            new String[]{STATUS_PENDING, STATUS_FAILED, String.valueOf(MAX_RETRY_ATTEMPTS)},
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
        values.put(COL_PROCESSING_STARTED_AT, System.currentTimeMillis());

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
        values.putNull(COL_PROCESSING_STARTED_AT);

        int rows = db.update(TABLE_SYNC_QUEUE, values, COL_ID + " = ?", new String[]{String.valueOf(id)});
        Log.d(TAG, "✅ Marked as uploaded: ID=" + id + " (rows=" + rows + ")");

        // إرسال broadcast للـ JavaScript
        sendStatsUpdateBroadcast();
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

        // إرسال broadcast للـ JavaScript
        sendStatsUpdateBroadcast();
    }

    /**
     * ✨ NEW: إعادة تعيين البيانات الفاشلة إلى pending عند عودة الإنترنت
     * هذه الدالة تُستدعى من UnifiedNetworkMonitor عند اكتشاف الاتصال
     */
    public int resetFailedData() {
        SQLiteDatabase db = getWritableDatabase();

        ContentValues values = new ContentValues();
        values.put(COL_STATUS, STATUS_PENDING);
        values.put(COL_RETRY_COUNT, 0);
        values.put(COL_ERROR_MESSAGE, (String) null);
        values.putNull(COL_PROCESSING_STARTED_AT);

        // إعادة تعيين العناصر الفاشلة + العناصر القيد المعالجة لأكثر من 5 دقائق
        long fiveMinutesAgo = System.currentTimeMillis() - (5 * 60 * 1000);
        int rows = db.update(TABLE_SYNC_QUEUE, values,
                            COL_STATUS + " = ? OR (" + COL_STATUS + " = ? AND " + COL_PROCESSING_STARTED_AT + " < ?)",
                            new String[]{STATUS_FAILED, STATUS_UPLOADING, String.valueOf(fiveMinutesAgo)});

        // ✅ طباعة log فقط عندما يكون هناك failed items فعلاً (توفير الذاكرة)
        if (rows > 0) {
            Log.d(TAG, "🔄 Reset " + rows + " failed items to pending (retry count = 0)");
            // إرسال broadcast لتحديث الواجهة
            sendStatsUpdateBroadcast();
        }
        
        return rows;
    }

    /**
     * الحصول على معرفات (IDs) الكيانات التي لها تعديلات قيد الانتظار (لمنع استبدالها)
     */
    public java.util.Set<Integer> getPendingEntityIds() {
        java.util.Set<Integer> pendingIds = new java.util.HashSet<>();
        SQLiteDatabase db = getReadableDatabase();
        
        Cursor cursor = db.rawQuery(
            "SELECT " + COL_DATA_JSON + " FROM " + TABLE_SYNC_QUEUE +
            " WHERE " + COL_STATUS + " = ? OR " + COL_STATUS + " = ?",
            new String[]{STATUS_PENDING, STATUS_FAILED}
        );

        while (cursor.moveToNext()) {
            try {
                String jsonStr = cursor.getString(0);
                org.json.JSONObject obj = new org.json.JSONObject(jsonStr);
                if (obj.has("entity_id")) {
                    pendingIds.add(obj.getInt("entity_id"));
                } else if (obj.has("id")) {
                    pendingIds.add(obj.getInt("id"));
                }
            } catch (Exception e) {
                Log.e(TAG, "Error parsing entity ID from pending action", e);
            }
        }
        cursor.close();
        
        return pendingIds;
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
     * الحصول على عدد البيانات المنتظرة (pending + failed)
     * ✨ CRITICAL FIX: إزالة شرط retry < 3 حتى تبقى البيانات الفاشلة للأبد
     */
    public int getPendingDataCount() {
        SQLiteDatabase db = getReadableDatabase();

        // البيانات المعلقة = pending أو failed (بدون حد للمحاولات!)
        Cursor cursor = db.rawQuery(
            "SELECT COUNT(*) FROM " + TABLE_SYNC_QUEUE +
            " WHERE " + COL_STATUS + " = ? OR " + COL_STATUS + " = ?",
            new String[]{STATUS_PENDING, STATUS_FAILED}
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
     * إرسال broadcast للـ JavaScript لتحديث الإحصائيات
     */
    private void sendStatsUpdateBroadcast() {
        try {
            int pending = getPendingDataCount();
            int uploaded = getUploadedDataCount();
            int failed = getFailedDataCount();

            android.content.Intent intent = new android.content.Intent("com.aso.app.DATA_SYNC_STATS");
            intent.putExtra("pending", pending);
            intent.putExtra("uploaded", uploaded);
            intent.putExtra("failed", failed);
            intent.putExtra("total", pending + uploaded + failed);
            intent.setPackage(context.getPackageName());

            if (context != null) {
                context.sendBroadcast(intent);
                Log.d(TAG, "📡 Broadcast sent: pending=" + pending + ", uploaded=" + uploaded + ", failed=" + failed);
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ Error sending broadcast", e);
        }
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
