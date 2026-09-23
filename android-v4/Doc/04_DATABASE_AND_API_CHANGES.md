# 04 — تعديلات قاعدة البيانات و API الجديد (v4)

## 1. المبدأ الحاكم

> "في حال كان هناك حاجة لإضافة حقول جديدة على قاعدة البيانات، التأكد بعدم ضياع البيانات أو المساس بأسماء الجداول والأعمدة الموجودة — فقط يمكن إضافة أعمدة إذا كان هناك حاجة ملحّة."

الحاجة الملحّة الوحيدة المبرَّرة معمارياً في هذه الخطة هي **دعم idempotency ومنع تكرار السجلات عند المزامنة من 10 أجهزة** (الملف 02) — وهذا يتطلب فعلياً أعمدة جديدة. كل تعديل أدناه هو: **إضافة عمود جديد Nullable لا يُغيّر بنية أو سلوك الجدول القديم أبداً**، ولا يُعاد تسمية أي شيء.

## 2. الأعمدة الجديدة (Migration جديدة، لا تُعدَّل أي migration قديمة)

migration جديدة باسم: `2026_XX_XX_add_v4_sync_columns.php` (ملف جديد بالكامل في `database/migrations/`)

| الجدول (بدون تغيير اسمه) | العمود الجديد | النوع | القيمة الافتراضية | الغرض |
|---------------------------|----------------|-------|---------------------|-------|
| `data` | `client_uuid` | `CHAR(36) NULLABLE UNIQUE` | `NULL` | معرّف idempotency من الملف 02 |
| `re_people` | `client_uuid` | `CHAR(36) NULLABLE UNIQUE` | `NULL` | " |
| `dead_pepoles` | `client_uuid` | `CHAR(36) NULLABLE UNIQUE` | `NULL` | " |
| `additional_deceased` | `client_uuid` | `CHAR(36) NULLABLE UNIQUE` | `NULL` | " |
| `guardian_bank_accounts` | `client_uuid` | `CHAR(36) NULLABLE UNIQUE` | `NULL` | " |
| `sponsorships` | `client_uuid` | `CHAR(36) NULLABLE UNIQUE` | `NULL` | " |
| (كل الجداول أعلاه) | `sync_origin_device_id` | `VARCHAR(64) NULLABLE` | `NULL` | تتبّع أي جهاز من الـ 10 أنشأ/عدّل السجل — ضروري لتشخيص أي تعارض مستقبلي |
| (كل الجداول أعلاه) | `needs_review` | `BOOLEAN` | `false` | علم التعارضات الحسّاسة من الملف 02 |

**Backfill آمن:** للسجلات القديمة الموجودة مسبقاً (قبل v4)، يُشغَّل Job واحد لمرة واحدة يولّد `client_uuid` عشوائياً لكل سجل قديم لا يملك قيمة (باستخدام `Str::uuid()` في Laravel)، **بدون أي تعديل على أي عمود أو قيمة أخرى موجودة فعلاً**.

**Trigger لتغطية السجلات المستقبلية من الموقع مباشرة:** نفس migration تنشئ `BEFORE INSERT TRIGGER` على كل جدول من الجداول أعلاه يولّد `client_uuid = UUID()` تلقائياً لأي صف جديد لا يحمل قيمة — سواء أُدرِج من الموقع (Blade/Controllers القديمة) أو من v3 أو من v4. هذا يضمن تغطية شاملة **دون تعديل أي ملف PHP قديم إطلاقاً** (تفاصيل السيناريو والمنطق الكامل في `02_UNIFIED_SYNC_ENGINE_AND_DUPLICATE_FIX.md` — المشكلة 4).

## 3. جداول جديدة بالكامل (لا علاقة لها بأي جدول قديم)

| الجدول الجديد | الغرض |
|----------------|-------|
| `sync_outbox_v4` | طابور العمليات المعلّقة القادمة من كل جهاز قبل تطبيقها (تسجيل، تعديل، حذف مؤجَّل) |
| `sync_idempotency_log_v4` | سجل كل `idempotency_key` تمت معالجته + الاستجابة المحفوظة — لمنع إعادة التنفيذ |
| `device_registry_v4` | تسجيل كل جهاز من الـ 10 (device_id، آخر ظهور، نسخة التطبيق، حالة الصحة) |
| `conflict_review_queue_v4` | التعارضات المحتملة التي تنتظر قراراً بشرياً (الملف 02) |
| `admin_dashboard_snapshot_v4` | لقطة دورية من أرقام لوحة القيادة (الملف 03) |
| `admin_reports_source_v4` | بيانات مسطّحة جاهزة للتقارير المحلية (الملف 03) |
| `permissions_manifest_v4` | نسخة موقّعة من صلاحيات كل مستخدم (الملف 03) |
| `record_audit_log_v4` | **(معتمد 2026-09-23 — الخيار أ)** سجل تدقيق حقل-بحقل لكل إنشاء/تحديث/محاولة تغيير حقل حسّاس من الأجهزة — مصدر تاريخي لكاشف التعارضات وشاشة Audit Trail (الملف 08 §3) — migration: `2026_09_23_000003_create_record_audit_log_v4_table.php` |

