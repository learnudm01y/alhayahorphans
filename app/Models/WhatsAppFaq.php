<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppFaq extends Model
{
    protected $table = 'whatsapp_faq';

    protected $fillable = ['question', 'answer', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
