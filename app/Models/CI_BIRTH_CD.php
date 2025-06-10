<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CI_BIRTH_CD extends Model
{
    use HasFactory;
    protected $table = 'CI_BIRTH_CD';
    public function users()
    {
        return $this->hasMany(User::class, 'country_code', 'code');
    }

}
