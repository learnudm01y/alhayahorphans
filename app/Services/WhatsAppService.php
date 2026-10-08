<?php

namespace App\Services;

use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'application/pdf' => 'pdf',
        'audio/mp4' => 'm4a',
        'audio/mpeg' => 'mp3',
        'video/mp4' => 'mp4',
    ];

    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.base_url'))
            && filled(config('services.whatsapp.api_key'))
            && filled(config('services.whatsapp.instance'));
    }

    public function sendText(string $phone, string $text): array
    {
        $payload = [
            'number' => $phone,
            'text' => $text,
        ];

        return $this->send($phone, 'text', $payload, $text);
    }

    public function fetchMedia(string $messageId): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'خدمة واتساب غير مهيأة: مفاتيح WHATSAPP_API_URL / WHATSAPP_API_KEY / WHATSAPP_INSTANCE مفقودة.'];
        }

        try {
            $response = $this->client()->timeout(30)->post(
                $this->endpoint('/chat/getBase64FromMediaMessage/' . $this->instance()),
                [
                    'message' => ['key' => ['id' => $messageId]],
                    'convertToMp4' => false,
                ]
            );

            if (!$response->successful()) {
                return ['ok' => false, 'error' => 'تعذر جلب الملف من واتساب.'];
            }

            $json = $response->json();
            $json = is_array($json) ? $json : [];

            $base64 = $json['base64'] ?? $json['data'] ?? null;

            if (blank($base64)) {
                $contentType = (string) $response->header('Content-Type');

                if (!str_contains($contentType, 'json')) {
                    $base64 = $response->body();
                }
            }

            if (blank($base64)) {
                return ['ok' => false, 'error' => 'تعذر تنزيل الملف من واتساب.'];
            }

            if (str_contains($base64, 'base64,')) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }

            $content = base64_decode($base64, true);

            if ($content === false) {
                return ['ok' => false, 'error' => 'تعذر فك ترميز الملف الوارد من واتساب.'];
            }

            $mime = $json['mimetype'] ?? $json['mime_type'] ?? null;
            $mime = filled($mime) ? (string) $mime : $this->sniffMime($content);

            return [
                'ok' => true,
                'content' => $content,
                'mime_type' => $mime,
                'extension' => self::MIME_EXT[$mime] ?? 'bin',
                'file_size' => (int) ($json['fileSize'] ?? $json['file_size'] ?? strlen($content)),
                'file_name' => $json['fileName'] ?? $json['file_name'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('فشل تنزيل وسيلة واتساب', ['message_id' => $messageId, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'انقطع الاتصال بخدمة واتساب أثناء تنزيل الملف. حاول مرة أخرى لاحقاً.'];
        }
    }

    private function send(string $phone, string $type, array $payload, ?string $logText = null): array
    {
        if (!$this->isConfigured()) {
            $error = 'خدمة واتساب غير مهيأة: مفاتيح WHATSAPP_API_URL / WHATSAPP_API_KEY / WHATSAPP_INSTANCE مفقودة.';
            $this->log($phone, 'outbound', $type, $logText, null, 'failed', $error, $payload);

            return ['ok' => false, 'msg_id' => null, 'error' => $error];
        }

        try {
            $response = $this->client()->timeout(15)->post(
                $this->endpoint('/message/sendText/' . $this->instance()),
                $payload
            );

            $msgId = (string) ($response->json('key.id') ?? $response->json('id') ?? '');

            if ($response->successful() && $msgId !== '') {
                $this->log($phone, 'outbound', $type, $logText, $msgId, 'sent', null, $payload);

                return ['ok' => true, 'msg_id' => $msgId, 'error' => null];
            }

            $providerError = (string) ($response->json('error.message') ?? $response->json('message') ?? '');
            $error = 'تعذر إرسال الرسالة إلى واتساب' . ($providerError !== '' ? ': ' . $providerError : '.');
            $this->log($phone, 'outbound', $type, $logText, null, 'failed', $error, $payload);

            return ['ok' => false, 'msg_id' => null, 'error' => $error];
        } catch (\Throwable $e) {
            Log::error('فشل إرسال رسالة واتساب', ['phone' => $phone, 'error' => $e->getMessage()]);

            $error = 'انقطع الاتصال بخدمة واتساب أثناء الإرسال. حاول مرة أخرى لاحقاً.';
            $this->log($phone, 'outbound', $type, $logText, null, 'failed', $error, $payload);

            return ['ok' => false, 'msg_id' => null, 'error' => $error];
        }
    }

    private function client()
    {
        return Http::withHeaders([
            'apikey' => (string) config('services.whatsapp.api_key'),
            'Content-Type' => 'application/json',
        ]);
    }

    private function instance(): string
    {
        return (string) config('services.whatsapp.instance');
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.whatsapp.base_url'), '/') . $path;
    }

    private function sniffMime(string $content): string
    {
        if (str_starts_with($content, "\xFF\xD8")) {
            return 'image/jpeg';
        }

        if (str_starts_with($content, "\x89PNG")) {
            return 'image/png';
        }

        if (str_starts_with($content, '%PDF')) {
            return 'application/pdf';
        }

        if (str_starts_with($content, 'RIFF') && str_contains(substr($content, 8, 20), 'WEBP')) {
            return 'image/webp';
        }

        if (strlen($content) > 12 && substr($content, 4, 4) === 'ftyp') {
            $brand = substr($content, 8, 4);

            if (in_array($brand, ['heic', 'heix', 'hevc', 'mif1'], true)) {
                return 'image/heic';
            }

            if ($brand === 'M4A ') {
                return 'audio/mp4';
            }

            if (in_array($brand, ['isom', 'mp42', 'iso2', 'dash'], true)) {
                return 'video/mp4';
            }
        }

        if (str_starts_with($content, 'ID3') || (isset($content[0]) && $content[0] === "\xFF")) {
            return 'audio/mpeg';
        }

        return 'application/octet-stream';
    }

    private function log(
        string $phone,
        string $direction,
        string $type,
        ?string $text,
        ?string $msgId,
        string $status,
        ?string $error = null,
        array $payload = []
    ): void {
        try {
            WhatsAppLog::create([
                'phone' => $phone,
                'direction' => $direction,
                'msg_id' => $msgId,
                'type' => $type,
                'text' => $text,
                'status' => $status,
                'error' => $error !== null ? mb_substr($error, 0, 500) : null,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('فشل تسجيل رسالة واتساب', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }
}
