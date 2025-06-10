<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeOfGuarantee extends Model
{
    use HasFactory;
    protected $fillable = ['description'];

    public function orphans()
    {
        return $this->hasMany(Orphan::class, 'orphan_type_of_guarantee');
    }
}
