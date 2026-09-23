# 08 — كتالوج الشاشات الشامل (v4) — مطابقة لوحة التحكم الكاملة

**القرار المعتمد من صاحب المشروع 2026-09-23:** المرحلة 5 تركّيزها على **مطابقة كل شاشات لوحة التحكم الموجودة حالياً على الموقع** — بكل مميزاتها وخصائصها وطريقة التعامل مع الملفات والبحث الشامل وغير ذلك — بحيث تكون على الهاتف **مطابقة تماماً** للوحة التحكم.

**قيد التنفيذ (2026-09-23):** إضافة وربط فقط — لا حذف/تعديل/إعادة تسمية أي ملف. `upload.html` / `photography.html` / `barcode.html` تبقى كما هي.

> هذا الكتالوج يشمل: (أ) الشاشات الأساسية المبنية سابقاً، (ب) الشاشات التقنية، (ج) كل شاشات لوحة التحكم v3 المطابَقة.

---

## أولاً: الشاشات الأساسية المبنية وموسَّعة (6)

| # | الشاشة | الحالة | ملاحظات |
|---|--------|--------|---------|
| 1 | لوحة القيادة الرئيسية | ✅ مبنية | KPIs + لقطة offline + AdminNav |
| 2 | إدارة الكفالات | ✅ موسَّعة | تبويبات مكفولين/غير مكفولين + فلاتر + تصدير Excel |
| 3 | التقارير | ✅ مبنية | توليد محلي PDF + AdminNav |
| 4 | إدارة الصلاحيات + المستخدمون + الأدوار | ✅ موسَّعة | أدوار CRUD + مستخدمين (`roles.html`/`users.html`) — **دمج مع #22** |
| 5 | إدارة الملفات (بوابة/مجلدات/مكررات/تدقيق) | ✅ موسَّعة | بوابة + مجلدات + مكررات + تدقيق (`files`/`folders`/`duplicates`/`attachment-audit`) — **دمج مع #23** |
| 6 | السجل المدني | ✅ موسَّعة | قراءة + بحث + استيراد Excel (`civil-import.html`) |

## ثانياً: الشاشات التقنية (6)

| # | الشاشة | الحالة |
|---|--------|--------|
| 7 | مراجعة التكرارات المحتملة | ✅ مبنية + AdminNav |
| 8 | لوحة صحة المزامنة | ✅ مبنية + AdminNav |
| 9 | سجل التدقيق (Audit Trail) | ✅ **مبنية** `screens/audit.html` |
| 10 | إدارة الأجهزة المسجَّلة | ✅ **مبنية** `screens/devices.html` |
| 11 | مركز الإشعارات المحلي | ✅ **مبنية** `screens/notifications.html` |
| 12 | البحث الموحّد (Global Search Hub) | ✅ **مبنية** `screens/global-search.html` |

## ثالثاً: شاشات لوحة التحكم v3 — منفَّذة (المرحلة 5)

### أ. إدارة التسجيلات (Records Management)

| # | الشاشة | الحالة | ملف |
|---|--------|--------|-----|
| 13 | قائمة السجلات | ✅ | `admin/records.html` |
| 14 | إدخال/تعديل سجل (تبويبات) | ✅ | `admin/record-form.html` |
| 15 | عرض تفاصيل سجل | ✅ | `admin/record-show.html` |
| 16 | البحث الشامل + فلاتر + اقتراحات | ✅ | `admin/search-records.html` |

### ب. إدارة التصنيفات (22 — شاشتان ديناميتكيتان)

| # | الشاشة | الحالة | ملف |
|---|--------|--------|-----|
| 17 | فهرس التصنيفات الـ22 | ✅ | `admin/categories.html` |
| 18 | CRUD ديناميكي لكل تصنيف `?cat=` | ✅ | `admin/category.html` |

الجداول المدعومة (whitelist في `AdminCrudControllerV4`): `academic_degrees`, `category_of_relations`, `aid_statuses`, `bank_names`, `city`, `currency_types`, `death_reasons`, `displacement_statuses`, `document_types`, `employment`, `general_category`, `health_statuses`, `orphan_needs`, `creativity_aspects`, `housing_status`, `marital_status`, `provinces`, `request_status`, `sponsorship_statuses`, `type_of_accommodation`, `type_of_guarantee`, `data_request_status`.

### ج. الكفالات (Sponsorships)

| # | الشاشة | الحالة |
|---|--------|--------|
| 19 | كل الكفالات | ✅ (موسَّع) |
| 20 | المكفولين / غير المكفولين | ✅ (تبويبات + API `sponsorships-list/*`) |
| 21 | إدارة الجمعيات (Sponsors) | ✅ `admin/sponsors.html` |

### د–ي. بقية الشاشات

| # | الشاشة | الحالة | ملف |
|---|--------|--------|-----|
| 22 | المستخدمون + الأدوار | — | **مُدمَج مع #4 أعلاه** (نفس الملفين) |
| 23 | بوابة/مجلدات/مكررات/تدقيق ملفات | — | **مُدمَج مع #5 أعلاه** (نفس الملفات) |
| 24 | استيراد السجل المدني | ✅ | `admin/civil-import.html` |
| 25 | طلبات المستخدمين | ✅ | `admin/user-requests.html` |
| 26 | الملف الشخصي + الإعدادات | ✅ | `admin/profile.html` + `admin/settings.html` |

> **الملفات الأصلية للمستخدم لم تُمس:** `upload.html`, `photography.html`, `barcode.html`, `index.html`, `data.html`, `detail.html`, `search.html`, `registration.html`, `full-file.html`, `sync-monitor.html`.

---

## البنية التشاركية المنفَّذة

| الملف | الغرض |
|-------|-------|
| `src/js/admin-api.js` | طبقة API موحدة online/offline + cache |
| `src/js/crud-helper.js` | جداول/نماذج/modal/pagination/toast |
| `src/js/categories-config.js` | خريطة الـ22 تصنيف |
| `src/js/admin-nav.js` | Sidebar/hamburger + `AdminNav.mount()` |
| `src/css/admin.css` | توسعة التصميم (RTL) |
| `app/Http/Controllers/Api/V4/AdminCrudControllerV4.php` | CRUD موحّد + بحث + ملفات + بروفايل… |
| `routes/api_v4.php` | +59 مسار `api.v4.*` (append فقط) |

---

## قائمة استلام الشاشات

| # | اسم الشاشة | الوصف |
|---|-----------|-------|
| 1 | مطابقة كاملة للوحة التحكم | ✅ **منفَّذة** — إضافة وربط فقط |

> ✅ **حُسم:** لا شاشات بزنس إضافية خارج مطابقة لوحة التحكم.
