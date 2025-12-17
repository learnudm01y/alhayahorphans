<?php

use App\Http\Controllers\Admin\GeneralCategoryCotroller;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\CreateRecordsController;
use App\Http\Controllers\Users\GeneralRegistrationController;
use App\Http\Controllers\Users\ShowGeneralRegisrationController;
use App\Http\Controllers\Users\UserLoginContoller;
use App\Http\Controllers\UnifiedFileManagementController;
use App\Http\Controllers\Users\UserProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\DuplicateFileController;
use App\Http\Controllers\SpeedTestController;
use App\Http\Controllers\ScoutSearchController;
use App\Http\Controllers\CivilRegistrySearchController;

// مسارات اختبار التحميل بدون مصادقة
Route::get('/test-download-all', [UnifiedFileManagementController::class, 'downloadAllDuplicateFiles']);
Route::post('/test-download-selected', [UnifiedFileManagementController::class, 'downloadSelectedDuplicateFiles']);

// مسار اختبار الإحصائيات الحقيقية بدون مصادقة (مؤقت)
Route::get('/test-real-stats-public', [UnifiedFileManagementController::class, 'getRealDuplicateFilesStatistics']);

// Scout Search API Routes
Route::prefix('api/scout')->group(function () {
    Route::get('instant-search', [ScoutSearchController::class, 'instantSearch'])->name('scout.instant.search');
    Route::post('advanced-search', [ScoutSearchController::class, 'advancedSearch'])->name('scout.advanced.search');
});

// Public API routes for duplicate files (without authentication middleware)

// Civil Registry Scout Search API Routes - مسارات البحث في السجل المدني
Route::prefix('api/civil-registry')->group(function () {
    Route::get('/', [CivilRegistrySearchController::class, 'index'])->name('civil.registry.index');
    Route::get('quick-search', [CivilRegistrySearchController::class, 'quickSearch'])->name('civil.registry.quick.search');
    Route::get('advanced-search', [CivilRegistrySearchController::class, 'advancedSearch'])->name('civil.registry.advanced.search');
    Route::get('search-by-id', [CivilRegistrySearchController::class, 'searchById'])->name('civil.registry.search.id');
    Route::get('search-by-name', [CivilRegistrySearchController::class, 'searchByName'])->name('civil.registry.search.name');
    Route::get('comprehensive-search', [CivilRegistrySearchController::class, 'comprehensiveSearch'])->name('civil.registry.comprehensive.search');
    Route::get('stats', [CivilRegistrySearchController::class, 'getStats'])->name('civil.registry.stats');
    Route::get('database-stats', [CivilRegistrySearchController::class, 'getStats'])->name('civil.registry.database.stats');
    Route::post('index-data', [CivilRegistrySearchController::class, 'indexData'])->name('civil.registry.index.data');
    Route::get('test-connection', [CivilRegistrySearchController::class, 'testConnection'])->name('civil.registry.test');
    Route::post('clear-cache', [CivilRegistrySearchController::class, 'clearCache'])->name('civil.registry.clear.cache');
});

// Civil Registry CRUD Routes - مسارات إدارة السجل المدني
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('admin/civil-registry')->name('admin.civil-registry.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CivilRegistryController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\CivilRegistryController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\CivilRegistryController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Http\Controllers\Admin\CivilRegistryController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [App\Http\Controllers\Admin\CivilRegistryController::class, 'edit'])->name('edit');
        Route::put('/{id}', [App\Http\Controllers\Admin\CivilRegistryController::class, 'update'])->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Admin\CivilRegistryController::class, 'destroy'])->name('destroy');
        Route::get('/search', [App\Http\Controllers\Admin\CivilRegistryController::class, 'search'])->name('search');
        Route::get('/stats', [App\Http\Controllers\Admin\CivilRegistryController::class, 'stats'])->name('stats');
    });
});

