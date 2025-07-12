<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UnifiedFileManagementController;
use App\Http\Controllers\SimpleFileUploadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Simple file upload for testing
Route::prefix('simple')->group(function () {
    Route::get('/test', [SimpleFileUploadController::class, 'test']);
    Route::post('/upload', [SimpleFileUploadController::class, 'simpleUpload']);
});

// File management API routes
Route::prefix('files')->group(function () {
    Route::get('/analytics', [UnifiedFileManagementController::class, 'getAnalytics']);
    Route::post('/smart-upload', [UnifiedFileManagementController::class, 'smartUpload']);
    Route::post('/batch-upload', [UnifiedFileManagementController::class, 'batchUpload']);
    Route::get('/batch-status/{batch_id}', [UnifiedFileManagementController::class, 'getBatchStatus']);
    Route::get('/download/{fileId}', [UnifiedFileManagementController::class, 'downloadFile']);

    // New endpoints for enhanced functionality
    Route::post('/generate-record-number', [UnifiedFileManagementController::class, 'generateRecordNumber']);
    Route::post('/excel-bulk-import', [UnifiedFileManagementController::class, 'excelBulkImport']);
    Route::post('/process-folder-upload', [UnifiedFileManagementController::class, 'processFolderUpload']);
    Route::get('/analytics/overview', [UnifiedFileManagementController::class, 'getAnalyticsOverview']);
    Route::get('/analytics/storage', [UnifiedFileManagementController::class, 'getStorageAnalytics']);
    Route::get('/analytics/activity', [UnifiedFileManagementController::class, 'getActivityAnalytics']);
});
