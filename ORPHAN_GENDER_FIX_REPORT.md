# تقرير إصلاح خطأ orphan_gender في جدول sponsorships

**التاريخ:** 11 يناير 2026  
**الإصدار:** APK v22  
**نوع الإصلاح:** إصلاح حرج لخطأ SQL

---

## المشكلة 🔴

### الخطأ الذي حدث:
```
[2026-01-11 16:46:23] local.ERROR: Failed to upload sync data 
{"error":"SQLSTATE[42S22]: Column not found: 1054 
Unknown column 'orphan_gender' in 'SET' 
(Connection: mysql, SQL: update `sponsorships` set 
`identity_number` = 407015601, 
`sponsored_birth_date` = 1978-01-02, 
`orphan_gender` = أنثى, ← ❌ هذا العمود غير موجود!
`guardian_identity_number` = 40701569201, 
`orphan_name` = نصرالله01 عبد الناصر01 رفيق01 الفرا01, 
`guardian_name` = نصرالله01 عبد الناصر01 رفيق01 الفرا01, 
`updated_at` = 2026-01-11 16:46:23, 
`updated_by` = 3 where `id` = 158)"}
```

### السبب:
الكود كان يحاول تحديث عمود `orphan_gender` في جدول `sponsorships` لكن هذا العمود:
- ❌ **غير موجود** في جدول `sponsorships`
- ✅ **موجود فقط** في جداول: `data`, `re_people`, `dead_people`

---

## الإصلاح ✅

### الملف المُعدّل:
**`app/Http/Controllers/Api/SponsorshipSyncController.php`**

### التعديل في دالة `uploadSyncData()`:

#### قبل الإصلاح (الكود الخاطئ):
```php
$filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));
$filteredUpdates['updated_at'] = now();
$filteredUpdates['updated_by'] = $request->user()->id;

// المشكلة: لا يوجد تصفية صريحة لـ dataOnlyFields!
// إذا كان orphan_gender في $updates، قد يتسلل إلى $filteredUpdates
```

#### بعد الإصلاح (الكود الصحيح):
```php
// تصفية التحديثات: فقط allowedFields وليس dataOnlyFields
$filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));

// إزالة أي حقول من dataOnlyFields قد تكون تسللت
foreach ($dataOnlyFields as $dataField) {
    unset($filteredUpdates[$dataField]);
}

$filteredUpdates['updated_at'] = now();
$filteredUpdates['updated_by'] = $request->user()->id;
```

---

## الاختبار المحلي 🧪

تم إنشاء ملف اختبار: `test_orphan_gender_fix.php`

### نتيجة الاختبار:
```
✅ orphan_gender موجود في $updates الأصلي
   (سيتم معالجته في updateOrphanDataByPersonType)

✅ orphan_gender غير موجود في $filteredUpdates
   (لن يتم تحديثه في جدول sponsorships)

✅ SQL لا يحتوي على orphan_gender

✅✅✅ جميع الاختبارات نجحت!
```

### SQL المُنتج بعد الإصلاح:
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

**✅ لاحظ: لا يوجد `orphan_gender` في SQL**

---

## كيف يتم معالجة orphan_gender الآن؟

### المسار الصحيح:

1. **يتم استلام البيانات من التطبيق** تحتوي على `orphan_gender`

2. **يتم تصفية البيانات** للحقول المسموح بها في `sponsorships` فقط
   - ✅ `identity_number`
   - ✅ `sponsored_birth_date`
   - ✅ `orphan_name`
   - ❌ `orphan_gender` (يتم استبعاده)

3. **يتم تحديث جدول `sponsorships`** بدون `orphan_gender`

4. **يتم استدعاء** `updateOrphanDataByPersonType()`:
   ```php
   if (isset($updates['orphan_gender'])) {
       $this->updateOrphanDataByPersonType($sponsorship, $updates, $userId);
   }
   ```

5. **يتم توجيه `orphan_gender`** للجدول الصحيح:
   - `breadwinner` → جدول `data`
   - `repeople` → جدول `re_people`
   - `dead` → جدول `dead_people`

---

## الحقول التي تذهب لجداول أخرى (dataOnlyFields)

القائمة الكاملة للحقول التي **لا** يجب تحديثها في `sponsorships`:

```php
$dataOnlyFields = [
    'health_status_id',
    'guardian_phone',
    'guardian_phone2',
    'guardian_city_id',
    'guardian_detailed_address',
    'guardian_first_name',
    'guardian_father_name',
    'guardian_grandfather_name',
    'guardian_family_name',
    'guardian_person_type',
    'orphan_first_name',
    'orphan_father_name',
    'orphan_grandfather_name',
    'orphan_family_name',
    'orphan_gender' // ← المشكلة كانت هنا!
];
```

---

## الخلاصة

### ما تم إصلاحه:
✅ إضافة تصفية صريحة لإزالة `dataOnlyFields` من `filteredUpdates`  
✅ ضمان عدم تحديث `orphan_gender` في جدول `sponsorships`  
✅ توجيه `orphan_gender` للجداول الصحيحة حسب `person_type`  
✅ اختبار محلي يؤكد نجاح الإصلاح  

### النتيجة:
- ❌ **قبل:** خطأ SQL عند تحديث البيانات
- ✅ **بعد:** تحديث ناجح بدون أخطاء، `orphan_gender` يذهب للجدول الصحيح

---

**الحالة:** ✅ جاهز للبناء والاختبار في APK v22
