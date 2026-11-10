<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * تتبّع تقدّم عملية حفظ الاستمارة على السيرفر (المعالجة + رفع المرفقات).
 * الحالة تُخزَّن في الكاش بمفتاح عشوائي يرسله المتصفح، ويقرأها endpoint الاستعلام.
 *
 * ملاحظة: أي فشل في الكاش لا يجب أن يكسر عملية الحفظ نفسها.
 */
class PortalSaveProgress
{
    private const TTL_SECONDS = 900;

    private ?string $token;
    private array $state;
    private int $filesTotal = 0;
    private int $filesDone = 0;
    private int $bytesTotal = 0;
    private int $bytesDone = 0;
    private float $filesStartedAt = 0.0;

    public function __construct(?string $token)
    {
        $this->token = $token;
        $this->state = [
            'status' => 'running',
            'percent' => 0,
            'message' => 'جاري بدء المعالجة...',
            'files_done' => 0,
            'files_total' => 0,
            'bytes_done' => 0,
            'bytes_total' => 0,
            'server_speed_bps' => 0,
        ];
    }

    public static function sanitizeToken($token): ?string
    {
        $token = is_string($token) ? trim($token) : '';

        return preg_match('/^[A-Za-z0-9\-]{16,64}$/', $token) === 1 ? $token : null;
    }

    public static function key(string $token): string
    {
        return 'portal_save_progress:' . $token;
    }

    public static function read(string $token): array
    {
        try {
            $data = Cache::get(self::key($token));
        } catch (\Throwable $e) {
            $data = null;
        }

        return is_array($data) ? $data : ['status' => 'pending', 'percent' => 0, 'message' => ''];
    }

    /** نسبة مئوية (0-100) لمرحلة المعالجة على السيرفر + رسالة. */
    public function update(int $percent, string $message): void
    {
        // النسبة لا تتراجع أبداً
        $this->state['percent'] = max((int) $this->state['percent'], min(99, $percent));
        $this->state['message'] = $message;
        $this->put();
    }

    public function startFiles(int $total, int $bytesTotal): void
    {
        $this->filesTotal = $total;
        $this->bytesTotal = $bytesTotal;
        $this->filesDone = 0;
        $this->bytesDone = 0;
        $this->filesStartedAt = microtime(true);
        $this->state['files_total'] = $total;
        $this->state['bytes_total'] = $bytesTotal;
        $this->update(45, 'جاري تجهيز المرفقات للرفع...');
    }

    public function fileStart(string $label): void
    {
        $next = min($this->filesDone + 1, max($this->filesTotal, 1));
        $this->state['message'] = "جاري رفع الملف {$next} من {$this->filesTotal}: {$label}";
        $this->put();
    }

    public function fileDone(int $bytes): void
    {
        $this->filesDone++;
        $this->bytesDone += max(0, $bytes);

        $elapsed = max(0.001, microtime(true) - $this->filesStartedAt);
        $this->state['files_done'] = $this->filesDone;
        $this->state['bytes_done'] = $this->bytesDone;
        $this->state['server_speed_bps'] = (int) round($this->bytesDone / $elapsed);

        $fraction = $this->bytesTotal > 0
            ? $this->bytesDone / $this->bytesTotal
            : ($this->filesTotal > 0 ? $this->filesDone / $this->filesTotal : 1);

        // مرحلة الملفات تغطي 45% → 90%
        $this->update(45 + (int) round(45 * min(1, $fraction)), $this->state['message']);
    }

    public function done(string $message = 'تم الحفظ بنجاح', array $extra = []): void
    {
        // بيانات إضافية اختيارية (مثل رابط التحويل redirect) تصل للواجهة مع حالة done
        foreach ($extra as $key => $value) {
            $this->state[$key] = $value;
        }
        $this->state['status'] = 'done';
        $this->state['percent'] = 100;
        $this->state['message'] = $message;
        $this->put();
    }

    public function fail(string $message): void
    {
        $this->state['status'] = 'failed';
        $this->state['message'] = $message;
        $this->put();
    }

    private function put(): void
    {
        if ($this->token === null) {
            return;
        }

        try {
            $this->state['updated_at'] = microtime(true);
            Cache::put(self::key($this->token), $this->state, self::TTL_SECONDS);
        } catch (\Throwable $e) {
            // التتبّع اختياري: لا نكسر الحفظ بسببه
        }
    }
}
