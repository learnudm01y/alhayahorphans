# 07 — التفصيل التقني العميق لكل مرحلة (مكمّل لـ `05_IMPLEMENTATION_PHASES_ROADMAP.md`)

هذا الملف يُفصّل كل مرحلة إلى مهام تقنية قابلة للتنفيذ المباشر (Class/Method/Endpoint/Test) بدون أي قرار معلّق.

---

## المرحلة 0 — التجميد والتوثيق الأساسي

### 0.1 نسخ احتياطي
```bash
# MySQL
mysqldump -u root -p --single-transaction --routines --triggers aso > backup_aso_pre_v4_$(date +%Y%m%d).sql
mysqldump -u root -p --single-transaction civilregistry > backup_civilregistry_pre_v4_$(date +%Y%m%d).sql
```
- يُخزَّن النسخ في مكان منفصل عن السيرفر (Google Drive/خارجي) — ليس فقط على نفس القرص.

### 0.2 نسخ SQLite من الأجهزة العشرة
- سكربت ADB بسيط يُشغَّل يدوياً على كل جهاز:
```bash
adb shell run-as com.aso.app cp /data/data/com.aso.app/databases/upload_queue.db /sdcard/backup_v3/
adb pull /sdcard/backup_v3/ ./device_backups/device_<N>/
```
- يُكرَّر لكل قاعدة من الخمس (`upload_queue.db`, `sponsorships_data.db`, `related_data.db`, `data_sync.db`, `civil_registry.db`) على كل جهاز.

### 0.3 قائمة تكافؤ الميزات (نموذج الأعمدة المطلوبة في الجدول)
| الميزة | ملف v3 المرجعي | معيار القبول في v4 |
|--------|------------------|----------------------|
| الرفع المجزأ | `ChunkedUploadWorker.java` | نفس السلوك + idempotent |
| الكاميرا/الباركود | `NativeCameraPlugin`, `BarcodeScannerPlugin` | بلا تغيير (يُستدعى كما هو) |
| معرف المتصل | `CallerInfoService.java` | بلا تغيير |
| البحث المحلي | `BackgroundSyncPlugin.getDataByIdNumber` | يقرأ من `sync_v4.db` بدل `related_data.db` |
| السجل المدني Offline | `CivilRegistryStore.java` | بلا تغيير (خارج نطاق التوحيد) |

**Definition of Done:** الجدول أعلاه مكتمل لكل الميزات الـ44 (القسم 44 من الموسوعة) وموقَّع.

---

## المرحلة 1 — تعديلات قاعدة البيانات

مرجع الكود الجاهز: `06_MIGRATION_v4_sync_columns_and_triggers.sql` و`06_..._add_v4_sync_columns_and_triggers.php`.

### خطوات التنفيذ بالترتيب
1. `php artisan migrate --pretend` على staging (معاينة SQL الناتج بدون تنفيذ).
2. تنفيذ فعلي: `php artisan migrate` على staging.
3. اختبار يدوي: إدراج صف تجريبي في `data` بدون تمرير `client_uuid` → تأكيد أن الـ Trigger ولّد قيمة تلقائياً.
4. اختبار الـ Backfill: `SELECT COUNT(*) FROM data WHERE client_uuid IS NULL;` يجب أن يُرجع `0`.
5. اختبار Rollback: `php artisan migrate:rollback --step=1` ثم التحقق أن الجداول القديمة عادت لحالتها الأصلية تماماً (`SHOW COLUMNS FROM data;`).
6. تكرار الخطوات 1-5 على production خارج ساعات الذروة، مع مراقبة `SHOW PROCESSLIST;` أثناء التنفيذ (خصوصاً خطوة الـ Backfill على جدول `data` إن كان كبيراً).

**Definition of Done:** Migration ناجحة + Rollback ناجح + صفر صفوف بدون `client_uuid`.

---

## المرحلة 2 — طبقة API الجديدة (Backend v4)

