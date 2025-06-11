<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeOfGuarantee extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'type_of_guarantee';

    public function orphans()
    {
        return $this->hasMany(RePeople::class, 'orphan_type_of_guarantee');
    }
}
