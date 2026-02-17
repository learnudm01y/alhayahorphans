package org.alhayah.sponsorships;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.os.Build;
import android.os.IBinder;
import android.os.PowerManager;
import android.util.Log;

import androidx.core.app.NotificationCompat;

import org.json.JSONObject;

import com.aso.app.MainActivity;
import com.aso.app.R;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.List;

/**
 * Foreground Service قوي لمزامنة البيانات (localStorage + IndexedDB)
 * معزول تماماً عن UploadForegroundService (الملفات)
 *
 * المميزات:
 * - قراءة من Database (DataSyncDatabaseHelper)
 * - Retry logic (3 محاولات لكل عنصر)
 * - WakeLock (لمنع النوم)
 * - حفظ الحالة باستمرار
 * - إشعار مستمر مع تقدم
 */
public class DataSyncForegroundService extends Service {
    private static final String TAG = "DataSyncForegroundService";
    private static final int NOTIFICATION_ID = 9999; // مختلف عن UploadForegroundService
    private static final String CHANNEL_ID = "data_sync_foreground_channel";
    private static final String CHANNEL_NAME = "مزامنة البيانات المستمرة";

    private PowerManager.WakeLock wakeLock;
    private DataSyncDatabaseHelper dbHelper;
    private volatile boolean isRunning = false;
    private Thread syncThread;
    private NotificationManager notificationManager;
    private NotificationCompat.Builder notificationBuilder;