// Public API routes for duplicate files (without authentication middleware)
Route::prefix('public-api/duplicate-files')->group(function () {
    Route::get('paginated', [UnifiedFileManagementController::class, 'getDuplicateFilesPaginated'])->name('public.duplicate.files.paginated');
    Route::get('real-statistics', [UnifiedFileManagementController::class, 'getRealDuplicateFilesStatistics'])->name('public.duplicate.files.real.statistics');
    Route::get('preview/{id}', [UnifiedFileManagementController::class, 'previewDuplicateFile'])->name('public.duplicate.files.preview');
    Route::get('serve/{id}', [UnifiedFileManagementController::class, 'serveImageFile'])->name('public.duplicate.files.serve');
    Route::get('download/{id}', [UnifiedFileManagementController::class, 'downloadDuplicateFileById'])->name('public.duplicate.files.download');
    Route::get('view/{id}', [UnifiedFileManagementController::class, 'viewDuplicateFile'])->name('public.duplicate.files.view');
    Route::delete('delete/{id}', [UnifiedFileManagementController::class, 'deleteDuplicateFileById'])->name('public.duplicate.files.delete');
    Route::delete('bulk-delete', [UnifiedFileManagementController::class, 'bulkDeleteDuplicateFiles'])->name('public.duplicate.files.bulk.delete');
    Route::delete('delete-all', [UnifiedFileManagementController::class, 'deleteAllDuplicateFiles'])->name('public.duplicate.files.delete.all');
});

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

// المسار الرئيسي - صفحة التسجيل العام
Route::get('/',[GeneralRegistrationController::class,'index'])->name('generalRegistration.index');
Route::post('users/generalRegistration/store',[GeneralRegistrationController::class,'store'])->name('store.generalRegistration');

// مسار صفحة تسجيل الدخول
Route::get('/login', function () {
    return view('auth/login');
})->name('login.page');

// Route for testing the advanced file manager
Route::get('/file-manager', function () {
    return view('file-management.advanced-interface');
})->name('file-manager');

