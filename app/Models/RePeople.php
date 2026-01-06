<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RePeople extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $table = 're_people';
    protected $fillable = [
        'registration_id',
        'sponsorship_status',
        'first_name',
        'first_name_normalized',
        'second_name',
        'second_name_normalized',
        'third_name',
        'third_name_normalized',
        'last_name',
        'last_name_normalized',
        'person_id',
        'person_birth_date',
        'person_age',
        'person_gender',
        'person_health_status',
        'person_type_of_guarantee',
        'person_note',
        'acadimic_degree'
    ];

    public function sponsorshipStatus()
    {
        return $this->belongsTo(SponsorshipStatus::class, 'sponsorship_status');
    }

    public function healthStatus()
    {
        return $this->belongsTo(HealthStatus::class, 'person_health_status');
    }

    public function guaranteeType()
    {
        return $this->belongsTo(TypeOfGuarantee::class, 'person_type_of_guarantee');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type');
    }
    public function attachments()
    {
        // إذا كان جدول attachments لا يحتوي على أعمدة polymorphic، استخدم علاقة hasMany بدلاً من morphMany
        return $this->hasMany(Attachment::class, 'person_identity_number', 'person_id');
    }

    /**
     * علاقة مع جدول Data للربط التلقائي
     * يربط registration_id مع file_id_number
     */
    public function dataRecord()
    {
        return $this->belongsTo(Data::class, 'registration_id', 'file_id_number');
    }
}
