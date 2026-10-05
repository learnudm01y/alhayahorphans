# تقرير إصلاحات «التسجيل العام» — المهمة الهندسية

**الفرع:** `adndoid-v2-edited`
**النقطة الآمنة:** `18f47eb chore: checkpoint before safe registration fixes`
**المدى:** `18f47eb` → `08ce370` (12 commits)
**الملفات المتغيّرة:** `app/Http/Controllers/Users/ShowGeneralRegisrationController.php` + `resources/views/user/generalRegistration/thank-you.blade.php` + `resources/views/user/dashboard/component/generalRegisrationIndex.blade.php`
**قاعدة الاختبارات:** `aso_testing` (أنشئت أثناء المهمة لأنها كانت مفقودة)

---

## 1. القواعد التي التزمت بها

| القاعدة | التنفيذ |
|---|---|
| تغييرات surgical فقط (لا rebuild / refactor / إعادة هيكلة) | ✅ لا يُغيَّر توقيع دالة قائمة، لا نقل كود، لا حذف ميزة |
| لا تغييرات على schema، لا migrations | ✅ لا يوجد أي `ALTER`/`migrate` |
| لا لمس Google Drive / Rclone / Chunked Upload / Android | ✅ لم تُمَس |
| commits صغيرة + فحص بعد كل commit | ✅ `php -l` ثم `git commit -F msg.txt` ثم `php artisan optimize:clear && php artisan test` |
| `git diff` + تحليل قبل التنفيذ + نقطة أمان | ✅ `18f47eb` + 4 ملفات احتياطية خارج المستودع |
| عدم استخدام `git checkout HEAD -- file` بعد النقطة | ⚠️ **مخالفة واحدة مُعلنة** قبل استلام النص (أُعيد تصحيحها: `d2e4085`) |

**خط الأساس للاختبارات:** `91 passed / 2 failed / 5 skipped (317 assertions)`
**بعد آخر commit:** `91 passed / 2 failed / 5 skipped (317 assertions)` — لم تتغيّر النتيجة إطلاقاً.
الفشلان مسجَّلان قبل أي تعديل: `Tests\Feature\Auth\RegistrationTest` و
`Tests\Feature\LivingMotherRegistrationTest` (انظر `REMAINING_RISKS.md` R2).

---

## 2. البنود ← commits

