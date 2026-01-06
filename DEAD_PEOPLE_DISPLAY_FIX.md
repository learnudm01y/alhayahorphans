# إصلاح عرض بيانات المتوفين (dead_people) - تقرير شامل

## المشكلة الأصلية

عند تسجيل دخول مستخدم لديه **أكثر من كفالة** بنفس رقم الهوية، كان النظام يجلب الكفالة الأولى المطابقة لرقم الهوية بدلاً من الكفالة المحددة برقم الملف الداخلي.

### مثال المشكلة:
- **رقم الهوية**: `666665457`
- **الكفالات المرتبطة**:
  - الكفالة #71: `internal_file_number = 002622`
  - الكفالة #4454: `internal_file_number = 002671`
- **المشكلة**: عند تسجيل الدخول برقم الملف `002671`، كان النظام يعرض بيانات الكفالة الأولى (#71) بدلاً من الكفالة الصحيحة (#4454).

---

## الحلول المطبقة

### 1. تخزين معرف الكفالة في الجلسة عند تسجيل الدخول

**الملف**: `app/Http/Controllers/Users/UserLoginContoller.php`

```php
// 🆕 حفظ معرف الكفالة في الجلسة لاستخدامه في صفحة general-registration
$request->session()->put('active_sponsorship_id', $sponsorship->id);
$request->session()->put('active_internal_file_number', $sponsorship->internal_file_number);
```

### 2. استخدام الجلسة لجلب الكفالة الصحيحة

**الملف**: `app/Http/Controllers/Users/ShowGeneralRegisrationController.php`

```php
// 🆕 أولاً: محاولة جلب الكفالة من الجلسة (الكفالة المحددة عند تسجيل الدخول)
$sponsorshipId = session('active_sponsorship_id');
$sponsorship = null;

if ($sponsorshipId) {
    $sponsorship = Sponsorship::find($sponsorshipId);
}

// إذا لم نجد الكفالة من الجلسة، نبحث بالطريقة التقليدية
if (!$sponsorship) {
    $sponsorship = Sponsorship::where('identity_number', $userIdNumber)->first();
}
```

### 3. جلب بيانات dead_people مباشرة باستخدام relation_id_number

**الملف**: `app/Http/Controllers/Users/ShowGeneralRegisrationController.php`

```php
// 🆕 جلب بيانات المتوفين مباشرة من dead_people باستخدام relation_id_number
if ($sponsorship->relation_id_number && !isset($values['field_father_first_name'])) {
    $deadPeopleRecord = DB::table('dead_people')
        ->where('re_file_id', $sponsorship->relation_id_number)
        ->first();
    
    if ($deadPeopleRecord) {
        // حقول الأب المتوفى
        $values['field_father_first_name'] = $deadPeopleRecord->father_first_name ?? '';
        $values['field_father_second_name'] = $deadPeopleRecord->father_second_name ?? '';
        // ... باقي الحقول
    }
}
```

### 4. تحديث updateSeparateNameFields لاستخدام relation_id_number

**الملف**: `app/Http/Controllers/Users/ShowGeneralRegisrationController.php`

```php
// استخدام relation_id_number مباشرة للربط مع dead_people
$relationIdNumber = $sponsorship->relation_id_number;
if (!$relationIdNumber && $sponsorship->relationData) {
    $relationIdNumber = $sponsorship->relationData->file_id_number;
}

// تحديث اسم الأب المتوفى
if (isset($namesData['father']) && is_array($namesData['father']) && $relationIdNumber) {
    $deadPeople = DB::table('dead_people')
        ->where('re_file_id', $relationIdNumber)
        ->first();
    // ...
}
```

### 5. إضافة CRUD كامل لحقول dead_people

**الملف**: `app/Http/Controllers/Users/ShowGeneralRegisrationController.php`

تم إضافة:
- جمع حقول dead_people من الفورم (`$deadPeopleFields`)
- معالجة التحديث للسجلات الموجودة
- إنشاء سجلات جديدة إذا لم تكن موجودة

```php
// حقول dead_people المدعومة
$deadPeopleFieldsList = [
    'field_father_first_name', 'field_father_second_name', 'field_father_third_name', 'field_father_last_name',
    'field_father_id', 'field_father_death_date',
    'field_mother_first_name', 'field_mother_second_name', 'field_mother_third_name', 'field_mother_last_name',
    'field_mother_id', 'field_mother_death_date'
];
```

---

## هيكل البيانات

### جدول sponsorships
| Column | Description |
|--------|-------------|
| `id` | المعرف الأساسي |
| `identity_number` | رقم هوية المكفول |
| `internal_file_number` | رقم الملف الداخلي (فريد لكل كفالة) |
| `relation_id_number` | رقم ملف العلاقة (يربط مع data و dead_people) |

### جدول dead_people
| Column | Description |
|--------|-------------|
| `re_file_id` | رقم ملف العلاقة (يربط مع `relation_id_number`) |
| `father_first_name` | الاسم الأول للأب |
| `father_second_name` | اسم الأب الثاني |
| `father_third_name` | اسم الأب الثالث |
| `father_last_name` | اسم عائلة الأب |
| `father_id` | رقم هوية الأب |
| `father_death_date` | تاريخ وفاة الأب |
| `father_death_reason` | سبب وفاة الأب (FK → death_reasons) |
| `mother_*` | نفس الحقول للأم |

---

## العلاقات

```
Sponsorship
    ├── relation_id_number ──→ Data.file_id_number (relationData)
    │                              └── deadPepole (hasOne DeadPepole where re_file_id = file_id_number)
    │
    └── [NEW] Direct Query: dead_people WHERE re_file_id = relation_id_number
```

---

## اختبار الإصلاح

```bash
php test_dead_people_fix.php
```

### نتيجة الاختبار المتوقعة:
```
✅ تم العثور على الكفالة #4454
✅ تم العثور على سجل dead_people (id: 8588)
👨 بيانات الأب المتوفى:
   - الاسم: المتوفي محمد براء المتوفي
   - رقم الهوية: 436765457
✅ relationData موجودة
✅ deadPepole موجودة عبر العلاقة
```

---

## الملفات المعدلة

1. `app/Http/Controllers/Users/UserLoginContoller.php`
   - إضافة تخزين `active_sponsorship_id` في الجلسة

2. `app/Http/Controllers/Users/ShowGeneralRegisrationController.php`
   - استخدام الجلسة لجلب الكفالة الصحيحة
   - جلب dead_people مباشرة باستخدام relation_id_number
   - تحديث updateSeparateNameFields
   - إضافة CRUD لحقول dead_people

---

## تاريخ التحديث

- **التاريخ**: {{ date('Y-m-d H:i:s') }}
- **المطور**: GitHub Copilot
- **الإصدار**: 1.0
