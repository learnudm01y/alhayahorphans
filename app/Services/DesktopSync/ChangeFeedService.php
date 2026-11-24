<?php

namespace App\Services\DesktopSync;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class ChangeFeedService
{
    private const LOG_TABLE = 'desktop_change_log_v1';

    private const TOMBSTONE_TABLE = 'desktop_tombstones_v1';

    private const TOKEN_VERSION = 1;

    private ?array $tableFields = null;

    private array $availableFields = [];

    private array $primaryKeys = [];

    public function __construct(
        private readonly SchemaGuard $schema,
        private readonly ChangeLogRetentionService $retention
    ) {
    }

    public function pull(string $sourceId, ?string $cursor = null, ?int $limit = null): array
    {
        $sourceId = $this->normalizeSourceId($sourceId);

        if (!$this->hasFeedSchema()) {
            return $this->pullReconciliationRequired($sourceId, 'missing_schema');
        }

        if ($cursor === null) {
            return $this->pullReconciliationRequired($sourceId, 'initial_cursor');
        }

        try {
            $position = $this->decodeCursor($cursor, $sourceId);
        } catch (InvalidArgumentException) {
            return $this->pullReconciliationRequired($sourceId, 'invalid_cursor');
        }

        if ($position === 0) {
            $oldest = DB::table(self::LOG_TABLE)->min('id');
            if ($oldest !== null && (int) $oldest > 1) {
                return $this->pullReconciliationRequired($sourceId, 'cursor_expired');
            }
            $cursorState = ['requires_full_reconciliation' => false, 'reason' => null];
        } else {
            $cursorState = $this->retention->inspectCursor($position);
        }

        if ($cursorState['requires_full_reconciliation']) {
            return $this->pullReconciliationRequired($sourceId, $cursorState['reason']);
        }

        $pageSize = $this->pageSize($limit, 'pull');
        $tables = array_keys($this->tableFields());

        if ($tables === []) {
            return $this->pullPayload($sourceId, [], false, $this->encodeCursor($sourceId, $position));
        }

        $rows = DB::table(self::LOG_TABLE)
            ->where('id', '>', $position)
            ->whereIn('table_name', $tables)
            ->orderBy('id')
            ->limit($pageSize + 1)
            ->get();
        $hasMore = $rows->count() > $pageSize;
        $pageRows = $rows->take($pageSize)->values();
        $last = $pageRows->last();
        $nextPosition = $last === null ? $position : (int) $last->id;

        return $this->pullPayload(
            $sourceId,
            $this->hydrateChanges($pageRows->all()),
            $hasMore,
            $this->encodeCursor($sourceId, $nextPosition)
        );
    }

    public function reconcile(
        string $sourceId,
        ?string $snapshotId = null,
        ?string $pageCursor = null,
        ?int $limit = null
    ): array {
        $sourceId = $this->normalizeSourceId($sourceId);

        if (!$this->schema->hasColumns(self::LOG_TABLE, ['id'])) {
            return $this->snapshotError($sourceId, 'missing_schema');
        }

        try {
            if ($snapshotId === null) {
                if ($pageCursor !== null) {
                    throw new InvalidArgumentException('A page cursor requires a snapshot.');
                }

                $snapshot = [
                    'type' => 'snapshot',
                    'v' => self::TOKEN_VERSION,
                    'id' => (string) Str::uuid(),
                    'source_id' => $sourceId,
                    'change_log_id' => $this->newestChangeLogId(),
                ];
                $snapshotId = $this->encodeToken($snapshot);
                $position = null;
            } else {
                $snapshot = $this->decodeSnapshot($snapshotId, $sourceId);
                $position = $pageCursor === null
                    ? null
                    : $this->decodePageCursor($pageCursor, $snapshot['id']);
            }

            $page = $this->readSnapshotPage(
                $position,
                $this->pageSize($limit, 'reconciliation'),
                $snapshot['id']
            );
        } catch (InvalidArgumentException) {
            return $this->snapshotError($sourceId, 'invalid_snapshot');
        }

        $highWatermark = (int) $snapshot['change_log_id'];

        return array_merge($this->basePayload($sourceId), [
            'snapshot_id' => $snapshotId,
            'records' => $page['records'],
            'requires_full_reconciliation' => false,
            'reason' => null,
            'has_more' => $page['has_more'],
            'next_page_cursor' => $page['next_page_cursor'],
            'next_cursor' => $this->encodeCursor($sourceId, $highWatermark),
            'change_range' => [
                'after_change_log_id' => $highWatermark,
                'through_change_log_id' => $this->newestChangeLogId(),
            ],
        ]);
    }

