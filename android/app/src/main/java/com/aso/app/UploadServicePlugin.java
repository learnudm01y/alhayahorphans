package com.aso.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.os.Build;
import android.util.Log;
import androidx.core.app.NotificationCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.util.List;

@CapacitorPlugin(name = "UploadService")
public class UploadServicePlugin extends Plugin {
    private static final String TAG = "UploadServicePlugin";
    private static final String CHANNEL_ID = "upload_notifications";
    private static final String CHANNEL_NAME = "رفع الملفات";
    private static int notificationId = 1000;

    @Override
    public void load() {
        super.load();

        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  🔌 UploadServicePlugin.load() - PLUGIN LOADED               ║");
        android.util.Log.e(TAG, "║  ✅ JavaScript can now call: UploadService.addFileToQueue()  ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "");

        // إنشاء قناة الإشعارات
        createNotificationChannel();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_DEFAULT
            );
            channel.setDescription("إشعارات رفع الملفات للخادم");

            NotificationManager manager = getContext().getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
                android.util.Log.e(TAG, "✅ تم إنشاء قناة الإشعارات");
            }
        }
    }

    private void showNotification(String title, String message, boolean isSuccess) {
        try {
            NotificationManager manager = (NotificationManager) getContext().getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager == null) return;

            int icon = isSuccess ? android.R.drawable.stat_sys_upload_done : android.R.drawable.stat_sys_warning;

            NotificationCompat.Builder builder = new NotificationCompat.Builder(getContext(), CHANNEL_ID)
                .setSmallIcon(icon)
                .setContentTitle(title)
                .setContentText(message)
                .setPriority(NotificationCompat.PRIORITY_DEFAULT)
                .setAutoCancel(true);

            manager.notify(notificationId++, builder.build());
            android.util.Log.e(TAG, "📢 إشعار: " + title + " - " + message);
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ فشل إظهار الإشعار: " + e.getMessage());
        }
    }

    private static final int PROGRESS_NOTIFICATION_ID = 9999;

    /**
     * تحديث إشعار التقدم مع progress bar
     */
    private void updateProgressNotification(int current, int total, String fileName, boolean isComplete) {
        try {
            NotificationManager manager = (NotificationManager) getContext().getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager == null) return;

            NotificationCompat.Builder builder = new NotificationCompat.Builder(getContext(), CHANNEL_ID)
                .setSmallIcon(android.R.drawable.stat_sys_upload)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setOngoing(!isComplete);

            if (isComplete) {
                // إشعار النجاح النهائي
                builder.setContentTitle("✅ تم الرفع بنجاح")
                       .setContentText(fileName)
                       .setProgress(0, 0, false)
                       .setAutoCancel(true)
                       .setSmallIcon(android.R.drawable.stat_sys_upload_done);
            } else {
                // إشعار التقدم
                int percentage = total > 0 ? (current * 100 / total) : 0;
                builder.setContentTitle("جاري الرفع... (" + current + "/" + total + ")")
                       .setContentText(fileName)
                       .setProgress(100, percentage, false);
            }

            manager.notify(PROGRESS_NOTIFICATION_ID, builder.build());
            android.util.Log.e(TAG, "📊 Progress: " + current + "/" + total + " - " + fileName);
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ فشل تحديث progress: " + e.getMessage());
        }
    }

    /**
     * إلغاء إشعار التقدم
     */
    private void cancelProgressNotification() {
        try {
            NotificationManager manager = (NotificationManager) getContext().getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager != null) {
                manager.cancel(PROGRESS_NOTIFICATION_ID);
                android.util.Log.e(TAG, "🚫 تم إلغاء progress notification");
            }
        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ فشل إلغاء progress: " + e.getMessage());
        }
    }

    /**
     * إضافة ملف - رفع فوري مباشر
     */
    @PluginMethod
    public void addFileToQueue(PluginCall call) {
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "╔════════════════════════════════════════════════════════════════╗");
        android.util.Log.e(TAG, "║  🔥🔥🔥 addFileToQueue() CALLED FROM JAVASCRIPT 🔥🔥🔥         ║");
        android.util.Log.e(TAG, "╚════════════════════════════════════════════════════════════════╝");
        android.util.Log.e(TAG, "⏱️  Timestamp: " + System.currentTimeMillis());
        android.util.Log.e(TAG, "🧵 Thread: " + Thread.currentThread().getName());

        try {
            android.util.Log.e(TAG, "📥 [Step 1/6] Extracting parameters from JavaScript call...");
            String filePath = call.getString("filePath");
            String fileName = call.getString("fileName");
            String fileType = call.getString("fileType", "image/jpeg");
            Integer photoId = call.getInt("photoId");
            String apiUrl = call.getString("apiUrl");
            Integer indexedDbId = call.getInt("indexedDbId"); // ID من IndexedDB
            String authToken = call.getString("authToken", ""); // Auth Token
            String associationName = call.getString("associationName");
            String personName = call.getString("personName");

            // محاولة جلب البيانات من قاعدة البيانات
            if ((associationName == null || associationName.isEmpty() || associationName.equals("default")) ||
                (personName == null || personName.isEmpty() || personName.equals("unknown"))) {

                android.util.Log.e(TAG, "⚠️ Names not provided by JS - fetching from IndexedDB directly...");

                if (photoId != null && photoId > 0) {
                    // محاولة 1: البحث في SponsorshipMappingHelper
                    SponsorshipMappingHelper mappingHelper = SponsorshipMappingHelper.getInstance(getContext());
                    SponsorshipMappingHelper.MappingData mapping = mappingHelper.getMapping(photoId);

                    if (mapping != null && !mapping.associationName.equals("General")) {
                        associationName = mapping.associationName;
                        personName = mapping.personName;
                        android.util.Log.e(TAG, "   ✅ Found in mapping DB (sponsorshipId=" + photoId + ")");
                        android.util.Log.e(TAG, "   ✅ Association: " + associationName);
                        android.util.Log.e(TAG, "   ✅ Person: " + personName);
                    } else {
                        // محاولة 2: قراءة مباشرة من IndexedDB عبر WebView
                        android.util.Log.e(TAG, "   ⚠️ No cached mapping - reading from IndexedDB via WebView...");
                        android.util.Log.e(TAG, "   🔍 sponsorshipId=" + photoId + ", Bridge=" + (getBridge() != null) + ", WebView=" + (getBridge() != null && getBridge().getWebView() != null));

                        try {
                            IndexedDBReader reader = IndexedDBReader.getInstance(getContext(), getBridge());
                            android.util.Log.e(TAG, "   📖 IndexedDBReader instance created");

                            IndexedDBReader.MappingData data = reader.getSponsorshipData(photoId);
                            android.util.Log.e(TAG, "   📨 IndexedDBReader returned: assoc=" + (data != null ? data.associationName : "null") + ", person=" + (data != null ? data.personName : "null"));

                            if (data != null && !data.associationName.equals("General")) {
                                associationName = data.associationName;
                                personName = data.personName;

                                android.util.Log.e(TAG, "   ✅✅✅ SUCCESS! Fetched from IndexedDB directly:");
                                android.util.Log.e(TAG, "   ✅ Association: " + associationName);
                                android.util.Log.e(TAG, "   ✅ Person: " + personName);

                                // حفظ في mapping database للمرات القادمة
                                mappingHelper.saveMapping(photoId, associationName, personName);
                                android.util.Log.e(TAG, "   ✅ Saved to mapping DB for future use");
                            } else {
                                android.util.Log.e(TAG, "   ⚠️⚠️⚠️ IndexedDB returned DEFAULTS - Check browser console!");
                                android.util.Log.e(TAG, "   ⚠️ Expected real data but got: assoc=" + (data != null ? data.associationName : "null") + ", person=" + (data != null ? data.personName : "null"));
                                android.util.Log.e(TAG, "   ⚠️ This means:");
                                android.util.Log.e(TAG, "   ⚠️   1. IndexedDB query failed (check browser console for [IndexedDBReader] logs)");
                                android.util.Log.e(TAG, "   ⚠️   2. Sponsorship ID " + photoId + " doesn't exist in IndexedDB");
                                android.util.Log.e(TAG, "   ⚠️   3. Database variable 'db' is undefined");
                                android.util.Log.e(TAG, "   ⚠️   4. Table names don't match (not 'sponsorships' or 'associations')");
                                if (associationName == null || associationName.isEmpty()) associationName = "General";
                                if (personName == null || personName.isEmpty()) personName = "unknown";
                            }
                        } catch (Exception e) {
                            android.util.Log.e(TAG, "   ❌❌❌ EXCEPTION in IndexedDBReader: " + e.getMessage());
                            e.printStackTrace();
                            if (associationName == null || associationName.isEmpty()) associationName = "General";
                            if (personName == null || personName.isEmpty()) personName = "unknown";
                        }
                    }
                } else {
                    android.util.Log.e(TAG, "   ❌ photoId is null or 0 - cannot fetch data");
                    if (associationName == null || associationName.isEmpty()) associationName = "General";
                    if (personName == null || personName.isEmpty()) personName = "unknown";
                }
            }

            // Enhanced parameter logging
            android.util.Log.e(TAG, "📊 [Step 2/6] Final parameters:");
            android.util.Log.e(TAG, "   ├─ fileName: " + fileName);
            android.util.Log.e(TAG, "   ├─ photoId: " + photoId);
            android.util.Log.e(TAG, "   ├─ fileType: " + fileType);
            android.util.Log.e(TAG, "   ├─ apiUrl: " + apiUrl);
            android.util.Log.e(TAG, "   ├─ indexedDbId: " + indexedDbId);
            android.util.Log.e(TAG, "   ├─ authToken: " + (authToken != null && authToken.length() > 0 ? "Present (" + authToken.length() + " chars)" : "Missing"));
            android.util.Log.e(TAG, "   ├─ associationName: '" + associationName + "'");
            android.util.Log.e(TAG, "   ├─ personName: '" + personName + "'");
            android.util.Log.e(TAG, "   └─ filePathLength: " + (filePath != null ? filePath.length() : 0) + " chars");

            // Critical warning if still using defaults
            if ("General".equals(associationName) || "unknown".equals(personName)) {
                android.util.Log.e(TAG, "⚠️⚠️⚠️ CRITICAL: Still using default names!");
                android.util.Log.e(TAG, "⚠️ Folder: Pictures/sponsorships_alhayahorphans/" + associationName + "/" + personName + "/");
                android.util.Log.e(TAG, "⚠️ SOLUTION:");
                android.util.Log.e(TAG, "⚠️   Call IndexedDBBridge.saveSponsorshipMappings() from JavaScript");
                android.util.Log.e(TAG, "⚠️   Example: await IndexedDBBridge.saveSponsorshipMappings({");
                android.util.Log.e(TAG, "⚠️     mappings: [{sponsorshipId: 908, associationName: 'xyz', personName: 'abc'}]");
                android.util.Log.e(TAG, "⚠️   })");
            } else {
                android.util.Log.e(TAG, "✅✅✅ Using REAL names from mapping database!");
                android.util.Log.e(TAG, "✅ Folder: Pictures/sponsorships_alhayahorphans/" + associationName + "/" + personName + "/");
            }

            android.util.Log.e(TAG, "✅ [Step 3/6] Validating parameters...");
            if (filePath == null || fileName == null || photoId == null || apiUrl == null) {
                android.util.Log.e(TAG, "❌❌❌ VALIDATION FAILED - Missing required parameters!");
                android.util.Log.e(TAG, "   filePath null? " + (filePath == null));
                android.util.Log.e(TAG, "   fileName null? " + (fileName == null));
                android.util.Log.e(TAG, "   photoId null? " + (photoId == null));
                android.util.Log.e(TAG, "   apiUrl null? " + (apiUrl == null));
                showNotification("خطأ في الرفع", "معاملات ناقصة", false);
                call.reject("معاملات ناقصة");
                return;
            }

            // ✅ [Step 4/6] حفظ الملف في Internal Storage (دائم)
            android.util.Log.e(TAG, "💾 [Step 4/6] حفض الملف في Internal Storage...");

            // حفظ auth token في SharedPreferences
            if (authToken != null && !authToken.isEmpty()) {
                getContext().getSharedPreferences("capacitor", android.content.Context.MODE_PRIVATE)
                        .edit()
                        .putString("auth_token", authToken)
                        .apply();
                android.util.Log.e(TAG, "🔑 Auth token saved");
            }

            // استخراج Base64 من data URI
            String base64Data = null;
            if (filePath.startsWith("data:")) {
                String[] parts = filePath.split(",");
                if (parts.length == 2) {
                    base64Data = parts[1];
                    android.util.Log.e(TAG, "✅ Base64 extracted: " + base64Data.length() + " chars");
                } else {
                    android.util.Log.e(TAG, "❌ Invalid data URI format");
                    call.reject("تنسيق data URI خاطئ");
                    return;
                }
            } else {
                android.util.Log.e(TAG, "❌ Not a data URI");
                call.reject("ليس data URI");
                return;
            }

            // تحويل Base64 إلى bytes
            byte[] fileBytes;
            try {
                fileBytes = android.util.Base64.decode(base64Data, android.util.Base64.DEFAULT);
                android.util.Log.e(TAG, "✅ Decoded to " + fileBytes.length + " bytes");
            } catch (Exception e) {
                android.util.Log.e(TAG, "❌ Base64 decode failed: " + e.getMessage());
                call.reject("فشل فك الترميز");
                return;
            }

            // إنشاء مجلد uploads في Internal Storage
            java.io.File uploadsDir = new java.io.File(getContext().getFilesDir(), "uploads");
            if (!uploadsDir.exists()) {
                uploadsDir.mkdirs();
                android.util.Log.e(TAG, "📁 Created uploads directory: " + uploadsDir.getAbsolutePath());
            }

            // حفظ الملف في Internal Storage
            java.io.File savedFile = new java.io.File(uploadsDir, fileName);
            try {
                java.io.FileOutputStream fos = new java.io.FileOutputStream(savedFile);
                fos.write(fileBytes);
                fos.close();
                android.util.Log.e(TAG, "✅ File saved (Internal): " + savedFile.getAbsolutePath());
            } catch (Exception e) {
                android.util.Log.e(TAG, "❌ File save failed: " + e.getMessage(), e);
                call.reject("فشل حفظ الملف");
                return;
            }

            // أيضاً حفظ نسخة في External Storage - مجلد sponsorships_alhayahorphans/جمعية/شخص
            try {
                // تنظيف أسماء المجلدات من الأحرف غير المسموحة
                String safeDirName = associationName.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
                String safePersonName = personName.replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");

                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.Q) {
                    // Android 10+ - استخدام Documents (يقبل جميع أنواع الملفات)
                    android.content.ContentValues values = new android.content.ContentValues();
                    values.put(android.provider.MediaStore.Files.FileColumns.DISPLAY_NAME, fileName);
                    values.put(android.provider.MediaStore.Files.FileColumns.MIME_TYPE, fileType);
                    values.put(android.provider.MediaStore.Files.FileColumns.RELATIVE_PATH,
                        android.os.Environment.DIRECTORY_DOCUMENTS + "/sponsorships_alhayahorphans/" + safeDirName + "/" + safePersonName);

                    android.net.Uri documentsUri = android.provider.MediaStore.Files.getContentUri("external");
                    android.net.Uri mediaUri = getContext().getContentResolver().insert(documentsUri, values);

                    if (mediaUri != null) {
                        java.io.OutputStream fos = getContext().getContentResolver().openOutputStream(mediaUri);
                        if (fos != null) {
                            fos.write(fileBytes);
                            fos.close();
                            android.util.Log.e(TAG, "✅ نسخة محفوظة في Documents/sponsorships_alhayahorphans/" + safeDirName + "/" + safePersonName + "/ (MediaStore)");
                        }
                    }
                } else {
                    // Android 9 وأقل - استخدام File API (كل شيء في Documents)
                    java.io.File documentsDir = android.os.Environment.getExternalStoragePublicDirectory(
                        android.os.Environment.DIRECTORY_DOCUMENTS);
                    java.io.File mainDir = new java.io.File(documentsDir, "sponsorships_alhayahorphans");
                    java.io.File associationDir = new java.io.File(mainDir, safeDirName);
                    java.io.File externalDir = new java.io.File(associationDir, safePersonName);

                    if (!externalDir.exists()) {
                        boolean created = externalDir.mkdirs();
                        android.util.Log.e(TAG, "📁 مجلد خارجي: " + (created ? "تم الإنشاء" : "موجود مسبقاً") +
                            " - " + externalDir.getAbsolutePath());
                    }

                    java.io.File externalFile = new java.io.File(externalDir, fileName);
                    java.io.FileOutputStream fos = new java.io.FileOutputStream(externalFile);
                    fos.write(fileBytes);
                    fos.close();

                    // إشعار MediaScanner بالملف الجديد
                    android.content.Intent mediaScanIntent = new android.content.Intent(
                        android.content.Intent.ACTION_MEDIA_SCANNER_SCAN_FILE);
                    mediaScanIntent.setData(android.net.Uri.fromFile(externalFile));
                    getContext().sendBroadcast(mediaScanIntent);

                    android.util.Log.e(TAG, "✅ نسخة محفوظة في: " + externalFile.getAbsolutePath());
                }
            } catch (Exception e) {
                // الحفظ في External اختياري - لا نفشل إذا فشل
                android.util.Log.w(TAG, "⚠️ فشل الحفظ في External Storage: " + e.getMessage());
                e.printStackTrace();
            }

            // حفظ المسار النسبي في SQLite مع associationName و personName
            String relativePath = "uploads/" + fileName;
            android.util.Log.e(TAG, "📂 Relative path: " + relativePath);

            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            long fileId = dbHelper.addFileToQueue(relativePath, fileName, fileType, photoId, apiUrl, associationName, personName);

            android.util.Log.e(TAG, "💾 Saved to SQLite with Association='" + associationName + "', Person='" + personName + "'");

            // حفظ mapping لـ IndexedDB
            if (indexedDbId != null && indexedDbId > 0) {
                dbHelper.saveIndexedDbMapping(fileId, indexedDbId);
                android.util.Log.e(TAG, "📊 Mapping saved: SQLite ID=" + fileId + " → IndexedDB ID=" + indexedDbId);
            }

            if (fileId <= 0) {
                android.util.Log.e(TAG, "❌ فشل الحفظ في Database!");
                showNotification("خطأ ❌", "فشل حفظ الملف", false);
                call.reject("فشل الحفظ");
                return;
            }

            android.util.Log.e(TAG, "✅ تم الحفظ - Queue ID: " + fileId);

            // رد فوري على JavaScript
            JSObject result = new JSObject();
            result.put("success", true);
            result.put("fileId", fileId);
            result.put("queued", true);
            call.resolve(result);

            android.util.Log.e(TAG, "📤 [Step 5/6] بدء الرفع في Foreground Service...");

            // بدء Foreground Service للرفع المستمر
            android.util.Log.e(TAG, "🚀 بدء UploadForegroundService...");
            android.util.Log.e(TAG, "   ├─ عدد الملفات المعلقة: " + dbHelper.getPendingFilesCount());
            android.util.Log.e(TAG, "   └─ Service سيعمل حتى عند إغلاق التطبيق");

            try {
                android.content.Intent serviceIntent = new android.content.Intent(getContext(), UploadForegroundService.class);

                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                    getContext().startForegroundService(serviceIntent);
                    android.util.Log.e(TAG, "✅ startForegroundService() called (Android 8+)");
                } else {
                    getContext().startService(serviceIntent);
                    android.util.Log.e(TAG, "✅ startService() called (Android <8)");
                }

                android.util.Log.e(TAG, "✅ Foreground Service started - الرفع سيستمر حتى عند إغلاق التطبيق");
            } catch (Exception serviceError) {
                android.util.Log.e(TAG, "❌❌❌ فشل بدء Service: " + serviceError.getMessage(), serviceError);
                throw serviceError;
            }

            android.util.Log.e(TAG, "✅ [Step 6/6] تم بدء خدمة الرفع المستمرة");

        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ خطأ: " + e.getMessage(), e);
            showNotification("خطأ في الرفع", e.getMessage(), false);
            call.reject("خطأ: " + e.getMessage());
        }
    }

    private boolean uploadFile(String path, String name, String type, int photoId, String url, String authToken) {
        java.net.HttpURLConnection conn = null;

        try {
            // استخراج Base64 data
            String base64Data = null;

            if (path.startsWith("data:")) {
                android.util.Log.e(TAG, "📦 استخراج Base64...");
                String[] p = path.split(",");
                if (p.length == 2) {
                    base64Data = p[1];
                    android.util.Log.e(TAG, "✅ طول Base64: " + base64Data.length() + " chars");
                } else {
                    android.util.Log.e(TAG, "❌ Base64 خطأ في التنسيق");
                    return false;
                }
            }

            if (base64Data == null || base64Data.length() == 0) {
                android.util.Log.e(TAG, "❌ الملف فارغ");
                return false;
            }

            android.util.Log.e(TAG, "");
            android.util.Log.e(TAG, "🌐 الرابط الكامل: " + url);
            android.util.Log.e(TAG, "📁 الملف: " + name);
            android.util.Log.e(TAG, "🆔 sponsorship_id: " + photoId);
            android.util.Log.e(TAG, "📊 fileType: " + type);
            android.util.Log.e(TAG, "");

            java.net.URL u = new java.net.URL(url);
            conn = (java.net.HttpURLConnection) u.openConnection();
            conn.setDoOutput(true);
            conn.setRequestMethod("POST");

            // إرسال JSON كما في upload.html
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Accept", "application/json");

            // إضافة Authorization header
            if (authToken != null && authToken.length() > 0) {
                conn.setRequestProperty("Authorization", "Bearer " + authToken);
                android.util.Log.e(TAG, "🔑 Authorization header added");
            } else {
                android.util.Log.e(TAG, "⚠️ No auth token - request may fail with 401");
            }

            conn.setConnectTimeout(30000);
            conn.setReadTimeout(60000);

            // بناء JSON payload كما في upload.html
            org.json.JSONObject json = new org.json.JSONObject();
            json.put("file_name", name);
            json.put("file_type", type);
            json.put("file_data", base64Data);  // Base64 string بدون data URI prefix
            json.put("sponsorship_id", photoId);

            android.util.Log.e(TAG, "📤 إرسال JSON payload...");
            android.util.Log.e(TAG, "   file_name: " + name);
            android.util.Log.e(TAG, "   file_type: " + type);
            android.util.Log.e(TAG, "   sponsorship_id: " + photoId);
            android.util.Log.e(TAG, "   file_data length: " + base64Data.length() + " chars");

            java.io.OutputStream out = conn.getOutputStream();
            byte[] jsonBytes = json.toString().getBytes("UTF-8");
            out.write(jsonBytes);
            out.flush();
            out.close();

            android.util.Log.e(TAG, "✅ تم إرسال البيانات (" + jsonBytes.length + " bytes)، انتظار الاستجابة...");

            int code = conn.getResponseCode();
            String message = conn.getResponseMessage();

            android.util.Log.e(TAG, "📡 رمز الاستجابة: " + code + " - " + message);

            // قراءة استجابة الخادم
            try {
                java.io.InputStream in = (code >= 200 && code < 300)
                    ? conn.getInputStream()
                    : conn.getErrorStream();

                if (in != null) {
                    java.io.BufferedReader reader = new java.io.BufferedReader(
                        new java.io.InputStreamReader(in)
                    );
                    StringBuilder response = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) {
                        response.append(line);
                    }
                    reader.close();

                    String responseText = response.toString();
                    if (responseText.length() > 0) {
                        android.util.Log.e(TAG, "📥 استجابة الخادم: " + responseText.substring(0, Math.min(500, responseText.length())));
                    }
                }
            } catch (Exception e) {
                android.util.Log.e(TAG, "⚠️ لا يمكن قراءة استجابة الخادم: " + e.getMessage());
            }

            boolean success = (code >= 200 && code < 300);

            if (success) {
                android.util.Log.e(TAG, "✅✅✅ الرفع نجح!");
            } else {
                android.util.Log.e(TAG, "❌❌❌ الرفع فشل! رمز: " + code);
            }

            return success;

        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ خطأ في الرفع: " + e.getMessage(), e);
            return false;
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
    }

    /**
     * مزامنة حالة الملفات المرفوعة من SQLite إلى IndexedDB
     * يُستدعى عند فتح التطبيق
     */
    @PluginMethod
    public void syncUploadedFilesStatus(PluginCall call) {
        android.util.Log.e(TAG, "");
        android.util.Log.e(TAG, "🔄🔄🔄 syncUploadedFilesStatus() استُدعي من JavaScript");

        try {
            UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
            List<Long> uploadedSqliteIds = dbHelper.getUploadedFilesNeedingSync();

            android.util.Log.e(TAG, "📊 عدد الملفات المرفوعة في SQLite: " + uploadedSqliteIds.size());

            org.json.JSONArray syncedIds = new org.json.JSONArray();

            for (Long sqliteId : uploadedSqliteIds) {
                int indexedDbId = dbHelper.getIndexedDbId(sqliteId);

                if (indexedDbId > 0) {
                    android.util.Log.e(TAG, "✅ مزامنة: SQLite ID=" + sqliteId + " → IndexedDB ID=" + indexedDbId);

                    // استدعاء JavaScript لتحديث IndexedDB
                    try {
                        getBridge().eval("window.SyncService && window.SyncService.markFileAsUploaded(" + indexedDbId + ")", null);
                        syncedIds.put(indexedDbId);
                    } catch (Exception e) {
                        android.util.Log.e(TAG, "⚠️ فشل callback لـ IndexedDB ID=" + indexedDbId);
                    }
                }
            }

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("syncedCount", syncedIds.length());
            result.put("syncedIds", syncedIds);

            android.util.Log.e(TAG, "✅ تمت مزامنة " + syncedIds.length() + " ملف");
            call.resolve(result);

        } catch (Exception e) {
            android.util.Log.e(TAG, "❌ خطأ في المزامنة: " + e.getMessage(), e);
            call.reject("خطأ في المزامنة: " + e.getMessage());
        }
    }
}
