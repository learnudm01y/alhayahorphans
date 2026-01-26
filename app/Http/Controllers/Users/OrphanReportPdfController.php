<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Data;
use App\Models\Sponsorship;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\PortalGeneralRegistrationFieldValue;
use Mpdf\Mpdf;

class OrphanReportPdfController extends Controller
{
    // العلامة البديلة للبيانات غير المتوفرة
    const NOT_AVAILABLE = '/|\\';

    /**
     * Generate PDF report for orphan and save to storage
     */
    public function generateReport(Request $request)
    {
        try {
            $user = Auth::user();
            $userIdNumber = trim($user->email);

            // جلب الكفالة من الجلسة أو البحث عنها
            $sponsorshipId = session('active_sponsorship_id');
            $sponsorship = null;

            if ($sponsorshipId) {
                $sponsorship = Sponsorship::find($sponsorshipId);
            }

            if (!$sponsorship) {
                $sponsorship = Sponsorship::where('identity_number', $userIdNumber)
                    ->orWhere('identity_number', 'LIKE', "%{$userIdNumber}%")
                    ->first();
            }

            if (!$sponsorship && is_numeric($userIdNumber)) {
                $sponsorship = Sponsorship::where('internal_file_number', $userIdNumber)->first();
            }

            if (!$sponsorship) {
                return response()->json(['error' => 'لم يتم العثور على بيانات الكفالة'], 404);
            }

            // تحميل العلاقات
            $sponsorship->load(['sponsor', 'relationData']);

            // جمع البيانات للتقرير
            $reportData = $this->collectReportData($sponsorship);

            // إنشاء PDF
            $html = view('user.dashboard.pdf.orphan-report', $reportData)->render();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font' => 'aealarabiya',
                'default_font_size' => 12,
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'directionality' => 'rtl',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);

            $mpdf->SetTitle('تقرير  - ' . ($reportData['orphan_name'] ?? 'غير معروف'));
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

            Log::info('PDF Report Generated and Saved', [
                'sponsorship_id' => $sponsorship->id,
                'relation_id_number' => $relationIdNumber,
                'file_path' => $fullPath,
                'file_name' => $fileName
            ]);

            // تحميل الملف للمستخدم بعد الحفظ
            $absolutePath = storage_path('app/' . $fullPath);

            return response()->download($absolutePath, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            Log::error('PDF Generation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'حدث خطأ أثناء إنشاء التقرير: ' . $e->getMessage()
            ], 500);
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
            // معلومات السكن والموقع
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
            // تعيين قيم افتراضية
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

        // استخدام relation_id_number للبحث في dead_people و re_people
        $relationIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number;

        // جلب بيانات المتوفين (dead_people)
        $deadPeople = DeadPepole::where('re_file_id', $relationIdNumber)->first();
        if (!$deadPeople) {
            // محاولة البحث برقم الملف الداخلي
            $deadPeople = DeadPepole::where('re_file_id', $sponsorship->internal_file_number)->first();
        }

        if ($deadPeople) {
            // بيانات الأم
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

            // بيانات الأب
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

        // جلب أفراد الأسرة من re_people باستخدام relation_id_number
        $data['family_members'] = $this->getFamilyMembers($relationIdNumber, $sponsorship->identity_number);

        return $data;
    }

    /**
     * جلب الحقول من جدول portal_general_registration_field_values
     */
    private function getPortalFieldValues($sponsorship)
    {
        $na = self::NOT_AVAILABLE;
        $values = [];

        $portalFields = PortalGeneralRegistrationFieldValue::where('sponsorship_id', $sponsorship->id)
            ->orWhere('file_id_number', $sponsorship->internal_file_number)
            ->orWhere('identity_number', $sponsorship->identity_number)
            ->get();

        // الحقول المتوقعة من البوابة
        $expectedFields = [
            'school_name' => 'school_name',
            'school_address' => 'school_address',
            'grade_level' => 'grade_level',
            'academic_stage' => 'academic_stage',
            'student_level' => 'student_level',
            'weakness_reason' => 'weakness_reason',
            'psychological_status' => 'psychological_status',
            'behavioral_status' => 'behavioral_status',
            'religious_commitment' => 'religious_commitment',
            'quran_memorization' => 'quran_memorization',
            'orphan_health_status' => 'orphan_health_status',
            'orphan_needs' => 'orphan_needs',
            'creativity_aspects' => 'creativity_aspects',
            'sponsorship_impact' => 'sponsorship_impact',
            'family_events' => 'family_events',
            'guardian_relation' => 'guardian_relation',
            'guardian_health' => 'guardian_health',
            'guardian_job' => 'guardian_job',
            'dependents_count' => 'dependents_count',
            'timestamp' => 'timestamp',
        ];

        foreach ($expectedFields as $key => $fieldKey) {
            $field = $portalFields->where('field_key', $fieldKey)->first();
            $values[$key] = $field ? $field->field_value : $na;
        }

        return $values;
    }

    /**
     * جلب بيانات المعيل
     */
    private function getGuardianData($sponsorship, $dataRecord)
    {
        $na = self::NOT_AVAILABLE;
        $data = [];

        // محاولة جلب المعيل من re_people أولاً
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
            $data['guardian_health'] = $this->getHealthStatus($guardian->person_health_status) ?? $na;
        } else {
            $data['guardian_full_name'] = $sponsorship->guardian_name ?? $na;
            $data['guardian_health'] = $na;
        }

        // جلب صلة القرابة
        if ($dataRecord && $dataRecord->data_relationship) {
            $data['guardian_relation'] = $this->getRelation($dataRecord->data_relationship) ?? $na;
        } else {
            $portalRelation = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
                $q->where('sponsorship_id', $sponsorship->id)
                    ->orWhere('file_id_number', $sponsorship->internal_file_number);
            })->where('field_key', 'guardian_relation')->first();
            $data['guardian_relation'] = $portalRelation ? $portalRelation->field_value : $na;
        }

        // جلب الوظيفة
        $portalJob = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'guardian_job')->first();
        $data['guardian_job'] = $portalJob ? $portalJob->field_value : ($dataRecord ? $this->getEmploymentStatus($dataRecord->data_employment_status_breadwinner) : $na);

        // عدد من يعيلهم
        $portalDependents = PortalGeneralRegistrationFieldValue::where(function($q) use ($sponsorship) {
            $q->where('sponsorship_id', $sponsorship->id)
                ->orWhere('file_id_number', $sponsorship->internal_file_number);
        })->where('field_key', 'dependents_count')->first();
        $data['dependents_count'] = $portalDependents ? $portalDependents->field_value : ($dataRecord->data_number_of_individuals ?? $na);

        return $data;
    }

    /**
     * جلب أفراد الأسرة
     */
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

    // Helper methods for lookups
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
