# المخاطر المتبقية بعد إصلاحات التسجيل (REMAINING_RISKS)

> إعداد: فرع `adndoid-v2-edited` — بعد commits `18f47eb` .. `24e8b17`.
> **لا شيء مما يلي نُفَّذ تلقائياً**؛ كل بند يحتاج قراراً أو تدخلاً يدوياً.

---

## R1 — أعلى خطورة: إنشاء صف جديد في جدول `data` يفشل دائماً في هذه البيئة

**الحالة:** إصلاح البند 4 (`data_section_id`) **لازم لكنه غير كافٍ**.

الأدلة (بيئة التطبيق نفسها):
- `php artisan` ← `@@session.sql_mode` =
  `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`
  (لأن `config/database.php` يحدّد `'strict' => true`) → أي عمود `NOT NULL` بلا default يوقف الإدراج بـ **1364**.
- تجربة داخل معاملة **تُلغى** (`START TRANSACTION` … `ROLLBACK`، بلا أي تغيير في البيانات) بنفس الوضعية
  وبالمُحمّلات التي ينتجها `createCentralDataRecords()`:

  ```
  ERROR 1364 (HY000) at line 1: Field 'data_relationship' doesn't have a default value
  ```

- الجدول **فارغ تماماً** (`SELECT COUNT(*) FROM data` = 0) → أي إنشاء ملف جديد يمرّ بهذا المسار.

**الأرقام الدقيقة** (استعلام `information_schema` على `aso`):

| البند | العدد |
|---|---|
| أعمدة `NOT NULL` بلا `DEFAULT` في `data` | **32** |
| منها ما يضمنه الكود دائماً (id, file_id_number, data_section_id, data_id_number, created_at, updated_at, الأسماء الأربعة) | 10 |
| **أعمدة قد توقف الإدراج** | **24** |
| منها يغطيها الكود إذا أرسلها النموذج (المواليد، الهاتف، المحافظة، المدينة، الحالة الصحية، حالة السكن، نوع السكن) | 7 |
| **متبقٍّ** | **17** |

تصنيف الـ17 المتبقية:

**أ) النموذج نفسه لا يجمعها ولا مصدر آخر لها (12 عموداً):**

```
data_gender, data_alt_phone_number, data_number_of_individuals,
data_marital_status, data_academic_qualification, data_displacement_status,
data_address_before_displacement, data_description_needs, data_number_alt,
data_number_of_individuals_with_chronic_diseases,
data_number_of_people_with_special_needs, data_user_insert_data
```

**ب) النموذج يرسلها لكن `createCentralDataRecords()` لا يطبّقها عند الإدراج
(يطبّقها الحلقة اللاحقة بعد إنشاء السجل — أي بعد فشله) (5 أعمدة):**

```
data_relationship              ← field_data_relationship
data_current_address           ← field_data_address
data_number_mail               ← field_dependents_male
data_number_female             ← field_dependents_female
data_employment_status_breadwinner ← field_guardian_job
```

**ج) أعمدة من الفئتين أ + ب تُستخدم كمفاتيح أجنبية (RESTRICT):**

```
data_relationship        → category_of_relations
data_marital_status      → marital_status
data_academic_qualification → academic_degrees
data_employment_status_breadwinner → employment
data_province / data_city / data_health_status / data_housing_status
data_current_housing_type
```

> أي قيمة «افتراضية» غير موجودة فعلياً في جدول الـ lookup ستُستبدل بـ **1452** بعد تجاوز 1364.

**الخيارات (تحتاج قراراً):**

| الخيار | الوصف | المخاطر |
|---|---|---|
| **أ** (موصى به) | إضافة `DEFAULT` للأعمدة الـ17/الـ24 عبر migration واحد صغير (و`ALTER … DROP DEFAULT` قابل للتراجع) | تغيير schema — خارج نطاق المهمة كما ورد، يحتاج موافقتك |
| **ب** | توسيع `createCentralDataRecords()` ليغطي مجموعة (ب) من القيم المُرسلة فعلاً + `data_user_insert_data` بنمط `Admin\SponsorshipController` (`auth()->user()->name ?? 'System'`) + توفير قيم لمجموعة (أ) من الحقول المكافئة إن وُجدت | **لن يكفي وحده** (مجموعة أ تبقى حاجباً) |
| **ج** | السماح لـ MySQL بالقيم الضمنية غير الصارمة (تعطيل strict في `config/database.php`) | مخالفة لسياسة المشروع، ويتخلّل باقي التطبيق (التواريخ `0000-00-00` … إلخ) |
| **د** | عدم دعم «إنشاء ملف جديد» حتى تُستورد بيانات `data` أصلاً (الجدول فارغ) | إيقاف ميزة |

**لا توجد بيانات وهمية أُضيفت** أثناء تنفيذ المهمة، ولا أي `ALTER TABLE` / migration.

