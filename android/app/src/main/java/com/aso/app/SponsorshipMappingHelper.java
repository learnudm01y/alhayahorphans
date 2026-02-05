package com.aso.app;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import java.util.HashMap;
import java.util.Map;

/**
 * قاعدة بيانات محلية لحفظ mapping بين sponsorship_id وأسماء الجمعيات والمكفولين
 * JavaScript يستدعي updateMapping() مرة واحدة عند تحميل البيانات من IndexedDB
 * Java يستخدم getMapping() عند رفع الملفات
 */
public class SponsorshipMappingHelper extends SQLiteOpenHelper {
    private static final String TAG = "SponsorshipMapping";
    private static final String DATABASE_NAME = "sponsorship_mapping.db";
    private static final int DATABASE_VERSION = 1;

    private static final String TABLE_MAPPING = "sponsorship_mapping";
    private static final String COLUMN_SPONSORSHIP_ID = "sponsorship_id";
    private static final String COLUMN_ASSOCIATION_NAME = "association_name";
    private static final String COLUMN_PERSON_NAME = "person_name";
    private static final String COLUMN_UPDATED_AT = "updated_at";

    private static SponsorshipMappingHelper instance;

    public static synchronized SponsorshipMappingHelper getInstance(Context context) {
        if (instance == null) {
            instance = new SponsorshipMappingHelper(context.getApplicationContext());
        }
        return instance;
    }

    private SponsorshipMappingHelper(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        String CREATE_TABLE = "CREATE TABLE " + TABLE_MAPPING + " ("
                + COLUMN_SPONSORSHIP_ID + " INTEGER PRIMARY KEY, "
                + COLUMN_ASSOCIATION_NAME + " TEXT NOT NULL, "
                + COLUMN_PERSON_NAME + " TEXT NOT NULL, "
                + COLUMN_UPDATED_AT + " INTEGER NOT NULL"
                + ")";
        db.execSQL(CREATE_TABLE);
        Log.d(TAG, "✅ Sponsorship mapping table created");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        db.execSQL("DROP TABLE IF EXISTS " + TABLE_MAPPING);
        onCreate(db);
    }

    /**
     * حفظ أو تحديث mapping لكفالة واحدة
     */
    public void saveMapping(int sponsorshipId, String associationName, String personName) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();

        values.put(COLUMN_SPONSORSHIP_ID, sponsorshipId);
        values.put(COLUMN_ASSOCIATION_NAME, associationName != null ? associationName : "General");
        values.put(COLUMN_PERSON_NAME, personName != null ? personName : "unknown");
        values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

        db.insertWithOnConflict(TABLE_MAPPING, null, values, SQLiteDatabase.CONFLICT_REPLACE);

        Log.d(TAG, "✅ Saved mapping: sponsorshipId=" + sponsorshipId +
                " → Association='" + associationName + "', Person='" + personName + "'");
    }

    /**
     * حفظ mappings متعددة دفعة واحدة (batch)
     */
    public void saveBatchMappings(Map<Integer, MappingData> mappings) {
        SQLiteDatabase db = this.getWritableDatabase();
        db.beginTransaction();

        try {
            for (Map.Entry<Integer, MappingData> entry : mappings.entrySet()) {
                ContentValues values = new ContentValues();
                values.put(COLUMN_SPONSORSHIP_ID, entry.getKey());
                values.put(COLUMN_ASSOCIATION_NAME, entry.getValue().associationName);
                values.put(COLUMN_PERSON_NAME, entry.getValue().personName);
                values.put(COLUMN_UPDATED_AT, System.currentTimeMillis());

                db.insertWithOnConflict(TABLE_MAPPING, null, values, SQLiteDatabase.CONFLICT_REPLACE);
            }

            db.setTransactionSuccessful();
            Log.d(TAG, "✅ Batch saved " + mappings.size() + " mappings");
        } finally {
            db.endTransaction();
        }
    }

    /**
     * الحصول على mapping لكفالة معينة
     */
    public MappingData getMapping(int sponsorshipId) {
        SQLiteDatabase db = this.getReadableDatabase();

        Cursor cursor = db.query(
            TABLE_MAPPING,
            new String[]{COLUMN_ASSOCIATION_NAME, COLUMN_PERSON_NAME},
            COLUMN_SPONSORSHIP_ID + " = ?",
            new String[]{String.valueOf(sponsorshipId)},
            null, null, null
        );

        MappingData data = null;
        if (cursor.moveToFirst()) {
            String association = cursor.getString(0);
            String person = cursor.getString(1);
            data = new MappingData(association, person);

            Log.d(TAG, "✅ Found mapping for sponsorshipId=" + sponsorshipId +
                    " → Association='" + association + "', Person='" + person + "'");
        } else {
            Log.w(TAG, "⚠️ No mapping found for sponsorshipId=" + sponsorshipId);
        }

        cursor.close();
        return data;
    }

    /**
     * الحصول على جميع mappings (للتصدير أو التصحيح)
     */
    public Map<Integer, MappingData> getAllMappings() {
        Map<Integer, MappingData> result = new HashMap<>();
        SQLiteDatabase db = this.getReadableDatabase();

        Cursor cursor = db.query(TABLE_MAPPING, null, null, null, null, null, null);

        if (cursor.moveToFirst()) {
            do {
                int id = cursor.getInt(cursor.getColumnIndexOrThrow(COLUMN_SPONSORSHIP_ID));
                String association = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_ASSOCIATION_NAME));
                String person = cursor.getString(cursor.getColumnIndexOrThrow(COLUMN_PERSON_NAME));

                result.put(id, new MappingData(association, person));
            } while (cursor.moveToNext());
        }

        cursor.close();
        Log.d(TAG, "📊 Total mappings in database: " + result.size());
        return result;
    }

    /**
     * حذف mapping معين
     */
    public void deleteMapping(int sponsorshipId) {
        SQLiteDatabase db = this.getWritableDatabase();
        db.delete(TABLE_MAPPING, COLUMN_SPONSORSHIP_ID + " = ?",
                new String[]{String.valueOf(sponsorshipId)});
        Log.d(TAG, "🗑️ Deleted mapping for sponsorshipId=" + sponsorshipId);
    }

    /**
     * حذف جميع mappings
     */
    public void clearAll() {
        SQLiteDatabase db = this.getWritableDatabase();
        db.delete(TABLE_MAPPING, null, null);
        Log.d(TAG, "🗑️ Cleared all mappings");
    }

    /**
     * عدد mappings المحفوظة
     */
    public int getCount() {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT COUNT(*) FROM " + TABLE_MAPPING, null);
        cursor.moveToFirst();
        int count = cursor.getInt(0);
        cursor.close();
        return count;
    }

    /**
     * Data class لحفظ بيانات mapping
     */
    public static class MappingData {
        public final String associationName;
        public final String personName;

        public MappingData(String associationName, String personName) {
            this.associationName = associationName != null ? associationName : "General";
            this.personName = personName != null ? personName : "unknown";
        }
    }
}
