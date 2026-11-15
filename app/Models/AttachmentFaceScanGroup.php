<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentFaceScanGroup extends Model
{
    protected $fillable = [
        'scan_id',
        'group_hash',
        'person_identity_number',
        'file_type',
        'status',
        'image_count',
        'processed_images',
        'last_attachment_id',
        'attempts',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(AttachmentFaceScan::class, 'scan_id');
    }
}
