<?php

/**
 * Offline Test Development Routes
 *
 * هذه الـ Routes مخصصة للاختبار المحلي فقط
 * تتصل بقاعدة البيانات الحقيقية بدون middleware معقد
 *
 * ⚠️ تحذير: لا تستخدم في الإنتاج!
 *
 * Base URL: /offline-test-development
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfflineTestController;

Route::prefix('offline-test-development')->group(function () {

    // ===== الصحة والاتصال =====
    Route::get('/health', [OfflineTestController::class, 'health']);

    // ===== المصادقة =====
    Route::post('/login', [OfflineTestController::class, 'login']);

    // ===== البيانات المرجعية =====
    Route::get('/sponsors', [OfflineTestController::class, 'getSponsors']);
    Route::get('/statuses', [OfflineTestController::class, 'getStatuses']);
    Route::get('/health-statuses', [OfflineTestController::class, 'getHealthStatuses']);
    Route::get('/cities', [OfflineTestController::class, 'getCities']);
    Route::get('/banks', [OfflineTestController::class, 'getBanks']);

    // ===== المزامنة =====
    Route::get('/initial-sync', [OfflineTestController::class, 'initialSync']);
    Route::post('/sync/upload', [OfflineTestController::class, 'uploadChanges']);

    // ===== الكفالات =====
    Route::get('/sponsorships', [OfflineTestController::class, 'getSponsorships']);
    Route::get('/sponsorships/{id}', [OfflineTestController::class, 'getSponsorship']);
    Route::put('/sponsorships/{id}', [OfflineTestController::class, 'updateSponsorship']);
    Route::post('/sponsorships/{id}', [OfflineTestController::class, 'updateSponsorship']); // للتوافق مع بعض المتصفحات

    // ===== الإحصائيات =====
    Route::get('/stats', [OfflineTestController::class, 'getStats']);
});

// ===== CORS Headers for local testing =====
// هذا يسمح للتطبيق المحلي بالوصول للـ API
Route::options('offline-test-development/{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
})->where('any', '.*');
