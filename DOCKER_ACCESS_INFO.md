# معلومات الوصول إلى تطبيق Laravel

## 🎉 تم إعداد بيئة Docker بنجاح!

### الحاويات العاملة
جميع الحاويات تعمل بنجاح:
- ✅ **laravel_app** - حاوية PHP-FPM 8.3
- ✅ **laravel_web** - حاوية Nginx
- ✅ **laravel_db** - حاوية MySQL 8.0
- ✅ **laravel_phpmyadmin** - حاوية phpMyAdmin

---

## 🌐 روابط الوصول

### 1. تطبيق Laravel الرئيسي
```
http://localhost:8888
```
**ملاحظة:** المنفذ 8000 كان مستخدماً، تم التغيير إلى 8888

### 2. phpMyAdmin (إدارة قاعدة البيانات)
```
http://localhost:9090
```
**معلومات تسجيل الدخول:**
- Server: `db`
- Username: `root`
- Password: `root`

**أو استخدم:**
- Username: `laravel`
- Password: `secret`

**ملاحظة:** المنافذ 8080، 8081، 8082 كانت مستخدمة، تم التغيير إلى 9090

### 3. قاعدة بيانات MySQL
```
Host: localhost
Port: 3306
Database: laravel
Username: laravel
Password: secret
Root Password: root
```

---

## 📝 الأوامر المنفذة بنجاح

تم تنفيذ الأوامر التالية تلقائياً:
```bash
✅ php artisan key:generate      # تم توليد مفتاح التطبيق
✅ php artisan storage:link      # تم ربط مجلد التخزين
```

---

## 🚀 الأوامر الشائعة

### إيقاف الحاويات
```powershell
docker-compose down
```

### تشغيل الحاويات
```powershell
docker-compose up -d
```

### عرض سجلات الحاويات
```powershell
# جميع الحاويات
docker-compose logs -f

# حاوية محددة
docker-compose logs -f app
docker-compose logs -f web
docker-compose logs -f db
```

### تنفيذ أوامر Artisan
```powershell
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
```

### الدخول إلى shell الحاوية
```powershell
docker-compose exec app bash
```

### إعادة بناء الحاويات
```powershell
docker-compose build --no-cache
docker-compose up -d --force-recreate
```

---

## 📊 معلومات تقنية

### الإصدارات المستخدمة
- PHP: 8.3 FPM
- Nginx: Alpine (أحدث إصدار)
- MySQL: 8.0
- Composer: أحدث إصدار

### الامتدادات المثبتة في PHP
- pdo_mysql
- mbstring
- exif
- pcntl
- bcmath
- gd
- zip

### الحجم الأقصى للرفع
- Nginx: 100MB
- phpMyAdmin: 100MB

---

## 🔧 استكشاف الأخطاء

### إذا لم يعمل التطبيق على المنفذ 8888
1. تحقق من أن الحاويات تعمل:
   ```powershell
   docker-compose ps
   ```

2. تحقق من السجلات:
   ```powershell
   docker-compose logs web
   docker-compose logs app
   ```

### إذا كانت قاعدة البيانات فارغة
```powershell
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
```

### إعادة تشغيل الحاويات
```powershell
docker-compose restart
```

---

## 📁 ملفات التكوين

- `Dockerfile` - تكوين حاوية PHP-FPM
- `docker-compose.yml` - تكوين جميع الحاويات
- `nginx/conf.d/default.conf` - تكوين Nginx
- `mysql/my.cnf` - تكوين MySQL
- `.dockerignore` - ملفات تم استبعادها من البناء

---

## ✅ الخطوة التالية

1. افتح المتصفح وانتقل إلى: **http://localhost:8888**
2. إذا كانت هذه أول مرة، قم بتشغيل Migrations:
   ```powershell
   docker-compose exec app php artisan migrate
   ```

---

**تم الإعداد بنجاح! 🎊**

تاريخ الإنشاء: ${new Date().toLocaleDateString('ar-SA')}
