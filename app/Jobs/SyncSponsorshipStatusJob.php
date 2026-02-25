<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Job لمزامنة حالة الكفالة بين الجداول الثلاثة:
 * sponsorships → data (عبر data_id_number = identity_number)
 * sponsorships → re_people (عبر person_id = identity_number)
 *
 * المنطق:
 * - نقرأ كل سجل في sponsorships له sponsorship_status_id
 * - نحدّث data.sponsorship_status بقيمة sponsorship_status_id حيث data_id_number = identity_number
 * - نحدّث re_people.sponsorship_status بقيمة sponsorship_status_id حيث person_id = identity_number
 */
class SyncSponsorshipStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 1;
    public $timeout = 3600; // ساعة كاملة

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lockKey = 'sync_sponsorship_status_job';
        $lock    = Cache::lock($lockKey, 3600);

        if (!$lock->get()) {
            Log::warning('SyncSponsorshipStatusJob: already running, skipping.');
            return;
        }

        try {
            Log::info('SyncSponsorshipStatusJob: بدء المزامنة');

            $stats = [
                'data_updated'       => 0,
                're_people_updated'  => 0,
                'processed'          => 0,
                'skipped_no_status'  => 0,
            ];

            // نعالج على دفعات لتجنب استهلاك الذاكرة
            DB::table('sponsorships')
                ->whereNotNull('sponsorship_status_id')
                ->whereNotNull('identity_number')
                ->orderBy('id')
                ->chunk(200, function ($sponsorships) use (&$stats) {
                    foreach ($sponsorships as $sponsorship) {
                        $identityNumber    = trim((string) $sponsorship->identity_number);
                        $statusId          = (int) $sponsorship->sponsorship_status_id;
                        $stats['processed']++;

                        if (!$identityNumber || !$statusId) {
                            $stats['skipped_no_status']++;
                            continue;
                        }

                        // ── 1. مزامنة جدول data ──────────────────────────────────
                        $dataUpdated = DB::table('data')
                            ->where('data_id_number', $identityNumber)
                            ->update(['sponsorship_status' => $statusId]);

                        $stats['data_updated'] += $dataUpdated;

                        // ── 2. مزامنة جدول re_people ─────────────────────────────
                        $repUpdated = DB::table('re_people')
                            ->where('person_id', $identityNumber)
                            ->update(['sponsorship_status' => $statusId]);

                        $stats['re_people_updated'] += $repUpdated;
                    }
                });

            Log::info('SyncSponsorshipStatusJob: اكتملت المزامنة', $stats);

        } catch (\Throwable $e) {
            Log::error('SyncSponsorshipStatusJob: فشل', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncSponsorshipStatusJob: Job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
