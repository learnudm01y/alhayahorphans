<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory, \App\Traits\SyncsLookupToMobile;
    protected $fillable = ['city', 'province_id'];
    protected $table = 'city';

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }
}
