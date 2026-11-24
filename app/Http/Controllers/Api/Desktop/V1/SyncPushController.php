<?php

namespace App\Http\Controllers\Api\Desktop\V1;

use App\Http\Controllers\Controller;
use App\Services\DesktopSync\DeviceCredentialService;
use App\Services\DesktopSync\IdempotencyService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class SyncPushController extends Controller
{
    public function __construct(
        private readonly IdempotencyService $operations,
        private readonly DeviceCredentialService $credentials
    ) {
    }

    public function push(Request $request): JsonResponse
    {
        $device = $this->authorizedDevice($request);
        if ($device === null) {
            return $this->forbidden();
        }

        $operations = $this->extractOperations($request->all());
        $request->replace(['operations' => $operations]);
        $validated = $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*.operationId' => ['required', 'uuid'],
            'operations.*.idempotencyKey' => ['required', 'string', 'max:191'],
            'operations.*.sourceId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'operations.*.deviceId' => ['sometimes', 'nullable', 'string', 'max:64'],
            'operations.*.entityType' => ['required', 'string', 'max:128'],
            'operations.*.clientUuid' => ['required', 'uuid'],
            'operations.*.canonicalId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'operations.*.operationType' => ['required', 'in:Create,Update,Delete'],
            'operations.*.baseVersion' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'operations.*.rowVersion' => ['sometimes', 'nullable', 'string', 'max:191'],
            'operations.*.occurredAtUtc' => ['sometimes', 'nullable', 'date'],
            'operations.*.payload' => ['sometimes', 'array'],
            'operations.*.fieldPatches' => ['sometimes', 'array', 'max:200'],
            'operations.*.fieldPatches.*.fieldName' => ['required', 'string', 'max:191'],
            'operations.*.fieldPatches.*.criticality' => ['sometimes', 'in:Mergeable,Critical,Reference,ServerOwned'],
            'operations.*.references' => ['sometimes', 'array'],
            'operations.*.tombstone' => ['sometimes', 'array'],
        ]);
        $operations = $this->validateOperationSemantics($validated['operations'], $device);

        try {
            return response()->json($this->operations->push($device, $operations));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['error' => 'invalid_operation', 'message' => $exception->getMessage()], 422);
        } catch (DomainException $exception) {
            return response()->json(['error' => 'operation_rejected', 'message' => $exception->getMessage()], 409);
        } catch (RuntimeException) {
            return $this->unavailable();
        }
    }

    private function extractOperations(array $input): mixed
    {
        $operations = $input['operations'] ?? $input['batches'] ?? [];
        if (!is_array($operations)) {
            return $operations;
        }

        $flattened = [];
        foreach ($operations as $operation) {
            if (is_array($operation) && isset($operation['operations']) && is_array($operation['operations'])) {
                foreach ($operation['operations'] as $nested) {
                    $flattened[] = $this->normalizeOperation($nested);
                }
            } else {
                $flattened[] = $this->normalizeOperation($operation);
            }
        }

        return $flattened;
    }

    private function normalizeOperation(mixed $operation): mixed
    {
        if (!is_array($operation)) {
            return $operation;
        }

        $entity = $operation['entity'] ?? $operation['Entity'] ?? [];
        $map = [
            'operationId' => ['operationId', 'OperationId'],
            'idempotencyKey' => ['idempotencyKey', 'IdempotencyKey'],
            'sourceId' => ['sourceId', 'SourceId'],
            'deviceId' => ['deviceId', 'DeviceId'],
            'operationType' => ['operationType', 'OperationType'],
            'baseVersion' => ['baseVersion', 'BaseVersion'],
            'rowVersion' => ['rowVersion', 'RowVersion'],
            'occurredAtUtc' => ['occurredAtUtc', 'OccurredAtUtc'],
            'payload' => ['payload', 'Payload'],
            'references' => ['references', 'References'],
            'tombstone' => ['tombstone', 'Tombstone'],
            'fieldPatches' => ['fieldPatches', 'FieldPatches', 'patches', 'Patches'],
            'entityType' => ['entityType', 'EntityType'],
            'clientUuid' => ['clientUuid', 'ClientUuid'],
            'canonicalId' => ['canonicalId', 'CanonicalId'],
        ];

        $normalized = $operation;
        foreach ($map as $target => $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $operation)) {
                    $normalized[$target] = $operation[$key];
                    break;
                }
            }
        }
        if (is_array($entity)) {
            $normalized['entityType'] ??= $entity['entityType'] ?? $entity['EntityType'] ?? null;
            $normalized['clientUuid'] ??= $entity['clientUuid'] ?? $entity['ClientUuid'] ?? null;
            $normalized['canonicalId'] ??= $entity['canonicalId'] ?? $entity['CanonicalId'] ?? null;
            $normalized['references'] ??= $entity['references'] ?? $entity['References'] ?? [];
        }

        if (isset($normalized['operationType'])) {
            $normalized['operationType'] = ucfirst(strtolower((string) $normalized['operationType']));
        }
        if (isset($normalized['fieldPatches']) && is_array($normalized['fieldPatches'])) {
            $normalized['fieldPatches'] = array_map(function (mixed $patch): mixed {
                if (!is_array($patch)) {
                    return $patch;
                }

                foreach ([
                    'fieldName' => ['fieldName', 'FieldName'],
                    'baseValue' => ['baseValue', 'BaseValue'],
                    'localValue' => ['localValue', 'LocalValue'],
                    'remoteValue' => ['remoteValue', 'RemoteValue'],
                    'criticality' => ['criticality', 'Criticality'],
                ] as $target => $keys) {
                    foreach ($keys as $key) {
                        if (array_key_exists($key, $patch)) {
                            $patch[$target] = $patch[$key];
                            break;
                        }
                    }
                }

                return $patch;
            }, $normalized['fieldPatches']);
        }

        if (isset($normalized['tombstone']) && is_array($normalized['tombstone'])) {
            $normalized['tombstone']['deletedAtUtc'] ??= $normalized['tombstone']['DeletedAtUtc'] ?? null;
            $normalized['tombstone']['deletedByDeviceId'] ??= $normalized['tombstone']['DeletedByDeviceId'] ?? null;
        }

        return $normalized;
    }

    private function validateOperationSemantics(array $operations, object $device): array
    {
        $errors = [];
        foreach ($operations as $index => $operation) {
            if (isset($operation['deviceId']) && $operation['deviceId'] !== $device->device_id) {
                $errors["operations.$index.deviceId"] = 'The operation device does not match the authenticated device.';
            }
            if ($operation['operationType'] !== 'Create' && !array_key_exists('baseVersion', $operation)) {
                $errors["operations.$index.baseVersion"] = 'BaseVersion is required for update and delete operations.';
            }
            if ($operation['operationType'] === 'Delete'
                && (!isset($operation['tombstone']['deletedAtUtc'])
                    || !isset($operation['tombstone']['deletedByDeviceId']))) {
                $errors["operations.$index.tombstone"] = 'A delete operation requires a complete tombstone.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $operations;
    }

    private function authorizedDevice(Request $request): ?object
    {
        $deviceId = $request->header('X-Device-Id');
        $sourceId = $request->header('X-Source-Id');
        $appVersion = $request->header('X-App-Version');
        if (!is_string($deviceId) || !is_string($sourceId) || ($appVersion !== null && !is_string($appVersion))) {
            return null;
        }

        try {
            return $this->credentials->registeredDevice($request->user(), $deviceId, $sourceId, $appVersion);
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
