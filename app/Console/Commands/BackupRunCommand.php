<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تشغيل النسخ الاحتياطي الكامل: أرشفة uploads و attachments إلى OneDrive + نسخة MySQL + نسخة ملفات Laravel';

    public function handle(BackupService $backup): int
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $this->info('Backup started');

        try {
            $run = $backup->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['Status', $run->status],
            ['Step', $run->current_step],
            ['Duration', $run->duration_seconds ? $run->duration_seconds . 's' : '-'],
            ['Database', $run->database_status . ' (' . $this->bytes($run->database_size) . ')'],
            ['Laravel', $run->laravel_status . ' (' . $this->bytes($run->laravel_size) . ')'],
            ['Uploads', $this->media($run, 'uploads')],
            ['Attachments', $this->media($run, 'attachments')],
            ['OneDrive', sprintf(
                '%s | uploaded %s | already-uploaded %s | failed %s | %s',
                $run->backups_status ?? '-',
                $run->backups_copied ?? 0,
                $run->backups_skipped ?? 0,
                $run->backups_failed ?? 0,
                $this->bytes($run->backups_size ?? 0)
            )],
            ['Error', $run->error_message ?: '-'],
        ]);

        return $run->status === BackupRun::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }

    private function media(BackupRun $run, string $key): string
    {
        return sprintf(
            '%s | total %s, copied %s, skipped %s, failed %s (%s)',
            $run->getAttribute("{$key}_status") ?? '-',
            $run->getAttribute("{$key}_files") ?? '-',
            $run->getAttribute("{$key}_copied") ?? '-',
            $run->getAttribute("{$key}_skipped") ?? '-',
            $run->getAttribute("{$key}_failed") ?? '-',
            $this->bytes($run->getAttribute("{$key}_size"))
        );
    }

    private function bytes($value): string
    {
        if (!$value) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $size = (float) $value;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, 2) . ' ' . $units[$index];
    }
}
