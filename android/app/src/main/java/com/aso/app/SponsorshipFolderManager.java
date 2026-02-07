package com.aso.app;

import android.content.Context;
import android.util.Log;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;

/**
 * Plugin لإدارة مجلدات المكفولين وإعادة تسميتها
 *
 * المهمة:
 * - عند تعديل اسم مكفول، إعادة تسمية مجلده
 * - تحديث جميع مسارات الملفات في قاعدة البيانات
 * - إعادة جدولة رفع الملفات التي فشلت
 */
@CapacitorPlugin(name = "SponsorshipFolderManager")
public class SponsorshipFolderManager extends Plugin {
    private static final String TAG = "FolderManager";

    @PluginMethod
    public void renameSponsorshipFolder(PluginCall call) {
        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  📁 renameSponsorshipFolder() CALLED                         ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");

        Integer sponsorshipId = call.getInt("sponsorshipId");
        String oldAssociationName = call.getString("oldAssociationName");
        String oldPersonName = call.getString("oldPersonName");
        String newAssociationName = call.getString("newAssociationName");
        String newPersonName = call.getString("newPersonName");

        Log.e(TAG, "📊 Parameters:");
        Log.e(TAG, "   🆔 sponsorshipId: " + sponsorshipId);
        Log.e(TAG, "   📂 OLD: " + oldAssociationName + "/" + oldPersonName);
        Log.e(TAG, "   ✨ NEW: " + newAssociationName + "/" + newPersonName);

        if (sponsorshipId == null || oldPersonName == null || newPersonName == null) {
            Log.e(TAG, "❌ Missing required parameters");
            call.reject("معاملات ناقصة");
            return;
        }

        Context context = getContext();
        boolean success = false;
        int filesUpdated = 0;
        String safeNewAssoc;
        String safeNewPerson;

        try {
            // ✨ الخطوة 0: جلب المسار الفعلي للمجلد من قاعدة البيانات (المفتاح الفريد)
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);
            String storedFolderPath = dbHelper.getFolderPath(sponsorshipId);

            if (storedFolderPath == null || storedFolderPath.isEmpty()) {
                Log.e(TAG, "⚠️⚠️⚠️ No folder_path found in database!");
                Log.e(TAG, "⚠️ This means no files were saved yet for this sponsorship");
                Log.e(TAG, "⚠️ Nothing to rename - operation skipped");

                // لا يوجد مجلد للتسمية - نجاح افتراضي
                JSObject result = new JSObject();
                result.put("success", true);
                result.put("filesUpdated", 0);
                result.put("message", "No folder to rename (no files saved yet)");
                call.resolve(result);
                return;
            }

            Log.e(TAG, "🔑 Found stored folder path in database:");
            Log.e(TAG, "   📂 " + storedFolderPath);

            // ✨ الخطوة 1: بناء المسار الجديد
            safeNewAssoc = (newAssociationName != null && !newAssociationName.isEmpty() ? newAssociationName : "General")
                .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
            safeNewPerson = newPersonName.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");

            // ✨ الخطوة 2: إعادة تسمية المجلد باستخدام المسار المحفوظ (المفتاح الفريد)
            File storedFolder = new File(storedFolderPath);

            // التأكد من أن المجلد الفعلي موجود
            if (!storedFolder.exists()) {
                Log.e(TAG, "⚠️ Stored folder not found on disk: " + storedFolderPath);
                Log.e(TAG, "⚠️ Maybe it was deleted manually. Clearing database record...");

                // تحديث قاعدة البيانات للإشارة إلى أن المجلد غير موجود
                JSObject result = new JSObject();
                result.put("success", false);
                result.put("filesUpdated", 0);
                result.put("error", "Folder not found on disk");
                call.resolve(result);
                return;
            }

            Log.e(TAG, "✅ Found physical folder: " + storedFolder.getAbsolutePath());

            // بناء المسار الجديد
            File documentsDir = android.os.Environment.getExternalStoragePublicDirectory(
                android.os.Environment.DIRECTORY_DOCUMENTS);
            File mainDir = new File(documentsDir, "sponsorships_alhayahorphans");
            File newAssocDir = new File(mainDir, safeNewAssoc);
            File newPersonDir = new File(newAssocDir, safeNewPerson);

            // ✨ تعريف المسار الجديد مرة واحدة فقط (FIX: تجنب التكرار)
            String newFolderPath = newPersonDir.getAbsolutePath();

            Log.e(TAG, "📊 Rename operation:");
            Log.e(TAG, "   FROM (stored): " + storedFolder.getAbsolutePath());
            Log.e(TAG, "   TO (new):      " + newFolderPath);

            // إنشاء مجلد الجمعية إذا لم يكن موجوداً
            if (!newAssocDir.exists()) {
                newAssocDir.mkdirs();
                Log.e(TAG, "✅ Created association folder: " + safeNewAssoc);
            }

            // إعادة التسمية
            boolean renamed = storedFolder.renameTo(newPersonDir);
            if (renamed) {
                Log.e(TAG, "✅ Folder renamed successfully!");
                success = true;

                // ✨ تحديث folder_path في قاعدة البيانات
                dbHelper.updateFolderPath(sponsorshipId, newFolderPath);
                Log.e(TAG, "✅ Updated folder_path in database: " + newFolderPath);
            } else {
                Log.e(TAG, "❌ Failed to rename folder (trying copy method...)");

                // محاولة نسخ الملفات بدلاً من إعادة التسمية
                if (copyDirectory(storedFolder, newPersonDir)) {
                    Log.e(TAG, "✅ Files copied to new folder");
                    deleteDirectory(storedFolder);
                    Log.e(TAG, "✅ Old folder deleted");
                    success = true;

                    // ✨ تحديث folder_path في قاعدة البيانات
                    dbHelper.updateFolderPath(sponsorshipId, newFolderPath);
                    Log.e(TAG, "✅ Updated folder_path in database: " + newFolderPath);
                }
            }

            // ✨ الخطوة 3: تحديث قاعدة بيانات الرفع
            filesUpdated = dbHelper.updatePersonNameInQueue(sponsorshipId, newPersonName);
            Log.e(TAG, "✅ Updated " + filesUpdated + " file records in upload queue");

            // ✨ الخطوة 4: حفظ تاريخ الأسماء الجديد (استخدام المتغير الموجود)
            dbHelper.savePersonNameHistory(sponsorshipId, newAssociationName, newPersonName, newFolderPath);
            Log.e(TAG, "✅ Name history saved to database");

            // ✨ الخطوة 5: إعادة جدولة الملفات الفاشلة
            if (filesUpdated > 0) {
                dbHelper.resetFailedUploads(sponsorshipId);
                Log.e(TAG, "✅ Reset failed uploads for re-upload");

                // تشغيل الخدمة لمحاولة الرفع مع معالجة Android 12+ exceptions
                try {
                    android.content.Intent serviceIntent = new android.content.Intent(
                        context, UploadForegroundService.class);
                    if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                        context.startForegroundService(serviceIntent);
                        Log.e(TAG, "🚀 Upload service triggered (Foreground)");
                    } else {
                        context.startService(serviceIntent);
                        Log.e(TAG, "🚀 Upload service triggered (Background)");
                    }
                } catch (IllegalStateException | SecurityException e) {
                    // Android 12+ may throw ForegroundServiceStartNotAllowedException
                    Log.e(TAG, "⚠️ Cannot start FGS from background - using WorkManager fallback");
                    UploadTaskScheduler.getInstance(context).scheduleUploadTask();
                }
            }

