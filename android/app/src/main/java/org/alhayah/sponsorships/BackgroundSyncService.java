package org.alhayah.sponsorships;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.os.IBinder;
import android.os.PowerManager;
import android.util.Log;

import androidx.core.app.NotificationCompat;

public class BackgroundSyncService extends Service {
    private static final String TAG = "BackgroundSyncService";
    private static final String CHANNEL_ID = "background_sync_channel";
    private static final int NOTIFICATION_ID = 1001;

    // Action constants for updating notification
    public static final String ACTION_UPDATE_PROGRESS = "org.alhayah.sponsorships.UPDATE_PROGRESS";
    public static final String ACTION_SET_INDETERMINATE = "org.alhayah.sponsorships.SET_INDETERMINATE";
    public static final String ACTION_SHOW_COMPLETE = "org.alhayah.sponsorships.SHOW_COMPLETE";

    public static final String EXTRA_PROGRESS = "progress";
    public static final String EXTRA_MAX = "max";
    public static final String EXTRA_STATUS = "status";
    public static final String EXTRA_MESSAGE = "message";

    private PowerManager.WakeLock wakeLock;
    private NotificationManager notificationManager;
    private NotificationCompat.Builder notificationBuilder;
    private boolean isRunning = false;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "Service created");

        notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        createNotificationChannel();

        // Acquire WakeLock to keep CPU running
        PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);
        wakeLock = powerManager.newWakeLock(
            PowerManager.PARTIAL_WAKE_LOCK,
            "ASO:BackgroundSyncWakeLock"
        );
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "Service started with action: " + (intent != null ? intent.getAction() : "null"));

        // ⚠️ مهم جداً: يجب استدعاء startForeground فوراً عند بدء الخدمة
        // لتجنب خطأ ForegroundServiceDidNotStartInTimeException
        if (!isRunning) {
            startForegroundService();
        }

        if (intent != null) {
            String action = intent.getAction();

            if (ACTION_UPDATE_PROGRESS.equals(action)) {
                // Update progress bar
                int progress = intent.getIntExtra(EXTRA_PROGRESS, 0);
                int max = intent.getIntExtra(EXTRA_MAX, 100);
                String status = intent.getStringExtra(EXTRA_STATUS);
                updateProgress(progress, max, status);
                return START_STICKY; // تغيير لـ STICKY لضمان استمرار العمل
            } else if (ACTION_SET_INDETERMINATE.equals(action)) {
                // Set indeterminate mode
                String message = intent.getStringExtra(EXTRA_MESSAGE);
                setIndeterminate(message);
                return START_STICKY; // تغيير لـ STICKY
            } else if (ACTION_SHOW_COMPLETE.equals(action)) {
                // Show completion notification then stop service
                String message = intent.getStringExtra(EXTRA_MESSAGE);
                showComplete(message);
                // إيقاف الخدمة بعد فترة قصيرة
                new android.os.Handler(android.os.Looper.getMainLooper()).postDelayed(() -> {
                    stopSelf();
                }, 2000);
                return START_NOT_STICKY;
            }
        }

        return START_STICKY; // تغيير من NOT_STICKY إلى STICKY لضمان بقاء الخدمة
    }

    private void startForegroundService() {
        Log.d(TAG, "Starting foreground service");

        // Acquire WakeLock (لمدة ساعة كحد أقصى - كافية للرفع)
        if (!wakeLock.isHeld()) {
            wakeLock.acquire(60 * 60 * 1000L); // ساعة واحدة
            Log.d(TAG, "WakeLock acquired for 60 minutes");
        }

        // Create notification with progress bar
        notificationBuilder = createNotificationBuilder("جاري المزامنة في الخلفية...", true);

        Notification notification = notificationBuilder.build();
        startForeground(NOTIFICATION_ID, notification);

        isRunning = true;
        Log.d(TAG, "Foreground service started successfully with WakeLock");
    }

    private NotificationCompat.Builder createNotificationBuilder(String message, boolean indeterminate) {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            this, 0, notificationIntent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("🔄 مزامنة جمعية الحياة")
            .setContentText(message)
            .setSmallIcon(R.drawable.ic_notification) // شعار التطبيق
            .setColor(0xFFFFFFFF) // ⚠️ لون أبيض بالكامل للخلفية
            .setColorized(false) // تعطيل التلوين التلقائي
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setCategory(NotificationCompat.CATEGORY_PROGRESS);

        if (indeterminate) {
            builder.setProgress(0, 0, true);
        }

        return builder;
    }

    /**
     * Update progress bar in notification
     */
    public void updateProgress(int progress, int max, String status) {
        if (notificationBuilder == null) {
            notificationBuilder = createNotificationBuilder(status != null ? status : "جاري المزامنة...", false);
        }

        // Calculate percentage
        int percentage = max > 0 ? (int) ((progress * 100.0) / max) : 0;

        notificationBuilder
            .setContentText(status != null ? status : "جاري المزامنة...")
            .setProgress(max, progress, false)
            .setSubText(percentage + "%")
            .setOngoing(true);

        notificationManager.notify(NOTIFICATION_ID, notificationBuilder.build());
        Log.d(TAG, "Progress updated: " + progress + "/" + max + " (" + percentage + "%)");
    }

    /**
     * Set notification to indeterminate (circular) mode
     */
    public void setIndeterminate(String message) {
        if (notificationBuilder == null) {
            notificationBuilder = createNotificationBuilder(message != null ? message : "جاري المزامنة...", true);
        }

        notificationBuilder
            .setContentText(message != null ? message : "جاري المزامنة...")
            .setProgress(0, 0, true)
            .setSubText(null)
            .setOngoing(true);

        notificationManager.notify(NOTIFICATION_ID, notificationBuilder.build());
        Log.d(TAG, "Set indeterminate mode: " + message);
    }

    /**
     * Show completion notification (can be dismissed)
     */
    public void showComplete(String message) {
        if (notificationBuilder == null) {
            notificationBuilder = createNotificationBuilder(message != null ? message : "اكتملت المزامنة", false);
        }

        notificationBuilder
            .setContentTitle("✅ اكتملت المزامنة")
            .setContentText(message != null ? message : "تمت المزامنة بنجاح")
            .setProgress(0, 0, false)
            .setSubText(null)
            .setOngoing(false); // Allow user to dismiss

        notificationManager.notify(NOTIFICATION_ID, notificationBuilder.build());
        Log.d(TAG, "Showing complete notification: " + message);

        // Release WakeLock
        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
        }
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "مزامنة الخلفية",
                NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("إشعارات مزامنة البيانات في الخلفية");
            channel.setShowBadge(false);

            notificationManager.createNotificationChannel(channel);
            Log.d(TAG, "Notification channel created");
        }
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "Service destroyed");

        isRunning = false;

        // Release WakeLock
        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
            Log.d(TAG, "WakeLock released");
        }
    }
}
