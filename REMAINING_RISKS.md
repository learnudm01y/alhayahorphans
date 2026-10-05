# المخاطر المتبقية بعد إصلاحات التسجيل (REMAINING_RISKS)

> إعداد: فرع `adndoid-v2-edited` — commits `18f47eb` .. `d463e93`.
> **التحديث الأخير:** استُبدلت قاعدة `aso` بـ `C:\Users\mfarr\Downloads\aso.sql` (طلب المستخدم)
> — أنظر R1 الذي يتحدث إلى «حُلّ»، وR9 الجديد. ثم جولة لاحقة: إصلاح اسم المتوفى وأفراد
> الأسرة (`7c78ac8`) + تحقّق «جميع الحقول الظاهرة» (`d463e93`) → R10 وR11.

---

## R1 — إنشاء صف جديد في `data`: **حُلّ باستبدال القاعدة** ✅

### قبل الاستبدال (التحليل الأصلي — سجل تاريخي)

- `sql_mode` في جلسة التطبيق كان صارماً (`STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE…`)
  لأن `config/database.php` يحدّد `'strict' => true`.
- جدول `data`: **32 عموداً `NOT NULL` بلا `DEFAULT`**، منها **24 عموداً** كان بإمكانه إيقاف
  الإدراج بخطأ `1364`، **17** منها بلا مصدر إطلاقاً في مسار الحفظ (12 عموداً لا يجمعها النموذج
  + 5 أعمدة يرسلها النموذج لكن `createCentralDataRecords()` لا يطبّقها قبل الإدراج).
- الأدلة: استعلام `information_schema` + تجربة إدراج داخل معاملة **تُلغى**
  (`ERROR 1364 … Field 'data_relationship' doesn't have a default value`).
- خلاصتها: إصلاح `data_section_id` (البند 4) **لازم لكنه غير كافٍ**، وحلّها يحتاج قراراً
  (schema / توسيع تطبيق الحقول / تعطيل strict / عدم الدعم).

### بعد الاستبدال (`aso.sql`, 546 MB)

أُعيد فحص الشيء نفسه على المخطط المستورد:

| المؤشر | النتيجة |
|---|---|
| أعمدة `data` `NOT NULL` بلا `DEFAULT` | **1** فقط وهو `id` (auto_increment) |
| `blockers` (ما كان قد أوقف الإدراج) | **0** |
| `data_section_id`, `data_relationship`, `data_marital_status`, `data_city`, `data_user_insert_data` … | كلها الآن `IS_NULLABLE=YES` مع default (`NULL`) |
| تجربة الإدراج ذاتها داخل معاملة تُلغى | **`INSERT OK (id=13734)`** ثم `rollBack` → `rows=11319` بلا أي تغيير |
| `data_number_alt` | **حُذف من المخطط الجديد** (دليل على أن `aso.sql` يمثّل مخططاً أحدث) |

**الخلاصة:** لم يعد هناك حاجة لأي من الخيارات الأربعة (schema / تعطيل strict / توسيع / إيقاف دعم الإنشاء).
البنود **4** و**6** تعمل الآن على مخطط سليم.

---

## R2 — فشل اختبار مسجَّل قبل أي تعديل

`Tests\Feature\LivingMotherRegistrationTest > deceased mother does not save to portal table…`

```
SQLSTATE[23000]: Integrity constraint violation: 1452 … CONSTRAINT `dead_people_re_file_id_foreign`
FOREIGN KEY (`re_file_id`) REFERENCES `data` (`file_id_number`)
```

السبب: الاختبارات تُشغَّل على `aso_testing` (**لا** على `aso`)، وهي ما تزال فارغة —
وفشل `Tests\Feature\Auth\RegistrationTest` معها. خط الأساس 91/2/5 **لم يتغيّر بعد استبدال `aso`**.

---

## R3 — تغيّر سلوك التحقق من المرفقات (البند 8)

`validateRequiredAttachments()` أصبح يقبل **الملف الموجود مسبقاً** ككافٍ للوثيقة المطلوبة.

