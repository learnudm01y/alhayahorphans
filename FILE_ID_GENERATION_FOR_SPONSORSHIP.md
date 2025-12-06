# توليد رقم الملف (file_id_number) عند إنشاء كفالة

## المشكلة السابقة
عند تنفيذ كفالة لشخص من جدول `re_people` أو `dead_people`، كان النظام يستخدم رقم الملف (`file_id_number`) الخاص بالمعيل أو الملف الرئيسي، وليس رقماً فريداً للشخص المكفول.

## الحل المطبق

### 1. **توليد رقم ملف جديد تلقائياً**
عند إنشاء كفالة لشخص من `re_people` أو `dead_people`:
- يتم توليد `file_id_number` جديد باستخدام الدالة `generateUniqueReservedCode()`
- يتم إنشاء سجل جديد في جدول `data` لهذا الشخص
- يتم ربط الكفالة بالرقم الجديد

### 2. **التعديلات المطبقة**

#### أ) `SponsorshipController.php` - دالة `store()`

```php
// إضافة حقول جديدة للتحقق
'record_id' => 'nullable|string',
'record_type' => 'nullable|string|in:re_people,dead_people,data',

// معالجة توليد file_id_number للأشخاص من re_people و dead_people
if (in_array($recordType, ['re_people', 'dead_people']) && $recordId) {
    // توليد file_id_number جديد
    $newFileId = generateUniqueReservedCode('data', 'file_id_number');
    
    // إنشاء سجل في جدول data
    $dataRecord = $this->createDataRecordForPerson($recordType, $recordId, $newFileId, $validatedData);
    
    // تحديث internal_file_number في validatedData
    $validatedData['internal_file_number'] = $newFileId;
}
```

#### ب) دالة مساعدة جديدة: `createDataRecordForPerson()`

```php
/**
 * إنشاء سجل في جدول data لشخص من re_people أو dead_people
 */
private function createDataRecordForPerson($recordType, $recordId, $fileIdNumber, $validatedData)
{
    // جلب معلومات الشخص من الجدول المناسب
    // إنشاء سجل في data مع file_id_number الجديد
    // وضع علامة على الرقم كمستخدم
}
```

#### ج) تحديث الاستجابة لعرض الرقم الجديد

```php
// إضافة معلومات الرقم الجديد للرسالة
if (isset($newFileId)) {
    $message .= ' - تم توليد رقم ملف جديد';
    $additionalInfo['new_file_id'] = $newFileId;
    $additionalInfo['file_id_generated'] = true;
}
```

#### د) `unsponsored.blade.php` - عرض الرقم الجديد

```javascript
// إذا تم توليد رقم ملف جديد، أضف معلومات إضافية
if (response.info && response.info.file_id_generated && response.info.new_file_id) {
    successMessage += `<br><br><div class="alert alert-success mt-3">
        <i class="fas fa-info-circle me-2"></i>
        <strong>رقم الملف الجديد: ${response.info.new_file_id}</strong>
    </div>`;
}
```

### 3. **سير العمل**

#### للأيتام من `re_people`:
1. المستخدم ينقر على "تنفيذ كفالة" لليتيم
2. النظام يرسل `record_id` و `record_type='re_people'`
3. يتم توليد رقم ملف جديد (مثل: `000234`)
4. يتم إنشاء سجل في `data`:
   - `file_id_number`: الرقم الجديد
   - `data_section_id`: 1 (قسم الأيتام)
   - `data_id_number`: رقم هوية اليتيم
   - بيانات اليتيم الأخرى
5. يتم إنشاء الكفالة مع `internal_file_number = 000234`
6. عرض رسالة نجاح مع الرقم الجديد

#### للأشخاص المتوفين من `dead_people`:
1. المستخدم ينقر على "تنفيذ كفالة" للأب/الأم المتوفي
2. النظام يرسل `record_id='father_000123'` و `record_type='dead_people'`
3. يتم توليد رقم ملف جديد (مثل: `000235`)
4. يتم إنشاء سجل في `data`:
   - `file_id_number`: الرقم الجديد
   - `data_section_id`: 4 (قسم المتوفين)
   - `data_id_number`: رقم هوية الأب/الأم
   - بيانات المتوفي الأخرى