    private function hydrateChanges(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (object $row): int => (int) $row->id, $rows);
        $tombstones = DB::table(self::TOMBSTONE_TABLE)
            ->whereIn('change_log_id', $ids)
            ->get()
            ->keyBy('change_log_id');
        $changes = [];

        foreach ($rows as $row) {
            $operation = strtolower((string) $row->operation);
            $tombstone = $tombstones->get($row->id);

            if ($operation === 'delete' && $tombstone === null) {
                throw new RuntimeException('A delete change is missing its tombstone.');
            }

            $changes[] = [
                'change_log_id' => (int) $row->id,
                'table_name' => $row->table_name,
                'primary_key' => $row->primary_key,
                'operation' => $operation,
                'client_uuid' => $row->client_uuid,
                'row_version' => $row->row_version,
                'row_version_source' => $row->row_version_source,
                'soft_delete' => (bool) $row->soft_delete,
                'changed_at' => $this->iso8601($row->changed_at),
                'data' => $operation === 'delete'
                    ? null
                    : $this->readRow($row->table_name, $row->primary_key),
                'tombstone' => $tombstone === null ? null : [
                    'table_name' => $tombstone->table_name,
                    'primary_key' => $tombstone->primary_key,
                    'client_uuid' => $tombstone->client_uuid,
                    'row_version' => $tombstone->row_version,
                    'deleted_at' => $this->iso8601($tombstone->deleted_at),
                    'deleted_by_device_id' => $tombstone->deleted_by_device_id,
                ],
            ];
        }

