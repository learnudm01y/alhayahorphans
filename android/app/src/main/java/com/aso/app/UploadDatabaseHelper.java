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
    private static final int DATABASE_VERSION = 5;  // Version 5: Added person_name_history table

    // Table name
    private static final String TABLE_UPLOAD_QUEUE = "upload_queue";
    private static final String TABLE_INDEXEDDB_MAPPING = "indexeddb_mapping";  // جدول جديد
    private static final String TABLE_PERSON_NAME_HISTORY = "person_name_history";  // ✨ NEW: تتبع الأسماء

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

        // ✨ NEW: إنشاء جدول تتبع الأسماء
        String CREATE_NAME_HISTORY = "CREATE TABLE " + TABLE_PERSON_NAME_HISTORY + " ("
                + "id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "sponsorship_id INTEGER NOT NULL UNIQUE, "
                + "current_association_name TEXT, "
                + "current_person_name TEXT NOT NULL, "
                + "previous_association_name TEXT, "
                + "previous_person_name TEXT, "
                + "folder_path TEXT, "
                + "updated_at INTEGER NOT NULL"
                + ")";
        db.execSQL(CREATE_NAME_HISTORY);
        Log.d(TAG, "تم إنشاء جدول person_name_history بنجاح");
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
        if (oldVersion < 5) {
            // إضافة جدول تتبع الأسماء في الإصدار 5
            String CREATE_NAME_HISTORY = "CREATE TABLE IF NOT EXISTS " + TABLE_PERSON_NAME_HISTORY + " ("
                    + "id INTEGER PRIMARY KEY AUTOINCREMENT, "
                    + "sponsorship_id INTEGER NOT NULL UNIQUE, "
                    + "current_association_name TEXT, "
                    + "current_person_name TEXT NOT NULL, "
                    + "previous_association_name TEXT, "
                    + "previous_person_name TEXT, "
                    + "folder_path TEXT, "
                    + "updated_at INTEGER NOT NULL"
                    + ")";
            db.execSQL(CREATE_NAME_HISTORY);
            Log.d(TAG, "Database upgraded to version 5: Added person_name_history table");
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

        // ✨ حفظ الاسم الحالي في person_name_history عند إضافة ملف لأول مرة
        // هذا يضمن أننا نملك سجل للاسم الأصلي عند التعديل لاحقاً
        if (photoId > 0 && personName != null && !personName.equals("unknown")) {
            String folderPath = "Documents/sponsorships_alhayahorphans/" +
                (associationName != null ? associationName : "General") + "/" + personName;

            // حفظ فقط إذا لم يكن موجوداً من قبل (لا نريد استبدال السجل القديم)
            String[] existingHistory = getPreviousPersonName(photoId);
            if (existingHistory == null) {
                savePersonNameHistory(photoId, associationName, personName, folderPath);
                Log.d(TAG, "✅ Saved initial name history for sponsorship " + photoId + ": " + personName);
            }
        }

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

    /**
     * تحديث اسم الشخص في جميع الملفات الخاصة به
     * يُستخدم عند تعديل اسم المكفول
     */
    public int updatePersonNameInQueue(int sponsorshipId, String newPersonName) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put(COLUMN_PERSON_NAME, newPersonName);

        int rowsUpdated = db.update(
            TABLE_UPLOAD_QUEUE,
            values,
            COLUMN_PHOTO_ID + " = ?",
            new String[]{String.valueOf(sponsorshipId)}
        );

        Log.d(TAG, "✅ Updated person_name for sponsorshipId=" + sponsorshipId +
                   " to '" + newPersonName + "' (" + rowsUpdated + " rows)");
        return rowsUpdated;
    }

    /**
     * ✨ NEW: حفظ أو تحديث سجل تاريخ الأسماء
     */
    public void savePersonNameHistory(int sponsorshipId, String associationName, String personName, String folderPath) {
        SQLiteDatabase db = this.getWritableDatabase();

        // الحصول على الاسم القديم إن وُجد
        String previousAssociation = null;
        String previousPerson = null;

        Cursor cursor = db.query(
            TABLE_PERSON_NAME_HISTORY,
            new String[]{"current_association_name", "current_person_name"},
            "sponsorship_id = ?",
            new String[]{String.valueOf(sponsorshipId)},
            null, null, null
        );

        if (cursor.moveToFirst()) {
            previousAssociation = cursor.getString(0);
            previousPerson = cursor.getString(1);
        }
        cursor.close();

        ContentValues values = new ContentValues();
        values.put("sponsorship_id", sponsorshipId);
        values.put("current_association_name", associationName);
        values.put("current_person_name", personName);
        values.put("previous_association_name", previousAssociation);
        values.put("previous_person_name", previousPerson);
        values.put("folder_path", folderPath);
        values.put("updated_at", System.currentTimeMillis());

        int rows = db.update(
            TABLE_PERSON_NAME_HISTORY,
            values,
            "sponsorship_id = ?",
            new String[]{String.valueOf(sponsorshipId)}
        );

        if (rows == 0) {
            db.insert(TABLE_PERSON_NAME_HISTORY, null, values);
            Log.d(TAG, "✅ Created name history for sponsorship " + sponsorshipId);
        } else {
            Log.d(TAG, "✅ Updated name history for sponsorship " + sponsorshipId);
        }
    }

    /**
     * ✨ NEW: الحصول على الاسم السابق للمكفول
     */
    public String[] getPreviousPersonName(int sponsorshipId) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.query(
            TABLE_PERSON_NAME_HISTORY,
            new String[]{"previous_association_name", "previous_person_name", "current_association_name", "current_person_name"},
            "sponsorship_id = ?",
            new String[]{String.valueOf(sponsorshipId)},
            null, null, null
        );

        String[] result = null;
        if (cursor.moveToFirst()) {
            String prevAssoc = cursor.getString(0);
            String prevPerson = cursor.getString(1);
            String currAssoc = cursor.getString(2);
            String currPerson = cursor.getString(3);

            // إذا لم يكن هناك اسم سابق، استخدم الاسم الحالي
            result = new String[]{
                prevAssoc != null ? prevAssoc : currAssoc,
                prevPerson != null ? prevPerson : currPerson,
                currAssoc,
                currPerson
            };
        }
        cursor.close();
        return result; // [0]=old_assoc, [1]=old_person, [2]=current_assoc, [3]=current_person
    }

    /**
     * ✨ NEW: الحصول على المسار الفعلي للمجلد من قاعدة البيانات
     * هذا هو "المفتاح الفريد" للمجلد الفيزيائي - نستخدمه لإعادة تسمية المجلد بشكل صحيح
     */
    public String getFolderPath(int sponsorshipId) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.query(
            TABLE_PERSON_NAME_HISTORY,
            new String[]{"folder_path"},
            "sponsorship_id = ?",
            new String[]{String.valueOf(sponsorshipId)},
            null, null, null
        );

        String folderPath = null;
        if (cursor.moveToFirst()) {
            folderPath = cursor.getString(0);
        }
        cursor.close();

        Log.d(TAG, "🔑 getFolderPath for sponsorshipId=" + sponsorshipId + ": " + folderPath);
        return folderPath;
    }

    /**
     * ✨ NEW: تحديث المسار الفعلي للمجلد بعد إعادة التسمية
     */
    public void updateFolderPath(int sponsorshipId, String newFolderPath) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put("folder_path", newFolderPath);
        values.put("updated_at", System.currentTimeMillis());

        int rows = db.update(
            TABLE_PERSON_NAME_HISTORY,
            values,
            "sponsorship_id = ?",
            new String[]{String.valueOf(sponsorshipId)}
        );

        Log.d(TAG, "✅ updateFolderPath for sponsorshipId=" + sponsorshipId + " to: " + newFolderPath + " (" + rows + " rows)");
    }

    /**
     * إعادة تعيين حالة الملفات الفاشلة لمكفول معين
     * لإعادة محاولة رفعها بعد تغيير الاسم/المجلد
     */
    public int resetFailedUploads(int sponsorshipId) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put(COLUMN_STATUS, STATUS_PENDING);
        values.put(COLUMN_RETRY_COUNT, 0);
        values.putNull(COLUMN_ERROR_MESSAGE);
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        int rowsUpdated = db.update(
            TABLE_UPLOAD_QUEUE,
            values,
            COLUMN_PHOTO_ID + " = ? AND " + COLUMN_STATUS + " = ?",
            new String[]{String.valueOf(sponsorshipId), STATUS_FAILED}
        );

        Log.d(TAG, "✅ Reset " + rowsUpdated + " failed uploads for sponsorshipId=" + sponsorshipId);
        return rowsUpdated;
    }
}
