# توصيات إعدادات PHP لتحسين تصدير Excel

## لـ php.ini أو .htaccess

### 1. زيادة حد الذاكرة
```ini
; في php.ini
memory_limit = 512M

; أو في .htaccess
php_value memory_limit 512M
```

**ملاحظة**: يمكن زيادته إلى 1G أو 2G إذا كانت البيانات ضخمة جداً، ولكن يُفضل استخدام الحل البرمجي (chunking) بدلاً من الاعتماد على زيادة الذاكرة فقط.

### 2. تعطيل الحد الزمني للتنفيذ
```ini
; في php.ini
max_execution_time = 0
max_input_time = 300

; أو في .htaccess
php_value max_execution_time 0
php_value max_input_time 300
```

**تحذير**: `max_execution_time = 0` يعني لا حد زمني. استخدمه بحذر على الخوادم الإنتاجية.

### 3. تحسين الـ Output Buffer
```ini
; في php.ini
output_buffering = 4096
implicit_flush = Off

; أو في .htaccess
php_value output_buffering 4096
php_flag implicit_flush Off
```

### 4. تحسين Garbage Collection
```ini
; في php.ini
zend.enable_gc = On
```

## لـ Laravel (.env)

```env
# تحديد بيئة الإنتاج
APP_ENV=production
APP_DEBUG=false

# تحسين الأداء
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# إذا كنت تريد استخدام queue للتصدير
QUEUE_DRIVER=database
```

## ملف .htaccess (للاستخدام على Apache)

```apache
<IfModule mod_php7.c>
    php_value memory_limit 512M
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value post_max_size 100M
    php_value upload_max_filesize 100M
</IfModule>

<IfModule mod_php8.c>
    php_value memory_limit 512M
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value post_max_size 100M
    php_value upload_max_filesize 100M
</IfModule>
```

## للخوادم باستخدام Nginx + PHP-FPM

في ملف `/etc/php/8.x/fpm/pool.d/www.conf`:

```ini
; زيادة عدد العمليات
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000

; زيادة الحد الزمني
request_terminate_timeout = 300
```

في ملف `/etc/php/8.x/fpm/php.ini`:

```ini
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
```

بعد التعديل، أعد تشغيل PHP-FPM:
```bash
sudo systemctl restart php8.1-fpm
# أو
sudo service php8.1-fpm restart
```

## التحقق من الإعدادات الحالية

### من سطر الأوامر:
```bash
php -i | grep memory_limit
php -i | grep max_execution_time
```

### من Laravel:
أنشئ route مؤقت في `routes/web.php`:
```php
Route::get('/phpinfo', function() {
    phpinfo();
})->middleware('auth'); // تأكد من حماية هذا المسار!
```

ثم زر: `http://your-domain.com/phpinfo`

**تحذير**: احذف هذا الـ route بعد الانتهاء من الفحص!

## مراقبة استهلاك الذاكرة في الكود

يمكنك إضافة هذا الكود لمراقبة استهلاك الذاكرة:

```php
// في بداية الدالة exportAll()
$startMemory = memory_get_usage();
Log::info('Memory at start: ' . round($startMemory / 1024 / 1024, 2) . ' MB');

// بعد كل chunk
$currentMemory = memory_get_usage();
Log::info('Current memory: ' . round($currentMemory / 1024 / 1024, 2) . ' MB');

// في النهاية
$peakMemory = memory_get_peak_usage();
Log::info('Peak memory: ' . round($peakMemory / 1024 / 1024, 2) . ' MB');
```

## أولويات التنفيذ

1. ✅ **الأولوية الأولى**: الكود المحسّن الذي تم تطبيقه (chunking + gc)
2. ⚡ **الأولوية الثانية**: تعديل `memory_limit` في php.ini إلى 512M
3. 🕐 **الأولوية الثالثة**: تعديل `max_execution_time` إلى 300 أو 0
4. 📊 **اختياري**: مراقبة استهلاك الذاكرة للتحسين المستمر

## الحل الأمثل لملفات Excel الضخمة جداً (> 100,000 سجل)

إذا كانت البيانات ضخمة جداً (أكثر من 100,000 سجل)، يُنصح بـ:

1. **تثبيت Laravel Excel**:
   ```bash
   composer require maatwebsite/excel
   ```

2. **استخدام Queued Exports**:
   ```php
   Excel::queue(new RecordsExport, 'records.xlsx', 'public')->chain([
       new NotifyUserOfCompletedExport(request()->user()),
   ]);
   ```

3. **أو تقسيم التصدير**:
   - تصدير 50,000 سجل في كل ملف
   - ضغط الملفات في ZIP
   - إرسال رابط التحميل

---

**ملاحظة أخيرة**: الحل الحالي (chunking) يجب أن يحل المشكلة في معظم الحالات. الإعدادات أعلاه هي تحسينات إضافية اختيارية.
