<?php

use App\Http\Controllers\Admin\GeneralCategoryCotroller;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\CreateRecordsController;
use App\Http\Controllers\Users\GeneralRegistrationController;
use App\Http\Controllers\Users\ShowGeneralRegisrationController;
use App\Http\Controllers\Users\UserLoginContoller;
use App\Http\Controllers\Users\UserProfileController;
use App\Http\Controllers\UnifiedFileManagementController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DuplicateFileController;
use App\Http\Controllers\SpeedTestController;

// مسارات اختبار التحميل بدون مصادقة
Route::get('/test-download-all', [UnifiedFileManagementController::class, 'downloadAllDuplicateFiles']);
Route::post('/test-download-selected', [UnifiedFileManagementController::class, 'downloadSelectedDuplicateFiles']);

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('users/generalRegistration',[GeneralRegistrationController::class,'index'])->name('generalRegistration.index');
Route::post('users/generalRegistration/store',[GeneralRegistrationController::class,'store'])->name('store.generalRegistration');

Route::get('/', function () {
    return view('auth/login');
});

// Route for testing the advanced file manager
Route::get('/file-manager', function () {
    return view('file-management.advanced-interface');
})->name('file-manager');

// حماية جميع مسارات المستخدمين بميدل وير auth و verified
Route::middleware(['auth', 'verified'])->group(function () {
    Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
        Route::get('/dashboard', [ShowGeneralRegisrationController::class, 'index'])->name('generalRegistration.index');
        // profifle management
        Route::get('profile', [UserProfileController::class, 'profile'])->name('index.profile');
        Route::get('settings', [UserProfileController::class, 'settings'])->name('settings');
        Route::get('profile/index', [UserProfileController::class, 'profileIndex'])->name('profile.page.index');
        // profile operations
        Route::post('update-profile', [UserProfileController::class, 'updateProfile'])->name('updateProfile');
        Route::delete('delete-profile/{id}', [UserProfileController::class, 'softDeleteProfile'])->name('deleteProfile');
        Route::post('/user/update-email', [UserProfileController::class, 'updateEmail'])->name('updateEmail');
        Route::post('/user/update-password', [UserProfileController::class, 'updatePassword'])->name('updatePassword');
        Route::post('/update-avatar', [UserProfileController::class, 'updateAvatar'])->name('updateAvatar');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);
});

    Route::post('/login/user', [UserLoginContoller::class, 'login'])->name('user.login');
    Route::post('/logout-user', [\App\Http\Controllers\Users\UserLoginContoller::class, 'logout'])->name('user.logout.custom');
    Route::get('/users/generalRegistration/login', function() {
        return view('user.generalRegistration.login');
    })->name('user.login.page.index');
    Route::post('admin/general-category/toggle-status', [GeneralCategoryCotroller::class, 'toggleStatus'])->name('admin.general-category.toggle-status');

    Route::post('/check-id-number', [GeneralRegistrationController::class, 'check'])->name('check.id');




// File Management Routes
Route::get('/file-management/advanced-interface', function () {
    return view('file-management.advanced-interface');
})->name('file-management.advanced-interface');

// API Routes for File Management
Route::prefix('api/files')->group(function () {
    Route::post('/generate-record-number', [App\Http\Controllers\UnifiedFileManagementController::class, 'generateRecordNumber'])
        ->name('api.files.generate-record-number');

    Route::post('/process-folder-upload', [App\Http\Controllers\UnifiedFileManagementController::class, 'processFolderUpload'])
        ->name('api.files.process-folder-upload');

    Route::get('/analytics', [App\Http\Controllers\UnifiedFileManagementController::class, 'getFileAnalytics'])
        ->name('api.files.analytics');
});

// Fallback routes for analytics without auth
Route::get('/api/files/analytics', [App\Http\Controllers\UnifiedFileManagementController::class, 'getFileAnalytics'])
    ->withoutMiddleware(['auth', 'verified'])
    ->name('api.files.analytics.fallback');

// Speed Test Routes
Route::prefix('api/speedtest')->group(function () {
    Route::post('/run', [SpeedTestController::class, 'run'])->name('api.speedtest.run');
    Route::get('/check-availability', [SpeedTestController::class, 'checkAvailability'])->name('api.speedtest.check');
    Route::post('/clear-cache', [SpeedTestController::class, 'clearCache'])->name('api.speedtest.clear-cache');
});

