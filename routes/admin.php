<?php

use App\Http\Controllers\Admin\AcademicDegreeController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AidStatusController;
use App\Http\Controllers\Admin\BankNameController;
use App\Http\Controllers\Admin\CategoryOfRelationController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CurrencyTypeController;
use App\Http\Controllers\Admin\DeathReasonController;
use App\Http\Controllers\Admin\DisplacementStatusController;
use App\Http\Controllers\Admin\DocumentTypeCotroller;
use App\Http\Controllers\Admin\EmploymentCotroller;
use App\Http\Controllers\Admin\GeneralCategoryCotroller;
use App\Http\Controllers\Admin\HealthStatusCotroller;
use App\Http\Controllers\Admin\HousingStatusController;
use App\Http\Controllers\Admin\MaritalStatusController;
use App\Http\Controllers\Admin\PersonsController;
use App\Http\Controllers\Admin\ProvinceController;
use App\Http\Controllers\Admin\RecordsManagementController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UnifiedFileManagementController;
use App\Http\Controllers\Admin\RecordsManagementEditController;
use App\Http\Controllers\Admin\RequestStatusController;
use App\Http\Controllers\Admin\SponsorshipStatusController;
use App\Http\Controllers\Admin\TypeOfAccommodationController;
use App\Http\Controllers\Admin\TypeOfGuaranteeController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ManageTheUserRequestController;
use App\Http\Controllers\DuplicateFileController;
Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
    // إدارة طلبات المستخدمين (عرض وتغيير حالة الطلب)
    Route::get('manage-user-requests', [ManageTheUserRequestController::class, 'index'])->name('manage.user.requests.index');
    Route::post('manage-user-requests/change-status', [ManageTheUserRequestController::class, 'changeStatus'])->name('manage.user.requests.changeStatus');
    Route::get('dashboard', [AdminController::class, 'index'])->name('dashboard');
    // profile management
    Route::get('profile', [AdminController::class, 'profile'])->name('index.profile');
    Route::get('settings', [AdminController::class, 'settings'])->name('settings');
    Route::get('profile/index', [AdminController::class, 'profileIndex'])->name('profile.page.index');
    // profile operations
    Route::post('update-profile', [AdminController::class, 'updateProfile'])->name('updateProfile');
    Route::delete('delete-profile/{id}', [AdminController::class, 'softDeleteProfile'])->name('deleteProfile');
    Route::post('/admin/update-email', [AdminController::class, 'updateEmail'])->name('updateEmail');
    Route::post('/admin/update-password', [AdminController::class, 'updatePassword'])->name('updatePassword');
    Route::post('/update-avatar', [AdminController::class, 'updateAvatar'])->name('updateAvatar');
    // civilian management
    Route::get('civilian', [PersonsController::class, 'index'])->name('index.civilian');
    Route::resource('persons', PersonsController::class);
    Route::put('/persons/{id}', [PersonsController::class, 'update'])->name('persons.update');
    Route::post('/persons/sort', [PersonsController::class, 'sort'])->name('admin.persons.sort');
    Route::post('/persons', [PersonsController::class, 'store'])->name('persons.store');
    // user role management
    Route::get('admin/user-role-management', [UserController::class, 'index101'])->name('role.management101');
    Route::get('user/user-role-management', [UserController::class, 'index102'])->name('role.management102');
    // records management
    Route::get('records-management', [RecordsManagementController::class, 'index'])->name('records.management');
    Route::get('records-management/create', [RecordsManagementController::class, 'create'])->name('records.management.create');
    Route::post('records-management/store', [RecordsManagementController::class, 'store'])->name('records.management.store');
    Route::post('records-management/upload', [RecordsManagementController::class, 'upload'])->name('documents.upload');
    Route::get('records-management/{id}/edit', [RecordsManagementEditController::class, 'edit'])->name('records.management.edit');
    // إصلاح مسار حذف فرد الأسرة ليكون POST كما يتوقعه الجافاسكريبت
    Route::post('records-management/delete-family-member/{id}', [RecordsManagementEditController::class, 'deleteFamilyMember'])
        ->name('records-management.delete-family-member');

    // إضافة مسار حذف المرفق عبر AJAX
    Route::post('records-management/delete-attachment/{id}', [RecordsManagementEditController::class, 'deleteAttachment'])
        ->name('records-management.delete-attachment');

    // مسار إنشاء سجل جديد (upload via AJAX optional أو ضمن create form)
    Route::post('records-management/upload', [RecordsManagementEditController::class, 'upload'])
        ->name('records.management.upload');

    // مسار تعديل سجل موجود (update via AJAX or full form submit)
    Route::put('records-management/{id}', [RecordsManagementEditController::class, 'update'])
        ->name('records.management.update');

    Route::delete('records-management/{id}', [RecordsManagementEditController::class, 'delete'])->name('records.management.delete');
    // إضافة هذا السطر لدعم حذف فرد الأسرة عبر AJAX
    // Route::delete('records-management/family-member/delete/{id}', [RecordsManagementEditController::class, 'deleteFamilyMember'])->name('records.management.family_member.delete');

    // category management
    Route::get('category-management/academicdegree', [AcademicDegreeController::class, 'academicdegree'])->name('category.management.academicdegree');
    Route::post('category-management/academicdegree/store', [AcademicDegreeController::class, 'createAcademicDegree'])->name('store.category.management.academicdegree');
    Route::put('/admin/category/academicdegree/{id}', [AcademicDegreeController::class, 'academicDegreeUpdate'])
        ->name('update.category.management.academicdegree');
    Route::delete('/admin/category/academicdegree/{id}', [AcademicDegreeController::class, 'academicDegreeDestroy'])
        ->name('delete.category.management.academicdegree');
    // Aid Status Management
    Route::resource('aid_status', AidStatusController::class);
    // Bank Name Management
    Route::resource('bank_name', BankNameController::class);
    // Category Of Relation Management
    Route::resource('CategoryOfRelation_name', CategoryOfRelationController::class);
    // city name Management
    Route::resource('city_name', CityController::class);
    // currency type  Management
    Route::resource('CurrencyType_name', CurrencyTypeController::class);
    // Death Reason description Management
    Route::resource('DeathReason_name', DeathReasonController::class);
    // Displacement status Management
    Route::resource('DisplacementStatus_name', DisplacementStatusController::class);
    //  DocumentType Name Management
    Route::resource('DocumentType_name', DocumentTypeCotroller::class);
    //  Employment status Management
    Route::resource('Employment_name', EmploymentCotroller::class);
    // General Category Toggle Status (AJAX)
    Route::post('general-category/toggle-status', [GeneralCategoryCotroller::class, 'toggleStatus'])->name('general-category.toggle-status');

    //   General Category Management
    Route::resource('GeneralCategory_name', GeneralCategoryCotroller::class);
    //   Health  Status Management
    Route::resource('HealthStatus_name', HealthStatusCotroller::class);
    //   Housing Status Management
    Route::resource('HousingStatus_name', HousingStatusController::class);
    //   Marital Status Management
    Route::resource('MaritalStatus_name', MaritalStatusController::class);
    //   Province Name  Management
    Route::resource('Province_name', ProvinceController::class);
    //   Request Status   Management
    Route::resource('RequestStatus_name', RequestStatusController::class);
    //   Sponsorship Status Management
    Route::resource('SponsorshipStatus_name', SponsorshipStatusController::class);
    //   Type Of Accommodation Management
    Route::resource('TypeOfAccommodation_name', TypeOfAccommodationController::class);
    //   Type Of Guarantee Management
    Route::resource('TypeOfGuarantee_name', TypeOfGuaranteeController::class);
    Route::get('records-management/{id}/show', [RecordsManagementEditController::class, 'show'])->name('records.management.show');
     // AJAX: جلب سجلات موظف مع pagination
    Route::get('ajax/admin-records/{admin}', [RecordsManagementEditController::class, 'ajaxAdminRecords'])->name('ajax.admin-records');

    // File Manager Route
    Route::get('file-manager', function () {
        return view('file-management.advanced-interface');
    })->name('file.manager');

    // File Management Routes
    Route::prefix('file')->group(function () {
        // Duplicate Detection Routes for Folders
        Route::get('duplicate-summary', [UnifiedFileManagementController::class, 'getDuplicateFilesSummary'])->name('file.duplicate.summary');
        Route::delete('delete-duplicates', [UnifiedFileManagementController::class, 'deleteDuplicateFiles'])->name('file.delete.duplicates');
        Route::get('download-duplicates', [UnifiedFileManagementController::class, 'downloadDuplicateFilesZip'])->name('file.download.duplicates');
        Route::get('download-duplicate', [UnifiedFileManagementController::class, 'downloadSingleDuplicateFile'])->name('file.download.duplicate.single');
        Route::get('duplicate-statistics', [UnifiedFileManagementController::class, 'getFolderDuplicateStatistics'])->name('file.duplicate.statistics');

        // Bulk Folder Upload with Duplicate Detection
        Route::post('process-bulk-folder-upload', [UnifiedFileManagementController::class, 'processBulkFolderUploadWithDuplicateDetection'])->name('file.process.bulk.folder.upload');

        // Legacy routes (keeping for backward compatibility)
        Route::post('create-test-duplicates', [UnifiedFileManagementController::class, 'createTestDuplicates'])->name('file.create.test.duplicates');

        // Excel Gateway Routes
        Route::get('excel-gateway', [UnifiedFileManagementController::class, 'showExcelGateway'])->name('file.excel.gateway');
        Route::post('excel-upload', [UnifiedFileManagementController::class, 'processExcelUpload'])
            ->middleware('large.upload')
            ->name('file.excel.upload');
        Route::post('process-excel', [UnifiedFileManagementController::class, 'processExcelUpload'])
            ->middleware('large.upload')
            ->name('file.process.excel');
        Route::get('php-diagnostic', [UnifiedFileManagementController::class, 'showPhpDiagnostic'])->name('file.php.diagnostic');
    });

    // Files Processing Routes
    Route::prefix('files')->group(function () {
        Route::post('process-folder-upload', [UnifiedFileManagementController::class, 'processFolderUpload'])->name('files.process.folder.upload');
    });
});


