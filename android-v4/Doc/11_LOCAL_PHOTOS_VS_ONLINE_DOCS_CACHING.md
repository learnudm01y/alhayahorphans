# 11 — الصور الشخصية محلياً دائماً / باقي الوثائق عند الاتصال فقط

## 1. المتطلب

> "جميع الصور الشخصية تكون متاحة محلياً، باقي الوثائق عند اتصال الانترنت."

يعني: **الصورة الشخصية الأساسية** لكل شخص/حالة (الصورة الظاهرة بالبطاقة/القائمة/التفاصيل) يجب أن تكون **محمَّلة ومخزَّنة على الجهاز دائماً** (متاحة أوفلاين 100%)، بينما **باقي المرفقات** (مستندات، صور إضافية، ملفات PDF، إلخ) تبقى **فهرسة فقط محلياً** (metadata فقط، بدون تحميل الملف الفعلي) وتُجلَب من السيرفر عند الطلب فقط متى توفّر اتصال.

## 2. لماذا هذا التمييز ضروري (وليس تخزين كل شي أو ولا شي)

- تخزين **كل** المرفقات (78,554+ ملف حالياً وبيكبر) محلياً على كل من الـ10 أجهزة = استهلاك تخزين ضخم غير مبرَّر (كثير مرفقات نادراً ما تُفتَح).
- عدم تخزين **ولا حتى الصورة الشخصية** = أهم عنصر بصري لتمييز الحالة (خصوصاً بمعرف المتصل `CallerInfoService` وشاشات القوائم) يختفي بالكامل بدون إنترنت — تجربة استخدام سيئة جداً بميدان العمل.
- **الحل الوسط:** الصورة الشخصية (عنصر بصري حرج صغير الحجم نسبياً) دائماً محلية؛ الباقي (مستندات، أحياناً بحجوم كبيرة) عند الطلب فقط.

## 3. التعديل المطلوب على `file_index_v4`

عمود جديد واحد فقط (nullable افتراضياً، يحافظ على المبدأ الحاكم لعدم المساس ببنية قديمة — الجدول أصلاً جديد بالكامل من الملف 03، فهذا تعديل على جدول v4 نفسه لا على جدول قديم):

```sql
ALTER TABLE file_index_v4
  ADD COLUMN cache_priority VARCHAR(20) NOT NULL DEFAULT 'on_demand' AFTER status,
  ADD COLUMN local_cache_path VARCHAR(500) NULL AFTER cache_priority,
  ADD COLUMN cached_at DATETIME NULL AFTER local_cache_path;
-- cache_priority: 'profile_photo' (يُحمَّل دائماً) | 'on_demand' (فقط عند الطلب أونلاين)
```

نسخة Laravel migration مكافئة (ملف جديد `2026_09_2X_000004_add_cache_priority_to_file_index_v4.php`):
```php
Schema::table('file_index_v4', function (Blueprint $table) {
    $table->string('cache_priority', 20)->default('on_demand')->after('status');
    $table->string('local_cache_path', 500)->nullable()->after('cache_priority');
    $table->dateTime('cached_at')->nullable()->after('local_cache_path');
});
```

## 4. آلية تصنيف الصورة الشخصية تلقائياً

عند بناء فهرس `file_index_v4` (سواء بالـ Backfill الأولي أو بأي إدراج جديد عبر المزامنة)، يُحدَّد `cache_priority = 'profile_photo'` إذا:
- المرفق مرتبط بحقل `photo_paths` بجدول `sponsorships` (العمود الموجود أصلاً `2026_08_20_113149_add_photo_paths_to_sponsorships_table` — قراءة فقط، بدون تعديل)، **أو**
- نوع المستند (`document_types`) من نوع "صورة شخصية"/"صورة الحالة" (إن وُجد تصنيف مشابه بجدول `document_types` الموجود أصلاً — قراءة فقط)، **أو**
- أول صورة (`file_type` من نوع صورة `jpg/png/jpeg`) مرتبطة بالسجل بترتيب `created_at` تصاعدياً (fallback منطقي إذا الحقول أعلاه غير متاحة).

كل باقي المرفقات (مستندات PDF، صور إضافية غير الأولى، ملفات الوفاة، إلخ) تبقى `on_demand`.

## 5. منطق المزامنة على الجهاز (Android)

`FileManagementOfflineQueueV4` / جزء جديد `ProfilePhotoSyncManagerV4.java`:

```java
public class ProfilePhotoSyncManagerV4 {
    // يُستدعى في كل دورة مزامنة (UnifiedSyncOrchestratorV4) بعد سحب file_index_v4 الجديد
    public void downloadPendingProfilePhotos() {
        List<FileIndexEntry> pending = fileIndexDb.query(
            "SELECT * FROM file_index_v4 WHERE cache_priority = 'profile_photo' AND local_cache_path IS NULL"
        );
        for (FileIndexEntry entry : pending) {
            // تحميل فعلي للملف (بخلاف باقي المرفقات التي تبقى metadata فقط)
            String localPath = downloadAndStore(entry.getFilePath(), entry.getClientUuid());
            fileIndexDb.update(entry.getId(), localPath, now());
        }
    }
}
```

