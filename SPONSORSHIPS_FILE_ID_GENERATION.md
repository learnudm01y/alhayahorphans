# توليد رقم الملف الداخلي عند استيراد الكفالات من Excel

## 📋 نظرة عامة

تم تطبيق نظام ذكي لتوليد أرقام الملفات الداخلية عند استيراد الكفالات من Excel، يأخذ في الاعتبار نوع الشخص المكفول ومكان تواجده في قاعدة البيانات.

---

## 🎯 المنطق المطبق

### الحالة 1️⃣: الشخص موجود في جدول `data`

**القاعدة:** استخدام رقم الملف الموجود

```
إذا كان رقم الهوية موجود في جدول data
├─ استخدام file_id_number الموجود
├─ عدم توليد رقم ملف جديد
└─ تحديث بيانات الهاتف إن وجدت
```

**مثال:**
```
رقم الهوية: 123456789
موجود في: data.data_id_number
رقم الملف الحالي: 001234
✅ النتيجة: استخدام رقم الملف 001234
```

---

### الحالة 2️⃣: الشخص موجود في جدول `re_people`

**القاعدة:** توليد رقم ملف داخلي جديد

```
إذا كان رقم الهوية موجود في جدول re_people
├─ توليد file_id_number جديد باستخدام generateUniqueReservedCode()
├─ حفظ الرقم الجديد في sponsorships.internal_file_number
└─ استخدام نفس الرقم في guardian_bank_accounts.guardian_registration
```

**مثال:**
```
رقم الهوية: 987654321
موجود في: re_people.re_id_number
رقم الملف الجديد: 005678 (تم توليده تلقائياً)
✅ النتيجة: توليد رقم ملف جديد 005678
```

**ملاحظة مهمة:**
- لا يتم إنشاء سجل جديد في جدول `data`
- الشخص يبقى في جدول `re_people`
- رقم الملف الجديد يُحفظ فقط في جدول `sponsorships`

---

### الحالة 3️⃣: الشخص موجود في جدول `dead_people`

**القاعدة:** توليد رقم ملف داخلي جديد

```
إذا كان رقم الهوية موجود في جدول dead_people
├─ توليد file_id_number جديد باستخدام generateUniqueReservedCode()
├─ حفظ الرقم الجديد في sponsorships.internal_file_number
└─ استخدام نفس الرقم في guardian_bank_accounts.guardian_registration
```

**مثال:**
```
رقم الهوية: 456789123
موجود في: dead_people.dead_id_number
رقم الملف الجديد: 007890 (تم توليده تلقائياً)
✅ النتيجة: توليد رقم ملف جديد 007890
```

**ملاحظة مهمة:**
- لا يتم إنشاء سجل جديد في جدول `data`
- الشخص يبقى في جدول `dead_people`
- رقم الملف الجديد يُحفظ فقط في جدول `sponsorships`

---

### الحالة 4️⃣: الشخص غير موجود في أي جدول

**القاعدة:** رفض الاستيراد

```
إذا لم يُعثر على رقم الهوية في:
├─ data.data_id_number
├─ re_people.re_id_number
└─ dead_people.dead_id_number

❌ النتيجة: رفض الصف وإضافته لقائمة الأخطاء
```

---

## 🔍 عملية البحث والتحقق

### 1. الفحص المسبق (Pre-validation)

قبل بدء عملية الاستيراد، يتم فحص جميع أرقام الهويات في الملف:

```php
// البحث في جدول data
$existingGuardiansInData = Data::whereIn('data_id_number', $uniqueGuardians)
    ->pluck('data_id_number')
    ->toArray();

// البحث في جدول re_people (العمود: person_id)
$existingGuardiansInRePeople = RePeople::whereIn('person_id', $uniqueGuardians)
    ->pluck('person_id')
    ->toArray();

// البحث في جدول dead_people (العمودان: father_id و mother_id)
$existingGuardiansInDeadPeopleFather = DeadPepole::whereIn('father_id', $uniqueGuardians)
    ->pluck('father_id')
    ->toArray();

$existingGuardiansInDeadPeopleMother = DeadPepole::whereIn('mother_id', $uniqueGuardians)
    ->pluck('mother_id')
    ->toArray();

// دمج جميع الهويات الموجودة
$allExistingGuardians = array_unique(array_merge(
    $existingGuardiansInData,
    $existingGuardiansInRePeople,
    $existingGuardiansInDeadPeopleFather,
    $existingGuardiansInDeadPeopleMother
));
```

