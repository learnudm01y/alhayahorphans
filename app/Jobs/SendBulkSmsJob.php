<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\NsmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class SendBulkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries   = 1;
    public $timeout = 900;

    /** الحدود الآمنة حسب توثيق NSMS */
    private const BULK_CHUNK    = 500;  // رقم في طلب send الواحد
    private const EXCEL_CHUNK   = 1000; // صف في طلب sendvar (الحد الأقصى 5000)
    private const CHUNK_DELAY   = 2;    // ثانية بين الطلبات (حد 30 طلب/دقيقة)

    public function __construct(
        public string  $type,            // bulk | excel
        public int     $userId,
        public string  $sender,
        public string  $message,         // نص الرسالة أو قالب Excel
        public array   $mobiles = [],    // للجماعي
        public ?string $file = null,     // مسار ملف الصفوف للـ Excel
        public ?string $mobileColumn = null,
    ) {}

    public function handle(NsmsClient $sms): void
    {
        try {
            $this->type === 'excel' ? $this->sendExcel($sms) : $this->sendBulk($sms);
        } finally {
            if ($this->file) Storage::delete($this->file);
        }
    }

    private function sendBulk(NsmsClient $sms): void
    {
        foreach (array_chunk($this->mobiles, self::BULK_CHUNK) as $i => $chunk) {
            if ($i > 0) sleep(self::CHUNK_DELAY);

            $res = $sms->send($chunk, $this->message, $this->sender);
            $this->log($res, count($chunk));
            if ($this->mustStop($res)) break;
        }
    }

    private function sendExcel(NsmsClient $sms): void
    {
        $rows = json_decode(Storage::get($this->file), true)['rows'] ?? [];

        foreach (array_chunk($rows, self::EXCEL_CHUNK) as $i => $chunk) {
            if ($i > 0) sleep(self::CHUNK_DELAY);

            $res = $sms->sendVar($this->sender, $this->mobileColumn, $this->message, $chunk);
            $this->log($res, count($chunk));
            if ($this->mustStop($res)) break;
        }
    }

    private function log(array $res, int $count): void
    {
        SmsLog::fromResponse($res, [
            'user_id'    => $this->userId,
            'type'       => $this->type,
            'sender'     => $this->sender,
            'recipients' => $count,
            'message'    => $this->message,
        ]);
    }

    /** أخطاء لا فائدة من متابعة الدفعات بعدها */
    private function mustStop(array $res): bool
    {
        return !($res['status'] ?? false)
            && in_array($res['code'] ?? 0, [1002, 1003, 1101, 1401, 1402, 1602], true);
    }
}
