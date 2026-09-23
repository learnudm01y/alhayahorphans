package com.aso.app;

import android.content.ContentValues;
import android.content.Context;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.MediaStore;
import android.util.Log;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;

/**
 * أرشفة الوسائط على الذاكرة الخارجية للجهاز.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * المعمارية — يجب أن تطابق بنية Google Drive تماماً
 *
 *   Google Drive : {remote}:temp/{الجمعية}/{الشخص}/{اسم الملف}
 *   الجهاز       : Documents/{ROOT}/{الجمعية}/{الشخص}/{اسم الملف}
 *
 * التطابق في اسمَي الجمعية والشخص ليس تجميلاً: هو ما يجعل مجلد الجهاز
 * صورة طبق الأصل عن Drive فيمكن مطابقتهما ومراجعتهما يدوياً. لذلك تُستخدم
 * هنا نفس قواعد التنظيف المطبَّقة على الخادم في
 * RcloneGoogleDriveService::sanitizeName() — لا قواعد أخرى.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * 🚨 الخطأ الذي عالجه هذا الملف
 *
 * كانت PhotoActivity وCameraActivity تُدرجان في مجموعة MediaStore.Downloads
 * بينما RELATIVE_PATH يبدأ بـ "Documents/". أندرويد يرفض هذا المزيج:
 *
 *   IllegalArgumentException: Primary directory Documents not allowed for
 *   content://media/external_primary/downloads; allowed directories are [Download]
 *
 * وكان الاستثناء يُبتلع في catch عام فتعود الدالة بـ null بصمت — فلا يُحفظ
 * شيء على الذاكرة الخارجية ولا تظهر أي رسالة خطأ.
 *
 * المجموعة الصحيحة للحفظ تحت Documents/ هي MediaStore.Files، وهي الوحيدة
 * التي تسمح بأي مجلد رئيسي.
 * ═══════════════════════════════════════════════════════════════════════
 */
public final class MediaArchive {
    private static final String TAG = "MediaArchive";

    /** المجلد الجذر على الجهاز. يقابل rootFolder على الخادم. */
    public static final String ROOT_FOLDER = "sponsorships_alhayahorphans";

    private MediaArchive() { }

    /**
     * تنظيف اسم مجلد بنفس قاعدة الخادم بالضبط.
     *
     * مطابق لـ RcloneGoogleDriveService::sanitizeName():
     *   - استبدال المحارف الممنوعة في أسماء الملفات بـ "_"
     *   - دمج المسافات المتكررة في مسافة واحدة
     *   - إزالة النقاط والمسافات من الطرفين
     *   - "unnamed" عند الفراغ
     *
     * ملاحظة: النسخة القديمة على الأندرويد كانت تستخدم
     * replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_") وهي أشد كثيراً —
     * تمسح الأقواس والفواصل والنقاط وكل حرف لاتيني مُشكَّل. النتيجة أن اسم
     * جمعية مثل "Al-Hayah (Gaza)" يصبح على الجهاز "Al-Hayah _Gaza_" وعلى
     * Drive "Al-Hayah (Gaza)" — أي مجلدان مختلفان لنفس الجهة.
     */
    public static String sanitizeName(String name) {
        if (name == null) return "unnamed";

        String cleaned = name.replaceAll("[<>:\"/\\\\|?*]", "_");
        cleaned = cleaned.replaceAll("\\s+", " ");
        // إزالة النقاط والمسافات من الطرفين (نظير trim($name, '. ') في PHP)
        cleaned = cleaned.replaceAll("^[. ]+", "").replaceAll("[. ]+$", "");

        return cleaned.isEmpty() ? "unnamed" : cleaned;
    }

    /** المسار النسبي داخل الذاكرة الخارجية: Documents/{ROOT}/{الجمعية}/{الشخص} */
    public static String relativeFolder(String associationName, String personName) {
        return Environment.DIRECTORY_DOCUMENTS
            + "/" + ROOT_FOLDER
            + "/" + sanitizeName(associationName)
            + "/" + sanitizeName(personName);
    }

    /** المسار المطلق المقابل — للعرض والتسجيل فقط. */
    public static String absoluteFolder(String associationName, String personName) {
        return Environment.getExternalStorageDirectory().getAbsolutePath()
            + "/" + relativeFolder(associationName, personName);
    }

