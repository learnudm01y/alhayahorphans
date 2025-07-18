# دليل تشغيل Speedtest CLI في الإنتاج

## نظرة عامة
تم تطوير نظام قياس سرعة الإنترنت المتكامل مع Laravel باستخدام Ookla Speedtest CLI للحصول على قياسات حقيقية ودقيقة لسرعة الإنترنت.

## المكونات الرئيسية

### 1. SpeedTestController (Laravel)
- **الموقع**: `app/Http/Controllers/SpeedTestController.php`
- **الوظائف**:
  - `run()`: تشغيل اختبار السرعة
  - `checkAvailability()`: فحص توفر CLI
  - `clearCache()`: مسح الذاكرة المؤقتة
- **المميزات**:
  - محاولات متعددة (3 محاولات افتراضية)
  - نظام cache للنتائج (5 دقائق افتراضية)
  - نظام fallback للبيانات الاحتياطية
  - معالجة الأخطاء المتقدمة
  - إعدادات قابلة للتخصيص

### 2. ملف الإعدادات
- **الموقع**: `config/speedtest.php`
- **الإعدادات الرئيسية**:
  - `timeout`: مهلة الانتظار (120 ثانية افتراضية)
  - `max_attempts`: عدد المحاولات (3 افتراضية)
  - `cache_enabled`: تفعيل الذاكرة المؤقتة
  - `cache_duration`: مدة الحفظ (300 ثانية)
  - إعدادات الأمان والتحكم في المعدل

### 3. متغيرات البيئة (.env)
```env
SPEEDTEST_TIMEOUT=120
SPEEDTEST_MAX_ATTEMPTS=3
SPEEDTEST_CACHE_ENABLED=true
SPEEDTEST_CACHE_DURATION=300
SPEEDTEST_CLI_PATH=speedtest
```

### 4. المسارات (Routes)
```php
Route::prefix('api/speedtest')->group(function () {
    Route::get('/', [SpeedTestController::class, 'run']);
    Route::get('/check', [SpeedTestController::class, 'checkAvailability']);
    Route::post('/clear-cache', [SpeedTestController::class, 'clearCache']);
});
```

## التثبيت والإعداد

### 1. تثبيت Speedtest CLI

#### Windows (PowerShell كمسؤول):
```powershell
# تشغيل سكريبت التثبيت
./install-speedtest-windows.ps1
```

#### Linux/Ubuntu:
```bash
# تثبيت من المستودع الرسمي
curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.deb.sh | sudo bash
sudo apt-get install speedtest

# أو تثبيت يدوي
wget -O speedtest.tgz https://install.speedtest.net/app/cli/ookla-speedtest-1.2.0-linux-x86_64.tgz
tar -xzf speedtest.tgz
sudo mv speedtest /usr/local/bin/
sudo chmod +x /usr/local/bin/speedtest
```

#### CentOS/RHEL:
```bash
curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.rpm.sh | sudo bash
sudo yum install speedtest
```

### 2. إعداد Laravel

#### أ. نسخ الملفات:
```bash
# نسخ Controller
cp SpeedTestController.php app/Http/Controllers/

# نسخ ملف الإعدادات
cp speedtest.php config/

# تحديث Routes
# إضافة مسارات Speedtest إلى routes/web.php
```

#### ب. إعداد متغيرات البيئة:
```bash
# إضافة إعدادات Speedtest إلى .env
cat .env.speedtest >> .env

# تحديث إعدادات Laravel
php artisan config:clear
php artisan config:cache
php artisan cache:clear
```

#### ج. تثبيت التبعيات:
```bash
# تحديث Composer
composer dump-autoload

# تثبيت Symfony Process (إذا لم يكن مثبتاً)
composer require symfony/process
```

### 3. إنشاء Wrapper آمن (اختياري)

#### Linux:
```bash
# إنشاء wrapper script
sudo nano /usr/local/bin/speedtest-safe
```

```bash
#!/bin/bash
# Secure Speedtest CLI Wrapper
timeout 120 speedtest --format=json --accept-license --accept-gdpr "$@"
```

```bash
# إعطاء صلاحيات
sudo chmod +x /usr/local/bin/speedtest-safe
```

#### Windows:
```batch
@echo off
REM Secure Speedtest CLI Wrapper
speedtest --format=json --accept-license --accept-gdpr %*
```

## الاختبار والتشغيل

### 1. اختبار CLI مباشرة:
```bash
# اختبار الإصدار
speedtest --version

# اختبار قبول الترخيص
speedtest --accept-license --accept-gdpr --format=json
```

### 2. اختبار Laravel API:

#### أ. فحص التوفر:
```bash
curl http://your-domain/api/speedtest/check
```

#### ب. تشغيل الاختبار:
```bash
curl http://your-domain/api/speedtest
```

#### ج. مسح الذاكرة:
```bash
curl -X POST http://your-domain/api/speedtest/clear-cache
```

### 3. استخدام لوحة التحكم:
- افتح `speedtest-production-dashboard.html` في المتصفح
- اربطها بـ Laravel للحصول على واجهة إدارة شاملة

## الاستخدام في JavaScript

### الاستخدام الأساسي:
```javascript
async function runSpeedTest() {
    try {
        const response = await fetch('/api/speedtest');
        const data = await response.json();
        
        if (data.success) {
            console.log('Download:', data.download_mbps, 'Mbps');
            console.log('Upload:', data.upload_mbps, 'Mbps');
            console.log('Ping:', data.ping_ms, 'ms');
        } else {
            // استخدام البيانات الاحتياطية
            const fallback = data.fallback_data;
            console.log('Fallback - Download:', fallback.download_mbps, 'Mbps');
        }
    } catch (error) {
        console.error('Test failed:', error);
    }
}
```