جميعها تُنشأ عبر migrations جديدة منفصلة، ولا تحتوي أي Foreign Key تُجبِر تعديل جدول قديم (تُستخدم علاقات منطقية بـ `client_uuid`/`id` بدون قيود FK صارمة على الجداول القديمة تفادياً لأي خطر عند migrate).

## 4. الفرق الحاسم في منطق الكتابة: Insert أعمى → Upsert آمن

**القديم** (`MobileRegistrationController::store`):
```
DB::transaction(function() {
    // Insert مباشر بدون فحص وجود مسبق
    Data::create([...]);
    RePeople::create([...]);
    ...
    DB::rollBack() عند الفشل فقط
});
```

**الجديد** (`SyncControllerV4` → `IdempotentUpsertServiceV4`):
```
1. تحقق من X-Idempotency-Key في sync_idempotency_log_v4
   → موجود؟ أرجع نفس الاستجابة المحفوظة فوراً (بدون أي Insert)
2. تحقق من client_uuid في الجدول المستهدف (updateOrCreate بمفتاح client_uuid وليس id)
3. شغّل ConflictDetectionServiceV4 على الحقول المنطقية الفريدة
   → تشابه محتمل؟ سجّل في conflict_review_queue_v4 ولا تُطبّق الحقول الحسّاسة
4. احفظ نتيجة العملية في sync_idempotency_log_v4
5. أرجع الاستجابة
```

هذا يُطبَّق **فقط** على مسارات `api_v4.php` الجديدة — `MobileRegistrationController.php` القديم يبقى كما هو حرفياً ويستمر بالعمل لأي جهاز لم يُحدَّث بعد لـ v4 (توافق عكسي كامل خلال فترة الانتقال).

## 5. عقد API الجديد (`routes/api_v4.php`)

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `POST` | `/api/mobile/v4/sync/registration` | تسجيل/تعديل سجل مع idempotency كاملة |
| `POST` | `/api/mobile/v4/sync/actions` | دفعة عمليات من `sync_outbox_v4` المحلي |
| `GET` | `/api/mobile/v4/sync/pull?since=` | سحب تزايدي موحّد (يغطي ما كانت تغطيه 4 نقاط مزامنة قديمة منفصلة) |
| `POST` | `/api/mobile/v4/device/register` | تسجيل جهاز جديد في `device_registry_v4` |
| `GET` | `/api/mobile/v4/device/health` | حالة كل الأجهزة العشرة (لوحة صحة المزامنة من الملف 03) |
| `GET` | `/api/mobile/v4/admin/dashboard-snapshot` | بيانات لوحة القيادة |
| `GET` | `/api/mobile/v4/admin/reports-source` | بيانات التقارير المسطّحة |
| `GET` | `/api/mobile/v4/admin/permissions-manifest` | صلاحيات المستخدم موقّعة |
| `GET` | `/api/mobile/v4/audit` | سجل التدقيق (`record_audit_log_v4`) — فلترة بـ client_uuid / entity_type / field_name / device_id / sensitive_only |
| `GET` | `/api/mobile/v4/conflicts` / `POST /api/mobile/v4/conflicts/{id}/resolve` | إدارة التعارضات |

كل مسار محمي فردياً بـ `auth:sanctum` + Rate Limiting مخصّص، ومُسجَّل بأسماء routes فريدة بادئتها `api.v4.*` (لا تصادم اسمي مع أي route قديم إطلاقاً).

## 6. الدين التقني الأمني الموروث — يُوثَّق ولا يُصلَح ضمن هذه الخطة

القرار الملزم رقم 6 في الخطة الرئيسية: أي إصلاح لهذه المخاطر يتطلب تعديل ملفات قديمة (`web.php`, `admin.php`, `RouteServiceProvider.php`, الخدمات ذات PDO المضمّن) — وهو خارج نطاق "عدم المساس بالملفات القديمة". يُوصى بشدة بفتح مشروع أمني منفصل بموافقة صريحة لمعالجة:

| المرجع | الخطر | الخطورة |
|--------|-------|----------|
| R1, R25 | PHP/PDO credentials مضمّنة في الكود (4 خدمات بحث + routes) | 🔴 حرج |
| R2 | Google Drive credentials في `rclone.conf` | 🔴 حرج |
| R10 | حذف ملفات المعرض بدون auth | 🔴 حرج |
| R18 | حذف الملفات المكررة للعامة بدون auth | 🔴 حرج |
| R19 | `/auto-login` بدون فحص جلسة | 🔴 حرج |
| R20, R21 | مسارات رفع/إدارة ملفات بدون middleware إطلاقاً | 🔴 حرج |
| R26 | تعطيل التحقق من SSL في `GoogleDriveService` | 🔴 حرج |
| C8, C9, C10 | تكرار تسجيل routes بحماية متضاربة | 🟠 عالي |

> ⚠️ هذه المخاطر موجودة بالفعل في الإنتاج بغضّ النظر عن مشروع v4 — يُنصَح بإبلاغ صاحب القرار بها بشكل مستقل فوراً، بمعزل عن جدول تنفيذ v4.
