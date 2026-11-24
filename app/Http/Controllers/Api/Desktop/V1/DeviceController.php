<?php

namespace App\Http\Controllers\Api\Desktop\V1;

use App\Http\Controllers\Controller;
use App\Services\DesktopSync\CapabilityService;
use App\Services\DesktopSync\DeviceCredentialService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceCredentialService $credentials,
        private readonly CapabilityService $capabilities
    ) {
    }

    public function enrollmentCode(Request $request): JsonResponse
    {
        $validated = $request->validate($this->deviceRules());

        try {
            $result = $this->credentials->createEnrollmentCode($request->user(), $validated);

            return response()->json(array_merge($result, $this->capabilities->metadata()), 201);
        } catch (DomainException) {
            return response()->json([
                'error' => 'device_source_mismatch',
                'message' => 'The device is registered to a different source identity.',
            ], 409);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge($this->deviceRules(), [
            'enrollmentCode' => ['required', 'string', 'size:64'],
        ]));

        try {
            $result = $this->credentials->register($validated);

            return response()->json(array_merge($result, $this->capabilities->metadata()));
        } catch (InvalidArgumentException) {
            return response()->json([
                'error' => 'invalid_enrollment_code',
                'message' => 'The enrollment credential is invalid or expired.',
            ], 401);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function capabilities(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        return response()->json(array_merge([
            'capabilities' => $this->capabilities->allowedFor($request->user()),
            'device' => $this->credentials->devicePayload($device),
        ], $this->capabilities->metadata()));
    }

    public function rotate(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        try {
            $result = $this->credentials->rotate(
                $request->user(),
                $device,
                $request->header('X-App-Version')
            );

            return response()->json(array_merge($result, $this->capabilities->metadata()));
        } catch (DomainException) {
            return $this->forbidden();
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    public function revoke(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        try {
            $device = $this->credentials->revoke($request->user(), $device);

            return response()->json(array_merge([
                'revoked' => true,
                'device' => $this->credentials->devicePayload($device),
            ], $this->capabilities->metadata()));
        } catch (DomainException) {
            return $this->forbidden();
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

    private function deviceRules(): array
    {
        return [
            'deviceId' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/'],
            'sourceId' => ['required', 'string', 'max:144', 'regex:/^laravel:[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/i'],
            'deviceLabel' => ['nullable', 'string', 'max:128'],
            'appVersion' => ['required', 'string', 'max:32'],
        ];
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
