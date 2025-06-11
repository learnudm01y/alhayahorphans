<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeOfAccommodation extends Model
{
    use HasFactory;

    protected $fillable = ['description'];
    protected $table = 'type_of_accommodation';
}
