# نظام منع تكرار الحسابات البنكية

## نظرة عامة
تم تطبيق نظام شامل لمنع تكرار الحسابات البنكية عبر جميع بوابات النظام. هذا النظام يضمن عدم إدخال نفس الحساب البنكي لنفس الشخص أكثر من مرة.

## معايير التحقق من التكرار
يتم التحقق من التكرار بناءً على **4 معايير أساسية**:

### 1. رقم الملف (guardian_registration)
- رقم الملف الموحد للمعيل أو الشخص
- يتم استخراجه من جدول `data` باستخدام `file_id_number`
- **مطلوب دائماً** للتحقق من التكرار

### 2. رقم الهوية (re_id_number)
- رقم هوية صاحب الحساب البنكي
- قد يكون رقم هوية المعيل أو أحد أفراد العائلة
- **مطلوب دائماً** للتحقق من التكرار

### 3. رقم الهاتف (re_phone_number)
- رقم الهاتف المرتبط بالحساب البنكي
- **اختياري** - يضيف دقة إضافية للتحقق

### 4. اسم البنك (bank_name)
- معرف البنك من جدول `bank_names`
- **اختياري** - يضيف دقة إضافية للتحقق

## آلية عمل النظام

### الخدمة المركزية
تم إنشاء `BankAccountValidationService` في:
```
app/Services/BankAccountValidationService.php
```

#### الوظائف الرئيسية:

1. **checkDuplicateBankAccount()**
   - التحقق من حساب بنكي واحد
   - يقبل معرف استثناء للتحديث
   - يرجع تفاصيل الحساب المكرر إن وُجد

2. **checkMultipleBankAccounts()**
   - التحقق من مجموعة حسابات بنكية دفعة واحدة
   - يرجع قائمة بجميع الحسابات المكررة

3. **quickDuplicateCheck()**
   - تحقق سريع باستخدام المعايير الأساسية فقط
   - مفيد للتحقق الأولي

4. **getExistingAccounts()**
   - الحصول على جميع الحسابات البنكية لشخص معين
   - مرتبة حسب تاريخ الإنشاء

## نقاط التطبيق

### 1. بوابة الأشخاص المكفولين (SponsorshipController)

#### أ. إضافة كفالة جديدة (store)
```php
// الموقع: app/Http/Controllers/Admin/SponsorshipController.php
// السطر: ~128-220

$bankValidationService = app(BankAccountValidationService::class);
$duplicateErrors = [];

foreach ($request->bank_accounts as $index => $account) {
    // التحقق من التكرار
    $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
        'guardian_registration' => $guardianFileId,
        're_phone_number' => $account['re_phone_number'] ?? null,
        'bank_name' => $account['bank_name'] ?? null,
        're_id_number' => $accountIdNumber
    ]);
    
    if ($duplicateCheck['is_duplicate']) {
        // تسجيل وتجاوز الحساب المكرر
        continue;
    }
    
    // حفظ الحساب
}
```

#### ب. تعديل كفالة (update)
```php
// الموقع: app/Http/Controllers/Admin/SponsorshipController.php
// السطر: ~380-475

// نفس الآلية مع إضافة excludeId للسماح بتعديل الحساب نفسه
$duplicateCheck = $bankValidationService->checkDuplicateBankAccount([...], $excludeId);
```

#### ج. استيراد من Excel (import)
```php
// الموقع: app/Http/Controllers/Admin/SponsorshipController.php
// السطر: ~1537-1565

// التحقق أثناء الاستيراد
if ($bankId) {
    $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([...]);
    
    if (!$duplicateCheck['is_duplicate']) {
        // حفظ الحساب
    } else {
        // تسجيل تحذير فقط
    }
}
```

### 2. بوابة التسجيل العام (GeneralRegistrationController)

#### التسجيل الجديد (store)
```php
// الموقع: app/Http/Controllers/Users/GeneralRegistrationController.php
// السطر: ~208-239

$bankValidationService = app(BankAccountValidationService::class);

foreach ($bankAccounts as $index => $bankAccount) {
    // التحقق من التكرار
    $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
        'guardian_registration' => $fileIdNumber,
        're_phone_number' => $bankAccount['re_phone_number'] ?? null,
        'bank_name' => $bankAccount['bank_name'] ?? null,
        're_id_number' => $accountIdNumber
    ]);
    
    if ($duplicateCheck['is_duplicate']) {
        // تسجيل وتجاوز
        continue;
    }
    
    // حفظ الحساب
}
```

### 3. بوابة إدارة التسجيلات (RecordsManagementController)

#### إضافة سجل جديد (store)
```php
// الموقع: app/Http/Controllers/Admin/RecordsManagementController.php
// السطر: ~183-210

$bankValidationService = app(BankAccountValidationService::class);

foreach ($bankAccounts as $index => $bankAccount) {
    // التحقق من التكرار
    $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
        'guardian_registration' => $fileIdNumber,
        're_phone_number' => $bankAccount['re_phone_number'] ?? null,
        'bank_name' => $bankAccount['bank_name'] ?? null,
        're_id_number' => $accountIdNumber
    ]);
    
    if ($duplicateCheck['is_duplicate']) {
        // تسجيل وتجاوز
        continue;
    }
    
    // حفظ الحساب
}
```

