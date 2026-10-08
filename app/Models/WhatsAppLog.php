<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppLog extends Model
{
    protected $table = 'whatsapp_logs';

    protected $fillable = [
        'phone',
        'direction',
        'msg_id',
        'type',
        'text',
        'media_id',
        'status',
        'error',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
