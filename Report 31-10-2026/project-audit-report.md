# تقرير التدقيق التقني لمشروع Laravel

**تاريخ الفحص:** 31-10-2026  
**نطاق الفحص:** ملفات المشروع الحالية، المسارات، التدفقات المطلوبة، migrations، الإعدادات المحلية والسجلات والاختبارات.  
**حدود الفحص:** لم يتصل التدقيق بقاعدة البيانات المحلية؛ لذلك لا يثبت حالة الجداول الفعلية أو تطبيق migrations. إعدادات الإنتاج وخدمات Google Drive/Rclone الفعلية غير متاحة. لم تُعرض قيم `.env` أو بيانات شخصية أو محتويات ملفات اعتماد.  
**الثقة:** `[مؤكد]` موجود مباشرة في المصدر أو في البيئة المفحوصة؛ `[مرجّح]` نتيجة محتملة يدعمها المصدر لكن أثرها يعتمد على ظروف التشغيل؛ `[غير متحقق]` يتطلب تشغيل الخدمة/قاعدة البيانات أو البيئة المستهدفة.

> التقرير يرصد الحالة الموجودة وقت الفحص ولا يتضمن إصلاحات. أظهر `git status` تعديلات وملفات غير متتبعة موجودة مسبقاً؛ لم تُسترجع أو تُعدّل ملفات التطبيق أثناء التدقيق. ملف التقرير هو المخرج الوحيد المقصود لهذه المهمة. تشغيل الاختبارات قد يضيف سجلات تشغيل محلية.

## 0) ملخص تنفيذي

أخطر النتائج مرتبة حسب الأثر؛ الأرقام تشير إلى قسم 5:

1. **#1 حرج:** واجهات عامة للسجل المدني تعيد بيانات هوية وعنوان وحسابات بنكية دون مصادقة.
2. **#2 حرج:** مسارات اختبار Google Drive العامة تتيح سرد الملفات ورفعها وحذفها عند توفر اعتماد الخدمة.
3. **#3 عالٍ:** مسارات رفع عامة تشمل رفع أجزاء إلى مسار مبني من مدخل غير موثوق، دون مصادقة أو حدود كافية.
4. **#4 عالٍ:** تنزيل أرشيف جميع الملفات المكررة متاح عبر GET عام.
5. **#5 عالٍ:** الدخول التلقائي يضع بيانات الاعتماد في query/path، ويُسجّل الدخول بعد مطابقة رقم الهوية ورقم الملف دون حدّ معدل ظاهر.
6. **#6 عالٍ:** خدمة المرفق تفحص وجود المستخدم فقط ولا تربط المرفق بالكفالة التي يملكها.
7. **#7 عالٍ:** رفع Google Drive يمنح الملف صلاحية `anyone/reader`؛ الرابط ليس خاصاً بالمستخدم.
8. **#8 عالٍ:** التحقق من TLS معطل في طلبات Google Drive وcURL.
9. **#9 عالٍ:** فشل Rclone يُسجّل ثم يُتجاوز؛ قد تُغلق الكفالة ويُعتمد الحفظ رغم غياب المرفق.
10. **#10 عالٍ:** عمليات التخزين الخارجي تقع داخل معاملة DB ولا يعوّض `rollBack` الملفات المرفوعة خارجياً.

## 1) نظرة عامة على المشروع

### الإصدارات

| المكوّن | الإصدار/المتطلب |
|---|---|
| PHP في CLI المحلي | 8.2.12 (`C:\xampp\php\php.ini`)؛ المشروع يطلب `^8.1` |
| Laravel | `v10.50.3` |
| Sanctum | `v3.3.3` |
| Scout | `v10.25.0` |
| Google API Client | `v2.19.4` |
| Guzzle | `7.15.3` |
| knp-snappy | `v1.0.5` |
| mPDF | `v8.3.1` |
| PhpSpreadsheet | `1.30.6` |
| PHPUnit | `10.5.64` |

الإصدارات مأخوذة من `composer.lock`، وليست قياساً لإصدارات خادم الإنتاج.

### هيكل المجلدات الرئيسية

`app/`، `bootstrap/`، `config/`، `database/`، `docs/`، `public/`، `resources/`، `routes/`، `storage/`، `tests/`، `mobile-app/`، `mobile-app-v4/`، `android/`، `android-v2/`، `android-v3/`، `android-v4/`، `scripts/`، `rclone/`.

### الإعدادات الفعلية من Laravel config bootstrap

| الخاصية | القيمة |
|---|---|
| `cache.default` | `file` |
| `session.driver` | `file` |
| `queue.default` | `database` |
| `filesystems.default` | `local` |
| `database.default` | `mysql` |

يوجد اتصال منفصل باسم `civilregistry` في migrations/الخدمات؛ لم يمكن التحقق من اتصاله. `config/filesystems.php` يعرّف `local` و`public` باستخدام local driver، وكلاهما `throw=false`.

## 2) خريطة المسارات (Routes)

الجدول يركّز على المسارات المرتبطة بالتدفقات الحرجة والأسطح العامة، وليس قائمة بكل مسارات النظام. عُرضت الوسائط من `php artisan route:list --path=... -vv`.

| الرابط | الطريقة | الكنترولر@الدالة | Middleware المرصود | يحتاج تسجيل دخول؟ |
|---|---|---|---|---|
| `/`، `/users/generalRegistration/store` | GET، POST | `GeneralRegistrationController@index/store` | `web` | لا؛ بوابة التسجيل العامة |
| `/users/generalRegistration/upload-chunk` | POST | `GeneralRegistrationController@uploadChunk` | `web`, CSRF، جلسة؛ بلا `auth` | لا |
| `/auto-login`, `/s/{credentials}` | GET | `ShowGeneralRegisrationController@autoLogin/autoLoginShort` | `web`؛ لا `auth` أو `throttle` ظاهر | لا |
| `/user/general-registration` | GET | `ShowGeneralRegisrationController@index` | `web`, `auth`, `verified` | نعم |
| `/user/general-registration/update` | POST | `ShowGeneralRegisrationController@updateSponsorshipData` | `web`, `auth`, `verified` | نعم |
| `/user/general-registration/attachment/{attachmentId}` | GET | `ShowGeneralRegisrationController@serveRcloneAttachment` | `web`, `auth`, `verified` | نعم؛ لا يظهر فحص ملكية المرفق |
| `/google-drive-test/*` | GET, POST, DELETE | `GoogleDriveTestController` (index/test/upload/list/delete/folder/search/info) | `web`, CSRF؛ لا `auth` أو `verified` | لا |
| `/api/civil-registry/*` | GET, POST | `CivilRegistrySearchController` (search/index/cache/stats) | `web`، بلا `auth` أو `throttle` ظاهر | لا |
| `/api/scout/instant-search`, `/api/scout/advanced-search` | GET, POST | `ScoutSearchController@instantSearch/advancedSearch` | `web`؛ بلا `auth` أو `throttle` ظاهر | لا |
| `/api/civil-registry/person-details/{personId}` | GET | closure داخل `routes/api.php` | `api`, `ThrottleRequests:api` | لا |
| `/api/civil-registry/save-bank-account` | POST | closure داخل `routes/api.php` | `api`؛ خارج مجموعة `auth:sanctum` | لا |
| `/api/chunked-upload-test` | POST | `ChunkedUploadController@handleChunk` | `api`, `ThrottleRequests:api` | لا |
| `/api/uploads/chunk` | POST | `ChunkedUploadController@handleChunk` | `auth:sanctum`, `large.upload` | نعم |
| `/test-download-all`, `/test-download-selected` | GET, POST | `UnifiedFileManagementController@downloadAllDuplicateFiles/downloadSelectedDuplicateFiles` | `web`, CSRF؛ بلا `auth` | لا |
| `/api/files/process-folder-upload` | POST | `UnifiedFileManagementController@processFolderUpload` | المسار الظاهر: `web`/CSRF؛ بلا `auth` | لا |
| `/api/files/download/{fileId}` | GET | `UnifiedFileManagementController@downloadFile` | `api`, `ThrottleRequests:api` | لا؛ لكن الدالة غير موجودة في الكنترولر |
| `/api/files/*` | GET, POST | تحليلات/رفع/استيراد/حالة دفعات | غالباً `api` مع throttle، وبعض تعريفات web؛ بلا `auth` في النتائج المفحوصة | لا |

