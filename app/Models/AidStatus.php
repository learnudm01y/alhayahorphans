<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AidStatus extends Model
{
    use HasFactory;
    protected $fillable = ['description'];

    public function beneficiaries()
    {
        return $this->hasMany(Data::class, 'beneficiaries_status');
    }
}
