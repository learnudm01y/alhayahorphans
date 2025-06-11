<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicDegree extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'academic_degrees';

    public function data()
    {
        return $this->hasMany(Data::class);
    }
}