تعريف `/api/files/process-folder-upload` مكرر في `routes/web.php`، وموجود أيضاً ضمن مجموعة `files` في `routes/api.php`. كما توجد مجموعة web صريحة بـ`withoutMiddleware(['auth','verified'])` حول عمليات إدارة ملفات مكررة.

## 3) التدفقات الحرجة

### 3.1 الدخول التلقائي وبوابة التسجيل العامة

1. `routes/web.php:163-164` يعرّف `/auto-login` و`/s/{credentials}` خارج مجموعة `auth`.
2. `ShowGeneralRegisrationController::autoLogin` يقرأ حقلي query `email` و`password`؛ `autoLoginShort` يقسم قيمة المسار إلى جزأين.
3. `processAutoLogin` يبحث في `sponsorships` عن تطابق `identity_number` و`internal_file_number` (`ShowGeneralRegisrationController.php:70-115`).
4. عند عدم وجود مستخدم، ينشئ مستخدماً وبريداً يساوي رقم الهوية وكلمة مرور مشتقة بـ`bcrypt($fileNumber)`، ثم يسجل الدخول مباشرة ويخزن `active_sponsorship_id` و`active_internal_file_number` في الجلسة.
5. لا يظهر middleware لتحديد المعدل على هذين المسارين. لم يُختبر تسجيل الدخول ببيانات واقعية.
6. صفحة `/` والتخزين الأولي/رفع الأجزاء مسارات عامة، بينما صفحة التحديث داخل مجموعة `auth` و`verified`.
7. `index` يجلب الكفالة من `active_sponsorship_id`، وإلا يبحث برقم المستخدم، ثم يحمل علاقات `sponsor` و`relationData` و`rePeople` و`deadPepole`.

### 3.2 حفظ الاستمارة `updateSponsorshipData`

1. يبدأ الكنترولر بـ`Auth::user()` و`allFiles()`، يجمع أسماء الملفات وأخطاء الرفع وأعدادها، ثم يسجل ملخصاً في log. بعض الحقول المسجلة تتضمن أرقام هوية/أسماء ملفات؛ لا تُعرض قيم السجلات هنا.
2. `authorizeSponsorshipForUpdate` يفحص وجود المستخدم، ثم يطابق الكفالة مع `active_sponsorship_id` أو مع البريد/رقم الهوية ورقم الملف. هذا الفحص موجود في `ShowGeneralRegisrationController.php:3572-3614`.
3. قواعد الطلب: `fields` و`family_members` مصفوفتان اختياريتان؛ `attachments.*.*` يقبل `pdf,jpg,jpeg,png,gif,webp,heic,heif,mp4,avi,mov,wmv,webm` حتى 50MB لكل ملف.
4. `validateRequiredAttachments` يستخرج أنواع الوثائق المفعّلة من إعداد الجمعية و`document_types`، ويعتبر الوثائق المخزنة سابقاً ملبّية للمتطلب؛ عند النقص يرجع 422 أو redirect قبل المعاملة.
5. تبدأ `DB::beginTransaction()` قرب السطر 1461. بعدها يحدّث حقول الكفالة و`data` و`re_people` و`dead_people` و`guardian_bank_accounts` و`portal_general_registration_field_values` بحسب نوع الشخص والحقول المرسلة. التغييرات تشمل إعادة ربط بيانات المعيل عند العثور على سجل قائم.
6. مسار Rclone يرفع الملف من مساره المؤقت، ثم يسجّل المرفق محلياً فقط عند تفعيل إعداد الحفظ المحلي. إذا أعاد Rclone `success=false` يسجل `RCLONE_UPLOAD_FAILED` ثم ينفذ `continue`؛ لا يوقف بقية الحفظ.
7. مسار التخزين المحلي يستعمل `UploadedFile::storeAs(..., 'public')` ثم ينشئ `Attachment`. المسار البديل لـGoogle Drive API يرفع الملف، يمنحه صلاحية `anyone/reader`، ثم يحفظ الرابط في `attachments`.
8. بعد الكتابة يحدّث الحالة إلى `sponsorship_status_id=2` ثم يعمل `commit` (`:3373-3386`) حتى إذا تم تجاوز ملف فشل رفعه. إطلاق `GenerateOrphanReportPdf` يحدث بعد الـcommit؛ فشل الإطلاق يُسجل ولا يعكس نجاح الحفظ.
9. عند exception قبل commit يعمل catch على `Throwable` ويجري `rollBack` عند وجود معاملة (`:3438`). هذا لا يحذف ملفات Rclone/Google Drive أو الملفات المحلية التي أُنشئت مسبقاً.

### 3.3 تصدير الاستمارات والمرفقات

1. `BulkExportSponsorshipForms::handle` ينشئ قفلاً عبر Cache ويستخرج الكفالات للجمعية باستخدام chunks.
2. لكل كفالة، ينشئ `GenerateOrphanReportPdf` ثم يستدعي `$pdfJob->handle()` مباشرة (`BulkExportSponsorshipForms.php:232-233`)؛ لا يضع المهمة الفرعية في queue.
3. `GenerateOrphanReportPdf::handle` يبني بيانات التقرير من `sponsorships` والبيانات المرتبطة وحقول البوابة، يرندر HTML عبر Snappy/wkhtmltopdf، يحذف ملفات PDF القديمة المطابقة، ثم يكتب الملف في `storage/app/public/uploads/{relationId}`.
4. بعد الكتابة يُدرج سجل `attachments`، ثم يحاول رفع PDF إلى Google Drive إذا كان مفعلاً. خطأ PDF يُسجّل ويُعاد طرحه؛ الاستثناء يُحصى داخل التصدير الأب كفشل لهذه الكفالة، لكن الاستدعاء المباشر لا ينفذ retry للـjob الفرعي.
5. عند تصدير المرفقات، `exportAttachmentsToDrive` ينشئ `storage/app/attachments-export/{UUID}`، يستخرج مرفقات كل شخص ويجهز XLSX، ثم يرفع المجلدات عبر Rclone. عند النجاح يحذف المجلد؛ عند الفشل يبقى حتى تمر عليه `purgeStaleExportDirs` في تشغيل لاحق (حدّ الحذف ثلاثة أيام). عند تعطيل Drive يبقى التصدير محلياً عمداً.
6. `RcloneGoogleDriveService` يعتمد على `services.rclone.path`, `remote_name`, `root_folder`, `config`; لم تُقرأ ملفات الاعتماد. البيئة المحلية لا تحتوي Rclone remote فعالاً.

