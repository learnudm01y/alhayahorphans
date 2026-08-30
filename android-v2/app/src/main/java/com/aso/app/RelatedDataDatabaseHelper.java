package com.aso.app;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import org.json.JSONArray;
import org.json.JSONObject;

/**
 * قاعدة بيانات لتخزين جميع بيانات الجداول المرتبطة:
 * data, re_people, dead_people, guardian_bank_accounts, death_reasons
 */
public class RelatedDataDatabaseHelper extends SQLiteOpenHelper {
    private static final String TAG = "RelatedDataDB";
    private static final String DATABASE_NAME = "related_data.db";
    private static final int DATABASE_VERSION = 2;

    private static RelatedDataDatabaseHelper instance;

    public static synchronized RelatedDataDatabaseHelper getInstance(Context context) {
        if (instance == null) {
            instance = new RelatedDataDatabaseHelper(context.getApplicationContext());
        }
        return instance;
    }

    private RelatedDataDatabaseHelper(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        // جدول data (المعيلين)
        db.execSQL("CREATE TABLE data_table ("
                + "id INTEGER PRIMARY KEY, "
                + "file_id_number TEXT, "
                + "data_id_number INTEGER, "
                + "data_first_name TEXT, "
                + "data_father_name TEXT, "
                + "data_grand_father_name TEXT, "
                + "data_family_name TEXT, "
                + "data_relationship INTEGER, "
                + "data_birth_date TEXT, "
                + "data_gender INTEGER, "
                + "data_phone_number TEXT, "
                + "data_alt_phone_number TEXT, "
                + "data_number_of_individuals INTEGER, "
                + "data_city INTEGER, "
                + "data_province INTEGER, "
                + "data_health_status INTEGER, "
                + "data_current_address TEXT, "
                + "data_section_id INTEGER, "
                + "data_request_status INTEGER, "
                + "data_marital_status INTEGER, "
                + "data_academic_qualification INTEGER, "
                + "data_displacement_status INTEGER, "
                + "data_employment_status_breadwinner INTEGER, "
                + "data_housing_status INTEGER, "
                + "data_current_housing_type INTEGER, "
                + "data_description_needs TEXT, "
                + "data_user_insert_data TEXT, "
                + "search_text TEXT, "
                + "updated_at INTEGER"
                + ")");
        db.execSQL("CREATE INDEX idx_data_file_id ON data_table(file_id_number)");
        db.execSQL("CREATE INDEX idx_data_id_number ON data_table(data_id_number)");
        db.execSQL("CREATE INDEX idx_data_search ON data_table(search_text)");

        // جدول re_people (أفراد العائلة)
        db.execSQL("CREATE TABLE re_people ("
                + "id INTEGER PRIMARY KEY, "
                + "registration_id INTEGER, "
                + "sponsorship_status INTEGER, "
                + "first_name TEXT, "
                + "second_name TEXT, "
                + "third_name TEXT, "
                + "last_name TEXT, "
                + "person_id TEXT, "
                + "person_birth_date TEXT, "
                + "person_age INTEGER, "
                + "person_gender INTEGER, "
                + "person_health_status INTEGER, "
                + "person_birth_certificate TEXT, "
                + "person_type_of_guarantee INTEGER, "
                + "person_note TEXT, "
                + "search_text TEXT, "
                + "updated_at INTEGER"
                + ")");
        db.execSQL("CREATE INDEX idx_repeople_reg_id ON re_people(registration_id)");
        db.execSQL("CREATE INDEX idx_repeople_person_id ON re_people(person_id)");
        db.execSQL("CREATE INDEX idx_repeople_search ON re_people(search_text)");

        // جدول dead_people (المتوفون)
        db.execSQL("CREATE TABLE dead_people ("
                + "id INTEGER PRIMARY KEY, "
                + "re_file_id TEXT, "
                + "sponsorship_status INTEGER, "
                + "father_first_name TEXT, "
                + "father_second_name TEXT, "
                + "father_third_name TEXT, "
                + "father_last_name TEXT, "
                + "father_id TEXT, "
                + "father_death_date TEXT, "
                + "father_death_reason INTEGER, "
                + "mother_first_name TEXT, "
                + "mother_second_name TEXT, "
                + "mother_third_name TEXT, "
                + "mother_last_name TEXT, "
                + "mother_id TEXT, "
                + "mother_death_reason INTEGER, "
                + "search_text TEXT, "
                + "updated_at INTEGER"
                + ")");
        db.execSQL("CREATE INDEX idx_dead_re_file ON dead_people(re_file_id)");
        db.execSQL("CREATE INDEX idx_dead_father_id ON dead_people(father_id)");
        db.execSQL("CREATE INDEX idx_dead_mother_id ON dead_people(mother_id)");
        db.execSQL("CREATE INDEX idx_dead_search ON dead_people(search_text)");

        // جدول guardian_bank_accounts (الحسابات البنكية)
        db.execSQL("CREATE TABLE guardian_bank_accounts ("
                + "id INTEGER PRIMARY KEY, "
                + "guardian_registration TEXT, "
                + "bank_name INTEGER, "
                + "bank_name_text TEXT, "
                + "iban_usd TEXT, "
                + "iban_shekel TEXT, "
                + "check_account INTEGER, "
                + "re_id_number TEXT, "
                + "re_guardian_name TEXT, "
                + "re_phone_number TEXT, "
                + "person_owner_identity_number TEXT, "
                + "search_text TEXT, "
                + "updated_at INTEGER"
                + ")");
        db.execSQL("CREATE INDEX idx_bank_reg ON guardian_bank_accounts(guardian_registration)");
        db.execSQL("CREATE INDEX idx_bank_re_id ON guardian_bank_accounts(re_id_number)");
        db.execSQL("CREATE INDEX idx_bank_search ON guardian_bank_accounts(search_text)");

        // جدول death_reasons (أسباب الوفاة)
        db.execSQL("CREATE TABLE death_reasons ("
                + "id INTEGER PRIMARY KEY, "
                + "description TEXT"
                + ")");

        Log.d(TAG, "All related data tables created");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        if (oldVersion < 2) {
            try {
                db.execSQL("ALTER TABLE re_people ADD COLUMN person_birth_certificate TEXT DEFAULT ''");
            } catch (Exception e) {
                Log.w(TAG, "Column person_birth_certificate may already exist: " + e.getMessage());
            }
        }
    }

