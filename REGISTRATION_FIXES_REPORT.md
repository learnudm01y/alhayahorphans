# تقرير إصلاحات «التسجيل العام» — المهمة الهندسية

**الفرع:** `adndoid-v2-edited`
**النقطة الآمنة:** `18f47eb chore: checkpoint before safe registration fixes`
**المدى:** `18f47eb` → `24e8b17` (9 commits)
**الملفات المتغيّرة:** `app/Http/Controllers/Users/ShowGeneralRegisrationController.php` + `resources/views/user/generalRegistration/thank-you.blade.php`
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

> **ملاحظة صريحة:** حزمة الاختبارات الكاملة أُجريت عند خط الأساس، وبعد `cf2bb27`،
> وبعد `fb8ed46`، وبعد آخر commit (`24e8b17`) — والنتيجة متطابقة في الأربعة.
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
4. **البند 4** — تجربة إدراج داخل معاملة **تُلغى** (لا أثر) أثبتت أن `data_section_id`
   وحدها لا تكفي → موثّق في `REMAINING_RISKS.md` R1

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

---

## 5. ما لم يُنفَّذ عمداً

- أي تغيير schema / migration / `ALTER TABLE`.
- أي قيمة افتراضية وهمية داخل `data` (المطلوب قرار في `REMAINING_RISKS.md` R1).
- الأصفار البادئة (17) والمراجعة الأمنية الشاملة (21) → تقرير فقط.
- بنود الترقيم غير المذكورة في سجل العمل (9, 14, 15, 16, 22, 23, 24, 26) → R7.