// File Management Routes للملفات المكررة - خارج admin group للاختبار
Route::prefix('admin/file')->withoutMiddleware(['web'])->group(function () {
    Route::get('duplicate-summary', [DuplicateFileController::class, 'getDuplicateFilesSummary'])->name('admin.file.duplicate.summary');
    Route::delete('delete-duplicates', [DuplicateFileController::class, 'deleteDuplicateFiles'])->name('admin.file.delete.duplicates');
    Route::get('download-duplicates', [DuplicateFileController::class, 'downloadDuplicateFiles'])->name('admin.file.download.duplicates');
    Route::get('download-duplicate/{session_id}/{file_id}', [DuplicateFileController::class, 'downloadDuplicateFile'])->name('admin.file.download.duplicate');
    Route::post('process-folder-duplicates', [DuplicateFileController::class, 'processFolderForDuplicates'])->name('admin.file.process.folder.duplicates');
});

// Test API Routes - بدون أي middleware
Route::prefix('test/api/file')->group(function () {
    Route::get('duplicate-summary', [DuplicateFileController::class, 'getDuplicateFilesSummary'])->name('test.api.file.duplicate.summary');
    Route::delete('delete-duplicates', [DuplicateFileController::class, 'deleteDuplicateFiles'])->name('test.api.file.delete.duplicates');
    Route::get('download-duplicates', [DuplicateFileController::class, 'downloadDuplicateFiles'])->name('test.api.file.download.duplicates');
    Route::post('process-folder-duplicates', [DuplicateFileController::class, 'processFolderForDuplicates'])->name('test.api.file.process.folder.duplicates');
});

