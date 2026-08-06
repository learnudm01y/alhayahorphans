package com.aso.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.ServiceInfo;
import android.net.Uri;
import android.os.Build;
import android.util.Log;
import androidx.annotation.NonNull;
import androidx.core.app.NotificationCompat;
import androidx.work.ForegroundInfo;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import java.io.File;
import java.io.IOException;
import java.io.InputStream;
import java.util.UUID;
import java.util.concurrent.TimeUnit;

import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

/**
 * رفع الملفات على شكل أجزاء (chunks).
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ما الذي أُصلح هنا ولماذا (نتائج تدقيق منظومة الرفع):
 *
 * ١) مهلة الكتابة المفقودة — كان الرفع يستخدم HttpURLConnection الذي لا يملك
 *    أي واجهة لمهلة الكتابة. على وصلة «متصلة لكن ميتة» (واي فاي بلا مخرج،
 *    أو تغطية عند الحافة) يمتلئ مخزن الإرسال ويظل الخيط محبوساً داخل
 *    getResponseCode() حتى يستسلم النواة بعد ١٥-٣٠ دقيقة. WorkManager يقتل
 *    العامل عند ١٠ دقائق قبل ذلك بكثير، فيبقى الصف 'uploading' للأبد.
 *    → الآن OkHttp بمهل صريحة: connect/read/write/call.
 *
 * ٢) لا إعادة محاولة للجزء الواحد — كان فشل جزء واحد يُسقط الملف بأكمله.
 *    → الآن لكل جزء عدة محاولات بتراجع أُسّي + عشوائية.
 *
 * ٣) القراءة القصيرة — كان read(buffer) يُفترض أنه يملأ المخزن دائماً، وهذا
 *    غير مضمون إطلاقاً مع content:// . أي قراءة قصيرة تُزيح حدود الأجزاء عن
 *    X-Total-Chunks فيُجمِّع الخادم ملفاً مبتوراً.
 *    → الآن readFully يملأ الجزء تماماً قبل الإرسال.
 *
 * ٤) بوابة الـ ping — كان كل شيء محكوماً بـ ping مدته ٣ ثوانٍ لمضيف Google،
 *    وهو ما يفشل بالضبط حين تكون الشبكة ضعيفة.
 *    → الآن نحاول ما دامت هناك شبكة، والمرجع هو نتيجة الرفع نفسه.
 *
 * ٥) عدم فحص isStopped() — عند إيقاف العامل كان الصف يبقى 'uploading'.
 *    → الآن يُفحص في كل حلقة ويُعاد الصف إلى 'pending' قبل الخروج.
 *
 * ٦) لا خدمة أمامية — الفيديوهات الكبيرة تصطدم بسقف ١٠ دقائق فتموت في المنتصف.
 *    → الآن setForegroundAsync + getForegroundInfo.
 *
 * ٧) معرّف الرفع كان رقم الصف المحلي فقط، فتتصادم مجلدات الأجزاء على الخادم
 *    بين الأجهزة وتُنتج ملفات مُركّبة من مصادر مختلفة.
 *    → الآن يتضمّن معرّف جهاز ثابتاً وفريداً.
 *
 * ٨) عند الفشل كان يعود Result.success() ويكسر الحلقة، فلا يعيد WorkManager
 *    جدولة أي شيء ويتجمّد الطابور.
 *    → الآن Result.retry() مع تراجع أُسّي.
 * ═══════════════════════════════════════════════════════════════════════
 */
public class ChunkedUploadWorker extends Worker {
    private static final String TAG = "ChunkedUploadWorker";

