# إعداد Laravel Telescope

## نظرة عامة
Laravel Telescope هي أداة تطوير قوية توفر رؤية عميقة لتطبيقك، بما في ذلك الاستعلامات، الطلبات، الاستثناءات، والمزيد.

## ✅ الإعدادات الحالية

### 1. التثبيت
- ✅ Telescope مثبت في `composer.json` (الإصدار ^5.14)
- ✅ جداول قاعدة البيانات موجودة في `database/migrations/2018_08_08_100000_create_telescope_entries_table.php`

### 2. الإعدادات في `.env`
```env
# Telescope Settings
TELESCOPE_ENABLED=true
TELESCOPE_PATH=telescope
TELESCOPE_DRIVER=database
```

### 3. التحكم في الوصول
تم تحديث `app/Providers/TelescopeServiceProvider.php` بنظام صلاحيات محسّن:

```php
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        // في بيئة التطوير، السماح لجميع المستخدمين المصادق عليهم
        if (app()->environment('local')) {
            return true;
        }
        
        // في بيئة الإنتاج، السماح فقط للمسؤولين
        return $user->hasRole('admin') ?? false;
    });
}
```

## 🔧 التكوين حسب البيئة

### بيئة التطوير (Development)
```env
APP_ENV=local
APP_DEBUG=true
TELESCOPE_ENABLED=true
```
- جميع المستخدمين المصادق عليهم يمكنهم الوصول لـ Telescope
- يتم تسجيل جميع الأحداث

### بيئة الإنتاج (Production)
```env
APP_ENV=production
APP_DEBUG=false
TELESCOPE_ENABLED=false  # أو احذف هذا السطر
```
- فقط المستخدمون ذوو صلاحية `admin` يمكنهم الوصول
- يتم تسجيل الاستثناءات والأخطاء فقط

## 📋 خطوات التفعيل

### 1. التأكد من تثبيت جداول Telescope
```bash
php artisan migrate
```

### 2. الوصول إلى Telescope
بعد تشغيل التطبيق، يمكنك الوصول إلى Telescope عبر:
```
http://localhost/telescope
```

### 3. مسح البيانات القديمة (اختياري)
يمكنك إعداد مهمة جدولة لمسح سجلات Telescope القديمة تلقائياً:

في `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('telescope:prune')->daily();
}
```

## ⚙️ الإعدادات المتقدمة

### تخصيص المراقبين (Watchers)
في `config/telescope.php`، يمكنك تفعيل/تعطيل مراقبين محددين:

```php
'watchers' => [
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'slow' => 100, // تسجيل الاستعلامات الأبطأ من 100ms
    ],
    
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => 64, // حجم الاستجابة بالكيلوبايت
    ],
    
    // يمكنك تعطيل مراقبين غير مطلوبين
    Watchers\DumpWatcher::class => [
        'enabled' => false,
    ],
],
```

### إخفاء البيانات الحساسة
في `TelescopeServiceProvider`:
```php
protected function hideSensitiveRequestDetails(): void
{
    Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);
    
    Telescope::hideRequestHeaders([
        'cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'authorization',
    ]);
}
```

## 🚀 نصائح للإنتاج

### 1. تعطيل Telescope في الإنتاج
الطريقة الأولى - عبر .env:
```env
TELESCOPE_ENABLED=false
```

الطريقة الثانية - عدم تحميل Service Provider:
في `config/app.php`:
```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\TelescopeServiceProvider::class, // احذف هذا السطر في الإنتاج
])->toArray(),
```

الطريقة الثالثة - في `app/Providers/AppServiceProvider.php`:
```php
public function register()
{
    if ($this->app->environment('local')) {
        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    }
}
```

### 2. تحسين الأداء
```env
TELESCOPE_QUEUE_CONNECTION=redis
TELESCOPE_QUEUE=telescope
```

### 3. تحديد المسارات المراقبة
في `config/telescope.php`:
```php
'only_paths' => [
    'api/*',
    'admin/*',
],

'ignore_paths' => [
    'livewire*',
    'nova-api*',
    'telescope*',
    'vendor/telescope*',
],
```

## 🔍 ميزات Telescope الرئيسية

1. **Requests** - تتبع جميع طلبات HTTP
2. **Commands** - مراقبة أوامر Artisan
3. **Schedule** - مراقبة المهام المجدولة
4. **Jobs** - تتبع وظائف الطوابير
5. **Exceptions** - تتبع الأخطاء والاستثناءات
6. **Logs** - عرض سجلات التطبيق
7. **Dumps** - عرض مخرجات dd() و dump()
8. **Queries** - تحليل استعلامات قاعدة البيانات
9. **Models** - مراقبة أحداث Eloquent
10. **Events** - تتبع الأحداث المرسلة
11. **Mail** - معاينة رسائل البريد الإلكتروني
12. **Notifications** - تتبع الإشعارات
13. **Cache** - مراقبة عمليات الذاكرة المؤقتة
14. **Redis** - مراقبة عمليات Redis

## ⚠️ تحذيرات الأمان

1. **لا تترك Telescope مفعّلاً في الإنتاج** دون حماية مناسبة
2. **استخدم نظام صلاحيات قوي** للتحكم في الوصول
3. **قم بمسح البيانات القديمة بانتظام** لتجنب امتلاء قاعدة البيانات
4. **أخفِ البيانات الحساسة** مثل كلمات المرور والتوكنات

## 🛠️ استكشاف الأخطاء

### المشكلة: لا يمكن الوصول إلى Telescope
**الحل:**
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### المشكلة: خطأ 403 Forbidden
**الحل:** تأكد من أن المستخدم لديه الصلاحيات المناسبة في `gate()`

### المشكلة: قاعدة البيانات ممتلئة
**الحل:**
```bash
php artisan telescope:prune
php artisan telescope:prune --hours=48  # حذف السجلات الأقدم من 48 ساعة
```

## 📚 المراجع
- [توثيق Laravel Telescope الرسمي](https://laravel.com/docs/telescope)
- [مستودع GitHub](https://github.com/laravel/telescope)
