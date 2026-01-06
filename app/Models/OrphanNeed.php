<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrphanNeed extends Model
{
    use HasFactory;

    protected $fillable = ['description'];
    protected $table = 'orphan_needs';
}