    /**
     * يحفظ الوسيط في الذاكرة الخارجية ضمن معمارية Drive.
     *
     * @return المسار المطلق عند النجاح، أو null عند الفشل (مع تسجيل السبب).
     */
    public static String save(Context context,
                              Uri sourceUri,
                              String fileName,
                              String mimeType,
                              String associationName,
                              String personName) {
        if (sourceUri == null || fileName == null) {
            Log.e(TAG, "معطيات ناقصة: sourceUri أو fileName فارغ");
            return null;
        }

        String relativeFolder = relativeFolder(associationName, personName);
        String absolutePath = absoluteFolder(associationName, personName) + "/" + fileName;

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                return saveViaMediaStore(context, sourceUri, fileName, mimeType, relativeFolder, absolutePath);
            }
            return saveViaFileApi(context, sourceUri, fileName, associationName, personName);
        } catch (Exception e) {
            // ⚠️ لا نبتلع الخطأ بصمت كما كان يحدث سابقاً.
            Log.e(TAG, "❌ فشل حفظ الوسيط في الذاكرة الخارجية: " + e.getClass().getSimpleName()
                + " — " + e.getMessage(), e);
            return null;
        }
    }

    /** أندرويد ١٠ فأحدث. */
    private static String saveViaMediaStore(Context context,
                                            Uri sourceUri,
                                            String fileName,
                                            String mimeType,
                                            String relativeFolder,
                                            String absolutePath) throws Exception {
        ContentValues values = new ContentValues();
        values.put(MediaStore.MediaColumns.DISPLAY_NAME, fileName);
        values.put(MediaStore.MediaColumns.RELATIVE_PATH, relativeFolder);
        if (mimeType != null && !mimeType.isEmpty()) {
            values.put(MediaStore.MediaColumns.MIME_TYPE, mimeType);
        }
        // IS_PENDING يمنع القارئات الأخرى من رؤية ملف نصف مكتوب.
        values.put(MediaStore.MediaColumns.IS_PENDING, 1);

        // ✅ MediaStore.Files هي المجموعة الوحيدة التي تسمح بـ Documents/ كمجلد
        // رئيسي. استخدام Downloads هنا كان يرمي IllegalArgumentException.
        Uri collection = MediaStore.Files.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY);
        Uri itemUri = context.getContentResolver().insert(collection, values);

        if (itemUri == null) {
            Log.e(TAG, "❌ تعذّر إنشاء مُدخل في MediaStore للمسار: " + relativeFolder);
            return null;
        }

        boolean copied = false;
        try (InputStream is = context.getContentResolver().openInputStream(sourceUri);
             OutputStream os = context.getContentResolver().openOutputStream(itemUri)) {
            if (is != null && os != null) {
                copyStream(is, os);
                copied = true;
            }
        } catch (Exception e) {
            Log.e(TAG, "❌ فشل النسخ إلى MediaStore: " + e.getMessage(), e);
        }

        if (!copied) {
            context.getContentResolver().delete(itemUri, null, null);
            return null;
        }

        // رفع علم الانتظار: الملف صار مرئياً ومكتملاً.
        ContentValues done = new ContentValues();
        done.put(MediaStore.MediaColumns.IS_PENDING, 0);
        context.getContentResolver().update(itemUri, done, null, null);

        Log.i(TAG, "✅ حُفظ في الذاكرة الخارجية: " + absolutePath);
        return absolutePath;
    }

    /** أندرويد ٩ وما قبل. */
    private static String saveViaFileApi(Context context,
                                         Uri sourceUri,
                                         String fileName,
                                         String associationName,
                                         String personName) throws Exception {
        File documentsDir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOCUMENTS);
        File personDir = new File(new File(new File(documentsDir, ROOT_FOLDER),
            sanitizeName(associationName)), sanitizeName(personName));

        if (!personDir.exists() && !personDir.mkdirs()) {
            Log.e(TAG, "❌ تعذّر إنشاء المجلد: " + personDir.getAbsolutePath());
            return null;
        }

        File destFile = new File(personDir, fileName);
        try (InputStream is = context.getContentResolver().openInputStream(sourceUri);
             OutputStream os = new FileOutputStream(destFile)) {
            if (is == null) {
                Log.e(TAG, "❌ تعذّر فتح المصدر: " + sourceUri);
                return null;
            }
            copyStream(is, os);
        }

        Log.i(TAG, "✅ حُفظ في الذاكرة الخارجية: " + destFile.getAbsolutePath());
        return destFile.getAbsolutePath();
    }

    private static void copyStream(InputStream is, OutputStream os) throws Exception {
        byte[] buffer = new byte[64 * 1024];
        int read;
        while ((read = is.read(buffer)) != -1) {
            os.write(buffer, 0, read);
        }
        os.flush();
    }
}
