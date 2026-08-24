package com.aso.app;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.Set;
import java.util.HashSet;

/**
 * قاعدة بيانات Native أصلية لتخزين الكفالات كاملة كبديل عن IndexedDB
 */
public class SponsorshipsDatabaseHelper extends SQLiteOpenHelper {
    private static final String TAG = "SponsorshipsDB";
    private static final String DATABASE_NAME = "sponsorships_data.db";
    private static final int DATABASE_VERSION = 3;

    private static final String TABLE_SPONSORSHIPS = "sponsorships";
    private static final String COLUMN_ID = "id";
    private static final String COLUMN_JSON_PAYLOAD = "json_payload";
    private static final String COLUMN_SEARCH_TEXT = "search_text"; // للفهرسة والبحث السريع
    private static final String COLUMN_SPONSOR_ID = "sponsor_id";
    private static final String COLUMN_STATUS_ID = "status_id";
    private static final String COLUMN_UPDATED_AT = "updated_at";

    private static SponsorshipsDatabaseHelper instance;

    public static synchronized SponsorshipsDatabaseHelper getInstance(Context context) {
        if (instance == null) {
            instance = new SponsorshipsDatabaseHelper(context.getApplicationContext());
        }
        return instance;
    }

    private SponsorshipsDatabaseHelper(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        String CREATE_TABLE = "CREATE TABLE " + TABLE_SPONSORSHIPS + " ("
                + COLUMN_ID + " INTEGER PRIMARY KEY, "
                + COLUMN_JSON_PAYLOAD + " TEXT NOT NULL, "
                + COLUMN_SEARCH_TEXT + " TEXT, "
                + COLUMN_SPONSOR_ID + " INTEGER, "
                + COLUMN_STATUS_ID + " INTEGER, "
                + COLUMN_UPDATED_AT + " INTEGER NOT NULL"
                + ")";
        db.execSQL(CREATE_TABLE);
        
        // إنشاء فهارس لتسريع البحث
        db.execSQL("CREATE INDEX idx_search_text ON " + TABLE_SPONSORSHIPS + "(" + COLUMN_SEARCH_TEXT + ")");
        db.execSQL("CREATE INDEX idx_sponsor_id ON " + TABLE_SPONSORSHIPS + "(" + COLUMN_SPONSOR_ID + ")");
        db.execSQL("CREATE INDEX idx_status_id ON " + TABLE_SPONSORSHIPS + "(" + COLUMN_STATUS_ID + ")");
        Log.d(TAG, "✅ Sponsorships table created");
        
        // جدول البيانات الأساسية (الجمعيات، الخ)
        db.execSQL("CREATE TABLE IF NOT EXISTS app_lookups (type TEXT PRIMARY KEY, json_payload TEXT NOT NULL)");
        Log.d(TAG, "✅ app_lookups table created");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        if (oldVersion < 3) {
            try {
                Cursor cur = db.rawQuery("SELECT " + COLUMN_ID + ", " + COLUMN_JSON_PAYLOAD + " FROM " + TABLE_SPONSORSHIPS, null);
                if (cur.moveToFirst()) {
                    do {
                        int id = cur.getInt(0);
                        String payload = cur.getString(1);
                        try {
                            JSONObject json = new JSONObject(payload);
                            StringBuilder st = new StringBuilder();
                            st.append(json.optString("first_name", "")).append(" ");
                            st.append(json.optString("last_name", "")).append(" ");
                            st.append(json.optString("identity_number", "")).append(" ");
                            st.append(json.optString("guardian_identity_number", "")).append(" ");
                            st.append(json.optString("orphan_name", "")).append(" ");
                            ContentValues cv = new ContentValues();
                            cv.put(COLUMN_SEARCH_TEXT, st.toString().toLowerCase());
                            db.update(TABLE_SPONSORSHIPS, cv, COLUMN_ID + "=?", new String[]{String.valueOf(id)});
                        } catch (Exception e) {}
                    } while (cur.moveToNext());
                }
                cur.close();
            } catch (Exception e) { Log.e(TAG, "Upgrade v3 failed", e); }
            if (oldVersion < 2) {
                db.execSQL("CREATE TABLE IF NOT EXISTS app_lookups (type TEXT PRIMARY KEY, json_payload TEXT NOT NULL)");
            }
        } else {
            db.execSQL("DROP TABLE IF EXISTS " + TABLE_SPONSORSHIPS);
            db.execSQL("DROP TABLE IF EXISTS app_lookups");
            onCreate(db);
        }
    }

