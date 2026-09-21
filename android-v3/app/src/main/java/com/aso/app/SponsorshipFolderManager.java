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
        String safeOldAssoc;
        String safeOldPerson;
        String safeNewAssoc;
        String safeNewPerson;

        try {
            // ✨ الخطوة 0: الحصول على الاسم القديم من قاعدة البيانات
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(context);
            String[] nameHistory = dbHelper.getPreviousPersonName(sponsorshipId);
            
            if (nameHistory != null) {
                // استخدام الأسماء من قاعدة البيانات بدلاً من المُمررة
                safeOldAssoc = (nameHistory[0] != null ? nameHistory[0] : "General")
                    .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
                safeOldPerson = nameHistory[1].replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
                
                Log.e(TAG, "📚 Found name history in database:");
                Log.e(TAG, "   OLD (from DB): " + nameHistory[0] + "/" + nameHistory[1]);
                Log.e(TAG, "   CURRENT (from DB): " + nameHistory[2] + "/" + nameHistory[3]);
            } else {
                // استخدام الاسم الممرر كاسم قديم افتراضياً
                safeOldAssoc = (oldAssociationName != null ? oldAssociationName : "General")
                    .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
                safeOldPerson = oldPersonName.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
                
                Log.e(TAG, "⚠️ No name history found, using passed oldPersonName");
            }
            
            // تنظيف الأسماء
            safeNewAssoc = (newAssociationName != null ? newAssociationName : "General")
                .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
            safeNewPerson = newPersonName.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");

            // ✨ الخطوة 1: إعادة تسمية المجلد
            File documentsDir = android.os.Environment.getExternalStoragePublicDirectory(
                android.os.Environment.DIRECTORY_DOCUMENTS);
            File mainDir = new File(documentsDir, "sponsorships_alhayahorphans");
            
            File oldAssocDir = new File(mainDir, safeOldAssoc);
            File oldPersonDir = new File(oldAssocDir, safeOldPerson);
            
            File newAssocDir = new File(mainDir, safeNewAssoc);
            File newPersonDir = new File(newAssocDir, safeNewPerson);

            if (oldPersonDir.exists()) {
                Log.e(TAG, "📂 Found old folder: " + oldPersonDir.getAbsolutePath());
                
                // إنشاء المجلد الجديد إذا لم يكن موجوداً
                if (!newAssocDir.exists()) {
                    newAssocDir.mkdirs();
                    Log.e(TAG, "✅ Created association folder: " + safeNewAssoc);
                }

                // إعادة التسمية
                boolean renamed = oldPersonDir.renameTo(newPersonDir);
                if (renamed) {
                    Log.e(TAG, "✅ Folder renamed successfully!");
                    Log.e(TAG, "   FROM: " + oldPersonDir.getAbsolutePath());
                    Log.e(TAG, "   TO:   " + newPersonDir.getAbsolutePath());
                    success = true;
                } else {
                    Log.e(TAG, "❌ Failed to rename folder");
                    
                    // محاولة نسخ الملفات بدلاً من إعادة التسمية
                    if (copyDirectory(oldPersonDir, newPersonDir)) {
                        Log.e(TAG, "✅ Files copied to new folder");
                        deleteDirectory(oldPersonDir);
                        Log.e(TAG, "✅ Old folder deleted");
                        success = true;
                    }
                }
            } else {
                Log.e(TAG, "⚠️ Old folder not found: " + oldPersonDir.getAbsolutePath());
                Log.e(TAG, "   (Maybe files were never saved, or already moved)");
                success = true; // لا توجد مشكلة إذا المجلد غير موجود
            }

            // ✨ الخطوة 2: تحديث قاعدة بيانات الرفع
            filesUpdated = dbHelper.updatePersonNameInQueue(sponsorshipId, newPersonName);
            Log.e(TAG, "✅ Updated " + filesUpdated + " file records in upload queue");

            // ✨ الخطوة 3: حفظ تاريخ الأسماء الجديد
            String newFolderPath = newAssocDir.getAbsolutePath() + "/" + safeNewPerson;
            dbHelper.savePersonNameHistory(sponsorshipId, newAssociationName, newPersonName, newFolderPath);
            Log.e(TAG, "✅ Name history saved to database");

            // ✨ الخطوة 4: إعادة جدولة الملفات الفاشلة
            if (filesUpdated > 0) {
                dbHelper.resetFailedUploads(sponsorshipId);
                Log.e(TAG, "✅ Reset failed uploads for re-upload");
                
                // تشغيل الخدمة لمحاولة الرفع
                SyncOrchestrator.scheduleUpload(context);
                Log.e(TAG, "🚀 Upload service triggered");
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
            result.put("oldPath", safeOldAssoc + "/" + safeOldPerson);
            result.put("newPath", safeNewAssoc + "/" + safeNewPerson);
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
