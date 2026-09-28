# تقرير فحص واجهة التسجيل العام (General Registration) — قراءة فقط

- **التاريخ:** 2026-09-27
- **النطاق:** ملفات التسجيل العام في `resources/views/user/generalRegistration/` + `GeneralRegistrationController::store()`
- **طبيعة العمل:** فحص وإبلاغ فقط — **لم يُعدَّل أي ملف** (لم تُنشأ أي مراجعة كود في هذه الجولة).
- **حالة الاختبار:** MySQL متوقف (`SQLSTATE[HY000] [2002]`) ⇒ **لا اختبار قاعدة بيانات أو يدوي تم**.

---

## 1. الملفات الخمسة وطريقة العمل (مسار البيانات)

### 1.1 `component/familyMemberFields.blade.php` — القالب فقط
يُستنسخ لكل فرد عبر `addFamilyMember()`.

- الأسماء: `family_members[{{ $idx }}][...]` (أسرة/مهنة/صلاقة/صحيح ومُبلغ عنه…).
- تعريفات الهوية والرفع:
  - `data-member-index="{{ $idx }}"` (L14)
  - `pattern="[0-9]{9,10}"` + `minlength=9` + `maxlength=10` لـ`person_id` (L14)
  - `data-upload-zone="family_{{ $idx }}"` (L96) و`#mainDocumentTypeSelect_{{ $idx }}"` (L97)
- حقول مخفية تُرسل دوماً: `file_id` (L2) و`registration_id` (L7).
- **لا تحتوي:** `sponsorship_status` ولا `person_type_of_guarantee`.
- **القالب فقط** يحمل `d-none` (`familyMember.blade.php` L11).

### 1.2 `component/familyMember.blade.php` — مصدر الحقيقة للصفوف
- `window.activeFamilyMemberIndexes = new Set()` (L32)
- `window.getActiveFamilyMemberForms()` (L35) → عناصر `.family-member-form` الموجودة في DOM **والتي يوجد فهرسها في الـSet**.
- `window.findInvalidFamilyMemberField()` (L61) → تُستخدم من بوابة "التالي" (L124) ومن بوابة التنقل (L168).
- **الإضافة:** `clone.classList.remove('d-none')` (L540) ← `appendChild` (L687) ← `Set.add(newIndex)` (L689) ← `reindexFamilyMembers()` (L692). كلها متزامنة بلا `setTimeout`.
- **الحذف** (داخل تأثير الـ300ms): قراءة الفهرس ← `clone.remove()` ← `Set.delete` (L666-671) ← إعادة بناء الـSet (L802).
- **إعادة الفهرسة:** `reindexFamilyMembers` (L725-812) تُحدِّث `name` و`id` و`data-member-index` و`data-upload-zone` و`data-person-id`.
- `d-none` لم تعد مصدراً للحقيقة داخل هذا الملف (صفر استخدام لـ`:not(.d-none)`).

### 1.3 `javascript/manageForm.blade.php` — الإرسال
`main_form` `submit` (L1075) ← `new FormData(main_form)` ← حذف كل مفاتيح `family_members*` (L1089-1093) ← إعادة الجمع من `getActiveFamilyMemberForms()` (L1096) ← `bank_accounts` من `.bank-account-form` (L1107) ← `additional_deceased` من `.additional-deceased-form` (L1126) ← المرفقات من `window.allDocs` (المفتاح `family_{idx}`) ← `fetch` ← عرض أخطاء 422 من `data.errors` / `data.message` (L1286-1303).

### 1.4 `javascript.blade.php` — المجمِّع + بوابات التحقق
```
1  @include taps
2  @include manageDaedTap
4  @include documentUpload
5  @include cropper
6  @include ageCalculating
7  @include errorTracker
8  @include autoComplete
9  @include showInsertedData
10 @include manageForm
```
ثم `checkDuplicateIdsInForm()` (L214) و`validateAllIds()` (L313). **لا يعتمد على `d-none` إطلاقاً** — يعتمد على `el.offsetParent !== null` (L222).

