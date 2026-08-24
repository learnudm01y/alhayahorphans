package org.alhayah.sponsorships;

import android.content.ContentValues;
import android.content.Context;
import android.content.SharedPreferences;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

import org.json.JSONObject;

import java.io.BufferedInputStream;
import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.List;
import java.util.concurrent.*;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.zip.GZIPInputStream;

/**
 * CivilRegistryStore — نسخة خفيفة من السجل المدني داخل SQLite.
 * يعتمد على manifest.json + ملفات gz من:
 *   GET /api/mobile/civil-registry/manifest
 *   GET /api/mobile/civil-registry/file/{file}
 */
public class CivilRegistryStore {

    public interface ProgressListener {
        void onProgress(int percent, String message);
        void onFinished(boolean success, String message);
    }

    private static final String TAG = "CivilRegistryStore";
    private static final String DB_NAME = "civil_registry.db";
    private static final int DB_VERSION = 2;
    private static final String TABLE = "civil_persons";
    private static final String PREFS = "civil_registry_prefs";

    private static volatile boolean cancelRequested = false;
    private static volatile boolean running = false;

    private final Context context;

    public CivilRegistryStore(Context context) {
        this.context = context.getApplicationContext();
    }

    private static class Helper extends SQLiteOpenHelper {
        Helper(Context context) {
            super(context, DB_NAME, null, DB_VERSION);
        }

        @Override
        public void onCreate(SQLiteDatabase db) {
            db.execSQL("CREATE TABLE IF NOT EXISTS " + TABLE + " (" +
                    "id TEXT PRIMARY KEY, " +
                    "f TEXT, fa TEXT, gf TEXT, fam TEXT, m TEXT, b TEXT, s TEXT, " +
                    "p TEXT, c TEXT, st TEXT, ho TEXT, d TEXT)");
            db.execSQL("CREATE INDEX IF NOT EXISTS idx_civil_f ON " + TABLE + "(f)");
            db.execSQL("CREATE INDEX IF NOT EXISTS idx_civil_fam ON " + TABLE + "(fam)");
            db.execSQL("CREATE INDEX IF NOT EXISTS idx_civil_full ON " + TABLE + "(f, fam)");
        }

        @Override
        public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
            db.execSQL("DROP TABLE IF EXISTS " + TABLE);
            onCreate(db);
        }

