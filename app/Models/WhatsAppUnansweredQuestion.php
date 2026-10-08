<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppUnansweredQuestion extends Model
{
    protected $table = 'whatsapp_unanswered_questions';

    protected $fillable = [
        'phone',
        'question',
    ];
}