### 1.5 `GeneralRegistrationController::store()`
1. `validate` — `'family_members' => 'required|array|min:1'` (L207) برسائل عربية.
2. فلترة البطاقات الفارغة **مع الحفاظ على المفاتيح** (L140-155) ثم `$request->merge`.
3. تحقق **قبل** المعاملة (L290-355): 9 أرقام `/^\d{9}$/` لكل `person_id`، ارتباط بمعيل موجود، منع تكرار داخل الطلب.
4. `beginTransaction` بعد التحقق فقط.
5. `Log::info('🔎 حالة family_members عند الحفظ')` (L677).
6. حفظ `re_people` (L683-716) ثم المرفقات ثم المستخدم.
7. `DB::commit` ثم `Log::info('📋 ملخص التسجيل العام')`.

---

## 2. مسار الإرسال الكامل (من الحقل حتى `commit`)

```
[الواجهة]
  إدخال/اختيار حقل
    → ageCalculating (input موثّق على document)
    → errorTracker (input/change على document) + patchAllDocsProcessedFile + updateTabStates
    → validateIdAndToggleSelect (يرفع/يعطّل اختيار الوثيقة حسب 9 أرقام)
  إضافة فرد  → addFamilyMember() → Set.add → reindexFamilyMembers
  حذف فرد   → clone.remove + Set.delete → reindexFamilyMembers
  "التالي"  → findInvalidFamilyMemberField() → تبويب المراجعة (يُفعَّل من showInsertedData:57-74)
  زر "حفظ السجل نهائياً"
    → showInsertedData:436: فحص وجود data_id_number + 9 أرقام
    → validateAllIds() → checkDuplicateIdsInForm()   ← (يعتمد offsetParent، انظر تعارض #1)
    → main_form.requestSubmit()
        → manageForm:1075: بناء FormData ديناميكياً + allDocs → fetch POST
        → errorTracker:337 (submit) → إضافة documents[…] hidden inputs (لا تُستخدم في الفعل)
        → manageDaedTap:263 (submit) → preventDefault عند نقص بيانات الأب  ← (تعارض #9)
[الخادم]
  validate → فلترة → تحقق مسبق → beginTransaction → re_people → مرفقات → مستخدم → commit → Log
[الاستجابة]
  422 → manageForm:1286-1303 يعرض data.errors/data.message
  نجاح → إعادة توجيه
```

---

## 3. قائمة التعارضات (12)

### عالية الخطورة

| # | التعارض | الموضع | الأثر |
|---|---------|--------|-------|
| **1** | **فحص تكرار الهوية يعمل داخل التبويب النشط فقط** — `filter(el => el.offsetParent !== null)`، وكل الأبواب `tab-pane` تصبح `display:none`Inactive | `javascript.blade.php:222`، مستدعى من `showInsertedData:500` | (أ) عند **الحفظ النهائي من تبويب المراجعة** تكون كل حقول الهوية مخفية ⇒ `idInputs=[]` ⇒ `validateAllIds()` ترجع `true` دائماً ⇒ **الفحص لا يفعل شيئاً**؛ (ب) على تبويب الأسرة يرى هوية الأفراد ولا يرى `data_id_number` ⇒ **تكرار المعيل مع فرد الأسرة لا يُلتقط**؛ (ج) الباكند لا يغطيه لتسجيل جديد (يبحث في `Data`/`RePeople` غير موجودَين) ⇒ **يمر**. ملاحظة: قيد المشروع يمنع تعديل منطق التحقق من التكرار — التقرير فقط. |
| **2** | **ترتيب تنفيذ `@push` مقلوب عن ترتيب الملف** — بنية `@push(1)` L1، `@push(2)` L379، `@endpush` L654، `@push(3)` L656، `@endpush` L868، `@endpush` L1513 | `manageForm.blade.php:1,379,654,656,868,1513` | تجربة فعلية على Blade نفسه: المخرج `[محتوى2] ثم [محتوى3] ثم [محتوى1]` — أي أن **معالج الإرسال الرئيسي (L1064+) يُسجَّل آخر**. لا كسر مباشر (كلها `DOMContentLoaded`)، لكن أي افتراض بترتيب الملف خاطئ. |
| **3** | **تكرار حرفي مؤكد** — السطور L443-653 وL657-867 **متطابقة حرفاً بحرف (210 سطراً، `Compare-Object` = لا فروق)** | `manageForm.blade.php:443-653` vs `657-867` | `#goToReviewTabBtn` ← 3 مستمعات (1 في `familyMember` + 2 هنا)؛ `#goToFamilyTabBtn` ← 2؛ `Swal.fire` يُستدعى أكثر من مرة. |

