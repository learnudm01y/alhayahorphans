<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorshipStatus extends Model
{
    use HasFactory;
    protected $fillable = ['description'];

    public function orphans()
    {
        return $this->hasMany(Orphan::class, 'sponsorship_status');
    }
}
