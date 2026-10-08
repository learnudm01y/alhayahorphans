# الثغرات الأمنية

> نظام الحياة لرعاية الأيتام · فحص أمني · التقرير ١ من ٢

النظام يحفظ بيانات أطفال أيتام وأوصيائهم: أرقام هوية، وثائق رسمية، حسابات بنكية. أخطر ما وُجد أن جزءاً كبيراً من هذه البيانات متاح دون تسجيل دخول، وأن نسخاً حقيقية منها محفوظة داخل مستودع git نفسه. البنود المعلّمة «تحقّقتُ يدوياً» أعدتُ فحصها سطراً بسطر.

- تاريخ الفحص: ٧ أكتوبر ٢٠٢٦
- النسخة: `main @ d2a7d14` (مطابقة لـ GitHub)
- نطاق: Laravel 10 + تطبيق Capacitor/Android + المكتبات

## الملخص

| الخطورة | العدد |
|---|---|
| 🔴 حرج | 12 |
| 🟠 عالٍ | 11 |
| 🟡 متوسط | 12 |
| ⚪ منخفض | 5 |
| **المجموع** | **40** |

## ما يجب فعله هذا الأسبوع

1. **أوقف المسارات المفتوحة الأخطر فوراً**: حمّل `offline-test-development.php` في البيئة المحلية فقط (S-01)، واحذف `google-drive-test` و`/test-download-all` و`/api/simple/*` و`/api/chunked-upload-test` و`/api/gallery/*` و`/api/search/*` (S-04، S-05، S-12).
2. **أغلق الرفع المجزأ العام** في نموذج التسجيل وتحقّق من مرفقاته (S-08، S-09)، فهما يسمحان بكتابة ملفات على الخادم دون تسجيل دخول.
3. **ضع واجهات السجل المدني والبحث عن الأوصياء خلف المصادقة** (S-02، S-03)، وقيّد CORS.
4. **عطّل التسجيل العام وأصلح حذف المستخدمين** (S-16، S-17)، وأضف فحص الدور لدخول الجوال.
5. **أخرج البيانات الشخصية من git وطهّر التاريخ** (S-27)، وتعامل مع الأمر كحادثة حماية بيانات محتملة.
6. **انقل الوثائق إلى قرص خاص** وقدّمها بمصادقة (S-10).

## الفهرس

- مسارات مكشوفة دون تسجيل دخول (7)
- رفع الملفات والتخزين (8)
- الصلاحيات والحسابات (11)
- تسريب البيانات الشخصية والأسرار (5)
- XSS والحقن (3)
- تطبيق الجوال (3)
- الإعدادات والمكتبات (3)

---

## مسارات مكشوفة دون تسجيل دخول

> `routes/web.php` يُحمَّل بمجموعة `web` فقط (جلسة + CSRF)، ورمز CSRF يحصل عليه أي زائر بفتح الصفحة الرئيسية، فلا يحمي من المهاجم. و`routes/api.php` عليه تحديد معدل فقط. فحص `route:list` أظهر أن 129 من أصل 580 مساراً بلا أي مصادقة.

### S-01 — واجهة «الاختبار دون اتصال» تعمل في الإنتاج بلا مصادقة، وتسجيل الدخول فيها لا يتحقق من كلمة المرور

- **الخطورة:** 🔴 حرج · ✓ تم التحقق يدوياً
- **الملفات:** `app/Providers/RouteServiceProvider.php:46-50`، `routes/offline-test-development.php:17-53`، `app/Http/Controllers/Api/OfflineTestController.php:62-112`، `app/Http/Controllers/Api/OfflineTestController.php:871-903`
- **الثغرة:** الملف يُحمَّل بمجموعة `api` فقط (التعليق في الكود: «بدون middleware لتسهيل الاختبار»). دالة `login` تجد المستخدم بالاسم أو البريد، وإن لم تجده تُرجع أول مدير، ولا تفحص كلمة المرور. ومسارات `updateSponsorship` و`uploadChanges` تكتب في `data` و`re_people` و`dead_people` و`guardian_bank_accounts`.
- **سيناريو الاستغلال:** أي شخص على الإنترنت يسرد كل الكفالات مع بيانات الأوصياء وحساباتهم البنكية، ويعدّل حالة أي كفالة، ويستبدل IBAN الوصي بحساب آخر، ويعرف اسم وبريد أول مدير.
- **الإصلاح المقترح:** احذف تحميل الملف من `RouteServiceProvider` (أو حمّله فقط عند `app()->environment('local')`)، واحذف الملف والمتحكم.

### S-02 — واجهات السجل المدني والبحث تكشف بيانات السكان والحسابات البنكية لأي شخص

- **الخطورة:** 🔴 حرج · ✓ تم التحقق يدوياً
- **الملفات:** `routes/web.php:78-98`، `routes/api.php:39-379`، `routes/api.php:382`، `routes/api.php:550-641`، `config/cors.php:22`
- **الثغرة:** `api/civil-registry/*` و`api/scout/*` و`/api/search/*` و`/api/person-name/{id}` و`/api/civil-registry/person-details/{personId}` بلا مصادقة. الأخيرة تُرجع الشخص وعنوانه وتاريخ وفاته و`iban_usd/iban_shekel` بمعرّف تسلسلي. وCORS يسمح بأي مصدر `*`.
- **سيناريو الاستغلال:** سرد المعرّفات 1..N يسحب السجل المدني والحسابات البنكية بالكامل، ويمكن لأي موقع خارجي قراءة الردود من متصفح الزائر. ومسارا `index-data` و`clear-cache` يطلقان عمليات ثقيلة.
- **الإصلاح المقترح:** ضع كل هذه المسارات تحت `auth` + صلاحية محددة + تحديد معدل، واحذف دوال البحث بـ PDO الخام، وقيّد CORS بنطاق التطبيق.

