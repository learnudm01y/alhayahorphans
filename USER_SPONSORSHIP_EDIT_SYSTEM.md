# نظام تعديل بيانات الكفالة للمستخدمين
## User Sponsorship Data Edit System

تاريخ الإنشاء: 2025-01-14

---

## 📋 نظرة عامة

نظام متكامل يسمح للمستخدمين (المكفولين) بتعديل بياناتهم الشخصية بناءً على الحقول المفعلة من قبل الجمعية الراعية. يعرض النظام فقط الحقول التي حددتها الجمعية كمرئية في نظام إدارة الحقول.

---

## 🎯 الميزات الرئيسية

### 1. عرض ديناميكي للحقول
- يتم عرض الحقول بناءً على إعدادات الجمعية من جدول `sponsor_field_settings`
- تجميع الحقول حسب الفئات (11 فئة)
- ترتيب الحقول حسب `order` المحدد في config

### 2. تعديل شامل للبيانات
- **بيانات المكفول الأساسية**: الاسم، رقم الهوية، تاريخ الميلاد
- **بيانات المعيل**: الاسم، الهاتف، العلاقة
- **بيانات إضافية**: من جدول `data` عبر علاقة `relationData`
- **أفراد الأسرة**: إضافة/تعديل/حذف أفراد العائلة
- **المرفقات**: رفع ملفات جديدة (PDF, JPG, PNG)

### 3. عرض المعلومات البنكية
- عرض الحساب البنكي المعتمد فقط (check_account = 1)
- عرض للقراءة فقط (readonly)
- رسالة تنبيه للتواصل مع الإدارة للتعديل

### 4. نظام التأكيد والحفظ
- تأكيد قبل الحفظ باستخدام SweetAlert2
- رسائل نجاح/خطأ واضحة
- Transaction للحفاظ على سلامة البيانات

---

## 📁 ملفات النظام

### 1. Controller
**المسار**: `app/Http/Controllers/Users/ShowGeneralRegisrationController.php`

#### الدوال الرئيسية:

##### `index()`
```php
// جلب بيانات الكفالة للمستخدم
// جلب الحقول المفعلة من إعدادات الجمعية
// تجميع الحقول حسب الفئات
// جلب الحساب البنكي المعتمد
```

##### `updateSponsorshipData(Request $request)`
```php
// التحقق من ملكية المستخدم للكفالة
// تحديث الحقول في جدول sponsorships
// تحديث الحقول في جدول data
// تحديث/إضافة أفراد الأسرة في re_people
// رفع المرفقات الجديدة
// DB Transaction للحماية
```

### 2. View الرئيسية
**المسار**: `resources/views/user/dashboard/component/generalRegisrationIndex.blade.php`

#### الأقسام:
1. **بطاقة العنوان**: معلومات الكفالة الأساسية (background gradient)
2. **Include للحقول الديناميكية**: `@include('user.dashboard.component.partials.sponsorship-fields-form')`
3. **أزرار الحفظ**: حفظ + رجوع
4. **JavaScript**: تأكيد الحفظ، رسائل النجاح/الخطأ

### 3. View Partial للحقول
**المسار**: `resources/views/user/dashboard/component/partials/sponsorship-fields-form.blade.php`

#### المكونات:

##### أ. الحقول الديناميكية
```blade
@foreach($groupedFields as $categoryId => $categoryData)
    // عرض عنوان الفئة
    @foreach($categoryData['fields'] as $field)
        // عرض الحقل بناءً على نوعه:
        // - textarea للوصف
        // - date لحقول التاريخ
        // - tel لحقول الهاتف
        // - email لحقل البريد
        // - text للحقول العادية
    @endforeach
@endforeach
```

##### ب. أفراد الأسرة
```blade
<div id="family-members-container">
    // عرض الأفراد الحاليين (قابل للتعديل)
    // زر إضافة فرد جديد
    // زر حذف لكل فرد
</div>
```

##### ج. المعلومات البنكية
```blade
@if(isset($approvedBankAccount))
    // عرض اسم البنك
    // رقم الآيبان (شيكل)
    // رقم الآيبان (دولار)
    // اسم صاحب الحساب
    // رسالة: لا يمكن التعديل
@endif
```

##### د. المرفقات
```blade
@if(isset($enabledFields['field_attachments']))
    // input file لرفع ملفات جديدة
    // عرض المرفقات الحالية
    // أزرار معاينة
@endif
```

