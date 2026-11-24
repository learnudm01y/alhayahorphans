<?php

namespace App\Services\DesktopSync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaGuard
{
    public function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }

    public function hasColumns(string $table, array $columns): bool
    {
        if (!$this->hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    public function columns(string $table): array
    {
        return $this->hasTable($table) ? Schema::getColumnListing($table) : [];
    }

    public function primaryKeyColumns(string $table): array
    {
        if (!$this->hasTable($table)) {
            return [];
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            [DB::connection()->getDatabaseName(), $table, 'PRIMARY']
        );

        return array_values(array_filter(
            array_map(static fn (object $row): string => $row->COLUMN_NAME, $rows),
            fn (string $column): bool => $this->hasColumn($table, $column)
        ));
    }
}
