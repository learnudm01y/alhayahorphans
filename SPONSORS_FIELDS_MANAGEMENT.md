# نظام إدارة حقول الجمعيات - Sponsors Fields Management System

## 📋 نظرة عامة
هذا النظام يسمح بإدارة مرنة للحقول التي تظهر لكل جمعية على حدة. يمكن لكل جمعية أن يكون لها مجموعة مخصصة من الحقول المفعلة والتي ستظهر في نماذج التسجيل والتقارير الخاصة بها.

## 🎯 الهدف من النظام
- تمكين كل جمعية من اختيار الحقول التي تحتاجها فقط
- تبسيط عملية التسجيل للمستخدمين
- تحسين تجربة المستخدم من خلال عرض الحقول المناسبة فقط
- مرونة في إضافة أو إزالة الحقول لكل جمعية

## 📁 الملفات المضافة

### 1. صفحة العرض الرئيسية
**المسار:** `resources/views/admin/dashboard/sponsors/fields-management.blade.php`
- عرض جميع الجمعيات في جدول مع DataTable
- زر "إدارة الحقول" لكل جمعية
- مودال بحجم كامل لإدارة الحقول

### 2. ملف JavaScript
**المسار:** `public/js/sponsor-fields-management.js`
- كلاس `SponsorFieldsManager` لإدارة جميع الوظائف
- تهيئة DataTable مع جلب البيانات من السيرفر
- إدارة الحقول (تفعيل/تعطيل)
- نظام بحث في الحقول
- حفظ الإعدادات

### 3. التعديلات على الملفات الموجودة

#### routes/admin.php
```php
// Sponsors Fields Management (إدارة حقول الجمعيات)
Route::get('sponsors/fields-management', [SponsorController::class, 'fieldsManagement'])
    ->name('sponsors.fields-management');
```

#### app/Http/Controllers/Admin/SponsorController.php
```php
/**
 * عرض صفحة إدارة حقول الجمعيات
 */
public function fieldsManagement()
{
    return view('admin.dashboard.sponsors.fields-management');
}
```

#### resources/views/admin/dashboard/layout/sidebar.blade.php
```blade
<div class="menu-item">
    <a class="menu-link" href="{{ route('admin.sponsors.fields-management') }}">
        <span class="menu-bullet">
            <i class="fas fa-cogs"></i>
        </span>
        <span class="menu-title"> تحديث البيانات </span>
    </a>
</div>
```

## 🚀 كيفية الاستخدام

### 1. الوصول إلى الصفحة
- من القائمة الجانبية، اذهب إلى: **إدارة الجمعيات > تحديث البيانات**
- أو مباشرة عبر الرابط: `/admin/sponsors/fields-management`

### 2. إدارة الحقول لجمعية معينة
1. ابحث عن الجمعية المطلوبة في الجدول
2. اضغط على زر "إدارة الحقول"
3. سيفتح مودال بحجم كامل يحتوي على:
   - **القسم الأيسر:** قائمة جميع الحقول المتاحة مع إمكانية تفعيلها/تعطيلها
   - **القسم الأيمن:** معاينة للحقول المفعلة فقط

### 3. تفعيل/تعطيل الحقول
- استخدم الـ Switch بجانب كل حقل لتفعيله أو تعطيله
- أو اضغط على عنصر الحقل بالكامل لتبديل حالته
- استخدم صندوق البحث للعثور على حقول معينة بسرعة

### 4. حفظ التغييرات
- اضغط على زر "حفظ التغييرات" في رأس أو ذيل المودال
- ستظهر رسالة تأكيد بعد نجاح العملية

## 🔧 المميزات الحالية

### ✅ تم تنفيذه
- [x] إضافة رابط في القائمة الجانبية
- [x] إنشاء Route للبوابة الجديدة
- [x] إضافة دالة في Controller
- [x] صفحة عرض مع DataTable لجميع الجمعيات
- [x] مودال بحجم كامل لإدارة الحقول
- [x] واجهة مستخدم سهلة وجذابة
- [x] نظام بحث في الحقول
- [x] معاينة فورية للحقول المفعلة
- [x] تصميم متجاوب (Responsive)
- [x] بيانات تجريبية (Demo Data)

