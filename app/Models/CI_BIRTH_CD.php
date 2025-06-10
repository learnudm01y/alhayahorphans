<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CI_BIRTH_CD extends Model
{
    use HasFactory;
    protected $table = 'ci_birth_cd';
    public function users()
    {
        return $this->hasMany(User::class, 'country_code', 'code');
    }

}