        @Override
        public void onOpen(SQLiteDatabase db) {
            super.onOpen(db);
        }
    }

    public JSONObject status() throws Exception {
        SharedPreferences prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        int total = prefs.getInt("total", 0);
        int percent = prefs.getInt("percent", 0);
        int lastLoaded = prefs.getInt("last_loaded", 0);
        boolean downloading = running || prefs.getBoolean("downloading", false);

        int loaded = 0;
        try {
            Helper helper = new Helper(context);
            SQLiteDatabase db = helper.getReadableDatabase();
            Cursor cursor = null;
            try {
                cursor = db.rawQuery("SELECT COUNT(*) FROM " + TABLE, null);
                if (cursor.moveToFirst()) {
                    loaded = cursor.getInt(0);
                }
            } catch (Exception ignored) {
                loaded = lastLoaded;
            } finally {
                if (cursor != null) cursor.close();
                helper.close();
            }
        } catch (Exception e) {
            loaded = lastLoaded;
        }

        JSONObject o = new JSONObject();
        o.put("loaded", loaded);
        o.put("total", total);
        o.put("percent", percent);
        o.put("downloading", downloading);
        o.put("ready", total > 0 && loaded >= total);
        return o;
    }

    public void download(String apiBase, String token, ProgressListener listener) {
        download(apiBase, token, listener, 4);
    }

    public void download(String apiBase, String token, ProgressListener listener, int workers) {
        if (running) {
            if (listener != null) listener.onFinished(false, "Already downloading");
            return;
        }
        running = true;
        cancelRequested = false;
        final int concurrency = Math.min(Math.max(workers, 2), 8);
        new Thread(() -> {
            Helper helper = null;
            SQLiteDatabase db = null;
            try {
                JSONObject manifest = fetchJson(apiBase + "/api/mobile/civil-registry/manifest", token);
                if (manifest == null || !manifest.optBoolean("success", false)) {
                    running = false;
                    if (listener != null) listener.onFinished(false, "Failed to fetch manifest");
                    return;
                }
                int total = manifest.optInt("total_records", 0);
                org.json.JSONArray chunks = manifest.optJSONArray("chunks");
                if (chunks == null || chunks.length() == 0) {
                    running = false;
                    if (listener != null) listener.onFinished(false, "No chunks");
                    return;
                }

                SharedPreferences prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
                prefs.edit().putInt("total", total).putBoolean("downloading", true).putInt("percent", 0).apply();

                helper = new Helper(context);
                db = helper.getWritableDatabase();

                final int totalChunks = chunks.length();
                final AtomicInteger doneChunks = new AtomicInteger(0);
                final AtomicInteger loaded = new AtomicInteger(0);
                final AtomicInteger failed = new AtomicInteger(0);

                java.util.Set<String> completedChunks = java.util.Collections.synchronizedSet(new java.util.HashSet<String>());
                String completedStr = prefs.getString("completed_chunks", "");
                if (!completedStr.isEmpty()) {
                    for (String s : completedStr.split(",")) {
                        if (!s.isEmpty()) completedChunks.add(s);
                    }
                    Log.d(TAG, "Resuming: " + completedChunks.size() + " chunks already done");
                }

                final int BATCH_SIZE = 1000;
                final BlockingQueue<List<ContentValues>> writeQueue = new LinkedBlockingQueue<>(50);
                final SQLiteDatabase dbFinal = db;
                final Object doneSignal = new Object();
                final java.util.concurrent.atomic.AtomicBoolean writersDone = new java.util.concurrent.atomic.AtomicBoolean(false);

                Thread writerThread = new Thread(() -> {
                    int batchCount = 0;
                    try {
                        while (!writersDone.get() || !writeQueue.isEmpty()) {
                            List<ContentValues> batch = writeQueue.poll(5, TimeUnit.SECONDS);
                            if (batch == null) continue;
                            if (batch.isEmpty()) {
                                synchronized (doneSignal) { doneSignal.notifyAll(); }
                                continue;
                            }
                            dbFinal.beginTransaction();
                            try {
                                for (ContentValues v : batch) {
                                    dbFinal.insertWithOnConflict(TABLE, null, v, SQLiteDatabase.CONFLICT_REPLACE);
                                }
                                dbFinal.setTransactionSuccessful();
                            } finally {
                                dbFinal.endTransaction();
                            }
                            loaded.addAndGet(batch.size());
                            batchCount++;
                            if (batchCount % 10 == 0) {
                                prefs.edit().putInt("last_loaded", loaded.get()).apply();
                            }
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Writer thread error", e);
                    }
                    prefs.edit().putInt("last_loaded", loaded.get()).apply();
                    Log.d(TAG, "Writer thread finished. Total written: " + loaded.get());
                }, "CivilRegistry-Writer");
                writerThread.start();

                ExecutorService executor = Executors.newFixedThreadPool(concurrency);
                CountDownLatch latch = new CountDownLatch(totalChunks);
                final int maxRetries = 3;

                for (int i = 0; i < totalChunks; i++) {
                    final int idx = i;
                    final String chunkName = chunks.optJSONObject(idx) != null ? chunks.optJSONObject(idx).optString("file", "") : "";
                    if (completedChunks.contains(chunkName) && !chunkName.isEmpty()) {
                        latch.countDown();
                        doneChunks.incrementAndGet();
                        continue;
                    }
                    executor.submit(() -> {
                        boolean chunkDone = false;
                        for (int retry = 0; retry < maxRetries && !chunkDone && !cancelRequested; retry++) {
                            if (retry > 0) {
                                try { Thread.sleep(2000 * retry); } catch (InterruptedException ignored) {}
                                Log.w(TAG, "Retrying chunk " + idx + " (attempt " + (retry + 1) + "/" + maxRetries + ")");
                            }
                            HttpURLConnection conn = null;
                            try {
                                JSONObject chunk = chunks.getJSONObject(idx);
                                String fileName = chunk.optString("file");
                                String url = apiBase + "/api/mobile/civil-registry/file/" + java.net.URLEncoder.encode(fileName, "UTF-8");
                                conn = openConn(url, token);
                                int status = conn.getResponseCode();
                                if (status != 200) {
                                    throw new RuntimeException("HTTP " + status);
                                }
                                InputStream is = new GZIPInputStream(new BufferedInputStream(conn.getInputStream()));
                                BufferedReader reader = new BufferedReader(new InputStreamReader(is, StandardCharsets.UTF_8));
                                String line;
                                java.util.List<ContentValues> batch = new java.util.ArrayList<>(BATCH_SIZE);
                                while ((line = reader.readLine()) != null) {
                                    if (cancelRequested) break;
                                    line = line.trim();
                                    if (line.isEmpty()) continue;
                                    JSONObject row = new JSONObject(line);
                                    ContentValues cv = new ContentValues();
                                    cv.put("id", row.opt("id") == null ? "" : String.valueOf(row.opt("id")));
                                    cv.put("f", row.optString("f"));
                                    cv.put("fa", row.optString("fa"));
                                    cv.put("gf", row.optString("gf"));
                                    cv.put("fam", row.optString("fam"));
                                    cv.put("m", row.optString("m"));
                                    cv.put("b", row.optString("b"));
                                    cv.put("s", row.optString("s"));
                                    cv.put("p", row.optString("p"));
                                    cv.put("c", row.optString("c"));
                                    cv.put("st", row.optString("st"));
                                    cv.put("ho", row.optString("ho"));
                                    cv.put("d", row.isNull("d") ? null : row.optString("d"));
                                    batch.add(cv);
                                    if (batch.size() >= BATCH_SIZE) {
                                        writeQueue.put(new java.util.ArrayList<>(batch));
                                        batch.clear();
                                    }
                                }
                                if (!batch.isEmpty()) {
                                    writeQueue.put(new java.util.ArrayList<>(batch));
                                }
                                reader.close();
                                completedChunks.add(chunkName);
                                chunkDone = true;
                            } catch (Exception e) {
                                Log.w(TAG, "chunk " + idx + " attempt " + (retry + 1) + " failed: " + e.getClass().getSimpleName() + ": " + e.getMessage());
                            } finally {
                                if (conn != null) try { conn.disconnect(); } catch (Exception ignored) {}
                            }
                        }
                        if (!chunkDone) {
                            failed.incrementAndGet();
                            Log.e(TAG, "chunk " + idx + " FAILED after " + maxRetries + " retries");
                        }
                        int done = doneChunks.incrementAndGet();
                        int percent = (int) (((long) done * 100) / totalChunks);
                        prefs.edit().putInt("percent", percent).putInt("last_loaded", loaded.get()).putString("completed_chunks", String.join(",", completedChunks)).apply();
                        if (listener != null) listener.onProgress(percent, "Downloading... " + percent + "% [failed:" + failed.get() + "]");
                        latch.countDown();
                    });
                }

                latch.await();
                executor.shutdown();
                writersDone.set(true);
                writeQueue.put(java.util.Collections.emptyList());
                writerThread.join(30000);

                if (cancelRequested) {
                    prefs.edit().putBoolean("downloading", false).apply();
                    running = false;
                    if (helper != null) helper.close();
                    if (listener != null) listener.onFinished(false, "Cancelled");
                    return;
                }

                prefs.edit().putBoolean("downloading", false).putInt("percent", 100).remove("completed_chunks").apply();
                running = false;
                if (helper != null) helper.close();
                if (listener != null) listener.onFinished(true, "Loaded " + loaded.get() + " records (failed:" + failed.get() + ")");
            } catch (Exception e) {
                Log.e(TAG, "download failed", e);
                try {
                    SharedPreferences prefs2 = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
                    prefs2.edit().putBoolean("downloading", false).apply();
                } catch (Exception ignored) {}
                running = false;
                if (helper != null) try { helper.close(); } catch (Exception ignored) {}
                if (listener != null) listener.onFinished(false, "Failed: " + e.getMessage());
            }
        }).start();
    }


    
    public org.json.JSONArray searchByName(String query, int limit) throws Exception {
        if (query == null || query.trim().isEmpty()) return new org.json.JSONArray();
        Helper helper = new Helper(context);
        SQLiteDatabase db = helper.getReadableDatabase();
        Cursor cursor = null;
        try {
            String trimmed = query.trim();
            String[] tokens = trimmed.split("\\s+");
            // Build token-based flexible search: every token must appear in the full name (f, fa, gf, fam)
            StringBuilder where = new StringBuilder();
            java.util.List<String> argsList = new java.util.ArrayList<>();
            // Efficient token-based search: only check full name to avoid 5x conditions per token
            for (int i = 0; i < tokens.length; i++) {
                if (tokens[i].isEmpty()) continue;
                String like = "%" + tokens[i].replace("'", "''") + "%";
                if (where.length() > 0) where.append(" AND ");
                where.append("(f || ' ' || fa || ' ' || gf || ' ' || fam) LIKE ?");
                argsList.add(like);
            }
            if (where.length() == 0) return new org.json.JSONArray();
            String sql = "SELECT id,f,fa,gf,fam,m,b,s,p,c,st,ho,d FROM " + TABLE + " WHERE " + where.toString() + " LIMIT ?";
            argsList.add(String.valueOf(limit));
            String[] args = argsList.toArray(new String[0]);
            cursor = db.rawQuery(sql, args);
            org.json.JSONArray arr = new org.json.JSONArray();
            java.util.Set<String> seenIds = new java.util.HashSet<>();
            while (cursor.moveToNext()) {
                org.json.JSONObject o = new org.json.JSONObject();
                String id = cursor.getString(0);
                if (seenIds.contains(id)) continue;
                seenIds.add(id);
                o.put("id", id);
                o.put("f", cursor.getString(1));
                o.put("fa", cursor.getString(2));
                o.put("gf", cursor.getString(3));
                o.put("fam", cursor.getString(4));
                o.put("m", cursor.getString(5));
                o.put("b", cursor.getString(6));
                o.put("s", cursor.getString(7));
                o.put("p", cursor.getString(8));
                o.put("c", cursor.getString(9));
                o.put("st", cursor.getString(10));
                o.put("ho", cursor.getString(11));
                o.put("d", cursor.isNull(12) ? org.json.JSONObject.NULL : cursor.getString(12));
                arr.put(o);
            }
            cursor.close();
            cursor = null;
            // Also find children of the matched persons: search for persons where any name token matches family link
            // For each matched parent, find his children (where fa or gf matches parent's f)
            if (arr.length() > 0 && arr.length() < 5) {
                // For each parent found, search for children
                for (int i = 0; i < arr.length(); i++) {
                    org.json.JSONObject parent = arr.getJSONObject(i);
                    String parentFirst = parent.optString("f");
                    String parentFamily = parent.optString("fam");
                    if (parentFirst != null && !parentFirst.isEmpty() && parentFamily != null && !parentFamily.isEmpty()) {
                        // Find children where family matches and father name matches parent's first name
                        String childSql = "SELECT id,f,fa,gf,fam,m,b,s,p,c,st,ho,d FROM " + TABLE + " WHERE fam LIKE ? AND (fa LIKE ? OR f LIKE ?) AND id != ? LIMIT 20";
                        String famLike = "%" + parentFamily.replace("'", "''") + "%";
                        String fatherLike = "%" + parentFirst.replace("'", "''") + "%";
                        Cursor childCursor = null;
                        try {
                            childCursor = db.rawQuery(childSql, new String[]{famLike, fatherLike, fatherLike, parent.optString("id")});
                            while (childCursor.moveToNext()) {
                                String childId = childCursor.getString(0);
                                if (seenIds.contains(childId)) continue;
                                seenIds.add(childId);
                                org.json.JSONObject co = new org.json.JSONObject();
                                co.put("id", childId);
                                co.put("f", childCursor.getString(1));
                                co.put("fa", childCursor.getString(2));
                                co.put("gf", childCursor.getString(3));
                                co.put("fam", childCursor.getString(4));
                                co.put("m", childCursor.getString(5));
                                co.put("b", childCursor.getString(6));
                                co.put("s", childCursor.getString(7));
                                co.put("p", childCursor.getString(8));
                                co.put("c", childCursor.getString(9));
                                co.put("st", childCursor.getString(10));
                                co.put("ho", childCursor.getString(11));
                                co.put("d", childCursor.isNull(12) ? org.json.JSONObject.NULL : childCursor.getString(12));
                                co.put("_isChild", true);
                                co.put("_parentId", parent.optString("id"));
                                arr.put(co);
                                if (arr.length() >= limit) break;
                            }
                        } finally {
                            if (childCursor != null) childCursor.close();
                        }
                    }
                    if (arr.length() >= limit) break;
                }
            }
            return arr;
        } finally {
            if (cursor != null) cursor.close();
            helper.close();
        }
    }

    public org.json.JSONArray search(String query, int limit, String searchType) throws Exception {
        if ("id".equals(searchType)) {
            org.json.JSONObject one = lookup(query);
            org.json.JSONArray arr = new org.json.JSONArray();
            if (one != null) arr.put(one);
            return arr;
        } else {
            return searchByName(query, limit);
        }
    }

    public void cancel() {
        cancelRequested = true;
    }

    public JSONObject lookup(String id) throws Exception {
        Helper helper = new Helper(context);
        SQLiteDatabase db = helper.getReadableDatabase();
        Cursor cursor = null;
        try {
            cursor = db.rawQuery("SELECT id,f,fa,gf,fam,m,b,s,p,c,st,ho,d FROM " + TABLE + " WHERE id = ? LIMIT 1", new String[]{id});
            if (cursor.moveToFirst()) {
                JSONObject o = new JSONObject();
                o.put("id", cursor.getString(0));
                o.put("f", cursor.getString(1));
                o.put("fa", cursor.getString(2));
                o.put("gf", cursor.getString(3));
                o.put("fam", cursor.getString(4));
                o.put("m", cursor.getString(5));
                o.put("b", cursor.getString(6));
                o.put("s", cursor.getString(7));
                o.put("p", cursor.getString(8));
                o.put("c", cursor.getString(9));
                o.put("st", cursor.getString(10));
                o.put("ho", cursor.getString(11));
                o.put("d", cursor.isNull(12) ? JSONObject.NULL : cursor.getString(12));
                return o;
            }
            return null;
        } finally {
            if (cursor != null) cursor.close();
            helper.close();
        }
    }

    private HttpURLConnection openConn(String urlStr, String token) throws Exception {
        URL url = new URL(urlStr);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setConnectTimeout(15000);
        conn.setReadTimeout(30000);
        conn.setRequestProperty("Authorization", "Bearer " + token);
        conn.setRequestProperty("Accept", "application/json");
        return conn;
    }

    private JSONObject fetchJson(String urlStr, String token) throws Exception {
        HttpURLConnection conn = null;
        try {
            conn = openConn(urlStr, token);
            int code = conn.getResponseCode();
            InputStream is = code >= 200 && code < 300 ? conn.getInputStream() : conn.getErrorStream();
            BufferedReader reader = new BufferedReader(new InputStreamReader(is, StandardCharsets.UTF_8));
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) sb.append(line);
            reader.close();
            JSONObject obj = new JSONObject(sb.toString());
            if (code != 200) Log.e(TAG, "fetchJson status " + code + ": " + obj.optString("message"));
            return obj;
        } finally {
            if (conn != null) conn.disconnect();
        }
    }
}