    /**
     * حجم الجزء يتكيّف مع الوصلة:
     *  • وصلة سريعة  → أجزاء كبيرة = عدد أقل من الرحلات = رفع أسرع بوضوح.
     *  • وصلة ضعيفة  → أجزاء صغيرة = كل جزء ينجح بسرعة ويُثبَّت التقدّم،
     *                   وفشل الجزء يكلّف إعادة إرسال قليلة لا ميجابايتات.
     * حجم الجزء جزء من معرّف الرفع، فتغيّره بين الجولات لا يفسد الاستئناف —
     * يبدأ جلسة رفع جديدة نظيفة بدل خلط أجزاء بأحجام مختلفة.
     */
    private static final int CHUNK_SIZE_FAST    = 4 * 1024 * 1024;  // ٤ ميجابايت
    private static final int CHUNK_SIZE_DEFAULT = 1024 * 1024;      // ١ ميجابايت
    private static final int CHUNK_SIZE_WEAK    = 256 * 1024;       // ٢٥٦ كيلوبايت

    /** محاولات الجزء الواحد قبل اعتبار الملف فاشلاً في هذه الجولة. */
    private static final int CHUNK_MAX_ATTEMPTS = 4;
    private static final long CHUNK_BACKOFF_BASE_MS = 1500L;
    private static final long CHUNK_BACKOFF_MAX_MS = 30000L;

    /** سقف محاولات الملف قبل وضعه في 'failed'. */
    private static final int FILE_MAX_RETRIES = 30;

    /** حد ملفات الجولة الواحدة: نترك الباقي لجولة تالية بدل الاصطدام بسقف التنفيذ. */
    private static final int MAX_FILES_PER_RUN = 20;

    /**
     * ميزانية وقت الجولة الواحدة.
     *
     * WorkManager يقتل أي عامل بعد ١٠ دقائق. الاصطدام بهذا السقف يعني القتل
     * في منتصف جزء وترك الصف في 'uploading'. لذا نستسلم طوعاً قبله بهامش:
     * ننهي الجزء الجاري، نُعيد الملف إلى 'pending'، ونطلب إعادة الجدولة.
     * الأجزاء المرفوعة محفوظة على الخادم فتُستأنف الجولة التالية من موضعها،
     * وبهذا يكتمل فيديو ضخم عبر عدة جولات بلا خسارة ولا انهيار.
     */
    private static final long RUN_BUDGET_MS = 8 * 60 * 1000L;

    /** لحظة بدء الجولة — أساس حساب الميزانية. */
    private long runStartedAt;

    /**
     * هل توقّف الملف الأخير بسبب انتهاء ميزانية الوقت لا بسبب فشل حقيقي؟
     * الفرق جوهري: الاستسلام الطوعي يجب ألا يزيد عدّاد المحاولات، وإلا استهلك
     * فيديو كبير محاولاته الثلاثين على مجرد تقطيع الجولات.
     */
    private boolean yieldedOnBudget;

    private boolean budgetExhausted() {
        return (System.currentTimeMillis() - runStartedAt) > RUN_BUDGET_MS;
    }

    private static final String CHANNEL_ID = "UploadChannel";
    private static final int NOTIFICATION_ID = 1001;

    private static final String PREFS_DEVICE = "upload_device_prefs";
    private static final String KEY_DEVICE_ID = "device_upload_id";

    private final OkHttpClient httpClient;

    public ChunkedUploadWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
        createNotificationChannel();

        // مهل صريحة على المحاور الأربعة. writeTimeout هو المفتاح: بدونه تتجمّد
        // الكتابة على مقبس نصف مفتوح لعشرات الدقائق دون أي استثناء.
        this.httpClient = new OkHttpClient.Builder()
            .connectTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(90, TimeUnit.SECONDS)
            .readTimeout(90, TimeUnit.SECONDS)
            .callTimeout(180, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .build();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "File Uploads",
                NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("Shows progress of file uploads");
            NotificationManager manager = getApplicationContext().getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private NotificationCompat.Builder baseNotification(String title, String message, int progress, int maxProgress) {
        return new NotificationCompat.Builder(getApplicationContext(), CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_menu_upload)
            .setContentTitle(title)
            .setContentText(message)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setProgress(maxProgress, progress, false);
    }

    /**
     * تُستدعى فقط حين يُشغَّل الطلب كـ expedited على أندرويد ٧-١١، وحينها
     * يكون WorkManager نفسه هو من يبدأ الخدمة الأمامية — وهو مسار مسموح.
     */
    @NonNull
    @Override
    public ForegroundInfo getForegroundInfo() {
        android.app.Notification notification =
            baseNotification("جاري رفع الملفات", "تحضير…", 0, 100).build();
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            return new ForegroundInfo(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC);
        }
        return new ForegroundInfo(NOTIFICATION_ID, notification);
    }

