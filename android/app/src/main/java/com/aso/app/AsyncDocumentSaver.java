package com.aso.app;

import android.content.Context;
import android.net.Uri;
import android.os.Environment;
import android.util.Log;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.LinkedBlockingQueue;
import java.util.concurrent.ThreadPoolExecutor;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.concurrent.atomic.AtomicLong;

/**
 * ✅ MEMORY-SAFE Document Saver
 *
 * النسخ يتم بـ 8KB فقط (ليس MB!) - آمن للذاكرة
 * فحص الذاكرة قبل كل عملية (threshold 85%)
 * Rate limiting (500ms بين كل حفظ)
 * حد أقصى للملف (100MB)
 * Thread pool محكم (max 2 threads)
 * تنظيف صحيح ومعالجة أخطاء
 */
public class AsyncDocumentSaver {
    private static final String TAG = "AsyncDocSaver";

    // Thread Pool - SMALL AND CONTROLLED
    private static final int CORE_THREADS = 1;      // خيط واحد عادة
    private static final int MAX_THREADS = 2;       // حد أقصى 2 خيوط متزامنة
    private static final int QUEUE_CAPACITY = 20;   // حد أقصى 20 ملف منتظر

    // Buffer - SMALL KB BUFFER
    private static final int BUFFER_SIZE = 8 * 1024;  // 8KB buffer - آمن!

    // Memory Protection
    private static final float MEMORY_THRESHOLD = 0.85f;  // حد 85% من الذاكرة
    private static final long MAX_FILE_SIZE = 100 * 1024 * 1024L; // حد أقصى 100MB

    // Rate Limiting
    private static final long MIN_DELAY_BETWEEN_SAVES = 500; // 500ms تأخير
    private final AtomicLong lastSaveTime = new AtomicLong(0);

    private static AsyncDocumentSaver instance;
    private final ExecutorService executorService;
    private final AtomicInteger activeTasksCount = new AtomicInteger(0);
    private final Context appContext;

    private AsyncDocumentSaver(Context context) {
        this.appContext = context.getApplicationContext();

        // إنشاء thread pool محكم
        this.executorService = new ThreadPoolExecutor(
            CORE_THREADS,
            MAX_THREADS,
            60L, TimeUnit.SECONDS,
            new LinkedBlockingQueue<>(QUEUE_CAPACITY),
            r -> {
                Thread t = new Thread(r, "DocSaver-" + System.currentTimeMillis());
                t.setPriority(Thread.MIN_PRIORITY); // أولوية منخفضة - لا يعطل UI
                return t;
            },
            new ThreadPoolExecutor.DiscardOldestPolicy() // حذف المهام القديمة إذا امتلأ
        );

        Log.e(TAG, "✅ AsyncDocumentSaver initialized");
        Log.e(TAG, "   📦 Buffer: " + (BUFFER_SIZE / 1024) + " KB (memory safe)");
        Log.e(TAG, "   🧵 Max threads: " + MAX_THREADS);
        Log.e(TAG, "   📋 Queue capacity: " + QUEUE_CAPACITY);
        Log.e(TAG, "   🧠 Memory threshold: " + (int)(MEMORY_THRESHOLD * 100) + "%");
    }

    public static synchronized AsyncDocumentSaver getInstance(Context context) {
        if (instance == null) {
            instance = new AsyncDocumentSaver(context);
        }
        return instance;
    }

