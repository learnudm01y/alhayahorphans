<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupUnlockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:unlock {--force : فك القفل حتى لو بدت العملية مازالت حية}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فك قفل النسخ الاحتياطي المهجور وإنهاء العمليات العالقة';

    public function handle(BackupService $backup): int
    {
        $status = $backup->lockStatus();

        if ($status !== null) {
            $this->table(['Field', 'Value'], [
                ['PID', $status['pid'] ?: '-'],
                ['Process alive', $status['alive'] ? 'yes' : 'no'],
                ['Age (seconds)', $status['age_seconds']],
                ['Stale', $status['stale'] ? 'yes' : 'no'],
            ]);

            if ($status['alive'] && !$status['stale'] && !$this->option('force')) {
                $this->error(
                    'توجد عملية نشطة (PID ' . $status['pid'] . ') — القفل لم يُفك. ' .
                    'انتظر انتهائها أو استخدم --force إذا كنت متأكداً أنها عالقة.'
                );

                return self::FAILURE;
            }
        }

        $removed  = $backup->forceUnlock();
        $recovered = $backup->recoverStuckRuns();

        $this->info(sprintf(
            'تم. القفل: %s | عمليات عالقة أُعيدت إلى "فشل": %d',
            $removed ? 'فُك' : 'لا يوجد قفل',
            $recovered
        ));

        return self::SUCCESS;
    }
}
