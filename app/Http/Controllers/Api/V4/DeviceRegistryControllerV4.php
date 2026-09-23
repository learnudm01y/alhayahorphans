<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use App\Services\V4\DeviceHandshakeServiceV4;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceRegistryControllerV4 extends Controller
{
    protected DeviceHandshakeServiceV4 $handshakeService;

    public function __construct(DeviceHandshakeServiceV4 $handshakeService)
    {
        $this->handshakeService = $handshakeService;
    }

    /**
     * POST /api/mobile/v4/device/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:64',
            'device_label' => 'nullable|string|max:128',
            'app_version' => 'nullable|string|max:20',
            'health_status' => 'nullable|string|in:healthy,degraded,error,unknown',
        ]);

        try {
            $device = $this->handshakeService->register(array_merge(
                $validated,
                ['user_id' => $request->user()?->id]
            ));

            return response()->json([
                'success' => true,
                'device' => $device,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/mobile/v4/device/health
     */
    public function health(): JsonResponse
    {
        $devices = $this->handshakeService->allDevices();

        return response()->json([
            'success' => true,
            'devices' => $devices,
            'count' => $devices->count(),
        ]);
    }
}