### متوسطة

| # | التعارض | الموضع | الأثر |
|---|---------|--------|-------|
| **4** | **مصدرا حقيقة مختلطان**: `activeFamilyMemberIndexes` مقابل `:not(.d-none)` | `create.blade.php:115`، `errorTracker:559/843/1067`، `showInsertedData:58/170` | اليوم متطابقان (الصف لا يحمل `d-none` بعد دخوله DOM) لكنهما **سيتفرقان فوراً** لو أُخفي صف بـ`d-none`. |
| **5** | **حقول يقرأها الباكند ولا توجد في القالب** | `GeneralRegistrationController.php:688,698` مقابل `familyMemberFields.blade.php` | `sponsorship_status` و`person_type_of_guarantee` تُحفظ دائماً `NULL`. (العكس آمن: `file_id`/`registration_id` تُرسل ويتجاهلها الباكند ويستخدم `$fileIdNumber`.) |
| **6** | **صيغة `person_id` ثلاثية التعارض**: القالب `9-10` ↔ الباكند `9` ↔ بوابة رفع الوثيقة `9` | `familyMemberFields:14`، `GeneralRegistrationController:298` | حقل يقبل 10 أرقام ⇒ في النهاية خطأ 422، و`validateIdAndToggleSelect` يعطّل قائمة الوثائق (يحجب البوابة). |
| **7** | **فلترة البطاقة الفارغة قد تكسر مطابقة `family_{idx}`** | `GeneralRegistrationController:140-155` + L801 | المفاتيح محفوظة ✓ لكن مرفق بطاقة مُستبعدة يُرفض بصمت (`تجاهل مرفق بسبب شرط تحقق نهائي`). محجوب عملياً لأن رفع الوثيقة يتطلب هوية 9 أرقام. |
| **8** | **مستمعا `invalid` مكرران بسلوكين مختلفين**: `errorTracker` يستدعي `e.preventDefault()` (يكتم النافذة الأصلية) و`manageForm` ينقل للتبويب ويُركِّز | `errorTracker:673-683`، `manageForm:873-892` | كلاهما ينفَّذ (لا `stopImmediatePropagation`) ⇒ منطق مكرر ومتضارب جزئياً. |

### منخفضة / كود ميت

| # | التعارض | الموضع | الأثر |
|---|---------|--------|-------|
| **9** | **`preventDefault` لا يوقف الإرسال** — `manageDaedTap:263` يمنع الافتراضي عند نقص بيانات الأب، لكن `manageForm:1075` مستمع منفصل على نفس الحدث | `manageDaedTap:263` + `manageForm:1075` | قد يُرسَل الطلب رغم رسالة الخطأ (يحتاج تأكيد تشغيلي). |
| **10** | **الوظيفة الوحيدة `setupDocumentUploadHandlersForMember` فارغة `{}`** ويُستدعى من 4 مواقع بحارس `typeof === 'function'` | `familyMember:857` مقابل `familyMember:695,833` و`manageForm:404,432` | يمرّ ولا يفعل شيئاً. التوجيه الفعلي عبر `initUploadZone` + `MutationObserver` (`documentUpload:1213,1451,1478`) ⇒ كود ميت مضلِّل لا كسر. |
| **11** | **ملفان ميتان لا يُحمَّلان**: `formSubmission.blade.php` و`allDocsSyncManager.blade.php` — لا يوجد `@include` لهما في أي ملف عرض (بحث كامل في `resources/views`) | — | منطقهما لا يعمل نهائياً. |
| **12** | **`submit` مسجَّل على `#review` وهو `div` وليس `form`** + **`DOMContentLoaded` مسجَّل مرتين** في الملف نفسه | `showInsertedData:422-424`، `4` و`427` | المستمع الأول ميت؛ الثاني يُستنسخ `#finalSaveBtn` بـ`cloneNode` (L433) ثم يُسجِّل الزر (L436) — يعمل بالترتيب لكنه هش. |

