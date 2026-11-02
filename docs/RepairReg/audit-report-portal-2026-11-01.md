# تقرير فحص مشروع alhayahorphans
## البوابة العامة للتسجيل + المسارات + بيئة الإنتاج

- **التاريخ:** 2026-11-01
- **المرحلة:** فحص فقط. لم يُنفَّذ أي تعديل على ملفات الإنتاج بناءً على هذا التقرير.
- **المشروع:** Laravel (PHP 8.4) — `/var/www/html/alhayahorphans` — MySQL قاعدة `aso`
- **أرقام الأسطر** تقريبية (حسب النسخة المرفوعة وقت الفحص). تأكد منها قبل التعديل.

### رموز الثقة
- **[مؤكد]** رأيته في الكود أو في ناتج أمر على السيرفر.
- **[مرجّح]** استنتاج منطقي يحتاج ملفاً آخر أو اختباراً.
- **[غير متحقق]** يحتاج تشغيلاً فعلياً.

---

## 0) نطاق الفحص

| الملف / المصدر | مدى الفحص |
|---|---|
| `app/Http/Controllers/Users/GeneralRegistrationController.php` (2317 سطراً) | كامل |
| `routes/web.php` (571 سطراً) | كامل |
| `app/Http/Controllers/Users/ShowGeneralRegisrationController.php` | جزئي: الدخول التلقائي، `serveRcloneAttachment`، رفع المرفقات داخل `updateSponsorshipData` |
| `app/Jobs/BulkExportSponsorshipForms.php` | كامل (وعُدّل سابقاً، انظر القسم 5) |
| مخرجات أوامر السيرفر (MySQL، PHP-FPM، nginx، `.env` بدون أسرار، supervisor) | كما وردت |

**لم يُفحص بعد:** `GuardianFileService`، ملف الـ helpers (`generateUniqueReservedCode`)، `NormalizedSearchService`، `BankAccountValidationService`، الموديلات والـ migrations، blade النموذج العام وملف JS الخاص به، `RcloneGoogleDriveService`، الكنترولرات خلف المسارات العامة (القسم 4.1)، `bootstrap/app.php` أو `Kernel.php`، `routes/auth.php`، إعداد nginx للموقع.

---

## 1) الملخص التنفيذي (أخطر 10 نقاط)

| # | النقطة | الخطورة | الثقة | المرجع |
|---|---|---|---|---|
| 1 | مسارات `google-drive-test/*` عامة (تشمل حذف ملف ورفعاً وقائمة) | حرجة | مؤكد أنها بلا حماية؛ الأثر يتوقف على الكنترولر | SEC-01 |
| 2 | مسارات `public-api/duplicate-files/*` عامة وفيها `delete` و`bulk-delete` و`delete-all` وتحميل بالـ id | حرجة | نفس ما سبق | SEC-01 |
| 3 | مسارات بحث ولوك-أب البوابة العامة تكشف أسماء وهواتف وIBAN وبيانات السجل المدني بلا أي حماية | حرجة | مؤكد | SEC-02 |
| 4 | `api/civil-registry/*` عامة (بحث بالهوية والاسم + `index-data` + `clear-cache`) | حرجة | مؤكد أنها بلا middleware | SEC-03 |
| 5 | `serveRcloneAttachment`: أي مستخدم مسجّل يقرأ **أي** مرفق بتغيير الرقم (IDOR) | حرجة | مؤكد | SEC-04 |
| 6 | الدخول التلقائي بهوية + رقم ملف (6 خانات) في رابط GET وبلا `throttle` | عالية | مؤكد | SEC-05 |
| 7 | `uploadChunk` بلا تحقق، و`temp_path` من العميل، والامتداد من اسم الملف | عالية | مؤكد | SEC-06/07 |
| 8 | `APP_ENV=local` و`APP_DEBUG=true` و`LOG_LEVEL=debug` على الإنتاج | عالية | مؤكد | SEC-09 |
| 9 | فقدان مرفقات صامت (إعادة محاولة Deadlock، صور بلا `_cropped`، مرفقات الأم المتوفاة) والمستخدم يرى «نجاح» | عالية | مؤكد | LOG-01/02 |
| 10 | بلا `throttle` في أي مكان + `GET /` يحجز رقم ملف لكل زيارة (22,118 رقماً محجوزاً غير مستخدم) | عالية | مؤكد | PERF-01، SEC-15 |

