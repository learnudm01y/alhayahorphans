<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppMediaService
{
    public const MAX_BYTES = 5242880;

    public const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    public function ingest(string $phone, string $messageId, string $mediaType): array
    {
        if (!in_array($mediaType, ['image', 'document'], true)) {
            return ['ok' => false, 'error' => 'يُقبل فقط الصور وملفات PDF. أعد الإرسال بصورة أو ملف PDF.'];
        }

        $fetched = $this->whatsapp->fetchMedia($messageId);

        if (($fetched['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => (string) ($fetched['error'] ?? 'تعذر تنزيل الملف.')];
        }

        $content = (string) ($fetched['content'] ?? '');
        $mime = strtolower((string) ($fetched['mime_type'] ?? ''));

        if ($content === '') {
            return ['ok' => false, 'error' => 'الملف فارغ. أعد الإرسال.'];
        }

        if (!isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'صيغة الملف غير مدعومة. يُقبل: JPG وPNG وGIF وWEBP وHEIC وPDF فقط.'];
        }

        if (strlen($content) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'حجم الملف يتجاوز 5 ميغابايت. أعد الإرسال بصورة أصغر.'];
        }

        $path = 'temp_uploads/whatsapp/' . $phone . '/' . $messageId . '.' . self::ALLOWED_MIME[$mime];

        try {
            Storage::disk('public')->put($path, $content);
        } catch (\Throwable $e) {
            Log::error('فشل حفظ مرفق واتساب مؤقت', ['phone' => $phone, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'تعذر حفظ الملف مؤقتاً. حاول مرة أخرى.'];
        }

        return [
            'ok' => true,
            'error' => null,
            'temp_path' => $path,
            'extension' => self::ALLOWED_MIME[$mime],
            'mime_type' => $mime,
            'size' => strlen($content),
            'hash' => sha1($content),
        ];
    }

    public function discard(?string $tempPath): void
    {
        if (blank($tempPath)) {
            return;
        }

        try {
            Storage::disk('public')->delete($tempPath);
        } catch (\Throwable $e) {
            Log::warning('فشل حذف مرفق واتساب مؤقت', ['path' => $tempPath, 'error' => $e->getMessage()]);
        }
    }

    public function cleanup(string $phone): void
    {
        try {
            $dir = 'temp_uploads/whatsapp/' . $phone;

            if (Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->deleteDirectory($dir);
            }
        } catch (\Throwable $e) {
            Log::warning('فشل تنظيف مجلد مرفقات واتساب', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }
}
