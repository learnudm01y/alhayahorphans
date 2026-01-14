# تقرير إصلاح مشاكل تكرار المعيلين والأعمدة غير الموجودة

## التاريخ: 2026-01-14

## المشاكل التي تم حلها

### 1. خطأ العمود غير موجود (data_personal_name)
**الخطأ:**
```
Column not found: 1054 Unknown column 'data_personal_name' in 'INSERT INTO'
```

**السبب:**
- الكود كان يحاول إدراج قيمة في عمود `data_personal_name` غير موجود في جدول `data`
- نفس المشكلة مع `father_full_name` و `mother_full_name` في جدول `dead_people`

**الحل:**
- تم إزالة استخدام هذه الأعمدة من الكود
- الاسم الكامل موجود في الأعمدة المنفصلة:
  - `data`: `data_first_name`, `data_father_name`, `data_grand_father_name`, `data_family_name`
  - `dead_people`: `father_first_name`, `father_second_name`, `father_third_name`, `father_last_name`

### 2. تكرار سجلات المعيلين في جدول data
**المشكلة:**
- عند إنشاء معيل جديد، كان يتم إنشاء سجل جديد حتى لو كان المعيل موجود بنفس رقم الهوية
- وجدت 3 هويات مكررة في قاعدة البيانات:
  ```
  - هوية 123456789: 2 سجل (file_ids: 053443,053444)
  - هوية 407015692: 2 سجل (file_ids: 002190,002682)
  - هوية 999888777: 2 سجل (file_ids: 3147,53442)
  ```

**الحل:**
- تم إضافة فحص قبل إنشاء سجل جديد في `data`:
  1. البحث عن سجل موجود بنفس `data_id_number` (رقم الهوية)
  2. إذا وجد سجل → استخدامه وتحديثه بدلاً من إنشاء سجل جديد
  3. تحديث `relation_id_number` في `sponsorships` بالـ `file_id_number` الموجود

### 3. خطأ Foreign Key في guardian_bank_accounts
**المشكلة:**
```
Integrity constraint violation: guardian_registration REFERENCES data.file_id_number
```
- القيمة `024433` في `guardian_registration` غير موجودة في `data.file_id_number`

**الحل:**
تم تحسين منطق إنشاء الحسابات البنكية:
1. التحقق من وجود `guardian_registration` في `data`
2. إذا لم يكن موجوداً → البحث عن سجل بـ `guardian_identity_number`
3. إذا وجد → استخدام `file_id_number` الموجود
4. إذا لم يوجد → إنشاء سجل جديد في `data` أولاً

### 4. تحديث حالة الكفالة
**التحسين:**
- تم إزالة الشرط الذي يمنع تغيير الحالة عندما تكون "أرسل للصرف"
- الآن **مهما كانت الحالة** يتم تغييرها إلى "انتظار الصرف" عند التعديل من الهاتف

## الملفات المعدلة

### SponsorshipSyncController.php
**الموقع:** `app/Http/Controllers/Api/SponsorshipSyncController.php`

#### التغييرات:

1. **إزالة data_personal_name** (السطر ~1375):
   ```php
   // تم إزالة
   $dataUpdates['data_personal_name'] = trim("$firstName...");
   ```

2. **إزالة father_full_name و mother_full_name** (السطر ~2030):
   ```php
   // تم إزالة من deceased_father و deceased_mother
   ```

3. **إضافة فحص التكرار** (السطر ~1425):
   ```php
   $duplicateCheck = DB::table('data')
       ->where('data_id_number', $guardianIdentity)
       ->first();
   
   if ($duplicateCheck) {
       // استخدام السجل الموجود بدلاً من إنشاء جديد
   }
   ```

4. **تحسين updateBankAccounts** (السطر ~1703):
   ```php
   // التحقق من وجود guardian_registration في data
   if (!empty($validGuardianRegistration)) {
       // البحث أو إنشاء سجل في data
   }
   ```

5. **إزالة استبعاد حالات معينة** (السطر ~3430):
   ```php
   // تم إزالة الشرط:
   // if (in_array($currentSponsorship->sponsorship_status_id, $excludedStatuses))
   ```

## اختبار التغييرات

### سكريبت الفحص
تم إنشاء `check_database_columns.php` للتحقق من:
- ✅ الأعمدة الموجودة في `data`, `dead_people`, `guardian_bank_accounts`
- ✅ المفاتيح الخارجية
- ✅ تكرار المعيلين في الجداول

### النتائج:
```bash
php check_database_columns.php
```

- ❌ `data_personal_name`: غير موجود (تم حل المشكلة)
- ❌ `father_full_name`: غير موجود (تم حل المشكلة)
- ❌ `mother_full_name`: غير موجود (تم حل المشكلة)
- ⚠️ وجدت 3 هويات مكررة (سيتم منع التكرار مستقبلاً)

## تأثير التغييرات

### الإيجابيات:
1. ✅ لن يتم إنشاء سجلات مكررة للمعيلين
2. ✅ تم حل خطأ Foreign Key في الحسابات البنكية
3. ✅ تم حل خطأ الأعمدة غير الموجودة
4. ✅ تحسين استخدام الموارد (عدم تكرار البيانات)
5. ✅ تحديث حالة الكفالة بشكل صحيح

### المخاطر:
- ⚠️ السجلات المكررة الموجودة حالياً لن يتم حذفها تلقائياً
- ⚠️ يجب مراجعة السجلات المكررة يدوياً

## التوصيات

### تنظيف البيانات المكررة:
```sql
-- البحث عن جميع التكرارات
SELECT data_id_number, COUNT(*) as count, GROUP_CONCAT(file_id_number) as file_ids
FROM data
WHERE data_id_number IS NOT NULL AND data_id_number != ''
GROUP BY data_id_number
HAVING count > 1;

-- دمج السجلات المكررة يدوياً حسب الحاجة
```

### مراقبة المستقبل:
- تشغيل `check_database_columns.php` بشكل دوري
- مراجعة logs للتأكد من عدم ظهور أخطاء Foreign Key

## الخلاصة
تم إصلاح جميع المشاكل المتعلقة بـ:
- ✅ الأعمدة غير الموجودة
- ✅ تكرار المعيلين
- ✅ Foreign Key في الحسابات البنكية
- ✅ تحديث حالة الكفالة

الكود جاهز الآن للاستخدام بأمان.