---

## 2) بيئة الإنتاج (قياسات من السيرفر)

| البند | القيمة | التقييم |
|---|---|---|
| الموارد | 4 أنوية، 15GB RAM (12GB متاحة)، بلا swap، قرص 193GB (32%) | المعالج هو عنق الزجاجة |
| PHP-FPM | `pm=dynamic`، `max_children=50`، `start=10`، `spare 5-15`، `max_requests=500`، `request_terminate_timeout` غير مضبوط، متوسط ذاكرة العامل ~62MB | 50 عاملاً على 4 أنوية |
| php.ini (FPM) | `max_execution_time = 1G` (خطأ: الصيغة بالثواني)، `memory_limit = 4G`، `post_max_size = 1G`، `upload_max_filesize = 1G`، `max_file_uploads = 100` | قيم مبالغ فيها |
| nginx | `worker_processes auto`، `worker_connections 768`، `fastcgi_read_timeout 900`، `client_max_body_size 512M` و`1G`، لا `limit_req` ولا `limit_conn` | بلا حماية معدّل |
| MySQL | `max_connections=151` (الأقصى المستخدم 19)، `innodb_buffer_pool_size=128MB`، `innodb_lock_wait_timeout=50` | البفر صغير |
| حجم القاعدة | 1258MB، منها جدول `telescope_entries_tags` ~581MB بيانات (فيه صفوف يتيمة غالباً) | تنظيف لاحقاً |
| `.env` | `APP_ENV=local`، `APP_DEBUG=true`، `LOG_LEVEL=debug`، `LOG_CHANNEL=stack`، `SESSION_DRIVER=file`، `CACHE_DRIVER=file`، `QUEUE_CONNECTION=database` (مكرر 3 مرات)، `TELESCOPE_ENABLED=false` | غير مناسب للإنتاج |
| الطابور | `database`، `retry_after=7500` للاتصال `database` | يجب أن يتجاوز أطول Job |
| Supervisor | عاملان `laravel-worker_00/01` + `websocket-server`؛ `_01` سجّل `abnormal termination` مرة | انظر OPS-02 |
| الملكية | `artisan cache:clear` فشل حتى بالمستخدم `www-data`، و`rm` على `attachments-export` فشل بالمستخدم `gui` | ملفات بملكيات مختلطة |

---

## 3) حالة قاعدة البيانات (قياسات)

| الجدول | الفهارس الفريدة | ملاحظات |
|---|---|---|
| `data` | `data_file_id_number_unique`، `client_uuid`، (وفهرس عادي على `data_id_number`) | **لا** فريد على `data_id_number` |
| `users` | `users_email_unique` | |
| `re_people` | `client_uuid` فقط | **لا** فريد على `person_id` ولا `(registration_id, person_id)` |
| `attachments` | `uniq_person_type_hash (person_identity_number(64), file_type(16), file_hash)` | `file_hash` يقبل NULL |
| `reserved_codes` | `reserved_codes_code_unique` | |

| القياس | القيمة |
|---|---|
| صفوف `data` / `re_people` / `attachments` / `reserved_codes` | ~13,107 / 32,541 / ~90,866 / ~36,605 |
| أرقام ملفات محجوزة غير مستخدمة | **22,118** |
| هويات معيلين مكررة في `data` | **1** (الملفان `014367` و`019141`، `data_request_status=4` لكليهما، أُنشئا بفارق 10 دقائق في 2026-10-30) |
| تكرار فرد داخل الملف نفسه في `re_people` | 0 |
| فرد في أكثر من ملف في `re_people` | 0 |
| `re_people.person_id` فارغ | 1 صف `NULL` + 1 صف `''` |
| Deadlocks | 0 (في اللوغ وفي InnoDB) |

---

## 4) النتائج التفصيلية

### 4.1 أمنية (SEC)

