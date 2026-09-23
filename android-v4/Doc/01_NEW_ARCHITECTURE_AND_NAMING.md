# 01 — البنية الجديدة وقواعد التسمية (v4)

## 1. مبدأ العزل الكامل عن v3

كل مكوّن جديد في v4 يُبنى في **مساحة اسم (namespace) جديدة تماماً**، بحيث لا يوجد أي احتمال تصادم اسم ملف أو Class أو جدول مع v3. القاعدة العامة: **لاحقة/بادئة `V4` أو `_v4` إلزامية على كل شيء جديد**.

## 2. بنية حزمة الأندرويد الجديدة

```
android-v4/                              ← مجلد جديد بجانب android-v3/ (لا داخله)
  app/src/main/java/com/aso/app/v4/      ← namespace جديد بالكامل تحت نفس applicationId
    sync/
      UnifiedSyncOrchestratorV4.java
      SyncOutboxManagerV4.java
      ConflictResolverV4.java
      DeviceIdentityManagerV4.java
      IdempotencyKeyGeneratorV4.java
      SyncWorkerV4.java                  (Extends Worker — يستبدل الجدولة القديمة فقط)
    admin_offline/
      AdminOfflineDataStoreV4.java
      PermissionsCacheManagerV4.java
      ReportsEngineV4.java
      FileManagementOfflineQueueV4.java
    db/
      SyncDatabaseHelperV4.java           → sync_v4.db
      AdminOfflineDatabaseHelperV4.java   → admin_offline_v4.db
    bridge/
      JavaScriptBridgeV4.java
  mobile-app-v4/                          ← مصدر واجهة الويب الجديدة (منفصل عن mobile-app/)
    src/
      admin/                              ← الشاشات الموازية للوحة التحكم offline
      screens/                            ← الشاشات الجديدة المطلوب إضافتها
      sync-client-v4.js                   ← عميل JS يتحدث فقط مع SyncOrchestratorV4
```

**لماذا مجلد `android-v4/` منفصل بجانب `android-v3/` وليس تعديلاً داخله؟**
لأن هذا يضمن حرفياً استحالة "المساس" بأي ملف قديم، ويتيح بناء الإصدارين من نفس الـ repo عبر Gradle product flavors أو module منفصل يُدمج لاحقاً في APK نهائي واحد (انظر القسم 4).

## 3. بنية الباكند الجديدة (Laravel)

```
routes/
  api_v4.php                    ← ملف جديد بالكامل (لا يُلمَس routes/api.php)
app/Http/Controllers/Api/V4/
  SyncControllerV4.php
  DeviceRegistryControllerV4.php
  AdminOfflineExportControllerV4.php
  ReconciliationControllerV4.php
app/Services/V4/
  IdempotentUpsertServiceV4.php
  ConflictDetectionServiceV4.php
  DeviceHandshakeServiceV4.php
app/Models/  (بدون ملفات جديدة للموديلات الأساسية — تُستخدم نفس Eloquent Models القديمة
              عبر الخدمات الجديدة، فقط تُضاف Traits جديدة عند الحاجة، انظر الملف 04)
```

يُسجَّل `routes/api_v4.php` في `RouteServiceProvider` كمجموعة مسارات مستقلة بادئتها `/api/mobile/v4/*`، محمية بـ `auth:sanctum` بشكل صريح على كل مسار (لا اعتماد ضمني كما حدث في التعارضات C8/C9/C10 القديمة).

## 4. استراتيجية الدمج في APK واحد

- Phase انتقالية: يُبنى APK يحتوي على **كلا المحرّكين** (v3 القديم + v4 الجديد) يعملان بالتوازي (Dual-Run) — انظر تفاصيل الفلاج (`sync_engine_version`) في الملف 02.
- بعد التحقق الميداني على الأجهزة العشرة، يُعطَّل تفعيل محرك v3 (بدون حذف أكواده) عبر remote config flag، ويصبح v4 هو الافتراضي.
- الكود القديم (Workers، Plugins، DB Helpers الخاصة بـ v3) **يبقى في المشروع** كمسار طوارئ/Rollback حتى يقرر صاحب المشروع صراحة إزالته لاحقاً في مشروع تنظيف منفصل.

## 5. جدول تسمية مطابق (Old → New) للرجوع السريع

| المكوّن القديم (v3) | لا يُمسّ | المكافئ الجديد (v4) |
|----------------------|----------|----------------------|
| `SponsorshipSyncWorker.java` | ✅ يبقى كما هو | `UnifiedSyncOrchestratorV4.java` (يغطي نفس الوظيفة + أكثر) |
| `DataSyncWorker.java` (org.alhayah) | ✅ يبقى كما هو | مدمج ضمن `UnifiedSyncOrchestratorV4.java` |
| `upload_queue.db`, `sponsorships_data.db`, `related_data.db`, `data_sync.db` | ✅ تبقى كما هي (قراءة فقط بعد الانتقال) | `sync_v4.db` (مصدر الحقيقة الوحيد الجديد) |
| `routes/api.php` (972 سطر) | ✅ لا يُعدَّل حرفاً واحداً | `routes/api_v4.php` |
| `MobileRegistrationController.php` | ✅ يبقى كما هو (نقطة دخول v3 لا تزال تعمل) | `SyncControllerV4.php` (يغطي نفس التسجيل + idempotency) |
| Blade admin views | ✅ تبقى كما هي (تُستخدم أونلاين فقط) | شاشات `mobile-app-v4/src/admin/*` (offline) |
| `TokenInterceptor.js` | ✅ يبقى كما هو | نسخة جديدة `token-interceptor-v4.js` تدعم تخزين offline موسّع للصلاحيات |