### S-03 — واجهات البحث عن الوصي في نموذج التسجيل العام تُرجع IBAN وأرقام الهواتف، ويمكن إضافة حساب بنكي دون تسجيل

- **الخطورة:** 🔴 حرج
- **الملفات:** `routes/web.php:195-201`، `app/Http/Controllers/Users/GeneralRegistrationController.php`، `routes/api.php:644-720`
- **الثغرة:** `getGuardianWithBankAccounts` و`checkExistingGuardian` و`fillFromCivilRegistry` و`searchAllTables` متاحة للعموم. و`POST /api/civil-registry/save-bank-account` يُدرج حساباً بنكياً بلا مصادقة.
- **سيناريو الاستغلال:** أرقام الملفات 6 خانات تسلسلية، فسردها يكشف هواتف وIBAN كل الأوصياء. ويمكن إلحاق IBAN مزيّف بملف وصيّ (يُنشأ غير معتمد، ويحتاج تأكيد مسار الاعتماد).
- **الإصلاح المقترح:** اجعل النموذج العام يتلقى «موجود/غير موجود» فقط دون أي بيانات مالية، وضع مسار الحفظ تحت المصادقة.

### S-04 — مسارات Google Drive التجريبية تسمح بالسرد والبحث والحذف بحساب الخدمة الحقيقي

- **الخطورة:** 🔴 حرج
- **الملفات:** `routes/web.php:428-437`، `app/Http/Controllers/Admin/GoogleDriveTestController.php:70-165`
- **الثغرة:** مجموعة `google-drive-test` (upload, delete, list, search, file-info, create-folder) بلا أي middleware.
- **سيناريو الاستغلال:** سرد ملفات الوثائق المخزنة في Drive وحذفها نهائياً.
- **الإصلاح المقترح:** احذف المسارات، أو انقلها إلى `admin.php` مع صلاحية أدوات النظام.

### S-05 — تنزيل كل الملفات المكررة (نسخ من وثائق الأيتام) في ملف ZIP بطلب GET واحد

- **الخطورة:** 🔴 حرج · ✓ تم التحقق يدوياً
- **الملفات:** `routes/web.php:71-75`، `routes/api.php:228-233`، `app/Http/Controllers/DuplicateFileController.php:22`
- **الثغرة:** `/test-download-all` و`/test-download-selected` و`/test-real-stats-public` و`/api/duplicate-files/{summary,download,delete,process}` بلا مصادقة، والـ middleware في `DuplicateFileController` معطّل بتعليق. ملاحظة: مسارات `public-api/duplicate-files/*` في web.php تُلغيها نسخة محمية في `admin.php` فهي محمية فعلياً.
- **سيناريو الاستغلال:** طلب واحد يُرجع ZIP بكل الوثائق المؤقتة، وطلب DELETE يمسحها، وبناء ZIP مع كل طلب يُستخدم لإغراق الخادم.
- **الإصلاح المقترح:** احذف مسارات الاختبار وضع الباقي تحت `auth` + `rolebreeze:admin`.

### S-06 — مسارات «إدارية» في web.php محمية بـ auth فقط، وأي حساب مسجَّل ذاتياً يمرّ

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `routes/web.php:205-312`، `routes/web.php:397-413`، `routes/web.php:440-484`
- **الثغرة:** `admin/reserved-codes/*` (تعديل الأرقام المحجوزة)، `admin/api/search-civil-registry`، `admin/file/excel-gateway`، `php-diagnostic`، `diagnostic/folders` (تكشف مسارات الخادم).
- **سيناريو الاستغلال:** مستخدم سجّل نفسه للتو (S-12) أو مستخدم بوابة الأيتام يصل لأدوات الإدارة والبحث في السجل المدني.
- **الإصلاح المقترح:** انقلها إلى `admin.php` وأضف `permission:`.

### S-07 — صفحات وسكربتات اختبار وتشخيص منشورة

- **الخطورة:** 🟡 متوسط
- **الملفات:** `public/test-export.php`، `public/php-diagnostic.php`، `routes/web.php:25-68`، `routes/web.php:338-350`، `routes/web.php:416-425`، `routes/web.php:489-515`
- **الثغرة:** `test-export.php` يشغّل Laravel ويستعلم القاعدة مع `display_errors=1` ويطبع stack trace. `/test-snappy-pdf` مع `enable-local-file-access`. مسارات speedtest تشغّل أداة قياس سرعة كثيفة دون مصادقة (`SPEEDTEST_REQUIRE_AUTH=false`). و12 صفحة اختبار، و`/api/files/analytics*`.
- **سيناريو الاستغلال:** كشف بنية الخادم وإعداداته، واستنزاف عرض النطاق بتشغيل قياس السرعة بشكل متكرر.
- **الإصلاح المقترح:** احذفها من الإنتاج كلياً.


---

## رفع الملفات والتخزين

