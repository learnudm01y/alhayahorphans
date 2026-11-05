<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberTransferRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'member_transfer_requests';

    /**
     * هل جدول الطابور مثبَّت في هذه الاستضافة؟
     *
     * حارس انحراف المخطط المتعارف عليه في هذا المستودع: شاشة الإدارة والاستمارة
     * يجب ألا تتعطّلا بميزة لم تُنفّذ الـ migration الخاصة بها بعد.
     */
    public static function tableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = \Illuminate\Support\Facades\Schema::hasTable((new static)->getTable());
        }

        return $exists;
    }

    protected $fillable = [
        'data_id',
        'file_id_number',
        'source_file_id_number',
        'identity_number',
        'member_snapshot',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'data_id' => 'integer',
        'file_id_number' => 'integer',
        'source_file_id_number' => 'integer',
        'identity_number' => 'integer',
        'member_snapshot' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function data()
    {
        return $this->belongsTo(Data::class, 'data_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * تسجيل طلب معلّق لفرد مرتبط بملف معيل آخر.
     * الإرسال المتكرر لنفس الملف ونفس الهوية يحدّث اللقطة بدل تضاعف الصفوف.
     */
    public static function recordPending(int $dataId, int $fileIdNumber, int $identityNumber, ?int $sourceFileIdNumber, array $snapshot): self
    {
        $request = static::firstOrNew([
            'file_id_number' => $fileIdNumber,
            'identity_number' => $identityNumber,
            'status' => self::STATUS_PENDING,
        ]);

        $request->fill([
            'data_id' => $dataId,
            'source_file_id_number' => $sourceFileIdNumber,
            'member_snapshot' => $snapshot,
            'reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $request->save();

        return $request;
    }
}
