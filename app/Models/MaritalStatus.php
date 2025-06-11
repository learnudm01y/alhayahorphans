<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaritalStatus extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'marital_status';

    public function data()
    {
        return $this->hasMany(Data::class, 'data_marital_status');
    }

    public function beneficiaries()
    {
        return $this->hasMany(Data::class, 'beneficiaries_marital_status');
    }
}