    /**
     * حفظ ملف بشكل async مع حماية كاملة للذاكرة
     */
    public void saveAsync(Uri sourceUri, String fileName, String associationName,
                         String personName, SaveCallback callback) {

        // الخطوة 1: فحص الذاكرة قبل الإضافة للقائمة
        if (!checkMemoryAvailable()) {
            Log.w(TAG, "⚠️ ذاكرة منخفضة - تم تخطي الحفظ: " + fileName);
            if (callback != null) {
                callback.onComplete(false, "ذاكرة منخفضة - تم تخطي الحفظ");
            }
            return;
        }

        // الخطوة 2: فحص المهام النشطة
        int activeTasks = activeTasksCount.get();
        if (activeTasks >= MAX_THREADS) {
            Log.w(TAG, "⚠️ وصلنا للحد الأقصى - في الانتظار: " + fileName);
        }

        // الخطوة 3: Rate limiting - منع الحفظ السريع المتتالي
        long now = System.currentTimeMillis();
        long lastSave = lastSaveTime.get();
        if (now - lastSave < MIN_DELAY_BETWEEN_SAVES) {
            long delay = MIN_DELAY_BETWEEN_SAVES - (now - lastSave);
            try {
                Thread.sleep(delay);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        }

        Log.e(TAG, "📤 طلب حفظ: " + fileName);
        Log.e(TAG, "   🧵 مهام نشطة: " + activeTasks + "/" + MAX_THREADS);

        // الخطوة 4: تنفيذ في الخلفية مع حماية الذاكرة
        executorService.submit(() -> {
            activeTasksCount.incrementAndGet();
            lastSaveTime.set(System.currentTimeMillis());

            try {
                Log.e(TAG, "🏃 بدء الحفظ: " + fileName);

                // فحص الذاكرة مرة أخرى قبل الحفظ الفعلي
                if (!checkMemoryAvailable()) {
                    Log.w(TAG, "⚠️ ذاكرة منخفضة أثناء الحفظ - إلغاء: " + fileName);
                    if (callback != null) {
                        callback.onComplete(false, "ذاكرة منخفضة");
                    }
                    return;
                }

                boolean success = saveToDocuments(sourceUri, fileName, associationName, personName);

                if (callback != null) {
                    callback.onComplete(success, success ? "تم الحفظ بنجاح" : "فشل الحفظ");
                }

            } catch (OutOfMemoryError e) {
                Log.e(TAG, "❌ نفذت الذاكرة: " + fileName);
                System.gc(); // فرض تنظيف الذاكرة
                if (callback != null) {
                    callback.onComplete(false, "خطأ: ذاكرة ممتلئة");
                }
            } catch (Exception e) {
                Log.e(TAG, "❌ خطأ في الحفظ " + fileName + ": " + e.getMessage());
                if (callback != null) {
                    callback.onComplete(false, "خطأ: " + e.getMessage());
                }
            } finally {
                activeTasksCount.decrementAndGet();
                Log.e(TAG, "✅ انتهى: " + fileName + " (نشط: " + activeTasksCount.get() + ")");
            }
        });
    }

    /**
     * فحص إذا كانت الذاكرة متاحة
     */
    private boolean checkMemoryAvailable() {
        Runtime runtime = Runtime.getRuntime();
        long maxMemory = runtime.maxMemory();
        long usedMemory = runtime.totalMemory() - runtime.freeMemory();
        float memoryUsage = (float) usedMemory / maxMemory;

        if (memoryUsage > MEMORY_THRESHOLD) {
            Log.w(TAG, "⚠️ استخدام الذاكرة: " + (int)(memoryUsage * 100) + "% (الحد: " +
                  (int)(MEMORY_THRESHOLD * 100) + "%)");
            return false;
        }

        return true;
    }

    /**
     * منطق الحفظ الأساسي - مع buffer 8KB آمن للذاكرة
     * ✨ الآن يحفظ في Public Documents (متاح لجميع التطبيقات)
     */
    private boolean saveToDocuments(Uri sourceUri, String fileName,
                                   String associationName, String personName) {
        InputStream inputStream = null;
        FileOutputStream outputStream = null;

        try {
            // ✅ ANDROID 10+ (API 29+): استخدام MediaStore للحفظ في Public Documents
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.Q) {
                return saveToPublicDocumentsViaMediaStore(sourceUri, fileName, associationName, personName);
            }

            // ✅ ANDROID 9 وما قبل: استخدام getExternalStoragePublicDirectory
            File documentsDir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOCUMENTS);
            if (documentsDir == null) {
                Log.e(TAG, "❌ لا يمكن الوصول لمجلد Documents العام");
                // Fallback إلى app-specific
                documentsDir = appContext.getExternalFilesDir(Environment.DIRECTORY_DOCUMENTS);
                if (documentsDir == null) {
                    Log.e(TAG, "❌ لا يمكن الوصول لمجلد Documents");
                    return false;
                }
            }

            // إنشاء هيكل المجلدات
            File mainDir = new File(documentsDir, "sponsorships_alhayahorphans");
            String safeAssociation = sanitizeFileName(associationName != null ? associationName : "General");
            String safePerson = sanitizeFileName(personName != null ? personName : "Unknown");

            File associationDir = new File(mainDir, safeAssociation);
            File personDir = new File(associationDir, safePerson);

            if (!personDir.exists()) {
                boolean created = personDir.mkdirs();
                if (!created) {
                    Log.e(TAG, "❌ فشل إنشاء المجلد: " + personDir.getAbsolutePath());
                    return false;
                }
                Log.e(TAG, "✅ تم إنشاء المجلد: " + personDir.getAbsolutePath());
            }

            // إنشاء الملف النهائي
            File destinationFile = new File(personDir, fileName);

            // فتح التدفقات
            inputStream = appContext.getContentResolver().openInputStream(sourceUri);
            if (inputStream == null) {
                Log.e(TAG, "❌ لا يمكن فتح تدفق الإدخال");
                return false;
            }

            outputStream = new FileOutputStream(destinationFile);

            // النسخ بـ buffer 8KB صغير - آمن للذاكرة!
            byte[] buffer = new byte[BUFFER_SIZE]; // 8KB فقط!
            long bytesWritten = 0;
            int bytesRead;
            long startTime = System.currentTimeMillis();
            int chunkCount = 0;

            while ((bytesRead = inputStream.read(buffer)) != -1) {
                outputStream.write(buffer, 0, bytesRead);
                bytesWritten += bytesRead;
                chunkCount++;

                // فحص إذا كان الملف كبير جداً
                if (bytesWritten > MAX_FILE_SIZE) {
                    Log.w(TAG, "⚠️ ملف كبير جداً (>" + (MAX_FILE_SIZE / 1024 / 1024) + "MB) - إلغاء");
                    destinationFile.delete();
                    return false;
                }

                // عرض التقدم كل 5MB
                if (bytesWritten % (5 * 1024 * 1024) == 0) {
                    double currentMB = bytesWritten / 1024.0 / 1024.0;
                    Log.e(TAG, "   📊 تقدم: " + String.format("%.1f", currentMB) + " MB");
                }

                // فحص الذاكرة دورياً (كل 1MB)
                if (bytesWritten % (1024 * 1024) == 0) {
                    if (!checkMemoryAvailable()) {
                        Log.w(TAG, "⚠️ ذاكرة منخفضة أثناء النسخ - إلغاء");
                        destinationFile.delete();
                        return false;
                    }
                }
            }

            outputStream.flush();

            long duration = System.currentTimeMillis() - startTime;
            double sizeMB = bytesWritten / 1024.0 / 1024.0;

            Log.e(TAG, "✅ تم حفظ الملف بنجاح:");
            Log.e(TAG, "   📁 ملف: " + fileName);
            Log.e(TAG, "   📊 حجم: " + String.format("%.2f", sizeMB) + " MB (" + bytesWritten + " bytes)");
            Log.e(TAG, "   📦 قطع: " + chunkCount + " × 8KB");
            Log.e(TAG, "   ⏱️  مدة: " + duration + " ms (" + String.format("%.1f", duration/1000.0) + "s)");
            Log.e(TAG, "   📍 مكان: " + destinationFile.getAbsolutePath());

            // التحقق من الملف
            if (!destinationFile.exists() || destinationFile.length() != bytesWritten) {
                Log.e(TAG, "❌ فشل التحقق من الملف");
                return false;
            }

            return true;

        } catch (OutOfMemoryError e) {
            Log.e(TAG, "❌ نفذت الذاكرة أثناء الحفظ: " + fileName);
            System.gc();
            return false;
        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في حفظ الملف: " + e.getMessage());
            e.printStackTrace();
            return false;
        } finally {
            // مهم جداً: إغلاق التدفقات لتحرير الذاكرة
            try {
                if (inputStream != null) inputStream.close();
                if (outputStream != null) outputStream.close();
            } catch (Exception e) {
                Log.e(TAG, "خطأ في إغلاق التدفقات: " + e.getMessage());
            }
        }
    }

    /**
     * تنظيف اسم الملف للأمان
     */
    private String sanitizeFileName(String name) {
        if (name == null || name.isEmpty()) {
            return "Unknown";
        }
        String sanitized = name.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_")
                              .replaceAll("\\s+", "_");
        return sanitized.substring(0, Math.min(sanitized.length(), 100));
    }

    /**
     * Callback interface للإنجاز async
     */
    public interface SaveCallback {
        void onComplete(boolean success, String message);
    }

    /**
     * الحصول على الحالة الحالية
     */
    public int getActiveTasks() {
        return activeTasksCount.get();
    }

    /**
     * إيقاف executor (استدعاء عند إغلاق التطبيق)
     */
    public void shutdown() {
        executorService.shutdown();
        Log.e(TAG, "🛑 تم إيقاف AsyncDocumentSaver");
    }

    /**
     * ✅ حفظ في Public Documents عبر MediaStore API (Android 10+)
     * هذا يضمن بقاء الملفات حتى بعد حذف التطبيق
     */
    @android.annotation.TargetApi(android.os.Build.VERSION_CODES.Q)
    private boolean saveToPublicDocumentsViaMediaStore(Uri sourceUri, String fileName,
                                                       String associationName, String personName) {
        InputStream inputStream = null;
        FileOutputStream outputStream = null;

        try {
            Log.e(TAG, "📱 استخدام MediaStore للحفظ في Documents العام (Android 10+)");

            // بناء المسار النسبي
            String safeAssociation = sanitizeFileName(associationName != null ? associationName : "General");
            String safePerson = sanitizeFileName(personName != null ? personName : "Unknown");
            String relativePath = Environment.DIRECTORY_DOCUMENTS + "/sponsorships_alhayahorphans/"
                                + safeAssociation + "/" + safePerson;

            // إعداد ContentValues
            android.content.ContentValues values = new android.content.ContentValues();
            values.put(android.provider.MediaStore.MediaColumns.DISPLAY_NAME, fileName);
            values.put(android.provider.MediaStore.MediaColumns.RELATIVE_PATH, relativePath);

            // تحديد MIME type
            String mimeType = "application/octet-stream";
            if (fileName.toLowerCase().endsWith(".jpg") || fileName.toLowerCase().endsWith(".jpeg")) {
                mimeType = "image/jpeg";
                values.put(android.provider.MediaStore.MediaColumns.MIME_TYPE, mimeType);
            } else if (fileName.toLowerCase().endsWith(".png")) {
                mimeType = "image/png";
                values.put(android.provider.MediaStore.MediaColumns.MIME_TYPE, mimeType);
            } else if (fileName.toLowerCase().endsWith(".mp4")) {
                mimeType = "video/mp4";
                values.put(android.provider.MediaStore.MediaColumns.MIME_TYPE, mimeType);
            }

            // اختيار Collection المناسب
            android.net.Uri collection;
            if (mimeType.startsWith("image/")) {
                collection = android.provider.MediaStore.Images.Media.getContentUri(android.provider.MediaStore.VOLUME_EXTERNAL_PRIMARY);
            } else if (mimeType.startsWith("video/")) {
                collection = android.provider.MediaStore.Video.Media.getContentUri(android.provider.MediaStore.VOLUME_EXTERNAL_PRIMARY);
            } else {
                // للملفات الأخرى، استخدام Downloads
                collection = android.provider.MediaStore.Downloads.getContentUri(android.provider.MediaStore.VOLUME_EXTERNAL_PRIMARY);
            }

            // إنشاء الملف في MediaStore
            android.net.Uri itemUri = appContext.getContentResolver().insert(collection, values);
            if (itemUri == null) {
                Log.e(TAG, "❌ فشل إنشاء الملف في MediaStore");
                return false;
            }

            Log.e(TAG, "✅ تم إنشاء URI في MediaStore: " + itemUri);

            // فتح التدفقات
            inputStream = appContext.getContentResolver().openInputStream(sourceUri);
            if (inputStream == null) {
                Log.e(TAG, "❌ لا يمكن فتح تدفق الإدخال");
                appContext.getContentResolver().delete(itemUri, null, null);
                return false;
            }

            outputStream = (FileOutputStream) appContext.getContentResolver().openOutputStream(itemUri);
            if (outputStream == null) {
                Log.e(TAG, "❌ لا يمكن فتح تدفق الإخراج");
                appContext.getContentResolver().delete(itemUri, null, null);
                return false;
            }

            // النسخ بـ buffer 8KB
            byte[] buffer = new byte[BUFFER_SIZE];
            long bytesWritten = 0;
            int bytesRead;
            long startTime = System.currentTimeMillis();

            while ((bytesRead = inputStream.read(buffer)) != -1) {
                outputStream.write(buffer, 0, bytesRead);
                bytesWritten += bytesRead;

                // فحص الحجم
                if (bytesWritten > MAX_FILE_SIZE) {
                    Log.w(TAG, "⚠️ ملف كبير جداً - إلغاء");
                    appContext.getContentResolver().delete(itemUri, null, null);
                    return false;
                }

                // فحص الذاكرة
                if (bytesWritten % (1024 * 1024) == 0 && !checkMemoryAvailable()) {
                    Log.w(TAG, "⚠️ ذاكرة منخفضة - إلغاء");
                    appContext.getContentResolver().delete(itemUri, null, null);
                    return false;
                }
            }

            long copyTime = System.currentTimeMillis() - startTime;
            double sizeInMB = bytesWritten / 1024.0 / 1024.0;

            Log.e(TAG, "✅ تم الحفظ في Documents العام بنجاح!");
            Log.e(TAG, "   📁 المسار النسبي: " + relativePath);
            Log.e(TAG, "   📄 اسم الملف: " + fileName);
            Log.e(TAG, "   📦 الحجم: " + String.format("%.2f", sizeInMB) + " MB");
            Log.e(TAG, "   ⏱️  الوقت: " + copyTime + " ms");

            return true;

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في الحفظ عبر MediaStore: " + e.getMessage(), e);
            return false;
        } finally {
            try {
                if (inputStream != null) inputStream.close();
                if (outputStream != null) outputStream.close();
            } catch (Exception ignored) {
            }
        }
    }
}
