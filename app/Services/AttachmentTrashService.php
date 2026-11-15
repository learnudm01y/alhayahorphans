<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\AttachmentTrash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AttachmentTrashService
{
    public function __construct(private FaceEmbeddingService $embeddings)
    {
    }

    public function deletionEnabled(): bool
    {
        return (bool) config('face_matching.deletion_enabled', false)
            && Schema::hasTable('attachment_trash');
    }

    public function trash(
        Attachment $attachment,
        ?int $userId,
        string $reason = 'face-auto-duplicate',
        ?int $scanId = null,
        ?int $resultId = null,
    ): array {
        if (!$this->deletionEnabled()) {
            return ['status' => 'disabled'];
        }

        $existing = AttachmentTrash::where('attachment_id', $attachment->id)
            ->where('status', 'trashed')
            ->first();
        if ($existing) {
            return ['status' => 'already-trashed', 'trash_id' => $existing->id];
        }

        $source = $this->embeddings->localPath($attachment);
        if ($source === null) {
            Log::warning('face-trash: تخطي مرفق غير محلي (لا يُحذف من Google Drive أو التخزين الخارجي)', [
                'attachment_id' => $attachment->id,
                'person' => $this->maskIdentity($attachment->person_identity_number),
            ]);

            return ['status' => 'remote-skipped'];
        }

        $target = $this->trashTargetPath($attachment, $source);
        if (!@rename($source, $target)) {
            Log::error('face-trash: تعذر نقل الملف إلى السلة', [
                'attachment_id' => $attachment->id,
                'person' => $this->maskIdentity($attachment->person_identity_number),
            ]);

            return ['status' => 'move-failed'];
        }

        try {
            $trash = DB::transaction(function () use ($attachment, $userId, $reason, $scanId, $resultId, $target): AttachmentTrash {
                $trash = AttachmentTrash::create([
                    'attachment_id' => $attachment->id,
                    'snapshot' => $attachment->getAttributes(),
                    'original_path' => (string) $attachment->file_path,
                    'trashed_path' => $this->relativeToApp($target),
                    'scan_id' => $scanId,
                    'result_id' => $resultId,
                    'deleted_by' => $userId,
                    'reason' => mb_substr($reason, 0, 64),
                    'status' => 'trashed',
                    'trashed_at' => now(),
                    'restore_deadline_at' => now()->addDays(max(1, (int) config('face_matching.trash_retention_days', 30))),
                ]);

                Attachment::whereKey($attachment->id)->delete();

                return $trash;
            });
        } catch (\Throwable $e) {
            @rename($target, $source);

            Log::error('face-trash: فشل تسجيل السلة، أُعيد الملف لمكانه', [
                'attachment_id' => $attachment->id,
                'exception' => $e::class,
            ]);

            return ['status' => 'db-failed'];
        }

        Log::info('face-trash: نُقل مرفق إلى السلة', [
            'attachment_id' => $attachment->id,
            'person' => $this->maskIdentity($attachment->person_identity_number),
            'reason' => $reason,
            'trash_id' => $trash->id,
        ]);

        return ['status' => 'trashed', 'trash_id' => $trash->id];
    }

    public function restore(AttachmentTrash $trash, ?int $userId): array
    {
        if ($trash->status !== 'trashed') {
            return ['status' => 'not-trashed'];
        }

        if ($trash->isExpired()) {
            return ['status' => 'expired'];
        }

        $snapshot = is_array($trash->snapshot) ? $trash->snapshot : [];
        $attributes = $this->snapshotAttributes($snapshot);

        $trashedFile = $trash->trashed_path ? $this->resolveTrashedPath($trash->trashed_path) : null;
        $movedBack = false;

        if ($trashedFile !== null && is_file($trashedFile)) {
            $target = $this->resolvePublicPath((string) ($attributes['file_path'] ?? ''));
            if ($target === null || file_exists($target)) {
                $dir = $target !== null ? dirname($target) : null;
                if ($dir === null || !is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                    $dir = null;
                }
                $suffix = '-' . substr(uniqid(), -6) . '.' . pathinfo($trashedFile, PATHINFO_EXTENSION);
                $fallback = $dir !== null
                    ? $dir . DIRECTORY_SEPARATOR . basename($trashedFile, '.' . pathinfo($trashedFile, PATHINFO_EXTENSION)) . $suffix
                    : null;
                if ($fallback === null || !@rename($trashedFile, $fallback)) {
                    return ['status' => 'move-failed'];
                }
                $target = $fallback;
            } elseif (!@rename($trashedFile, $target)) {
                return ['status' => 'move-failed'];
            }

            $attributes['file_path'] = $this->publicRelative($target) ?? (string) ($attributes['file_path'] ?? '');
            $movedBack = true;
        }

        try {
            $attachmentId = DB::transaction(function () use ($attributes, $trash, $userId): int {
                try {
                    DB::table('attachments')->insert($attributes);
                } catch (\Throwable $e) {
                    unset($attributes['id']);
                    DB::table('attachments')->insert($attributes);
                }

                $id = (int) ($attributes['id'] ?? DB::getPdo()->lastInsertId());
                $trash->update(['status' => 'restored', 'restored_at' => now()]);

                return $id;
            });
        } catch (\Throwable $e) {
            if ($movedBack) {
                $source = $this->resolvePublicPath((string) ($attributes['file_path'] ?? ''));
                $back = $trash->trashed_path ? $this->resolveTrashedPath($trash->trashed_path) : null;
                if ($source !== null && $back !== null && is_file($source)) {
                    @rename($source, $back);
                }
            }

            Log::error('face-trash: فشل الاسترجاع', [
                'trash_id' => $trash->id,
                'exception' => $e::class,
            ]);

            return ['status' => 'db-failed'];
        }

        Log::info('face-trash: استُعيد مرفق من السلة', [
            'trash_id' => $trash->id,
            'attachment_id' => $attachmentId,
            'person' => $this->maskIdentity((string) ($attributes['person_identity_number'] ?? '')),
        ]);

        return ['status' => 'restored', 'attachment_id' => $attachmentId];
    }

    public function emptyTrash(): array
    {
        $purged = 0;
        $filesRemoved = 0;
        $errors = 0;

        foreach (AttachmentTrash::where('status', 'trashed')->orderBy('id')->cursor() as $trash) {
            $trash->update(['status' => 'purged', 'purged_at' => now()]);
            $purged++;

            $file = $trash->trashed_path ? $this->resolveTrashedPath($trash->trashed_path) : null;
            if ($file === null) {
                continue;
            }

            if (is_file($file) && @unlink($file)) {
                $filesRemoved++;
            } elseif (is_file($file)) {
                $errors++;
            }

            Log::info('face-trash: تنظيف نهائي من صفحة السلة', [
                'trash_id' => $trash->id,
                'attachment_id' => $trash->attachment_id,
            ]);
        }

        return ['purged' => $purged, 'files_removed' => $filesRemoved, 'errors' => $errors];
    }

    public function purgeExpired(bool $dryRun = true): array
    {
        $query = AttachmentTrash::where('status', 'trashed')
            ->whereNotNull('restore_deadline_at')
            ->where('restore_deadline_at', '<', now());

        $purged = 0;
        $filesRemoved = 0;
        $errors = 0;

        foreach ($query->cursor() as $trash) {
            $file = $trash->trashed_path ? $this->resolveTrashedPath($trash->trashed_path) : null;
            if ($dryRun) {
                $purged++;

                continue;
            }

            $trash->update(['status' => 'purged', 'purged_at' => now()]);
            $purged++;

            if ($file !== null && is_file($file) && @unlink($file)) {
                $filesRemoved++;
            }

            Log::info('face-trash: تنظيف نهائي لعنصر منتهي', [
                'trash_id' => $trash->id,
                'attachment_id' => $trash->attachment_id,
            ]);
        }

        return [
            'dry_run' => $dryRun,
            'purged' => $purged,
            'files_removed' => $filesRemoved,
            'errors' => $errors,
        ];
    }

    public function forAttachment(int $attachmentId): ?AttachmentTrash
    {
        if (!Schema::hasTable('attachment_trash')) {
            return null;
        }

        return AttachmentTrash::where('attachment_id', $attachmentId)
            ->where('status', 'trashed')
            ->latest('id')
            ->first();
    }

    private function snapshotAttributes(array $snapshot): array
    {
        $attributes = $snapshot;
        unset($attributes['password'], $attributes['remember_token']);

        return $attributes;
    }

    private function trashTargetPath(Attachment $attachment, string $source): string
    {
        $root = storage_path('app/attachments-trash/' . now()->format('Y-m-d'));
        if (!is_dir($root)) {
            mkdir($root, 0777, true);
        }

        $name = $attachment->id . '-' . basename($source);
        $target = $root . DIRECTORY_SEPARATOR . $name;

        if (file_exists($target)) {
            $target = $root . DIRECTORY_SEPARATOR . $attachment->id . '-' . substr(uniqid(), -6) . '-' . basename($source);
        }

        return $target;
    }

    public function resolveTrashedPath(?string $relativePath): ?string
    {
        $root = realpath(storage_path('app/attachments-trash'));
        $full = $root === false ? false : realpath(storage_path('app/' . ltrim($relativePath, '/\\')));

        if ($full === false || !is_file($full)
            || !str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $full;
    }

    private function resolvePublicPath(string $filePath): ?string
    {
        $normalized = str_replace('\\', '/', trim($filePath));
        $normalized = ltrim($normalized, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        if ($normalized === '' || str_contains($normalized, "\0")) {
            return null;
        }

        $root = realpath(storage_path('app/public'));

        return $root === false ? null : $root . DIRECTORY_SEPARATOR . $normalized;
    }

    private function relativeToApp(string $fullPath): string
    {
        $base = realpath(storage_path('app'));

        return ltrim(str_replace('\\', '/', substr($fullPath, strlen($base))), '/');
    }

    private function publicRelative(string $fullPath): ?string
    {
        $root = realpath(storage_path('app/public'));
        if ($root === false || !str_starts_with($fullPath, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return ltrim(str_replace('\\', '/', substr($fullPath, strlen($root))), '/');
    }

    private function maskIdentity(?string $identity): string
    {
        return '***' . substr((string) $identity, -3);
    }
}