### الاستخدام المتقدم:
```javascript
class SpeedTestManager {
    constructor() {
        this.cache = new Map();
        this.maxRetries = 3;
    }
    
    async runTest(options = {}) {
        const { useCache = true, timeout = 120000 } = options;
        
        // فحص الذاكرة المؤقتة
        if (useCache && this.cache.has('lastResult')) {
            const cached = this.cache.get('lastResult');
            if (Date.now() - cached.timestamp < 300000) { // 5 minutes
                return { ...cached.data, cached: true };
            }
        }
        
        // تشغيل الاختبار
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        
        try {
            const response = await fetch('/api/speedtest', {
                signal: controller.signal
            });
            const data = await response.json();
            
            // حفظ في الذاكرة المؤقتة
            this.cache.set('lastResult', {
                data: data,
                timestamp: Date.now()
            });
            
            return data;
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('Test timeout');
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }
    
    async checkAvailability() {
        const response = await fetch('/api/speedtest/check');
        return response.json();
    }
    
    async clearCache() {
        this.cache.clear();
        const response = await fetch('/api/speedtest/clear-cache', {
            method: 'POST'
        });
        return response.json();
    }
}
```

## الأمان والحماية

### 1. إعدادات الأمان:
```php
// في config/speedtest.php
'security' => [
    'allowed_ips' => env('SPEEDTEST_ALLOWED_IPS', null),
    'require_auth' => env('SPEEDTEST_REQUIRE_AUTH', false),
    'max_concurrent_tests' => 3,
]
```

### 2. Rate Limiting:
```php
'rate_limit' => [
    'enabled' => true,
    'max_requests' => 5,
    'per_minutes' => 60,
]
```

### 3. مراقبة الأداء:
```php
'store_results' => true,
'results_table' => 'speedtest_results',
'cleanup_after_days' => 30,
```

## استكشاف الأخطاء

### 1. CLI غير متوفر:
```bash
# فحص التثبيت
which speedtest
speedtest --version

# إعادة التثبيت
# Linux
sudo apt-get install --reinstall speedtest

# Windows
choco uninstall speedtest
choco install speedtest
```

### 2. مشاكل الصلاحيات:
```bash
# Linux
sudo chmod +x /usr/local/bin/speedtest
sudo chown www-data:www-data /usr/local/bin/speedtest

# فحص صلاحيات PHP
php -m | grep proc
```

### 3. مشاكل الشبكة:
```bash
# اختبار الاتصال
ping speedtest.net
telnet speedtest.net 80

# فحص الجدار الناري
sudo ufw status
```

### 4. مشاكل Laravel:
```bash
# فحص اللوجز
tail -f storage/logs/laravel.log

# فحص الإعدادات
php artisan config:show speedtest

# تحديث الذاكرة المؤقتة
php artisan cache:clear
php artisan config:cache
```

## الأداء والتحسين

### 1. تحسين الذاكرة المؤقتة:
```php
// تخصيص مدة الحفظ حسب الاستخدام
'cache_duration' => env('SPEEDTEST_CACHE_DURATION', 300),

// استخدام Redis للذاكرة المؤقتة
'cache_driver' => 'redis',
```

### 2. تحسين الأداء:
```php
// تقليل عدد المحاولات للتطبيقات السريعة
'max_attempts' => 1,

// تقليل مهلة الانتظار
'timeout' => 60,
```

### 3. مراقبة الأداء:
```php
// تسجيل الأداء
'verbose_logging' => true,

// حفظ النتائج للتحليل
'store_results' => true,
```

## الصيانة

### 1. تنظيف دوري:
```bash
# تنظيف الذاكرة المؤقتة
php artisan cache:clear

# تنظيف النتائج القديمة
php artisan speedtest:cleanup
```

### 2. مراقبة النظام:
```bash
# فحص حالة الخدمة
curl -s http://localhost/api/speedtest/check | jq '.available'

# مراقبة الأداء
tail -f storage/logs/speedtest.log
```

### 3. النسخ الاحتياطية:
```bash
# نسخ احتياطية للإعدادات
cp config/speedtest.php config/speedtest.php.backup
cp .env .env.backup
```

## الدعم والمساعدة

### 1. الوثائق الرسمية:
- [Ookla Speedtest CLI](https://www.speedtest.net/apps/cli)
- [Laravel Documentation](https://laravel.com/docs)
- [Symfony Process](https://symfony.com/doc/current/components/process.html)

### 2. الأخطاء الشائعة:
- **CLI not found**: تأكد من التثبيت والصلاحيات
- **Timeout**: زيادة مهلة الانتظار
- **Permission denied**: فحص صلاحيات PHP و CLI
- **Network error**: فحص الاتصال والجدار الناري

### 3. المساعدة:
- فحص ملفات اللوج في `storage/logs/`
- استخدام `php artisan tinker` للاختبار
- تشغيل `composer dump-autoload` لتحديث الفئات

---

## ملاحظات مهمة

1. **الأمان**: لا تعرض API endpoints بدون حماية في الإنتاج
2. **الأداء**: استخدم الذاكرة المؤقتة لتقليل التحميل
3. **المراقبة**: راقب استخدام الموارد والأداء
4. **النسخ الاحتياطية**: احتفظ بنسخ احتياطية للإعدادات
5. **التحديثات**: حدث Speedtest CLI بانتظام

تم إنشاء هذا النظام للعمل في بيئة الإنتاج بأعلى معايير الأمان والأداء.
