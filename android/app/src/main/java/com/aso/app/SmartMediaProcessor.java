package com.aso.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Matrix;
import android.media.ExifInterface;
import android.media.MediaCodecInfo;
import android.media.MediaExtractor;
import android.media.MediaFormat;
import android.net.Uri;
import android.os.Handler;
import android.os.HandlerThread;
import android.os.StatFs;
import android.provider.OpenableColumns;
import android.util.Log;

import androidx.media3.common.MediaItem;
import androidx.media3.common.MimeTypes;
import androidx.media3.transformer.Composition;
import androidx.media3.transformer.DefaultEncoderFactory;
import androidx.media3.transformer.ExportException;
import androidx.media3.transformer.ExportResult;
import androidx.media3.transformer.Transformer;
import androidx.media3.transformer.VideoEncoderSettings;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;

/**
 * [SmartMedia] الضغط الذكي للصور والفيديو قبل دخول طابور الرفع.
 *
 * المبادئ (حسب المواصفات):
 *  - الأصل لا يُرفع أبداً عند تفعيل الضغط.
 *  - Streaming للفيديو (Media3 Transformer) — لا byte[] كامل.
 *  - صور: JPEG بجودة 85-90%، إزالة Metadata مع الحفاظ على Orientation،
 *    لا تصغير للصور الصغيرة، حد أقصى MAX_IMAGE_DIMENSION قابل للتهيئة.
 *  - فيديو: الحفاظ على الدقة/FPS/الصوت، خفض Bitrate أولاً، ثم خفض الدقة
 *    فقط عند الضخامة المفرطة. H.264 كخيار توافق أساسي.
 *  - حد أدنى للحجم (1MB) — لا ضغط بلا فائدة.
 *  - اختيار النسخة الأفضل: لا نستعمل المضغوطة إن لم تكن أصغر.
 *  - فحص المساحة الحرة قبل الفيديو الكبير + تنظيف الملفات المؤقتة.
 *  - Logs بادئة [SmartMedia] بدون توكنات أو بيانات شخصية.
 */
public final class SmartMediaProcessor {
    private static final String TAG = "SmartMedia";

    // ─── الإعدادات (قابلة للتهيئة هنا — لا hard-code في أماكن متفرقة) ───
    /** لا نضغط ملفات أصغر من هذا الحد. */
    public static final long MIN_COMPRESSION_SIZE = 1 * 1024 * 1024L;       // 1 MB
    /** جودة الصور: 85-90%. */
    public static final int IMAGE_QUALITY = 87;
    /** حد أقصى لبعد الصورة (الأكبر بين العرض والطول). */
    public static final int MAX_IMAGE_DIMENSION = 2560;
    /** حد أقصى لبعد الفيديو قبل خفض الدقة. */
    public static final int MAX_VIDEO_DIMENSION = 1920;
    /** FPS افتراضي عندما لا يُعلنه ملف الإدخال. */
    public static final int DEFAULT_FPS = 30;
    /** سقف Bitrate للفيديو (1080p). */
    public static final int MAX_VIDEO_BITRATE = 4_500_000;
    /** سياسة الفشل: استخدم الأصل عند فشل الضغط. */
    public static final boolean FALLBACK_TO_ORIGINAL = true;
    /** أقل مساحة حرة مطلوبة قبل بدء معالجة فيديو. */
    public static final long MIN_FREE_SPACE = 100L * 1024 * 1024;           // 100 MB
    /** ميزانية الجولة الواحدة (تسليم الباقي لجولة تالية قبل سقف WorkManager). */
    public static final long PROCESS_BUDGET_MS = 15 * 60 * 1000L;           // 15 دقيقة

    public static final String PREFS_NAME = "upload_state";
    public static final String KEY_ENABLED = "smart_compression_enabled";

    private SmartMediaProcessor() { }

    // ─── الإعداد: ON/OFF عبر SharedPreferences الموجودة ───
    public static boolean isEnabled(Context context) {
        try {
            SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
            return prefs.getBoolean(KEY_ENABLED, true); // الافتراضي ON
        } catch (Exception e) {
            return true;
        }
    }

