# 🐳 تعليمات Docker - مشروع Laravel

## ✅ تم إنجازه

تم تعديل وتحسين ملفات Docker التالية:

### 1. **Dockerfile**
- ✅ تم تحديث من Apache إلى PHP-FPM 8.3
- ✅ تم إضافة جميع الإضافات المطلوبة (GD, MySQL, ZIP, etc.)
- ✅ تم تحسين عملية بناء الصورة
- ✅ تم إضافة Composer
- ✅ تم ضبط الصلاحيات بشكل صحيح

### 2. **docker-compose.yml**
- ✅ حاوية PHP-FPM (app)
- ✅ حاوية Nginx (web) - المنفذ 8000
- ✅ حاوية MySQL 8.0 (db) - المنفذ 3306
- ✅ حاوية phpMyAdmin (phpmyadmin) - المنفذ 8080
- ✅ تم ضبط المتغيرات البيئية
- ✅ تم إضافة Volumes للبيانات

### 3. **nginx/conf.d/default.conf**
- ✅ تم ضبط إعدادات Nginx
- ✅ تم رفع حد تحميل الملفات إلى 100MB
- ✅ تم إضافة security headers
- ✅ تم تحسين الأداء مع caching

### 4. **mysql/my.cnf**
- ✅ تم ضبط MySQL للأداء الأمثل
- ✅ تم ضبط character set إلى utf8mb4
- ✅ تم ضبط المنطقة الزمنية

### 5. **ملفات الإعداد**
- ✅ docker-setup.bat (Windows)
- ✅ docker-setup.sh (Linux/Mac)
- ✅ docker-start.bat (تشغيل سريع)
- ✅ .dockerignore
- ✅ DOCKER_README.md
- ✅ DOCKER_COMMANDS_AR.md

---

## 🚀 كيفية الاستخدام

### الطريقة الأولى: استخدام السكريبت الآلي (موصى به)

#### على Windows:
```cmd
docker-setup.bat
```

هذا السكريبت سيقوم بـ:
1. بناء جميع الحاويات
2. تشغيل الحاويات
3. تثبيت حزم Composer
4. إنشاء ملف .env
5. توليد مفتاح التطبيق
6. تشغيل الهجرات
7. ضبط الصلاحيات

### الطريقة الثانية: خطوة بخطوة

#### 1. بناء الحاويات:
```powershell
docker-compose build
```

#### 2. تشغيل الحاويات:
```powershell
docker-compose up -d
```

#### 3. تثبيت الحزم:
```powershell
docker-compose exec app composer install
```

#### 4. إعداد البيئة:
```powershell
# نسخ ملف .env
docker-compose exec app cp .env.example .env

# توليد مفتاح التطبيق
docker-compose exec app php artisan key:generate

# تشغيل الهجرات
docker-compose exec app php artisan migrate

# إنشاء رابط التخزين
docker-compose exec app php artisan storage:link
```

#### 5. مسح الذاكرة المؤقتة:
```powershell
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

---

## 📍 نقاط الوصول

بعد تشغيل الحاويات، يمكنك الوصول إلى:

| الخدمة | الرابط | الوصف |
|--------|--------|-------|
| التطبيق | http://localhost:8888 | تطبيق Laravel |
| phpMyAdmin | http://localhost:9090 | إدارة قاعدة البيانات |
| MySQL | localhost:3306 | قاعدة البيانات |

---

## 🔑 بيانات الاعتماد

### قاعدة البيانات:
```
Host: db (من داخل Docker) أو localhost (من الكمبيوتر)
Port: 3306
Database: laravel
Username: laravel
Password: secret
Root Password: root
```

### phpMyAdmin:
```
Server: db
Username: root
Password: root
```

---

## 🛠️ الأوامر الشائعة

### إدارة الحاويات:
```powershell
# عرض حالة الحاويات
docker-compose ps

# إيقاف الحاويات
docker-compose down

# إعادة تشغيل الحاويات
docker-compose restart

# عرض السجلات
docker-compose logs -f

