# تقرير اختبار توجيه البيانات حسب person_type

**التاريخ:** 11 يناير 2026  
**الإصدار:** APK v22  
**الحالة:** ✅ جميع الاختبارات نجحت

---

## ملخص تنفيذي

تم التحقق بنجاح من أن النظام يميز نوع الشخص المكفول من خلال عمود `person_type` ويوجه البيانات للجداول الصحيحة في حالتين:
1. ✅ **إنشاء سجل جديد** إذا لم يكن للشخص بيانات مسبقة
2. ✅ **تحديث السجل الموجود** إذا كانت له بيانات مسبقة

---

## اختبارات تم إجراؤها

### اختبار 1: توجيه الجداول حسب person_type
**الملف:** `test_person_type_routing.php`  
**النتيجة:** ✅ نجح (7/7 اختبارات)

| person_type | الجدول المتوقع | الجدول الفعلي | الحالة |
|-------------|----------------|---------------|--------|
| breadwinner | data | data | ✅ |
| repeople | re_people | re_people | ✅ |
| dead | dead_people | dead_people | ✅ |
| orphan | data | data | ✅ |
| unknown | data (افتراضي) | data | ✅ |

**الحالات الخاصة المختبرة:**
- ✅ person_type غير موجود في updates → يستخدم من الكفالة
- ✅ identity_number غير موجود → يتجاهل التحديث
- ✅ person_type غير معروف → يستخدم الجدول الافتراضي (data)

---

### اختبار 2: منع orphan_gender من جدول sponsorships
**الملف:** `test_orphan_gender_fix.php`  
**النتيجة:** ✅ نجح

**ما تم اختباره:**
- ✅ `orphan_gender` **غير موجود** في `$filteredUpdates` لجدول sponsorships
- ✅ `orphan_gender` **موجود** في `$updates` الأصلي (سيتم معالجته لاحقاً)
- ✅ SQL لتحديث sponsorships **لا يحتوي** على `orphan_gender`

**SQL المُنتج:**
```sql
UPDATE `sponsorships` SET 
`identity_number` = '407015601', 
`sponsored_birth_date` = '1978-01-02', 
`guardian_identity_number` = '40701569201', 
`orphan_name` = 'نصرالله01 عبد الناصر01 رفيق01 الفرا01', 
`guardian_name` = 'نصرالله01 عبد الناصر01 رفيق01 الفرا01', 
`updated_at` = '2026-01-11 16:57:46', 
`updated_by` = '3' 
WHERE `id` = 158
```
**✅ لاحظ: لا يوجد `orphan_gender`**

---

### اختبار 3: التكامل الكامل (من التطبيق إلى قاعدة البيانات)
**الملف:** `test_full_integration.php`  
**النتيجة:** ✅ نجح

**التدفق المختبر:**

#### المرحلة 1: التطبيق (detail.html)
```javascript
// البيانات المرسلة من التطبيق:
{
    orphan_first_name: 'نصرالله01',
    orphan_father_name: 'عبد الناصر01',
    orphan_grandfather_name: 'رفيق01',
    orphan_family_name: 'الفرا01',
    orphan_gender: 'أنثى',
    birth_date: '1978-01-02',
    person_type: 'breadwinner' // ✅ يتم إرساله
}
```

#### المرحلة 2: الباك-إند (SponsorshipSyncController)
```php
// تصفية للـ sponsorships (فقط الحقول المسموح بها):
$filteredUpdates = [
    'person_type' => 'breadwinner',
    'orphan_name' => 'نصرالله01 عبد الناصر01 رفيق01 الفرا01'
    // ❌ orphan_gender تم استبعاده
];

// SQL لتحديث sponsorships:
UPDATE `sponsorships` SET 
    `person_type` = 'breadwinner', 
    `orphan_name` = 'نصرالله01 عبد الناصر01 رفيق01 الفرا01' 
WHERE `id` = 158
```

