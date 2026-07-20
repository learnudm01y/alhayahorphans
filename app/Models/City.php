<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory, \App\Traits\SyncsLookupToMobile;
    protected $fillable = ['city'];
    protected $table = 'city';
}
