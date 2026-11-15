<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\AttachmentFaceEmbedding;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class FaceEmbeddingService
{
    public function process(Attachment $attachment, bool $force = false): array
    {
        $modelVersion = (string) config('face_matching.model_version');
        $existing = AttachmentFaceEmbedding::where('attachment_id', $attachment->id)->first();

        if (!$force && $existing && $this->isCurrent($existing, $attachment, $modelVersion)) {
            return $this->recordResult($existing, true);
        }

        $path = $this->localPath($attachment);
        if ($path === null) {
            return $this->store($attachment, $modelVersion, 'failed', 'file-missing');
        }

        $lock = @fopen(storage_path('framework/face-embedding.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return $this->failureResult($attachment, $modelVersion, 'busy');
        }

        try {
            $settings = config('face_matching.process');
            $process = new Process([
                (string) config('face_matching.node_binary', 'node'),
                base_path('scripts/face-embed.js'),
                '--in', $path,
                '--budget', (string) $settings['budget_ms'],
                '--max-side', (string) $settings['max_side'],
                '--min-score', (string) $settings['min_score'],
                '--min-face-width', (string) $settings['min_face_width'],
                '--dominance-ratio', (string) $settings['dominance_ratio'],
            ], base_path());
            $process->setTimeout((float) $settings['timeout_seconds']);
            $process->setIdleTimeout((float) $settings['timeout_seconds']);
            $process->run();

            $payload = $this->parseJsonOutput($process->getOutput());
            if (!($payload['ok'] ?? false)) {
                $reason = (string) ($payload['reason'] ?? 'no-output');
                Log::warning('face-embed: تعذر استخراج البصمة', [
                    'attachment_id' => $attachment->id,
                    'person' => $this->maskIdentity($attachment->person_identity_number),
                    'reason' => $reason,
                    'exit' => $process->getExitCode(),
                ]);

                return $this->store($attachment, $modelVersion, 'failed', $reason);
            }

            if (($payload['model_version'] ?? null) !== $modelVersion) {
                return $this->store($attachment, $modelVersion, 'failed', 'model-mismatch');
            }

            $check = $payload['check'] ?? [];
            if (!($check['pass'] ?? false)) {
                return $this->store(
                    $attachment,
                    $modelVersion,
                    'skipped',
                    (string) ($check['code'] ?? 'no-face'),
                    $check,
                );
            }

            if (!$this->isValidEmbedding($check['embedding'] ?? null)) {
                return $this->store($attachment, $modelVersion, 'failed', 'invalid-embedding', $check);
            }

            return $this->store($attachment, $modelVersion, 'ready', null, $check);
        } catch (ProcessTimedOutException) {
            return $this->store($attachment, $modelVersion, 'failed', 'timeout');
        } catch (\Throwable $e) {
            Log::error('face-embed: خطأ غير متوقع', [
                'attachment_id' => $attachment->id,
                'person' => $this->maskIdentity($attachment->person_identity_number),
                'exception' => $e::class,
            ]);

            return $this->store($attachment, $modelVersion, 'failed', 'engine-error');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function isCurrent(
        AttachmentFaceEmbedding $record,
        Attachment $attachment,
        string $modelVersion,
    ): bool {
        if (!in_array($record->status, ['ready', 'skipped'], true)
            || $record->model_version !== $modelVersion
            || $record->source_hash !== $attachment->file_hash
            || $record->source_updated_at?->timestamp !== $attachment->updated_at?->timestamp) {
            return false;
        }

        return $record->status !== 'ready' || $this->isValidEmbedding($record->embedding);
    }

    private function store(
        Attachment $attachment,
        string $modelVersion,
        string $status,
        ?string $reason,
        array $check = [],
    ): array {
        $record = AttachmentFaceEmbedding::updateOrCreate(
            ['attachment_id' => $attachment->id],
            [
                'model_version' => $modelVersion,
                'status' => $status,
                'embedding' => $status === 'ready' ? $check['embedding'] : null,
                'reason_code' => $reason !== null ? mb_substr($reason, 0, 64) : null,
                'source_hash' => $attachment->file_hash,
                'source_updated_at' => $attachment->updated_at,
                'detection_score' => $check['score'] ?? null,
                'face_count' => $check['face_count'] ?? null,
                'face_box' => $check['box'] ?? null,
                'metrics' => $check['metrics'] ?? null,
                'processed_at' => now(),
            ],
        );

        return $this->recordResult($record, false);
    }

    private function recordResult(AttachmentFaceEmbedding $record, bool $cached): array
    {
        return [
            'attachment_id' => $record->attachment_id,
            'model_version' => $record->model_version,
            'status' => $record->status,
            'reason_code' => $record->reason_code,
            'embedding' => $record->embedding,
            'detection_score' => $record->detection_score,
            'face_count' => $record->face_count,
            'face_box' => $record->face_box,
            'metrics' => $record->metrics,
            'cached' => $cached,
        ];
    }

    private function failureResult(Attachment $attachment, string $modelVersion, string $reason): array
    {
        return [
            'attachment_id' => $attachment->id,
            'model_version' => $modelVersion,
            'status' => 'failed',
            'reason_code' => $reason,
            'embedding' => null,
            'cached' => false,
        ];
    }

    public function localPath(Attachment $attachment): ?string
    {
        return $this->resolveLocalPath($attachment->file_path);
    }

    private function resolveLocalPath(?string $filePath): ?string
    {
        $normalized = str_replace('\\', '/', trim((string) $filePath));
        $normalized = ltrim($normalized, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        if ($normalized === '' || str_contains($normalized, "\0")) {
            return null;
        }

        $root = realpath(storage_path('app/public'));
        $fullPath = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . $normalized);

        if ($fullPath === false || !is_file($fullPath)
            || !str_starts_with($fullPath, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $fullPath;
    }

    private function isValidEmbedding(mixed $embedding): bool
    {
        if (!is_array($embedding) || count($embedding) !== 128) {
            return false;
        }

        foreach ($embedding as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                return false;
            }
        }

        return true;
    }

    private function maskIdentity(?string $identity): string
    {
        return '***' . substr((string) $identity, -3);
    }

    private function parseJsonOutput(string $stdout): array
    {
        foreach (array_reverse(explode("\n", str_replace("\r", '', $stdout))) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
