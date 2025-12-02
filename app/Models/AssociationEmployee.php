<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssociationEmployee extends Model
{
    use HasFactory;

    protected $fillable = ['sponsor_id', 'employee_name'];

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }
}
