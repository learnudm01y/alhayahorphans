package org.alhayah.sponsorships;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Build;
import android.os.IBinder;
import android.os.PowerManager;
import android.util.Log;

import androidx.core.app.NotificationCompat;

import com.aso.app.ApiConfig;
import com.aso.app.MainActivity;
import com.aso.app.R;
import com.aso.app.SponsorshipsDatabaseHelper;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicInteger;

public class DownloadForegroundService extends Service {
    private static final String TAG = "DownloadForegroundSvc";
    private static final int NOTIFICATION_ID = 8888;
    private static final String CHANNEL_ID = "download_sync_foreground_channel";
    private static final String CHANNEL_NAME = "مزامنة الكفالات من السيرفر";

    private PowerManager.WakeLock wakeLock;
    private volatile boolean isRunning = false;
    private Thread syncThread;
    private NotificationManager notificationManager;
    private NotificationCompat.Builder notificationBuilder;

    public static void startDownload(Context context) {
        startDownload(context, 3);
    }

    public static void startDownload(Context context, int workers) {
        Intent intent = new Intent(context, DownloadForegroundService.class);
        intent.putExtra("workers", workers);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            context.startForegroundService(intent);
        } else {
            context.startService(intent);
        }
    }


    @Override
    public void onCreate() {
        super.onCreate();
        notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        createNotificationChannel();

        PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);
        wakeLock = powerManager.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "ASO:DownloadSyncWakeLock");
        wakeLock.acquire(60 * 60 * 1000L);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        startForegroundWithNotification();
        int workers = intent != null ? intent.getIntExtra("workers", 3) : 3;
        if (workers < 1) workers = 1;
        if (workers > 6) workers = 6;
        final int w = workers;
        if (!isRunning) {
            isRunning = true;
            syncThread = new Thread(() -> performDownload(w));
            syncThread.start();
        }
        return START_STICKY;
    }

    private void startForegroundWithNotification() {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 0, notificationIntent, PendingIntent.FLAG_IMMUTABLE);

        notificationBuilder = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setContentTitle("تحديث بيانات الكفالات")
                .setContentText("جاري التحضير...")
                .setSmallIcon(R.mipmap.ic_launcher)
                .setProgress(100, 0, true)
                .setContentIntent(pendingIntent)
                .setOngoing(true);

        Notification notification = notificationBuilder.build();
        try {
            if (Build.VERSION.SDK_INT >= 34) {
                startForeground(NOTIFICATION_ID, notification, android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC);
            } else {
                startForeground(NOTIFICATION_ID, notification);
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed startForeground", e);
            stopSelf();
        }
    }

    private void updateNotification(String text, int progress, int max) {
        if (notificationBuilder != null && notificationManager != null) {
            notificationBuilder.setContentText(text);
            notificationBuilder.setProgress(max, progress, false);
            notificationManager.notify(NOTIFICATION_ID, notificationBuilder.build());
        }
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel serviceChannel = new NotificationChannel(
                    CHANNEL_ID,
                    CHANNEL_NAME,
                    NotificationManager.IMPORTANCE_LOW
            );
            if (notificationManager != null) {
                notificationManager.createNotificationChannel(serviceChannel);
            }
        }
    }

    private void broadcastProgress(int progress, String message, int total) {
        BackgroundSyncPlugin.emitProgress(progress, message, total); // Keep legacy
        // Broadcast intent for reliable delivery across webviews
        Intent intent = new Intent("com.aso.app.SYNC_PROGRESS");
        intent.putExtra("progress", progress);
        intent.putExtra("message", message);
        intent.putExtra("total", total);
        sendBroadcast(intent);
    }

    private void performDownload(int workersParam) {
        Log.i(TAG, "Starting real sponsorship sync with " + workersParam + " workers");
        int workers = Math.min(Math.max(workersParam, 1), 6);
        try {
            SharedPreferences prefs = getApplicationContext().getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
            String token = prefs.getString("api_token", "");
            if (token.isEmpty()) {
                stopSelf();
                return;
            }

            String baseUrl = ApiConfig.getBaseUrl(getApplicationContext());
            if (baseUrl == null || baseUrl.isEmpty()) {
                stopSelf();
                return;
            }
            if (baseUrl.endsWith("/")) {
                baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
            }

            String initialUrlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/initial" : baseUrl + "/api/mobile/sync/initial";
            String urlStr = baseUrl.endsWith("/api") ? baseUrl + "/mobile/sync/sponsorships" : baseUrl + "/api/mobile/sync/sponsorships";

            SponsorshipsDatabaseHelper dbHelper = SponsorshipsDatabaseHelper.getInstance(getApplicationContext());
            DataSyncDatabaseHelper syncDbHelper = DataSyncDatabaseHelper.getInstance(getApplicationContext());
            java.util.Set<Integer> pendingIds = syncDbHelper.getPendingEntityIds();

            broadcastProgress(5, "Fetching base data...", 0);
            updateNotification("Fetching base data...", 5, 100);

            try {
                URL initialUrl = new URL(initialUrlStr);
                HttpURLConnection connInit = (HttpURLConnection) initialUrl.openConnection();
                connInit.setRequestMethod("GET");
                connInit.setRequestProperty("Authorization", "Bearer " + token);
                connInit.setRequestProperty("Accept", "application/json");
                connInit.setConnectTimeout(60000);
                connInit.setReadTimeout(60000);

                int initResponseCode = connInit.getResponseCode();
                if (initResponseCode == 200) {
                    BufferedReader reader = new BufferedReader(new InputStreamReader(connInit.getInputStream()));
                    StringBuilder response = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) response.append(line);
                    reader.close();

                    Log.d(TAG, "Initial lookups sync response: " + response.toString().substring(0, Math.min(200, response.length())));

                    JSONObject initJson = new JSONObject(response.toString());
                    if (initJson.optBoolean("success", false)) {
                        JSONObject data = initJson.optJSONObject("data");
                        if (data != null) {
                            if (data.has("sponsors")) dbHelper.saveLookup("sponsors", data.optJSONArray("sponsors") != null ? data.getJSONArray("sponsors").toString() : "[]");
                            if (data.has("sponsorship_statuses")) dbHelper.saveLookup("sponsorship_statuses", data.optJSONArray("sponsorship_statuses") != null ? data.getJSONArray("sponsorship_statuses").toString() : "[]");
                            if (data.has("bank_names")) dbHelper.saveLookup("bank_names", data.optJSONArray("bank_names") != null ? data.getJSONArray("bank_names").toString() : "[]");
                            if (data.has("health_statuses")) dbHelper.saveLookup("health_statuses", data.optJSONArray("health_statuses") != null ? data.getJSONArray("health_statuses").toString() : "[]");
                            if (data.has("cities")) dbHelper.saveLookup("cities", data.optJSONArray("cities") != null ? data.getJSONArray("cities").toString() : "[]");
                            if (data.has("sponsorship_types")) dbHelper.saveLookup("sponsorship_types", data.optJSONArray("sponsorship_types") != null ? data.getJSONArray("sponsorship_types").toString() : "[]");
                            if (data.has("valid_sponsorship_ids")) {
                                dbHelper.syncValidSponsorships(data.optJSONArray("valid_sponsorship_ids"));
                            }
                            Log.d(TAG, "Lookups & Pruning completed.");
                        }
                    }
                } else {
                    Log.e(TAG, "Initial lookups failed: " + initResponseCode);
                }
            } catch (Exception e) {
                Log.e(TAG, "Exception during initial lookups", e);
            }

            String lastSyncDate = dbHelper.getMaxUpdatedAt();
            AtomicInteger totalSponsorshipsSynced = new AtomicInteger(0);
            AtomicInteger pagesCompleted = new AtomicInteger(0);

            // Fetch first page synchronously to learn lastPage
            int lastPage = 1;
            boolean firstPageDone = false;
            int retryCount = 0;
            final int MAX_RETRIES = 5;
            while (!firstPageDone && retryCount < MAX_RETRIES && isRunning) {
                try {
                    String currentUrlStr = urlStr + "?per_page=200&page=1";
                    if (lastSyncDate != null) {
                        currentUrlStr += "&last_sync=" + java.net.URLEncoder.encode(lastSyncDate, "UTF-8");
                    }
                    URL url = new URL(currentUrlStr);
                    HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                    conn.setRequestMethod("GET");
                    conn.setRequestProperty("Authorization", "Bearer " + token);
                    conn.setRequestProperty("Accept", "application/json");
                    conn.setConnectTimeout(60000);
                    conn.setReadTimeout(60000);
                    int responseCode = conn.getResponseCode();
                    if (responseCode == 200) {
                        BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                        StringBuilder response = new StringBuilder();
                        String line;
                        while ((line = reader.readLine()) != null) response.append(line);
                        reader.close();
                        JSONObject json = new JSONObject(response.toString());
                        JSONObject pagination = json.optJSONObject("pagination");
                        if (pagination != null) {
                            lastPage = pagination.optInt("last_page", 1);
                        } else {
                            JSONObject meta = json.has("meta") ? json.optJSONObject("meta") : json;
                            if (meta != null) lastPage = meta.optInt("last_page", 1);
                        }
                        JSONArray dataArray = json.optJSONArray("data");
                        if (dataArray == null) dataArray = json.optJSONArray("sponsorships");
                        if (dataArray != null && dataArray.length() > 0) {
                            synchronized (dbHelper) { dbHelper.saveBatchSponsorships(dataArray, pendingIds); }
                            totalSponsorshipsSynced.addAndGet(dataArray.length());
                        }
                        pagesCompleted.incrementAndGet();
                        int pct = lastPage > 0 ? (int)(((float)pagesCompleted.get() / lastPage)*100) : 100;
                        broadcastProgress(pct, "Syncing... " + pct + "% [" + workers + " workers]", totalSponsorshipsSynced.get());
                        updateNotification("Loading data (1/" + lastPage + ")...", pct, 100);
                        firstPageDone = true;
                    } else if (responseCode == 401) {
                        Log.e(TAG, "Unauthorized");
                        break;
                    } else {
                        retryCount++;
                        if (retryCount >= MAX_RETRIES) break;
                        Thread.sleep(3000);
                    }
                } catch (Exception e) {
                    Log.e(TAG, "Exception on page 1", e);
                    retryCount++;
                    if (retryCount >= MAX_RETRIES) break;
                    try { Thread.sleep(3000); } catch (InterruptedException ie) { break; }
                }
            }

            if (!firstPageDone) {
                Log.e(TAG, "Failed to fetch first page, aborting");
            } else if (lastPage > 1 && isRunning) {
                final int finalLastPage = lastPage;
                final int finalWorkers = workers;
                int remaining = finalLastPage - 1;
                ExecutorService executor = Executors.newFixedThreadPool(Math.min(finalWorkers, remaining));
                CountDownLatch latch = new CountDownLatch(remaining);
                final String fLastSync = lastSyncDate;
                final String fUrlStr = urlStr;
                final String fToken = token;
                for (int p = 2; p <= finalLastPage; p++) {
                    final int pageNum = p;
                    executor.submit(() -> {
                        int rc = 0;
                        while (rc < MAX_RETRIES && isRunning) {
                            try {
                                String curUrl = fUrlStr + "?per_page=200&page=" + pageNum;
                                if (fLastSync != null) curUrl += "&last_sync=" + java.net.URLEncoder.encode(fLastSync, "UTF-8");
                                URL url = new URL(curUrl);
                                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                                conn.setRequestMethod("GET");
                                conn.setRequestProperty("Authorization", "Bearer " + fToken);
                                conn.setRequestProperty("Accept", "application/json");
                                conn.setConnectTimeout(60000);
                                conn.setReadTimeout(60000);
                                int code = conn.getResponseCode();
                                if (code == 200) {
                                    BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                                    StringBuilder resp = new StringBuilder();
                                    String line;
                                    while ((line = reader.readLine()) != null) resp.append(line);
                                    reader.close();
                                    JSONObject json = new JSONObject(resp.toString());
                                    JSONArray dataArray = json.optJSONArray("data");
                                    if (dataArray == null) dataArray = json.optJSONArray("sponsorships");
                                    if (dataArray != null && dataArray.length() > 0) {
                                        synchronized (dbHelper) { dbHelper.saveBatchSponsorships(dataArray, pendingIds); }
                                        totalSponsorshipsSynced.addAndGet(dataArray.length());
                                    }
                                    int done = pagesCompleted.incrementAndGet();
                                    int pct = (int)(((float)done / finalLastPage)*100);
                                    broadcastProgress(pct, "Syncing... " + pct + "% [" + finalWorkers + " workers]", totalSponsorshipsSynced.get());
                                    updateNotification("Loading data (" + done + "/" + finalLastPage + ")...", pct, 100);
                                    break;
                                } else if (code == 401) {
                                    break;
                                } else {
                                    rc++;
                                    if (rc >= MAX_RETRIES) break;
                                    Thread.sleep(3000);
                                }
                            } catch (Exception e) {
                                Log.e(TAG, "Exception on page " + pageNum, e);
                                rc++;
                                if (rc >= MAX_RETRIES) break;
                                try { Thread.sleep(3000); } catch (InterruptedException ie) { break; }
                            }
                        }
                        latch.countDown();
                    });
                }
                latch.await();
                executor.shutdown();
            }

            // تحميل الجداول المرتبطة
            if (isRunning) {
                downloadRelatedTables(dbHelper, token, baseUrl);
            }

            BackgroundSyncPlugin.emitFinished();
            Intent intent = new Intent("com.aso.app.SYNC_PROGRESS");
            intent.putExtra("progress", 100);
            intent.putExtra("message", "Complete");
            intent.putExtra("total", totalSponsorshipsSynced.get());
            sendBroadcast(intent);

            updateNotification("Sync completed", 100, 100);
            Thread.sleep(2000);
        } catch (Exception e) {
            Log.e(TAG, "Sync failed", e);
        } finally {
            stopSelf();
        }
    }

    private void performDownload() {
        performDownload(3);
    }

    /**
     * تحميل جميع الجداول المرتبطة — يكمل من حيث توقف (يتخطى الصفحات المحملة)
     */
    private void downloadRelatedTables(SponsorshipsDatabaseHelper sponsorshipsDb, String token, String baseUrl) {
        com.aso.app.RelatedDataDatabaseHelper relatedDb = com.aso.app.RelatedDataDatabaseHelper.getInstance(getApplicationContext());
        SharedPreferences prefs = getApplicationContext().getSharedPreferences("related_sync_prefs", Context.MODE_PRIVATE);
        java.text.SimpleDateFormat iso = new java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US);

        String[][] tables = {
                {"data-table", "data_table"},
                {"re-people", "re_people"},
                {"dead-people", "dead_people"},
                {"bank-accounts", "guardian_bank_accounts"},
                {"death-reasons", "death_reasons"}
        };

        for (String[] table : tables) {
            if (!isRunning) break;
            String endpoint = table[0];
            String tableName = table[1];

            updateNotification("Checking " + tableName + "...", 0, 100);
            broadcastProgress(0, "Checking " + tableName, 0);

            int perPage = 200;
            int page = 1;
            int lastPage = 1;
            int totalSynced = 0;
            int maxRetries = 3;
            String lastSync = prefs.getString("last_sync_" + tableName, null);
            boolean isFirstSync = (lastSync == null);

            while (page <= lastPage && isRunning) {
                int retries = 0;
                boolean pageDone = false;

                while (retries < maxRetries && !pageDone && isRunning) {
                    HttpURLConnection conn = null;
                    try {
                        String urlStr = baseUrl + "/api/mobile/sync/" + endpoint + "?per_page=" + perPage + "&page=" + page;
                        if (lastSync != null) {
                            urlStr += "&last_sync=" + java.net.URLEncoder.encode(lastSync, "UTF-8");
                        }
                        URL url = new URL(urlStr);
                        conn = (HttpURLConnection) url.openConnection();
                        conn.setRequestMethod("GET");
                        conn.setRequestProperty("Authorization", "Bearer " + token);
                        conn.setRequestProperty("Accept", "application/json");
                        conn.setConnectTimeout(60000);
                        conn.setReadTimeout(60000);

                        int code = conn.getResponseCode();
                        if (code == 200) {
                            BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                            StringBuilder resp = new StringBuilder();
                            String line;
                            while ((line = reader.readLine()) != null) resp.append(line);
                            reader.close();

                            JSONObject json = new JSONObject(resp.toString());
                            JSONArray data = json.optJSONArray("data");
                            JSONObject pagination = json.optJSONObject("pagination");

                            if (pagination != null) {
                                lastPage = pagination.optInt("last_page", 1);
                                int serverTotal = pagination.optInt("total", 0);

                                if (page == 1 && serverTotal == 0 && lastSync != null) {
                                    Log.d(TAG, tableName + " no new records — skipping");
                                    page = lastPage + 1;
                                    pageDone = true;
                                    break;
                                }

                                if (page == 1 && serverTotal > 0 && lastSync == null) {
                                    int localCount = relatedDb.getCount(tableName);
                                    if (localCount >= serverTotal) {
                                        Log.d(TAG, tableName + " complete (" + localCount + "/" + serverTotal + ") — skipping");
                                        prefs.edit().putString("last_sync_" + tableName, iso.format(new java.util.Date())).apply();
                                        page = lastPage + 1;
                                        pageDone = true;
                                        break;
                                    }
                                }
                            }

                            if (data != null && data.length() > 0) {
                                relatedDb.saveBatch(tableName, data);
                                totalSynced += data.length();
                            }

                            int pct = lastPage > 0 ? (int)(((float)page / lastPage) * 100) : 100;
                            broadcastProgress(pct, "Loading " + tableName + " (" + totalSynced + ")", totalSynced);
                            updateNotification(tableName + " (" + page + "/" + lastPage + ")", pct, 100);

                            pageDone = true;
                            page++;
                        } else if (code == 401) {
                            Log.w(TAG, "401 on " + tableName + " page " + page + " — stopping");
                            pageDone = true;
                            page = lastPage + 1;
                        } else {
                            retries++;
                            Log.w(TAG, "HTTP " + code + " on " + tableName + " page " + page + " (retry " + retries + "/" + maxRetries + ")");
                            if (retries < maxRetries) Thread.sleep(3000);
                            else { page++; pageDone = true; }
                        }
                    } catch (Exception e) {
                        retries++;
                        Log.w(TAG, "Error on " + tableName + " page " + page + ": " + e.getMessage() + " (retry " + retries + "/" + maxRetries + ")");
                        if (retries < maxRetries) {
                            try { Thread.sleep(5000); } catch (InterruptedException ignored) {}
                        } else { page++; pageDone = true; }
                    } finally {
                        if (conn != null) conn.disconnect();
                    }
                }
            }

            if (isFirstSync || totalSynced > 0) {
                prefs.edit().putString("last_sync_" + tableName, iso.format(new java.util.Date())).apply();
            }
            Log.d(TAG, tableName + " done: synced " + totalSynced + " records" + (isFirstSync ? " (full)" : " (incremental)"));
        }

        updateNotification("Related tables sync completed", 100, 100);
    }


        @Override
    public void onDestroy() {
        super.onDestroy();
        isRunning = false;
        if (wakeLock != null && wakeLock.isHeld()) {
            try {
                wakeLock.release();
            } catch (Exception ignored) {}
        }
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