### 3.4 الطوابير

| المهمة/الإعداد | tries | backoff | timeout | ملاحظة |
|---|---:|---:|---:|---|
| `BulkExportSponsorshipForms` | 1 | 120 | 7200 ثانية | `failed()` موجود |
| `GenerateOrphanReportPdf` | 3 | 60 | غير ظاهر في تعريف المهمة | لا `failed()` مخصص ظاهر؛ داخل bulk يُستدعى `handle()` مباشرة |
| `ProcessRcloneUploadJob` | 5 | `[60,300,900,1800]` | 3600 ثانية | `failed()` موجود |
| database queue `retry_after` | — | — | 3900 ثانية | أقل من timeout مهمة bulk |
| `laravel-worker.conf` | `--tries=3` | — | `--timeout=300`, `--max-time=3600` | `numprocs=2`, `stopwaitsecs=3600` |

`laravel-worker.conf` ملف بالمشروع وليس إثباتاً أن Supervisor يعمل به في الإنتاج. إذا نفذ bulk مدة تتجاوز `retry_after` فقد يعاد إتاحته بينما لم تنتهِ المهمة؛ كما أن `$tries=1` يتغلب عملياً على توقع retries الثلاثة من worker لهذه المهمة.

## 4) قاعدة البيانات

### الجداول الرئيسية من migrations

| الجدول | الأعمدة/العلاقات الأساسية في المصدر | الفهارس الملحوظة |
|---|---|---|
| `sponsorships` | `id`, `sponsor_id`, organization/file numbers, `identity_number`, `guardian_identity_number`, `relation_id_number`, `person_type`, `sponsorship_status_id`, dates, `notes`, `created_by`; علاقات إلى sponsors/status/type/users و`data`/`re_people` عبر أرقام الملف/الهوية | sponsor, internal/external file, identity, status/type, dates؛ أضيفت فهارس relation وguardian |
| `attachments` | `id`, `person_identity_number`, `stored_file_name`, `file_path`, `file_type`, timestamps؛ migrations لاحقة تضيف `file_size`, `original_file_name`, `mime_type`, `record_number`, `file_hash` | فهرس identity؛ إضافة unique مركب prefix على identity/file_type/file_hash |
| `data` | `id`, `file_id_number`, `data_section_id`, `data_request_status`, `data_id_number`, الاسم الرباعي، relationship, birth/gender/phones, counts, marital/education/displacement, addresses, city/province/health/needs/employment/housing, `data_user_insert_data` | `file_id_number` unique؛ migrations تضيف `data_id_number` و`file_id_number` وفهرس أسماء |
| `re_people` | `id`, `registration_id`, sponsorship status, الاسم الرباعي، `person_id`, birth/age/gender, health, birth certificate/photo, guarantee type/note | فهرس `person_id` والأسماء؛ لا يظهر فهرس `registration_id` في migrations المفحوصة |
| `dead_people` | `id`, `re_file_id`, بيانات الأب والأم، `father_id`, `mother_id`, تواريخ الوفاة والأسباب؛ روابط إلى `data` و`death_reasons` | فهارس father/mother؛ FK على `re_file_id` |
| `document_types` | `id`, `description`, `pref`, `file_type`, أعلام enable/required للبوابات basic/family/deceased | المفتاح الأساسي فقط ظاهر |
| `sponsor_field_settings` | `id`, `sponsor_id`, JSON `enabled_documents`، أعلام `field_*` لحقول الشخص/السكن/التعليم/الصحة/المعيل/الإخوة/المتابعة/المرفقات، وإعدادات حفظ محلي أضيفت لاحقاً | FK وunique على `sponsor_id` |
| `portal_general_registration_field_values` | `id`, `sponsorship_id`, `file_id_number`, `identity_number`, `field_key`, `field_value`, `updated_by_user_id`, timestamps | unique `(file_id_number, field_key)` وفهارس sponsorship/file/identity/user |
| `guardian_bank_accounts` | `id`, `guardian_registration`, bank, account/IBAN/phone/owner and approval fields added later | فهرس `guardian_registration` وFK إلى `data`/bank |
| `google_drive_uploads` | بصمة محلية، مسار/اسم/حجم/MIME، الحالة والتقدم، نوع/معرف الكيان، upload user والربط بـattachment | فهارس status/hash/entity/user |
| `jobs`, `failed_jobs` | queue payload/attempts/reservation وfailed UUID/payload/exception | جدول queue وUUID الفاشل؛ تعتمد الفهارس النهائية على migrations |

### اختلافات/مخاطر schema

1. Migration `2025_12_18_000000_create_portal_general_registration_field_values_table.php` تنفذ `dropIfExists` في `up()` قبل `create`؛ إذا كان الجدول موجوداً لكن migration غير مسجل، يمكن فقد بياناته.
2. Migration إنشاء `re_people` تحذف `persons` في `down()`، وmigration `dead_people` تحذف `dead_pepoles` بدلاً من `dead_people`.
3. `DeadPepole` يضبط `$primaryKey='re_file_id'` بينما migration ينشئ المفتاح الأساسي `id`; `re_file_id` FK وليس unique في migration.
4. `RePeople` يضبط `$incrementing=false` مع أن migration ينشئ `bigIncrements('id')`.
5. `data.data_id_number`, `data_phone_number`, `data_alt_phone_number` و`dead_people.father_id/mother_id` أعداد صحيحة، رغم استعمالها كمُعرّفات/أرقام اتصال في التدفقات. الأصفار البادئة لا تُحفظ كجزء من قيمة integer.
6. `ChunkedUploadController` يمرر إلى `Attachment::create` حقولاً إضافية غير موجودة في `$fillable` وغير معرفة في migrations الحالية لـ`attachments` (`file_name`, `mime_type`, `attachment_type`, `description`, `entity_type`, `entity_id`, `storage_type`, `google_drive_file_id`, `google_drive_path`, `uploaded_by`, `is_synced`). كما لا يمرر `person_identity_number` المطلوب في migration الأساسية.
7. لا يمكن إثبات ما إذا كانت الأعمدة/الجداول أعلاه موجودة يدوياً في قاعدة البيانات؛ `migrate:status` وفحوص schema الحية تعذرت بسبب رفض اتصال MySQL.

## 5) الأخطاء المحتملة

