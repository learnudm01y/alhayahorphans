# تثبيت الخطوط العربية لـ PDF على السيرفر

## المشكلة
ملفات PDF لا تعرض الخط العربي بشكل صحيح على الاستضافة.

## الحل

### 1. رفع ملفات الخطوط إلى السيرفر

تأكد من رفع ملفات الخطوط التالية إلى مجلد `public/fonts/`:

```bash
public/fonts/Cairo-Regular.ttf
public/fonts/Cairo-Bold.ttf
```

### 2. التحقق من صلاحيات الملفات

قم بتشغيل الأمر التالي على السيرفر:

```bash
cd /var/www/html/alhayahorphans
chmod 644 public/fonts/*.ttf
chown www-data:www-data public/fonts/*.ttf
```

### 3. تثبيت حزم الخطوط على السيرفر (اختياري لكن موصى به)

```bash
sudo apt-get update
sudo apt-get install -y fonts-liberation
sudo apt-get install -y fontconfig
sudo fc-cache -fv
```

### 4. إعادة تشغيل Queue Workers

بعد رفع الخطوط، أعد تشغيل Queue Workers:

```bash
sudo supervisorctl restart laravel-worker:*
```

### 5. اختبار

قم بإنشاء PDF جديد وتحقق من الخط.

## ملاحظات

- تم تحديث ملف `resources/views/user/dashboard/pdf/orphan-report.blade.php` لاستخدام خط Cairo
- الخط يتم تحميله مباشرة من `public/fonts/` باستخدام `public_path()`
- wkhtmltopdf يحتاج للوصول المباشر لملفات الخطوط من نظام الملفات

## التحقق من نجاح التثبيت

قم بتشغيل الأمر التالي للتحقق من الخطوط المتاحة:

```bash
fc-list | grep -i cairo
fc-list | grep -i arabic
```

## إذا استمرت المشكلة

جرب نسخ الخطوط إلى مجلد خطوط النظام:

```bash
sudo mkdir -p /usr/share/fonts/truetype/cairo
sudo cp public/fonts/Cairo-*.ttf /usr/share/fonts/truetype/cairo/
sudo fc-cache -fv
```
