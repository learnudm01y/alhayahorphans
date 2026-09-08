package com.aso.app;

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
import android.util.Log;

import androidx.core.app.NotificationCompat;

import org.json.JSONObject;

import java.util.concurrent.TimeUnit;

import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import okhttp3.WebSocket;
import okhttp3.WebSocketListener;
import okio.ByteString;

/**
 * RealtimeSyncService
 * خدمة أندرويد أصلية (Foreground Service) للمزامنة في الوقت الفعلي باستخدام WebSockets.
 * تعمل حتى إذا كان التطبيق مغلقاً تماماً، وتتصل بـ Laravel WebSockets / Reverb.
 */
public class RealtimeSyncService extends Service {
    private static final String TAG = "RealtimeSyncService";
    private static final String CHANNEL_ID = "realtime_sync_channel";
    private static final int NOTIFICATION_ID = 8888;
    private static final int UPDATE_NOTIFICATION_ID = 8889;
    
    private OkHttpClient client;
    private WebSocket webSocket;
    private boolean isRunning = false;
    private SponsorshipsDatabaseHelper dbHelper;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "🚀 RealtimeSyncService Created");
        dbHelper = SponsorshipsDatabaseHelper.getInstance(this);
        createNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (!isRunning) {
            isRunning = true;
            Log.d(TAG, "▶️ Starting Foreground Service");
            startForeground(NOTIFICATION_ID, getNotification("متصل.. جاهز لاستقبال التحديثات الفورية"));
            connectWebSocket();
        }
        // STICKY يضمن إعادة تشغيل الخدمة تلقائياً إذا قتلها النظام
        return START_STICKY;
    }

    private void connectWebSocket() {
        if (client == null) {
            client = new OkHttpClient.Builder()
                    .readTimeout(0, TimeUnit.MILLISECONDS)
                    .pingInterval(30, TimeUnit.SECONDS) // إرسال Ping للحفاظ على الاتصال
                    .build();
        }

        // الاتصال بخادم الـ WebSocket في الـ Production
        String wsUrl = "ws://alhayahorphans.org:6001";

        Log.d(TAG, "🔌 Connecting to Custom WebSocket: " + wsUrl);

        Request request = new Request.Builder()
                .url(wsUrl)
                .build();

        webSocket = client.newWebSocket(request, new WebSocketListener() {
            @Override
            public void onOpen(WebSocket webSocket, Response response) {
                Log.d(TAG, "✅ WebSocket Connected!");
                updateForegroundNotification("متصل بالسيرفر للمزامنة الحية");
                
                // 🆕 إرسال إشعار للـ JS بسحب التحديثات التي ربما ضاعت أثناء فترة الانقطاع
                Intent intent = new Intent("com.aso.app.WEBSOCKET_RECONNECTED");
                sendBroadcast(intent);
                
                // ✨ NEW: Schedule DriveStatusWorker to fetch missed offline updates
                try {
                    androidx.work.OneTimeWorkRequest driveStatusWorkRequest = new androidx.work.OneTimeWorkRequest.Builder(DriveStatusWorker.class).build();
                    androidx.work.WorkManager.getInstance(getApplicationContext()).enqueueUniqueWork(
                        "DriveStatusProcessor",
                        androidx.work.ExistingWorkPolicy.REPLACE,
                        driveStatusWorkRequest
                    );
                } catch(Exception ignored){}
            }

            @Override
            public void onMessage(WebSocket webSocket, String text) {
                Log.d(TAG, "📩 WebSocket Message: " + text);
                processIncomingUpdate(text);
            }

            @Override
            public void onMessage(WebSocket webSocket, ByteString bytes) {
                Log.d(TAG, "📩 WebSocket Bytes received");
            }

            @Override
            public void onClosing(WebSocket webSocket, int code, String reason) {
                Log.d(TAG, "⚠️ WebSocket Closing: " + reason);
                webSocket.close(1000, null);
            }

            @Override
            public void onClosed(WebSocket webSocket, int code, String reason) {
                Log.d(TAG, "❌ WebSocket Closed: " + reason);
                reconnect();
            }

            @Override
            public void onFailure(WebSocket webSocket, Throwable t, Response response) {
                Log.e(TAG, "🚫 WebSocket Failure: " + t.getMessage());
                reconnect();
            }
        });
    }

    private void reconnect() {
        if (isRunning) {
            Log.d(TAG, "🔄 Reconnecting in 5 seconds...");
            updateForegroundNotification("جاري إعادة الاتصال...");
            new android.os.Handler(android.os.Looper.getMainLooper()).postDelayed(() -> {
                if (isRunning) connectWebSocket();
            }, 5000);
        }
    }

    private void processIncomingUpdate(String message) {
        try {
            JSONObject json = new JSONObject(message);
            String event = json.optString("event");
            String type = json.optString("type");
            String actionType = json.optString("action_type");
            
            // ========================================
            // 🆕 معالجة تحديثات التصنيفات (Lookup Updates) من الـ Trait
            // الرسالة القادمة من SyncsLookupToMobile لها: type=realtimeUpdate, action_type=lookup_update
            // ========================================
            if ("realtimeUpdate".equals(type) && "lookup_update".equals(actionType)) {
                String lookupType = json.optString("lookup_type");
                Log.d(TAG, "📥 Lookup Update received via WebSocket! Type: " + lookupType);
                
                // إرسال Broadcast للواجهة (JS) لتقوم بسحب التحديثات فوراً
                Intent intent = new Intent("com.aso.app.REALTIME_UPDATE");
                intent.putExtra("sponsorship_id", -1); // -1 يعني تحديث عام وليس كفالة محددة
                intent.putExtra("update_type", "lookup_update");
                intent.putExtra("lookup_type", lookupType);
                intent.putExtra("sponsorship_data", json.toString());
                sendBroadcast(intent);
                
                showUpdateNotification("تحديث تصنيفات", "تم تحديث " + lookupType + " من السيرفر");
                return;
            }
            
            // حدث التحديث من السيرفر المحلي الخاص
            if ("SponsorshipUpdated".equals(event)) {
                int id = json.optInt("id");
                
                if (id > 0) {
                    // 🚀 الحماية من الكتابة فوق التعديلات المحلية المعلقة (Race Condition Fix)
                    org.alhayah.sponsorships.DataSyncDatabaseHelper dataSyncDb = org.alhayah.sponsorships.DataSyncDatabaseHelper.getInstance(getApplicationContext());
                    java.util.Set<Integer> pendingIds = dataSyncDb.getPendingEntityIds();
                    
                    if (pendingIds != null && pendingIds.contains(id)) {
                        Log.w(TAG, "⚠️ Realtime Update Ignored: Sponsorship " + id + " has pending local edits.");
                    } else {
                        // 1. الكتابة المباشرة في قاعدة بيانات SQLite المحلية!!
                        synchronized (com.aso.app.SponsorshipsDatabaseHelper.class) {
                            dbHelper.saveSponsorship(id, json.toString());
                        }
                        Log.d(TAG, "💾 Saved incoming real-time update to SQLite natively! ID: " + id);
                        
                        // 2. إشعار محلي للمستخدم
                        showUpdateNotification("تحديث جديد!", "تم تحديث بيانات الكفالة رقم " + id + " من السيرفر");
                        
                        // 3. إرسال Broadcast للواجهة (JS) لتتحدث في الـ Real-time لو كان التطبيق مفتوحاً
                        Intent intent = new Intent("com.aso.app.REALTIME_UPDATE");
                        intent.putExtra("sponsorship_id", id);
                        intent.putExtra("sponsorship_data", json.toString());
                        sendBroadcast(intent);
                    }
                }
            } else if ("SponsorshipDeleted".equals(event)) {
                int id = json.optInt("id");
                if (id > 0) {
                    org.alhayah.sponsorships.DataSyncDatabaseHelper dataSyncDb = org.alhayah.sponsorships.DataSyncDatabaseHelper.getInstance(getApplicationContext());
                    java.util.Set<Integer> pendingIds = dataSyncDb.getPendingEntityIds();
                    
                    if (pendingIds != null && pendingIds.contains(id)) {
                        Log.w(TAG, "⚠️ Realtime Delete Ignored: Sponsorship " + id + " has pending local edits.");
                    } else {
                        // حذف الكفالة من SQLite
                        android.database.sqlite.SQLiteDatabase db = dbHelper.getWritableDatabase();
                        db.delete("sponsorships", "id=?", new String[]{String.valueOf(id)});
                        Log.d(TAG, "🗑️ Deleted sponsorship via real-time WebSocket! ID: " + id);
                        
                        showUpdateNotification("حذف كفالة", "تم حذف الكفالة رقم " + id + " من السيرفر");
                        
                        Intent intent = new Intent("com.aso.app.REALTIME_UPDATE");
                        intent.putExtra("sponsorship_id", id);
                        sendBroadcast(intent);
                    }
                }
            } else if ("UploadStatusUpdated".equals(event)) {
                String fileName = json.optString("file_name");
                String status = json.optString("status");
                
                if (fileName != null && !fileName.isEmpty()) {
                    UploadDatabaseHelper uploadDbHelper = UploadDatabaseHelper.getInstance(getApplicationContext());
                    UploadDatabaseHelper.UploadItem item = uploadDbHelper.getFileByName(fileName);
                    
                    if (item != null) {
                        String newStatus = "completed".equals(status) ? UploadDatabaseHelper.STATUS_COMPLETED : UploadDatabaseHelper.STATUS_FAILED;
                        
                        // Update status natively
                        uploadDbHelper.updateFileStatus(item.id, newStatus, null);
                        
                        // Notify JS through plugin
                        UploadServicePlugin.notifyUploadStatusChanged(item.id, newStatus, null);
                        
                        Log.d(TAG, "⚡ Real-time Upload Status Updated for: " + fileName + " -> " + newStatus);
                    }
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ Error parsing incoming WebSocket message", e);
        }
    }

    private void showUpdateNotification(String title, String message) {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        
        Intent intent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 0, intent, PendingIntent.FLAG_IMMUTABLE);

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle(title)
                .setContentText(message)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setContentIntent(pendingIntent)
                .setAutoCancel(true);
                
        manager.notify(UPDATE_NOTIFICATION_ID, builder.build());
    }

    private Notification getNotification(String content) {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 0, notificationIntent, PendingIntent.FLAG_IMMUTABLE);

        return new NotificationCompat.Builder(this, CHANNEL_ID)
                .setContentTitle("المزامنة الفورية (Real-time)")
                .setContentText(content)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentIntent(pendingIntent)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .build();
    }

    private void updateForegroundNotification(String content) {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        manager.notify(NOTIFICATION_ID, getNotification(content));
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    "Realtime Sync Channel",
                    NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("خدمة المزامنة الفورية في الخلفية عبر WebSockets");
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        isRunning = false;
        if (webSocket != null) {
            webSocket.close(1000, "Service Destroyed");
        }
        Log.d(TAG, "🛑 RealtimeSyncService Destroyed");
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