| # | الخطورة | النوع | الملف:السطر | الوصف | السيناريو الذي يظهر فيه الخطأ | الأثر | درجة الثقة |
|---:|---|---|---|---|---|---|---|
| 1 | حرجة | أمني | `routes/api.php:557-665`; `CivilRegistrySearchController.php:39-65,144-167` | واجهات غير مصادق عليها تبحث برقم الهوية/الاسم، وتعيد بيانات السجل المدني؛ `person-details` يعيد كذلك بيانات شخصية وحسابات بنكية | عند إتاحة اتصال `civilregistry` | كشف بيانات هوية وعناوين وبيانات مصرفية؛ لا يتطلب حساباً | [مؤكد] المسارات والمخرجات؛ نجاح الاتصال الخارجي غير متحقق |
| 2 | حرجة | أمني/تكامل | `routes/web.php:445-453`; `GoogleDriveTestController.php:140-166` | مسارات اختبار Drive العامة تشمل list/upload/delete/create/search/info | عند وجود credentials عاملة في الخادم | كشف أو حذف/رفع ملفات باستخدام صلاحية الخدمة | [مؤكد] غياب auth والربط بالخدمة؛ تفعيل credentials في الإنتاج غير متحقق |
| 3 | عالية | أمني/تشغيلي | `routes/web.php:142`; `GeneralRegistrationController.php:1207-1231`; `routes/api.php:36` | رفع chunks عام؛ `file_id_number` يدخل مباشرة في مسار مجلد، مع `mkdir(0777)` وبدون حد ظاهر للحجم/عدد الأجزاء | طلب عام بمدخل مسار غير موثوق أو أجزاء كثيرة | كتابة خارج مجلد temp المقصود داخل حدود صلاحيات PHP، وملء التخزين/إفساد الملفات | [مؤكد] |
| 4 | عالية | أمني/خصوصية | `routes/web.php:71-72`; `UnifiedFileManagementController.php:4216-4328` | تنزيل جميع ملفات `DuplicateFileTemp` في ZIP متاح بلا auth | عند وجود ملفات مكررة محفوظة | تسريب ملفات قد تتضمن مستندات شخصية؛ تحميل كل السجلات إلى الذاكرة | [مؤكد] |
| 5 | عالية | أمني | `routes/web.php:163-164`; `ShowGeneralRegisrationController.php:31-115` | بيانات الدخول تُقبل في URL؛ `processAutoLogin` يطابق الهوية ورقم الملف ويسجل الدخول دون كلمة مرور تفاعلية أو rate limit ظاهر | مشاركة الرابط أو ظهوره في سجل المتصفح/الخادم أو إعادة تخمين بيانات المطابقة | انتحال جلسة مستخدم؛ تسريب بيانات اعتماد عبر URL | [مؤكد] التدفق؛ قابلية التخمين/الاستغلال تعتمد على سرية أرقام الملف |
| 6 | عالية | أمني/IDOR | `ShowGeneralRegisrationController.php:4252-4278`; route `web.php:174` | `serveRcloneAttachment` يستخدم `Attachment::find($attachmentId)` ولا يتحقق من ارتباطه بهوية المستخدم/الكفالة النشطة | مستخدم مسجل يطلب معرف مرفق لا يخصه | قراءة مرفقات مستخدمين آخرين عند معرفة/تخمين المعرف | [مؤكد] |
| 7 | عالية | خصوصية | `GoogleDriveService.php:491-503`; الاستدعاء `ShowGeneralRegisrationController.php:3286` | الملف المرفوع يمنح صلاحية Drive `type=anyone, role=reader` | كل رفع عبر مسار Google Drive API | أي شخص يحصل على الرابط يستطيع قراءة مستندات الكفالة دون جلسة التطبيق | [مؤكد] |
| 8 | عالية | أمني/تكامل | `GoogleDriveService.php:62,151,173,499` | تعطيل TLS peer verification في HTTP وcURL | اتصال Google عبر شبكة غير موثوقة/اعتراض TLS | إمكانية قبول شهادة مزورة واعتراض/تعديل الاتصال أو بيانات الاعتماد المنقولة | [مؤكد] |
| 9 | عالية | منطقي/تشغيلي | `ShowGeneralRegisrationController.php:3096-3105,3373-3386` | عند فشل Rclone يسجل الخطأ ثم `continue`؛ في النهاية يضبط الحالة إلى 2 ويعمل commit | يفشل رفع مرفق واحد بينما بقية البيانات صالحة | تظهر الاستمارة كأنها انتقلت للمراجعة مع مستند ناقص؛ تقدم الملف أُعلن منجزاً قبل نتيجة الرفع | [مؤكد] |
| 10 | عالية | تكامل/اتساق بيانات | `ShowGeneralRegisrationController.php:1461,3088-3121,3270-3290,3386,3438` | رفع ملفات/إنشاء صلاحيات Drive يحدث داخل DB transaction؛ rollback لا يعكس الأثر الخارجي | يفشل تحديث قاعدة البيانات بعد رفع الملف أو بعد جعل الملف عاماً | ملفات orphan في Drive/local أو سجلات DB غير متطابقة مع الواقع | [مؤكد] |
| 11 | متوسطة | منطقي/تخزين | `ShowGeneralRegisrationController.php:3116-3130,3191-3205`; `config/filesystems.php:34-48` | `storeAs` نتيجته لا تُفحص، وقرص `public` مضبوط `throw=false`، ثم يُنشأ Attachment ويزداد العدّاد | امتلاء القرص أو رفض الكتابة أو صلاحيات غير كافية | سجل يشير إلى مسار ملف لم يُحفظ، بينما يستمر الحفظ | [مؤكد] الكود؛ وقوع فشل كتابة فعلي غير متحقق |
| 12 | متوسطة | تكامل/منطقي | `ChunkedUploadController.php:380-397`; `Attachment.php:12-19`; migrations attachments | إنشاء المرفق لا يرسل `person_identity_number`/`file_type`، ويمرر حقولاً خارج `$fillable` وغير موجودة في migrations | اكتمال رفع chunk ووصول التنفيذ إلى إنشاء Attachment | يتوقف إنشاء المرفق قبل إنشاء `GoogleDriveUpload` أو dispatch؛ الملف المجمع قد يبقى بلا سجل صالح | [مؤكد] التعارض في المصدر؛ schema الحية غير متحققة |
| 13 | متوسطة | تشغيلي/تزامن | `BulkExportSponsorshipForms.php:45-55,232-233`; `config/queue.php:38-53`; `laravel-worker.conf:3,9,12` | timeout المهمة 7200 أكبر من `retry_after=3900` و`stopwaitsecs=3600`؛ كما أن PDF الفرعي ينفذ `handle()` مباشرة فلا يستفيد من retries الثلاثة | تصدير يستغرق أكثر من 65 دقيقة أو يفشل PDF مؤقتاً | إعادة إتاحة المهمة أثناء عملها/إنهاء worker أو فشل بلا retry للجزء الفرعي | [مؤكد] القيم وطريقة الاستدعاء؛ أثر التزامن يتطلب مهمة طويلة |
| 14 | متوسطة | تخزين/تشغيلي | `GeneralRegistrationController.php:1207-1235` | مجلدات chunk العامة تُنشأ دون تنظيف ظاهر في الدالة؛ رُصدت 110 ملفات قديمة في `storage/app/public/temp_uploads`، مجموعها 15,217,900 بايت، من 31-08 إلى 01-10-2026 | رفع لا يكتمل أو ملفات قديمة تبقى بعد انتهاء العميل | تراكم ملفات قابلة للوصول عبر التخزين العام، وزيادة المساحة المستخدمة | [مؤكد] الملفات والتواريخ؛ مصدر كل ملف لم يُتحقق |
| 15 | متوسطة | منطقي | `routes/api.php:212-228`; `UnifiedFileManagementController` | المسار `api/files/download/{fileId}` يشير إلى `UnifiedFileManagementController@downloadFile`، لكن لا توجد هذه الدالة في الكنترولر أو دالة `__call`؛ البحث وجد `GoogleDriveService::downloadFile` فقط | استدعاء مسار التنزيل | استثناء/استجابة 500 بدلاً من تنزيل الملف | [مؤكد] |
| 16 | متوسطة | منطقي/تزامن | `ShowGeneralRegisrationController.php:1461,3109-3121,3178-3200,3373` | لا يظهر قفل/مفتاح idempotency أو قفل صف للكفالة؛ أسماء الملفات تعتمد count ثم timestamp بالثواني | ضغط مزدوج أو إعادة إرسال متزامنة قبل تحديث الحالة | تكرار سجلات attachments أو تصادم اسم/استبدال الملف؛ قد ينفذ الطلبان قبل إغلاق الحالة | [مرجّح] |
| 17 | متوسطة | خصوصية/تشغيلي | `ShowGeneralRegisrationController.php:1330-1400,3441-3450`; `ScoutSearchController.php:43-50,116-150` | سجلات التشخيص تحتوي مفاتيح/قيم هوية، أسماء ملفات أصلية، وعبارات البحث؛ لم تُعرض قيمها في هذا التقرير | فحص السجلات أو منح صلاحية قراءة logs لأشخاص غير مخولين | تسريب بيانات شخصية في ملفات السجل ونسخها الاحتياطية | [مؤكد] |
| 18 | متوسطة | تخزين/اتساق | `GenerateOrphanReportPdf.php:85-124`; `config/filesystems.php:34-48` | يحذف ملفات PDF القديمة قبل إنشاء الجديدة؛ `Storage::put` غير مفحوص و`throw=false` ثم يدرج سجل Attachment | يفشل توليد/كتابة PDF بعد حذف السابق أو يفشل التخزين | فقدان الملف السابق أو سجل DB يشير إلى PDF غير موجود | [مؤكد] |
| 19 | متوسطة | منطقي/قاعدة بيانات | `DeadPepole.php:9-14`; `2025_05_07_115548_create_dead_pepoles_table.php:14-18,35` | Eloquent يستخدم `re_file_id` كمفتاح أساسي بينما الجدول ينشئ `id` كمفتاح أساسي و`re_file_id` كـFK غير فريد | `find`, `save`, `delete`, أو عمليات `updateOrCreate` عبر Eloquent | تحديث/حذف صفوف متعددة أو سلوك قراءة غير متوقع | [مؤكد] |
| 20 | متوسطة | منطقي/قاعدة بيانات | `RePeople.php:8-14`; `2025_05_05_115549_create_re_people_table.php:14-16` | النموذج يعلن `$incrementing=false` مع عمود `id` auto-increment | `RePeople::create`/`updateOrCreate` وإنشاء عدة سجلات مرتبطة | لا يحتفظ Eloquent بمعرف الصف المولّد على النموذج؛ قد تتعطل العلاقات اللاحقة | [مؤكد] الاختلاف؛ أثر كل مسار يعتمد على الاستخدام |
| 21 | متوسطة | تشغيلي/بيانات | `2025_12_18_000000_create_portal_general_registration_field_values_table.php:11-17`; migrations re/dead: `:44`, `:47` | migration البوابة تحذف الجدول في `up()`؛ و`down()` لـ`re_people`/`dead_people` يحذف أسماء جداول خاطئة | تشغيل migration على جدول موجود غير مسجل أو rollback | فقد بيانات جدول البوابة؛ rollback يترك الجدول الصحيح ويحذف جدولاً غير مقصود | [مؤكد] السلوك؛ وقوعه يتطلب migration/rollback |
| 22 | متوسطة | منطقي/بيانات | `2025_05_06_115550_create_data_table.php:19,27-28`; `dead_people` migration `:22,30` | أرقام الهوية/الهاتف ممثلة كـinteger في بعض الجداول | أرقام تبدأ بصفر أو تُحوّل من Excel/JSON | فقد الأصفار البادئة واختلاف المطابقة بين قواعد البيانات | [مؤكد] النوع؛ وجود حالات متأثرة فعلياً غير متحقق |
| 23 | متوسطة | تشغيلي/مراقبة | `UnifiedFileManagementController.php:514-555` | catch يعيد `success=true` وإحصاءات صفرية عند فشل الاستعلام | خطأ قاعدة/قرص أثناء جلب التحليلات | العميل يفسر الفشل كأن الأرقام الحقيقية صفر ولا يعيد المحاولة | [مؤكد] |
| 24 | منخفضة | أداء | `ShowGeneralRegisrationController.php:225-226`; `RePeople` migration/indexes | `LIKE "%...%"` يبدأ wildcard فلا يستفيد من فهرس identity؛ كما لا يظهر فهرس `re_people.registration_id` في migrations رغم الاستعلام به | تضخم بيانات الكفالات/الأفراد | مسح أوسع وتأخر الصفحة/التسجيل | [مؤكد] تركيب الاستعلام/غياب الفهرس في migrations؛ حجم الأثر غير متحقق |
| 25 | متوسطة | منطقي/ملفات | `ShowGeneralRegisrationController.php:3198,4265` | التخزين يحفظ `file_path` مسبوقاً بـ`storage/` ثم خدمة المرفق تضيف `storage/` مرة ثانية | فتح مرفق محلي من `serveRcloneAttachment` | رابط التحويل يصبح غالباً `/storage/storage/...` فيفشل الوصول | [مؤكد] |
| 26 | منخفضة | تشغيلي/إعداد | `RcloneGoogleDriveService.php:45-48`; مواضع `env()` في jobs/services | توجد استدعاءات `env()` خارج ملفات config كقيمة fallback | عند الاعتماد على fallback مع `config:cache` أو عند غياب مفتاح config | قد لا تتوفر قيمة `env()` وقت التشغيل؛ لم يثبت أن المسار الحالي يعتمد عليها | [مرجّح] |

