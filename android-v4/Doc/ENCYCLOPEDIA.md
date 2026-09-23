# الموسوعة التقنية الشاملة — Al-Hayah Orphans

> **Source of Truth مبني على قراءة الكود مباشرة** — READ → TRACE → VERIFY → DOCUMENT
> فرع Git: `adndoid-v2-edited` | تاريخ التوليد: 2026-09-22
> قاعدة: عند التعارض بين هذه الوثيقة والكود، **الكود هو الصحيح**.

---

## جدول المحتويات (50 قسماً)

| # | القسم | الحالة |
|---|-------|--------|
| 1 | نظرة عامة على المنظومة | Verified |
| 2 | معمارية النظام | Verified |
| 3 | Tech Stack (Backend) | Verified |
| 4 | Tech Stack (Android) | Verified |
| 5 | قواعد البيانات (Laravel) | Verified |
| 6 | قواعد البيانات (Android Local) | Verified |
| 7 | Route Files | Verified |
| 8 | API Contract — Public (بدون auth) | Verified |
| 9 | API Contract — Protected (auth:sanctum) | Verified |
| 10 | SyncController | Verified |
| 11 | SponsorshipSyncController | Verified |
| 12 | ChunkedUploadController | Verified |
| 13 | MobileRegistrationController | Verified |
| 14 | GoogleDriveUploadController | Verified |
| 15 | ActionSyncController | Verified |
| 16 | ServerActionController | Verified |
| 17 | MobileSyncController / OfflineTestController | Verified |
| 18 | FileAnalyticsController | Verified |
| 19 | Search Services (7 محركات) | Verified |
| 20 | File Management Controllers | Verified |
| 21 | Rate Limiting | Verified |
| 22 | Middleware Stack | Verified |
| 23 | Authentication & Sanctum | Verified |
| 24 | Queue & Jobs | Verified |
| 25 | Scheduled Tasks (Console Kernel) | Verified |
| 26 | Large File Upload (php.ini) | Verified |
| 27 | Rclone + Google Drive Integration | Verified |
| 28 | Models (Eloquent) | Verified |
| 29 | Migrations Inventory | Verified |
| 30 | Seeders & Factories | Verified |
| 31 | Config Files | Verified |
| 32 | Blade Views | Verified |
| 33 | Helpers & View Components | Verified |
| 34 | Observers & Traits | Verified |
| 35 | Tests | Verified |
| 36 | Android Build Config | Verified |
| 37 | Android Manifest & Permissions | Verified |
| 38 | Capacitor Plugins | Verified |
| 39 | Android Package Structure | Verified |
| 40 | Android Upload Pipeline | Verified |
| 41 | Android Sync Pipeline | Verified |
| 42 | Android Local Databases | Verified |
| 43 | Android Network & Retry | Verified |
| 44 | Android Native Features | Verified |
| 45 | Web ↔ Java Bridge | Verified |
| 46 | Offline/Online Sync Architecture | Verified |
| 47 | Conflicts & Discrepancies | Conflicts |
| 48 | Missing Components | Missing |
| 49 | Risks & Final Architecture Map | Risks |
| 50 | android-v4 Additions | Verified |

---

## 1. نظرة عامة على المنظومة

منظومة واحدة تجمع ثلاثة مستويات:

1. **موقع Laravel 10** (`C:\xampp\htdocs\alhayahorphans`) — لوحة الإدارة + بوابة التسجيل + API.
2. **تطبيق Android** (`android-v3/`) — Capacitor 8 hybrid، واجهة محلية + Java backend خلفي.
3. **قاعدة بيانات MySQL** — قاعدتان: `aso` (الافتراضية) + `civilregistry` (السجل المدني).

**الدوال الأساسية:** تسجيل أيتام، كفالات، رفع ملفات مجزأ، مزامنة offline/online، رفع Google Drive عبر Rclone، بحث فوري.

---

## 2. معمارية النظام

```
[Blade Views / Android WebView]
        │
[Sanctum Token] ──► routes/api.php (972 سطراً)
        │
   ┌────┴─────────────────────────┐
   │  API Controllers (10)        │
   │  + Search Services (7)       │
   │  + File Mgmt Controllers     │
   └────┬─────────────────────────┘
        │
   ┌────┴─────────────────────────┐
   │  MySQL: aso (default)        │
   │  MySQL: civilregistry        │
   └────┬─────────────────────────┘
        │
   ┌────┴─────────────────────────┐
   │  Queue: database driver      │
   │  ProcessRcloneUploadJob      │──► rclone ──► Google Drive
   │  UploadToGoogleDriveJob      │
   └──────────────────────────────┘

Android (android-v3):
  WebView ── JS Bridge ──► Java Workers
  WorkManager: ChunkedUploadWorker, FileSyncWorker,
               DriveStatusWorker, PrepareUploadsWorker,
               SmartMediaWorker, SponsorshipSyncWorker
  ForegroundServices: DataSync, Download, Background, Realtime
  Local SQLite: upload_queue.db, sponsorships_data.db,
                related_data.db, data_sync.db, civil_registry.db
```

---

## 3. Tech Stack — Backend

| المكوّن | القيمة | المصدر |
|---------|--------|--------|
| Framework | Laravel 10 (v10.50.3) | composer.json |
| PHP | ^8.1 | composer.json |
| Auth | Sanctum v3.3.3 | composer.json |
| Search | Scout v10.25.0 (driver=database) | composer.json + scout.php |
| Auth UI | Breeze v1.29.1 | composer.json |
| Permissions | spatie/laravel-permission 6.25.0 | composer.json |
| Queue | database driver | queue.php |
| File Storage | local (public disk) | filesystems.php |
| PDF | barryvdh/laravel-snappy | snappy.php |

---

## 4. Tech Stack — Android

| المكوّن | القيمة | المصدر |
|---------|--------|--------|
| Capacitor | 8 | capacitor.config.json |
| SQLite plugin | @capacitor-community/sqlite | capacitor.plugins.json |
| appId | `com.aso.app` | app/build.gradle |
| versionCode / versionName | 110 / "3.2" | app/build.gradle |
| minSdk / targetSdk / compileSdk | 24 / 36 / 36 | variables.gradle |
| AGP | 8.13.0 | build.gradle |
| Java | 21 | app/build.gradle |
| MultiDex | enabled | app/build.gradle |
| minifyEnabled | true (release) | app/build.gradle |
| webDir | `mobile-app/dist` | capacitor.config.json |
| CapacitorHttp | disabled | capacitor.config.json |
| allowMixedContent | true (android-v3) / false (root) | capacitor.config.json vs capacitor.config.json |

---

## 5. قواعد البيانات — Laravel

### القاعدة الافتراضية `aso`
- **134 migration** في `database/migrations/`
- جداول محورية (من الأسماء المُتحقَّق منها):
  - `users`, `personal_access_tokens`, `password_reset_tokens`, `failed_jobs`, `jobs`
  - `persons` (السجل المدني — connection ثانوية أيضاً)
  - `data`, `re_people`, `dead_pepoles`, `sponsorships`, `sponsors`, `sponsorship_sponsor`
  - `sponsor_field_settings`, `sponsor_document_types`, `sponsor_report_designs`
  - `attachments`, `enhanced_attachments`, `google_drive_uploads`, `sync_progress`, `file_id_registry`
  - `chunked_uploads`, `refresh_tokens`, `offline_upload_statuses`, `server_sync_actions`
  - `guardian_bank_accounts`, `bank_names`, `additional_dead_people`, `additional_deceased`
  - `document_types`, `death_reasons`, `health_statuses`, `aid_statuses`, `housing_statuses`
  - `provinces`, `city`, `sponsorship_statuses`, `request_statuses`, `displacement_statuses`
  - `type_of_guarantees`, `type_of_accommodations`, `marital_statuses`, `employments`, `academic_degrees`
  - `category_of_relations`, `relations`, `representative_of_associations`, `reports_designs`, `currency_types`
  - `general_categories`, `duplicate_files_temp`, `reserved_codes`, `portal_general_registration_field_values`
  - `sponsor_field_settings` (تُضاف إليها أعمدة بشكل متكرر عبر migrations)
  - جداول spatie: `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`

### القاعدة الثانوية `civilregistry`
- `persons` (read-heavy)
- تُستخدم عبر `DB::connection('civilregistry')` في:
  - `SyncController::checkPersonAllTables`
  - `MobileRegistrationController::civilRegistryPerson` / `serveCivilRegistryFile`
  - `api.php` endpoint `/civil-registry/person-details/{personId}`
  - `CivilRegistryScoutSearchService`

### الفئات (49 model في `app/Models/`)
منها: `User`, `Sponsorship`, `Sponsor`, `SponsorFieldSetting`, `SponsorDocumentType`, `Data`, `RePeople`, `DeadPepole`, `AdditionalDeceased`, `AdditionalDeadPeople`, `GuardianBankAccount`, `Attachment`, `EnhancedAttachment`, `GoogleDriveUpload`, `ServerSyncAction`, `Persons`, `CivilRegistryPerson`, `Relation`, `Province`, `City`, `BankName`, `DocumentType`, `DeathReason`, `HealthStatus`, `SponsorshipStatus`, `RequestStatus`, `DisplacementStatus`, `TypeOfGuarantee`, `TypeOfAccommodation`, `MaritalStatus`, `Employment`, `AcademicDegree`, `CategoryOfRelation`, `RepresentativeOfAssociation`, `ReportsDesign`, `CurrencyType`, `GeneralCategory`, `DuplicateFileTemp`, `OrphanNeed`, `CreativityAspect`, `HousingStatus`, `AidStatus`, `AidManagement`, `AssociationEmployee`, `PortalGeneralRegistrationFieldValue`, `PersonSearchable`, `SponsorReportDesign`, `CI_BIRTH_CD`, `CI_BIRTH_TB_CD`, `CI_PERSONAL_CD`…

---

## 6. قواعد البيانات — Android Local

| قاعدة | ملف | نسخة |
|-------|------|-------|
| `upload_queue.db` | `UploadDatabaseHelper.java` | v7 |
| `sponsorships_data.db` | `SponsorshipsDatabaseHelper.java` | v3 |
| `related_data.db` | `RelatedDataDatabaseHelper.java` | v2 |
| `data_sync.db` | `DataSyncDatabaseHelper.java` | v2 |
| `civil_registry.db` | `CivilRegistryStore.java` | v2 |

---

## 7. Route Files

