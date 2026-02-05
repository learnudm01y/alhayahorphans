# تطبيق الكفالات - جاهز للإنتاج 🚀

## النسخة: Production 2026-01-22

### ✅ التحديثات الرئيسية

1. **نظام المصادقة**
   - يسمح فقط للمستخدمين الذين لديهم `role = 'admin'`
   - استخدام Laravel Sanctum Bearer Tokens
   - التوكن صالح لمدة 30 يوم

2. **API Endpoints**
   - جميع الطلبات تذهب إلى `/api/mobile`
   - فحص صلاحية Admin في كل عملية تسجيل دخول

3. **ملفات التطبيق**
   - جميع ملفات HTML محدثة
   - `sync-service-real.js` يستخدم Production API
   - نسخة موحدة: `v=20260122-production`

---

## 🎯 كيفية النسخ والنشر

### الطريقة 1: استخدام السكريبت

```powershell
.\copy-app-production.ps1
```

### الطريقة 2: يدوياً

```powershell
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
Copy-Item "mobile-app-offline" -Destination "mobile-app-production-$timestamp" -Recurse
```

---

## 📝 التعديلات المطلوبة قبل النشر

### 1. تحديث API URL

**الملف**: `js/sync-service-real.js`  
**السطر**: ~35

```javascript
// للاستضافة الحقيقية
const localUrl = 'https://yourdomain.com/api/mobile';

// للأجهزة المحمولة
const androidUrl = 'https://yourdomain.com/api/mobile';
```

### 2. التأكد من CORS

في Laravel: `config/cors.php`

```php
'paths' => ['api/*'],
'allowed_origins' => ['*'],  // أو حدد الدومين
'supports_credentials' => true,
```

---

## 🧪 الاختبار

### اختبار محلي:
```
http://127.0.0.1:8000/mobile-app-offline/login.html
```

### اختبار API:
```bash
php test_production_api.php
```

**النتائج المتوقعة**: جميع الاختبارات ✅

---

## 📦 الملفات الأساسية

```
mobile-app-offline/
├── login.html           ✅ صفحة تسجيل الدخول
├── index.html           ✅ الصفحة الرئيسية
├── data.html            ✅ إدخال البيانات
├── detail.html          ✅ تفاصيل الكفالة
├── photography.html     ✅ التصوير
├── sync-monitor.html    ✅ مراقبة المزامنة
├── upload.html          ✅ رفع الملفات
└── js/
    └── sync-service-real.js  ✅ خدمة المزامنة (Production)
```

---

## 🔐 المصادقة

### المتطلبات:
1. اسم مستخدم موجود في `users`
2. كلمة مرور صحيحة
3. `role = 'admin'`

### مثال لإضافة مستخدم admin:

```php
DB::table('users')->insert([
    'name' => 'admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('password123'),
    'role' => 'admin',
    'created_at' => now(),
    'updated_at' => now()
]);
```

---

## 📚 التوثيق الكامل

للمزيد من التفاصيل، راجع:
- [PRODUCTION_READY_DOCUMENTATION.md](PRODUCTION_READY_DOCUMENTATION.md)
- [PRODUCTION_LOGIN_SETUP.md](PRODUCTION_LOGIN_SETUP.md)

---

## ⚡ البدء السريع

```bash
# 1. انسخ التطبيق
.\copy-app-production.ps1

# 2. عدّل API URL في sync-service-real.js

# 3. ارفع إلى الاستضافة

# 4. افتح في المتصفح
https://yourdomain.com/app/login.html
```

---

✅ **جاهز للإنتاج والاستخدام الفعلي!**
