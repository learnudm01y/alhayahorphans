# 🔭 حل مشكلة Laravel Telescope في Docker/الإنتاج

## ❌ المشكلة

```
Class "Laravel\Telescope\TelescopeServiceProvider" not found
```

**السبب:**
- Laravel Telescope موجود في `require-dev` فقط
- عند تشغيل `composer install` في الإنتاج، لا يتم تثبيت حزم `require-dev`
- ولكن `TelescopeServiceProvider` مُعرّف في `config/app.php`

---

## ✅ الحلول المتاحة

### الحل 1️⃣: نقل Telescope إلى require (إذا كنت تحتاجه في الإنتاج) ⭐

**تم تطبيق هذا الحل في الكود!**

تم نقل `"laravel/telescope": "^5.14"` من `require-dev` إلى `require` في `composer.json`.

**الخطوات على الخادم:**

```bash
cd /home/ubuntu/alhayahorphans

# احذف الملفات القديمة
rm -rf vendor/ composer.lock

# أعد بناء Docker مع تثبيت Telescope
docker compose down
docker compose build --no-cache
docker compose up -d

# أو إذا كنت تريد تثبيته محلياً فقط
docker compose exec laravel_app composer install
docker compose exec laravel_app php artisan migrate
docker compose exec laravel_app php artisan telescope:install
```

**المزايا:**
- ✅ Telescope سيعمل في الإنتاج
- ✅ يمكنك مراقبة الأخطاء والطلبات

**العيوب:**
- ⚠️ زيادة حجم التطبيق قليلاً
- ⚠️ يستهلك موارد إضافية

---

### الحل 2️⃣: تعطيل Telescope في الإنتاج (موصى به للإنتاج) 🔒

**تم تطبيق هذا الحل أيضاً!**

تم تعديل `config/app.php` لتحميل `TelescopeServiceProvider` فقط إذا كانت الحزمة موجودة.

**الكود المُطبّق:**
```php
// في config/app.php
...(class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)
    ? [App\Providers\TelescopeServiceProvider::class]
    : []
),
```

**لا يحتاج خطوات إضافية!** - سيعمل مباشرة عند إعادة البناء.

**المزايا:**
- ✅ لن يحدث خطأ إذا لم يكن Telescope مثبتاً
- ✅ أخف وأسرع في الإنتاج
- ✅ أكثر أماناً

---

## 🚀 خطوات التطبيق على الخادم

### الطريقة السريعة (موصى بها):

```bash
# 1. انتقل إلى مجلد المشروع
cd /home/ubuntu/alhayahorphans

# 2. احذف الحاويات القديمة
docker compose down

# 3. احذف الملفات المؤقتة
rm -rf vendor/ composer.lock

# 4. أعد رفع الملفات المحدثة من جهازك المحلي
# استخدم scp أو git pull

# 5. أعد بناء الصورة
docker compose build --no-cache

# 6. شغل الحاويات
docker compose up -d

# 7. تحقق من الحالة
docker compose ps
docker compose logs laravel_app
```

---

### إذا كنت تريد Telescope في الإنتاج:

```bash
# بعد رفع ملف composer.json المحدث
cd /home/ubuntu/alhayahorphans

docker compose down
docker compose build --no-cache
docker compose up -d

# ثبت Telescope داخل الحاوية
docker compose exec laravel_app php artisan telescope:install
docker compose exec laravel_app php artisan migrate

# انشر أصول Telescope
docker compose exec laravel_app php artisan vendor:publish --tag=telescope-assets
```

---

## 🔐 تأمين Telescope في الإنتاج

إذا قررت استخدام Telescope في الإنتاج، **يجب تأمينه!**

### 1. قم بتعديل `app/Providers/TelescopeServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        return in_array($user->email, [
            'admin@gmail.com', // ضع البريد الإلكتروني للمسؤولين
            // أضف المزيد من البريد الإلكتروني هنا
        ]);
    });
}
```

### 2. قم بتعديل `.env`:

```env
TELESCOPE_ENABLED=true  # أو false لتعطيله
```

### 3. عدّل `register()` في نفس الملف:

```php
public function register(): void
{
    // تفعيل Telescope فقط إذا كان مفعلاً في .env
    if (! config('app.debug') && ! env('TELESCOPE_ENABLED', false)) {
        return;
    }

    $this->hideSensitiveRequestDetails();

    // ... باقي الكود
}
```

---

## 🧪 التحقق من النجاح

### من الخادم:

```bash
# تحقق من عمل الحاوية
docker compose ps

# تحقق من الأخطاء
docker compose logs laravel_app

# ادخل إلى الحاوية
docker compose exec laravel_app bash

# تحقق من Composer
composer show | grep telescope
```

### من المتصفح:

```
https://your-domain.com/telescope
```

يجب أن ترى واجهة Telescope (إذا كان مفعلاً ولديك الصلاحيات).

---

## 📝 ملاحظات مهمة

### حزم require-dev:
- يتم تثبيتها فقط في التطوير
- **لا يتم تثبيتها** في الإنتاج عند تشغيل `composer install --no-dev`

### Dockerfile الحالي:
```dockerfile
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader
```

⚠️ **مشكلة:** لا يوجد flag `--no-dev`، لذا سيتم تثبيت حزم dev.

### لتحسين Dockerfile (اختياري):

```dockerfile
# في بيئة الإنتاج، أضف --no-dev
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader --no-dev
```

---

## 🔄 الخيار الموصى به

**للإنتاج:** استخدم **الحل 2** (تعطيل Telescope)
- أسرع
- أخف
- أكثر أماناً
- تم تطبيقه بالفعل في الكود!

**للتطوير:** استخدم **الحل 1** (تثبيت Telescope)
- مفيد للتطوير والتصحيح
- راقب الاستعلامات والأخطاء

---

## 🆘 إذا استمرت المشكلة

### حل سريع (تعطيل Telescope مؤقتاً):

```bash
# على الخادم، عدّل config/app.php مباشرة
nano /home/ubuntu/alhayahorphans/config/app.php

# احذف أو علّق على هذا السطر:
# App\Providers\TelescopeServiceProvider::class,

# أعد البناء
docker compose down
docker compose up -d --build
```

---

## 📚 روابط مفيدة

- [Laravel Telescope Docs](https://laravel.com/docs/10.x/telescope)
- [Telescope في الإنتاج](https://laravel.com/docs/10.x/telescope#configuration)

---

**تم التحديث:** 30 أكتوبر 2025
**الحل المطبق:** الحلان 1 و 2 معاً (مرن ويعمل في جميع الحالات)
