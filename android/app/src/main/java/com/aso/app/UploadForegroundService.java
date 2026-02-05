package com.aso.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.os.Build;
import android.os.IBinder;
import android.os.PowerManager;
import android.util.Log;
import androidx.core.app.NotificationCompat;

/**
 * Foreground Service للرفع المستمر حتى عند إغلاق التطبيق والشاشة
 * يعمل بشكل مستقل تماماً ولا يتوقف إلا بعد انتهاء جميع الملفات
 */
public class UploadForegroundService extends Service {
    private static final String TAG = "UploadForegroundService";
    private static final int NOTIFICATION_ID = 8888;
    private static final String CHANNEL_ID = "upload_foreground_channel";
    private static final String CHANNEL_NAME = "رفع الملفات المستمر";

    private PowerManager.WakeLock wakeLock;
    private UploadDatabaseHelper dbHelper;
    private volatile boolean isRunning = false;
    private Thread uploadThread;
    private int currentSessionSuccessCount = 0; // عداد الجلسة الحالية فقط
    private boolean isInForeground = false; // حالة Foreground

    @Override
    public void onCreate() {
        super.onCreate();
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "🚀🚀🚀 UploadForegroundService CREATED 🚀🚀🚀");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        dbHelper = UploadDatabaseHelper.getInstance(this);
        createNotificationChannel();