    /**
     * حفظ قائمة من البيانات الأساسية كـ JSON
     */
    public void saveLookup(String type, String jsonPayload) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();
        values.put("type", type);
        values.put("json_payload", jsonPayload);
        db.insertWithOnConflict("app_lookups", null, values, SQLiteDatabase.CONFLICT_REPLACE);
    }

    /**
     * استرجاع البيانات الأساسية
     */
    public String getLookup(String type) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT json_payload FROM app_lookups WHERE type = ?", new String[]{type});
        String result = null;
        if (cursor.moveToFirst()) {
            result = cursor.getString(0);
        }
        cursor.close();
        return result;
    }

    /**
     * حفظ كفالة واحدة (إدخال أو تحديث)
     */
    public void saveSponsorship(int id, String jsonPayload) {
        SQLiteDatabase db = this.getWritableDatabase();
        ContentValues values = new ContentValues();

        values.put(COLUMN_ID, id);
        values.put(COLUMN_JSON_PAYLOAD, jsonPayload);
        
        // استخراج نصوص البحث من الـ JSON لتسهيل عملية الـ Search - شامل كل الجداول
        try {
            JSONObject json = new JSONObject(jsonPayload);
            StringBuilder searchText = new StringBuilder();
            searchText.append(json.optString("first_name", "")).append(" ");
            searchText.append(json.optString("second_name", "")).append(" ");
            searchText.append(json.optString("third_name", "")).append(" ");
            searchText.append(json.optString("last_name", "")).append(" ");
            searchText.append(json.optString("identity_number", "")).append(" ");
            searchText.append(json.optString("guardian_identity_number", "")).append(" ");
            searchText.append(json.optString("orphan_name", "")).append(" ");
            searchText.append(json.optString("guardian_name", "")).append(" ");
            searchText.append(json.optString("association_name", "")).append(" ");
            searchText.append(json.optString("sponsor_name", "")).append(" ");
            searchText.append(json.optString("internal_file_number", "")).append(" ");
            searchText.append(json.optString("external_file_number", "")).append(" ");
            searchText.append(json.optString("relation_id_number", "")).append(" ");
            // بيانات المعيل من data
            if (json.has("guardian_data") && !json.isNull("guardian_data")) {
                JSONObject g = json.optJSONObject("guardian_data");
                if (g != null) {
                    searchText.append(g.optString("data_first_name", "")).append(" ");
                    searchText.append(g.optString("data_family_name", "")).append(" ");
                    searchText.append(g.optString("data_id_number", "")).append(" ");
                }
            }
            // أفراد الأسرة
            if (json.has("family_members") && !json.isNull("family_members")) {
                org.json.JSONArray fam = json.optJSONArray("family_members");
                if (fam != null) for (int i=0;i<fam.length();i++) {
                    JSONObject m = fam.optJSONObject(i);
                    if (m==null) continue;
                    searchText.append(m.optString("first_name","")).append(" ");
                    searchText.append(m.optString("last_name","")).append(" ");
                    searchText.append(m.optString("person_id","")).append(" ");
                }
            }
            // المتوفون
            if (json.has("deceased_data") && !json.isNull("deceased_data")) {
                JSONObject d = json.optJSONObject("deceased_data");
                if (d != null) {
                    searchText.append(d.optString("father_first_name","")).append(" ");
                    searchText.append(d.optString("father_last_name","")).append(" ");
                    searchText.append(d.optString("father_id","")).append(" ");
                    searchText.append(d.optString("mother_first_name","")).append(" ");
                    searchText.append(d.optString("mother_last_name","")).append(" ");
                    searchText.append(d.optString("mother_id","")).append(" ");
                }
            }
            // الحسابات البنكية
            if (json.has("bank_accounts") && !json.isNull("bank_accounts")) {
                org.json.JSONArray banks = json.optJSONArray("bank_accounts");
                if (banks != null) for (int i=0;i<banks.length();i++) {
                    JSONObject b = banks.optJSONObject(i);
                    if (b==null) continue;
                    searchText.append(b.optString("bank_name","")).append(" ");
                    searchText.append(b.optString("re_guardian_name","")).append(" ");
                    searchText.append(b.optString("person_owner_identity_number","")).append(" ");
                }
            }
            values.put(COLUMN_SEARCH_TEXT, searchText.toString().toLowerCase());
            
            values.put(COLUMN_SPONSOR_ID, json.optInt("sponsor_id", 0));
            values.put(COLUMN_STATUS_ID, json.optInt("sponsorship_status_id", 0));
        } catch (Exception e) {
            values.put(COLUMN_SEARCH_TEXT, "");
            values.put(COLUMN_SPONSOR_ID, 0);
            values.put(COLUMN_STATUS_ID, 0);
        }

        long updatedAtMillis = System.currentTimeMillis();
        try {
            JSONObject json = new JSONObject(jsonPayload);
            String updatedAtStr = json.optString("updated_at", "");
            if (!updatedAtStr.isEmpty()) {
                try {
                    java.text.SimpleDateFormat sdf;
                    if (updatedAtStr.contains("T")) {
                        sdf = new java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSSSSS'Z'", java.util.Locale.US);
                        sdf.setTimeZone(java.util.TimeZone.getTimeZone("UTC"));
                    } else {
                        sdf = new java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.US);
                    }
                    updatedAtMillis = sdf.parse(updatedAtStr).getTime();
                } catch (Exception ex) {
                    try {
                        java.text.SimpleDateFormat sdf2 = new java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", java.util.Locale.US);
                        sdf2.setTimeZone(java.util.TimeZone.getTimeZone("UTC"));
                        updatedAtMillis = sdf2.parse(updatedAtStr).getTime();
                    } catch (Exception ex2) {
                        // ignore
                    }
                }
            }
        } catch (Exception e) {}

        values.put(COLUMN_UPDATED_AT, updatedAtMillis);

        db.insertWithOnConflict(TABLE_SPONSORSHIPS, null, values, SQLiteDatabase.CONFLICT_REPLACE);
    }

    /**
     * حفظ قائمة كفالات (Batch Insert) لسرعة المزامنة
     */
    public void saveBatchSponsorships(JSONArray sponsorships, java.util.Set<Integer> pendingIds) {
        SQLiteDatabase db = this.getWritableDatabase();
        db.beginTransaction();
        int skipped = 0;
        try {
            for (int i = 0; i < sponsorships.length(); i++) {
                JSONObject obj = sponsorships.getJSONObject(i);
                int id = obj.getInt("id");
                
                // حماية البيانات: تجاهل بيانات السيرفر إذا كان هناك تعديل محلي ينتظر الرفع
                if (pendingIds != null && pendingIds.contains(id)) {
                    skipped++;
                    continue;
                }
                
                saveSponsorship(id, obj.toString());
            }
            db.setTransactionSuccessful();
            Log.d(TAG, "✅ Saved " + (sponsorships.length() - skipped) + " sponsorships. Skipped " + skipped + " (pending local edits).");
        } catch (Exception e) {
            Log.e(TAG, "❌ Error saving batch sponsorships", e);
        } finally {
            db.endTransaction();
        }
    }

    /**
     * استرجاع صفحة من الكفالات مع إمكانية الفلترة
     */
    public JSONArray getSponsorships(int page, int limit, int sponsorId, int statusId, String search) {
        JSONArray result = new JSONArray();
        SQLiteDatabase db = this.getReadableDatabase();
        
        int offset = (page - 1) * limit;
        
        StringBuilder queryBuilder = new StringBuilder("SELECT " + COLUMN_JSON_PAYLOAD + " FROM " + TABLE_SPONSORSHIPS + " WHERE 1=1 ");
        java.util.ArrayList<String> argsList = new java.util.ArrayList<>();
        
        if (sponsorId > 0) {
            queryBuilder.append(" AND " + COLUMN_SPONSOR_ID + " = ? ");
            argsList.add(String.valueOf(sponsorId));
        }
        if (statusId > 0) {
            queryBuilder.append(" AND " + COLUMN_STATUS_ID + " = ? ");
            argsList.add(String.valueOf(statusId));
        }
        if (search != null && !search.isEmpty()) {
            queryBuilder.append(" AND " + COLUMN_SEARCH_TEXT + " LIKE ? ");
            argsList.add("%" + search.toLowerCase() + "%");
        }
        
        queryBuilder.append(" ORDER BY " + COLUMN_ID + " ASC LIMIT ? OFFSET ?");
        argsList.add(String.valueOf(limit));
        argsList.add(String.valueOf(offset));
        
        String[] args = argsList.toArray(new String[0]);
                     
        Cursor cursor = db.rawQuery(queryBuilder.toString(), args);
        
        try {
            if (cursor.moveToFirst()) {
                do {
                    result.put(new JSONObject(cursor.getString(0)));
                } while (cursor.moveToNext());
            }
        } catch (Exception e) {
            Log.e(TAG, "Error parsing JSON", e);
        } finally {
            cursor.close();
        }
        
        return result;
    }

    /**
     * بحث في الكفالات - بالكلمات المنفصلة (كل كلمة يجب أن تظهر)
     */
    public JSONArray searchSponsorships(String searchTerm, int page, int limit) {
        JSONArray result = new JSONArray();
        SQLiteDatabase db = this.getReadableDatabase();

        int offset = (page - 1) * limit;
        String[] tokens = searchTerm.toLowerCase().trim().split("\\s+");
        StringBuilder where = new StringBuilder();
        java.util.ArrayList<String> argsList = new java.util.ArrayList<>();
        for (String t : tokens) {
            if (t.isEmpty()) continue;
            if (where.length() > 0) where.append(" AND ");
            where.append(COLUMN_SEARCH_TEXT).append(" LIKE ?");
            argsList.add("%" + t + "%");
        }
        if (where.length() == 0) return result;
        String query = "SELECT " + COLUMN_JSON_PAYLOAD + " FROM " + TABLE_SPONSORSHIPS
                     + " WHERE " + where.toString()
                     + " ORDER BY " + COLUMN_ID + " ASC LIMIT ? OFFSET ?";
        argsList.add(String.valueOf(limit));
        argsList.add(String.valueOf(offset));

        Cursor cursor = db.rawQuery(query, argsList.toArray(new String[0]));

        try {
            if (cursor.moveToFirst()) {
                do {
                    result.put(new JSONObject(cursor.getString(0)));
                } while (cursor.moveToNext());
            }
        } catch (Exception e) {
            Log.e(TAG, "Error parsing JSON", e);
        } finally {
            cursor.close();
        }

        return result;
    }


    /**
     * البحث برقم ملف التسجيل (relation_id_number / file_id_number) داخل json_payload
     */
    public JSONArray findByRelationId(String rel) {
        JSONArray result = new JSONArray();
        if (rel == null || rel.isEmpty()) return result;
        SQLiteDatabase db = this.getReadableDatabase();
        String digits = rel.replaceFirst("^0+", "");
        if (digits.isEmpty()) digits = rel;
        Cursor cursor = db.rawQuery("SELECT " + COLUMN_JSON_PAYLOAD + " FROM " + TABLE_SPONSORSHIPS
                + " WHERE " + COLUMN_JSON_PAYLOAD + " LIKE ? LIMIT 200",
                new String[]{"%" + digits + "%"});
        try {
            if (cursor.moveToFirst()) {
                do {
                    try {
                        JSONObject o = new JSONObject(cursor.getString(0));
                        String r = String.valueOf(o.optString("relation_id_number", "")).replaceFirst("^0+", "");
                        String f = String.valueOf(o.optString("file_id_number", "")).replaceFirst("^0+", "");
                        String i = String.valueOf(o.optString("internal_file_number", "")).replaceFirst("^0+", "");
                        if (r.equals(digits) || f.equals(digits) || i.equals(digits)) {
                            result.put(o);
                        }
                    } catch (Exception ignored) {}
                } while (cursor.moveToNext());
            }
        } finally { cursor.close(); }
        return result;
    }

    /**
     * استرجاع كفالة واحدة
     */
    public String getSponsorship(int id) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.query(TABLE_SPONSORSHIPS, new String[]{COLUMN_JSON_PAYLOAD},
                COLUMN_ID + "=?", new String[]{String.valueOf(id)}, null, null, null);
                
        String json = null;
        if (cursor.moveToFirst()) {
            json = cursor.getString(0);
        }
        cursor.close();
        return json;
    }

    /**
     * حذف جميع الكفالات
     */
    public void clearAll() {
        SQLiteDatabase db = this.getWritableDatabase();
        db.execSQL("DELETE FROM " + TABLE_SPONSORSHIPS);
        Log.d(TAG, "🗑️ Cleared all sponsorships");
    }

    public int getCount() {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT COUNT(*) FROM " + TABLE_SPONSORSHIPS, null);
        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();
        return count;
    }

    public Set<Integer> getExistingSponsorshipIds() {
        Set<Integer> ids = new HashSet<>();
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT " + COLUMN_ID + " FROM " + TABLE_SPONSORSHIPS, null);
        if (cursor.moveToFirst()) {
            do {
                ids.add(cursor.getInt(0));
            } while (cursor.moveToNext());
        }
        cursor.close();
        return ids;
    }

    /**
     * Get the latest updated_at timestamp for incremental sync.
     */
    public String getMaxUpdatedAt() {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT MAX(updated_at) FROM " + TABLE_SPONSORSHIPS, null);
        String maxDate = null;
        if (cursor.moveToFirst()) {
            long maxTimestamp = cursor.getLong(0);
            if (maxTimestamp > 0) {
                java.text.SimpleDateFormat sdf = new java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.US);
                maxDate = sdf.format(new java.util.Date(maxTimestamp));
            }
        }
        cursor.close();
        return maxDate;
    }

    /**
     * Prune deleted sponsorships by keeping only those in the validIds array
     */
    public void syncValidSponsorships(org.json.JSONArray validIds) {
        if (validIds == null || validIds.length() == 0) return;
        
        SQLiteDatabase db = this.getWritableDatabase();
        try {
            // Get all current local IDs
            Cursor c = db.rawQuery("SELECT " + COLUMN_ID + " FROM " + TABLE_SPONSORSHIPS, null);
            java.util.Set<Integer> localIds = new java.util.HashSet<>();
            if (c.moveToFirst()) {
                do {
                    localIds.add(c.getInt(0));
                } while (c.moveToNext());
            }
            c.close();
            
            // Remove valid IDs from our localIds set
            for (int i = 0; i < validIds.length(); i++) {
                localIds.remove(validIds.getInt(i));
            }
            
            // Delete the remaining ones (these were deleted on the server)
            if (!localIds.isEmpty()) {
                db.beginTransaction();
                try {
                    for (int idToDelete : localIds) {
                        db.delete(TABLE_SPONSORSHIPS, COLUMN_ID + " = ?", new String[]{String.valueOf(idToDelete)});
                    }
                    db.setTransactionSuccessful();
                    Log.d(TAG, "✅ Pruned " + localIds.size() + " deleted sponsorships from local database.");
                } finally {
                    db.endTransaction();
                }
            } else {
                Log.d(TAG, "✅ No deleted sponsorships found to prune.");
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ Error pruning deleted sponsorships", e);
        }
    }
}
