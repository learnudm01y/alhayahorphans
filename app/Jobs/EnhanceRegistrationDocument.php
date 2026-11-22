<?php

namespace App\Jobs;

use App\Services\DocumentEnhancementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnhanceRegistrationDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;
    public $timeout = 120;
    public $failOnTimeout = true;

    public function __construct(public int $attachmentId, public string $sourcePath)
    {
        $this->onConnection(config('document_enhancement.connection'));
        $this->onQueue(config('document_enhancement.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('document-enhancement:'.$this->attachmentId))->releaseAfter(20)->expireAfter(180)];
    }

    public function handle(DocumentEnhancementService $documents): void
    {
        $documents->process($this->attachmentId, $this->sourcePath);
    }

    public function failed(?\Throwable $exception): void
    {
        try {
            app(DocumentEnhancementService::class)->fail($this->attachmentId, $this->sourcePath);
        } catch (\Throwable $failure) {
            Log::warning('document-enhancement: failure status unavailable', [
                'attachment_id' => $this->attachmentId,
                'exception' => $failure::class,
            ]);
        }
    }
}
