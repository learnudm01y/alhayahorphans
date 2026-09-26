# تقرير التوثيق الهندسي الشامل — تطبيق «نظام الحياة / Al-Hayah Orphans»

> **نوع المستند:** مرجع رسمي للمطوّرين (مُستخرج بالقراءة المباشرة للكود الفعلي)
> **تاريخ الإصدار:** 2026-09-24
> **نطاق المستودع:** `C:\xampp\htdocs\alhayahorphans`
> **منهجية:** كل معلومة أدناه مبنية على قراءة ملفات فعلية — لا افتراضات. حيث لا يوجد كود، يُصرَّح بذلك صراحةً.

---

## جدول المحتويات

1. [المعمارية والبناء](#1-المعمارية-والبناء)
2. [الباكند (Laravel / MySQL)](#2-الباكند-laravel--mysql)
3. [قاعدة البيانات المحلية (Android)](#3-قاعدة-البيانات-المحلية-android)
4. [آلية العمل والمزامنة](#4-آلية-العمل-والمزامنة)
5. [دليل الواجهات](#5-دليل-الواجهات)
6. [الأمان والصلاحيات](#6-الأمان-والصلاحيات)

---

## 1) المعمارية والبناء

### 1.1 نظرة عامة

| البند | android-v3 (الإنتاج) | android-v4 (الطبقة الإضافية) |
|-------|----------------------|------------------------------|
| `applicationId` | `com.aso.app` | `com.aso.app` (نفس الحزمة) |
| `versionCode` | **110** | **200** |
| `versionName` | **"3.2"** | **"4.0"** |
| لغة الملفات | **Java فقط** (55 ملف `.java`) | **Java فقط** (71 ملف `.java` = v3 + 16 جديداً) |
| Kotlin | **غير مستخدم** — لا يوجد ملف `.kt` واحد في الشجرتين، ولا `kotlin-android` في `build.gradle` | نفسه |
| Capacitor | 8.0.1 (`@capacitor/android ^8.0.1`) | نفس الإصدار |
| `namespace` | `com.aso.app` | `com.aso.app` |
| `minSdk` / `targetSdk` / `compileSdk` | 24 / 36 / 36 (من `variables.gradle`) | 24 / 36 / 36 |
| Java source/target | 21 | 21 |
| MultiDex | مُفعَّل | مُفعَّل |
| الفرق بين الشجرتين | — | v4 = v3 + 16 ملفاً جديداً تحت `com.aso.app.v4.*`؛ **50 من 55 ملفاً متطابقة حرفاً بحرف** (MD5)، والـ5 المخالفة: `AutoUploadApplication`, `ChunkedUploadWorker`, `DriveStatusWorker`, `MainActivity`, `BackgroundSyncPlugin` |

**ملاحظة Dual-Run:** v4 لا يحذف ولا يعدّل كود v3 — يضيف فوقه. ملف `AutoUploadApplication` في v4 يحتوي كود v3 كاملاً **وأيضاً** سطرَي جدولة v4 (سطر 113 و122).

### 1.2 لغة كل طبقة فعلية

| الطبقة | اللغة | الدليل |
|--------|-------|--------|
| Java Native (Workers, Services, Plugins, DB Helpers, Bridges) | **Java** | جميع ملفات `android-v3|v4/app/src/main/java/**/*.java` |
| JS داخل WebView (شاشات admin/screens + الشاشات القديمة) | **JavaScript** (ES5/IIFE + some modern) | `assets/public/**/*.js` |
| HTML/CSS | HTML5 + CSS3 | `assets/public/**/*.html`, `*.css` |
| PHP (الباكند) | PHP 8.x (Laravel) | `app/`, `routes/` |
| Kotlin | **غير موجود** | grep `androidx.room\|\.kt` → صفر نتائج |

### 1.3 نظام Capacitor: قائمة Plugins المخصّصة

**Plugins رسمية (من `package.json` في جذر المستودع):**

| الحزمة | الإصدار |
|--------|---------|
| `@capacitor/android` | ^8.0.1 |
| `@capacitor/core` / `cli` | ^8.0.1 |
| `@capacitor/app` | ^8.0.0 |
| `@capacitor/camera` | ^8.0.0 |
| `@capacitor/filesystem` | ^8.0.0 |
| `@capacitor/haptics` | ^8.0.0 |
| `@capacitor/keyboard` | ^8.0.0 |
| `@capacitor/local-notifications` | ^8.0.0 |
| `@capacitor/network` | ^8.0.0 |
| `@capacitor/preferences` | ^8.0.0 |
| `@capacitor/status-bar` | ^8.0.0 |
| `@capacitor-community/sqlite` | ^7.0.3 |
| `@capawesome/capacitor-background-task` | ^8.0.0 |
| `@capawesome/capacitor-file-picker` | ^8.0.0 |

**Plugins مخصّصة (Java classes تمتد `com.getcapacitor.Plugin` مع `@CapacitorPlugin`):**

| # | الاسم المسجَّل | الملف | الوظيفة (من الكود) | v3 | v4 |
|---|---------------|-------|-------------------|----|----|
| 1 | `UploadService` | `com.aso.app.UploadServicePlugin` | إدارة رفع الملفات عبر الخلفية | ✅ | ✅ |
| 2 | `GoogleDriveUpload` | `com.aso.app.GoogleDriveUploadPlugin` | رفع للـ Google Drive + حالة drive | ✅ | ✅ |
| 3 | `SponsorshipFolderManager` | `com.aso.app.SponsorshipFolderManager` | إنشاء/إدارة مجلدات المكفولين محلياً | ✅ | ✅ |
| 4 | `NativeCamera` | `com.aso.app.NativeCameraPlugin` | تصوير فيديو أصلي (بلا Base64، يستخدم OkHttp streaming) | ✅ | ✅ |
| 5 | `NativePhoto` | `com.aso.app.NativePhotoPlugin` | التقاط صورة أصلي سريع | ✅ | ✅ |
| 6 | `BackgroundSync` | `org.alhayah.sponsorships.BackgroundSyncPlugin` | تشغيل/إيقاف مزامنة الخلفية من JS | ✅ | ✅ (ملف مُعدَّل في v4) |
| 7 | `PermissionsManager` | `com.aso.app.PermissionsManagerPlugin` | طلب صلاحيات Android متسلسلة (request codes 1001–1005) | ✅ | ✅ |
| 8 | `BarcodeScanner` | `com.aso.app.BarcodeScannerPlugin` | مسح باركود عبر ML Kit | ✅ | ✅ |
| 9 | **`JavaScriptBridgeV4`** | `com.aso.app.v4.bridge.JavaScriptBridgeV4` | جسر الشاشات الإدارية v4 ↔ SQLite المحلي | ❌ | ✅ **جديد** |

**عدد Plugins المخصّصة: 8 في v3 · 9 في v4.**

**دوال `JavaScriptBridgeV4` (12 `@PluginMethod` — من `JavaScriptBridgeV4.java`):**

| Method | الوظيفة | مصدر البيانات |
|--------|---------|---------------|
| `getDashboardSnapshot` | آخر لقطة لوحة قيادة | `admin_offline_v4.db → admin_dashboard_snapshot_v4` |
| `queryReportRows` | استعلام صفوف تقارير (sponsorships/files/bank_accounts/all) | `admin_reports_source_v4` |
| `getReportBundle` | حزمة تقرير مُهيّأة | `ReportsEngineV4` |
| `generateReportPdf` | توليد PDF محلي (Android `PdfDocument`) | `ReportsEngineV4.generatePdf` |
| `getPermissionsManifest` | قراءة manifest الصلاحيات الموقّع | `permissions_manifest_v4` |
| `hasPermission` | فحص صلاحية (عادية/حساسة) | `PermissionsCacheManagerV4` |
| `queryFileIndex` | فهرس الملفات | `file_index_v4` |
| `queueFileDelete` | طابور حذف ملف Offline | `FileManagementOfflineQueueV4` + outbox |
| `queueFileRename` | طابور إعادة تسمية | نفس |
| `queueFileMove` | طابور نقل | نفس |
| `getSyncHealth` | صحة المزامنة (pending, last pull, device id) | `sync_v4.db` + prefs |
| *(داخلي)* `resolveQueued` | توحيد استجابة الطوابير | — |

**التسجيل في `MainActivity.java` (سطر 33 في v4):**

```java
registerPlugin(com.aso.app.v4.bridge.JavaScriptBridgeV4.class);  // v4 only
// + نفس 8 plugins المسجَّلة في v3 (سطور 24–32)
```

### 1.4 المعمارية الفعلية (كما هي بالكود — لا MVVM/MVP)

الكود **لا يتبع** أي نمط MVVM أو MVP أو Clean Architecture. البنية الفعلية:

```
┌─────────────────────────────────────────────────────────────┐
│                    WebView (HTML/JS)                        │
│  ┌──────────────────┐  ┌─────────────────────────────────┐  │
│  │ legacy screens   │  │ v4 admin + screens (28 شاشة)    │  │
│  │ index/data/...   │  │ AdminAPI / SyncClientV4 / Nav   │  │
│  └────────┬─────────┘  └──────────────┬──────────────────┘  │
│           │  JS Bridges (AndroidBridge, CameraBridge)      │
│           │  + Capacitor Plugins                           │
└───────────┼─────────────────────────────┬───────────────────┘
            │                             │
┌───────────▼──────────────┐  ┌───────────▼──────────────────┐
│  Legacy Native Layer (v3)│  │  v4 Native Layer (جديد)      │
│  Workers:                │  │  Workers:                    │
│   DataSyncWorker         │  │   SyncWorkerV4               │
│   SponsorshipSyncWorker  │  │  Orchestrator:               │
│   ChunkedUploadWorker    │  │   UnifiedSyncOrchestratorV4  │
│   SmartMediaWorker...    │  │  Managers:                   │
│  Services:               │  │   SyncOutboxManagerV4        │
│   RealtimeSyncService    │  │   DeviceIdentityManagerV4    │
│   DownloadForeground...  │  │   RemoteConfigManagerV4      │
│  DB Helpers (5 SQLite):  │  │   ConflictResolverV4         │
│   UploadDatabaseHelper   │  │  DB (2 SQLite):              │
│   RelatedDataDatabase... │  │   SyncDatabaseHelperV4       │
│   DataSyncDatabase...    │  │   AdminOfflineDatabase...    │
│   SponsorshipsDatabase.. │  │  Bridge:                     │
│   CivilRegistryStore     │  │   JavaScriptBridgeV4         │
└──────────────────────────┘  └──────────────────────────────┘
            │                             │
            └──────────┬──────────────────┘
                       ▼
         Laravel Backend (routes/api.php + routes/api_v4.php)
```

**حقائق معمارية من الكود:**
- **لا يوجد Repository Pattern** — DB Helpers تستخدم `SQLiteDatabase` الخام مباشرة.
- **لا يوجد ViewModel / LiveData / Flow.**
- **التنسيق بين الطبقات:** JS ↔ Java عبر Capacitor Plugin API (`@PluginMethod` / `PluginCall`) + `WebView.addJavascriptInterface` (جسور `AndroidBridge`, `CameraBridge`).
- **التواصل الشبكي:** `HttpURLConnection` في v4 Orchestrator، `OkHttp 4.12.0` في رفع الملفات والـ WebSocket.
- **الجدولة:** AndroidX WorkManager حصراً (لا AlarmManager للـ sync الأساسي، مع `SCHEDULE_EXACT_ALARM` مُصرَّح له في Manifest).

### 1.5 أهم مكتبات `build.gradle`

**ملاحظة:** ملفا `app/build.gradle` في v3 وv4 **متطابقان في كتلة `dependencies` بالكامل** — الفرق فقط `versionCode`/`versionName`/التعليق.

| المكتبة | الإصدار | الاستخدام الفعلي في الكود |
|---------|---------|--------------------------|
| `androidx.appcompat:appcompat` | (من `variables.gradle`) | Activity/Theme |
| `androidx.core:core-splashscreen` | (متغير) | شاشة الإقلاع |
| `project(':capacitor-android')` | Capacitor 8 | نواة WebView/Plugins |
| `project(':capacitor-cordova-android-plugins')` | — | توافق Cordova |
| **`androidx.work:work-runtime`** | **2.8.1** | جميع Workers (v3 + v4) |
| **`com.squareup.okhttp3:okhttp`** | **4.12.0** | رفع ملفات chunked + WebSocket (`RealtimeSyncService`) |
| **`androidx.media3:media3-transformer`** | **1.7.1** | ضغط فيديو (`SmartMediaProcessor`) |
| **`com.google.mlkit:barcode-scanning`** | **17.3.0** | مسح الباركود (`BarcodeScannerPlugin` + `BarcodeScannerActivity`) |
| `junit` / `espresso` | (متغيرات) | اختبارات |

**من `capacitor.build.gradle` (متطابق في الاثنين):** Java 21 + 12 مشروع Capacitor/Capawesome plugin (sqlite, camera, filesystem, network, preferences, local-notifications, background-task, file-picker, haptics, status-bar, keyboard, app).

**`variables.gradle` (متطابق):** `minSdk 24`, `compileSdk 36`, `targetSdk 36`, cordova-android `14.0.1`.

**`capacitor.config.json` (جذر + assets للنسختين — متطابق):**

```json
{
  "appId": "com.aso.app",
  "appName": "Sponsorships",
  "webDir": "mobile-app/dist",
  "server": { "cleartext": true },
  "android": {
    "allowMixedContent": false,
    "webContentsDebuggingEnabled": true,
    "minWebViewVersion": 60
  }
}
```

> `CapacitorHttp.enabled = false` — أي أن `fetch()` في JS يذهب مباشرة للشبكة (وليس عبر interceptor أصلي).

---

## 2) الباكند (Laravel / MySQL)

### 2.1 تسجيل ملفات المسارات

من `app/Providers/RouteServiceProvider.php` (يُحمَّل **5 ملفات فقط**):

| الملف | Middleware | Prefix |
|-------|-----------|--------|
| `routes/api.php` | `api` | `/api` |
| **`routes/api_v4.php`** | `api` | `/api` |
| `routes/offline-test-development.php` | `api` | `/api` |
| `routes/web.php` | `web` | — |
| `routes/admin.php` | `['web','auth','rolebreeze:admin']` | — |

`routes/web.php` يقرأ بدوره `routes/auth.php`.

**ملفات ميتة (غير مُسجَّلة):** `file-management.php`, `exact_search_api.php`, `admin_backup.php`, `test_snappy.php`, `test-download.php`, `api.phpBak`, `api.phpbak2`, `Backup/api.phpBak`.

**Rate Limiters:**

| الاسم | الحد | الاستثناء |
|-------|------|-----------|
| `api` | 60 req/min | مسارات chunk-upload → 1200 req/min |
| `api-v4` | **120 req/min** | — |

### 2.2 `routes/api.php` القديم (Legacy)

بنية عامة (≈102 تعريف route، كثيرها closures):

| المجموعة | المحتوى | Auth |
|----------|---------|------|
| `prefix('files')` | 15 route: upload/analytics/batch/download/excel… | متغيّر |
| `prefix('duplicate-files')` | 4 | لا |
| `prefix('sync')` | 8 → `SyncController` | sanctum |
| `prefix('uploads')` | 9 → `ChunkedUploadController`, `GoogleDriveUploadController` | sanctum |
| `prefix('mobile')` **عام** | `POST /login`, `POST /refresh-token`, `GET /health` → `SponsorshipSyncController` | لا |
| `prefix('mobile')` **sanctum** | المجموعة الأساسية القديمة: sync/initial, sync/full, upload-file, registration/*, photos/*, civil-registry/*, server-actions, sync/actions… | sanctum |
| closures بحث | `/api/search/ultra-fast|lightning|exact|smart|final-exact|exact-only` | **لا auth** |

### 2.3 `routes/api_v4.php` الجديد — 60 مساراً

كلها تحت `Route::prefix('mobile/v4')` + **`auth:sanctum` + `throttle:api-v4`**. الأسماء `api.v4.*`.

| # | Method | URI (بعد `/api/mobile/v4/`) | Controller@method |
|---|--------|----------------------------|-------------------|
| 1 | POST | `sync/registration` | SyncControllerV4@registration |
| 2 | POST | `sync/actions` | SyncControllerV4@actions |
| 3 | GET | `sync/pull` | SyncControllerV4@pull |
| 4 | POST | `device/register` | DeviceRegistryControllerV4@register |
| 5 | GET | `device/health` | DeviceRegistryControllerV4@health |
| 6 | GET | `conflicts` | ReconciliationControllerV4@index |
| 7 | POST | `conflicts/{id}/resolve` | ReconciliationControllerV4@resolve |
| 8 | GET | `audit` | ReconciliationControllerV4@audit |
| 9 | GET | `admin/dashboard-snapshot` | AdminOfflineExportControllerV4@dashboardSnapshot |
| 10 | GET | `admin/reports-source` | AdminOfflineExportControllerV4@reportsSource |
| 11 | GET | `admin/permissions-manifest` | AdminOfflineExportControllerV4@permissionsManifest |
| 12–16 | GET/POST/PUT/DELETE | `records`, `records/{id}` | AdminCrudControllerV4@records* |
| 17–20 | GET/POST/PUT/DELETE | `categories/{table}`, `categories/{table}/{id}` | AdminCrudControllerV4@categories* |
| 21–23 | GET | `search-records`, `search-suggestions`, `global-search` | AdminCrudControllerV4@search* |
| 24–30 | CRUD | `sponsorships`, `sponsorships/{id}`, `sponsorships/{id}/status`, `sponsorships-list/{sponsored\|unsponsored}` | AdminCrudControllerV4@sponsorship* |
| 31–34 | CRUD | `sponsors` | AdminCrudControllerV4@sponsor* |
| 35–38 | CRUD | `users` | AdminCrudControllerV4@users* |
| 39–42 | CRUD | `roles` | AdminCrudControllerV4@roles* |
| 43–47 | GET/DELETE | `files`, `files/folders`, `files/duplicates`, `files/audit`, `files/{id}` | AdminCrudControllerV4@file* |
| 48–52 | GET/POST | `civil-registry`, `civil-registry/search`, `civil-registry/import-template`, `civil-registry/validate-import`, `civil-registry/import` | AdminCrudControllerV4@civil* |
| 53–54 | GET/PUT | `user-requests`, `user-requests/change-status` | AdminCrudControllerV4@userRequest* |
| 55–58 | GET/PUT | `profile`, `profile/email`, `profile/password` | AdminCrudControllerV4@profile* |
| 59 | GET | `notifications` | AdminCrudControllerV4@notificationsIndex |
| 60 | GET | `config/flags` | ConfigFlagsControllerV4@flags |

### 2.4 Controllers

**الإجمالي:** 97 ملفاً تحت `app/Http/Controllers/`.

| المجلد | العدد | الدور |
|--------|-------|-------|
| الجذر | 12 | ملفات/بحث/رفع عامة |
| `Auth/` | 9 | Breeze auth |
| `Roles/`, `Users/` | 8 | إدارة مستخدمين |
| **`Admin/`** | **53** | لوحة التحكم Laravel القديمة (Blade) |
| **`Api/`** | **10** | مزامنة الجوال القديمة (v3) |
| **`Api/V4/`** | **6** | مزامنة + لوحة v4 |

**`Api/V4/` — 6 Controllers (v4 فقط):**

| Controller | السطور | المسؤولية |
|------------|--------|-----------|
| `SyncControllerV4` | 159 | registration (يطلب `X-Idempotency-Key` إلزامياً → 400 بدونه)، actions (دفعة)، pull (INCREMENTAL، limit 500/جدول) |
| `ReconciliationControllerV4` | 173 | conflicts index/resolve (merge\|keep_both\|reject_incoming)، audit log |
| `DeviceRegistryControllerV4` | 62 | device register/health |
| `ConfigFlagsControllerV4` | 25 | `legacy_sync_enabled` |
| `AdminOfflineExportControllerV4` | 197 | dashboard snapshot (cache ساعة)، reports source، permissions manifest (HMAC-SHA256) |
| `AdminCrudControllerV4` | **1188** | CRUD الموحّد: records, categories (whitelist 21 جدولاً), search, sponsorships, sponsors, users, roles, files, civil-registry, user-requests, profile, notifications |

**`Api/` القديمة (v3):**

| Controller | السطور | المسارات |
|------------|--------|----------|
| `SponsorshipSyncController` | **4476** (الأكبر) | login, refresh, sponsors, sync/initial\|full\|…, upload-file, photos/* |
| `MobileRegistrationController` | 1237 | registration/*, civil-registry/* |
| `SyncController` | 578 | `/api/sync/*` |
| `MobileSyncController` | 421 | **غير مُسجَّل في أي routes file** (كود ميت) |
| `ActionSyncController` | 321 | sync/actions, pending-actions, ack-action |
| `ChunkedUploadController` | — | chunk, upload-status, retry-rclone |
| `GoogleDriveUploadController` | — | drive-status, offline-inbox, stats |
| `ServerActionController` | 45 | server-actions pull/ack |
| `FileAnalyticsController` | — | analytics |
| `OfflineTestController` | — | فقط في `offline-test-development.php` |

### 2.5 Services

**37 ملفاً** تحت `app/Services/`. الأهم:

**`app/Services/V4/` (4 خدمات — v4 فقط):**

| الخدمة | السطور | الوظيفة |
|--------|--------|---------|
| `IdempotentUpsertServiceV4` | 175 | بحث في `sync_idempotency_log_v4` بالـ key → ردّ مخزَّن؛ وإلا transaction: upsert حسب `client_uuid` + strip حقول حساسة + conflict check + تسجيل الرد |
| `ConflictDetectionServiceV4` | 121 | كشف تعارض على مستوى المحتوى (person_id / data_id_number / identity_number / name+DOB) → `conflict_review_queue_v4` بثقة 1.0 أو 0.75 — **لا دمج تلقائي أبداً** |
| `AuditLoggerV4` | 236 | كتابة `record_audit_log_v4` (create/update/blocked_sensitive) — أخطاءها تُبتلع ولا تكسر المزامنة |
| `DeviceHandshakeServiceV4` | 103 | upsert `device_registry_v4` |

**خدمات قديمة بارزة:** `ExcelImportService` (1557 سطراً، PhpSpreadsheet)، `SearchService` (1229، تطبيع عربي: أ/إ/آ→ا، ة→ه، ى→ي)، + 14 خدمة بحث متخصصة (`UltraFastSearchService`, `LightningSearchService`, `ScoutSearchService`…).

### 2.6 Models

**49 model** تحت `app/Models/`.

**`Data.php` (284 سطراً):**
- `$table = 'data'`
- العلاقات: `section()`, `person()`, `guardianBankAccount()`, `requestStatus()`, `city()`, `province()`, `rePeople()`, `deadPepole()` (sic — اسم الجدول `dead_people`), `sponsorshipsAsOrphan()`…
- **ليس في `fillable`:** `client_uuid`, `sync_origin_device_id`, `needs_review` (تُضاف على مستوى DB).

**`Sponsorship.php` (294 سطراً):**
- أحداث نموذج: `deleting` → detach pivot + إنشاء `ServerSyncAction`; `created/updated` → `ServerSyncAction` مع payload مُثرى عبر `SponsorshipSyncController::getSingleEnrichedSponsorship`.
- `belongsToMany(Sponsor)` عبر `sponsorship_sponsor`.

**`User.php`:** `HasApiTokens` (Sanctum) + `HasRoles` (Spatie) + `Notifiable`.

### 2.7 قاعدة MySQL (`aso` — 78 جدولاً)

**جداول v4 التسعة (أُضيفت خصيصاً — مig migrations `2026_09_23_*`):**

| الجدول | الأعمدة الرئيسية |
|--------|-----------------|
| `sync_outbox_v4` | client_uuid, device_id, entity_type, operation_type, payload_json, **idempotency_key UNIQUE**, status(pending/applied/failed) |
| `sync_idempotency_log_v4` | **idempotency_key UNIQUE**, response_json, entity_type, entity_id |
| `device_registry_v4` | **device_id UNIQUE**, device_label, user_id, app_version, last_seen_at, last_sync_at, pending_ops_count, health_status |
| `conflict_review_queue_v4` | entity_type, existing_record_*, incoming_payload_json, match_reason, match_confidence, status(open/resolved), resolved_by_user_id |
| `record_audit_log_v4` | entity_type, client_uuid, record_id, operation(create/update/blocked_sensitive/delete), field_name, old/new_value, is_sensitive, changed_by_device, idempotency_key + 4 فهارس |
| `admin_dashboard_snapshot_v4` | snapshot_key UNIQUE, snapshot_value_json, generated_at |
| `admin_reports_source_v4` | report_type, row_data_json, source_client_uuid |
| `permissions_manifest_v4` | user_id UNIQUE, manifest_json, signature(HMAC), issued_at, expires_at |
| `file_index_v4` | client_uuid UNIQUE, identity_number, person_name, stored_file_name, file_path, source, status, **cache_priority**, local_cache_path, cached_at |

**الجداول الستة القديمة المُوسَّعة بعمودات v4** (`client_uuid CHAR(36) UNIQUE`, `sync_origin_device_id`, `needs_review` + trigger `trg_{table}_v4_client_uuid`):
`data`, `re_people`, `dead_people`, `additional_deceased`, `guardian_bank_accounts`, `sponsorships`.

**جداول بارزة أخرى:**

| الجدول | الدور |
|--------|-------|
| `data` (43 عموداً) | سجلات الأسر — الجدول الرئيسي |
| `re_people` (20) | أفراد الأسرة الأحياء |
| `dead_people` (20) | الوفيات (أب/أم) |
| `sponsorships` (27) | الكفالات |
| `sponsors` (24) | الجمعيات المكفِّلة |
| `guardian_bank_accounts` (14) | حسابات البنكة |
| `attachments` (10) | المرفقات |
| `file_id_registry` (11) | حجز/تفعيل أرقام الملفات (handshake_token) |
| `server_sync_actions` (8) | طابور أcciones الخادم→التطبيق (v3) |
| `chunked_uploads` | جلسات رفع مقسّمة |
| `refresh_tokens` | توكنات تجديد |
| 21 جدول تصنيف | `academic_degrees` … `type_of_guarantee` (جميعها تستخدم عمود `description` أو `attribute` أو `city` بدل `name`) |
| `persons` (connection `civilregistry`) | السجل المدني — قاعدة بيانات منفصلة |

**العلاقة الجوهرية:**
```
users ──< sponsorships >── sponsors (pivot: sponsorship_sponsor)
data (المعيل/file_id_number) ──< re_people (registration_id)
data ──< dead_people (re_file_id)
data ──< guardian_bank_accounts (guardian_registration)
data ──< attachments (person_identity_number ↔ data_id_number)
data.data_request_status → request_status.id (FK)
data.data_city → city.id … + 9 FKs أخرى للتصنيفات
```

### 2.8 إعداد Cutover

`config/services.php`:
```php
'sync_v4' => [
    'legacy_sync_enabled' => env('LEGACY_SYNC_ENABLED', true), // safe default = dual-run
],
```
الإنتاج حالياً `true` (Dual-Run) — تفعيل `false` = إلغاء جدولة v3 وقتياً فقط.

---

## 3) قاعدة البيانات المحلية (Android)

### 3.1 هل Room مستخدم؟

**لا.** grep `androidx.room|RoomDatabase|@Entity|@Dao` في `android-v3` و`android-v4` → **صفر نتائج**. جميع القواعد **SQLite خام** عبر `android.database.sqlite.SQLiteOpenHelper`.

**بديل `@capacitor-community/sqlite` مُثبَّت في `package.json` لكن لا يوجد أي استخدام له في شاشات `mobile-app-v4` أو assets** (لم يُعثر على `Capacitor.Plugins.SQLite` في JS).

### 3.2 قواعد v3 الخمس

| # | اسم القاعدة | الإصدار | Helper Class | الجداول (CREATE TABLE فعلي) |
|---|------------|---------|--------------|----------------------------|
| 1 | **`data_sync.db`** | 2 | `org.alhayah.sponsorships.DataSyncDatabaseHelper` | `sync_queue` |
| 2 | **`upload_queue.db`** | 7 | `com.aso.app.UploadDatabaseHelper` | `upload_queue`, `indexeddb_mapping`, `person_name_history` |
| 3 | **`related_data.db`** | 2 | `com.aso.app.RelatedDataDatabaseHelper` | `data_table`, `re_people`, `dead_people`, `guardian_bank_accounts`, `death_reasons`, `additional_deceased` |
| 4 | **`sponsorships_data.db`** | 3 | `com.aso.app.SponsorshipsDatabaseHelper` | `sponsorships`, `app_lookups` |
| 5 | **`civil_registry.db`** | 2 | `org.alhayah.sponsorships.CivilRegistryStore` | `civil_persons` (+ SharedPreferences `civil_registry_prefs`) |

### 3.3 قواعد v4 (2 جديدة)

| # | اسم القاعدة | الإصدار | Helper | الجداول |
|---|------------|---------|--------|---------|
| 6 | **`sync_v4.db`** | 1 | `com.aso.app.v4.db.SyncDatabaseHelperV4` | `outbox_local` (idempotency_key UNIQUE, status, attempts…), `records_v4` (client_uuid PK, payload_json, sync_status, needs_review), `sync_state` (key/value — cursor آخر pull) |
| 7 | **`admin_offline_v4.db`** | **2** | `com.aso.app.v4.db.AdminOfflineDatabaseHelperV4` | `admin_dashboard_snapshot_v4`, `admin_reports_source_v4` (UNIQUE(report_type,row_key)), `permissions_manifest_v4`, `file_index_v4` (cache_priority, local_cache_path, cached_at — أُضيفت في v2), `admin_sync_state` |

**منطق العزل (مُوثَّق في تعليقات الكود):**
> `UnifiedSyncOrchestratorV4`: *"Dual-Run: this class ONLY touches sync_v4.db + admin_offline_v4.db. It never opens sponsorships_data.db / related_data.db / data_sync.db / upload_queue.db."*

**تحديث v1→v2 في `AdminOfflineDatabaseHelperV4.onUpgrade`:** `ALTER TABLE file_index_v4 ADD COLUMN` ×3 فقط (لا حذف).

### 3.4 IndexedDB في WebView (طبقة ثالثة)

| الملف | الاستخدام |
|-------|-----------|
| `public/js/file-storage-indexeddb.js` | طابور رفع Web القديم |
| `assets/public/js/registration.js` | تسجيل Offline |
| `assets/public/js/full-file-edit.js` | تعديل ملف Offline |
| `assets/public/js/photo-store.js` | مخزن صور |
| `assets/public/js/civil-registry.js` | كاش السجل المدني |
| `assets/public/js/sync-service.js` | طابور pending-actions (يُعاد تشغيله عند الاتصال) |

---

## 4) آلية العمل والمزامنة

### 4.1 جدول كل العاملين (Workers) والخدمات

#### v3 (8 Workers + 5 Services + 4 Receivers)

| المكوّن | النوع | الجدولة / المُشغِّل | الوظيفة |
|---------|-------|-------------------|---------|
| `DataSyncWorker` | Worker | **دوري 15 دقيقة** · unique `periodic_data_sync_check` · KEEP | مزامنة بيانات related_data |
| `SponsorshipSyncWorker` | Worker | **دوري 15 دقيقة** · unique `periodic_sponsorships_sync` · KEEP + one-shot `FullSyncWork` · REPLACE | مزامنة الكفالات |
| `NetworkConnectedWorker` | Worker | مجدول عبر `scheduleWork` | عند عودة الشبكة |
| `ChunkedUploadWorker` | Worker | مُشغَّل من upload pipeline | رفع chunked |
| `DriveStatusWorker` | Worker | مُشغَّل بعد الرفع | فحص حالة Google Drive |
| `PrepareUploadsWorker` | Worker | — | تجهيز ملفات الرفع |
| `SmartMediaWorker` | Worker | دوري (`schedulePeriodicMediaProcessing`) | ضغط صور/فيديو |
| `FileSyncWorker` | Worker | — | مزامنة حالة ملفات |
| `DataSyncForegroundService` | Service (dataSync) | إشعار 9999 | مزامنة أمامية |
| `BackgroundSyncService` | Service | إشعار 1001 | مزامنة خلفية |
| `DownloadForegroundService` | Service | إشعار 8888 · **`static isDownloading()`** | تنزيل — **يحجب v4** |
| `RealtimeSyncService` | Service | OkHttp WebSocket → Laravel Reverb | مزامنة لحظية |
| `CallerInfoService` | Service | إشعار 7777 | معلومات المتصل |
| `DataSyncBootReceiver` | Receiver | BOOT_COMPLETED + MY_PACKAGE_REPLACED | إعادة جدولة |
| `UploadBootReceiver` | Receiver | BOOT_COMPLETED + MY_PACKAGE_REPLACED | استرجاع رفعات عالقة |
| `RealtimeSyncBootReceiver` | Receiver | BOOT_COMPLETED | إعادة WebSocket |
| `IncomingCallReceiver` | Receiver | PHONE_STATE | مكالمات |
| `UploadTaskScheduler` | (داخلي) | unique `BackgroundUploadWork` · debounce 10 ثوانٍ · chunk 8KB | طابور الرفع |

**استدعاءات إضافية عند `Application.onCreate` (من `AutoUploadApplication.java`):**
- `reclaimStaleUploads` + `reclaimStaleProcessing` + `reclaimStaleLocalProcessing` (استرجاع عالق)
- `scheduleUploadTask` + `schedulePeriodicUploadSweep` + `schedulePeriodicMediaProcessing`
- `UnifiedNetworkMonitor.startMonitoring` (throttle 5 ثوانٍ)

#### v4 (1 Worker + Orchestrator + 8 Managers)

| المكوّن | النوع | الجدولة | الوظيفة |
|---------|-------|---------|---------|
| **`SyncWorkerV4`** | Worker | **دوري 15 دقيقة** · unique **`unified_sync_v4`** · **KEEP** · backoff أسّي · constraint `NetworkType.CONNECTED` + one-shot unique **`unified_sync_v4_immediate`** · KEEP | نقطة الدخول الوحيدة لمحرك v4 |
| `UnifiedSyncOrchestratorV4` | كلاس ثابت | يُستدعى من Worker | 4 مراحل (أدناه) · timeout 60 ث · batch 50 |
| `SyncOutboxManagerV4` | كلاس ثابت | — | enqueue/idempotency/marks · batch 50 |
| `IdempotencyKeyGeneratorV4` | كلاس ثابت | — | `sha256(clientUuid\|operation\|payloadJson)` |
| `DeviceIdentityManagerV4` | كلاس ثابت | — | device_id ثابت (يرفض أندرويد إيموليتر `9774d56d682e549c`) |
| `RemoteConfigManagerV4` | كلاس ثابت | يُستدعى كل دورة + عند الإقلاع البارد | جلب/تطبيق `legacy_sync_enabled` |
| `ConflictResolverV4` | كلاس ثابت | بعد كل استجابة ناجحة | **لا دمج تلقائي** — يعلّم `needs_review` فقط |
| `ProfilePhotoSyncManagerV4` | كلاس ثابت | مرحلة 4b · max 40 صورة/دورة | صور always-local |
| `AdminOfflineDataStoreV4` | — | مرحلة 4 | كاش admin_offline_v4.db |
| `ReportsEngineV4` | — | عند الطلب | PDF محلي `PdfDocument` |
| `PermissionsCacheManagerV4` | — | مرحلة 4c | mirror Spatie · صلاحية 24 ساعة |
| `FileManagementOfflineQueueV4` | — | عند الطلب | طابور حذف/تسمية/نقل ملفات |

**إضافة v4 في `AutoUploadApplication.onCreate` (سطور 112–125):**
```java
SyncSchedulerV4.schedulePeriodicSync(this);      // سطر 113
RemoteConfigManagerV4.applyCachedFlag(this);     // سطر 122
```
كلاهما داخل try/catch — **فشل v4 لا يكسر v3**.

### 4.2 تعارض/تكرار بين محركات المزامنة

**الحالة الفعلية: محركان يعملان بالتوازي (Dual-Run) بإدارة:**

| المحرك | Unique Work | المدة | يلمس |
|--------|-------------|-------|------|
| v3 Data | `periodic_data_sync_check` | 15 د | data_sync.db + related_data.db |
| v3 Sponsorships | `periodic_sponsorships_sync` + `FullSyncWork` | 15 د + فوري | sponsorships_data.db + upload_queue.db |
| **v4 Unified** | **`unified_sync_v4`** + `unified_sync_v4_immediate` | 15 د + فوري | **sync_v4.db + admin_offline_v4.db فقط** |

**ضمانات منع التكرار داخل v4 (من الكود):**

1. **`ExistingPeriodicWorkPolicy.KEEP`** على `unified_sync_v4` — لا يُنشئ عملاً ثانياً أبداً (الإصلاح الهيكلي).
2. **`SyncWorkerV4.doWork`** يرفض العمل إذا كان `DownloadForegroundService.isDownloading()` → `Result.retry()` (يتفادى تنافس الشبكة مع v3).
3. **Idempotency مزدوج (عمود + خادم):**
   - الجهاز: `idempotency_key` يُخزَّن في `outbox_local` ولا يُحذف إلا بعد `markApplied`.
   - الخادم: `IdempotentUpsertServiceV4` يبحث في `sync_idempotency_log_v4` → إن وُجد يرجع الردّ المخزَّن بدون INSERT ثانٍ.
   - المفتاح: `sha256Hex(clientUuid + "|" + operationType + "|" + payloadJson)` — **يتطابق بالضبط** بين `IdempotencyKeyGeneratorV4.java` والخادم.
4. **`client_uuid UNIQUE`** على مستوى MySQL (6 جداول) + trigger يملؤه تلقائياً عند الإدراج القديم.
5. **`CONFLICT_REPLACE`** في outbox/local mirror — نفس `client_uuid` لا يتكرر.
6. **فشل الشبكة لا يحذف الطابور:** `drainOutbox` في catch → `// Network/parse failure: DO NOT delete. Idempotency makes retry safe.`
7. **`MAX_RUN_ATTEMPTS = 5`** ثم `Result.failure` (لا لولب لا نهائي). أخطاء auth → `failure` فوراً (لا retry).

**تداخل مقصود واحد:** `SyncWorkerV4` يستعلم من `DownloadForegroundService.isDownloading()` — هذا هو نقطة التنسيق الوحيدة بين المحركين أثناء Dual-Run.

### 4.3 دورة المزامنة v4 (4 مراحل — من `UnifiedSyncOrchestratorV4.runFullCycle`)

```
[0] لا يوجد auth token؟ → "no_auth_token" (يتوقف)
    │
[1] ensureDeviceRegistered → POST /device/register (مرة واحدة فقط ثم cached)
[1b] RemoteConfigManagerV4.fetchAndApply → GET /config/flags  (non-fatal)
    │
[2] drainOutbox → POST /sync/actions  (batch ≤50)
    │              نجاح → markApplied (حذف الصف)
    │              فشل HTTP → markFailed (يبقى pending)
    │              فشل شبكة → يبقى pending (لا حذف)
    │              خطأ → يوقف الدورة (pull يتخطاه لتوضيح)
[3] pullIncremental → GET /sync/pull?since={last_pull_at}
    │                 6 جداول × limit 500 → upsertRecord في records_v4
    │                 يحدّث cursor = server_time
[4] pullAdminOffline (non-fatal كاملاً):
    │  4a dashboard-snapshot → saveDashboardSnapshot
    │  4b reports-source?since= → ingestReports
    │  4c permissions-manifest → saveManifest (+signature)
    │  4d setLastAdminPull(now)
[4b] ProfilePhotoSyncManagerV4.downloadPendingProfilePhotos (non-fatal, ≤40)
    │
    ▼
  null = نجاح · غير null = رسالة خطأ
```

**العناوين المُرسَّلة في كل طلب v4 (من `httpPost`/`httpGet`):**
`Authorization: Bearer {token}` · `X-Device-Id` · `X-Sync-Source: app_v4` · (+ `X-Idempotency-Key` في registration).

**التوكن يُقرأ من:** `SharedPreferences "auth_prefs" → key "api_token"`.

### 4.4 آلية Idempotency — خلاصة من الكود

| الطبقة | الآلية | الموقع |
|--------|--------|--------|
| 1. توليد المفتاح | `sha256(clientUuid\|op\|payload)` | `IdempotencyKeyGeneratorV4.generate` |
| 2. حفظ المفتاح | `outbox_local.idempotency_key UNIQUE` | `SyncDatabaseHelperV4.enqueue` |
| 3. إرسال المفتاح | header `X-Idempotency-Key` + حقل داخل `actions[]` | Orchestrator + `SyncControllerV4@actions` |
| 4. كاش الخادم | بحث `sync_idempotency_log_v4` → ردّ مخزَّن | `IdempotentUpsertServiceV4::handle` |
| 5. فريد السجل | `client_uuid UNIQUE` في 6 جداول + trigger | migration `2026_09_23_000001` |
| 6. كشف التعارض | مطابقة محتوى → `conflict_review_queue_v4` | `ConflictDetectionServiceV4` |
| 7. حل التعارض | بشري فقط عبر `POST /conflicts/{id}/resolve` | لا auto-merge في الطرفين |

**`SyncControllerV4@registration`:** بدون `X-Idempotency-Key` → **HTTP 400** (إلزامي).

### 4.5 سلوك Offline الفعلي لكل شاشة رئيسية

**آلية v4 العامة (تُطبَّق على 28 شاشة admin/screens):**
1. `AdminAPI.withCache(key, fetcher)`: نجاح → `localStorage v4_admin_cache_*` · فشل شبكة → يرجع الكاش مع `offline:true`.
2. أولوية Bridge: `JavaScriptBridgeV4.*` (SQLite محلي) → fetch HTTPS → localStorage cache.
3. شارة `#mode-badge` عبر `CrudHelper.setOnlineBadge()` + أحداث `navigator.onLine`.

| الشاشة | السلوك عند انقطاع الإنترنت |
|--------|----------------------------|
| `dashboard.html` | يعرض آخر لقطة من `admin_offline_v4.db` (bridge) أو `v4_admin_cache_dashboard` |
| `records.html` / `record-show.html` | قراءة من الكاش · `records` CRUD قد يفشل PUT → الكاش يظهر |
| **`record-form.html`** | عند فشل الإرسال: يضع السجل في **`localStorage v4_record_outbox`** (طابور Offline) — لا حفظ فوري في sync_v4.db من JS |
| `sponsorships.html` | قراءة كاش · **`btn-export` معطّل** (رسالة: «التصدير يتطلب اتصالاً») |
| `reports.html` | `generateReportPdf` عبر **bridge محلي** → يعمل Offline كاملاً (PDF من SQLite) |
| `permissions.html` | قراءة `permissions_manifest_v4` عبر bridge · يفحص `expires_at` (سليم/قريب/منتهي) |
| `files.html` / `folders.html` | `queryFileIndex` bridge → Offline · الحذف عبر `queueFileDelete` → **outbox** (يُنفَّذ عند عودة الشبكة) |
| **`conflict-review.html`** | العرض Offline (REST + `v4_conflicts_cache`) · **زر الحل معطّل Offline** (يقول: يتطلب اتصالاً) |
| `civil-registry.html` | قراءة IndexedDB/كاش · شارة Offline/Online |
| **`civil-import.html`** | **`btn-import` معطّل Offline** صراحةً · validate قد يعمل على ملف محلي |
| `sync-health.html` | يعرض `pending_outbox` + `pending_file_ops` + `device_id` من bridge (دائماً متاح) |
| `audit.html` / `devices.html` | كاش REST فقط — Offline: آخر نتيجة محفوظة |
| `notifications.html` | **`localStorage v4_local_notifications`** — يعمل Offline كاملاً |
| `settings.html` | تفضيلات `localStorage v4_prefs` Offline · تغيير كلمة المرور يتطلب اتصالاً |
| `users.html` / `roles.html` | كاش قراءة · الكتابة تحتاج اتصالاً |

**الشاشات القديمة (v3) Offline:**
- `sync-service.js` + IndexedDB outbox (`navigator.onLine` gates).
- `full-file-edit.js`: يرمي `"لا يوجد اتصال بالإنترنت"` عند محاولة حفظ Offline.
- Service Worker `sw.js` (cache `alhayah-sponsorships-v33`) — **لكن لم يُعثر على `serviceWorker.register` في أي HTML مُصدَّر** (يوجد فقط في `temp.js` الماسيح). أي أن SW قد لا يكون مُسجَّلاً فعلياً.

---

## 5) دليل الواجهات

### 5.1 التصنيف حسب الموقع (بدليل وجود الملف)

| الفئة | العدد | المواقع | الدليل |
|-------|-------|---------|--------|
| **خاص بـ v4 فقط** | **28 HTML** | `mobile-app-v4/src/{admin,screens}/` + `android-v4/assets/public/{admin,screens}/` | **غائب تماماً** من `android-v3/assets` و`public/` |
| **مشترك v3 + v4 (Legacy mobile)** | **10 HTML** | `android-v3/assets/public/` = `android-v4/assets/public/` (نفس العناوين) | موجود في الاثنين · **غائب** من `mobile-app-v4/src` |
| **خاص بـ Web القديم (`public/`)** | 21 HTML | `public/` فقط | index (نظام الرعاية) + أقسام اختبار/دليل/speedtest |
| **JS خاص بـ v4** | 5 | `admin-api.js`, `admin-nav.js`, `categories-config.js`, `crud-helper.js`, `sync-client-v4.js` | غائب من v3 |
| **JS مشترك** | 15+ | `config.js`, `api-service.js`, `sync-service.js`, `token-interceptor.js`, `registration.js`… | في v3 وv4 |

**مقارنة assets:** `android-v4` = **فوقاني صارم** لـ `android-v3` (35 ملفاً إضافياً للـ v4، **صفر** ملفات v3-only).

**ملاحظة انسداد:** نسخة `admin-api.js` في `android-v4/assets` (303 سطراً) **أقدم** من `mobile-app-v4/src` (315 سطراً — تضيف `downloadCivilTemplate` و`validateCivilImport`).

### 5.2 الشاشات الخاصة بـ v4 — 22 admin + 6 screens

**`admin/` (22):**

| الملف | العنوان (h1) | الوظيفة | الأزرار/الإجراءات الفعلية | JS |
|-------|-------------|---------|---------------------------|-----|
| `dashboard.html` | لوحة القيادة الرئيسية | إحصائيات (ملفات/أسر/وفيات/كفالات/بنوك/تعارضات/معلّقة/أجهزة) من snapshot | لا أزرار — بطاقات · تحميل DOMContentLoaded/focus | config, **sync-client-v4**, admin-api, crud-helper, admin-nav |
| `records.html` | البيانات المدخلة | قائمة سجلات + فلتر قسم | `btn-search` · chips `data-sec` (الكل/أولاً/ثانياً/ثالثاً) · `+ إدخال جديد` → `record-form.html?new=1` · نقر صف → `record-show.html?id=` | config, admin-api, crud, nav |
| `record-form.html` | إدخال/تعديل سجل | نموذج تبويبات (المعيل/أفراد/وفيات/وثائق) | تبويبات · `btn-add-member` · `btn-add-dead` · **`btn-save`** · `btn-cancel` · روابط `../upload.html` `../photography.html` · طابور `v4_record_outbox` | + categories-config |
| `record-show.html` | تفاصيل السجل | عرض تفصلي + تبويبات | **`btn-pdf`** · **`btn-del`** · تبويبات (المعيل/أفراد/وفيات/بنوك/مرفقات) · `← رجوع` | — |
| `search-records.html` | **البحث المتقدم** | بحث متعدد الفلاتر | `btn-search` · `btn-clear` · `btn-toggle-filters` · `btn-apply-filters` · `btn-reset-filters` · chips `data-type` | — |
| `categories.html` | إدارة التصنيفات | شبكة **21** تصنيفاً | بحث `#q` · بطاقات → `category.html?cat=KEY` | **categories-config** |
| `category.html` | تصنيف | CRUD لتصنيف واحد | `btn-new` · `btn-back` · تعديل/حذف صف عبر CrudHelper | — |
| `sponsorships.html` | إدارة الكفالات | قائمة (الكل/مكفول/غير مكفول) | chips · `btn-search` · **`btn-pdf` محلي** · **`btn-export` (يحتاج اتصالاً)** · روابط inline | + sync-client-v4 |
| `sponsors.html` | إدارة الجمعيات | CRUD جمعيات | `btn-new` · `btn-search` · تعديل/حذف | — |
| `users.html` | إدارة المستخدمين | إدارة مستخدمين | `btn-new` · `btn-search` · chips `data-role` | — |
| `user-requests.html` | إدارة طلبات المستخدمين | مراجعة طلبات | `btn-search` · chips `data-st` (الكل/جديد/قيد/مقبول/مرفوض) | — |
| `roles.html` | الأدوار والصلاحيات | CRUD أدوار | `btn-new` · `btn-refresh` | — |
| `permissions.html` | إدارة الصلاحيات والأدوار | قراءة manifest موقّع | لا أزرار — بطاقات + شارة (سليم/قريب/منتهي) + Online/Offline | + sync-client-v4 |
| `reports.html` | التقارير (توليد محلي) | تقارير Offline | **`btn-run`** · **`btn-pdf`** | + sync-client-v4 |
| `files.html` | إدارة الملفات والمرفقات | فهرس مرفقات | `btn-search` · أزرار صف `data-op=delete` · شارة Online/Offline | + sync-client-v4 |
| `folders.html` | إدارة المجلدات | تصفح/تحميل | `btn-search` · **`btn-zip`** · `data-open`/`data-view`/`data-dl` | — |
| `duplicates.html` | **الملفات المكررة** | إدارة تكرارات | `btn-search` · `btn-export` · **`btn-bulk`** · `data-view`/`data-del` | — |
| `attachment-audit.html` | **تدقيق المرفقات** | نظافة مرفقات | chips (مكررات/روابط مكسورة/يتيمة/بدون) · `btn-export` · `btn-clean` · `btn-refresh` | — |
| `civil-registry.html` | السجل المدني (عرض offline) | تصفح/بحث Offline | `btn-search` · شارة Online/Offline | + sync-client-v4 |
| `civil-import.html` | استيراد Excel — السجل المدني | استيراد Excel | `btn-template` · `btn-validate` · **`btn-import` (معطّل Offline)** · `btn-reset` | — |
| `profile.html` | الملف الشخصي | تعديل بياناتي | نموذج عبر `CrudHelper.renderForm` → `AdminAPI.updateProfile` | — |
| `settings.html` | الإعدادات | تفضيلات + بريد/كلمة مرور | 3 نماذج → `v4_prefs` · `AdminAPI.updatePassword` · `SyncClientV4.getSyncHealth` | — |

**`screens/` (6):**

| الملف | العنوان | الوظيفة | الأزرار |
|-------|---------|---------|---------|
| `sync-health.html` | لوحة صحة المزامنة | pending outbox/file ops/last pull/device | `btn-refresh` |
| `conflict-review.html` | **تعارضات البيانات** | حل تعارضات | `btn-load` · **`data-act`: دمج / إبقاء كحالتين / تجاهل الوارد** (معطّل Offline) |
| `audit.html` | **تدقيق السجلات** | بحث audit trail | `q-uuid` · `q-field` · `f-sens` · `btn-search` |
| `devices.html` | إدارة الأجهزة المسجَّلة | قائمة أجهزة | `btn-refresh` |
| `notifications.html` | مركز الإشعارات المحلي | إشعارات `v4_local_notifications` | chips · `btn-mark` · `btn-refresh` · `data-read` |
| `global-search.html` | **البحث السريع الموحّد** | بحث موحّد | `btn-search` · chips `data-src` (الكل/أشخاص/كفالات/ملفات/مدني) |

**لا يوجد أي `onclick=` في شاشات v4** — كل الوصل عبر `addEventListener` / `data-*`.

### 5.3 الشاشات المشتركة (v3 + v4) — 10 شاشات ميدانية Legacy

| الملف | العنوان | موجود في v3 | موجود في v4 |
|-------|---------|-------------|-------------|
| `index.html` | الحياة لتنمية الأسرة - الكفالات | ✅ | ✅ |
| `data.html` | الحياة - بيانات | ✅ | ✅ |
| `detail.html` | الحياة - تفاصيل الكفالة | ✅ | ✅ |
| `full-file.html` | الحياة - تعديل الملف | ✅ | ✅ |
| `photography.html` | الحياة - تصوير | ✅ | ✅ |
| `upload.html` | الحياة - رفع الملفات | ✅ | ✅ |
| `registration.html` | الحياة - تسجيل جديد | ✅ | ✅ |
| `barcode.html` | قارئ الباركود | ✅ | ✅ |
| `search.html` | بحث | ✅ | ✅ |
| `sync-monitor.html` | الحياة - متابعة المزامنة | ✅ | ✅ |

**وأيضاً في v4:** زر «لوحة التحكم» أُضيف إلى `assets/public/index.html` (يشير إلى `admin/dashboard.html`).

### 5.4 مكتبة JS الأساسية لشاشات v4

| الملف | السطور | التصدير | الدور |
|-------|--------|---------|-------|
| `admin-api.js` | 315 | `window.AdminAPI` | طبقة API موحّدة: `http()` + `withCache()` + bridge-first (dashboard/query/deleteFile/permissions) + ~30 دالة domain |
| `sync-client-v4.js` | 131 | `window.SyncClientV4` | يتحدث **فقط** مع `JavaScriptBridgeV4` + REST conflicts/devices — تعليق: «لا يلمس أي endpoint قديم من v3» |
| `admin-nav.js` | 162 | `window.AdminNav` | يحقن sidebar (9 أقسام / 26 رابطاً) + hamburger |
| `crud-helper.js` | 238 | `window.CrudHelper` | `$`, `toast`, `renderTable`, `renderForm`, `showModal`, `renderPagination`, `setOnlineBadge` |
| `categories-config.js` | 34 | `window.CATEGORIES` | **21** تعريف `{key,name,icon,fields}` |
| `config.js` | 4 | `window.APP_CONFIG` | `BASE_URL/API_URL` → `https://alhayahorphans.org[/api]` |
| `token-interceptor.js` | 133 | `class TokenInterceptor` | **فقط في assets Android** (ليس في mobile-app-v4/src): Bearer + refresh صامت على 401 عبر `POST /mobile/refresh-token` + طابور `failedQueue` + `forceLogout` |

---

## 6) الأمان والصلاحيات

### 6.1 صلاحيات AndroidManifest.xml

**الملفان متطابقان حرفاً بحرف** (179 سطراً، MD5 متطابق بين v3 وv4).

| الصلاحية | الحالة |
|----------|--------|
| `INTERNET` | ✅ |
| `ACCESS_NETWORK_STATE` / `ACCESS_WIFI_STATE` / `CHANGE_NETWORK_STATE` | ✅ |
| `WAKE_LOCK` | ✅ |
| `FOREGROUND_SERVICE` + `FOREGROUND_SERVICE_DATA_SYNC` + `_CAMERA` + `_MEDIA_PROCESSING` | ✅ |
| `POST_NOTIFICATIONS` | ✅ |
| `RECEIVE_BOOT_COMPLETED` | ✅ |
| `SCHEDULE_EXACT_ALARM` / `USE_EXACT_ALARM` | ✅ |
| `REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` | ✅ |
| `WRITE_EXTERNAL_STORAGE` (maxSdk 32) / `READ_EXTERNAL_STORAGE` (maxSdk 32) | ✅ |
| `READ_MEDIA_IMAGES` / `READ_MEDIA_VIDEO` | ✅ |
| **`MANAGE_EXTERNAL_STORAGE`** | ✅ (تتطلب تبريراً في Google Play) |
| `CAMERA` | ✅ |
| `RECORD_AUDIO` | ✅ |
| **`READ_PHONE_STATE`** | ✅ |
| **`READ_CALL_LOG`** | ✅ (حساسة جداً — مرتبطة `IncomingCallReceiver`) |
| **`ANSWER_PHONE_CALLS`** | ✅ |
| **`SYSTEM_ALERT_WINDOW`** | ✅ (overlay) |
| `usesCleartextTraffic="true"` | ✅ |
| `requestLegacyExternalStorage="true"` | ✅ |
| `allowBackup="true"` | ✅ |
| `webContentsDebuggingEnabled` (capacitor config) | ✅ |
| `EnableSafeBrowsing = false` | ✅ |

**المكوّنات المُصرَّح بها (متطابقة):** `MainActivity` (singleTask, LAUNCHER) · `CameraActivity` · `BarcodeScannerActivity` · `PhotoActivity` · `ChromiumInitProvider` · `FileProvider` · Services: `DataSyncForegroundService`, `RealtimeSyncService`, `DownloadForegroundService`, `CallerInfoService` · Receivers: `DataSyncBootReceiver`, `RealtimeSyncBootReceiver`, `UploadBootReceiver`, `IncomingCallReceiver`.

**الفرق بين النسختين في Manifest: لا شيء.**

### 6.2 آلية التوكن/الجلسات الفعلية

**الطرف الخادم (Laravel):**
- **Sanctum** (`auth:sanctum`) على كل `routes/api_v4.php` الـ60 + معظم `api.php` mobile.
- `POST /api/mobile/login` (عام) → توكن · `POST /api/mobile/refresh-token` (عام) → تجديد عبر `refresh_tokens` table (token 64 hex, expires_at).
- جدول `personal_access_tokens` (Laravel Sanctum).
- v4 endpoints تحديداً: `auth:sanctum` + `throttle:api-v4` (120/min).

**الطرف العميل (JavaScript):**

| الملف | آلية |
|-------|------|
| `token-interceptor.js` (assets فقط) | `interceptRequest()` يحقن `Authorization: Bearer` من `localStorage auth_token` · على 401: single-flight refresh عبر `refresh_token` → `POST /mobile/refresh-token` · طابور `failedQueue` يمنع race · فشل → `forceLogout()` (يحذف الرمزين + حدث `auth-expired`) |
| `admin-api.js` | `getToken()` = `localStorage api_token` · يحقنها في كل `http()` call |
| `sync-client-v4.js` | يعتمد على نفس `localStorage api_token` للـ REST القليلة |
| Java (Orchestrator) | `SharedPreferences "auth_prefs" → "api_token"` |

**ملاحظة ازدواج مفاتيح:** `auth_token` (interceptor) ≠ `api_token` (admin-api) — مخزّنان في localStorage بمفتاحين مختلفين.

**توكنات Java → Server:** Bearer من SharedPreferences مباشرة (بلا refresh في Orchestrator — عند 401 يرجع `unauthorized` ويتوقف Worker).

### 6.3 ملاحظات أمنية من قراءة الكود (تُذكر ولا تُصلَّح)

> ما يلي **ملاحظات رصدية فقط** بطلب التقرير — لم يُجرَ أي تعديل.

| # | الملاحظة | الموقع | الخطورة النظرية |
|---|----------|--------|-----------------|
| 1 | **`READ_CALL_LOG` + `ANSWER_PHONE_CALLS` + `IncomingCallReceiver`** — قراءة سجل المكالمات غير مبرَّرة وظيفياً لتطبيق كفالات في الكود المرئي | Manifest سطر 173–174 + `IncomingCallReceiver.java` | عالية (سياسات Play + خصوصية) |
| 2 | **`MANAGE_EXTERNAL_STORAGE`** (كل الملفات) مع `requestLegacyExternalStorage` | Manifest سطر 168–169 | عالية |
| 3 | **`usesCleartextTraffic="true"` + `server.cleartext: true` + network_security_config يسمح LAN** | Manifest سطر 18 + `capacitor.config.json` | متوسطة (خطر MITM على Wi-Fi عام) — مقبول معلناً للاختبار |
| 4 | **`webContentsDebuggingEnabled: true`** في release config | `capacitor.config.json` سطر 44 | متوسطة (Chrome DevTools يستطيع تفكيك WebView) |
| 5 | **`EnableSafeBrowsing=false` + `MetricsOptOut`** | Manifest meta-data سطر 28–34 | منخفضة |
| 6 | **`allowBackup="true"`** — نسخ احتياطي للـ SQLite المحلي (قمع بيانات حساسة) | Manifest سطر 7 | متوسطة |
| 7 | **مسار بحث عام بلا auth في `api.php`:** closures `/api/search/ultra-fast|lightning|exact|smart|...` **بدون middleware auth** | `routes/api.php` | عالية (تسريب محتمل) |
| 8 | **`duplicate-files` + `gallery` + `person-name` endpoints عامة بلا auth** | `routes/api.php` | متوسطة–عالية |
| 9 | **التوكن في `localStorage`** (أ不去 WebView data) — أي XSS يسرقه | `admin-api.js`, `token-interceptor.js` | متوسطة |
| 10 | **fallback خطر في `IdempotencyKeyGeneratorV4`:** عند فشل SHA-256 يرجع `Integer.toHexString(input.hashCode())` — ضعيف جداً (32-bit) لكنه مقصود «stability» | `IdempotencyKeyGeneratorV4.java` سطر 41 | منخفضة (فشل SHA-256 نظري) |
| 11 | **`AndroidBridge`/`CameraBridge` عبر `addJavascriptInterface`** — كل JS داخل WebView يستطيع استدعاء Java | `MainActivity.java` | متوسطة (يعتمد على مصدر HTML) |
| 12 | **صفوف بحث قديمة بـ raw SQL/Service بدون binding واضح** في closures القديمة | `routes/api.php` | يجب فحصها — **لم يُجرَ فحص SQLi تفصيلي هنا** |
| 13 | **`X-Sync-Source` و`X-Device-Id` headers غير موقّعة** — يُصدَّق بها من العميل دون تحقق توقيع (التوكن هو الحارس الوحيد) | Orchestrator headers | منخفضة |
| 14 | **HMAC manifest يعتمد على `config('app.key')`** — جيد، لكن `app.key` إذا تسرب يسمح بتزوير الصلاحيات الموقّعة | `AdminOfflineExportControllerV4` | متوسطة |
| 15 | **`MANAGE_EXTERNAL_STORAGE` + `file_paths` FileProvider** معاً — توسيع غير لازم للوصول | Manifest + `res/xml/file_paths` | منخفضة |
| 16 | **`SecureFileController` / `SecureFileAccess` / `FileSecurityHelper` = ملفات فارغة (0 سطر)** — لا حماية ملفات فعلي مُستخدَم | `app/Http/Controllers/SecureFileController.php` و giâضها | عالية (وظيفة لم تُنفَّذ) |
| 17 | **`BlockSuspiciousStoragePaths` يستخدم `stripos` وليس regex** — أنماط `.*` لا تعمل كتعبير نمطي | `BlockSuspiciousStoragePaths.php` | منخفضة (سلوك متوقع موثّقاً في `SecureFileSystemTest`) |

**ما لم يُوجد في الكود (صراحة):**
- **لا يوجد تشفير للبيانات على مستوى التطبيق** (لا SQLCipher رغم أن `libsqlcipher.so` يُحزَم في APK — **لا يوجد استدعاء `System.loadLibrary("sqlcipher")` أو `PRAGMA key`** في أي Helper — القواعد plaintext).
- **لا يوجد Certificate Pinning.**
- **لا يوجد Root/Emulator detection** (ما عدا رفض ANDROID_ID المعروف للمحاكي في `DeviceIdentityManagerV4`).
- **لا يوجد ProGuard rules مخصّصة لـ Capacitor** تُقرأ هنا (يوجد `proguard-rules.pro` لكن لم يُفحص محتواه في هذه الجولة).
- **لا يوجد biometric lock ولا PIN للتطبيق.**

---

## ملحق أ: فهرس ملفات v4 الـ16 الجديدة

```
android-v4/app/src/main/java/com/aso/app/v4/
├── sync/
│   ├── UnifiedSyncOrchestratorV4.java   (454 سطراً)
│   ├── SyncWorkerV4.java                (63)
│   ├── SyncSchedulerV4.java             (98)
│   ├── SyncOutboxManagerV4.java         (73)
│   ├── RemoteConfigManagerV4.java       (141)
│   ├── ProfilePhotoSyncManagerV4.java
│   ├── IdempotencyKeyGeneratorV4.java   (44)
│   ├── DeviceIdentityManagerV4.java     (82)
│   └── ConflictResolverV4.java          (74)
├── db/
│   ├── SyncDatabaseHelperV4.java        (272)
│   └── AdminOfflineDatabaseHelperV4.java(402)
├── bridge/
│   └── JavaScriptBridgeV4.java          (216)
└── admin_offline/
    ├── AdminOfflineDataStoreV4.java
    ├── ReportsEngineV4.java
    ├── PermissionsCacheManagerV4.java
    └── FileManagementOfflineQueueV4.java
```

## ملحق ب: إحصاءات سريعة

| المقياس | القيمة |
|---------|--------|
| ملفات Java v3 / v4 | 55 / 71 |
| Plugins Capacitor مخصّصة v3 / v4 | 8 / 9 |
| قواعد SQLite محلية (v3+v4) | 5 + 2 = **7** |
| Room? | **لا** |
| Workers v3 / v4 | 8 / 1 |
| Services v3 | 5 |
| Receivers | 4 |
| مسارات `api.php` (تقريب) | ~102 |
| مسارات `api_v4.php` | **60** |
| Controllers إجمال / Api/V4 | 97 / 6 |
| Models | 49 |
| جداول MySQL | 78 (منها **9 v4**) |
| مig migrations 2026_* | 38 (منها **4 v4**) |
| شاشات HTML: v4 خاصة / مشتركة / web قديم | 28 / 10 / 21 |
| versionCode v3 / v4 | 110 / 200 |
| اختبارات v4 خضراء (2026-09-24) | **43/43** |

---

*انتهى التقرير — مُستخرج بالقراءة المباشرة للكود · 2026-09-24*