## سلوك النظام

### عند اكتشاف تكرار:
1. **تسجيل في Log**
   - يتم تسجيل تفصيلي في ملف الـ Log
   - يحتوي على تفاصيل الحساب المكرر والموجود

2. **تجاوز الحساب**
   - لا يتم حفظ الحساب المكرر
   - العملية تستمر للحسابات الأخرى

3. **إشعار المستخدم**
   - رسالة تحذير في الواجهة
   - تحتوي على تفاصيل الحسابات المكررة

### رسائل التحذير

#### في الواجهات (sponsored.blade.php):
```javascript
if (response.info && response.info.bank_duplicates && response.info.bank_duplicates.length > 0) {
    icon = 'warning';
    message += '\n\n⚠️ تم تجاهل الحسابات البنكية المكررة التالية:\n\n';
    response.info.bank_duplicates.forEach(function(dup) {
        message += `${dup.index}. ${dup.message}\n\n`;
    });
}
```

#### محتوى رسالة التكرار:
```
يوجد حساب بنكي مسجل مسبقاً بنفس البيانات:
- رقم الملف: 000123
- رقم الهوية: 123456789
- رقم الهاتف: 0591234567
- اسم البنك: بنك فلسطين
- رقم الآيبان: PS12BANK12345678901234567890
- تاريخ التسجيل: 2024-01-15 10:30:00
```

## استعلام قاعدة البيانات

### الاستعلام المستخدم:
```sql
SELECT * FROM guardian_bank_accounts 
WHERE guardian_registration = ? 
  AND re_id_number = ?
  AND re_phone_number = ?  -- اختياري
  AND bank_name = ?         -- اختياري
  AND id != ?               -- للتحديث فقط
LIMIT 1
```

## ملاحظات مهمة

### 1. التعديل مقابل الإضافة
- **الإضافة الجديدة**: لا يُسمح بالتكرار إطلاقاً
- **التعديل**: يُستثنى الحساب المُعدَّل نفسه من التحقق

### 2. الأداء
- استعلام واحد لكل حساب بنكي
- استخدام indexes على الأعمدة:
  - `guardian_registration`
  - `re_id_number`
  - `bank_name`

### 3. التوافق العكسي
- النظام لا يحذف أو يعدل البيانات القديمة
- يمنع فقط الإدخال الجديد المكرر

### 4. Log Files
يتم تسجيل جميع العمليات في:
```
storage/logs/laravel.log
```

مع رموز تعبيرية للتمييز:
- 🔍 = بدء التحقق
- ✅ = نجاح العملية
- ⚠️ = تحذير (تكرار)
- ❌ = خطأ

## الاختبار

### سيناريوهات الاختبار:

#### 1. إضافة حساب بنكي جديد
```
✅ يجب أن ينجح إذا كانت البيانات فريدة
✅ يجب أن يُرفض إذا كانت البيانات مكررة
```

#### 2. تعديل حساب موجود
```
✅ يجب أن ينجح تعديل نفس الحساب
✅ يجب أن يُرفض التعديل إلى بيانات مكررة لحساب آخر
```

#### 3. استيراد من Excel
```
✅ يجب تجاوز الحسابات المكررة وإكمال الاستيراد
✅ يجب إظهار تقرير بالحسابات المكررة
```

#### 4. التسجيل العام
```
✅ يجب منع المستخدم من إدخال حساب مكرر
✅ يجب إظهار رسالة واضحة عن سبب الرفض
```

## الصيانة المستقبلية

### إضافة معيار تحقق جديد:
1. تعديل `checkDuplicateBankAccount()` في `BankAccountValidationService`
2. إضافة المعيار في استعلام WHERE
3. تحديث رسالة التكرار في `buildDuplicateMessage()`

### تغيير سلوك النظام:
- تعديل في مكان واحد: `BankAccountValidationService`
- التغيير يطبق تلقائياً على جميع البوابات

## دعم فني

### عند حدوث مشكلة:
1. فحص ملف Log: `storage/logs/laravel.log`
2. البحث عن: `🔍 بدء التحقق من تكرار الحساب البنكي`
3. فحص البيانات المرسلة والنتيجة

### أخطاء شائعة:
- **guardian_registration فارغ**: التحقق من جدول data
- **re_id_number فارغ**: التحقق من البيانات المرسلة
- **false positive**: مراجعة شروط التحقق

## الخلاصة
النظام يوفر:
- ✅ حماية شاملة من التكرار
- ✅ مرونة في التعديل
- ✅ شفافية للمستخدم
- ✅ تسجيل تفصيلي
- ✅ سهولة الصيانة

تاريخ التطبيق: 2024-12-11
الإصدار: 1.0