### S-08 — رفع مجزأ عام يكتب أي ملف في أي مكان على الخادم (تنفيذ كود محتمل)

- **الخطورة:** 🔴 حرج · ✓ تم التحقق يدوياً
- **الملفات:** `routes/web.php:142`، `app/Http/Controllers/Users/GeneralRegistrationController.php:662-702`
- **الثغرة:** `file_id_number` يُلصق بالمسار دون أي تحقق، فيمكن تمرير `../`. فلتر اسم الملف يسمح بـ `.php` وبالأسماء التي تبدأ بنقطة. الكتابة بالإلحاق (`FILE_APPEND`) دون حد للحجم أو عدد الأجزاء، والمجلد بصلاحيات `0777` داخل `storage/app/public` المنشور على الويب.
- **سيناريو الاستغلال:** زائر مجهول يضيف محتوى لملفات التطبيق الموجودة، أو يُنشئ سكربتاً تحت جذر الويب، أو يملأ القرص. حتى دون `../` تصبح الملفات متاحة عبر `/storage/temp_uploads/...`.
- **الإصلاح المقترح:** اشترط مصادقة أو رمز رفع موقّعاً من الخادم، وتحقق من `file_id_number` بـ `^\d{6}$`، وولّد الاسم في الخادم مع قائمة امتدادات وMIME مسموحة، وخزّن الأجزاء خارج `storage/app/public`، وحدّد الحجم والعدد.

### S-09 — مرفقات التسجيل العام غير مُتحقق منها وتُحفظ بامتداد يختاره المهاجم في مجلد عام

- **الخطورة:** 🔴 حرج
- **الملفات:** `app/Http/Controllers/Users/GeneralRegistrationController.php:127`، `app/Http/Controllers/Users/GeneralRegistrationController.php:498-615`
- **الثغرة:** التحقق يشمل `document_file.*` فقط، بينما تُعالَج `attachments.*.file` و`attachments.*.temp_path` بامتداد العميل (`getClientOriginalExtension`) و`file_type` من العميل، وتُحفظ في `uploads/{id}` على القرص العام.
- **سيناريو الاستغلال:** رفع HTML/SVG نشط (أو سكربت) تحت نطاق التطبيق، و`file_type` يحوي `../` يكتب فوق وثائق عائلة أخرى، وزرع وثائق لأي رقم ملف.
- **الإصلاح المقترح:** `mimes:jpg,jpeg,png,pdf|max:5120`، والامتداد من MIME المكتشف في الخادم، وقائمة بيضاء لـ `file_type`، وقبول `temp_path` فقط إن أصدره الخادم لنفس الجلسة.

### S-10 — كل وثائق الأيتام قابلة للتنزيل علناً بروابط يمكن تخمينها

- **الخطورة:** 🔴 حرج
- **الملفات:** `public/storage → storage/app/public`، `routes/web.php:518-555`، `config/filesystems.php`، `app/Http/Controllers/Admin/RecordsManagementController.php:366-371`
- **الثغرة:** الوثائق تُخزَّن على القرص `public` بنمط `uploads/{رقم الملف}/{بادئة النوع}_{رقم الملف}_{رقم الهوية}.{امتداد}`. مسار `/storage/{path}` يمنع الخروج من المجلد بشكل صحيح لكنه لا يتحقق أبداً من هوية الطالب. وفحص `Referer` في `showSecureFile` لا قيمة له ما دام التنزيل المباشر ممكناً.
- **سيناريو الاستغلال:** أرقام الملفات تسلسلية وبادئات الأنواع معروفة، فيمكن سرد بطاقات الهوية وشهادات الوفاة وصور القاصرين.
- **الإصلاح المقترح:** انقل الوثائق إلى القرص الخاص `local`، واحذف مسار `/storage/{path}` ورابط `public/storage` لها، وقدّمها عبر متحكم بمصادقة وتفويض أو روابط موقّعة قصيرة العمر.

### S-11 — رفع مجلدات واستيراد CSV مباشرة في الجداول الأساسية دون تسجيل

- **الخطورة:** 🔴 حرج
- **الملفات:** `routes/web.php:364-393`، `routes/api.php:205-225`، `app/Http/Controllers/UnifiedFileManagementController.php:686-1083`، `app/Http/Middleware/VerifyCsrfToken.php`
- **الثغرة:** مجموعة `api/files/*` بلا مصادقة ومستثناة من CSRF. `processExcelWithMapping` ينفّذ `array_combine($headers, $row)` ثم `DB::table($targetTable)->insert($rowData)` والجدول من قائمة تشمل `guardian_bank_accounts`، والمهاجم يتحكم بأسماء الأعمدة وقيمها.
- **سيناريو الاستغلال:** إدراج صفوف عشوائية بما فيها حسابات بنكية (خطر تحويل الصرف لحسابات احتيالية)، وتخزين ملفات في مجلد أي يتيم، والاستجابة تكشف ربط الهويات بأرقام الملفات.
- **الإصلاح المقترح:** مصادقة + دور مدير، وإزالة استثناء CSRF، وقائمة بيضاء للأعمدة، وعدم إرجاع الربط.

