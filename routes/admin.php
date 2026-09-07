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
use App\Http\Controllers\Admin\FamilyRelationController;
use App\Http\Controllers\Admin\FolderManagementController;
use App\Http\Controllers\Admin\FileSystemSyncController;
use App\Http\Controllers\Admin\GeneralCategoryCotroller;
use App\Http\Controllers\Admin\HealthStatusCotroller;
use App\Http\Controllers\Admin\HousingStatusController;
use App\Http\Controllers\Admin\MaritalStatusController;
use App\Http\Controllers\Admin\OrphanNeedController;
use App\Http\Controllers\Admin\CreativityAspectController;
use App\Http\Controllers\Admin\PersonsController;
use App\Http\Controllers\Admin\PersonSearchController;
use App\Http\Controllers\Admin\ScoutSearchController;
use App\Http\Controllers\Admin\ProvinceController;
use App\Http\Controllers\Admin\RecordsManagementController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UnifiedFileManagementController;
use App\Http\Controllers\ZipTestController;
use App\Http\Controllers\Admin\RecordsManagementEditController;
use App\Http\Controllers\Admin\RequestStatusController;
use App\Http\Controllers\Admin\SponsorshipStatusController;
use App\Http\Controllers\Admin\SponsorController;
use App\Http\Controllers\Admin\SponsorshipController;
use App\Http\Controllers\Admin\UnifiedSearchController;
use App\Http\Controllers\Admin\TypeOfAccommodationController;
use App\Http\Controllers\Admin\TypeOfGuaranteeController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Admin\SpeedTestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ManageTheUserRequestController;
use App\Http\Controllers\AttachmentAuditController;
use App\Http\Controllers\DuplicateFileController;
use App\Http\Controllers\CivilRegistrySearchController;
use App\Http\Controllers\Admin\CivilRegistryController;
Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
    // إدارة طلبات المستخدمين (عرض وتغيير حالة الطلب)
    Route::get('manage-user-requests', [ManageTheUserRequestController::class, 'index'])
        ->middleware('permission:عرض قسم إدارة التسجيلات|إدارة طلبات المستخدمين')
        ->name('manage.user.requests.index');
    Route::post('manage-user-requests/change-status', [ManageTheUserRequestController::class, 'changeStatus'])
        ->middleware('permission:عرض قسم إدارة التسجيلات|إدارة طلبات المستخدمين')
        ->name('manage.user.requests.changeStatus');
    Route::get('dashboard', [AdminController::class, 'index'])
        ->middleware('permission:الوصول للصفحة الرئيسية|عرض قسم إدارة التسجيلات|عرض قسم إدارة التصنيفات|عرض قسم إدارة الجمعيات|عرض قسم الكفالات|عرض قسم إدارة الصلاحيات|عرض قسم إدارة السجل المدني|عرض قسم إدارة الملفات|عرض قسم أدوات النظام')
        ->name('dashboard');
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
    Route::get('civilian', [PersonsController::class, 'index'])
        ->middleware('permission:عرض قسم إدارة السجل المدني|عرض السجل المدني القديم')
        ->name('index.civilian');
    Route::resource('persons', PersonsController::class);
    Route::post('/persons/sort', [PersonsController::class, 'sort'])->name('admin.persons.sort');
    Route::post('/persons', [PersonsController::class, 'store'])->name('persons.store');

    // Person Search Routes - محرك البحث المتقدم
    Route::prefix('persons')->name('admin.persons.')->group(function () {
        Route::get('/search', [PersonSearchController::class, 'index'])->name('search.index');
        Route::post('/search', [PersonSearchController::class, 'search'])->name('search');
        Route::post('/quick-search', [PersonSearchController::class, 'quickSearch'])->name('search.quick');
        Route::post('/search/statistics', [PersonSearchController::class, 'getStatistics'])->name('search.statistics');
        Route::post('/search/export', [PersonSearchController::class, 'export'])->name('search.export');
        Route::get('/search/advanced', [PersonSearchController::class, 'advancedSearch'])->name('search.advanced');
        Route::get('/{id}/details', [PersonsController::class, 'show'])->name('details');
    });

    // Scout Search Routes - محرك البحث بتقنية Scout السريع
    Route::prefix('scout')->name('admin.scout.')->group(function () {
        Route::get('/instant-search', [ScoutSearchController::class, 'instantSearch'])->name('instant.search');
        Route::post('/advanced-search', [ScoutSearchController::class, 'advancedSearch'])->name('advanced.search');
        Route::get('/suggestions', [ScoutSearchController::class, 'suggestions'])->name('suggestions');
        Route::get('/stats', [ScoutSearchController::class, 'stats'])->name('stats');
        Route::post('/index-data', [ScoutSearchController::class, 'indexData'])->name('index.data');
        Route::post('/clear-cache', [ScoutSearchController::class, 'clearCache'])->name('clear.cache');
    });

    // Family Relations Routes - مسارات العلاقات العائلية
    Route::prefix('family-relations')->name('family.relations.')->group(function () {
        Route::post('/search', [\App\Http\Controllers\Admin\FamilyRelationController::class, 'searchFamilyRelations'])->name('search');
        Route::post('/search-by-name', [\App\Http\Controllers\Admin\FamilyRelationController::class, 'searchFamilyRelationsByName'])->name('search.by.name');
        Route::post('/family-tree', [\App\Http\Controllers\Admin\FamilyRelationController::class, 'getFamilyTree'])->name('tree');
        Route::get('/statistics', [\App\Http\Controllers\Admin\FamilyRelationController::class, 'getRelationsStatistics'])->name('statistics');
        Route::post('/clear-cache', [\App\Http\Controllers\Admin\FamilyRelationController::class, 'clearRelationsCache'])->name('clear.cache');
    });

    // user role management
    Route::get('admin/user-role-management', [UserController::class, 'index101'])
        ->middleware('permission:عرض قسم إدارة الصلاحيات|المستخدمين')
        ->name('role.management101');
    Route::get('user/user-role-management', [UserController::class, 'index102'])
        ->middleware('permission:عرض قسم إدارة الصلاحيات|المستخدمين')
        ->name('role.management102');
    // records management
    Route::get('records-management', [RecordsManagementController::class, 'index'])
        ->middleware('permission:عرض قسم إدارة التسجيلات|عرض البيانات المدخلة')
        ->name('records.management');

    // 🏆 التصدير الاحترافي - Streaming (الموصى به) ⚡
    Route::get('records-management/export-streaming', [\App\Http\Controllers\Admin\RecordsManagementController::class, 'exportAllStreaming'])->name('records.management.exportStreaming');

    // Export all records (Excel - قديم، قد يسبب crash مع البيانات الكبيرة)
    Route::get('records-management/export-all', [\App\Http\Controllers\Admin\RecordsManagementController::class, 'exportAll'])->name('records.management.exportAll');

    // Export all records as CSV files (أخف على الذاكرة)
    Route::get('records-management/export-all-csv', [\App\Http\Controllers\Admin\RecordsManagementController::class, 'exportAllCSV'])->name('records.management.exportAllCSV');

    Route::get('records-management/create', [RecordsManagementController::class, 'create'])
        ->middleware('permission:عرض قسم إدارة التسجيلات|إدخال بيانات التسجيلات')
        ->name('records.management.create');
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

    // مسار جلب الحسابات البنكية (GET لأنه استعلام فقط)
    Route::get('records-management/get-bank-accounts', [RecordsManagementEditController::class, 'getBankAccounts'])
        ->name('records.management.getBankAccounts');

    // مسار جلب الحسابات البنكية باستخدام رقم الملف مباشرة
    Route::get('records-management/get-bank-accounts-by-file-id', [RecordsManagementEditController::class, 'getBankAccountsByFileId'])
        ->name('records.management.getBankAccountsByFileId');

    // مسار اعتماد حساب بنكي
    Route::post('records-management/approve-bank-account', [RecordsManagementEditController::class, 'approveBankAccount'])
        ->name('records.management.approveBankAccount');

    // مسار تعديل سجل موجود (update via AJAX or full form submit)
    Route::put('records-management/{id}', [RecordsManagementEditController::class, 'update'])
        ->name('records.management.update');

    Route::delete('records-management/{id}', [RecordsManagementEditController::class, 'delete'])->name('records.management.delete');
    // إضافة هذا السطر لدعم حذف فرد الأسرة عبر AJAX
    // Route::delete('records-management/family-member/delete/{id}', [RecordsManagementEditController::class, 'deleteFamilyMember'])->name('records.management.family_member.delete');

    // category management
    Route::get('category-management/academicdegree', [AcademicDegreeController::class, 'academicdegree'])
        ->middleware('permission:عرض قسم إدارة التصنيفات|إدارة الدرجة العلمية')
        ->name('category.management.academicdegree');
    Route::post('category-management/academicdegree/store', [AcademicDegreeController::class, 'createAcademicDegree'])->name('store.category.management.academicdegree');
    Route::put('/admin/category/academicdegree/{id}', [AcademicDegreeController::class, 'academicDegreeUpdate'])
        ->name('update.category.management.academicdegree');
    Route::delete('/admin/category/academicdegree/{id}', [AcademicDegreeController::class, 'academicDegreeDestroy'])
        ->name('delete.category.management.academicdegree');
    // Aid Status Management
    Route::resource('aid_status', AidStatusController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة حالة المساعدة');
    // Sponsors Management (إدارة الجمعيات)
    // ملاحظة: يجب وضع جميع الـ Routes المخصصة قبل Route::resource
    Route::get('sponsors/fields-management', [SponsorController::class, 'fieldsManagement'])
        ->middleware('permission:عرض قسم إدارة الجمعيات|تحديث بيانات الجمعيات')
        ->name('sponsors.fields-management');
    Route::get('sponsors/fields-management/data', [SponsorController::class, 'getSponsorsForFieldsManagement'])->name('sponsors.fields-management.data');
    Route::post('sponsors/export-forms', [SponsorController::class, 'exportForms'])->name('sponsors.export-forms');
    Route::post('sponsors/bulk-export-family-reports', [SponsorController::class, 'bulkExportFamilyReports'])->name('sponsors.bulk-export-family-reports');
    Route::post('sponsors/sync-sponsorship-status', [SponsorController::class, 'syncSponsorshipStatus'])->name('sponsors.sync-sponsorship-status');
    Route::get('sponsors/{sponsor}/fields', [SponsorController::class, 'getSponsorFields'])->name('sponsors.get-fields');
    Route::post('sponsors/{sponsor}/fields', [SponsorController::class, 'saveSponsorFields'])->name('sponsors.save-fields');
    Route::get('sponsors/{sponsor}/documents', [SponsorController::class, 'getDocumentSettings'])->name('sponsors.get-documents');
    Route::post('sponsors/{sponsor}/documents', [SponsorController::class, 'saveDocumentSettings'])->name('sponsors.save-documents');
    Route::post('sponsors/{sponsor}/toggle-google-drive', [SponsorController::class, 'toggleGoogleDrive'])->name('sponsors.toggle-google-drive');
    Route::get('sponsors/generate-file-id', [SponsorController::class, 'generateFileId'])->name('sponsors.generate-file-id');
    Route::post('sponsors/{sponsor}/employees', [SponsorController::class, 'storeEmployee'])->name('sponsors.employees.store');
    Route::get('sponsors/{sponsor}/employees', [SponsorController::class, 'getEmployees'])->name('sponsors.employees.index');
    Route::delete('sponsors/employees/{employee}', [SponsorController::class, 'destroyEmployee'])->name('sponsors.employees.destroy');

    // تصميم تقارير الجمعيات
    Route::post('sponsors/save-report-design', [\App\Http\Controllers\Admin\SponsorReportDesignController::class, 'saveReportDesign'])->name('sponsors.save-report-design');
    Route::get('sponsors/{sponsor}/report-design', [\App\Http\Controllers\Admin\SponsorReportDesignController::class, 'getReportDesign'])->name('sponsors.get-report-design');
    Route::delete('sponsors/{sponsor}/report-design', [\App\Http\Controllers\Admin\SponsorReportDesignController::class, 'deleteReportDesign'])->name('sponsors.delete-report-design');

    Route::get('sponsors/test-form', [SponsorController::class, 'testForm'])->name('sponsors.test');
    Route::get('sponsors/test-final', [SponsorController::class, 'testFinal'])->name('sponsors.test.final');
    Route::resource('sponsors', SponsorController::class)->middleware('permission:عرض قسم إدارة الجمعيات|إدارة الجمعيات');

    // Sponsorships Management (إدارة الكفالات)
    Route::get('sponsorships/sponsored', [SponsorshipController::class, 'sponsored'])
        ->middleware('permission:عرض قسم الكفالات|عرض المكفولين')
        ->name('sponsorships.sponsored');
    Route::get('sponsorships/unsponsored', [SponsorshipController::class, 'unsponsored'])
        ->middleware('permission:عرض قسم الكفالات|عرض غير المكفولين')
        ->name('sponsorships.unsponsored');
    Route::get('sponsorships/export', [SponsorshipController::class, 'export'])->name('sponsorships.export');
    Route::post('sponsorships/import', [SponsorshipController::class, 'import'])->name('sponsorships.import');
    Route::post('sponsorships/create-missing-persons', [SponsorshipController::class, 'createMissingPersons'])->name('sponsorships.createMissingPersons');
    Route::post('sponsorships/get-person-details', [SponsorshipController::class, 'getPersonDetails'])->name('sponsorships.getPersonDetails');
    Route::post('sponsorships/{id}/update-status', [SponsorshipController::class, 'updateStatus'])->name('sponsorships.updateStatus');
    Route::post('sponsorships/{id}/regenerate-file-number', [SponsorshipController::class, 'regenerateFileNumber'])->name('sponsorships.regenerateFileNumber');
    Route::post('sponsorships/unified-search', [UnifiedSearchController::class, 'search'])->name('sponsorships.unifiedSearch');
    Route::resource('sponsorships', SponsorshipController::class);

    // Bank Name Management
    Route::resource('bank_name', BankNameController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة أسماء البنوك');
    // Category Of Relation Management
    Route::resource('CategoryOfRelation_name', CategoryOfRelationController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة صلة القرابة');
    // city name Management
    Route::resource('city_name', CityController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة أسماء المدن');
    // currency type  Management
    Route::resource('CurrencyType_name', CurrencyTypeController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة العملات');
    // Death Reason description Management
    Route::resource('DeathReason_name', DeathReasonController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة أسباب الوفاة');
    // Displacement status Management
    Route::resource('DisplacementStatus_name', DisplacementStatusController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة حالة النزوح');
    //  DocumentType Name Management
    Route::resource('DocumentType_name', DocumentTypeCotroller::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة أنواع الوثائق');
    //  Employment status Management
    Route::resource('Employment_name', EmploymentCotroller::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة الحالة الوظيفية');
    // General Category Toggle Status (AJAX)
    Route::post('general-category/toggle-status', [GeneralCategoryCotroller::class, 'toggleStatus'])->name('general-category.toggle-status');

    //   General Category Management
    Route::resource('GeneralCategory_name', GeneralCategoryCotroller::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة الأقسام الرئيسية');
    //   Health  Status Management
    Route::resource('HealthStatus_name', HealthStatusCotroller::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة الحالة الصحية');
    //   Orphan Needs Management
    Route::resource('orphan_needs', OrphanNeedController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة احتياجات المكفول');
    //   Creativity Aspects Management
    Route::resource('creativity_aspects', CreativityAspectController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة جوانب الإبداع');
    //   Housing Status Management
    Route::resource('HousingStatus_name', HousingStatusController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة حالة المنزل');
    //   Marital Status Management
    Route::resource('MaritalStatus_name', MaritalStatusController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة الحالة الاجتماعية');
    //   Province Name  Management
    Route::resource('Province_name', ProvinceController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة المحافظات');
    //   Request Status   Management
    Route::resource('RequestStatus_name', RequestStatusController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة حالة الطلب');
    //   Sponsorship Status Management
    Route::resource('SponsorshipStatus_name', SponsorshipStatusController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة حالة الكفالة');
    //   Type Of Accommodation Management
    Route::resource('TypeOfAccommodation_name', TypeOfAccommodationController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة نوع السكن');
    //   Type Of Guarantee Management
    Route::resource('TypeOfGuarantee_name', TypeOfGuaranteeController::class)->middleware('permission:عرض قسم إدارة التصنيفات|إدارة نوع الكفالة');
    Route::get('records-management/{id}/show', [RecordsManagementEditController::class, 'show'])->name('records.management.show');
    Route::post('records-management/get-additional-info', [RecordsManagementEditController::class, 'getAdditionalInfo'])->name('records.management.getAdditionalInfo');
    Route::post('records-management/update-portal-field', [RecordsManagementEditController::class, 'updatePortalFieldValue'])->name('records.management.updatePortalField');
    Route::get('records-management/{id}/export-family-report', [RecordsManagementEditController::class, 'exportFamilyReport'])->name('records.management.export-family-report');
     // AJAX: جلب سجلات موظف مع pagination
    Route::get('ajax/admin-records/{admin}', [RecordsManagementEditController::class, 'ajaxAdminRecords'])->name('ajax.admin-records');
     // AJAX: جلب سجلات موظف مع pagination
    Route::get('manage-folders', [FolderManagementController::class, 'index'])
        ->middleware('permission:عرض قسم إدارة الملفات|إدارة المجلدات')
        ->name('manage.folders.index');
    Route::get('folders/contents', [FolderManagementController::class, 'getFolderContents'])->name('folders.contents');
    Route::get('folders/search', [FolderManagementController::class, 'search'])->name('folders.search');
    Route::get('folders/download-zip', [FolderManagementController::class, 'downloadFolderAsZip'])->name('folders.download.zip');
    Route::get('folders/view-pdf', [FolderManagementController::class, 'viewPdf'])->name('folders.view.pdf');
    Route::get('folders/download-pdf', [FolderManagementController::class, 'downloadPdf'])->name('folders.download.pdf');

    // File System Sync Routes
    Route::get('sync-files', [FileSystemSyncController::class, 'syncPhysicalFiles'])->name('sync.files');

    // File Manager Route
    Route::get('file-manager', function () {
        return view('file-management.advanced-interface');
    })->middleware('permission:عرض قسم إدارة الملفات|الوصول لبوابة الملفات')->name('file.manager');

    // File Management Routes
    Route::prefix('file')->group(function () {
        // Duplicate Detection Routes for Folders
        Route::get('duplicate-summary', [UnifiedFileManagementController::class, 'getDuplicateFilesSummary'])->name('file.duplicate.summary');
        Route::delete('delete-duplicates', [UnifiedFileManagementController::class, 'deleteDuplicateFiles'])->name('file.delete.duplicates');
        Route::get('download-duplicates', [UnifiedFileManagementController::class, 'downloadDuplicateFilesZip'])->name('file.download.duplicates');
        Route::get('download-duplicate', [UnifiedFileManagementController::class, 'downloadSingleDuplicateFile'])->name('file.download.duplicate.single');
        Route::get('duplicate-statistics', [UnifiedFileManagementController::class, 'getFolderDuplicateStatistics'])->name('file.duplicate.statistics');

        // Single File Duplicate Detection Routes
        Route::post('check-single-duplicate', [UnifiedFileManagementController::class, 'checkSingleDuplicate'])->name('file.check.single.duplicate');
        Route::post('handle-duplicate', [UnifiedFileManagementController::class, 'handleDuplicate'])->name('file.handle.duplicate');

        // Bulk Folder Upload with Duplicate Detection
        Route::post('process-bulk-folder-upload', [UnifiedFileManagementController::class, 'processBulkFolderUploadWithDuplicateDetection'])->name('file.process.bulk.folder.upload');

        // Regular File Upload (non-folder)
        Route::post('upload-files', [UnifiedFileManagementController::class, 'smartUpload'])->name('file.upload.files');

        // Batch Upload for Large Folders (to avoid PHP limits)
        Route::post('process-bulk-folder-upload-batch', [UnifiedFileManagementController::class, 'processBulkFolderUploadBatch'])->name('file.process.bulk.folder.upload.batch');

        // Document Types Data Route
        Route::get('document-types-data', [UnifiedFileManagementController::class, 'getDocumentTypesData'])->name('file.document.types.data');

        // Legacy routes (keeping for backward compatibility)
        Route::post('create-test-duplicates', [UnifiedFileManagementController::class, 'createTestDuplicates'])->name('file.create.test.duplicates');

        // Excel Gateway Routes
        Route::get('excel-gateway', [UnifiedFileManagementController::class, 'showExcelGateway'])->name('file.excel.gateway');
        Route::get('excel-gateway/sidebar', [AdminController::class, 'showExcelGatewaySidebar'])
            ->middleware('permission:عرض قسم إدارة الملفات|رفع ملفات اكسل')
            ->name('file.excel.gateway.sidebar');
        Route::post('excel-upload', [UnifiedFileManagementController::class, 'processExcelUpload'])
            ->middleware('large.upload')
            ->name('file.excel.upload');
        Route::post('process-excel', [UnifiedFileManagementController::class, 'processExcelUpload'])
            ->middleware('large.upload')
            ->name('file.process.excel');

        // Excel Validation Routes - فحص الملفات قبل الإدخال
        Route::post('validate-excel', [UnifiedFileManagementController::class, 'validateExcelBeforeImport'])
            ->middleware('large.upload')
            ->name('file.validate.excel');
        Route::post('import-validated-excel', [UnifiedFileManagementController::class, 'importValidatedExcel'])
            ->middleware('large.upload')
            ->name('file.import.validated.excel');

        Route::get('php-diagnostic', [UnifiedFileManagementController::class, 'showPhpDiagnostic'])->name('file.php.diagnostic');
    });

    // Duplicate records AJAX endpoints (protected)
    Route::prefix('duplicates')->middleware(['auth'])->group(function () {
        Route::post('find', [\App\Http\Controllers\Admin\DuplicateRecordsController::class, 'findDbDuplicates'])->name('duplicates.find');
        Route::post('check-existing', [\App\Http\Controllers\Admin\DuplicateRecordsController::class, 'checkExisting'])->name('duplicates.checkExisting');
        Route::post('process-insert', [\App\Http\Controllers\Admin\DuplicateRecordsController::class, 'processInsert'])->name('duplicates.processInsert');
        Route::get('export/{cacheKey}', [\App\Http\Controllers\Admin\DuplicateRecordsController::class, 'exportDuplicates'])->name('duplicates.export');
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
    Route::get('duplicate-info/{fileId}', [DuplicateFileController::class, 'getDuplicateFileInfo'])->name('admin.file.duplicate.info');
    Route::get('view/{filename}', [UnifiedFileManagementController::class, 'viewFileSecure'])->name('admin.file.view');
});

// Replace duplicate file API
Route::post('/api/replace-duplicate-file', [DuplicateFileController::class, 'replaceDuplicateFile'])->name('api.replace.duplicate.file');

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
        Route::get('/', [UnifiedFileManagementController::class, 'duplicateFilesIndex'])
            ->middleware('permission:عرض قسم إدارة الملفات|إدارة الملفات المكررة')
            ->name('duplicate.files.index');
        Route::get('test', function () {
            return view('admin.duplicate-files.test');
        })->name('duplicate.files.test');
        Route::get('test-real-stats', function () {
            return view('admin.duplicate-files.test-real-stats');
        })->name('duplicate.files.test.real.stats');
        Route::get('count', [UnifiedFileManagementController::class, 'getDuplicateFilesCount'])->name('duplicate.files.count');
        Route::get('paginated', [UnifiedFileManagementController::class, 'getDuplicateFilesPaginated'])->name('duplicate.files.paginated');
        Route::get('view/{id}', [UnifiedFileManagementController::class, 'viewDuplicateFile'])->name('duplicate.files.view');
        Route::get('download/{id}', [UnifiedFileManagementController::class, 'downloadDuplicateFileById'])->name('duplicate.files.download');
        Route::delete('delete/{id}', [UnifiedFileManagementController::class, 'deleteDuplicateFileById'])->name('duplicate.files.delete');
        Route::delete('bulk-delete', [UnifiedFileManagementController::class, 'bulkDeleteDuplicateFiles'])->name('duplicate.files.bulk.delete');
        Route::get('preview/{id}', [UnifiedFileManagementController::class, 'previewDuplicateFile'])->name('duplicate.files.preview');
        Route::get('serve/{id}', [UnifiedFileManagementController::class, 'serveImageFile'])->name('duplicate.files.serve');
        Route::get('download-all', [UnifiedFileManagementController::class, 'downloadAllDuplicateFiles'])->name('duplicate.files.download.all');
        Route::post('download-selected', [UnifiedFileManagementController::class, 'downloadSelectedDuplicateFiles'])->name('duplicate.files.download.selected');
        Route::get('real-statistics', [UnifiedFileManagementController::class, 'getRealDuplicateFilesStatistics'])->name('duplicate.files.real.statistics');
    });
});;

Route::prefix('admin')->group(function () {
    Route::get('attachment-audit', [AttachmentAuditController::class, 'index'])->name('attachment-audit.index');
    Route::get('attachment-audit/duplicates', [AttachmentAuditController::class, 'findDuplicates'])->name('attachment-audit.duplicates');
    Route::delete('attachment-audit/duplicates/delete', [AttachmentAuditController::class, 'deleteDuplicates'])->name('attachment-audit.duplicates.delete');
    Route::delete('attachment-audit/duplicates/delete-single', [AttachmentAuditController::class, 'deleteSingleDuplicate'])->name('attachment-audit.duplicates.delete-single');
    Route::get('attachment-audit/broken-links', [AttachmentAuditController::class, 'checkBrokenLinks'])->name('attachment-audit.broken-links');
    Route::delete('attachment-audit/broken-links/delete', [AttachmentAuditController::class, 'deleteBrokenLink'])->name('attachment-audit.broken-links.delete');
    Route::delete('attachment-audit/broken-links/delete-all', [AttachmentAuditController::class, 'deleteAllBrokenLinks'])->name('attachment-audit.broken-links.delete-all');
    Route::get('attachment-audit/export', [AttachmentAuditController::class, 'export'])->name('attachment-audit.export');
    Route::get('attachment-audit/without-attachments', [AttachmentAuditController::class, 'getWithoutAttachments'])->name('attachment-audit.without-attachments');
    Route::post('attachment-audit/export-without-attachments', [AttachmentAuditController::class, 'exportWithoutAttachments'])->name('attachment-audit.export-without-attachments');
    Route::get('attachment-audit/orphan-files', [AttachmentAuditController::class, 'findOrphanFiles'])->name('attachment-audit.orphan-files');
    Route::post('attachment-audit/add-orphan-files', [AttachmentAuditController::class, 'addOrphanFiles'])->name('attachment-audit.add-orphan-files');
});

// Admin Routes Group - Additional Testing Routes
Route::prefix('admin')->group(function () {
    Route::get('test-connection', [UnifiedFileManagementController::class, 'testConnection'])->name('admin.test.connection');
    Route::get('test-document-types', [UnifiedFileManagementController::class, 'testDocumentTypes'])->name('admin.test.document.types');
    Route::post('process-bulk-folder-upload-with-duplicate-detection', [UnifiedFileManagementController::class, 'processBulkFolderUploadWithDuplicateDetection'])->name('admin.process.bulk.folder.upload.duplicate.detection');

    // مسارات اختبار الإصلاحات
    Route::post('file/test-duplicate-detection', [TestController::class, 'testDuplicateDetection'])->name('admin.file.test.duplicate.detection');
    Route::get('file/get-recent-logs', [TestController::class, 'getRecentLogs'])->name('admin.file.get.recent.logs');

    // مسارات اختبار ZIP
    Route::get('test/zip-creation', [ZipTestController::class, 'testZipCreation'])->name('admin.test.zip.creation');
    Route::get('test/download-with-error-handling', [ZipTestController::class, 'testDownloadWithBetterErrorHandling'])->name('admin.test.download.error.handling');

    // مسارات اختبار السرعة - Speed Test Routes
    Route::get('speedtest', [SpeedTestController::class, 'index'])->name('admin.speedtest.index');
    Route::get('speedtest/standalone', function() {
        return view('admin.speedtest.standalone');
    })->middleware('permission:عرض قسم أدوات النظام|اختبار سرعة الإنترنت')->name('admin.speedtest.standalone');
    Route::match(['get', 'post'], 'speedtest/api', [SpeedTestController::class, 'api'])->name('admin.speedtest.api');
    Route::get('speedtest/stats', [SpeedTestController::class, 'stats'])->name('admin.speedtest.stats');

    // اختبار سريع بدون مصادقة للتطوير فقط
    Route::get('speedtest/quick-test', function() {
        return response()->json([
            'status' => 'active',
            'version' => '1.0.0',
            'server_time' => date('Y-m-d H:i:s'),
            'server_location' => 'Local Server',
            'message' => 'نظام قياس السرعة يعمل بنجاح'
        ]);
    })->name('admin.speedtest.quick.test');

    // API endpoints بدون مصادقة للتطوير
    Route::match(['get', 'post'], 'speedtest/test-api', function(Illuminate\Http\Request $request) {
        $endpoint = $request->input('endpoint', 'empty');

        switch ($endpoint) {
            case 'empty':
                return response('', 200, [
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Connection' => 'keep-alive'
                ]);

            case 'garbage':
                $chunkCount = $request->input('ckSize', 4);
                $chunkSize = 1048576; // 1MB
                $data = str_repeat('A', $chunkSize);

                return response()->streamDownload(function() use ($data, $chunkCount) {
                    for ($i = 0; $i < $chunkCount; $i++) {
                        echo $data;
                        flush();
                    }
                }, 'test-data.bin', [
                    'Content-Type' => 'application/octet-stream',
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache'
                ]);

            case 'getIP':
                $ip = $request->ip();
                return response()->json([
                    'processedString' => $ip . ' - Local Server',
                    'rawIspInfo' => [
                        'ip' => $ip,
                        'country' => 'Local',
                        'isp' => 'Local Server'
                    ]
                ], 200, [
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate'
                ]);

            default:
                return response()->json(['error' => 'Invalid endpoint'], 400);
        }
    })->name('admin.speedtest.test.api');

    // OpenSpeedTest Routes
    Route::get('openspeedtest', [SpeedTestController::class, 'openSpeedTest'])
        ->middleware('permission:عرض قسم أدوات النظام|OpenSpeedTest')
        ->name('admin.openspeedtest.index');
    Route::get('openspeedtest/download', [SpeedTestController::class, 'download'])->name('admin.openspeedtest.download');
    Route::post('openspeedtest/upload', [SpeedTestController::class, 'upload'])->name('admin.openspeedtest.upload');
    Route::get('openspeedtest/getip', [SpeedTestController::class, 'getIP'])->name('admin.openspeedtest.getip');
    Route::get('openspeedtest/status', [SpeedTestController::class, 'status'])->name('admin.openspeedtest.status');

    // OpenSpeedTest Backend Files (بدون middleware للوصول المباشر)
    Route::get('openspeedtest/backend/download', function() {
        return response()->file(public_path('openspeedtest/downloading'), [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache'
        ]);
    })->name('admin.openspeedtest.backend.download')->withoutMiddleware(['auth']);

    Route::post('openspeedtest/backend/upload', function() {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache'
        ]);
    })->name('admin.openspeedtest.backend.upload')->withoutMiddleware(['auth']);

    Route::get('openspeedtest/backend/getip', function() {
        return response()->json([
            'ip' => request()->ip(),
            'hostname' => request()->getHost(),
            'country' => 'Local',
            'isp' => 'Local Server'
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store, must-revalidate'
        ]);
    })->name('admin.openspeedtest.backend.getip')->withoutMiddleware(['auth']);

    // تنظيف الملفات المؤقتة
    Route::get('openspeedtest/cleanup', function() {
        try {
            $tempPath = storage_path('app/public/temp');
            if (is_dir($tempPath)) {
                $files = glob($tempPath . '/*');
                $deletedCount = 0;
                foreach ($files as $file) {
                    if (is_file($file) && unlink($file)) {
                        $deletedCount++;
                    }
                }
                return response()->json([
                    'status' => 'success',
                    'message' => "تم حذف {$deletedCount} ملف مؤقت"
                ]);
            }
            return response()->json([
                'status' => 'info',
                'message' => 'لا توجد ملفات مؤقتة للحذف'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطأ في تنظيف الملفات: ' . $e->getMessage()
            ], 500);
        }
    })->name('admin.openspeedtest.cleanup');

    // Search Routes - البحث الشامل في السجلات
    Route::get('search-records', [\App\Http\Controllers\Admin\SearchOnRecordsController::class, 'index'])->name('search.records.index');
    Route::get('search-records/modal', [\App\Http\Controllers\Admin\SearchOnRecordsController::class, 'modalContent'])->name('search.records.modal');
    Route::get('search-test', function() { return view('search_test'); })->name('search.test'); // للاختبار
    Route::post('search-records', [\App\Http\Controllers\Admin\SearchOnRecordsController::class, 'search'])
        ->middleware('search.rate.limit')
        ->name('search.records');
    Route::get('search-records/stats', [\App\Http\Controllers\Admin\SearchOnRecordsController::class, 'getSearchStats'])->name('search.stats');
    Route::get('search-records/suggestions', [\App\Http\Controllers\Admin\SearchOnRecordsController::class, 'getSearchSuggestions'])->name('search.suggestions');
    Route::post('search-records/clear-cache', [\App\Http\Controllers\Admin\SearchOnRecordsController::class, 'clearSearchCache'])->name('search.clear.cache');

    // Profile Search Routes - البحث السريع في الملفات مع الاقتراحات
    Route::get('profile-search', [\App\Http\Controllers\Admin\ProfileSearchController::class, 'quickSearch'])->name('profile.search');

    // Sponsorships Routes - مسارات الكفالات
    Route::prefix('sponsorships')->name('sponsorships.')->group(function () {
        Route::get('sponsored', [SponsorshipController::class, 'sponsored'])->name('sponsored');
        Route::get('unsponsored', [SponsorshipController::class, 'unsponsored'])->name('unsponsored');
    });

    // Civil Registry API Routes - مسارات API للسجل المدني
    Route::prefix('api/civil-registry')->name('api.civil.registry.')->group(function () {
        Route::get('search', [CivilRegistrySearchController::class, 'quickSearch'])->name('search');
        Route::get('search-by-id', [CivilRegistrySearchController::class, 'searchById'])->name('search.id');
        Route::get('search-by-name', [CivilRegistrySearchController::class, 'searchByName'])->name('search.name');
        Route::get('comprehensive-search', [CivilRegistrySearchController::class, 'comprehensiveSearch'])->name('comprehensive.search');
        Route::get('stats', [CivilRegistrySearchController::class, 'getStats'])->name('stats');
        Route::get('database-stats', [CivilRegistrySearchController::class, 'getStats'])->name('database.stats');
        Route::get('test-connection', [CivilRegistrySearchController::class, 'testConnection'])->name('test.connection');
        Route::post('clear-cache', [CivilRegistrySearchController::class, 'clearCache'])->name('clear.cache');
    });

    // Civil Registry CRUD Routes - مسارات إدارة السجل المدني
    // Civil Registry CRUD Routes - مسارات إدارة السجل المدني
    Route::prefix('civil-registry')->group(function () {
        Route::get('/', [CivilRegistryController::class, 'index'])
            ->middleware('permission:عرض قسم إدارة السجل المدني|السجل المدني الجديد')
            ->name('civil-registry.index');
        Route::get('/create', [CivilRegistryController::class, 'create'])
            ->middleware('permission:عرض قسم إدارة السجل المدني|إضافة مواطن')
            ->name('civil-registry.create');
        Route::post('/', [CivilRegistryController::class, 'store'])->name('civil-registry.store');
        Route::get('/{id}', [CivilRegistryController::class, 'show'])->name('civil-registry.show');
        Route::get('/{id}/edit', [CivilRegistryController::class, 'edit'])->name('civil-registry.edit');
        Route::put('/{id}', [CivilRegistryController::class, 'update'])->name('civil-registry.update');
        Route::delete('/{id}', [CivilRegistryController::class, 'destroy'])->name('civil-registry.destroy');
        Route::get('/search', [CivilRegistryController::class, 'search'])->name('civil-registry.search');
        Route::get('/stats', [CivilRegistryController::class, 'stats'])->name('civil-registry.stats');
        Route::get('/import-template', [CivilRegistryController::class, 'importTemplate'])->name('civil-registry.import-template');
        Route::post('/import', [CivilRegistryController::class, 'importFromExcel'])->name('civil-registry.import');
    });
});

// Public routes for duplicate files (without authentication middleware)
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

Route::get('sponsors/{sponsor}/field-settings-check', [App\Http\Controllers\Admin\SponsorController::class, 'checkFieldSettings']);

// Test Documents Display
Route::get('/test-documents-direct', function() { return view('test-documents-direct'); });
