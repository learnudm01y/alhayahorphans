<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UnifiedFileManagementController;

/*
|--------------------------------------------------------------------------
| Advanced File Management Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/files')->middleware(['auth'])->group(function () {
    // Smart upload endpoint
    Route::post('/smart-upload', [UnifiedFileManagementController::class, 'smartUpload'])->name('files.smart-upload');

    // Batch upload endpoints
    Route::post('/batch-upload', [UnifiedFileManagementController::class, 'batchUpload'])->name('files.batch-upload');
    Route::get('/batch-status/{batch_id}', [UnifiedFileManagementController::class, 'getBatchStatus'])->name('files.batch.status');

    // Search and analytics
    Route::get('/search', [UnifiedFileManagementController::class, 'searchFiles'])->name('files.search');
    Route::get('/analytics', [UnifiedFileManagementController::class, 'getFileAnalytics'])->name('files.analytics');

    // Cloud integration
    Route::post('/sync-cloud', [UnifiedFileManagementController::class, 'syncWithCloud'])->name('files.sync-cloud');

    // Duplicate files management
    Route::get('/duplicate-summary', [UnifiedFileManagementController::class, 'getDuplicateSummary'])->name('files.duplicate.summary');
});

// File management interface routes
Route::middleware(['auth'])->group(function () {
    Route::get('/file-management', function () {
        return view('file-management.advanced-interface');
    })->name('file-management.interface');
});
