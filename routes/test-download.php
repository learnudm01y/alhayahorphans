<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UnifiedFileManagementController;

Route::get('/test-download-all', [UnifiedFileManagementController::class, 'downloadAllDuplicateFiles']);
Route::post('/test-download-selected', [UnifiedFileManagementController::class, 'downloadSelectedDuplicateFiles']);
