<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentFaceEmbedding extends Model
{
    protected $fillable = [
        'attachment_id',
        'model_version',
        'status',
        'embedding',
        'reason_code',
        'source_hash',
        'source_updated_at',
        'detection_score',
        'face_count',
        'face_box',
        'metrics',
        'processed_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'face_box' => 'array',
        'metrics' => 'array',
        'detection_score' => 'float',
        'face_count' => 'integer',
        'source_updated_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
