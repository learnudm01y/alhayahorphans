# 13G — خطة إصلاح فجوات لوحة التحكم (نتائج التدقيق الفعلي)

> أُنشئت بتنفيذ منهجية `12_ADMIN_PANEL_COMPREHENSIVE_AUDIT_METHODOLOGY.md` فعلياً على السيرفر المحلي (2026-09-23). ليست افتراضات — كل سطر أدناه من نتيجة فحص مباشر.

---

## 1. نتائج الفحص الآلي (Backend)

### 1.1 جداول التصنيفات الـ21 ( whitelist `AdminCrudControllerV4::CATEGORY_TABLES` — بعد إصلاح 2026-09-24 )

> **سجل التدقيق الأصلي (2026-09-23) كان 22 مدخلاً خاطئاً:** `aid_statuses` (الجدول الصحيح `aid_status` مفرد) و`data_request_status` (عمود على `data` وليس جدولاً). أُصلح P0 #6 و #7 → القائمة الآن 21 تصنيفاً صحيحاً بالكامل.

| الجدول | موجود؟ | الصفوف | ملاحظة |
|--------|--------|--------|--------|
| academic_degrees | ✅ | 0 | فارغ — CRUD يعمل، لا بيانات seed |
| category_of_relations | ✅ | 0 | فارغ — عمود الملصق `attribute` |
| aid_status | ✅ | 0 | **مُصلَّح 2026-09-24** — كان مُسجَّلاً خطاً `aid_statuses` |
| bank_names | ✅ | 0 | فارغ |
| city | ✅ | 0 | فارغ — عمود الملصق `city` |
| currency_types | ✅ | 0 | فارغ |
| death_reasons | ✅ | 0 | فارغ |
| displacement_statuses | ✅ | 0 | فارغ |
| document_types | ✅ | 0 | فارغ — لا صفوف للتصنيف أصلاً |
| employment | ✅ | 0 | فارغ |
| general_category | ✅ | 0 | فارغ |
| health_statuses | ✅ | 0 | فارغ |
| orphan_needs | ✅ | **1** | فيه صف واحد فقط |
| creativity_aspects | ✅ | **1** | فيه صف واحد فقط |
| housing_status | ✅ | 0 | فارغ |
| marital_status | ✅ | 0 | فارغ |
| provinces | ✅ | 0 | فارغ |
| request_status | ✅ | 0 | فارغ |
| sponsorship_statuses | ✅ | 0 | فارغ |
| type_of_accommodation | ✅ | 0 | فارغ |
| type_of_guarantee | ✅ | 0 | فارغ |

**النتيجة بعد الإصلاح:** 21/21 موجودة وصحيحة، **19 فارغة أو شبه فارغة** (بيانات فقط).

### 1.2 مسارات v4

| الفحص | النتيجة |
|-------|---------|
| عدد المسارات المُحلَّلة | **60** |
| تكرار Method+URI | **صفر** (`DUP_ROUTES_NONE`) |

### 1.3 سجلات الأخطاء

| الفحص | النتيجة |
|-------|---------|
| ValidationException / 422 في آخر 2000 سطر | **صفر** |
| أخطاء v4 في آخر 2000 سطر | **صفر** |

### 1.4 جداول المرفقات / الرفع

| الجدول | النتيجة |
|--------|---------|
| chunked_uploads | موجود، **0 صف** — لا رفعات حديثة فاشلة/علقّة |
| attachments | موجود، **0 صف** (آخر created_at = NULL) |
| file_index_v4 | موجود، **0 صف** — لم يُبنى بعد (source فارغ) |
| sponsorships | **0 صف** |

**تفسير:** قاعدة `aso` المحلية بيئة تطوير فارغة تقريباً — لا يثبت خلل وظيفي، بل غياب بيانات. الفحص الميداني على الأجهزة العشرة (المرحلة 6) هو ما يكشف فجوات حقيقية.

### 1.5 صور sponsorships

| العمود | موجود؟ |
|--------|--------|
| orphan_photo_path | ✅ |
| guardian_photo_path | ✅ |

---

## 2. نتائج الفحص الأمامي (AdminNav / API / Catalog)