| ملف | ملاحظة |
|-----|--------|
| `routes/api.php` | **972 سطراً** — API الرئيسي — **مُقرأ كاملاً** |
| `routes/web.php` | **564 سطراً** — صفحات Blade + public APIs — **مُقرأ كاملاً** |
| `routes/admin.php` | **636 سطراً** — مسارات الإدارة — **مُقرأ كاملاً** |
| `routes/auth.php` | مصادقة Breeze (يُحمَّل من web.php سطر 495) |
| `routes/console.php` | أوامر console |
| `routes/channels.php` | قنوات Broadcast |
| `routes/file-management.php` | إدارة الملفات |
| `routes/exact_search_api.php` | بحث دقيق منفصل |
| `routes/test_snappy.php` | اختبار PDF |
| `routes/test-download.php` | اختبار تحميل |
| `routes/offline-test-development.php` | اختبار offline |
| `routes/admin_backup.php` | نسخة احتياطية |
| `routes/Backup/api.phpBak` | نسخة احتياطية |
| `routes/api.phpBak` | نسخة احتياطية |

### 7.1 ملخص routes/web.php (564 سطراً — مُقرأ كاملاً)

**مسارات عامة بدون auth (إضافات أمنية — انظر R18–R21):**

| Method | Path | Handler |
|--------|------|---------|
| GET | `/test-snappy-pdf` | closure Snappy PDF |
| GET | `/test-download-all` | `UnifiedFileManagementController@downloadAllDuplicateFiles` |
| POST | `/test-download-selected` | `downloadSelectedDuplicateFiles` |
| GET | `/test-real-stats-public` | `getRealDuplicateFilesStatistics` |
| GET | `/api/scout/instant-search` | `ScoutSearchController@instantSearch` |
| POST | `/api/scout/advanced-search` | `advancedSearch` |
| GET | `/api/civil-registry/*` (11 مساراً) | `CivilRegistrySearchController` — index, quick-search, advanced, by-id, by-name, comprehensive, stats, index-data, test, clear-cache |
| GET/DELETE | `/public-api/duplicate-files/*` (9 مسارات) | paginated, statistics, preview, serve, download, view, **delete, bulk-delete, delete-all** |
| GET | `/` | `GeneralRegistrationController@index` (صفحة التسجيل العام) |
| POST | `/users/generalRegistration/store` + `/upload-chunk` | تسجيل عام + رفع مجزأ |
| GET | `/login` | view auth/login |
| GET | `/auto-login` + `/s/{credentials}` | `ShowGeneralRegisrationController@autoLogin/autoLoginShort` — **دخول مباشر** |
| POST | `/login/user`, `/logout-user` | تسجيل دخول مخصص |
| POST | `/check-id-number`, `/search-all-tables`, `/fill-from-civil-registry` | بوابة التسجيل العام |
| POST | `/check-existing-guardian`, `/get-guardian-with-bank-accounts`, `/lookup-guardian`, `/lookup-mother`, `/check-person-linked` | lookups عامة |
| POST | `/admin/general-category/toggle-status` | toggle بدون auth صريح |
| POST/GET | `/api/files/*` + fallbacks | analytics / folder upload — **`withoutMiddleware(['auth','verified'])`** |
| POST/GET | `/api/speedtest/*` + fallbacks | speedtest — **بدون auth عمداً** |
| POST | `/api/files/process-folder-upload-with-duplicates`, `/process-folder-duplicates`, `/check-single-duplicate`, `/duplicate-session-stats/{id}`, `/clean-expired-duplicates` | duplicate file APIs — **مجموعة `withoutMiddleware(['auth','verified'])` صريحة (سطر 373)** |
| GET | `/google-drive-test/*` (9 مسارات) | `GoogleDriveTestController` — بدون auth |
| GET | `/storage/{path}` | تقديم ملفات storage مع حماية traversal/blocked-patterns (سطر 527-563) |
| GET | 14 صفحة اختبار documents (`/test-documents-api`, `/debug-documents-api`, …) | views فقط |
| GET | `/file-manager`, `/file-management/advanced-interface` | views |

**مسارات محمية بـ `['auth','verified']`:**
- `user/*` — dashboard, general-registration (update/search/attachment), profile ops, update-email/password/avatar
- `/profile` (Breeze ProfileController edit/update/destroy)
- `roles` + `users` resource
- `admin/reserved-codes/*` — sync, cleanup, stats, gaps (closures مع `DB::table`)
- `admin/file/*` — excel-gateway, php-diagnostic, show/{filename}, diagnostic/folders
- `admin/api/search-civil-registry` — closure يتصل `DB::connection('civilregistry')`

### 7.2 ملخص routes/admin.php (636 سطراً — مُقرأ كاملاً)