| البند | المحتوى | Commit |
|---|---|---|
| 1 | تحليل الكود + `git diff` لكل commit في السلسلة | هذا التقرير |
| 2 | نقطة أمان + احتياطات (بدون فقدان أي تغيير) | `18f47eb` |
| 3 | **الملكية قبل الحفظ**: helper `authorizeSponsorshipForUpdate()` — `sponsorship_id` غير صحيح/غير رقمي/غير موجود في جلسة المستخدم أو غير مطابق لـ `identity_number`/`internal_file_number` → `403` + `Log` (`SPONSORSHIP_UPDATE_BAD_REQUEST` / `SPONSORSHIP_UPDATE_FORBIDDEN`)؛ إعادة رمي `HttpExceptionInterface` من الكاتش الخارجي | `cf2bb27` |
| 4 | **`data_section_id`** عند غيابها/null/'' داخل `insertOrUpdateDataRecord()` بالقيمة نفسها التي يستخدمها `Admin\SponsorshipController` والمسارات العامة = `1` (لا اختراع، لا ثابت جديد، لا migration) | `fb8ed46` |
| 5 | الإصلاحات التي كانت مفقودة من `5edce4d` واستُعيدت بهونك بونك (`$neverClearKeys`, `$fieldOrClear`, إزالة `$excludedFromPortalFields`, توحيد مفتاح البوابة, `FAMILY_MEMBER_SKIPPED_*`, `field_health_status` لكل `person_type`, `$sponsorshipFields`, `portal_fields_stored`, `catch (\Throwable)`) | `d2e4085` |
| 6 | **حماية القيم الفارغة**: `dropUnsafeDirtyAttributes()` قبل حفظ `sponsorships` و`data` — `''` داخل عمود رقمي/تاريخي → `NULL` فقط إن كان العمود يقبل null وإلا إسقاط القيمة (يبقى القديم)، `NULL` داخل عمود `NOT NULL` → إسقاط، `''` داخل نص → كما هي. البيانات من `Schema::getColumns()` مع كاش ثابت وسِلوك احتياطي عند تعذّر القراءة | `fb8ed46` |
| 7 | **المدخلات تبقى بعد الخطأ**: دمج `old('fields')` فوق `extractFieldValues()` في `index()` (يغطي الحقول الـ30 التي لا تستدعي `old()` في القالب)، مع حماية المفاتيح الداخلية `_*`؛ + إظهار رسالتَي «لم يتم العثور على بيانات الكفالة» و«ملف مغلق» عبر `session()->now('error')` لأن القالب لا يقرأ متغيّر `$error` | `7f38170` |
| 8 | **المرفقات المحفوظة سابقاً تكفي**: وثيقة مطلوبة تُعتبر مغطّاة بملف جديد **أو** بملف موجود مسبقاً لنفس الشخص ونفس نوع الوثيقة (نفس استعلام `index()`) | `06a541a` + `8c6d41d` |
| 9 | — | انظر `REMAINING_RISKS.md` R7 |
| 10 | **لا JSON خام**: النموذج يُرسل `form.submit()` (صفحة عادية) ← رفض المرفقات الناقصة صار `redirect()->back()->withInput()->with('error')`؛ يبقى JSON `422` فقط عند `$request->expectsJson()` | `85eaec6` |
| 11 | **رسائل عربية ودّية**: `friendlyErrorMessage()` — أخطاء Query/PDO/`SQLSTATE`/`SQL[` → رسالة فشل حفظ بلا SQL وبلا bindings، `ModelNotFoundException` → رسالة بحث، رسائلنا العربية تظهر كما هي، غير ذلك → رسالة عامة. التفاصيل التقنية تبقى في `Log::error(UPDATE_SPONSORSHIP_ERROR)` | `85eaec6` |
| 12 | **أمان المعاملة**: `DB::rollBack()` مشروط بـ `DB::transactionLevel() > 0` (كان قد يُستدعى قبل `beginTransaction()`) | `85eaec6` |
| 13 | **`fields_count === 0` ليس خطأً عالمياً**: التحقق من الملكية نُقل قبل الفحص المبكر، ثم يُرمي الاستثناء فقط إن كانت الجمعية تدير حقلاً واحداً على الأقل (`sponsorHasEnabledFields()` — يحاكي ما يعرضه `index()` فعلاً: بادئة `field_`، بلا `*_section`، وبلا الحقول القديمة غير المعروضة)، وإلا تنبيه `UPDATE_SPONSORSHIP_NO_FIELDS_RECEIVED_SPONSOR_HAS_NONE` والمتابعة للحفظ | `91b6709` |
| 14 | — | انظر `REMAINING_RISKS.md` R7 |
| 15 | — | انظر `REMAINING_RISKS.md` R7 |
| 16 | — | انظر `REMAINING_RISKS.md` R7 |
| 17 | الأصفار البادئة | **تقرير فقط** — لم يُنفَّذ أي تغيير (`REMAINING_RISKS.md` R6) |
| 18 | استرجاع ما كان مفقوداً من `5edce4d` | `d2e4085` |
| 19 | **رسالة النجاح في مكانها**: `thank-you.blade.php` كان لا يعرض `session('success')` إطلاقاً — أصبح يعرضه في صندوق تأكيد تحت العنوان (نفس النص ونفس المفتاح ونفس وجهة التوجيه) | `24e8b17` |
| 20 | **رسالة الخطأ في مكانها**: التوجيه الفاشل يستخدم `with('error', …)` + `withInput()` ويعرضه Toaster القالب؛ `ValidationException` تتحول إلى رسالة عربية تذكر الحقول بدل النص الإنجليزي الخام | `85eaec6` |
| 21 | مراجعة أمنية أوسع | **تقرير فقط** (`REMAINING_RISKS.md` R6) |
| 25 | commits صغيرة + فحص بعد كل واحدة | ✅ جدول 3 أدناه |
| 22, 23, 24, 26 | مُتجاوزة بقرار المستخدم | — |
| 27 | هذا التقرير + `REMAINING_RISKS.md` | `a1bf658` |
| 28 | **اسم المتوفى وأفراد الأسرة لا يُحفظان** (تحقيق بعد بند 27، هوية `445570054`): السببان موثّقان أدناه في قسم 7 | `7c78ac8` |
| 30 | **منع الحفظ حتى تعبئة جميع الحقول الظاهرة** (طلب المستخدم): فحص `validateVisibleFieldsBeforeSubmit()` قبل فحص المرفقات | `d463e93` |
| 31 | **لا تكرار الفرد**: إن وُجد (نفس الملف + نفس الهوية) ⇒ تحديث بياناته فقط، وإلا ⇒ إضافة — في حلقة أفراد الأسرة و`insertOrUpdateRePeopleRecord()` ومساري `dead_people` المركزي/الـlegacy (`upsertDeadPeopleForFile()`). تفاصيل التحقيق والتحقق وتنظيف البيانات في قسم 7.6 | `08ce370` |

