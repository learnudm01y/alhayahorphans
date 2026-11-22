<?php

namespace App\Services;

use App\Jobs\EnhanceRegistrationDocument;
use App\Models\Attachment;
use App\Models\DocumentType;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentEnhancementService
{
    private const SUPPORTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tif', 'tiff'];

    public function enqueue(array $attachmentIds): void
    {
        try {
            if ($attachmentIds === [] || !$this->schemaReady()) {
                return;
            }
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(fn () => $this->enqueue($attachmentIds));
                return;
            }

            foreach (Attachment::whereKey($attachmentIds)->get() as $attachment) {
                try {
                    if (!$this->isDocument($attachment) || $this->localPath($attachment->file_path) === null) {
                        continue;
                    }
                    $claimed = Attachment::whereKey($attachment->id)
                        ->where('file_path', $attachment->file_path)
                        ->whereNull('document_enhancement_status')
                        ->update(['document_enhancement_status' => 'queued']);
                    if ($claimed) {
                        Bus::dispatch(new EnhanceRegistrationDocument($attachment->id, $attachment->file_path));
                    }
                } catch (\Throwable $exception) {
                    $this->fail($attachment->id, $attachment->file_path);
                    Log::warning('document-enhancement: enqueue failed', [
                        'attachment_id' => $attachment->id, 'exception' => $exception::class,
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('document-enhancement: queue unavailable', ['exception' => $exception::class]);
        }
    }

    /**
     * @return array{success: bool, status: string, message: string}
     */
    public function requestEnhancement(int $attachmentId): array
    {
        if (!$this->schemaReady()) {
            return ['success' => false, 'status' => 'unavailable', 'message' => 'ميزة تحسين المستندات غير مهيأة بعد.'];
        }
        $attachment = Attachment::find($attachmentId);
        if (!$attachment) {
            return ['success' => false, 'status' => 'missing', 'message' => 'المرفق غير موجود.'];
        }
        $status = (string) $attachment->document_enhancement_status;
        if (in_array($status, ['queued', 'processing'], true)) {
            return ['success' => true, 'status' => $status, 'message' => 'التحسين جارٍ بالفعل لهذا المرفق.'];
        }
        if (!$this->isDocument($attachment)) {
            return ['success' => false, 'status' => 'not-document', 'message' => 'هذا المرفق ليس وثيقة قابلة للتحسين.'];
        }
        if ($this->localPath($attachment->file_path) === null) {
            return ['success' => false, 'status' => 'not-allowed', 'message' => 'لا يمكن تحسين هذا الملف؛ الصيغ المدعومة: JPG وPNG.'];
        }

        $claimed = Attachment::whereKey($attachment->id)
            ->where('file_path', $attachment->file_path)
            ->where(function ($query) {
                $query->whereNull('document_enhancement_status')
                    ->orWhereIn('document_enhancement_status', ['failed', 'unchanged', 'enhanced']);
            })
            ->update(['document_enhancement_status' => 'queued']);
        if (!$claimed) {
            $current = (string) Attachment::whereKey($attachment->id)->value('document_enhancement_status');
            return ['success' => true, 'status' => $current !== '' ? $current : 'queued', 'message' => 'التحسين جارٍ بالفعل لهذا المرفق.'];
        }

        try {
            Bus::dispatch(new EnhanceRegistrationDocument($attachment->id, $attachment->file_path));
        } catch (\Throwable $exception) {
            $this->fail($attachment->id, $attachment->file_path);
            Log::warning('document-enhancement: admin dispatch failed', [
                'attachment_id' => $attachment->id, 'exception' => $exception::class,
            ]);
            return ['success' => false, 'status' => 'failed', 'message' => 'تعذر إرسال طلب التحسين إلى الخادم.'];
        }

        return ['success' => true, 'status' => 'queued', 'message' => 'تم إرسال المرفق للتحسين.'];
    }

    public function status(int $attachmentId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $attachment = Attachment::find($attachmentId, [
            'id', 'file_path', 'stored_file_name', 'document_enhancement_status', 'document_enhancement_metadata',
        ]);
        if (!$attachment) {
            return null;
        }
        $metadata = is_array($attachment->document_enhancement_metadata) ? $attachment->document_enhancement_metadata : [];

        return [
            'status' => $attachment->document_enhancement_status ?: 'none',
            'reason' => $metadata['reason'] ?? null,
            'operations' => $metadata['operations'] ?? [],
            'file_name' => $attachment->stored_file_name ?: basename((string) $attachment->file_path),
        ];
    }

    public function process(int $attachmentId, string $sourcePath): void
    {
        if (!$this->schemaReady()) {
            return;
        }
        $attachment = $this->pending($attachmentId, $sourcePath)->first();
        if (!$attachment) {
            return;
        }
        $source = $this->localPath($sourcePath);
        if (!$this->isDocument($attachment) || $source === null) {
            $this->finishUnchanged($attachmentId, $sourcePath, ['reason' => 'ineligible-source']);
            return;
        }
        $sourceHash = hash_file('sha256', $source);
        $sourceBytes = filesize($source);
        $this->pending($attachmentId, $sourcePath)->update(['document_enhancement_status' => 'processing']);
        $workingDirectory = storage_path('app/document-enhancement/'.Str::uuid());
        File::ensureDirectoryExists($workingDirectory);
        $output = $workingDirectory.'/enhanced.jpg';
        // Same-directory staging keeps the final replacement a single atomic rename on one filesystem.
        $replacement = dirname($source).'/.enhance-'.bin2hex(random_bytes(8)).'.tmp';

        try {
            $result = Process::path(base_path())->timeout(90)->env([
                'OMP_THREAD_LIMIT' => '1', 'OPENBLAS_NUM_THREADS' => '1',
            ])->run([
                (string) config('document_enhancement.python_binary'),
                base_path('scripts/document-enhance.py'),
                '--input', $source, '--output', $output,
                '--tesseract', (string) config('document_enhancement.tesseract_binary'),
                '--max-side', '3200', '--max-pixels', '24000000', '--osd-timeout', '15',
            ]);
            $metadata = json_decode($result->output(), true);
            if (!$result->successful() || !is_array($metadata)
                || !in_array($metadata['status'] ?? '', ['enhanced', 'unchanged'], true)) {
                throw new \RuntimeException('Document enhancement process failed.');
            }
            $metadata = array_intersect_key($metadata, array_flip([
                'status', 'reason', 'operations', 'warnings', 'width', 'height',
            ]));
            if ($metadata['status'] === 'unchanged') {
                $this->finishUnchanged($attachmentId, $sourcePath, $metadata);
                return;
            }

            $dimensions = @getimagesize($output);
            $bytes = is_file($output) ? filesize($output) : 0;
            if (!$dimensions || ($dimensions['mime'] ?? '') !== 'image/jpeg'
                || $dimensions[0] * $dimensions[1] > 24000000 || $bytes < 1 || $bytes > 5 * 1024 * 1024) {
                throw new \RuntimeException('Invalid enhanced document output.');
            }
            clearstatcache(true, $source);
            if (!is_file($source) || !hash_equals($sourceHash, hash_file('sha256', $source))) {
                $this->finishUnchanged($attachmentId, $sourcePath, ['reason' => 'source-changed']);
                return;
            }
            if (!$this->isDocument($attachment)) {
                $this->finishUnchanged($attachmentId, $sourcePath, ['reason' => 'classification-changed']);
                return;
            }

            if (!@copy($output, $replacement) || filesize($replacement) !== $bytes) {
                throw new \RuntimeException('Could not stage enhanced document.');
            }
            clearstatcache(true, $replacement);
            if (filesize($replacement) !== $bytes || @getimagesize($replacement) === false) {
                throw new \RuntimeException('Staged enhanced document is invalid.');
            }

            // file_hash and stored_file_name stay untouched: the path remains the upload identity.
            $metadata['replaced_sha256'] = $sourceHash;
            $metadata['replaced_size'] = $sourceBytes;
            // Claim before touching bytes: a stale row must never receive an overwritten file.
            $claimed = $this->pending($attachmentId, $sourcePath)
                ->where('file_type', $attachment->file_type)
                ->update([
                    'file_size' => $bytes,
                    'document_enhancement_status' => 'enhanced',
                    'document_enhancement_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                ]);
            if (!$claimed) {
                // The type changed while processing; the row is still pending but no longer ours.
                $this->finishUnchanged($attachmentId, $sourcePath, ['reason' => 'classification-changed']);
                return;
            }
            if (!@rename($replacement, $source)) {
                Attachment::whereKey($attachmentId)->where('file_path', $sourcePath)
                    ->where('document_enhancement_status', 'enhanced')
                    ->update([
                        'file_size' => $sourceBytes,
                        'document_enhancement_status' => 'processing',
                        'document_enhancement_metadata' => null,
                    ]);
                throw new \RuntimeException('Could not replace original document.');
            }
            clearstatcache(true, $source);
        } finally {
            if (is_file($replacement)) {
                @unlink($replacement);
            }
            File::deleteDirectory($workingDirectory);
        }
    }

    public function fail(int $attachmentId, string $sourcePath): void
    {
        if ($this->schemaReady()) {
            $this->pending($attachmentId, $sourcePath)->update([
                'document_enhancement_status' => 'failed',
                'document_enhancement_metadata' => json_encode(['reason' => 'processing-failed']),
            ]);
        }
    }

    private function finishUnchanged(int $attachmentId, string $sourcePath, array $metadata): void
    {
        $this->pending($attachmentId, $sourcePath)->update([
            'document_enhancement_status' => 'unchanged',
            'document_enhancement_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function pending(int $attachmentId, string $sourcePath)
    {
        return Attachment::whereKey($attachmentId)->where('file_path', $sourcePath)
            ->whereIn('document_enhancement_status', ['queued', 'processing']);
    }

    private function schemaReady(): bool
    {
        return Schema::hasColumns('attachments', [
            'document_enhancement_status', 'document_enhancement_metadata', 'file_size',
        ]) && Schema::hasColumns('document_types', ['pref', 'file_type']);
    }

    private function isDocument(Attachment $attachment): bool
    {
        // Public registration stores document_types.pref; admin records store the DocumentType id.
        $value = (string) $attachment->file_type;
        if (DocumentType::where('pref', $value)->where('file_type', 'document')->exists()) {
            return true;
        }

        return ctype_digit($value)
            && DocumentType::whereKey((int) $value)->where('file_type', 'document')->exists();
    }

    private function localPath(string $path): ?string
    {
        if (!preg_match('#^storage/uploads/[0-9]{6}/[^/\\\\\x00]+$#D', $path)) {
            return null;
        }
        $disk = Storage::disk('public');
        $root = realpath($disk->path('uploads'));
        $resolved = realpath($disk->path(substr($path, strlen('storage/'))));
        if ($root === false || $resolved === false || !is_file($resolved)
            || !str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return in_array(strtolower(pathinfo($resolved, PATHINFO_EXTENSION)), self::SUPPORTED_IMAGE_EXTENSIONS, true)
            ? $resolved : null;
    }
}
