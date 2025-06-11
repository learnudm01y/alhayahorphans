<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'provinces';

    public function data()
    {
        return $this->hasMany(Data::class, 'province');
    }
}
