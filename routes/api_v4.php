<?php

use App\Http\Controllers\Api\V4\AdminCrudControllerV4;
use App\Http\Controllers\Api\V4\AdminOfflineExportControllerV4;
use App\Http\Controllers\Api\V4\DeviceRegistryControllerV4;
use App\Http\Controllers\Api\V4\ReconciliationControllerV4;
use App\Http\Controllers\Api\V4\SyncControllerV4;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V4 Routes
|--------------------------------------------------------------------------
| جميع مسارات android-v4 الجديدة — لا تُمس routes/api.php القديمة.
| كل مسار محمي فردياً بـ auth:sanctum + throttle:api-v4.
| الأسماء: api.v4.* (بادئة تمنع أي تصادم مع route قديم).
*/

Route::prefix('mobile/v4')->group(function () {
    // ---------------------------------------------
    // Sync
    // ---------------------------------------------
    Route::post('sync/registration', [SyncControllerV4::class, 'registration'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.sync.registration');

    Route::post('sync/actions', [SyncControllerV4::class, 'actions'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.sync.actions');

    Route::get('sync/pull', [SyncControllerV4::class, 'pull'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.sync.pull');

    // ---------------------------------------------
    // Device
    // ---------------------------------------------
    Route::post('device/register', [DeviceRegistryControllerV4::class, 'register'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.device.register');

    Route::get('device/health', [DeviceRegistryControllerV4::class, 'health'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.device.health');

    // ---------------------------------------------
    // Conflicts / Reconciliation
    // ---------------------------------------------
    Route::get('conflicts', [ReconciliationControllerV4::class, 'index'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.conflicts.index');

    Route::post('conflicts/{id}/resolve', [ReconciliationControllerV4::class, 'resolve'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.conflicts.resolve');

    Route::get('audit', [ReconciliationControllerV4::class, 'audit'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.audit.index');

    // ---------------------------------------------
    // Admin Offline Export
    // ---------------------------------------------
    Route::get('admin/dashboard-snapshot', [AdminOfflineExportControllerV4::class, 'dashboardSnapshot'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.admin.dashboard-snapshot');

    Route::get('admin/reports-source', [AdminOfflineExportControllerV4::class, 'reportsSource'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.admin.reports-source');

    Route::get('admin/permissions-manifest', [AdminOfflineExportControllerV4::class, 'permissionsManifest'])
        ->middleware('auth:sanctum', 'throttle:api-v4')
        ->name('api.v4.admin.permissions-manifest');

    // ---------------------------------------------
    // Admin CRUD (Phase 5 — screens parity) — APPEND ONLY
    // ---------------------------------------------
    $m = ['auth:sanctum', 'throttle:api-v4'];

    // Records
    Route::get('records', [AdminCrudControllerV4::class, 'recordsIndex'])->middleware($m)->name('api.v4.records.index');
    Route::post('records', [AdminCrudControllerV4::class, 'recordsStore'])->middleware($m)->name('api.v4.records.store');
    Route::get('records/{id}', [AdminCrudControllerV4::class, 'recordsShow'])->middleware($m)->name('api.v4.records.show');
    Route::put('records/{id}', [AdminCrudControllerV4::class, 'recordsUpdate'])->middleware($m)->name('api.v4.records.update');
    Route::delete('records/{id}', [AdminCrudControllerV4::class, 'recordsDestroy'])->middleware($m)->name('api.v4.records.destroy');

    // Categories (whitelisted tables)
    Route::get('categories/{table}', [AdminCrudControllerV4::class, 'categoriesIndex'])->middleware($m)->name('api.v4.categories.index');
    Route::post('categories/{table}', [AdminCrudControllerV4::class, 'categoriesStore'])->middleware($m)->name('api.v4.categories.store');
    Route::put('categories/{table}/{id}', [AdminCrudControllerV4::class, 'categoriesUpdate'])->middleware($m)->name('api.v4.categories.update');
    Route::delete('categories/{table}/{id}', [AdminCrudControllerV4::class, 'categoriesDestroy'])->middleware($m)->name('api.v4.categories.destroy');

    // Search
    Route::get('search-records', [AdminCrudControllerV4::class, 'searchRecords'])->middleware($m)->name('api.v4.search.records');
    Route::get('search-suggestions', [AdminCrudControllerV4::class, 'searchSuggestions'])->middleware($m)->name('api.v4.search.suggestions');
    Route::get('global-search', [AdminCrudControllerV4::class, 'globalSearch'])->middleware($m)->name('api.v4.search.global');

    // Sponsorships / Sponsors
    Route::get('sponsorships', [AdminCrudControllerV4::class, 'sponsorshipsIndex'])->middleware($m)->name('api.v4.sponsorships.index');
    Route::post('sponsorships', [AdminCrudControllerV4::class, 'sponsorshipsStore'])->middleware($m)->name('api.v4.sponsorships.store');
    Route::put('sponsorships/{id}', [AdminCrudControllerV4::class, 'sponsorshipsUpdate'])->middleware($m)->name('api.v4.sponsorships.update');
    Route::delete('sponsorships/{id}', [AdminCrudControllerV4::class, 'sponsorshipsDestroy'])->middleware($m)->name('api.v4.sponsorships.destroy');
    Route::put('sponsorships/{id}/status', [AdminCrudControllerV4::class, 'sponsorshipsUpdateStatus'])->middleware($m)->name('api.v4.sponsorships.status');
    Route::get('sponsorships-list/sponsored', [AdminCrudControllerV4::class, 'sponsoredIndex'])->middleware($m)->name('api.v4.sponsorships.sponsored');
    Route::get('sponsorships-list/unsponsored', [AdminCrudControllerV4::class, 'unsponsoredIndex'])->middleware($m)->name('api.v4.sponsorships.unsponsored');

    Route::get('sponsors', [AdminCrudControllerV4::class, 'sponsorsIndex'])->middleware($m)->name('api.v4.sponsors.index');
    Route::post('sponsors', [AdminCrudControllerV4::class, 'sponsorsStore'])->middleware($m)->name('api.v4.sponsors.store');
    Route::put('sponsors/{id}', [AdminCrudControllerV4::class, 'sponsorsUpdate'])->middleware($m)->name('api.v4.sponsors.update');
    Route::delete('sponsors/{id}', [AdminCrudControllerV4::class, 'sponsorsDestroy'])->middleware($m)->name('api.v4.sponsors.destroy');

    // Users / Roles
    Route::get('users', [AdminCrudControllerV4::class, 'usersIndex'])->middleware($m)->name('api.v4.users.index');
    Route::post('users', [AdminCrudControllerV4::class, 'usersStore'])->middleware($m)->name('api.v4.users.store');
    Route::put('users/{id}', [AdminCrudControllerV4::class, 'usersUpdate'])->middleware($m)->name('api.v4.users.update');
    Route::delete('users/{id}', [AdminCrudControllerV4::class, 'usersDestroy'])->middleware($m)->name('api.v4.users.destroy');

    Route::get('roles', [AdminCrudControllerV4::class, 'rolesIndex'])->middleware($m)->name('api.v4.roles.index');
    Route::post('roles', [AdminCrudControllerV4::class, 'rolesStore'])->middleware($m)->name('api.v4.roles.store');
    Route::put('roles/{id}', [AdminCrudControllerV4::class, 'rolesUpdate'])->middleware($m)->name('api.v4.roles.update');
    Route::delete('roles/{id}', [AdminCrudControllerV4::class, 'rolesDestroy'])->middleware($m)->name('api.v4.roles.destroy');

    // Files / Folders / Duplicates / Attachment Audit
    Route::get('files', [AdminCrudControllerV4::class, 'filesIndex'])->middleware($m)->name('api.v4.files.index');
    Route::get('files/folders', [AdminCrudControllerV4::class, 'foldersIndex'])->middleware($m)->name('api.v4.files.folders');
    Route::get('files/duplicates', [AdminCrudControllerV4::class, 'duplicatesIndex'])->middleware($m)->name('api.v4.files.duplicates');
    Route::get('files/audit', [AdminCrudControllerV4::class, 'filesAudit'])->middleware($m)->name('api.v4.files.audit');
    Route::delete('files/{id}', [AdminCrudControllerV4::class, 'filesDestroy'])->middleware($m)->name('api.v4.files.destroy');

    // Civil Registry
    Route::get('civil-registry', [AdminCrudControllerV4::class, 'civilIndex'])->middleware($m)->name('api.v4.civil.index');
    Route::get('civil-registry/search', [AdminCrudControllerV4::class, 'civilSearch'])->middleware($m)->name('api.v4.civil.search');
    Route::get('civil-registry/import-template', [AdminCrudControllerV4::class, 'civilImportTemplate'])->middleware($m)->name('api.v4.civil.import-template');
    Route::post('civil-registry/validate-import', [AdminCrudControllerV4::class, 'civilValidateImport'])->middleware($m)->name('api.v4.civil.validate-import');
    Route::post('civil-registry/import', [AdminCrudControllerV4::class, 'civilImport'])->middleware($m)->name('api.v4.civil.import');

    // User Requests
    Route::get('user-requests', [AdminCrudControllerV4::class, 'userRequestsIndex'])->middleware($m)->name('api.v4.user-requests.index');
    Route::put('user-requests/change-status', [AdminCrudControllerV4::class, 'userRequestsChangeStatus'])->middleware($m)->name('api.v4.user-requests.change-status');

    // Profile
    Route::get('profile', [AdminCrudControllerV4::class, 'profileShow'])->middleware($m)->name('api.v4.profile.show');
    Route::put('profile', [AdminCrudControllerV4::class, 'profileUpdate'])->middleware($m)->name('api.v4.profile.update');
    Route::put('profile/email', [AdminCrudControllerV4::class, 'profileUpdateEmail'])->middleware($m)->name('api.v4.profile.update-email');
    Route::put('profile/password', [AdminCrudControllerV4::class, 'profileUpdatePassword'])->middleware($m)->name('api.v4.profile.update-password');

    // Notifications
    Route::get('notifications', [AdminCrudControllerV4::class, 'notificationsIndex'])->middleware($m)->name('api.v4.notifications.index');

    // ---------------------------------------------
    // Remote Config Flags (Phase 7 — Staged Cutover)
    // ---------------------------------------------
    Route::get('config/flags', [\App\Http\Controllers\Api\V4\ConfigFlagsControllerV4::class, 'flags'])
        ->middleware($m)
        ->name('api.v4.config.flags');

    // =========================================================================
    // نسخ مسارات التطبيق القديمة (v3) — المزامنة والرفع واللوجين
    // نقل كامل لسطور routes/api.php تحت بادئة mobile/v4، بنفس الكنترولرات،
    // بينما تبقى api.php كما هي تماماً (لا حذف ولا تعديل — Dual-Run).
    // ملاحظات:
    //  - GET sponsors غير منسوخ: مسار v4 sponsorsIndex (إدارة) مسجّل أعلاه
    //    وأولوية التسجيل له، والتطبيق لا يستدعي هذا المسار إطلاقاً.
    //  - POST sync/actions غير منسوخ: المسار موجود أعلاه ويقبل الآن صيغتين
    //    (دفع v4 بـ actions[] أو صيغة v3 المفردة) — انظر SyncControllerV4@actions.
    //  - مسارات الرفع تستخدم auth:sanctum فقط (بدون throttle:api-v4) وتُضاف
    //    لقائمة 1200 طلب/دقيقة في RouteServiceProvider.
    // =========================================================================

    $a  = ['auth:sanctum', 'throttle:api-v4']; // مسارات عادية (نسخة عن حماية v3)
    $au = ['auth:sanctum'];                    // مسارات رفع أجزاء (حد واسع)

    // ----- عام (بدون مصادقة) -----
    Route::post('login', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'login'])
        ->name('api.v4.legacy.login');
    Route::post('refresh-token', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'refreshToken'])
        ->name('api.v4.legacy.refresh-token');
    Route::get('health', function () {
        return response()->json([
            'status' => 'healthy',
            'app' => 'alhayah-sponsorships',
            'timestamp' => now()->toISOString(),
            'version' => '2.0.0',
        ]);
    })->name('api.v4.legacy.health');

    // ----- مصادقة -----
    Route::post('logout', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'logout'])
        ->middleware($a)->name('api.v4.legacy.logout');
    Route::post('verify-password', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'verifyPassword'])
        ->middleware($a)->name('api.v4.legacy.verify-password');

    // ----- جداول المعايرة -----
    Route::get('sponsorship-statuses', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSponsorshipStatuses'])
        ->middleware($a)->name('api.v4.legacy.sponsorship-statuses');

    // ----- مزامنة الكفالات -----
    Route::get('sync/initial', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getInitialSync'])
        ->middleware($a)->name('api.v4.legacy.sync.initial');
    Route::get('sync/full', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getFullSync'])
        ->middleware($a)->name('api.v4.legacy.sync.full');
    Route::get('sync/sponsorships', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSponsorships'])
        ->middleware($a)->name('api.v4.legacy.sync.sponsorships');
    Route::get('sync/sponsorship/{id}', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSponsorshipDetails'])
        ->middleware($a)->name('api.v4.legacy.sync.sponsorship');
    Route::post('sync/upload', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'uploadSyncData'])
        ->middleware($a)->name('api.v4.legacy.sync.upload');
    Route::post('sync/photos/metadata', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'syncPhotoMetadata'])
        ->middleware($a)->name('api.v4.legacy.sync.photos-metadata');
    Route::get('sync/stats', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSyncStats'])
        ->middleware($a)->name('api.v4.legacy.sync.stats');
    Route::get('sync/data-table', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSyncDataTable'])
        ->middleware($a)->name('api.v4.legacy.sync.data-table');
    Route::get('sync/re-people', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSyncRePeople'])
        ->middleware($a)->name('api.v4.legacy.sync.re-people');
    Route::get('sync/dead-people', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSyncDeadPeople'])
        ->middleware($a)->name('api.v4.legacy.sync.dead-people');
    Route::get('sync/bank-accounts', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSyncBankAccounts'])
        ->middleware($a)->name('api.v4.legacy.sync.bank-accounts');
    Route::get('sync/death-reasons', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSyncDeathReasons'])
        ->middleware($a)->name('api.v4.legacy.sync.death-reasons');
    Route::get('sync/additional-deceased', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'getSyncAdditionalDeceased'])
        ->middleware($a)->name('api.v4.legacy.sync.additional-deceased');
    Route::post('sync/bulk-upload', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'bulkUpsert'])
        ->middleware($a)->name('api.v4.legacy.sync.bulk-upload');

    // ----- طابور الإجراءات -----
    Route::get('server-actions', [\App\Http\Controllers\Api\ServerActionController::class, 'pullActions'])
        ->middleware($a)->name('api.v4.legacy.server-actions');
    Route::post('server-actions/ack', [\App\Http\Controllers\Api\ServerActionController::class, 'ackActions'])
        ->middleware($a)->name('api.v4.legacy.server-actions.ack');
    Route::post('sponsorships/sync-updates', [\App\Http\Controllers\Api\ActionSyncController::class, 'syncAction'])
        ->middleware($a)->name('api.v4.legacy.sponsorships.sync-updates');
    Route::get('sync/pending-actions', [\App\Http\Controllers\Api\ActionSyncController::class, 'getPendingActions'])
        ->middleware($a)->name('api.v4.legacy.sync.pending-actions');
    Route::post('sync/ack-action', [\App\Http\Controllers\Api\ActionSyncController::class, 'ackAction'])
        ->middleware($a)->name('api.v4.legacy.sync.ack-action');

    // ----- رفع الملفات -----
    Route::post('upload-file', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'uploadFile'])
        ->middleware($a)->name('api.v4.legacy.upload-file');
    Route::post('upload-chunk', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'handleChunk'])
        ->middleware($au, 'large.upload')->name('api.v4.legacy.upload-chunk');
    Route::get('upload-status/{upload_id}', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'uploadStatus'])
        ->middleware($au)->name('api.v4.legacy.upload-status');
    Route::post('retry-rclone-upload', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'retryRcloneUpload'])
        ->middleware($au, 'large.upload')->name('api.v4.legacy.retry-rclone-upload');

    // ----- التسجيل الميداني -----
    Route::get('registration/lookups', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'lookups'])
        ->middleware($a)->name('api.v4.legacy.registration.lookups');
    Route::get('registration/photo/{personId}', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'photo'])
        ->middleware($a)->name('api.v4.legacy.registration.photo');
    Route::get('registration/photo/{personId}/exists', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'photoExists'])
        ->middleware($a)->name('api.v4.legacy.registration.photo-exists');
    Route::post('registration/new-file-id', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'newFileId'])
        ->middleware($a)->name('api.v4.legacy.registration.new-file-id');
    Route::post('registration/upload-chunk', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'uploadChunk'])
        ->middleware($au, 'large.upload')->name('api.v4.legacy.registration.upload-chunk');
    Route::get('registration/file/{fileId}', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'getFile'])
        ->middleware($a)->name('api.v4.legacy.registration.file');
    Route::post('registration/store', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'store'])
        ->middleware($a)->name('api.v4.legacy.registration.store');
    Route::post('registration/update', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'update'])
        ->middleware($a)->name('api.v4.legacy.registration.update');
    Route::post('registration/find-by-id', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'findById'])
        ->middleware($a)->name('api.v4.legacy.registration.find-by-id');
    Route::post('registration/deceased-lookup', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'deceasedLookup'])
        ->middleware($a)->name('api.v4.legacy.registration.deceased-lookup');

    // ----- صور الكفالات -----
    Route::get('photos/manifest', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'photosManifest'])
        ->middleware($a)->name('api.v4.legacy.photos.manifest');
    Route::get('photos/{id}/exists', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'photoExists'])
        ->where('id', '[0-9]+')->middleware($a)->name('api.v4.legacy.photos.exists');
    Route::get('photos/{id}', [\App\Http\Controllers\Api\SponsorshipSyncController::class, 'photoFile'])
        ->where('id', '[0-9]+')->middleware($a)->name('api.v4.legacy.photos.file');

    // ----- السجل المدني -----
    Route::get('civil-registry/manifest', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'civilRegistryManifest'])
        ->middleware($a)->name('api.v4.legacy.civil.manifest');
    Route::get('civil-registry/person/{id}', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'civilRegistryPerson'])
        ->where('id', '[0-9]+')->middleware($a)->name('api.v4.legacy.civil.person');
    Route::get('civil-registry/file/{file}', [\App\Http\Controllers\Api\MobileRegistrationController::class, 'serveCivilRegistryFile'])
        ->where('file', '[a-zA-Z0-9.\-_]+')->middleware($a)->name('api.v4.legacy.civil.file');
    // بحث السجل المدني (كما هو في web.php — بدون مصادقة للتوافق)
    Route::get('civil-registry/search-by-id', [\App\Http\Controllers\CivilRegistrySearchController::class, 'searchById'])
        ->name('api.v4.legacy.civil.search-by-id');
    Route::get('civil-registry/search-by-name', [\App\Http\Controllers\CivilRegistrySearchController::class, 'searchByName'])
        ->name('api.v4.legacy.civil.search-by-name');

    // ----- أدوات المزامنة (كانت api/sync/*) -----
    Route::post('sync/check-person-all-tables', [\App\Http\Controllers\Api\SyncController::class, 'checkPersonAllTables'])
        ->middleware($a)->name('api.v4.legacy.tools.check-person-all-tables');
    Route::post('sync/check-person-exists', [\App\Http\Controllers\Api\SyncController::class, 'checkPersonExists'])
        ->middleware($a)->name('api.v4.legacy.tools.check-person-exists');
    Route::post('sync/generate-file-id', [\App\Http\Controllers\Api\SyncController::class, 'generateFileID'])
        ->middleware($a)->name('api.v4.legacy.tools.generate-file-id');
    Route::post('sync/activate-file-id', [\App\Http\Controllers\Api\SyncController::class, 'activateFileID'])
        ->middleware($a)->name('api.v4.legacy.tools.activate-file-id');
    Route::post('sync/new-person-entry', [\App\Http\Controllers\Api\SyncController::class, 'newPersonEntry'])
        ->middleware($a)->name('api.v4.legacy.tools.new-person-entry');
    Route::get('sync/eligible-sponsorships', [\App\Http\Controllers\Api\SyncController::class, 'getEligibleSponsorships'])
        ->middleware($a)->name('api.v4.legacy.tools.eligible-sponsorships');
    Route::post('sync/sponsorships/update-relation-id', [\App\Http\Controllers\Api\SyncController::class, 'updateSponsorshipRelationId'])
        ->middleware($a)->name('api.v4.legacy.tools.update-relation-id');
    Route::get('sync/person-by-relation/{relationId}', [\App\Http\Controllers\Api\SyncController::class, 'getPersonByRelation'])
        ->middleware($a)->name('api.v4.legacy.tools.person-by-relation');

    // ----- تتبع رفع جوجل درايف (كانت api/uploads/*) -----
    Route::post('uploads/chunk', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'handleChunk'])
        ->middleware($au, 'large.upload')->name('api.v4.legacy.uploads.chunk');
    Route::post('uploads/check-duplicate', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'checkDuplicate'])
        ->middleware($a)->name('api.v4.legacy.uploads.check-duplicate');
    Route::post('uploads/drive-status', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'driveStatus'])
        ->middleware($a)->name('api.v4.legacy.uploads.drive-status');
    Route::get('uploads/offline-inbox', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'offlineInbox'])
        ->middleware($a)->name('api.v4.legacy.uploads.offline-inbox');
    Route::post('uploads/offline-inbox/ack', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'ackOfflineInbox'])
        ->middleware($a)->name('api.v4.legacy.uploads.offline-inbox.ack');
    Route::post('uploads/notify-completed', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'notifyCompleted'])
        ->middleware($a)->name('api.v4.legacy.uploads.notify-completed');
    Route::post('uploads/mark-failed', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'markFailed'])
        ->middleware($a)->name('api.v4.legacy.uploads.mark-failed');
    Route::get('uploads/stats', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'getStats'])
        ->middleware($a)->name('api.v4.legacy.uploads.stats');
    Route::get('uploads/pending', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'getPendingUploads'])
        ->middleware($a)->name('api.v4.legacy.uploads.pending');
    Route::get('uploads/entity/{type}/{id}', [\App\Http\Controllers\Api\GoogleDriveUploadController::class, 'getEntityUploads'])
        ->middleware($a)->name('api.v4.legacy.uploads.entity');
});
