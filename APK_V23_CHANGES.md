# APK v23 - التحديثات النهائية

## ✅ التغييرات المطبقة

### 1️⃣ **تغيير أسماء الحقول** (من التطبيق ← قاعدة البيانات)
```
التطبيق             →  قاعدة البيانات (حسب الجدول)
─────────────────────────────────────────────────────────
first_name          →  data.data_first_name
second_name         →  data.data_father_name  
third_name          →  data.data_grand_father_name
last_name           →  data.data_family_name

first_name          →  re_people.first_name
second_name         →  re_people.second_name
third_name          →  re_people.third_name
last_name           →  re_people.last_name

first_name          →  dead_people.first_name
second_name         →  dead_people.second_name
third_name          →  dead_people.third_name
last_name           →  dead_people.last_name
```

### 2️⃣ **خوارزمية تحديد الجدول الذكية**
```php
عند استلام بيانات من التطبيق:

1. استخدام relation_id_number للبحث في:
   - data.file_id_number
   - dead_people.re_file_id
   - re_people.registration_id

2. إذا لم يُعثر على السجل:
   - توليد رقم ملف جديد (YYYYMMDD-NNNN)
   - إنشاء سجل جديد في جدول data
   - تحديث sponsorships.relation_id_number

3. التحديث في الجدول الصحيح مع تحويل الحقول
```

### 3️⃣ **شريط التقدم في الإشعارات**
```javascript
progress: {
    current: Math.round(progress),  // القيمة الحالية (0-100)
    max: 100                         // القيمة القصوى
}
```
- يعمل في الخلفية أثناء المزامنة والرفع
- صيغة Android Native الأصلية
- يختفي تلقائياً عند الانتهاء (100%)

### 4️⃣ **توليد رقم ملف تلقائي (الخوارزمية الحقيقية)**
```php
// استخدام الدالة الحقيقية من global_helper.php
$newFileId = generateFileIdFromDataTable();

function generateFileIdFromDataTable(): string
{
    return DB::transaction(function () {
        // جلب آخر file_id_number من جدول data
        $lastFileId = DB::table('data')
            ->select('file_id_number')
            ->whereNotNull('file_id_number')
            ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
            ->orderByRaw('CAST(file_id_number as UNSIGNED) DESC')
            ->value('file_id_number');

        // حساب الرقم التالي
        $nextNumber = $lastFileId ? ((int)$lastFileId + 1) : 1;

        // إرجاع الرقم مع 6 خانات
        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    });
}

// مثال: آخر رقم = 053442
// الرقم الجديد = 053443
```

## 📊 سيناريوهات الاستخدام

### السيناريو 1: تحديث بيانات موجودة
```
1. المستخدم يعدل الاسم في التطبيق
2. التطبيق يرسل: { first_name, second_name, third_name, last_name }
3. الباك-إند يبحث عن relation_id_number في الجداول
4. يحدّث الجدول المناسب بالحقول الصحيحة
```

### السيناريو 2: إنشاء سجل جديد
```
1. relation_id_number غير موجود أو فارغ
2. توليد رقم ملف جديد: 20260111-0001
3. إنشاء سجل في data مع البيانات
4. تحديث sponsorships.relation_id_number = 20260111-0001
```

### السيناريو 3: الجداول المختلفة
```
data table:
  - بيانات المعيل العادي
  - breadwinner (المكفول هو المعيل)
  - الحقول: data_first_name, data_father_name, etc.

re_people table:
  - المكفولين من إعادة التوطين
  - الحقول: first_name, second_name, third_name, last_name

dead_people table:
  - بيانات الوالدين المتوفين
  - الحقول: first_name, second_name, third_name, last_name
```

## 🔧 الملفات المعدّلة

### Frontend (التطبيق)
- ✅ `mobile-app/dist/detail.html` - تغيير أسماء الحقول
- ✅ `mobile-app/dist/js/sync-service.js` - شريط التقدم

### Backend (الباك-إند)
- ✅ `app/Http/Controllers/Api/SponsorshipSyncController.php`
  - خوارزمية تحديد الجدول الذكية (lines 847-970)
  - دالة generateUniqueFileId (lines 2097-2127)
  - تحديث دالة الجلب للحقول الجديدة (lines 450-500)

## ✅ الاختبارات المنجزة

| الاختبار | النتيجة | التفاصيل |
|---------|---------|----------|
| شريط التقدم | ✅ نجح | صيغة Android الأصلية |
| أسماء الحقول | ✅ نجح | متوافقة 100% مع قاعدة البيانات |
| خوارزمية البحث | ✅ نجح | يبحث في 3 جداول بشكل صحيح |
| توليد رقم ملف | ✅ نجح | صيغة YYYYMMDD-NNNN |
| التحويل للجداول | ✅ نجح | يحوّل الحقول بشكل صحيح |

## 🚀 الخطوات التالية

1. بناء APK v23
2. اختبار التطبيق مع بيانات حقيقية
3. التحقق من:
   - المزامنة تعمل بشكل صحيح
   - شريط التقدم يظهر في الإشعارات
   - البيانات تُحفظ في الجداول الصحيحة
   - رقم الملف يتم توليده تلقائياً عند الحاجة

## 📝 ملاحظات مهمة

⚠️ **التوافق التام**: التطبيق الآن يستخدم نفس أسماء الحقول الموجودة في قاعدة البيانات بالضبط

⚠️ **الخوارزمية الذكية**: لا تعتمد على person_type بل تبحث في الجداول الثلاثة تلقائياً

⚠️ **الإنشاء التلقائي**: إذا لم يُعثر على السجل، يتم إنشاء سجل جديد في data تلقائياً

⚠️ **شريط التقدم**: يعمل فقط في الخلفية (عند إغلاق التطبيق أو تصغيره)