### S-12 — مسارات رفع عامة أخرى: الرفع البسيط، الرفع المجزأ التجريبي، ومعرض الصور مع الحذف

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `routes/api.php:36`، `routes/api.php:199-202`، `routes/api.php:430-560`، `app/Http/Controllers/SimpleFileUploadController.php:36-37`
- **الثغرة:** `/api/simple/upload` يحفظ الاسم الأصلي في مجلد عام. `/api/chunked-upload-test` يُنشئ مرفقاً على أي `X-Sponsorship-Id` منسوباً للمستخدم 1. ومعرض `/api/gallery/*` ينفذ `unlink(public_path('uploads/'.$folder.'/'.$file))` بلا مصادقة ولا تنقية.
- **سيناريو الاستغلال:** إلحاق وثيقة مزيفة بأي كفالة، وحذف ملفات من `public/uploads`، وملء القرص. (مدى تجاوز المجلد عبر الترميز يحتاج تأكيداً من إعداد الخادم.)
- **الإصلاح المقترح:** احذف هذه المسارات أو احمها، و`basename()` مع فحص realpath.

### S-13 — حدود الرفع والذاكرة مرفوعة إلى أقصى حد مع إلغاء `open_basedir`

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `public/.htaccess`، `public/php.ini`، `app/Http/Middleware/LargeFileUploadMiddleware.php`
- **الثغرة:** رفع 1-2GB، ذاكرة 2-4GB، تنفيذ حتى 7200 ثانية، و`open_basedir none`.
- **سيناريو الاستغلال:** مع مسارات الرفع العامة أعلاه، عدد قليل من العملاء يستنزف القرص والذاكرة وعمّال PHP.
- **الإصلاح المقترح:** حدود لكل مسار (أجزاء صغيرة للرفع المجزأ)، وأعد `open_basedir`.

### S-14 — تعليمة shell مبنية بدمج نصوص (ثغرة حقن أوامر كامنة)

- **الخطورة:** 🟡 متوسط
- **الملفات:** `app/Http/Controllers/Api/SponsorshipSyncController.php:1377-1388`
- **الثغرة:** `shell_exec("rclone moveto \"{$oldPath}\" \"{$newPath}\"")` بأسماء الكافل والمجلد. حالياً لا يستدعيها أحد.
- **سيناريو الاستغلال:** أي إعادة استخدام لها تفتح حقن أوامر عبر اسم كافل.
- **الإصلاح المقترح:** `Process::run([...])` بمصفوفة معاملات، أو احذف الدالة.

### S-15 — طبقات حماية وهمية: ملفات middleware فارغة أو غير مسجّلة

- **الخطورة:** 🟡 متوسط
- **الملفات:** `app/Http/Middleware/SecureFileAccess.php`، `app/Http/Middleware/SecurityHeadersMiddleware.php`، `app/Http/Middleware/BlockSuspiciousStoragePaths.php`، `app/Http/Middleware/DatabasePermissionCheck.php`، `app/Http/Controllers/SecureFileController.php`
- **الثغرة:** `SecureFileAccess` و`SecurityHeadersMiddleware` و`SecureFileController` ملفات فارغة (0 بايت)، و`BlockSuspiciousStoragePaths` غير مسجّل ويُرجع 200 بدل 403، و`DatabasePermissionCheck` يسجّل فقط.
- **سيناريو الاستغلال:** أسماء توحي بحماية غير موجودة فعلاً.
- **الإصلاح المقترح:** نفّذها وسجّلها أو احذفها.


---

## الصلاحيات والحسابات

### S-16 — أي شخص يسجّل حساباً، وأي حساب يحصل على رمز يفتح واجهة مزامنة الجوال كاملة

- **الخطورة:** 🔴 حرج
- **الملفات:** `routes/auth.php:15-18`، `app/Http/Controllers/Auth/RegisteredUserController.php:31-48`، `app/Http/Controllers/Api/SponsorshipSyncController.php:34-83`، `routes/api.php:799-933`
- **الثغرة:** التسجيل العام مفعّل ويسجّل الدخول فوراً، و`User` لا يطبّق `MustVerifyEmail` فشرط `verified` يمر للجميع. `/api/mobile/login` يُصدر رمز Sanctum بصلاحيات `['*']` لأي مستخدم، ولا يوجد أي فحص دور أو صلاحية في متحكمات المزامنة والرفع.
- **سيناريو الاستغلال:** تسجيل حساب ← تسجيل دخول الجوال ← `GET /api/mobile/sync/full` يسحب كل الكفالات، و`bulk-upload` و`sync/actions` يعدّلانها. وحسابات بوابة الأيتام (الهوية + رقم الملف) تعمل بنفس الطريقة.
- **الإصلاح المقترح:** عطّل التسجيل العام أو قيّده، واسمح بدخول الجوال لأدوار الموظفين فقط، وأضف `permission:`/abilities على مسارات الرمز.

### S-17 — أي مستخدم مسجّل يحذف أي مستخدم آخر نهائياً، بمن فيهم المدير

- **الخطورة:** 🔴 حرج · ✓ تم التحقق يدوياً
- **الملفات:** `routes/web.php:169`، `app/Http/Controllers/Users/UserProfileController.php:60-75`
- **الثغرة:** `User::findOrFail($id)->delete()` بلا أي فحص ملكية، والتعليق يقول «Soft Delete» بينما النموذج بلا SoftDeletes.
- **سيناريو الاستغلال:** حساب مسجّل ذاتياً يرسل `DELETE /user/delete-profile/1` فيُحذف حساب المدير.
- **الإصلاح المقترح:** استخدم `Auth::user()` فقط أو سياسة تفويض، وراجع `AdminController::softDeleteProfile` بالمثل.

