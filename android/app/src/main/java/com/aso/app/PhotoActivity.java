package com.aso.app;

import android.Manifest;
import android.app.Activity;
import android.content.ContentValues;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.MediaStore;
import android.util.Log;
import android.widget.Toast;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import java.io.File;

/**
 * ═══════════════════════════════════════════════════════════════════
 * 📸 PhotoActivity - Native Android Camera for Photo Capture
 *
 * نفس نهج CameraActivity (الذي يعمل بشكل ممتاز):
 * - يفتح الكاميرا مباشرة (Intent.ACTION_IMAGE_CAPTURE)
 * - يحصل على file:// URI مباشرة (بدون Base64!)
 * - يحفظ في SQLite (metadata only)
 * - يرفع عبر FileSyncWorker (streaming!)
 *
 * ✅ لا Base64 أبداً!
 * ✅ لا Capacitor bridge!
 * ✅ لا JavaScript memory issues!
 * ✅ لا Activity recreation problems!
 * ═══════════════════════════════════════════════════════════════════
 */
public class PhotoActivity extends AppCompatActivity {

    private static final String TAG = "PhotoActivity";
    private static final int REQUEST_IMAGE_CAPTURE = 2001;
    private static final int REQUEST_CAMERA_PERMISSION = 2002;

    private Uri photoUri;
    private int sponsorshipId;
    private String apiUrl;
    private String authToken;
    private String personName;
    private String associationName;
    private long realFileId = -1;  // ✅ Store real file ID from database

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  📸 PhotoActivity.onCreate() - Native Photo Capture          ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");

        // Get parameters from Intent
        Intent intent = getIntent();
        sponsorshipId = intent.getIntExtra("sponsorshipId", -1);
        apiUrl = intent.getStringExtra("apiUrl");
        authToken = intent.getStringExtra("authToken");
        personName = intent.getStringExtra("personName");
        associationName = intent.getStringExtra("associationName");

        Log.e(TAG, "📋 Parameters:");
        Log.e(TAG, "   sponsorshipId: " + sponsorshipId);
        Log.e(TAG, "   personName: " + personName);
        Log.e(TAG, "   associationName: " + associationName);
        Log.e(TAG, "   apiUrl: " + (apiUrl != null ? apiUrl : "not provided"));

        // Use default API URL if not provided
        if (apiUrl == null || apiUrl.isEmpty()) {
            apiUrl = "https://alhayahorphans.org/api/mobile/upload-file";
            Log.e(TAG, "   Using default API URL: " + apiUrl);
        }

        if (sponsorshipId == -1) {
            Log.e(TAG, "❌ sponsorshipId مطلوب!");
            Toast.makeText(this, "خطأ: sponsorshipId مطلوب", Toast.LENGTH_SHORT).show();
            finish();
            return;
        }

