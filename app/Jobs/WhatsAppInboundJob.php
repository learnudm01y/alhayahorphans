<?php

namespace App\Jobs;

use App\Models\WhatsAppLog;
use App\Services\WhatsAppFlowService;
use App\Services\WhatsAppMediaService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class WhatsAppInboundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;
    public $timeout = 120;

    private const STATUS_MAP = [
        'ERROR' => 'failed',
        'PENDING' => 'pending',
        'SENT' => 'sent',
        'DELIVERED' => 'delivered',
        'READ' => 'read',
        'PLAYED' => 'read',
        'DELETED' => 'deleted',
    ];

    public function __construct(
        public string $event,
        public array $data,
    ) {
    }

    public function handle(WhatsAppService $whatsapp, WhatsAppFlowService $flow, WhatsAppMediaService $media): void
    {
        if ($this->event === 'messages.upsert') {
            foreach ($this->asItems($this->data, 'key') as $message) {
                $this->handleMessage($message, $whatsapp, $flow, $media);
            }
        } elseif ($this->event === 'messages.update') {
            foreach ($this->asItems($this->data, 'keyId') as $update) {
                $this->handleStatus($update);
            }
        }
    }

    private function asItems(array $data, string $markerKey): array
    {
        if (isset($data[$markerKey]) || isset($data['key'])) {
            return [$data];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    private function handleMessage(array $message, WhatsAppService $whatsapp, WhatsAppFlowService $flow, WhatsAppMediaService $media): void
    {
        $id = (string) ($message['key']['id'] ?? $message['keyId'] ?? '');
        $fromMe = filter_var($message['key']['fromMe'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($id === '' || $fromMe) {
            return;
        }

        $phone = $this->extractPhone($message['key'] ?? []);

        if ($phone === '') {
            return;
        }

        $doneKey = 'whatsapp:done:' . $id;
        $lockKey = 'whatsapp:lock:' . $id;

        if (Cache::get($doneKey)) {
            return;
        }

        if (!Cache::add($lockKey, 1, 60)) {
            return;
        }

        $phoneLock = Cache::lock('whatsapp:phone:' . $phone, 15);

        try {
            $phoneLock->block(5);

            $body = is_array($message['message'] ?? null) ? $message['message'] : [];
            [$type, $text] = $this->extractContent($body, (string) ($message['messageType'] ?? ''));

            $isFirstContact = !WhatsAppLog::where('phone', $phone)
                ->where('direction', 'inbound')
                ->exists();

            WhatsAppLog::create([
                'phone' => $phone,
                'direction' => 'inbound',
                'msg_id' => $id,
                'type' => $type,
                'text' => $text,
                'media_id' => $type === 'text' ? null : $id,
                'status' => 'received',
                'payload' => $message,
            ]);

            $reply = null;

            if ($type === 'text') {
                $reply = $flow->handle($phone, (string) $text, $isFirstContact);
            } else {
                $reply = $flow->handleMedia($phone, $id, $type, $text);
            }

            if (!$flow->hasState($phone)) {
                $media->cleanup($phone);
            }

            if ($reply !== null && $reply !== '') {
                $whatsapp->sendText($phone, $reply);
            }

            Cache::put($doneKey, 1, now()->addDays(7));
        } finally {
            $phoneLock->release();
            Cache::forget($lockKey);
        }
    }

    private function extractPhone(array $key): string
    {
        foreach ([$key['remoteJid'] ?? '', $key['remoteJidAlt'] ?? ''] as $jid) {
            $jid = (string) $jid;

            if (str_ends_with($jid, '@s.whatsapp.net')) {
                $phone = substr($jid, 0, -strlen('@s.whatsapp.net'));

                if ($phone !== '' && ctype_digit($phone)) {
                    return $phone;
                }
            }
        }

        return '';
    }

    private function extractContent(array $body, string $rawType): array
    {
        $map = [
            'conversation' => 'text',
            'extendedTextMessage' => 'text',
            'imageMessage' => 'image',
            'videoMessage' => 'video',
            'audioMessage' => 'audio',
            'documentMessage' => 'document',
            'stickerMessage' => 'sticker',
        ];

        $detected = isset($map[$rawType]) ? $rawType : null;

        if ($detected === null) {
            foreach (array_keys($map) as $key) {
                if (isset($body[$key])) {
                    $detected = $key;
                    break;
                }
            }
        }

        if ($detected === null) {
            return ['unknown', null];
        }

        $value = $body[$detected] ?? null;

        if (is_string($value)) {
            $text = $value;
        } elseif (is_array($value)) {
            $text = $value['text'] ?? $value['caption'] ?? null;
        } else {
            $text = null;
        }

        return [$map[$detected], $text !== null ? (string) $text : null];
    }

    private function handleStatus(array $update): void
    {
        $id = (string) ($update['keyId'] ?? $update['key']['id'] ?? '');
        $state = strtoupper((string) ($update['status'] ?? $update['update']['status'] ?? ''));

        if ($id === '' || $state === '') {
            return;
        }

        $log = WhatsAppLog::where('msg_id', $id)
            ->where('direction', 'outbound')
            ->first();

        if (!$log) {
            return;
        }

        $mapped = self::STATUS_MAP[$state] ?? strtolower($state);
        $fields = ['status' => $mapped];

        if ($mapped === 'failed') {
            $providerError = (string) ($update['errorMessage'] ?? $update['error'] ?? '');
            $fields['error'] = mb_substr('فشل التسليم' . ($providerError !== '' ? ': ' . $providerError : '.'), 0, 500);
        }

        $log->update($fields);
    }
}
