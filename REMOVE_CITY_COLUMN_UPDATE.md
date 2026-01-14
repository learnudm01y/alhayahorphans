# إزالة عمود المحافظة وتحسين عرض العنوان التفصيلي
## 📅 التاريخ: 14 يناير 2026

## 🎯 الهدف من التحديث

إزالة عمود المحافظة (guardian_city_id) والاعتماد فقط على العنوان التفصيلي (guardian_detailed_address) الذي يُعرض من:
- جدول `data` → عمود `data_current_address` (للأحياء: breadwinner, family_member, orphan)
- جدول `portal_general_registration_field_values` (للمتوفين: deceased_father, deceased_mother)

## ✅ التعديلات المنفذة

### 1️⃣ Frontend - mobile-app/dist/detail.html

#### التغييرات:
- ✅ حذف حقل المدينة (guardian_city_id) من واجهة المستخدم
- ✅ توسيع حقل العنوان التفصيلي من صفين إلى 3 صفوف
- ✅ إزالة populateSelect للمدينة
- ✅ إزالة setValue للمدينة
- ✅ إزالة guardian_city_id من شرط حفظ بيانات المعيل

#### الكود المحذوف:
```html
<!-- تم حذف هذا الحقل -->
<div class="form-field"><label class="form-label">المدينة</label>
    <select class="form-select" id="guardian_city_id" data-field="guardian_city_id">
        <option value="">-- اختر المدينة --</option>
    </select>
</div>
```

#### الكود المحدّث:
```html
<!-- العنوان التفصيلي فقط - موسّع -->
<div class="form-field">
    <label class="form-label">العنوان التفصيلي</label>
    <textarea class="form-input" id="guardian_detailed_address" 
              data-field="guardian_detailed_address" 
              rows="3" 
              style="resize: vertical;"></textarea>
</div>
```

### 2️⃣ Backend - SponsorshipSyncController.php

#### التغييرات في جلب البيانات:

**السطور 920-926 - حذف جلب المدينة من portal_general_registration_field_values:**
```php
// تم حذف هذا الكود:
// if (isset($portalFields['guardian_city_id'])) {
//     $result['guardian_city_id'] = $portalFields['guardian_city_id']->field_value ?? '';
// }
```

#### التغييرات في حفظ البيانات:

**السطر 1199 - تحديث dataOnlyFields:**
```php
// قبل:
$dataOnlyFields = [
    'health_status_id', 'guardian_phone', 'guardian_phone2',
    'guardian_city_id', 'guardian_detailed_address', // ❌ guardian_city_id محذوف
    ...
];

// بعد:
$dataOnlyFields = [
    'health_status_id', 'guardian_phone', 'guardian_phone2',
    'guardian_detailed_address', // ✅ فقط العنوان التفصيلي
    ...
];
```

**السطر 1269 - تحديث hasGuardianContactUpdate:**
```php
// قبل:
$hasGuardianContactUpdate = isset($updates['guardian_phone']) || 
                            isset($updates['guardian_phone2']) ||
                            isset($updates['guardian_detailed_address']) || 
                            isset($updates['guardian_city_id']) || // ❌ محذوف
                            ...

// بعد:
$hasGuardianContactUpdate = isset($updates['guardian_phone']) || 
                            isset($updates['guardian_phone2']) ||
                            isset($updates['guardian_detailed_address']) || // ✅ فقط
                            ...
```

**السطور 1282-1284 - تحديث Log:**
```php
// تم حذف guardian_city_id من log:
Log::info('🔍 فحص تحديث بيانات المعيل', [
    'guardian_phone' => $updates['guardian_phone'] ?? 'not_set',
    'guardian_phone2' => $updates['guardian_phone2'] ?? 'not_set',
    'guardian_detailed_address' => $updates['guardian_detailed_address'] ?? 'not_set'
    // ❌ تم حذف: 'guardian_city_id' => ...
]);
```