**SEC-01 — مسارات عامة خطيرة بلا `auth` [الحالة: مؤكد أنها بلا middleware؛ الأثر الفعلي يحتاج فحص الكنترولرات]**
- `google-drive-test/*` (قائمة، بحث، معلومات ملف، رفع، إنشاء مجلد، **`DELETE /delete`**) → `GoogleDriveTestController`.
- `public-api/duplicate-files/*` (`web.php` ~116-126): `paginated`، `preview/{id}`، `serve/{id}`، `download/{id}`، `view/{id}`، **`delete/{id}`**، **`bulk-delete`**، **`delete-all`** → `UnifiedFileManagementController`.
- `api/files/*` معرّفة صراحة بـ `withoutMiddleware(['auth','verified'])`: `process-folder-upload`، `generate-record-number`، `process-folder-duplicates`، `clean-expired-duplicates`...
- `test-download-all`، `test-download-selected`، `test-real-stats-public` (التعليق في الكود: «بدون مصادقة (مؤقت)»).
- `POST admin/general-category/toggle-status` (~205) خارج مجموعة `auth` لأنها تُغلق في السطر ~193.
- 14 مسار صفحات اختبار (`/test-documents-api`، `/debug-documents-api`، `/direct-test-save`، `/comprehensive-test`...) و`/file-manager` و`/test-snappy-pdf`.
- حماية CSRF لا تمنع الاستغلال لأن الصفحة الرئيسية `/` عامة وتعطي أي زائر جلسة ورمز CSRF.

**SEC-02 — مسارات `GeneralRegistrationController` العامة تكشف بيانات شخصية [مؤكد]**
`check-id-number`، `search-all-tables`، `fill-from-civil-registry`، `check-existing-guardian`، `get-guardian-with-bank-accounts`، `lookup-guardian`، `lookup-mother`، `check-person-linked`. لا توجد مصادقة ولا `throttle` ولا CAPTCHA، والكنترولر لا يتحقق من هوية الطالب. تُعيد أسماء وهواتف وحسابات IBAN ومعلومات السجل المدني. تعليقات الكود نفسها تقول «لا نكشف وجود بيانات قديمة» لكن الاستجابة تحمل البيانات (انظر ~1801، 1878، 1936).

**SEC-03 — `api/civil-registry/*` و`api/scout/*` عامة [مؤكد أنها بلا middleware]**
تشمل `quick-search`، `search-by-id`، `search-by-name`، `comprehensive-search`، `stats`، و**`POST index-data`** و**`POST clear-cache`** و`test-connection`.

**SEC-04 — IDOR في `serveRcloneAttachment` (`ShowGeneralRegisrationController` ~4189) [مؤكد]**
`Attachment::find($attachmentId)` دون التحقق أن المرفق يخص الملف النشط للمستخدم (`active_internal_file_number`). أي معيل مسجّل يقرأ أي مرفق من ~90 ألفاً بتغيير الرقم. الاستجابة بـ `Cache-Control: public`. للمسار المحلي يعيد التوجيه إلى `asset('storage/...')` العام.

**SEC-05 — الدخول التلقائي `/auto-login` و`/s/{credentials}` [مؤكد]**
- (هوية + رقم ملف) في رابط GET: يظهر في سجل nginx وتاريخ المتصفح والـ Referer.
- رقم الملف من 6 خانات، والمستخدم فعلياً أقل من ~20 ألفاً، مع غياب `throttle` → تخمين ممكن لهوية معروفة (والهويات تتوفر عبر SEC-02/03).
- المستخدم يُنشأ بكلمة مرور `bcrypt(رقم الملف)` و`email_verified_at = now()`.
- يُسجَّل رقم الهوية في اللوغ عند النجاح والفشل.
- مسار `/login/user` ظاهرياً بلا `throttle` في الـ routes (قد يكون داخل الكنترولر، لم يُفحص).

**SEC-06 — `uploadChunk` (`GeneralRegistrationController` 1207-1247) [مؤكد]**
- `$fileId` من العميل يدخل مسار المجلد (path traversal محتمل).
- لا قيد على الامتداد (يسمح بـ `.php`) ولا على الحجم أو عدد الأجزاء.
- مجلد بصلاحيات `0777`، والمؤقتات لا تُنظَّف.
- `FILE_APPEND` على اسم ثابت: إعادة إرسال الجزء الأول أو رفع الاسم نفسه مرتين يُفسد الملف.

**SEC-07 — `store`: امتداد ومسار من العميل [مؤكد]**
- الامتداد من `getClientOriginalExtension()` (981، 985، 1035) دون قائمة سماح.
- قاعدة التحقق في السطر 278 على `document_file.*` بينما المرفقات الفعلية `attachments.N.file` بلا `mimes` أو `max`.
- `attachments.*.temp_path` من العميل (996، 1046-1047) دون التحقق أنه يبدأ بـ `temp_uploads/{رقم الملف}/` → يمكن نقل أي ملف من `public` disk إلى سجل المرسل.
- خطر تنفيذ PHP يعتمد على وجود symlink `public/storage` وإعداد `location ~ \.php$` في nginx [غير متحقق].