### 2. المعالجة لكل صف

```php
// أولاً: البحث في جدول data
$person = Data::where('data_id_number', $guardianIdentityNumber)->first();

if (!$person) {
    // ثانياً: البحث في re_people (العمود: person_id)
    $rePerson = RePeople::where('person_id', $guardianIdentityNumber)->first();
    
    if (!$rePerson) {
        // ثالثاً: البحث في dead_people (العمودان: father_id أو mother_id)
        $deadPerson = DeadPepole::where('father_id', $guardianIdentityNumber)
            ->orWhere('mother_id', $guardianIdentityNumber)
            ->first();
    }
    
    // إذا وُجد في re_people أو dead_people، توليد رقم ملف جديد
    if ($rePerson || $deadPerson) {
        $internalFileNumber = generateUniqueReservedCode('data', 'file_id_number');
        $shouldGenerateNewFileId = true;
    } else {
        // غير موجود في أي جدول - رفض الصف
        $errors[] = "الصف {$rowNumber}: هوية المعيل غير موجودة";
        continue;
    }
} else {
    // موجود في data - استخدام رقم الملف الموجود
    $internalFileNumber = $person->file_id_number;
    $shouldGenerateNewFileId = false;
}
```

### 3. ملاحظات هامة عن أعمدة قاعدة البيانات

**جدول `re_people`:**
- العمود المستخدم: `person_id` (رقم الهوية)
- **ليس** `re_id_number`

**جدول `dead_people`:**
- العمودان المستخدمان: `father_id` و `mother_id`
- **ليس** `dead_id_number`
- يتم البحث في كلا العمودين لأن الملف قد يحتوي على رقم هوية الأب أو الأم

---

## 📊 الجداول المتأثرة

### 1. جدول `sponsorships`

```sql
INSERT INTO sponsorships (
    guardian_identity_number,    -- رقم هوية المعيل
    internal_file_number,        -- ✅ رقم الملف المولد أو الموجود
    identity_number,             -- رقم هوية اليتيم
    orphan_name,
    guardian_name,
    sponsorship_type_id,
    sponsorship_status_id,
    created_by,
    ...
) VALUES (...)
```

### 2. جدول `guardian_bank_accounts`

```sql
INSERT INTO guardian_bank_accounts (
    guardian_registration,              -- ✅ رقم الملف المولد أو الموجود
    person_owner_identity_number,       -- رقم هوية صاحب المحفظة
    re_id_number,                       -- رقم هوية اليتيم
    re_guardian_name,
    bank_name,
    re_phone_number,
    ...
) VALUES (...)
```

**ملاحظة مهمة:**
- `guardian_registration` يربط بـ `internal_file_number` (وليس `file_id_number` من جدول data)
- هذا يضمن الربط الصحيح سواء كان الرقم مولداً حديثاً أو موجوداً مسبقاً

---

## 🔧 دالة التوليد

### `generateUniqueReservedCode()`

**الموقع:** `app/Helpers/global_helper_updated.php`

**الوظيفة:**
- توليد رقم فريد من 6 أرقام
- التحقق من عدم وجود الرقم في الجدول المحدد
- استخدام أسلوب Transaction لضمان الأمان

**الاستخدام:**
```php
$fileId = generateUniqueReservedCode('data', 'file_id_number');
```

**النتيجة:**
```
000001, 000002, 000003, ... 999999
```

---

## 📝 أمثلة عملية

### مثال 1: استيراد كفالة لشخص من جدول data

**البيانات في Excel:**
```
هوية المعيل: 123456789
اسم المعيل: أحمد محمد
هوية اليتيم: 987654321
اسم اليتيم: خالد أحمد
```

**النتيجة:**
```
✅ تم العثور على المعيل في جدول data
✅ استخدام رقم الملف الموجود: 001234
✅ تم إنشاء الكفالة برقم الملف: 001234
```

---

### مثال 2: استيراد كفالة لشخص من جدول re_people

**البيانات في Excel:**
```
هوية المعيل: 456789123
اسم المعيل: محمد علي (يتيم)
هوية اليتيم: 789123456
اسم اليتيم: سارة محمد
```

**النتيجة:**
```
✅ تم العثور على المعيل في جدول re_people
✅ تم توليد رقم ملف جديد: 005678
✅ تم إنشاء الكفالة برقم الملف الجديد: 005678
⚠️ المعيل لا يزال في جدول re_people (لم يُنقل إلى data)
```

---