### S-18 — البوابة: أي مستخدم يعدّل أي كفالة (IDOR)

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `routes/web.php:159`، `app/Http/Controllers/Users/ShowGeneralRegisrationController.php:1045-1135`
- **الثغرة:** التعليق يقول إن الجلسة تُفحص، لكن الكود يأخذ `sponsorship_id` من الطلب ولا يقارنه بـ `session('active_sponsorship_id')`.
- **سيناريو الاستغلال:** مستخدم بوابة يرسل `sponsorship_id` لعائلة أخرى مع حقول وأفراد ومرفقات فيكتب فوق بياناتها ووثائقها.
- **الإصلاح المقترح:** استخدم معرّف الجلسة وتجاهل المدخل، أو 403 عند الاختلاف.

### S-19 — أي مستخدم مسجّل ينزّل أي مرفق (IDOR)

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `routes/web.php:162`، `app/Http/Controllers/Users/ShowGeneralRegisrationController.php:3054-3088`
- **الثغرة:** `Attachment::find($id)` بلا فحص مالك ثم بث الملف من Drive.
- **سيناريو الاستغلال:** المرور على `/user/general-registration/attachment/1..N` يجمع هويات وشهادات وفاة كل العائلات.
- **الإصلاح المقترح:** تحقق أن المرفق يخص كفالة/عائلة الجلسة.

### S-20 — فحص الوصول للملف يثق بالأرقام الموجودة في البريد الإلكتروني الذي يختاره المستخدم

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `app/Http/Controllers/UnifiedFileManagementController.php:4835-4870`، `routes/web.php:406`
- **الثغرة:** `$userIdNumber = preg_replace('/[^0-9]/','',$user->email)` ثم السماح إذا طابق رقم الملف، ويشمل أفراد العائلة.
- **سيناريو الاستغلال:** التسجيل ببريد `@mail.com` يمنح الوصول لملفات الضحية وعائلتها.
- **الإصلاح المقترح:** اربط الهوية بحقل مُتحقَّق منه لا بالبريد، وأغلق التسجيل المفتوح.

### S-21 — دخول بوابة الأيتام قابل للتخمين ويكتب «كلمة المرور» في السجل

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `routes/web.php:183`، `app/Http/Controllers/Users/UserLoginContoller.php:14-130`
- **الثغرة:** بيانات الدخول = رقم الهوية + رقم الملف الداخلي (6 خانات)، بلا تحديد معدل، ورسائل خطأ مختلفة تكشف وجود الهوية، والأسطر 56-61 تسجّل `internal_file_number` (أي كلمة المرور نفسها) مع كل محاولة خاطئة. والأرقام الصحيحة معروضة في `/admin/reserved-codes/gaps` لأي مستخدم مسجّل.
- **سيناريو الاستغلال:** تخمين أرقام الملفات لهوية معروفة، أو قراءتها من السجل.
- **الإصلاح المقترح:** `throttle` بالـ IP والهوية، ورسالة موحّدة، وعدم تسجيل الأرقام، واستخدام OTP أو سرّ حقيقي.

### S-22 — أغلب مسارات admin.php بلا صلاحيات دقيقة، ومسارات بصلاحيات تُلغيها نسخ بلا صلاحيات

- **الخطورة:** 🟡 متوسط
- **الملفات:** `routes/admin.php:63`، `routes/admin.php:204-209`، `routes/admin.php:403`، `routes/admin.php:559-561`، `routes/admin.php:578-592`
- **الثغرة:** 41 فقط من ~229 مساراً عليها `permission:`. أي مستخدم دوره `admin` يصل لكل شيء بما فيه `getRecentLogs` (آخر 50 سطراً من السجل). و`sponsorships/sponsored|unsponsored` المحمية تُستبدل بنسخة لاحقة بلا صلاحية.
- **الإصلاح المقترح:** صلاحيات متسقة، وحذف مسارات الاختبار، وعدم تكرار التسجيل.

### S-23 — تصعيد الصلاحيات الذاتي عبر تعديل الأدوار

- **الخطورة:** 🟡 متوسط
- **الملفات:** `app/Http/Controllers/Roles/RoleController.php:116-135`، `app/Http/Controllers/Users/UserController.php:108-129`، `app/Models/User.php`
- **الثغرة:** صاحب صلاحية «تعديل صلاحية مستخدم» يعدّل دوره ويمنح نفسه كل الصلاحيات، وصاحب «تعديل مستخدم» يضبط `role=admin` لأي حساب، و`role` قابل للإسناد الجماعي.
- **الإصلاح المقترح:** امنع تعديل الدور الذاتي، واحمِ أدوار المدير الأعلى.

### S-24 — إجراءات الخادم عامة غير مقيّدة بالجهاز أو المستخدم

- **الخطورة:** 🟡 متوسط
- **الملفات:** `app/Http/Controllers/Api/ServerActionController.php:15-50`
- **الثغرة:** أي حامل رمز يسحب كل الإجراءات المعلّقة ويؤكدها.
- **سيناريو الاستغلال:** استنزاف إجراءات الأجهزة الأخرى أو تعليمها كمنفّذة.
- **الإصلاح المقترح:** قيّد بـ `device_id` والمستخدم.

### S-25 — رموز Sanctum لا تنتهي

