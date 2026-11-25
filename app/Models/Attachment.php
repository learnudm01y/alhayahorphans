<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_identity_number',
        'record_number',
        'stored_file_name',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        'document_enhancement_status',
        'document_enhancement_metadata',
    ];

    protected $casts = [
        'document_enhancement_metadata' => 'array',
    ];

    protected $table = 'attachments';
}
