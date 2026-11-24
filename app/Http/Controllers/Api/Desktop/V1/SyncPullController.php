<?php

namespace App\Http\Controllers\Api\Desktop\V1;

use App\Http\Controllers\Controller;
use App\Services\DesktopSync\ChangeFeedService;
use App\Services\DesktopSync\DeviceCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SyncPullController extends Controller
{
    public function __construct(
        private readonly ChangeFeedService $changes,
        private readonly DeviceCredentialService $credentials
    ) {
    }

    public function pull(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:4096'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        try {
            return response()->json($this->changes->pull(
                $device->source_id,
                $validated['cursor'] ?? null,
                isset($validated['limit']) ? (int) $validated['limit'] : null
            ));
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function reconcile(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'snapshot_id' => ['nullable', 'string', 'max:4096'],
            'page_cursor' => ['nullable', 'string', 'max:4096'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        try {
            return response()->json($this->changes->reconcile(
                $device->source_id,
                $validated['snapshot_id'] ?? null,
                $validated['page_cursor'] ?? null,
                isset($validated['limit']) ? (int) $validated['limit'] : null
            ));
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    private function authorizedDevice(Request $request): ?object
    {
        $deviceId = $request->header('X-Device-Id');
        $sourceId = $request->header('X-Source-Id');
        $appVersion = $request->header('X-App-Version');

        if (!is_string($deviceId)
            || !is_string($sourceId)
            || ($appVersion !== null && !is_string($appVersion))) {
            return null;
        }

        try {
            return $this->credentials->registeredDevice(
                $request->user(),
                $deviceId,
                $sourceId,
                $appVersion
            );
        } catch (RuntimeException) {
            return null;
        }
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'error' => 'device_not_registered',
            'message' => 'The device credential is not registered or is no longer active.',
        ], 403);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'desktop_sync_unavailable',
            'message' => 'Desktop synchronization is temporarily unavailable.',
        ], 503);
    }
}