    // ==================== حفظ البيانات ====================

    /**
     * حفظ دفعة بيانات من أي جدول
     */
    public void saveBatch(String tableName, JSONArray records) {
        SQLiteDatabase db = this.getWritableDatabase();
        db.beginTransaction();
        try {
            for (int i = 0; i < records.length(); i++) {
                JSONObject obj = records.getJSONObject(i);
                ContentValues cv = new ContentValues();

                switch (tableName) {
                    case "data_table":
                        cv.put("id", obj.optInt("id"));
                        cv.put("file_id_number", obj.optString("file_id_number", ""));
                        cv.put("data_id_number", obj.optInt("data_id_number", 0));
                        cv.put("data_first_name", obj.optString("data_first_name", ""));
                        cv.put("data_father_name", obj.optString("data_father_name", ""));
                        cv.put("data_grand_father_name", obj.optString("data_grand_father_name", ""));
                        cv.put("data_family_name", obj.optString("data_family_name", ""));
                        cv.put("data_relationship", obj.optInt("data_relationship", 0));
                        cv.put("data_birth_date", obj.optString("data_birth_date", ""));
                        cv.put("data_gender", obj.optInt("data_gender", 0));
                        cv.put("data_phone_number", obj.optString("data_phone_number", ""));
                        cv.put("data_alt_phone_number", obj.optString("data_alt_phone_number", ""));
                        cv.put("data_number_of_individuals", obj.optInt("data_number_of_individuals", 0));
                        cv.put("data_city", obj.optInt("data_city", 0));
                        cv.put("data_province", obj.optInt("data_province", 0));
                        cv.put("data_health_status", obj.optInt("data_health_status", 0));
                        cv.put("data_current_address", obj.optString("data_current_address", ""));
                        cv.put("data_section_id", obj.optInt("data_section_id", 0));
                        cv.put("data_request_status", obj.optInt("data_request_status", 0));
                        cv.put("data_marital_status", obj.optInt("data_marital_status", 0));
                        cv.put("data_academic_qualification", obj.optInt("data_academic_qualification", 0));
                        cv.put("data_displacement_status", obj.optInt("data_displacement_status", 0));
                        cv.put("data_employment_status_breadwinner", obj.optInt("data_employment_status_breadwinner", 0));
                        cv.put("data_housing_status", obj.optInt("data_housing_status", 0));
                        cv.put("data_current_housing_type", obj.optInt("data_current_housing_type", 0));
                        cv.put("data_description_needs", obj.optString("data_description_needs", ""));
                        cv.put("data_user_insert_data", obj.optString("data_user_insert_data", ""));
                        // بناء نص البحث
                        String dataSearch = (obj.optString("data_first_name", "") + " "
                                + obj.optString("data_father_name", "") + " "
                                + obj.optString("data_grand_father_name", "") + " "
                                + obj.optString("data_family_name", "") + " "
                                + obj.optString("data_id_number", "") + " "
                                + obj.optString("file_id_number", "") + " "
                                + obj.optString("data_phone_number", "")).toLowerCase().trim();
                        cv.put("search_text", dataSearch);
                        break;

                    case "re_people":
                        cv.put("id", obj.optInt("id"));
                        cv.put("registration_id", obj.optInt("registration_id", 0));
                        cv.put("sponsorship_status", obj.optInt("sponsorship_status", 0));
                        cv.put("first_name", obj.optString("first_name", ""));
                        cv.put("second_name", obj.optString("second_name", ""));
                        cv.put("third_name", obj.optString("third_name", ""));
                        cv.put("last_name", obj.optString("last_name", ""));
                        cv.put("person_id", obj.optString("person_id", ""));
                        cv.put("person_birth_date", obj.optString("person_birth_date", ""));
                        cv.put("person_age", obj.optInt("person_age", 0));
                        cv.put("person_gender", obj.optInt("person_gender", 0));
                        cv.put("person_health_status", obj.optInt("person_health_status", 0));
                        cv.put("person_birth_certificate", obj.optString("person_birth_certificate", ""));
                        cv.put("person_type_of_guarantee", obj.optInt("person_type_of_guarantee", 0));
                        cv.put("person_note", obj.optString("person_note", ""));
                        String reSearch = (obj.optString("first_name", "") + " "
                                + obj.optString("second_name", "") + " "
                                + obj.optString("third_name", "") + " "
                                + obj.optString("last_name", "") + " "
                                + obj.optString("person_id", "")).toLowerCase().trim();
                        cv.put("search_text", reSearch);
                        break;

                    case "dead_people":
                        cv.put("id", obj.optInt("id"));
                        cv.put("re_file_id", obj.optString("re_file_id", ""));
                        cv.put("sponsorship_status", obj.optInt("sponsorship_status", 0));
                        cv.put("father_first_name", obj.optString("father_first_name", ""));
                        cv.put("father_second_name", obj.optString("father_second_name", ""));
                        cv.put("father_third_name", obj.optString("father_third_name", ""));
                        cv.put("father_last_name", obj.optString("father_last_name", ""));
                        cv.put("father_id", obj.optString("father_id", ""));
                        cv.put("father_death_date", obj.optString("father_death_date", ""));
                        cv.put("father_death_reason", obj.optInt("father_death_reason", 0));
                        cv.put("mother_first_name", obj.optString("mother_first_name", ""));
                        cv.put("mother_second_name", obj.optString("mother_second_name", ""));
                        cv.put("mother_third_name", obj.optString("mother_third_name", ""));
                        cv.put("mother_last_name", obj.optString("mother_last_name", ""));
                        cv.put("mother_id", obj.optString("mother_id", ""));
                        cv.put("mother_death_reason", obj.optInt("mother_death_reason", 0));
                        String deadSearch = (obj.optString("father_first_name", "") + " "
                                + obj.optString("father_second_name", "") + " "
                                + obj.optString("father_third_name", "") + " "
                                + obj.optString("father_last_name", "") + " "
                                + obj.optString("father_id", "") + " "
                                + obj.optString("mother_first_name", "") + " "
                                + obj.optString("mother_second_name", "") + " "
                                + obj.optString("mother_third_name", "") + " "
                                + obj.optString("mother_last_name", "") + " "
                                + obj.optString("mother_id", "") + " "
                                + obj.optString("re_file_id", "")).toLowerCase().trim();
                        cv.put("search_text", deadSearch);
                        break;

                    case "guardian_bank_accounts":
                        cv.put("id", obj.optInt("id"));
                        cv.put("guardian_registration", obj.optString("guardian_registration", ""));
                        cv.put("bank_name", obj.optInt("bank_name", 0));
                        cv.put("bank_name_text", obj.optString("bank_name_text", ""));
                        cv.put("iban_usd", obj.optString("iban_usd", ""));
                        cv.put("iban_shekel", obj.optString("iban_shekel", ""));
                        cv.put("check_account", obj.optInt("check_account", 0));
                        cv.put("re_id_number", obj.optString("re_id_number", ""));
                        cv.put("re_guardian_name", obj.optString("re_guardian_name", ""));
                        cv.put("re_phone_number", obj.optString("re_phone_number", ""));
                        cv.put("person_owner_identity_number", obj.optString("person_owner_identity_number", ""));
                        String bankSearch = (obj.optString("re_guardian_name", "") + " "
                                + obj.optString("re_id_number", "") + " "
                                + obj.optString("person_owner_identity_number", "") + " "
                                + obj.optString("guardian_registration", "") + " "
                                + obj.optString("re_phone_number", "")).toLowerCase().trim();
                        cv.put("search_text", bankSearch);
                        break;

                    case "death_reasons":
                        cv.put("id", obj.optInt("id"));
                        cv.put("description", obj.optString("description", ""));
                        break;
                }

                if (!"death_reasons".equals(tableName)) {
                    cv.put("updated_at", System.currentTimeMillis());
                }
                db.insertWithOnConflict(tableName, null, cv, SQLiteDatabase.CONFLICT_REPLACE);
            }
            db.setTransactionSuccessful();
            Log.d(TAG, "Saved " + records.length() + " records to " + tableName);
        } catch (Exception e) {
            Log.e(TAG, "Error saving batch to " + tableName, e);
        } finally {
            db.endTransaction();
        }
    }

