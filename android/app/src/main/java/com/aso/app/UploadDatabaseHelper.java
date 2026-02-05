package com.aso.app;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import java.util.ArrayList;
import java.util.List;

/**
 * مدير قاعدة بيانات محلية لإدارة قائمة انتظار الملفات المطلوب رفعها
 * يعمل بشكل مستقل عن الصفحات وحالة التطبيق
 */
public class UploadDatabaseHelper extends SQLiteOpenHelper {
    private static final String TAG = "UploadDatabaseHelper";
    private static final String DATABASE_NAME = "upload_queue.db";
    private static final int DATABASE_VERSION = 4;  // Version 4: Added associationName and personName columns

    // Table name
    private static final String TABLE_UPLOAD_QUEUE = "upload_queue";
    private static final String TABLE_INDEXEDDB_MAPPING = "indexeddb_mapping";  // جدول جديد

    // Columns - upload_queue
    private static final String COLUMN_ID = "id";
    private static final String COLUMN_FILE_PATH = "file_path";
    private static final String COLUMN_FILE_NAME = "file_name";
    private static final String COLUMN_FILE_TYPE = "file_type";
    private static final String COLUMN_PHOTO_ID = "photo_id";
    private static final String COLUMN_API_URL = "api_url";
    private static final String COLUMN_STATUS = "status";
    private static final String COLUMN_RETRY_COUNT = "retry_count";
    private static final String COLUMN_ERROR_MESSAGE = "error_message";
    private static final String COLUMN_CREATED_AT = "created_at";
    private static final String COLUMN_UPDATED_AT = "updated_at";
    private static final String COLUMN_ASSOCIATION_NAME = "association_name";
    private static final String COLUMN_PERSON_NAME = "person_name";

    // Columns - indexeddb_mapping
    private static final String COLUMN_SQLITE_ID = "sqlite_id";
    private static final String COLUMN_INDEXEDDB_ID = "indexeddb_id";

    // Status values
    public static final String STATUS_PENDING = "pending";
    public static final String STATUS_UPLOADING = "uploading";
    public static final String STATUS_COMPLETED = "completed";
    public static final String STATUS_FAILED = "failed";

    private static UploadDatabaseHelper instance;

    public static synchronized UploadDatabaseHelper getInstance(Context context) {
        if (instance == null) {
            instance = new UploadDatabaseHelper(context.getApplicationContext());
        }
        return instance;
    }

    private UploadDatabaseHelper(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        String CREATE_TABLE = "CREATE TABLE " + TABLE_UPLOAD_QUEUE + " ("
                + COLUMN_ID + " INTEGER PRIMARY KEY AUTOINCREMENT, "
                + COLUMN_FILE_PATH + " TEXT NOT NULL, "
                + COLUMN_FILE_NAME + " TEXT NOT NULL, "
                + COLUMN_FILE_TYPE + " TEXT, "
                + COLUMN_PHOTO_ID + " INTEGER NOT NULL, "
                + COLUMN_API_URL + " TEXT NOT NULL, "
                + COLUMN_STATUS + " TEXT DEFAULT '" + STATUS_PENDING + "', "
                + COLUMN_RETRY_COUNT + " INTEGER DEFAULT 0, "
                + COLUMN_ERROR_MESSAGE + " TEXT, "
                + COLUMN_CREATED_AT + " INTEGER NOT NULL, "
                + COLUMN_UPDATED_AT + " INTEGER NOT NULL, "
                + COLUMN_ASSOCIATION_NAME + " TEXT DEFAULT 'General', "
                + COLUMN_PERSON_NAME + " TEXT DEFAULT 'unknown'"
                + ")";

        db.execSQL(CREATE_TABLE);
        Log.d(TAG, "تم إنشاء جدول قائمة الرفع بنجاح");

        // إنشاء جدول mapping
        String CREATE_MAPPING_TABLE = "CREATE TABLE " + TABLE_INDEXEDDB_MAPPING + " ("
                + COLUMN_SQLITE_ID + " INTEGER PRIMARY KEY, "
                + COLUMN_INDEXEDDB_ID + " INTEGER NOT NULL"
                + ")";
        db.execSQL(CREATE_MAPPING_TABLE);
        Log.d(TAG, "تم إنشاء جدول mapping بنجاح");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        if (oldVersion < 3) {
            // إضافة جدول mapping في الإصدار 3
            String CREATE_MAPPING_TABLE = "CREATE TABLE IF NOT EXISTS " + TABLE_INDEXEDDB_MAPPING + " ("
                    + COLUMN_SQLITE_ID + " INTEGER PRIMARY KEY, "
                    + COLUMN_INDEXEDDB_ID + " INTEGER NOT NULL"
                    + ")";
            db.execSQL(CREATE_MAPPING_TABLE);
            Log.d(TAG, "Database upgraded to version 3: Added mapping table");
        }
        if (oldVersion < 4) {
            // إضافة أعمدة associationName و personName في الإصدار 4
            db.execSQL("ALTER TABLE " + TABLE_UPLOAD_QUEUE + " ADD COLUMN " + COLUMN_ASSOCIATION_NAME + " TEXT DEFAULT 'General'");
            db.execSQL("ALTER TABLE " + TABLE_UPLOAD_QUEUE + " ADD COLUMN " + COLUMN_PERSON_NAME + " TEXT DEFAULT 'unknown'");
            Log.d(TAG, "Database upgraded to version 4: Added associationName and personName columns");
        }
    }

