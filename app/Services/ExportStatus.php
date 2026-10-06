<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * تتبع حالة عمليات التصدير الجماعية (استمارات التحديث + التقارير الشاملة).
 *
 * يُخزَّن الحالة في الكاش ليقرأها الواجهة عبر endpoint مستقل
 * (admin/sponsors/export-status) دون المساس بأي وظيفة أو ميزة قائمة.
 */
class ExportStatus
{
    public const TYPE_FORMS  = 'forms';
    public const TYPE_FAMILY = 'family';

    public const STAGE_QUEUED    = 'queued';
    public const STAGE_RUNNING   = 'running';
    public const STAGE_COMPLETED = 'completed';
    public const STAGE_FAILED    = 'failed';

    /** مدة بقاء الحالة (24 ساعة) */
    protected const TTL = 86400;

    /**
     * بناء مفتاح الحالة المميز لعملية تصدير.
     */
    public static function key(string $type, int $sponsorId, ?int $statusId = null, bool $updatedOnly = false): string
    {
        return sprintf('export_status:%s:%d:%d:%d', $type, $sponsorId, (int) $statusId, $updatedOnly ? 1 : 0);
    }

    /**
     * تسجيل العملية كـ «في الانتظار» (بعد dispatch وقبل تشغيل عامل الطابور).
     */
    public static function start(string $key, array $payload): void
    {
        static::write($key, array_merge([
            'stage'     => self::STAGE_QUEUED,
            'processed' => 0,
            'success'   => 0,
            'failed'    => 0,
        ], $payload));
    }

    /**
     * تحديث الحالة إلى «قيد التشغيل» مع بيانات ثابتة (الإجمالي مثلاً).
     */
    public static function running(string $key, array $payload = []): void
    {
        static::write($key, array_merge(static::read($key) ?: [
            'processed' => 0, 'success' => 0, 'failed' => 0,
        ], $payload, ['stage' => self::STAGE_RUNNING]));
    }

    /**
     * تحديث عدّادات التقدّم (يُستدعى بعد كل دفعة).
     */
    public static function progress(string $key, int $processed, int $success, int $failed, ?int $total = null): void
    {
        $payload = [
            'stage'     => self::STAGE_RUNNING,
            'processed' => $processed,
            'success'   => $success,
            'failed'    => $failed,
        ];

        if ($total !== null) {
            $payload['total'] = $total;
        }

        static::write($key, array_merge(static::read($key) ?: [], $payload));
    }

    /**
     * إنهاء العملية بنجاح.
     */
    public static function finish(string $key, array $payload = []): void
    {
        static::write($key, array_merge(static::read($key) ?: [], $payload, [
            'stage'     => self::STAGE_COMPLETED,
            'processed' => (int) ($payload['processed'] ?? 0),
            'success'   => (int) ($payload['success'] ?? 0),
            'failed'    => (int) ($payload['failed'] ?? 0),
        ]));
    }

    /**
     * تعليم العملية كفاشلة (مع بقاء العدّادات السابقة إن وُجدت).
     */
    public static function fail(string $key, string $message): void
    {
        static::write($key, array_merge(static::read($key) ?: [], [
            'stage'   => self::STAGE_FAILED,
            'message' => $message,
        ]));
    }

    /**
     * قراءة الحالة المخزَّنة (أو null إن لم تكن موجودة).
     */
    public static function get(string $key): ?array
    {
        return static::read($key);
    }

    protected static function read(string $key): ?array
    {
        $data = Cache::get($key);

        return is_array($data) ? $data : null;
    }

    protected static function write(string $key, array $payload): void
    {
        $payload['updated_at'] = now()->toDateTimeString();

        Cache::put($key, $payload, self::TTL);
    }
}