### 2.1 هيكل `IdempotentUpsertServiceV4`
```php
class IdempotentUpsertServiceV4
{
    public function handle(string $idempotencyKey, string $entityType, array $payload, string $deviceId): array
    {
        // 1. فحص التكرار على مستوى الطلب
        $existingLog = DB::table('sync_idempotency_log_v4')
            ->where('idempotency_key', $idempotencyKey)->first();
        if ($existingLog) {
            return json_decode($existingLog->response_json, true); // Retry-safe
        }

        return DB::transaction(function () use ($idempotencyKey, $entityType, $payload, $deviceId) {
            $model = $this->resolveModel($entityType);

            // 2. Upsert بمفتاح client_uuid لا id
            $record = $model::updateOrCreate(
                ['client_uuid' => $payload['client_uuid']],
                array_merge($payload, ['sync_origin_device_id' => $deviceId])
            );

            // 3. كشف تعارض المحتوى (مستقل عن نجاح الـ upsert أعلاه)
            $conflict = app(ConflictDetectionServiceV4::class)->check($entityType, $record, $payload);
            if ($conflict) {
                $record->update(['needs_review' => true]);
            }

            $response = ['success' => true, 'id' => $record->id, 'conflict' => (bool) $conflict];

            // 4. حفظ في سجل idempotency لمنع أي إعادة تنفيذ لاحقة
            DB::table('sync_idempotency_log_v4')->insert([
                'idempotency_key' => $idempotencyKey,
                'response_json'   => json_encode($response),
                'entity_type'     => $entityType,
                'entity_id'       => $record->id,
                'created_at'      => now(),
            ]);

            return $response;
        });
    }
}
```

### 2.2 هيكل `ConflictDetectionServiceV4`
```php
class ConflictDetectionServiceV4
{
    // حقول الفحص لكل نوع كيان — يُوسَّع حسب entity_type
    private array $matchFieldsMap = [
        'person' => ['national_id'],                 // أولوية 1: تطابق تام
        'person_fallback' => ['full_name', 'date_of_birth'], // أولوية 2: تشابه احتمالي
    ];

    public function check(string $entityType, $record, array $incomingPayload): ?array
    {
        if ($entityType !== 'person') return null;

        // تطابق تام برقم الهوية (إن وُجد)
        if (!empty($incomingPayload['national_id'])) {
            $exact = DB::table('re_people')
                ->where('national_id', $incomingPayload['national_id'])
                ->where('id', '!=', $record->id)
                ->first();
            if ($exact) {
                return $this->logConflict($record, $incomingPayload, 'national_id_match', 1.0);
            }
        }

        // تشابه احتمالي بالاسم + تاريخ الميلاد
        $similar = DB::table('re_people')
            ->where('full_name', $incomingPayload['full_name'] ?? null)
            ->where('date_of_birth', $incomingPayload['date_of_birth'] ?? null)
            ->where('id', '!=', $record->id)
            ->first();
        if ($similar) {
            return $this->logConflict($record, $incomingPayload, 'name_dob_match', 0.75);
        }

        return null;
    }

    private function logConflict($record, $incomingPayload, $reason, $confidence): array
    {
        DB::table('conflict_review_queue_v4')->insert([
            'entity_type'            => 'person',
            'existing_record_id'     => $record->id,
            'existing_record_uuid'   => $record->client_uuid,
            'incoming_payload_json'  => json_encode($incomingPayload),
            'incoming_source'        => request()->header('X-Sync-Source', 'app_v4'),
            'incoming_device_id'     => request()->header('X-Device-Id'),
            'match_reason'           => $reason,
            'match_confidence'       => $confidence,
            'status'                 => 'open',
            'created_at'             => now(),
        ]);
        return ['reason' => $reason, 'confidence' => $confidence];
    }
}
```

