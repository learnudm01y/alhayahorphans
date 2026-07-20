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
        Intent intent = new Intent(context, DownloadForegroundService.class);
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

        if (!isRunning) {
            isRunning = true;
            syncThread = new Thread(this::performDownload);
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

    private void performDownload() {
        Log.i(TAG, "Starting real sponsorship sync");
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

            broadcastProgress(5, "جاري جلب البيانات الأساسية من السيرفر...", 0);
            updateNotification("جاري جلب البيانات الأساسية...", 5, 100);

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
                            
                            // 🧹 Prune deleted sponsorships (Hard-Delete Sync)
                            if (data.has("valid_sponsorship_ids")) {
                                dbHelper.syncValidSponsorships(data.optJSONArray("valid_sponsorship_ids"));
                            }
                            
                            Log.d(TAG, "✅ Lookups & Pruning successfully completed.");
                        } else {
                            Log.e(TAG, "Initial lookups sync success, but data object is null");
                        }
                    } else {
                        Log.e(TAG, "Initial lookups sync failed: success=false in JSON");
                    }
                } else {
                    Log.e(TAG, "Initial lookups sync failed with HTTP Code: " + initResponseCode);
                    java.io.InputStream errorStream = connInit.getErrorStream();
                    if (errorStream != null) {
                        BufferedReader reader = new BufferedReader(new InputStreamReader(errorStream));
                        StringBuilder errorResp = new StringBuilder();
                        String line;
                        while ((line = reader.readLine()) != null) errorResp.append(line);
                        reader.close();
                        Log.e(TAG, "Initial lookups sync error response: " + errorResp.toString());
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Exception during initial lookups", e);
            }

            String lastSyncDate = dbHelper.getMaxUpdatedAt();
            int page = 1;
            int lastPage = 1;
            int totalSponsorshipsSynced = 0;
            int retryCount = 0;
            final int MAX_RETRIES = 5;

            while (page <= lastPage && isRunning) {
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
                    conn.setConnectTimeout(60000); // زيادة وقت الانتظار لـ 60 ثانية لتجاوز مشاكل السيرفر
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
                            dbHelper.saveBatchSponsorships(dataArray, pendingIds);
                            totalSponsorshipsSynced += dataArray.length();

                            int percentage = (int) (((float) page / lastPage) * 100);
                            broadcastProgress(percentage, "جاري المزامنة... " + percentage + "%", totalSponsorshipsSynced);
                            updateNotification("جاري تحميل البيانات (" + page + "/" + lastPage + ")...", percentage, 100);
                        }
                        page++;
                        retryCount = 0; // تصفير العداد عند النجاح
                    } else if (responseCode == 401) {
                        Log.e(TAG, "Unauthorized");
                        break; // التوكن غير صالح، لا داعي للمحاولة
                    } else {
                        Log.e(TAG, "Server error: " + responseCode + " on page " + page);
                        retryCount++;
                        if (retryCount >= MAX_RETRIES) {
                            Log.e(TAG, "Max retries reached. Aborting.");
                            break;
                        }
                        Thread.sleep(3000); // انتظار قبل إعادة المحاولة
                    }
                } catch (Exception e) {
                    Log.e(TAG, "Exception on page " + page, e);
                    retryCount++;
                    if (retryCount >= MAX_RETRIES) {
                        Log.e(TAG, "Max retries reached after exception. Aborting.");
                        break;
                    }
                    try { Thread.sleep(3000); } catch (InterruptedException ie) { break; }
                }
            }

            // Finished
            BackgroundSyncPlugin.emitFinished();
            Intent intent = new Intent("com.aso.app.SYNC_PROGRESS");
            intent.putExtra("progress", 100);
            intent.putExtra("message", "مكتمل");
            intent.putExtra("total", totalSponsorshipsSynced);
            sendBroadcast(intent);

            updateNotification("اكتملت المزامنة بنجاح", 100, 100);
            Thread.sleep(2000);
        } catch (Exception e) {
            Log.e(TAG, "Sync failed", e);
        } finally {
            stopSelf();
        }
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