- **الخطورة:** 🟡 متوسط
- **الملفات:** `config/sanctum.php:49`، `app/Http/Controllers/Api/SponsorshipSyncController.php:88-112`، `app/Http/Controllers/Api/MobileSyncController.php:77`
- **الثغرة:** `'expiration' => null`، و`refreshToken` يُصدر رمزاً بلا انتهاء وبصلاحيات `['*']` بينما يخبر العميل أنه ينتهي بعد 30 يوماً.
- **سيناريو الاستغلال:** رمز مسروق من جهاز ميداني يبقى صالحاً للأبد.
- **الإصلاح المقترح:** حدّد انتهاءً و`expiresAt` وصلاحيات محدودة.

### S-26 — تسميم رأس Host في إعادة تعيين كلمة المرور (يحتاج تأكيد)

- **الخطورة:** 🟡 متوسط
- **الملفات:** `app/Http/Kernel.php:17`
- **الثغرة:** `TrustHosts` معطّل ولا يوجد `URL::forceRootUrl`.
- **سيناريو الاستغلال:** طلب `forgot-password` برأس Host مزيّف قد يرسل رابط إعادة تعيين يشير لنطاق المهاجم (يعتمد على إعداد الخادم الافتراضي).
- **الإصلاح المقترح:** فعّل `TrustHosts` وثبّت الرابط الجذري.


---

## تسريب البيانات الشخصية والأسرار

### S-27 — بيانات شخصية حقيقية من الإنتاج وسجلات الخادم محفوظة في git

- **الخطورة:** 🔴 حرج · ✓ تم التحقق يدوياً
- **الملفات:** `laravel.log`، `laravel-second-copy.log`، `docs/laravel103.log`، `missing_persons.json`، `p1_dump.json … p4_dump.json`، `sync_json*.json`، `sync_response.json`، `from_server/laravel.log`
- **الثغرة:** السجلات تحوي مئات أرقام الهوية الحقيقية ومسارات الإنتاج (`/var/www/html/alhayahorphans`، `rclone.conf`) وصف `insert into users` باسم وهاتف وbcrypt hash. `missing_persons.json` يضم 827 شخصاً بالهوية والاسم الكامل. ملفات `*_dump.json` تضم هويات الأيتام والأوصياء وهواتفهم وتواريخ ميلادهم. وتاريخ git يضم عدة ملفات APK.
- **سيناريو الاستغلال:** أي شخص لديه وصول للمستودع (أو أي نسخة منه) يملك قاعدة بيانات أطفال وأوصياء.
- **الإصلاح المقترح:** `git rm --cached` + `.gitignore`، ثم تطهير التاريخ بـ `git filter-repo` أو BFG مع force-push وإعادة استنساخ الجميع. عامل الأمر كحادثة حماية بيانات محتملة وراجع من يملك صلاحية الوصول للمستودع.

### S-28 — ملف `.envbak` بمفتاح APP_KEY حقيقي في تاريخ git (فرع جانبي)

- **الخطورة:** 🟡 متوسط
- **الملفات:** `git: 59fa245 → 1a5dd84 (origin/adndoid-v2-edited)`
- **الثغرة:** المفتاح (محجوب هنا) مع `APP_DEBUG=true` وبيانات قاعدة root. يختلف عن مفتاح الإنتاج الحالي فيبدو أنه تغيّر، لكن يحتاج تأكيداً أنه لم يُستخدم في الإنتاج.
- **سيناريو الاستغلال:** مفتاح APP_KEY يسمح بتزوير الروابط الموقّعة وفك تشفير الكوكيز والحقول المشفرة.
- **الإصلاح المقترح:** طهّر التاريخ، وبدّل المفتاح إن كان قد استُخدم يوماً.

### S-29 — بيانات الاتصال بقاعدة البيانات مكتوبة في الكود (root بلا كلمة مرور)

- **الخطورة:** 🟡 متوسط · ✓ تم التحقق يدوياً
- **الملفات:** `app/Services/ExactMatchSearchService.php:17-20`، `app/Services/LightningSearchService.php:20`، `app/Services/SimpleExactSearchService.php:17`، `app/Services/SmartExactSearchService.php:19`، `routes/api.php:92-94`، `routes/api.php:255-257`
- **الثغرة:** `new PDO('mysql:host=localhost;dbname=aso', 'root', '')` يتجاوز إعدادات Laravel.
- **سيناريو الاستغلال:** إن كان root بلا كلمة مرور في الإنتاج فأي وصول محلي يعني تحكماً كاملاً بالقاعدة (يحتاج تأكيد).
- **الإصلاح المقترح:** `DB::connection()->getPdo()` ومستخدم قاعدة بأقل صلاحيات.

### S-30 — رسائل الاستثناءات وstack traces تُرجَع للعميل

- **الخطورة:** 🟡 متوسط
- **الملفات:** `app/Http/Controllers/Admin/DiagnosticController.php:88-92`، `routes/api.php:362`، `public/test-export.php`
- **الثغرة:** نحو 300 موضع تضع `$e->getMessage()` في الاستجابة، وبعضها يُرجع trace كاملاً وأخطاء PDO.
- **سيناريو الاستغلال:** كشف أسماء الجداول والأعمدة والمسارات، ما يسهّل باقي الهجمات.
- **الإصلاح المقترح:** رسالة عامة للعميل والتفاصيل في السجل.

