<?php

namespace App\Services\DesktopSync;

use Illuminate\Support\Facades\DB;

class ChangeLogRetentionService
{
    private const LOG_TABLE = 'desktop_change_log_v1';

    private const TOMBSTONE_TABLE = 'desktop_tombstones_v1';

    private const STATE_TABLE = 'desktop_sync_state_v1';

    public function __construct(private readonly SchemaGuard $schema)
    {
    }

    public function prune(?int $retentionDays = null): int
    {
        if (!$this->schema->hasColumns(self::LOG_TABLE, ['id', 'created_at'])
            || !$this->schema->hasColumns(self::TOMBSTONE_TABLE, ['change_log_id', 'deleted_at'])) {
            return 0;
        }

        $days = $retentionDays ?? (int) config('desktop_sync.change_log_retention_days', 90);
        $cutoff = now('UTC')->subDays(max(0, $days));

        return DB::transaction(function () use ($cutoff): int {
            $acknowledgedCursor = $this->acknowledgedCursor();

            if ($acknowledgedCursor < 1) {
                return 0;
            }

            DB::table(self::TOMBSTONE_TABLE)
                ->where('change_log_id', '<=', $acknowledgedCursor)
                ->where('deleted_at', '<', $cutoff)
                ->delete();

            return DB::table(self::LOG_TABLE)
                ->where('id', '<=', $acknowledgedCursor)
                ->where('created_at', '<', $cutoff)
                ->delete();
        });
    }

    public function inspectCursor(int $cursor): array
    {
        if (!$this->schema->hasColumns(self::LOG_TABLE, ['id'])) {
            return $this->reconciliationResult('missing_schema');
        }

        if ($cursor < 0) {
            return $this->reconciliationResult('invalid_cursor');
        }

        if ($cursor === 0) {
            return $this->reconciliationResult('initial_cursor');
        }

        $bounds = DB::table(self::LOG_TABLE)
            ->selectRaw('MIN(id) AS oldest_id, MAX(id) AS newest_id')
            ->first();

        if ($bounds === null || $bounds->oldest_id === null) {
            return $this->reconciliationResult('cursor_expired');
        }

        $oldest = (int) $bounds->oldest_id;
        $newest = (int) $bounds->newest_id;

        if ($cursor > $newest) {
            return $this->reconciliationResult('cursor_ahead');
        }

        if ($cursor < $oldest - 1) {
            return $this->reconciliationResult('cursor_expired');
        }

        return [
            'requires_full_reconciliation' => false,
            'reason' => null,
            'oldest_change_log_id' => $oldest,
            'newest_change_log_id' => $newest,
        ];
    }

    public function readAfter(int $cursor, int $limit = 500): array
    {
        $cursorState = $this->inspectCursor($cursor);

        if ($cursorState['requires_full_reconciliation']) {
            return [
                'requires_full_reconciliation' => true,
                'reason' => $cursorState['reason'],
                'changes' => [],
                'next_cursor' => null,
            ];
        }

        $limit = max(1, min(1000, $limit));
        $changes = DB::table(self::LOG_TABLE)
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $last = $changes->last();

        return [
            'requires_full_reconciliation' => false,
            'reason' => null,
            'changes' => $changes->all(),
            'next_cursor' => $last === null ? $cursor : (int) $last->id,
        ];
    }

    private function acknowledgedCursor(): int
    {
        if (!$this->schema->hasColumns(self::STATE_TABLE, ['last_change_log_id'])) {
            return 0;
        }

        $cursor = DB::table(self::STATE_TABLE)->min('last_change_log_id');

        return $cursor === null ? 0 : (int) $cursor;
    }

    private function reconciliationResult(string $reason): array
    {
        return [
            'requires_full_reconciliation' => true,
            'reason' => $reason,
            'oldest_change_log_id' => null,
            'newest_change_log_id' => null,
        ];
    }
}