    public static void setEnabled(Context context, boolean enabled) {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                .edit().putBoolean(KEY_ENABLED, enabled).apply();
    }

    // ─── أدوات الحجم والمسارات ───
    public static String stripFilePrefix(String path) {
        if (path != null && path.startsWith("file://")) {
            return path.substring(7);
        }
        return path;
    }

    public static long getFileSize(Context context, String path) {
        if (path == null || path.isEmpty()) return 0;
        if (path.startsWith("content://")) {
            try {
                Uri uri = Uri.parse(path);
                android.database.Cursor cur = context.getContentResolver().query(
                        uri, new String[]{OpenableColumns.SIZE}, null, null, null);
                if (cur != null && cur.moveToFirst()) {
                    int idx = cur.getColumnIndex(OpenableColumns.SIZE);
                    if (idx >= 0 && !cur.isNull(idx)) {
                        long size = cur.getLong(idx);
                        cur.close();
                        if (size > 0) return size;
                    } else {
                        cur.close();
                    }
                }
            } catch (Exception ignored) { }
            try (InputStream is = context.getContentResolver().openInputStream(Uri.parse(path))) {
                if (is == null) return 0;
                byte[] b = new byte[64 * 1024];
                long total = 0;
                int r;
                while ((r = is.read(b)) != -1) total += r;
                return total;
            } catch (Exception e) {
                return 0;
            }
        }
        File f = new File(stripFilePrefix(path));
        return f.exists() ? f.length() : 0;
    }

    private static InputStream open(Context context, String path) throws Exception {
        if (path.startsWith("content://")) {
            InputStream is = context.getContentResolver().openInputStream(Uri.parse(path));
            if (is == null) throw new Exception("cannot open content:// stream");
            return is;
        }
        return new FileInputStream(stripFilePrefix(path));
    }

    // ─── مجلد العمل المؤقت للملفات المحسّنة (تخزين خاص — لا يتأثر بالكاميرا) ───
    public static File smartMediaDir(Context context) {
        File base = context.getExternalFilesDir(null);
        if (base == null) base = context.getFilesDir();
        File dir = new File(base, "smartmedia");
        if (!dir.exists()) dir.mkdirs();
        return dir;
    }

    public static long freeBytes(Context context) {
        try {
            StatFs stat = new StatFs(smartMediaDir(context).getAbsolutePath());
            return stat.getAvailableBytes();
        } catch (Exception e) {
            return 0;
        }
    }

    // ─── الكشف عن نوع الوسائط ───
    private static String mimeFromExtension(String name) {
        if (name == null) return "application/octet-stream";
        String lower = name.toLowerCase();
        if (lower.endsWith(".jpg") || lower.endsWith(".jpeg")) return "image/jpeg";
        if (lower.endsWith(".png")) return "image/png";
        if (lower.endsWith(".webp")) return "image/webp";
        if (lower.endsWith(".heic") || lower.endsWith(".heif")) return "image/heic";
        if (lower.endsWith(".gif")) return "image/gif";
        if (lower.endsWith(".mp4") || lower.endsWith(".m4v")) return "video/mp4";
        if (lower.endsWith(".3gp") || lower.endsWith(".3gpp")) return "video/3gpp";
        if (lower.endsWith(".mkv")) return "video/x-matroska";
        if (lower.endsWith(".mov")) return "video/quicktime";
        return "application/octet-stream";
    }

    public static String resolveType(String fileType, String fileName) {
        if (fileType != null) {
            String t = fileType.toLowerCase();
            if (t.startsWith("image/") || t.startsWith("video/")) return t;
        }
        return mimeFromExtension(fileName);
    }

    /** منطق خالص قابل للاختبار: هل يستحق هذا الملف معالجة؟ */
    public static boolean shouldProcessByType(String fileType, String fileName, long size) {
        String type = resolveType(fileType, fileName);
        boolean media = type.startsWith("image/") || type.startsWith("video/");
        return media && size >= MIN_COMPRESSION_SIZE;
    }

