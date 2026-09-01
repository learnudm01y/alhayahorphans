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
    private static final int DATABASE_VERSION = 6;  // Version 6: Added auth_token column

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
    private static final String COLUMN_AUTH_TOKEN = "auth_token";  // ✨ NEW: Token for upload
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
    public static final String STATUS_PROCESSING_SERVER = "processing_server";
    public static final String STATUS_COMPLETED = "completed";
    public static final String STATUS_FAILED = "failed";

    private static UploadDatabaseHelper instance;
    private Context context;

    public static synchronized UploadDatabaseHelper getInstance(Context context) {
        if (instance == null) {
            instance = new UploadDatabaseHelper(context.getApplicationContext());
        }
        return instance;
    }

    private UploadDatabaseHelper(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
        this.context = context;
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
                + COLUMN_AUTH_TOKEN + " TEXT, "
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
        if (oldVersion < 6) {
            // إضافة عمود auth_token في الإصدار 6
            db.execSQL("ALTER TABLE " + TABLE_UPLOAD_QUEUE + " ADD COLUMN " + COLUMN_AUTH_TOKEN + " TEXT");
            Log.d(TAG, "Database upgraded to version 6: Added auth_token column");
        }
    }

    /**
     * إضافة ملف جديد إلى قائمة الانتظار
     */
    public long addFileToQueue(String filePath, String fileName, String fileType, int photoId, String apiUrl, String authToken, String associationName, String personName) {
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  💾 addFileToQueue() - Saving to SQLite Database             ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        Log.e(TAG, "📋 Input Parameters:");
        Log.e(TAG, "   filePath: " + filePath);
        Log.e(TAG, "   fileName: " + fileName);
        Log.e(TAG, "   fileType: " + fileType);
        Log.e(TAG, "   photoId: " + photoId);
        Log.e(TAG, "   apiUrl: " + apiUrl);
        Log.e(TAG, "   authToken: " + (authToken != null && !authToken.isEmpty() ? "[present, length=" + authToken.length() + "]" : "[empty]"));
        Log.e(TAG, "   associationName: " + associationName);
        Log.e(TAG, "   personName: " + personName);

        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();

        long currentTime = System.currentTimeMillis();

        values.put(COLUMN_FILE_PATH, filePath);
        values.put(COLUMN_FILE_NAME, fileName);
        values.put(COLUMN_FILE_TYPE, fileType);
        values.put(COLUMN_PHOTO_ID, photoId);
        values.put(COLUMN_API_URL, apiUrl);
        values.put(COLUMN_AUTH_TOKEN, authToken);
        values.put(COLUMN_STATUS, STATUS_PENDING);
        values.put(COLUMN_RETRY_COUNT, 0);
        values.put(COLUMN_CREATED_AT, currentTime);
        values.put(COLUMN_UPDATED_AT, currentTime);
        values.put(COLUMN_ASSOCIATION_NAME, associationName != null ? associationName : "General");
        values.put(COLUMN_PERSON_NAME, personName != null ? personName : "unknown");

        Log.e(TAG, "💾 Inserting into SQLite...");
        long id = db.insert(TABLE_UPLOAD_QUEUE, null, values);

        if (id > 0) {
            Log.e(TAG, "✅✅✅ File saved to database successfully!");
            Log.e(TAG, "   Database ID: " + id);
            Log.e(TAG, "   Status: " + STATUS_PENDING);
            Log.e(TAG, "   Retry count: 0");
            Log.e(TAG, "   Created at: " + new java.text.SimpleDateFormat("HH:mm:ss.SSS", java.util.Locale.US).format(new java.util.Date(currentTime)));
        } else {
            Log.e(TAG, "❌❌❌ FAILED to save file to database!");
            Log.e(TAG, "   Return ID: " + id);
        }
        Log.e(TAG, "");

        return id;
    }

    /**
     * الحصول على ملف بواسطة ID
     */
    public UploadItem getFileById(long id) {
        SQLiteDatabase db = this.getReadableDatabase();

        Cursor cursor = db.query(
            TABLE_UPLOAD_QUEUE,
            null,
            COLUMN_ID + " = ?",
            new String[]{String.valueOf(id)},
            null,
            null,
            null
        );

        UploadItem item = null;
        if (cursor.moveToFirst()) {
            item = cursorToUploadItem(cursor);
        }

        cursor.close();
        return item;
    }

    public UploadItem getFileByName(String fileName) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.query(TABLE_UPLOAD_QUEUE, null,
                COLUMN_FILE_NAME + " = ?", new String[]{fileName},
                null, null, null);

        UploadItem item = null;
        if (cursor != null && cursor.moveToFirst()) {
            item = cursorToUploadItem(cursor);
            cursor.close();
        }
        return item;
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
     * الحصول على جميع الملفات التي تتطلب فحص حالتها من السيرفر (معالجة، فاشلة، معلقة)
     */
    public List<UploadItem> getFilesForVerification() {
        List<UploadItem> files = new ArrayList<>();
        SQLiteDatabase db = this.getReadableDatabase();

        Cursor cursor = db.query(
            TABLE_UPLOAD_QUEUE,
            null,
            COLUMN_STATUS + " IN (?, ?, ?)",
            new String[]{STATUS_PROCESSING_SERVER, STATUS_FAILED, STATUS_PENDING},
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
        Log.d(TAG, "🔍 تم جلب " + files.size() + " ملف لفحص حالتهم من السيرفر.");

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

    // ═══════════════════════════════════════════════════════════════════
    // 🔒 حجز ذرّي + مُحرِّر الأقفال العالقة
    //
    // المشكلة التي تعالجها هذه الكتلة (السبب الجذري الأول لتعليق الملفات):
    //  - getNextPendingFile() ثم updateFileStatus() عمليتان منفصلتان، فيمكن
    //    لعاملين متزامنين (وهناك ٥ نقاط دخول تشغّل ChunkedUploadWorker) أن
    //    يحجزا نفس الصف ويرفعا نفس الملف مرتين.
    //  - إذا مات العامل وهو في حالة 'uploading' (قتل WorkManager عند ١٠ دقائق،
    //    أو سقوط العملية) يبقى الصف 'uploading' إلى الأبد: getNextPendingFile
    //    لا تراه لأنها تصفّي على 'pending' فقط، بينما getPendingFilesCount
    //    تعدّه — فيظهر للمستخدم "ملف معلق" لا يتحرك أبداً.
    // ═══════════════════════════════════════════════════════════════════

    /** يُعتبر الصف عالقاً إذا لم يُلمس updated_at خلال هذه المدة. */
    public static final long STALE_UPLOAD_TIMEOUT_MS = 10 * 60 * 1000L;      // ١٠ دقائق
    /** مهلة انتظار المعالجة على الخادم قبل إعادة الملف للطابور. */
    public static final long STALE_PROCESSING_TIMEOUT_MS = 45 * 60 * 1000L;  // ٤٥ دقيقة

    /**
     * يحجز أول ملف معلق ذرّياً: ينقله من pending إلى uploading داخل معاملة
     * واحدة، فلا يمكن لعاملَين أن يحصلا على نفس الصف.
     * يُرجع الصف المحجوز أو null إذا لم يبقَ شيء.
     */
    public UploadItem claimNextPendingFile() {
        SQLiteDatabase db = this.getWritableDatabase();
        UploadItem claimed = null;

        db.beginTransaction();
        try {
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

            UploadItem candidate = null;
            if (cursor.moveToFirst()) {
                candidate = cursorToUploadItem(cursor);
            }
            cursor.close();

            if (candidate != null) {
                ContentValues values = new ContentValues();
                values.put(COLUMN_STATUS, STATUS_UPLOADING);
                values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

                // شرط الحالة في WHERE هو ما يجعل الحجز ذرّياً: إن سبقنا عامل
                // آخر إلى الصف فلن يتغيّر أي سطر ونعود بلا شيء.
                int rows = db.update(
                    TABLE_UPLOAD_QUEUE,
                    values,
                    COLUMN_ID + " = ? AND " + COLUMN_STATUS + " = ?",
                    new String[]{String.valueOf(candidate.id), STATUS_PENDING}
                );

                if (rows == 1) {
                    candidate.status = STATUS_UPLOADING;
                    claimed = candidate;
                }
            }

            db.setTransactionSuccessful();
        } catch (Exception e) {
            Log.e(TAG, "claimNextPendingFile failed", e);
        } finally {
            db.endTransaction();
        }

        return claimed;
    }

    /**
     * نبضة حياة: تُحدِّث updated_at للصف الجاري رفعه حتى لا يعتبره المُحرِّر
     * عالقاً أثناء رفع فيديو كبير يستغرق وقتاً طويلاً.
     */
    public void touchUpload(long id) {
        try {
            SQLiteDatabase db = this.getWritableDatabase();
            ContentValues values = new ContentValues();
            values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());
            db.update(TABLE_UPLOAD_QUEUE, values, COLUMN_ID + " = ?", new String[]{String.valueOf(id)});
        } catch (Exception e) {
            Log.w(TAG, "touchUpload failed for " + id, e);
        }
    }

    /**
     * يُعيد إلى الطابور كل صف عالق في 'uploading' لم تصله نبضة منذ مدة.
     * هذه هي شبكة الأمان الوحيدة ضد موت العامل في منتصف الرفع.
     *
     * @return عدد الصفوف المُحرَّرة
     */
    public int reclaimStaleUploads() {
        return reclaimStale(STATUS_UPLOADING, STALE_UPLOAD_TIMEOUT_MS, "استُؤنف بعد توقف الرفع");
    }

    /**
     * يُعيد إلى الطابور الملفات التي بقيت 'processing_server' مدة طويلة.
     * الخادم قد يموت أثناء رفع rclone دون أن يُخطر الجهاز إطلاقاً، فبدون هذا
     * تبقى الملفات معلّقة للأبد بانتظار إشعار لن يأتي.
     *
     * @return عدد الصفوف المُحرَّرة
     */
    public int reclaimStaleProcessing() {
        return reclaimStale(STATUS_PROCESSING_SERVER, STALE_PROCESSING_TIMEOUT_MS, "انتهت مهلة المعالجة على الخادم");
    }

    private int reclaimStale(String status, long timeoutMs, String reason) {
        try {
            SQLiteDatabase db = this.getWritableDatabase();
            long cutoff = System.currentTimeMillis() - timeoutMs;

            ContentValues values = new ContentValues();
            values.put(COLUMN_STATUS, STATUS_PENDING);
            values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());
            values.put(COLUMN_ERROR_MESSAGE, reason);

            int rows = db.update(
                TABLE_UPLOAD_QUEUE,
                values,
                COLUMN_STATUS + " = ? AND " + COLUMN_UPDATED_AT + " < ?",
                new String[]{status, String.valueOf(cutoff)}
            );

            if (rows > 0) {
                Log.w(TAG, "♻️ حُرِّر " + rows + " ملف عالق في '" + status + "' وأُعيد للطابور");
            }
            return rows;
        } catch (Exception e) {
            Log.e(TAG, "reclaimStale(" + status + ") failed", e);
            return 0;
        }
    }

    /**
     * تحديث حالة الملف
     */
    public void updateFileStatus(long id, String status, String errorMessage) {
        Log.e(TAG, "✅✅✅ updateFileStatus() CALLED");
        Log.e(TAG, "   File ID: " + id);
        Log.e(TAG, "   New Status: " + status);
        Log.e(TAG, "   Error Message: " + errorMessage);

        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();

        values.put(COLUMN_STATUS, status);
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        if (errorMessage != null) {
            values.put(COLUMN_ERROR_MESSAGE, errorMessage);
        }

        int rowsAffected = db.update(TABLE_UPLOAD_QUEUE, values, COLUMN_ID + " = ?", new String[]{String.valueOf(id)});
        Log.e(TAG, "✅✅✅ updateFileStatus() UPDATE EXECUTED");
        Log.e(TAG, "   Rows affected: " + rowsAffected);

        if (rowsAffected == 0) {
            Log.e(TAG, "   ❌❌❌ WARNING: No rows were updated! File ID " + id + " might not exist!");
        } else if (rowsAffected > 1) {
            Log.e(TAG, "   ⚠️ WARNING: Multiple rows updated ("+rowsAffected+")! This should not happen!");
        } else {
            Log.e(TAG, "   ✅ SUCCESS: 1 row updated correctly");
        }
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

    // ═══════════════════════════════════════════════════════════════════
    // 🔄 Circuit Breaker Logic - Exponential Backoff
    // ═══════════════════════════════════════════════════════════════════

    private static final int MAX_RETRY_ATTEMPTS = 3;
    private static final long BASE_RETRY_DELAY_MS = 60_000; // 1 minute
    private static final long MAX_RETRY_DELAY_MS = 3_600_000; // 60 minutes

    /**
     * التحقق من أهلية الملف لإعادة المحاولة
     * @param item الملف المطلوب فحصه
     * @return true إذا كان يجب إعادة المحاولة، false إذا وصل للحد الأقصى
     */
    public boolean shouldRetry(UploadItem item) {
        // فحص عدد المحاولات
        if (item.retryCount >= MAX_RETRY_ATTEMPTS) {
            Log.w(TAG, "❌ Circuit Breaker: File ID=" + item.id + " reached max retries (" + MAX_RETRY_ATTEMPTS + ")");
            return false;
        }

        // فحص الشبكة
        if (!isNetworkAvailable()) {
            Log.w(TAG, "⚠️ Circuit Breaker: No network available - skipping retry");
            return false;
        }

        // ✨ NEW: السماح بالمحاولة الأولى فوراً (بدون انتظار)
        if (item.retryCount == 0) {
            Log.d(TAG, "🚀 Circuit Breaker: File ID=" + item.id + " first attempt - no delay");
            return true;
        }

        // فحص وقت الانتظار (exponential backoff) فقط للإعادات
        long timeSinceLastAttempt = System.currentTimeMillis() - item.updatedAt;
        long requiredDelay = calculateRetryDelay(item.retryCount);

        if (timeSinceLastAttempt < requiredDelay) {
            long remainingWait = (requiredDelay - timeSinceLastAttempt) / 1000; // seconds
            Log.w(TAG, "⏳ Circuit Breaker: File ID=" + item.id + " must wait " + remainingWait + "s before retry");
            return false;
        }

        Log.d(TAG, "✅ Circuit Breaker: File ID=" + item.id + " eligible for retry (" + (item.retryCount + 1) + "/" + MAX_RETRY_ATTEMPTS + ")");
        return true;
    }

    /**
     * حساب وقت الانتظار قبل إعادة المحاولة (Exponential Backoff)
     * @param retryCount عدد المحاولات السابقة
     * @return وقت الانتظار بالميلي ثانية
     */
    public long calculateRetryDelay(int retryCount) {
        // Exponential backoff: 1m, 2m, 4m, 8m, 16m, ... max 60m
        long delay = BASE_RETRY_DELAY_MS * (1L << retryCount); // 2^retryCount
        return Math.min(delay, MAX_RETRY_DELAY_MS);
    }

    /**
     * التحقق من توفر الشبكة
     * @return true إذا كانت الشبكة متاحة
     */
    private boolean isNetworkAvailable() {
        try {
            android.net.ConnectivityManager cm =
                (android.net.ConnectivityManager) context.getSystemService(android.content.Context.CONNECTIVITY_SERVICE);

            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.M) {
                android.net.Network network = cm.getActiveNetwork();
                if (network == null) return false;

                android.net.NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
                return capabilities != null &&
                       capabilities.hasCapability(android.net.NetworkCapabilities.NET_CAPABILITY_INTERNET);
            } else {
                android.net.NetworkInfo networkInfo = cm.getActiveNetworkInfo();
                return networkInfo != null && networkInfo.isConnected();
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ Error checking network: " + e.getMessage());
            return false; // Assume no network on error
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // End Circuit Breaker Logic
    // ═══════════════════════════════════════════════════════════════════

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

    /**
     * إعادة تعيين الملفات المعلقة قيد المعالجة لتصبح في طابور الرفع
     */
    public void resetProcessingFiles() {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put(COLUMN_STATUS, STATUS_PENDING);
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        int updated = db.update(TABLE_UPLOAD_QUEUE, values,
                               COLUMN_STATUS + " = ?",
                               new String[]{STATUS_PROCESSING_SERVER});

        Log.d(TAG, "تمت إعادة تعيين " + updated + " ملف عالق في السيرفر");
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
        int authTokenIdx = cursor.getColumnIndex(COLUMN_AUTH_TOKEN);
        int associationIdx = cursor.getColumnIndex(COLUMN_ASSOCIATION_NAME);
        int personIdx = cursor.getColumnIndex(COLUMN_PERSON_NAME);
        item.authToken = (authTokenIdx >= 0) ? cursor.getString(authTokenIdx) : "";
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
        public String authToken;
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