#### المرحلة 3: توجيه بيانات المكفول
```php
// استدعاء updateOrphanDataByPersonType()
$personType = 'breadwinner';
$tableName = 'data'; // ✅ الجدول الصحيح

// البيانات المجهزة:
$personData = [
    'person_first_name' => 'نصرالله01',
    'person_father_name' => 'عبد الناصر01',
    'person_grandfather_name' => 'رفيق01',
    'person_family_name' => 'الفرا01',
    'person_gender' => 'أنثى', // ✅ orphan_gender → person_gender
    'person_birth_date' => '1978-01-02'
];

// SQL للتحديث (إذا موجود):
UPDATE `data` SET 
    `person_first_name` = 'نصرالله01', 
    `person_father_name` = 'عبد الناصر01', 
    `person_grandfather_name` = 'رفيق01', 
    `person_family_name` = 'الفرا01', 
    `person_gender` = 'أنثى', 
    `person_birth_date` = '1978-01-02' 
WHERE `person_identity_number` = '407015601'

// SQL للإنشاء (إذا غير موجود):
INSERT INTO `data` (
    `person_first_name`, `person_father_name`, 
    `person_grandfather_name`, `person_family_name`, 
    `person_gender`, `person_birth_date`, 
    `person_identity_number`
) VALUES (
    'نصرالله01', 'عبد الناصر01', 'رفيق01', 'الفرا01', 
    'أنثى', '1978-01-02', '407015601'
)
```

---

## آلية العمل

### 1. تحديد نوع الشخص (person_type)

```php
// في SponsorshipSyncController.php - updateOrphanDataByPersonType()
$personType = $updates['person_type'] ?? $sponsorship->person_type ?? 'orphan';
```

**الأولوية:**
1. من البيانات الواردة (`$updates['person_type']`)
2. من الكفالة الموجودة (`$sponsorship->person_type`)
3. افتراضي (`'orphan'`)

---

### 2. اختيار الجدول المناسب

```php
$tableName = match($personType) {
    'breadwinner' => 'data',
    'repeople' => 're_people',
    'dead' => 'dead_people',
    default => 'data' // افتراضي
};
```

**خريطة التوجيه:**
```
breadwinner  →  data
repeople     →  re_people
dead         →  dead_people
orphan       →  data (افتراضي)
أي نوع آخر   →  data (افتراضي)
```

---

### 3. التحقق من وجود السجل

```php
$exists = DB::table($tableName)
    ->where('person_identity_number', $identityNumber)
    ->exists();
```

---

### 4. إنشاء أو تحديث السجل

**إذا كان موجود:**
```php
DB::table($tableName)
    ->where('person_identity_number', $identityNumber)
    ->update($personData);
```

**إذا لم يكن موجود:**
```php
$personData['person_identity_number'] = $identityNumber;
$personData['created_at'] = now();
DB::table($tableName)->insert($personData);
```

---

## تعيين الحقول (Field Mapping)

### من التطبيق إلى قاعدة البيانات:

| حقل التطبيق | حقل جدول الشخص | ملاحظات |
|-------------|----------------|---------|
| orphan_first_name | person_first_name | ✅ |
| orphan_father_name | person_father_name | ✅ |
| orphan_grandfather_name | person_grandfather_name | ✅ |
| orphan_family_name | person_family_name | ✅ |
| orphan_gender | person_gender | ✅ |
| birth_date | person_birth_date | ✅ |
| identity_number | person_identity_number | ✅ مفتاح البحث |

---

## الملفات المُعدّلة

### 1. Backend (Laravel)

**`app/Http/Controllers/Api/SponsorshipSyncController.php`**

**السطور 683-708:** تصفية صريحة لإزالة `dataOnlyFields`
```php
// تصفية التحديثات: فقط allowedFields وليس dataOnlyFields
$filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));

// إزالة أي حقول من dataOnlyFields قد تكون تسللت
foreach ($dataOnlyFields as $dataField) {
    unset($filteredUpdates[$dataField]);
}
```

**السطور 1951-2027:** دالة `updateOrphanDataByPersonType()`
- تحديد `person_type`
- اختيار الجدول المناسب
- التحقق من وجود السجل
- إنشاء أو تحديث السجل

---

### 2. Frontend (Mobile App)

**`mobile-app/dist/detail.html`**

