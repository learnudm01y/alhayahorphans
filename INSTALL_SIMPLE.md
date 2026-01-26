# دليل التثبيت المبسط

## 🎯 المطلوب فقط
1. تثبيت مكتبة PDF (wkhtmltopdf)
2. تشغيل Queue Worker

---

## 📦 الخطوة 1: تثبيت wkhtmltopdf

```bash
# تحديث النظام
sudo apt-get update

# تثبيت المكتبات المطلوبة
sudo apt-get install -y libfontconfig1 libxrender1 libxext6 libx11-6 fontconfig xfonts-75dpi xfonts-base

# تحميل wkhtmltopdf
cd /tmp
wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox_0.12.6.1-2.jammy_amd64.deb

# تثبيت
sudo apt install -y ./wkhtmltox_0.12.6.1-2.jammy_amd64.deb

# إنشاء symbolic link
sudo ln -sf /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf

# التحقق
wkhtmltopdf --version
```

---

## ⚙️ الخطوة 2: إعداد Queue Worker

### A. تثبيت Supervisor

```bash
sudo apt-get install -y supervisor
```

### B. إنشاء ملف التكوين

```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

ضع هذا المحتوى (استبدل `/var/www` بمسار مشروعك):

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
stopwaitsecs=3600
```

### C. تفعيل Supervisor

```bash
# إعادة قراءة التكوين
sudo supervisorctl reread

# تحديث
sudo supervisorctl update

# بدء العمليات
sudo supervisorctl start laravel-worker:*

# التحقق من الحالة
sudo supervisorctl status
```

---

## ✅ التحقق من العمل

### 1. اختبار wkhtmltopdf

```bash
echo '<h1>اختبار PDF</h1>' | wkhtmltopdf - test.pdf
ls -lh test.pdf
```

### 2. اختبار Queue

```bash
# إنشاء job اختباري
php artisan tinker
>>> \App\Jobs\GenerateOrphanReportPdf::dispatch(361);
>>> exit

# مراقبة الـ logs
tail -f storage/logs/worker.log
```

---

## 🔧 أوامر Supervisor المهمة

```bash
# عرض الحالة
sudo supervisorctl status

# إعادة تشغيل (بعد كل git pull)
sudo supervisorctl restart laravel-worker:*

# إيقاف
sudo supervisorctl stop laravel-worker:*

# بدء
sudo supervisorctl start laravel-worker:*

# مراقبة logs مباشرة
tail -f storage/logs/worker.log
```

---

## 🚨 استكشاف الأخطاء

### مشكلة: PDF لا يعمل

```bash
# تحقق من وجود wkhtmltopdf
which wkhtmltopdf

# تحقق من المسار في config
php artisan tinker
>>> config('snappy.pdf.binary');
```

### مشكلة: Queue لا تعمل

```bash
# تحقق من صلاحيات المجلدات
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage

# تحقق من logs
sudo tail -f /var/log/supervisor/supervisord.log
tail -f storage/logs/worker.log

# إعادة تشغيل
sudo supervisorctl restart laravel-worker:*
```

### مشكلة: Workers لا تبدأ

```bash
# تحقق من التكوين
sudo supervisorctl reread
sudo supervisorctl update

# تحقق من logs
sudo tail -f /var/log/supervisor/supervisord.log
```

---

## 📝 ملاحظات مهمة

1. **بعد كل تحديث كود** (`git pull`):
   ```bash
   sudo supervisorctl restart laravel-worker:*
   ```

2. **Rclone موجود بالفعل** - لا حاجة لإعداده

3. **المسارات**: استبدل `/var/www` بمسار مشروعك الفعلي

4. **الصلاحيات**: تأكد دائماً من صلاحيات `storage/` و `bootstrap/cache/`

---

## 🎯 التثبيت السريع (نسخ ولصق)

```bash
# 1. تثبيت wkhtmltopdf
sudo apt-get update && sudo apt-get install -y libfontconfig1 libxrender1 libxext6 libx11-6 fontconfig xfonts-75dpi xfonts-base
cd /tmp && wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox_0.12.6.1-2.jammy_amd64.deb
sudo apt install -y ./wkhtmltox_0.12.6.1-2.jammy_amd64.deb
sudo ln -sf /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf

# 2. تثبيت Supervisor
sudo apt-get install -y supervisor

# 3. إعداد الصلاحيات (استبدل /var/www بمسارك)
cd /var/www
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 4. نسخ ملف التكوين (ثم حرره بالمسار الصحيح)
sudo cp laravel-worker.conf /etc/supervisor/conf.d/

# 5. تفعيل
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
sudo supervisorctl status
```

**انتهى! ✅**
