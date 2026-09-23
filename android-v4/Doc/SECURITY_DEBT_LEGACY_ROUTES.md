# SECURITY_DEBT_LEGACY_ROUTES.md
> **دين تقني أمني موروث — مشروع مستقل مقترح لصاحب القرار**
>
> المصدر: `android-v4/Doc/04_DATABASE_AND_API_CHANGES.md` §6 + مخاطر `docs/ENCYCLOPEDIA.md` §49 (R1–R28)
> تاريخ التسليم: 2026-09-23 | **خارج نطاق تنفيذ android-v4** (قرار ملزم رقم 6 في `00_MASTER_PLAN.md`)

---

## 1. لماذا هذا الملف منفصل

أي إصلاح للمخاطر أدناه يتطلب **تعديل ملفات قديمة** (`routes/web.php`, `routes/admin.php`, `app/Providers/RouteServiceProvider.php`, خدمات بحث بـ PDO مضمّن) — وهو خارج تفويض خطة v4 whose المبدأ الحاكم: **لا حذف ولا تعديل لأي ملف قديم**.

تُوثَّق هنا كـ **"دين تقني أمني"** يُطرح كمشروع أمني قائم بذاته، بموافقة صريحة مسبقة من صاحب القرار.

---

## 2. جدول المخاطر الحرجة

| المرجع | الخطر | الخطورة | الملف/الموضع |
|--------|-------|---------|--------------|
| R1, R25 | PHP/PDO credentials مضمّنة في الكود (4 خدمات بحث + routes) | 🔴 حرج | `routes/api.php` + `app/Services/{ExactMatch,Lightning,SmartExact,SimpleExact}Search.php` |
| R2 | Google Drive credentials في `rclone.conf` | 🔴 حرج | `rclone.conf` |
| R10 | حذف ملفات المعرض بدون auth | 🔴 حرج | `routes/api.php` ~493 |
| R18 | حذف الملفات المكررة للعامة بدون auth | 🔴 حرج | `routes/web.php` + `routes/admin.php` |
| R19 | `/auto-login` و `/s/{credentials}` بدون فحص جلسة | 🔴 حرج | `routes/web.php` ~155-156 |
| R20, R21 | مسارات رفع/إدارة ملفات بدون middleware إطلاقاً (`withoutMiddleware`) | 🔴 حرج | `routes/web.php` + `routes/admin.php` |
| R26 | تعطيل التحقق من SSL في `GoogleDriveService` (`'verify' => false` + `CURLOPT_SSL_VERIFYPEER=false`) | 🔴 حرج | `app/Services/GoogleDriveService.php` |
| C8, C9, C10 | تكرار تسجيل routes بحماية متضاربة (آخر تسجيل يفوز) | 🟠 عالي | `web.php`/`admin.php` |

---

## 3. جدول المخاطر العالية/المتوسطة (اختصار)

| # | الخطر | الخطورة |
|---|-------|---------|
| R3 | CORS wildcard `*` | 🟠 |
| R4 | 3 middleware فارغة (file_security, SecureFileAccess, SecurityHeaders) | 🟠 |
| R9 | `SimpleFileUploadController` بدون auth | 🟠 |
| R11 | Duplicate files API بدون auth | 🟠 |
| R12 | Civil registry save-bank-account بدون auth | 🟠 |
| R16 | لا يوجد `app/Policies` | 🟠 |
| R17 | `BROADCAST_DRIVER=log` + RealtimeSyncService يعتمد WebSocket | 🟠 |
| R23 | Test routes للعامة | 🟠 |
| R24 | `POST /api/replace-duplicate-file` بدون auth | 🟠 |
| R27 | `User::$fillable` يشمل `password` و `role` | 🟠 |
| R5 | ازدواج نظام مزامنة (2 Workers + 2 طابور + 5 SQLite) — **يُعالج جزئياً في v4** | 🟠 |
| R6 | `allowMixedContent=true` في android-v3 | 🟠 |
| R7 | `.env` + `.envbak` + `.env.speedtest` في repo | 🟠 |
| R13 | Single-job timeout 3600s | 🟡 |
| R14 | 4 نسخ `global_helper` | 🟡 |
| R15, R28 | ملفات backup controllers في شجرة المصنّف | 🟡 |

> **ملاحظة v4:** خطر R5 (ازدواج المزامنة) يعالجه محرك v4 الموحّد إصلاحاً جذرياً — يبقى المخاطر الأخرى خارج نطاق v4.

---

## 4. نطاق المشروع المقترح

### 4.1 المخرجات
1. توحيد حماية المسارات المتكررة (C8/C9/C10) — إزالة التسجيل المزدوج.
2. إضافة `auth`/`sanctum` لجميع المسارات الحرجّة (R9–R12, R18–R21, R24).
3. نقل الـ credentials المضمّنة إلى `.env` + تدوير المفاتيح المسربة (R1, R25, R2).
4. تفعيل التحقق في `GoogleDriveService` (R26).
5. إصلاح `/auto-login` (R19).
6. ملء الـ middleware الفارغة (R4) + إضافة Security Headers.
7. تقييد CORS (R3) + حماية `User::$fillable` (R27).

### 4.2 ما لا يُنفَّذ ضمن v4
- لا يُحذف أي route قديم أثناء انتقال v4.
- لا يُعدَّل `RouteServiceProvider.php` خارج إضافة مجموعات v4 (تم بالفعل).
- المراجعة الأمنية تبدأ بعد إغلاق v4 (المرحلة 8) بموافقة صريحة.

### 4.3 الترتيب المقترح
1. **أسبوع 1:** R26 + R1/R25 (تدوير أسرار) — أعلى خطورة.
2. **أسبوع 2:** R18–R21 + R9–R12 + R19 (مسارات بدون auth).
3. **أسبوع 3:** C8/C9/C10 + R4 + R3 + R27.
4. **أسبوع 4:** R23 + R24 + R7 + R15/R28 (تنظيف).

---

## 5. مرجع مستقل

- `android-v4/Doc/04_DATABASE_AND_API_CHANGES.md` §6
- `docs/ENCYCLOPEDIA.md` §47 (C1–C10) + §49 (R1–R28)
- `android-v4/Doc/00_MASTER_PLAN.md` المبدأ 6

**الحالة:** 📋 مُسجَّل — بانتظار قرار صاحب المشروع بفتح مشروع أمني مستقل.
