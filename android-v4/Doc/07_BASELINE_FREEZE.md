# 07 — تجميد النسخة المرجعية (Baseline Freeze)

> **المرحلة 0 — التجميد والتوثيق الأساسي** من `05_IMPLEMENTATION_PHASES_ROADMAP.md`.
> تاريخ التجميد: **2026-09-23**

---

## 1. النسخة المرجعية v3 (Baseline)

| البند | القيمة | المصدر |
|-------|--------|--------|
| المسار | `C:\xampp\htdocs\alhayahorphans\android-v3\` | — |
| **versionCode** | **110** | `android-v3/app/build.gradle` سطر 16 |
| **versionName** | **3.2** | `android-v3/app/build.gradle` سطر 17 |
| **applicationId** | **`com.aso.app`** | `android-v3/app/build.gradle` سطر 13 (ونفس `namespace`) |
| **اسم العرض (Display Name)** | **Sponsorships** | `android-v3/app/src/main/res/values/strings.xml` (`app_name`)، ويُستدعى عبر `android:label="@string/app_name"` في `AndroidManifest.xml` |
| minSdk / targetSdk / compileSdk | 24 / 36 / 36 | `variables.gradle` |
| Java | 21 | `build.gradle` |

الإصدار الحالي يعمل فعلياً على **10 أجهزة** كحد أدنى (انظر `00_MASTER_PLAN.md`).

---

## 2. قواعد SQLite الخمس المحلية في v3

| # | قاعدة البيانات | ملف Helper / Store (المسار الكامل داخل android-v3) | الحزمة |
|---|----------------|------------------------------------------------------|--------|
| 1 | `upload_queue.db` | `app/src/main/java/com/aso/app/UploadDatabaseHelper.java` | `com.aso.app` |
| 2 | `sponsorships_data.db` | `app/src/main/java/com/aso/app/SponsorshipsDatabaseHelper.java` | `com.aso.app` |
| 3 | `related_data.db` | `app/src/main/java/com/aso/app/RelatedDataDatabaseHelper.java` | `com.aso.app` |
| 4 | `data_sync.db` | `app/src/main/java/org/alhayah/sponsorships/DataSyncDatabaseHelper.java` | `org.alhayah.sponsorships` |
| 5 | `civil_registry.db` | `app/src/main/java/org/alhayah/sponsorships/CivilRegistryStore.java` | `org.alhayah.sponsorships` |

> المرجع: `docs/ENCYCLOPEDIA.md` القسم 42 (Android Local Databases).
> هذه القواعد الخمس تبقى **بعملها كما هي** خلال فترة Dual-Run ولا تُمسّ (المبدأ 3 في `00_MASTER_PLAN.md`).

---

## 3. الإصدار المستهدف v4

| البند | v3 (مرجعي) | v4 (مستهدف) |
|-------|------------|--------------|
| versionCode | 110 | **200** |
| versionName | 3.2 | **4.0** |
| applicationId | `com.aso.app` | **`com.aso.app`** (بلا تغيير — المبدأ 3 إلزامي) |
| اسم العرض | Sponsorships | **Sponsorships** (بلا تغيير — لا يظهر أي اختلاف للمستخدم) |

> قفزة `versionCode` من 110 إلى 200 توفر فجوة واضحة للتمييز في تقارير التحليلات (انظر `00_MASTER_PLAN.md` القسم 4).
> تُنفَّذ هذه القيم في **المرحلة 8** (قبل الإغلاق النهائي).

---

## 4. حالة النسخ الاحتياطي لقواعد MySQL

**التاريخ:** 2026-09-23 | **المجلد:** `C:\xampp\htdocs\alhayahorphans\.backups\v4_baseline\`

| قاعدة البيانات | الملف | الحجم | الحالة |
|----------------|-------|-------|--------|
| `aso` | `.backups/v4_baseline/aso_2026_09_23.sql` | 509,079,059 bytes (~485 MB) | ✅ ناجح |
| `civilregistry` | `.backups/v4_baseline/civilregistry_2026_09_23.sql` | 911,260,244 bytes (~869 MB) | ✅ ناجح |

**بيانات الاتصال المستخدمة (من `.env` — قُرئت فقط، لم يُعدَّل):**
- `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_USERNAME=root`, `DB_PASSWORD=` (فارغ)
- `DB_DATABASE=aso`, `CIVIL_DB_DATABASE=civilregistry`

**أداة النسخ:** `C:\xampp\mysql\bin\mysqldump.exe` (MariaDB 10.4.32 — XAMPP).

**ملاحظة تشغيلية:** خدمة MySQL لم تكن تعمل عند البدء؛ تم تشغيل `mysqld.exe` مؤقتاً ثم نُفِّذ النجاحان ثم أُعيد التحقق من الملفات (ترويسات dumps سليمة: `-- MariaDB dump 10.19 ... Database: aso` / `Database: civilregistry`).

**أمر يدوي للنسخ يدوياً عند الحاجة:**
```
C:\xampp\mysql\bin\mysqldump.exe -h 127.0.0.1 -P 3306 -u root aso > .backups\v4_baseline\aso_<YYYY_MM_DD>.sql
C:\xampp\mysql\bin\mysqldump.exe -h 127.0.0.1 -P 3306 -u root civilregistry > .backups\v4_baseline\civilregistry_<YYYY_MM_DD>.sql
```

**نسخ SQLite من الأجهزة (لم يُنفَّذ هنا — يتطلب وصولاً ميدانياً للـ10 أجهزة):**
تُوثَّق نسخ `upload_queue.db`, `sponsorships_data.db`, `related_data.db`, `data_sync.db`, `civil_registry.db` من كل جهاز حسب بند المرحلة 0 في `05_IMPLEMENTATION_PHASES_ROADMAP.md`.

---

## 5. ما لم يُمسّ

- ✅ لم يُعدَّل ملف `.env`.
- ✅ لم يُعدَّل أي ملف موجود في v3 أو Laravel.
- ✅ أُنشئت نسخ احتياطية جديدة فقط داخل `.backups/v4_baseline/`.

**تاريخ التجميد: 2026-09-23**
