<?php

use App\Http\Controllers\Api\Desktop\V1\AttachmentController;
use App\Http\Controllers\Api\Desktop\V1\ConflictController;
use App\Http\Controllers\Api\Desktop\V1\DeviceController;
use App\Http\Controllers\Api\Desktop\V1\HealthController;
use App\Http\Controllers\Api\Desktop\V1\SyncPullController;
use App\Http\Controllers\Api\Desktop\V1\SyncPushController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::middleware('throttle:desktop-v1')->group(function () {
    Route::get('health', [HealthController::class, 'show'])->name('api.desktop.v1.health');
    Route::post('devices/register', [DeviceController::class, 'register'])->name('api.desktop.v1.devices.register');

    Route::post('devices/enrollment-code', [DeviceController::class, 'enrollmentCode'])
        ->middleware(['auth:sanctum', CheckAbilities::class.':desktop:admin-bootstrap'])
        ->name('api.desktop.v1.devices.enrollment-code');

    Route::middleware(['auth:sanctum', CheckAbilities::class.':sync:write'])->group(function () {
        Route::post('sync/push', [SyncPushController::class, 'push'])->name('api.desktop.v1.sync.push');
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':desktop:admin-bootstrap'])->group(function () {
        Route::get('conflicts', [ConflictController::class, 'index'])->name('api.desktop.v1.conflicts.index');
        Route::post('conflicts/{conflictUuid}/resolve', [ConflictController::class, 'resolve'])->name('api.desktop.v1.conflicts.resolve');
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':sync:read'])->group(function () {
        Route::get('capabilities', [DeviceController::class, 'capabilities'])->name('api.desktop.v1.capabilities');
        Route::get('sync/pull', [SyncPullController::class, 'pull'])->name('api.desktop.v1.sync.pull');
        Route::get('sync/reconciliation', [SyncPullController::class, 'reconcile'])->name('api.desktop.v1.sync.reconciliation');
        Route::post('devices/token/rotate', [DeviceController::class, 'rotate'])->name('api.desktop.v1.devices.token.rotate');
        Route::post('devices/token/revoke', [DeviceController::class, 'revoke'])->name('api.desktop.v1.devices.token.revoke');
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':sync:attachments:read'])->group(function () {
        Route::get('attachments', [AttachmentController::class, 'index'])->name('api.desktop.v1.attachments.index');
        Route::get('attachments/{attachmentUuid}/status', [AttachmentController::class, 'status'])->name('api.desktop.v1.attachments.status');
        Route::get('attachments/{attachmentUuid}/content', [AttachmentController::class, 'download'])->name('api.desktop.v1.attachments.download');
    });

    Route::middleware(['auth:sanctum', CheckAbilities::class.':sync:attachments:write'])->group(function () {
        Route::post('attachments', [AttachmentController::class, 'store'])->name('api.desktop.v1.attachments.store');
        Route::put('attachments/{attachmentUuid}/chunks/{chunkIndex}', [AttachmentController::class, 'chunk'])->name('api.desktop.v1.attachments.chunks.store');
        Route::post('attachments/{attachmentUuid}/complete', [AttachmentController::class, 'complete'])->name('api.desktop.v1.attachments.complete');
    });
});
