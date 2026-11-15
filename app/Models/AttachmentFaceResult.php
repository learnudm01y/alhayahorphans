<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentFaceResult extends Model
{
    protected $fillable = [
        'scan_id',
        'decision',
        'person_identity_number',
        'file_type',
        'attachment_id',
        'matched_attachment_id',
        'distance',
        'review_status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = ['distance' => 'float', 'reviewed_at' => 'datetime'];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(AttachmentFaceScan::class, 'scan_id');
    }
}