        // WakeLock لمنع النوم
        PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);
        wakeLock = powerManager.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "ASO:UploadWakeLock");
        wakeLock.acquire(60 * 60 * 1000L); // ساعة واحدة كحد أقصى
        android.util.Log.e(TAG, "🔓 WakeLock acquired - الجهاز لن ينام أثناء الرفع");
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "▶️▶️▶️ onStartCommand() - بدء الخدمة ▶️▶️▶️");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // بدء Foreground Service فقط إذا كان هناك إنترنت
        if (isNetworkAvailable()) {
            Notification notification = createNotification("جاري التحضير...", 0, 0);
            try {
                startForeground(NOTIFICATION_ID, notification);
                isInForeground = true;
                android.util.Log.e(TAG, "🛡️ Foreground Service started with notification (online)");
            } catch (Exception e) {
                android.util.Log.e(TAG, "❌ فشل startForeground: " + e.getMessage(), e);
                stopSelf();
                return START_NOT_STICKY;
            }
        } else {
            // Android 8+ يتطلب startForeground لكن يمكن إخفاؤه فوراً
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                Notification notification = createNotification("Waiting for internet...", 0, 0);
                try {
                    startForeground(NOTIFICATION_ID, notification);
                    // إخفاء فوراً
                    stopForeground(true);
                    isInForeground = false;
                    android.util.Log.e(TAG, "🛡️ Service started in background mode (offline - notification hidden)");
                } catch (Exception e) {
                    android.util.Log.e(TAG, "❌ فشل startForeground: " + e.getMessage(), e);
                    stopSelf();
                    return START_NOT_STICKY;
                }
            } else {
                android.util.Log.e(TAG, "🛡️ Service started in background mode (offline - no notification)");
            }
        }

        // بدء الرفع في thread منفصل
        if (!isRunning) {
            isRunning = true;
            uploadThread = new Thread(this::processUploadQueue);
            uploadThread.start();
            android.util.Log.e(TAG, "🧵 Upload thread started");
        } else {
            android.util.Log.e(TAG, "⚠️ Upload thread already running");
        }

        // START_STICKY = إعادة تشغيل الخدمة إذا قتلها النظام
        return START_STICKY;
    }

    /**
     * معالجة قائمة الملفات المعلقة
     */
    private void processUploadQueue() {
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        android.util.Log.e(TAG, "📦📦📦 بدء معالجة قائمة الرفع 📦📦📦");
        android.util.Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            // إعادة تعيين عداد الجلسة الحالية
            currentSessionSuccessCount = 0;
            int failedCount = 0;
            int waitCycles = 0; // عدد دورات الانتظار

            while (isRunning) {
                UploadDatabaseHelper.UploadItem file = dbHelper.getNextPendingFile();

                if (file == null) {
                    // لا توجد ملفات معلقة حالياً
                    waitCycles++;

                    // إخفاء الإشعار إذا كنا في Foreground
                    if (isInForeground) {
                        Log.d(TAG, "🔕 إخفاء الإشعار - لا توجد ملفات");
                        stopForeground(true); // إزالة الإشعار
                        isInForeground = false;
                    }

                    // عرض ملخص الجلسة إذا تم رفع ملفات
                    if (currentSessionSuccessCount > 0 && waitCycles == 1) {
                        Log.d(TAG, "✅ تمت الجلسة: رفع " + currentSessionSuccessCount + " ملف بنجاح");
                    }

                    Log.d(TAG, "⏸️ لا توجد ملفات - انتظار 10 ثواني... (دورة " + waitCycles + ")");

                    // انتظار ملفات جديدة (10 ثواني) - الخدمة تبقى عاملة
                    try {
                        Thread.sleep(10000);
                    } catch (InterruptedException e) {
                        break;
                    }
                    continue;
                }

                // تم العثور على ملف - إعادة تعيين عداد الانتظار
                if (waitCycles > 0) {
                    Log.d(TAG, "🔄 ملف جديد في القائمة - استئناف الرفع");
                    waitCycles = 0;
                    // إعادة تعيين عداد الجلسة لجلسة جديدة
                    currentSessionSuccessCount = 0;
                }

                // Show notification only if online
                if (!isInForeground && isNetworkAvailable()) {
                    Log.d(TAG, "Showing notification - starting upload");
                    int totalPending = dbHelper.getPendingFilesCount();
                    Notification notification = createNotification("Uploading files...", currentSessionSuccessCount, totalPending + currentSessionSuccessCount);
                    startForeground(NOTIFICATION_ID, notification);
                    isInForeground = true;
                } else if (!isNetworkAvailable()) {
                    Log.d(TAG, "Offline - hiding notification, keeping background sync active");
                    if (isInForeground) {
                        stopForeground(true);
                        isInForeground = false;
                    }
                }


                Log.d(TAG, "");
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log.d(TAG, "📤 بدء رفع مللف...");
                Log.d(TAG, "   📁 " + file.fileName);
                Log.d(TAG, "   🆔 SQLite ID: " + file.id);
                Log.d(TAG, "   🔢 Photo ID: " + file.photoId);
                Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

                // تحديث الحالة: قيد الرفع
                dbHelper.updateFileStatus(file.id, UploadDatabaseHelper.STATUS_UPLOADING, null);

                // محاولة الرفع
                boolean success = uploadFile(file);

                if (success) {
                    currentSessionSuccessCount++; // عداد الجلسة الحالية فقط
                    dbHelper.updateFileStatus(file.id, UploadDatabaseHelper.STATUS_COMPLETED, null);

                    // تحديث IndexedDB مباشرة عبر WebView إذا كان التطبيق مفتوح
                    int indexedDbId = dbHelper.getIndexedDbId(file.id);
                    if (indexedDbId > 0) {
                        notifyJavaScriptOfUpload(indexedDbId);
                    }

                    // Update notification immediately after success
                    int remainingFiles = dbHelper.getPendingFilesCount();
                    int totalFiles = currentSessionSuccessCount + remainingFiles;

                    if (isNetworkAvailable()) {
                        updateNotification("Uploaded " + currentSessionSuccessCount + " of " + totalFiles + " files",
                                           currentSessionSuccessCount, totalFiles);
                        Log.d(TAG, "Upload successful! (Session: " + currentSessionSuccessCount + " success, " + failedCount + " failed)");
                    } else {
                        Log.d(TAG, "Upload successful but offline - hiding notification");
                        if (isInForeground) {
                            stopForeground(true);
                            isInForeground = false;
                        }
                    }
                } else {
                    // Upload failed - keep as pending until internet returns
                    failedCount++;
                    dbHelper.incrementRetryCount(file.id);
                    dbHelper.updateFileStatus(file.id, UploadDatabaseHelper.STATUS_PENDING,
                            "Waiting for internet - attempt " + (file.retryCount + 1));
                    Log.w(TAG, "Upload failed - will retry when internet returns (attempt " + (file.retryCount + 1) + ")");

                    // Hide notification when offline
                    if (!isNetworkAvailable() && isInForeground) {
                        Log.d(TAG, "Offline - hiding notification");
                        stopForeground(true);
                        isInForeground = false;
                    }

                    // Wait 30 seconds before next attempt
                    Thread.sleep(30000);
                }

                // توقف قصير بين الملفات
                Thread.sleep(500);
            }

            Log.d(TAG, "🛑 الخدمة متوقفة - isRunning=false");

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في معالجة القائمة: " + e.getMessage(), e);
        } finally {
            // إخفاء الإشعار عند الخروج
            if (isInForeground) {
                stopForeground(true);
                isInForeground = false;
            }
        }
    }

    /**
     * رفع ملف واحد
     */
    private boolean uploadFile(UploadDatabaseHelper.UploadItem item) {
        java.net.HttpURLConnection conn = null;

        try {
            // قراءة الملف من Internal Storage
            java.io.File file = new java.io.File(getFilesDir(), item.filePath);
            if (!file.exists()) {
                Log.e(TAG, "❌ الملف غير موجود: " + file.getAbsolutePath());
                return false;
            }

            // قراءة bytes من الملف
            byte[] fileBytes = new byte[(int) file.length()];
            java.io.FileInputStream fis = new java.io.FileInputStream(file);
            fis.read(fileBytes);
            fis.close();

            // تحويل إلى Base64 للرفع
            String base64Data = android.util.Base64.encodeToString(fileBytes, android.util.Base64.NO_WRAP);
            Log.e(TAG, "✅ File read: " + fileBytes.length + " bytes, Base64: " + base64Data.length() + " chars");

            Log.d(TAG, "🌐 اتصال بـ: " + item.apiUrl);

            java.net.URL url = new java.net.URL(item.apiUrl);
            conn = (java.net.HttpURLConnection) url.openConnection();
            conn.setDoOutput(true);
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Accept", "application/json");

            // Auth token من SharedPreferences
            String authToken = getSharedPreferences("capacitor", Context.MODE_PRIVATE)
                    .getString("auth_token", "");
            if (!authToken.isEmpty()) {
                conn.setRequestProperty("Authorization", "Bearer " + authToken);
            }

            conn.setConnectTimeout(30000);
            conn.setReadTimeout(60000);

            // JSON payload
            org.json.JSONObject json = new org.json.JSONObject();
            json.put("file_name", item.fileName);
            json.put("file_type", item.fileType);
            json.put("file_data", base64Data);
            json.put("sponsorship_id", item.photoId);

            java.io.OutputStream out = conn.getOutputStream();
            byte[] jsonBytes = json.toString().getBytes("UTF-8");
            out.write(jsonBytes);
            out.flush();
            out.close();

            int responseCode = conn.getResponseCode();
            Log.d(TAG, "📡 Response: " + responseCode);

            boolean success = (responseCode >= 200 && responseCode < 300);

            // حذف الملف من Internal Storage بعد نجاح الرفع
            if (success) {
                java.io.File uploadedFile = new java.io.File(getFilesDir(), item.filePath);
                if (uploadedFile.exists() && uploadedFile.delete()) {
                    Log.e(TAG, "🗑️ تم حذف الملف من Internal Storage");
                }
            }

            return success;

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في رفع " + item.fileName + ": " + e.getMessage());
            return false;
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
    }

    /**
     * إرسال إشعار لـ JavaScript عن نجاح الرفع
     */
    private void notifyJavaScriptOfUpload(int indexedDbId) {
        try {
            Intent intent = new Intent("com.aso.app.FILE_UPLOADED");
            intent.putExtra("indexedDbId", indexedDbId);
            sendBroadcast(intent);
            Log.d(TAG, "📡 Broadcast sent for IndexedDB ID: " + indexedDbId);
        } catch (Exception e) {
            Log.e(TAG, "⚠️ فشل broadcast: " + e.getMessage());
        }
    }

    /**
     * Check if internet is available
     */
    private boolean isNetworkAvailable() {
        try {
            ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm != null) {
                NetworkInfo activeNetwork = cm.getActiveNetworkInfo();
                boolean isConnected = activeNetwork != null && activeNetwork.isConnectedOrConnecting();
                Log.d(TAG, "Network check: " + (isConnected ? "ONLINE" : "OFFLINE"));
                return isConnected;
            }
        } catch (Exception e) {
            Log.e(TAG, "Network check error: " + e.getMessage());
        }
        return false;
    }

    /**
     * Create notification channel
     */
    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    CHANNEL_NAME,
                    NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("إشعارات رفع الملفات في الخلفية");

            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    /**
     * Create notification
     */
    private Notification createNotification(String text, int progress, int max) {
        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.stat_sys_upload)
                .setContentTitle("Uploading Files")
                .setContentText(text)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setOngoing(true);

        if (max > 0 && progress > 0) {
            int percentage = (progress * 100) / max;
            builder.setProgress(max, progress, false)
                   .setSubText(percentage + "% complete");
        } else if (max > 0) {
            // Show total files count even at start
            builder.setProgress(max, 0, false)
                   .setSubText("0% - " + max + " files total");
        } else {
            builder.setProgress(0, 0, true); // indeterminate
        }

        return builder.build();
    }

    /**
     * تحديث notification
     */
    private void updateNotification(String text, int progress, int max) {
        try {
            Notification notification = createNotification(text, progress, max);
            NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager != null) {
                manager.notify(NOTIFICATION_ID, notification);
            }
        } catch (Exception e) {
            Log.e(TAG, "فشل تحديث notification: " + e.getMessage());
        }
    }

    @Override
    public void onDestroy() {
        Log.d(TAG, "");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.d(TAG, "🛑 UploadForegroundService DESTROYED");
        Log.d(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        isRunning = false;

        if (uploadThread != null && uploadThread.isAlive()) {
            uploadThread.interrupt();
        }

        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
            Log.d(TAG, "🔒 WakeLock released");
        }

        super.onDestroy();
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