            Log.e(TAG, "");
            Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
            Log.e(TAG, "║  ✅ FOLDER RENAME COMPLETE                                    ║");
            Log.e(TAG, "║  📁 Folder: " + (success ? "Renamed" : "Failed"));
            Log.e(TAG, "║  📊 Files updated: " + filesUpdated);
            Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
            Log.e(TAG, "");

            JSObject result = new JSObject();
            result.put("success", success);
            result.put("filesUpdated", filesUpdated);
            result.put("oldPath", storedFolderPath);
            result.put("newPath", newPersonDir.getAbsolutePath());
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "❌ Exception in renameSponsorshipFolder", e);
            call.reject("فشل إعادة تسمية المجلد: " + e.getMessage());
        }
    }

    /**
     * نسخ مجلد بكامل محتوياته
     */
    private boolean copyDirectory(File source, File dest) {
        try {
            if (!dest.exists()) {
                dest.mkdirs();
            }

            File[] files = source.listFiles();
            if (files == null) return true;

            for (File file : files) {
                File destFile = new File(dest, file.getName());
                if (file.isDirectory()) {
                    copyDirectory(file, destFile);
                } else {
                    copyFile(file, destFile);
                }
            }
            return true;
        } catch (Exception e) {
            Log.e(TAG, "❌ Copy directory failed", e);
            return false;
        }
    }

    /**
     * نسخ ملف واحد
     */
    private void copyFile(File source, File dest) throws Exception {
        java.io.FileInputStream fis = new java.io.FileInputStream(source);
        java.io.FileOutputStream fos = new java.io.FileOutputStream(dest);
        byte[] buffer = new byte[8192];
        int bytesRead;
        while ((bytesRead = fis.read(buffer)) != -1) {
            fos.write(buffer, 0, bytesRead);
        }
        fis.close();
        fos.close();
    }

    /**
     * حذف مجلد بكامل محتوياته
     */
    private boolean deleteDirectory(File directory) {
        if (directory.isDirectory()) {
            File[] files = directory.listFiles();
            if (files != null) {
                for (File file : files) {
                    deleteDirectory(file);
                }
            }
        }
        return directory.delete();
    }
}