    /**
     * إضافة ملف جديد إلى قائمة الانتظار
     */
    public long addFileToQueue(String filePath, String fileName, String fileType, int photoId, String apiUrl, String associationName, String personName) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();

        long currentTime = System.currentTimeMillis();

        values.put(COLUMN_FILE_PATH, filePath);
        values.put(COLUMN_FILE_NAME, fileName);
        values.put(COLUMN_FILE_TYPE, fileType);
        values.put(COLUMN_PHOTO_ID, photoId);
        values.put(COLUMN_API_URL, apiUrl);
        values.put(COLUMN_STATUS, STATUS_PENDING);
        values.put(COLUMN_RETRY_COUNT, 0);
        values.put(COLUMN_CREATED_AT, currentTime);
        values.put(COLUMN_UPDATED_AT, currentTime);
        values.put(COLUMN_ASSOCIATION_NAME, associationName != null ? associationName : "General");
        values.put(COLUMN_PERSON_NAME, personName != null ? personName : "unknown");

        long id = db.insert(TABLE_UPLOAD_QUEUE, null, values);
        Log.d(TAG, "Added new file to queue: " + fileName + " (ID: " + id + ", Association: " + associationName + ", Person: " + personName + ")");

        return id;
    }

    /**
     * الحصول على جميع الملفات في حالة معينة
     */
    public List<UploadItem> getFilesByStatus(String status) {
        List<UploadItem> files = new ArrayList<>();
        SQLiteDatabase db = this.getReadableDatabase();

        Cursor cursor = db.query(
            TABLE_UPLOAD_QUEUE,
            null,
            COLUMN_STATUS + " = ?",
            new String[]{status},
            null,
            null,
            COLUMN_CREATED_AT + " ASC"
        );

        if (cursor.moveToFirst()) {
            do {
                UploadItem item = cursorToUploadItem(cursor);
                files.add(item);
            } while (cursor.moveToNext());
        }

        cursor.close();
        Log.d(TAG, "تم جلب " + files.size() + " ملف بحالة: " + status);

        return files;
    }

    /**
     * الحصول على أول ملف معلق في القائمة
     */
    public UploadItem getNextPendingFile() {
        SQLiteDatabase db = this.getReadableDatabase();

        Cursor cursor = db.query(
            TABLE_UPLOAD_QUEUE,
            null,
            COLUMN_STATUS + " = ?",
            new String[]{STATUS_PENDING},
            null,
            null,
            COLUMN_CREATED_AT + " ASC",
            "1"
        );

        UploadItem item = null;
        if (cursor.moveToFirst()) {
            item = cursorToUploadItem(cursor);
        }

        cursor.close();
        return item;
    }

    /**
     * تحديث حالة الملف
     */
    public void updateFileStatus(long id, String status, String errorMessage) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();

        values.put(COLUMN_STATUS, status);
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        if (errorMessage != null) {
            values.put(COLUMN_ERROR_MESSAGE, errorMessage);
        }

        db.update(TABLE_UPLOAD_QUEUE, values, COLUMN_ID + " = ?", new String[]{String.valueOf(id)});
        Log.d(TAG, "تم تحديث حالة الملف " + id + " إلى: " + status);
    }

    /**
     * زيادة عداد إعادة المحاولة
     */
    public void incrementRetryCount(long id) {
        SQLiteDatabase db = this.getWritableDatabase();
        db.execSQL("UPDATE " + TABLE_UPLOAD_QUEUE
                + " SET " + COLUMN_RETRY_COUNT + " = " + COLUMN_RETRY_COUNT + " + 1, "
                + COLUMN_UPDATED_AT + " = " + System.currentTimeMillis()
                + " WHERE " + COLUMN_ID + " = " + id);

        Log.d(TAG, "تم زيادة عداد إعادة المحاولة للملف: " + id);
    }

    /**
     * حذف الملفات المكتملة
     */
    public void deleteCompletedFiles() {
        SQLiteDatabase db = this.getWritableDatabase();
        int deleted = db.delete(TABLE_UPLOAD_QUEUE, COLUMN_STATUS + " = ?",
                               new String[]{STATUS_COMPLETED});
        Log.d(TAG, "تم حذف " + deleted + " ملف مكتمل من القائمة");
    }

    /**
     * إعادة تعيين الملفات الفاشلة إلى pending عند عودة الإنترنت
     */
    public int resetFailedFiles() {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put(COLUMN_STATUS, STATUS_PENDING);
        values.put(COLUMN_RETRY_COUNT, 0);
        values.put(COLUMN_ERROR_MESSAGE, (String) null);
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        int updated = db.update(TABLE_UPLOAD_QUEUE, values,
                COLUMN_STATUS + " = ?",
                new String[]{STATUS_FAILED});

        if (updated > 0) {
            Log.e(TAG, "✅ تم إعادة تعيين " + updated + " ملف فاشل إلى pending");
        }
        return updated;
    }

    /**
     * حذف ملف معين
     */
    public void deleteFile(long id) {
        SQLiteDatabase db = this.getWritableDatabase();
        db.delete(TABLE_UPLOAD_QUEUE, COLUMN_ID + " = ?", new String[]{String.valueOf(id)});
        Log.d(TAG, "تم حذف الملف: " + id);
    }

    /**
     * الحصول على عدد الملفات المعلقة
     */
    public int getPendingFilesCount() {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery(
            "SELECT COUNT(*) FROM " + TABLE_UPLOAD_QUEUE
            + " WHERE " + COLUMN_STATUS + " IN (?, ?)",
            new String[]{STATUS_PENDING, STATUS_UPLOADING}
        );

        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();

        return count;
    }

    /**
     * إعادة تعيين جميع الملفات التي كانت قيد الرفع إلى معلق
     * (يستخدم عند بدء التطبيق للتعامل مع الملفات التي توقفت بسبب إغلاق التطبيق)
     */
    public void resetUploadingFiles() {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put(COLUMN_STATUS, STATUS_PENDING);
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        int updated = db.update(TABLE_UPLOAD_QUEUE, values,
                               COLUMN_STATUS + " = ?",
                               new String[]{STATUS_UPLOADING});

        if (updated > 0) {
            Log.d(TAG, "تمت إعادة تعيين " + updated + " ملف من حالة الرفع إلى معلق");
        }
    }

    /**
     * الحصول على جميع الملفات الفاشلة
     */
    public List<UploadItem> getFailedFiles() {
        return getFilesByStatus(STATUS_FAILED);
    }

    /**
     * إعادة محاولة رفع الملفات الفاشلة
     */
    public void retryFailedFiles() {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put(COLUMN_STATUS, STATUS_PENDING);
        values.put(COLUMN_ERROR_MESSAGE, (String) null);
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        int updated = db.update(TABLE_UPLOAD_QUEUE, values,
                               COLUMN_STATUS + " = ?",
                               new String[]{STATUS_FAILED});

        Log.d(TAG, "تمت إعادة تعيين " + updated + " ملف فاشل للمحاولة مرة أخرى");
    }

    private UploadItem cursorToUploadItem(Cursor cursor) {
        UploadItem item = new UploadItem();
        item.id = cursor.getLong(cursor.getColumnIndexOrThrow(COLUMN_ID));
        item.filePath = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_FILE_PATH));
        item.fileName = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_FILE_NAME));
        item.fileType = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_FILE_TYPE));
        item.photoId = cursor.getInt(cursor.getColumnIndexOrThrow(COLUMN_PHOTO_ID));
        item.apiUrl = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_API_URL));
        item.status = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_STATUS));
        item.retryCount = cursor.getInt(cursor.getColumnIndexOrThrow(COLUMN_RETRY_COUNT));
        item.errorMessage = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_ERROR_MESSAGE));
        item.createdAt = cursor.getLong(cursor.getColumnIndexOrThrow(COLUMN_CREATED_AT));
        item.updatedAt = cursor.getLong(cursor.getColumnIndexOrThrow(COLUMN_UPDATED_AT));

        // Load new fields (with null check for database migration)
        int associationIdx = cursor.getColumnIndex(COLUMN_ASSOCIATION_NAME);
        int personIdx = cursor.getColumnIndex(COLUMN_PERSON_NAME);
        item.associationName = (associationIdx >= 0) ? cursor.getString(associationIdx) : "General";
        item.personName = (personIdx >= 0) ? cursor.getString(personIdx) : "unknown";

        return item;
    }

    /**
     * حفظ mapping بين SQLite ID و IndexedDB ID
     */
    public void saveIndexedDbMapping(long sqliteId, int indexedDbId) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put(COLUMN_SQLITE_ID, sqliteId);
        values.put(COLUMN_INDEXEDDB_ID, indexedDbId);

        db.insertWithOnConflict(TABLE_INDEXEDDB_MAPPING, null, values, SQLiteDatabase.CONFLICT_REPLACE);
        Log.d(TAG, "حفظ mapping: SQLite=" + sqliteId + " → IndexedDB=" + indexedDbId);
    }

    /**
     * الحصول على IndexedDB ID من SQLite ID
     */
    public int getIndexedDbId(long sqliteId) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.query(TABLE_INDEXEDDB_MAPPING,
                new String[]{COLUMN_INDEXEDDB_ID},
                COLUMN_SQLITE_ID + " = ?",
                new String[]{String.valueOf(sqliteId)},
                null, null, null);

        int indexedDbId = -1;
        if (cursor != null && cursor.moveToFirst()) {
            indexedDbId = cursor.getInt(0);
            cursor.close();
        }
        return indexedDbId;
    }

    /**
     * الحصول على جميع الملفات المرفوعة التي تحتاج مزامنة مع IndexedDB
     */
    public List<Long> getUploadedFilesNeedingSync() {
        List<Long> sqliteIds = new ArrayList<>();
        SQLiteDatabase db = this.getReadableDatabase();

        Cursor cursor = db.query(TABLE_UPLOAD_QUEUE,
                new String[]{COLUMN_ID},
                COLUMN_STATUS + " = ?",
                new String[]{STATUS_COMPLETED},
                null, null, null);

        if (cursor != null) {
            while (cursor.moveToNext()) {
                sqliteIds.add(cursor.getLong(0));
            }
            cursor.close();
        }

        return sqliteIds;
    }

    /**
     * عنصر الرفع
     */
    public static class UploadItem {
        public long id;
        public String filePath;
        public String fileName;
        public String fileType;
        public int photoId;
        public String apiUrl;
        public String status;
        public int retryCount;
        public String errorMessage;
        public long createdAt;
        public long updatedAt;
        public String associationName;
        public String personName;

        @Override
        public String toString() {
            return "UploadItem{" +
                    "id=" + id +
                    ", fileName='" + fileName + '\'' +
                    ", fileType='" + fileType + '\'' +
                    ", photoId=" + photoId +
                    ", status='" + status + '\'' +
                    ", retryCount=" + retryCount +
                    ", association='" + associationName + '\'' +
                    ", person='" + personName + '\'' +
                    '}';
        }
    }

    /**
     * الحصول على عدد الملفات المرفوعة بنجاح
     */
    public int getUploadedFilesCount() {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery(
            "SELECT COUNT(*) FROM " + TABLE_UPLOAD_QUEUE +
            " WHERE " + COLUMN_STATUS + " = ?",
            new String[]{STATUS_COMPLETED}
        );

        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();
        return count;
    }

    /**
     * الحصول على عدد الملفات الفاشلة
     */
    public int getFailedFilesCount() {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery(
            "SELECT COUNT(*) FROM " + TABLE_UPLOAD_QUEUE +
            " WHERE " + COLUMN_STATUS + " = ?",
            new String[]{STATUS_FAILED}
        );

        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();
        return count;
    }
}