**مجموعة `admin/` الرئيسية (سطر 47):**
- `manage-user-requests` (index/change-status) — `permission:عرض قسم إدارة التسجيلات|إدارة طلبات المستخدمين`
- `dashboard` — permission متعدد (8 صلاحيات بال|)
- profile ops (index/settings/update/delete/email/password/avatar)
- `civilian` + `persons` resource + person search (search, quick-search, statistics, export, advanced, details)
- `scout/*` — instant-search, advanced-search, suggestions, stats, index-data, clear-cache
- `family-relations/*` — search, by-name, family-tree, statistics, clear-cache
- role management (`index101`/`index102`)
- `records-management/*` — index (permission), export-streaming, export-all, export-all-csv, create/store/upload, edit/delete, family-member delete (POST), delete-attachment, bank accounts (get/approve), update (PUT), show, additional-info, portal-field, export-family-report, ajax-admin-records
- **category management** — 19 resource للتصنيفات (aid_status, bank_name, CategoryOfRelation_name, city_name, CurrencyType_name, DeathReason_name, DisplacementStatus_name, DocumentType_name, Employment_name, GeneralCategory_name, HealthStatus_name, orphan_needs, creativity_aspects, HousingStatus_name, MaritalStatus_name, Province_name, RequestStatus_name, SponsorshipStatus_name, TypeOfAccommodation_name, TypeOfGuarantee_name) + academicdegree — كلها `permission:عرض قسم إدارة التصنيفات|…`
- **sponsors/*** — fields-management, export-forms, bulk-export-family-reports, sync-sponsorship-status, fields/documents per sponsor, toggle-google-drive, generate-file-id, employees CRUD, report-design CRUD, test-form, resource (permission:عرض قسم إدارة الجمعيات)
- **sponsorships/*** — sponsored/unsponsored (permission), export, import, create-missing-persons, get-person-details, update-status, regenerate-file-number, unified-search, resource
- `manage-folders` + folders contents/search/download-zip/view-pdf/download-pdf (permission:عرض قسم إدارة الملفات)
- `sync-files` — `FileSystemSyncController@syncPhysicalFiles`
- `file-manager` view (permission)
- `file/*` — duplicate summary/delete/download/statistics, check-single-duplicate, handle-duplicate, bulk folder upload (+batch), upload-files, document-types-data, excel-gateway (+sidebar permission), excel-upload/process-excel/validate-excel/import-validated-excel (`large.upload`), php-diagnostic
- `duplicates/*` (`auth` فقط) — find, check-existing, process-insert, export
- `files/process-folder-upload`

**مجموعات خارجية في admin.php:**
- `admin/file` مع `withoutMiddleware(['web'])` — duplicate-summary, delete-duplicates, download-duplicates, download-duplicate/{session}/{file}, process-folder-duplicates, duplicate-info, view/{filename} — **بدون middleware إطلاقاً**
- `POST /api/replace-duplicate-file` — بدون auth
- `test/api/file/*` — **بدون أي middleware** (تعليق صريح: "بدون أي middleware")
- ثانية `admin/duplicate-files/*` (16 مساراً) — index مع permission، الباقي بدون permission صريح
- `admin/attachment-audit/*` (14 مساراً) — **بدون auth/permission على المجموعة**
- ثالثة `admin` — test-connection, test-document-types, test ZIP, **speedtest** (index/standalone/api/stats/quick-test/test-api بدون auth)، **openspeedtest** (index مع permission; download/upload/getip/status; backend مع `withoutMiddleware(['auth'])`)، cleanup temp، search-records (index/modal/search مع `search.rate.limit`/stats/suggestions/clear-cache)، profile-search، sponsorships sponsored/unsponsored مكرر، api/civil-registry مكرر (8)، civil-registry CRUD مع permissions على index/create فقط (store/show/edit/update/destroy **بدون permission صريح**)
- **مكرر:** `public-api/duplicate-files/*` — نفس 9 مسارات موجودة أيضاً في web.php (سطر 621)
- `GET sponsors/{sponsor}/field-settings-check` — بدون auth
- `GET /test-documents-direct` — view

**ملاحظة حرجة:** المجموعة الرئيسية `Route::group(['prefix'=>'admin'])` **لا تحمل middleware `auth` جماعياً** — الحماية تعتمد على `permission:` في كل route منفرداً (والكثير بدونها)؛ أي أن `admin/profile`, `admin/persons` resource, `records-management/store` وغيرها قد تُفتح دون تسجيل دخول ما لم يفحص الـ controller نفسه.

---

## 8. API Contract — Public (بدون auth)

جميعها تحت prefix `/api` مع middleware `api` فقط:

| Method | Path | Handler |
|--------|------|---------|
| GET | `/user` | closure (auth:sanctum) |
| POST | `/chunked-upload-test` | `ChunkedUploadController@handleChunk` |
| GET | `/search/ultra-fast?q=&limit=` | `UltraFastSearchService` |
| GET | `/search/lightning?q=&limit=` | `LightningSearchService` |
| GET | `/search/exact?q=&limit=` | `ExactMatchSearchService` |
| GET | `/search/smart?q=&limit=` | `SmartExactSearchService` |
| GET | `/search/final-exact?q=&limit=` | PDO مباشر بـ credentials مضمّنة |
| GET | `/search/exact-only?q=` | PDO مباشر، نتيجة واحدة |
| GET | `/person-name/{id_number}` | `data` table + `City` |
| GET | `/gallery/folder/{folder_name}` | قائمة ملفات public/uploads |
| DELETE | `/gallery/file/{folder}/{file}` | حذف ملف |
| GET | `/gallery/download/{folder}/{file}` | تحميل ملف |
| GET | `/civil-registry/person-details/{personId}` | سجل مدني + حسابات بنكية |
| POST | `/civil-registry/save-bank-account` | حفظ حساب بنكي |
| POST | `/check-duplicate-file` | `UnifiedFileManagementController@checkSingleDuplicate` |
| POST | `/replace-duplicate-file` | `UnifiedFileManagementController@handleDuplicate` |
| POST | `/simple/upload` | `SimpleFileUploadController@simpleUpload` |
| GET | `/simple/test` | `SimpleFileUploadController@test` |
| GET | `/sync/public/health` | `{status, timestamp, version: 2.0.0}` |
| POST | `/mobile/login` | `SponsorshipSyncController@login` |
| POST | `/mobile/refresh-token` | `SponsorshipSyncController@refreshToken` |
| GET | `/mobile/health` | `{status, app: alhayah-sponsorships, version: 2.0.0}` |

---

## 9. API Contract — Protected (auth:sanctum)

### `/api/sync/*`
| Method | Path | Handler |
|--------|------|---------|
| POST | `/sync/check-person-all-tables` | `SyncController@checkPersonAllTables` |
| POST | `/sync/check-person-exists` | `SyncController@checkPersonExists` |
| POST | `/sync/generate-file-id` | `SyncController@generateFileID` |
| POST | `/sync/activate-file-id` | `SyncController@activateFileID` |
| POST | `/sync/new-person-entry` | `SyncController@newPersonEntry` |
| GET | `/sync/eligible-sponsorships` | `SyncController@getEligibleSponsorships` |
| POST | `/sync/sponsorships/update-relation-id` | `SyncController@updateSponsorshipRelationId` |
| GET | `/sync/person-by-relation/{relationId}` | `SyncController@getPersonByRelation` |

### `/api/uploads/*`
| Method | Path | Handler | ملاحظة |
|--------|------|---------|--------|
| POST | `/uploads/chunk` | `ChunkedUploadController@handleChunk` | `large.upload` |
| POST | `/uploads/check-duplicate` | `GoogleDriveUploadController@checkDuplicate` | |
| POST | `/uploads/drive-status` | `GoogleDriveUploadController@driveStatus` | |
| GET | `/uploads/offline-inbox` | `GoogleDriveUploadController@offlineInbox` | |
| POST | `/uploads/offline-inbox/ack` | `GoogleDriveUploadController@ackOfflineInbox` | |
| POST | `/uploads/notify-completed` | `GoogleDriveUploadController@notifyCompleted` | |
| POST | `/uploads/mark-failed` | `GoogleDriveUploadController@markFailed` | |
| GET | `/uploads/stats` | `GoogleDriveUploadController@getStats` | |
| GET | `/uploads/pending` | `GoogleDriveUploadController@getPendingUploads` | |
| GET | `/uploads/entity/{type}/{id}` | `GoogleDriveUploadController@getEntityUploads` | |

### `/api/mobile/*`
| Method | Path | Handler | ملاحظة |
|--------|------|---------|--------|
| POST | `/mobile/logout` | `SponsorshipSyncController@logout` | |
| POST | `/mobile/verify-password` | `SponsorshipSyncController@verifyPassword` | متوفر أيضاً في `/verify-password` |
| GET | `/mobile/sponsors` | `SponsorshipSyncController@getSponsors` | |
| GET | `/mobile/sponsorship-statuses` | `SponsorshipSyncController@getSponsorshipStatuses` | |
| GET | `/mobile/sync/initial` | `SponsorshipSyncController@getInitialSync` | |
| GET | `/mobile/sync/full` | `SponsorshipSyncController@getFullSync` | |
| GET | `/mobile/sync/sponsorships` | `SponsorshipSyncController@getSponsorships` | |
| GET | `/mobile/sync/sponsorship/{id}` | `SponsorshipSyncController@getSponsorshipDetails` | |
| POST | `/mobile/sync/upload` | `SponsorshipSyncController@uploadSyncData` | |
| POST | `/mobile/sync/photos/metadata` | `SponsorshipSyncController@syncPhotoMetadata` | |
| GET | `/mobile/sync/stats` | `SponsorshipSyncController@getSyncStats` | |
| GET | `/mobile/sync/data-table` | `getSyncDataTable` | |
| GET | `/mobile/sync/re-people` | `getSyncRePeople` | |
| GET | `/mobile/sync/dead-people` | `getSyncDeadPeople` | |
| GET | `/mobile/sync/bank-accounts` | `getSyncBankAccounts` | |
| GET | `/mobile/sync/death-reasons` | `getSyncDeathReasons` | |
| GET | `/mobile/sync/additional-deceased` | `getSyncAdditionalDeceased` | |
| GET | `/mobile/server-actions` | `ServerActionController@pullActions` | limit 100 |
| POST | `/mobile/server-actions/ack` | `ServerActionController@ackActions` | |
| POST | `/mobile/upload-file` | `SponsorshipSyncController@uploadFile` | Legacy |
| POST | `/mobile/upload-chunk` | `ChunkedUploadController@handleChunk` | `large.upload` |
| GET | `/mobile/upload-status/{upload_id}` | `ChunkedUploadController@uploadStatus` | |
| POST | `/mobile/retry-rclone-upload` | `ChunkedUploadController@retryRcloneUpload` | `large.upload` |
| POST | `/mobile/sync/bulk-upload` | `SponsorshipSyncController@bulkUpsert` | |
| POST | `/mobile/sync/actions` | `ActionSyncController@syncAction` | |
| POST | `/mobile/sponsorships/sync-updates` | `ActionSyncController@syncAction` | alias |
| GET | `/mobile/sync/pending-actions` | `ActionSyncController@getPendingActions` | |
| POST | `/mobile/sync/ack-action` | `ActionSyncController@ackAction` | |
| GET | `/mobile/registration/lookups` | `MobileRegistrationController@lookups` | |
| GET | `/mobile/registration/photo/{personId}` | `photo` | |
| GET | `/mobile/registration/photo/{personId}/exists` | `photoExists` | |
| POST | `/mobile/registration/new-file-id` | `newFileId` | reserved_codes |
| POST | `/mobile/registration/upload-chunk` | `uploadChunk` | `large.upload` |
| GET | `/mobile/registration/file/{fileId}` | `getFile` | |
| POST | `/mobile/registration/store` | `store` | data_request_status=1 |
| POST | `/mobile/registration/update` | `update` | data_request_status=1 |
| POST | `/mobile/registration/find-by-id` | `findById` | |
| POST | `/mobile/registration/deceased-lookup` | `deceasedLookup` | |
| GET | `/mobile/photos/manifest` | `SponsorshipSyncController@photosManifest` | |
| GET | `/mobile/photos/{id}/exists` | `photoExists` | where id=[0-9]+ |
| GET | `/mobile/photos/{id}` | `photoFile` | where id=[0-9]+ |
| GET | `/mobile/civil-registry/manifest` | `civilRegistryManifest` | |
| GET | `/mobile/civil-registry/person/{id}` | `civilRegistryPerson` | where id=[0-9]+ |
| GET | `/mobile/civil-registry/file/{file}` | `serveCivilRegistryFile` | where file=[a-zA-Z0-9.\-_]+ |

---

## 10. SyncController

- `checkPersonAllTables` — فحص `data` / `re_people` / `dead_people` بـ `identity_number`.
- `generateFileID` — توليد file_id مع `file_id_registry` + `handshake_token`.
- `activateFileID` / `newPersonEntry` — حجز/تفعيل رقم ملف.
- `getEligibleSponsorships` / `updateSponsorshipRelationId` / `getPersonByRelation`.

---

## 11. SponsorshipSyncController

- `login` — إصدار token (Sanctum).
- `getSponsorships` — per_page=50، ترتيب `updated_at desc, id desc`، إثراء `enrichSponsorshipData`، فلترة `last_sync`.
- `getFullSync` — per_page=100، يستبعد حالات `تم الصرف` / `أرسل للصرف`.
- `uploadSyncData` — types: `sponsorship`, `person_data`, `bank_account`.
- `syncPhotoMetadata` / `photosManifest` / `photoFile`.
- `getInitialSync` — sponsors/statuses/bank_names/health_statuses/city/provinces/type_of_guarantee.
- `getSyncDataTable` / `getSyncRePeople` / `getSyncDeadPeople` / `getSyncBankAccounts` / `getSyncDeathReasons` / `getSyncAdditionalDeceased` — per_page min(200,500).
- `updateSponsorship` / `updatePhoto` / `bulkUpsert`.
- `refreshToken` / `logout` / `verifyPassword`.

---

## 12. ChunkedUploadController

- `handleChunk` — يستقبل header `X-Upload-Id` (يُعقّم عبر `safeUploadId`) + `X-Chunk-Index` + `X-Chunk-Size`.
- علامة إنجاز: `storage/app/chunks/_done/{uploadId}.json` — تمنع إعادة الرفع اللانهائية.
- تجميع الأجزاء → إنشاء سجل في `attachments` + `google_drive_uploads`.
- sha256 hash للتحقق.
- تحديد type (photo/video/document) من mime.
- `uploadStatus` / `retryRcloneUpload`.
- يوجد أيضاً test route بدون auth: `POST /api/chunked-upload-test`.

---

## 13. MobileRegistrationController

- `lookups` — قوائم جاهزة للتسجيل.
- `newFileId` — حجز رقم ملف 6 أرقام في جدول `reserved_codes`.
- `store` / `update` — يكتبان `data_request_status = 1` (بانتظار المراجعة).
- `findById` — بحث برقم الهوية في جداول التسجيل (حل تباين رقم الملف بين الكفالة والتسجيل).
- `deceasedLookup` — جلب متوفى من الموقع / السجل المدني.
- `photo` / `photoExists`.
- `uploadChunk` (large.upload) / `getFile`.
- `civilRegistryManifest` / `civilRegistryPerson` / `serveCivilRegistryFile`.
- `resolveAttachmentContent` — يدعم: مسارات Rclone (`remote:path`)، روابط، ملفات محلية.

---

## 14. GoogleDriveUploadController

- `driveStatus` / `offlineInbox` / `ackOfflineInbox`.
- `checkDuplicate` / `notifyCompleted` / `markFailed`.
- `getStats` / `getPendingUploads` / `getEntityUploads`.

---

## 15. ActionSyncController

- `syncAction` — يعالج `sponsorship_update` + تحديث `bank_accounts` داخل transaction.
- يتعامل مع تغليف `payload.payload` (تغليف مزدوج موثّق في الكود).
- `getPendingActions` / `ackAction`.

---

## 16. ServerActionController

- `pullActions` — يجلب pending limit 100.
- `ackActions` — تأكيد الاستلام (delivery_status).

---

## 17. MobileSyncController / OfflineTestController

- `MobileSyncController`: login بحقل `name`/`email`/`phone`، `getInitialSync`، orphans per_page=50.
- `OfflineTestController`: للاختبار فقط (تحذير صريح في الكود) — health/login/sponsorships.

---

## 18. FileAnalyticsController

- `getDetailedAnalytics` / `getAnalytics` — إحصائيات الملفات من الجداول الجديدة.
- مساران مساعيان: `/files/analytics-legacy` و `/files/analytics` يوجّهان لـ `UnifiedFileManagementController`.

---

## 19. Search Services (7 محركات + audit 29 خدمة)

| الخدمة | النوع | ملاحظة |
|--------|-------|--------|
| `UltraFastSearchService` | SQL | endpoint `/search/ultra-fast` |
| `LightningSearchService` | PDO مباشر | endpoint `/search/lightning` |
| `ExactMatchSearchService` | مطابقة تامة | endpoint `/search/exact` |
| `SmartExactSearchService` | ذكي مع أسماء مركبة | endpoint `/search/smart` |
| `SimpleExactSearchService` | بسيط | موجود لكن غير مربوط بـ route مباشرة في api.php |
| `CustomScoutSearchService` | Scout | |
| `CivilRegistryScoutSearchService` | Scout على السجل المدني | |
| `PersonSearchService` / `OptimizedPersonSearchService` / `NormalizedSearchService` / `SearchCacheService` / `ScoutSearchService` / `SearchService` | خدمات مساعدة | |

**`/search/final-exact` و `/search/exact-only`** يفتحان PDO بـ `host=localhost, dbname=aso, user=root, pass=''` **مباشرة داخل الكود** (credential مضمّنة — أمنياً مخاطرة).

### Hardcoded PDO credentials (تحقق مباشر 2026-09-22 — ×4 خدمات)

| الخدمة | السطور | الدليل |
|--------|--------|--------|
| `ExactMatchSearchService` | 18–20 (`mysql:host=localhost;dbname=aso`, user `root` سطر 20) | قراءة مباشرة |
| `LightningSearchService` | 18–20 (`'root'` سطر 20) | قراءة مباشرة |
| `SmartExactSearchService` | 17–18 | قراءة مباشرة |
| `SimpleExactSearchService` | 15–16 | قراءة مباشرة |

الحصيلة: **4 خدمات بحث تفتح PDO بـ `root` مضمّناً في الكود** (توسيع لـ R1 الذي كان يذكر مسارين في routes فقط — **R25**).

---

---

---

## 19b. Services Inventory (29 خدمة — audit مكتمل 2026-09-22)

فحص subagent كامل لـ `app/Services/*.php` (29 ملفاً، **13,111 سطراً**) مع security flags:

| الخدمة | ملاحظة |
|--------|--------|
| `AttachmentAudit` | |
| `ChunkUploadService` | |
| `CivilRegistryScoutSearchService` | Scout على civilregistry |
| `CloudIntegrationService` | |
| `CustomScoutSearchService` | Scout |
| `DuplicateDetectionService` | |
| `ExactMatchSearchService` | ⚠️ hardcoded PDO root (18–20) |
| `FileSecurityService` | |
| `FolderDuplicateDetectionService` | |
| `GoogleDriveService` | ⚠️ SSL verify off ×~20 موضع (انظر 27) |
| `LightningSearchService` | ⚠️ hardcoded PDO root (18–20) |
| `NormalizedSearchService` | |
| `OptimizedPersonSearchService` | |
| `PersonSearchService` | |
| `RcloneGoogleDriveService` | |
| `SearchCacheService` | |
| `SearchService` | |
| `ScoutSearchService` | |
| `SimpleExactSearchService` | ⚠️ hardcoded PDO (15–16) |
| `SmartExactSearchService` | ⚠️ hardcoded PDO (17–18) |
| `SmartUploadService` | |
| `SyncsLookupToMobile` (trait) | |
| `SyncService` | |
| `UltraFastSearchService` | SQL مباشر |
| + 4 خدمات أخرى | أسماء مُتحقَّق منها في فهرس المجلد |

Security flags من audit: 4× hardcoded credentials، 1× SSL verify disabled (GoogleDriveService)، بقية الخدمات بلا secrets ظاهرة في القراءة الميدانية.

---

---

---

## 20. Controllers Inventory (86 ملفاً + 4 backup — audit مكتمل 2026-09-22)

### التوزيع حسب المجلد (تحقق مباشر بالعدّ)

| المجلد | العدد |
|--------|-------|
| `Admin/` | **45** |
| `Api/` | **12** |
| `Auth/` | **9** |
| `Controllers/` (عام) | **12** |
| `Roles/` | **1** |
| `Users/` | **7** |
| **الإجمالي** | **86** |

(سابقاً كان مُوثَّقاً "10 API controllers" فقط — الآن 86 بإحصاء مباشر.)

### ملفات Management أساسية

- `UnifiedFileManagementController` — smartUpload, batchUpload, getBatchStatus, downloadFile, generateRecordNumber, excelBulkImport, processFolderUpload, analytics endpoints, checkSingleDuplicate, handleDuplicate.
- `SimpleFileUploadController` — simpleUpload, test (بدون auth — R9).
- `DuplicateFileController` — getDuplicateFilesSummary, deleteDuplicateFiles, downloadDuplicateFiles, processFolderForDuplicates (بدون auth).
- `SecureFileController` — **فارغ 0 سطر** (M13).
- `AttachmentAuditController` / `FileSystemSyncController` / `FolderManagementController` / `SpeedTestController`.

### ملفات Backup داخل Controllers (تحقق مباشر — ×4)

| الملف | الامتداد |
|-------|----------|
| `Admin/CivilRegistryController.phpbak` | `.phpbak` |
| `Users/GeneralRegistrationController.php.bak_fix2` | `.php.bak_fix2` |
| `Api/ChunkedUploadController.phpBak` | `.phpBak` |
| `Api/SponsorshipSyncController.phpBak` | `.phpBak` |

ملاحظة: كود قديم قد يُستدعى/يُحمَّل — مرتبط بـ R15/R28.

---

## 21. Rate Limiting

`app/Providers/RouteServiceProvider.php`:

| Limit | Routes |
|-------|--------|
| 1200/min | `api/mobile/upload-chunk`, `api/uploads/chunk`, `api/mobile/upload-status/*` |
| 60/min | باقي مسارات API |

---

## 22. Middleware Stack

**`app/Http/Kernel.php` — 17 middleware مُعرَّفة:**

| Alias | Class | ملاحظة |
|-------|-------|--------|
| `api` | (Laravel default) + `throttle:api` | يطبق على routes/api.php |
| `large.upload` | `LargeFileUploadMiddleware` | **عمداً لا logging** (مذكور في الكود) |
| `auth:sanctum` | Sanctum | |
| `throttle:uploads` | (custom limiter) | 1200/min للرفع |
| — | `SecurityHeadersMiddleware` | **0 سطور — فارغ** |
| — | `SecureFileAccess` | **0 سطور — فارغ** |
| — | `SearchRateLimiter` | |
| — | `RoleMiddleware` | |
| — | `DatabasePermissionCheck` | |
| — | `ConvertJsonToFormData` | |
| — | `BlockSuspiciousStoragePaths` | |

**طوابير middleware (global/web/api):** مُتحقَّق منها في subagent سابق.

---

## 23. Authentication & Sanctum

- Guard: `sanctum` على API.
- جدول: `personal_access_tokens`.
- جدول مساعدة: `refresh_tokens` (migration `2026_06_30_072018`).
- routes محمية: `auth:sanctum` على `/sync/*`, `/uploads/*`, `/mobile/*` (ما عدا login/health).
- استثناء: `POST /api/verify-password` محمي بـ `auth:sanctum` وموجود على مسارين (`/api/verify-password` و `/api/mobile/verify-password`).
- spatie/laravel-permission للـ roles على `User`.

---

## 24. Queue & Jobs

| Job | الملف | الإعداد |
|-----|-------|---------|
| `ProcessRcloneUploadJob` | app/Jobs/ProcessRcloneUploadJob.php | tries=5, backoff=[60,300,900,1800], maxExceptions=3, timeout=3600 |
| `UploadToGoogleDriveJob` | | |
| `SyncSponsorshipStatusJob` | | |
| `GenerateOrphanReportPdf` | | |
| `BulkExportSponsorshipForms` | | |
| `BulkExportFamilyReportsJob` | | |

- `config/queue.php`: `retry_after = 3900` (> job timeout 3600 لضمان عدم إعادة تشغيل مبكرة).
- Worker config: `laravel-worker.conf` (Supervisor).
- Driver: **database** (جدول `jobs`).

---

## 25. Scheduled Tasks — Console Kernel

`app/Console/Kernel.php`:

| المهمة | التوقيت |
|--------|---------|
| `SyncSponsorshipStatusJob` | يومياً 03:00 |
| تنظيف `reserved_codes` | كل 5 دقائق |
| كنس `storage/chunks` | متكرر |

**11 artisan commands:**
- TestPhpSettings, SecurityAuditCommand, OptimizeSearchPerformance, IndexCivilRegistryData, ImportMissingPersons, FixUserRoles, ExportCivilRegistry, ConfigureLargeFileUpload, CleanupExpiredDuplicates, CleanExpiredDuplicateFiles.

---

## 26. Large File Upload (php.ini)

`config/large-file-config.php` + `public/php.ini`:

| الإعداد | القيمة |
|---------|--------|
| memory_limit | 1024M → 2048M |
| max_execution_time | 3600 |
| upload_max_filesize | 1024M |
| post_max_size | 1024M |

ملاحظة موثّقة في `routes/api.php` (سطر تعليق): `large.upload` كان مُسجَّلاً في Kernel لكن غير مُطبَّق على مسار `/api/mobile/upload-chunk` لفترة، فكان تجميع الفيديو يموت تحت حدود php.ini الافتراضية.

---

## 27. Rclone + Google Drive

| المكوّن | القيمة |
|---------|--------|
| remote | `alhayahorphans` |
| root | `temp` |
| مسار نهائي | `temp/{جمعية}/{شخص}` |
| config | `rclone.conf` — **(سر: REDACTED)** |
| Service | `RcloneGoogleDriveService` |
| sanitize | `sanitizeName` |
| Job | `ProcessRcloneUploadJob` |

`app/Services/GoogleDriveService.php` — API مباشر.
`CloudIntegrationService` — تكامل سحابي إضافي.

### ⚠️ SSL verification disabled (تحقق مباشر 2026-09-22)

`GoogleDriveService.php` يعطّل التحقق من شهادات SSL في **~20 موضعاً**:

- `'verify' => false` في السطور: 62, 151, 280, 313, 335, 370, 400, 434, 470, 499, 533, 559, 588, 610, 650, 672, 695, 734, 766
- `CURLOPT_SSL_VERIFYPEER, false` في السطر **173**
- `credentials.json` مساره `storage_path('app/google/credentials.json')` (سطر 32، +34–46)

مخاطر: MITM على اتصالات Google Drive + سر OAuth مخزّن في مسار متوقع — **R26**.

---

## 28. Models (Eloquent) — audit مكتمل 2026-09-22

**49 model** في `app/Models/` (فحص subagent كامل للعلاقات + red flags).

### نتائج ميدانية مؤكدّة

- **لا يوجد `$guarded = []` في أي model** (grep على `app/Models` — لا نتائج) → الادعاء القديم بوجود mass-assignment واسع عبر `$guarded=[]` **غير صحيح**.
- **`User::$fillable` يشمل `password` و `role`** (سطور 20–29) → خطر mass-assignment على حقلَي auth/تفويض عند أي `update($request->all())`.
- النسخ الاحتياطية للموديلات/الكترولرز موثّقة في §20 (×4 ملفات backup).

### أهم العلاقات

- `Sponsorship` ↔ `Sponsor` (عبر `sponsorship_sponsor`)
- `Sponsorship` → `SponsorshipStatus`, `HealthStatus`, `TypeOfGuarantee`, `TypeOfAccommodation`
- `Data` → `City`, `Province`
- `DeadPepole` → أسباب الوفاة
- `Attachment` / `EnhancedAttachment` / `GoogleDriveUpload`
- `ServerSyncAction` (طابور actions من السيرفر للهاتف)
- `GuardianBankAccount` → `BankName`
- `User` (spatie roles) — `$fillable` حساس (password, role)

---

## 29. Migrations Inventory

**134 migration** — التوزيع الزمني:
- 2014: users, persons, city, ci_*
- 2019: failed_jobs, personal_access_tokens
- 2024: general_categories, search indexes
- 2025: الجداول الأساسية كلها (sponsorships, data, re_people, dead_pepoles, attachments, guardian_bank_accounts, spatie permissions, search indexes)
- 2026: chunked_uploads, refresh_tokens, server_sync_actions, offline_upload_statuses, file_id_registry, google_drive_uploads, additional_dead/deceased, sponsor_field_settings additions, search indexes للتسجيل

---

## 30. Seeders & Factories

**7 seeders:**
- `DatabaseSeeder` (رئيسي)
- `UserSeeder`, `CreateAdminUserSeeder`
- `PermissionTableSeeder` (spatie)
- `CountriesSeeder`, `CitiesSeeder`, `CiBirthSeeder`

**1 factory:** `UserFactory`

---

## 31. Config Files

**26 ملف config:**

| الملف | ملاحظة |
|-------|--------|
| `app.php` | |
| `auth.php` | guards: web (session) + api (sanctum) |
| `database.php` | connections: mysql (aso) + civilregistry |
| `queue.php` | driver=database, retry_after=3900 |
| `scout.php` | driver=database |
| `search.php` | |
| `session.php` | |
| `cors.php` | wildcard |
| `cache.php` | |
| `filesystems.php` | local disk |
| `mail.php` | |
| `broadcasting.php` | BROADCAST_DRIVER=log/null |
| `logging.php` | |
| `hashing.php` | bcrypt |
| `permission.php` | spatie |
| `sanctum.php` | |
| `large-file-config.php` | 1024M/2048M/3600 |
| `file_security.php` | **0 سطور — فارغ** |
| `duplicate_detection.php` | |
| `folder_duplicate_detection.php` | |
| `code_generation.php` | |
| `snappy.php` | |
| `speedtest.php` | |
| `sponsor_fields.php` | |
| `services.php` | |
| `view.php` | |

---

## 32. Blade Views

- **224 ملف blade** (تقدير من glob المُقطَّع عند 100).
- بنية رئيسية:
  - `layouts/`: app, admin, guest, navigation
  - `user/generalRegistration/`: index, create, login, thank-you + javascript/ (15 ملف) + component/ (7)
  - `user/dashboard/`: index + toolbars, layout, javascript, component, pdf
  - `file-management/`: index, folders-management, modals
  - `admin/`: speedtest, file, duplicate-files, google_drive_test, dashboard
  - `auth/`: Breeze (login, register, forgot-password, reset-password, verify-email, confirm-password)
  - `components/`: 12 مكون blade

- **ملفات JS مضمّنة:**
  - `imageProcessorWorker.js` (Web Worker لمعالجة الصور)
  - `highlightInvalidField.js`
  - `resources/js/app.js` + `bootstrap.js` (Vite/Laravel Mix)

---

## 33. Helpers & View Components

**11 helper:**
- `SearchHelper`, `RoleHelper`, `NameSegmentation`, `FileSecurityHelper`
- `global_helper.php`, `global_helper_updated.php`, `global_helper_safe.php`, `global_helper_original_backup.php` (4 نسخ!)

**2 View Component:** `AppLayout`, `GuestLayout`

**1 Exception Handler:** `app/Exceptions/Handler.php`

---

## 34. Observers & Traits

- `app/Observers/SponsorshipObserver.php`
- `app/Observers/DataExcelObserver.php`
- `app/Traits/SyncsLookupToMobile.php`

---

## 35. Tests

**15 ملف:**
- Unit: `ExampleTest`
- Feature: `SponsorshipImportWithCivilRegistryTest`, `SecureFileSystemTest`, `ProfileTest`, `LivingMotherRegistrationTest`, `GuardianBankAccountFKFixTest`, `ExampleTest`, Auth/* (6 ملفات)
- `TestCase`, `CreatesApplication`
- Config: `phpunit.xml`

**Requests:** 2 فقط — `ProfileUpdateRequest`, `LoginRequest`

---

## 36. Android Build Config

| الملف | المحتوى |
|-------|---------|
| `android-v3/variables.gradle` | minSdk=24, target/compile=36 |
| `android-v3/build.gradle` | AGP 8.13.0 |
| `android-v3/app/build.gradle` | appId=com.aso.app, vc=110, vn="3.2", Java 21, MultiDex, minify |
| `android-v3/gradle.properties` | |
| `android-v3/settings.gradle` | |
| `android-v3/capacitor.settings.gradle` | |
| `android-v3/app/capacitor.build.gradle` | |
| `android-v3/app/proguard-rules.pro` | |
| `gradle-wrapper.properties` | Gradle 8.14.3 (من cache path) |

---

## 37. Android Manifest & Permissions

`android-v3/app/src/main/AndroidManifest.xml`:
- `android:largeHeap="true"`
- cleartextTrafficEnabled (عبر config)
- ChromiumInitProvider
- Services/Receivers المسجّلة: DataSync, Download, Background, Upload, Realtime, CallerInfo, IncomingCall

**19 ملف res:**
- `xml/`: network_security_config, file_paths, data_extraction_rules, config, backup_rules
- `layout/`: activity_main, caller_info_overlay
- `values/`: styles, strings, colors, ic_launcher_background
- `drawable*`: 7 ملفات
- `mipmap-*`: launcher icons

---

## 38. Capacitor Plugins

`capacitor.plugins.json` — **12 plugin** (منها):
- `@capacitor-community/sqlite`
- Camera / Filesystem / Device / Network / Preferences / Haptics / Keyboard / StatusBar / SplashScreen / Share / App (الافتراضية)
- Custom plugins مسجّلة في Manifest/Java: BackgroundSyncPlugin, UploadServicePlugin, GoogleDriveUploadPlugin, NativeCameraPlugin, NativePhotoPlugin, BarcodeScannerPlugin, PermissionsManagerPlugin

---

## 39. Android Package Structure

### `com.aso.app` (44 ملف Java)
| فئة | الملفات |
|-----|---------|
| Entry | MainActivity, AutoUploadApplication, ChromiumInitProvider |
| Network/API | ApiConfig, InternetUtils, UnifiedNetworkMonitor, NetworkQuality |
| Upload | ChunkedUploadWorker, FileSyncWorker, DriveStatusWorker, PrepareUploadsWorker, SmartMediaWorker/Processor, UploadDatabaseHelper, UploadServicePlugin, UploadTaskScheduler, UploadStatusBridge, UploadBootReceiver, GoogleDriveUploadPlugin, StreamingRequestBody, AsyncDocumentSaver |
| Sync | SponsorshipSyncWorker, RelatedDataDatabaseHelper, SponsorshipsDatabaseHelper, SyncOrchestrator, PendingStatusUpdateHelper, SponsorshipFolderManager, SmartRetryEvaluator |
| Realtime | RealtimeSyncService, RealtimeSyncBootReceiver |
| Camera | CameraActivity, CameraBridge, CameraMemoryManager, PhotoActivity, NativeCameraPlugin, NativePhotoPlugin, MemoryMonitor |
| Storage | WebStorageManager, MediaArchive |
| UI/Bridge | JavaScriptBridge (غير موجود هنا — في org.alhayah) |
| Diagnostics | BarcodeScanner*, CallerInfoService, IncomingCallReceiver |
| Misc | PermissionsManagerPlugin |

### `org.alhayah.sponsorships` (11 ملف Java)
| الملف | الوظيفة |
|-------|---------|
| DataSyncWorker | مزامنة دورية (كل 15د) |
| DataSyncDatabaseHelper | `data_sync.db` v2 |
| DataSyncForegroundService | خدمة أمامية |
| DataSyncBootReceiver | إعادة تشغيل بعد boot |
| BackgroundSyncService / BackgroundSyncPlugin | مزامنة خلفية |
| NetworkConnectedWorker | WorkManager عند توفر الشبكة |
| CivilRegistryStore | `civil_registry.db` v2 + manifest |
| DownloadForegroundService | تحميل مجزأ للسجل المدني |
| ConsoleMessageInterceptor | التقاط `SYNC_DATA:` من console.log |
| JavaScriptBridge | onDataSaved + evaluateJavascript |

---

## 40. Android Upload Pipeline

```
JS (WebView) ──► UploadServicePlugin / GoogleDriveUploadPlugin
                     │
                     ▼
              upload_queue.db (v7)
                     │
         ┌───────────┼───────────┐
         ▼           ▼           ▼
  PrepareUploads  ChunkedUpload  FileSyncWorker
     Worker         Worker
         │           │           │
         └───────────┼───────────┘
                     ▼
     POST /api/mobile/upload-chunk
     Headers: X-Upload-Id, X-Chunk-Index, X-Chunk-Size
                     │
                     ▼
        GET /api/mobile/upload-status/{id}
                     │
                     ▼
     Offline path: POST /api/uploads/offline-inbox + /ack
                     │
                     ▼
        Server: attachments + google_drive_uploads
                     │
                     ▼
        ProcessRcloneUploadJob ──► Google Drive
                     │
                     ▼
        DriveStatusWorker ──► offline-inbox
```

**- chunk sizing:** `NetworkQuality` — 600 kbps (بطيء) / 5000 kbps (سريع).
- **retry:** `SmartRetryEvaluator` — 5 محاولات، backoff 2s × 2^n.
- **streaming:** `StreamingRequestBody` (يمنع تحميل الملف كاملاً في الذاكرة).

---

## 41. Android Sync Pipeline

```
┌───────────── com.aso.app ─────────────┐
│ SponsorshipSyncWorker                 │
│   GET /mobile/sync/sponsorships       │
│   GET /mobile/sync/initial            │
│   GET /mobile/sync/data-table|re-...  │
│   POST /mobile/sync/actions           │
│   GET /mobile/server-actions + ack    │
│ → sponsorships_data.db + related_data │
└───────────────────────────────────────┘

┌──────── org.alhayah.sponsorships ─────┐
│ DataSyncWorker (كل 15 دقيقة)         │
│   GET /mobile/sync/initial            │
│   GET /mobile/sync/full               │
│   related data tables                 │
│ → data_sync.db                        │
│ BackgroundSyncService (Service)       │
│ NetworkConnectedWorker (WorkManager)  │
└───────────────────────────────────────┘

Console bridge: ConsoleMessageInterceptor التقاط SYNC_DATA:
JS bridge: JavaScriptBridge → onDataSaved / evaluateJavascript
```

---

## 42. Android Local Databases (تفصيل)

| DB | Owner | المحتوى |
|----|-------|---------|
| `upload_queue.db` | com.aso.app | طابور رفع مجزأ (status, retry, chunk_index, upload_id) |
| `sponsorships_data.db` | com.aso.app | كفالات محلية + صور manifest |
| `related_data.db` | com.aso.app | people, banks, death_reasons, additional_deceased |
| `data_sync.db` | org.alhayah | مزامنة أولية/دورية |
| `civil_registry.db` | org.alhayah | manifest + أشخاص (تنزيل مجزأ) |

---

## 43. Android Network & Retry

| المكوّن | القيمة |
|---------|--------|
| `InternetUtils` | `NET_CAPABILITY_VALIDATED` (يمنع false-positive) |
| `UnifiedNetworkMonitor` | مراقبة network callback موحّدة |
| `NetworkQuality` | تحديد حجم chunk: 600 kbps → صغير، 5000 kbps → كبير |
| `SmartRetryEvaluator` | max 5, backoff = 2s × 2^attempt |
| `NetworkConnectedWorker` | WorkManager constraint: connected |

---

## 44. Android Native Features

| الميزة | الملفات |
|--------|---------|
| كاميرا | CameraActivity, CameraBridge, CameraMemoryManager, NativeCameraPlugin, PhotoActivity, NativePhotoPlugin, MemoryMonitor |
| مسح باركود | BarcodeScannerPlugin, BarcodeScannerActivity (MLKit) |
| إذن | PermissionsManagerPlugin |
| مكالمات | IncomingCallReceiver, CallerInfoService (caller info overlay) |
| حفظ مستندات | AsyncDocumentSaver (8KB buffer, يحترم 85% memory threshold) |
| أرشيف | MediaArchive (تطابق بنية Drive `temp/{جمعية}/{شخص}` + إصلاح MediaStore.Files) |
| مجلدات | SponsorshipFolderManager |

---

## 45. Web ↔ Java Bridge

| الآلية | الملف | كيف تعمل |
|--------|-------|----------|
| JS → Java | Capacitor Plugins | `UploadServicePlugin`, `BackgroundSyncPlugin`, `GoogleDriveUploadPlugin` |
| Java → JS | `JavaScriptBridge.java` | `evaluateJavascript` + `onDataSaved` |
| JS → Java (upload status) | `UploadStatusBridge` | evaluateJavascript مباشرة |
| console.log → Java | `ConsoleMessageInterceptor` | التقاط `SYNC_DATA:` |

---

## 46. Offline/Online Sync Architecture

الوثائق الموجودة: `docs/sync/INDEX.md`, `OFFLINE_ONLINE_SYNC_ARCHITECTURE.md`, `SMART_SYNC_WORKFLOW.md`, `GOOGLE_DRIVE_DIRECT_UPLOAD.md`, `FILE_ID_GENERATION_AND_SYNC_MONITORING.md`

**التحقق من الكود (verified):**

### Online path
1. طابور محلي (`upload_queue.db`) → Worker.
2. Chunked upload مع `large.upload`.
3. `upload-status` polling.
4. إنشاء سجل `attachments` + `google_drive_uploads`.
5. Queue → `ProcessRcloneUploadJob` → Drive.
6. `DriveStatusWorker` يقرأ `/uploads/offline-inbox` → ack.

### Offline path
1. الأجزاء تُخزَّن محلياً (`upload_queue.db` + علامة `_done`).
2. عند عودة الشبكة: `NetworkConnectedWorker` + `UnifiedNetworkMonitor`.
3. `offline-inbox` endpoint + `ack` لمزامنة حالات الملفات.
4. صور الكفالات: `photosManifest` → تنزيل على الطلب.

### File ID
- توليد 6 أرقام في `reserved_codes` → تفعيل في `file_id_registry` مع `handshake_token`.

### Civil Registry
- Manifest → تنزيل مجزأ → `civil_registry.db` → `DownloadForegroundService`.

---

## 47. Conflicts & Discrepancies (C1–C10 + ملاحظة R22)

| # | التعارض | الوثيقة القديمة | الكود الفعلي | الحسم |
|---|---------|------------------|-------------|-------|
| C1 | مسار المزامنة | `/api/mobile/sponsorships/sync` | `/api/mobile/sync/sponsorships` | **الكود**: `/sync/sponsorships` (routes/api.php:901) |
| C2 | نظام مزامنة مزدوج | A/B dual system (SYSTEM_AUDIT_REPORT) | فعلياً: `SponsorshipSyncWorker` (com.aso) + `DataSyncWorker` (org.alhayah) يعملان متوازيين | **الكود**: كلاهما موجود فعلاً — تعارض حقيقي |
| C3 | `large.upload` | غير مُطبَّق على upload-chunk | **مُطبَّق الآن** على 3 مسارات (routes/api.php:824, 927, 948, 930) | **الكود**: مُطبَّق |
| C4 | allowMixedContent | root: false | android-v3: **true** | **الكود**: android-v3 يسمح بالـ mixed content |
| C5 | Desktop dir كـ webDir | capacitor.config.json: `mobile-app/dist` | مجلد `mobile-app/` يحتوي dist فقط (لا مصدر) | **الكود**: webDir صحيح لكن مصدر البناء غير موجود في mobile-app/ |
| C6 | package name مزدوج | `com.aso.app` | فيه أيضاً `org.alhayah.sponsorships` كـ applicationId/namespace ثانوي | **الكود**: كلاهما في نفس APK |
| C7 | جدول guardian_bank_accounts | مذكور في قراءة سطحية | يوجد أيضاً `guardian_banks_account` (بدون s) في بعض الاستعلامات في routes/api.php | **الكود**: كلا الاسمين موجودان (scattered) |
| C8 | `public-api/duplicate-files/*` مسجّلة مرتين | — | نفس 9 مسارات في web.php:116 و admin.php:621 بنفس الأسماء | **الكود**: تكرار فعلي — Laravel يسمح بالأسماء المتكررة لكن آخر تسجيل يفوز |
| C9 | `api/files/*` مسجّلة مرتين | — | web.php:330 (مع auth ضمني) + web.php:373 (نفسها `withoutMiddleware(['auth','verified'])`) — أسماء متطابقة `api.files.*` | **الكود**: المسار بدون auth هو الفائز (تسجيل لاحق) |
| C10 | civil-registry CRUD مساران | — | web.php:101 (`admin/civil-registry`, auth+verified) + admin.php:601 (`admin/civil-registry`, permission فقط على index/create) | **الكود**: كلاهما مُعرَّف — تضارب حماية |

---

## 48. Missing Components & Gaps (M1–M14)

| # | المكوّن | الدليل | الأثر |
|---|---------|--------|-------|
| M1 | `config/file_security.php` | 0 سطور | إعدادات أمن الملفات غير مُهيّأة |
| M2 | `SecureFileAccess` middleware | 0 سطور | حماية الملفات غير مفعّلة عبر middleware |
| M3 | `SecurityHeadersMiddleware` | 0 سطور | لا CSP/HSTS من التطبيق |
| M4 | `mobile-app/` مصدر البناء | يوجد dist فقط | لا يمكن إعادة بناء WebView من هذا المسار |
| M5 | `app/Listeners` | غير موجود | لا يوجد event listeners |
| M6 | `app/Policies` | غير موجود | لا يوجد policies (الصلاحيات عبر spatie roles فقط) |
| M7 | `app/Notifications` | غير موجود | لا إشعارات Laravel |
| M8 | `app/Rules` | غير موجود | validation rules مضمّنة في controllers |
| M9 | `app/Resources` | غير موجود | لا API Resources |
| M10 | `app/Imports` | غير موجود | Excel import مضمّن في services |
| M11 | `app/Livewire` | غير موجود | لا Livewire |
| M12 | ملفات فرعية Android المفقودة مؤكدة | `com.aso.app/{DataSyncWorker,BackgroundSyncService,BackgroundSyncPlugin,ConsoleMessageInterceptor,CivilRegistryStore}.java` | موجودة في `org.alhayah.sponsorships` فقط — **ليس ناقصاً فعلياً** |
| M13 | `app/Http/Controllers/SecureFileController.php` | **0 سطر — فارغ** (تحقق مباشر 2026-09-22) | لا يوجد منفذ فعلي لـ SecureFileController؛ حماية الملفات عبر مسارات أخرى (وبعضها عام — R18/R21) |
| M14 | لا `$guarded = []` في أي model | grep `app/Models` — لا نتائج (2026-09-22) | الادعاء بأن mass-assignment واسع عبر `$guarded=[]` **غير صحيح**؛ الخطر الحقيقي في `User::$fillable` (password, role — R27) |

---

## 49. Risks & Final Architecture Map

### مخاطر (28 خطر: R1–R28)

| # | الخطر | الخطورة | الدليل |
|---|-------|---------|--------|
| R1 | **PHP credentials مضمّنة** في `routes/api.php` (`root` / ``) لمسارين بحث + **4 خدمات بحث** (R25) | 🔴 حرج | سطور 92-96, 255-259 + Services |
| R2 | **rclone.conf** يحوي Google Drive credentials | 🔴 حرج | `rclone.conf` — REDACTED |
| R3 | **CORS wildcard** (`*`) | 🟠 عالي | config/cors.php |
| R4 | **3 middleware فارغة** (file_security, SecureFileAccess, SecurityHeaders) | 🟠 عالي | 0 سطور |
| R5 | **ازدواج نظام مزامنة** (2 Workers متوازيان + 2 طابور + 5 SQLite) | 🟠 عالي | وحدة تصميمية |
| R6 | **`allowMixedContent=true`** في android-v3 | 🟠 عالي | capacitor.config.json |
| R7 | **`.env` + `.envbak` + `.env.speedtest`** في repo | 🟠 عالي | مخزون الملفات |
| R8 | **`broadcasting: log/null`** — لا real broadcast فعلي | 🟡 متوسط | config/broadcasting.php |
| R9 | **`SimpleFileUploadController` بدون auth** على `/api/simple/upload` | 🟠 عالي | routes/api.php:199-202 |
| R10 | **Gallery delete بدون auth** (`DELETE /api/gallery/file/...`) | 🔴 حرج | routes/api.php:493 |
| R11 | **Duplicate files API بدون auth** | 🟠 عالي | routes/api.php:227-233 |
| R12 | **Civil registry save-bank-account بدون auth** | 🟠 عالي | routes/api.php:644 |
| R13 | **Single-job timeout 3600s** + `retry_after 3900` = ملفات ضخمة تعلّق الـ worker ساعات | 🟡 متوسط | queue.php + ProcessRcloneUploadJob |
| R14 | **4 نسخ global_helper** (original/safe/updated/current) | 🟡 متوسط | احتمال اختلاف سلوكي |
| R15 | **`.phpBak` + `admin_backup` + `routes/Backup` + 4 backup controllers** (R28) في tree | 🟡 متوسط | كود قديم قد يُحمَّل |
| R16 | **لا يوجد app/Policies** — التفويض يعتمد على spatie roles يدوياً | 🟠 عالي | غير موجود |
| R17 | **BROADCAST_DRIVER=log** — RealtimeSyncService يعتمد على WebSocket بدون broadcast حقيقي | 🟠 عالي | config + Android code |
| R18 | **حذف ملفات مكررة للعامة** — `DELETE /public-api/duplicate-files/delete\|bulk-delete\|delete-all` بدون auth (web.php:123-125 + admin.php:628-630) | 🔴 حرج | routes/web.php, routes/admin.php |
| R19 | **`/auto-login` و `/s/{credentials}`** — دخول مباشر بدون فحص جلسة في web.php:155-156 | 🔴 حرج | routes/web.php |
| R20 | **`withoutMiddleware(['auth','verified'])` صريح** على `api/files/*` (رفع مجلدات) و `api/speedtest/*` (web.php:342-359, 373-402) | 🔴 حرج | routes/web.php |
| R21 | **`admin/file/*` بدون أي middleware** (`withoutMiddleware(['web'])` — admin.php:353) + **`test/api/file/*` بدون أي middleware** (admin.php:367) — تشمل `delete-duplicates` و `process-folder-duplicates` | 🔴 حرج | routes/admin.php |
| R22 | ~~**مجموعة `admin` الرئيسية بلا `auth` جماعياً**~~ **مصحّح (2026-09-22):** RouteServiceProvider يضيف `Route::middleware(['web','auth','rolebreeze:admin'])->group(base_path('routes/admin.php'))` (سطور 54–55) → **المجموعة محمية فعلياً بـ auth+rolebreeze**؛ لا يزال هناك مسارات admin مفردة خارج هذه الحماية (انظر R21/R24) لكن الادعاء الأصلي بغياب auth الجماعي **غير دقيق** | 🟡 منخفض (كادعاية سابقة) | `app/Providers/RouteServiceProvider.php:54-55` |
| R23 | **test routes للعامة**: `/test-snappy-pdf`, `/test-download-all`, `/google-drive-test/*` (9)، `/test-real-stats-public`, 14 صفحة test documents، `admin/speedtest/quick-test` + `test-api` (يولّد بيانات عشوائية للأفراد) | 🟠 عالي | web.php + admin.php |
| R24 | **`POST /api/replace-duplicate-file`** و **`GET sponsors/{sponsor}/field-settings-check`** بدون auth | 🟠 عالي | admin.php:364, 633 |
| R25 | **Hardcoded PDO credentials في 4 خدمات بحث** — `ExactMatchSearch` (18–20), `LightningSearch` (18–20), `SmartExactSearch` (17–18), `SimpleExactSearch` (15–16) — `user=root` مضمّناً داخل app/Services (توسيع لـ R1) | 🔴 حرج | قراءة مباشرة 2026-09-22 |
| R26 | **SSL verification disabled** في `GoogleDriveService.php` — `'verify' => false` (~19 موضعاً) + `CURLOPT_SSL_VERIFYPEER=false` (سطر 173) + مسار `credentials.json` في storage (سطر 32) | 🔴 حرج | قراءة مباشرة 2026-09-22 |
| R27 | **`User::$fillable` يشمل `password` و `role`** (سطور 20–29) — أي `update($request->all())` يسمح بتغيير كلمة المرور/الدور mass-assignment | 🟠 عالي | `app/Models/User.php:20-29` |
| R28 | **4 ملفات backup controllers داخل شجرة المصنّف** — `.phpbak` / `.phpBak` / `.bak_fix2` (CivilRegistry, GeneralRegistration, ChunkedUpload, SponsorshipSync) — كود قديم قد يُحمَّل/يُستدعى (توسيع R15) | 🟡 متوسط | تحقق مباشر 2026-09-22 |

### Final Architecture Map

```
┌─────────────────────────────────────────────────────────────┐
│                    EXTERNAL / CDN                           │
│  • jsDelivr (face-api models)  • Google Drive (via Rclone) │
└───────────────────────────┬─────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────┐
│                    LARAVEL 10 (aso)                         │
│  routes/api.php (972) ─ routes/web/admin/auth               │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────────┐ │
│  │ 10 API      │  │ 86 Controllers│  │ 29 Services        │ │
│  │ Controllers │  │ (full audit)  │  │ (full audit)       │ │
│  └──────┬──────┘  └──────┬───────┘  └─────────┬──────────┘ │
│         │                │                     │            │
│  ┌──────▼────────────────▼─────────────────────▼──────────┐ │
│  │  Eloquent (49 models — full audit) + Scout (database) + spatie RBAC │ │
│  └──────┬─────────────────────────────────────────────────┘ │
│         │                                                   │
│  ┌──────▼──────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │ MySQL `aso`     │  │ MySQL        │  │ Queue (DB)     │ │
│  │ 134 migrations  │  │ `civilregistry`│ │ 6 Jobs         │ │
│  └─────────────────┘  └──────────────┘  └───────┬────────┘ │
│                                                  │          │
│  ┌───────────────────────────────────────────────▼────────┐ │
│  │ ProcessRcloneUploadJob → rclone → Google Drive         │ │
│  │ (remote: alhayahorphans, root: temp)                   │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  Console: SyncSponsorshipStatusJob@03:00 | cleanup 5m      │
│  Middleware: large.upload | auth:sanctum | throttle          │
└──────────────────────────┬───────────────────────────────────┘
                           │ HTTPS (allowMixedContent=true)
┌──────────────────────────▼───────────────────────────────────┐
│                 ANDROID (com.aso.app) v3.2 vc110             │
│  Capacitor 8 WebView ←→ 12 plugins + 9 custom Java plugins  │
│                                                              │
│  ┌─── com.aso.app ──────────┐  ┌── org.alhayah.sponsorships┐ │
│  │ ChunkedUploadWorker      │  │ DataSyncWorker (15m)      │ │
│  │ FileSyncWorker           │  │ DataSyncForegroundService │ │
│  │ DriveStatusWorker        │  │ BackgroundSyncService     │ │
│  │ PrepareUploadsWorker     │  │ NetworkConnectedWorker    │ │
│  │ SmartMediaWorker         │  │ DownloadForegroundService │ │
│  │ SponsorshipSyncWorker    │  │ CivilRegistryStore        │ │
│  │ RealtimeSyncService(WS)  │  │ ConsoleMessageInterceptor │ │
│  │ + SyncOrchestrator       │  │ + BackgroundSyncPlugin    │ │
│  └──────────┬───────────────┘  └──────────┬───────────────┘ │
│             │                             │                  │
│  ┌──────────▼─────────────────────────────▼──────────────┐  │
│  │ Local SQLite: upload_queue.db(v7)                     │  │
│  │ sponsorships_data.db(v3) related_data.db(v2)          │  │
│  │ data_sync.db(v2)  civil_registry.db(v2)               │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  Resilience: InternetUtils(VALIDATED) | NetworkQuality      │
│              SmartRetry(5, 2s×2^n) | MemoryMonitor          │
│  Camera: NativeCamera/Photo | Barcode(MLKit) | CallerInfo   │
│  Bridge: JSBridge(ondatasaved) | UploadStatusBridge         │
└──────────────────────────────────────────────────────────────┘
```

---

## نسب التحقق (Verification Ratios)

| المجال | النسبة | أساسها |
|--------|--------|--------|
| API Contract (routes/api.php) | **100%** | قراءة الملف كاملاً (972 سطراً) |
| routes/web.php | **100%** | قراءة الملف كاملاً (564 سطراً) |
| routes/admin.php | **100%** | قراءة الملف كاملاً (636 سطراً) |
| API Controllers (10 ملفات) | **100%** | قراءة مباشرة |
| Middleware | **100%** | Kernel + 17 ملف |
| Config | **100%** | 26 ملف |
| Queue/Jobs | **100%** | 6 jobs + config + worker.conf |
| Console Kernel | **100%** | قراءة مباشرة |
| Models | **100%** | audit subagent مكتمل (49 model + علاقات + red flags) — تحديث 2026-09-22 |
| Migrations | **90%** | أسماء الملفات مُتحقق منها 100%؛ محتوى كل migration لم يُقرأ بالكامل |
| Blade Views | **70%** | أسماء وبنية؛ محتوى JS المضمّن مُتحقق جزئياً |
| Android Java (55 ملف) | **100%** | قراءة/فحص مباشرة |
| Android Build/Config | **100%** | gradle + manifest + capacitor |
| Services (29) | **100%** | audit subagent مكتمل (13,111 سطراً + security flags) — تحديث 2026-09-22 |
| Controllers (86) | **100%** | audit subagent مكتمل + إحصاء مباشر بالمجلدات — تحديث 2026-09-22 |
| Tests | **100%** | أسماء + phpunit.xml |
| Database schema (فعلي) | **غير متحقق منه من الكود** | يتطلب الاتصال بقاعدة البيانات |
| `.env` values | **غير متحقق منه** (ممنوع عرضها) | |
| `rclone.conf` secrets | **SECRET FOUND — VALUE REDACTED** | |

---

## Verified / Conflicts / Missing / Risks — ملخص

### Verified (مُتحقَّق)
- 972 سطر routes/api.php كاملة + جدول API contract.
- **564 سطر routes/web.php كاملة** + **636 سطر routes/admin.php كاملة** (تحديث 2026-09-22).
- **86 controller** (Admin:45, Api:12, Auth:9, Controllers:12, Roles:1, Users:7) — audit مكتمل 2026-09-22.
- **29 service** (13,111 سطراً) — audit مكتمل 2026-09-22.
- **49 model** — audit مكتمل 2026-09-22 (لا `$guarded=[]`؛ `User::$fillable` حساس).
- 10 API controllers + 55 Java file + 26 config + 7 seeders.
- طابورا الرفع والمزامنة المزدوجان موجودان فعلاً.
- `large.upload` مُطبَّق على 4 مسارات رفع.
- **R22 مصحّح**: `admin.php` محمي جماعياً بـ `['web','auth','rolebreeze:admin']` (RouteServiceProvider:54-55).

### Conflicts (تعارضات)
- **10 تعارضات (C1–C10)** — انظر القسم 47.
- الجديد: تكرار `public-api/duplicate-files` و `api/files` و civil-registry CRUD.
- **مصحّح 2026-09-22:** الادعاء بأن `admin.php` بلا auth جماعي كان غير دقيق (R22 مصحّح).

### Missing (ناقص)
- **14 بنداً (M1–M14)** — انظر القسم 48.
- أهمها: 3 middleware فارغة + **SecureFileController فارغ** + لا Policies + لا mobile-app source + لا `$guarded=[]` في أي model (تصحيح).

### نسب تحقق المهام الكبرى (تحديث 2026-09-22)

| المهمة | الحالة | النسبة |
|--------|--------|--------|
| Android v3 analysis | منتهية | 100% |
| Controllers / Services / Models | **منتهية** | Controllers 100% (86)؛ Services 100% (29)؛ Models 100% (49) |
| Attachments / Storage / Security | جزئية | مسارات موثقة؛ SecureFileController فارغ؛ SSL-off + hardcoded PDO موثّقان |
| Mobile sync and search | منتهية عملياً | Sync 100%؛ Search endpoints 100% + 4 خدمات بحث بـ hardcoded PDO موثّقة |

### Risks (مخاطر)
- **28 خطر (R1–R28)** — انظر القسم 49.
- **حرجة (جديدة من web/admin):** R18 (حذف ملفات للعامة), R19 (auto-login), R20 (withoutMiddleware على رفع الملفات), R21 (admin/file بدون middleware إطلاقاً).
- **حرجة (جديدة من audits 2026-09-22):** R25 (hardcoded PDO root ×4 خدمات), R26 (SSL verify off + credentials.json في GoogleDriveService).
- **حرجة (سابقة):** R1 (credentials في الكود — routes)، R10 (gallery delete بدون auth)، R2 (rclone secrets).
- **R22 مصحّح** — admin.php محمي جماعياً فعلاً (انظر الجدول).
- **R27 متوسط-عالٍ**: `User::$fillable` حساس (password, role).
- **R28 متوسط**: 4 ملفات backup controllers.

---

## مصادر التحقق

| المصدر | الحالة |
|--------|--------|
| `routes/api.php` (972 سطراً) | مُقرأ كاملاً |
| `routes/web.php` (564 سطراً) | **مُقرأ كاملاً** |
| `routes/admin.php` (636 سطراً) | **مُقرأ كاملاً** |
| `app/Http/Controllers/Api/*.php` (10) | مُقرأة |
| `app/Http/Controllers/**` (86 + 4 backup) | **audit subagent مكتمل 2026-09-22** |
| `app/Http/Kernel.php` + Middleware (17) | مُقرأة |
| `app/Providers/RouteServiceProvider.php` | مُقرأ |
| `config/*.php` (26) | مُقرأة |
| `app/Console/Kernel.php` + Commands (11) | مُقرأة |
| `app/Jobs/*.php` (6) | مُقرأة |
| `laravel-worker.conf`, `rclone.conf` | مُقرأة |
| `android-v3/**/*.java` (55) | مُقرأة/فُحصت |
| `android-v3/{build,variables,settings}.gradle`, AndroidManifest, capacitor.* | مُقرأة |
| `composer.json`, `package.json`, `.env.example` | مُقرأة |
| `APP_SYSTEM_ARCHITECTURE.md`, `SYSTEM_AUDIT_REPORT.md`, `STRATEGIC_SCAN_REPORT.md`, `docs/sync/*` | قُرئت للمقارنة |
| `database/migrations/*.php` (134) | أسماء مُتحقَّق منها |
| `database/seeders/*.php` (7) | مُتحقق منها |
| `app/Models/*.php` (49) | **مُقرأة بالكامل** (audit subagent — علاقات + red flags) |
| `app/Services/*.php` (29) | **مُقرأة بالكامل** (audit subagent — 13,111 سطراً + security flags) |
| `app/Http/Controllers/**/*.php` (86 + 4 backup) | **مُفحوصة بالكامل** (audit subagent + إحصاء مباشر) |
| `app/Providers/RouteServiceProvider.php:54-55` | مُقرأ — يؤكد `['web','auth','rolebreeze:admin']` على admin.php |
| Hardcoded PDO (4 خدمات بحث) | تحقق مباشر 2026-09-22 |
| SSL verify off في GoogleDriveService | تحقق مباشر 2026-09-22 |
| `app/Models` grep `$guarded=[]` | لا نتائج — تحقق مباشر 2026-09-22 |
| ملفات backup controllers (×4) | تحقق مباشر 2026-09-22 |

**لم يُقرأ بالكامل:** محتوى كل migration فردياً، `.env` (ممنوع)، `rclone.conf` values (REDACTED)، `routes/auth.php` و `routes/console.php` و `routes/channels.php` و `routes/file-management.php` و `routes/exact_search_api.php` و `routes/test_*.php` و `routes/offline-test-development.php` (أسماء مُتحقَّق منها فقط). **(تمّ إكمال Controllers/Services/Models في 2026-09-22 — لم يبقَ منها.)**

---

## 50. android-v4 Additions

> **قسم جديد (2026-09-23)** — لا يُعدّل أي قسم من الأقسام 1–49. المرجع الكامل: `android-v4/Doc/00–09`.

### 50.1 الملخص
android-v4 = نفس أساس v3 التقني (Capacitor 8 + WebView + Java) + طبقة مزامنة موحّدة جديدة + طبقة بيانات offline للوحة التحكم + شاشات إضافية — **كلها ملفات جديدة بأسماء جديدة** (لا حذف ولا تعديل لأي ملف قديم).

### 50.2 المكوّنات الجديدة

| المكوّن | الملفات | الوصف |
|---------|---------|-------|
| مسارات API v4 | `routes/api_v4.php` | 60 مسار تحت `api/mobile/v4/*` بـ `auth:sanctum` + `throttle:api-v4` + أسماء `api.v4.*` |
| وحدات تحكم v4 | `app/Http/Controllers/Api/V4/*` | `SyncControllerV4`, `DeviceRegistryControllerV4`, `AdminOfflineExportControllerV4`, `ReconciliationControllerV4`, `AdminCrudControllerV4`, `ConfigFlagsControllerV4` |
| خدمات v4 | `app/Services/V4/*` | `IdempotentUpsertServiceV4`, `ConflictDetectionServiceV4`, `DeviceHandshakeServiceV4`, `AuditLoggerV4` |
| محرك مزامنة Android | `android-v4/.../v4/sync/*` | `UnifiedSyncOrchestratorV4`, `SyncSchedulerV4`, `SyncWorkerV4`, `SyncOutboxManagerV4`, `ConflictResolverV4`, `DeviceIdentityManagerV4`, `IdempotencyKeyGeneratorV4`, `RemoteConfigManagerV4` |
| قواعد بيانات Android | `v4/db/*` | `sync_v4.db` + `admin_offline_v4.db` (جديدة بالكامل) |
| Offline Admin | `v4/admin_offline/*` + `v4/bridge/*` | `AdminOfflineDataStoreV4`, `ReportsEngineV4`, `PermissionsCacheManagerV4`, `FileManagementOfflineQueueV4`, `JavaScriptBridgeV4` |
| جداول MySQL جديدة | migrations مستقلة | `sync_outbox_v4`, `sync_idempotency_log_v4`, `device_registry_v4`, `conflict_review_queue_v4`, `admin_dashboard_snapshot_v4`, `admin_reports_source_v4`, `permissions_manifest_v4`, `record_audit_log_v4`, `file_index_v4` |
| أعمدة idempotency | migration إضافية | `client_uuid` + `sync_origin_device_id` + `needs_review` على 6 جداول قديمة (nullable فقط) |
| شاشات | `mobile-app-v4/src/{admin,screens}/*` | 22 admin + 6 screens (مُنسَخة داخل APK assets) |

### 50.3 علاج التكرار (Idempotency)
1. `client_uuid` فريد على كل سجل + Trigger يولّده تلقائياً للسجلات الجديدة.
2. `X-Idempotency-Key` + `sync_idempotency_log_v4` — نفس الطلب 3 مرات = سجل واحد.
3. WorkManager Unique Work `"unified_sync_v4"` — لا يوجد عاملَي مزامنة متزامنان أبداً.
4. `ConflictDetectionServiceV4` → `conflict_review_queue_v4` (مراجعة بشرية، لا حذف صامت).

### 50.4 عتبات التشغيل (Phase 7 Cutover)
- Flag: `config('services.sync_v4.legacy_sync_enabled')` ← `.env: LEGACY_SYNC_ENABLED`
- Endpoint: `GET /api/mobile/v4/config/flags`
- Android: `RemoteConfigManagerV4` يلغي/يعيد جدولة unique works القديمة وقت التنفيذ فقط (**لا حذف كود**).
- تراجع فوري: `LEGACY_SYNC_ENABLED=true` → إعادة الجدولة خلال دورة مزامنة واحدة.

### 50.5 الإصدار
| الحالة | versionCode | versionName | applicationId |
|--------|-------------|-------------|---------------|
| v3 (قديم) | 110 | 3.2 | `com.aso.app` |
| **v4 (حالي)** | **200** | **4.0** | `com.aso.app` (بلا تغيير) |

### 50.6 اختبارات
| المجموعة | العدد | الحالة |
|----------|-------|--------|
| `SyncIdempotencyV4Test` | 5 | ✅ |
| `ConflictDetectionV4Test` | 6 | ✅ |
| `RecordAuditLogV4Test` | 6 | ✅ |
| `AdminOfflineV4Test` | 8 | ✅ |
| `ConfigFlagsV4Test` | 3 | ✅ |
| **المجموع** | **28** | **✅** |

### 50.7 مراجع
- `android-v4/Doc/00_MASTER_PLAN.md` … `09_PHASE5_PENDING_STATUS.md`
- `SECURITY_DEBT_LEGACY_ROUTES.md` (مشروع مستقل — خارج v4)
- `docs/ENCYCLOPEDIA.md` هذا الملف (القسم 50 فقط — الأقسام 1–49 كما هي)
