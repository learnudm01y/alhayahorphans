<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\PersonsController;
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

});
