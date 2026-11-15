<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttachmentFaceScan extends Model
{
    public const ACTIVE_STATUSES = ['queued', 'running', 'stopping'];

    protected $fillable = [
        'requested_by',
        'status',
        'person_identity_number',
        'model_version',
        'auto_threshold',
        'review_threshold',
        'total_groups',
        'processed_groups',
        'total_images',
        'processed_images',
        'duplicate_count',
        'review_count',
        'trashed_count',
        'last_error',
        'prepared_at',
        'started_at',
        'finished_at',
        'expires_at',
    ];

    protected $casts = [
        'auto_threshold' => 'float',
        'review_threshold' => 'float',
        'prepared_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(AttachmentFaceScanGroup::class, 'scan_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AttachmentFaceResult::class, 'scan_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttachmentFaceScanEvent::class, 'scan_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
