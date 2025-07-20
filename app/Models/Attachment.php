<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_identity_number',
        'stored_file_name',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];
    protected $table = 'attachments';
}
