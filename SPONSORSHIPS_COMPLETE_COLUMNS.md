# تحديث ميزة استيراد/تصدير الكفالات - جميع الأعمدة

## 📊 التحديث الرئيسي

تم تحديث نظام استيراد وتصدير الكفالات ليشمل **جميع الأعمدة** من جدولي `sponsorships` و `guardian_bank_accounts`.

---

## 🆕 الأعمدة الجديدة المضافة

### 1. أعمدة جدول Sponsorships

| العمود | الوصف | نوع البيانات | ملاحظات |
|--------|-------|---------------|----------|
| `guardian_identity_number` | رقم هوية ولي الأمر/المعيل | نص | **مطلوب** - يُستخدم للبحث في جدول data |
| `identity_number` | رقم هوية اليتيم | نص | اختياري - رقم هوية الشخص المكفول |
| `internal_file_number` | رقم الملف الداخلي | نص | اختياري |
| `external_file_number` | رقم الملف الخارجي | نص | اختياري |
| `sponsoring_organization` | المؤسسة الراعية | نص | اختياري |
| `sponsorship_duration_months` | مدة الكفالة بالأشهر | رقم | اختياري |
| `monthly_amount` | المبلغ الشهري | رقم عشري | اختياري |
| `notes` | ملاحظات | نص | اختياري - استُبدل `guardian_notes` |

### 2. أعمدة جدول Guardian Bank Accounts

| العمود | الوصف | نوع البيانات | ملاحظات |
|--------|-------|---------------|----------|
| `guardian_registration` | رقم تسجيل المعيل | نص | **مهم جداً** - يربط بـ file_id_number من جدول data |
| `re_id_number` | رقم هوية اليتيم | نص | اختياري - يُملأ من عمود B |
| `account_number_or_related_phone_number` | رقم الحساب أو الهاتف | نص | اختياري |

---

## 🔄 التغييرات في الملفات

### 1. `SponsorshipController.php` - دالة `import()`

#### التغيير الرئيسي: ترتيب الأعمدة

**قبل:**
```php
$identityNumber = trim($row[0] ?? '');  // A - الرقم الوطني (كان واحد فقط)
$orphanName = trim($row[1] ?? '');      // B
$guardianName = trim($row[2] ?? '');    // C
$bankName = trim($row[3] ?? '');        // D
// ... 7 أعمدة إضافية فقط
```

**بعد:**
```php
$guardianIdentityNumber = trim($row[0] ?? ''); // A - رقم هوية ولي الأمر (مطلوب)
$identityNumber = trim($row[1] ?? '');         // B - رقم هوية اليتيم
$orphanName = trim($row[2] ?? '');             // C
$guardianName = trim($row[3] ?? '');           // D
$internalFileNumber = trim($row[4] ?? '');     // E - جديد
$externalFileNumber = trim($row[5] ?? '');     // F - جديد
$sponsoringOrganization = trim($row[6] ?? ''); // G - جديد
$startDate = $row[7] ?? null;                  // H
$endDate = $row[8] ?? null;                    // I
$durationMonths = $row[9] ?? null;             // J - جديد
$monthlyAmount = $row[10] ?? null;             // K
$notes = trim($row[11] ?? '');                 // L
$bankName = trim($row[12] ?? '');              // M
$ibanUsd = trim($row[13] ?? '');               // N
$ibanShekel = trim($row[14] ?? '');            // O
$phoneNumber = trim($row[15] ?? '');           // P
$accountNumber = trim($row[16] ?? '');         // Q - جديد
```

#### التغيير في البحث عن الشخص

**قبل:**
```php
$person = Data::where('data_id_number', $identityNumber)->first();
```

**بعد:**
```php
// البحث بواسطة رقم هوية المعيل وليس اليتيم
$person = Data::where('data_id_number', $guardianIdentityNumber)->first();
```

#### التغيير في إنشاء Sponsorship

**قبل:**
```php
$sponsorship->identity_number = $identityNumber;
$sponsorship->monthly_amount = $monthlyAmount;
$sponsorship->guardian_notes = $notes;
```

**بعد:**
```php
$sponsorship->guardian_identity_number = $guardianIdentityNumber;
$sponsorship->identity_number = $identityNumber ?: null;
$sponsorship->internal_file_number = $internalFileNumber ?: null;
$sponsorship->external_file_number = $externalFileNumber ?: null;
$sponsorship->sponsoring_organization = $sponsoringOrganization ?: null;
$sponsorship->sponsorship_duration_months = $durationMonths ?: null;
$sponsorship->monthly_amount = $monthlyAmount ?: null;
$sponsorship->notes = $notes ?: null;
```