### S-31 — تسجيل الطلبات كاملة ببياناتها الشخصية ورمز CSRF

- **الخطورة:** ⚪ منخفض
- **الملفات:** `app/Http/Controllers/Admin/RecordsManagementEditController.php:544`، `app/Http/Controllers/Admin/SponsorController.php:47`، `app/Http/Controllers/Admin/SearchOnRecordsController.php:146`، `app/Services/SearchService.php`
- **الثغرة:** `Log::info` لجسم الطلب ونصوص البحث (أسماء وهويات). في الإنتاج `LOG_LEVEL=error` يحجب أغلبها.
- **الإصلاح المقترح:** سجّل المعرّفات فقط.


---

## XSS والحقن

> لم يُعثر على حقن SQL قابل للاستغلال: كل استعلامات `whereRaw/selectRaw/DB::select` التي تمس مدخلات المستخدم تستخدم ربط `?`، ولا يوجد `$guarded = []` ولا `create($request->all())`.

### S-32 — XSS مخزّن في أعمدة DataTables الخام وقوالب JavaScript

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `app/DataTables/UnifiedPeopleDataTable.php:46-88`، `app/DataTables/RecordsManagementeDataTable.php:55`، `app/DataTables/RecordsManagementeDataTable.php:107`، `app/DataTables/SponsorshipsDataTable.php:24`، `app/DataTables/SponsorshipsDataTable.php:307-352`، `resources/views/admin/dashboard/sponsorships/unsponsored.blade.php:1389-1412`، `public/js/scout-search-fixed.js:203-353`
- **الثغرة:** أسماء الأشخاص والأوصياء والجهات والـ IBAN تُدمج دون ترميز في HTML داخل `rawColumns`، وفي `onclick="…('${person.full_name}')"` و`innerHTML`. لا يوجد أي دالة ترميز في 62 قالباً يستخدم innerHTML أو `.html()`. والتحقق على الأسماء `string|max:255` فقط.
- **سيناريو الاستغلال:** حساب مسجّل ذاتياً (أو نموذج التسجيل العام) يُدخل اسماً يحوي شيفرة، فتُنفَّذ في جلسة المدير حين يفتح القائمة، فيتصرف المهاجم بصلاحيات المدير.
- **الإصلاح المقترح:** `e()` لكل قيمة داخل HTML في DataTables، و`rawColumns` للعناصر الثابتة فقط، و`textContent` أو دالة `escapeHtml` في JS، وربط الأحداث بـ `addEventListener`، وCSP صارمة.

### S-33 — مخرجات غير مرمّزة في بعض القوالب

- **الخطورة:** ⚪ منخفض
- **الملفات:** `resources/views/user/dashboard/component/generalRegisrationIndex_new.blade.php:202-210`، `resources/views/layouts/admin.blade.php:19`
- **الثغرة:** `@php echo` لاسم البنك داخل خاصية HTML (القالب يبدو غير مستخدم)، و`{!! $breadcrumb !!}` آمن فقط إن بُني دائماً في الخادم.
- **الإصلاح المقترح:** استخدم `{{ }}`.

### S-34 — عمود الترتيب من الطلب ويحمّل السجل المدني كاملاً

- **الخطورة:** ⚪ منخفض
- **الملفات:** `app/Http/Controllers/Admin/PersonsController.php:74-85`
- **الثغرة:** `sort_field` يُتحقق منه كنص فقط. Laravel يقتبس المعرّفات فلا حقن، لكن `->get()` على كل السجل المدني.
- **سيناريو الاستغلال:** طلب واحد يستنفد ذاكرة الخادم.
- **الإصلاح المقترح:** قائمة بيضاء للأعمدة وترقيم.


---

## تطبيق الجوال

### S-35 — إعدادات النقل والتخزين في التطبيق إعدادات تطوير لا إنتاج

- **الخطورة:** 🟠 عالٍ · ✓ تم التحقق يدوياً
- **الملفات:** `capacitor.config.json`، `android/app/src/main/AndroidManifest.xml:7`، `android/app/src/main/AndroidManifest.xml:15`، `android/app/src/main/res/xml/file_paths.xml`، `public/js/api-service.js:128-130`
- **الثغرة:** `cleartext: true`، `allowMixedContent: true`، `webContentsDebuggingEnabled: true`، `allowBackup="true"`، `usesCleartextTraffic="true"` بلا network security config. FileProvider يكشف كامل الذاكرة الخارجية ``. الرمز وبيانات المستخدم في localStorage، والبيانات دون اتصال غير مشفرة في IndexedDB/SQLite.
- **سيناريو الاستغلال:** من يحصل على الجهاز يقرأ الرمز وبيانات الأطفال عبر Chrome DevTools أو `adb backup`، وعلى شبكة Wi-Fi معادية يمكن حقن محتوى في الاتصال غير المشفر.
- **الإصلاح المقترح:** عطّل التصحيح والنص الصريح والمحتوى المختلط في نسخة الإصدار، و`allowBackup=false`، وضيّق مسارات FileProvider، وخزّن الرمز في Keystore وشفّر SQLite.

