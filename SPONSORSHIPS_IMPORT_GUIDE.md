# دليل استيراد الكفالات من Excel

## 📋 نظرة عامة

تتيح هذه الميزة استيراد بيانات الكفالات بشكل مجمّع من ملفات Excel، مع دعم:
- ✅ إدخال البيانات إلى جدولي `sponsorships` و `guardian_bank_accounts`
- ✅ التحقق من وجود الأشخاص في قاعدة البيانات
- ✅ دعم التطبيع (Normalization) لأسماء البنوك
- ✅ قائمة الأشخاص المعلقين (بدون ملفات)
- ✅ تقارير تفصيلية عن النجاح والفشل

---

## 🚀 كيفية الاستخدام

### الخطوة 1: تحضير ملف Excel

1. تحميل القالب من زر "تحميل القالب" في المودال
2. ملء البيانات حسب الأعمدة المطلوبة
3. التأكد من صحة الأرقام الوطنية وأسماء البنوك

### الخطوة 2: رفع الملف

1. الضغط على زر "رفع ملف Excel" (أصفر)
2. اختيار **المؤسسة الكافلة** (مطلوب)
3. اختيار **نوع الكفالة** (مطلوب)
4. اختيار **حالة الكفالة** (مطلوب)
5. اختيار ملف Excel
6. الضغط على "بدء الاستيراد"

### الخطوة 3: متابعة النتائج

بعد الاستيراد، ستظهر رسالة تحتوي على:
- **إجمالي السجلات**: عدد الصفوف في الملف
- **تم الإدخال بنجاح**: السجلات التي تمت إضافتها
- **أخطاء**: السجلات التي فشلت
- **بحاجة إلى ملفات**: الأشخاص غير الموجودين في قاعدة البيانات

---

## 📊 هيكل ملف Excel

### الأعمدة المطلوبة (بالترتيب):

| # | العمود | اسم العمود في Excel | مطلوب | نوع البيانات | مثال | ملاحظات |
|---|--------|---------------------|-------|---------------|------|----------|
| A | **هوية المعيل** | **هوية المعيل*** | ✅ نعم | رقم | 123456789 | رقم هوية ولي الأمر - يجب أن يكون موجوداً في جدول `data` (data_id_number) |
| B | رقم هوية اليتيم | هوية اليتيم | ❌ لا | رقم | 987654321 | رقم هوية الشخص المكفول |
| C | اسم اليتيم | اسم اليتيم | ❌ لا | نص | محمد أحمد علي | اختياري |
| D | اسم المعيل | اسم المعيل | ❌ لا | نص | أحمد علي محمد | اسم ولي الأمر |
| E | رقم الملف الداخلي | رقم الملف الداخلي | ❌ لا | نص | INT-2024-001 | رقم الملف الداخلي للكفالة |
| F | رقم الملف الخارجي | رقم الملف الخارجي | ❌ لا | نص | EXT-100 | رقم الملف الخارجي من المؤسسة |
| G | المؤسسة الراعية | المؤسسة الراعية | ❌ لا | نص | جمعية الحياة | اسم المؤسسة التي تقدم الكفالة |
| H | تاريخ بدء الكفالة | تاريخ بدء الكفالة | ❌ لا | تاريخ | 2024-01-15 | تنسيق: YYYY-MM-DD |
| I | تاريخ انتهاء الكفالة | تاريخ انتهاء الكفالة | ❌ لا | تاريخ | 2025-01-15 | تنسيق: YYYY-MM-DD |
| J | مدة الكفالة (بالأشهر) | مدة الكفالة (بالأشهر) | ❌ لا | رقم | 12 | عدد الأشهر |
| K | المبلغ الشهري | المبلغ الشهري | ❌ لا | رقم | 500 | رقم فقط بدون فواصل |
| L | ملاحظات | ملاحظات | ❌ لا | نص | ملاحظات عامة | نص حر |
| M | **المحفظة (البنك)** | **المحفظة** | ❌ لا | نص | بنك فلسطين | **اسم البنك** - يجب أن يكون مسجلاً في `bank_names` |
| N | IBAN بالدولار | IBAN بالدولار | ❌ لا | نص | PS00PALS000000... | اختياري |
| O | IBAN بالشيكل | IBAN بالشيكل | ❌ لا | نص | PS00PALS000000... | اختياري |
| P | رقم الهاتف | رقم الهاتف | ❌ لا | رقم | 0599123456 | اختياري |
| Q | رقم الحساب/الهاتف المرتبط | رقم الحساب/الهاتف المرتبط | ❌ لا | نص | 0599123456 | رقم الحساب أو الهاتف المرتبط |

### ⚠️ ملاحظات هامة جداً:

