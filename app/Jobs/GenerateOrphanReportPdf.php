<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Data;
use App\Models\Sponsorship;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\PortalGeneralRegistrationFieldValue;
use Mpdf\Mpdf;

class GenerateOrphanReportPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // العلامة البديلة للبيانات غير المتوفرة
    const NOT_AVAILABLE = '(/)';

    protected $sponsorshipId;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct($sponsorshipId)
    {
        $this->sponsorshipId = $sponsorshipId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $sponsorship = Sponsorship::with(['sponsor', 'relationData'])->find($this->sponsorshipId);

            if (!$sponsorship) {
                Log::error('GenerateOrphanReportPdf: Sponsorship not found', [
                    'sponsorship_id' => $this->sponsorshipId
                ]);
                return;
            }

            // جمع البيانات للتقرير
            $reportData = $this->collectReportData($sponsorship);

            // إنشاء PDF
            $html = view('user.dashboard.pdf.orphan-report', $reportData)->render();

            // إعداد mPDF - استخدام خط xbriyaz المدمج والمتوافق مع اللغة العربية
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font_size' => 12,
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'directionality' => 'rtl',
                'default_font' => 'xbriyaz',
            ]);

            $mpdf->SetTitle('تقرير اليتيم - ' . ($reportData['orphan_name'] ?? 'غير معروف'));
            $mpdf->WriteHTML($html);

            // استخدام relation_id_number للمسار
            $relationIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number ?? 'unknown';

            // إنشاء مسار المجلد
            $folderPath = 'public/uploads/' . $relationIdNumber;

            // التأكد من وجود المجلد أو إنشائه
            if (!Storage::exists($folderPath)) {
                Storage::makeDirectory($folderPath);
            }

            // اسم الملف
            $fileName = 'orphan_report_' . $relationIdNumber . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $fullPath = $folderPath . '/' . $fileName;

            // حفظ PDF في الملف
            $pdfContent = $mpdf->Output('', 'S'); // S = String
            Storage::put($fullPath, $pdfContent);

            Log::info('GenerateOrphanReportPdf: PDF Report Generated and Saved', [
                'sponsorship_id' => $sponsorship->id,
                'relation_id_number' => $relationIdNumber,
                'file_path' => $fullPath,
                'file_name' => $fileName
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateOrphanReportPdf: Error generating PDF', [
                'sponsorship_id' => $this->sponsorshipId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * جمع بيانات التقرير من جميع الجداول
     */
    private function collectReportData($sponsorship)
    {
        $na = self::NOT_AVAILABLE;
        $data = [];

        // بيانات الكفالة الأساسية
        $data['file_number'] = $sponsorship->internal_file_number ?? $na;
        $data['orphan_name'] = $sponsorship->orphan_name ?? $na;
        $data['identity_number'] = $sponsorship->identity_number ?? $na;
        $data['guardian_name'] = $sponsorship->guardian_name ?? $na;
        $data['guardian_identity_number'] = $sponsorship->guardian_identity_number ?? $na;

        // جلب بيانات جدول data
        $dataRecord = null;
        if ($sponsorship->relationData) {
            $dataRecord = $sponsorship->relationData;
        } else {
            $dataRecord = Data::where('file_id_number', $sponsorship->internal_file_number)->first();
        }

        if ($dataRecord) {
            $data['phone_number'] = $dataRecord->data_phone_number ?? $na;
            $data['city'] = $this->getCityName($dataRecord->data_city) ?? $na;
            $data['province'] = $this->getProvinceName($dataRecord->data_province) ?? $na;
            $data['housing_status'] = $this->getHousingStatus($dataRecord->data_housing_status) ?? $na;
            $data['current_housing_type'] = $this->getHousingType($dataRecord->data_current_housing_type) ?? $na;
            $data['displacement_status'] = $this->getDisplacementStatus($dataRecord->data_displacement_status) ?? $na;
            $data['health_status'] = $this->getHealthStatus($dataRecord->data_health_status) ?? $na;
            $data['description_needs'] = $dataRecord->data_description_needs ?? $na;
            $data['number_of_individuals'] = $dataRecord->data_number_of_individuals ?? $na;
            $data['employment_status'] = $this->getEmploymentStatus($dataRecord->data_employment_status_breadwinner) ?? $na;
            $data['marital_status'] = $this->getMaritalStatus($dataRecord->data_marital_status) ?? $na;
            $data['academic_qualification'] = $this->getAcademicQualification($dataRecord->data_academic_qualification) ?? $na;
        } else {
            $data['phone_number'] = $na;
            $data['city'] = $na;
            $data['province'] = $na;
            $data['housing_status'] = $na;
            $data['current_housing_type'] = $na;
            $data['displacement_status'] = $na;
            $data['health_status'] = $na;
            $data['description_needs'] = $na;
            $data['number_of_individuals'] = $na;
            $data['employment_status'] = $na;
            $data['marital_status'] = $na;
            $data['academic_qualification'] = $na;
        }

        // جلب الحقول الإضافية من portal_general_registration_field_values
        $portalValues = $this->getPortalFieldValues($sponsorship);
        $data = array_merge($data, $portalValues);

        // استخدام relation_id_number للبحث
        $relationIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number;

        // جلب بيانات المتوفين
        $deadPeople = DeadPepole::where('re_file_id', $relationIdNumber)->first();
        if (!$deadPeople) {
            $deadPeople = DeadPepole::where('re_file_id', $sponsorship->internal_file_number)->first();
        }

        if ($deadPeople) {
            $data['mother_name'] = $this->formatFullName(
                $deadPeople->mother_first_name,
                $deadPeople->mother_second_name,
                $deadPeople->mother_third_name,
                $deadPeople->mother_last_name
            ) ?: $na;
            $data['mother_id'] = $deadPeople->mother_id ?? $na;
            $data['mother_alive'] = empty($deadPeople->mother_death_date) ? 'نعم' : 'لا';
            $data['mother_death_date'] = $deadPeople->mother_death_date ?? $na;
            $data['mother_death_reason'] = $this->getDeathReason($deadPeople->mother_death_reason) ?? $na;

            $data['father_name'] = $this->formatFullName(
                $deadPeople->father_first_name,
                $deadPeople->father_second_name,
                $deadPeople->father_third_name,
                $deadPeople->father_last_name
            ) ?: $na;
            $data['father_id'] = $deadPeople->father_id ?? $na;
            $data['father_death_date'] = $deadPeople->father_death_date ?? $na;
            $data['father_death_reason'] = $this->getDeathReason($deadPeople->father_death_reason) ?? $na;
        } else {
            $data['mother_name'] = $na;
            $data['mother_id'] = $na;
            $data['mother_alive'] = $na;
            $data['mother_death_date'] = $na;
            $data['mother_death_reason'] = $na;
            $data['father_name'] = $na;
            $data['father_id'] = $na;
            $data['father_death_date'] = $na;
            $data['father_death_reason'] = $na;
        }

        // جلب بيانات المعيل
        $guardianData = $this->getGuardianData($sponsorship, $dataRecord);
        $data = array_merge($data, $guardianData);

        // جلب أفراد الأسرة
        $data['family_members'] = $this->getFamilyMembers($relationIdNumber, $sponsorship->identity_number);

        return $data;
    }

    private function getPortalFieldValues($sponsorship)
    {
        $na = self::NOT_AVAILABLE;
        $values = [];

        $portalFields = PortalGeneralRegistrationFieldValue::where('sponsorship_id', $sponsorship->id)
            ->orWhere('file_id_number', $sponsorship->internal_file_number)
            ->orWhere('identity_number', $sponsorship->identity_number)
            ->get();

        // تصحيح أسماء الحقول لتطابق الأسماء الفعلية في قاعدة البيانات
        $expectedFields = [
            'school_name' => 'field_school_name',
            'school_address' => 'field_school_address',
            'grade_level' => 'field_grade',
            'academic_stage' => 'field_grade',  // نفس الحقل للمرحلة والصف
            'student_level' => 'field_student_level',
            'weakness_reason' => 'field_weakness_reason',
            'psychological_status' => 'field_psychological_state',
            'behavioral_status' => 'field_behavioral_state',
            'religious_commitment' => 'field_religious_commitment',
            'quran_memorization' => 'field_quran_memorization',
            'orphan_health_status' => 'field_health_status',
            'orphan_needs' => 'field_orphan_needs',
            'creativity_aspects' => 'field_creativity_aspects',
            'sponsorship_impact' => 'field_sponsorship_impact',
            'family_events' => 'field_important_events',
            'guardian_relation' => 'field_guardian_relationship',
            'guardian_health' => 'field_guardian_health',
            'guardian_job' => 'field_guardian_job',
            'timestamp' => 'field_data_update_date',
        ];

        foreach ($expectedFields as $key => $fieldKey) {
            $field = $portalFields->where('field_key', $fieldKey)->first();
            $values[$key] = $field ? $field->field_value : $na;
        }

        // حساب عدد المعالين (ذكور + إناث)
        $maleField = $portalFields->where('field_key', 'field_dependents_male')->first();
        $femaleField = $portalFields->where('field_key', 'field_dependents_female')->first();
        $maleCount = $maleField ? (int)$maleField->field_value : 0;
        $femaleCount = $femaleField ? (int)$femaleField->field_value : 0;
        $totalDependents = $maleCount + $femaleCount;
        $values['dependents_count'] = $totalDependents > 0 ? $totalDependents : $na;

        return $values;
    }

    private function getGuardianData($sponsorship, $dataRecord)
    {
        $na = self::NOT_AVAILABLE;
        $data = [];

        // جلب اسم المعيل من portal fields أولاً
        $portalFirstName = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_data_first_name')->first();

        $portalFatherName = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_data_father_name')->first();

        $portalGrandFatherName = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_data_grand_father_name')->first();

        $portalFamilyName = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_data_family_name')->first();

        // تجميع اسم المعيل رباعي من الحقول
        if ($portalFirstName || $portalFatherName || $portalGrandFatherName || $portalFamilyName) {
            $data['guardian_full_name'] = $this->formatFullName(
                $portalFirstName ? $portalFirstName->field_value : null,
                $portalFatherName ? $portalFatherName->field_value : null,
                $portalGrandFatherName ? $portalGrandFatherName->field_value : null,
                $portalFamilyName ? $portalFamilyName->field_value : null
            ) ?: ($sponsorship->guardian_name ?? $na);
        } else {
            // fallback to re_people or sponsorship
            $guardian = null;
            if ($sponsorship->guardian_identity_number) {
                $guardian = RePeople::where('person_id', $sponsorship->guardian_identity_number)->first();
            }

            if ($guardian) {
                $data['guardian_full_name'] = $this->formatFullName(
                    $guardian->first_name,
                    $guardian->second_name,
                    $guardian->third_name,
                    $guardian->last_name
                ) ?: ($sponsorship->guardian_name ?? $na);
            } else {
                $data['guardian_full_name'] = $sponsorship->guardian_name ?? $na;
            }
        }

        // جلب صلة القرابة - استخدام الأسماء الصحيحة للحقول
        $portalRelation = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_guardian_relationship')->first();

        if ($portalRelation) {
            // قد تكون القيمة رقم (ID) أو نص
            $relationValue = $portalRelation->field_value;
            if (is_numeric($relationValue)) {
                $data['guardian_relation'] = $this->getRelation($relationValue) ?? $na;
            } else {
                $data['guardian_relation'] = $relationValue ?: $na;
            }
        } elseif ($dataRecord && $dataRecord->data_relationship) {
            $data['guardian_relation'] = $this->getRelation($dataRecord->data_relationship) ?? $na;
        } else {
            $data['guardian_relation'] = $na;
        }

        // جلب الحالة الصحية للمعيل
        $portalHealth = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_guardian_health')->first();
        $data['guardian_health'] = $portalHealth ? $portalHealth->field_value : $na;

        // جلب وظيفة المعيل
        $portalJob = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_guardian_job')->first();
        $data['guardian_job'] = $portalJob ? $portalJob->field_value : ($dataRecord ? $this->getEmploymentStatus($dataRecord->data_employment_status_breadwinner) : $na);

        // جلب عدد المعالين (ذكور + إناث)
        $portalMale = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_dependents_male')->first();

        $portalFemale = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'field_dependents_female')->first();

        $maleCount = $portalMale ? (int)$portalMale->field_value : 0;
        $femaleCount = $portalFemale ? (int)$portalFemale->field_value : 0;
        $totalDependents = $maleCount + $femaleCount;
        $data['dependents_count'] = $totalDependents > 0 ? $totalDependents : ($dataRecord->data_number_of_individuals ?? $na);

        return $data;
    }

    private function getFamilyMembers($fileIdNumber, $excludeIdentity = null)
    {
        $members = RePeople::where('registration_id', $fileIdNumber)
            ->when($excludeIdentity, function($q) use ($excludeIdentity) {
                $q->where('person_id', '!=', $excludeIdentity);
            })
            ->get();

        $na = self::NOT_AVAILABLE;
        $result = [];

        foreach ($members as $index => $member) {
            $result[] = [
                'index' => $index + 1,
                'full_name' => $this->formatFullName(
                    $member->first_name,
                    $member->second_name,
                    $member->third_name,
                    $member->last_name
                ) ?: $na,
                'birth_date' => $member->person_birth_date ? date('d/m/Y', strtotime($member->person_birth_date)) : $na,
                'academic_degree' => $member->acadimic_degree ?? $na,
                'health_status' => $this->getHealthStatus($member->person_health_status) ?? $na,
            ];
        }

        return $result;
    }

    // Helper methods
    private function formatFullName($first, $second, $third, $last)
    {
        $parts = array_filter([$first, $second, $third, $last], function($v) {
            return !empty($v) && $v !== null;
        });
        return implode(' ', $parts) ?: null;
    }

    private function getCityName($id)
    {
        if (!$id) return null;
        return DB::table('city')->where('id', $id)->value('city');
    }

    private function getProvinceName($id)
    {
        if (!$id) return null;
        return DB::table('provinces')->where('id', $id)->value('description');
    }

    private function getHousingStatus($id)
    {
        if (!$id) return null;
        return DB::table('housing_status')->where('id', $id)->value('description');
    }

    private function getHousingType($id)
    {
        if (!$id) return null;
        return DB::table('type_of_accommodation')->where('id', $id)->value('description');
    }

    private function getDisplacementStatus($id)
    {
        if (!$id) return null;
        return DB::table('displacement_statuses')->where('id', $id)->value('description');
    }

    private function getHealthStatus($id)
    {
        if (!$id) return null;
        return DB::table('health_statuses')->where('id', $id)->value('description');
    }

    private function getEmploymentStatus($id)
    {
        if (!$id) return null;
        return DB::table('employment')->where('id', $id)->value('description');
    }

    private function getMaritalStatus($id)
    {
        if (!$id) return null;
        return DB::table('marital_status')->where('id', $id)->value('description');
    }

    private function getAcademicQualification($id)
    {
        if (!$id) return null;
        return DB::table('academic_degrees')->where('id', $id)->value('description');
    }

    private function getDeathReason($id)
    {
        if (!$id) return null;
        return DB::table('death_reasons')->where('id', $id)->value('description');
    }

    private function getRelation($id)
    {
        if (!$id) return null;
        return DB::table('category_of_relations')->where('id', $id)->value('attribute');
    }
}
