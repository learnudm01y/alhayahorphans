<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentTrash extends Model
{
    protected $table = 'attachment_trash';

    protected $fillable = [
        'attachment_id',
        'snapshot',
        'original_path',
        'trashed_path',
        'scan_id',
        'result_id',
        'deleted_by',
        'reason',
        'status',
        'trashed_at',
        'restore_deadline_at',
        'restored_at',
        'purged_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'trashed_at' => 'datetime',
        'restore_deadline_at' => 'datetime',
        'restored_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(AttachmentFaceScan::class, 'scan_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AttachmentFaceResult::class, 'result_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isExpired(): bool
    {
        return $this->restore_deadline_at !== null && $this->restore_deadline_at->isPast();
    }
}
