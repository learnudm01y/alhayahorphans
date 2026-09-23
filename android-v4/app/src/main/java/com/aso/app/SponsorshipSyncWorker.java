package com.aso.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;
import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;
import org.json.JSONArray;
import org.json.JSONObject;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

public class SponsorshipSyncWorker extends Worker {
    private static final String TAG = "SponsorshipSyncWorker";

    public SponsorshipSyncWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.i(TAG, "Starting real sponsorship sync");
        try {
            // Skip if DownloadForegroundService is already running
            if (org.alhayah.sponsorships.DownloadForegroundService.isDownloading()) {
                Log.i(TAG, "DownloadForegroundService is running, skipping periodic sync");
                return Result.success();
            }

            SharedPreferences prefs = getApplicationContext()
                .getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
            String token = prefs.getString("api_token", "");

            if (token.isEmpty()) {
                Log.e(TAG, "No auth token - cannot sync");
                return Result.failure();
            }

            String baseUrl = ApiConfig.getBaseUrl(getApplicationContext());
            if (baseUrl == null || baseUrl.isEmpty()) {
                Log.e(TAG, "No base URL found");
                return Result.failure();
            }
            if (baseUrl.endsWith("/")) baseUrl = baseUrl.substring(0, baseUrl.length() - 1);

            String initialUrlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/initial" : baseUrl + "/api/mobile/sync/initial";
            String urlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/sponsorships" : baseUrl + "/api/mobile/sync/sponsorships";

            SponsorshipsDatabaseHelper dbHelper = SponsorshipsDatabaseHelper.getInstance(getApplicationContext());
            org.alhayah.sponsorships.DataSyncDatabaseHelper syncDbHelper = org.alhayah.sponsorships.DataSyncDatabaseHelper.getInstance(getApplicationContext());

            // 1. Fetch and save lookups + pruning
            Log.i(TAG, "Fetching initial lookups");
            org.alhayah.sponsorships.BackgroundSyncPlugin.emitProgress(5, "جاري جلب البيانات الأساسية من السيرفر...", 0);
            try {
                String initResp = fetchUrl(initialUrlStr, token);
                JSONObject initJson = new JSONObject(initResp);
                if (initJson.has("success") && initJson.getBoolean("success")) {
                    JSONObject data = initJson.optJSONObject("data");
                    if (data != null) {
                        if (data.has("sponsors")) dbHelper.saveLookup("sponsors", data.getJSONArray("sponsors").toString());
                        if (data.has("sponsorship_statuses")) dbHelper.saveLookup("sponsorship_statuses", data.getJSONArray("sponsorship_statuses").toString());
                        if (data.has("bank_names")) dbHelper.saveLookup("bank_names", data.getJSONArray("bank_names").toString());
                        if (data.has("health_statuses")) dbHelper.saveLookup("health_statuses", data.getJSONArray("health_statuses").toString());
                        if (data.has("cities")) dbHelper.saveLookup("cities", data.getJSONArray("cities").toString());
                        if (data.has("sponsorship_types")) dbHelper.saveLookup("sponsorship_types", data.getJSONArray("sponsorship_types").toString());

                        // إضافات التسجيل
                        if (data.has("sections")) dbHelper.saveLookup("sections", data.getJSONArray("sections").toString());
                        if (data.has("provinces")) dbHelper.saveLookup("provinces", data.getJSONArray("provinces").toString());
                        if (data.has("relations")) dbHelper.saveLookup("relations", data.getJSONArray("relations").toString());
                        if (data.has("marital_statuses")) dbHelper.saveLookup("marital_statuses", data.getJSONArray("marital_statuses").toString());
                        if (data.has("academic_degrees")) dbHelper.saveLookup("academic_degrees", data.getJSONArray("academic_degrees").toString());
                        if (data.has("employment_statuses")) dbHelper.saveLookup("employment_statuses", data.getJSONArray("employment_statuses").toString());
                        if (data.has("displacement_statuses")) dbHelper.saveLookup("displacement_statuses", data.getJSONArray("displacement_statuses").toString());
                        if (data.has("housing_statuses")) dbHelper.saveLookup("housing_statuses", data.getJSONArray("housing_statuses").toString());
                        if (data.has("housing_types")) dbHelper.saveLookup("housing_types", data.getJSONArray("housing_types").toString());
                        if (data.has("document_types")) dbHelper.saveLookup("document_types", data.getJSONArray("document_types").toString());
                        if (data.has("death_reasons")) dbHelper.saveLookup("death_reasons", data.getJSONArray("death_reasons").toString());

                        if (data.has("valid_sponsorship_ids")) {
                            dbHelper.syncValidSponsorships(data.optJSONArray("valid_sponsorship_ids"));
                        }
                        Log.i(TAG, "Successfully saved initial lookups");
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to fetch initial lookups", e);
            }

            // 2. Fetch Server Stats
            Log.i(TAG, "Fetching server sync stats");
            int serverTotalCount = -1;
            String statsUrlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/stats" : baseUrl + "/api/mobile/sync/stats";
            try {
                String statsResp = fetchUrl(statsUrlStr, token);
                JSONObject statsJson = new JSONObject(statsResp);
                if (statsJson.optBoolean("success", false)) {
                    JSONObject dataObj = statsJson.optJSONObject("data");
                    if (dataObj != null) {
                        serverTotalCount = dataObj.optInt("total_sponsorships", -1);
                        Log.i(TAG, "Server total sponsorships count: " + serverTotalCount);
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to fetch server stats", e);
            }

            // 3. Session Management
            SharedPreferences sessionPrefs = getApplicationContext().getSharedPreferences("sync_session_prefs", Context.MODE_PRIVATE);
            boolean sessionActive = sessionPrefs.getBoolean("session_active", false);

            int page = 1;
            int lastPage = 1;
            String lastSyncDate = null;
            int expectedTotal = 0;
            boolean isFullSync = true;
            int localCountBefore = dbHelper.getCount();

            if (sessionActive) {
                page = sessionPrefs.getInt("session_current_page", 0) + 1;
                lastPage = sessionPrefs.getInt("session_last_page", 1);
                lastSyncDate = sessionPrefs.getString("session_last_sync_date", null);
                expectedTotal = sessionPrefs.getInt("session_expected_total", 0);
                isFullSync = (lastSyncDate == null);
                Log.i(TAG, "Resuming interrupted sync: page=" + page + "/" + lastPage + ", isFullSync=" + isFullSync);
            } else {
                expectedTotal = serverTotalCount;
                if (localCountBefore == 0 || serverTotalCount == -1 || localCountBefore < serverTotalCount) {
                    lastSyncDate = null;
                    isFullSync = true;
                    page = 1;
                    Log.i(TAG, "New FULL sync from page 1 (localCount=" + localCountBefore + ")");
                } else {
                    lastSyncDate = dbHelper.getMaxUpdatedAt();
                    isFullSync = false;
                    page = 1;
                    Log.i(TAG, "New INCREMENTAL sync from " + lastSyncDate);
                }

                sessionPrefs.edit()
                    .putBoolean("session_active", true)
                    .putInt("session_current_page", page - 1)
                    .putInt("session_last_page", 1)
                    .putString("session_last_sync_date", lastSyncDate)
                    .putInt("session_expected_total", expectedTotal)
                    .apply();
            }

            int totalSponsorshipsSynced = 0;
            boolean failedMidSession = false;

            while (page <= lastPage) {
                try {
                    String currentUrlStr = urlStr + "?per_page=200&page=" + page;
                    if (lastSyncDate != null) {
                        currentUrlStr += "&last_sync=" + java.net.URLEncoder.encode(lastSyncDate, "UTF-8");
                    }
                    String resp = fetchUrl(currentUrlStr, token);
                    JSONObject json = new JSONObject(resp);

                    JSONObject pagination = json.optJSONObject("pagination");
                    if (pagination != null) {
                        lastPage = pagination.optInt("last_page", 1);
                        if (isFullSync) expectedTotal = pagination.optInt("total", expectedTotal);
                    } else {
                        JSONObject meta = json.has("meta") ? json.optJSONObject("meta") : json;
                        if (meta != null) {
                            lastPage = meta.optInt("last_page", 1);
                            if (isFullSync) expectedTotal = meta.optInt("total", expectedTotal);
                        }
                    }

                    JSONArray dataArray = json.optJSONArray("data");
                    if (dataArray == null && json.has("sponsorships")) {
                        dataArray = json.optJSONArray("sponsorships");
                    }

                    if (dataArray != null && dataArray.length() > 0) {
                        java.util.Set<Integer> currentPendingIds = syncDbHelper.getPendingEntityIds();
                        synchronized (SponsorshipsDatabaseHelper.class) {
                            dbHelper.saveBatchSponsorships(dataArray, currentPendingIds);
                        }
                        totalSponsorshipsSynced += dataArray.length();

                        int percentage = (int) (((float) page / lastPage) * 100);
                        org.alhayah.sponsorships.BackgroundSyncPlugin.emitProgress(percentage, "جاري المزامنة... " + percentage + "%", totalSponsorshipsSynced);
                        Log.i(TAG, "Page " + page + "/" + lastPage + " synced. " + dataArray.length() + " items.");
                    }

                    sessionPrefs.edit()
                        .putInt("session_current_page", page)
                        .putInt("session_last_page", lastPage)
                        .putInt("session_expected_total", expectedTotal)
                        .apply();

                    page++;
                } catch (Exception e) {
                    Log.e(TAG, "Exception during sync of page " + page, e);
                    failedMidSession = true;
                    break;
                }
            }

            if (failedMidSession) {
                Log.w(TAG, "Sync interrupted. Retrying via WorkManager.");
                return Result.retry();
            }

            // 4. Validation
            int localCountAfter = dbHelper.getCount();
            Log.i(TAG, "Sync completed. LocalBefore=" + localCountBefore + ", LocalAfter=" + localCountAfter + ", Expected=" + expectedTotal);

            if (isFullSync && expectedTotal > 0 && localCountAfter < expectedTotal) {
                Log.w(TAG, "Validation failed! Local (" + localCountAfter + ") < Server (" + expectedTotal + "). Retrying.");
                return Result.retry();
            }

            // 5. Sync related tables
            try {
                syncRelatedTables(dbHelper, token, baseUrl);
            } catch (Exception e) {
                Log.e(TAG, "Failed to sync related tables", e);
            }

            sessionPrefs.edit().clear().apply();

            Log.i(TAG, "Resilient Sync Session Completed and Verified Successfully!");
            org.alhayah.sponsorships.BackgroundSyncPlugin.emitFinished();
            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "Sync crash", e);
            if (getRunAttemptCount() < 3) return Result.retry();
            return Result.failure();
        }
    }

    private String fetchUrl(String urlStr, String token) throws Exception {
        HttpURLConnection conn = null;
        BufferedReader reader = null;
        try {
            URL url = new URL(urlStr);
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Bypass-Tunnel-Reminder", "true");
            conn.setConnectTimeout(60000);
            conn.setReadTimeout(60000);
            int code = conn.getResponseCode();
            if (code == 401) throw new SecurityException("Unauthorized");
            if (code != 200) throw new Exception("HTTP " + code);
            reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) sb.append(line);
            return sb.toString();
        } finally {
            if (reader != null) try { reader.close(); } catch (Exception ignored) {}
            if (conn != null) conn.disconnect();
        }
    }

    private void syncRelatedTables(SponsorshipsDatabaseHelper sponsorshipsDb, String token, String baseUrl) {
        com.aso.app.RelatedDataDatabaseHelper relatedDb = com.aso.app.RelatedDataDatabaseHelper.getInstance(getApplicationContext());
        SharedPreferences prefs = getApplicationContext().getSharedPreferences("related_sync_prefs", Context.MODE_PRIVATE);
        java.text.SimpleDateFormat iso = new java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US);

        String[][] tables = {
                {"data-table", "data_table"},
                {"re-people", "re_people"},
                {"dead-people", "dead_people"},
                {"additional-deceased", "additional_deceased"},
                {"bank-accounts", "guardian_bank_accounts"},
                {"death-reasons", "death_reasons"}
        };

        for (String[] table : tables) {
            String endpoint = table[0];
            String tableName = table[1];
            int perPage = 200;
            int page = 1;
            int lastPage = 1;
            int totalSynced = 0;
            String lastSync = prefs.getString("last_sync_" + tableName, null);
            boolean isFirstSync = (lastSync == null);

            while (page <= lastPage) {
                try {
                    String urlStr = baseUrl + "/api/mobile/sync/" + endpoint + "?per_page=" + perPage + "&page=" + page;
                    if (lastSync != null) urlStr += "&last_sync=" + java.net.URLEncoder.encode(lastSync, "UTF-8");
                    String resp = fetchUrl(urlStr, token);
                    JSONObject json = new JSONObject(resp);
                    JSONArray data = json.optJSONArray("data");
                    JSONObject pagination = json.optJSONObject("pagination");

                    if (pagination != null) {
                        lastPage = pagination.optInt("last_page", 1);
                        int serverTotal = pagination.optInt("total", 0);
                        if (page == 1 && serverTotal == 0 && lastSync != null) break;
                        if (page == 1 && serverTotal > 0 && lastSync == null) {
                            int localCount = relatedDb.getCount(tableName);
                            if (localCount >= serverTotal) {
                                prefs.edit().putString("last_sync_" + tableName, iso.format(new java.util.Date())).apply();
                                break;
                            }
                        }
                    }

                    if (data != null && data.length() > 0) {
                        relatedDb.saveBatch(tableName, data);
                        totalSynced += data.length();
                    }
                    page++;
                } catch (Exception e) {
                    Log.w(TAG, "Error syncing " + tableName + " page " + page + ": " + e.getMessage());
                    break;
                }
            }

            if (isFirstSync || totalSynced > 0) {
                prefs.edit().putString("last_sync_" + tableName, iso.format(new java.util.Date())).apply();
            }
            Log.i(TAG, tableName + " synced: " + totalSynced + " records");
        }
    }
}
