<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerSyncAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_type',
        'entity_id',
        'payload',
        'status',
        'delivery_status',
        'user_id'
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
