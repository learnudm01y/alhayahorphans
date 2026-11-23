<?php

namespace App\Services;

class AttachmentFilenameGenerator
{
    public function generate($fileType, string $fileIdNumber, string $personIdentityNumber, string $extension): string
    {
        $safeFileType = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $fileType) ?: 'doc';
        $extension = ltrim(strtolower($extension), '.');

        return "{$safeFileType}_{$fileIdNumber}_{$personIdentityNumber}_"
            . date('Ymd_His') . '_' . uniqid() . ".{$extension}";
    }
}