### 4. Route
**المسار**: `routes/web.php`

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
        Route::get('/general-registration', [ShowGeneralRegisrationController::class, 'index'])
            ->name('generalRegistration.index');
        Route::post('/general-registration/update', [ShowGeneralRegisrationController::class, 'updateSponsorshipData'])
            ->name('update-sponsorship-data');
    });
});
```

---

## 🔄 تدفق البيانات

### 1. عند الدخول للصفحة:
```
User Login
    ↓
Get user->email (identity_number)
    ↓
Find Sponsorship by identity_number
    ↓
Get sponsor_id from Sponsorship
    ↓
Get SponsorFieldSetting for sponsor_id
    ↓
Filter enabled fields (value = 1)
    ↓
Group fields by category
    ↓
Get approved bank account (check_account = 1)
    ↓
Render view with:
    - sponsorship
    - groupedFields
    - enabledFields
    - approvedBankAccount
```

### 2. عند حفظ البيانات:
```
Form Submit
    ↓
SweetAlert2 Confirmation
    ↓
POST to /user/general-registration/update
    ↓
Validate ownership (identity_number matches)
    ↓
DB::beginTransaction()
    ↓
Update sponsorships fields
    ↓
Update data (relationData) fields
    ↓
Update/Create re_people (family members)
    ↓
Upload new attachments
    ↓
DB::commit()
    ↓