        return $changes;
    }

    private function readRow(string $table, string $primaryKey): ?array
    {
        $fields = $this->availableFields($table);
        $keys = $this->primaryKeyValues($table, $primaryKey);

        if ($fields === []) {
            throw new RuntimeException('An allowlisted table has no readable fields.');
        }

        $query = DB::table($table)->select($fields);
        foreach ($keys as $column => $value) {
            $query->where($column, $value);
        }

        $row = $query->first();

        return $row === null ? null : (array) $row;
    }

    private function readSnapshotPage(?array $position, int $pageSize, string $snapshotId): array
    {
        $tables = $this->availableSnapshotTables();
        $start = 0;

        if ($position !== null) {
            $start = array_search($position['table'], $tables, true);
            if ($start === false) {
                throw new InvalidArgumentException('The snapshot page table is unavailable.');
            }
        }

        $records = [];
        $lastRow = null;
        $lastTable = null;

        for ($index = $start; $index < count($tables); $index++) {
            $table = $tables[$index];
            $fields = $this->availableFields($table);
            $primaryKey = $this->primaryKeyColumns($table);
            $remaining = $pageSize - count($records);

            if ($remaining < 1) {
                break;
            }

            $query = DB::table($table)->select($fields);
            if ($this->schema->hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($position !== null && $index === $start) {
                $this->applyKeyset($query, $primaryKey, $position['keys']);
            }

            foreach ($primaryKey as $column) {
                $query->orderBy($column);
            }

            $rows = $query->limit($remaining + 1)->get();
            $hasExtra = $rows->count() > $remaining;
            $pageRows = $rows->take($remaining)->values();

            foreach ($pageRows as $row) {
                $data = (array) $row;
                $records[] = [
                    'table_name' => $table,
                    'primary_key' => $this->encodePrimaryKey($primaryKey, $data),
                    'client_uuid' => $data['client_uuid'] ?? null,
                    'data' => $data,
                ];
            }

            if ($pageRows->isNotEmpty()) {
                $lastRow = $pageRows->last();
                $lastTable = $table;
            }

            if ($hasExtra) {
                return [
                    'records' => $records,
                    'has_more' => true,
                    'next_page_cursor' => $this->nextPageCursor($snapshotId, $lastTable, $primaryKey, (array) $lastRow),
                ];
            }

            if (count($records) === $pageSize) {
                $hasMore = $this->hasSnapshotRows(array_slice($tables, $index + 1));

                return [
                    'records' => $records,
                    'has_more' => $hasMore,
                    'next_page_cursor' => $hasMore
                        ? $this->nextPageCursor($snapshotId, $lastTable, $primaryKey, (array) $lastRow)
                        : null,
                ];
            }
        }

        return ['records' => $records, 'has_more' => false, 'next_page_cursor' => null];
    }

    private function nextPageCursor(string $snapshotId, string $table, array $primaryKey, array $row): string
    {
        $keys = [];
        foreach ($primaryKey as $column) {
            $keys[$column] = $row[$column];
        }

        return $this->encodeToken([
            'type' => 'snapshot-page',
            'v' => self::TOKEN_VERSION,
            'snapshot_id' => $snapshotId,
            'table' => $table,
            'keys' => $keys,
        ]);
    }

    private function hasSnapshotRows(array $tables): bool
    {
        foreach ($tables as $table) {
            $query = DB::table($table);
            if ($this->schema->hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function applyKeyset(Builder $query, array $primaryKey, array $values): void
    {
        if (count($values) !== count($primaryKey)) {
            throw new InvalidArgumentException('The snapshot keyset is incomplete.');
        }

        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $values)) {
                throw new InvalidArgumentException('The snapshot keyset is invalid.');
            }
        }

        $query->where(function (Builder $outer) use ($primaryKey, $values): void {
            foreach ($primaryKey as $index => $column) {
                $outer->orWhere(function (Builder $part) use ($primaryKey, $values, $index, $column): void {
                    for ($previous = 0; $previous < $index; $previous++) {
                        $key = $primaryKey[$previous];
                        $part->where($key, $values[$key]);
                    }

                    $part->where($column, '>', $values[$column]);
                });
            }
        });
    }

    private function availableSnapshotTables(): array
    {
        $tables = [];

        foreach (array_keys($this->tableFields()) as $table) {
            $primaryKey = $this->primaryKeyColumns($table);
            $fields = $this->availableFields($table);

            if ($primaryKey !== [] && $fields !== [] && array_diff($primaryKey, $fields) === []) {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
    }

    private function availableFields(string $table): array
    {
        if (array_key_exists($table, $this->availableFields)) {
            return $this->availableFields[$table];
        }

        $allowlist = $this->tableFields();
        if (!isset($allowlist[$table]) || !$this->schema->hasTable($table)) {
            return $this->availableFields[$table] = [];
        }

        $fields = [];
        foreach ($allowlist[$table] as $field) {
            if ($this->schema->hasColumn($table, $field)) {
                $fields[] = $field;
            }
        }

        return $this->availableFields[$table] = $fields;
    }

    private function primaryKeyColumns(string $table): array
    {
        if (array_key_exists($table, $this->primaryKeys)) {
            return $this->primaryKeys[$table];
        }

        return $this->primaryKeys[$table] = $this->schema->primaryKeyColumns($table);
    }

    private function primaryKeyValues(string $table, string $encoded): array
    {
        $primaryKey = $this->primaryKeyColumns($table);
        if ($primaryKey === []) {
            throw new RuntimeException('An allowlisted table has no usable primary key.');
        }

        if (count($primaryKey) === 1) {
            return [$primaryKey[0] => $encoded];
        }

        try {
            $values = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('A composite primary key is invalid.', 0, $exception);
        }

        if (!is_array($values)) {
            throw new RuntimeException('A composite primary key is invalid.');
        }

        $keys = [];
        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $values)) {
                throw new RuntimeException('A composite primary key is incomplete.');
            }
            $keys[$column] = $values[$column];
        }

        return $keys;
    }

    private function encodePrimaryKey(array $primaryKey, array $row): string
    {
        if (count($primaryKey) === 1) {
            return (string) $row[$primaryKey[0]];
        }

        $values = [];
        foreach ($primaryKey as $column) {
            $values[$column] = $row[$column];
        }

        return json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function tableFields(): array
    {
        if ($this->tableFields !== null) {
            return $this->tableFields;
        }

        $configured = config('desktop_sync.table_fields');
        if (is_array($configured)) {
            return $this->tableFields = $this->normalizeTableFields($configured);
        }

        $ownership = $this->readCatalog('DesktopApp/spec/domain/entity-ownership.json');
        $fields = $this->readCatalog('DesktopApp/spec/domain/field-catalog.json');
        $entities = [];

        foreach ($ownership['entities'] ?? [] as $entity) {
            if (($entity['kind'] ?? null) === 'physical-table'
                && ($entity['owner'] ?? null) === 'Laravel'
                && in_array($entity['exposure'] ?? null, ['read-only-projection', 'bidirectional-sync'], true)) {
                $entities[$entity['id']] = $entity;
            }
        }

        $allowlist = [];
        foreach ($fields['entities'] ?? [] as $fieldEntity) {
            $id = $fieldEntity['entityId'] ?? null;
            if (!is_string($id) || !isset($entities[$id])) {
                continue;
            }

            $entity = $entities[$id];
            $table = $entity['sourceOfTruth']['object'];
            $names = [];
            foreach ($fieldEntity['fields'] ?? [] as $field) {
                if (($field['owner'] ?? null) === 'Laravel' && is_string($field['name'] ?? null)) {
                    $names[] = $field['name'];
                }
            }

            $names = array_merge($names, $entity['identity']['primaryKey'] ?? []);
            if (is_string($entity['identity']['syncKey'] ?? null)) {
                $names[] = $entity['identity']['syncKey'];
            }
            if (is_string($entity['deletion']['field'] ?? null)) {
                $names[] = $entity['deletion']['field'];
            }

            $allowlist[$table] = array_values(array_unique($names));
        }

        return $this->tableFields = $this->normalizeTableFields($allowlist);
    }

    private function normalizeTableFields(array $configured): array
    {
        $allowlist = [];
        foreach ($configured as $table => $fields) {
            if (!is_string($table) || !is_array($fields)) {
                continue;
            }

            $allowlist[$table] = array_values(array_unique(array_filter(
                $fields,
                static fn (mixed $field): bool => is_string($field) && $field !== ''
            )));
        }

        ksort($allowlist);

        return $allowlist;
    }

    private function readCatalog(string $path): array
    {
        $absolute = base_path($path);
        if (!is_file($absolute)) {
            throw new RuntimeException('The desktop sync metadata catalog is unavailable.');
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            throw new RuntimeException('The desktop sync metadata catalog is unreadable.');
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

    private function decodeCursor(string $cursor, string $sourceId): int
    {
        $payload = $this->decodeToken($cursor, 'change');
        if (!hash_equals($sourceId, strtolower((string) ($payload['source_id'] ?? '')))
            || !is_int($payload['change_log_id'] ?? null)
            || $payload['change_log_id'] < 0) {
            throw new InvalidArgumentException('The cursor is invalid.');
        }

        return $payload['change_log_id'];
    }

    private function decodeSnapshot(string $snapshotId, string $sourceId): array
    {
        $payload = $this->decodeToken($snapshotId, 'snapshot');
        if (!is_string($payload['id'] ?? null)
            || $payload['id'] === ''
            || !hash_equals($sourceId, strtolower((string) ($payload['source_id'] ?? '')))
            || !is_int($payload['change_log_id'] ?? null)
            || $payload['change_log_id'] < 0) {
            throw new InvalidArgumentException('The snapshot is invalid.');
        }

        return $payload;
    }

    private function decodePageCursor(string $cursor, string $snapshotId): array
    {
        $payload = $this->decodeToken($cursor, 'snapshot-page');
        if (!hash_equals($snapshotId, (string) ($payload['snapshot_id'] ?? ''))
            || !is_string($payload['table'] ?? null)
            || !is_array($payload['keys'] ?? null)) {
            throw new InvalidArgumentException('The snapshot page cursor is invalid.');
        }

        return ['table' => $payload['table'], 'keys' => $payload['keys']];
    }

    private function decodeToken(string $token, string $type): array
    {
        if ($token === '') {
            throw new InvalidArgumentException('The token is empty.');
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('The token encoding is invalid.');
        }

        try {
            $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The token payload is invalid.', 0, $exception);
        }

        if (!is_array($payload)
            || ($payload['type'] ?? null) !== $type
            || ($payload['v'] ?? null) !== self::TOKEN_VERSION) {
            throw new InvalidArgumentException('The token contract is invalid.');
        }

        return $payload;
    }

    private function encodeCursor(string $sourceId, int $changeLogId): string
    {
        return $this->encodeToken([
            'type' => 'change',
            'v' => self::TOKEN_VERSION,
            'source_id' => $sourceId,
            'change_log_id' => $changeLogId,
        ]);
    }

    private function encodeToken(array $payload): string
    {
        return base64_encode(json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    private function hasFeedSchema(): bool
    {
        return $this->schema->hasColumns(self::LOG_TABLE, [
            'id',
            'table_name',
            'primary_key',
            'operation',
            'client_uuid',
            'row_version',
            'row_version_source',
            'soft_delete',
            'changed_at',
        ]) && $this->schema->hasColumns(self::TOMBSTONE_TABLE, [
            'change_log_id',
            'table_name',
            'primary_key',
            'client_uuid',
            'row_version',
            'deleted_at',
            'deleted_by_device_id',
        ]);
    }

    private function newestChangeLogId(): int
    {
        $id = DB::table(self::LOG_TABLE)->max('id');

        return $id === null ? 0 : (int) $id;
    }

    private function pageSize(?int $requested, string $channel): int
    {
        $maximum = max(1, min(1000, (int) config(
            'desktop_sync.'.$channel.'_page_max',
            $channel === 'pull' ? 500 : 200
        )));
        $default = max(1, min($maximum, (int) config('desktop_sync.'.$channel.'_page_size', 200)));

        return max(1, min($maximum, $requested ?? $default));
    }

    private function pullPayload(string $sourceId, array $changes, bool $hasMore, string $nextCursor): array
    {
        return array_merge($this->basePayload($sourceId), [
            'changes' => $changes,
            'requires_full_reconciliation' => false,
            'reason' => null,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ]);
    }

    private function pullReconciliationRequired(string $sourceId, string $reason): array
    {
        return array_merge($this->basePayload($sourceId), [
            'changes' => [],
            'requires_full_reconciliation' => true,
            'reason' => $reason,
            'has_more' => false,
            'next_cursor' => null,
        ]);
    }

    private function snapshotError(string $sourceId, string $reason): array
    {
        return array_merge($this->basePayload($sourceId), [
            'snapshot_id' => null,
            'records' => [],
            'requires_full_reconciliation' => true,
            'reason' => $reason,
            'has_more' => false,
            'next_page_cursor' => null,
            'next_cursor' => null,
            'change_range' => null,
        ]);
    }

    private function basePayload(string $sourceId): array
    {
        return [
            'sourceId' => $sourceId,
            'server_time' => now('UTC')->toISOString(),
        ];
    }

    private function normalizeSourceId(string $sourceId): string
    {
        return strtolower(trim($sourceId));
    }

    private function iso8601(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->toISOString();
    }
}