---

## 3. سجل الـ commits والفحص بعد كل واحدة

| # | Commit | `php -l` | الاختبارات |
|---|---|---|---|
| 1 | `18f47eb` نقطة أمان | ✅ | 91/2/5 (خط الأساس) |
| 2 | `d2e4085` استرجاع `5edce4d` | ✅ الملف مطابق 100% | — |
| 3 | `cf2bb27` البند 3 | ✅ | 91/2/5 |
| 4 | `fb8ed46` البنود 4+6 | ✅ | 91/2/5 |
| 5 | `7f38170` البند 7 | ✅ | — |
| 6 | `06a541a` + `8c6d41d` البند 8 | ✅ | — |
| 7 | `85eaec6` البنود 10+11+12+20 | ✅ | — |
| 8 | `91b6709` البند 13 | ✅ | — |
| 9 | `24e8b17` البند 19 | ✅ `view:clear` | **91/2/5** ✅ (بعد `optimize:clear`) |
| 10 | `7c78ac8` البند 28 | ✅ `php -l` | **91/2/5** ✅ |
| 11 | `d463e93` البند 30 | ✅ `node --check` + `view:cache` | **91/2/5** ✅ |
| 12 | `08ce370` البند 31 | ✅ `php -l` + 3 اختبارات وظيفية داخل معاملة تُلغى | **91/2/5** ✅ |

> **ملاحظة صريحة:** حزمة الاختبارات الكاملة أُجريت عند خط الأساس، وبعد `cf2bb27`،
> وبعد `fb8ed46`، وبعد `24e8b17`، وبعد `7c78ac8`، وبعد `d463e93`، وبعد آخر commit
> (`08ce370`) — والنتيجة متطابقة في كل مرة (`91 passed / 2 failed / 5 skipped`).
> الفحص البيني لبقية الـ commits اقتصر على `php -l` وفحوصات الوظائف أدناه.
> لأن النتيجة النهائية = خط الأساس تماماً، لا يوجد انحسار مُخفي بين commits.

### فحوصات وظيفية أُجريت (بلا تغيير في البيانات)

1. **البند 6** — نموذج `Data` في الذاكرة (لا `save()`): `NULL`/`''` داخل أعمدة `NOT NULL`
   أُسقِطت، وعمود `nullable` استقبل `NULL` كما هو وحوّل `''` → `NULL` ✅
2. **البند 7** — حقن `_old_input.fields` في الجلسة واستدعاء `mergeOldInputFields()`
   عبر Reflection: القيم المكتوبة ظهرت، المفتاح الداخلي `_stored_person_type` بقي كما هو،
   وبدون `old()` تُرجَع القيم المستخرَجة بلا تغيير ✅
3. **البند 13** — `sponsorHasEnabledFields()` على كفالات حقيقية (sponsor_id=1 وأخرى):
   `true` في كل الحالات هنا لأن الجمعية تدير حقلاً واحداً فأكثر ✅
4. **البند 4** — تجربة إدراج داخل معاملة **تُلغى** (لا أثر): قبل استبدال القاعدة فشلت بـ
   `1364 data_relationship` (موثّق في `REMAINING_RISKS.md` R1)، وبعد استبدال `aso`
   بـ `aso.sql` **نجحت** (`INSERT OK id=13734` ثم `rollBack`) على المخطط الجديد ✅

---

## 6. استبدال قاعدة البيانات (طلب المستخدم — بعد التقرير الأصلي)

