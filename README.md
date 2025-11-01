# نظام إدارة الأيتام - ASO

نظام شامل لإدارة بيانات الأيتام والعائلات المستفيدة مع تكامل مع السجل المدني.

## 📋 المتطلبات

- PHP >= 8.1
- Composer
- MySQL >= 5.7
- Node.js & NPM (لبناء الأصول الأمامية)
- Laragon أو XAMPP أو بيئة تطوير Laravel أخرى

## 🚀 التثبيت

### 1. استنساخ المشروع
```bash
git clone [repository-url]
cd ASO
```

### 2. تثبيت الاعتمادات
```bash
composer install
npm install
```

### 3. إعداد البيئة
```bash
cp .env.example .env
php artisan key:generate
```

### 4. تكوين قاعدة البيانات
قم بتحديث ملف `.env` بمعلومات قاعدة البيانات:

```env
# قاعدة البيانات الرئيسية
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aso
DB_USERNAME=root
DB_PASSWORD=

# قاعدة بيانات السجل المدني
CIVIL_DB_HOST=127.0.0.1
CIVIL_DB_PORT=3306
CIVIL_DB_DATABASE=civilregistry
CIVIL_DB_USERNAME=root
CIVIL_DB_PASSWORD=
```

### 5. تشغيل الترحيلات
```bash
php artisan migrate
```

### 6. بناء الأصول
```bash
npm run build
# أو للتطوير
npm run dev
```

### 7. تشغيل التطبيق
```bash
php artisan serve
```

الآن يمكنك الوصول إلى التطبيق على: `http://localhost:8000`

## 🔧 الإعدادات المتقدمة

### Laravel Telescope (أداة التطوير)

Telescope هو أداة تطوير قوية لمراقبة التطبيق. للمزيد من المعلومات، راجع [TELESCOPE_SETUP.md](TELESCOPE_SETUP.md)

**الوصول إلى Telescope:**
```
http://localhost:8000/telescope
```

**ملاحظة:** Telescope معطّل افتراضياً في بيئة الإنتاج للأمان والأداء.

### الصلاحيات والأدوار

يستخدم النظام حزمة `spatie/laravel-permission` لإدارة الصلاحيات والأدوار.

```bash
# تشغيل seeders للصلاحيات
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### البحث

يستخدم النظام Laravel Scout للبحث المتقدم:

```bash
# إعادة فهرسة البيانات
php artisan scout:import "App\Models\Persons"
```

## 📁 هيكل المشروع

```
.
├── app/
│   ├── Console/          # أوامر Artisan
│   ├── DataTables/       # تعريفات DataTables
│   ├── Helpers/          # دوال مساعدة
│   ├── Http/
│   │   ├── Controllers/  # المتحكمات
│   │   └── Middleware/   # Middleware
│   ├── Models/           # نماذج Eloquent
│   ├── Observers/        # Observers
│   ├── Providers/        # Service Providers
│   └── Services/         # طبقة الخدمات
├── config/               # ملفات التكوين
├── database/
│   ├── migrations/       # ملفات الترحيل
│   └── seeders/          # ملفات البذر
├── public/               # الملفات العامة
├── resources/
│   ├── css/              # ملفات CSS
│   ├── js/               # ملفات JavaScript
│   ├── lang/             # ملفات الترجمة
│   └── views/            # عروض Blade
├── routes/               # تعريفات المسارات
├── storage/              # التخزين
└── tests/                # الاختبارات
```

## 🔐 الأمان

### في بيئة الإنتاج

1. **تعطيل وضع التطوير:**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **تعطيل Telescope:**
   ```env
   TELESCOPE_ENABLED=false
   ```

3. **استخدام HTTPS:**
   ```env
   APP_URL=https://your-domain.com
   ```

4. **تحديث APP_KEY:**
   ```bash
   php artisan key:generate
   ```

### حماية الملفات الحساسة

تأكد من أن ملف `.env` غير متاح للعموم وأنه مضاف إلى `.gitignore`.

## 📊 قاعدة البيانات

### الجداول الرئيسية

- `persons` - بيانات الأشخاص
- `data` - بيانات الطلبات
- `re_people` - بيانات الأقارب
- `dead_people` - بيانات المتوفين
- `aid_management` - إدارة المساعدات
- `attachments` - المرفقات
- `enhanced_attachments` - المرفقات المحسّنة

### الاتصال بالسجل المدني

يستخدم النظام اتصال منفصل بقاعدة بيانات السجل المدني للبحث:

```php
// في config/database.php
'civilregistry' => [
    'driver' => 'mysql',
    'host' => env('CIVIL_DB_HOST', '127.0.0.1'),
    'database' => env('CIVIL_DB_DATABASE', 'civilregistry'),
    'username' => env('CIVIL_DB_USERNAME', 'root'),
    'password' => env('CIVIL_DB_PASSWORD', ''),
    // ...
]
```

## 🛠️ الصيانة

### مسح ذاكرة التخزين المؤقت
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### تحسين الأداء (الإنتاج)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### النسخ الاحتياطي
```bash
# نسخ احتياطي لقاعدة البيانات
php artisan backup:run
```

### السجلات
السجلات موجودة في `storage/logs/laravel.log`

## 📝 التطوير

### معايير الكود

- اتبع PSR-12 coding standard
- استخدم أسماء وصفية للمتغيرات والدوال
- أضف تعليقات للكود المعقد
- اكتب اختبارات للميزات الجديدة

### المساهمة

1. قم بإنشاء فرع جديد (`git checkout -b feature/amazing-feature`)
2. قم بإجراء التغييرات وإضافة commits (`git commit -m 'Add some amazing feature'`)
3. ادفع إلى الفرع (`git push origin feature/amazing-feature`)
4. افتح Pull Request

## 🐛 استكشاف الأخطاء

### المشكلة: خطأ في الاتصال بقاعدة البيانات
**الحل:** تحقق من معلومات الاتصال في `.env` وتأكد من أن MySQL يعمل.

### المشكلة: خطأ 500
**الحل:**
```bash
php artisan cache:clear
php artisan config:clear
# تحقق من storage/logs/laravel.log
```

### المشكلة: الأصول (CSS/JS) لا تعمل
**الحل:**
```bash
npm run build
php artisan storage:link
```

### المشكلة: لا يمكن الوصول إلى Telescope
**الحل:**
```bash
php artisan route:clear
php artisan config:clear
# تحقق من TELESCOPE_ENABLED=true في .env
```

## 📚 الوثائق الإضافية

- [إعداد Telescope](TELESCOPE_SETUP.md)
- [تقرير الإكمال](COMPLETION_REPORT.md)

## 📄 الترخيص

هذا المشروع مملوك لجمعية الحياة للأيتام وتحت حقوق الملكية الخاصة.

## 📞 الدعم

للدعم الفني، يرجى التواصل مع فريق التطوير.

---

**آخر تحديث:** نوفمبر 2025