### أدلة مختصرة للملاحظات

#### #1 — واجهات السجل المدني والبيانات البنكية العامة

**الموقع:** `routes/api.php`، closure `person-details`، الأسطر 557-558؛ المخرجات 614-637.

```php
Route::get('/civil-registry/person-details/{personId}', function ($personId) {
    try {
        $person = DB::connection('civilregistry')
            ->table('persons')->where('ID', $personId)->first();
```

**الموقع:** `routes/api.php`، closure `person-details`، الأسطر 614-637.

```php
                'person' => [
                    'id_number' => $person->CI_ID_NUM,
                    'full_name' => $fullName,
                    'mother_name' => $person->MOTHER_NAME1,
                    'birth_date' => $person->CI_BIRTH_DT,
                    'street' => $person->STREET,
                    'house_no' => $person->HOUSE_NO,
                ],
                'bank_accounts' => $bankAccounts->toArray(),
```

**الموقع:** `CivilRegistrySearchController::searchById`، الأسطر 144-167.

```php
$results = $this->searchService->searchByIdNumber($idNumber);
return response()->json([
    'success' => $results['success'],
    'data' => $results['data'] ?? [],
    'total_count' => $results['total_count'] ?? 0,
    'execution_time' => $results['execution_time'] ?? '0 ms',
    'engine' => $results['engine'] ?? 'Unknown'
]);
```

#### #2 — إدارة Google Drive من مسارات بلا مصادقة

