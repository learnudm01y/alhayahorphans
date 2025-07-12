<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileOrganizationService
{
    /**
     * Organize files into record-based folder structure
     */
    public function organizeFileByRecord(string $recordNumber, UploadedFile $file, string $fileType, ?string $personId = null): array
    {
        // Ensure 6-digit record number with leading zeros
        $recordNumber = str_pad($recordNumber, 6, '0', STR_PAD_LEFT);

        // Create hierarchical folder structure
        $baseFolder = "uploads/{$recordNumber}";
        $typeFolder = $this->getTypeFolderName($fileType);
        $finalPath = "{$baseFolder}/{$typeFolder}";

        // Generate unique filename with metadata
        $fileName = $this->generateFileName($file, $fileType, $recordNumber, $personId);

        // Store file with folder auto-creation
        $storedPath = $file->storeAs("public/{$finalPath}", $fileName);

        // Update file registry
        $this->updateFileRegistry($recordNumber, $fileName, $storedPath, $fileType, $personId);

        return [
            'path' => "storage/{$finalPath}/{$fileName}",
            'filename' => $fileName,
            'folder' => $finalPath,
            'record_number' => $recordNumber
        ];
    }

    /**
     * Merge files when duplicate record numbers are uploaded
     */
    public function mergeFilesToExistingRecord(string $recordNumber, array $newFiles): array
    {
        $recordNumber = str_pad($recordNumber, 6, '0', STR_PAD_LEFT);
        $results = [];

        foreach ($newFiles as $file) {
            // Check for duplicates before merging
            $isDuplicate = $this->checkForDuplicate($recordNumber, $file);

            if (!$isDuplicate) {
                $result = $this->organizeFileByRecord($recordNumber, $file['file'], $file['type'], $file['person_id'] ?? null);
                $results[] = $result;
            } else {
                Log::info("Duplicate file skipped for record {$recordNumber}: {$file['file']->getClientOriginalName()}");
            }
        }

        return $results;
    }

    /**
     * Generate intelligent file names with metadata
     */
    private function generateFileName(UploadedFile $file, string $fileType, string $recordNumber, ?string $personId = null): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Ymd_His');

        $nameParts = [
            $fileType,
            $recordNumber,
            $personId ? "person_{$personId}" : null,
            $timestamp
        ];

        $cleanName = implode('_', array_filter($nameParts));
        return "{$cleanName}.{$extension}";
    }

    /**
     * Get folder name based on file type
     */
    private function getTypeFolderName(string $fileType): string
    {
        return match ($fileType) {
            'image' => 'images',
            'pdf' => 'documents/pdf',
            'excel' => 'documents/excel',
            'word' => 'documents/word',
            'archive' => 'archives',
            'identity' => 'identity_docs',
            'medical' => 'medical_docs',
            'legal' => 'legal_docs',
            default => 'misc'
        };
    }

    /**
     * Check for file duplicates using hash comparison
     */
    private function checkForDuplicate(string $recordNumber, array $file): bool
    {
        $fileHash = hash_file('md5', $file['file']->getPathname());

        return Attachment::where('record_number', $recordNumber)
            ->where('file_hash', $fileHash)
            ->exists();
    }

    /**
     * Update file registry with comprehensive metadata
     */
    private function updateFileRegistry(string $recordNumber, string $fileName, string $storedPath, string $fileType, ?string $personId = null): void
    {
        Attachment::create([
            'record_number' => $recordNumber,
            'person_identity_number' => $personId,
            'stored_file_name' => $fileName,
            'file_path' => "storage/" . str_replace('public/', '', $storedPath),
            'file_type' => $fileType,
            'file_hash' => hash_file('md5', storage_path("app/{$storedPath}")),
            'file_size' => Storage::size("public/" . str_replace('public/', '', $storedPath)),
            'upload_date' => now(),
            'compression_status' => $this->needsCompression($fileType) ? 'pending' : 'not_required',
            'access_permissions' => $this->getDefaultPermissions($fileType),
            'metadata' => json_encode($this->extractMetadata($storedPath, $fileType))
        ]);
    }

    /**
     * Extract file metadata
     */
    private function extractMetadata(string $filePath, string $fileType): array
    {
        $metadata = [
            'uploaded_at' => now()->toISOString(),
            'file_type' => $fileType,
            'storage_location' => 'local'
        ];

        // Add type-specific metadata using match expression
        $typeSpecificMetadata = match ($fileType) {
            'image' => $this->getImageMetadata($filePath),
            'pdf' => $this->getPdfMetadata($filePath),
            'excel' => $this->getExcelMetadata($filePath),
            default => []
        };

        return array_merge($metadata, $typeSpecificMetadata);
    }

    private function getImageMetadata(string $filePath): array
    {
        try {
            $fullPath = storage_path("app/{$filePath}");
            if (file_exists($fullPath)) {
                $imageInfo = getimagesize($fullPath);
                return [
                    'width' => $imageInfo[0] ?? null,
                    'height' => $imageInfo[1] ?? null,
                    'mime_type' => $imageInfo['mime'] ?? null
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to extract image metadata: " . $e->getMessage());
        }

        return [];
    }

    private function getPdfMetadata(string $filePath): array
    {
        // Implement PDF metadata extraction
        return ['pages' => null, 'title' => null];
    }

    private function getExcelMetadata(string $filePath): array
    {
        // Implement Excel metadata extraction
        return ['sheets' => null, 'rows' => null];
    }

    private function needsCompression(string $fileType): bool
    {
        return in_array($fileType, ['image', 'pdf']);
    }

    private function getDefaultPermissions(string $fileType): string
    {
        return match ($fileType) {
            'identity', 'legal' => 'restricted',
            'medical' => 'confidential',
            'image', 'pdf', 'excel' => 'standard',
            default => 'standard'
        };
    }
}