    /**
     * الدالة المركزية لنقاط الدخول الثلاث.
     * يقرر: معالجة محلية (حالة processing → يضغط SmartMediaWorker) أم إدراج
     * مباشر في طابور الرفع (pending). الأصل لا يدخل حالة قابلة للرفع أبداً
     * عند تفعيل الضغط.
     */
    public static long queueForUpload(Context context, UploadDatabaseHelper db,
                                      String filePath, String fileName, String fileType,
                                      int photoId, String apiUrl, String authToken,
                                      String associationName, String personName) {
        long size = getFileSize(context, filePath);
        boolean process = isEnabled(context) && shouldProcessByType(fileType, fileName, size);

        if (!process) {
            return db.addFileToQueue(filePath, fileName, fileType, photoId, apiUrl, authToken,
                    associationName, personName);
        }

        long id = db.addFileToQueueForProcessing(filePath, fileName, fileType, photoId, apiUrl,
                authToken, associationName, personName, size);
        if (id > 0) {
            Log.i(TAG, "Processing started id=" + id + " type=" + fileType + " original=" + size);
            UploadServicePlugin.notifyMediaStatusChanged(id, "processing",
                    "جاري تجهيز الملف...", size, 0, 0.0);
            UploadTaskScheduler.getInstance(context).scheduleSmartMediaProcessing();
        }
        return id;
    }

    // ═══════════════════════════════════════════════════════════════════
    // معالجة الملف (تُستدعى من SmartMediaWorker فقط)
    // ═══════════════════════════════════════════════════════════════════
    public static ProcessResult processFile(Context context, String sourcePath, String fileType,
                                            String fileName, long timeBudgetMs) {
        ProcessResult r = new ProcessResult();
        r.finalPath = sourcePath;
        r.finalFileName = fileName;
        r.originalSize = getFileSize(context, sourcePath);
        r.processedSize = r.originalSize;

        if (r.originalSize < MIN_COMPRESSION_SIZE) {
            r.type = "none";
            return r;
        }

        String type = resolveType(fileType, fileName);
        boolean isImage = type.startsWith("image/");
        boolean isVideo = type.startsWith("video/");
        if (!isImage && !isVideo) {
            r.type = "none";
            return r;
        }

        File outDir = smartMediaDir(context);
        if (freeBytes(context) < MIN_FREE_SPACE) {
            r.error = "insufficient storage (" + freeBytes(context) + " bytes free)";
            Log.w(TAG, "Compression skipped: " + r.error);
            return r;
        }

        String baseName = baseNameWithoutExtension(fileName);
        File outFile = new File(outDir, baseName + "_optimized." + (isImage ? "jpg" : "mp4"));

        long started = System.currentTimeMillis();
        if (isImage) {
            r.type = "image";
            Log.i(TAG, "Processing image original=" + r.originalSize);
            ImageResult ir = compressImage(context, sourcePath, outFile);
            if (ir.success && ir.outputFile != null && ir.outputFile.length() < r.originalSize) {
                r.finalPath = ir.outputFile.getAbsolutePath();
                r.finalFileName = ir.outputFile.getName();
                r.processedSize = ir.outputFile.length();
                r.compressed = true;
                r.ratio = savedPercent(r.originalSize, r.processedSize);
                Log.i(TAG, "Type=image Original=" + r.originalSize + " Processed=" + r.processedSize
                        + " Saved=" + r.ratio + "% in " + (System.currentTimeMillis() - started) + "ms");
            } else {
                if (ir.outputFile != null && !ir.outputFile.delete()) {
                    Log.w(TAG, "could not delete temp image: " + ir.outputFile.getName());
                }
                r.error = ir.error != null ? ir.error : "compressed not smaller";
                Log.i(TAG, "Image kept original. reason=" + r.error);
            }
        } else {
            r.type = "video";
            Log.i(TAG, "Processing video original=" + r.originalSize);
            VideoResult vr = compressVideo(context, sourcePath, outFile, timeBudgetMs);
            if (vr.success && vr.outputFile != null && vr.outputFile.length() < r.originalSize) {
                r.finalPath = vr.outputFile.getAbsolutePath();
                r.finalFileName = vr.outputFile.getName();
                r.processedSize = vr.outputFile.length();
                r.compressed = true;
                r.ratio = savedPercent(r.originalSize, r.processedSize);
                Log.i(TAG, "Type=video Original=" + r.originalSize + " Processed=" + r.processedSize
                        + " Saved=" + r.ratio + "% in " + (System.currentTimeMillis() - started) + "ms");
            } else {
                if (vr.outputFile != null && !vr.outputFile.delete()) {
                    Log.w(TAG, "could not delete temp video: " + vr.outputFile.getName());
                }
                r.error = vr.error != null ? vr.error : "compressed not smaller";
                Log.i(TAG, "Video kept original. reason=" + r.error);
            }
        }
        return r;
    }

