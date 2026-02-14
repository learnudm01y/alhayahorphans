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
import androidx.work.Data;
import androidx.work.ExistingWorkPolicy;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;

import java.io.File;

/**
 * ═══════════════════════════════════════════════════════════════════
 * 📹 CameraActivity - Native Android Camera for Video Recording
 *
 * بدلاً من استخدام WebView + JavaScript + Base64:
 * - يفتح الكاميرا مباشرة (Intent.ACTION_VIDEO_CAPTURE)
 * - يحصل على file:// URI مباشرة (بدون Base64!)
 * - يحفظ في SQLite (metadata only)
 * - يرفع عبر FileSyncWorker (streaming!)
 *
 * ✅ لا Base64 أبداً!
 * ✅ لا Capacitor bridge!
 * ✅ لا JavaScript memory issues!
 * ═══════════════════════════════════════════════════════════════════
 */
public class CameraActivity extends AppCompatActivity {

    private static final String TAG = "CameraActivity";
    private static final int REQUEST_VIDEO_CAPTURE = 1001;
    private static final int REQUEST_CAMERA_PERMISSION = 1002;

    private Uri videoUri;
    private int sponsorshipId;
    private String apiUrl;
    private String authToken;
    private String personName;
    private String associationName;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        Log.e(TAG, "");
        Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        Log.e(TAG, "║  📹 CameraActivity - Native Video Recording                   ║");
        Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");

        // الحصول على البيانات من Intent
        Intent intent = getIntent();
        sponsorshipId = intent.getIntExtra("sponsorshipId", -1);
        apiUrl = intent.getStringExtra("apiUrl");
        authToken = intent.getStringExtra("authToken");
        personName = intent.getStringExtra("personName");
        associationName = intent.getStringExtra("associationName");

        Log.e(TAG, "📊 Parameters:");
        Log.e(TAG, "   sponsorshipId: " + sponsorshipId);
        Log.e(TAG, "   personName: " + personName);
        Log.e(TAG, "   associationName: " + associationName);

        if (sponsorshipId == -1 || apiUrl == null) {
            Log.e(TAG, "❌ معاملات ناقصة!");
            Toast.makeText(this, "خطأ: معاملات ناقصة", Toast.LENGTH_SHORT).show();
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
        Log.e(TAG, "📹 فتح الكاميرا للتصوير...");

        try {
            // إنشاء Intent للتصوير
            Intent takeVideoIntent = new Intent(MediaStore.ACTION_VIDEO_CAPTURE);

            if (takeVideoIntent.resolveActivity(getPackageManager()) != null) {

                // إنشاء ملف للفيديو
                ContentValues values = new ContentValues();
                values.put(MediaStore.Video.Media.TITLE, "video_" + sponsorshipId + "_" + System.currentTimeMillis());
                values.put(MediaStore.Video.Media.DESCRIPTION, "Video for sponsorship " + sponsorshipId);
                values.put(MediaStore.Video.Media.MIME_TYPE, "video/mp4");

                videoUri = getContentResolver().insert(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, values);

                if (videoUri != null) {
                    takeVideoIntent.putExtra(MediaStore.EXTRA_OUTPUT, videoUri);
                    takeVideoIntent.putExtra(MediaStore.EXTRA_VIDEO_QUALITY, 1); // High quality

                    Log.e(TAG, "✅ Video URI created: " + videoUri.toString());
                    startActivityForResult(takeVideoIntent, REQUEST_VIDEO_CAPTURE);
                } else {
                    Log.e(TAG, "❌ فشل إنشاء URI للفيديو");
                    Toast.makeText(this, "خطأ في إنشاء ملف الفيديو", Toast.LENGTH_SHORT).show();
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

        if (requestCode == REQUEST_VIDEO_CAPTURE) {
            if (resultCode == RESULT_OK && videoUri != null) {
                Log.e(TAG, "");
                Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
                Log.e(TAG, "║  ✅ تم التصوير بنجاح!                                        ║");
                Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
                Log.e(TAG, "📹 Video URI: " + videoUri.toString());

                // الحصول على المسار الفعلي للملف
                String filePath = videoUri.toString();
                String fileName = "video_" + sponsorshipId + "_" + System.currentTimeMillis() + ".mp4";

                // حفظ في قاعدة البيانات وجدولة الرفع
                saveAndQueueUpload(filePath, fileName);

                Toast.makeText(this, "✅ تم حفظ الفيديو وجاري الرفع", Toast.LENGTH_LONG).show();

                // إرجاع معلومات الملف لـ JavaScript (لتحديث الإحصائيات!)
                Intent resultIntent = new Intent();
                resultIntent.putExtra("sponsorshipId", sponsorshipId);
                resultIntent.putExtra("fileName", fileName);
                resultIntent.putExtra("fileType", "video");
                resultIntent.putExtra("success", true);

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
                "video/mp4",
                sponsorshipId,
                apiUrl,
                authToken != null ? authToken : "",
                associationName != null ? associationName : "",
                personName != null ? personName : ""
            );

            Log.e(TAG, "✅ تم الحفظ في قاعدة البيانات - File ID: " + fileId);

            // ✅ جدولة FileSyncWorker باستخدام scheduleImmediateSync() - الطريقة الصحيحة!
            Log.e(TAG, "📤 جدولة الرفع الفوري عبر FileSyncWorker.scheduleImmediateSync()...");
            Log.e(TAG, "🔍🔍🔍 DIAGNOSTIC: About to call FileSyncWorker.scheduleImmediateSync()...");
            try {
                Log.e(TAG, "🚀🚀🚀 CALLING FileSyncWorker.scheduleImmediateSync() NOW!");
                FileSyncWorker.scheduleImmediateSync(this);
                Log.e(TAG, "✅✅✅ FileSyncWorker.scheduleImmediateSync() RETURNED - تم جدولة FileSyncWorker!");
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
}