#### التغيير في إنشاء GuardianBankAccount

**قبل:**
```php
$bankAccount->person_owner_identity_number = $identityNumber;
$bankAccount->bank_name = $bankId;
```

**بعد:**
```php
$bankAccount->guardian_registration = $person->file_id_number; // ربط بـ file_id
$bankAccount->person_owner_identity_number = $guardianIdentityNumber;
$bankAccount->re_id_number = $identityNumber ?: null;
$bankAccount->bank_name = $bankId;
$bankAccount->account_number_or_related_phone_number = $accountNumber ?: $phoneNumber;
```

---

### 2. `SponsorshipController.php` - دالة `export()`

#### تحديث رؤوس الأعمدة

**قبل:** 15 عمود (A-O)
```php
$headers = [
    '#', 'المؤسسة الكافلة', 'رقم ملف داخلي', 'رقم ملف خارجي',
    'الرقم الوطني', 'اسم اليتيم', 'اسم ولي الأمر',
    'تاريخ بدء الكفالة', 'تاريخ نهاية الكفالة',
    'نوع الكفالة', 'حالة الكفالة', 'المبلغ الشهري',
    'ملاحظات الولي', 'تم الإنشاء بواسطة', 'تاريخ الإنشاء'
];
```

**بعد:** 18 عمود (A-R)
```php
$headers = [
    '#', 'المؤسسة الكافلة', 'رقم ملف داخلي', 'رقم ملف خارجي',
    'رقم هوية ولي الأمر', 'رقم هوية اليتيم', 'اسم اليتيم', 'اسم ولي الأمر',
    'المؤسسة الراعية', 'تاريخ بدء الكفالة', 'تاريخ نهاية الكفالة',
    'مدة الكفالة (أشهر)', 'نوع الكفالة', 'حالة الكفالة',
    'المبلغ الشهري', 'ملاحظات', 'تم الإنشاء بواسطة', 'تاريخ الإنشاء'
];
$sheet->getStyle('A1:R1')->applyFromArray($headerStyle); // من O1 إلى R1
```

#### تحديث البيانات المُصدّرة

**بعد:**
```php
$data = [
    $sponsorship->id,
    $sponsorNames ?: '-',
    $sponsorship->internal_file_number ?: '-',
    $sponsorship->external_file_number ?: '-',
    $sponsorship->guardian_identity_number ?: '-',  // جديد
    $sponsorship->identity_number ?: '-',           // جديد
    $sponsorship->orphan_name ?: '-',
    $sponsorship->guardian_name ?: '-',
    $sponsorship->sponsoring_organization ?: '-',   // جديد
    // ... التواريخ
    $sponsorship->sponsorship_duration_months ?: '-', // جديد
    // ... الأنواع والحالات
    $sponsorship->monthly_amount ?: '-',
    $sponsorship->notes ?: '-',                     // تغيّر من guardian_notes
    // ... البيانات الإضافية
];
```

---

### 3. `create_import_template.php`

#### تحديث الرؤوس

**قبل:** 11 عمود (A-K)
**بعد:** 17 عمود (A-Q)

```php
$headers = [
    'رقم هوية ولي الأمر/المعيل *',
    'رقم هوية اليتيم',
    'اسم اليتيم',
    'اسم ولي الأمر/المعيل',
    'رقم الملف الداخلي',
    'رقم الملف الخارجي',
    'المؤسسة الراعية',
    'تاريخ بدء الكفالة',
    'تاريخ انتهاء الكفالة',
    'مدة الكفالة (بالأشهر)',
    'المبلغ الشهري',
    'ملاحظات',
    'اسم البنك',
    'IBAN بالدولار',
    'IBAN بالشيكل',
    'رقم الهاتف',
    'رقم الحساب/الهاتف المرتبط'
];
```

#### تحديث الأمثلة

```php
$exampleData = [
    [
        '123456789',            // A - هوية المعيل
        '987654321',            // B - هوية اليتيم
        'محمد أحمد علي',       // C
        'أحمد علي محمد',        // D
        'INT-2024-001',         // E
        'EXT-100',              // F
        'جمعية الحياة الخيرية', // G
        '2024-01-15',           // H
        '2025-01-15',           // I
        '12',                   // J
        '500',                  // K
        'كفالة شهرية منتظمة',   // L
        'بنك فلسطين',           // M
        'PS00PALS00000...',     // N
        'PS00PALS00000...',     // O
        '0599123456',           // P
        '0599123456'            // Q
    ],
    // ... صفوف إضافية
];
```