| البند | التفصيل |
|---|---|
| المطلوب | حذف `aso` واستيراد `C:\Users\mfarr\Downloads\aso.sql` بدلها |
| نسخة الأمان | `…\Temp\opencode\aso-backup\aso-before-replace.sql` (1.8 MB — 79 جدولاً) قبل أي حذف |
| الملف | 572,975,843 بايت (546 MB)، phpMyAdmin 5.2.2 من MariaDB 11.4.7 |
| التنفيذ | `DROP DATABASE aso` ثم `mysql -uroot --default-character-set=utf8mb4 --force < aso.sql` |
| النتيجة | **86 جدولاً**، utf8mb4/utf8mb4_unicode_ci، `data`=11319، `sponsorships`=3093، `re_people`=28822، `attachments`=80108، `users`=6912 |
| الأخطاء | **خطأ واحد فقط**: `ERROR 1452` على `ALTER TABLE telescope_entries_tags ADD CONSTRAINT` (الملف نفسه صدّر 8.3 مليون وسم مقابل 108 من `telescope_entries`) — أثره = غياب قيد FK واحد في أداة تتبّع التطوير فقط |
| تأثيره على R1 | **حُلّ**: `data` لم يعد يحوي سوى `id` بلا default، وكل الأعمدة الـ24 السابقة صارت nullable/bdefault، وتجربة الإدراج التشخيصية نجحت |
| الاختبارات | ما تزال `91 passed / 2 failed / 5 skipped` لأنها تعمل على `aso_testing` (لم تُطلب إعادة تعبئتها) |
| `aso_testing` | بقيت كما هي — إن أردت تشغيلها على البيانات الجديدة استورد `aso.sql` فيها |

---

## 4. فحص يدوي مقترح (بعد نشر التغييرات)

1. الدخول بحساب **لا تملك** كفالة → تظهر رسالة عربية «لم يتم العثور على بيانات الكفالة» (لا صفحة صامتة).
2. كفالة بحالة ≠ «جارية» → «تم تحديث البيانات مسبقاً وإغلاق الملف».
3. إدخال قيم ثم استعمال تحقّق JS معيل ثم إعادة التوجيه → **القيم نفسها ظاهرة** (بما فيها الحقول بلا `old()`).
4. وثيقة كانت مرفوعة سابقاً دون رفع جديد → الحفظ يمرّ (لا رسالة نقص).
5. رفع ملف > 50MB أو فاسد → رسالة عربية ودّية، **لا JSON**، والمدخلات محفوظة.
6. إرسال `sponsorship_id` غير مملوك → `403`.
7. حفظ ناجح → صفحة الشكر تعرض «تم حفظ التغييرات بنجاح».
8. (إن كان ملفك «معيل» وتُرسَل بلا `fields[]` على الإطلاق) → تنبيه في اللوج بدل رفض صارم.
9. **اسم المتوفى** بعد الحفظ: `father_first_name` … `mother_last_name` ممتلئة في `dead_people`
   (وليس `NULL`)، واللوج يحوي `DEAD_PEOPLE_NAMES_FROM_NAMES_INPUT`.
10. **أفراد الأسرة**: اللوج يحوي `FAMILY_MEMBERS_SUBMIT` مع `rows_received > 0` عند وجود صفوف
    في الصفحة، ويظهر السجل بعد إعادة التحميل.
11. **تحقّق الحقول**: اترك حقل مرئياً فارغاً واضغط حفظ → يظهر `Swal` بقائمة الحقول الناقصة،
    الحقول بإطار أحمر، ولا يُرسَل الطلب. أضف `data-optional="1"` لأي حقل اختياري يجب عدم إلزامه.
12. **عدم التكرار**: أضف صفاً جديداً في أفراد الأسرة بهوية فرد موجود مسبقاً في نفس الملف ثم احفظ
    → السجل واحد فقط (لا صف ثانٍ)، ويظهر لوج `FAMILY_MEMBER_UPDATED_EXISTING`، والقيمة الجديدة
    مستبدَلة في الصف القديم.

---

### 7.6 البند 31 — «لا تكرار الفرد» (`08ce370`)

**الشكوى:** الهوية `445570054` تظهر مكرّرة. التحقيق في `laravel.log` و`aso`:

