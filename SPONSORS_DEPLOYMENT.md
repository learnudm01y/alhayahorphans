# تعليمات نشر نظام إدارة الجمعيات

## ✅ تم إنشاء الملفات التالية:

### 1. Controller
- `app/Http/Controllers/Admin/SponsorController.php`

### 2. Model
- تم تحديث `app/Models/Sponsor.php`

### 3. DataTable
- `app/DataTables/SponsorDataTable.php`

### 4. Views
- `resources/views/admin/dashboard/sponsors/index.blade.php`
- `resources/views/admin/dashboard/sponsors/partials/actions.blade.php`

### 5. Routes
- تم تحديث `routes/admin.php`

### 6. Sidebar
- تم تحديث `resources/views/admin/dashboard/layout/sidebar.blade.php`

### 7. Migration
- `database/migrations/2025_12_01_233939_add_fields_to_sponsors_table.php`

---

## 📋 خطوات النشر على الاستضافة:

### 1️⃣ رفع الكود
```bash
git add .
git commit -m "إضافة نظام إدارة الجمعيات"
git push origin main
```

### 2️⃣ على الاستضافة
```bash
# سحب التحديثات
git pull origin main

# تشغيل الـ Migration
php artisan migrate

# تنظيف الـ Cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

---

## 🎯 الميزات المتوفرة:

### ✅ الوظائف الأساسية:
- ✅ عرض جميع الجمعيات في جدول تفاعلي (Yajra DataTable)
- ✅ إضافة جمعية جديدة
- ✅ تعديل بيانات الجمعية
- ✅ حذف جمعية
- ✅ البحث في الجمعيات
- ✅ تصدير البيانات (Excel, CSV, PDF, Print)

### 📊 الحقول المتوفرة:
1. اسم الجمعية (إجباري)
2. الشخص المسؤول
3. رقم الهاتف
4. البريد الإلكتروني
5. العنوان
6. الموقع الإلكتروني
7. وصف الجمعية
8. الحالة (نشط/غير نشط)

### 🎨 الواجهة:
- تصميم متناسق مع الهوية البصرية للموقع
- استخدام Bootstrap و Metronic
- Modal منبثق للإضافة والتعديل
- أيقونات واضحة ومفهومة
- رسائل تأكيد بـ SweetAlert2

---

## 🔗 الروابط:

### في Sidebar:
```
الصفحة الرئيسية
├── إدارة التسجيلات
├── إدارة التصنيفات
├── إدارة الجمعيات ← جديد
│   └── إدارة الجمعيات
├── Pages
└── ...
```

### الرابط المباشر:
```
/admin/sponsors
```

---

## 🧪 الاختبار:

بعد النشر، تحقق من:
1. ✅ ظهور القائمة في Sidebar
2. ✅ فتح صفحة إدارة الجمعيات
3. ✅ إضافة جمعية جديدة
4. ✅ تعديل البيانات
5. ✅ حذف جمعية
6. ✅ البحث والتصدير

---

## ⚠️ ملاحظات مهمة:

1. **جدول sponsors يجب أن يكون موجود** في قاعدة البيانات
2. إذا كان الجدول موجود ولديه أعمدة مختلفة، الـ Migration سيضيف الحقول الناقصة فقط
3. التصميم متوافق مع باقي صفحات الموقع
4. جميع العمليات تتم عبر AJAX بدون إعادة تحميل الصفحة

---

## 🛠️ في حالة وجود مشاكل:

### خطأ في Migration:
```bash
# التحقق من الأعمدة الموجودة
php artisan tinker
> Schema::getColumnListing('sponsors');
```

### خطأ في الصلاحيات:
تأكد من أن المستخدم لديه صلاحية الوصول إلى `/admin/sponsors`

---

تم إنشاء النظام بنجاح! 🎉
