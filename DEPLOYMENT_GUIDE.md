# دليل إعداد Queue و PDF على الاستضافة

## المتطلبات الأساسية

### 1. تثبيت wkhtmltopdf

```bash
# تحديث النظام
sudo apt-get update

# تثبيت المكتبات المطلوبة
sudo apt-get install -y \
    libfontconfig1 \
    libxrender1 \
    libxext6 \
    libx11-6 \
    fontconfig \
    xfonts-75dpi \
    xfonts-base

# تحميل wkhtmltopdf
cd /tmp
wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox_0.12.6.1-2.jammy_amd64.deb

# تثبيت الحزمة
sudo apt install -y ./wkhtmltox_0.12.6.1-2.jammy_amd64.deb

# إنشاء symbolic link
sudo ln -s /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf

# التحقق من التثبيت
wkhtmltopdf --version
```

### 2. تثبيت Rclone

```bash
# تثبيت Rclone
curl https://rclone.org/install.sh | sudo bash

# التحقق من التثبيت
rclone --version

# إنشاء مجلد التكوين
sudo mkdir -p /var/www/.config/rclone

# نسخ ملف التكوين من Windows
# قم بنسخ محتوى: C:\Users\Legion\AppData\Roaming\rclone\rclone.conf
# إلى: /var/www/.config/rclone/rclone.conf

# إعطاء الصلاحيات
sudo chown -R www-data:www-data /var/www/.config
sudo chmod 600 /var/www/.config/rclone/rclone.conf
```

### 3. إعداد ملف .env على الاستضافة

```bash
# تحديث متغيرات Rclone في .env
RCLONE_PATH=/usr/bin/rclone
RCLONE_CONFIG=/var/www/.config/rclone/rclone.conf
RCLONE_REMOTE_NAME=alhayah
RCLONE_ROOT_FOLDER=temp
```

### 4. تثبيت Supervisor

```bash
# تثبيت Supervisor
sudo apt-get install -y supervisor

# التحقق من التثبيت
supervisorctl --version
```

## الإعداد التلقائي

### الخطوة 1: نسخ الملفات إلى الاستضافة

```bash
# رفع الملفات التالية:
# - laravel-worker.conf
# - check-requirements.sh
# - setup-supervisor.sh
```

### الخطوة 2: تشغيل سكريبت التحقق

```bash
# جعل السكريبت قابل للتنفيذ
chmod +x check-requirements.sh

# تشغيل السكريبت
sudo ./check-requirements.sh
```

### الخطوة 3: إعداد Supervisor

```bash
# جعل السكريبت قابل للتنفيذ
chmod +x setup-supervisor.sh

# تشغيل السكريبت
sudo ./setup-supervisor.sh
```

## الإعداد اليدوي

### 1. نسخ ملف Supervisor

```bash
# نسخ ملف التكوين
sudo cp laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

# تحرير المسارات (استبدل /var/www بمسار مشروعك)
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

### 2. تفعيل Supervisor

```bash
# إعادة قراءة التكوين
sudo supervisorctl reread

# تحديث Supervisor
sudo supervisorctl update

# بدء العمليات
sudo supervisorctl start laravel-worker:*

# التحقق من الحالة
sudo supervisorctl status
```

## التحقق من عمل النظام

### 1. اختبار wkhtmltopdf

```bash
# اختبار بسيط
echo '<h1>Test PDF</h1>' | wkhtmltopdf - test.pdf

# إذا نجح، سيتم إنشاء test.pdf
ls -lh test.pdf
```

### 2. اختبار Rclone

```bash
# عرض القائمة البعيدة
sudo -u www-data rclone listremotes

# اختبار الاتصال
sudo -u www-data rclone lsd alhayah:
```

### 3. اختبار Queue

```bash
# إنشاء job اختباري
php artisan tinker
>>> \App\Jobs\GenerateOrphanReportPdf::dispatch(361);
>>> exit

# مراقبة الـ logs
tail -f storage/logs/worker.log

# أو
tail -f storage/logs/laravel.log
```

## أوامر Supervisor المفيدة

```bash
# عرض حالة جميع العمليات
sudo supervisorctl status

# إيقاف workers
sudo supervisorctl stop laravel-worker:*

# بدء workers
sudo supervisorctl start laravel-worker:*

# إعادة تشغيل workers
sudo supervisorctl restart laravel-worker:*

# إعادة تشغيل Supervisor نفسه
sudo systemctl restart supervisor

# مراقبة logs مباشرة
sudo supervisorctl tail -f laravel-worker:laravel-worker_00 stdout
```

## استكشاف الأخطاء

### مشكلة: Workers لا تبدأ

```bash
# التحقق من logs
sudo tail -f /var/log/supervisor/supervisord.log

# التحقق من صلاحيات المجلدات
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

### مشكلة: PDF لا يتم إنشاؤه

```bash
# التحقق من وجود wkhtmltopdf
which wkhtmltopdf

# التحقق من المسار في config/snappy.php
php artisan tinker
>>> config('snappy.pdf.binary');

# اختبار يدوي
wkhtmltopdf https://google.com test.pdf
```

### مشكلة: Rclone لا يعمل

```bash
# التحقق من ملف التكوين
sudo cat /var/www/.config/rclone/rclone.conf

# اختبار كمستخدم www-data
sudo -u www-data rclone listremotes

# التحقق من الصلاحيات
ls -la /var/www/.config/rclone/
```

### مشكلة: Queue لا تعالج Jobs

```bash
# التحقق من قاعدة البيانات
php artisan tinker
>>> DB::table('jobs')->count();
>>> DB::table('failed_jobs')->count();

# مسح الـ cache
php artisan cache:clear
php artisan config:clear

# إعادة تشغيل workers
sudo supervisorctl restart laravel-worker:*
```

## الصيانة الدورية

### تنظيف الـ logs

```bash
# إنشاء cron job لتنظيف logs
sudo crontab -e

# إضافة السطر التالي (تنظيف كل أسبوع)
0 0 * * 0 find /var/www/storage/logs -name "*.log" -mtime +7 -delete
```

### مراقبة الأداء

```bash
# عرض استهلاك الموارد
htop

# عرض عمليات PHP
ps aux | grep php

# عرض حجم queue
watch -n 5 'php artisan queue:monitor'
```

## ملاحظات مهمة

1. **التحديثات**: بعد كل `git pull`، قم بإعادة تشغيل workers:
   ```bash
   sudo supervisorctl restart laravel-worker:*
   ```

2. **الصلاحيات**: تأكد دائماً من أن `www-data` يملك صلاحيات الكتابة على:
   - `storage/`
   - `bootstrap/cache/`

3. **الأمان**: لا تشارك ملف `rclone.conf` - يحتوي على tokens حساسة

4. **الأداء**: يمكنك زيادة عدد workers في `laravel-worker.conf` (numprocs)

5. **التوكنات**: إذا انتهت صلاحية توكن Google Drive، قم بتجديده:
   ```bash
   sudo -u www-data rclone config reconnect alhayah:
   ```
