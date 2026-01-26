# التثبيت السريع (دقيقتان فقط)

## 🎯 ما نحتاجه
1. ✅ تثبيت مكتبة PDF (wkhtmltopdf)
2. ✅ تشغيل Queue Worker

*(ملاحظة: Rclone موجود بالفعل - لا حاجة لإعداده)*

---

## 🚀 طريقة 1: التثبيت التلقائي

```bash
cd /var/www  # أو مسار مشروعك

# إعطاء صلاحيات التنفيذ
chmod +x check-requirements.sh setup-supervisor.sh

# تثبيت wkhtmltopdf و Supervisor
sudo ./check-requirements.sh

# إعداد Queue Worker
sudo ./setup-supervisor.sh

# التحقق
sudo supervisorctl status
```

---

## 📝 طريقة 2: التثبيت اليدوي

### 1. تثبيت wkhtmltopdf (سطر واحد)

```bash
sudo apt-get update && sudo apt-get install -y libfontconfig1 libxrender1 libxext6 libx11-6 fontconfig xfonts-75dpi xfonts-base && cd /tmp && wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox_0.12.6.1-2.jammy_amd64.deb && sudo apt install -y ./wkhtmltox_0.12.6.1-2.jammy_amd64.deb && sudo ln -sf /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf
```

### 2. إعداد Queue Worker

```bash
# تثبيت Supervisor
sudo apt-get install -y supervisor

# نسخ ملف التكوين (عدّل المسار /var/www حسب مشروعك)
sudo cp laravel-worker.conf /etc/supervisor/conf.d/

# حرر الملف لتعديل المسار
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
# استبدل /var/www بمسار مشروعك

# تفعيل
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 3. إعداد الصلاحيات

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## ✅ التحقق من النجاح

```bash
# 1. التحقق من wkhtmltopdf
wkhtmltopdf --version

# 2. التحقق من Queue
sudo supervisorctl status

# 3. مراقبة logs
tail -f storage/logs/worker.log

# 4. اختبار PDF
php artisan tinker
>>> \App\Jobs\GenerateOrphanReportPdf::dispatch(361);
>>> exit
```

---

## 🔧 أوامر مهمة

```bash
# بعد كل git pull
sudo supervisorctl restart laravel-worker:*

# عرض الحالة
sudo supervisorctl status

# مراقبة logs
tail -f storage/logs/worker.log

# إيقاف/بدء
sudo supervisorctl stop laravel-worker:*
sudo supervisorctl start laravel-worker:*
```

---

## 📚 مزيد من التفاصيل

- **دليل مبسط كامل**: [INSTALL_SIMPLE.md](INSTALL_SIMPLE.md)
- **استكشاف الأخطاء**: راجع قسم "استكشاف الأخطاء" في INSTALL_SIMPLE.md

**انتهى! 🎉**
