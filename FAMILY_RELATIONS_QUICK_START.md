# نظام البحث عن العلاقات العائلية - دليل التشغيل السريع

## خطوات التشغيل

### 1. تأكد من اتصال قاعدة البيانات
تأكد من وجود إعدادات قاعدة بيانات `civilregistry` في ملف `.env`:

```env
CIVIL_DB_HOST=localhost
CIVIL_DB_PORT=3306
CIVIL_DB_DATABASE=civilregistry
CIVIL_DB_USERNAME=root
CIVIL_DB_PASSWORD=your_password
```

### 2. تشغيل Migration (إذا لم يتم بالفعل)
```bash
php artisan migrate --path=database/migrations/2025_11_08_000001_add_indexes_to_relations_table.php
```

### 3. مسح الكاش (اختياري)
```bash
php artisan cache:clear
php artisan config:clear
```

### 4. الوصول إلى النظام
1. سجّل الدخول كـ Admin
2. انتقل إلى: `/admin/civil-registry`
3. اضغط على زر "البحث عن العلاقات العائلية" (الزر الأخضر)
4. أدخل رقم الهوية واضغط "بحث"

---

## الميزات الرئيسية

✅ **بحث سريع جداً** - باستخدام فهارس B-Tree  
✅ **بحث ثنائي الاتجاه** - علاقات مباشرة وعكسية  
✅ **واجهة عربية كاملة** - سهلة الاستخدام  
✅ **إحصائيات في الوقت الفعلي** - عدد العلاقات، الأنواع، وقت التنفيذ  
✅ **Caching ذكي** - لتقليل الضغط على قاعدة البيانات  
✅ **API كامل** - للتكامل مع أنظمة أخرى  

---

## API Endpoints

### البحث عن العلاقات
```
POST /admin/family-relations/search
Body: { "id_number": "123456789" }
```

### شجرة العائلة
```
POST /admin/family-relations/family-tree
Body: { "id_number": "123456789", "depth": 3 }
```

### الإحصائيات
```
GET /admin/family-relations/statistics
```

### مسح الكاش
```
POST /admin/family-relations/clear-cache
```

---

## حل المشاكل الشائعة

### البحث بطيء جداً
**الحل**: تأكد من تشغيل Migration الخاص بالفهارس
```bash
php artisan migrate:status
```

### لا توجد نتائج
**الأسباب المحتملة**:
1. رقم الهوية غير موجود في قاعدة البيانات
2. لا توجد علاقات مسجلة لهذا الشخص
3. بيانات جدول `relations` غير كاملة

### خطأ في الاتصال
**الحل**: تحقق من إعدادات قاعدة البيانات في `.env`

---

## للمزيد من التفاصيل
راجع الملف الكامل: `FAMILY_RELATIONS_SYSTEM_DOCUMENTATION.md`

---

**تم التطوير بواسطة**: GitHub Copilot  
**التاريخ**: 8 نوفمبر 2025  
**الحالة**: ✅ جاهز للإنتاج