### ملاحظات خارج الملفات لكنها تُحمَّل عبر `javascript.blade.php`
- **`manageDaedTap:44`** يمسح `.family-member-form` بالترتيب **مع القالب** ثم يقرأ `family_members[${index}]` ⇒ **إزاحة بـ1** ⇒ تعبئة بيانات الأب لا تعمل/أو تستهدف صف خاطئ.
- **`errorTracker:337-361`** يضيف hidden inputs `documents[…]` داخل النموذج في كل محاولة إرسال (تراكم) بينما `manageForm` يتجاهلها ويجمع من `window.allDocs`.
- **`manageForm:62`** يستخدم `#mainDocumentTypeSelect` (بلا لاحقة) بينما المعرفات فعلياً `..._main/_father/_mother/_0` ⇒ لا يطابق شيئاً (كود ميت).

---

## 4. أمور تحققت من اتساقها (ليست تعارضات)

- بوابة **"فرد واحد على الأقل"** ثلاثية متسقة: `showInsertedData:57-74,102-114` (تعطيل تبويب المراجعة) ↔ قاعدة `required|array|min:1` ↔ سجل الحالة `:677`.
- `bank_accounts` و`additional_deceased` لا يعتمدان على `d-none` (`:1107` / `:1126`).
- المفاتيح بعد الفلترة محفوظة ⇒ `family_{1}` ما زال يشير للفرد الصحيح.
- أخطاء 422 تظهر للمستخدم (`manageForm:1286-1303`).
- `checkDuplicateIdsInForm` لا يعتمد على `d-none` إطلاقاً.
- `#saveToGoogleBtn` (`manageForm:919-975`) هو حفظ كلمات مرور Chrome — تابع مستقل وليس مسار حفظ السجل.
- لا مستمعات إرسال إضافية غير `mainForm:1075` و`form:1035` (هذا الأخير يعدّل المدخلات فقط) — مؤكَّد بفهرسة كل `addEventListener` في الملف.

---

## 5. أولويات الإصلاح (عند الحصول على الإذن)

1. فلترة `offsetParent` في `checkDuplicateIdsInForm` — **ممنوع حالي التعديل** (تعارض #1).
2. تكرار بلوك `@push` وترتيبه (تعارضان #2 و#3).
3. إزاحة `manageDaedTap:44` بسبب القالب.
4. تعارض `preventDefault` مع مستمع الإرسال (#9).
5. حقول `sponsorship_status` / `person_type_of_guarantee` (#5) وصيغة `person_id` (#6).
6. توحيد مصدر الحقيقة للصفوف (`activeFamilyMemberIndexes` مقابل `:not(.d-none)`) (#4).

---

## 6. ما لم يُختبر بعد (يحتاج MySQL)

- تسجيل جديد كامل من الواجهة حتى `DB::commit`.
- تحديث جماعي من لوحة الاستيراد.
- قراءة سطري `🔎 حالة family_members عند الحفظ` و`📋 ملخص التسجيل العام` في `storage/logs/laravel.log` — **توصية:** إفراغ السجل قبل الاختبار.
- سيناريو حذف فرد أوسط ثم حفظ (التأكد من عدم بقاء فهرس شبحي في `Set` وانتقال مفاتيح المرفقات).
- سيناريو Slow 3G والفحص اليدوي لـPayload `family_members[0..N]`.
- تأكيد عملي للتعارضين #1 و#9 (كلاهما يحتاج تشغيل المتصفح).

---

## 7. ملحق: ترتيب التنفيذ الفعلي لـ`@push`

| الحالة | المخرج عبر `@stack` |
|--------|---------------------|
| `@push(A)` … `@push(B)` … `@endpush` … `C` … `@endpush` | `B` ثم `A+C` |
| `@push(A)` `@push(B)` `@endpush` `@push(D)` `@endpush` `@endpush` (بنية `manageForm`) | **`B` ثم `D` ثم `A`** |
| محتوى `manageForm` | L380-653 ثم L657-867 ثم (L2-378 + L869-1512) |

تم التحقق بتجربة Blade فعلية (`Blade::compileString` + `eval`) — السكربتات المؤقتة أُنشئت في `scratch/` ثم حُذفت.
