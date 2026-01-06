<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorFieldSetting extends Model
{
    use HasFactory;

    protected $table = 'sponsor_field_settings';

    protected $fillable = [
        'sponsor_id',
        'enabled_documents', // معرفات الوثائق المفعلة
        // معلومات المكفول الأساسية
        'field_sponsor_name',
        'field_identity_number',
        'field_phone',
        'field_mother_name',
        'field_father_death_date',
        // معلومات السكن
        'field_housing_status',
        'field_housing_type',
        'field_housing_address_detail',
        'field_house_demolition',
        'field_house_repair_need',
        // المعلومات الدراسية
        'field_school_name',
        'field_school_address',
        'field_grade',
        'field_student_level',
        'field_weakness_reason',
        'field_tent_school',
        'field_orphan_ambition',
        // الحالة النفسية والسلوكية
        'field_psychological_state',
        'field_behavioral_state',
        'field_orphan_behavior',
        // الجوانب الدينية
        'field_religious_commitment',
        'field_commitment',
        'field_quran_memorization',
        'field_prayer_commitment',
        // الحالة الصحية
        'field_health_status',
        'field_orphan_health',
        'field_treatment_cost',
        'field_receives_treatment',
        'field_family_sick_member',
        'field_family_disease_cost',
        // احتياجات وإبداع
        'field_orphan_needs',
        'field_creativity_aspects',
        // معلومات المعيل
        'field_relationship',
        'field_guardian_health',
        'field_guardian_job',
        'field_guardian_job_text',
        'field_dependents_female',
        'field_dependents_male',
        'field_family_members_count',
        // معلومات اخوة المكفول
        'field_siblings_names',
        'field_sibling_birthdate',
        'field_sibling_grade',
        'field_sibling_notes',
        // تأثير الكفالة والمتابعة
        'field_sponsorship_impact',
        'field_important_events',
        'field_supervisor_notes',
        'field_data_update_date',
        'field_supervisor_name',
        // معلومات المؤسسة والمرفقات
        'field_institution_name',
        'field_attachments',

        // ===== NEWLY ADDED FIELDS =====
        // From sponsorships table
        'field_sponsoring_organization',
        'field_external_file_number',
        'field_guardian_identity_number',
        'field_sponsorship_duration_months',
        'field_sponsorship_start_date',
        'field_sponsorship_end_date',
        'field_sponsorship_type_id',
        'field_sponsorship_status_id',
        // From data table - Guardian info
        'field_data_id_number',
        'field_data_first_name',
        'field_data_father_name',
        'field_data_grand_father_name',
        'field_data_family_name',
        'field_data_birth_date',
        'field_data_phone_number',
        'field_data_city',
        'field_re_guardian_name',
        'field_re_guardian_phone',
        'field_re_guardian_id',
        // From dead_people table
        'field_father_first_name',
        'field_father_id',
        'field_father_death_reason',
        'field_mother_first_name',
        'field_mother_id',
        'field_mother_death_date',
        'field_mother_death_reason',
        // From re_people table
        'field_first_name',
        'field_person_id',
        'field_person_birth_date',
        'field_person_age',
        'field_person_gender',
        'field_person_health_status',
        'field_person_type_of_guarantee',
        'field_person_note',
        // Bank Account Fields - الحقول الجديدة
        'field_guardian_account_owner_name',
        'field_guardian_bank_name',
        'field_guardian_id_owner',
        'field_guardian_phone_number',
        'field_guardian_iban_usd',
        'field_guardian_iban_shekel',
    ];

    protected $casts = [
        // معلومات المكفول الأساسية
        'field_sponsor_name' => 'boolean',
        'field_identity_number' => 'boolean',
        'field_phone' => 'boolean',
        'field_mother_name' => 'boolean',
        'field_father_death_date' => 'boolean',
        // معلومات السكن
        'field_housing_status' => 'boolean',
        'field_housing_type' => 'boolean',
        'field_housing_address_detail' => 'boolean',
        'field_house_demolition' => 'boolean',
        'field_house_repair_need' => 'boolean',
        // المعلومات الدراسية
        'field_school_name' => 'boolean',
        'field_school_address' => 'boolean',
        'field_grade' => 'boolean',
        'field_student_level' => 'boolean',
        'field_weakness_reason' => 'boolean',
        'field_tent_school' => 'boolean',
        'field_orphan_ambition' => 'boolean',
        // الحالة النفسية والسلوكية
        'field_psychological_state' => 'boolean',
        'field_behavioral_state' => 'boolean',
        'field_orphan_behavior' => 'boolean',
        // الجوانب الدينية
        'field_religious_commitment' => 'boolean',
        'field_commitment' => 'boolean',
        'field_quran_memorization' => 'boolean',
        'field_prayer_commitment' => 'boolean',
        // الحالة الصحية
        'field_health_status' => 'boolean',
        'field_orphan_health' => 'boolean',
        'field_treatment_cost' => 'boolean',
        'field_receives_treatment' => 'boolean',
        'field_family_sick_member' => 'boolean',
        'field_family_disease_cost' => 'boolean',
        // احتياجات وإبداع
        'field_orphan_needs' => 'boolean',
        'field_creativity_aspects' => 'boolean',
        // معلومات المعيل
        'field_relationship' => 'boolean',
        'field_guardian_health' => 'boolean',
        'field_guardian_job' => 'boolean',
        'field_guardian_job_text' => 'boolean',
        'field_dependents_female' => 'boolean',
        'field_dependents_male' => 'boolean',
        'field_family_members_count' => 'boolean',
        // معلومات اخوة المكفول
        'field_siblings_names' => 'boolean',
        'field_sibling_birthdate' => 'boolean',
        'field_sibling_grade' => 'boolean',
        'field_sibling_notes' => 'boolean',
        // تأثير الكفالة والمتابعة
        'field_sponsorship_impact' => 'boolean',
        'field_important_events' => 'boolean',
        'field_supervisor_notes' => 'boolean',
        'field_data_update_date' => 'boolean',
        'field_supervisor_name' => 'boolean',
        // معلومات المؤسسة والمرفقات
        'field_institution_name' => 'boolean',
        'field_attachments' => 'boolean',

        // ===== NEWLY ADDED FIELDS =====
        // From sponsorships table
        'field_sponsoring_organization' => 'boolean',
        'field_external_file_number' => 'boolean',
        'field_guardian_identity_number' => 'boolean',
        'field_sponsorship_duration_months' => 'boolean',
        'field_sponsorship_start_date' => 'boolean',
        'field_sponsorship_end_date' => 'boolean',
        'field_sponsorship_type_id' => 'boolean',
        'field_sponsorship_status_id' => 'boolean',
        // From data table - Guardian info
        'field_data_id_number' => 'boolean',
        'field_data_first_name' => 'boolean',
        'field_data_father_name' => 'boolean',
        'field_data_grand_father_name' => 'boolean',
        'field_data_family_name' => 'boolean',
        'field_data_birth_date' => 'boolean',
        'field_data_phone_number' => 'boolean',
        'field_data_city' => 'boolean',
        'field_re_guardian_name' => 'boolean',
        'field_re_guardian_phone' => 'boolean',
        'field_re_guardian_id' => 'boolean',
        // From dead_people table
        'field_father_first_name' => 'boolean',
        'field_father_id' => 'boolean',
        'field_father_death_reason' => 'boolean',
        'field_mother_first_name' => 'boolean',
        'field_mother_id' => 'boolean',
        'field_mother_death_date' => 'boolean',
        'field_mother_death_reason' => 'boolean',
        // From re_people table
        'field_first_name' => 'boolean',
        'field_person_id' => 'boolean',
        'field_person_birth_date' => 'boolean',
        'field_person_age' => 'boolean',
        'field_person_gender' => 'boolean',
        'field_person_health_status' => 'boolean',
        'field_person_type_of_guarantee' => 'boolean',
        'field_person_note' => 'boolean',
        // Bank Account Fields - الحقول الجديدة
        'field_guardian_account_owner_name' => 'boolean',
        'field_guardian_bank_name' => 'boolean',
        'field_guardian_id_owner' => 'boolean',
        'field_guardian_phone_number' => 'boolean',
        'field_guardian_iban_usd' => 'boolean',
        'field_guardian_iban_shekel' => 'boolean',
        // Enabled Documents - JSON array
        'enabled_documents' => 'array',
    ];

    /**
     * العلاقة مع جدول الجمعيات
     */
    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * الحصول على جميع الحقول المفعلة
     */
    public function getActiveFields()
    {
        $activeFields = [];
        foreach ($this->fillable as $field) {
            if ($field !== 'sponsor_id' && $this->$field == 1) {
                $activeFields[] = $field;
            }
        }
        return $activeFields;
    }

    /**
     * الحصول على عدد الحقول المفعلة
     */
    public function getActiveFieldsCount()
    {
        return count($this->getActiveFields());
    }
}
