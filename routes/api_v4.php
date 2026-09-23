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
});
