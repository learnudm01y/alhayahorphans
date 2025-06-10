<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RePeople extends Model
{
    use HasFactory;
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    protected $fillable = [
        'registration_id','sponsorship_status',
        'first_name','second_name','third_name','last_name',
        'orphan_id','orphan_birth_date','orphan_age','orphan_gender',
        'orphan_health_status','orphan_birth_certificate','orphan_photo',
        'orphan_type_of_guarantee'
    ];

    public function sponsorshipStatus()
    {
        return $this->belongsTo(SponsorshipStatus::class, 'sponsorship_status');
    }

    public function healthStatus()
    {
        return $this->belongsTo(HealthStatus::class, 'orphan_health_status');
    }

    public function guaranteeType()
    {
        return $this->belongsTo(TypeOfGuarantee::class, 'orphan_type_of_guarantee');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type');
    }
}