Redirect with success message
```

---

## 🗂️ تعيين الحقول للجداول

### جدول `sponsorships`:
```php
'orphan_name', 'identity_number', 'birth_date', 
'internal_file_number', 'guardian_name', 
'guardian_phone', 'guardian_relationship'
```

### جدول `data` (عبر relationData):
```php
// جميع الحقول الأخرى مع بادئة data_
'data_first_name', 'data_phone_number', 'data_address', 
'data_description', 'data_health_status', ...
```

### جدول `re_people`:
```php
'person_name', 'person_relationship', 
'person_birth_date', 'person_health_status'
```

### جدول `attachments`:
```php
'file_id_number', 'stored_file_name', 'file_path'
```

---

## 🎨 التصميم والواجهة

### الألوان:
- **Primary**: #3b82f6 (أزرق)
- **Secondary**: #6c757d (رمادي)
- **Gradient Header**: #667eea → #764ba2 (بنفسجي)
- **Bank Card**: #667eea → #764ba2 (بنفسجي)

### المكونات:
- **Bootstrap 5**: Grid system, Cards, Forms
- **Bootstrap Icons**: أيقونات
- **Custom CSS**: card-custom, field-group-title, bank-info-card
- **SweetAlert2**: تأكيدات ورسائل

### Responsive:
- `col-md-6`: عمودين في الشاشات المتوسطة+
- `col-12`: عمود واحد في الموبايل
- `text-md-end`: محاذاة يمين في الشاشات المتوسطة+

---

## 🔒 الأمان

### 1. المصادقة:
```php
Route::middleware(['auth', 'verified'])
```

### 2. التحقق من الملكية:
```php
$sponsorship = Sponsorship::where('identity_number', $user->email)->firstOrFail();
```

### 3. CSRF Protection:
```blade
@csrf
```

### 4. التحقق من البيانات:
```php
$request->validate([
    'fields' => 'array',
    'family_members' => 'array',
    'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
]);
```

### 5. Database Transactions:
```php
DB::beginTransaction();
// operations...
DB::commit();
// catch: DB::rollBack();
```

---

## 📝 أنواع الحقول المدعومة

### 1. نصية (text):
```blade
<input type="text" name="fields[field_sponsor_name]">
```

### 2. نصية طويلة (textarea):
```blade
<textarea name="fields[field_description]" rows="3"></textarea>
```

### 3. تاريخ (date):
```blade
<input type="date" name="fields[field_birth_date]">
```

### 4. هاتف (tel):
```blade
<input type="tel" pattern="[0-9]*" name="fields[field_phone]">
```

### 5. بريد (email):
```blade
<input type="email" name="fields[field_email]">
```

---

## 🧪 الاختبار

### بيانات الاختبار:
```
Username (identity_number): 3865168918
Password (internal_file_number): 002213
```

### خطوات الاختبار:

#### 1. تسجيل الدخول:
```
1. انتقل لـ /login
2. أدخل: 3865168918
3. كلمة السر: 002213
4. تسجيل الدخول
```

#### 2. الوصول للصفحة:
```
1. انقر "عرض تفاصيل السجل" من Dashboard
2. يجب أن تظهر بطاقة العنوان مع معلومات الكفالة
3. يجب أن تظهر الحقول المفعلة فقط
```

#### 3. تعديل البيانات:
```
1. قم بتعديل أي حقل
2. انقر "حفظ التغييرات"
3. تأكيد في SweetAlert2
4. انتظر رسالة النجاح
5. التحقق من حفظ البيانات
```

#### 4. إضافة فرد:
```
1. انقر "إضافة فرد جديد"
2. أدخل بيانات الفرد
3. احفظ النموذج
4. تحقق من حفظ الفرد في re_people
```

#### 5. رفع مرفقات:
```
1. اختر ملف (PDF/JPG/PNG)
2. احفظ النموذج
3. تحقق من حفظ الملف في storage/attachments
4. تحقق من إضافة سجل في attachments
```

---

## ⚠️ الملاحظات المهمة

### 1. المعلومات البنكية:
- **للعرض فقط** - لا يمكن التعديل من قبل المستخدم
- يجب التواصل مع الإدارة للتحديث
- يعرض فقط الحساب المعتمد (check_account = 1)

### 2. الحقول المطلوبة:
- تحدد بناءً على `required` في config/sponsor_fields.php
- يتم التحقق في الـ frontend (HTML5 validation)
- يجب إضافة validation في الـ backend

### 3. العلاقات:
- `Sponsorship` → `relationData` (belongsTo Data)
- `Data` → `rePeople` (hasMany RePeople)
- `Data` → `attachments` (hasMany Attachment)

### 4. الحقول المخفية:
- إذا كان الحقل غير مفعل في `sponsor_field_settings`، لن يظهر
- لن يتم حفظه حتى لو تم إرساله من الـ frontend

---

## 🐛 معالجة الأخطاء

### 1. إذا لم توجد كفالة:
```php
if (!$sponsorship) {
    // يتم عرض البيانات القديمة من جدول data
    return view('...', compact('data'));
}
```

### 2. إذا لم توجد إعدادات حقول:
```php
if (!$fieldSettings) {
    // عرض جميع الحقول
    $enabledFields = $fieldsConfig;
}
```

### 3. عند فشل الحفظ:
```php
catch (\Exception $e) {
    DB::rollBack();
    Log::error('UPDATE_SPONSORSHIP_ERROR', [...]);
    return redirect()->back()->with('error', '...');
}
```

---

## 📊 الإحصائيات

### عدد الملفات المنشأة: 3
1. ShowGeneralRegisrationController.php (محدّث)
2. generalRegisrationIndex.blade.php (محدّث)
3. sponsorship-fields-form.blade.php (جديد)

### عدد الدوال: 2
1. `index()` - عرض البيانات
2. `updateSponsorshipData()` - حفظ التعديلات

### عدد الجداول المتأثرة: 4
1. `sponsorships`
2. `data`
3. `re_people`
4. `attachments`

### عدد الحقول المدعومة: 53 حقل
(من config/sponsor_fields.php)

---

## 🚀 التطويرات المستقبلية

### 1. إضافة Validation متقدم:
```php
// في updateSponsorshipData()
$rules = [];
foreach ($enabledFields as $field) {
    if ($field['required']) {
        $rules['fields.' . $field['db_column']] = 'required';
    }
}
$request->validate($rules);
```

### 2. إضافة Audit Log:
```php
// تسجيل كل تعديل
AuditLog::create([
    'user_id' => Auth::id(),
    'sponsorship_id' => $sponsorship->id,
    'action' => 'update',
    'old_data' => $oldData,
    'new_data' => $newData,
]);
```

### 3. إضافة معاينة قبل الحفظ:
```javascript
// عرض modal بالتغييرات قبل الحفظ
showPreviewModal(changedFields);
```

### 4. إضافة إشعارات:
```php
// إشعار للإدارة عند كل تعديل
Notification::send($admins, new SponsorshipUpdated($sponsorship));
```

### 5. إضافة تتبع الحالة:
```php
// pending_review, approved, rejected
$sponsorship->update_status = 'pending_review';
```

---

## 📞 الدعم

للمساعدة أو الاستفسارات:
- راجع ملف `SPONSOR_FIELD_SETTINGS_SUMMARY.md`
- راجع ملف `conversation-summary.md`
- تحقق من logs: `storage/logs/laravel.log`

---

## ✅ التحقق من النجاح

### العلامات:
- ✅ عرض الحقول بناءً على إعدادات الجمعية
- ✅ تعديل البيانات وحفظها في الجداول الصحيحة
- ✅ إضافة/تعديل أفراد الأسرة
- ✅ رفع المرفقات
- ✅ عرض المعلومات البنكية
- ✅ رسائل نجاح/خطأ واضحة
- ✅ Transactions للحماية
- ✅ Authentication & Authorization
- ✅ Responsive Design

---

**تم الإنشاء بنجاح ✓**
**جاهز للاختبار ✓**