        // التحقق من صلاحيات الكاميرا
        if (checkCameraPermission()) {
            openCamera();
        } else {
            requestCameraPermission();
        }
    }

    private boolean checkCameraPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            return ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA)
                   == PackageManager.PERMISSION_GRANTED;
        }
        return true;
    }

    private void requestCameraPermission() {
        Log.e(TAG, "📋 طلب صلاحية الكاميرا...");
        ActivityCompat.requestPermissions(
            this,
            new String[]{Manifest.permission.CAMERA},
            REQUEST_CAMERA_PERMISSION
        );
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        if (requestCode == REQUEST_CAMERA_PERMISSION) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                Log.e(TAG, "✅ تم منح صلاحية الكاميرا");
                openCamera();
            } else {
                Log.e(TAG, "❌ تم رفض صلاحية الكاميرا");
                Toast.makeText(this, "يجب السماح بالوصول للكاميرا", Toast.LENGTH_LONG).show();
                finish();
            }
        }
    }

    private void openCamera() {
        Log.e(TAG, "📸 فتح الكاميرا للتصوير...");

        try {
            // إنشاء Intent للتصوير
            Intent takePictureIntent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);

            if (takePictureIntent.resolveActivity(getPackageManager()) != null) {

                // إنشاء ملف للصورة
                ContentValues values = new ContentValues();
                values.put(MediaStore.Images.Media.TITLE, "photo_" + sponsorshipId + "_" + System.currentTimeMillis());
                values.put(MediaStore.Images.Media.DESCRIPTION, "Photo for sponsorship " + sponsorshipId);
                values.put(MediaStore.Images.Media.MIME_TYPE, "image/jpeg");

                photoUri = getContentResolver().insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values);

                if (photoUri != null) {
                    takePictureIntent.putExtra(MediaStore.EXTRA_OUTPUT, photoUri);

                    Log.e(TAG, "✅ Photo URI created: " + photoUri.toString());
                    startActivityForResult(takePictureIntent, REQUEST_IMAGE_CAPTURE);
                } else {
                    Log.e(TAG, "❌ فشل إنشاء URI للصورة");
                    Toast.makeText(this, "خطأ في إنشاء ملف الصورة", Toast.LENGTH_SHORT).show();
                    finish();
                }

            } else {
                Log.e(TAG, "❌ لا يوجد تطبيق كاميرا");
                Toast.makeText(this, "لا يوجد تطبيق كاميرا", Toast.LENGTH_SHORT).show();
                finish();
            }

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في فتح الكاميرا: " + e.getMessage());
            e.printStackTrace();
            Toast.makeText(this, "خطأ في فتح الكاميرا", Toast.LENGTH_SHORT).show();
            finish();
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        if (requestCode == REQUEST_IMAGE_CAPTURE) {
            if (resultCode == RESULT_OK && photoUri != null) {
                Log.e(TAG, "");
                Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
                Log.e(TAG, "║  ✅ تم التصوير بنجاح!                                        ║");
                Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
                Log.e(TAG, "📸 Photo URI: " + photoUri.toString());

                // ✅ FAST CHECK: Only query metadata (NO full file read!)
                Log.e(TAG, "🔍 Quick file metadata check...");
                try {
                    android.database.Cursor cursor = getContentResolver().query(photoUri,
                        new String[]{android.provider.OpenableColumns.SIZE, android.provider.OpenableColumns.DISPLAY_NAME},
                        null, null, null);
                    if (cursor != null && cursor.moveToFirst()) {
                        int sizeIndex = cursor.getColumnIndex(android.provider.OpenableColumns.SIZE);
                        int nameIndex = cursor.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME);

                        if (sizeIndex != -1) {
                            long fileSize = cursor.getLong(sizeIndex);
                            String displayName = nameIndex != -1 ? cursor.getString(nameIndex) : "unknown";
                            Log.e(TAG, "📊 File: " + displayName + " - " + (fileSize / 1024.0 / 1024.0) + " MB");
                        }
                        cursor.close();
                    }
                } catch (Exception e) {
                    Log.w(TAG, "⚠️ Metadata check failed: " + e.getMessage());
                }

                // الحصول على المسار الفعلي للملف
                String filePath = photoUri.toString();
                String fileName = "photo_" + sponsorshipId + "_" + System.currentTimeMillis() + ".jpg";

                Log.e(TAG, "📋 Preparing to save to database:");
                Log.e(TAG, "   filePath: " + filePath);
                Log.e(TAG, "   fileName: " + fileName);
                Log.e(TAG, "   sponsorshipId: " + sponsorshipId);
                Log.e(TAG, "   apiUrl: " + apiUrl);

                // ✅ DIRECT save to PUBLIC Documents folder (FAST!)
                Log.e(TAG, "💾 Starting DIRECT save to Documents...");
                saveToExternalDocumentsFolder(photoUri, fileName);
                Log.e(TAG, "ℹ️ Photo also stored in MediaStore (accessible in gallery)");

                // حفظ في قاعدة البيانات وجدولة الرفع (FAST - no blocking!)
                saveAndQueueUpload(filePath, fileName);

                Toast.makeText(this, "✅ تم حفظ الصورة وجاري الرفع", Toast.LENGTH_LONG).show();

                // إرجاع معلومات الملف لـ JavaScript (لتحديث الإحصائيات!)
                Intent resultIntent = new Intent();
                resultIntent.putExtra("sponsorshipId", sponsorshipId);
                resultIntent.putExtra("fileName", fileName);
                resultIntent.putExtra("fileType", "photo");
                resultIntent.putExtra("fileId", realFileId);  // ✅ CRITICAL: Return real file ID!
                resultIntent.putExtra("success", true);

                Log.e(TAG, "✅ Returning to JavaScript with fileId: " + realFileId);

                setResult(RESULT_OK, resultIntent);
                finish();

            } else {
                Log.e(TAG, "❌ تم إلغاء التصوير أو فشل");
                Toast.makeText(this, "تم إلغاء التصوير", Toast.LENGTH_SHORT).show();
                setResult(RESULT_CANCELED);
                finish();
            }
        }
    }

    private void saveAndQueueUpload(String filePath, String fileName) {
        Log.e(TAG, "");
        Log.e(TAG, "═══════════════════════════════════════════════════════════");
        Log.e(TAG, "💾 حفظ في قاعدة البيانات وجدولة الرفع...");
        Log.e(TAG, "═══════════════════════════════════════════════════════════");

        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(this);

            // حفظ في قاعدة البيانات
            long fileId = dbHelper.addFileToQueue(
                filePath,
                fileName,
                "image/jpeg",
                sponsorshipId,
                apiUrl,
                authToken != null ? authToken : "",
                associationName != null ? associationName : "",
                personName != null ? personName : ""
            );

            // ✅ CRITICAL: Store real file ID to return to JavaScript
            realFileId = fileId;

            Log.e(TAG, "✅ تم الحفظ في قاعدة البيانات - File ID: " + fileId);
            Log.e(TAG, "✅ realFileId stored: " + realFileId + " (will be returned to JS)");

            // ✅ جدولة FileSyncWorker باستخدام scheduleImmediateSync() - الطريقة الصحيحة!
            Log.e(TAG, "📤 جدولة الرفع الفوري عبر FileSyncWorker.scheduleImmediateSync()...");
            try {
                FileSyncWorker.scheduleImmediateSync(this);
                Log.e(TAG, "✅ تم جدولة FileSyncWorker!");
                Log.e(TAG, "   📋 Work name: file_sync_orchestrator (unified)");
                Log.e(TAG, "   🔧 Policy: KEEP (no duplicates)");
                Log.e(TAG, "   🎯 File ID " + fileId + " will be uploaded automatically");
            } catch (Exception workEx) {
                Log.e(TAG, "❌ فشل جدولة WorkManager: " + workEx.getMessage());
                workEx.printStackTrace();
            }
            Log.e(TAG, "═══════════════════════════════════════════════════════════");

        } catch (Exception e) {
            Log.e(TAG, "❌ خطأ في الحفظ والجدولة: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * ✨ حفظ مباشر في PUBLIC Documents folder
     *
     * المسار: /storage/emulated/0/Documents/Alhayah/[Association]/[Person]/[fileName]
     *
     * ✅ حفظ مباشر بدون نسخ مزدوج
     * ✅ سريع جداً
     * ✅ الملف يبقى بعد حذف التطبيق
     */
    private void saveToExternalDocumentsFolder(android.net.Uri sourceUri, String fileName) {
        Log.e(TAG, "💾 saveToExternalDocumentsFolder() START");
        Log.e(TAG, "   sourceUri: " + sourceUri.toString());
        Log.e(TAG, "   fileName: " + fileName);

        try {
            // ✅ استخدام PUBLIC Documents directory
            java.io.File documentsDir = android.os.Environment.getExternalStoragePublicDirectory(
                android.os.Environment.DIRECTORY_DOCUMENTS
            );

            if (documentsDir == null) {
                Log.e(TAG, "❌ Cannot access Documents directory - documentsDir is null");
                return;
            }

            Log.e(TAG, "✅ Documents dir: " + documentsDir.getAbsolutePath());

            // إنشاء المجلد: Documents/Alhayah/
            java.io.File mainDir = new java.io.File(documentsDir, "Alhayah");
            if (!mainDir.exists()) {
                boolean created = mainDir.mkdirs();
                Log.e(TAG, "   Alhayah dir created: " + created);
            }

            // إنشاء مجلد الجمعية
            String safeAssociationName = (associationName != null && !associationName.isEmpty())
                ? associationName.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_")
                : "General";

            java.io.File associationDir = new java.io.File(mainDir, safeAssociationName);
            if (!associationDir.exists()) {
                boolean created = associationDir.mkdirs();
                Log.e(TAG, "   Association dir created: " + created + " (" + safeAssociationName + ")");
            }

            // إنشاء مجلد الشخص
            String safePersonName = (personName != null && !personName.isEmpty())
                ? personName.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_")
                : "Unknown_" + sponsorshipId;

            java.io.File personDir = new java.io.File(associationDir, safePersonName);
            if (!personDir.exists()) {
                boolean created = personDir.mkdirs();
                Log.e(TAG, "   Person dir created: " + created + " (" + safePersonName + ")");
            }

            // إنشاء الملف النهائي
            java.io.File destinationFile = new java.io.File(personDir, fileName);

            Log.e(TAG, "✅ Final path: " + destinationFile.getAbsolutePath());
            Log.e(TAG, "🔄 Starting file copy...");

            // نسخ الملف (سريع!)
            long startCopy = System.currentTimeMillis();
            long bytesWritten = 0;

            try (java.io.InputStream inputStream = getContentResolver().openInputStream(sourceUri);
                 java.io.FileOutputStream outputStream = new java.io.FileOutputStream(destinationFile)) {

                if (inputStream == null) {
                    Log.e(TAG, "❌ Cannot open input stream from URI");
                    return;
                }

                byte[] buffer = new byte[16384]; // 16KB buffer for faster copy
                int bytesRead;

                while ((bytesRead = inputStream.read(buffer)) != -1) {
                    outputStream.write(buffer, 0, bytesRead);
                    bytesWritten += bytesRead;
                }

                outputStream.flush();

                long copyTime = System.currentTimeMillis() - startCopy;
                Log.e(TAG, "✅✅✅ File saved successfully!");
                Log.e(TAG, "   Size: " + (bytesWritten / 1024.0 / 1024.0) + " MB");
                Log.e(TAG, "   Time: " + copyTime + " ms");
                Log.e(TAG, "   Path: " + destinationFile.getAbsolutePath());
                Log.e(TAG, "   File exists: " + destinationFile.exists());
                Log.e(TAG, "   File size: " + destinationFile.length() + " bytes");

                // حفظ مسار الملف الخارجي في قاعدة البيانات
                UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(this);
                dbHelper.savePersonNameHistory(
                    sponsorshipId,
                    associationName != null ? associationName : "",
                    personName != null ? personName : "",
                    personDir.getAbsolutePath()
                );
                Log.e(TAG, "✅ Saved folder path to database");

            } catch (Exception copyEx) {
                Log.e(TAG, "❌ Error during file copy: " + copyEx.getMessage());
                copyEx.printStackTrace();

                // حذف الملف الناقص إن وجد
                if (destinationFile.exists()) {
                    destinationFile.delete();
                    Log.e(TAG, "🗑️ Deleted incomplete file");
                }
            }

        } catch (Exception e) {
            Log.e(TAG, "❌ Error saving to external documents: " + e.getMessage());
            e.printStackTrace();
        }

        Log.e(TAG, "💾 saveToExternalDocumentsFolder() END");
    }
}
