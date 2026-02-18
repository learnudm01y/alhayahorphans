<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Data;
use App\Models\GeneralCategory;
use App\Models\CategoryOfRelation;
use App\Models\CI_PERSONAL_CD;
use App\Models\AcademicDegree;
use App\Models\DisplacementStatus;
use App\Models\City;
use App\Models\Province;
use App\Models\HealthStatus;
use App\Models\Employment;
use App\Models\HousingStatus;
use App\Models\TypeOfAccommodation;
use App\Models\DocumentType;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfGuarantee;
use App\Models\DeathReason;
use App\Models\Attachment;
use App\Models\RePeople;
use App\Models\BankName;
use App\Models\GuardianBankAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class RecordsManagementEditController extends Controller
{
    /**
     * عرض تقرير نشاط الموظفين (admins) مع كروت إحصائية
     */
    public function adminActivityReport()
    {
        // جلب جميع المستخدمين من نوع admin
        $admins = \App\Models\User::where('role', 'admin')->get();

        // جلب جميع السجلات من جدول Data لمطابقة الإدخالات
        $allData = Data::select('id', 'data_user_insert_data', 'created_at')
            ->get();

        // اليوم الحالي
        $today = now()->format('Y-m-d');
        $month = now()->format('Y-m');
        $year = now()->format('Y');

        $adminsStats = $admins->map(function($admin) use ($allData, $today, $month, $year) {
            $userName = $admin->name;
            // جميع السجلات التي أدخلها هذا المستخدم
            $userData = $allData->where('data_user_insert_data', $userName);

            // عدد السجلات اليوم
            $countToday = $userData->filter(function($row) use ($today) {
                return optional($row->created_at)->format('Y-m-d') === $today;
            })->count();
            // عدد السجلات هذا الشهر
            $countMonth = $userData->filter(function($row) use ($month) {
                return optional($row->created_at)->format('Y-m') === $month;
            })->count();
            // عدد السجلات هذه السنة
            $countYear = $userData->filter(function($row) use ($year) {
                return optional($row->created_at)->format('Y') === $year;
            })->count();

            return [
                'user' => $admin,
                'count_today' => $countToday,
                'count_month' => $countMonth,
                'count_year' => $countYear,
            ];
        });

        return view('admin.dashboard.admin_activity_report', [
            'adminsStats' => $adminsStats
        ]);
    }
    public function edit($id)
    {
        $data = Data::findOrFail($id);
        // جلب نفس البيانات المساعدة كما في create
        $generalSection = GeneralCategory::all();
        $category_of_relationship = CategoryOfRelation::all();
        $ci_personal_cd = CI_PERSONAL_CD::all(); // الحالة الاجتماعية
        $academic_qualification = AcademicDegree::all();
        $displacement_status = DisplacementStatus::all();
        $city = City::all();
        $province = Province::all();
        $health_status = HealthStatus::all();
        $employment_status_breadwinner = Employment::all();
        $housing_status = HousingStatus::all();
        $TypeOfAccommodation = TypeOfAccommodation::all();
        $documentTypes = DocumentType::all();
        $sponsorship_status = SponsorshipStatus::all();
        $guarantee_types = TypeOfGuarantee::all();
        $death_reasons = DeathReason::all();

        // 🏦 جلب البيانات البنكية
        $bank_name = BankName::all();
        $bankAccounts = GuardianBankAccount::where('guardian_registration', $data->file_id_number)->get();

        return view('admin.dashboard.records_management.edit', compact(
            'data',
            'generalSection',
            'category_of_relationship',
            'ci_personal_cd',
            'academic_qualification',
            'displacement_status',
            'city',
            'province',
            'health_status',
            'employment_status_breadwinner',
            'housing_status',
            'TypeOfAccommodation',
            'documentTypes',
            'sponsorship_status',
            'guarantee_types',
            'death_reasons',
            'bank_name',
            'bankAccounts'
        ));
    }

    public function show($id)
    {
        // جلب البيانات الأساسية فقط لتقليل الضغط على قاعدة البيانات
        $data = Data::with([
            'section',
            'requestStatus',
            'categoryOfRelation',
            'healthStatus',
            'maritalStatus',
            'academicQualification',
            'city',
            'employmentStatusBreadwinner',
            'province',
            'housingStatus',
            'currentHousingType',
            'attachments',
            'rePeople.healthStatus', // البيانات الأساسية لأفراد الأسرة فقط
            'rePeople.guaranteeType',
            'rePeople.sponsorshipStatus',
            'rePeople.attachments', // إضافة المرفقات لأفراد الأسرة
            'deadPepole.fatherAttachments',
            'deadPepole.motherAttachments',
        ])->findOrFail($id);

        // جلب معلومات الكفالة لولي الأمر
        $guardianSponsorships = \App\Models\Sponsorship::with(['sponsorshipType', 'sponsorshipStatus', 'sponsors'])
            ->where('identity_number', $data->data_id_number)
            ->get();

        // جلب البيانات الإضافية للمعيل من portal_general_registration_field_values
        $guardianPortalFields = \App\Models\PortalGeneralRegistrationFieldValue::where('identity_number', $data->data_id_number)
            ->get()
            ->map(function($field) {
                return [
                    'key' => $this->translateFieldKey($field->field_key),
                    'value' => $field->field_value
                ];
            });

        // معلومات الأم الحية من جدول portal_general_registration_field_values
        $liveMother = null;
        $motherPortalFields = collect([]);

        // التحقق من حالة الأم
        $motherStatus = \App\Models\PortalGeneralRegistrationFieldValue::where('file_id_number', $data->file_id_number)
            ->where('field_key', 'field_mother_status')
            ->value('field_value');

        // التحقق من عدم وجود الأم في جدول المتوفين
        $deadMother = $data->deadPepole ? $data->deadPepole->mother_id : null;

        // إذا لم تكن الأم متوفية، جلب بياناتها من portal_general_registration_field_values
        if (!$deadMother && ($motherStatus === 'حية' || $motherStatus === 'على قيد الحياة')) {
            // جلب حقول الأم الحية من portal_general_registration_field_values
            $livingMotherFields = \App\Models\PortalGeneralRegistrationFieldValue::where('file_id_number', $data->file_id_number)
                ->whereIn('field_key', [
                    'field_living_mother_id',
                    'field_living_mother_first_name',
                    'field_living_mother_second_name',
                    'field_living_mother_third_name',
                    'field_living_mother_last_name',
                    'field_living_mother_birth_date',
                    'field_living_mother_health_status',
                    'field_living_mother_phone'
                ])
                ->get()
                ->keyBy('field_key');

            // إذا وُجدت بيانات للأم الحية
            if ($livingMotherFields->isNotEmpty()) {
                $liveMother = (object)[
                    'person_id' => $livingMotherFields->get('field_living_mother_id')?->field_value,
                    'first_name' => $livingMotherFields->get('field_living_mother_first_name')?->field_value,
                    'second_name' => $livingMotherFields->get('field_living_mother_second_name')?->field_value,
                    'third_name' => $livingMotherFields->get('field_living_mother_third_name')?->field_value,
                    'last_name' => $livingMotherFields->get('field_living_mother_last_name')?->field_value,
                    'person_birth_date' => $livingMotherFields->get('field_living_mother_birth_date')?->field_value,
                    'person_age' => null, // يمكن حسابه من تاريخ الميلاد إذا لزم
                    'person_gender' => 2, // أنثى
                    'healthStatus' => null, // يمكن ربطه إذا كان field_value يحتوي على ID
                ];

                // حساب العمر إذا كان تاريخ الميلاد متوفراً
                if ($liveMother->person_birth_date) {
                    try {
                        $birthDate = new \DateTime($liveMother->person_birth_date);
                        $today = new \DateTime();
                        $liveMother->person_age = $today->diff($birthDate)->y;
                    } catch (\Exception $e) {
                        $liveMother->person_age = null;
                    }
                }

                // جلب جميع البيانات الإضافية للأم من portal (بخلاف الحقول الأساسية)
                $motherPortalFields = \App\Models\PortalGeneralRegistrationFieldValue::where('file_id_number', $data->file_id_number)
                    ->where('field_key', 'LIKE', '%mother%')
                    ->whereNotIn('field_key', [
                        'field_living_mother_id',
                        'field_living_mother_first_name',
                        'field_living_mother_second_name',
                        'field_living_mother_third_name',
                        'field_living_mother_last_name',
                        'field_living_mother_birth_date',
                        'field_mother_status'
                    ])
                    ->get()
                    ->map(function($field) {
                        return [
                            'key' => $this->translateFieldKey($field->field_key),
                            'value' => $field->field_value
                        ];
                    });
            }
        }

        return view('admin.dashboard.records_management.show', compact('data', 'guardianSponsorships', 'guardianPortalFields', 'liveMother', 'motherPortalFields'));
    }

    /**
     * جلب البيانات الإضافية ومعلومات الكفالة عبر AJAX
     */
    public function getAdditionalInfo(Request $request)
    {
        $type = $request->input('type'); // 'family_member' فقط
        $personId = $request->input('person_id');
        $fileId = $request->input('file_id');

        $response = [
            'portal_fields' => [],
            'sponsorships' => []
        ];

        if ($type === 'family_member') {
            // جلب البيانات الإضافية لفرد الأسرة فقط
            $portalFields = \App\Models\PortalGeneralRegistrationFieldValue::where('identity_number', $personId)->get();
            foreach ($portalFields as $field) {
                $response['portal_fields'][] = [
                    'key' => $this->translateFieldKey($field->field_key),
                    'value' => $field->field_value
                ];
            }

            // جلب معلومات الكفالة لفرد الأسرة
            $sponsorships = \App\Models\Sponsorship::with(['sponsorshipType', 'sponsorshipStatus', 'sponsors'])
                ->where('identity_number', $personId)
                ->get();

            foreach ($sponsorships as $sponsorship) {
                $response['sponsorships'][] = $this->formatSponsorshipData($sponsorship, $personId);
            }
        }

        return response()->json($response);
    }

    /**
     * تحويل مفاتيح الحقول من الإنجليزية إلى العربية
     */
    private function translateFieldKey($key)
    {
        $translations = [
            // معلومات السكن
            'housing_status' => 'الحالة السكنية',
            'housing_type' => 'نوع السكن',
            'current_housing_type' => 'نوع السكن الحالي',
            'field_house_demolition' => 'حالة هدم المنزل',
            'field_house_repair_need' => 'حاجة المنزل للترميم',

            // معلومات النزوح
            'displacement_status' => 'حالة النزوح',
            'previous_address' => 'العنوان قبل النزوح',
            'address_before_displacement' => 'العنوان قبل النزوح',
            'current_address' => 'العنوان الحالي',

            // معلومات الاتصال
            'phone_number' => 'رقم الهاتف',
            'alt_phone_number' => 'رقم هاتف بديل',
            'alternative_phone' => 'رقم هاتف بديل',

            // معلومات العمل والدخل
            'employment_status' => 'حالة العمل',
            'employment_status_breadwinner' => 'حالة عمل العائل',
            'monthly_income' => 'الدخل الشهري',
            'income' => 'الدخل',
            'field_guardian_job_text' => 'وظيفة المعيل',

            // معلومات العائلة
            'number_of_males' => 'عدد الذكور',
            'number_of_females' => 'عدد الإناث',
            'number_of_individuals' => 'عدد أفراد الأسرة',
            'chronic_diseases_count' => 'عدد المصابين بأمراض مزمنة',
            'number_of_individuals_with_chronic_diseases' => 'عدد المصابين بأمراض مزمنة',
            'special_needs_count' => 'عدد ذوي الاحتياجات الخاصة',
            'number_of_people_with_special_needs' => 'عدد ذوي الاحتياجات الخاصة',
            'field_family_sick_member' => 'وجود فرد مريض في الأسرة',
            'field_family_disease_cost' => 'تكلفة علاج الأسرة',

            // معلومات عامة
            'city' => 'المدينة',
            'province' => 'المحافظة',
            'description_needs' => 'وصف الاحتياج',
            'needs_description' => 'وصف الاحتياج',
            'marital_status' => 'الحالة الاجتماعية',
            'academic_qualification' => 'المؤهل العلمي',
            'health_status' => 'الحالة الصحية',
            'birth_date' => 'تاريخ الميلاد',
            'gender' => 'الجنس',
            'age' => 'العمر',

            // معلومات الكفالة
            'guardian_name' => 'اسم المعيل',
            'guardian_relationship' => 'صلة القرابة مع المعيل',
            'sponsorship_type' => 'نوع الكفالة',
            'sponsorship_status' => 'حالة الكفالة',
            'sponsor_name' => 'اسم الكفيل',
            'field_sponsorship_impact' => 'أثر الكفالة',

            // معلومات المدرسة والدراسة
            'field_school_name' => 'اسم المدرسة',
            'field_school_address' => 'عنوان المدرسة',
            'field_grade' => 'المرحلة الدراسية',
            'field_student_level' => 'مستوى الطالب',
            'field_weakness_reason' => 'سبب الضعف الدراسي',
            'field_tent_school' => 'مدرسة خيمة',

            // معلومات المكفول
            'field_orphan_ambition' => 'طموح المكفول',
            'field_psychological_state' => 'الحالة النفسية',
            'field_behavioral_state' => 'الحالة السلوكية',
            'field_orphan_behavior' => 'سلوك المكفول',
            'field_religious_commitment' => 'الالتزام الديني',
            'field_commitment' => 'الالتزام',
            'field_quran_memorization' => 'حفظ القرآن',
            'field_prayer_commitment' => 'الالتزام بالصلاة',
            'field_orphan_needs' => 'احتياجات المكفول',
            'field_creativity_aspects' => 'جوانب الإبداع',

            // معلومات الصحة
            'field_receives_treatment' => 'يتلقى علاج',
            'field_orphan_health' => 'الحالة الصحية للمكفول',
            'field_treatment_cost' => 'تكلفة العلاج',

            // معلومات الأم
            'field_mother_status' => 'حالة الأم',
            'field_living_mother_first_name' => 'الاسم الأول للأم',
            'field_living_mother_second_name' => 'اسم الأب للأم',
            'field_living_mother_third_name' => 'اسم الجد للأم',
            'field_living_mother_last_name' => 'اسم العائلة للأم',
            'field_living_mother_id' => 'رقم هوية الأم',
            'field_living_mother_birth_date' => 'تاريخ ميلاد الأم',
            'field_living_mother_health_status' => 'الحالة الصحية للأم',
            'field_living_mother_phone' => 'رقم هاتف الأم',
            'field_living_mother_age' => 'عمر الأم',
            'field_living_mother_education' => 'تعليم الأم',
            'field_living_mother_work' => 'عمل الأم',
            'field_mother_first_name' => 'الاسم الأول للأم المتوفية',
            'field_mother_id' => 'رقم هوية الأم المتوفية',
            'field_mother_death_date' => 'تاريخ وفاة الأم',
            'field_mother_death_reason' => 'سبب وفاة الأم',

            // معلومات إدارية
            'field_important_events' => 'الأحداث المهمة',
            'field_supervisor_notes' => 'ملاحظات المشرف',
            'field_data_update_date' => 'تاريخ تحديث البيانات',
            'field_supervisor_name' => 'اسم المشرف',
        ];

        return $translations[$key] ?? $key;
    }

    /**
     * تنسيق بيانات الكفالة للعرض
     */
    private function formatSponsorshipData($sponsorship, $personId)
    {
        $isGuardian = $sponsorship->guardian_identity_number === $personId;
        $role = $isGuardian ? 'معيل' : 'مكفول';

        return [
            'role' => $role,
            'internal_file_number' => $sponsorship->internal_file_number,
            'external_file_number' => $sponsorship->external_file_number,
            'orphan_name' => $sponsorship->orphan_name,
            'guardian_name' => $sponsorship->guardian_name,
            'guardian_identity' => $sponsorship->guardian_identity_number,
            'birth_date' => $sponsorship->sponsored_birth_date ? $sponsorship->sponsored_birth_date->format('Y-m-d') : null,
            'sponsorship_type' => optional($sponsorship->sponsorshipType)->description,
            'sponsorship_status' => optional($sponsorship->sponsorshipStatus)->description,
            'start_date' => $sponsorship->sponsorship_start_date ? $sponsorship->sponsorship_start_date->format('Y-m-d') : null,
            'end_date' => $sponsorship->sponsorship_end_date ? $sponsorship->sponsorship_end_date->format('Y-m-d') : null,
            'duration_months' => $sponsorship->sponsorship_duration_months,
            'sponsors' => $sponsorship->sponsors ? $sponsorship->sponsors->pluck('sponsor_name')->toArray() : [],
            'notes' => $sponsorship->notes,
        ];
    }

 public function update(Request $request, $id)
    {
        try {
            Log::info('--- Start update with nested attachmentsByPerson ---');
            Log::info('Request all:', $request->all());
            Log::info('Request files:', $request->allFiles());

            // معالجة file_id لأفراد الأسرة إذا كان فارغاً
            if ($request->has('family_members')) {
                $familyMembers = $request->input('family_members');
                $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);
                foreach ($familyMembers as $idx => $member) {
                    if (empty($member['file_id'])) {
                        $familyMembers[$idx]['file_id'] = $fileIdNumber;
                    }
                }
                $request->merge(['family_members' => $familyMembers]);
            }

            // 1. فلترة attachments_to_delete وتحويلها إلى أرقام صحيحة
            if ($request->has('attachments_to_delete')) {
                $filtered = array_filter((array)$request->input('attachments_to_delete'), function($v){
                    // دعم القيم النصية الرقمية
                    return is_numeric($v) && intval($v) == $v;
                });
                // تحويل كل عنصر إلى int فعلياً
                $filtered = array_map('intval', $filtered);
                $request->merge(['attachments_to_delete' => array_values($filtered)]);
            }

            // 2. Validation للحقلات الأساسية + attachmentsByPerson
            $validationRules = [
                'file_id_number' => 'required|string',
                'data_section_id' => 'required|integer',
                'data_id_number' => 'required|string',
                'data_first_name' => 'required|string|max:255',
                'data_father_name' => 'nullable|string|max:255',
                'data_grand_father_name' => 'nullable|string|max:255',
                'data_family_name' => 'nullable|string|max:255',
                'data_relationship' => 'nullable|string|max:255',
                'data_birth_date' => 'nullable|date',
                'data_gender' => 'nullable|string|max:10',
                'data_phone_number' => 'nullable|string|max:20',
                'data_alt_phone_number' => 'nullable|string|max:20',
                'data_number_of_individuals' => 'nullable|integer',
                'data_marital_status' => 'nullable|integer',
                'data_academic_qualification' => 'nullable|integer',
                'data_displacement_status' => 'nullable|integer',
                'data_address_before_displacement' => 'nullable|string',
                'data_current_address' => 'nullable|string',
                'data_city' => 'nullable|integer',
                'data_province' => 'nullable|integer',
                'data_health_status' => 'nullable|integer',
                'data_description_needs' => 'nullable|string',
                'data_number_mail' => 'nullable|string',
                'data_number_female' => 'nullable|integer',
                'data_number_of_individuals_with_chronic_diseases' => 'nullable|integer',
                'data_number_of_people_with_special_needs' => 'nullable|integer',
                'data_employment_status_breadwinner' => 'nullable|integer',
                'data_housing_status' => 'nullable|integer',
                'data_current_housing_type' => 'nullable|string',
                'data_user_insert_data' => 'nullable|string',
                'data_request_status' => 'nullable|integer',
                'attachments_to_delete' => 'sometimes|array',
                // هنا: استخدم integer فقط (بدون exists) أو استخدم exists:attachments,id إذا كنت متأكد أن القيم أرقام صحيحة
                'attachments_to_delete.*' => 'integer|exists:attachments,id',
                'attachmentsByPerson.*.person_identity_number' => 'required',
                'attachmentsByPerson.*.file_id_number' => 'required',
                // لا تضف قاعدة file هنا نهائياً
                'attachmentsByPerson.*.documents.*.file_type' => 'required|string',
                'attachmentsByPerson.*.documents.*.stored_file_name' => 'required|string',
                // تأكد أن أفراد الأسرة لديهم file_id_number
                'family_members.*.file_id' => 'required|string',
            ];
            $validationMessages = [
                'attachmentsByPerson.*.documents.*.file.required' => 'ملف الوثيقة مطلوب.',
                'attachmentsByPerson.*.documents.*.file.mimes' => 'صيغة الملف غير مدعومة.',
                'attachmentsByPerson.*.documents.*.file.max' => 'حجم الملف لا يتجاوز 5 ميغابايت.',
                'attachmentsByPerson.*.documents.*.file_type.required' => 'نوع الوثيقة مطلوب.',
                'attachmentsByPerson.*.person_identity_number.required' => 'رقم هوية الشخص مطلوب.',
                'attachmentsByPerson.*.file_id_number.required' => 'رقم الملف العام مطلوب.',
            ];
            $request->validate($validationRules, $validationMessages);


            DB::beginTransaction();

            // جلب السجل الرئيسي
            $data = Data::findOrFail($id);
            Log::info('Loaded Data for update:', $data->toArray());

            // 3. حذف المرفقات المطلوبة (existing attachments_to_delete)
            $attachmentsToDelete = $request->input('attachments_to_delete', []);
            Log::info('Attachments to delete:', $attachmentsToDelete);
            foreach ($attachmentsToDelete as $attId) {
                $attachment = Attachment::find($attId);
                if ($attachment) {
                    $storagePath = str_replace('storage/', '', $attachment->file_path);
                    if (Storage::disk('public')->exists($storagePath)) {
                        Storage::disk('public')->delete($storagePath);
                    }
                    $attachment->delete();
                    Log::info("Deleted existing attachment ID {$attId}");
                }
            }

            // 4. تحديث البيانات الأساسية
            $fileIdNumberRaw = $request->input('file_id_number');
            $fileIdNumber = str_pad($fileIdNumberRaw, 6, '0', STR_PAD_LEFT);
            $oldDataIdNumber = $data->getOriginal('data_id_number');
            $newDataIdNumber = $request->input('data_id_number');

            // التحقق من عدم تكرار رقم الملف إذا تم تغييره
            if ($fileIdNumber !== $data->getOriginal('file_id_number')) {
                $existingFile = Data::where('file_id_number', $fileIdNumber)
                    ->where('id', '!=', $data->id)
                    ->first();

                if ($existingFile) {
                    return redirect()->back()->with('error', "رقم الملف {$fileIdNumber} مستخدم بالفعل لشخص آخر. الرجاء استخدام رقم مختلف.");
                }
            }

            $data->update([
                'file_id_number' => $fileIdNumber,
                'data_section_id' => $request->input('data_section_id'),
                'data_id_number' => $newDataIdNumber,
                'data_first_name' => $request->input('data_first_name'),
                'data_father_name' => $request->input('data_father_name'),
                'data_grand_father_name' => $request->input('data_grand_father_name'),
                'data_family_name' => $request->input('data_family_name'),
                'data_relationship' => $request->input('data_relationship'),
                'data_birth_date' => $request->input('data_birth_date'),
                'data_gender' => $request->input('data_gender'),
                'data_phone_number' => $request->input('data_phone_number'),
                'data_alt_phone_number' => $request->input('data_alt_phone_number'),
                'data_number_of_individuals' => $request->input('data_number_of_individuals'),
                'data_marital_status' => $request->input('data_marital_status'),
                'data_academic_qualification' => $request->input('data_academic_qualification'),
                'data_displacement_status' => $request->input('data_displacement_status'),
                'data_address_before_displacement' => $request->input('data_address_before_displacement'),
                'data_current_address' => $request->input('data_current_address'),
                'data_city' => $request->input('data_city'),
                'data_province' => $request->input('data_province'),
                'data_health_status' => $request->input('data_health_status'),
                'data_description_needs' => $request->input('data_description_needs'),
                'data_number_mail' => $request->input('data_number_mail'),
                'data_number_female' => $request->input('data_number_female'),
                'data_number_of_individuals_with_chronic_diseases' => $request->input('data_number_of_individuals_with_chronic_diseases'),
                'data_number_of_people_with_special_needs' => $request->input('data_number_of_people_with_special_needs'),
                'data_employment_status_breadwinner' => $request->input('data_employment_status_breadwinner'),
                'data_housing_status' => $request->input('data_housing_status'),
                'data_current_housing_type' => $request->input('data_current_housing_type'),
                'data_user_insert_data' => $request->input('data_user_insert_data'),
                'data_request_status' => 2,
            ]);

            Log::info('Updated Data basic info');

            // 5. معالجة نقل/إعادة تسمية مرفقات قديمة عند تغيير رقم الهوية الرئيسي
            if ($oldDataIdNumber && $oldDataIdNumber !== $newDataIdNumber) {
                Log::info('Updating attachments for changed data_id_number', ['old'=>$oldDataIdNumber,'new'=>$newDataIdNumber]);
                $oldAttachments = Attachment::where('person_identity_number', $oldDataIdNumber)->get();
                foreach ($oldAttachments as $attachment) {
                    $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                    $parts = explode('_', $attachment->stored_file_name);
                    $docType = $parts[0] ?? 'doc';
                    $newFileName = "{$docType}_{$fileIdNumber}_{$newDataIdNumber}.{$extension}";
                    $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                    $newRelativePath = "attachments/{$fileIdNumber}/{$newFileName}";
                    if (Storage::disk('public')->exists($oldStoragePath)) {
                        Storage::disk('public')->move($oldStoragePath, $newRelativePath);
                    }
                    $attachment->update([
                        'person_identity_number' => $newDataIdNumber,
                        'stored_file_name' => $newFileName,
                        'file_path' => "storage/{$newRelativePath}",
                    ]);
                    Log::info("Renamed old attachment to {$newFileName}");
                }
            }

            // 7. معالجة أفراد الأسرة (حذف القدامى ثم إضافة الجدد)
            if ($request->has('family_members')) {
                // حذف جميع أفراد الأسرة المرتبطين بهذا السجل
                $oldFamilyMembers = $data->rePeople()->get()->keyBy('id');
                $data->rePeople()->delete();

                $familyMembers = $request->input('family_members');
                $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);
                foreach ($familyMembers as $member) {
                    // إذا كان هناك id قديم، تحقق من تغيير رقم الهوية
                    $oldMember = isset($member['id']) ? $oldFamilyMembers->get($member['id']) : null;
                    $oldPersonId = $oldMember ? $oldMember->person_id : null;
                    $newPersonId = $member['person_id'] ?? null;

                    // إضافة فرد الأسرة الجديد
                    $newRePeople = \App\Models\RePeople::create([
                        'file_id' => $member['file_id'] ?? $fileIdNumber,
                        'registration_id' => $member['registration_id'] ?? $fileIdNumber,
                        'sponsorship_status' => $member['sponsorship_status'] ?? null,
                        'first_name' => $member['first_name'] ?? null,
                        'second_name' => $member['second_name'] ?? null,
                        'third_name' => $member['third_name'] ?? null,
                        'last_name' => $member['last_name'] ?? null,
                        'person_id' => $newPersonId,
                        'person_birth_date' => $member['person_birth_date'] ?? null,
                        'person_age' => $member['person_age'] ?? null,
                        'person_gender' => $member['person_gender'] ?? null,
                        'person_health_status' => $member['person_health_status'] ?? null,
                        'person_type_of_guarantee' => $member['person_type_of_guarantee'] ?? null,
                    ]);

                    // إذا تغير رقم الهوية، عدل المرفقات المرتبطة
                    if ($oldPersonId && $newPersonId && $oldPersonId != $newPersonId) {
                        $attachments = \App\Models\Attachment::where('person_identity_number', $oldPersonId)->get();
                        foreach ($attachments as $attachment) {
                            $folder = dirname(str_replace('storage/', '', $attachment->file_path));
                            $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                            $parts = explode('_', $attachment->stored_file_name);
                            $docType = $parts[0] ?? 'doc';
                            $newFileName = "{$docType}_{$fileIdNumber}_{$newPersonId}.{$extension}";
                            $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                            $newStoragePath = $folder . '/' . $newFileName;

                            // إعادة تسمية الملف في نفس المجلد
                            if (Storage::disk('public')->exists($oldStoragePath)) {
                                Storage::disk('public')->move($oldStoragePath, $newStoragePath);
                            }

                            $attachment->update([
                                'person_identity_number' => $newPersonId,
                                'stored_file_name' => $newFileName,
                                'file_path' => "storage/{$newStoragePath}",
                            ]);
                        }
                    }
                }
            }

            // 6. معالجة المتوفين وتحديث مرفقاتهم إذا تغيرت الهويات (كما في السابق)
            if ($request->input('data_section_id') == 1) {
                $dead = $data->deadPepole()->first();
                $fatherFilled = $request->filled('father_first_name') || $request->filled('father_last_name') || $request->filled('father_id');
                $motherFilled = $request->filled('mother_first_name') || $request->filled('mother_last_name') || $request->filled('mother_id');
                if ($fatherFilled || $motherFilled) {
                    $deadData = [
                        're_file_id' => $fileIdNumber,
                        // بيانات الأب
                        'father_first_name' => $request->input('father_first_name'),
                        'father_second_name' => $request->input('father_second_name'),
                        'father_third_name' => $request->input('father_third_name'),
                        'father_last_name' => $request->input('father_last_name'),
                        'father_id' => $request->input('father_id'),
                        'father_death_date' => $request->input('father_death_date'),
                        'father_death_reason' => $request->input('father_death_reason'),
                        // بيانات الأم
                        'mother_first_name' => $request->input('mother_first_name'),
                        'mother_second_name' => $request->input('mother_second_name'),
                        'mother_third_name' => $request->input('mother_third_name'),
                        'mother_last_name' => $request->input('mother_last_name'),
                        'mother_id' => $request->input('mother_id'),
                        'mother_death_date' => $request->input('mother_death_date'),
                        'mother_death_reason' => $request->input('mother_death_reason'),
                    ];
                    $oldFatherId = $dead ? $dead->father_id : null;
                    $oldMotherId = $dead ? $dead->mother_id : null;
                    $newFatherId = $request->input('father_id');
                    $newMotherId = $request->input('mother_id');

                    if ($dead) {
                        $dead->update($deadData);
                    } else {
                        \App\Models\DeadPepole::create($deadData);
                    }

                    // إذا تغير رقم هوية الأب، عدل المرفقات المرتبطة
                    if ($oldFatherId && $newFatherId && $oldFatherId != $newFatherId) {
                        $attachments = \App\Models\Attachment::where('person_identity_number', $oldFatherId)->get();
                        foreach ($attachments as $attachment) {
                            $folder = dirname(str_replace('storage/', '', $attachment->file_path));
                            $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                            $parts = explode('_', $attachment->stored_file_name);
                            $docType = $parts[0] ?? 'doc';
                            $newFileName = "{$docType}_{$fileIdNumber}_{$newFatherId}.{$extension}";
                            $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                            $newStoragePath = $folder . '/' . $newFileName;

                            if (Storage::disk('public')->exists($oldStoragePath)) {
                                Storage::disk('public')->move($oldStoragePath, $newStoragePath);
                            }

                            $attachment->update([
                                'person_identity_number' => $newFatherId,
                                'stored_file_name' => $newFileName,
                                'file_path' => "storage/{$newStoragePath}",
                            ]);
                        }
                    }
                    // إذا تغير رقم هوية الأم، عدل المرفقات المرتبطة
                    if ($oldMotherId && $newMotherId && $oldMotherId != $newMotherId) {
                        $attachments = \App\Models\Attachment::where('person_identity_number', $oldMotherId)->get();
                        foreach ($attachments as $attachment) {
                            $folder = dirname(str_replace('storage/', '', $attachment->file_path));
                            $extension = pathinfo($attachment->stored_file_name, PATHINFO_EXTENSION);
                            $parts = explode('_', $attachment->stored_file_name);
                            $docType = $parts[0] ?? 'doc';
                            $newFileName = "{$docType}_{$fileIdNumber}_{$newMotherId}.{$extension}";
                            $oldStoragePath = str_replace('storage/', '', $attachment->file_path);
                            $newStoragePath = $folder . '/' . $newFileName;

                            if (Storage::disk('public')->exists($oldStoragePath)) {
                                Storage::disk('public')->move($oldStoragePath, $newStoragePath);
                            }

                            $attachment->update([
                                'person_identity_number' => $newMotherId,
                                'stored_file_name' => $newFileName,
                                'file_path' => "storage/{$newStoragePath}",
                            ]);
                        }
                    }
                }
            }

            // 8. معالجة المرفقات الجديدة المتداخلة من attachmentsByPerson
            if ($request->has('attachmentsByPerson')) {
                foreach ($request->input('attachmentsByPerson') as $pIndex => $personData) {
                    $personKey = $personData['person_key'] ?? null;
                    $personIdentityNumber = $personData['person_identity_number'];
                    // استخدم دومًا رقم الملف الحالي للمجلد
                    $fileIdNumberPadded = $fileIdNumber;

                    if (!isset($personData['documents']) || !is_array($personData['documents'])) {
                        continue;
                    }
                    foreach ($personData['documents'] as $dIndex => $docMeta) {
                        // جلب UploadedFile
                        $uploadedFile = $request->file("attachmentsByPerson.{$pIndex}.documents.{$dIndex}.file");
                        if ($uploadedFile instanceof \Illuminate\Http\UploadedFile && $uploadedFile->isValid()) {
                            $allowed = ['jpeg', 'jpg', 'png', 'pdf'];
                            $ext = strtolower($uploadedFile->getClientOriginalExtension());
                            if (!in_array($ext, $allowed)) {
                                throw new \Exception("صيغة الملف غير مدعومة ({$ext})");
                            }
                            $fileType = $docMeta['file_type'];
                            $storedFileName = $docMeta['stored_file_name'];
                            if (!str_ends_with($storedFileName, ".{$ext}")) {
                                $storedFileName = "{$fileType}_{$fileIdNumberPadded}_{$personIdentityNumber}.{$ext}";
                            }
                            // استخدم مجلد uploads وليس attachments
                            $folder = "uploads/{$fileIdNumberPadded}";
                            $path = $uploadedFile->storeAs("public/{$folder}", $storedFileName);
                            $filePath = "storage/{$folder}/{$storedFileName}";
                            Attachment::create([
                                'person_identity_number' => $personIdentityNumber,
                                'stored_file_name' => $storedFileName,
                                'file_path' => $filePath,
                                'file_type' => $fileType,
                                'file_size' => $uploadedFile->getSize(),
                            ]);
                            Log::info("Created new attachment for person {$personIdentityNumber}", [
                                'file_type' => $fileType,
                                'file_path' => $filePath
                            ]);
                        }
                    }
                }
            }

            // 🏦 تحديث الحسابات البنكية
            if ($request->has('bank_accounts') && !empty($request->bank_accounts)) {
                $guardianRegistration = $data->file_id_number;

                Log::info('🏦 البدء في تحديث الحسابات البنكية', [
                    'data_id' => $data->id,
                    'guardian_registration' => $guardianRegistration,
                    'accounts_count' => count($request->bank_accounts)
                ]);

                // احتفاظ بـ IDs الحسابات المحدثة
                $processedIds = [];

                foreach ($request->bank_accounts as $index => $account) {
                    // التحقق من أن هناك حقل واحد على الأقل مملوء
                    $hasData = !empty($account['bank_name']) ||
                               !empty($account['re_guardian_name']) ||
                               !empty($account['person_owner_identity_number']) ||
                               !empty($account['re_phone_number']) ||
                               !empty($account['iban_usd']) ||
                               !empty($account['iban_shekel']);

                    if ($hasData) {
                        // 🆕 التحقق إذا كان هذا أول حساب بنكي للشخص (يُعتمد تلقائياً)
                        $existingAccountsCount = GuardianBankAccount::where('guardian_registration', $guardianRegistration)->count();
                        $isFirstAccount = ($existingAccountsCount == 0);

                        $bankAccountData = [
                            'guardian_registration' => $guardianRegistration,
                            're_id_number' => $data->data_id_number,
                            'bank_name' => $account['bank_name'] ?? null,
                            're_guardian_name' => $account['re_guardian_name'] ?? null,
                            'person_owner_identity_number' => $account['person_owner_identity_number'] ?? null,
                            're_phone_number' => $account['re_phone_number'] ?? null,
                            'iban_usd' => $account['iban_usd'] ?? null,
                            'iban_shekel' => $account['iban_shekel'] ?? null,
                            'check_account' => $isFirstAccount ? 1 : 0, // ✅ الحساب الأول يُعتمد تلقائياً
                        ];

                        if (!empty($account['id'])) {
                            // تحديث حساب موجود
                            GuardianBankAccount::where('id', $account['id'])->update($bankAccountData);
                            $processedIds[] = $account['id'];
                            Log::info('✅ تم تحديث الحساب البنكي', ['account_id' => $account['id']]);
                        } else {
                            // إنشاء حساب جديد
                            $newAccount = GuardianBankAccount::create($bankAccountData);
                            $processedIds[] = $newAccount->id;
                            Log::info('🟢 تم إنشاء حساب بنكي جديد', $bankAccountData);
                        }
                    }
                }
            }

            DB::commit();
            Log::info('--- Update success ---');
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث السجل والمرفقات بنجاح'
                ]);
            }
            return redirect()->route('admin.records.management.edit', $data->id)
                             ->with('success', 'تم تحديث السجل بنجاح');
        } catch (\Exception $e) {
            Log::error('Exception in update:', ['message'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
            DB::rollBack();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث السجل: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء تحديث السجل: ' . $e->getMessage());
        }
    }
    /**
     * حذف فرد الأسرة عبر AJAX
     */
    public function deleteFamilyMember(Request $request, $id)
    {
        try {
            Log::info("🗑️ Starting family member deletion for ID: {$id}");

            // حاول إيجاد العضو
            $member = RePeople::find($id);
            if (!$member) {
                Log::warning("⚠️ Family member not found", ['id' => $id]);
                // إذا لم يُعثر على العضو
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'فرد الأسرة غير موجود'
                    ], 404);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'فرد الأسرة غير موجود');
            }

            Log::info("👤 Family member found", [
                'person_id' => $member->person_id,
                'registration_id' => $member->registration_id
            ]);

            // البحث عن المرفقات المرتبطة بهذا الفرد وحذفها
            if ($member->person_id && preg_match('/^\d+$/', $member->person_id)) {
                $attachments = Attachment::where('person_identity_number', $member->person_id)->get();
                Log::info("📎 Found attachments for family member", ['count' => $attachments->count()]);

                $deletedFiles = 0;
                $failedFiles = 0;

                foreach ($attachments as $attachment) {
                    try {
                        // محاولة حذف الملف الفيزيائي
                        $possiblePaths = [
                            str_replace('storage/', '', $attachment->file_path ?? ''),
                            'uploads/' . $attachment->person_identity_number . '/' . $attachment->stored_file_name,
                            ltrim($attachment->file_path ?? '', '/'),
                        ];

                        $fileDeleted = false;
                        foreach ($possiblePaths as $path) {
                            if ($path && Storage::disk('public')->exists($path)) {
                                Storage::disk('public')->delete($path);
                                Log::info("✅ Deleted attachment file", [
                                    'path' => $path,
                                    'attachment_id' => $attachment->id
                                ]);
                                $deletedFiles++;
                                $fileDeleted = true;
                                break;
                            }
                        }

                        if (!$fileDeleted) {
                            Log::warning("⚠️ Attachment file not found", [
                                'attachment_id' => $attachment->id,
                                'tried_paths' => $possiblePaths
                            ]);
                            $failedFiles++;
                        }

                        // حذف المرفق من قاعدة البيانات
                        $attachment->delete();

                    } catch (\Exception $e) {
                        Log::error("❌ Error deleting attachment: " . $e->getMessage());
                        $failedFiles++;
                    }
                }

                // محاولة حذف مجلد الفرد إذا كان فارغاً
                $memberFolderPath = 'uploads/' . $member->person_id;
                if (Storage::disk('public')->exists($memberFolderPath)) {
                    try {
                        $files = Storage::disk('public')->files($memberFolderPath);
                        if (empty($files)) {
                            Storage::disk('public')->deleteDirectory($memberFolderPath);
                            Log::info("📁 Deleted empty member folder", ['folder' => $memberFolderPath]);
                        }
                    } catch (\Exception $e) {
                        Log::warning("⚠️ Could not delete member folder: " . $e->getMessage());
                    }
                }

                Log::info("📊 Family member attachments deletion summary", [
                    'deleted_files' => $deletedFiles,
                    'failed_files' => $failedFiles,
                    'total_attachments' => $attachments->count()
                ]);
            }

            // حذف العضو من قاعدة البيانات
            $member->delete();
            Log::info("✅ Family member deleted successfully");

            // الرد بنجاح
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->back()
                ->with('success', 'تم حذف فرد الأسرة وجميع مرفقاته بنجاح');

        } catch (\Exception $e) {
            Log::error("❌ Error deleting family member: " . $e->getMessage());
            Log::error("📍 Error trace: " . $e->getTraceAsString());

            // في حال حدوث استثناء أثناء الحذف
            $message = 'حدث خطأ أثناء حذف فرد الأسرة: ' . $e->getMessage();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 500);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', $message);
        }
    }
    /**
     * حذف مرفق عبر AJAX
     */
    public function deleteAttachment(Request $request, $id)
    {
        try {
            $attachment = \App\Models\Attachment::find($id);
            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'المرفق غير موجود'
                ], 404);
            }

            // حذف الملف من التخزين إذا كان موجوداً
            // تأكد أن المسار يبدأ بـ uploads أو attachments حسب تخزينك
            $storagePath = str_replace('storage/', '', $attachment->file_path);
            if ($storagePath && Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
            }

            $attachment->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المرفق: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            Log::info("🗑️ Starting deletion process for record ID: {$id}");

            $record = \App\Models\Data::findOrFail($id);
            Log::info("📋 Record found", ['file_id' => $record->file_id_number, 'name' => $record->data_first_name]);

            // جمع جميع أرقام الهوية المرتبطة بالسجل
            $allIdentityNumbers = [];

            // إضافة رقم الهوية الرئيسي ورقم الملف
            if (preg_match('/^\d+$/', $record->data_id_number)) {
                $allIdentityNumbers[] = (string) $record->data_id_number;
            }
            if (preg_match('/^\d+$/', $record->file_id_number)) {
                $allIdentityNumbers[] = (string) $record->file_id_number;
            }

            // جلب جميع أفراد الأسرة المرتبطين
            $familyMembers = \App\Models\RePeople::where('registration_id', $record->file_id_number)->get();
            $familyPeopleIds = $familyMembers->pluck('person_id')
                ->filter(function($id) {
                    return preg_match('/^\d+$/', $id);
                })
                ->map(function($id) { return (string) $id; })
                ->values()
                ->all();

            $allIdentityNumbers = array_merge($allIdentityNumbers, $familyPeopleIds);
            Log::info("👨‍👩‍👧‍👦 Family members IDs", $familyPeopleIds);

            // جلب بيانات المتوفين المرتبطة
            $dead = \App\Models\DeadPepole::where('re_file_id', $record->file_id_number)->first();
            $deadIds = [];
            if ($dead) {
                if (preg_match('/^\d+$/', $dead->father_id)) {
                    $deadIds[] = (string) $dead->father_id;
                }
                if (preg_match('/^\d+$/', $dead->mother_id)) {
                    $deadIds[] = (string) $dead->mother_id;
                }
                $allIdentityNumbers = array_merge($allIdentityNumbers, $deadIds);
            }
            Log::info("⚰️ Deceased IDs", $deadIds);

            // جلب جميع المرفقات المرتبطة بهذه الأرقام قبل الحذف
            $attachments = [];
            if (!empty($allIdentityNumbers)) {
                $attachments = \App\Models\Attachment::whereIn('person_identity_number', $allIdentityNumbers)->get();
                Log::info("📎 Found attachments", ['count' => $attachments->count()]);
            }

            // إضافة: البحث عن المرفقات بناءً على file_id_number في file_path أيضاً
            $additionalAttachments = \App\Models\Attachment::where('file_path', 'LIKE', "%{$record->file_id_number}%")
                ->orWhere('stored_file_name', 'LIKE', "%{$record->file_id_number}%")
                ->get();

            if ($additionalAttachments->count() > 0) {
                Log::info("📎 Found additional attachments by file_id", ['count' => $additionalAttachments->count()]);
                $attachments = $attachments->merge($additionalAttachments)->unique('id');
                Log::info("📎 Total unique attachments", ['count' => $attachments->count()]);
            }

            // حذف الملفات الفيزيائية من التخزين أولاً
            $deletedFiles = 0;
            $failedFiles = 0;
            foreach ($attachments as $attachment) {
                try {
                    // بناء مسار الملف بطرق مختلفة للتأكد من العثور عليه
                    $possiblePaths = [
                        // المسار الصحيح الأكثر احتمالاً بناءً على file_id_number
                        'uploads/' . $record->file_id_number . '/' . $attachment->stored_file_name,
                        // المسار المباشر من file_path
                        str_replace('storage/', '', $attachment->file_path ?? ''),
                        // مسار بناءً على person_identity_number و stored_file_name (احتياطي)
                        'uploads/' . $attachment->person_identity_number . '/' . $attachment->stored_file_name,
                        // مسار بناءً على file_path الكامل
                        ltrim($attachment->file_path ?? '', '/'),
                    ];

                    $fileDeleted = false;
                    foreach ($possiblePaths as $path) {
                        if ($path && Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                            Log::info("✅ Deleted file", ['path' => $path, 'attachment_id' => $attachment->id]);
                            $deletedFiles++;
                            $fileDeleted = true;
                            break;
                        }
                    }

                    if (!$fileDeleted) {
                        // محاولة حذف مجلد كامل بناءً على file_id_number (الطريقة الصحيحة)
                        $folderPath = 'uploads/' . $record->file_id_number;
                        if (Storage::disk('public')->exists($folderPath)) {
                            Storage::disk('public')->deleteDirectory($folderPath);
                            Log::info("📁 Deleted entire folder using file_id", ['folder' => $folderPath]);
                            $deletedFiles++;
                        } else {
                            // محاولة أخيرة بناءً على person_identity_number كخطة احتياطية
                            $fallbackFolderPath = 'uploads/' . $attachment->person_identity_number;
                            if (Storage::disk('public')->exists($fallbackFolderPath)) {
                                Storage::disk('public')->deleteDirectory($fallbackFolderPath);
                                Log::info("📁 Deleted entire folder using person_identity_number", ['folder' => $fallbackFolderPath]);
                                $deletedFiles++;
                            } else {
                                Log::warning("⚠️ File not found in any expected path", [
                                    'attachment_id' => $attachment->id,
                                    'file_path' => $attachment->file_path,
                                    'stored_file_name' => $attachment->stored_file_name,
                                    'person_identity_number' => $attachment->person_identity_number,
                                    'file_id_number' => $record->file_id_number,
                                    'tried_paths' => $possiblePaths,
                                    'tried_folders' => [$folderPath, $fallbackFolderPath]
                                ]);
                                $failedFiles++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error deleting file for attachment ID {$attachment->id}: " . $e->getMessage());
                    $failedFiles++;
                }
            }

            Log::info("📊 File deletion summary", [
                'deleted_files' => $deletedFiles,
                'failed_files' => $failedFiles,
                'total_attachments' => $attachments->count()
            ]);

            // محاولة أخيرة لحذف مجلد السجل الرئيسي إذا كان موجوداً وفارغاً
            $mainFolderPath = 'uploads/' . $record->file_id_number;
            if (Storage::disk('public')->exists($mainFolderPath)) {
                try {
                    $remainingFiles = Storage::disk('public')->allFiles($mainFolderPath);
                    if (empty($remainingFiles)) {
                        Storage::disk('public')->deleteDirectory($mainFolderPath);
                        Log::info("📁 Final cleanup: Deleted main record folder", ['folder' => $mainFolderPath]);
                    } else {
                        // إذا كانت هناك ملفات متبقية، احذفها بالقوة
                        Log::warning("📁 Forcing deletion of remaining files", [
                            'folder' => $mainFolderPath,
                            'remaining_files' => $remainingFiles
                        ]);

                        foreach ($remainingFiles as $file) {
                            try {
                                Storage::disk('public')->delete($file);
                                Log::info("🗑️ Force deleted file", ['file' => $file]);
                                $deletedFiles++;
                            } catch (\Exception $e) {
                                Log::error("❌ Could not force delete file: {$file} - " . $e->getMessage());
                                $failedFiles++;
                            }
                        }

                        // محاولة حذف المجلد مرة أخيرة
                        if (Storage::disk('public')->exists($mainFolderPath)) {
                            Storage::disk('public')->deleteDirectory($mainFolderPath);
                            Log::info("📁 Force deleted main record folder after cleanup", ['folder' => $mainFolderPath]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("⚠️ Could not perform final folder cleanup: " . $e->getMessage());
                }
            } else {
                Log::info("📁 Main record folder already deleted or not found", ['folder' => $mainFolderPath]);
            }

            // حذف المرفقات من قاعدة البيانات (بما في ذلك الإضافية التي وُجدت)
            if ($attachments->count() > 0) {
                $attachmentIds = $attachments->pluck('id')->toArray();
                $deletedAttachmentsCount = \App\Models\Attachment::whereIn('id', $attachmentIds)->delete();
                Log::info("🗄️ Deleted all related attachments from database", ['count' => $deletedAttachmentsCount]);
            }

            // الآن حذف البيانات من قاعدة البيانات
            DB::beginTransaction();

            // حذف أفراد الأسرة
            $deletedFamilyCount = \App\Models\RePeople::where('registration_id', $record->file_id_number)->delete();
            Log::info("👥 Deleted family members", ['count' => $deletedFamilyCount]);

            // حذف بيانات المتوفين
            $deletedDeadCount = \App\Models\DeadPepole::where('re_file_id', $record->file_id_number)->delete();
            Log::info("⚰️ Deleted deceased records", ['count' => $deletedDeadCount]);

            // حذف السجل الرئيسي
            $record->delete();
            Log::info("📋 Deleted main record");

            DB::commit();
            Log::info("✅ Deletion process completed successfully");

            return redirect()->route('admin.records.management')->with('success', "تم حذف السجل وجميع البيانات المرتبطة به بنجاح. تم حذف {$deletedFiles} ملف من التخزين.");

        } catch (\Exception $e) {
            if (isset($record)) {
                DB::rollBack();
            }
            Log::error("❌ Error during deletion process: " . $e->getMessage());
            Log::error("📍 Error trace: " . $e->getTraceAsString());

            return redirect()->route('admin.records.management')->with('error', 'حدث خطأ أثناء حذف السجل: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: جلب سجلات موظف مع pagination
     */
    public function ajaxAdminRecords($adminId)
    {
        $admin = \App\Models\User::findOrFail($adminId);
        $perPage = 15;
        $page = request('page', 1);
        $records = \App\Models\Data::where('data_user_insert_data', $admin->name)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // بناء جدول HTML
        if ($records->count()) {
            $html = view('admin.dashboard.component._admin_records_table', [
                'records' => $records,
                'admin' => $admin
            ])->render();
            return response()->json(['success' => true, 'html' => $html]);
        } else {
            return response()->json(['success' => false, 'message' => 'لا توجد سجلات مدخلة لهذا الموظف.']);
        }
    }

    /**
     * AJAX: جلب الحسابات البنكية لشخص معين بناءً على رقم هوية المعيل
     */
    public function getBankAccounts(Request $request)
    {
        try {
            $guardianIdentity = $request->input('guardian_identity');

            if (!$guardianIdentity) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم هوية المعيل مطلوب'
                ], 400);
            }

            // البحث عن file_id_number من جدول data باستخدام identity_number
            $guardianFileId = Data::where('data_id_number', $guardianIdentity)
                                 ->value('file_id_number');

            if (!$guardianFileId) {
                // محاولة أخيرة: البحث في جدول data باستخدام file_id_number مباشرة
                $existsInData = Data::where('file_id_number', $guardianIdentity)->exists();
                if ($existsInData) {
                    $guardianFileId = $guardianIdentity;
                }
            }

            // 🆕 إذا لم نجد في data، نبحث في dead_people (للمتوفين)
            if (!$guardianFileId) {
                $deadPeopleRecord = DB::table('dead_people')
                    ->where('father_id', $guardianIdentity)
                    ->orWhere('mother_id', $guardianIdentity)
                    ->first();

                if ($deadPeopleRecord) {
                    $guardianFileId = $deadPeopleRecord->re_file_id;
                    Log::info('🏦 تم العثور على file_id من dead_people', [
                        'guardian_identity' => $guardianIdentity,
                        'guardian_file_id' => $guardianFileId
                    ]);
                }
            }

            // 🆕 محاولة إضافية: البحث مباشرة في guardian_bank_accounts باستخدام re_id_number
            if (!$guardianFileId) {
                $directAccount = GuardianBankAccount::where('re_id_number', $guardianIdentity)->first();
                if ($directAccount) {
                    $guardianFileId = $directAccount->guardian_registration;
                    Log::info('🏦 تم العثور على file_id من guardian_bank_accounts مباشرة', [
                        'guardian_identity' => $guardianIdentity,
                        'guardian_file_id' => $guardianFileId
                    ]);
                }
            }

            if (!$guardianFileId) {
                return response()->json([
                    'success' => true,
                    'accounts' => [],
                    'message' => 'لا توجد حسابات بنكية'
                ]);
            }

            // جلب الحسابات البنكية
            $bankAccounts = GuardianBankAccount::with('bank')
                ->where('guardian_registration', $guardianFileId)
                ->get();

            Log::info('🏦 تم جلب الحسابات البنكية', [
                'guardian_identity' => $guardianIdentity,
                'guardian_file_id' => $guardianFileId,
                'accounts_count' => $bankAccounts->count()
            ]);

            return response()->json([
                'success' => true,
                'accounts' => $bankAccounts,
                'guardian_file_id' => $guardianFileId
            ]);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في جلب الحسابات البنكية:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الحسابات البنكية'
            ], 500);
        }
    }

    /**
     * جلب الحسابات البنكية باستخدام رقم الملف مباشرة (file_id)
     */
    public function getBankAccountsByFileId(Request $request)
    {
        try {
            $fileId = $request->input('file_id');

            if (!$fileId) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الملف مطلوب'
                ], 400);
            }

            // جلب الحسابات البنكية مباشرة باستخدام file_id
            $bankAccounts = GuardianBankAccount::with('bank')
                ->where('guardian_registration', $fileId)
                ->get();

            Log::info('🏦 تم جلب الحسابات البنكية برقم الملف', [
                'file_id' => $fileId,
                'accounts_count' => $bankAccounts->count()
            ]);

            return response()->json([
                'success' => true,
                'accounts' => $bankAccounts,
                'guardian_file_id' => $fileId
            ]);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في جلب الحسابات البنكية برقم الملف:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الحسابات البنكية'
            ], 500);
        }
    }

    /**
     * اعتماد حساب بنكي معين للمعيل
     */
    public function approveBankAccount(Request $request)
    {
        try {
            $accountId = $request->input('account_id');
            $guardianFileId = $request->input('guardian_file_id');

            if (!$accountId) {
                return response()->json([
                    'success' => false,
                    'message' => 'معرف الحساب البنكي مطلوب'
                ], 400);
            }

            // جلب الحساب المطلوب اعتماده
            $account = GuardianBankAccount::findOrFail($accountId);

            // إلغاء اعتماد جميع الحسابات الأخرى لنفس المعيل
            GuardianBankAccount::where('guardian_registration', $account->guardian_registration)
                ->where('id', '!=', $accountId)
                ->update(['check_account' => 0]);

            // اعتماد الحساب المحدد
            $account->check_account = 1;
            $account->save();

            Log::info('✅ تم اعتماد الحساب البنكي', [
                'account_id' => $accountId,
                'guardian_registration' => $account->guardian_registration,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم اعتماد الحساب البنكي بنجاح',
                'account_id' => $accountId
            ]);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في اعتماد الحساب البنكي:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'account_id' => $request->input('account_id')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء اعتماد الحساب البنكي'
            ], 500);
        }
    }

    /**
     * تصدير تقرير الأسرة بصيغة PDF
     */
    public function exportFamilyReport($id, Request $request)
    {
        // جلب معرف الفرد المحدد
        $memberId = $request->query('member_id');

        // جلب معرف الجمعية إن وجد
        $sponsorId = $request->query('sponsor_id');

        // تحميل تصميم التقرير للجمعية إن وجد
        $reportDesign = null;
        if ($sponsorId) {
            $reportDesign = \App\Models\SponsorReportDesign::where('sponsor_id', $sponsorId)->first();
        }

        // جلب البيانات الأساسية للمعيل
        $data = Data::with([
            'section',
            'requestStatus',
            'categoryOfRelation',
            'healthStatus',
            'city',
            'province',
            'attachments',
            'rePeople.healthStatus',
            'rePeople.guaranteeType',
            'rePeople.sponsorshipStatus',
            'rePeople.attachments',
        ])->findOrFail($id);

        // جلب أفراد الأسرة
        $allFamilyMembers = $data->rePeople;

        // البحث عن الفرد المحدد
        $selectedMember = $allFamilyMembers->firstWhere('id', $memberId);

        if (!$selectedMember) {
            abort(404, 'الفرد المحدد غير موجود');
        }

        // ترتيب الأفراد: الفرد المحدد أولاً، ثم الباقي
        $familyMembers = collect([$selectedMember])->merge(
            $allFamilyMembers->filter(function($member) use ($memberId) {
                return $member->id != $memberId;
            })
        );

        // حساب عدد الأفراد الذين يعيلهم المعيل عندما تكون القيمة غير متوفرة
        // يتضمن العدد اليتيم الأساسي والموجودين في جدول الإخوة المعروض
        $computedDependents = $familyMembers->count();

        // التحقق من حالة الكفالة لكل فرد
        foreach ($familyMembers as $member) {
            $hasSponsorship = \App\Models\Sponsorship::where('identity_number', $member->person_id)->exists();
            $member->is_sponsored = $hasSponsorship;
        }

        // جلب حالة كفالة المعيل
        $guardianHasSponsorship = \App\Models\Sponsorship::where('identity_number', $data->data_id_number)->exists();
        $data->is_sponsored = $guardianHasSponsorship;

        // جلب جميع صور الملف من المرفقات
        $allAttachments = collect();

        // إضافة مرفقات المعيل
        if ($data->attachments) {
            $allAttachments = $allAttachments->merge($data->attachments);
        }

        // إضافة مرفقات أفراد الأسرة
        foreach ($familyMembers as $member) {
            if ($member->attachments) {
                $allAttachments = $allAttachments->merge($member->attachments);
            }
        }

        // تنظيم الوثائق حسب نوعها
        $personalPhotos = collect();
        $otherDocuments = collect();

        // جلب أنواع الوثائق من قاعدة البيانات
        $documentTypes = DB::table('document_types')->get()->keyBy('pref');

        foreach ($allAttachments as $attachment) {

            // التحقق من نوع الوثيقة
            $isPersonalPhoto = false;
            $isFullBodyPhoto = false;

            // البحث عن نوع الوثيقة في document_types
            $docType = $documentTypes->get($attachment->file_type);
            if ($docType) {
                $description = strtolower($docType->description ?? '');
                // النوع 12 فقط = صور شخصية (صورة الهوية النوع 3 تُعتبر وثيقة عادية)
                $isPersonalPhoto = str_contains($description, 'صور شخصية') ||
                                  $attachment->file_type == '12';
                $isFullBodyPhoto = str_contains($description, 'صورة طولية') ||
                                  str_contains($description, 'full body') ||
                                  $attachment->file_type == '19';
            }

            // يمكن أيضاً الفحص من اسم الملف
            if (!$isPersonalPhoto && $attachment->stored_file_name) {
                $fileName = strtolower($attachment->stored_file_name);
                // الملفات التي تبدأ بـ 12_ فقط هي صور شخصية
                $isPersonalPhoto = str_starts_with($fileName, '12_') ||
                                  str_contains($fileName, 'personal_photo');
            }

            if (!$isFullBodyPhoto && $attachment->stored_file_name) {
                $fileName = strtolower($attachment->stored_file_name);
                // الملفات التي تبدأ بـ 19_ هي صور طولية
                $isFullBodyPhoto = str_starts_with($fileName, '19_') ||
                                  str_contains($fileName, 'full_body');
            }

            // تصنيف الوثيقة
            if ($isPersonalPhoto && !$isFullBodyPhoto) {
                $personalPhotos->push($attachment);
            } elseif (!$isFullBodyPhoto) {
                // استبعاد الصور الطولية
                $otherDocuments->push($attachment);
            }
        }

        // فلترة الصور فقط (استبعاد PDF)
        $documentImages = $allAttachments->filter(function($attachment) {
            return Str::endsWith(strtolower($attachment->stored_file_name), ['jpg', 'jpeg', 'png', 'gif']);
        });

        // المسار الكامل لصورة الخلفية
        $backgroundPath = public_path('background102.jpg');

        // تحويل صورة الخلفية إلى base64
        $backgroundBase64 = '';
        if (file_exists($backgroundPath)) {
            $backgroundBase64 = base64_encode(file_get_contents($backgroundPath));
        }

        // إعداد بيانات التصميم المخصص
        $customDesign = null;
        if ($reportDesign) {
            Log::info('Loading report design', [
                'sponsor_id' => $sponsorId,
                'design_id' => $reportDesign->id,
                'background_type' => $reportDesign->background_type,
                'has_single_image' => !empty($reportDesign->single_image),
                'has_header_image' => !empty($reportDesign->header_image),
                'has_main_image' => !empty($reportDesign->main_image),
                'has_footer_image' => !empty($reportDesign->footer_image),
            ]);

            $customDesign = [
                'background_type' => $reportDesign->background_type,
                'theme_colors' => $reportDesign->theme_colors ?? [
                    'primary' => '#1a1a1a',
                    'secondary' => '#4a4a4a',
                    'accent' => '#007bff'
                ],
                'single_image' => $reportDesign->single_image_base64,
                'header_image' => $reportDesign->header_image_base64,
                'main_image' => $reportDesign->main_image_base64,
                'footer_image' => $reportDesign->footer_image_base64,
            ];

            Log::info('Custom design prepared', [
                'has_single_image_base64' => !empty($customDesign['single_image']),
                'has_header_image_base64' => !empty($customDesign['header_image']),
                'has_main_image_base64' => !empty($customDesign['main_image']),
                'has_footer_image_base64' => !empty($customDesign['footer_image']),
                'single_image_length' => $customDesign['single_image'] ? strlen($customDesign['single_image']) : 0,
                'header_image_length' => $customDesign['header_image'] ? strlen($customDesign['header_image']) : 0,
                'main_image_length' => $customDesign['main_image'] ? strlen($customDesign['main_image']) : 0,
                'footer_image_length' => $customDesign['footer_image'] ? strlen($customDesign['footer_image']) : 0,
                'header_starts_with' => $customDesign['header_image'] ? substr($customDesign['header_image'], 0, 30) : 'null',
                'main_starts_with' => $customDesign['main_image'] ? substr($customDesign['main_image'], 0, 30) : 'null',
                'footer_starts_with' => $customDesign['footer_image'] ? substr($customDesign['footer_image'], 0, 30) : 'null',
            ]);
        } else {
            Log::warning('No report design found for sponsor', ['sponsor_id' => $sponsorId]);
        }

        // توليد PDF
        $pdf = PDF::loadView('admin.dashboard.reports.family_report', [
            'guardian' => $data,
            'selectedMember' => $selectedMember,
            'familyMembers' => $familyMembers,
            'documentImages' => $documentImages,
            'personalPhotos' => $personalPhotos,
            'otherDocuments' => $otherDocuments,
            'computedDependents' => $computedDependents,
            'backgroundBase64' => $backgroundBase64,
            'customDesign' => $customDesign
        ]);

        // تحسين إعدادات PDF
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('encoding', 'UTF-8');
        $pdf->setOption('margin-top', 0);
        $pdf->setOption('margin-bottom', 0);
        $pdf->setOption('margin-left', 0);
        $pdf->setOption('margin-right', 0);

        return $pdf->stream('family_report_' . $selectedMember->person_id . '.pdf');
    }
}