5. يتم إنشاء الكفالة مع `internal_file_number = 000235`
6. عرض رسالة نجاح مع الرقم الجديد

#### للمعيلين من `data`:
1. المستخدم ينقر على "تنفيذ كفالة" للمعيل
2. النظام يرسل `record_type='data'`
3. **لا يتم توليد رقم جديد** - يتم استخدام `file_id_number` الموجود
4. يتم إنشاء الكفالة مع الرقم الحالي

### 4. **البيانات المخزنة في جدول `data`**

#### لـ `re_people`:
- `file_id_number`: الرقم الجديد المُولَّد
- `data_section_id`: 1 (أيتام)
- `data_id_number`: `person_id` من re_people
- `data_first_name`, `data_father_name`, إلخ: من re_people
- `data_request_status`: 2 (تمت الموافقة)

#### لـ `dead_people`:
- `file_id_number`: الرقم الجديد المُولَّد
- `data_section_id`: 4 (متوفين)
- `data_id_number`: `father_id` أو `mother_id` من dead_people
- `data_first_name`, `data_father_name`, إلخ: من dead_people
- `data_request_status`: 2 (تمت الموافقة)

### 5. **العرض في الواجهات**

#### جدول الكفالات (`SponsorshipsDataTable`):
- يعرض `internal_file_number` - سيكون الرقم الجديد المُولَّد

#### مودال تفاصيل الكفالة:
- يعرض `internal_file_number` - الرقم الجديد

#### صفحة التعديل:
- تُحمل جميع البيانات بما فيها `internal_file_number` الصحيح

### 6. **الملفات المعدلة**

1. ✅ `app/Http/Controllers/Admin/SponsorshipController.php`
   - تعديل دالة `store()` لتوليد رقم جديد
   - إضافة دالة `createDataRecordForPerson()`
   - تحديث الاستجابة JSON

2. ✅ `resources/views/admin/dashboard/sponsorships/unsponsored.blade.php`
   - تحديث رسالة النجاح لعرض الرقم الجديد

3. ✅ `resources/views/admin/dashboard/sponsorships/index.blade.php`
   - إضافة عرض البيانات البنكية الموجودة

### 7. **التحقق من الصحة**

#### اختبار التوليد:
```sql
-- التحقق من السجلات الجديدة في data
SELECT * FROM data 
WHERE file_id_number IN (
    SELECT internal_file_number 
    FROM sponsorships 
    WHERE created_at >= '2025-12-06'
)
ORDER BY created_at DESC;
```

#### اختبار الكفالات:
```sql
-- التحقق من الكفالات الجديدة
SELECT 
    s.id,
    s.internal_file_number,
    s.orphan_name,
    d.file_id_number,
    d.data_section_id
FROM sponsorships s
LEFT JOIN data d ON d.file_id_number = s.internal_file_number
WHERE s.created_at >= '2025-12-06'
ORDER BY s.created_at DESC;
```

### 8. **ملاحظات مهمة**

⚠️ **الأرقام فريدة**: كل شخص يحصل على `file_id_number` فريد خاص به
⚠️ **لا تكرار**: النظام يستخدم `generateUniqueReservedCode()` للتأكد من عدم التكرار
⚠️ **التوافق**: المعيلون من `data` يستخدمون أرقامهم الموجودة بالفعل
⚠️ **التتبع**: جميع العمليات مسجلة في الـ logs

### 9. **الفوائد**

✅ كل شخص لديه رقم ملف فريد
✅ سهولة التتبع والبحث
✅ فصل واضح بين المعيلين والمكفولين
✅ إمكانية إضافة معلومات مستقلة لكل شخص
✅ توافق مع النظام الحالي

### 10. **الحالات الخاصة**

#### إذا فشل توليد الرقم:
```php
if (!$newFileId) {
    throw new \Exception('فشل في توليد رقم ملف فريد');
}
```

#### إذا لم يُعثر على الشخص:
```php
if (!$person) {
    throw new \Exception('لم يتم العثور على الشخص في جدول ...');
}
```

---

## التاريخ
تم التطبيق: 6 ديسمبر 2025
المطور: GitHub Copilot
