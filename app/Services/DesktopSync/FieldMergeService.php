<?php

namespace App\Services\DesktopSync;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class FieldMergeService
{
    private ?array $policy = null;

    private ?array $ownership = null;

    private ?array $fieldCatalog = null;

    private ?array $entityTables = null;

    private ?array $tableFields = null;

    public function __construct(protected readonly SchemaGuard $schema)
    {
    }

    public function prepare(object $device, array $operation): array
    {
        $entityType = (string) ($operation['entityType'] ?? '');
        $operationType = ucfirst(strtolower((string) ($operation['operationType'] ?? '')));
        if (!in_array($operationType, ['Create', 'Update', 'Delete'], true)) {
            throw new InvalidArgumentException('The desktop operation type is invalid.');
        }

        $plan = $this->basePlan($device, $operation, $entityType, $operationType);
        $this->ensureWritableTable($plan['table']);

        if (isset($operation['deviceId'])
            && !hash_equals((string) $device->device_id, (string) $operation['deviceId'])) {
            return $this->asConflict($plan, 'SourceIdentityMismatch');
        }
        if (isset($operation['sourceId'])
            && !hash_equals(strtolower((string) $device->source_id), strtolower((string) $operation['sourceId']))) {
            return $this->asConflict($plan, 'SourceIdentityMismatch');
        }

        $referenceConflict = $this->referenceConflict($operation['references'] ?? []);
        if ($referenceConflict !== null) {
            return $this->asConflict($plan, $referenceConflict);
        }

        if ($operationType === 'Create') {
            return $this->prepareCreate($plan, $operation);
        }

        return $this->prepareExisting($plan, $operation);
    }

    public function commit(array $plan): array
    {
        if (($plan['status'] ?? null) !== 'apply') {
            throw new InvalidArgumentException('A held operation cannot be committed.');
        }

        $now = now('UTC');
        $canonicalId = $plan['canonicalId'];
        $row = $plan['current'];

        if ($plan['operationType'] === 'Create') {
            $values = $plan['values'];
            if ($this->schema->hasColumn($plan['table'], 'client_uuid')) {
                $values['client_uuid'] = $plan['clientUuid'];
            }
            if ($this->schema->hasColumn($plan['table'], 'row_version')) {
                $values['row_version'] = 1;
            }
            if ($this->schema->hasColumn($plan['table'], 'created_at')) {
                $values['created_at'] = $now;
            }
            if ($this->schema->hasColumn($plan['table'], 'updated_at')) {
                $values['updated_at'] = $now;
            }
            if ($this->schema->hasColumn($plan['table'], 'sync_origin_device_id')) {
                $values['sync_origin_device_id'] = $plan['deviceId'];
            }

            $primaryKey = $this->schema->primaryKeyColumns($plan['table']);
            if (count($primaryKey) === 1 && !array_key_exists($primaryKey[0], $values)) {
                $id = DB::table($plan['table'])->insertGetId($values);
                $canonicalId = (string) $id;
            } else {
                DB::table($plan['table'])->insert($values);
                $canonicalId = $this->encodePrimaryKey($primaryKey, $values);
            }

            $row = $this->lockRow($plan['table'], $canonicalId);
        } elseif ($plan['operationType'] === 'Delete') {
            if ($row === null) {
                throw new RuntimeException('The row selected for deletion is unavailable.');
            }

            if ($this->schema->hasColumn($plan['table'], 'deleted_at')) {
                $values = [
                    'deleted_at' => $plan['tombstone']['deletedAtUtc'] ?? $now,
                ];
                $values = $this->withRevision($plan, $values, $now);
                $this->keysQuery($plan['table'], $canonicalId)->update($values);
                $row = $this->lockRow($plan['table'], $canonicalId, true);
            } else {
                $this->keysQuery($plan['table'], $canonicalId)->delete();
                $row = null;
            }
        } elseif ($plan['values'] !== [] || $plan['forceRevision']) {
            if ($row === null) {
                throw new RuntimeException('The row selected for updating is unavailable.');
            }

            $values = $this->withRevision($plan, $plan['values'], $now);
            $this->keysQuery($plan['table'], $canonicalId)->update($values);
            $row = $this->lockRow($plan['table'], $canonicalId, true);
        }

        return $this->appliedResult($plan, $canonicalId, $row);
    }