### 2.3 اختبارات إلزامية (PHPUnit)
```php
public function test_duplicate_idempotency_key_returns_cached_response_without_new_insert()
{
    $key = hash('sha256', 'test-payload');
    $payload = ['client_uuid' => Str::uuid(), 'full_name' => 'Test Person', ...];

    $r1 = $this->postJson('/api/mobile/v4/sync/registration', $payload, ['X-Idempotency-Key' => $key]);
    $countAfterFirst = RePeople::count();

    $r2 = $this->postJson('/api/mobile/v4/sync/registration', $payload, ['X-Idempotency-Key' => $key]);
    $countAfterSecond = RePeople::count();

    $this->assertEquals($countAfterFirst, $countAfterSecond); // لا زيادة
    $this->assertEquals($r1->json(), $r2->json());             // نفس الاستجابة تماماً
}

public function test_similar_person_from_different_device_flagged_not_duplicated()
{
    // جهاز A يسجل شخصاً
    $this->postJson('/api/mobile/v4/sync/registration', $personA, ['X-Device-Id' => 'device-A', ...]);
    // جهاز B يسجل نفس الشخص برقم هوية مطابق
    $response = $this->postJson('/api/mobile/v4/sync/registration', $personB_sameNationalId, ['X-Device-Id' => 'device-B', ...]);

    $this->assertTrue($response->json('conflict'));
    $this->assertDatabaseHas('conflict_review_queue_v4', ['match_reason' => 'national_id_match']);
    $this->assertEquals(1, RePeople::where('national_id', $personA['national_id'])->count()); // ما زال سجل واحد فقط "معتمد"، الثاني معلّق مراجعة
}
```

**Definition of Done:** كل الاختبارات أعلاه خضراء + Postman/Insomnia collection موثَّقة لكل مسار v4.

---

## المرحلة 3 — محرك المزامنة الموحّد (Android)

### 3.1 منع التوازي عبر WorkManager (الكود الفعلي)
```java
public class SyncSchedulerV4 {
    public static void scheduleUnifiedSync(Context context) {
        PeriodicWorkRequest syncRequest = new PeriodicWorkRequest.Builder(
                SyncWorkerV4.class, 15, TimeUnit.MINUTES)
            .setConstraints(new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build())
            .build();

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            "unified_sync_v4",                       // اسم فريد — مفتاح المنع
            ExistingPeriodicWorkPolicy.KEEP,          // لا يُنشئ نسخة ثانية أبداً
            syncRequest
        );
    }
}
```

### 3.2 توليد الـ Idempotency Key على الجهاز
```java
public class IdempotencyKeyGeneratorV4 {
    public static String generate(String clientUuid, String operationType, String payloadJson) {
        String raw = clientUuid + "|" + operationType + "|" + payloadJson;
        return sha256Hex(raw); // نفس دالة الهاش المستخدمة على الباكند لضمان التطابق
    }
}
```

### 3.3 تسلسل عمل `SyncOutboxManagerV4`
1. أي شاشة تُنشئ سجلاً جديداً → تستدعي `SyncOutboxManagerV4.enqueue(entity, operationType, payload)`.
2. `enqueue()` يولّد `client_uuid` فوراً (إن لم يكن موجوداً) + `idempotency_key`، ويكتب في `sync_v4.db` جدول `outbox_local` بحالة `pending`.
3. `SyncWorkerV4.doWork()` (عند تشغيله من WorkManager):
   - يقرأ كل الصفوف `pending` من `outbox_local` بالترتيب (`created_at_device` تصاعدياً).
   - يرسلها **دفعة واحدة** لـ `POST /api/mobile/v4/sync/actions` (وليس طلباً منفصلاً لكل صف — تقليل الحمل).
   - عند نجاح الاستجابة: يُحدِّث الحالة المحلية إلى `applied` ويحذف من `outbox_local` بعد تأكيد.
   - عند فشل الشبكة أثناء الإرسال: **لا حذف** — يبقى `pending` ليُعاد لاحقاً (آمن بفضل idempotency).

