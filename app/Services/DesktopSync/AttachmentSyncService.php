<?php

namespace App\Services\DesktopSync;

use DomainException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AttachmentSyncService
{
    private const TABLE = 'desktop_attachments_v1';

    private const ROOT = 'desktop-sync/v1/attachments';

    private const DEFAULT_MAX_CHUNK_BYTES = 4194304;

    private const DEFAULT_MAX_FILE_BYTES = 209715200;

    public function __construct(private readonly SchemaGuard $schema)
    {
    }

    public function list(object $device, array $filters = []): array
    {
        $this->ensureSchema();

        $query = DB::table(self::TABLE)->where('source_id', $device->source_id);
        foreach (['entityType' => 'entity_type', 'primaryKey' => 'primary_key', 'clientUuid' => 'client_uuid'] as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }

        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $rows = $query->orderBy('id')->limit($limit)->get();

        return [
            'data' => $rows->map(fn (object $row): array => $this->payload($row))->values()->all(),
            'server_time' => now('UTC')->toISOString(),
        ];
    }

    public function initialize(object $device, array $data): array
    {
        $this->ensureSchema();
        $data = $this->normalizeManifest($data);
        $manifest = $data;
        unset($manifest['operationId']);
        $manifestHash = hash('sha256', $this->encode($manifest));
        $operationHash = hash('sha256', 'initialize:'.$manifestHash);
        $now = now('UTC');

        return DB::transaction(function () use ($device, $data, $manifestHash, $operationHash, $now): array {
            $row = DB::table(self::TABLE)
                ->where('attachment_uuid', $data['attachmentUuid'])
                ->lockForUpdate()
                ->first();

            if ($row !== null) {
                $this->assertOwnedByDevice($row, $device);
                $metadata = $this->decode($row->metadata_json);
                if (!hash_equals((string) ($metadata['manifestHash'] ?? ''), $manifestHash)) {
                    throw new DomainException('manifest_mismatch');
                }

                $metadata = $this->registerOperation($metadata, $data['operationId'], $operationHash);
                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'metadata_json' => $this->encode($metadata),
                    'updated_at' => $now,
                ]);
                $row->metadata_json = $this->encode($metadata);
                $row->updated_at = $now;

                return $this->payload($row, $metadata);
            }

            $metadata = $this->registerOperation([
                'manifestHash' => $manifestHash,
                'chunkSize' => $data['chunkSize'],
                'totalChunks' => $data['totalChunks'],
                'chunks' => [],
                'operations' => [],
                'completedAt' => null,
            ], $data['operationId'], $operationHash);

            DB::table(self::TABLE)->insert([
                'attachment_uuid' => $data['attachmentUuid'],
                'operation_id' => $data['operationId'],
                'device_id' => $device->device_id,
                'source_id' => $device->source_id,
                'entity_type' => $data['entityType'],
                'primary_key' => $data['primaryKey'],
                'client_uuid' => $data['clientUuid'],
                'attachment_reference_kind' => $data['attachmentReferenceKind'],
                'canonical_attachment_id' => $data['canonicalAttachmentId'],
                'original_file_name' => $data['originalFileName'],
                'mime_type' => $data['mimeType'],
                'file_size' => $data['fileSize'],
                'sha256' => $data['sha256'],
                'storage_path' => null,
                'status' => 'pending',
                'metadata_json' => $this->encode($metadata),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $row = DB::table(self::TABLE)
                ->where('attachment_uuid', $data['attachmentUuid'])
                ->first();
            if ($row === null) {
                throw new RuntimeException('The attachment manifest could not be persisted.');
            }

            return $this->payload($row, $metadata);
        });
    }

    public function status(object $device, string $attachmentUuid): array
    {
        $this->ensureSchema();
        $attachmentUuid = $this->normalizeUuid($attachmentUuid);
        $row = $this->findForSource($attachmentUuid, $device->source_id);

        return $this->payload($row);
    }

    public function uploadChunk(
        object $device,
        string $attachmentUuid,
        int $chunkIndex,
        string $operationId,
        string $checksum,
        string $contents
    ): array {
        $this->ensureSchema();
        $attachmentUuid = $this->normalizeUuid($attachmentUuid);
        $operationId = $this->normalizeOperationId($operationId);
        $checksum = strtolower($checksum);
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new InvalidArgumentException('invalid_chunk_checksum');
        }
        if ($contents === '') {
            throw new InvalidArgumentException('empty_chunk');
        }

        $size = strlen($contents);
        if ($size > $this->maxChunkBytes()) {
            throw new InvalidArgumentException('chunk_too_large');
        }
        $actualChecksum = hash('sha256', $contents);
        if (!hash_equals($checksum, $actualChecksum)) {
            throw new InvalidArgumentException('chunk_checksum_mismatch');
        }

        $operationHash = hash('sha256', $this->encode([
            'scope' => 'chunk',
            'attachmentUuid' => $attachmentUuid,
            'chunkIndex' => $chunkIndex,
            'size' => $size,
            'sha256' => $actualChecksum,
        ]));
        $disk = $this->disk();
        $previousPath = null;

        $result = DB::transaction(function () use (
            $device,
            $attachmentUuid,
            $chunkIndex,
            $operationId,
            $actualChecksum,
            $size,
            $operationHash,
            $contents,
            $disk,
            &$previousPath
        ): array {
            $row = $this->findOwned($attachmentUuid, $device);
            $metadata = $this->decode($row->metadata_json);
            $knownOperation = isset($metadata['operations'][$operationId]);
            $metadata = $this->registerOperation($metadata, $operationId, $operationHash);

            if ($row->status === 'completed') {
                if (!$knownOperation) {
                    throw new DomainException('attachment_already_completed');
                }

                return $this->payload($row, $metadata);
            }

            $totalChunks = (int) ($metadata['totalChunks'] ?? 0);
            $chunkSize = (int) ($metadata['chunkSize'] ?? 0);
            if ($chunkIndex < 0 || $chunkIndex >= $totalChunks || $chunkSize <= 0) {
                throw new InvalidArgumentException('invalid_chunk_index');
            }
            $expectedSize = $chunkIndex === $totalChunks - 1
                ? (int) $row->file_size - ($chunkSize * ($totalChunks - 1))
                : $chunkSize;
            if ($size !== $expectedSize) {
                throw new InvalidArgumentException('chunk_size_mismatch');
            }

            $chunks = $metadata['chunks'] ?? [];
            $existing = $chunks[$chunkIndex] ?? null;
            if (is_array($existing)
                && ($existing['sha256'] ?? null) === $actualChecksum
                && (int) ($existing['size'] ?? 0) === $size
                && $this->validStoredChunk($disk, $existing, $attachmentUuid)
                && $this->fileMatches($disk->path($existing['path']), $size, $actualChecksum)) {
                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'metadata_json' => $this->encode($metadata),
                    'updated_at' => now('UTC'),
                ]);
                $row->metadata_json = $this->encode($metadata);

                return $this->payload($row, $metadata);
            }

            $path = $this->chunkPath($attachmentUuid, $chunkIndex, $actualChecksum);
            $this->writeChunkAtomically($disk, $path, $contents, $operationId);
            $previousPath = is_array($existing) ? ($existing['path'] ?? null) : null;
            $chunks[$chunkIndex] = [
                'size' => $size,
                'sha256' => $actualChecksum,
                'path' => $path,
            ];
            ksort($chunks);
            $metadata['chunks'] = $chunks;
            $now = now('UTC');

            DB::table(self::TABLE)->where('id', $row->id)->update([
                'status' => 'uploading',
                'metadata_json' => $this->encode($metadata),
                'updated_at' => $now,
            ]);
            $row->status = 'uploading';
            $row->metadata_json = $this->encode($metadata);
            $row->updated_at = $now;

            return $this->payload($row, $metadata);
        });

        if (is_string($previousPath)
            && $previousPath !== ''
            && $previousPath !== $this->chunkPath($attachmentUuid, $chunkIndex, $actualChecksum)
            && $this->isChunkPathSafe($previousPath, $attachmentUuid)) {
            $disk->delete($previousPath);
        }

        return $result;
    }

    public function complete(object $device, string $attachmentUuid, string $operationId): array
    {
        $this->ensureSchema();
        $attachmentUuid = $this->normalizeUuid($attachmentUuid);
        $operationId = $this->normalizeOperationId($operationId);
        $operationHash = hash('sha256', 'complete:'.$attachmentUuid);
        $disk = $this->disk();

        $result = DB::transaction(function () use ($device, $attachmentUuid, $operationId, $operationHash, $disk): array {
            $row = $this->findOwned($attachmentUuid, $device);
            $metadata = $this->decode($row->metadata_json);
            $metadata = $this->registerOperation($metadata, $operationId, $operationHash);

            if ($row->status !== 'completed') {
                $assembled = $this->assembleAtomically($disk, $row, $metadata, $operationId);
                $now = now('UTC');
                $metadata['completedAt'] = $now->toISOString();
                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'canonical_attachment_id' => $row->canonical_attachment_id ?? $row->attachment_uuid,
                    'file_size' => $assembled['fileSize'],
                    'sha256' => $assembled['sha256'],
                    'storage_path' => $assembled['storagePath'],
                    'status' => 'completed',
                    'metadata_json' => $this->encode($metadata),
                    'updated_at' => $now,
                ]);
                $row->canonical_attachment_id ??= $row->attachment_uuid;
                $row->file_size = $assembled['fileSize'];
                $row->sha256 = $assembled['sha256'];
                $row->storage_path = $assembled['storagePath'];
                $row->status = 'completed';
                $row->metadata_json = $this->encode($metadata);
                $row->updated_at = $now;
            } else {
                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'metadata_json' => $this->encode($metadata),
                    'updated_at' => now('UTC'),
                ]);
                $row->metadata_json = $this->encode($metadata);
            }

            return $this->payload($row, $metadata);
        });

        $disk->deleteDirectory(self::ROOT.'/'.$attachmentUuid.'/chunks');

        return $result;
    }

    public function download(object $device, string $attachmentUuid): array
    {
        $this->ensureSchema();
        $attachmentUuid = $this->normalizeUuid($attachmentUuid);
        $row = $this->findForSource($attachmentUuid, $device->source_id);
        if ($row->status !== 'completed' || !is_string($row->storage_path)) {
            throw new DomainException('attachment_not_ready');
        }
        if (!$this->isStoragePathSafe($row->storage_path)) {
            throw new RuntimeException('The attachment storage path is invalid.');
        }

        $disk = $this->disk();
        $root = realpath($disk->path(self::ROOT));
        $path = realpath($disk->path($row->storage_path));
        if ($root === false
            || $path === false
            || !str_starts_with($path, $root.DIRECTORY_SEPARATOR)
            || !is_file($path)) {
            throw new RuntimeException('The completed attachment content is unavailable.');
        }

        return [
            'path' => $path,
            'name' => $row->original_file_name ?? $attachmentUuid.'.bin',
            'mimeType' => $row->mime_type ?? 'application/octet-stream',
        ];
    }

    private function normalizeManifest(array $data): array
    {
        $data['attachmentUuid'] = $this->normalizeUuid((string) ($data['attachmentUuid'] ?? ''));
        $data['operationId'] = $this->normalizeOperationId((string) ($data['operationId'] ?? ''));
        $data['clientUuid'] = isset($data['clientUuid'])
            ? $this->normalizeUuid((string) $data['clientUuid'])
            : null;
        $data['primaryKey'] = $data['primaryKey'] ?? null;
        $data['attachmentReferenceKind'] = $data['attachmentReferenceKind'] ?? null;
        $data['canonicalAttachmentId'] = $data['canonicalAttachmentId'] ?? null;
        $data['fileSize'] = (int) ($data['fileSize'] ?? 0);
        $data['chunkSize'] = (int) ($data['chunkSize'] ?? 0);
        $data['totalChunks'] = (int) ($data['totalChunks'] ?? 0);
        $data['mimeType'] = strtolower(trim((string) ($data['mimeType'] ?? '')));
        $data['sha256'] = strtolower((string) ($data['sha256'] ?? ''));
        $data['originalFileName'] = (string) ($data['originalFileName'] ?? '');

        if ($data['fileSize'] <= 0) {
            throw new InvalidArgumentException('invalid_file_size');
        }
        if ($data['fileSize'] > $this->maxFileBytes()) {
            throw new InvalidArgumentException('file_too_large');
        }
        if ($data['chunkSize'] <= 0 || $data['chunkSize'] > $this->maxChunkBytes()) {
            throw new InvalidArgumentException('chunk_too_large');
        }
        $expectedChunks = (int) ceil($data['fileSize'] / $data['chunkSize']);
        if ($data['totalChunks'] !== $expectedChunks
            || $data['totalChunks'] < 1
            || $data['totalChunks'] > $this->maxChunks()) {
            throw new InvalidArgumentException('invalid_chunk_manifest');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $data['sha256'])) {
            throw new InvalidArgumentException('invalid_file_checksum');
        }
        if (!in_array($data['mimeType'], $this->allowedMimeTypes(), true)) {
            throw new InvalidArgumentException('invalid_mime_type');
        }
        if (!$this->isOriginalFileNameSafe($data['originalFileName'])) {
            throw new InvalidArgumentException('invalid_storage_path');
        }

        return $data;
    }

    private function assembleAtomically(
        FilesystemAdapter $disk,
        object $row,
        array $metadata,
        string $operationId
    ): array {
        $totalChunks = (int) ($metadata['totalChunks'] ?? 0);
        $chunks = $metadata['chunks'] ?? [];
        for ($index = 0; $index < $totalChunks; $index++) {
            if (!isset($chunks[$index]) || !$this->validStoredChunk($disk, $chunks[$index], $row->attachment_uuid)) {
                throw new DomainException('chunks_incomplete');
            }
        }

        $finalPath = $this->finalPath($row->attachment_uuid);
        $temporaryPath = $finalPath.'.assembling-'.$operationId.'-'.bin2hex(random_bytes(6));
        $disk->makeDirectory(dirname($finalPath));
        $target = null;

        try {
            $target = fopen($disk->path($temporaryPath), 'xb');
            if ($target === false) {
                throw new RuntimeException('The attachment assembly target could not be opened.');
            }

            $hash = hash_init('sha256');
            $assembledSize = 0;
            for ($index = 0; $index < $totalChunks; $index++) {
                $source = $disk->readStream($chunks[$index]['path']);
                if (!is_resource($source)) {
                    throw new RuntimeException('A persisted attachment chunk could not be read.');
                }

                try {
                    while (!feof($source)) {
                        $buffer = fread($source, 262144);
                        if ($buffer === false) {
                            throw new RuntimeException('A persisted attachment chunk could not be read.');
                        }
                        if ($buffer === '') {
                            continue;
                        }

                        $assembledSize += strlen($buffer);
                        if ($assembledSize > $this->maxFileBytes()) {
                            throw new InvalidArgumentException('file_too_large');
                        }
                        hash_update($hash, $buffer);
                        $this->writeAll($target, $buffer);
                    }
                } finally {
                    fclose($source);
                }
            }

            fclose($target);
            $target = null;
            $assembledChecksum = hash_final($hash);
            if ($assembledSize !== (int) $row->file_size
                || !hash_equals(strtolower((string) $row->sha256), $assembledChecksum)) {
                throw new InvalidArgumentException('checksum_mismatch');
            }
            $this->assertContentMimeType($disk->path($temporaryPath), (string) $row->mime_type);

            $deduplicatedPath = $this->findDeduplicatedPath($disk, $row);
            if ($deduplicatedPath !== null) {
                return [
                    'storagePath' => $deduplicatedPath,
                    'fileSize' => $assembledSize,
                    'sha256' => $assembledChecksum,
                ];
            }

            if ($disk->exists($finalPath)) {
                if (!$this->fileMatches($disk->path($finalPath), $assembledSize, $assembledChecksum)) {
                    throw new RuntimeException('The existing attachment content does not match its manifest.');
                }
            } elseif (!rename($disk->path($temporaryPath), $disk->path($finalPath))) {
                throw new RuntimeException('The assembled attachment could not be published.');
            }

            return [
                'storagePath' => $finalPath,
                'fileSize' => $assembledSize,
                'sha256' => $assembledChecksum,
            ];
        } finally {
            if (is_resource($target)) {
                fclose($target);
            }
            $disk->delete($temporaryPath);
        }
    }

    private function findDeduplicatedPath(FilesystemAdapter $disk, object $row): ?string
    {
        $candidates = DB::table(self::TABLE)
            ->where('source_id', $row->source_id)
            ->where('sha256', $row->sha256)
            ->where('status', 'completed')
            ->where('id', '!=', $row->id)
            ->whereNotNull('storage_path')
            ->orderBy('id')
            ->get(['storage_path']);

        foreach ($candidates as $candidate) {
            if (is_string($candidate->storage_path)
                && $this->isStoragePathSafe($candidate->storage_path)
                && $disk->exists($candidate->storage_path)
                && $this->fileMatches($disk->path($candidate->storage_path), (int) $row->file_size, (string) $row->sha256)) {
                return $candidate->storage_path;
            }
        }

        return null;
    }

    private function writeChunkAtomically(
        FilesystemAdapter $disk,
        string $path,
        string $contents,
        string $operationId
    ): void {
        if ($disk->exists($path)) {
            if ($this->fileMatches($disk->path($path), strlen($contents), hash('sha256', $contents))) {
                return;
            }
            $disk->delete($path);
        }

        $disk->makeDirectory(dirname($path));
        $temporaryPath = $path.'.uploading-'.$operationId.'-'.bin2hex(random_bytes(6));
        try {
            if (!$disk->put($temporaryPath, $contents)) {
                throw new RuntimeException('The attachment chunk could not be persisted.');
            }
            if ($disk->exists($path)) {
                return;
            }
            if (!$disk->move($temporaryPath, $path)) {
                throw new RuntimeException('The attachment chunk could not be published.');
            }
        } finally {
            $disk->delete($temporaryPath);
        }
    }

    private function payload(object $row, ?array $metadata = null): array
    {
        $metadata ??= $this->decode($row->metadata_json);
        $totalChunks = (int) ($metadata['totalChunks'] ?? 0);
        $completed = $row->status === 'completed';
        $received = [];

        if ($completed && $totalChunks > 0) {
            $received = range(0, $totalChunks - 1);
        } else {
            $disk = $this->disk();
            foreach (($metadata['chunks'] ?? []) as $index => $chunk) {
                if (is_array($chunk) && $this->validStoredChunk($disk, $chunk, $row->attachment_uuid)) {
                    $received[] = (int) $index;
                }
            }
            sort($received);
        }

        $missing = $completed || $totalChunks === 0
            ? []
            : array_values(array_diff(range(0, $totalChunks - 1), $received));

        return [
            'attachmentUuid' => $row->attachment_uuid,
            'operationId' => $row->operation_id,
            'entityType' => $row->entity_type,
            'primaryKey' => $row->primary_key,
            'clientUuid' => $row->client_uuid,
            'attachmentReferenceKind' => $row->attachment_reference_kind,
            'canonicalAttachmentId' => $row->canonical_attachment_id,
            'originalFileName' => $row->original_file_name,
            'mimeType' => $row->mime_type,
            'fileSize' => (int) $row->file_size,
            'sha256' => $row->sha256,
            'chunkSize' => (int) ($metadata['chunkSize'] ?? 0),
            'totalChunks' => $totalChunks,
            'receivedChunks' => $received,
            'missingChunks' => $missing,
            'status' => $row->status,
            'completed' => $completed,
            'completedAt' => $metadata['completedAt'] ?? null,
            'downloadUrl' => $completed
                ? '/api/desktop/v1/attachments/'.$row->attachment_uuid.'/content'
                : null,
            'createdAt' => $this->timestamp($row->created_at),
            'updatedAt' => $this->timestamp($row->updated_at),
        ];
    }

    private function validStoredChunk(FilesystemAdapter $disk, mixed $chunk, string $attachmentUuid): bool
    {
        if (!is_array($chunk)
            || !is_string($chunk['path'] ?? null)
            || !$this->isChunkPathSafe($chunk['path'], $attachmentUuid)
            || !preg_match('/^[a-f0-9]{64}$/', (string) ($chunk['sha256'] ?? ''))
            || !$disk->exists($chunk['path'])) {
            return false;
        }

        return (int) ($chunk['size'] ?? 0) > 0 && $disk->size($chunk['path']) === (int) $chunk['size'];
    }

    private function findOwned(string $attachmentUuid, object $device): object
    {
        $row = $this->findForSource($attachmentUuid, $device->source_id);
        $this->assertOwnedByDevice($row, $device);

        return $row;
    }

    private function findForSource(string $attachmentUuid, string $sourceId): object
    {
        $row = DB::table(self::TABLE)->where('attachment_uuid', $attachmentUuid)->first();
        if ($row === null) {
            throw new DomainException('attachment_not_found');
        }
        if (!hash_equals((string) $row->source_id, $sourceId)) {
            throw new DomainException('attachment_forbidden');
        }

        return $row;
    }

    private function assertOwnedByDevice(object $row, object $device): void
    {
        if (!hash_equals((string) $row->source_id, (string) $device->source_id)
            || !hash_equals((string) $row->device_id, (string) $device->device_id)) {
            throw new DomainException('attachment_forbidden');
        }
    }

    private function registerOperation(array $metadata, string $operationId, string $operationHash): array
    {
        $operations = $metadata['operations'] ?? [];
        if (isset($operations[$operationId])
            && !hash_equals((string) $operations[$operationId], $operationHash)) {
            throw new DomainException('operation_mismatch');
        }

        $operations[$operationId] = $operationHash;
        $metadata['operations'] = $operations;

        return $metadata;
    }

    private function normalizeUuid(string $value): string
    {
        $value = strtolower($value);
        if (!Str::isUuid($value)) {
            throw new InvalidArgumentException('invalid_attachment_uuid');
        }

        return $value;
    }

    private function normalizeOperationId(string $value): string
    {
        $value = strtolower($value);
        if (!Str::isUuid($value)) {
            throw new InvalidArgumentException('invalid_operation_id');
        }

        return $value;
    }

    private function isOriginalFileNameSafe(string $fileName): bool
    {
        return $fileName !== ''
            && trim($fileName) === $fileName
            && !in_array($fileName, ['.', '..'], true)
            && !preg_match('/[\x00-\x1F\x7F\/\\\\]/u', $fileName);
    }

    private function isStoragePathSafe(string $path): bool
    {
        $parts = explode('/', $path);

        return count($parts) === 5
            && implode('/', array_slice($parts, 0, 3)) === self::ROOT
            && Str::isUuid($parts[3])
            && $parts[4] === 'content'
            && !str_contains($path, '..')
            && !str_contains($path, '\\');
    }

    private function isChunkPathSafe(string $path, string $attachmentUuid): bool
    {
        $prefix = self::ROOT.'/'.$attachmentUuid.'/chunks/';
        $fileName = basename($path);

        return str_starts_with($path, $prefix)
            && !str_contains($path, '..')
            && !str_contains($path, '\\')
            && preg_match('/^[0-9]{6}-[a-f0-9]{64}$/', $fileName);
    }

    private function chunkPath(string $attachmentUuid, int $chunkIndex, string $checksum): string
    {
        return self::ROOT.'/'.$attachmentUuid.'/chunks/'.sprintf('%06d', $chunkIndex).'-'.$checksum;
    }

    private function finalPath(string $attachmentUuid): string
    {
        return self::ROOT.'/'.$attachmentUuid.'/content';
    }

    private function assertContentMimeType(string $path, string $declaredMimeType): void
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            throw new RuntimeException('The attachment content type could not be inspected.');
        }

        try {
            $detected = finfo_file($fileInfo, $path);
        } finally {
            finfo_close($fileInfo);
        }
        if (!is_string($detected) || !$this->mimeTypesMatch($declaredMimeType, strtolower($detected))) {
            throw new InvalidArgumentException('mime_content_mismatch');
        }
    }

    private function mimeTypesMatch(string $declared, string $detected): bool
    {
        if ($declared === $detected) {
            return true;
        }
        if ($declared === 'text/csv' && $detected === 'text/plain') {
            return true;
        }
        if ($declared === 'application/pdf' && $detected === 'application/x-pdf') {
            return true;
        }
        if (in_array($declared, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], true) && in_array($detected, ['application/zip', 'application/octet-stream'], true)) {
            return true;
        }
        if (in_array($declared, ['application/msword', 'application/vnd.ms-excel'], true)
            && in_array($detected, ['application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'], true)) {
            return true;
        }

        return false;
    }

    private function fileMatches(string $path, int $expectedSize, string $expectedChecksum): bool
    {
        if (!is_file($path) || filesize($path) !== $expectedSize) {
            return false;
        }

        $checksum = hash_file('sha256', $path);

        return is_string($checksum) && hash_equals(strtolower($expectedChecksum), $checksum);
    }

    private function writeAll($target, string $buffer): void
    {
        $offset = 0;
        $length = strlen($buffer);
        while ($offset < $length) {
            $written = fwrite($target, substr($buffer, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('The assembled attachment could not be written.');
            }
            $offset += $written;
        }
    }

    private function allowedMimeTypes(): array
    {
        $configured = config('desktop_sync.attachment_allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'video/mp4',
            'video/quicktime',
            'video/webm',
        ]);

        return array_map('strtolower', is_array($configured) ? $configured : []);
    }

    private function maxChunkBytes(): int
    {
        return max(1, (int) config('desktop_sync.attachment_max_chunk_bytes', self::DEFAULT_MAX_CHUNK_BYTES));
    }

    private function maxFileBytes(): int
    {
        return max(1, (int) config('desktop_sync.attachment_max_file_bytes', self::DEFAULT_MAX_FILE_BYTES));
    }

    private function maxChunks(): int
    {
        return max(1, (int) config('desktop_sync.attachment_max_chunks', 10000));
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('desktop_sync.attachment_disk', 'local'));
    }

    private function timestamp(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->toISOString();
    }

    private function ensureSchema(): void
    {
        if (!$this->schema->hasColumns(self::TABLE, [
            'id', 'attachment_uuid', 'operation_id', 'device_id', 'source_id', 'entity_type',
            'primary_key', 'client_uuid', 'attachment_reference_kind', 'canonical_attachment_id',
            'original_file_name', 'mime_type', 'file_size', 'sha256', 'storage_path', 'status',
            'metadata_json', 'created_at', 'updated_at',
        ])) {
            throw new RuntimeException('Desktop attachment synchronization is unavailable.');
        }
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decode(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
