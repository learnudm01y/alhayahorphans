<?php

namespace App\Services\DesktopSync;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ChangeLogWriter
{
    private const LOG_TABLE = 'desktop_change_log_v1';

    private const TOMBSTONE_TABLE = 'desktop_tombstones_v1';

    public function __construct(private readonly SchemaGuard $schema)
    {
    }

    public function installTriggersFor(string $table): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Desktop change capture requires MySQL database triggers.');
        }

        if (!$this->schema->hasTable($table) || !$this->hasLogSchema() || !$this->hasTombstoneSchema()) {
            return false;
        }

        $primaryKey = $this->schema->primaryKeyColumns($table);
        $columns = $this->schema->columns($table);

        if ($primaryKey === [] || $columns === []) {
            return false;
        }

        try {
            foreach (['insert', 'update', 'delete'] as $event) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$this->quoteIdentifier($this->triggerName($table, $event)));
                DB::unprepared($this->triggerDefinition($table, $event, $primaryKey, $columns));
            }
        } catch (Throwable $exception) {
            $this->removeTriggersFor($table);
            throw $exception;
        }

        return true;
    }

    public function removeTriggersFor(string $table): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || !$this->schema->hasTable($table)) {
            return;
        }

        foreach (['insert', 'update', 'delete'] as $event) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.$this->quoteIdentifier($this->triggerName($table, $event)));
        }
    }

    private function triggerDefinition(
        string $table,
        string $event,
        array $primaryKey,
        array $columns
    ): string {
        $alias = $event === 'delete' ? 'OLD' : 'NEW';
        $hasDeletedAt = $this->schema->hasColumn($table, 'deleted_at');
        $body = '';

        if ($event === 'update' && $hasDeletedAt) {
            $body = 'IF OLD.`deleted_at` IS NULL AND NEW.`deleted_at` IS NOT NULL THEN '
                .$this->insertChange($table, 'update', 'delete', $primaryKey, $columns, '1')
                .' '.$this->insertTombstone($table, 'NEW', $primaryKey, $columns)
                .' ELSE '
                .$this->insertChange($table, $event, 'update', $primaryKey, $columns, 'IF(NEW.`deleted_at` IS NULL, 0, 1)')
                .' END IF;';
        } else {
            $softDelete = $event === 'insert' && $hasDeletedAt
                ? 'IF(NEW.`deleted_at` IS NULL, 0, 1)'
                : '0';
            $body = $this->insertChange($table, $event, $event, $primaryKey, $columns, $softDelete);

            if ($event === 'delete') {
                $body .= ' '.$this->insertTombstone($table, $alias, $primaryKey, $columns);
            }
        }

        return 'CREATE TRIGGER '.$this->quoteIdentifier($this->triggerName($table, $event))
            .' AFTER '.strtoupper($event).' ON '.$this->quoteIdentifier($table)
            .' FOR EACH ROW BEGIN '.$body.' END';
    }

    private function insertChange(
        string $table,
        string $event,
        string $operation,
        array $primaryKey,
        array $columns,
        string $softDelete
    ): string {
        $alias = $event === 'delete' ? 'OLD' : 'NEW';
        [$rowVersion, $versionSource] = $this->rowVersion($table, $alias, $columns);

        return 'INSERT INTO '.$this->quoteIdentifier(self::LOG_TABLE)
            .' (`table_name`, `primary_key`, `operation`, `client_uuid`, `row_version`, `row_version_source`, `soft_delete`, `changed_at`, `created_at`) VALUES ('
            .$this->stringLiteral($table).', '
            .$this->primaryKeyExpression($alias, $primaryKey).', '
            .$this->stringLiteral($operation).', '
            .$this->optionalColumnExpression($table, $alias, 'client_uuid').', '
            .$rowVersion.', '
            .$versionSource.', '
            .$softDelete.', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));';
    }

    private function insertTombstone(string $table, string $alias, array $primaryKey, array $columns): string
    {
        [$rowVersion] = $this->rowVersion($table, $alias, $columns);
        $deletedAt = $this->schema->hasColumn($table, 'deleted_at')
            ? 'COALESCE('.$alias.'.`deleted_at`, UTC_TIMESTAMP(6))'
            : 'UTC_TIMESTAMP(6)';

        return 'INSERT INTO '.$this->quoteIdentifier(self::TOMBSTONE_TABLE)
            .' (`change_log_id`, `table_name`, `primary_key`, `client_uuid`, `row_version`, `deleted_at`, `deleted_by_device_id`, `created_at`) VALUES (LAST_INSERT_ID(), '
            .$this->stringLiteral($table).', '
            .$this->primaryKeyExpression($alias, $primaryKey).', '
            .$this->optionalColumnExpression($table, $alias, 'client_uuid').', '
            .$rowVersion.', '
            .$deletedAt.', '
            .$this->optionalColumnExpression($table, $alias, 'sync_origin_device_id').', UTC_TIMESTAMP(6));';
    }

    private function rowVersion(string $table, string $alias, array $columns): array
    {
        if ($this->schema->hasColumn($table, 'row_version')) {
            return ['CAST('.$alias.'.`row_version` AS CHAR)', "'row_version'"];
        }

        $hash = $this->hashRow($alias, $columns);

        if ($this->schema->hasColumn($table, 'updated_at')) {
            return [
                'COALESCE(CAST('.$alias.'.`updated_at` AS CHAR), '.$hash.')',
                'IF('.$alias.'.`updated_at` IS NULL, \'sha256\', \'updated_at\')',
            ];
        }

        return [$hash, "'sha256'"];
    }

    private function hashRow(string $alias, array $columns): string
    {
        $arguments = [];

        foreach ($columns as $column) {
            $arguments[] = $this->stringLiteral($column);
            $arguments[] = $alias.'.'.$this->quoteIdentifier($column);
        }

        return 'SHA2(JSON_OBJECT('.implode(', ', $arguments).'), 256)';
    }

    private function primaryKeyExpression(string $alias, array $primaryKey): string
    {
        if (count($primaryKey) === 1) {
            return 'CAST('.$alias.'.'.$this->quoteIdentifier($primaryKey[0]).' AS CHAR)';
        }

        $arguments = [];

        foreach ($primaryKey as $column) {
            $arguments[] = $this->stringLiteral($column);
            $arguments[] = $alias.'.'.$this->quoteIdentifier($column);
        }

        return 'JSON_OBJECT('.implode(', ', $arguments).')';
    }

    private function optionalColumnExpression(string $table, string $alias, string $column): string
    {
        return $this->schema->hasColumn($table, $column)
            ? 'CAST('.$alias.'.'.$this->quoteIdentifier($column).' AS CHAR)'
            : 'NULL';
    }

    private function hasLogSchema(): bool
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
            'created_at',
        ]);
    }

    private function hasTombstoneSchema(): bool
    {
        return $this->schema->hasColumns(self::TOMBSTONE_TABLE, [
            'change_log_id',
            'table_name',
            'primary_key',
            'client_uuid',
            'row_version',
            'deleted_at',
            'deleted_by_device_id',
            'created_at',
        ]);
    }

    private function triggerName(string $table, string $event): string
    {
        return 'trg_d1_'.substr(hash('sha256', $table), 0, 20).'_'.$event;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function stringLiteral(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }
}
