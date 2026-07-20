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
            // Remove trailing slash if any
            if (baseUrl.endsWith("/")) {
                baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
            }
            
            // The Laravel routes
            String initialUrlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/initial" : baseUrl + "/api/mobile/sync/initial";
            String urlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/sponsorships" : baseUrl + "/api/mobile/sync/sponsorships";
            
            SponsorshipsDatabaseHelper dbHelper = SponsorshipsDatabaseHelper.getInstance(getApplicationContext());
            org.alhayah.sponsorships.DataSyncDatabaseHelper syncDbHelper = org.alhayah.sponsorships.DataSyncDatabaseHelper.getInstance(getApplicationContext());
            java.util.Set<Integer> pendingIds = syncDbHelper.getPendingEntityIds();

            // 1. Fetch and save lookups (Initial Sync)
            Log.i(TAG, "Fetching initial lookups");
            org.alhayah.sponsorships.BackgroundSyncPlugin.emitProgress(5, "جاري جلب البيانات الأساسية من السيرفر...", 0);
            try {
                URL initialUrl = new URL(initialUrlStr);
                HttpURLConnection connInit = (HttpURLConnection) initialUrl.openConnection();
                connInit.setRequestMethod("GET");
                connInit.setRequestProperty("Authorization", "Bearer " + token);
                connInit.setRequestProperty("Accept", "application/json");
                connInit.setRequestProperty("Bypass-Tunnel-Reminder", "true");
                connInit.setConnectTimeout(60000);
                connInit.setReadTimeout(60000);
                
                if (connInit.getResponseCode() == 200) {
                    BufferedReader reader = new BufferedReader(new InputStreamReader(connInit.getInputStream()));
                    StringBuilder response = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) response.append(line);
                    reader.close();
                    
                    JSONObject initJson = new JSONObject(response.toString());
                    if (initJson.has("success") && initJson.getBoolean("success")) {
                        JSONObject data = initJson.optJSONObject("data");
                        if (data != null) {
                            if (data.has("sponsors")) dbHelper.saveLookup("sponsors", data.getJSONArray("sponsors").toString());
                            if (data.has("sponsorship_statuses")) dbHelper.saveLookup("sponsorship_statuses", data.getJSONArray("sponsorship_statuses").toString());
                            if (data.has("bank_names")) dbHelper.saveLookup("bank_names", data.getJSONArray("bank_names").toString());
                            if (data.has("health_statuses")) dbHelper.saveLookup("health_statuses", data.getJSONArray("health_statuses").toString());
                            if (data.has("cities")) dbHelper.saveLookup("cities", data.getJSONArray("cities").toString());
                            if (data.has("sponsorship_types")) dbHelper.saveLookup("sponsorship_types", data.getJSONArray("sponsorship_types").toString());
                            Log.i(TAG, "Successfully saved initial lookups");
                        }
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to fetch initial lookups", e);
            }

            // 2. Fetch Server Stats First (Validation Check)
            Log.i(TAG, "Fetching server sync stats");
            int serverTotalCount = -1;
            String statsUrlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/stats" : baseUrl + "/api/mobile/sync/stats";
            try {
                URL statsUrl = new URL(statsUrlStr);
                HttpURLConnection connStats = (HttpURLConnection) statsUrl.openConnection();
                connStats.setRequestMethod("GET");
                connStats.setRequestProperty("Authorization", "Bearer " + token);
                connStats.setRequestProperty("Accept", "application/json");
                connStats.setRequestProperty("Bypass-Tunnel-Reminder", "true");
                connStats.setConnectTimeout(30000);
                connStats.setReadTimeout(30000);

                if (connStats.getResponseCode() == 200) {
                    BufferedReader reader = new BufferedReader(new InputStreamReader(connStats.getInputStream()));
                    StringBuilder response = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) response.append(line);
                    reader.close();

                    JSONObject statsJson = new JSONObject(response.toString());
                    if (statsJson.optBoolean("success", false)) {
                        JSONObject dataObj = statsJson.optJSONObject("data");
                        if (dataObj != null) {
                            serverTotalCount = dataObj.optInt("total_sponsorships", -1);
                            Log.i(TAG, "Server total sponsorships count: " + serverTotalCount);
                        }
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to fetch server stats", e);
            }

            // 3. Resilient Session Management (Save/Resume Interrupted Sync Sessions)
            SharedPreferences sessionPrefs = getApplicationContext().getSharedPreferences("sync_session_prefs", Context.MODE_PRIVATE);
            boolean sessionActive = sessionPrefs.getBoolean("session_active", false);

            int page = 1;
            int lastPage = 1;
            String lastSyncDate = null;
            int expectedTotal = 0;
            boolean isFullSync = true;
            int localCountBefore = dbHelper.getCount();

            if (sessionActive) {
                // Resume interrupted session
                page = sessionPrefs.getInt("session_current_page", 0) + 1;
                lastPage = sessionPrefs.getInt("session_last_page", 1);
                lastSyncDate = sessionPrefs.getString("session_last_sync_date", null);
                expectedTotal = sessionPrefs.getInt("session_expected_total", 0);
                isFullSync = (lastSyncDate == null);
                Log.i(TAG, "🔄 Resuming interrupted sync session: page=" + page + "/" + lastPage + ", isFullSync=" + isFullSync);
            } else {
                // Start new session
                expectedTotal = serverTotalCount;

                // Check if database is incomplete - if so, force a full sync to recover missing items
                if (localCountBefore == 0 || serverTotalCount == -1 || localCountBefore < serverTotalCount) {
                    lastSyncDate = null;
                    isFullSync = true;
                    
                    // Failsafe resume: calculate estimated page based on existing rows count (200 records per page)
                    int estimatedPage = (localCountBefore / 200) + 1;
                    if (estimatedPage > 1) {
                        page = estimatedPage;
                        Log.i(TAG, "🆕 Starting new FULL sync session, estimated starting page: " + page + " (localCount=" + localCountBefore + ")");
                    } else {
                        page = 1;
                        Log.i(TAG, "🆕 Starting new FULL sync session from page 1 (localCount=" + localCountBefore + ")");
                    }
                } else {
                    lastSyncDate = dbHelper.getMaxUpdatedAt();
                    isFullSync = false;
                    page = 1;
                    Log.i(TAG, "🆕 Starting new INCREMENTAL sync session from " + lastSyncDate);
                }

                // Initialize state in SharedPreferences
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
                    URL url = new URL(currentUrlStr);
                    HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                    conn.setRequestMethod("GET");
                    conn.setRequestProperty("Authorization", "Bearer " + token);
                    conn.setRequestProperty("Accept", "application/json");
                    conn.setRequestProperty("Bypass-Tunnel-Reminder", "true");
                    conn.setConnectTimeout(30000);
                    conn.setReadTimeout(30000);

                    int responseCode = conn.getResponseCode();

                    if (responseCode == 200) {
                        BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                        StringBuilder response = new StringBuilder();
                        String line;
                        while ((line = reader.readLine()) != null) response.append(line);
                        reader.close();

                        JSONObject json = new JSONObject(response.toString());

                        // Laravel Pagination Parser
                        JSONObject pagination = json.optJSONObject("pagination");
                        if (pagination != null) {
                            lastPage = pagination.optInt("last_page", 1);
                            if (isFullSync) {
                                expectedTotal = pagination.optInt("total", expectedTotal);
                            }
                        } else {
                            JSONObject meta = json.has("meta") ? json.optJSONObject("meta") : json;
                            if (meta != null) {
                                lastPage = meta.optInt("last_page", 1);
                                if (isFullSync) {
                                    expectedTotal = meta.optInt("total", expectedTotal);
                                }
                            }
                        }

                        JSONArray dataArray = json.optJSONArray("data");
                        if (dataArray == null && json.has("sponsorships")) {
                            dataArray = json.optJSONArray("sponsorships");
                        }

                        if (dataArray != null && dataArray.length() > 0) {
                            java.util.Set<Integer> currentPendingIds = syncDbHelper.getPendingEntityIds();
                            dbHelper.saveBatchSponsorships(dataArray, currentPendingIds);
                            totalSponsorshipsSynced += dataArray.length();

                            // Broadcast progress to user interface
                            int percentage = (int) (((float) page / lastPage) * 100);
                            org.alhayah.sponsorships.BackgroundSyncPlugin.emitProgress(percentage, "جاري المزامنة... " + percentage + "%", totalSponsorshipsSynced);

                            Log.i(TAG, "✅ Page " + page + "/" + lastPage + " synced successfully. " + dataArray.length() + " items saved.");
                        }

                        // Save current completed page state to resume if connection drops next page
                        sessionPrefs.edit()
                            .putInt("session_current_page", page)
                            .putInt("session_last_page", lastPage)
                            .putInt("session_expected_total", expectedTotal)
                            .apply();

                        page++;
                    } else if (responseCode == 401) {
                        Log.e(TAG, "Unauthorized - token expired");
                        return Result.failure();
                    } else {
                        Log.e(TAG, "Sync failed with code: " + responseCode + " on page " + page);
                        failedMidSession = true;
                        break;
                    }
                } catch (Exception e) {
                    Log.e(TAG, "❌ Exception during sync of page " + page, e);
                    failedMidSession = true;
                    break;
                }
            }

            if (failedMidSession) {
                Log.w(TAG, "⚠️ Sync interrupted due to error/disconnect. Retrying sequentially via WorkManager.");
                return Result.retry();
            }

            // 4. Verification Check: Compare Local Count vs Server Count (Only for full sync verification)
            int localCountAfter = dbHelper.getCount();
            Log.i(TAG, "🎉 Sync loop completed successfully. LocalCountBefore=" + localCountBefore + 
                        ", LocalCountAfter=" + localCountAfter + ", ExpectedTotal=" + expectedTotal);

            if (isFullSync && expectedTotal > 0 && localCountAfter < expectedTotal) {
                Log.w(TAG, "⚠️ Validation failed! Local database count (" + localCountAfter + 
                           ") is less than expected server total (" + expectedTotal + "). Retrying sync.");
                return Result.retry();
            }

            // Clean session stats only upon complete success and validation
            sessionPrefs.edit().clear().apply();

            Log.i(TAG, "🎉 Resilient Sync Session Completed and Verified Successfully!");
            org.alhayah.sponsorships.BackgroundSyncPlugin.emitFinished();
            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "Sync crash", e);
            if (getRunAttemptCount() < 3) {
                return Result.retry();
            }
            return Result.failure();
        }
    }
}