    private int currentSessionSuccessCount = 0;
    private int currentSessionFailureCount = 0;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.e(TAG, "");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "🚀🚀🚀 DataSyncForegroundService CREATED 🚀🚀🚀");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        dbHelper = DataSyncDatabaseHelper.getInstance(this);
        notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        createNotificationChannel();

        // WakeLock
        PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);
        wakeLock = powerManager.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "ASO:DataSyncWakeLock");
        wakeLock.acquire(60 * 60 * 1000L); // ساعة واحدة
        Log.e(TAG, "🔓 WakeLock acquired - لن ينام أثناء المزامنة");
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.e(TAG, "");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "▶️▶️▶️ onStartCommand() - بدء الخدمة ▶️▶️▶️");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // بدء Foreground Service
        startForegroundWithNotification();

        // بدء المزامنة في thread منفصل
        if (!isRunning) {
            isRunning = true;
            syncThread = new Thread(this::processSyncQueue);
            syncThread.start();
            Log.e(TAG, "🧵 Sync thread started");
        } else {
            Log.e(TAG, "⚠️ Sync thread already running");
        }

        return START_STICKY; // مهم جداً - النظام سيعيد تشغيله إذا قُتل
    }

    private void startForegroundWithNotification() {
        Notification notification = createNotification("جاري التحضير...", 0, 0);
        try {
            // ✅ CRITICAL FIX: Android 14+ requires foregroundServiceType
            if (Build.VERSION.SDK_INT >= 34) { // Android 14 (API 34 - UPSIDE_DOWN_CAKE)
                Log.e(TAG, "📱 Android 14+ detected - using FOREGROUND_SERVICE_TYPE_DATA_SYNC");
                startForeground(NOTIFICATION_ID, notification,
                    android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC);
            } else {
                Log.e(TAG, "📱 Android < 14 - standard startForeground");
                startForeground(NOTIFICATION_ID, notification);
            }
            Log.e(TAG, "🛡️ Foreground Service started with notification");
        } catch (Exception e) {
            Log.e(TAG, "❌ فشل startForeground: " + e.getMessage(), e);
            e.printStackTrace();
            stopSelf();
        }
    }

    /**
     * معالجة قائمة المزامنة - نفس منطق UploadForegroundService لكن للبيانات
     */
    private void processSyncQueue() {
        Log.e(TAG, "");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "🔄 Starting data sync process");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        currentSessionSuccessCount = 0;
        currentSessionFailureCount = 0;

        while (isRunning) {
            try {
                // الحصول على البيانات المنتظرة (pending + failed بشرط retry < 3)
                List<DataSyncDatabaseHelper.DataSyncItem> pendingItems = dbHelper.getPendingData();

                if (pendingItems.isEmpty()) {
                    Log.e(TAG, "✅ No more pending data - sync complete");
                    break;
                }

                Log.e(TAG, "📊 Found " + pendingItems.size() + " pending data items");

                // معالجة كل عنصر
                int currentIndex = 0;
                for (DataSyncDatabaseHelper.DataSyncItem item : pendingItems) {
                    if (!isRunning) {
                        Log.e(TAG, "⚠️ Service stopped - aborting sync");
                        break;
                    }

                    currentIndex++;

                    // 🆕 إعادة تحويل failed items إلى pending للمحاولة مجدداً
                    if ("failed".equals(item.status)) {
                        dbHelper.markAsUploading(item.id); // تحويل إلى uploading مباشرة
                        Log.d(TAG, "🔄 Retrying failed item: ID=" + item.id + " (retry " + (item.retryCount + 1) + "/3)");
                    }

                    // تحديث الإشعار بـ Progress حقيقي
                    String message = "(" + currentIndex + "/" + pendingItems.size() + ") " + item.dataType;
                    updateNotification(message, currentIndex, pendingItems.size());

                    // محاولة المزامنة
                    boolean success = syncDataItem(item);

                    if (success) {
                        currentSessionSuccessCount++;
                    } else {
                        currentSessionFailureCount++;
                    }

                    // تأخير قصير بين العناصر
                    Thread.sleep(500);
                }

                // فحص مرة أخرى في حالة وجود عناصر جديدة
                pendingItems = dbHelper.getPendingData();
                if (pendingItems.isEmpty()) {
                    break;
                }

            } catch (InterruptedException e) {
                Log.e(TAG, "⚠️ Thread interrupted", e);
                break;
            } catch (Exception e) {
                Log.e(TAG, "❌ Error in sync loop", e);
                try {
                    Thread.sleep(5000); // انتظر 5 ثواني قبل إعادة المحاولة
                } catch (InterruptedException ie) {
                    break;
                }
            }
        }

        Log.e(TAG, "");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "📊 Sync session complete:");
        Log.e(TAG, "   ✅ Success: " + currentSessionSuccessCount);
        Log.e(TAG, "   ❌ Failures: " + currentSessionFailureCount);
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // إشعار نهائي
        showCompletionNotification();

        // إيقاف الخدمة
        stopSelf();
    }

    /**
     * مزامنة عنصر واحد من البيانات (مع Retry logic)
     */
    private boolean syncDataItem(DataSyncDatabaseHelper.DataSyncItem item) {
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🔄 Syncing: ID=" + item.id + ", Type=" + item.dataType +
                   ", Retry=" + item.retryCount + "/" + DataSyncDatabaseHelper.MAX_RETRY_ATTEMPTS);

        // تحديث الحالة إلى "uploading"
        dbHelper.markAsUploading(item.id);

        try {
            // فحص الإنترنت
            if (!isInternetAvailable()) {
                Log.e(TAG, "❌ No internet - aborting sync");
                dbHelper.markAsFailed(item.id, "No internet connection");
                return false;
            }

            // الحصول على API URL و Token من SharedPreferences
            SharedPreferences prefs = getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
            String token = prefs.getString("api_token", "");
            String baseUrl = prefs.getString("api_base_url", "https://alhayahorphans.org");

            if (token.isEmpty()) {
                Log.e(TAG, "❌ No API token - user not logged in");
                dbHelper.markAsFailed(item.id, "No API token");
                return false;
            }

            // بناء URL
            String fullUrl = baseUrl + item.endpoint;
            Log.d(TAG, "🌐 POST " + fullUrl);

            // إرسال البيانات
            URL url = new URL(fullUrl);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            conn.setDoOutput(true);
            conn.setConnectTimeout(30000); // 30 ثانية
            conn.setReadTimeout(30000);

            // كتابة البيانات
            OutputStream os = conn.getOutputStream();
            os.write(item.dataJson.getBytes("UTF-8"));
            os.flush();
            os.close();

            // قراءة الاستجابة
            int responseCode = conn.getResponseCode();
            Log.d(TAG, "📡 Response code: " + responseCode);

            if (responseCode >= 200 && responseCode < 300) {
                // نجحت المزامنة
                Log.d(TAG, "✅ Sync successful: ID=" + item.id);
                dbHelper.markAsUploaded(item.id);
                return true;
            } else {
                // فشلت المزامنة
                String errorMessage = "HTTP " + responseCode;
                Log.e(TAG, "❌ Sync failed: " + errorMessage);
                dbHelper.markAsFailed(item.id, errorMessage);
                return false;
            }

        } catch (Exception e) {
            Log.e(TAG, "❌ Exception during sync: " + e.getMessage(), e);
            dbHelper.markAsFailed(item.id, e.getMessage());
            return false;
        }
    }

    /**
     * فحص حالة الإنترنت
     */
    private boolean isInternetAvailable() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Network network = cm.getActiveNetwork();
            if (network == null) return false;

            NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
            return capabilities != null &&
                   capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
        } else {
            android.net.NetworkInfo networkInfo = cm.getActiveNetworkInfo();
            return networkInfo != null && networkInfo.isConnected();
        }
    }

    /**
     * إنشاء Notification
     */
    private Notification createNotification(String message, int progress, int max) {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            this, 0, notificationIntent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        notificationBuilder = new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("🔄 مزامنة بيانات الحياة")
            .setContentText(message)
            .setSmallIcon(R.drawable.ic_notification)
            .setColor(0xFFFFFFFF)
            .setColorized(false)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setCategory(NotificationCompat.CATEGORY_PROGRESS);

        if (max > 0) {
            int percentage = (int) ((progress * 100.0) / max);
            notificationBuilder.setProgress(max, progress, false);
            notificationBuilder.setSubText(percentage + "%");
        } else {
            notificationBuilder.setProgress(0, 0, true);
        }

        return notificationBuilder.build();
    }

    /**
     * تحديث الإشعار
     */
    private void updateNotification(String message, int progress, int max) {
        Notification notification = createNotification(message, progress, max);
        notificationManager.notify(NOTIFICATION_ID, notification);
    }

    /**
     * إشعار الاكتمال
     */
    private void showCompletionNotification() {
        String message = "تمت المزامنة: " + currentSessionSuccessCount + " عنصر";
        if (currentSessionFailureCount > 0) {
            message += " (فشل: " + currentSessionFailureCount + ")";
        }

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("✅ اكتملت مزامنة البيانات")
            .setContentText(message)
            .setSmallIcon(R.drawable.ic_notification)
            .setOngoing(false)
            .setPriority(NotificationCompat.PRIORITY_LOW);

        notificationManager.notify(NOTIFICATION_ID, builder.build());
    }

    /**
     * إنشاء Notification Channel
     */
    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("إشعارات مزامنة البيانات في الخلفية");
            channel.setShowBadge(false);

            notificationManager.createNotificationChannel(channel);
            Log.d(TAG, "✅ Notification channel created");
        }
    }

    /**
     * Static method لبدء الخدمة بسهولة من أي مكان
     */
    public static void startSync(Context context) {
        Log.d(TAG, "🚀 startSync() called - launching DataSyncForegroundService");
        Intent serviceIntent = new Intent(context, DataSyncForegroundService.class);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            context.startForegroundService(serviceIntent);
        } else {
            context.startService(serviceIntent);
        }
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.e(TAG, "");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "🛑 DataSyncForegroundService DESTROYED");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        isRunning = false;

        // إيقاف thread
        if (syncThread != null && syncThread.isAlive()) {
            syncThread.interrupt();
        }

        // إطلاق WakeLock
        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
            Log.d(TAG, "🔒 WakeLock released");
        }
    }
}
