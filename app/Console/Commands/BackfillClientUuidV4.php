<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * v4:backfill-client-uuid
 *
 * يولّد client_uuid عشوائيًا لكل سجل قديم (قبل v4) لا يملك قيمة.
 * لا يعدّل أي عمود آخر إطلاقاً. يُشغَّل مرة واحدة بعد تطبيق المايجريشن
 * (الملف 04 §2 — Backfill آمن).
 */
class BackfillClientUuidV4 extends Command
{
    protected $signature = 'v4:backfill-client-uuid {--chunk=1000 : Chunk size for updates}';

    protected $description = 'Fill client_uuid for legacy rows that predate android-v4 (one-time, safe)';

    private const TABLES = [
        'data',
        're_people',
        'dead_people',
        'additional_deceased',
        'guardian_bank_accounts',
        'sponsorships',
    ];

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $total = 0;

        foreach (self::TABLES as $table) {
            if (!\Schema::hasTable($table) || !\Schema::hasColumn($table, 'client_uuid')) {
                $this->warn("Skipping {$table} (missing table or client_uuid column)");
                continue;
            }

            $count = 0;
            DB::table($table)
                ->whereNull('client_uuid')
                ->orderBy('id')
                ->chunk($chunk, function ($rows) use ($table, &$count) {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->whereNull('client_uuid')
                            ->update(['client_uuid' => (string) Str::uuid()]);
                        $count++;
                    }
                });

            $total += $count;
            $this->info("{$table}: backfilled {$count}");
        }

        $this->info("Done. Total rows backfilled: {$total}");

        return self::SUCCESS;
    }
}