**الموقع:** `routes/web.php`، مجموعة Google Drive test، الأسطر 445-453.

```php
Route::prefix('google-drive-test')->group(function () {
    Route::post('/upload', [GoogleDriveTestController::class, 'uploadTest']);
    Route::get('/list', [GoogleDriveTestController::class, 'listFiles']);
    Route::delete('/delete', [GoogleDriveTestController::class, 'deleteFile']);
    Route::post('/create-folder', [GoogleDriveTestController::class, 'createFolder']);
    Route::get('/search', [GoogleDriveTestController::class, 'searchFiles']);
});
```

**الموقع:** `GoogleDriveTestController::deleteFile`، الأسطر 140-160.

```php
$request->validate(['file_id' => 'required|string']);
if (!$this->driveService) {
    throw new Exception('فشل تهيئة خدمة Google Drive');
}
$fileId = $request->input('file_id');
$this->driveService->deleteFile($fileId);
return response()->json(['success' => true]);
```

#### #3 — الرفع العام ومسار التخزين

**الموقع:** `routes/web.php:142` و`GeneralRegistrationController::uploadChunk`، الأسطر 1207-1231.

```php
$fileId = $request->input('file_id_number');
...
$tempDir = storage_path('app/public/temp_uploads/' . $fileId);
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
$safeFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
$tempPath = $tempDir . '/' . $safeFileName;
file_put_contents($tempPath, file_get_contents($chunk->getRealPath()), FILE_APPEND);
```

**الموقع:** `routes/api.php:36`، والمسار البديل المحمي موجود تحت `/api/uploads/chunk` قرب السطر 833.

```php
// TEST ROUTE WITHOUT AUTH
Route::post('/chunked-upload-test', [ChunkedUploadController::class, 'handleChunk']);
```

#### #4 — تنزيل كل الملفات المكررة

**الموقع:** `routes/web.php:71-72`; `downloadAllDuplicateFiles`، الأسطر 4216-4220.

```php
Route::get('/test-download-all', [UnifiedFileManagementController::class, 'downloadAllDuplicateFiles']);
Route::post('/test-download-selected', [UnifiedFileManagementController::class, 'downloadSelectedDuplicateFiles']);
...
$files = DuplicateFileTemp::all();
```

#### #5 — بيانات الاعتماد في URL

**الموقع:** `routes/web.php:163-164`; `autoLogin` و`processAutoLogin`، الأسطر 53-75.

```php
$identityNumber = $request->query('email');
$fileNumber = $request->query('password');
...
$sponsorship = Sponsorship::with('relationData')
    ->where('identity_number', $identityNumber)
    ->where('internal_file_number', $fileNumber)
    ->first();
```

**الموقع:** `processAutoLogin`، الأسطر 87-105.

```php
'email' => $identityNumber,
'password' => bcrypt($fileNumber),
...
Auth::login($user);
request()->session()->put('active_sponsorship_id', $sponsorship->id);
```

#### #6 — عدم فحص ملكية المرفق

**الموقع:** `serveRcloneAttachment`، الأسطر 4252-4269؛ route محمي عند `routes/web.php:174`.

```php
$attachment = Attachment::find($attachmentId);
if (!$attachment) {
    abort(404, 'المرفق غير موجود');
}
$filePath = $attachment->file_path;
if (!$this->isRclonePath($filePath)) {
    return redirect(asset('storage/' . $filePath));
}
```

#### #7 — مشاركة ملفات Drive للعامة

**الموقع:** `GoogleDriveService::makePublicReadOnly`، الأسطر 491-503؛ الاستدعاء في `updateSponsorshipData:3286`.

```php
$permission = [
    'type' => 'anyone',
    'role' => 'reader',
];
$response = Http::withOptions(['verify' => false])
    ->withToken($this->accessToken)
    ->post($this->baseUrl . '/files/' . $fileId . '/permissions?supportsAllDrives=true', $permission);
```

#### #8 — تعطيل تحقق TLS

**الموقع:** `GoogleDriveService::getAccessToken/uploadFile`، الأسطر 62,151,173.

```php
$response = Http::withOptions([
    'verify' => false,
])->asForm()->post('https://oauth2.googleapis.com/token', [...]);
...
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
```

#### #9 و#10 — فشل الرفع والمعاملة

**الموقع:** `updateSponsorshipData`، الأسطر 3096-3105.

```php
$progress->fileDone((int) $file->getSize());
if (!$uploadResult['success']) {
    Log::error('RCLONE_UPLOAD_FAILED', [...]);
    continue;
}
```

**الموقع:** `updateSponsorshipData`، الأسطر 1461, 3373-3386, 3438.

```php
DB::beginTransaction();
...
$sponsorship->sponsorship_status_id = 2;
$sponsorship->save();
DB::commit();
...
DB::rollBack();
```

#### #11 — حفظ محلي دون فحص النتيجة

**الموقع:** `updateSponsorshipData`، الأسطر 3191-3200؛ `config/filesystems.php:34-48`.

```php
$path = $file->storeAs($folder, $newFileName, 'public');
$progress->fileDone((int) $file->getSize());
Attachment::create([
    'person_identity_number' => $sponsorship->identity_number,
    'stored_file_name' => $newFileName,
    'file_path' => 'storage/' . $path,
    'file_type' => $docTypeId,
    'file_size' => $file->getSize(),
]);
```

#### #12 — عقد Attachment لا يطابق مسار chunked upload

**الموقع:** `ChunkedUploadController::assembleAndUpload`، الأسطر 380-397؛ `Attachment.php:12-19`.

```php
$attachment = Attachment::create([
    'file_name' => $fileName,
    'stored_file_name' => $fileName,
    'file_path' => 'pending_rclone_upload',
    'file_size' => $fileSize,
    'mime_type' => $fileType,
    'entity_type' => 'sponsorship',
    'entity_id' => $sponsorshipId,
    'uploaded_by' => auth()->id() ?? 1,
]);
```

```php
protected $fillable = [
    'person_identity_number', 'stored_file_name', 'file_path',
    'file_type', 'file_size', 'file_hash',
];
```

#### #13 — إعدادات retries والـtimeout

**الموقع:** `BulkExportSponsorshipForms.php:45-55,232-233`; `config/queue.php:53`; `laravel-worker.conf:3,9,12`.

```php
public $tries = 1;
public $backoff = 120;
public $timeout = 7200;
...
$pdfJob = new GenerateOrphanReportPdf($sponsorship->id, $this->sponsorId);
$pdfJob->handle();
```

```ini
command=php /var/www/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=300
numprocs=2
stopwaitsecs=3600
```

#### #14 — chunks العامة المتروكة

**الموقع:** `GeneralRegistrationController::uploadChunk`، الأسطر 1218-1235.

```php
$tempDir = storage_path('app/public/temp_uploads/' . $fileId);
...
$tempPath = $tempDir . '/' . $safeFileName;
file_put_contents($tempPath, file_get_contents($chunk->getRealPath()), FILE_APPEND);
if ($chunkIndex == $totalChunks - 1) {
    return response()->json([
        'success' => true,
        'complete' => true,
        'path' => 'temp_uploads/' . $fileId . '/' . $safeFileName
    ]);
}
```

#### #15 — مسار تنزيل بدالة غير موجودة