1. **عمود A "هوية المعيل"**: هذا هو العمود الأساسي الذي يُستخدم للتحقق من وجود الشخص في قاعدة البيانات
2. **عمود M "المحفظة"**: يعني **اسم البنك** الذي يتعامل معه المعيل (مثل: بنك فلسطين، بنك القدس)
3. **لا تخلط**: "هوية المعيل" ≠ "هوية اليتيم" - كل واحد في عمود منفصل

### مثال على صف بيانات:

```
123456789 | 987654321 | محمد أحمد | أحمد محمد | INT-001 | EXT-100 | جمعية الحياة | 2024-01-15 | 2025-01-15 | 12 | 500 | ملاحظات | بنك فلسطين | PS00... | PS00... | 0599123456 | 0599123456
```

---

## 🔍 عملية المعالجة

### 1. التحقق من الصحة (Validation)

```php
// التحقق من وجود الملف والفلاتر
- ملف Excel (xlsx أو xls)
- حجم أقصى: 10MB
- المؤسسة الكافلة (sponsor_id)
- نوع الكفالة (sponsorship_type_id)
- حالة الكفالة (sponsorship_status_id)
```

### 2. قراءة البيانات

```php
// قراءة ملف Excel
$spreadsheet = IOFactory::load($file);
$rows = $spreadsheet->getActiveSheet()->toArray();

// إزالة صف الرؤوس
array_shift($rows);
```

### 3. معالجة كل صف

#### أ. البحث عن الشخص

```php
// البحث عن المعيل في جدول data (بواسطة رقم هوية ولي الأمر)
$person = Data::where('data_id_number', $guardianIdentityNumber)->first();

if (!$person) {
    // إضافة للقائمة المعلقة (Pending)
    $pendingPersons[] = [
        'guardian_identity_number' => $guardianIdentityNumber,
        'identity_number' => $identityNumber,
        'orphan_name' => $orphanName,
        'guardian_name' => $guardianName,
        'internal_file_number' => $internalFileNumber,
        'external_file_number' => $externalFileNumber,
        // ... باقي البيانات
    ];
    continue; // تخطي هذا الصف
}
```

#### ب. التحقق من البنك (Normalization)

```php
// تطبيع اسم البنك للبحث
$normalizedBankName = normalizeArabicText($bankName);
// استبدال: أ → ا، إ → ا، آ → ا، ة → ه

$bank = BankName::whereRaw(
    'REPLACE(REPLACE(REPLACE(description, "أ", "ا"), "إ", "ا"), "آ", "ا") LIKE ?',
    ["%{$normalizedBankName}%"]
)->first();

if (!$bank) {
    $missingBanks[] = $bankName; // إضافة للقائمة المفقودة
    continue;
}
```

#### ج. إنشاء سجل الكفالة

```php
DB::beginTransaction();

$sponsorship = new Sponsorship();
$sponsorship->identity_number = $identityNumber ?: null;
$sponsorship->orphan_name = $orphanName ?: null;
$sponsorship->guardian_name = $guardianName ?: null;
$sponsorship->guardian_identity_number = $guardianIdentityNumber;
$sponsorship->internal_file_number = $internalFileNumber ?: null;
$sponsorship->external_file_number = $externalFileNumber ?: null;
$sponsorship->sponsoring_organization = $sponsoringOrganization ?: null;
$sponsorship->sponsorship_type_id = $request->sponsorship_type_id;
$sponsorship->sponsorship_status_id = $request->sponsorship_status_id;
$sponsorship->sponsorship_start_date = $startDate;
$sponsorship->sponsorship_end_date = $endDate;
$sponsorship->sponsorship_duration_months = $durationMonths ?: null;
$sponsorship->monthly_amount = $monthlyAmount ?: null;
$sponsorship->notes = $notes ?: null;
$sponsorship->created_by = auth()->id();
$sponsorship->save();

// ربط بالمؤسسة الكافلة
$sponsorship->sponsors()->attach($request->sponsor_id);
```

#### د. إضافة البيانات البنكية

```php
// إضافة البيانات البنكية إذا كانت متوفرة
if ($bankId && (!empty($ibanUsd) || !empty($ibanShekel) || !empty($accountNumber))) {
    $bankAccount = new GuardianBankAccount();
    
    // ربط بـ file_id_number من جدول data (وليس data_id_number)
    $bankAccount->guardian_registration = $person->file_id_number;
    
    $bankAccount->person_owner_identity_number = $guardianIdentityNumber;
    $bankAccount->re_id_number = $identityNumber ?: null; // رقم هوية اليتيم
    $bankAccount->re_guardian_name = $guardianName ?: null;
    $bankAccount->bank_name = $bankId;
    $bankAccount->iban_usd = $ibanUsd ?: null;
    $bankAccount->iban_shekel = $ibanShekel ?: null;
    $bankAccount->re_phone_number = $phoneNumber ?: null;
    $bankAccount->account_number_or_related_phone_number = $accountNumber ?: $phoneNumber;
    $bankAccount->save();
}

DB::commit();
```

