<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralCategory extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'general_category';

    public function data()
    {
        return $this->hasMany(Data::class, 'data_displacement_status');
    }
    
}