**SEC-08 — مسار `/storage/{path}` (`web.php` ~535-571) [مؤكد]**
حماية traversal جيدة (`realpath` + فحص البداية + منع `.env`/`.git`/`logs/`)، لكنه **عام** ويخدم كل `storage/app/public` بلا مصادقة مع `Cache-Control: public, max-age=31536000`. أسماء الملفات تتضمن رقم الهوية.

**SEC-09 — إعدادات البيئة [مؤكد]**
`APP_ENV=local`، `APP_DEBUG=true`، `LOG_LEVEL=debug`. ردود 500 في عدة دوال تعيد `$e->getMessage()` (مثلاً السطر ~1197) فتسرّب نصوص SQL. (لا يوجد كود في `app/config/routes/bootstrap` يعتمد على `environment('local')`، فتغيير البيئة آمن من هذه الجهة.)

**SEC-10 — مسارات إدارية تحت `auth,verified` فقط [مرجّح]**
`Route::resource('users')`، `Route::resource('roles')`، `admin/file/*` (ومنها `php-diagnostic` و`diagnostic/folders`)، `admin/civil-registry` (CRUD)، `admin/reserved-codes/*`. مستخدمو البوابة (`role=user`، `email_verified_at` مضبوط تلقائياً) يجتازون هذه الـ middleware. يحتاج فحص الكنترولرات (`can:` أو `authorize` أو constructor middleware).

**SEC-11 — الكتابة فوق سجل معيل موجود من نموذج عام [مؤكد، ويبدو مقصوداً]**
`store` (309-321، 343-360، 520-525): إذا وُجدت الهوية أو رقم الملف يُحدَّث السجل بما أرسله المستخدم (حتى `data_id_number` في حالة رقم الملف لشخص آخر).

**SEC-12 — بيانات شخصية في اللوغ [مؤكد]**
IBAN وهواتف (548، 556)، كل `family_members` (882-891)، كائنات `UploadedFile` (921، 1094، 1096)، وأرقام هويات في لوغ الدخول التلقائي. مع `LOG_LEVEL=debug` يتضخم الملف (اللوغ تجاوز 400 ألف سطر).

**SEC-13 — كلمة مرور المستخدم الجديد في `store` (1103-1112)**
تُولَّد بـ `str_shuffle` (غير آمن تشفيرياً) ولا تُحفظ ولا تُرسل. [مؤكد]، خطورة منخفضة.

**SEC-14 — صفحات واختبارات في الإنتاج** (انظر SEC-01) تزيد سطح الهجوم.

**SEC-15 — لا `throttle` في أي مكان في `web.php`** [مؤكد]. لا حماية معدّل على الصفحة الرئيسية، الحفظ، الرفع، البحث، الدخول.

### 4.2 منطقية (LOG)

**LOG-01 — فقدان مرفقات عند إعادة محاولة Deadlock (`store` 1047، 1190) [مؤكد]**
`Storage::move` لملف `temp_path` يحدث داخل المعاملة؛ عند rollback ثم إعادة المحاولة يكون الملف المؤقت قد اختفى فيُسجَّل «الملف المؤقت غير موجود» ويُتجاوز المرفق والاستمارة تُحفظ بدونه. وملفات `storeAs` من المحاولة الأولى تبقى يتيمة على القرص.

**LOG-02 — حذف صامت للمرفقات والمستخدم يرى «نجاح» [مؤكد]**
- الصور التي لا يحتوي اسمها `_cropped` تُتجاهل (944).
- مرفقات الأم تُربط بـ `mother_id` فقط، فإذا كانت الأم متوفاة (`deceased_mother_id`) تُحذف (957-958).
- فرد أسرة بلا `person_id` تُحذف مرفقاته (975).
- لا يوجد تحقق خادمي من اكتمال المرفقات المطلوبة في `store` (يوجد `validateRequiredAttachments` في `ShowGeneralRegisrationController` فقط).

