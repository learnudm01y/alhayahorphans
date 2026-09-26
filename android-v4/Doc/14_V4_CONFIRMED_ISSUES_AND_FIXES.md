# 14 — مشاكل v4 المؤكَّدة (من `ALHAYAH_SYSTEM_TECHNICAL_REPORT.md`) والإصلاح

كل ما هنا **كود v4 الخاص فينا** (لا ملفات قديمة محمية) — الإصلاح مباشر ومسموح دون أي استثناء.

> **حالة التنفيذ 2026-09-24: كل البنود 1–4 مُنفَّذة.** التحقق النهائي: rebuild APK + تثبيت على الأجهزة.

---

## 1. نسخة `admin-api.js` قديمة فعلياً بالـ APK ✅ مُصلَّح

**ملخص:** `assets/public/js/admin-api.js` (كان 303 سطراً) ناقصة دالتين: `downloadCivilTemplate`, `validateCivilImport`.

**الإصلاح المُنفَّذ:** نُسخ المصدر (315 سطراً) → `android-v4/.../assets/public/js/` + hash متطابق · نفس النسخة لـ `sync-client-v4.js` و`civil-import.html` بعد إصلاحات التوكن · rebuild APK · **إعادة تثبيت على الأجهزة العشرة (متبقٍّ ميداني)**.

---

## 2. ازدواج مفتاح تخزين التوكن (`auth_token` مقابل `api_token`) ✅ مُصلَّح

### المشكلة المؤكَّدة
| الملف | المفتاح (قبل الإصلاح) |
|-------|----------------------|
| `token-interceptor.js` (v3) | `auth_token` (يقرأ/يكتب) |
| `admin-api.js` (v4) | `api_token` فقط — **ولا يكتبه أبداً** (لا `setToken` ولا أي `setItem('api_token'` في الشجرة كلها) |
| `sync-client-v4.js` | `api_token` فقط |
| `civil-import.html` (import XHR) | `api_token` فقط |

**إضافة حرجة:** `api_token` لا يكتبه أي كود — يعني `getToken()` كان يرجع فارغاً دائماً إلا لو ضُبط يدوياً. fallback إلى `auth_token` هو الإصلاح الكامل (لا حاجة لـ`setToken` — لا شاشة دخول v4).

### الإصلاح المُنفَّذ (بدون لمس `token-interceptor.js`)
```javascript
// admin-api.js getToken + sync-client-v4.js (3 أماكن) + civil-import.html
localStorage.getItem('api_token') || localStorage.getItem('auth_token') || ''
```
القراءة فقط: `api_token` (إن وُجد) ثم `auth_token` (من شاشات v3). **صفر كتابة** بملفات v4.

---

## 3. Fallback ضعيف بـ `IdempotencyKeyGeneratorV4.java` ✅ مُصلَّح

**قبل:** عند فشل SHA-256 → `Integer.toHexString(input.hashCode())` (32-bit، قابل للتصادم).

**بعد (التوصية نفسها بالملف — إزالة fallback لا استبداله):**
```java
} catch (Exception e) {
    // SHA-256 is guaranteed on every standard Android/JVM. Rethrow rather than
    // fall back to a weak key that could collide and break idempotency.
    throw new IllegalStateException("SHA-256 unavailable", e);
}
```
أُزيل أيضاً `import android.util.Log` و`TAG` غير المستخدمين.

---

## 4. تضارب عدد جداول التصنيف (21 مقابل 22) ✅ حُسم بـ21

**التحقق المباشر:** `AdminCrudControllerV4::CATEGORY_TABLES` = **21** عنصراً بالضبط · `categories-config.js` = 21 · nav «إدارة التصنيفات (21)».

**الإصلاح المُنفَّذ:** كتالوجات `08` (العنوان + القائمة — أُزيل `aid_statuses` المفرد `aid_status` و`data_request_status` غير الجدول) + `09` + `12` (بما فيها SQL التحقق بأسماء الجداول الصحيحة المفردة).

---

## 5. ملاحظات إضافية من قسم الأمان بالتقرير — تخص كود v4 تحديداً (وليس ضمن الدين الأمني القديم)

| # | الملاحظة | هل ضمن نطاق v4 القابل للإصلاح؟ |
|---|----------|----------------------------------|
| `X-Sync-Source` / `X-Device-Id` headers غير موقَّعة | ✅ نعم — كود v4 خاص فينا | تحسين مستقبلي: توقيع الـ headers بـ HMAC مماثل لـ`permissions_manifest_v4`، أولوية منخفضة حالياً لأن التوكن هو الحارس الفعلي |
| `app.key` إذا تسرّب يسمح بتزوير manifest الصلاحيات الموقّع | ✅ نعم — اعتماد v4 على `app.key` | ليس خللاً بحد ذاته (ممارسة قياسية Laravel)، لكن يُضاف كبند لخطة تدوير المفاتيح المستقبلية إن وُجدت (مرتبط بملف `SECURITY_DEBT_LEGACY_ROUTES.md` القسم 4.3 أسبوع 1) |

---

## 6. حالة خطة التنفيذ

| # | البند | الحالة |
|---|-------|--------|
| 1 | نسخ `admin-api.js` + rebuild | ✅ مُنفَّذ · التثبيت الميداني متبقٍ |
| 2 | توحيد التوكن | ✅ مُنفَّذ (3 ملفات) |
| 4 | حسم رقم الجداول = 21 | ✅ مُنفَّذ (08/09/12) |
| 3 | fallback الهاش | ✅ مُنفَّذ (إزالة) |
| — | إعادة اختبار سيناريو "ج" بملف 13 بعد التثبيت | ⏳ بعد التثبيت الميداني |