| الدليل | الخلاصة |
|---|---|
| `05:37:25` `CREATED_RECORDS_FOR_FAMILY_MEMBER re_people_id=35868` (ملف `025795`) | الصف الأصلي أُنشئ مع الإنشاء المركزي |
| `07:41:31` `FAMILY_MEMBER_ADDED person_id=445570054` → `35875` | **سبب التكرار المباشر**: صف أُضيف من الفورم بـ `is_new=1` بهوية موجودة، والمسار كان `insert` أعمى |
| `07:41:30` `GUARDIAN_FILE_RELINK_APPLIED from 025796 → 025795 (re_people:1, dead_people:1)` | مسار ثانٍ: توليد ملف جديد ثم إعادة ربطه يجمع ملفين في مفتاح واحد |
| `insertOrUpdateRePeopleRecord()` كان مشروطاً بـ `$reusedFileNumber` | المسار المركزي يُنشئ نسخة ثانية عند توليد ملف جديد |
| فجوة معرّفات `35869`-`35873` غير موجودة في الجدول | **ليست حذفاً**: ثغرة `*_name_name` (07:02-07:08) كانت تُ rolled back داخل المعاملة |

**القاعدة المطبَّقة** (في كل نقاط إنشاء الأشخاص):

1. حلقة أفراد الأسرة: بحث `registration_id = الملف` و`person_id = الهوية` **قبل** القرار؛
   إن وُجد ← تحديث الحقول المُرسلة فقط + لوج `FAMILY_MEMBER_UPDATED_EXISTING`، وإلا ← المسار القديم.
2. `insertOrUpdateRePeopleRecord()`: حُذِّد شرط `$reusedFileNumber` من الفحص (بقي في التوقيع لاستدعاءات).
3. helper جديد `upsertDeadPeopleForFile($record, $fallbackRelationFile)`: يبحث عن سجل لنفس الملف،
   ثم لملف الكفالة المرتبط؛ إن وجد ← تحديث (يبقى `re_file_id` كما هو)، وإلا ← `insertGetId`.
   طُبِّق على المسار المركزي للمتوفى ومسار legacy (وكذلك `re_people` في legacy عبر المسار 1).

**التحقق (كله داخل `BEGIN … ROLLBACK`، بلا أثر على البيانات):**

```
TEST1 re_people: lookup_id=35868 | rows 2→2 | gender NULL→1  => OK (updated, no duplicate)
TEST2 dead_people: rows(file) 2→2 | rows(new file) 0→0 | kept_re_file_id=025795 => OK
TEST3 person جديد (ملف 099999): rows=1 => OK (inserted)
VERIFY after rollback: لا أي تغيير في البيانات
```

**تنظيف البيانات (طلب المستخدم — حالة واحدة فقط):**

| البند | التفصيل |
|---|---|
| النسخة الاحتياطية | `…\Temp\opencode\alhayah-backup\dup-445570054-20261005.sql` (الصفوف الأربعة كما هي) |
| الدمج | `re_people 35875` ← `35868` (كانتا للملف `025795` والهوية `445570054`)، نُقل `person_gender=1` و`person_note` لأن `35868` كانت `NULL` |
| الحذف | `re_people 35875` + `dead_people 30844` (كانت فارغة تماماً) — لا يوجد أي FK يشير إلى الجدولين |
| النتيجة | `025795` = فردان فقط (`445570054`, `444072730`) وصف `dead_people` واحد (`30843`) |
| العدّادات | `re_people` أزواج مكرّرة 22 → **21**؛ `dead_people` ملفات متعددة الصفوف 8900 → **8899** |
| ما لم يُنظَّف (قرار) | `data 13737` (ملف `025796` فارغ)، وبقية التكرارات التاريخية → `REMAINING_RISKS.md` R12 |

---

## 5. ما لم يُنفَّذ عمداً

- أي تغيير schema / migration / `ALTER TABLE`.
- أي قيمة افتراضية وهمية داخل `data` — **لم تعد مطلوبة** بعد استبدال القاعدة
  (المشكلة الأصلية حُلّت بالمخطط المستورد، انظر `REMAINING_RISKS.md` R1 وفقرة 6 أعلاه).
- الأصفار البادئة (17) والمراجعة الأمنية الشاملة (21) → تقرير فقط.
- بنود الترقيم غير المذكورة في سجل العمل (9, 14, 15, 16, 22, 23, 24, 26) → R7.
- **تحقّق السيرفر لحالة ملء الحقول** (بند 30) → بقرار المستخدم: **من جهة المتصفح فقط**،
  دون قواعد `$request->validate` جديدة (انظر `REMAINING_RISKS.md` R10).

---

## 7. جولة لاحقة (بعد التقرير الأصلي): اسم المتوفى وأفراد الأسرة + تحقّق الحقول

### 7.1 التحقيق — هوية `445570054`