**ملاحظة مهمة:**
- `guardian_registration` يُربط بـ `file_id_number` من جدول `data` وليس `data_id_number`
- يتم البحث عن المعيل بواسطة `data_id_number` لكن الربط في جدول البنوك يستخدم `file_id_number`

### 4. إعداد النتيجة

```php
$result = [
    'success' => true,
    'message' => 'تمت عملية الاستيراد',
    'summary' => [
        'total' => count($rows),
        'success' => $successCount,
        'errors' => $errorCount,
        'pending' => count($pendingPersons),
    ],
    'pending_persons' => $pendingPersons,
    'missing_banks' => $missingBanks,
    'errors' => $errors,
];
```

---

## ⚠️ الحالات الخاصة

### 1. شخص غير موجود في قاعدة البيانات

**المشكلة:** الرقم الوطني غير موجود في جدول `data`

**الحل:**
1. يتم إضافة الشخص إلى قائمة "الأشخاص بحاجة إلى إنشاء ملفات"
2. تظهر نافذة منبثقة تعرض القائمة
3. بجانب كل شخص زر "إنشاء ملف"
4. الضغط على الزر ينقل إلى صفحة إدخال البيانات مع pre-fill للمعلومات المتوفرة

**رابط إنشاء الملف:**
```
/admin/records-management?identity={رقم_الهوية}&name={اسم_الولي}
```

### 2. بنك غير موجود

**المشكلة:** اسم البنك غير موجود في جدول `bank_names`

**الحل:**
1. يتم جمع أسماء البنوك المفقودة
2. بعد الاستيراد، تظهر رسالة تحذيرية بالقائمة
3. يجب إضافة البنوك من قسم "إدارة البنوك" أولاً
4. إعادة رفع الملف بعد إضافة البنوك

### 3. أخطاء في التنسيق

**المشكلة:** تاريخ أو رقم بتنسيق خاطئ

**الحل:**
- يتم تخطي الصف وإضافته لقائمة الأخطاء
- تظهر رسالة توضح رقم الصف والخطأ
- يمكن مراجعة الأخطاء في Console و Logs

---

## 🗂️ الجداول المتأثرة

### 1. جدول `sponsorships`

```sql
INSERT INTO sponsorships (
    identity_number,
    orphan_name,
    guardian_name,
    sponsorship_type_id,
    sponsorship_status_id,
    sponsorship_start_date,
    sponsorship_end_date,
    monthly_amount,
    guardian_notes,
    created_by,
    created_at,
    updated_at
) VALUES (...)
```

### 2. جدول `sponsorship_sponsor` (Many-to-Many)

```sql
INSERT INTO sponsorship_sponsor (
    sponsorship_id,
    sponsor_id
) VALUES (...)
```

### 3. جدول `guardian_bank_accounts`

```sql
INSERT INTO guardian_bank_accounts (
    person_owner_identity_number,
    re_guardian_name,
    bank_name,
    iban_usd,
    iban_shekel,
    re_phone_number,
    created_at,
    updated_at
) VALUES (...)
```

---

## 🔧 التعامل مع الأخطاء

### أخطاء شائعة وحلولها:

#### ❌ "الرقم الوطني مطلوب"
**السبب:** العمود A فارغ  
**الحل:** تعبئة الرقم الوطني لجميع الصفوف

#### ❌ "الشخص غير موجود"
**السبب:** الرقم الوطني غير مسجل في النظام  
**الحل:** إنشاء ملف للشخص أولاً

#### ❌ "البنك غير موجود"
**السبب:** اسم البنك غير مطابق  
**الحل:** 
- التأكد من كتابة اسم البنك بالضبط
- استخدام التطبيع (أ، إ، آ = ا)
- إضافة البنك من قسم إدارة البنوك

#### ❌ "خطأ في تنسيق التاريخ"
**السبب:** تنسيق التاريخ غير صحيح  
**الحل:** استخدام `YYYY-MM-DD` (مثل: 2024-12-06)

#### ❌ "حجم الملف كبير جداً"
**السبب:** الملف أكبر من 10MB  
**الحل:** تقسيم الملف إلى عدة ملفات أصغر

---

## 📈 مثال كامل

### ملف Excel (3 صفوف):