**LOG-03 — الحسابات البنكية (555-626) [مؤكد]**
- `$isFirstAccount` محسوب قبل الحلقة → كل الحسابات المرسلة لملف جديد تأخذ `check_account = 1`.
- تحديث حساب موجود يكتب `check_account = 0` فيمسح علامة الحساب الأساسي.
- فحص التكرار يقارن `person_owner_identity_number` بهوية المعيل لكن المخزَّن `null` عند غياب الهوية → لا يُكتشف التكرار.
- `$duplicateBankErrors` و`$bankValidationService` تُنشأ ولا تُستخدم → لا تحقق من IBAN.

**LOG-04 — التحديث بقيم فارغة يمسح بيانات (523، 680) [مؤكد]**
`update($guardianData)` و`update($deceasedData)` تكتبان `null` لأي حقل غاب من الطلب.

**LOG-05 — `updateOrCreate` بدون `file_id_number` (2247، 2296) [مرجّح]**
مفتاح `identity_number + field_key` فقط، فتنتقل القيم بين ملفين عند تكرار الهوية. وجلب `sponsorship_id` بـ `first()` بلا ترتيب قد يأخذ كفالة خاطئة.

**LOG-06 — مقارنة أرقام الملفات غير متسقة [مرجّح]**
`!= $fileIdNumber` في PHP (يعامل `"000123"` و`"123"` كمتساويين) مقابل `where('registration_id','!=',...)` نصي في SQL داخل `findOtherFileForIdentity` (464-481).

**LOG-07 — معيل مكرر في `data` وصفّان في `re_people`**
حالة `014367` / `019141` بفارق 10 دقائق ليست سباقاً متزامناً. يحتاج معرفة معنى `data_request_status = 4` ومصدر الإدخال. و`re_people` فيه `person_id` NULL واحد وفارغ واحد (يمنع الفهرس الفريد دون تحويل `''` إلى `NULL`).

**LOG-08 — أخرى**
كود ميت (965-971)، رسالة بها خطأ إملائي (1663: «لم يلا يوجد»)، دالة `store` بطول ~1000 سطر.

### 4.3 الأداء والتزامن (PERF)

**PERF-01 — `index()` و`GET /`**: 14 استعلام `::all()` في كل زيارة بلا كاش، ويحجز رقم ملف جديد لكل زيارة (22,118 رقماً بلا استخدام). تحت الضغط ينافس الجدول ويعيد `TEMP-...` الذي يُرفض عند الحفظ (155، 231). [مؤكد، `generateUniqueReservedCode` لم يُفحص]

**PERF-02 — معاملة طويلة**: داخل المعاملة حفظ ملفات وحساب SHA-1 ونقل ملفات وسجلات كبيرة، فتطول الأقفال.

**PERF-03 — سباقات "افحص ثم أدخل"** (429، 464-481، 1105): الفهارس الفريدة على `data.file_id_number` و`users.email` تمنع التكرار لكن تُخرج `Duplicate entry` خاماً (500). `re_people` و`data.data_id_number` بلا قيد.

**PERF-04 — بحث ثقيل عام**: `LIKE '%..%'` (1607-1610) و`searchAllTables` بلا حد معدل.

**PERF-05 — سعة السيرفر**: 50 عاملاً على 4 أنوية، وlog debug، و`APP_DEBUG`، و`buffer_pool` صغير، وجلسات وكاش `file`، وقيم رفع ضخمة، وclosures كثيرة تمنع `route:cache`. تُقدَّر تجربة 500 مستخدم متزامن بـ: بطء شديد (طابور 450 طلباً)، تكرار أفراد (`re_people`)، أخطاء `Duplicate entry` خام، وفقدان مرفقات عند إعادة المحاولة.

**PERF-06 — `generateNewFileNumber()` في `ShowGeneralRegisrationController`**: `MAX(CAST(...))` مع `REGEXP` على 3 جداول في كل استدعاء.

### 4.4 تشغيلية (OPS)

**OPS-01 — Telescope**: الحزمة مثبتة ومعطّلة (`TELESCOPE_ENABLED=false`)، `telescope_entries` فيه 108 صفاً فقط بينما `telescope_entries_tags` ~581MB بيانات (يتيمة غالباً). يزيد حجم النسخ الاحتياطي فقط. تنظيفه بـ `TRUNCATE` على جدول الوسوم بعد التأكد.

