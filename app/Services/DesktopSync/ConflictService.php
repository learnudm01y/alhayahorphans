<?php

namespace App\Services\DesktopSync;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ConflictService
{
    private const TABLE = 'desktop_conflicts_v1';

    private const OPERATIONS = 'desktop_operations_v1';

    public function __construct(
        private readonly SchemaGuard $schema,
        private readonly FieldMergeService $fields
    ) {
    }

    public function hold(object $device, array $plan): array
    {
        $this->ensureSchema();
        $conflictUuid = (string) Str::uuid();
        $now = now('UTC');
        $version = 1;

        DB::table(self::TABLE)->insert([
            'conflict_uuid' => $conflictUuid,
            'operation_id' => $plan['operationId'],
            'device_id' => $device->device_id,
            'source_id' => $device->source_id,
            'entity_type' => $plan['entityType'],
            'primary_key' => $plan['canonicalId'],
            'client_uuid' => $plan['clientUuid'],
            'conflict_type' => $plan['conflictType'],
            'base_payload_json' => $this->encode($plan['basePayload']),
            'local_payload_json' => $this->encode($plan['localPayload']),
            'remote_payload_json' => $this->encode($plan['remotePayload']),
            'conflicting_fields_json' => $this->encode($plan['conflictingFields']),
            'status' => 'pending',
            'resolution_payload_json' => $this->encode([
                'conflictVersion' => $version,
                'decision' => null,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Log::info('desktop.conflict_held', [
            'conflict_uuid' => $conflictUuid,
            'operation_id' => $plan['operationId'],
            'device_id' => $device->device_id,
            'source_id' => $device->source_id,
            'entity_type' => $plan['entityType'],
            'conflict_type' => $plan['conflictType'],
        ]);

        return [
            'operationId' => $plan['operationId'],
            'status' => 'pending',
            'clientUuid' => $plan['clientUuid'],
            'canonicalId' => $plan['canonicalId'],
            'rowVersion' => $plan['rowVersion'],
            'baseVersion' => $plan['currentVersion'],
            'conflict' => [
                'conflictUuid' => $conflictUuid,
                'type' => $plan['conflictType'],
                'status' => 'pending',
                'conflictVersion' => $version,
            ],
        ];
    }

    public function list(string $status = 'pending', ?string $entityType = null, int $limit = 100): array
    {
        $this->ensureSchema();
        $query = DB::table(self::TABLE)->orderBy('id')->limit(max(1, min(500, $limit)));
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($entityType !== null && $entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        return $query->get()->map(fn (object $row): array => $this->payload($row))->all();
    }

    public function resolve(
        User $user,
        string $conflictUuid,
        int $expectedConflictVersion,
        string $resolution,
        array $selectedFields = []
    ): array {
        $this->ensureSchema();
        if (!in_array($resolution, ['server', 'local', 'selected-fields'], true)) {
            throw new InvalidArgumentException('The conflict resolution is invalid.');
        }

        return DB::transaction(function () use (
            $user,
            $conflictUuid,
            $expectedConflictVersion,
            $resolution,
            $selectedFields
        ): array {
            $conflict = DB::table(self::TABLE)->where('conflict_uuid', $conflictUuid)->lockForUpdate()->first();
            if ($conflict === null) {
                throw new DomainException('The conflict was not found.');
            }

            $state = $this->decode($conflict->resolution_payload_json);
            $currentVersion = (int) ($state['conflictVersion'] ?? 1);
            if ($conflict->status !== 'pending' || $expectedConflictVersion !== $currentVersion) {
                throw new DomainException('The conflict version is stale.');
            }

            $operationResult = $this->fields->resolve($conflict, $resolution, $selectedFields);
            $now = now('UTC');
            $nextVersion = $currentVersion + 1;
            $result = array_merge($operationResult, [
                'status' => 'resolved',
                'conflictUuid' => $conflictUuid,
                'resolution' => $resolution,
                'selectedFields' => $selectedFields,
                'conflictVersion' => $nextVersion,
            ]);
            $decision = [
                'resolution' => $resolution,
                'selectedFields' => $selectedFields,
                'expectedConflictVersion' => $expectedConflictVersion,
                'resolvedByUserId' => $user->getKey(),
                'resolvedAt' => $now->toISOString(),
                'result' => $result,
            ];

            DB::table(self::TABLE)->where('id', $conflict->id)->update([
                'status' => 'resolved',
                'resolution_payload_json' => $this->encode([
                    'conflictVersion' => $nextVersion,
                    'decision' => $decision,
                ]),
                'resolved_by_user_id' => $user->getKey(),
                'resolved_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table(self::OPERATIONS)->where('operation_id', $conflict->operation_id)->update([
                'status' => 'resolved',
                'response_json' => $this->encode($result),
                'processed_at' => $now,
                'updated_at' => $now,
            ]);

            Log::info('desktop.conflict_resolved', [
                'conflict_uuid' => $conflictUuid,
                'operation_id' => $conflict->operation_id,
                'user_id' => $user->getKey(),
                'resolution' => $resolution,
                'conflict_version' => $nextVersion,
            ]);

            return $result;
        });
    }

    private function payload(object $row): array
    {
        $state = $this->decode($row->resolution_payload_json);

        return [
            'conflictUuid' => $row->conflict_uuid,
            'operationId' => $row->operation_id,
            'deviceId' => $row->device_id,
            'sourceId' => $row->source_id,
            'entityType' => $row->entity_type,
            'canonicalId' => $row->primary_key,
            'clientUuid' => $row->client_uuid,
            'type' => $row->conflict_type,
            'status' => $row->status,
            'base' => $this->decode($row->base_payload_json),
            'local' => $this->decode($row->local_payload_json),
            'remote' => $this->decode($row->remote_payload_json),
            'conflictingFields' => $this->decode($row->conflicting_fields_json),
            'conflictVersion' => (int) ($state['conflictVersion'] ?? 1),
            'decision' => $state['decision'] ?? null,
            'createdAt' => $row->created_at,
            'resolvedAt' => $row->resolved_at,
        ];
    }

    private function ensureSchema(): void
    {
        if (!$this->schema->hasColumns(self::TABLE, [
            'id', 'conflict_uuid', 'operation_id', 'device_id', 'source_id', 'entity_type',
            'primary_key', 'client_uuid', 'conflict_type', 'base_payload_json',
            'local_payload_json', 'remote_payload_json', 'conflicting_fields_json', 'status',
            'resolution_payload_json', 'resolved_by_user_id', 'resolved_at', 'created_at', 'updated_at',
        ]) || !$this->schema->hasColumns(self::OPERATIONS, [
            'operation_id', 'status', 'response_json', 'processed_at', 'updated_at',
        ])) {
            throw new RuntimeException('Desktop conflict handling is unavailable.');
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
