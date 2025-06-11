<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousingStatus extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'housing_status';

    public function data()
    {
        return $this->hasMany(Data::class, 'data_housing_status');
    }
}
