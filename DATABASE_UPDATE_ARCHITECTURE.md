# معمارية نظام تحديث البيانات - Smart Data Update Architecture

## تاريخ التحليل: 12 يناير 2026

---

## 📊 بنية الجداول Tables Schema

### 1. `sponsorships` (جدول الكفالات)
```
id
sponsor_id
identity_number           → رقم هوية المكفول
guardian_identity_number  → رقم هوية المعيل
relation_id_number        → رقم الربط الداخلي (مخفي)
person_type              → نوع الشخص المكفول
  - breadwinner          (معيل - موجود في data)
  - family_member        (فرد عائلة - موجود في re_people)
  - deceased_father      (أب متوفي - موجود في dead_people)
  - deceased_mother      (أم متوفية - موجود في dead_people)
  - orphan               (يتيم - قيمة عامة قديمة)
```

### 2. `data` (جدول المعيلين)
```
id
file_id_number           → رقم الملف (UNIQUE)
data_id_number           → رقم الهوية
data_first_name
data_father_name
data_grand_father_name
data_family_name
data_gender              → INTEGER (1=ذكر, 2=أنثى)
data_birth_date
```

### 3. `re_people` (جدول أفراد العائلة)
```
id
registration_id          → رقم التسجيل
person_id                → رقم الهوية
first_name
second_name
third_name
last_name
person_gender            → INTEGER (1=ذكر, 2=أنثى)
person_birth_date
```

### 4. `dead_people` (جدول المتوفين)
```
id
re_file_id               → رقم الملف المرتبط (FK → data.file_id_number)
father_first_name
father_second_name
father_third_name
father_last_name
father_id                → رقم هوية الأب
father_death_date
mother_first_name
mother_second_name
mother_third_name
mother_last_name
mother_id                → رقم هوية الأم
mother_death_date
```

---

## 🎯 خوارزمية المطابقة Matching Algorithm

### المدخلات من `sponsorships`:
```
relation_id_number       → رقم الربط
identity_number          → رقم هوية المكفول
guardian_identity_number → رقم هوية المعيل
person_type              → نوع الشخص
```

### قواعد المطابقة:

#### ✅ للمكفول (Sponsored Person):

**القاعدة 1: person_type = 'breadwinner'**
```sql
المطابقة:
  sponsorships.relation_id_number + identity_number
  ↓
  data.file_id_number + data_id_number

التحديث في: data
الحقول:
  - data_first_name, data_father_name, data_grand_father_name, data_family_name
  - data_gender (1 أو 2)
  - data_birth_date
  - data_id_number
```

**القاعدة 2: person_type = 'family_member'**
```sql
المطابقة:
  sponsorships.relation_id_number + identity_number
  ↓
  re_people.registration_id + person_id

التحديث في: re_people
الحقول:
  - first_name, second_name, third_name, last_name
  - person_gender (1 أو 2)
  - person_birth_date
  - person_id
```

**القاعدة 3: person_type = 'deceased_father'**
```sql
المطابقة:
  sponsorships.relation_id_number + identity_number
  ↓
  dead_people.re_file_id + father_id

التحديث في: dead_people (قسم الأب)
الحقول:
  - father_first_name, father_second_name, father_third_name, father_last_name
  - father_id
  - father_death_date
  
ملاحظة: لا يوجد person_gender - الجنس محدد مسبقاً (ذكر)
```

**القاعدة 4: person_type = 'deceased_mother'**
```sql
المطابقة:
  sponsorships.relation_id_number + identity_number
  ↓
  dead_people.re_file_id + mother_id

التحديث في: dead_people (قسم الأم)
الحقول:
  - mother_first_name, mother_second_name, mother_third_name, mother_last_name
  - mother_id
  - mother_death_date
  
ملاحظة: لا يوجد person_gender - الجنس محدد مسبقاً (أنثى)
```

---

#### ✅ للمعيل (Guardian):

**يتم تحديده من: `guardian_person_type` في الطلب**

**القاعدة 1: guardian_person_type = 'breadwinner'**
```sql
المطابقة:
  sponsorships.relation_id_number + guardian_identity_number
  ↓
  data.file_id_number + data_id_number

التحديث في: data
```

**القاعدة 2: guardian_person_type = 'family_member'**
```sql
المطابقة:
  sponsorships.relation_id_number + guardian_identity_number
  ↓
  re_people.registration_id + person_id

التحديث في: re_people
```

**القاعدة 3: guardian_person_type = 'deceased_father/mother'**
```sql
المطابقة:
  sponsorships.relation_id_number + guardian_identity_number
  ↓
  dead_people.re_file_id + father_id/mother_id

التحديث في: dead_people
```