// حماية جميع مسارات المستخدمين بميدل وير auth و verified
Route::middleware(['auth', 'verified'])->group(function () {
    Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
        Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
        Route::get('/general-registration', [ShowGeneralRegisrationController::class, 'index'])->name('generalRegistration.index');
        Route::post('/general-registration/update', [ShowGeneralRegisrationController::class, 'updateSponsorshipData'])->name('update-sponsorship-data');
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
    Route::post('/search-all-tables', [GeneralRegistrationController::class, 'searchAllTables'])->name('search.all.tables');
    Route::post('/fill-from-civil-registry', [GeneralRegistrationController::class, 'fillFromCivilRegistry'])->name('fill.civil.registry');


// Reserved Codes Management Routes (Admin Only)
Route::middleware(['auth', 'verified'])->prefix('admin/reserved-codes')->group(function () {
    // مزامنة جدول reserved_codes مع جدول data
    Route::post('/sync', function () {
        if (function_exists('syncReservedCodesWithData')) {
            $stats = syncReservedCodesWithData();
            return response()->json([
                'success' => true,
                'message' => 'تمت المزامنة بنجاح',
                'stats' => $stats
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'الدالة غير موجودة'
        ], 500);
    })->name('admin.reserved-codes.sync');

    // تنظيف الأكواد القديمة يدوياً
    Route::post('/cleanup', function () {
        if (function_exists('cleanupOldReservedCodes')) {
            $deleted = cleanupOldReservedCodes(1);
            return response()->json([
                'success' => true,
                'message' => "تم حذف {$deleted} كود غير مستخدم",
                'deleted_count' => $deleted
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'الدالة غير موجودة'
        ], 500);
    })->name('admin.reserved-codes.cleanup');

    // إحصائيات جدول reserved_codes
    Route::get('/stats', function () {
        $stats = [
            'total' => DB::table('reserved_codes')->count(),
            'used' => DB::table('reserved_codes')->where('used', true)->count(),
            'unused' => DB::table('reserved_codes')->where('used', false)->count(),
            'old_unused' => DB::table('reserved_codes')
                ->where('used', false)
                ->where('reserved_at', '<', now()->subMinutes(1))
                ->count(),
        ];
        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    })->name('admin.reserved-codes.stats');

    // إحصائيات الفجوات في جدول data
    Route::get('/gaps', function () {
        try {
            // جلب جميع الأرقام المستخدمة
            $usedCodes = DB::table('data')
                ->select(DB::raw("CAST(file_id_number as UNSIGNED) as code_num"))
                ->whereRaw("LENGTH(file_id_number) = 6 AND file_id_number REGEXP '^[0-9]+$'")
                ->orderBy('code_num', 'asc')
                ->pluck('code_num')
                ->toArray();

            if (empty($usedCodes)) {
                return response()->json([
                    'success' => true,
                    'gaps' => [],
                    'total_gaps' => 0,
                    'message' => 'لا توجد أرقام مستخدمة بعد'
                ]);
            }

            // البحث عن الفجوات
            $gaps = [];
            $expectedNext = 1;

            foreach ($usedCodes as $usedCode) {
                if ($usedCode > $expectedNext) {
                    // وجدنا فجوة
                    $gapStart = $expectedNext;
                    $gapEnd = $usedCode - 1;
                    $gapSize = $gapEnd - $gapStart + 1;

                    $gaps[] = [
                        'start' => str_pad($gapStart, 6, '0', STR_PAD_LEFT),
                        'end' => str_pad($gapEnd, 6, '0', STR_PAD_LEFT),
                        'size' => $gapSize
                    ];
                }
                $expectedNext = $usedCode + 1;
            }

            return response()->json([
                'success' => true,
                'gaps' => $gaps,
                'total_gaps' => array_sum(array_column($gaps, 'size')),
                'gap_ranges' => count($gaps),
                'min_code' => str_pad(min($usedCodes), 6, '0', STR_PAD_LEFT),
                'max_code' => str_pad(max($usedCodes), 6, '0', STR_PAD_LEFT),
                'total_used' => count($usedCodes)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    })->name('admin.reserved-codes.gaps');
});


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
// Excel Gateway Routes - محمية بمصادقة المستخدم
Route::middleware(['auth', 'verified'])->group(function () {
    Route::group(['prefix' => 'admin/file'], function() {
        Route::get('/excel-gateway', [UnifiedFileManagementController::class, 'showExcelGateway'])
            ->name('admin.file.excel.gateway');

        Route::get('/php-diagnostic', [UnifiedFileManagementController::class, 'showPhpDiagnostic'])
            ->name('admin.file.php.diagnostic');

        // العرض الآمن للملفات من storage - محمي بمصادقة المستخدم
        Route::get('/show/{filename}', [UnifiedFileManagementController::class, 'showSecureFile'])
            ->name('admin.file.show');

        // اختبار نظام إدارة المجلدات
        Route::get('/diagnostic/folders', [App\Http\Controllers\Admin\DiagnosticController::class, 'testFolderSystem'])
            ->name('admin.diagnostic.folders');
    });
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

// Google Drive Test Routes - مسارات اختبار Google Drive
Route::prefix('google-drive-test')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'index'])->name('google.drive.test.index');
    Route::post('/test-connection', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'testConnection'])->name('google.drive.test.connection');
    Route::post('/upload', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'uploadTest'])->name('google.drive.test.upload');
    Route::get('/list', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'listFiles'])->name('google.drive.test.list');
    Route::delete('/delete', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'deleteFile'])->name('google.drive.test.delete');
    Route::post('/create-folder', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'createFolder'])->name('google.drive.test.folder');
    Route::get('/search', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'searchFiles'])->name('google.drive.test.search');
    Route::get('/file-info', [\App\Http\Controllers\Admin\GoogleDriveTestController::class, 'getFileInfo'])->name('google.drive.test.info');
});

require __DIR__ . '/auth.php';

// Test Documents API
Route::get('/test-documents-api', function() { return view('test-documents-api'); })->name('test.documents.api');

Route::get('/test-documents-display', function() { return view('test-documents-display'); });

Route::get('/diagnose-documents', function() { return view('diagnose-documents'); });

Route::get('/simple-modal-test', function() { return view('simple-modal-test'); });

Route::get('/debug-documents-api', function() { return view('debug-documents-api'); });

Route::get('/test-save-documents', function() { return view('test-save-documents'); });

Route::get('/direct-test-save', function() { return view('direct-test-save'); });

Route::get('/comprehensive-test', function() { return view('comprehensive-test'); });

Route::get('/quick-save-test', function() { return view('quick-save-test'); });

Route::get('/final-documents-test', function() { return view('final-documents-test'); });

Route::get('/test-save-button', function() { return view('test-save-button'); });

Route::get('/test-new-documents-system', function() { return view('test-new-documents-system'); });

Route::get('/direct-documents-page', function() { return view('direct-documents-page'); });