# عرض سجل حاوية معينة
docker-compose logs -f app
```

### أوامر Laravel Artisan:
```powershell
# تشغيل أي أمر artisan
docker-compose exec app php artisan [command]

# أمثلة:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:list
docker-compose exec app php artisan make:controller ExampleController
```

### أوامر Composer:
```powershell
# تثبيت الحزم
docker-compose exec app composer install

# تحديث الحزم
docker-compose exec app composer update

# إضافة حزمة جديدة
docker-compose exec app composer require vendor/package
```

### قاعدة البيانات:
```powershell
# الوصول إلى MySQL CLI
docker-compose exec db mysql -u laravel -psecret laravel

# عمل نسخة احتياطية
docker-compose exec db mysqldump -u laravel -psecret laravel > backup.sql

# استعادة من نسخة احتياطية
docker-compose exec -T db mysql -u laravel -psecret laravel < backup.sql
```

---

## 🐛 حل المشاكل

### المشكلة: المنفذ مستخدم بالفعل
```powershell
# في docker-compose.yml غيّر المنافذ:
# للتطبيق: "8001:80" بدلاً من "8000:80"
# للمySQL: "3307:3306" بدلاً من "3306:3306"
```

### المشكلة: مشاكل الصلاحيات
```powershell
# إصلاح صلاحيات storage
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 775 /var/www/storage
```

### المشكلة: خطأ في الاتصال بقاعدة البيانات
1. تأكد من تشغيل حاوية MySQL: `docker-compose ps`
2. تحقق من ملف .env يحتوي على البيانات الصحيحة
3. انتظر عدة ثوانٍ حتى يكتمل تشغيل MySQL
4. تحقق من السجلات: `docker-compose logs db`

### البداية من الصفر:
```powershell
# إيقاف وحذف كل شيء
docker-compose down -v

# إعادة البناء والتشغيل
docker-compose up -d --build
```

---

## 📊 هيكل المشروع

```
.
├── Dockerfile                 # إعدادات حاوية PHP-FPM
├── docker-compose.yml         # تنسيق الخدمات
├── docker-setup.bat          # سكريبت إعداد Windows
├── docker-setup.sh           # سكريبت إعداد Linux/Mac
├── docker-start.bat          # تشغيل سريع
├── .dockerignore             # ملفات مستبعدة من Docker
├── nginx/
│   └── conf.d/
│       └── default.conf      # إعدادات Nginx
├── mysql/
│   └── my.cnf               # إعدادات MySQL
└── ... (ملفات Laravel)
```

---

## ⚡ نصائح للأداء

1. **استخدم volumes لـ storage**
   - يحسن الأداء على Windows
   
2. **قم بتفعيل OPcache**
   - مفعّل افتراضياً في PHP-FPM

3. **استخدم cache للإعدادات**
```powershell
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
```

4. **راقب استهلاك الموارد**
```powershell
docker stats
```

---

## 📝 ملاحظات مهمة

- ✅ جميع البيانات محفوظة في Docker volumes
- ✅ ملفات المشروع محفوظة على الكمبيوتر
- ✅ يمكن إيقاف وتشغيل الحاويات دون فقد البيانات
- ✅ للحذف الكامل استخدم: `docker-compose down -v`

---

## 🆘 الدعم

إذا واجهت أي مشاكل:

1. تحقق من السجلات: `docker-compose logs -f`
2. تأكد من تشغيل Docker Desktop
3. تأكد من توفر المنافذ (8000, 3306, 8080)
4. جرب إعادة بناء الحاويات: `docker-compose build --no-cache`

---

## ✨ ميزات إضافية

### phpMyAdmin
- الوصول السهل لقاعدة البيانات
- واجهة مستخدم بديهية
- دعم الاستيراد والتصدير

### MySQL 8.0
- أداء محسّن
- دعم كامل لـ utf8mb4
- إعدادات مخصصة للأداء

### Nginx
- خادم ويب سريع وخفيف
- دعم ملفات كبيرة (100MB)
- تحسينات أمنية

---

**تم التحديث:** أكتوبر 2025
**الحالة:** ✅ جاهز للاستخدام