| الملاحظة | الدليل من `storage/logs/laravel.log` |
|---|---|
| حُفظ على كفالة أخرى | كفيلتان بنفس الهوية: `3472` و`3769`؛ الاتجاه كان إلى **3769** (ملف `025795`) |
| اسم المتوفى يبقى `NULL` | `DEAD_PEOPLE_CREATED … created_fields: [father_id, father_death_date]` — بلا أي `*_name` |
| أفراد الأسرة لا يصلون أصلاً | لا يوجد أي لوج `FAMILY_MEMBER_*` إطلاقاً |

### 7.2 السبب الأول — اسم المتوفى (`7c78ac8`)

- الفورم يرسل الأسماء في **`names[father][first_name…]`** وليس `fields[field_father_first_name]`.
- `updateSeparateNameFields()` (يقرأ `names[]`) يُستدعى **فقط** داخل فرع `else` (السطر 1591-1596)،
  وقد تُخطَّى لأن `$needsCentralDataCreation = true`.
- فرع `family_member` في `createCentralDataRecords()` ينشئ `data` + `re_people` فقط.
- الكتلة العامة لـ `dead_people` كانت تقرأ `$deadPeopleFields` من `fields[]` فقط ← كل `father_*_name` = `NULL`.

**الإصلاح:** شرط الإنشاء صار `if (!empty($deadPeopleFields) || $hasDeadParentNames)`، وطُبِّقت حلقة
تكتب `names[father]`/`names[mother]` بعد `mother_death_reason` مع لوج `DEAD_PEOPLE_NAMES_FROM_NAMES_INPUT`.

> ⚠️ أثناء التنفيذ بُني اسم عمود خاطئ `father_first_name_name` → `SQLSTATE[42S22]` → ارتداد المعاملة
> ورسالة عامة للمستخدم؛ صُحِّح إلى `$deadNamePrefix . '_' . $deadNamePart` **قبل** الالتزام بالcommit
> (تجربة SQL داخل معاملة تُلغى + `php -l`).

### 7.3 السبب الثاني — أفراد الأسرة (`7c78ac8`)

`family_members` **لم تكن موجودة في الطلب أصلاً** (`FAMILY_MEMBERS_SUBMIT {"in_request":false,"rows_received":0}`):
`index()` يضبط `$familyMembers = collect()` عند غياب `relationData` فلا تُرسم صفوف من الخادم، وصفوف JS
تُبنى حصراً بزر `addFamilyMember()`، ولا restore عبر `old()` بعد إعادة التوجيه.

**الإصلاح (يستقبل الصفوف إن وصلت):** لوج غير مشروط `FAMILY_MEMBERS_SUBMIT`، شرط
`is_array(...) && !empty(...)`، و`$isNewFamilyMember = is_new || empty($memberData['id'])` بدل السقوط
الصامت، مع `FAMILY_MEMBER_SKIPPED_INVALID_ROW`.
**ما لم يُعالَج:** لماذا لا تُرسم الصفوف من الخادم أصلاً → `REMAINING_RISKS.md` R11.

### 7.4 التأكيد بعد الإصلاح (07:14:38 — sponsorship `3768`، هوية `444072730`، ملف `025796`)

- `DEAD_PEOPLE_NAMES_FROM_NAMES_INPUT` بالأعمدة الصحيحة، ثم
  `DEAD_PEOPLE_CREATED … created_fields` يحوي `father_first_name … mother_last_name` ✅
- `FAMILY_MEMBERS_SUBMIT {"in_request":false,"rows_received":0}` — مؤكِّد لسبب 7.3 ✅
- الحفظ نجح (لا `UPDATE_SPONSORSHIP_ERROR`) ✅

### 7.5 البند 30 — تحقّق «جميع الحقول الظاهرة» (`d463e93`)

- `validateVisibleFieldsBeforeSubmit(form)` تُستدعى فور `e.preventDefault()` **قبل** فحص المرفقات.
- تتجاوز: المخفي (`isElementVisible()` الموجودة أصلاً)، `readonly`، `disabled`،
  `type = hidden|file|checkbox|radio|button`، وأي حقل عليه `data-optional="1"`.
- عند النقص: إطار أحمر + قائمة مسمّاة في `Swal` (حتى 15 ثم «… وN حقلاً آخر») + تمرير وتركيز على أول حقل،
  ويزول الأحمر تلقائياً عند الكتابة.
- فُحص بـ `node --check` على الكتلة المضافة + `view:cache` على كامل القوالب + اختبارات 91/2/5.
