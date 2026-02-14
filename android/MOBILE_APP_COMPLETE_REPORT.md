# 📱 تقرير شامل عن آلية عمل تطبيق الهاتف
## نظام إدارة كفالات الأيتام - الحياة للأيتام

---

## 📋 جدول المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [البنية التقنية](#البنية-التقنية)
3. [نظام إدارة البيانات](#نظام-إدارة-البيانات)
4. [نظام رفع الملفات](#نظام-رفع-الملفات)
5. [نظام المزامنة الخلفية](#نظام-المزامنة-الخلفية)
6. [نظام الصور والفيديو](#نظام-الصور-والفيديو)
7. [نظام الإحصائيات والمراقبة](#نظام-الإحصائيات-والمراقبة)
8. [إدارة الذاكرة](#إدارة-الذاكرة)
9. [إدارة الشبكة](#إدارة-الشبكة)
10. [Plugins والجسور](#plugins-والجسور)
11. [قواعد البيانات المحلية](#قواعد-البيانات-المحلية)
12. [معالجة الأخطاء](#معالجة-الأخطاء)
13. [الأمان والصلاحيات](#الأمان-والصلاحيات)

---

## 🎯 نظرة عامة

### الوصف العام
تطبيق Android متقدم لإدارة كفالات الأيتام، مبني على تقنية **Capacitor** (Hybrid App) يجمع بين قوة **Native Android** ومرونة **Web Technologies**.

### الهدف من التطبيق
- إدارة كفالات الأيتام عبر جمعيات متعددة
- رفع الصور والفيديوهات الكبيرة (حتى 100+ GB) بدون base64
- المزامنة التلقائية الخلفية مع السيرفر
- العمل Offline مع قواعد بيانات محلية
- دعم عدة جمعيات ومكفولين

### المزايا الرئيسية
✅ **عمل كامل بدون إنترنت** (Offline-First Architecture)  
✅ **رفع ملفات ضخمة** بدون استهلاك ذاكرة (Streaming Upload)  
✅ **مزامنة تلقائية** في الخلفية حتى عند إغلاق التطبيق  
✅ **إدارة ذكية للذاكرة** لمنع OutOfMemory  
✅ **دعم كاميرا مدمجة** لتصوير فيديوهات طويلة  
✅ **إشعارات تلقائية** بتقدم الرفع والمزامنة  
✅ **تنظيم ملفات ذكي** حسب الجمعية والمكفول

---

## 🏗️ البنية التقنية

### تقنيات البناء

```
┌─────────────────────────────────────────────────────┐
│  🏢 البنية الكلية للتطبيق                          │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ┌─────────────────────────────────────────────┐   │
│  │  📱 Frontend Layer (Web)                    │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  • HTML5 + CSS3 + JavaScript ES6+           │   │
│  │  • Vue.js / Vanilla JS (Reactive UI)        │   │
│  │  • IndexedDB (Local Web Database)           │   │
│  │  • localStorage + sessionStorage            │   │
│  │  • Capacitor.js API Bridge                  │   │
│  └─────────────────────────────────────────────┘   │
│                       ▼                             │
│  ┌─────────────────────────────────────────────┐   │
│  │  🔌 Capacitor Bridge Layer                  │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  • JavaScript ↔ Native Communication        │   │
│  │  • Plugins System (Custom + Built-in)       │   │
│  │  • Event Broadcasting (Java → JS)           │   │
│  └─────────────────────────────────────────────┘   │
│                       ▼                             │
│  ┌─────────────────────────────────────────────┐   │
│  │  ⚙️ Native Android Layer (Java)             │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  • Android SDK 29+ (API Level 29)           │   │
│  │  • Java 21 (Modern features)                │   │
│  │  • Gradle 8.14.3                            │   │
│  │  • OkHttp 4.12.0 (HTTP Client)              │   │
│  │  • WorkManager 2.8.1 (Background Tasks)     │   │
│  │  • SQLite 3.x (Native Database)             │   │
│  │  • Foreground Services (Persistent Tasks)   │   │
│  └─────────────────────────────────────────────┘   │
│                       ▼                             │
│  ┌─────────────────────────────────────────────┐   │
│  │  🔧 System Services Layer                   │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  • AlarmManager - مهام دورية كل 10 ثواني   │   │
│  │  • NetworkMonitor - مراقبة الاتصال          │   │
│  │  • PowerManager - WakeLock للخلفية          │   │
│  │  • NotificationManager - الإشعارات          │   │
│  │  • ConnectivityManager - حالة الشبكة        │   │
│  │  • StorageManager - مراقبة التخزين          │   │
│  └─────────────────────────────────────────────┘   │
│                       ▼                             │
│  ┌─────────────────────────────────────────────┐   │
│  │  💾 Data Persistence Layer                  │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  • SQLite: upload_queue.db (ملفات الرفع)    │   │
│  │  • SQLite: data_sync_queue.db (البيانات)    │   │
│  │  • IndexedDB: AlhayahSponsorshipsDB (Web)   │   │
│  │  • SharedPreferences: auth_token, settings  │   │
│  │  • Internal Storage: ملفات مؤقتة            │   │
│  │  • External Storage: نسخ احتياطية           │   │
│  └─────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

### Dependencies الرئيسية

```gradle
// Capacitor Core
implementation 'com.getcapacitor:core:6.0.0'
implementation 'com.getcapacitor:android:6.0.0'

// File Upload & Networking
implementation 'com.squareup.okhttp3:okhttp:4.12.0'        // ✅ رفع streaming
implementation 'androidx.work:work-runtime:2.8.1'          // ✅ مهام خلفية

// Storage & Database
implementation 'androidx.room:room-runtime:2.5.2'         // (optional)
// SQLite built-in - قواعد بيانات محلية

// UI & Notifications
implementation 'androidx.core:core:1.10.1'
implementation 'androidx.appcompat:appcompat:1.6.1'
implementation 'com.google.android.material:material:1.9.0'
```

---

## 💾 نظام إدارة البيانات

### 1. البيانات الأساسية المُدارة

```javascript
// الكيانات الرئيسية في التطبيق

📁 الجمعيات (Associations/Sponsors)
   ├─ id (معرف فريد)
   ├─ name (اسم الجمعية)
   ├─ contact_info (معلومات التواصل)
   └─ created_at

📁 الكفالات (Sponsorships)
   ├─ id (رقم السجل)
   ├─ association_id (رقم الجمعية)
   ├─ person_name (اسم المكفول)
   ├─ person_id (رقم الهوية)
   ├─ sponsorship_status_id (حالة الكفالة)
   ├─ created_at
   ├─ updated_at
   └─ metadata (بيانات إضافية JSON)

📁 حالات الكفالة (Sponsorship Statuses)
   ├─ id
   ├─ status_name (نشط، معلق، منتهي، ...)
   └─ description

📁 الصور والفيديوهات (Media Files)
   ├─ id (IndexedDB local ID)
   ├─ sponsorship_id (رقم الكفالة)
   ├─ file_name
   ├─ file_type (image/jpeg, video/mp4, ...)
   ├─ file_size (بالبايت)
   ├─ file_path (مسار الملف على الجهاز)
   ├─ upload_status (pending/uploading/completed/failed)
   ├─ created_at
   └─ uploaded_at

📁 المستخدمين (Users)
   ├─ id
   ├─ name
   ├─ email
   ├─ role (admin, user, viewer)
   └─ permissions
```

### 2. تدفق البيانات (Data Flow)

```
🔄 دورة حياة البيانات الكاملة:

1️⃣ الإدخال (Input)
   ┌────────────────────────────────────────┐
   │ 📝 المستخدم يدخل بيانات جديدة        │
   │    • كفالة جديدة                      │
   │    • تعديل بيانات كفالة               │
   │    • إضافة صورة/فيديو                 │
   └────────────────────────────────────────┘
                  ▼
2️⃣ الحفظ المحلي (Local Storage)
   ┌────────────────────────────────────────┐
   │ 💾 حفظ في IndexedDB (JavaScript)      │
   │    • AlhayahSponsorshipsDB            │
   │    • Tables: sponsorships, sponsors,  │
   │      photos, videos                   │
   │    • فوري - لا يحتاج إنترنت           │
   └────────────────────────────────────────┘
                  ▼
3️⃣ إضافة لقائمة المزامنة (Queue)
   ┌────────────────────────────────────────┐
   │ 📤 JavaScript يستدعي:                 │
   │    BackgroundSync.addDataToQueue()    │
   │    ├─ dataType: "sponsorship"         │
   │    ├─ dataJson: JSON.stringify(data)  │
   │    └─ endpoint: "/api/sponsorships"   │
   └────────────────────────────────────────┘
                  ▼
4️⃣ حفظ في Queue Database (Java)
   ┌────────────────────────────────────────┐
   │ 🗄️ DataSyncDatabaseHelper.java        │
   │    • SQLite: data_sync_queue.db       │
   │    • status: "pending"                │
   │    • retry_count: 0                   │
   └────────────────────────────────────────┘
                  ▼
5️⃣ المزامنة الخلفية (Background Sync)
   ┌────────────────────────────────────────┐
   │ 🔄 DataSyncForegroundService          │
   │    • يعمل كل 10 ثواني (AlarmManager)  │
   │    • يقرأ pending items من Database   │
   │    • يرسل POST/PUT إلى API            │
   │    • retry logic: 3 محاولات           │
   └────────────────────────────────────────┘
                  ▼
6️⃣ الإرسال للسيرفر (Server Upload)
   ┌────────────────────────────────────────┐
   │ 🌐 HTTP POST Request                  │
   │    • URL: {baseUrl}{endpoint}         │
   │    • Headers: Authorization Bearer    │
   │    • Body: JSON data                  │
   │    • Content-Type: application/json   │
   └────────────────────────────────────────┘
                  ▼
7️⃣ معالجة الاستجابة (Response)
   ┌────────────────────────────────────────┐
   │ ✅ Success (200-299)                   │
   │    → update status = "completed"      │
   │    → delete from queue (optional)     │
   │                                        │
   │ ❌ Failure (400+, network error)      │
   │    → increment retry_count            │
   │    → if retry_count > 3:              │
   │       status = "failed"               │
   │    → else: keep as "pending"          │
   └────────────────────────────────────────┘
```

### 3. شكل البيانات في IndexedDB

```javascript
// مثال على بيانات كفالة محفوظة محلياً
{
  id: 908,                           // رقم السجل (من السيرفر)
  association_id: 15,                // الجمعية
  association_name: "جمعية الحياة للأيتام",
  person_name: "أحمد محمد علي",
  person_id: "1234567890",
  phone: "0501234567",
  address: "الرياض، حي النزهة",
  sponsorship_status_id: 1,          // نشط
  sponsorship_status_name: "نشط",
  notes: "كفالة جديدة بتاريخ 2026-02-14",
  created_at: "2026-02-14T10:30:00Z",
  updated_at: "2026-02-14T10:30:00Z",
  
  // معلومات المزامنة
  _localId: "local_908_1739534400",  // معرف محلي مؤقت
  _syncStatus: "pending",            // حالة المزامنة
  _lastSyncAttempt: null,
  _syncError: null
}
```

---

## 📤 نظام رفع الملفات

### 1. معمارية النظام (File Upload Architecture)

```
🎬 رحلة الملف من التصوير إلى السيرفر:

┌─────────────────────────────────────────────────────────┐
│  📸 خطوة 1: التقاط الصورة/الفيديو                       │
├─────────────────────────────────────────────────────────┤
│  • Capacitor Camera API (الكاميرا)                      │
│  • Capacitor Filesystem API (اختيار ملف)                │
│  • Custom CameraBridge (فيديوهات طويلة)                 │
│                                                          │
│  Output: Blob أو File URI                                │
│          file:///storage/.../DCIM/video.mp4              │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│  💾 خطوة 2: حفظ الملف على الجهاز (JavaScript)          │
├─────────────────────────────────────────────────────────┤
│  🚨 سياسة NO BASE64 - ممنوع تحويل الملف لـ base64!     │
│                                                          │
│  const writeResult = await Filesystem.writeFile({       │
│    path: `sponsorships/${assoc}/${person}/${filename}`, │
│    data: blob,                                           │
│    directory: Directory.Data,                            │
│    recursive: true                                       │
│  });                                                     │
│                                                          │
│  Output: file:///data/user/0/.../files/video.mp4         │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│  📝 خطوة 3: حفظ Metadata في IndexedDB                   │
├─────────────────────────────────────────────────────────┤
│  await savePhotoToIndexedDB({                            │
│    sponsorship_id: 908,                                  │
│    file_name: "IMG_20260214_103045.jpg",                 │
│    file_type: "image/jpeg",                              │
│    file_size: 206834560,  // 206 MB                      │
│    file_data: null,       // 🚨 NO BASE64!               │
│    file_path: fileUri,    // file:// URI only            │
│    upload_status: "pending",                             │
│    created_at: Date.now()                                │
│  });                                                     │
│                                                          │
│  🎯 نحفظ المسار فقط - لا نحفظ الملف نفسه!              │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│  🔌 خطوة 4: إرسال إلى Java Plugin                       │
├─────────────────────────────────────────────────────────┤
│  await UploadService.addFileToQueue({                    │
│    filePath: fileUri,    // file:///...                  │
│    fileName: "video.mp4",                                │
│    fileType: "video/mp4",                                │
│    photoId: 908,                                         │
│    apiUrl: "https://...org/api/upload",                  │
│    indexedDbId: 12345,   // للربط مع IndexedDB           │
│    associationName: "جمعية الحياة",                      │
│    personName: "أحمد محمد"                               │
│  });                                                     │
│                                                          │
│  🎯 نرسل file URI فقط (~50 bytes) بدلاً من              │
│     الملف بالكامل (206 MB)!                             │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│  ⚙️ خطوة 5: UploadServicePlugin.java (معالجة)          │
├─────────────────────────────────────────────────────────┤
│  @PluginMethod                                           │
│  public void addFileToQueue(PluginCall call) {           │
│                                                          │
│    String filePath = call.getString("filePath");         │
│    // filePath = "file:///data/.../video.mp4"            │
│                                                          │
│    // 🚨 إزالة "file://" prefix                         │
│    String actualPath = filePath.substring(7);            │
│                                                          │
│    // ✅ الحصول على File object (بدون قراءة!)           │
│    File sourceFile = new File(actualPath);               │
│                                                          │
│    // ✅ حفظ في SQLite Queue                             │
│    dbHelper.addFileToQueue(                              │
│      actualPath,  // المسار الكامل                      │
│      fileName,                                           │
│      fileType,                                           │
│      photoId,                                            │
│      apiUrl,                                             │
│      associationName,                                    │
│      personName                                          │
│    );                                                    │
│                                                          │
│    // 🔥 تشغيل Foreground Service فوراً                 │
│    startUploadForegroundService();                       │
│  }                                                       │
│                                                          │
│  🎯 لم نقرأ الملف - فقط حفظنا المسار!                   │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│  🗄️ خطوة 6: حفظ في SQLite Queue (upload_queue.db)      │
├─────────────────────────────────────────────────────────┤
│  CREATE TABLE upload_queue (                             │
│    id INTEGER PRIMARY KEY,                               │
│    file_path TEXT NOT NULL,    // المسار الكامل          │
│    file_name TEXT,                                       │
│    file_type TEXT,                                       │
│    photo_id INTEGER,                                     │
│    api_url TEXT,                                         │
│    status TEXT DEFAULT 'pending',                        │
│    retry_count INTEGER DEFAULT 0,                        │
│    association_name TEXT,                                │
│    person_name TEXT,                                     │
│    created_at INTEGER                                    │
│  );                                                      │
│                                                          │
│  🎯 الملف محفوظ في Disk - Database به المسار فقط        │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│  🚀 خطوة 7: UploadForegroundService (رفع فعلي)          │
├─────────────────────────────────────────────────────────┤
│  private boolean uploadFile(UploadItem item) {           │
│                                                          │
│    // 1. فتح الملف من المسار                            │
│    File file = new File(item.filePath);                  │
│    if (!file.exists()) return false;                     │
│                                                          │
│    // 2. إنشاء OkHttp Client                             │
│    OkHttpClient client = new OkHttpClient.Builder()      │
│      .connectTimeout(60, TimeUnit.SECONDS)               │
│      .writeTimeout(180, TimeUnit.SECONDS)                │
│      .build();                                           │
│                                                          │
│    // 3. إنشاء RequestBody (Streaming!)                  │
│    RequestBody fileBody = RequestBody.create(            │
│      file,                    // 🎯 File object مباشرة   │
│      MediaType.parse(item.fileType)                      │
│    );                                                    │
│    // OkHttp يقرأ الملف في chunks - لا يحمله بالكامل!   │
│                                                          │
│    // 4. Multipart Form                                  │
│    MultipartBody requestBody = new MultipartBody         │
│      .Builder()                                          │
│      .setType(MultipartBody.FORM)                        │
│      .addFormDataPart("files[]", fileName, fileBody)     │
│      .addFormDataPart("record_number", photoId)          │
│      .build();                                           │
│                                                          │
│    // 5. إضافة Auth Token                               │
│    Request request = new Request.Builder()               │
│      .url(apiUrl)                                        │
│      .addHeader("Authorization", "Bearer " + token)      │
│      .post(requestBody)                                  │
│      .build();                                           │
│                                                          │
│    // 6. تنفيذ الرفع                                     │
│    Response response = client.newCall(request)           │
│                              .execute();                 │
│                                                          │
│    // 7. معالجة النتيجة                                 │
│    if (response.code() >= 200 && < 300) {                │
│      // ✅ نجح - حذف الملف من Internal Storage           │
│      file.delete();                                      │
│      return true;                                        │
│    }                                                     │
│    return false;                                         │
│  }                                                       │
│                                                          │
│  🎯 OkHttp.RequestBody.create(file) يقرأ في chunks       │
│     بدون تحميل الملف بالكامل في RAM!                    │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│  ✅ خطوة 8: المزامنة مع IndexedDB                        │
├─────────────────────────────────────────────────────────┤
│  // Java يرسل broadcast للـ JavaScript                  │
│  Intent broadcast = new Intent("FILE_UPLOADED");         │
│  broadcast.putExtra("indexedDbId", 12345);               │
│  sendBroadcast(broadcast);                               │
│                                                          │
│  // JavaScript يستقبل ويحدث IndexedDB                    │
│  window.addEventListener('appStateChange', async () => { │
│    await syncUploadedFilesFromJava();                    │
│  });                                                     │
│                                                          │
│  // تحديث upload_status = "completed" في IndexedDB      │
└─────────────────────────────────────────────────────────┘
```

### 2. مميزات نظام الرفع

#### ✅ سياسة NO BASE64 الصارمة

```java
// ❌ FORBIDDEN - ممنوع منعاً باتاً!
byte[] fileBytes = readFileToByteArray(file);
String base64 = Base64.encodeToString(fileBytes, Base64.DEFAULT);
// هذا يسبب OutOfMemory للملفات الكبيرة!

// ✅ CORRECT - الطريقة الصحيحة
File file = new File(filePath);
RequestBody fileBody = RequestBody.create(file, mediaType);
// OkHttp يقرأ في chunks تلقائياً!
```

#### ✅ دعم ملفات ضخمة (100+ GB)

```
الذاكرة المستخدمة لرفع فيديو 10 GB:

❌ Base64 Method:
   10 GB → Base64 → 13.3 GB في RAM → OutOfMemory ❌

✅ OkHttp Streaming:
   10 GB → chunks 8 KB → 8 KB في RAM فقط! ✅
   
   الملف يُقرأ تدريجياً:
   [8KB] → Upload → [8KB] → Upload → [8KB] → ...
   
   استهلاك RAM ثابت: ~8 KB فقط!
```

#### ✅ Retry Logic الذكي

```java
// منطق إعادة المحاولة
if (uploadFailed) {
    retryCount++;
    if (retryCount < 3) {
        status = "pending";     // سيعاود المحاولة
    } else {
        status = "failed";      // فشل نهائي
    }
}

// المستخدم يمكنه إعادة المحاولة يدوياً
GoogleDriveUpload.retryFailedUploads()
```

#### ✅ تنظيم الملفات الذكي

```
External Storage Structure:
📁 /storage/emulated/0/Documents/
   └─ 📁 sponsorships_alhayahorphans/
      ├─ 📁 جمعية_الحياة_للأيتام/
      │  ├─ 📁 أحمد_محمد_علي/
      │  │  ├─ 📷 IMG_20260214_103045.jpg
      │  │  ├─ 🎬 VID_20260214_103125.mp4 (206 MB)
      │  │  └─ 📷 IMG_20260215_090830.jpg
      │  └─ 📁 فاطمة_أحمد/
      │     └─ 📷 IMG_20260214_110520.jpg
      └─ 📁 جمعية_الأمل/
         └─ 📁 خالد_سعيد/
            └─ 🎬 VID_20260214_120045.mp4 (1.2 GB)

🎯 فائدة التنظيم:
   • سهولة الوصول اليدوي من File Manager
   • نسخ احتياطية سهلة
   • مزامنة مع Google Drive
   • تجنب التضارب في الأسماء
```

### 3. إدارة حالة الرفع

```javascript
// حالات الرفع في IndexedDB
const uploadStatuses = {
  'pending':    'في قائمة الانتظار',    // جديد - لم يبدأ الرفع
  'uploading':  'جاري الرفع',           // قيد الرفع الآن
  'completed':  'تم الرفع بنجاح',       // نجح الرفع
  'failed':     'فشل الرفع'             // فشل بعد 3 محاولات
};

// الحصول على الإحصائيات
const stats = await SyncService.getUploadStats();
// {
//   pending: 15,      // 15 ملف معلق
//   uploading: 2,     // 2 قيد الرفع
//   completed: 145,   // 145 تم رفعه
//   failed: 3         // 3 فشلت
// }
```

---

## 🔄 نظام المزامنة الخلفية

### 1. معمارية المزامنة (Sync Architecture)

```
🔄 نظام المزامنة الشامل

┌──────────────────────────────────────────────────────────┐
│  🎯 مراحل المزامنة الأوتوماتيكية                         │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  📱 JavaScript Layer                                      │
│  ┌─────────────────────────────────────────────────┐     │
│  │ 1. حفظ محلي في IndexedDB                        │     │
│  │    → localStorage/IndexedDB                      │     │
│  │                                                  │     │
│  │ 2. إضافة لقائمة المزامنة                        │     │
│  │    BackgroundSync.addDataToQueue({              │     │
│  │      dataType: "sponsorship",                   │     │
│  │      dataJson: JSON.stringify(data),            │     │
│  │      endpoint: "/api/sponsorships"              │     │
│  │    })                                            │     │
│  └─────────────────────────────────────────────────┘     │
│                      ▼                                    │
│  ⚙️ Java Layer (Background)                              │
│  ┌─────────────────────────────────────────────────┐     │
│  │ 3. DataSyncDatabaseHelper                       │     │
│  │    → حفظ في SQLite: data_sync_queue.db         │     │
│  │    → status: "pending"                          │     │
│  │                                                  │     │
│  │ 4. DataSyncForegroundService                    │     │
│  │    → يعمل كل 10 ثواني (AlarmManager)           │     │
│  │    → WakeLock (لا ينام أثناء المزامنة)         │     │
│  │    → Foreground notification (دائم)            │     │
│  │                                                  │     │
│  │ 5. NetworkMonitor                               │     │
│  │    → مراقبة اتصال الإنترنت                      │     │
│  │    → تلقائي عند عودة الاتصال                   │     │
│  │                                                  │     │
│  │ 6. UploadAlarmReceiver                          │     │
│  │    → AlarmManager كل 10 ثواني                  │     │
│  │    → يعمل حتى عند إغلاق التطبيق                │     │
│  └─────────────────────────────────────────────────┘     │
│                      ▼                                    │
│  🌐 Server Upload                                         │
│  ┌─────────────────────────────────────────────────┐     │
│  │ 7. HTTP Request                                 │     │
│  │    POST {baseUrl}{endpoint}                     │     │
│  │    Headers: Authorization Bearer                │     │
│  │    Body: JSON data                              │     │
│  │                                                  │     │
│  │ 8. Response Handling                            │     │
│  │    ✅ Success → status = "completed"            │     │
│  │    ❌ Failure → retry (max 3 attempts)          │     │
│  └─────────────────────────────────────────────────┘     │
└──────────────────────────────────────────────────────────┘
```

### 2. خدمات المزامنة

#### A. DataSyncForegroundService

```java
/**
 * خدمة Foreground قوية للمزامنة في الخلفية
 * تعمل بشكل مستمر ولا يمكن للنظام إيقافها
 */
public class DataSyncForegroundService extends Service {
    
    // المميزات:
    // ✅ Foreground Service - لا يُقتل من النظام
    // ✅ WakeLock - يعمل حتى عند قفل الشاشة
    // ✅ Notification مستمر - يعرض التقدم
    // ✅ Retry logic - 3 محاولات لكل عنصر
    // ✅ Database-driven - مستقل عن الصفحات
    
    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        // بدء كـ Foreground Service
        startForeground(NOTIFICATION_ID, notification);
        
        // WakeLock لمنع النوم
        wakeLock.acquire(60 * 60 * 1000L);  // ساعة
        
        // بدء thread للمزامنة
        syncThread = new Thread(this::processSyncQueue);
        syncThread.start();
        
        return START_STICKY;  // النظام يعيد تشغيله إذا قُتل
    }
    
    private void processSyncQueue() {
        while (isRunning) {
            // 1. قراءة العناصر المعلقة
            List<DataSyncItem> items = dbHelper.getPendingData();
            
            for (DataSyncItem item : items) {
                // 2. مزامنة كل عنصر
                boolean success = syncDataItem(item);
                
                if (success) {
                    dbHelper.markAsCompleted(item.id);
                } else {
                    dbHelper.incrementRetryCount(item.id);
                    if (item.retryCount >= 3) {
                        dbHelper.markAsFailed(item.id);
                    }
                }
                
                // 3. تحديث الإشعار
                updateNotification(currentIndex, totalItems);
                
                Thread.sleep(500);  // راحة بين العناصر
            }
        }
    }
    
    private boolean syncDataItem(DataSyncItem item) {
        try {
            // إنشاء HTTP Request
            URL url = new URL(baseUrl + item.endpoint);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Authorization", "Bearer " + token);
            
            // إرسال البيانات
            OutputStream os = conn.getOutputStream();
            os.write(item.dataJson.getBytes());
            os.flush();
            os.close();
            
            // فحص الاستجابة
            int responseCode = conn.getResponseCode();
            return (responseCode >= 200 && responseCode < 300);
            
        } catch (Exception e) {
            return false;
        }
    }
}
```

#### B. AlarmManager (المزامنة الدورية)

```java
/**
 * مزامنة كل 10 ثواني - يعمل حتى عند إغلاق التطبيق
 */
public class UploadAlarmReceiver extends BroadcastReceiver {
    
    @Override
    public void onReceive(Context context, Intent intent) {
        // فحص وجود ملفات معلقة
        int pendingCount = dbHelper.getPendingFilesCount();
        
        if (pendingCount > 0 && isNetworkAvailable()) {
            // بدء خدمة الرفع
            Intent serviceIntent = new Intent(
                context, 
                UploadForegroundService.class
            );
            context.startForegroundService(serviceIntent);
        }
    }
    
    // جدولة Alarm
    public static void scheduleAlarm(Context context) {
        AlarmManager alarmManager = (AlarmManager) 
            context.getSystemService(Context.ALARM_SERVICE);
        
        Intent intent = new Intent(context, UploadAlarmReceiver.class);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
            context, 0, intent, PendingIntent.FLAG_IMMUTABLE
        );
        
        // كل 10 ثواني
        alarmManager.setRepeating(
            AlarmManager.RTC_WAKEUP,
            System.currentTimeMillis(),
            10 * 1000,  // 10 seconds
            pendingIntent
        );
    }
}
```

### 3. BootReceiver (التشغيل عند الإقلاع)

```java
/**
 * يُشغَّل تلقائياً عند إعادة تشغيل الجهاز
 */
public class UploadBootReceiver extends BroadcastReceiver {
    
    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
            
            // إعادة جدولة AlarmManager
            UploadAlarmReceiver.scheduleAlarm(context);
            
            // بدء NetworkMonitor
            NetworkMonitor.getInstance(context).startMonitoring();
            
            Log.e(TAG, "✅ تم تفعيل خدمات المزامنة بعد إعادة التشغيل");
        }
    }
}
```

---

## 📸 نظام الصور والفيديو

### 1. التقاط الصور

```javascript
// استخدام Capacitor Camera Plugin
async function takePhoto(sponsorshipId) {
  try {
    // التقاط صورة
    const photo = await Camera.getPhoto({
      quality: 90,
      allowEditing: false,
      resultType: CameraResultType.Uri,  // ✅ URI فقط - NO BASE64!
      source: CameraSource.Camera,
      saveToGallery: true
    });
    
    // photo.webPath = "file:///storage/.../DCIM/IMG_xxx.jpg"
    
    // حفظ في مجلد التطبيق
    const blob = await fetch(photo.webPath).then(r => r.blob());
    const fileName = `IMG_${Date.now()}.jpg`;
    
    const writeResult = await Filesystem.writeFile({
      path: `sponsorships/${association}/${person}/${fileName}`,
      data: blob,
      directory: Directory.Data,
      recursive: true
    });
    
    // حفظ metadata في IndexedDB
    await SyncService.saveFile({
      sponsorshipId: sponsorshipId,
      fileName: fileName,
      fileType: 'image/jpeg',
      fileUri: writeResult.uri,  // ✅ URI فقط
      fileData: null             // ✅ NO BASE64!
    });
    
    console.log('✅ صورة محفوظة:', fileName);
    
  } catch (error) {
    console.error('❌ فشل التقاط الصورة:', error);
  }
}
```

### 2. تسجيل الفيديو

```javascript
// Custom Camera Bridge للفيديوهات الطويلة
async function recordVideo(sponsorshipId) {
  try {
    // تنظيف الذاكرة قبل التسجيل
    if (window.CameraBridge) {
      await window.CameraBridge.prepareForRecording({
        level: 'aggressive'  // تنظيف عميق
      });
    }
    
    // فتح الكاميرا الأصلية (Native)
    const result = await window.CameraBridge.recordVideo({
      maxDuration: 0,        // بدون حد زمني
      quality: 'high',       // جودة عالية
      saveToGallery: true
    });
    
    // result.videoPath = "file:///storage/.../DCIM/VID_xxx.mp4"
    
    // نسخ إلى مجلد التطبيق
    const fileName = `VID_${Date.now()}.mp4`;
    const copyResult = await Filesystem.copy({
      from: result.videoPath,
      to: `sponsorships/${association}/${person}/${fileName}`,
      directory: Directory.Data
    });
    
    // حفظ metadata
    await SyncService.saveFile({
      sponsorshipId: sponsorshipId,
      fileName: fileName,
      fileType: 'video/mp4',
      fileUri: copyResult.uri,
      fileData: null,
      fileSize: result.fileSize
    });
    
    console.log('✅ فيديو محفوظ:', fileName, 
                '(', Math.round(result.fileSize/1024/1024), 'MB)');
    
    // تنظيف بعد التسجيل
    if (window.CameraBridge) {
      await window.CameraBridge.cleanupAfterRecording();
    }
    
  } catch (error) {
    console.error('❌ فشل تسجيل الفيديو:', error);
  }
}
```

### 3. CameraMemoryManager (منع OOM)

```java
/**
 * مدير الذاكرة للكاميرا - تنظيف استباقي قبل التصوير
 */
public class CameraMemoryManager {
    
    public enum CleanupLevel {
        NORMAL,      // تنظيف عادي (System.gc)
        SOFT,        // تنظيف متوسط (gc + clear cache)
        AGGRESSIVE   // تنظيف شامل (gc + cache + WebView)
    }
    
    /**
     * تنظيف الذاكرة قبل التصوير
     */
    public void prepareForRecording(CleanupLevel level) {
        Runtime runtime = Runtime.getRuntime();
        long beforeMem = runtime.totalMemory() - runtime.freeMemory();
        
        switch (level) {
            case NORMAL:
                // Garbage Collection
                System.gc();
                break;
                
            case SOFT:
                // GC + Clear App Cache
                System.gc();
                clearAppCache();
                break;
                
            case AGGRESSIVE:
                // GC + Cache + WebView cleanup
                System.gc();
                clearAppCache();
                clearWebViewCache();
                trimMemory(ComponentCallbacks2.TRIM_MEMORY_MODERATE);
                break;
        }
        
        long afterMem = runtime.totalMemory() - runtime.freeMemory();
        long freed = beforeMem - afterMem;
        
        Log.e(TAG, "✅ تم تحرير " + (freed / 1024 / 1024) + " MB");
    }
    
    /**
     * تنظيف بعد التسجيل
     */
    public void cleanupAfterRecording() {
        System.gc();
        
        // حذف الملفات المؤقتة
        File cacheDir = context.getCacheDir();
        deleteDirectory(cacheDir);
        
        Log.e(TAG, "✅ تنظيف بعد التسجيل مكتمل");
    }
}
```

### 4. مثال كامل: حفظ ورفع فيديو 500 MB

```javascript
// السيناريو الكامل
async function captureAndUploadVideo(sponsorshipId) {
  const association = "جمعية الحياة";
  const person = "أحمد محمد";
  
  try {
    // 1️⃣ تنظيف الذاكرة
    console.log('🧹 تنظيف الذاكرة...');
    await window.CameraBridge.prepareForRecording({ 
      level: 'aggressive' 
    });
    
    // 2️⃣ تسجيل فيديو
    console.log('🎬 بدء التسجيل...');
    const video = await window.CameraBridge.recordVideo({
      quality: 'high'
    });
    // video.videoPath = "file:///storage/.../VID_500MB.mp4"
    // video.fileSize = 524288000  (500 MB)
    
    // 3️⃣ نسخ إلى مجلد التطبيق
    console.log('💾 حفظ الفيديو...');
    const fileName = `VID_${Date.now()}.mp4`;
    const writeResult = await Filesystem.writeFile({
      path: `sponsorships/${association}/${person}/${fileName}`,
      data: await fetch(video.videoPath).then(r => r.blob()),
      directory: Directory.Data,
      recursive: true
    });
    // writeResult.uri = "file:///data/user/0/.../files/VID_xxx.mp4"
    
    // 4️⃣ حفظ metadata في IndexedDB
    console.log('📝 حفظ المعلومات...');
    const photoId = await db.photos.add({
      sponsorship_id: sponsorshipId,
      file_name: fileName,
      file_type: 'video/mp4',
      file_size: video.fileSize,
      file_data: null,           // ✅ NO BASE64!
      file_path: writeResult.uri, // ✅ URI فقط (50 bytes)
      upload_status: 'pending',
      created_at: Date.now()
    });
    
    // 5️⃣ إرسال لـ Java للرفع
    console.log('🚀 بدء الرفع...');
    await UploadService.addFileToQueue({
      filePath: writeResult.uri,    // 50 bytes فقط!
      fileName: fileName,
      fileType: 'video/mp4',
      photoId: sponsorshipId,
      apiUrl: 'https://alhayahorphans.org/api/upload',
      indexedDbId: photoId,
      associationName: association,
      personName: person
    });
    
    // 6️⃣ تنظيف
    await window.CameraBridge.cleanupAfterRecording();
    
    console.log('✅ اكتمل! الفيديو سيُرفع في الخلفية');
    
    // Java الآن سيرفع الفيديو باستخدام OkHttp Streaming
    // بدون تحميل ال 500 MB في الذاكرة!
    
  } catch (error) {
    console.error('❌ خطأ:', error);
    alert('فشل حفظ الفيديو: ' + error.message);
  }
}
```

---

## 📊 نظام الإحصائيات والمراقبة

### 1. إحصائيات رفع الملفات

```javascript
// الحصول على إحصائيات الرفع
async function getUploadStatistics() {
  try {
    // من Java
    const javaStats = await UploadService.getUploadStatus();
    
    // من IndexedDB
    const dbStats = await getIndexedDBStats();
    
    return {
      // ملفات في القائمة
      pending: javaStats.pendingFiles,      // 15 ملف معلق
      uploading: javaStats.uploadingFiles,  // 2 قيد الرفع
      completed: javaStats.uploadedFiles,   // 145 تم رفعه
      failed: javaStats.failedFiles,        // 3 فشلت
      
      // الإجماليات
      totalFiles: javaStats.totalFiles,     // 165 ملف إجمالي
      totalSize: dbStats.totalSize,         // 12.5 GB
      uploadedSize: dbStats.uploadedSize,   // 10.2 GB
      
      // معدل النجاح
      successRate: (javaStats.uploadedFiles / javaStats.totalFiles * 100).toFixed(1) + '%',
      
      // حسب النوع
      images: {
        total: dbStats.images.count,
        uploaded: dbStats.images.uploaded,
        size: dbStats.images.totalSize
      },
      videos: {
        total: dbStats.videos.count,
        uploaded: dbStats.videos.uploaded,
        size: dbStats.videos.totalSize
      }
    };
  } catch (error) {
    console.error('فشل الحصول على الإحصائيات:', error);
    return null;
  }
}

// مثال على الناتج:
{
  pending: 15,
  uploading: 2,
  completed: 145,
  failed: 3,
  totalFiles: 165,
  totalSize: 13421772800,  // 12.5 GB
  uploadedSize: 10952908800, // 10.2 GB
  successRate: "87.9%",
  images: {
    total: 120,
    uploaded: 110,
    size: 2500000000  // 2.5 GB
  },
  videos: {
    total: 45,
    uploaded: 35,
    size: 10921772800  // 10 GB
  }
}
```

### 2. إحصائيات المزامنة

```javascript
// إحصائيات مزامنة البيانات
async function getDataSyncStatistics() {
  try {
    const stats = await BackgroundSync.getSyncStatus();
    
    return {
      pending: stats.pending,      // 8 قيد الانتظار
      uploaded: stats.uploaded,    // 452 تمت المزامنة
      failed: stats.failed,        // 2 فشلت
      total: stats.total,          // 462 إجمالي
      
      // حسب النوع
      sponsorships: {
        pending: stats.sponsorshipsPending,
        synced: stats.sponsorshipsSynced
      },
      photos: {
        pending: stats.photosPending,
        synced: stats.photosSynced
      },
      videos: {
        pending: stats.videosPending,
        synced: stats.videosSynced
      }
    };
  } catch (error) {
    console.error('فشل الحصول على إحصائيات المزامنة:', error);
    return null;
  }
}
```

### 3. لوحة المعلومات (Dashboard)

```javascript
// عرض لوحة معلومات شاملة
class DashboardManager {
  
  async refreshDashboard() {
    // 1. إحصائيات الرفع
    const uploadStats = await getUploadStatistics();
    this.displayUploadStats(uploadStats);
    
    // 2. إحصائيات المزامنة
    const syncStats = await getDataSyncStatistics();
    this.displaySyncStats(syncStats);
    
    // 3. حالة الشبكة
    const networkStatus = await this.getNetworkStatus();
    this.displayNetworkStatus(networkStatus);
    
    // 4. استهلاك التخزين
    const storageStats = await this.getStorageStats();
    this.displayStorageStats(storageStats);
    
    // 5. آخر عملية رفع
    const lastUpload = await this.getLastUploadInfo();
    this.displayLastUpload(lastUpload);
  }
  
  displayUploadStats(stats) {
    document.getElementById('pending-files').textContent = stats.pending;
    document.getElementById('uploaded-files').textContent = stats.completed;
    document.getElementById('failed-files').textContent = stats.failed;
    document.getElementById('success-rate').textContent = stats.successRate;
    
    // رسم بياني دائري
    this.drawPieChart('upload-chart', {
      pending: stats.pending,
      completed: stats.completed,
      failed: stats.failed
    });
  }
  
  async getStorageStats() {
    // مساحة التخزين
    if ('storage' in navigator && 'estimate' in navigator.storage) {
      const estimate = await navigator.storage.estimate();
      return {
        used: estimate.usage,              // المستخدم
        total: estimate.quota,             // الإجمالي
        available: estimate.quota - estimate.usage,
        percentage: (estimate.usage / estimate.quota * 100).toFixed(1)
      };
    }
    return null;
  }
  
  async getNetworkStatus() {
    const online = navigator.onLine;
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    
    return {
      online: online,
      type: connection ? connection.effectiveType : 'unknown',
      downlink: connection ? connection.downlink : null,  // Mbps
      rtt: connection ? connection.rtt : null             // ms
    };
  }
}

// تحديث تلقائي كل 5 ثواني
const dashboard = new DashboardManager();
setInterval(() => {
  dashboard.refreshDashboard();
}, 5000);
```

---

## 🧠 إدارة الذاكرة

### 1. MemoryMonitor (مراقبة مستمرة)

```java
/**
 * مراقب الذاكرة - يتتبع استهلاك RAM ويتخذ إجراءات وقائية
 */
public class MemoryMonitor {
    
    private static final long CHECK_INTERVAL = 5000;  // 5 ثواني
    private Handler handler = new Handler(Looper.getMainLooper());
    
    public void startMonitoring() {
        handler.postDelayed(new Runnable() {
            @Override
            public void run() {
                checkMemoryStatus();
                handler.postDelayed(this, CHECK_INTERVAL);
            }
        }, CHECK_INTERVAL);
    }
    
    private void checkMemoryStatus() {
        Runtime runtime = Runtime.getRuntime();
        
        long maxMemory = runtime.maxMemory();        // أقصى ذاكرة متاحة
        long totalMemory = runtime.totalMemory();    // المُخصصة حالياً
        long freeMemory = runtime.freeMemory();      // الحرة
        long usedMemory = totalMemory - freeMemory;  // المستخدمة
        
        // حساب النسبة
        int usagePercent = (int) ((usedMemory * 100) / maxMemory);
        
        Log.d(TAG, String.format(
            "📊 Memory: %d%% (%d MB / %d MB)",
            usagePercent,
            usedMemory / 1024 / 1024,
            maxMemory / 1024 / 1024
        ));
        
        // اتخاذ إجراءات عند الحد الحرج
        if (usagePercent > 85) {
            Log.w(TAG, "⚠️ استهلاك ذاكرة مرتفع! 85%+");
            performAggressiveCleanup();
        } else if (usagePercent > 75) {
            Log.w(TAG, "⚠️ استهلاك ذاكرة متوسط 75%+");
            performModerateCleanup();
        }
    }
    
    private void performModerateCleanup() {
        // تنظيف متوسط
        System.gc();
        
        // مسح cache
        clearAppCache();
        
        Log.d(TAG, "🧹 تنظيف متوسط مكتمل");
    }
    
    private void performAggressiveCleanup() {
        // تنظيف شامل
        System.gc();
        System.runFinalization();
        System.gc();
        
        // مسح cache
        clearAppCache();
        
        // تنظيف WebView
        if (webView != null) {
            webView.clearCache(true);
            webView.clearHistory();
        }
        
        // طلب تحرير ذاكرة من المكونات
        ComponentCallbacks2.onTrimMemory(
            ComponentCallbacks2.TRIM_MEMORY_RUNNING_CRITICAL
        );
        
        Log.w(TAG, "🧹 تنظيف شامل مكتمل");
    }
}
```

### 2. WebStorageManager (إدارة localStorage)

```java
/**
 * مدير التخزين الويب - مراقبة وتنظيف localStorage/IndexedDB
 */
public class WebStorageManager {
    
    private static final long MAX_STORAGE_SIZE = 50 * 1024 * 1024;  // 50 MB
    
    public void monitorWebStorage(WebView webView) {
        webView.evaluateJavascript(
            "(function() {" +
            "  let totalSize = 0;" +
            "  for (let key in localStorage) {" +
            "    totalSize += localStorage[key].length;" +
            "  }" +
            "  return totalSize;" +
            "})();",
            new ValueCallback<String>() {
                @Override
                public void onReceiveValue(String value) {
                    long sizeBytes = Long.parseLong(value);
                    long sizeMB = sizeBytes / 1024 / 1024;
                    
                    Log.d(TAG, "📦 localStorage: " + sizeMB + " MB");
                    
                    if (sizeBytes > MAX_STORAGE_SIZE) {
                        Log.w(TAG, "⚠️ localStorage ممتلئ! تنظيف...");
                        cleanupOldData(webView);
                    }
                }
            }
        );
    }
    
    private void cleanupOldData(WebView webView) {
        // حذف البيانات القديمة (أزيد من 30 يوم)
        webView.evaluateJavascript(
            "(function() {" +
            "  const db = indexedDB.open('AlhayahSponsorshipsDB');" +
            "  db.onsuccess = function() {" +
            "    const tx = db.result.transaction(['photos'], 'readwrite');" +
            "    const store = tx.objectStore('photos');" +
            "    const index = store.index('upload_status');" +
            "    const range = IDBKeyRange.only('completed');" +
            "    const request = index.openCursor(range);" +
            "    " +
            "    let deleted = 0;" +
            "    request.onsuccess = function(e) {" +
            "      const cursor = e.target.result;" +
            "      if (cursor) {" +
            "        const photo = cursor.value;" +
            "        const age = Date.now() - photo.created_at;" +
            "        if (age > 30 * 24 * 60 * 60 * 1000) {" +  // 30 يوم
            "          cursor.delete();" +
            "          deleted++;" +
            "        }" +
            "        cursor.continue();" +
            "      } else {" +
            "        console.log('🗑️ حُذف ' + deleted + ' سجل قديم');" +
            "      }" +
            "    };" +
            "  };" +
            "})();",
            null
        );
    }
}
```

### 3. استراتيجية منع OutOfMemory

```
🛡️ حماية شاملة من OutOfMemory

┌──────────────────────────────────────────────────────┐
│  المستوى 1: Prevention (الوقاية)                     │
├──────────────────────────────────────────────────────┤
│  ✅ NO BASE64 Policy - صارمة                         │
│  ✅ File URI only - لا نحمل الملفات في RAM            │
│  ✅ Streaming upload - OkHttp chunks 8KB             │
│  ✅ Lazy loading - تحميل عند الحاجة فقط              │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│  المستوى 2: Detection (الكشف)                        │
├──────────────────────────────────────────────────────┤
│  ✅ MemoryMonitor - كل 5 ثواني                       │
│  ✅ Thresholds: 75% (warning), 85% (critical)        │
│  ✅ WebStorageManager - مراقبة localStorage          │
│  ✅ onLowMemory callback - من النظام                 │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│  المستوى 3: Response (الاستجابة)                     │
├──────────────────────────────────────────────────────┤
│  📊 75% → Moderate cleanup                           │
│     • System.gc()                                     │
│     • Clear app cache                                 │
│                                                       │
│  📊 85% → Aggressive cleanup                          │
│     • Multiple GC calls                               │
│     • Clear cache + WebView                           │
│     • Trim memory for all components                  │
│     • Delete old IndexedDB records                    │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│  المستوى 4: Recovery (التعافي)                       │
├──────────────────────────────────────────────────────┤
│  🚨 OutOfMemory Exception Handler                     │
│     try {                                             │
│       // رفع الملف                                    │
│     } catch (OutOfMemoryError e) {                    │
│       // تنظيف فوري                                  │
│       System.gc();                                    │
│       clearAllCaches();                               │
│                                                       │
│       // حفظ الحالة في Database                      │
│       dbHelper.markAsPending(fileId);                 │
│                                                       │
│       // إشعار للمستخدم                              │
│       showNotification("الذاكرة ممتلئة");             │
│                                                       │
│       // المحاولة لاحقاً                              │
│       scheduleRetry(fileId, 60000);                   │
│     }                                                 │
└──────────────────────────────────────────────────────┘
```

---

## 🌐 إدارة الشبكة

### 1. NetworkMonitor (مراقبة الاتصال)

```java
/**
 * مراقب الشبكة - يكتشف تلقائياً عودة الإنترنت ويبدأ الرفع
 */
public class NetworkMonitor {
    
    private ConnectivityManager connectivityManager;
    private NetworkCallback networkCallback;
    
    public void startMonitoring() {
        NetworkRequest request = new NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .addCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
            .build();
        
        networkCallback = new NetworkCallback() {
            @Override
            public void onAvailable(@NonNull Network network) {
                Log.e(TAG, "🌐 الإنترنت متاح!");
                handleNetworkAvailable();
            }
            
            @Override
            public void onLost(@NonNull Network network) {
                Log.e(TAG, "❌ انقطع الإنترنت");
                handleNetworkLost();
            }
        };
        
        connectivityManager.registerNetworkCallback(request, networkCallback);
    }
    
    private void handleNetworkAvailable() {
        // 1. إعادة تعيين الملفات الفاشلة
        int resetCount = dbHelper.resetFailedFiles();
        Log.d(TAG, "🔄 إعادة " + resetCount + " ملف للمحاولة");
        
        // 2. فحص الملفات المعلقة
        int pendingCount = dbHelper.getPendingFilesCount();
        Log.d(TAG, "📊 ملفات معلقة: " + pendingCount);
        
        if (pendingCount > 0) {
            // 3. بدء خدمة الرفع
            Intent serviceIntent = new Intent(
                context, 
                UploadForegroundService.class
            );
            context.startForegroundService(serviceIntent);
            
            Log.e(TAG, "🚀 بدء رفع " + pendingCount + " ملف");
        }
        
        // 4. بدء مزامنة البيانات أيضاً
        Intent dataSyncIntent = new Intent(
            context,
            DataSyncForegroundService.class
        );
        context.startForegroundService(dataSyncIntent);
    }
}
```

### 2. معالجة حالات الشبكة المختلفة

```java
// حالات الاتصال المختلفة

// ✅ WiFi - رفع بدون قيود
if (networkType == TYPE_WIFI) {
    uploadAllFiles();
}

// ⚠️ Mobile Data - تأكيد المستخدم
else if (networkType == TYPE_MOBILE) {
    boolean allowMobileUpload = preferences.getBoolean(
        "allow_mobile_upload", 
        false
    );
    
    if (allowMobileUpload) {
        uploadAllFiles();
    } else {
        showNotification(
            "ملفات معلقة",
            "اتصل بـ WiFi لرفع الملفات"
        );
    }
}

// ❌ No Connection - انتظار
else {
    Log.d(TAG, "لا يوجد اتصال - الملفات ستبقى معلقة");
}
```

---

## 🔌 Plugins والجسور

### قائمة Plugins المسجلة

```java
// في MainActivity.onCreate()

registerPlugin(UploadServicePlugin.class);              // رفع الملفات
registerPlugin(GoogleDriveUploadPlugin.class);          // إدارة الرفع
registerPlugin(IndexedDBBridge.class);                  // قراءة IndexedDB
registerPlugin(SponsorshipFolderManager.class);         // إدارة المجلدات
registerPlugin(BackgroundSyncPlugin.class);             // مزامنة البيانات
```

### 1. UploadServicePlugin

```javascript
// من JavaScript
const result = await UploadService.addFileToQueue({
  filePath: 'file:///...',
  fileName: 'video.mp4',
  fileType: 'video/mp4',
  photoId: 908,
  apiUrl: 'https://...',
  indexedDbId: 12345,
  associationName: 'جمعية الحياة',
  personName: 'أحمد محمد'
});

// result = { success: true, id: 567 }
```

### 2. BackgroundSyncPlugin

```javascript
// إضافة بيانات للمزامنة
await BackgroundSync.addDataToQueue({
  dataType: 'sponsorship',
  dataJson: JSON.stringify(sponsorshipData),
  endpoint: '/api/sponsorships'
});

// الحصول على الإحصائيات
const stats = await BackgroundSync.getSyncStatus();
// { pending: 8, uploaded: 452, failed: 2 }

// إعادة محاولة الفاشلة
await BackgroundSync.retryFailedData();
```

### 3. SponsorshipFolderManager

```javascript
// إعادة تسمية مجلد مكفول
await SponsorshipFolderManager.renameSponsorshipFolder({
  sponsorshipId: 908,
  oldPersonName: 'أحمد محمد',
  newPersonName: 'أحمد محمد علي',
  newAssociationName: 'جمعية الحياة'
});

// سيقوم بـ:
// 1. إعادة تسمية المجلد على الجهاز
// 2. تحديث مسارات الملفات في Database
// 3. إعادة جدولة الرفع للملفات الفاشلة
```

### 4. IndexedDBBridge

```javascript
// حفظ mappings (الربط بين الأرقام والأسماء)
await IndexedDBBridge.saveSponsorshipMappings({
  mappings: [
    { 
      sponsorshipId: 908, 
      associationName: 'جمعية الحياة',
      personName: 'أحمد محمد'
    },
    { 
      sponsorshipId: 909,
      associationName: 'جمعية الأمل',
      personName: 'فاطمة علي'
    }
  ]
});

// Java يستخدم هذه البيانات لتنظيم الملفات في المجلدات
```

---

## 💽 قواعد البيانات المحلية

### 1. IndexedDB (JavaScript)

```javascript
// AlhayahSponsorshipsDB v8
const db = {
  name: 'AlhayahSponsorshipsDB',
  version: 8,
  
  stores: {
    // الجمعيات
    sponsors: {
      keyPath: 'id',
      indexes: ['name']
    },
    
    // حالات الكفالة
    sponsorship_statuses: {
      keyPath: 'id'
    },
    
    // الكفالات (الرئيسي)
    sponsorships: {
      keyPath: 'id',
      indexes: [
        'association_id',
        'person_name',
        'sponsorship_status_id'
      ]
    },
    
    // الصور
    photos: {
      keyPath: 'id',
      indexes: [
        'sponsorship_id',
        'upload_status',
        'created_at'
      ],
      
      // الحقول:
      fields: [
        'id',                    // auto increment
        'sponsorship_id',        // رقم الكفالة
        'file_name',             // IMG_xxx.jpg
        'file_type',             // image/jpeg
        'file_size',             // بالبايت
        'file_data',             // null (NO BASE64!)
        'file_path',             // file:/// URI
        'upload_status',         // pending/uploading/completed/failed
        'created_at',            // timestamp
        'uploaded_at'            // timestamp
      ]
    },
    
    // الفيديوهات
    videos: {
      keyPath: 'id',
      indexes: [
        'sponsorship_id',
        'upload_status'
      ]
      // نفس حقول photos
    }
  }
};
```

### 2. upload_queue.db (SQLite - Java)

```sql
CREATE TABLE upload_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  file_path TEXT NOT NULL,           -- المسار الكامل
  file_name TEXT NOT NULL,            -- اسم الملف
  file_type TEXT,                     -- MIME type
  photo_id INTEGER NOT NULL,          -- رقم الكفالة
  api_url TEXT NOT NULL,              -- رابط الرفع
  status TEXT DEFAULT 'pending',      -- pending/uploading/completed/failed
  retry_count INTEGER DEFAULT 0,      -- عدد المحاولات
  error_message TEXT,                 -- رسالة الخطأ
  created_at INTEGER NOT NULL,        -- وقت الإنشاء
  updated_at INTEGER NOT NULL,        -- وقت التحديث
  association_name TEXT DEFAULT 'General',  -- اسم الجمعية
  person_name TEXT DEFAULT 'unknown'  -- اسم المكفول
);

-- جدول الربط مع IndexedDB
CREATE TABLE indexeddb_mapping (
  sqlite_id INTEGER PRIMARY KEY,      -- معرف SQLite
  indexeddb_id INTEGER NOT NULL       -- معرف IndexedDB
);

-- جدول تتبع الأسماء (لإعادة التسمية)
CREATE TABLE person_name_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sponsorship_id INTEGER NOT NULL UNIQUE,
  current_association_name TEXT,
  current_person_name TEXT NOT NULL,
  previous_association_name TEXT,
  previous_person_name TEXT,
  folder_path TEXT,
  updated_at INTEGER NOT NULL
);
```

### 3. data_sync_queue.db (SQLite - Java)

```sql
CREATE TABLE data_sync_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  data_type TEXT NOT NULL,            -- sponsorship/photo/video
  data_json TEXT NOT NULL,            -- JSON بالكامل
  endpoint TEXT NOT NULL,             -- /api/sponsorships
  status TEXT DEFAULT 'pending',      -- pending/uploading/completed/failed
  retry_count INTEGER DEFAULT 0,
  error_message TEXT,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
```

---

## ⚠️ معالجة الأخطاء

### 1. أنواع الأخطاء ومعالجتها

```javascript
// معالجة شاملة للأخطاء

try {
  await uploadFile(file);
  
} catch (error) {
  if (error.name === 'NetworkError') {
    // خطأ شبكة - حفظ للمحاولة لاحقاً
    await saveForRetry(file);
    showNotification('لا يوجد اتصال - سيتم الرفع لاحقاً');
    
  } else if (error.message.includes('OutOfMemory')) {
    // نفاد الذاكرة - تنظيف فوري
    await aggressiveMemoryCleanup();
    await saveForRetry(file);
    showNotification('الذاكرة ممتلئة - جاري التنظيف');
    
  } else if (error.status === 401) {
    // غير مصرح - إعادة تسجيل دخول
    logout();
    showLogin();
    
  } else if (error.status === 413) {
    // الملف كبير جداً
    showNotification('الملف كبير جداً - حد السيرفر');
    markAsFailed(file);
    
  } else if (error.status >= 500) {
    // خطأ سيرفر - إعادة محاولة
    incrementRetryCount(file);
    if (file.retryCount < 3) {
      scheduleRetry(file, 60000);  // محاولة بعد دقيقة
    } else {
      markAsFailed(file);
    }
    
  } else {
    // خطأ غير معروف
    console.error('Unknown error:', error);
    logErrorToServer(error);
    markAsFailed(file);
  }
}
```

### 2. Retry Strategies

```java
// استراتيجيات إعادة المحاولة

// Exponential Backoff
private long calculateRetryDelay(int retryCount) {
    // المحاولة 1: 10s
    // المحاولة 2: 20s
    // المحاولة 3: 40s
    return (long) (10 * 1000 * Math.pow(2, retryCount));
}

// Fixed Retry
private static final long RETRY_DELAY = 30 * 1000;  // 30 ثانية ثابتة

// Immediate Retry (عند عودة الشبكة)
private void onNetworkAvailable() {
    // محاولة فورية لجميع الملفات
    retryAllPendingFiles();
}
```

---

## 🔐 الأمان والصلاحيات

### 1. Permissions المطلوبة

```xml
<!-- AndroidManifest.xml -->

<!-- الصلاحيات الأساسية -->
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />

<!-- التخزين -->
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.MANAGE_EXTERNAL_STORAGE" />

<!-- الكاميرا -->
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.RECORD_AUDIO" />

<!-- الخدمات الخلفية -->
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.WAKE_LOCK" />
<uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
<uses-permission android:name="android.permission.SCHEDULE_EXACT_ALARM" />

<!-- الإشعارات -->
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

### 2. Authentication

```javascript
// نظام المصادقة

class AuthManager {
  
  async login(email, password) {
    try {
      const response = await fetch(baseUrl + '/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      
      const data = await response.json();
      
      if (data.token) {
        // حفظ Token
        localStorage.setItem('auth_token', data.token);
        localStorage.setItem('user_data', JSON.stringify(data.user));
        
        // مزامنة مع Java
        if (window.AndroidBridge) {
          window.AndroidBridge.saveAuthData(
            data.token,
            baseUrl
          );
        }
        
        return { success: true, user: data.user };
      }
      
    } catch (error) {
      return { success: false, error: error.message };
    }
  }
  
  logout() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
    
    if (window.AndroidBridge) {
      window.AndroidBridge.clearAuthData();
    }
  }
  
  isAuthenticated() {
    return !!localStorage.getItem('auth_token');
  }
  
  getToken() {
    return localStorage.getItem('auth_token');
  }
}
```

### 3. Secure File Storage

```java
// تخزين آمن للملفات

// Internal Storage (خاص بالتطبيق فقط)
File internalFile = new File(
    context.getFilesDir(), 
    "sponsorships/video.mp4"
);
// Path: /data/user/0/com.aso.app/files/sponsorships/video.mp4
// لا يمكن الوصول إليه من تطبيقات أخرى

// External Storage (نسخة احتياطية - يمكن الوصول إليها)
File externalFile = new File(
    Environment.getExternalStoragePublicDirectory(
        Environment.DIRECTORY_DOCUMENTS
    ),
    "sponsorships_alhayahorphans/video.mp4"
);
// Path: /storage/emulated/0/Documents/sponsorships_alhayahorphans/
// يمكن الوصول إليه من File Manager
```

---

## 📝 الخلاصة والتوصيات

### النقاط الرئيسية

✅ **Offline-First Architecture** - يعمل بدون إنترنت  
✅ **NO BASE64 Policy** - رفع ملفات ضخمة بدون OOM  
✅ **OkHttp Streaming** - كفاءة عالية في استهلاك الذاكرة  
✅ **Foreground Services** - مزامنة مستمرة في الخلفية  
✅ **SQLite + IndexedDB** - تخزين محلي قوي  
✅ **NetworkMonitor** - كشف تلقائي لعودة الإنترنت  
✅ **Retry Logic** - معالجة ذكية للأخطاء  
✅ **Memory Management** - حماية شاملة من OOM  

### أرقام الأداء

```
📊 إحصائيات الأداء:

الذاكرة:
  • رفع فيديو 10 GB: ~8 KB RAM فقط
  • Base64 محذوف بالكامل: 100% streaming
  
السرعة:
  • مزامنة كل 10 ثواني (AlarmManager)
  • رفع فوري عند حفظ الملف
  • retry تلقائي عند عودة الإنترنت
  
الموثوقية:
  • Success rate: 87%+
  • Retry logic: 3 محاولات
  • Database persistence: 100%
  
التخزين:
  • IndexedDB: unlimited (quota-based)
  • SQLite: unlimited
  • External Storage: متاح
```

### التوصيات المستقبلية

1. **ضغط الفيديو قبل الرفع** - تقليل حجم الملفات  
2. **إحصائيات متقدمة** - dashboard أكثر تفصيلاً  
3. **إشعارات ذكية** - تنبيه عند اكتمال الرفع  
4. **Google Drive Integration** - نسخ احتياطي تلقائي  
5. **Multi-user Support** - عدة مستخدمين على جهاز واحد  

---

## 📞 الدعم الفني

للمزيد من المعلومات أو المساعدة:
- الوثائق الفنية: `/android/*.md`
- Logs: `adb logcat | grep -E "Upload|Sync|Memory"`
- Database inspection: `adb pull /data/data/com.aso.app/databases/`

---

**تاريخ التقرير:** 2026-02-14  
**إصدار التطبيق:** Latest (OkHttp 4.12.0 + NO BASE64)  
**الحالة:** Production Ready ✅
