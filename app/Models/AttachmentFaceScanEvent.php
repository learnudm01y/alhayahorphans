<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentFaceScanEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['scan_id', 'user_id', 'event', 'metadata', 'created_at'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(AttachmentFaceScan::class, 'scan_id');
    }
}