- هذا التحميل يحدث **تلقائياً وبأولوية أعلى** من أي تحميل on-demand آخر — يُدرَج في بداية طابور المزامنة (`sync_v4.db`)، **قبل** أي بيانات إدارية أخرى (Phase 4 من التصميم الحالي)، لأنه العنصر البصري الأكثر استخداماً يومياً (قوائم، معرف المتصل).
- الصور الشخصية **لا تُحذَف من التخزين المحلي أبداً تلقائياً** (لتفادي إعادة تحميلها من الصفر لاحقاً)، إلا بأمر يدوي صريح من المستخدم ("مسح الذاكرة المؤقتة") — يُضاف كخيار جديد بشاشة الإعدادات (`admin/settings.html`، إضافة بدون تعديل بنية الملف الأساسية).

## 6. باقي الوثائق — منطق "عند الطلب" (On-Demand)

- الشاشات التي تعرض مرفقات (`admin/files.html`, شاشة تفاصيل السجل `admin/record-show.html`) تعرض **قائمة الفهرسة فقط** (اسم الملف، النوع، الحجم، تاريخ الرفع) من `file_index_v4` — بدون تحميل فعلي.
- عند نقر المستخدم على مرفق معيّن:
  - **متصل بالإنترنت:** تحميل فوري + تخزين مؤقت (Cache) بحد أقصى (مثلاً آخر 50 ملف تم فتحه، LRU eviction) لتسريع إعادة الفتح خلال نفس الجلسة.
  - **غير متصل:** رسالة واضحة "هذا المستند يتطلب اتصال بالإنترنت — سيُفتَح تلقائياً عند توفر الاتصال"، مع خيار "إضافة لقائمة التحميل عند الاتصال" (يُسجَّل بـ`sync_outbox_v4` كطلب تحميل مؤجَّل بنفس آلية الملف 02/03).

## 7. تحديث معيار قبول اختبار Airplane Mode (يُدمَج مع ما تحقق سابقاً)

إضافة على ما تحقق بالمرحلة 4 (توليد التقارير offline): يُختبَر أيضاً أنه بوضع Airplane Mode:
- الصور الشخصية لكل الحالات المُزامنة سابقاً **تظهر فعلياً** بالقوائم والتفاصيل ومعرف المتصل.
- محاولة فتح مستند غير الصورة الشخصية تُظهر رسالة "يتطلب اتصال" بدل شاشة بيضاء أو خطأ صامت.

## 8. أوامر تحقق ميدانية

```bash
# التحقق من توزيع cache_priority بعد إضافة العمود والتصنيف التلقائي
mysql -u root -p -e "SELECT cache_priority, COUNT(*) FROM aso.file_index_v4 GROUP BY cache_priority;"

# التحقق من أن عدد الصور الشخصية المصنَّفة منطقي (يقارب عدد السجلات النشطة، لا كل 78K+ ملف)
mysql -u root -p -e "SELECT COUNT(*) FROM aso.file_index_v4 WHERE cache_priority = 'profile_photo';"
```

## 9. حالة التنفيذ (2026-09-23)

| البند | الحالة |
|-------|--------|
| Migration `2026_09_23_000004_add_cache_priority_to_file_index_v4.php` | ✅ نُفِّذ — أعمدة `cache_priority` / `local_cache_path` / `cached_at` موجودة فعلياً بقاعدة `aso` |
| تصنيف تلقائي (sponsorships photo paths + fallback أول صورة لكل هوية) | ✅ داخل الـ migration (يُنفَّذ عند وجود صفوف — حالياً `file_index_v4`=0 بيئة التطوير) |
| `AdminOfflineDatabaseHelperV4` v1→v2 (ALTER محلي `admin_offline_v4.db`) | ✅ `onUpgrade` يضيف الأعمدة بدون DROP |
| `AdminOfflineDataStoreV4.upsertFileIndexEntry` يمرر `cache_priority`/`local_cache_path`/`cached_at` | ✅ overload جديد |
| `ProfilePhotoSyncManagerV4.java` | ✅ مُنشأ — يحمّل `profile_photo` pending كحد أقصى 40/دورة |
| ربط بـ `UnifiedSyncOrchestratorV4.runFullCycle` كـ Phase 4b (بعد `pullAdminOffline`) | ✅ non-fatal |
| مساحة الأيقونة/النص لـ "مسح الذاكرة المؤقتة" بشاشة settings | ⏸ اختياري — يُنفَّذ عند الحاجة الميدانية |
| معيار قبول Airplane Mode (صور تظهر + رسالة "يتطلب اتصال") | ⏸ مرتبط بالمرحلة 6 (10 أجهزة) |

**بناء APK بعد هذه التعديلات:** ✅ ناجح — `app-debug.apk` 55.8MB · 2026-09-23 14:47 · versionCode 200.