**OPS-02 — ملكيات الملفات**: `cache:clear` فشل حتى بـ `www-data`، و`rm` فشل بـ `gui`؛ تشغيل `artisan` بمستخدم `gui` ينشئ ملفات بملكية مختلفة، و`laravel-worker_01` أعطى `abnormal termination`.

**OPS-03 — الـ Job `BulkExportSponsorshipForms`**: ظهرت الحالة «فشل: attempted too many times» رغم `5621/5621 ناجح`، واللوغ يُظهر اكتمال التصدير (06:42:40) والفشل المسجَّل 08:59:17. `retry_after=7500` أكبر من `timeout=7200`، فالسبب غير محسوم [مرجّح: مدة التشغيل الفعلية أو إعادة التقاط من عامل آخر، بحاجة لتوقيت البدء]. `failed()` يكتب «فشل» فوق تصدير مكتمل.

**OPS-04 — `.env`**: `QUEUE_CONNECTION` مكرر 3 مرات.

---

## 5) تعديلات سُلِّمت سابقاً (للعلم، لا تُكرَّر)

1. **`BulkExportSponsorshipForms.php`**: فحص Rclone مبكراً، فشل المرفقات لا يوقف الاستمارات، حذف المجلد المؤقت بعد نجاح الرفع وتنظيف المجلدات القديمة (>3 أيام)، رفع مجلد كل شخص مباشرة تحت مجلد الجهة الداعمة (بدون `المرفقات` وبدون uuid)، إضافة مجلد `تقرير التصدير` لملف الإكسل، اسم مجلد الفرد = الاسم فقط (مع `(2)` عند التكرار)، فاصلة `، ` بين أنواع المرفقات في الإكسل، رسالة «جارٍ رفع المرفقات»، ومدة القفل `timeout + 300`.
   - **ملاحظة:** مرر `''` كمعامل ثالث لـ `uploadFolder` دون رؤية `RcloneGoogleDriveService` (غير متحقق من سلوكه).
2. **شريط تقدم الحفظ**: كلاس `App\Support\PortalSaveProgress`، وتعديل `updateSponsorshipData` (إرجاع JSON عند الطلب الـ XHR، وتقارير التقدم)، ودالة `saveProgress`، وملف `public/js/portal-save-progress.js`، ومسار `save-progress/{token}` (موجود في `web.php` ~144-150).

---

## 6) أسئلة مفتوحة

1. هل الواجهة (blade/JS) تستخدم فعلاً المسارات العامة المذكورة في SEC-01/03؟ (grep قبل أي حجب).
2. ما معنى `data_request_status = 4`؟ ومن أين جاء السجلان المكرران؟
3. هل مستخدمو البوابة (`role=user`) يصلون فعلاً إلى `users`/`roles`/`admin/*`؟ (يحسمه `route:list -v` وفحص الكنترولرات).
4. هل `public/storage` رابط رمزي، وهل nginx ينفّذ `.php` داخله؟
5. مدة تشغيل تصدير 5621 كفالة الفعلية (بدء/انتهاء) مقابل `retry_after`.
6. هل تُستخدم رسائل SMS أو إشعارات أخرى مرتبطة بالاستمارة العامة؟ (لم تظهر في الملفات المفحوصة).

---

## 7) أوامر التحقق (قراءة فقط)

```bash
cd /var/www/html/alhayahorphans

# الحماية الفعلية للمسارات (تشمل middleware الكنترولر)
php artisan route:list --path=google-drive-test -v
php artisan route:list --path=public-api -v
php artisan route:list --path=users -v
php artisan route:list --path=admin -v

# هل استُغلت المسارات العامة؟ (أعداد بدون هويات)
sudo awk '{print $6, $7, $9}' /var/log/nginx/access.log \
 | sed -E 's#^("?[A-Z]+) /s/[^ ]*#\1 /s/<redacted>#; s#\?[^ ]*##' \
 | grep -E "google-drive-test|public-api|/api/(files|civil-registry|scout|speedtest)|/test-|/auto-login|/s/" \
 | sort | uniq -c | sort -rn | head -25

# storage والتنفيذ داخله
ls -la public/storage
sudo nginx -T 2>/dev/null | grep -n -B2 -A8 'location ~ \\.php'

# الفهارس والتكرار
sudo mysql aso -e "SHOW INDEX FROM re_people; SHOW INDEX FROM data;"
sudo mysql aso -e "SELECT id, registration_id FROM re_people WHERE person_id IS NULL OR person_id='';"
```