### S-36 — رابط احتياطي لنفق عام يمكن لأي أحد حجزه

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `android/app/src/main/assets/public/js/api-service.js:9`
- **الثغرة:** إن غاب `APP_CONFIG` يرسل التطبيق الطلبات (مع الرمز وبيانات الدخول) إلى `https://yummy-pans-post.loca.lt/api`.
- **سيناريو الاستغلال:** من يحجز هذا النطاق الفرعي على loca.lt يستقبل بيانات الدخول والبيانات المرسلة.
- **الإصلاح المقترح:** احذف الرابط الاحتياطي وافشل بوضوح عند غياب الإعداد.

### S-37 — مستقبِلا بث مُصدَّران وService Worker قد يخزّن صفحات المدير

- **الخطورة:** ⚪ منخفض
- **الملفات:** `android/app/src/main/AndroidManifest.xml:105-124`، `public/sw.js:58-90`
- **الثغرة:** `RealtimeSyncBootReceiver` و`UploadBootReceiver` مُصدَّران (يحتاج تأكيد فحص `getAction()`). و`sw.js` يخزّن كل GET غير `/api/` بنطاق `/` (غير مسجّل حالياً).
- **الإصلاح المقترح:** تحقق من الإجراء في المستقبِلات، واستثنِ المسارات المصادَق عليها أو احذف sw.js.


---

## الإعدادات والمكتبات

### S-38 — مكتبات بثغرات معروفة: 68 تنبيهاً في 19 حزمة PHP و13 في npm (3 حرجة)

- **الخطورة:** 🟠 عالٍ
- **الملفات:** `composer.json`، `composer.lock`، `package-lock.json`
- **الثغرة:** `spatie/browsershot 5.0.0` مثبّتة بإصدار قديم (6 ثغرات: قراءة ملفات محلية، تجاوز مسار، SSRF). `phpoffice/phpspreadsheet 1.30.2` بثغرات حرجة عند `IOFactory::load` لملفات مرفوعة (مستخدمة في `SponsorshipController:2160` و`ExcelImportService:327`). `knp-snappy 1.5.1` مع `enable-local-file-access=true`. Laravel 10 خارج دعم الأمان. `laravelcollective/html` متروكة. `@capacitor/android` (حرجة) و`tar` و`basic-ftp`. وإصدارات `"*"` غير مثبّتة.
- **الإصلاح المقترح:** `composer update` للحزم المتأثرة، وخطة ترقية إلى Laravel 11/12، و`npm audit fix` وCapacitor ≥ 8.3.5، وتثبيت الإصدارات.

### S-39 — كوكيز الجلسة ورؤوس الأمان ضعيفة

- **الخطورة:** 🟡 متوسط
- **الملفات:** `config/session.php:49`، `config/session.php:171`، `.env.production`
- **الثغرة:** `SESSION_SECURE_COOKIE` غير مضبوط في الإنتاج، و`encrypt => false`، ولا CSP ولا HSTS ولا X-Frame-Options ولا nosniff بشكل عام.
- **سيناريو الاستغلال:** كوكي الجلسة قد يُرسل عبر HTTP، والصفحات قابلة للتضمين في إطارات.
- **الإصلاح المقترح:** `SESSION_SECURE_COOKIE=true` وmiddleware لرؤوس الأمان.

### S-40 — استثناءات CSRF ونطاق CORS

- **الخطورة:** ⚪ منخفض
- **الملفات:** `app/Http/Middleware/VerifyCsrfToken.php`، `config/cors.php`، `routes/offline-test-development.php`
- **الثغرة:** `$except = ['api/files/*', 'admin/speedtest/api']` (التعليق يقول مؤقت)، و`allowed_origins => ['*']`، ورأس `Access-Control-Allow-Origin: *` يدوي على مسارات الاختبار.
- **الإصلاح المقترح:** أزل الاستثناءات وقيّد المصادر.


---

## ما فُحص ووُجد سليماً

- لا حقن SQL قابل للاستغلال: الاستعلامات الخام تستخدم ربط المعاملات، والأعمدة المتغيرة تأتي من إعدادات الخادم.
- لا إسناد جماعي خطر: لا `$guarded = []` ولا `create($request->all())`.
- ملفات `.env` و`.env.production` و`credentials.json` لحساب خدمة Google لم تُرفع إلى git أبداً.
- تسجيل الدخول الأساسي (Breeze): حد 5 محاولات، وتجديد الجلسة، وتسجيل الخروج سليم.
- مسار `/storage/{path}` يمنع الخروج من المجلد بشكل صحيح (المشكلة فقط غياب المصادقة).
- `ChunkedUploadController` ينقّي معرّف الرفع، و`UploadToGoogleDriveJob` و`RcloneGoogleDriveService` يمرّران معاملات الأوامر بأمان.
- لا إعادة توجيه مفتوحة، ولا `unserialize()` على مدخلات المستخدم، و`.env.production` فيه `APP_DEBUG=false`.

---

**منهجية الفحص:** منهجية الفحص: مراجعة ثابتة للكود عبر ثلاثة مسارات متوازية (المسارات والصلاحيات، الملفات والتنفيذ، الحقن والأسرار والمكتبات)، مع `php artisan route:list` لمعرفة الـ middleware الفعلي لكل مسار، و`composer audit` و`npm audit`، وفحص تاريخ git. لم يُعدَّل أي ملف ولم يُرسل أي طلب إلى خادم الإنتاج؛ سيناريوهات الاستغلال مستنتجة من الكود. البنود «يحتاج تأكيد» تعتمد على إعداد خادم الويب أو قاعدة الإنتاج.