**السطور 1332-1344 - حذف حفظ المدينة في portal_general_registration_field_values:**
```php
// تم حذف هذا الكود بالكامل:
// if (isset($updates['guardian_city_id'])) {
//     $this->savePortalFieldValue(
//         $sponsorshipId,
//         $fileIdNumber,
//         $identityNumber,
//         'guardian_city_id',
//         $updates['guardian_city_id'],
//         $userId
//     );
// }
```

**السطور 1370-1373 - حذف حفظ المدينة في data:**
```php
// قبل:
if (isset($updates['guardian_phone'])) $dataUpdates['data_phone_number'] = ...;
if (isset($updates['guardian_phone2'])) $dataUpdates['data_alt_phone_number'] = ...;
if (isset($updates['guardian_detailed_address'])) $dataUpdates['data_current_address'] = ...;
if (isset($updates['guardian_city_id'])) $dataUpdates['data_city'] = $updates['guardian_city_id']; // ❌ محذوف

// بعد:
if (isset($updates['guardian_phone'])) $dataUpdates['data_phone_number'] = ...;
if (isset($updates['guardian_phone2'])) $dataUpdates['data_alt_phone_number'] = ...;
if (isset($updates['guardian_detailed_address'])) $dataUpdates['data_current_address'] = ...;
// ✅ تم حذف data_city
```

## 🗂️ هيكل التخزين

### للمعيلين الأحياء (breadwinner, family_member, orphan):
```
جدول: data
├── data_phone_number (من guardian_phone)
├── data_alt_phone_number (من guardian_phone2)
└── data_current_address (من guardian_detailed_address) ✅
```

### للمعيلين المتوفين (deceased_father, deceased_mother):
```
جدول: portal_general_registration_field_values
├── field_key: 'guardian_phone' → field_value
├── field_key: 'guardian_phone2' → field_value
└── field_key: 'guardian_detailed_address' → field_value ✅
مع:
├── sponsorship_id
├── file_id_number
└── identity_number
```

## 📱 بناء التطبيق المحدث

### الأوامر المنفذة:
```bash
# 1. نسخ الملفات المحدثة
cd "I:\unit test\alhayahorphans\ASO - Copy\mobile-app"
npx cap sync android

# 2. نسخ dist إلى Android
npx cap copy android

# 3. بناء APK
cd "I:\unit test\alhayahorphans\ASO - Copy\android"
.\gradlew assembleRelease
```

### النتيجة:
✅ **ملف APK المحدث:**
- **المسار:** `I:\unit test\alhayahorphans\ASO - Copy\android\app\build\outputs\apk\release\app-release-unsigned.apk`
- **الحجم:** 3.09 MB
- **التاريخ:** 14 يناير 2026 - 3:13 PM

## 🔍 ملاحظات مهمة

### 1. لا حاجة للمدينة بعد الآن:
- تم الاعتماد الكامل على العنوان التفصيلي
- العنوان يُكتب بشكل حر من قبل المستخدم
- لا توجد قيود على التنسيق

### 2. التوافق مع الكود الحالي:
- ✅ جلب البيانات يعمل بنفس الطريقة
- ✅ حفظ البيانات يتم في الجدول الصحيح حسب person_type
- ✅ portal_general_registration_field_values يعمل بنظام key-value كما هو

### 3. البيانات القديمة:
- البيانات المخزنة في guardian_city_id **لن تُحذف**
- فقط لن تظهر في التطبيق الجديد
- إذا كانت هناك حاجة، يمكن دمج المدينة مع العنوان التفصيلي

## 🎉 الخلاصة

تم بنجاح:
1. ✅ إزالة عمود المحافظة من واجهة المستخدم
2. ✅ إزالة جميع المراجع إلى guardian_city_id من Backend
3. ✅ التأكد من عرض العنوان التفصيلي من الجدول الصحيح:
   - `data.data_current_address` للأحياء
   - `portal_general_registration_field_values` للمتوفين
4. ✅ بناء APK محدث بنجاح (3.09 MB)

التطبيق جاهز للاستخدام! 🚀