| الرقم الوطني | اسم اليتيم | اسم ولي الأمر | اسم البنك | IBAN USD | IBAN ILS | الهاتف | تاريخ البداية | تاريخ النهاية | المبلغ | ملاحظات |
|--------------|-----------|---------------|-----------|----------|----------|---------|---------------|---------------|--------|----------|
| 123456789 | محمد أحمد | أحمد محمد | بنك فلسطين | PS00PALS000... | PS00PALS000... | 0599123456 | 2024-01-15 | 2025-01-15 | 500 | كفالة شهرية |
| 987654321 | فاطمة خالد | خالد محمود | بنك القدس | PS00ALQD000... | PS00ALQD000... | 0598765432 | 2024-02-01 | 2025-02-01 | 600 | كفالة سنوية |
| 456789123 | سارة عبدالله | عبدالله حسن | بنك الاستثمار | | PS00TICO000... | 0597654321 | 2024-03-10 | | 450 | |

### النتيجة المتوقعة:

**السيناريو 1: الجميع موجودون**
```json
{
  "success": true,
  "summary": {
    "total": 3,
    "success": 3,
    "errors": 0,
    "pending": 0
  }
}
```

**السيناريو 2: شخص واحد غير موجود**
```json
{
  "success": true,
  "summary": {
    "total": 3,
    "success": 2,
    "errors": 0,
    "pending": 1
  },
  "pending_persons": [
    {
      "identity_number": "456789123",
      "orphan_name": "سارة عبدالله",
      "guardian_name": "عبدالله حسن",
      ...
    }
  ]
}
```

**السيناريو 3: بنك مفقود**
```json
{
  "success": true,
  "summary": {
    "total": 3,
    "success": 2,
    "errors": 1,
    "pending": 0
  },
  "missing_banks": ["بنك الاستثمار"],
  "errors": ["الصف 4: البنك 'بنك الاستثمار' غير موجود في النظام"]
}
```

---

## 🔐 الأمان والصلاحيات

### التحقق من الصحة (Validation):
- ✅ نوع الملف: Excel فقط (xlsx, xls)
- ✅ الحجم: أقصى 10MB
- ✅ الحقول المطلوبة: sponsor_id, type_id, status_id
- ✅ وجود المستخدم: auth()->id()

### التعاملات (Transactions):
```php
DB::beginTransaction();
try {
    // إنشاء الكفالة
    // إضافة البيانات البنكية
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
}
```

### السجلات (Logging):
```php
Log::info('🎯 بدء عملية استيراد الكفالات');
Log::info('✅ تم استيراد الصف X بنجاح');
Log::error('❌ خطأ في استيراد الصف X');
```

---

## 📱 واجهة المستخدم

### الأزرار:
1. **رفع ملف Excel** (أصفر): فتح مودال الاستيراد
2. **تصدير Excel** (أخضر): تصدير البيانات
3. **إضافة كفالة جديدة** (أزرق): إضافة يدوية

### المودالات:
1. **مودال الاستيراد**: رفع الملف واختيار الفلاتر
2. **مودال الأشخاص المعلقين**: عرض الذين يحتاجون إلى ملفات
3. **مودال النتيجة**: ملخص العملية

### رسائل SweetAlert2:
- 🔄 **جاري الرفع**: مع شريط تقدم
- ✅ **نجاح**: مع ملخص النتائج
- ❌ **خطأ**: مع تفاصيل المشكلة
- ⚠️ **تحذير**: للبنوك المفقودة

---

## 🛠️ الملفات المعنية

### Backend:
- `SponsorshipController.php` → `import()` method
- `routes/admin.php` → `POST /sponsorships/import`

### Frontend:
- `sponsored.blade.php` → مودالات و JavaScript
- `public/templates/sponsorships_import_template.xlsx` → القالب

### Database:
- `sponsorships` table
- `sponsorship_sponsor` pivot table
- `guardian_bank_accounts` table
- `data` table (للتحقق)
- `bank_names` table (للتحقق)

---

## 📝 ملاحظات إضافية

### أفضل الممارسات:
1. ✅ اختبر بملف صغير (5-10 صفوف) أولاً
2. ✅ تحقق من البيانات قبل الرفع
3. ✅ احتفظ بنسخة احتياطية من الملف
4. ✅ أضف البنوك المفقودة قبل الاستيراد
5. ✅ راجع الـ Logs بعد كل عملية

### التحسينات المستقبلية:
- ⏳ دعم رفع ملفات CSV
- ⏳ معاينة البيانات قبل الاستيراد
- ⏳ استيراد تدريجي للملفات الكبيرة (Queue)
- ⏳ تقارير Excel تفصيلية بالأخطاء
- ⏳ إمكانية التراجع عن الاستيراد (Undo)

---

**تاريخ التحديث:** 2024-12-06  
**الإصدار:** 1.0.0  
**المطور:** نظام الحياة للأيتام
