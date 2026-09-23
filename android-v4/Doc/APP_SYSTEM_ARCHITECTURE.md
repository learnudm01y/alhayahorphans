# المواصفات التقنية التفصيلية - نظام الحياة (android-v3)

---

## 1. وحدة التسجيل المتقدم (Registration Module)

### الوظيفة: حفظ سجل جديد (Submit Registration)
- **File:** `registration.js` / `MobileRegistrationController.php`
- **Class:** `RegApp` (JS Object) / `MobileRegistrationController` (Laravel)
- **Method:** `submitForm()` (JS) → `store(Request $request)` (PHP)
- **API:** `POST /api/mobile/registration/store`
- **Database:** `data`, `re_people`, `dead_people`, `guardian_bank_accounts`, `additional_deceased`, `attachments`.
- **Input:** كائن JSON ضخم يحتوي على (بيانات المعيل، مصفوفة أفراد الأسرة، مصفوفة الحسابات البنكية، بيانات الوفاة، قائمة المرفقات المؤقتة).
- **Output:** `JSON {success: true, file_id_number: "XXXXXX"}`
- **Error Handling:** 
    - `Try-Catch` شامل في الـ API مع `DB::rollBack()` في حال فشل أي جدول.
    - التحقق من التوكن (401).
    - التحقق من صحة البيانات (422 Validator).
- **Dependencies:** `TokenInterceptor.js`, `SweetAlert2`, `Chunked Upload Service`.

---

## 2. محرك المزامنة الخلفي (Background Sync Engine)

### الوظيفة: مزامنة الكفالات (Sync Sponsorships)
- **File:** `SponsorshipSyncWorker.java`
- **Class:** `SponsorshipSyncWorker` (Extends `Worker`)
- **Method:** `doWork()` → `fetchUrl()` → `syncRelatedTables()`
- **API:** `GET /api/mobile/sync/sponsorships`
- **Database:** SQLite `sponsorships_data.db` (Table: `sponsorships`).
- **Input:** `auth_token`, `last_sync` (Timestamp), `page_number`.
- **Output:** `Result.success()` أو `Result.retry()` مع تخزين البيانات في SQLite.
- **Error Handling:** 
    - التعامل مع انقطاع الشبكة عبر `NetworkType.CONNECTED` في `WorkRequest`.
    - معالجة الـ JSON Errors لتجنب توقف المزامنة.
- **Dependencies:** `HttpURLConnection`, `SponsorshipsDatabaseHelper`, `ApiConfig`.

---

## 3. نظام الرفع المجزأ (Chunked Upload System)

### الوظيفة: رفع المرفقات (Upload Attachment)
- **File:** `UploadServicePlugin.java` / `ChunkedUploadWorker.java`
- **Class:** `UploadServicePlugin` / `ChunkedUploadWorker`
- **Method:** `uploadFile(PluginCall call)` → `enqueueWorker()` → `doWork()`
- **API:** `POST /api/mobile/registration/upload-chunk`
- **Database:** `upload_tasks.db` (Table: `pending_uploads`).
- **Input:** `filePath`, `fileName`, `fileIdNumber`.
- **Output:** `temp_path` (بعد اكتمال آخر جزء).
- **Error Handling:** 
    - إعادة المحاولة التلقائية (Exponential Backoff).
    - التحقق من سلامة الأجزاء (Checksum/Size validation).
- **Dependencies:** `Capacitor Filesystem`, `WorkManager`, `FormData`.

---

## 4. البحث المحلي السريع (Local Search System)

### الوظيفة: البحث بالهوية (Identity Lookup)
- **File:** `BackgroundSyncPlugin.java` / `RelatedDataDatabaseHelper.java`
- **Class:** `BackgroundSyncPlugin`
- **Method:** `getDataByIdNumber(PluginCall call)` → `db.getDataByIdNumber(id)`
- **API:** لا يوجد (Offline Only).
- **Database:** `related_data.db` (Table: `data_table`).
- **Input:** `idNumber` (9 digits).
- **Output:** `JSON Object` يحتوي على كافة بيانات السجل المحمل مسبقاً.
- **Error Handling:** إرجاع `found: false` في حال عدم وجود السجل بدلاً من الانهيار.
- **Dependencies:** `SQLite Indexing`, `JSBridge`.

---

## 5. خدمة معرف المتصل (Caller ID Service)

### الوظيفة: كشف بيانات المتصل (Caller Info Display)
- **File:** `CallerInfoService.java` / `IncomingCallReceiver.java`
- **Class:** `CallerInfoService`
- **Method:** `onStartCommand()` → `showOverlay()`
- **API:** لا يوجد (تعتمد على قاعدة البيانات المحلية).
- **Database:** `related_data.db` و `sponsorships_data.db`.
- **Input:** `incoming_number` من نظام الأندرويد.
- **Output:** `Overlay Window` تظهر فوق شاشة الاتصال تعرض اسم المعيل/المكفول.
- **Error Handling:** التحقق من صلاحية `SYSTEM_ALERT_WINDOW`.
- **Dependencies:** `WindowManager`, `TelephonyManager`, `BroadcastReceiver`.

---

## 6. نظام السجل المدني غير المتصل (Offline Civil Registry)

### الوظيفة: تحميل وقراءة السجل (Civil Registry Storage)
- **File:** `CivilRegistryStore.java`
- **Class:** `CivilRegistryStore`
- **Method:** `getPerson(String id)` → `findChunkForId()`
- **API:** `GET /api/mobile/civil-registry/manifest`
- **Database:** ملفات JSON مضغوطة (`.gz`) مخزنة في الـ Internal Storage.
- **Input:** `identity_number`.
- **Output:** كائن `Person` (الاسم، تاريخ الميلاد، الجنس، المدينة).
- **Error Handling:** التحقق من اكتمال تحميل الـ Chunks قبل البحث.
- **Dependencies:** `GZIPInputStream`, `Internal Storage API`.

---

## 7. إدارة الجلسة والتوكن (Security Layer)

### الوظيفة: اعتراض الطلبات (Token Interceptor)
- **File:** `token-interceptor.js`
- **Class:** `TokenInterceptor`
- **Method:** `intercept()`
- **API:** جميع طلبات الـ Fetch/XHR.
- **Database:** `LocalStorage` (لجلب التوكن).
- **Input:** `Request Object`.
- **Output:** `Modified Request` مع `Authorization Header`.
- **Error Handling:** توجيه المستخدم لصفحة تسجيل الدخول في حال استلام خطأ 401.
- **Dependencies:** `Fetch API`, `Native Storage`.

---
**إقرار الجودة:** تم فحص كافة المسارات البرمجية المذكورة أعلاه ومطابقتها للكود الفعلي في المجلدات `java` و `assets/public/js`.
**تاريخ التحديث:** 2026-09-22