// Fallback speedtest routes without auth
Route::post('/api/speedtest/run', [SpeedTestController::class, 'run'])
    ->withoutMiddleware(['auth', 'verified'])
    ->name('api.speedtest.run.fallback');
Route::get('/api/speedtest/check-availability', [SpeedTestController::class, 'checkAvailability'])
    ->withoutMiddleware(['auth', 'verified'])
    ->name('api.speedtest.check.fallback');

// // Routes للمعالجة الملفات المكررة
// Route::group(['prefix' => 'file-management'], function() {
//     Route::get('/download-duplicates', [UnifiedFileManagementController::class, 'downloadDuplicateFiles'])
//         ->name('file.download-duplicates');
//     Route::get('/duplicate-summary', [UnifiedFileManagementController::class, 'getDuplicateFilesSummary'])
//         ->name('file.duplicate-summary');
//     Route::post('/cleanup-expired', [UnifiedFileManagementController::class, 'cleanupExpiredDuplicates'])
//         ->name('file.cleanup-expired');
// });
// Routes للمعالجة الملفات المكررة - تم نقلها إلى admin.php

// API Routes for Duplicate File Management - TEST WITHOUT MIDDLEWARE
Route::prefix('api/files')->withoutMiddleware(['auth', 'verified'])->group(function () {
    Route::post('/generate-record-number', [App\Http\Controllers\UnifiedFileManagementController::class, 'generateRecordNumber'])
        ->name('api.files.generate-record-number');

    Route::post('/process-folder-upload', [App\Http\Controllers\UnifiedFileManagementController::class, 'processFolderUpload'])
        ->name('api.files.process-folder-upload');

    Route::post('/process-folder-upload-with-duplicates', [App\Http\Controllers\UnifiedFileManagementController::class, 'processFolderUploadWithDuplicateDetection'])
        ->name('api.files.process-folder-upload-with-duplicates');

    // Route for duplicate file detection service
    Route::post('/process-folder-duplicates', [App\Http\Controllers\DuplicateFileController::class, 'processFolderForDuplicates'])
        ->name('api.files.process-folder-duplicates');

    Route::get('/analytics', [App\Http\Controllers\UnifiedFileManagementController::class, 'getAnalytics'])
        ->name('api.files.analytics');

    // Duplicate file management endpoints
    Route::post('/check-single-duplicate', [App\Http\Controllers\DuplicateFileController::class, 'checkSingleFile'])
        ->name('api.files.check-single-duplicate');

    Route::post('/process-folder-duplicates', [App\Http\Controllers\DuplicateFileController::class, 'processFolderForDuplicates'])
        ->name('api.files.process-folder-duplicates');

    Route::get('/duplicate-session-stats/{session_id}', [App\Http\Controllers\DuplicateFileController::class, 'getSessionStatistics'])
        ->name('api.files.duplicate-session-stats');

    Route::post('/clean-expired-duplicates', [App\Http\Controllers\DuplicateFileController::class, 'cleanExpiredFiles'])
        ->name('api.files.clean-expired-duplicates');
});

// Excel Gateway Routes
Route::group(['prefix' => 'admin/file'], function() {
    Route::get('/excel-gateway', [UnifiedFileManagementController::class, 'showExcelGateway'])
        ->name('admin.file.excel.gateway');

    Route::get('/php-diagnostic', [UnifiedFileManagementController::class, 'showPhpDiagnostic'])
        ->name('admin.file.php.diagnostic');
});

// Speedtest Routes - Production Ready
Route::prefix('api/speedtest')->withoutMiddleware(['auth', 'verified'])->group(function () {
    Route::get('/', [SpeedTestController::class, 'run'])
        ->name('api.speedtest.run');

    Route::get('/check', [SpeedTestController::class, 'checkAvailability'])
        ->name('api.speedtest.check');

    Route::post('/clear-cache', [SpeedTestController::class, 'clearCache'])
        ->name('api.speedtest.clear-cache');
});

require __DIR__ . '/auth.php';
