<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorshipStatus extends Model
{
    use HasFactory, \App\Traits\SyncsLookupToMobile;
    protected $fillable = ['description'];
    protected $table = 'sponsorship_statuses';

    public function orphans()
    {
        return $this->hasMany(RePeople::class, 'sponsorship_status');
    }
}
