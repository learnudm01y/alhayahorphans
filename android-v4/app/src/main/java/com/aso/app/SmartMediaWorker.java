package com.aso.app;

import android.content.Context;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

/**
 * [SmartMedia] Worker يعالج الصفوف في حالة 'processing' محلياً قبل دخول
 * طابور الرفع (المسار: Prepare → Compress → Queue → Upload).
 *
 * الضغط خارج ChunkedUploadWorker تماماً — لا يتغير منطق الرفع المجزأ.
 * عند النجاح يُستبدل file_path بالملف النهائي وتُحفظ بيانات الضغط ثم تُنقل
 * الحالة إلى upload_pending ليحجزها عامل الرفع.
 */
public class SmartMediaWorker extends Worker {
    private static final String TAG = "SmartMediaWorker";

    private static final int MAX_FILES_PER_RUN = 5;

    private final UploadDatabaseHelper dbHelper;

    public SmartMediaWorker(@NonNull Context context, @NonNull WorkerParameters workerParams) {
        super(context, workerParams);
        dbHelper = UploadDatabaseHelper.getInstance(context);
    }

    @NonNull
    @Override
    public Result doWork() {
        Context ctx = getApplicationContext();
        long runStartedAt = System.currentTimeMillis();

        Log.i(TAG, "[SmartMedia] Processing sweep started");

        // شبكة أمان: كل صف عالق في 'processing' منذ مدة طويلة (مات التطبيق
        // أثناء الضغط) يُعاد للأصل حتى لا يُحبَس ملف إلى الأبد.
        try {
            int reclaimed = dbHelper.reclaimStaleLocalProcessing();
            if (reclaimed > 0) {
                Log.w(TAG, "reclaimed " + reclaimed + " stale local-processing rows → pending (fallback original)");
            }
        } catch (Exception e) {
            Log.w(TAG, "reclaim stale processing failed: " + e.getMessage());
        }

        // تنظيف الملفات المؤقتة غير المرتبطة بأي صف نشط.
        try {
            SmartMediaProcessor.cleanupOrphanedTempFiles(ctx, dbHelper);
        } catch (Exception e) {
            Log.w(TAG, "cleanup failed: " + e.getMessage());
        }

        if (!SmartMediaProcessor.isEnabled(ctx)) {
            Log.d(TAG, "smart compression disabled — nothing to process");
            return Result.success();
        }

        int processed = 0;
        try {
            while (processed < MAX_FILES_PER_RUN) {
                if (isStopped()) {
                    Log.w(TAG, "stopped — clean exit");
                    return Result.retry();
                }

                long elapsed = System.currentTimeMillis() - runStartedAt;
                if (elapsed > SmartMediaProcessor.PROCESS_BUDGET_MS) {
                    Log.i(TAG, "run budget exceeded — leaving rest for next run");
                    break;
                }

                // حجز ذرّي لأول ملف قيد المعالجة المحلية.
                UploadDatabaseHelper.UploadItem item = dbHelper.claimNextProcessingFile();
                if (item == null) {
                    break;
                }

                processed++;
                Log.d(TAG, "processing file #" + processed + ": " + item.fileName);

                long budgetLeft = SmartMediaProcessor.PROCESS_BUDGET_MS - elapsed;
                SmartMediaProcessor.ProcessResult result =
                        SmartMediaProcessor.processFile(ctx, item.filePath, item.fileType,
                                item.fileName, budgetLeft);

                if (result.compressed) {
                    dbHelper.markProcessed(item.id, result.finalPath, result.finalFileName,
                            result.originalSize, result.processedSize, true, result.type, result.ratio);
                    String msg = "تم تحسين الملف (الحجم الأصلي: " + formatSize(result.originalSize)
                            + " → " + formatSize(result.processedSize)
                            + "، تم توفير " + result.ratio + "%)";
                    Log.i(TAG, "Queueing processed file id=" + item.id
                            + " original=" + result.originalSize + " processed=" + result.processedSize);
                    UploadServicePlugin.notifyMediaStatusChanged(item.id, "processed", msg,
                            result.originalSize, result.processedSize, result.ratio);
                } else if (result.error == null) {
                    // تخطي بلا فائدة (صغير / أصلي أفضل): نُدرج الأصل في الطابور.
                    dbHelper.markProcessed(item.id, result.finalPath, result.finalFileName,
                            result.originalSize, result.processedSize, false, "none", 0);
                    String msg = "استُخدم الملف الأصلي (لا فائدة من الضغط)";
                    UploadServicePlugin.notifyMediaStatusChanged(item.id, "skipped", msg,
                            result.originalSize, result.processedSize, 0);
                } else {
                    // فشل: سقوط آمن حسب السياسة (الافتراضي: استخدام الأصل).
                    dbHelper.markCompressionFailed(item.id, result.error, SmartMediaProcessor.FALLBACK_TO_ORIGINAL);
                    String msg = SmartMediaProcessor.FALLBACK_TO_ORIGINAL
                            ? "تعذّر الضغط — سيُرفع الملف الأصلي"
                            : "فشل الضغط";
                    Log.w(TAG, "compression failed id=" + item.id + " reason=" + result.error);
                    UploadServicePlugin.notifyMediaStatusChanged(item.id, "failed", msg,
                            result.originalSize, result.processedSize, 0);
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "processing sweep failed", e);
            return Result.retry();
        }

        // شغّل الرفع إن تحرّرت ملفات (upload_pending) للرفع المجزأ.
        try {
            UploadTaskScheduler.getInstance(ctx).startImmediateUpload();
        } catch (Exception e) {
            Log.w(TAG, "failed to trigger upload: " + e.getMessage());
        }

        // إن بقي شيء قيد المعالجة فابقَ حياً (retry) حتى يفرغ الطابور.
        if (dbHelper.getProcessingFilesCount() > 0) {
            Log.d(TAG, "more processing rows remain — continuing");
            return Result.retry();
        }

        Log.i(TAG, "[SmartMedia] Processing sweep done (" + processed + " files)");
        return Result.success();
    }

    private String formatSize(long bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return String.format("%.2f KB", bytes / 1024.0);
        return String.format("%.2f MB", bytes / (1024.0 * 1024.0));
    }
}