- ✅ أزال عائق «إعادة رفع كل وثيقة عند كل تعديل».
- ⚠️ إذا كان القرار التجاري أن **كل تعديل يستلزم وثيقة جديدة**، فالبند 8 يحتاج تقييداً
  (مثلاً: قبول الملف القديم ما لم يُطلب تاريخه كحد أدنى، أو تنبيه بدل الرفض).
- ملفات `<input type=file>` **لا يمكن استعادتها** في `old()` بعد إعادة التوجيه — لذلك
  الوثائق السابقة تُحسب كافية، والرسالة تظهر فقط عند غياب الملف السابق وعدم رفع جديد.

---

## R4 — لا توجد اختبارات تغطّي المسار الحرج

- `POST /user/general-registration/update` **ليس مغطّى بأي اختبار**.
- التحقق يدوي فقط (قائمة الفحص في `REGISTRATION_FIXES_REPORT.md`).
- التوصية: اختبار Feature واحد (نجاح مبسّط + رفض غير المالك + رسالة خطأ عربية).

---

## R5 — قاعدة اختبار `aso_testing`

- أُنشئت فارغة أثناء المهمة لأنها كانت غير موجودة (`utf8mb4_unicode_ci`).
- 2 `failed` + 5 `skipped` مسجَّلة قبل أي تعديل ولم تُعالَج عمداً (سببها بيانات ناقصة).
- إن أردت تشغيل الاختبارات على بيانات حقيقية: استورد `aso.sql` داخل `aso_testing`
  (خطوة لم تُنفَّذ لأن الطلب كان بخصوص `aso` فقط).

---

## R6 — بنود لم تُنفَّذ بقرار

| البند | الحالة |
|---|---|
| 17 — الأصفار البادئة في أرقام الهوية/الملفات | **تقرير فقط**؛ لا تغيير في `trim`/`ltrim`/`%09d` |
| 21 — مراجعة أمنية أوسع (CSRF, mass-assignment, rate-limit, Rclone, storage) | **تقرير فقط**؛ التغيير الأمني الوحيد هو البند 3 (الملكية) |

---

## R7 — بنود تم تجاوزها بقرار المستخدم

البنود `9`, `14`, `15`, `16`, `22`, `23`, `24`, `26` **تم تجاوزها صراحةً** بعَدَم تنفيذها.
لا يوجد لها أي تعديل في هذه الجلسة، ولا يُنسب إليها شيء في `REGISTRATION_FIXES_REPORT.md`.
تُفتح فقط إذا أُعيد إرسال نصها.

---

## R8 — ملاحظات تشغيلية

- **استبدال قاعدة `aso` (طلب المستخدم):**
  - نسخة أمان لما قبل الحذف: `C:\Users\mfarr\AppData\Local\Temp\opencode\aso-backup\aso-before-replace.sql` (1.8 MB).
  - الاستيراد: `mysql -uroot --default-character-set=utf8mb4 --force < aso.sql`
    (546 MB / 8,598,168 سطراً / 86 جدولاً).
  - **خطأ واحد فقط**: `ERROR 1452` عند السطر 8,598,150 = `ALTER TABLE telescope_entries_tags ADD CONSTRAINT …`
    أي أن **قيود `telescope_entries_tags` الوحيدة لم تُنشأ** لأن الملف نفسه صدّر
    8,297,803 وسم مقابل **108** فقط من `telescope_entries` (تناسق ناقص في المصدر).
    **أثره:** صفر على التطبيق (أداة تتبّع التطوير)؛ يُصحَّح بحذف 8.3 مليون وسم يتيم ثم إعادة `ALTER`.
  - عدد `FOREIGN KEY` في `aso` الآن **44**.
