<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'pref',
        'basic_enabled',
        'deceased_enabled',
        'family_enabled'
    ];
    protected $table = 'document_types';

    public function orphans()
    {
        return $this->hasMany(RePeople::class, 'document_type');
    }
}