**الموقع:** `routes/api.php:212-228`; `UnifiedFileManagementController`.

```php
Route::get('/download/{fileId}', [UnifiedFileManagementController::class, 'downloadFile']);
```

لم يُعثر في `UnifiedFileManagementController` على `downloadFile` أو `__call`; الدالة التي تحمل هذا الاسم موجودة في `GoogleDriveService` وليست الكنترولر.

#### #16 — سباق طلبات الحفظ

**الموقع:** `updateSponsorshipData`، الأسطر 3109-3121 و3178-3200.

```php
$existingCount = Attachment::where('person_identity_number', $identity)
    ->where('file_type', $docTypeId)->count();
$timestamp = date('Ymd_His');
$newFileName = "...{$timestamp}{$serial}.{$extension}";
...
Attachment::create([...]);
```

#### #17 — تسجيل بيانات شخصية

**الموقع:** `updateSponsorshipData`، الأسطر 1340-1400؛ `ScoutSearchController::instantSearch`، الأسطر 43-50.

```php
$names[] = $file->getClientOriginalName();
...
Log::info('UPDATE_SPONSORSHIP_SUBMIT', [
    'attachments_summary' => $attachmentsSummary,
    'fields_keys_sample' => $fieldsIncomingKeysSample,
]);
```

#### #18 — تقرير PDF

**الموقع:** `GenerateOrphanReportPdf::handle`، الأسطر 85-124.

```php
Storage::delete($file);
...
Storage::put($fullPath, $pdfContent);
DB::table('attachments')->insert([
    'person_identity_number' => $relationIdNumber,
    'file_path' => 'storage/' . $publicPath,
    'file_type' => 'pdf',
]);
```

#### #19 و#20 — مفاتيح Eloquent

**الموقع:** `DeadPepole.php:9-14`; `RePeople.php:8-14`; migrations المشار إليها في الجدول.

```php
protected $primaryKey = 're_file_id';
protected $table = 'dead_people';
public $incrementing = false;
```

```php
class RePeople extends Model
{
    public $incrementing = false;
    protected $table = 're_people';
}
```

#### #21 — migrations هدامة/rollback خاطئ

**الموقع:** `2025_12_18_000000_create_portal_general_registration_field_values_table.php:11-17`.

```php
public function up(): void
{
    Schema::dropIfExists('portal_general_registration_field_values');
    Schema::create('portal_general_registration_field_values', function (Blueprint $table) {
```

**الموقع:** `2025_05_05_115549_create_re_people_table.php:44`; `2025_05_07_115548_create_dead_pepoles_table.php:47`.

```php
Schema::dropIfExists('persons');
...
Schema::dropIfExists('dead_pepoles');
```

#### #22 — الأرقام كـinteger

**الموقع:** `2025_05_06_115550_create_data_table.php:19,27-28`; `2025_05_07_115548_create_dead_pepoles_table.php:22,30`.

```php
$table->integer('data_id_number');
$table->integer('data_phone_number');
$table->integer('data_alt_phone_number');
```

#### #23 — تحليلات نجاح شكلي

**الموقع:** `UnifiedFileManagementController::getFileAnalytics`، الأسطر 535-555.

```php
} catch (\Exception $e) {
    ...
    return response()->json([
        'success' => true,
        'analytics' => [
            'overview' => ['total_files' => 0, 'total_size' => 0],
            'file_types' => [],
            'recent_activity' => [],
        ],
```

#### #24 و#25 — أداء ومسار مرفق محلي

**الموقع:** `ShowGeneralRegisrationController::index`، الأسطر 225-226.

```php
$sponsorship = Sponsorship::where('identity_number', $userIdNumber)
    ->orWhere('identity_number', 'LIKE', "%{$userIdNumber}%")
    ->first();
```

**الموقع:** `updateSponsorshipData:3198`; `serveRcloneAttachment:4265`.

```php
'file_path' => 'storage/' . $path,
...
return redirect(asset('storage/' . $filePath));
```

## 6) سجلات الأخطاء

الفترة المحسوبة: **17-10-2026 21:52 إلى 31-10-2026 21:52** حسب ساعة البيئة. جُمعت رؤوس السجل فقط؛ لم تُعرض رسائل حرة أو قيم/بيانات شخصية.

| التصنيف المختصر | العدد | أول ظهور | آخر ظهور | الملف:السطر من stack trace |
|---|---:|---|---|---|
| ERROR غير مصنف — نص الرسالة محذوف لتجنب احتمال PII | 774 | 23-10-2026 21:14 | 31-10-2026 19:47 | لا يوجد إطار تطبيق موحد قابل للتجميع |
| `PDOException` | 5 | 27-10-2026 09:01 | 27-10-2026 09:06 | `Connection.php:423` |
| SQLSTATE `HY000/2002` (رفض اتصال) | 4 | 27-10-2026 07:38 | 31-10-2026 19:20 | `Connection.php:829` |
| `RuntimeException` | 3 | 23-10-2026 21:14 | 26-10-2026 11:49 | `ArgvInput.php:222` |
| `FatalError` | 2 | 24-10-2026 08:39 | 27-10-2026 09:46 | `ServeCommand.php:95` |
| `ParseErrorException` | 1 | 27-10-2026 09:03 | 27-10-2026 09:03 | `ParseErrorException.php:44` |
| `QueryException` | 1 | 27-10-2026 09:19 | 27-10-2026 09:19 | `Connection.php:829` |
| `ErrorException` | 1 | 27-10-2026 09:34 | 27-10-2026 09:34 | `HandleExceptions.php:255` |
| `TypeError` | 1 | 27-10-2026 09:56 | 27-10-2026 09:56 | `DeathsDataTable.php:52` |
| WARNING | 55 | 23-10-2026 19:38 | 31-10-2026 19:18 | أنماط stack متفرقة؛ لا يوجد إطار واحد غالب |

**الإجمالي:** 792 ERROR، 55 WARNING، 0 CRITICAL. تشمل السجلات آثار تشغيل `php artisan test` أثناء التدقيق، لذلك ليست مؤشراً مستقلاً على إنتاج. ظهر في مخرجات الاختبار خطأ اتصال MySQL `SQLSTATE[HY000] [2002]`؛ تم حجب بقية نص SQL/السياق. لم تظهر السلاسل `RCLONE_UPLOAD_FAILED` أو `UPDATE_SPONSORSHIP_ERROR` أو `MISSING_REQUIRED_ATTACHMENTS` في نافذة السجل.

## 7) الإعدادات والبيئة

### PHP وHTTP server

| المصدر | `upload_max_filesize` | `post_max_size` | `max_file_uploads` | `memory_limit` | `max_execution_time` | `max_input_time` |
|---|---:|---:|---:|---:|---:|---:|
| CLI المحمّل `C:\xampp\php\php.ini` | 500M | 500M | 20 | 512M | 0 | -1 |
| `public/.htaccess`، بشرط تفعيل `mod_php7/8` | 1024M | 1024M | 100 | 2048M | 3600 | 3600 |
| `public/php.ini` (ملف بالمشروع؛ ليس مثبتاً أنه المحمّل للويب) | 2048M | 2048M | 100 | 4096M | 7200 | 7200 |