- `sms-feature.zip` بقي **غير مُتعقَّب** (untracked) ولم يُحذَف.
- احتياطات خارج المستودع (لا تُلف تلقائياً):
  `C:\Users\mfarr\AppData\Local\Temp\opencode\alhayah-backup\`
  (`working-tree.patch`, `controller_5edce4d.php`, `controller_82a8180.php`, `fixes-5edce4d.diff`).
- MySQL أُعيد إقلاعه يدوياً خلال الجلسة (`innodb_buffer_pool_size=16M` في `my.ini`)؛
  الاستيراد نجح رغم صغر الحجم.
- لا `php artisan migrate` / لا تغيير schema يدوياً / لا تعديل على Google Drive أو Rclone
  أو Chunked Upload أو Android.

### بيئة التشغيل بعد نقل المشروع (مُبلَّغ به للمستخدم)

| العنصر | القيمة |
|---|---|
| مسار المشروع | **`E:\laragon\www\alhayahorphans`** (المستودع والـ git هنا؛ `C:\xampp\htdocs\alhayahorphans` بقي فيه `node_modules` فقط) |
| الويب | Apache عبر **Laragon** — vhost `auto.alhayahorphans.test` و`DocumentRoot E:/laragon/www/alhayahorphans` |
| PHP (CLI) | `C:\xampp\php\php.exe` (PHP 8.2.12) — `php artisan` يعمل |
| قاعدة البيانات | **ما زالت MariaDB 10.4 الخاصة بـ XAMPP** على `127.0.0.1:3306` (datadir `C:\xampp\mysql\data`، قاعدتا `aso` + `aso_testing`) |
| تحذير | تشغيل MySQL الخاص بـ Laragon (8.4 على 3306) سيتعارض ولن يحوي `aso` → يُترك موقوفاً |
| الإقلاع | MySQL **ليس خدمة**؛ بعد أي إعادة تشغيل يُعاد تشغيله يدوياً: `Start-Process 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini'` |
| إصلاح 4 أكتوبر | `mysql\innodb_table_stats.ibd` كان مُصفَّر الترويسة فأُعيدت النسخة السليمة من `C:\xampp\mysql\backup\mysql\innodb_table_stats.{frm,ibd}` (space id = 1) — راجع `data_backup_*`/`data_corrupt_*` داخل `C:\xampp\mysql` ونسخ `…\Temp\opencode\mysql-datadir-mysql-backup\` قبل أي حذف |

---

## R10 — تحقّق ملء الحقول من جهة المتصفح فقط (البند 30)

`validateVisibleFieldsBeforeSubmit()` في `generalRegisrationIndex.blade.php` تمنع الإرسال ما لم
يُعبَّأ كل حقل مرئي، لكنها **تعتمد على JavaScript** فقط (بقرار المستخدم: لا قواعد سيرفرية):

- من يرسل الطلب مباشرة (`curl`/سكربت) يتجاوز الفحص تماماً — `$request->validate` الحالي يشترط
  `'fields' => 'array'` و`'family_members' => 'array'` و`'attachments*'` فقط.
- الإلزام يشمل **كل** ما هو مرئي، بما فيها ملاحظات/سبب الوفاة؛ الاستثناء الوحيد هو `data-optional="1"`.
- حقول `readonly`/`disabled`/`hidden`/`file`/`checkbox`/`radio` لا تُفحص (تعطّلها كان سيعطّل الحفظ).
- الفحص يسبق فحص المرفقات، والتحقق من المرفقات يبقى كما هو في السيرفر.

---

## R11 — صفوف أفراد الأسرة لا تُرسم من الخادم

`FAMILY_MEMBERS_SUBMIT {"in_request":false,"rows_received":0}` مسجَّل حتى بعد الإصلاح:
السبب أن `index()` يضبط `$familyMembers = collect()` عند غياب `relationData`، فلا توجد صفوف في الـ DOM
وَلا شيء يُرسَل (صفوف JS تُبنى حصراً بزر «إضافة فرد»). الإصلاح في `7c78ac8` جعل الكونترولر
**يستقبل** الصفوف ويتعامل مع `is_new`/`id` الفارغ، لكنه **لا يضمن** وصولها.

- المطلوب للتأكد: كفالة لديها أفراد أسرة مسجَّلون → فتح الصفحة → التأكد من رسم الصفوف → `rows_received > 0`.
- إن ظل `false` على كفالة كهذه فالمشكلة في تمرير `relationData` إلى `index()` وتستحق تحقيقاً مستقلاً
  (لم يُلمس `index()` في هذه الجلسة عمداً).
