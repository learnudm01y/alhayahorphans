<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Data extends Model
{
    use HasFactory;

    protected $table = 'data';

    protected $fillable = [
        'file_id_number',
        'data_section_id',
        'data_id_number',
        'data_first_name',
        'data_father_name',
        'data_grand_father_name',
        'data_family_name',
        'data_relationship',
        'data_birth_date',
        'data_gender',
        'data_phone_number',
        'data_alt_phone_number',
        'data_number_of_individuals',
        'data_marital_status',
        'data_academic_qualification',
        'data_displacement_status',
        'data_address_before_displacement',
        'data_current_address',
        'data_city',
        'data_province',
        'data_health_status',
        'data_description_needs',
        'data_number_mail',
        'data_number_female',
        'data_number_of_individuals_with_chronic_diseases',
        'data_number_of_people_with_special_needs',
        'data_employment_status_breadwinner',
        'data_housing_status',
        'data_current_housing_type',
        'data_user_insert_data',
        'data_request_status',
    ];

    // علاقات Eloquent
    public function section()
    {
        return $this->belongsTo(GeneralCategory::class, 'data_section_id');
    }
    public function person()
    {
        return $this->belongsTo(RePeople::class, 'file_id_number', 'file_id');
    }

    public function guardianBankAccount()
    {
        // العلاقة الصحيحة: كل سجل بيانات له حساب بنكي واحد عبر file_id_number <-> guardian_registration
        return $this->hasOne(GuardianBankAccount::class, 'guardian_registration', 'file_id_number');
    }

    public function requestStatus()
    {
        return $this->belongsTo(RequestStatus::class, 'data_request_status');
    }

    public function categoryOfRelation()
    {
        return $this->belongsTo(CategoryOfRelation::class, 'data_relationship');
    }

    public function maritalStatus()
    {
        return $this->belongsTo(MaritalStatus::class, 'data_marital_status');
    }

    public function academicQualification()
    {
        return $this->belongsTo(AcademicDegree::class, 'data_academic_qualification');
    }

    public function displacementStatus()
    {
        return $this->belongsTo(GeneralCategory::class, 'data_displacement_status');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'data_city');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'data_province');
    }

    public function healthStatus()
    {
        return $this->belongsTo(HealthStatus::class, 'data_health_status');
    }

    public function employmentStatusBreadwinner()
    {
        return $this->belongsTo(Employment::class, 'data_employment_status_breadwinner');
    }

    public function housingStatus()
    {
        return $this->belongsTo(HousingStatus::class, 'data_housing_status');
    }

    public function currentHousingType()
    {
        return $this->belongsTo(TypeOfAccommodation::class, 'data_current_housing_type');
    }

    public function userInserted()
    {
        return $this->belongsTo(User::class, 'data_user_insert_data');
    }
    public function attachments()
    {
        // علاقة hasMany الأصلية: فقط مرفقات صاحب الملف
        return $this->hasMany(Attachment::class, 'person_identity_number', 'data_id_number');
    }

    public function rePeople()
    {
        // علاقة أفراد الأسرة: كل سجل Data له عدة أفراد أسرة عبر registration_id <-> file_id_number
        return $this->hasMany(RePeople::class, 'registration_id', 'file_id_number');
    }

    public function deadPepole()
    {
        // علاقة واحد لواحد مع جدول المتوفين عبر re_file_id <-> file_id_number
        return $this->hasOne(\App\Models\DeadPepole::class, 're_file_id', 'file_id_number');
    }
}
