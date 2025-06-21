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
        'second_name',
        'third_name',
        'last_name',
        'person_id',
        'person_birth_date',
        'person_age',
        'person_gender',
        'person_health_status',
        'person_type_of_guarantee'
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
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