    public function resolve(object $conflict, string $resolution, array $selectedFields): array
    {
        $table = $this->tableFor($conflict->entity_type);
        $this->ensureWritableTable($table);
        $row = $this->lockRow($table, $conflict->primary_key, true);
        if ($row === null) {
            throw new RuntimeException('The conflicted business row is unavailable.');
        }

        if (array_key_exists('client_uuid', $row)
            && $row['client_uuid'] !== null
            && !hash_equals((string) $row['client_uuid'], (string) $conflict->client_uuid)) {
            throw new DomainException('The conflicted row identity has changed.');
        }

        $local = $this->decodeJson($conflict->local_payload_json);
        $operationType = $local['operationType'] ?? 'Update';
        if ($operationType === 'Delete' && $resolution === 'selected-fields') {
            throw new InvalidArgumentException('A delete conflict only supports server or local resolution.');
        }

        $values = [];
        $operationType = 'Update';
        $tombstone = null;
        if ($resolution === 'local') {
            $values = $local['values'] ?? [];
            $operationType = $local['operationType'] ?? 'Update';
            $tombstone = $local['tombstone'] ?? null;
        } elseif ($resolution === 'selected-fields') {
            $localValues = $local['values'] ?? [];
            if ($selectedFields === [] || array_diff($selectedFields, array_keys($localValues)) !== []) {
                throw new InvalidArgumentException('The selected fields are not present in the local operation.');
            }
            foreach ($selectedFields as $field) {
                $values[$field] = $localValues[$field];
            }
        }

        $plan = $this->basePlan($this->deviceFromConflict($conflict), [
            'operationId' => $conflict->operation_id,
            'clientUuid' => $conflict->client_uuid,
            'canonicalId' => $conflict->primary_key,
            'tombstone' => $tombstone,
        ], $conflict->entity_type, $operationType);
        $plan['current'] = $row;
        $plan['currentVersion'] = $this->currentVersion($table, $conflict->primary_key, $row);
        $plan['rowVersion'] = $this->rowVersion($row);
        $plan['values'] = $values;
        $plan['forceRevision'] = true;
        $plan['tombstone'] = $tombstone;

        return $this->commit($plan);
    }

    private function prepareCreate(array $plan, array $operation): array
    {
        if ($operation['canonicalId'] ?? null) {
            return $this->asConflict($plan, 'SourceIdentityMismatch');
        }
        if (($operation['baseVersion'] ?? null) !== null) {
            return $this->asConflict($plan, 'VersionMismatch');
        }

        if ($this->schema->hasColumn($plan['table'], 'client_uuid')) {
            $existing = DB::table($plan['table'])
                ->where('client_uuid', $plan['clientUuid'])
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $this->asConflict($plan, 'SourceIdentityMismatch');
            }
        }

        $values = $operation['payload'] ?? [];
        foreach ($operation['fieldPatches'] ?? $operation['patches'] ?? [] as $patch) {
            $field = $this->patchField($plan, $patch);
            $values[$field] = $patch['localValue'] ?? null;
        }
        foreach (array_keys($values) as $field) {
            $this->assertWritableField($plan, $field);
        }

        $plan['values'] = $values;
        $plan['localPayload'] = [
            'operationType' => 'Create',
            'values' => $values,
            'tombstone' => null,
        ];
        $plan['basePayload'] = ['values' => new \stdClass(), 'baseVersion' => null];
        $plan['remotePayload'] = ['values' => new \stdClass(), 'baseVersion' => null];