    /**
     * 🚨 تحذير — لا تُعِد إدخال setForegroundAsync() هنا مهما كان المبرر.
     *
     * كانت النسخة السابقة تستدعيها عند كل جزء لرفع سقف تنفيذ العامل.
     * النتيجة: انهيار كامل للتطبيق عند كل محاولة رفع.
     *
     * السبب: التطبيق يستهدف targetSdk 36، ومنذ أندرويد ١٢ يُمنع بدء أي خدمة
     * أمامية والتطبيق في الخلفية. WorkManager ينفّذ الطلب على الخيط الرئيسي
     * داخل SystemForegroundDispatcher، فتُرمى ForegroundServiceStartNotAllowedException
     * هناك — أي خارج أي try/catch نضعه حول الاستدعاء، فلا سبيل لالتقاطها،
     * والعملية تسقط بالكامل.
     *
     * البديل المطبَّق لمشكلة سقف العشر دقائق: العامل يستسلم طوعاً قبل بلوغ
     * السقف (انظر RUN_BUDGET_MS)، ويترك الملف في 'pending'. الأجزاء المرفوعة
     * محفوظة على الخادم، فتستأنف الجولة التالية من حيث توقفت بلا خسارة.
     * إشعار التقدّم هنا إشعار عادي لا يمسّ الخدمات الأمامية إطلاقاً.
     */
    private void updateProgressNotification(String title, String message, int progress, int max) {
        try {
            NotificationManager manager =
                (NotificationManager) getApplicationContext().getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager != null) {
                manager.notify(NOTIFICATION_ID, baseNotification(title, message, progress, max).build());
            }
        } catch (Exception e) {
            // الإشعار رفاهية؛ لا يجوز أن يُسقط الرفع أبداً.
            Log.w(TAG, "تعذّر تحديث الإشعار: " + e.getMessage());
        }
    }

    private void clearNotification() {
        NotificationManager manager = (NotificationManager) getApplicationContext().getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.cancel(NOTIFICATION_ID);
        }
    }

    /**
     * معرّف جهاز ثابت وفريد. بدونه تكون مجلدات الأجزاء على الخادم مسمّاة برقم
     * الصف المحلي فقط (upload_1، upload_2…) وهو يتكرّر عبر الأجهزة، فتختلط
     * أجزاء ملفات مختلفة في نفس المجلد.
     */
    private String getDeviceId() {
        SharedPreferences prefs = getApplicationContext().getSharedPreferences(PREFS_DEVICE, Context.MODE_PRIVATE);
        String id = prefs.getString(KEY_DEVICE_ID, null);
        if (id == null || id.isEmpty()) {
            id = UUID.randomUUID().toString().replace("-", "").substring(0, 12);
            prefs.edit().putString(KEY_DEVICE_ID, id).apply();
        }
        return id;
    }

    /** حجم الجزء المناسب لحالة الشبكة الحالية. */
    private int pickChunkSize() {
        try {
            switch (NetworkQuality.classify(getApplicationContext())) {
                case NetworkQuality.FAST: return CHUNK_SIZE_FAST;
                case NetworkQuality.WEAK: return CHUNK_SIZE_WEAK;
                default:                  return CHUNK_SIZE_DEFAULT;
            }
        } catch (Throwable ignored) {
            return CHUNK_SIZE_DEFAULT;
        }
    }

    @NonNull
    @Override
    public Result doWork() {
        runStartedAt = System.currentTimeMillis();
        Log.i(TAG, "بدء عامل الرفع المُجزَّأ");

        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getApplicationContext());

        // شبكة الأمان: أعِد للطابور كل ما علق في 'uploading' أو 'processing_server'.
        // هذا ما يُنقذ الملفات التي ماتت أثناء رفعة سابقة.
        dbHelper.reclaimStaleUploads();
        dbHelper.reclaimStaleProcessing();

        // البوابة الصحيحة: وجود شبكة. لا نستخدم ping هنا — الشبكة الضعيفة
        // تُسقط الـ ping بينما الرفع نفسه قد ينجح ببطء.
        if (!InternetUtils.hasAnyNetwork(getApplicationContext())) {
            Log.i(TAG, "لا توجد شبكة. تأجيل الجولة.");
            return Result.retry();
        }

        try {
            SharedPreferences prefs = getApplicationContext().getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
            String token = prefs.getString("api_token", "");
            String baseUrl = ApiConfig.getBaseUrl(getApplicationContext());

            if (token == null || token.isEmpty()) {
                token = getApplicationContext().getSharedPreferences("capacitor", Context.MODE_PRIVATE)
                    .getString("auth_token", "");
                if (token == null || token.isEmpty()) {
                    Log.e(TAG, "لا يوجد رمز مصادقة، إيقاف الجولة");
                    return Result.failure();
                }
            }

            drainOfflineInbox(dbHelper, baseUrl, token);

            int processed = 0;
            int success = 0;
            boolean sawFailure = false;

            while (processed < MAX_FILES_PER_RUN) {
                if (isStopped()) {
                    Log.w(TAG, "أُوقف العامل — إنهاء نظيف");
                    return Result.retry();
                }

                if (budgetExhausted()) {
                    Log.i(TAG, "انتهت ميزانية الجولة — تسليم الباقي لجولة تالية");
                    clearNotification();
                    return Result.retry();
                }

                // حجز ذرّي: pending → uploading في معاملة واحدة.
                UploadDatabaseHelper.UploadItem nextFile = dbHelper.claimNextPendingFile();

                if (nextFile == null) {
                    Log.d(TAG, "لا مزيد من الملفات المعلقة");
                    break;
                }

                processed++;
                Log.d(TAG, "📤 معالجة الملف #" + processed + ": " + nextFile.fileName);

                String uploadToken = (nextFile.authToken != null && !nextFile.authToken.isEmpty())
                    ? nextFile.authToken : token;

                int totalPending = dbHelper.getPendingFilesCount();
                String notificationTitle = "رفع الملفات (" + processed + "/" + Math.max(totalPending, processed) + ")";

                yieldedOnBudget = false;
                boolean uploaded = processFile(nextFile, uploadToken, baseUrl, notificationTitle, dbHelper);

                if (!uploaded && yieldedOnBudget) {
                    // استسلام طوعي: أعِد الملف للطابور بلا أي عقوبة.
                    dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, null);
                    clearNotification();
                    return Result.retry();
                }

                if (isStopped()) {
                    // لا نحاسب الملف على إيقافٍ من النظام: أعِده للطابور كما هو.
                    dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, null);
                    Log.w(TAG, "أُوقف العامل أثناء الرفع — أُعيد الملف للطابور بلا عقوبة");
                    return Result.retry();
                }

                if (uploaded) {
                    dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PROCESSING_SERVER, null);
                    success++;
                    Log.d(TAG, "✅ رُفع للخادم، بانتظار معالجة Drive (" + success + "/" + processed + ")");
                } else {
                    sawFailure = true;

                    if (!InternetUtils.hasAnyNetwork(getApplicationContext())) {
                        // انقطاع الشبكة ليس ذنب الملف: أعِده بلا زيادة العداد.
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, "انقطعت الشبكة");
                        Log.w(TAG, "انقطعت الشبكة أثناء الرفع — تأجيل بلا عقوبة");
                        return Result.retry();
                    }

                    dbHelper.incrementRetryCount(nextFile.id);
                    int newRetryCount = nextFile.retryCount + 1;

                    if (newRetryCount >= FILE_MAX_RETRIES) {
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_FAILED,
                            "فشل بعد " + FILE_MAX_RETRIES + " محاولة");
                        Log.e(TAG, "❌ فشل نهائي بعد " + FILE_MAX_RETRIES + " محاولة");
                    } else {
                        dbHelper.updateFileStatus(nextFile.id, UploadDatabaseHelper.STATUS_PENDING, null);
                        Log.w(TAG, "⚠️ فشل — سيُعاد لاحقاً (" + newRetryCount + "/" + FILE_MAX_RETRIES + ")");

                        // مهم: لا نستمر في الحلقة على هذا الملف، وإلا صار الطابور
                        // حلقة مغلقة تحرق ٣٠ محاولة في ثوانٍ. نترك التراجع
                        // الأُسّي لـ WorkManager.
                        clearNotification();
                        return Result.retry();
                    }
                }
            }

            clearNotification();

            if (sawFailure) {
                return Result.retry();
            }
            return Result.success();

        } catch (Exception e) {
            Log.e(TAG, "فشل العامل كلياً", e);
            clearNotification();
            return Result.retry();
        }
    }

    /**
     * صندوق الوارد: يجلب من الخادم حالات الملفات التي انتهت معالجتها.
     *
     * ⚠️ لا نُقرّ (ack) إلا بما طبّقناه فعلاً محلياً. النسخة السابقة كانت تُقرّ
     * بكل شيء بلا شرط، فيحذف الخادم إشعار «فشل» لم يُطبَّق أبداً على الجهاز،
     * ويبقى الملف في processing_server إلى الأبد بانتظار خبر لن يصل مرة أخرى.
     */
    private void drainOfflineInbox(UploadDatabaseHelper dbHelper, String baseUrl, String token) {
        try {
            org.json.JSONArray ackIds = new org.json.JSONArray();

            Request request = new Request.Builder()
                .url(baseUrl + "/api/uploads/offline-inbox")
                .header("Authorization", "Bearer " + token)
                .header("Accept", "application/json")
                .get()
                .build();

            try (Response response = httpClient.newCall(request).execute()) {
                if (!response.isSuccessful() || response.body() == null) return;

                org.json.JSONObject jsonResponse = new org.json.JSONObject(response.body().string());
                if (!jsonResponse.optBoolean("success", false)) return;

                org.json.JSONArray dataArray = jsonResponse.optJSONArray("data");
                if (dataArray == null) return;

                for (int i = 0; i < dataArray.length(); i++) {
                    org.json.JSONObject fileObj = dataArray.getJSONObject(i);
                    int id = fileObj.optInt("id");
                    String fName = fileObj.optString("file_name");
                    String status = fileObj.optString("status");
                    String error = fileObj.optString("error_message", null);

                    UploadDatabaseHelper.UploadItem item = dbHelper.getFileByName(fName);
                    if (item == null) {
                        // لا نعرف هذا الملف — لا نُقرّ به حتى لا نُتلف إشعار جهاز آخر.
                        continue;
                    }

                    if ("completed".equals(status)) {
                        dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_COMPLETED, null);
                        ackIds.put(id);
                    } else if ("failed".equals(status)) {
                        // نُعيده للطابور بدل ابتلاع الخبر: الخادم فشل، والجهاز
                        // يملك الملف الأصلي ويستطيع المحاولة ثانيةً.
                        dbHelper.updateFileStatus(item.id, UploadDatabaseHelper.STATUS_PENDING,
                            error != null && !error.isEmpty() ? error : "فشلت المعالجة على الخادم");
                        ackIds.put(id);
                    }
                    // أي حالة أخرى (قيد المعالجة مثلاً): لا نُقرّ بها.
                }
            }

            if (ackIds.length() > 0) {
                org.json.JSONObject ackObj = new org.json.JSONObject();
                ackObj.put("ids", ackIds);
                RequestBody ackBody = RequestBody.create(
                    ackObj.toString(), MediaType.parse("application/json; charset=utf-8"));
                Request ackRequest = new Request.Builder()
                    .url(baseUrl + "/api/uploads/offline-inbox/ack")
                    .header("Authorization", "Bearer " + token)
                    .header("Accept", "application/json")
                    .post(ackBody)
                    .build();
                httpClient.newCall(ackRequest).execute().close();
            }
        } catch (Exception e) {
            Log.w(TAG, "تعذّر فحص صندوق الوارد", e);
        }
    }

    private boolean processFile(UploadDatabaseHelper.UploadItem item, String token, String baseUrl,
                                String notifTitle, UploadDatabaseHelper dbHelper) {
        try {
            String filePath = item.filePath;
            String fileName = item.fileName;
            String fileType = item.fileType;
            if (fileType == null) {
                fileType = "application/octet-stream";
            }
            long sponsorshipId = item.photoId;

            if (filePath == null || fileName == null) {
                Log.e(TAG, "معطيات ناقصة للعنصر " + item.id);
                return false;
            }

            if (filePath.startsWith("file://")) {
                filePath = filePath.substring(7);
            }

            long fileSize;
            Uri uri;

            if (filePath.startsWith("content://")) {
                uri = Uri.parse(filePath);
                fileSize = measureContentSize(uri);
            } else {
                File file = new File(getApplicationContext().getFilesDir(), filePath);
                if (!file.exists()) {
                    file = new File(filePath);
                }
                if (!file.exists()) {
                    Log.e(TAG, "الملف غير موجود: " + filePath);
                    return false;
                }
                fileSize = file.length();
                uri = Uri.fromFile(file);
            }

            if (fileSize <= 0) {
                Log.e(TAG, "الملف فارغ أو تعذّر قياسه: " + filePath);
                return false;
            }

            final int chunkSize = pickChunkSize();
            final int totalChunks = (int) ((fileSize + chunkSize - 1) / chunkSize);

            // معرّف رفع مستقر عبر المحاولات وفريد عبر الأجهزة.
            String uploadId = "u_" + getDeviceId() + "_" + item.id + "_" + chunkSize;
            String chunkUrl = baseUrl + "/api/mobile/upload-chunk";
            String statusUrl = baseUrl + "/api/mobile/upload-status/" + uploadId;

            java.util.Set<Integer> receivedChunks = fetchReceivedChunks(statusUrl, token);

            try (InputStream fileStream = openStream(uri)) {
                if (fileStream == null) {
                    Log.e(TAG, "تعذّر فتح مجرى القراءة: " + uri);
                    return false;
                }

                byte[] buffer = new byte[chunkSize];

                for (int chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    if (isStopped()) return false;

                    // استسلام طوعي قبل سقف WorkManager: الأجزاء المُرسَلة محفوظة
                    // على الخادم، فالجولة التالية تستأنف من هنا تماماً.
                    if (budgetExhausted()) {
                        Log.i(TAG, "ميزانية الجولة انتهت عند الجزء " + chunkIndex
                            + "/" + totalChunks + " — سيُستأنف لاحقاً");
                        yieldedOnBudget = true;
                        return false;
                    }

                    // ⚠️ readFully لا readOnce: القراءة القصيرة كانت تُزيح حدود
                    // الأجزاء عن العدد المُعلن فيُجمّع الخادم ملفاً مبتوراً.
                    int bytesRead = readFully(fileStream, buffer);
                    if (bytesRead <= 0) break;

                    if (receivedChunks.contains(chunkIndex)) {
                        Log.d(TAG, "تخطّي الجزء " + (chunkIndex + 1) + " (موجود على الخادم)");
                        continue;
                    }

                    updateProgressNotification(
                        notifTitle,
                        fileName + " — الجزء " + (chunkIndex + 1) + "/" + totalChunks,
                        chunkIndex + 1,
                        totalChunks
                    );

                    boolean isLast = (chunkIndex == totalChunks - 1);
                    boolean chunkSuccess = uploadChunkWithRetry(
                        chunkUrl, token, uploadId, chunkIndex, totalChunks,
                        fileName, fileType, sponsorshipId, buffer, bytesRead, isLast, dbHelper, item.id
                    );

                    if (!chunkSuccess) {
                        Log.e(TAG, "فشل الجزء " + chunkIndex + " بعد كل المحاولات");
                        return false;
                    }

                    // نبضة حياة: تمنع المُحرِّر من اعتبار الملف عالقاً أثناء
                    // رفع فيديو كبير يستغرق وقتاً طويلاً.
                    dbHelper.touchUpload(item.id);
                }
            }

            return true;

        } catch (Exception e) {
            Log.e(TAG, "فشل الرفع المُجزَّأ للعنصر " + item.id, e);
            return false;
        }
    }

    private InputStream openStream(Uri uri) throws IOException {
        return getApplicationContext().getContentResolver().openInputStream(uri);
    }

    private long measureContentSize(Uri uri) {
        // نحاول أولاً عبر البيانات الوصفية بدل قراءة الملف كاملاً — قراءة فيديو
        // بحجم ٥٠٠ ميجابايت لمجرد معرفة حجمه إهدار صريح للوقت والبطارية.
        try (android.database.Cursor cursor = getApplicationContext().getContentResolver()
                .query(uri, new String[]{android.provider.OpenableColumns.SIZE}, null, null, null)) {
            if (cursor != null && cursor.moveToFirst()) {
                int idx = cursor.getColumnIndex(android.provider.OpenableColumns.SIZE);
                if (idx >= 0 && !cursor.isNull(idx)) {
                    long size = cursor.getLong(idx);
                    if (size > 0) return size;
                }
            }
        } catch (Exception ignored) { }

        try (InputStream stream = openStream(uri)) {
            if (stream == null) return 0;
            long total = 0;
            byte[] scratch = new byte[64 * 1024];
            int read;
            while ((read = stream.read(scratch)) != -1) {
                total += read;
            }
            return total;
        } catch (Exception e) {
            Log.e(TAG, "تعذّر قياس حجم " + uri, e);
            return 0;
        }
    }

    /**
     * يملأ المخزن تماماً ما لم ينتهِ الملف. يُرجع عدد البايتات المقروءة فعلاً.
     */
    private int readFully(InputStream stream, byte[] buffer) throws IOException {
        int offset = 0;
        while (offset < buffer.length) {
            int read = stream.read(buffer, offset, buffer.length - offset);
            if (read == -1) break;
            offset += read;
        }
        return offset;
    }

    private java.util.Set<Integer> fetchReceivedChunks(String statusUrl, String token) {
        java.util.Set<Integer> received = new java.util.HashSet<>();
        try {
            Request request = new Request.Builder()
                .url(statusUrl)
                .header("Authorization", "Bearer " + token)
                .header("Accept", "application/json")
                .get()
                .build();

            try (Response response = httpClient.newCall(request).execute()) {
                if (response.isSuccessful() && response.body() != null) {
                    org.json.JSONObject json = new org.json.JSONObject(response.body().string());
                    org.json.JSONArray chunksArr = json.optJSONArray("received_chunks");
                    if (chunksArr != null) {
                        for (int i = 0; i < chunksArr.length(); i++) {
                            received.add(chunksArr.getInt(i));
                        }
                    }
                }
            }
        } catch (Exception e) {
            // فشل الاستعلام يعني إعادة الرفع من الصفر — مؤلم لكنه صحيح.
            Log.w(TAG, "تعذّر جلب حالة الأجزاء؛ سيُفترض عدم وصول شيء", e);
        }
        return received;
    }

    /**
     * إرسال جزء واحد مع إعادة محاولة وتراجع أُسّي.
     *
     * الجزء الأخير خاص: الخادم يبدأ عنده تجميع الملف كاملاً داخل نفس الطلب،
     * وهو ما قد يستغرق دقائق لفيديو كبير. لذلك نمنحه مهلة أطول بكثير — وإلا
     * انتهت مهلة العميل بينما الخادم نجح فعلاً، فيُعيد العميل رفع الملف كله.
     */
    private boolean uploadChunkWithRetry(String url, String token, String uploadId, int chunkIndex,
                                         int totalChunks, String fileName, String fileType,
                                         long sponsorshipId, byte[] buffer, int bytesRead,
                                         boolean isLast, UploadDatabaseHelper dbHelper, long itemId) {
        for (int attempt = 1; attempt <= CHUNK_MAX_ATTEMPTS; attempt++) {
            if (isStopped()) return false;

            ChunkResult result = uploadChunkData(url, token, uploadId, chunkIndex, totalChunks,
                fileName, fileType, sponsorshipId, buffer, bytesRead, isLast);

            if (result == ChunkResult.OK) {
                return true;
            }

            // خطأ دائم (٤٠١/٤٢٢…): لا فائدة من التكرار في هذه الجولة.
            if (result == ChunkResult.PERMANENT) {
                Log.e(TAG, "خطأ دائم في الجزء " + chunkIndex + " — إيقاف المحاولات");
                return false;
            }

            if (attempt < CHUNK_MAX_ATTEMPTS) {
                long delay = Math.min(CHUNK_BACKOFF_MAX_MS, CHUNK_BACKOFF_BASE_MS * (1L << (attempt - 1)));
                // عشوائية تمنع تزامن كل الأجهزة على نفس اللحظة.
                delay += (long) (Math.random() * 1000);
                Log.w(TAG, "الجزء " + chunkIndex + " فشل (محاولة " + attempt + "/" + CHUNK_MAX_ATTEMPTS
                    + ") — إعادة بعد " + delay + "ms");
                dbHelper.touchUpload(itemId);
                try {
                    Thread.sleep(delay);
                } catch (InterruptedException ie) {
                    Thread.currentThread().interrupt();
                    return false;
                }
            }
        }
        return false;
    }

    private enum ChunkResult { OK, TRANSIENT, PERMANENT }

    private ChunkResult uploadChunkData(String urlString, String token, String uploadId, int chunkIndex,
                                        int totalChunks, String fileName, String fileType,
                                        long sponsorshipId, byte[] buffer, int bytesRead, boolean isLast) {
        try {
            RequestBody body = RequestBody.create(
                buffer, MediaType.parse("application/octet-stream"), 0, bytesRead);

            Request request = new Request.Builder()
                .url(urlString)
                .header("Authorization", "Bearer " + token)
                .header("Accept", "application/json")
                .header("X-Upload-Id", uploadId)
                .header("X-Chunk-Index", String.valueOf(chunkIndex))
                .header("X-Total-Chunks", String.valueOf(totalChunks))
                .header("X-Chunk-Size", String.valueOf(bytesRead))
                .header("X-File-Name", fileName)
                .header("X-File-Type", fileType)
                .header("X-Sponsorship-Id", String.valueOf(sponsorshipId))
                .header("Bypass-Tunnel-Reminder", "true")
                .post(body)
                .build();

            // الجزء الأخير يشغّل التجميع على الخادم: مهلة أطول بكثير.
            OkHttpClient client = isLast
                ? httpClient.newBuilder()
                    .readTimeout(10, TimeUnit.MINUTES)
                    .callTimeout(12, TimeUnit.MINUTES)
                    .build()
                : httpClient;

            try (Response response = client.newCall(request).execute()) {
                int code = response.code();

                if (code < 400) {
                    return ChunkResult.OK;
                }

                // ٤٠٩ = الخادم يعرف الجزء مسبقاً؛ نعتبره نجاحاً (خاصية التكرار الآمن).
                if (code == 409) {
                    return ChunkResult.OK;
                }

                // ٤٢٩ و٥xx و٤٠٨ عابرة — تستحق إعادة المحاولة.
                if (code == 408 || code == 429 || code >= 500) {
                    Log.w(TAG, "خطأ عابر في الجزء: HTTP " + code);
                    return ChunkResult.TRANSIENT;
                }

                Log.e(TAG, "خطأ دائم في الجزء: HTTP " + code);
                return ChunkResult.PERMANENT;
            }
        } catch (Exception e) {
            // كل استثناءات الشبكة عابرة بطبيعتها.
            Log.w(TAG, "استثناء شبكة في الجزء " + chunkIndex + ": " + e.getMessage());
            return ChunkResult.TRANSIENT;
        }
    }
}