**السطور 333-342:** إضافة `person_type` عند حفظ بيانات المكفول
```javascript
// إرسال person_type للمكفول دائماً
if (modifiedFields.orphan_first_name || modifiedFields.orphan_father_name || 
    modifiedFields.orphan_grandfather_name || modifiedFields.orphan_family_name || 
    modifiedFields.identity_number || modifiedFields.orphan_gender ||
    modifiedFields.birth_date) {
    // إضافة person_type من الكفالة الحالية
    dataToSave.person_type = currentSponsorship.person_type || 'orphan';
}
```

---

## سيناريوهات الاستخدام

### سيناريو 1: إنشاء سجل جديد
**الحالة:** شخص مكفول جديد، لا توجد بيانات مسبقة

1. المستخدم يدخل بيانات المكفول في التطبيق
2. التطبيق يرسل البيانات مع `person_type = 'breadwinner'`
3. الباك-إند يحدد الجدول: `data`
4. يتحقق من وجود السجل: `NOT EXISTS`
5. ✅ **ينشئ سجل جديد** في جدول `data`

```sql
INSERT INTO `data` (
    person_first_name, person_father_name, 
    person_grandfather_name, person_family_name,
    person_gender, person_birth_date, 
    person_identity_number, created_at
) VALUES (...)
```

---

### سيناريو 2: تحديث سجل موجود
**الحالة:** شخص مكفول موجود مسبقاً في النظام

1. المستخدم يعدل بيانات المكفول في التطبيق
2. التطبيق يرسل التعديلات مع `person_type = 'repeople'`
3. الباك-إند يحدد الجدول: `re_people`
4. يتحقق من وجود السجل: `EXISTS`
5. ✅ **يحدث السجل الموجود** في جدول `re_people`

```sql
UPDATE `re_people` SET 
    person_first_name = 'نصرالله01',
    person_gender = 'ذكر',
    person_birth_date = '1978-01-02',
    updated_at = NOW()
WHERE person_identity_number = '407015601'
```

---

### سيناريو 3: تغيير نوع الشخص
**الحالة:** تحويل من `breadwinner` إلى `dead`

1. المستخدم يغير `person_type` من `breadwinner` إلى `dead`
2. التطبيق يرسل `person_type = 'dead'`
3. الباك-إند يحدد الجدول الجديد: `dead_people`
4. يتحقق من وجود السجل في `dead_people`: `NOT EXISTS`
5. ✅ **ينشئ سجل جديد** في `dead_people`
6. السجل القديم في `data` يبقى كما هو (لأغراض السجل التاريخي)

---

## الخلاصة

### ✅ ما تم التحقق منه:

1. **التطبيق يرسل person_type بشكل صحيح**
   - ✅ يضيفه تلقائياً عند حفظ بيانات المكفول
   - ✅ يستخدم القيمة من الكفالة الحالية

2. **الباك-إند يميز person_type بشكل صحيح**
   - ✅ يقرأه من البيانات الواردة أو الكفالة الموجودة
   - ✅ يستخدم قيمة افتراضية إذا لم يكن موجود

3. **التوجيه للجدول الصحيح**
   - ✅ breadwinner → data
   - ✅ repeople → re_people
   - ✅ dead → dead_people
   - ✅ قيم أخرى → data (افتراضي)

4. **إنشاء سجل جديد يعمل بشكل صحيح**
   - ✅ يتحقق من عدم وجود السجل
   - ✅ ينشئ سجل جديد في الجدول الصحيح
   - ✅ يستخدم person_identity_number كمفتاح

5. **تحديث السجل الموجود يعمل بشكل صحيح**
   - ✅ يتحقق من وجود السجل
   - ✅ يحدث البيانات في الجدول الصحيح
   - ✅ يحافظ على person_identity_number

6. **orphan_gender لا يذهب لجدول sponsorships**
   - ✅ يتم استبعاده من `$filteredUpdates`
   - ✅ يتم تحويله إلى `person_gender` في الجدول الصحيح

---

## ملفات الاختبار

1. **test_orphan_gender_fix.php** - اختبار منع orphan_gender
2. **test_person_type_routing.php** - اختبار توجيه الجداول
3. **test_full_integration.php** - اختبار التكامل الكامل

**النتيجة:** ✅✅✅ جميع الاختبارات نجحت

---

**الحالة النهائية:** ✅ النظام جاهز للبناء والاختبار في APK v22
