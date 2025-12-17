<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorDocumentType extends Model
{
    use HasFactory;

    protected $table = 'sponsor_document_types';

    protected $fillable = [
        'sponsor_id',
        'document_type_id',
        'is_enabled',
        'basic_enabled',
        'family_enabled',
        'deceased_enabled',
        'is_required',
        'notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'basic_enabled' => 'boolean',
        'family_enabled' => 'boolean',
        'deceased_enabled' => 'boolean',
        'is_required' => 'boolean',
    ];

    /**
     * العلاقة مع الجمعية
     */
    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * العلاقة مع نوع الوثيقة
     */
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
