<?php

namespace App\Services\DesktopSync;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IdempotencyService
{
    private const TABLE = 'desktop_operations_v1';

    public function __construct(
        private readonly SchemaGuard $schema,
        private readonly FieldMergeService $fields,
        private readonly ConflictService $conflicts
    ) {
    }

    public function push(object $device, array $operations): array
    {
        $this->ensureSchema();
        $results = [];
        $warnings = [];

        foreach ($operations as $operation) {
            $outcome = $this->process($device, $operation);
            $results[] = $outcome['result'];
            if ($outcome['warning'] !== null) {
                $warnings[] = $outcome['warning'];
            }
        }

        return [
            'results' => $results,
            'retentionWarnings' => $warnings,
            'server_time' => now('UTC')->toISOString(),
        ];
    }

    public function prune(?Carbon $now = null): int
    {
        $this->ensureSchema();
        $now ??= now('UTC');
        $deleted = 0;
        $rows = DB::table(self::TABLE)->whereNotNull('response_json')->get(['id', 'status', 'payload_json']);

        foreach ($rows as $row) {
            if (in_array($row->status, ['pending', 'processing'], true)) {
                continue;
            }

            $payload = $this->decode($row->payload_json);
            $expiresAt = isset($payload['retentionExpiresAt'])
                ? Carbon::parse($payload['retentionExpiresAt'], 'UTC')
                : null;
            if ($expiresAt === null || !$expiresAt->lt($now)) {
                continue;
            }

            $deleted += DB::table(self::TABLE)->where('id', $row->id)->delete();
        }

        return $deleted;
    }

    private function process(object $device, array $operation): array
    {
        $now = now('UTC');
        $operationId = (string) ($operation['operationId'] ?? '');
        if ($operationId === '' || !is_string($operation['idempotencyKey'] ?? null) || $operation['idempotencyKey'] === '') {
            throw new \InvalidArgumentException('OperationId and IdempotencyKey are required.');
        }

        $requestHash = $this->requestHash($operation);
        $initialPayload = [
            'idempotencyKey' => $operation['idempotencyKey'],
            'requestHash' => $requestHash,
            'retentionExpiresAt' => $now->copy()->addDays($this->retentionDays())->toISOString(),
            'operation' => $operation,
        ];

        return DB::transaction(function () use ($device, $operation, $operationId, $requestHash, $initialPayload, $now): array {
            DB::table(self::TABLE)->insertOrIgnore([
                'operation_id' => $operationId,
                'device_id' => $device->device_id,
                'source_id' => $device->source_id,
                'entity_type' => (string) ($operation['entityType'] ?? ''),
                'primary_key' => isset($operation['canonicalId']) ? (string) $operation['canonicalId'] : null,
                'client_uuid' => $operation['clientUuid'] ?? null,
                'operation_type' => ucfirst(strtolower((string) ($operation['operationType'] ?? ''))),
                'payload_json' => $this->encode($initialPayload),
                'base_version' => $operation['baseVersion'] ?? null,
                'row_version' => $operation['rowVersion'] ?? null,
                'status' => 'processing',
                'response_json' => null,
                'received_at' => $now,
                'processed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $stored = DB::table(self::TABLE)->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($stored === null) {
                throw new RuntimeException('The desktop operation could not be persisted.');
            }

            $payload = $this->decode($stored->payload_json);
            $expiresAt = Carbon::parse($payload['retentionExpiresAt'], 'UTC');
            if ($stored->device_id !== $device->device_id
                || $stored->source_id !== $device->source_id
                || !hash_equals((string) ($payload['idempotencyKey'] ?? ''), $operation['idempotencyKey'])
                || !hash_equals((string) ($payload['requestHash'] ?? ''), $requestHash)) {
                return [
                    'result' => [
                        'operationId' => $operationId,
                        'status' => 'rejected',
                        'error' => 'idempotency_mismatch',
                        'retentionExpiresAt' => $expiresAt->toISOString(),
                    ],
                    'warning' => $this->retentionWarning($operationId, $expiresAt),
                ];
            }

            if ($stored->response_json !== null) {
                return [
                    'result' => $this->decode($stored->response_json),
                    'warning' => $this->retentionWarning($operationId, $expiresAt),
                ];
            }

            $plan = $this->fields->prepare($device, $operation);
            $result = $plan['status'] === 'conflict'
                ? $this->conflicts->hold($device, $plan)
                : $this->fields->commit($plan);
            $result['retentionExpiresAt'] = $expiresAt->toISOString();

            DB::table(self::TABLE)->where('id', $stored->id)->update([
                'primary_key' => $result['canonicalId'] ?? $stored->primary_key,
                'status' => $result['status'],
                'response_json' => $this->encode($result),
                'processed_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'result' => $result,
                'warning' => $this->retentionWarning($operationId, $expiresAt),
            ];
        });
    }

    private function retentionDays(): int
    {
        $policy = json_decode(
            file_get_contents(base_path('DesktopApp/spec/domain/sync-policy.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $policyMinimum = (int) ($policy['tombstones']['minimumOnlineRetentionDays'] ?? 90);

        return max(
            1,
            $policyMinimum,
            (int) config('desktop_sync.maximum_offline_days', $policyMinimum),
            (int) config('desktop_sync.operation_retention_days', $policyMinimum)
        );
    }

    private function retentionWarning(string $operationId, Carbon $expiresAt): ?array
    {
        $warningDays = max(0, (int) config('desktop_sync.operation_retention_warning_days', 7));
        if (now('UTC')->lt($expiresAt->copy()->subDays($warningDays))) {
            return null;
        }

        return [
            'operationId' => $operationId,
            'expiresAt' => $expiresAt->toISOString(),
            'expired' => now('UTC')->greaterThan($expiresAt),
            'message' => 'Reconcile pending operations before the idempotency result expires.',
        ];
    }

    private function requestHash(array $operation): string
    {
        return hash('sha256', $this->encode($this->canonicalize($operation)));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function ensureSchema(): void
    {
        if (!$this->schema->hasColumns(self::TABLE, [
            'id', 'operation_id', 'device_id', 'source_id', 'entity_type', 'primary_key',
            'client_uuid', 'operation_type', 'payload_json', 'base_version', 'row_version',
            'status', 'response_json', 'received_at', 'processed_at', 'created_at', 'updated_at',
        ])) {
            throw new RuntimeException('Desktop operation idempotency is unavailable.');
        }
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decode(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