// Admin Routes Group
Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
    // Duplicate Files Management with Pagination - إدارة الملفات المكررة مع التقسيم للصفحات
    Route::prefix('duplicate-files')->group(function () {
        Route::get('/', [UnifiedFileManagementController::class, 'duplicateFilesIndex'])->name('duplicate.files.index');
        Route::get('test', function () {
            return view('admin.duplicate-files.test');
        })->name('duplicate.files.test');
        Route::get('count', [UnifiedFileManagementController::class, 'getDuplicateFilesCount'])->name('duplicate.files.count');
        Route::get('paginated', [UnifiedFileManagementController::class, 'getDuplicateFilesPaginated'])->name('duplicate.files.paginated');
        Route::get('view/{id}', [UnifiedFileManagementController::class, 'viewDuplicateFile'])->name('duplicate.files.view');
        Route::get('download/{id}', [UnifiedFileManagementController::class, 'downloadDuplicateFileById'])->name('duplicate.files.download');
        Route::delete('delete/{id}', [UnifiedFileManagementController::class, 'deleteDuplicateFileById'])->name('duplicate.files.delete');
        Route::delete('bulk-delete', [UnifiedFileManagementController::class, 'bulkDeleteDuplicateFiles'])->name('duplicate.files.bulk.delete');
        Route::get('preview/{id}', [UnifiedFileManagementController::class, 'previewDuplicateFile'])->name('duplicate.files.preview');
    });
});;

// Admin Routes Group - Additional Testing Routes
Route::prefix('admin')->group(function () {
    Route::get('test-connection', [UnifiedFileManagementController::class, 'testConnection'])->name('admin.test.connection');
    Route::get('test-document-types', [UnifiedFileManagementController::class, 'testDocumentTypes'])->name('admin.test.document.types');
    Route::post('process-bulk-folder-upload-with-duplicate-detection', [UnifiedFileManagementController::class, 'processBulkFolderUploadWithDuplicateDetection'])->name('admin.process.bulk.folder.upload.duplicate.detection');

    // مسارات اختبار الإصلاحات
    Route::post('file/test-duplicate-detection', [TestController::class, 'testDuplicateDetection'])->name('admin.file.test.duplicate.detection');
    Route::get('file/get-recent-logs', [TestController::class, 'getRecentLogs'])->name('admin.file.get.recent.logs');
});