### مثال 3: استيراد كفالة لشخص من جدول dead_people

**البيانات في Excel:**
```
هوية المعيل: 321654987
اسم المعيل: عبدالله حسن (متوفى)
هوية اليتيم: 654987321
اسم اليتيم: أمل عبدالله
```

**النتيجة:**
```
✅ تم العثور على المعيل في جدول dead_people
✅ تم توليد رقم ملف جديد: 007890
✅ تم إنشاء الكفالة برقم الملف الجديد: 007890
⚠️ المعيل لا يزال في جدول dead_people (لم يُنقل إلى data)
```

---

### مثال 4: استيراد كفالة لشخص غير موجود

**البيانات في Excel:**
```
هوية المعيل: 999999999
اسم المعيل: غير معروف
هوية اليتيم: 888888888
اسم اليتيم: غير معروف
```

**النتيجة:**
```
❌ هوية المعيل غير موجودة في أي جدول
❌ تم رفض الصف وإضافته لقائمة الأخطاء
💡 يجب إضافة المعيل أولاً من صفحة التسجيل
```

---

## 🎨 سجلات النظام (Logs)

### عند توليد رقم ملف جديد

```
✅ تم توليد رقم ملف داخلي جديد للشخص من re_people
{
    "guardian_identity": "456789123",
    "new_file_id": "005678",
    "row": 5
}
```

### عند استخدام رقم موجود

```
✅ تم العثور على الشخص في جدول data، استخدام رقم الملف الموجود
{
    "guardian_identity": "123456789",
    "existing_file_id": "001234",
    "row": 3
}
```

### عند نجاح الاستيراد

```
✅ تم استيراد الصف 5 بنجاح
{
    "guardian_identity": "456789123",
    "orphan_identity": "789123456",
    "internal_file_number": "005678",
    "generated_new_file_id": true
}
```

---

## ⚠️ ملاحظات مهمة

### 1. عدم إنشاء سجلات في data

- الأشخاص من `re_people` و `dead_people` **لا يتم نقلهم** إلى جدول `data`
- يتم توليد رقم ملف داخلي فقط لأغراض الكفالة
- الشخص يبقى في جدوله الأصلي

### 2. الأولوية في البحث

```
1. data.data_id_number       (أعلى أولوية)
2. re_people.re_id_number    (أولوية متوسطة)
3. dead_people.dead_id_number (أولوية منخفضة)
```

### 3. استخدام رقم الملف

- في `sponsorships.internal_file_number`: يُحفظ الرقم المولد أو الموجود
- في `guardian_bank_accounts.guardian_registration`: نفس الرقم للربط

### 4. عدم التكرار

- الدالة `generateUniqueReservedCode()` تضمن عدم تكرار الأرقام
- يتم الفحص في جدول `data` قبل توليد الرقم
- استخدام Transaction لضمان الأمان

---

## 🔒 الأمان والموثوقية

### Transaction Safety

```php
DB::beginTransaction();

try {
    // إنشاء الكفالة
    $sponsorship->save();
    
    // ربط المؤسسة
    $sponsorship->sponsors()->attach($request->sponsor_id);
    
    // إضافة البيانات البنكية
    if ($bankId) {
        $bankAccount->save();
    }
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### Logging

- جميع العمليات مسجلة في `storage/logs/laravel.log`
- يمكن تتبع كل عملية توليد رقم ملف
- تسجيل الأخطاء مع التفاصيل الكاملة

---

## 📈 إحصائيات الاستيراد

عند انتهاء عملية الاستيراد، يتم عرض:

```json
{
    "success": true,
    "message": "تمت عملية الاستيراد",
    "summary": {
        "total": 100,           // إجمالي الصفوف
        "success": 85,          // تم بنجاح
        "errors": 15            // فشلت
    },
    "errors": [
        "الصف 5: هوية المعيل غير موجودة",
        "الصف 12: فشل في توليد رقم ملف",
        ...
    ]
}
```

---

## 📞 الدعم الفني

في حالة وجود مشاكل:
1. التحقق من سجلات النظام (`storage/logs/laravel.log`)
2. البحث عن الرسائل التي تبدأ بـ `✅` أو `❌`
3. التأكد من وجود الأشخاص في قاعدة البيانات
4. التحقق من عمل دالة `generateUniqueReservedCode()`

---

**تاريخ التحديث:** 8 ديسمبر 2025  
**الإصدار:** 2.0  
**الحالة:** ✅ مطبق ومفعّل