### 3.4 خطة الاختبار الميداني (جهازين، أسبوع)
- سيناريو يومي مُكرَّر: تسجيل 5 حالات على كل جهاز بالتزامن، قطع الشبكة يدوياً 3 مرات يومياً أثناء المزامنة.
- مقياس النجاح: `SELECT client_uuid, COUNT(*) FROM re_people GROUP BY client_uuid HAVING COUNT(*) > 1;` يجب أن يُرجع 0 صفوف كل يوم.

**Definition of Done:** أسبوع كامل بدون أي `client_uuid` مكرر + استهلاك بطارية/بيانات أقل أو مساوٍ لمحرّك v3 القديم (يُقاس عبر Android Battery Historian).

---

## المرحلة 4 — تكافؤ لوحة التحكم Offline

### 4.1 مزامنة تزايدية لبيانات الإدارة (نفس المنسِّق الموحّد، نوع بيانات إضافي)
```java
// داخل UnifiedSyncOrchestratorV4 — مرحلة إضافية بعد مزامنة البيانات التشغيلية
private void syncAdminData(String sinceTimestamp) {
    ApiResponse dashboard = apiClient.get("/api/mobile/v4/admin/dashboard-snapshot?since=" + sinceTimestamp);
    adminOfflineDb.upsertSnapshot(dashboard.getBody());

    ApiResponse reports = apiClient.get("/api/mobile/v4/admin/reports-source?since=" + sinceTimestamp);
    adminOfflineDb.upsertReportsSource(reports.getBody());

    ApiResponse permissions = apiClient.get("/api/mobile/v4/admin/permissions-manifest");
    permissionsCacheManagerV4.store(permissions.getBody()); // يتحقق من التوقيع HMAC قبل التخزين
}
```

### 4.2 توليد تقرير PDF محلي (بدون اتصال)
- `ReportsEngineV4.java` يستخدم `android.graphics.pdf.PdfDocument` (مدمج في Android SDK، بدون مكتبة خارجية إضافية):
  1. يقرأ الصفوف من `admin_reports_source_v4` المحلية.
  2. يملأ قالب HTML/CSS (نسخة مطابقة بصرياً لقالب Blade الأصلي، قراءة فقط دون تعديله).
  3. يُصيّر القالب عبر `WebView.createPrintDocumentAdapter()` أو تحويل مباشر لصفحات `PdfDocument`.
  4. يُضاف Watermark أسفل الصفحة: `"بيانات محلية بتاريخ: {last_admin_sync_at}"`.

### 4.3 اختبار العزل الكامل عن الشبكة
- تفعيل Airplane Mode فعلياً على جهاز الاختبار قبل فتح كل شاشة من شاشات لوحة التحكم في `mobile-app-v4/src/admin/`.
- كل شاشة يجب أن تُحمَّل من `admin_offline_v4.db` فقط — أي استدعاء شبكة فاشل يُسجَّل كخلل حرج (raise فوري، ليس Fallback صامت).

**Definition of Done:** كل شاشات لوحة التحكم (القائمة في الملف 03، القسم 6) تعمل وتُنتج بيانات صحيحة في Airplane Mode الكامل.

---

## المرحلة 5 — الشاشات الجديدة الإضافية

انظر الملف الجديد `08_NEW_SCREENS_CATALOG.md` للقائمة الكاملة المقترحة + نموذج التنفيذ التقني لكل شاشة.

---

## المرحلة 6 — اختبار تحمّل 10 أجهزة (تفصيل السيناريوهات)

### سيناريو A: تصادم إنشاء متزامن
```
T+0s   جهاز 1 و جهاز 2 (أوفلاين كلاهما) يُنشئان سجلاً لنفس الشخص (رقم هوية مطابق)
T+30s  جهاز 1 يتصل بالإنترنت → يُزامن → يُنشأ سجل جديد (لا تعارض بعد، هو الوحيد على السيرفر)
T+60s  جهاز 2 يتصل → يُزامن → ConflictDetectionServiceV4 يكتشف national_id_match
       → السجل الثاني يُعلَّم needs_review، يظهر في طابور المراجعة
النتيجة المتوقعة: سجل واحد "نشط" + سطر تعارض واحد بانتظار قرار بشري، صفر سجلات مكررة صامتة
```