    // ==================== البحث ====================

    /**
     * بحث في جدول data
     */
    public JSONArray searchData(String query, int limit) {
        return searchTable("data_table", query, limit,
                new String[]{"id", "file_id_number", "data_id_number", "data_first_name",
                        "data_father_name", "data_grand_father_name", "data_family_name",
                        "data_phone_number", "data_alt_phone_number", "data_city",
                        "data_health_status", "data_current_address"});
    }

    /**
     * بحث في جدول re_people
     */
    public JSONArray searchRePeople(String query, int limit) {
        return searchTable("re_people", query, limit,
                new String[]{"id", "registration_id", "first_name", "second_name",
                        "third_name", "last_name", "person_id", "person_birth_date",
                        "person_gender", "person_health_status"});
    }

    /**
     * بحث في جدول dead_people
     */
    public JSONArray searchDeadPeople(String query, int limit) {
        return searchTable("dead_people", query, limit,
                new String[]{"id", "re_file_id", "father_first_name", "father_second_name",
                        "father_third_name", "father_last_name", "father_id",
                        "mother_first_name", "mother_second_name", "mother_third_name",
                        "mother_last_name", "mother_id"});
    }

    /**
     * بحث في جدول guardian_bank_accounts
     */
    public JSONArray searchBankAccounts(String query, int limit) {
        return searchTable("guardian_bank_accounts", query, limit,
                new String[]{"id", "guardian_registration", "re_guardian_name",
                        "re_id_number", "person_owner_identity_number", "re_phone_number",
                        "bank_name_text", "iban_shekel"});
    }