        return $plan;
    }

    private function prepareExisting(array $plan, array $operation): array
    {
        $canonicalId = $operation['canonicalId'] ?? null;
        if (!is_string($canonicalId) || $canonicalId === '') {
            return $this->asConflict($plan, 'SourceIdentityMismatch');
        }

        $plan['canonicalId'] = $canonicalId;
        $row = $this->lockRow($plan['table'], $canonicalId, true);
        if ($row === null) {
            return $this->asConflict($plan, 'SourceIdentityMismatch');
        }

        $plan['current'] = $row;
        $plan['rowVersion'] = $this->rowVersion($row);
        $plan['currentVersion'] = $this->currentVersion($plan['table'], $canonicalId, $row);

        if (array_key_exists('client_uuid', $row)
            && $row['client_uuid'] !== null
            && !hash_equals((string) $row['client_uuid'], $plan['clientUuid'])) {
            return $this->asConflict($plan, 'SourceIdentityMismatch');
        }

        if ($this->schema->hasColumn($plan['table'], 'client_uuid')) {
            $mapped = DB::table($plan['table'])
                ->where('client_uuid', $plan['clientUuid'])
                ->lockForUpdate()
                ->first();
            if ($mapped !== null && $this->encodeRowPrimaryKey($plan['table'], (array) $mapped) !== $canonicalId) {
                return $this->asConflict($plan, 'SourceIdentityMismatch');
            }
        }

        $baseValues = [];
        $localValues = [];
        $remoteValues = [];
        $mergedValues = [];
        $fields = [];
        $hasRemoteChange = false;
        $hasCriticalConflict = false;
        $hasConcurrentConflict = false;

        foreach ($operation['fieldPatches'] ?? $operation['patches'] ?? [] as $patch) {
            $field = $this->patchField($plan, $patch);
            $criticality = $this->criticality($plan['entityType'], $field);
            if ($criticality === 'ServerOwned') {
                throw new InvalidArgumentException('Server-owned fields cannot be patched by a client.');
            }

            $base = $patch['baseValue'] ?? null;
            $local = $patch['localValue'] ?? null;
            $remote = $row[$field] ?? null;
            $localChanged = !$this->deepEquals($base, $local);
            $remoteChanged = !$this->deepEquals($base, $remote);
            $equivalent = $this->deepEquals($local, $remote);
            $class = 'None';

            if ($localChanged && $remoteChanged && !$equivalent) {
                $class = $criticality === 'Mergeable' ? 'ConcurrentField' : 'CriticalField';
                $hasCriticalConflict ||= $class === 'CriticalField';
                $hasConcurrentConflict ||= $class === 'ConcurrentField';
            } elseif ($localChanged && !$remoteChanged) {
                $class = 'LocalOnly';
                $mergedValues[$field] = $local;
            } elseif ($localChanged && $equivalent) {
                $class = 'Equivalent';
            } elseif ($remoteChanged) {
                $class = 'RemoteOnly';
            }

            $hasRemoteChange ||= $remoteChanged;
            $baseValues[$field] = $base;
            $remoteValues[$field] = $remote;
            if ($localChanged) {
                $localValues[$field] = $local;
            }
            $fields[] = [
                'fieldName' => $field,
                'baseValue' => $base,
                'localValue' => $local,
                'remoteValue' => $remote,
                'criticality' => $criticality,
                'conflictClass' => $class,
            ];
        }

        $plan['basePayload'] = [
            'values' => $baseValues,
            'baseVersion' => $operation['baseVersion'] ?? null,
            'rowVersion' => $operation['rowVersion'] ?? null,
        ];
        $plan['localPayload'] = [
            'operationType' => $plan['operationType'],
            'values' => $localValues,
            'tombstone' => $operation['tombstone'] ?? null,
        ];
        $plan['remotePayload'] = [
            'values' => $remoteValues,
            'baseVersion' => $plan['currentVersion'],
            'rowVersion' => $plan['rowVersion'],
        ];
        $plan['conflictingFields'] = $fields;
        $plan['values'] = $mergedValues;
        $plan['tombstone'] = $operation['tombstone'] ?? null;

        $baseVersion = $operation['baseVersion'] ?? null;
        $stale = (!is_int($baseVersion) && !ctype_digit((string) $baseVersion))
            || (int) $baseVersion !== $plan['currentVersion'];

        if ($plan['operationType'] === 'Delete' && ($stale || $hasRemoteChange)) {
            return $this->asConflict($plan, 'DeleteAgainstUpdate', $fields);
        }
        if ($stale) {
            return $this->asConflict($plan, 'VersionMismatch', $fields);
        }
        if ($hasCriticalConflict) {
            return $this->asConflict($plan, 'CriticalField', $fields);
        }
        if ($hasConcurrentConflict) {
            return $this->asConflict($plan, 'ConcurrentField', $fields);
        }

        if (array_key_exists('client_uuid', $row) && $row['client_uuid'] === null) {
            $plan['values']['client_uuid'] = $plan['clientUuid'];
        }

        return $plan;
    }

    private function basePlan(object $device, array $operation, string $entityType, string $operationType): array
    {
        return [
            'status' => 'apply',
            'deviceId' => $device->device_id,
            'sourceId' => $device->source_id,
            'entityType' => $entityType,
            'table' => $this->tableFor($entityType),
            'operationId' => (string) ($operation['operationId'] ?? ''),
            'operationType' => $operationType,
            'clientUuid' => (string) ($operation['clientUuid'] ?? ''),
            'canonicalId' => isset($operation['canonicalId']) ? (string) $operation['canonicalId'] : null,
            'current' => null,
            'currentVersion' => 0,
            'rowVersion' => null,
            'values' => [],
            'forceRevision' => false,
            'tombstone' => $operation['tombstone'] ?? null,
            'conflictType' => null,
            'basePayload' => ['values' => new \stdClass()],
            'localPayload' => ['operationType' => $operationType, 'values' => new \stdClass()],
            'remotePayload' => ['values' => new \stdClass()],
            'conflictingFields' => [],
        ];
    }

    private function asConflict(array $plan, string $type, array $fields = []): array
    {
        $plan['status'] = 'conflict';
        $plan['conflictType'] = $type;
        $plan['values'] = [];
        if ($fields !== []) {
            $plan['conflictingFields'] = $fields;
        }

        return $plan;
    }

    private function appliedResult(array $plan, string $canonicalId, ?array $row): array
    {
        $rowVersion = $row === null
            ? $this->nextRowVersion($plan['rowVersion'])
            : $this->rowVersion($row);
        $baseVersion = $row === null
            ? $plan['currentVersion'] + 1
            : $this->currentVersion($plan['table'], $canonicalId, $row);

        return [
            'operationId' => $plan['operationId'],
            'status' => 'applied',
            'clientUuid' => $plan['clientUuid'],
            'canonicalId' => $canonicalId,
            'rowVersion' => $rowVersion,
            'baseVersion' => $baseVersion,
            'conflict' => null,
        ];
    }

    private function withRevision(array $plan, array $values, $now): array
    {
        if ($this->schema->hasColumn($plan['table'], 'row_version')) {
            $values['row_version'] = $this->nextRowVersion($plan['rowVersion']);
        }
        if ($this->schema->hasColumn($plan['table'], 'updated_at')) {
            $values['updated_at'] = $now;
        }
        if ($this->schema->hasColumn($plan['table'], 'sync_origin_device_id')) {
            $values['sync_origin_device_id'] = $plan['deviceId'];
        }

        return $values;
    }

    private function nextRowVersion(?string $current): string
    {
        return $current !== null && ctype_digit($current)
            ? (string) ((int) $current + 1)
            : (string) Str::uuid();
    }

    private function currentVersion(string $table, string $canonicalId, array $row): int
    {
        if (isset($row['row_version']) && ctype_digit((string) $row['row_version'])) {
            return (int) $row['row_version'];
        }

        $id = DB::table('desktop_change_log_v1')
            ->where('table_name', $table)
            ->where('primary_key', $canonicalId)
            ->max('id');

        return $id === null ? 0 : (int) $id;
    }

    private function rowVersion(array $row): string
    {
        if (array_key_exists('row_version', $row) && $row['row_version'] !== null) {
            return (string) $row['row_version'];
        }
        if (array_key_exists('updated_at', $row) && $row['updated_at'] !== null) {
            return (string) $row['updated_at'];
        }

        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function lockRow(string $table, string $canonicalId, bool $withTrashed = false): ?array
    {
        $query = $this->keysQuery($table, $canonicalId)->lockForUpdate();
        if (!$withTrashed && $this->schema->hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $row = $query->first();

        return $row === null ? null : (array) $row;
    }

    private function keysQuery(string $table, string $canonicalId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table);
        foreach ($this->primaryKeyValues($table, $canonicalId) as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    private function primaryKeyValues(string $table, string $canonicalId): array
    {
        $primaryKey = $this->schema->primaryKeyColumns($table);
        if (count($primaryKey) === 1) {
            return [$primaryKey[0] => $canonicalId];
        }

        $values = json_decode($canonicalId, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($values)) {
            throw new InvalidArgumentException('The canonical identity is invalid.');
        }

        $result = [];
        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $values)) {
                throw new InvalidArgumentException('The canonical identity is incomplete.');
            }
            $result[$column] = $values[$column];
        }

        return $result;
    }

    private function encodeRowPrimaryKey(string $table, array $row): string
    {
        return $this->encodePrimaryKey($this->schema->primaryKeyColumns($table), $row);
    }

    private function encodePrimaryKey(array $primaryKey, array $row): string
    {
        if (count($primaryKey) === 1) {
            if (!array_key_exists($primaryKey[0], $row)) {
                throw new RuntimeException('The inserted row has no canonical identity.');
            }

            return (string) $row[$primaryKey[0]];
        }

        $values = [];
        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException('The composite canonical identity is incomplete.');
            }
            $values[$column] = $row[$column];
        }

        return json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function patchField(array $plan, array $patch): string
    {
        $field = $patch['fieldName'] ?? null;
        if (!is_string($field) || $field === '') {
            throw new InvalidArgumentException('A field patch has no field name.');
        }
        $this->assertWritableField($plan, $field);

        return $field;
    }

    private function assertWritableField(array $plan, string $field): void
    {
        $fields = $this->tableFields()[$plan['table']] ?? [];
        if (!in_array($field, $fields, true) || !$this->schema->hasColumn($plan['table'], $field)) {
            throw new InvalidArgumentException('The patched field is not writable through desktop sync.');
        }
        if ($field === 'client_uuid' || in_array($field, $this->policy()['fields']['serverOwnedFieldNames'], true)) {
            throw new InvalidArgumentException('Identity and server-owned fields cannot be patched by a client.');
        }
    }

    private function criticality(string $entityType, string $field): string
    {
        $policy = $this->policy()['fields'];
        if (in_array($field, $policy['serverOwnedFieldNames'], true)) {
            return 'ServerOwned';
        }

        $definition = null;
        foreach ($this->fieldCatalog()['entities'] ?? [] as $entity) {
            if (($entity['entityId'] ?? null) !== $entityType) {
                continue;
            }
            foreach ($entity['fields'] ?? [] as $candidate) {
                if (($candidate['name'] ?? null) === $field) {
                    $definition = $candidate;
                    break 2;
                }
            }
        }

        if (in_array($definition['identityRole'] ?? null, $policy['criticalIdentityRoles'], true)
            || in_array($field, $policy['criticalFieldNames'], true)) {
            return 'Critical';
        }
        foreach ($policy['criticalFieldSuffixes'] as $suffix) {
            if (str_ends_with($field, $suffix)) {
                return 'Critical';
            }
        }
        if (($definition['reference'] ?? null) !== null) {
            return 'Reference';
        }
        if (in_array($field, $policy['mergeableFieldNames'], true)) {
            return 'Mergeable';
        }

        return 'Critical';
    }

    private function referenceConflict(array $references): ?string
    {
        foreach ($references as $reference) {
            if (($reference['isDeleted'] ?? false) === true) {
                return 'DeletedReference';
            }
        }
        foreach ($references as $reference) {
            if (!is_string($reference['targetCanonicalId'] ?? null) || $reference['targetCanonicalId'] === '') {
                return 'UnresolvedReference';
            }
        }

        return null;
    }

    private function deepEquals(mixed $first, mixed $second): bool
    {
        return json_encode($first, JSON_THROW_ON_ERROR) === json_encode($second, JSON_THROW_ON_ERROR);
    }

    private function ensureWritableTable(string $table): void
    {
        if (!$this->schema->hasTable($table)
            || $this->schema->primaryKeyColumns($table) === []
            || ($this->tableFields()[$table] ?? []) === []) {
            throw new RuntimeException('The requested desktop sync entity is unavailable.');
        }
    }

    private function tableFor(string $entityType): string
    {
        if ($this->entityTables === null) {
            $configured = config('desktop_sync.entity_tables');
            if (is_array($configured)) {
                $this->entityTables = $configured;
            } else {
                $this->entityTables = [];
                foreach ($this->ownership()['entities'] ?? [] as $entity) {
                    if (($entity['kind'] ?? null) === 'physical-table'
                        && ($entity['owner'] ?? null) === 'Laravel'
                        && ($entity['exposure'] ?? null) === 'bidirectional-sync') {
                        $this->entityTables[$entity['id']] = $entity['sourceOfTruth']['object'];
                    }
                }
            }
        }

        if (!isset($this->entityTables[$entityType])) {
            throw new InvalidArgumentException('The desktop entity is not bidirectionally synchronized.');
        }

        return $this->entityTables[$entityType];
    }

    private function tableFields(): array
    {
        if ($this->tableFields !== null) {
            return $this->tableFields;
        }

        $configured = config('desktop_sync.table_fields');
        if (is_array($configured)) {
            return $this->tableFields = $configured;
        }

        $allowlist = [];
        foreach ($this->fieldCatalog()['entities'] ?? [] as $fieldEntity) {
            $entityType = $fieldEntity['entityId'] ?? null;
            if (!is_string($entityType)) {
                continue;
            }

            try {
                $table = $this->tableFor($entityType);
            } catch (InvalidArgumentException) {
                continue;
            }

            $names = [];
            foreach ($fieldEntity['fields'] ?? [] as $field) {
                if (($field['owner'] ?? null) === 'Laravel' && is_string($field['name'] ?? null)) {
                    $names[] = $field['name'];
                }
            }
            $names = array_merge($names, $this->schema->primaryKeyColumns($table));
            foreach (['client_uuid', 'row_version', 'updated_at', 'created_at', 'deleted_at', 'sync_origin_device_id'] as $column) {
                if ($this->schema->hasColumn($table, $column)) {
                    $names[] = $column;
                }
            }
            $allowlist[$table] = array_values(array_unique($names));
        }

        return $this->tableFields = $allowlist;
    }

    private function policy(): array
    {
        return $this->policy ??= $this->readCatalog('DesktopApp/spec/domain/sync-policy.json');
    }

    private function ownership(): array
    {
        return $this->ownership ??= $this->readCatalog('DesktopApp/spec/domain/entity-ownership.json');
    }

    private function fieldCatalog(): array
    {
        return $this->fieldCatalog ??= $this->readCatalog('DesktopApp/spec/domain/field-catalog.json');
    }

    private function readCatalog(string $path): array
    {
        $contents = file_get_contents(base_path($path));
        if ($contents === false) {
            throw new RuntimeException('The desktop sync metadata catalog is unavailable.');
        }

        try {
            $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The desktop sync metadata catalog is invalid.', 0, $exception);
        }

        if (!is_array($catalog)) {
            throw new RuntimeException('The desktop sync metadata catalog is invalid.');
        }

        return $catalog;
    }

    private function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function deviceFromConflict(object $conflict): object
    {
        return (object) [
            'device_id' => $conflict->device_id,
            'source_id' => $conflict->source_id,
            'user_id' => $conflict->resolved_by_user_id,
        ];
    }
}
