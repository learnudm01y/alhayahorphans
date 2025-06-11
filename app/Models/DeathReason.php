<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeathReason extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'death_reasons';

    public function deadPeople()
    {
        return $this->hasMany(DeadPepole::class);
    }
}