### 🔄 قيد التطوير
- [ ] ربط مع قاعدة البيانات لجلب الحقول الفعلية
- [ ] حفظ إعدادات الحقول في قاعدة البيانات
- [ ] إضافة صلاحيات للوصول
- [ ] نظام ترتيب الحقول (Drag & Drop)
- [ ] تحديد الحقول الإلزامية
- [ ] إضافة حقول مخصصة جديدة

## 📊 البيانات التجريبية

حالياً يتم عرض 20 حقل تجريبي مقسمة إلى 6 فئات:
1. **معلومات أساسية:** الاسم، رقم الهوية، تاريخ الميلاد، الجنس، الجنسية
2. **معلومات السكن:** العنوان، المحافظة، المدينة، نوع السكن
3. **معلومات الاتصال:** الهاتف الأساسي، الهاتف الاحتياطي، البريد الإلكتروني
4. **معلومات شخصية:** الحالة الاجتماعية، عدد الأطفال، المؤهل العلمي
5. **معلومات صحية:** الحالة الصحية، نوع الإعاقة، الأمراض المزمنة
6. **معلومات البنك:** رقم الحساب، اسم البنك

## 🔮 الخطوات القادمة

### 1. إنشاء جدول لحفظ إعدادات الحقول
```sql
CREATE TABLE sponsor_field_settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    sponsor_id BIGINT NOT NULL,
    field_id BIGINT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_required BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id),
    FOREIGN KEY (field_id) REFERENCES form_fields(id)
);
```

### 2. إنشاء جدول للحقول المتاحة
```sql
CREATE TABLE form_fields (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    field_name VARCHAR(255) NOT NULL,
    field_key VARCHAR(255) NOT NULL UNIQUE,
    field_type VARCHAR(50),
    category_id BIGINT,
    default_order INT DEFAULT 0,
    is_system_field BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 3. إنشاء API Endpoints
- `GET /admin/sponsors/{id}/fields` - جلب إعدادات الحقول
- `POST /admin/sponsors/{id}/fields` - حفظ إعدادات الحقول
- `GET /admin/form-fields` - جلب جميع الحقول المتاحة

### 4. تطبيق الحقول في النماذج
- تحديث نماذج التسجيل لعرض الحقول المفعلة فقط
- التحقق من الحقول الإلزامية
- تطبيق الترتيب المخصص للحقول

## 📝 ملاحظات مهمة

1. **الحقول الإلزامية:** بعض الحقول الأساسية (مثل الاسم ورقم الهوية) لا يمكن تعطيلها
2. **الأداء:** يتم تحميل الحقول بشكل غير متزامن لتحسين الأداء
3. **التوافق:** النظام متوافق مع جميع المتصفحات الحديثة
4. **الأمان:** يجب إضافة صلاحيات مناسبة قبل النشر في الإنتاج

## 🎨 التصميم والواجهة

- استخدام Bootstrap 5
- أيقونات Font Awesome
- تصميم متجاوب
- ألوان متناسقة مع باقي النظام
- مودال بحجم كامل لتوفير مساحة عمل واسعة

## 🛠️ التقنيات المستخدمة

- **Backend:** Laravel 10
- **Frontend:** Blade Templates, jQuery
- **UI Framework:** Bootstrap 5, Metronic Theme
- **DataTable:** DataTables.js
- **Notifications:** SweetAlert2
- **Icons:** Font Awesome

## 📞 الدعم

في حالة وجود أي استفسارات أو مشاكل، يرجى التواصل مع فريق التطوير.

---

**آخر تحديث:** ديسمبر 2025
**الإصدار:** 1.0.0 (Beta)
**الحالة:** جاهز للاختبار - يحتاج إلى ربط مع قاعدة البيانات