---

## 🔧 التعديلات المطلوبة

### 1. في التطبيق (Mobile App)

#### ملف: `mobile-app/dist/detail.html`

**التعديلات المطلوبة:**

1. **إضافة حقل مخفي لـ person_type:**
```html
<input type="hidden" id="person_type" data-field="person_type">
```

2. **تحديد نوع الجنس بناءً على person_type:**
```javascript
// بدلاً من عرض (ذكر/أنثى)
// إذا كان person_type = 'deceased_father' → عرض "أب متوفي"
// إذا كان person_type = 'deceased_mother' → عرض "أم متوفية"
// وإلا → عرض (ذكر/أنثى) عادي
```

3. **إرسال person_type مع كل تحديث:**
```javascript
dataToSave.person_type = currentSponsorship.person_type;
dataToSave.guardian_person_type = currentSponsorship.guardian_person_type;
```

### 2. في الخادم (Backend)

#### ملف: `app/Http/Controllers/Api/SponsorshipSyncController.php`

**الدالة: `uploadSyncData()`**

**التعديلات:**

1. **استخدام person_type لتحديد الجدول:**
```php
$personType = $updates['person_type'] ?? $sponsorship->person_type ?? 'orphan';

switch($personType) {
    case 'breadwinner':
        $targetTable = 'data';
        $matchFields = ['file_id_number' => $relation_id_number, 'data_id_number' => $identity_number];
        break;
    
    case 'family_member':
        $targetTable = 're_people';
        $matchFields = ['registration_id' => $relation_id_number, 'person_id' => $identity_number];
        break;
    
    case 'deceased_father':
        $targetTable = 'dead_people';
        $matchFields = ['re_file_id' => $relation_id_number, 'father_id' => $identity_number];
        $prefix = 'father';
        break;
    
    case 'deceased_mother':
        $targetTable = 'dead_people';
        $matchFields = ['re_file_id' => $relation_id_number, 'mother_id' => $identity_number];
        $prefix = 'mother';
        break;
}
```

2. **البحث بطريقة ذكية:**
```php
// البحث باستخدام جميع الحقول
$query = DB::table($targetTable);
foreach ($matchFields as $field => $value) {
    $query->where($field, $value);
}
$record = $query->first();
```

3. **التحديث بناءً على نوع الجدول:**
```php
if ($targetTable === 'dead_people') {
    // لا يوجد تحديث للجنس - الجنس محدد مسبقاً من person_type
    if (isset($updates['first_name'])) {
        $updateData[$prefix . '_first_name'] = $updates['first_name'];
    }
    // ... بقية الحقول
}
```

---

## 🚨 المشاكل الحالية

### ❌ المشكلة 1: عدم استخدام person_type
**الكود الحالي:**
```php
// يبحث فقط باستخدام relation_id_number في جميع الجداول
$dataRecord = DB::table('data')->where('file_id_number', $targetKeyValue)->first();
$deadRecord = DB::table('dead_people')->where('re_file_id', $targetKeyValue)->first();
$repeopleRecord = DB::table('re_people')->where('registration_id', $targetKeyValue)->first();
```

**المشكلة:** لا يستخدم `identity_number` للتأكد من الشخص الصحيح!

### ❌ المشكلة 2: الجنس في جدول dead_people
**الكود الحالي:**
```php
if (isset($updates['orphan_gender'])) {
    $updateData['person_gender'] = ...;  // ❌ لا يوجد person_gender في dead_people!
}
```

**الحل:** استخدام `person_type` لتحديد father أو mother بدلاً من الجنس

### ❌ المشكلة 3: عدم مطابقة identity_number
**الكود الحالي:** لا يتحقق من رقم الهوية عند البحث

**الحل:** استخدام WHERE clause مع رقمين:
- `relation_id_number` + `identity_number`

---

## ✅ الحل المقترح

### خطوات التنفيذ:

1. ✅ **فحص الجداول** (مكتمل)
2. ⏳ **تعديل Backend** (قيد التنفيذ)
   - إعادة كتابة دالة `uploadSyncData()`
   - إضافة دالة `matchSponsoredPerson()`
   - إضافة دالة `matchGuardianPerson()`
   - إضافة دالة `updatePersonData()`
3. ⏳ **تعديل Mobile App**
   - إرسال `person_type` مع كل طلب
   - تعديل واجهة الجنس للمتوفين
   - إضافة validation

---

**المرحلة التالية:** تنفيذ التعديلات على Backend

