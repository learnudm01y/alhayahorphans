<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestStatus extends Model
{
    use HasFactory;
    protected $fillable = ['description'];

    public function data()
    {
        return $this->hasMany(Data::class, 'request_status');
    }
}