| الفحص | النتيجة | الإجراء المتخذ |
|-------|---------|----------------|
| تكرار href مطابق بالحرف في `admin-nav.js` | **صفر** (31 رابط فريد) | ✅ لا حاجة |
| `sponsorships.html` + `?tab=` × 3 | تبويبات مقصودة على نفس الملف — **ليس تكراراً** | ✅ مقبول |
| `fetch()` خارج `admin-api.js` | **2** في `civil-import.html` (template + validate) | ✅ **أُصلح** — أُضيف `AdminAPI.downloadCivilTemplate` + `validateCivilImport` |
| تكرار سطور كتالوج 08 (#4/#22 و #5/#23) | تكرار توثيقي فقط | ✅ **أُدمج** السطور في `08_NEW_SCREENS_CATALOG.md` |
| تكرار route Method+URI | **صفر** | ✅ |

**معيار قبول ملف 10 محقق بالكامل.**

---

## 3. قائمة الإصلاحات (Fix Plan)

### P0 — إصلاحات مُنفَّذة ضمن هذا التدقيق

| # | الفجوة | الإصلاح | الحالة |
|---|--------|---------|--------|
| 1 | fetch مباشر بـ `civil-import.html` | نقل لـ `AdminAPI` | ✅ |
| 2 | تكرار توثيقي بالكتالوج 08 | دمج السطور | ✅ |
| 3 | صور شخصية لا تُحمَّل محلياً | migration `000004` + `ProfilePhotoSyncManagerV4` + ربط بـ `runFullCycle` | ✅ |
| 4 | فهرسة متأخرة بعد رفع الملفات | استدعاء `upsertFileIndexEntry` فور `STATUS_COMPLETED` في `ChunkedUploadWorker` + `DriveStatusWorker` | ✅ |
| 5 | أعمدة cache_priority محلياً | `AdminOfflineDatabaseHelperV4` v1→v2 ALTER + overload upsert | ✅ |
| 6 | whitelist `aid_statuses` خاطئ (الجدول الفعلي `aid_status` مفرد) | تغيير whitelist + `categories-config.js` إلى `aid_status` | ✅ 2026-09-24 |
| 7 | `data_request_status` ليس جدولاً (عمود على `data` يشير لـ `request_status`) | إزالته من whitelist + `categories-config.js` (21 فئة بدل 22) | ✅ 2026-09-24 |
| 8 | CRUD التصنيفات يبحث/يُدرج بعمود `name` والجداول القديمة تستخدم `description`/`attribute`/`city` → فشل الإدراج | `labelColumn()` في `AdminCrudControllerV4` + alias `name` في الاستجابات + map عند Store/Update | ✅ 2026-09-24 |
| 9 | `tests/Feature/SecureFileSystemTest.php` فارغ (0 سطر) → Class not found EXIT=1 | كتابة اختبار فعلي لـ `BlockSuspiciousStoragePaths` (6 حالات) | ✅ 2026-09-24 |

### P1 — يحتاج قرار صاحب المشروع / بيانات حقيقية

| # | الفجوة | التفاصيل المقترحة | الحالة |
|---|--------|-------------------|--------|
| 1 | ~~جدول `aid_statuses` مفقود~~ | أُصلح — الجدول الصحيح `aid_status` موجود ومُفعَّل في whitelist (بند P0 #6) | ✅ 2026-09-24 |
| 2 | ~~جدول `data_request_status` مفقود كجدول~~ | أُصلح — أُزيل من whitelist لأنه عمود على `data` وليس جدولاً؛ الحالة تُدار عبر `request_status` (بند P0 #7) | ✅ 2026-09-24 |
| 3 | 19 جدول تصنيف فارغ/شبه فارغ | ليست فجوة برمجية — شاشات CRUD تعمل، فقط لا seed data. تُملأ بإدخال المستخدم أو استيراد من الإنتاج | ⏸ بيانات |
| 4 | `file_index_v4` فارغ (0 صف) | متوقع — لم يُبنى source بعد. يمتلئ تلقائياً بعد أول مزامنة كاملة + رفعات فعلية على أجهزة المرحلة 6 | ⏸ طبيعي |

### P2 — فحوصات ميدانية إلزامية (المرحلة 6 — 10 أجهزة)

| البند | طريقة |
|-------|-------|
| فتح كل `?cat=` × 21 والتحقق CRUD | يدوي على جهاز |
| بحث `search-records.html` أوفلاين | Airplane Mode |
| `user-requests.html` → controller حقيقي؟ | `AdminCrudControllerV4` routes موجودة ✅ |
| تصدير Excel بالكفالات | ضغط زر فعلي |
| `civil-import.html` بملف 5000+ صف | قياس timeout |
| أخطاء console لكل شاشة | `adb logcat` أثناء الفتح |

---

## 4. ملاحظات بيئة التطوير المحلية

- قاعدة `aso` المحلية **فارغة جداً** (sponsorships=0, attachments=0, أغلب التصنيفات=0) — نتائج "جدول فارغ" هنا **لا تعني** أن الإنتاج فارغ.
- التحقق الحقيقي من امتلاء الفهارس و`cache_priority` يكون بعد تشغيل المزامنة على جهاز ميداني متصل بالإنتاج.
- أوامر التحقق بعد أي مزامنة ميدانية:
  ```sql
  SELECT cache_priority, COUNT(*) FROM aso.file_index_v4 GROUP BY cache_priority;
  SELECT COUNT(*) FROM aso.file_index_v4 WHERE cache_priority='profile_photo';
  ```

---

## 5. معيار القبول (ملف 12)

- [x] سكربت 3.1 شُغِّل (بأسماء whitelist الصحيحة وليس أسماء UNION القديمة)
- [x] `route:list` → 60 مسار، صفر تكرار
- [x] laravel.log → صفر 422 / صفر أخطاء v4
- [x] فحص AdminNav → صفر href مكرر
- [x] فحص fetch → أُصلح آخر مخالفين
- [x] توثيق النتائج في هذا الملف
- [ ] فحوصات P2 الميدانية على 10 أجهزة (مرتبطة بالمرحلة 6)
