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

    /**
     * العلاقة مع الجمعيات
     */
    public function sponsors()
    {
        return $this->belongsToMany(Sponsor::class, 'sponsor_document_types')
            ->withPivot(['is_enabled', 'basic_enabled', 'family_enabled', 'deceased_enabled', 'is_required', 'notes'])
            ->withTimestamps();
    }

    /**
     * العلاقة مع إعدادات الوثائق للجمعيات
     */
    public function sponsorDocumentTypes()
    {
        return $this->hasMany(SponsorDocumentType::class);
    }
}