### سيناريو B: انقطاع أثناء الاستجابة (Retry Storm)
```
T+0s   جهاز 3 يرسل POST /sync/actions لدفعة من 5 عمليات
T+2s   السيرفر ينفّذ العمليات الخمس بنجاح لكن الاتصال ينقطع قبل وصول الاستجابة للجهاز
T+5s   الجهاز (لم يستلم تأكيداً) يعيد إرسال نفس الدفعة بنفس idempotency_keys
النتيجة المتوقعة: السيرفر يعيد نفس الاستجابات المحفوظة من sync_idempotency_log_v4
       بدون تنفيذ أي INSERT إضافي — يُتحقق بـ: SELECT COUNT(*) قبل/بعد يجب أن يتطابق
```

### سيناريو C: حمل متزامن كامل (10/10)
- سكربت تحميل (Load Test) يُشغَّل من 10 أجهزة فعلية (ليس محاكاة) في نفس النافذة الزمنية (10:00-10:15 صباحاً)، كل جهاز يُدخل 20 سجلاً جديداً + يُعدِّل 10 سجلات موجودة.
- تُراقَب: `device_registry_v4.health_status` لكل الأجهزة، زمن استجابة `POST /sync/actions` (يجب أن يبقى < 3 ثواني حتى مع الحمل الكامل).

**Definition of Done:** السيناريوهات A/B/C تُنفَّذ يومياً لمدة أسبوعين متتاليين بدون أي نتيجة غير متوقعة.

---

## المرحلة 7 — التحوّل التدريجي (Staged Cutover)

### آلية الـ Flag
```php
// config/services.php (ملف موجود مسبقاً — إضافة مفتاح جديد فقط، لا تعديل مفاتيح قائمة)
'sync_v4' => [
    'legacy_sync_enabled' => env('LEGACY_SYNC_ENABLED', true), // القيمة الافتراضية الآمنة
],
```
- على جانب Android: `RemoteConfigManagerV4` يقرأ الـ flag من endpoint جديد `GET /api/mobile/v4/config/flags` كل مزامنة، ويُفعّل/يُعطّل جدولة `SponsorshipSyncWorker`/`DataSyncWorker` القديمين برمجياً (`WorkManager.cancelUniqueWork(...)` / إعادة الجدولة) — **بدون حذف الكود**، فقط تحكّم بالتشغيل وقت التنفيذ.

### خطة المراقبة 72 ساعة
| المقياس | أداة المراقبة | حد الإنذار |
|---------|------------------|--------------|
| معدل التعارضات الجديدة/ساعة | استعلام دوري على `conflict_review_queue_v4` | > 5/ساعة → تحقيق يدوي |
| فشل مزامنة | `device_registry_v4.health_status = 'error'` | أي جهاز > 30 دقيقة بدون تحسّن |
| زمن استجابة API | Laravel Telescope/Logs على `api_v4.php` | p95 > 3 ثواني |

**معيار التراجع الفوري:** أي مقياس يتجاوز حده لأكثر من ساعة متواصلة → `legacy_sync_enabled=true` فوراً.

---

## المرحلة 8 — الإغلاق والتوثيق النهائي

- تحديث `versionCode`/`versionName` في `app/build.gradle` (ملف موجود — تعديل رقمين فقط، لا يُعتبر "ملفاً قديماً يُمَس" لأنه ملف إعداد بناء وليس منطق عمل، والتعديل هنا حتمي لإصدار أي نسخة جديدة أصلاً).
- إضافة قسم 50 جديد في `ENCYCLOPEDIA.md` (ملف Markdown توثيقي، إضافة قسم لاحق لا تعديل على أي من الـ49 قسماً الحالية).
- أرشفة كل نتائج اختبارات المرحلة 6 (سيناريوهات A/B/C) كملحق دائم لإثبات جاهزية الإنتاج.
