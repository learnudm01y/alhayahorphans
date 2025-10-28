# Laravel Docker Commands - Quick Reference

## بناء وتشغيل المشروع

### بناء الحاويات
```powershell
docker-compose build --no-cache
```

### تشغيل الحاويات
```powershell
docker-compose up -d
```

### إيقاف الحاويات
```powershell
docker-compose down
```

### عرض حالة الحاويات
```powershell
docker-compose ps
```

### عرض السجلات
```powershell
docker-compose logs -f
```

## إعداد Laravel

### تثبيت الحزم
```powershell
docker-compose exec app composer install
```

### إنشاء ملف .env
```powershell
docker-compose exec app cp .env.example .env
```

### توليد مفتاح التطبيق
```powershell
docker-compose exec app php artisan key:generate
```

### تشغيل الهجرات
```powershell
docker-compose exec app php artisan migrate
```

### مسح الذاكرة المؤقتة
```powershell
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

### إنشاء رابط التخزين
```powershell
docker-compose exec app php artisan storage:link
```

## الوصول إلى المشروع

- التطبيق: http://localhost:8888
- phpMyAdmin: http://localhost:9090

## بيانات قاعدة البيانات

- المضيف: db (من داخل الحاوية) أو localhost (من الكمبيوتر)
- المنفذ: 3306
- قاعدة البيانات: laravel
- المستخدم: laravel
- كلمة المرور: secret
- كلمة مرور Root: root