---

### 4. `SPONSORSHIPS_IMPORT_GUIDE.md`

تم تحديث:
- جدول الأعمدة (من 11 إلى 17)
- أمثلة الكود لتعكس التغييرات
- إضافة ملاحظات حول:
  - الفرق بين `data_id_number` و `file_id_number`
  - استخدام `guardian_registration` للربط

---

## ⚠️ ملاحظات هامة جداً

### 1. الفرق بين data_id_number و file_id_number

```
جدول data:
├── data_id_number      → رقم الهوية الفعلي (123456789)
└── file_id_number      → رقم تسجيل الملف (مثل: FLE-2024-001)

الاستخدام:
├── البحث عن الشخص     → data_id_number
└── الربط بالبيانات البنكية → file_id_number (guardian_registration)
```

### 2. البحث عن المعيل وليس اليتيم

**مهم:** الكفالة تُربط بالمعيل (ولي الأمر/الأب) وليس باليتيم مباشرة.

```php
// ✅ صحيح
$person = Data::where('data_id_number', $guardianIdentityNumber)->first();
$bankAccount->guardian_registration = $person->file_id_number;

// ❌ خطأ (الطريقة القديمة)
$person = Data::where('data_id_number', $identityNumber)->first();
```

### 3. الحقول المطلوبة vs الاختيارية

| نوع الحقل | الحقول |
|-----------|--------|
| **مطلوب** | `guardian_identity_number` فقط |
| **اختياري** | جميع الأعمدة الأخرى (16 عمود) |

---

## ✅ اختبار التحديثات

### الخطوات:

1. **إعادة إنشاء القالب:**
   ```bash
   php create_import_template.php
   ```

2. **تحميل القالب الجديد:**
   - افتح صفحة الكفالات
   - اضغط "رفع ملف Excel"
   - اضغط "تحميل القالب"

3. **اختبار الاستيراد:**
   - املأ البيانات في القالب
   - ارفع الملف
   - تحقق من:
     - إدخال جميع البيانات في جدول `sponsorships`
     - إدخال البيانات البنكية في `guardian_bank_accounts`
     - الربط الصحيح بـ `file_id_number`

4. **اختبار التصدير:**
   - اضغط زر "تصدير إلى Excel"
   - تحقق من وجود 18 عمود
   - تحقق من صحة البيانات

---

## 📈 ملخص الإحصائيات

| المقياس | قبل | بعد | الزيادة |
|---------|-----|-----|---------|
| أعمدة الاستيراد | 11 | 17 | +6 (55%) |
| أعمدة التصدير | 15 | 18 | +3 (20%) |
| أعمدة sponsorships | 7 | 14 | +7 (100%) |
| أعمدة guardian_bank_accounts | 4 | 8 | +4 (100%) |

---

## 🎯 الهدف المحقق

✅ **جميع الأعمدة من جدولي `sponsorships` و `guardian_bank_accounts` مشمولة الآن**

- ✅ جميع حقول Sponsorship (14 حقل)
- ✅ جميع حقول Guardian Bank Account (8 حقول)
- ✅ الربط الصحيح بين الجداول
- ✅ القالب المحدث (17 عمود)
- ✅ التصدير الكامل (18 عمود مع ID)
- ✅ التوثيق الشامل

---

## 📝 الملفات المحدثة

1. `app/Http/Controllers/Admin/SponsorshipController.php`
   - `import()` - سطر 838-1019
   - `export()` - سطر 675-833

2. `create_import_template.php`
   - رؤوس الأعمدة (سطر 28-45)
   - الأمثلة (سطر 69-73)
   - عرض الأعمدة (سطر 99-117)

3. `SPONSORSHIPS_IMPORT_GUIDE.md`
   - جدول الأعمدة
   - أمثلة الكود
   - الملاحظات التقنية

4. `SPONSORSHIPS_COMPLETE_COLUMNS.md` (جديد)
   - هذا الملف - توثيق شامل للتحديثات

---

## 🔄 آخر تحديث

**التاريخ:** 2024-12-06  
**النسخة:** 2.0  
**المطور:** ASO System

---