Laragon Apache vhost المحلي يوجه إلى `public/` ويسمح بـ`AllowOverride All`. لم يوجد `LimitRequestBody` أو `client_max_body_size` أو `fastcgi_read_timeout` في vhost/`.htaccess` المفحوصين؛ لم يُثبت إعداد Apache/NGINX العالمي أو SAPI الفعلي للويب. لا يمكن اعتبار قيم CLI قيماً فعلية لـPHP في web worker.

### Queue وRclone وملفات البيئة

- worker config في `laravel-worker.conf`: `numprocs=2`, `--timeout=300`, `--tries=3`, `--max-time=3600`, `stopwaitsecs=3600`. تفعيل الملف في الإنتاج غير متحقق.
- Rclone المحلي: `v1.75.0`. `listremotes` لم يُظهر remote، ولم يوجد ملف إعداد Rclone الافتراضي؛ `about` لم ينجح لعدم وجود remote. `RCLONE_PATH` و`RCLONE_CONFIG` مضبوطا الاسم في `.env` لكن المسارين المشار إليهما غير موجودين محلياً. اسم remote الافتراضي في config هو `alhayahorphans`؛ لا يوجد remote فعّال محلياً بهذا الاسم.
- Google Drive: مسار `storage/app/google/credentials.json` الذي يفحصه `GoogleDriveService` غير موجود محلياً؛ لا يثبت ذلك حالة production. لم يُفتح أي ملف credentials.
- فُحص وجود المفاتيح فقط دون عرض قيمها: `APP_KEY`, مفاتيح `DB_*`, `CACHE_DRIVER`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, مفاتيح `RCLONE_*`, `USE_RCLONE_FOR_UPLOADS`, `USE_LOCAL_STORAGE_FOR_UPLOADS`, `APP_DEBUG`. مفاتيح `GOOGLE_APPLICATION_CREDENTIALS` و`GOOGLE_DRIVE_GENERAL_REGISTRATION_PARENT_ID` و`GOOGLE_DRIVE_FOLDER_ID` غير معبأة في `.env` المحلي. لم تُدرج أي قيمة سرية.

### التخزين والصلاحيات

- `storage/app/public/uploads`: 1,159 ملفاً، **208,331,340 بايت**.
- `storage/app/attachments-export`: غير موجود عند الفحص؛ الحجم صفر.
- `storage/app/public/temp_uploads`: 110 ملفات، **15,217,900 بايت**، أقدمها 31-08-2026 وأحدثها 01-10-2026.
- `storage/app/chunks`: 18 ملفاً، 5,172 بايت؛ الأقدم 24-09-2026 والأحدث 26-09-2026. لم يُثبت أيها انتهى عمره أو يجوز حذفه.
- `storage/app/temp_uploads`: لا ملفات عند الفحص.
- `public/storage` junction محلي يشير إلى `E:\laragon\www\alhayahorphans\storage\app\public`؛ الهدف غير موجود، ويختلف عن جذر المشروع الحالي `alhayahorphans55`.
- تحقق الوصول للقراءة/الكتابة تم تحت حساب CLI فقط؛ صلاحيات هوية Apache/Supervisor لم تُقَس.

## 8) الاختبارات

- الموجود: Unit وFeature tests ضمن `tests/Feature` و`tests/Unit`، وتشمل أمثلة Laravel، المصادقة، السجل المدني، الملفات، المزامنة والتعارضات، وميزات الإدارة.
- الأمر: `php artisan test`.
- النتيجة: **170 failed, 1 skipped, 7 passed (11 assertions)**، exit code `2`، مدة التنفيذ نحو 1467 ثانية.
- من الاختبارات الناجحة: `Tests\Unit\ExampleTest` و`Tests\Feature\SecureFileSystemTest`.
- الخطأ الذي ظهر في عدة اختبارات: `SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it` لاتصال MySQL. اختبار قاعدة السجل المدني أظهر تعذر الاتصال بها أيضاً. لا يمكن استنتاج أن كل إخفاقات الـ170 سببها الوحيد الاتصال؛ لم تُحلل كل رسالة فشل منفردة.
- `php artisan migrate:status` وفحص مخطط live لم ينجحا لرفض اتصال MySQL. لم تُنفذ migrations.

## 9) أسئلة مفتوحة وملاحق

### ما لم يمكن التحقق منه

1. مخطط MySQL الحقيقي، الفهارس الفعلية، migrations المسجلة، القيود والبيانات؛ الاتصال المحلي مرفوض.
2. تفاصيل إنتاج Apache/NGINX، PHP-FPM/mod_php، Supervisor/systemd الفعلي، وصلاحيات حسابات الخدمة.
3. صلاحية Google credentials وإعدادات Shared Drive/ACL على الخادم؛ ملف credentials المحلي غير موجود.
4. Rclone remote وحالة الاتصال بالسحابة؛ لا remote محلي فعال، ولم تُقرأ ملفات config.
5. نتائج الاستعلامات وأداء الجداول على حجم إنتاج حقيقي؛ لا قاعدة متاحة.
6. ما إذا كانت قيمة `file_path` في سجلات المرفقات التاريخية تبدأ بـ`storage/` دائماً.
7. أثر/سبب كل سجل ERROR غير مصنف؛ أُخفي النص الحر لتفادي تسريب بيانات شخصية، كما أن نافذة السجل تتضمن تشغيل الاختبارات.

### ملفات تمت مراجعتها فعلياً

`composer.json`, `composer.lock`, `routes/web.php`, `routes/api.php`, `config/queue.php`, `config/filesystems.php`, `config/services.php`, `config/database.php`, `laravel-worker.conf`, `public/.htaccess`, `public/php.ini`, Apache vhost المحلي، `ShowGeneralRegisrationController.php`, `GeneralRegistrationController.php`, `UnifiedFileManagementController.php`, `GoogleDriveTestController.php`, `CivilRegistrySearchController.php`, `ScoutSearchController.php`, `ChunkedUploadController.php`, `MobileRegistrationController.php`, `GoogleDriveService.php`, `RcloneGoogleDriveService.php`, `BulkExportSponsorshipForms.php`, `GenerateOrphanReportPdf.php`, `ProcessRcloneUploadJob.php`, `Attachment.php`, `DeadPepole.php`, `RePeople.php`, migrations الخاصة بالجداول المذكورة وفهارس البحث، و`storage/logs/laravel.log` (تجميع آلي دون إخراج الرسائل).

### الأوامر/الوسائل المستخدمة

- `php -v`; قراءة إصدارات الحزم من `composer.lock`.
- `php artisan route:list --path=... -vv` للمسارات الحرجة والوسائط.
- `php artisan tinker --execute=...` لقراءة config drivers دون قراءة قاعدة البيانات.
- `php artisan test`; `php artisan migrate:status` ومحاولات schema الحية.
- `rclone version`, `rclone listremotes`, `rclone about` باستخدام النسخة الموجودة بالمشروع.
- فحص `php.ini` المحمّل للـCLI وملفات `.htaccess`/vhost وملفات storage باستخدام PowerShell.
- `rg` لمراجعة الدوال والمسارات والمigrations؛ Python لتجميع أعداد السجلات والتخزين مع إسقاط نصوص الرسائل والقيم الشخصية.
- `git status --short` لحصر حالة العمل السابقة؛ لم تُسترجع أي تغييرات ولم يُعدّل أي ملف تطبيق.