---

## R2 — فشل اختبار مرتبط بـ R1

`Tests\Feature\LivingMotherRegistrationTest > deceased mother does not save to portal table…`

```
SQLSTATE[23000]: Integrity constraint violation: 1452
Cannot add or update a child row … CONSTRAINT `dead_people_re_file_id_foreign`
FOREIGN KEY (`re_file_id`) REFERENCES `data` (`file_id_number`)
```

`dead_people.re_file_id` و`re_people.registration_id` يشيران إلى `data.file_id_number`
والجدول فارغ. هذا الفشل **موجود قبل التعديلات** (نفسه في خط الأساس 91/2/5)
وسيختفي تلقائياً إذا حلّ R1.

---

## R3 — تغيّر سلوك التحقق من المرفقات (البند 8)

`validateRequiredAttachments()` أصبح يقبل **الملف الموجود مسبقاً** ككافٍ للوثيقة المطلوبة.

- ✅ أزال عائق «إعادة رفع كل وثيقة عند كل تعديل».
- ⚠️ إذا كان القرار التجاري أن **كل تعديل يستلزم وثيقة جديدة**، فالبند 8 يحتاج عكساً كاملاً
  (مثلاً: تقييد قبول الملف القديم بأحدث تاريخ، أو إشعار/تنبيه بدل رفض).
- الملفات المُدخلة عبر `<input type=file>` **لا يمكن استعادتها** في `old()` بعد إعادة التوجيه —
  لأن الوثائق السابقة تُحسب كافية، الرسالة تظهر فقط عندما لا يوجد ملف سابق ولا رفع جديد.

---

## R4 — لا توجد اختبارات تغطّي المسار الحرج

- مسار `POST /user/general-registration/update` **ليس مغطّى بأي اختبار**.
- التغييرات يدوي التحقق فقط (انظر `REGISTRATION_FIXES_REPORT.md` ← قسم «فحص يدوي مقترح»).
- التوصية: اختبار Feature واحد على الأقل (نجاح مبسّط + رفض غير المالك + رسالة الخطأ).

---

## R5 — قاعدة الاختبار `aso_testing`

- كانت **غير موجودة أصلاً**؛ أُنشئت فارغة بترميز `utf8mb4_unicode_ci`
  (`CREATE DATABASE IF NOT EXISTS aso_testing …`) حتى تعمل حزمة الاختبارات.
- 5 اختبارات `skipped` و2 `failed` مسجَّلة **قبل** أي تعديل (خط الأساس) — لم تُعالَج عمداً
  لأن سببها بيانات ناقصة في بيئة الاختبار لا منطق التطبيق.
- إن كنت تتوقّع `aso_testing` مُبذَّرة، فالمطلوب seeders (خارج نطاق المهمة).

---

## R6 — بنود لم تُنفَّذ بقرار

| البند | الحالة |
|---|---|
| 17 — الأصفار البادئة في أرقام الهوية/الملفات (`099999` ونحوه) | **تقرير فقط**؛ لا تغيير في `trim`/`ltrim`/`sprintf('%09d')` |
| 21 — مراجعة أمنية أوسع (CSRF, mass-assignment, rate-limit, Rclone credentials, storage paths) | **تقرير فقط**؛ لم يُنفَّذ أي تغيير أمني باستثناء البند 3 (الملكية) |
| 25 — لا يوجد `git checkout HEAD -- file` بعد نقطة الأمان | التزم به ما عدا **مخالفة واحدة مُعلنة** (أُعيد إصلاحها في `d2e4085`) |

---

## R7 — بنود تم تجاوزها بقرار المستخدم

البنود `9`, `14`, `15`, `16`, `22`, `23`, `24`, `26` **تم تجاوزها صراحةً** بعدم تنفيذها
(لا يوجد لها أي تعديل في هذه الجلسة، ولا يُنسب إليها شيء في `REGISTRATION_FIXES_REPORT.md`).
يُعاد فتحها فقط إذا أُعيد إرسال نصها.

---

## R8 — ملاحظات تشغيلية

- `sms-feature.zip` بقي **غير مُتعقَّب** (untracked) ولم يُحذَف — احذفه أو تبعه يدوياً.
- احتياطات خارج المستودع (لا تُلف تلقائياً):
  `C:\Users\mfarr\AppData\Local\Temp\opencode\alhayah-backup\`
  (`working-tree.patch`, `controller_5edce4d.php`, `controller_82a8180.php`, `fixes-5edce4d.diff`).
- MySQL كان منهك `innodb_buffer_pool_size=16M`؛ أُعيد إقلاعه يدوياً خلال الجلسة.
- لا `php artisan migrate` / لا تغيير schema / لا تعديل على Google Drive أو Rclone
  أو Chunked Upload أو Android.
