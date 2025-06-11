<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeadPepole extends Model
{
    use HasFactory;
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    protected $fillable = [
        // father
        'father_first_name',
        'father_second_name',
        'father_third_name',
        'father_last_name',
        'father_id',
        'father_death_date',
        'father_death_reason',
        'father_death_certificate',
        // mother
        'mother_first_name',
        'mother_second_name',
        'mother_third_name',
        'mother_last_name',
        'mother_id',
        'mother_death_date',
        'mother_death_reason',
        'mother_death_certificate',
    ];

    public function fatherDeathReason()
    {
        return $this->belongsTo(DeathReason::class, 'father_death_reason');
    }

    public function motherDeathReason()
    {
        return $this->belongsTo(DeathReason::class, 'mother_death_reason');
    }
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
