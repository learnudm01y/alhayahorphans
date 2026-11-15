<?php

namespace App\Jobs;

use App\Services\FaceScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAttachmentFaceScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 420;

    public $failOnTimeout = true;

    public function __construct(public int $scanId)
    {
        $this->onQueue(config('face_matching.queue', 'faces'));
    }

    public function handle(FaceScanService $scans): void
    {
        $scans->run($this->scanId);
    }

    public function failed(?\Throwable $exception): void
    {
        try {
            app(FaceScanService::class)->fail($this->scanId, $exception);
        } catch (\Throwable $failure) {
            Log::error('face-scan: تعذر تسجيل فشل المهمة', [
                'scan_id' => $this->scanId,
                'exception' => $failure::class,
            ]);
        }
    }
}
