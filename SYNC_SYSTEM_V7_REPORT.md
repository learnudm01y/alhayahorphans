# تقرير إصلاح نظام المزامنة - الإصدار 7

## تاريخ: $(Get-Date -Format "yyyy-MM-dd")

## ملخص التغييرات

### 1. إعادة هيكلة المنطق بالكامل

**المنطق القديم (خاطئ):**
- كان يجلب البيانات من جداول `data`, `re_people`, `dead_people` مباشرة
- كان يعتمد على حقول غير موجودة في الخادم الفعلي

**المنطق الجديد (صحيح):**
- يبدأ من جدول `sponsorships` فقط
- إذا وجد `relation_id_number` → يجلب البيانات المرتبطة
- إذا لم يوجد → يبحث في `civilregistry.persons` باستخدام `identity_number`
- يجلب: الاسم الكامل، تاريخ الميلاد، الجنس من السجل المدني

### 2. الملفات المُحدّثة

#### Backend (Laravel):
- `app/Http/Controllers/Api/SponsorshipSyncController.php` - Controller جديد بالكامل
- `routes/api.php` - تحديث المسارات لاستخدام Controller الجديد

#### Frontend (Mobile App):
- `mobile-app/dist/js/sync-service.js` - خدمة مزامنة جديدة مع IndexedDB
- `mobile-app/dist/index.html` - الصفحة الرئيسية مع إحصائيات صحيحة
- `mobile-app/dist/login.html` - صفحة تسجيل الدخول
- `mobile-app/dist/data.html` - صفحة البيانات مع فلترة بالجمعية والحالة
- `mobile-app/dist/photography.html` - صفحة التصوير مع فلترة صحيحة
- `mobile-app/dist/sync-monitor.html` - صفحة متابعة المزامنة (جديدة)
- `mobile-app/dist/upload.html` - صفحة رفع الملفات (جديدة)

### 3. الميزات الجديدة

#### التخزين المحلي الدائم:
- استخدام IndexedDB للتخزين الدائم على الجهاز
- الجداول المحلية:
  - `sponsors` - الجمعيات
  - `sponsorship_statuses` - حالات الكفالة
  - `sponsorships` - الكفالات
  - `pending_uploads` - التغييرات المعلقة للرفع
  - `files` - الملفات المحفوظة
  - `sync_meta` - بيانات المزامنة

#### إجبار اختيار الجمعية والحالة:
- لا تظهر البيانات في صفحة data.html أو photography.html قبل اختيار الجمعية
- زر البحث يجب الضغط عليه لجلب البيانات

#### البحث:
- يعمل على البيانات المحلية المخزنة
- بحث بالاسم، رقم الهوية، رقم الملف الداخلي/الخارجي، اسم الولي

#### هيكل مجلدات Google Drive:
```
alhayah/
  ├── [اسم الجمعية]/
  │   └── [اسم المكفول]/
  │       ├── photo_001.jpg
  │       └── document.pdf
```

### 4. APIs الجديدة

| Endpoint | Method | الوصف |
|----------|--------|-------|
| `/api/mobile/login` | POST | تسجيل الدخول |
| `/api/mobile/logout` | POST | تسجيل الخروج |
| `/api/mobile/health` | GET | فحص صحة الاتصال |
| `/api/mobile/sponsors` | GET | جلب الجمعيات |
| `/api/mobile/sponsorship-statuses` | GET | جلب حالات الكفالة |
| `/api/mobile/sync/initial` | GET | المزامنة الأولية |
| `/api/mobile/sync/sponsorships` | GET | جلب الكفالات (مع فلترة) |
| `/api/mobile/sync/sponsorship/{id}` | GET | تفاصيل كفالة واحدة |
| `/api/mobile/sync/upload` | POST | رفع تحديثات |
| `/api/mobile/sync/stats` | GET | إحصائيات المزامنة |
| `/api/mobile/upload-file` | POST | رفع ملف إلى Google Drive |

### 5. ملف APK

- **الاسم:** `alhayah-v7-sync-fixed.apk`
- **الحجم:** 13.57 MB
- **الموقع:** `mobile-app/alhayah-v7-sync-fixed.apk`

### 6. خطوات التثبيت

1. رفع ملفات Laravel إلى الخادم
2. تثبيت ملف APK على الهاتف
3. تسجيل الدخول باسم مستخدم له صلاحية admin
4. الضغط على "مزامنة" لتحميل الجمعيات والحالات
5. اختيار الجمعية والحالة في صفحة البيانات أو التصوير

### 7. ملاحظات مهمة

- جدول `sponsorships` حالياً فارغ (0 سجلات) - يجب إضافة بيانات
- يجب أن يكون للمستخدم صلاحية `admin` للدخول
- الملفات تُحفظ محلياً أولاً ثم تُرفع إلى Google Drive