    public static double savedPercent(long original, long processed) {
        if (original <= 0) return 0;
        return Math.round(((original - processed) * 1000.0 / original)) / 10.0;
    }

    private static String baseNameWithoutExtension(String name) {
        if (name == null) return "file";
        int dot = name.lastIndexOf('.');
        String base = dot > 0 ? name.substring(0, dot) : name;
        return base.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF]", "_");
    }

    // ═══════════════════════════════════════════════════════════════════
    // ضغط الصور — إزالة Metadata تلقائياً (إعادة الترميز تهمل كل علامات
    // EXIF) مع إعادة تطبيق Orientation فيزيائياً حتى لا تنقلب الصورة.
    // ═══════════════════════════════════════════════════════════════════
    private static ImageResult compressImage(Context context, String sourcePath, File outFile) {
        ImageResult res = new ImageResult();
        InputStream boundsStream = null;
        Bitmap bitmap = null;
        try {
            BitmapFactory.Options bounds = new BitmapFactory.Options();
            bounds.inJustDecodeBounds = true;
            BitmapFactory.decodeStream(open(context, sourcePath), null, bounds);
            if (bounds.outWidth <= 0 || bounds.outHeight <= 0) {
                res.error = "cannot read image bounds";
                return res;
            }

            int orientation = getExifOrientation(context, sourcePath);

            // لا نكبّر الصور الصغيرة أبداً — ننزّل العينات فقط عندما يتجاوز
            // البعدُ الأقصى MAX_IMAGE_DIMENSION.
            int sample = 1;
            while (Math.max(bounds.outWidth, bounds.outHeight) / (sample * 2) >= MAX_IMAGE_DIMENSION) {
                sample *= 2;
            }

            BitmapFactory.Options opts = new BitmapFactory.Options();
            opts.inSampleSize = sample;
            bitmap = BitmapFactory.decodeStream(open(context, sourcePath), null, opts);
            if (bitmap == null) {
                res.error = "decode failed";
                return res;
            }

            // تطبيق Orientation فيزيائياً (الصور الملتقطة عمودياً لا تنقلب).
            Bitmap finalBmp = applyOrientation(bitmap, orientation);
            if (finalBmp != bitmap) {
                bitmap.recycle();
            }

            try (FileOutputStream fos = new FileOutputStream(outFile)) {
                finalBmp.compress(Bitmap.CompressFormat.JPEG, IMAGE_QUALITY, fos);
            }
            finalBmp.recycle();
            finalBmp = null;

            if (outFile.length() <= 0) {
                outFile.delete();
                res.error = "empty output";
                return res;
            }

            res.success = true;
            res.outputFile = outFile;
        } catch (Exception e) {
            res.error = e.getMessage();
            Log.w(TAG, "Image compression failed: " + res.error);
        } finally {
            if (boundsStream != null) {
                try { boundsStream.close(); } catch (Exception ignored) { }
            }
            if (bitmap != null && !bitmap.isRecycled()) bitmap.recycle();
        }
        return res;
    }

    private static Bitmap applyOrientation(Bitmap src, int orientation) {
        if (orientation == ExifInterface.ORIENTATION_NORMAL || orientation == ExifInterface.ORIENTATION_UNDEFINED) {
            return src;
        }
        try {
            Matrix m = new Matrix();
            switch (orientation) {
                case ExifInterface.ORIENTATION_FLIP_HORIZONTAL:   m.postScale(-1, 1); break;
                case ExifInterface.ORIENTATION_ROTATE_180:        m.postRotate(180); break;
                case ExifInterface.ORIENTATION_FLIP_VERTICAL:     m.postScale(1, -1); break;
                case ExifInterface.ORIENTATION_TRANSPOSE:         m.postRotate(90); m.postScale(-1, 1); break;
                case ExifInterface.ORIENTATION_ROTATE_90:         m.postRotate(90); break;
                case ExifInterface.ORIENTATION_TRANSVERSE:        m.postRotate(270); m.postScale(-1, 1); break;
                case ExifInterface.ORIENTATION_ROTATE_270:        m.postRotate(270); break;
                default: return src;
            }
            return Bitmap.createBitmap(src, 0, 0, src.getWidth(), src.getHeight(), m, true);
        } catch (OutOfMemoryError e) {
            Log.w(TAG, "OOM while rotating image");
            return src;
        }
    }

    private static int getExifOrientation(Context context, String path) {
        try {
            if (path.startsWith("content://")) {
                try (InputStream is = open(context, path)) {
                    if (is == null) return ExifInterface.ORIENTATION_NORMAL;
                    return new ExifInterface(is).getAttributeInt(
                            ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL);
                }
            }
            return new ExifInterface(stripFilePrefix(path)).getAttributeInt(
                    ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL);
        } catch (Exception e) {
            return ExifInterface.ORIENTATION_NORMAL;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ضغط الفيديو — Media3 Transformer (خط أنابيب موثوق) بدل Surface-to-
    // Surface اليدوي الذي كان يعلق على أجهزة معيّنة (stalled: no progress
    // for 30s). يفرض H.264 + سقف Bitrate (VBR للتوافق الأوسع مع مشفّرات
    // سامسونج التي لا تعلن CBR) عبر VideoEncoderSettings، ويتولى الصوت/
    // الاستدارة/التوقيت تلقائياً. لا byte[] كامل في RAM.
    // ═══════════════════════════════════════════════════════════════════
    private static VideoResult compressVideo(Context context, String sourcePath, File outFile, long timeBudgetMs) {
        VideoResult res = new VideoResult();
        MediaExtractor probe = null;
        HandlerThread thread = null;
        try {
            probe = new MediaExtractor();
            setDataSource(probe, context, sourcePath);

            int videoIdx = -1;
            int audioIdx = -1;
            int trackCount = probe.getTrackCount();
            for (int i = 0; i < trackCount; i++) {
                MediaFormat fmt = probe.getTrackFormat(i);
                String mime = fmt.getString(MediaFormat.KEY_MIME);
                if (mime != null) {
                    if (videoIdx < 0 && mime.startsWith("video/")) videoIdx = i;
                    else if (audioIdx < 0 && mime.startsWith("audio/")) audioIdx = i;
                }
            }
            if (videoIdx < 0) {
                res.error = "no video track";
                return res;
            }

            MediaFormat inVideo = probe.getTrackFormat(videoIdx);
            int srcW = inVideo.getInteger(MediaFormat.KEY_WIDTH);
            int srcH = inVideo.getInteger(MediaFormat.KEY_HEIGHT);
            int fps = inVideo.containsKey(MediaFormat.KEY_FRAME_RATE)
                    ? inVideo.getInteger(MediaFormat.KEY_FRAME_RATE) : DEFAULT_FPS;
            if (fps <= 0 || fps > 120) fps = DEFAULT_FPS;

            long durationUs = 0;
            if (inVideo.containsKey(MediaFormat.KEY_DURATION)) {
                try { durationUs = inVideo.getLong(MediaFormat.KEY_DURATION); } catch (Exception ignored) { }
            }
            if (durationUs <= 0) durationUs = estimateVideoDuration(probe, videoIdx);

            int targetBitrate = targetBitrate(srcW, srcH, fps);

            // فحص المساحة الحرة قبل بدء المعالجة.
            long needed = (durationUs / 1_000_000L) * targetBitrate / 8L + 64L * 1024 * 1024;
            if (freeBytes(context) < needed) {
                res.error = "insufficient storage for transcode (need ~" + (needed / 1024 / 1024) + "MB)";
                Log.w(TAG, res.error);
                return res;
            }
            probe.release();
            probe = null;

            final Uri srcUri = sourcePath.startsWith("content://")
                    ? Uri.parse(sourcePath)
                    : Uri.fromFile(new File(stripFilePrefix(sourcePath)));

            // لا نترك ملفاً قديماً يفسد جولة Transformer الجديدة.
            if (outFile.exists() && !outFile.delete()) {
                Log.w(TAG, "could not clear stale output: " + outFile.getName());
            }

            final long budgetEnd = System.currentTimeMillis()
                    + (timeBudgetMs > 0 ? timeBudgetMs : PROCESS_BUDGET_MS);
            final long started = System.currentTimeMillis();
            final CountDownLatch latch = new CountDownLatch(1);
            final AtomicReference<String> errorRef = new AtomicReference<>(null);
            final AtomicReference<Transformer> transformerRef = new AtomicReference<>(null);

            thread = new HandlerThread("smart-media-transformer");
            thread.start();
            final android.os.Looper looper = thread.getLooper();
            Handler handler = new Handler(looper);

            handler.post(() -> {
                try {
                    VideoEncoderSettings encSettings = new VideoEncoderSettings.Builder()
                            .setBitrate(targetBitrate)
                            .setBitrateMode(MediaCodecInfo.EncoderCapabilities.BITRATE_MODE_VBR)
                            .build();
                    DefaultEncoderFactory encoderFactory = new DefaultEncoderFactory.Builder(context)
                            .setRequestedVideoEncoderSettings(encSettings)
                            .setEnableFallback(true)
                            .build();
                    Transformer transformer = new Transformer.Builder(context)
                            .setLooper(looper)
                            .setVideoMimeType(MimeTypes.VIDEO_H264) // H.264 = توافق أساسي
                            .setEncoderFactory(encoderFactory)
                            .addListener(new Transformer.Listener() {
                                @Override
                                public void onCompleted(Composition composition, ExportResult exportResult) {
                                    Log.i(TAG, "Transformer completed in "
                                            + (System.currentTimeMillis() - started) + "ms");
                                    latch.countDown();
                                }

                                @Override
                                public void onError(Composition composition, ExportResult exportResult,
                                                    ExportException exception) {
                                    String msg = (exception != null && exception.getMessage() != null)
                                            ? exception.getMessage() : "unknown transformer error";
                                    errorRef.set(msg);
                                    Log.w(TAG, "Transformer error: " + msg);
                                    latch.countDown();
                                }
                            })
                            .build();
                    transformerRef.set(transformer);
                    transformer.start(MediaItem.fromUri(srcUri), outFile.getAbsolutePath());
                } catch (Exception e) {
                    String msg = (e != null && e.getMessage() != null)
                            ? e.getMessage() : "transformer setup failed";
                    errorRef.set(msg);
                    Log.w(TAG, "Transformer setup error: " + msg);
                    latch.countDown();
                }
            });

            long remaining = budgetEnd - System.currentTimeMillis();
            boolean finished = remaining > 0 && latch.await(remaining, TimeUnit.MILLISECONDS);

            if (!finished) {
                res.error = "aborted: time budget exceeded";
                Log.w(TAG, res.error);
                cancelGracefully(transformerRef.get(), outFile);
                return res;
            }
            if (errorRef.get() != null) {
                res.error = errorRef.get();
                Log.w(TAG, "[SmartMedia] transcode " + res.error);
                deleteGracefully(outFile);
                return res;
            }
            if (!outFile.exists() || outFile.length() <= 0) {
                res.error = "transcode produced no output";
                Log.w(TAG, res.error);
                deleteGracefully(outFile);
                return res;
            }

            res.success = true;
            res.outputFile = outFile;
            Log.i(TAG, "Transcode finished → " + outFile.getAbsolutePath()
                    + " (" + outFile.length() + " bytes)");
        } catch (Exception e) {
            res.error = e.getMessage();
            Log.e(TAG, "Video compression failed: " + res.error);
            deleteGracefully(outFile);
        } finally {
            if (probe != null) {
                try { probe.release(); } catch (Exception ignored) { }
            }
            if (thread != null) {
                try { thread.quitSafely(); } catch (Exception ignored) { }
            }
        }
        return res;
    }

    private static void cancelGracefully(Transformer transformer, File outFile) {
        if (transformer != null) {
            try { transformer.cancel(); } catch (Exception ignored) { }
        }
        // نمنح خط الأنابيب لحظة لإغلاق المخرجات قبل الحذف.
        try {
            Thread.sleep(300);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
        deleteGracefully(outFile);
    }

    private static void deleteGracefully(File f) {
        for (int i = 0; i < 4 && f != null && f.exists(); i++) {
            if (f.delete()) return;
            try {
                Thread.sleep(250);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
                return;
            }
        }
    }

    private static void setDataSource(MediaExtractor extractor, Context context, String path) throws Exception {
        if (path.startsWith("content://")) {
            extractor.setDataSource(context, Uri.parse(path), null);
        } else {
            extractor.setDataSource(stripFilePrefix(path));
        }
    }

    private static long estimateVideoDuration(MediaExtractor ex, int videoIdx) {
        try {
            ex.selectTrack(videoIdx);
            long last = 0;
            while (ex.getSampleTime() >= 0) {
                last = ex.getSampleTime();
                ex.advance();
            }
            ex.seekTo(0, MediaExtractor.SEEK_TO_PREVIOUS_SYNC);
            ex.unselectTrack(videoIdx);
            return last;
        } catch (Exception e) {
            return 0;
        }
    }

    private static int targetBitrate(int width, int height, int fps) {
        // معادلة معتدلة تعتمد على عدد البكسلات: جودة جيدة بلا إسراف.
        long pixels = (long) width * height;
        long bits = (long) (pixels * fps * 0.06);
        bits = Math.min(bits, MAX_VIDEO_BITRATE);
        bits = Math.max(bits, 1_200_000L);
        return (int) bits;
    }

    // ═══════════════════════════════════════════════════════════════════
    // تنظيف الملفات المؤقتة — لا تُترك ملفات يتيمة.
    // ═══════════════════════════════════════════════════════════════════
    public static void cleanupOrphanedTempFiles(Context context, UploadDatabaseHelper db) {
        try {
            File dir = smartMediaDir(context);
            if (!dir.exists()) return;
            List<String> active = db.getActiveQueueFilePaths();
            Set<String> referenced = new HashSet<>();
            for (String p : active) {
                if (p != null) referenced.add(p);
            }
            File[] files = dir.listFiles();
            if (files == null) return;
            for (File f : files) {
                if (!referenced.contains(f.getAbsolutePath())) {
                    if (f.delete()) {
                        Log.i(TAG, "cleaned orphan temp: " + f.getName());
                    }
                }
            }
        } catch (Exception e) {
            Log.w(TAG, "cleanup failed: " + e.getMessage());
        }
    }

    // ─── أنواع النتائج المساعدة ───
    public static class ProcessResult {
        public String finalPath;
        public String finalFileName;
        public long originalSize;
        public long processedSize;
        public boolean compressed = false;
        public String type = "none";
        public double ratio = 0;
        public String error; // != null ⇒ فشل/تخطي
    }

    private static class ImageResult {
        boolean success;
        File outputFile;
        String error;
    }

    private static class VideoResult {
        boolean success;
        File outputFile;
        String error;
    }

}