    /**
     * بحث عام في أي جدول
     */
    private JSONArray searchTable(String tableName, String query, int limit, String[] columns) {
        JSONArray result = new JSONArray();
        SQLiteDatabase db = this.getReadableDatabase();

        String[] tokens = query.toLowerCase().trim().split("\\s+");
        StringBuilder where = new StringBuilder();
        java.util.ArrayList<String> args = new java.util.ArrayList<>();

        for (String t : tokens) {
            if (t.isEmpty()) continue;
            if (where.length() > 0) where.append(" AND ");
            where.append("search_text LIKE ?");
            args.add("%" + t + "%");
        }

        if (where.length() == 0) return result;

        String sql = "SELECT * FROM " + tableName + " WHERE " + where.toString()
                + " LIMIT " + limit;

        Cursor cursor = db.rawQuery(sql, args.toArray(new String[0]));
        try {
            if (cursor.moveToFirst()) {
                do {
                    JSONObject obj = new JSONObject();
                    for (String col : columns) {
                        int idx = cursor.getColumnIndex(col);
                        if (idx >= 0) {
                            obj.put(col, cursor.getString(idx));
                        }
                    }
                    result.put(obj);
                } while (cursor.moveToNext());
            }
        } catch (Exception e) {
            Log.e(TAG, "Search error in " + tableName, e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * الحصول على سجل بالـ ID من أي جدول
     */
    public JSONObject getById(String tableName, int id) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM " + tableName + " WHERE id = ?", new String[]{String.valueOf(id)});
        JSONObject result = null;
        try {
            if (cursor.moveToFirst()) {
                result = new JSONObject();
                for (int i = 0; i < cursor.getColumnCount(); i++) {
                    result.put(cursor.getColumnName(i), cursor.getString(i));
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "GetById error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث برقم الهوية في جدول data
     */
    public JSONObject getDataByIdNumber(String idNumber) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM data_table WHERE data_id_number = ? OR file_id_number = ? LIMIT 1",
                new String[]{idNumber, idNumber});
        JSONObject result = null;
        try {
            if (cursor.moveToFirst()) {
                result = new JSONObject();
                for (int i = 0; i < cursor.getColumnCount(); i++) {
                    result.put(cursor.getColumnName(i), cursor.getString(i));
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "getDataByIdNumber error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث برقم الهاتف في جدول data
     * يبحث في data_phone_number و data_alt_phone_number
     * أو آخر 9 أرقام من data_id_number
     */
    public JSONObject getDataByPhoneNumber(String phoneNumber) {
        SQLiteDatabase db = this.getReadableDatabase();
        String cleaned = phoneNumber.replaceAll("[^0-9]", "");
        String last9 = cleaned.length() > 9 ? cleaned.substring(cleaned.length() - 9) : cleaned;

        Cursor cursor = db.rawQuery(
                "SELECT * FROM data_table WHERE " +
                "data_phone_number LIKE ? OR data_phone_number LIKE ? OR " +
                "data_alt_phone_number LIKE ? OR data_alt_phone_number LIKE ? LIMIT 1",
                new String[]{
                    "%" + cleaned, "%" + last9,
                    "%" + cleaned, "%" + last9
                });

        JSONObject result = null;
        try {
            if (cursor.moveToFirst()) {
                result = new JSONObject();
                for (int i = 0; i < cursor.getColumnCount(); i++) {
                    result.put(cursor.getColumnName(i), cursor.getString(i));
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "getDataByPhoneNumber error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث برقم التسجيل في جدول re_people
     */
    public JSONObject getRePersonByRegId(String registrationId) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM re_people WHERE registration_id = ? LIMIT 1",
                new String[]{registrationId});
        JSONObject result = null;
        try {
            if (cursor.moveToFirst()) {
                result = new JSONObject();
                for (int i = 0; i < cursor.getColumnCount(); i++) {
                    result.put(cursor.getColumnName(i), cursor.getString(i));
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "getRePersonByRegId error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث برقم الهوية في جدول re_people
     */
    public JSONObject getRePersonByIdNumber(String personId) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM re_people WHERE person_id = ? LIMIT 1",
                new String[]{personId});
        JSONObject result = null;
        try {
            if (cursor.moveToFirst()) {
                result = new JSONObject();
                for (int i = 0; i < cursor.getColumnCount(); i++) {
                    result.put(cursor.getColumnName(i), cursor.getString(i));
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "getRePersonByIdNumber error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث برقم الملف في جدول dead_people
     */
    public JSONObject getDeadPersonByFileId(String fileId) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM dead_people WHERE re_file_id = ? LIMIT 1",
                new String[]{fileId});
        JSONObject result = null;
        try {
            if (cursor.moveToFirst()) {
                result = new JSONObject();
                for (int i = 0; i < cursor.getColumnCount(); i++) {
                    result.put(cursor.getColumnName(i), cursor.getString(i));
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "getDeadPersonByFileId error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث بالهوية في جدول dead_people (أب أو أم)
     */
    public JSONObject getDeadPersonByIdNumber(String idNumber) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM dead_people WHERE father_id = ? OR mother_id = ? LIMIT 1",
                new String[]{idNumber, idNumber});
        JSONObject result = null;
        try {
            if (cursor.moveToFirst()) {
                result = new JSONObject();
                for (int i = 0; i < cursor.getColumnCount(); i++) {
                    result.put(cursor.getColumnName(i), cursor.getString(i));
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "getDeadPersonByIdNumber error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث برقم التسجيل في جدول guardian_bank_accounts
     */
    public JSONArray getBankAccountsByRegistration(String registration) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM guardian_bank_accounts WHERE guardian_registration = ?",
                new String[]{registration});
        JSONArray result = new JSONArray();
        try {
            if (cursor.moveToFirst()) {
                do {
                    JSONObject obj = new JSONObject();
                    for (int i = 0; i < cursor.getColumnCount(); i++) {
                        obj.put(cursor.getColumnName(i), cursor.getString(i));
                    }
                    result.put(obj);
                } while (cursor.moveToNext());
            }
        } catch (Exception e) {
            Log.e(TAG, "getBankAccountsByRegistration error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    /**
     * البحث برقم الهوية في جدول guardian_bank_accounts
     */
    public JSONArray getBankAccountsByIdNumber(String idNumber) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT * FROM guardian_bank_accounts WHERE re_id_number = ? OR person_owner_identity_number = ?",
                new String[]{idNumber, idNumber});
        JSONArray result = new JSONArray();
        try {
            if (cursor.moveToFirst()) {
                do {
                    JSONObject obj = new JSONObject();
                    for (int i = 0; i < cursor.getColumnCount(); i++) {
                        obj.put(cursor.getColumnName(i), cursor.getString(i));
                    }
                    result.put(obj);
                } while (cursor.moveToNext());
            }
        } catch (Exception e) {
            Log.e(TAG, "getBankAccountsByIdNumber error", e);
        } finally {
            cursor.close();
        }
        return result;
    }

    // ==================== الإحصائيات ====================

    public int getCount(String tableName) {
        SQLiteDatabase db = this.getReadableDatabase();
        Cursor cursor = db.rawQuery("SELECT COUNT(*) FROM " + tableName, null);
        int count = 0;
        if (cursor.moveToFirst()) {
            count = cursor.getInt(0);
        }
        cursor.close();
        return count;
    }

    /**
     * مسح جميع البيانات
     */
    public void clearAll() {
        SQLiteDatabase db = this.getWritableDatabase();
        db.delete("data_table", null, null);
        db.delete("re_people", null, null);
        db.delete("dead_people", null, null);
        db.delete("guardian_bank_accounts", null, null);
        db.delete("death_reasons", null, null);
        Log.d(TAG, "All related data tables cleared");
    }
}
