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
use App\Http\Controllers\Admin\RecordsManagementEditController;
use App\Http\Controllers\Admin\RequestStatusController;
use App\Http\Controllers\Admin\SponsorshipStatusController;
use App\Http\Controllers\Admin\TypeOfAccommodationController;
use App\Http\Controllers\Admin\TypeOfGuaranteeController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
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
});
