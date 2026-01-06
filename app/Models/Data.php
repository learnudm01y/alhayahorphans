<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Data extends Model
{
    use HasFactory;

    protected $table = 'data';

    protected $fillable = [
        'file_id_number',
        'original_file_id_from_excel', // رقم الملف الأصلي من Excel
        'data_section_id',
        'data_id_number',
        'data_first_name',
        'data_first_name_normalized',
        'data_father_name',
        'data_father_name_normalized',
        'data_grand_father_name',
        'data_grand_father_name_normalized',
        'data_family_name',
        'data_family_name_normalized',
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

    /**
     * Boot method لإضافة الحماية التلقائية للسجلات المستوردة من Excel
     */
    protected static function boot()
    {
        parent::boot();

        // قبل الحفظ: التأكد من تعيين حالة "مقبول" للسجلات المستوردة من Excel
        static::saving(function ($data) {
            if (!empty($data->original_file_id_from_excel) &&
                (empty($data->data_request_status) || $data->data_request_status == 0)) {

                $acceptedStatusId = self::getAcceptedStatusId();
                $data->data_request_status = $acceptedStatusId;

                Log::info('Data Model: Auto-assigned accepted status on save', [
                    'file_id' => $data->file_id_number,
                    'status_id' => $acceptedStatusId,
                    'event' => 'saving'
                ]);
            }
        });

        // بعد الحفظ: فحص نهائي للتأكد من ظهور السجل
        static::saved(function ($data) {
            if (!empty($data->original_file_id_from_excel)) {
                // فحص فوري: هل السجل مرئي في DataTable؟
                $isVisible = DB::table('data')
                    ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
                    ->where('data.id', $data->id)
                    ->where('request_status.description', 'مقبول')
                    ->exists();

                if (!$isVisible) {
                    // إصلاح طارئ فوري
                    $acceptedStatusId = self::getAcceptedStatusId();
                    DB::table('data')
                        ->where('id', $data->id)
                        ->update(['data_request_status' => $acceptedStatusId]);

                    Log::warning('Data Model: Emergency visibility fix applied', [
                        'data_id' => $data->id,
                        'file_id' => $data->file_id_number,
                        'status_id' => $acceptedStatusId
                    ]);
                }
            }
        });
    }

    /**
     * الحصول على ID حالة "مقبول" مع ضمانة أكيدة
     */
    private static function getAcceptedStatusId(): int
    {
        try {
            // البحث المباشر
            $acceptedStatusId = DB::table('request_status')
                ->where('description', 'مقبول')
                ->value('id');

            if ($acceptedStatusId) {
                return (int) $acceptedStatusId;
            }

            // البحث الجزئي
            $partialMatch = DB::table('request_status')
                ->where('description', 'like', '%مقبول%')
                ->orWhere('description', 'like', '%موافق%')
                ->orWhere('description', 'like', '%accepted%')
                ->value('id');

            if ($partialMatch) {
                return (int) $partialMatch;
            }

            // استخدام أعلى ID (غالباً الأحدث)
            $highestId = DB::table('request_status')
                ->orderBy('id', 'desc')
                ->value('id');

            return $highestId ? (int) $highestId : 2;

        } catch (\Exception $e) {
            Log::error('Data Model: Error finding accepted status', [
                'error' => $e->getMessage()
            ]);
            return 2; // قيمة آمنة
        }
    }

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
        return $this->belongsTo(CI_PERSONAL_CD::class, 'data_marital_status');
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
