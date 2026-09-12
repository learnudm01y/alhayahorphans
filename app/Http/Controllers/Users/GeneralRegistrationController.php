<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\AcademicDegree;
use App\Models\Attachment;
use App\Models\BankName;
use App\Models\CategoryOfRelation;
use App\Models\CI_PERSONAL_CD;
use App\Models\City;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\AdditionalDeceased;
use App\Models\DeathReason;
use App\Models\DisplacementStatus;
use App\Models\DocumentType;
use App\Models\Employment;
use App\Models\GeneralCategory;
use App\Models\HealthStatus;
use App\Models\HousingStatus;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\RePeople;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfAccommodation;
use App\Models\TypeOfGuarantee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\GuardianBankAccount;
use App\Services\BankAccountValidationService;

class GeneralRegistrationController extends Controller
{
    public function index(): View
    {
        $generalSection = GeneralCategory::all();
        // $file_id_number = generateFiveDigitCode(Data::class, 'file_id_number');
        // حماية من Deadlock: إذا فشل توليد الرقم، نعرض الصفحة بكود مؤقت
        try {
            $file_id_number = generateUniqueReservedCode('data', 'file_id_number');
        } catch (\Throwable $e) {
            Log::warning('generateUniqueReservedCode failed, using temp fallback', [
                'error' => $e->getMessage()
            ]);
            // Fallback: رقم مؤقت فريد لا يُحفظ في DB - المستخدم سيحصل على رقم حقيقي عند الحفظ
            $file_id_number = 'TEMP-' . strtoupper(substr(uniqid(), -6));
        }
        $category_of_relationship = CategoryOfRelation::all();
        $ci_personal_cd = CI_PERSONAL_CD::all(); // الحالة الاجتماعية
        $academic_qualification = AcademicDegree::all();
        $displacement_status = DisplacementStatus::all();
        $city = City::all();
        $province = Province::all();
        $health_status = HealthStatus::all();
        $employment_status_breadwinner = Employment::all();
        $HousingStatus = HousingStatus::all();
        $TypeOfAccommodation = TypeOfAccommodation::all();
        $documentTypes = \App\Models\DocumentType::all(); // Assuming you have a DocumentType model
        $sponsorship_status = SponsorshipStatus::all();
        $guarantee_types = TypeOfGuarantee::all(); // Assuming you have a TypeOfGuarantee model
        $death_reasons = DeathReason::all(); // Assuming you have a DeathReason model
        $bank_name = BankName::all(); // Assuming you have a BankName model
        
        // القيمة الافتراضية للضغط: مفعّل للتسجيل الرئيسي (لا توجد جمعية محددة بعد)
        $compressAttachments = true;
        
        return view(
            'user.generalRegistration.create',
            compact(
                'generalSection',
                'file_id_number',
                'category_of_relationship',
                'ci_personal_cd',
                'academic_qualification',
                'displacement_status',
                'city',
                'province',
                'health_status',
                'employment_status_breadwinner',
                'HousingStatus',
                'TypeOfAccommodation',
                'documentTypes',
                'sponsorship_status',
                'guarantee_types',
                'death_reasons',
                'bank_name',
                'compressAttachments'
            )
        );
    }

    public function store(Request $request)
    {
        try {
            // 1. Validate basic data and attachments
            $request->validate([
                'file_id_number' => 'required|string',
                'data_section_id' => 'required|integer',
                'data_id_number' => 'required|string|digits:9',
                'data_first_name' => 'required|string|max:255',
                'data_father_name' => 'nullable|string|max:255',
                'data_grand_father_name' => 'nullable|string|max:255',
                'data_family_name' => 'nullable|string|max:255',
                'data_relationship' => 'nullable|string|max:100',
                'data_birth_date' => 'nullable|date',
                'data_gender' => 'nullable|in:1,2',
                'data_phone_number' => 'nullable|string|max:15',
                'data_alt_phone_number' => 'nullable|string|max:15',
                'data_number_of_individuals' => 'nullable|integer',
                'data_marital_status' => 'nullable|string',
                'data_academic_qualification' => 'nullable|string',
                'data_displacement_status' => 'nullable|string',
                'data_address_before_displacement' => 'nullable|string',
                'data_current_address' => 'nullable|string',
                'data_city' => 'nullable|string',
                'data_province' => 'required|integer|exists:provinces,id',
                'data_health_status' => 'nullable|string',
                'data_description_needs' => 'nullable|string',
                'data_number_mail' => 'nullable|integer',
                'data_number_female' => 'nullable|integer',
                'data_number_of_individuals_with_chronic_diseases' => 'nullable|integer',
                'data_number_of_people_with_special_needs' => 'nullable|integer',
                'data_employment_status_breadwinner' => 'nullable|string',
                'data_housing_status' => 'nullable|string',
                'data_current_housing_type' => 'nullable|string',
                'data_request_status' => 'nullable|integer',
                // Mother fields (living - baseTap)
                'mother_id' => 'nullable|string|digits:9',
                'mother_first_name' => 'nullable|string|max:255',
                'mother_second_name' => 'nullable|string|max:255',
                'mother_third_name' => 'nullable|string|max:255',
                'mother_last_name' => 'nullable|string|max:255',
                'mother_is_alive' => 'nullable|in:0,1',
                // Mother fields (deceased - deceased tab)
                'deceased_mother_id' => 'nullable|string|digits:9',
                'deceased_mother_first_name' => 'nullable|string|max:255',
                'deceased_mother_second_name' => 'nullable|string|max:255',
                'deceased_mother_third_name' => 'nullable|string|max:255',
                'deceased_mother_last_name' => 'nullable|string|max:255',
                // Family members (if any)
                'family_members' => 'sometimes|array',
                // Attachments
                'document_file.*' => 'required|file|mimes:jpg,jpeg,png,pdf,heic|max:5120',
            ], [
                'file_id_number.required' => 'رقم الملف الموحد مطلوب.',
                'data_id_number.required' => 'رقم الهوية مطلوب.',
                'data_id_number.digits' => 'رقم الهوية يجب أن يكون 9 أرقام بالضبط.',
                'mother_id.digits' => 'رقم هوية الأم يجب أن يكون 9 أرقام بالضبط.',
                'deceased_mother_id.digits' => 'رقم هوية الأم المتوفية يجب أن يكون 9 أرقام بالضبط.',
                'document_file.*.mimes' => 'يجب أن تكون صيغة الملف jpg أو jpeg أو png أو pdf أو heic.',
                'document_file.*.max' => 'حجم الملف لا يجوز أن يتجاوز 5 ميغابايت.',
            ]);


            DB::beginTransaction();

            // استخدم رقم الملف مع الأصفار البادئة دائماً
            $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);

            $guardianIdentity = $request->input('data_id_number');
            $useExistingGuardian = false;
            $existingGuardianData = null;
            $existingFileIdNumber = null;

            // 1️⃣ أولاً: البحث عن معيل موجود في قاعدة البيانات
            if (!empty($guardianIdentity)) {
                $existingGuardianData = Data::where('data_id_number', $guardianIdentity)->first();

                if ($existingGuardianData) {
                    $useExistingGuardian = true;
                    $existingFileIdNumber = $existingGuardianData->file_id_number;
                    $fileIdNumber = $existingFileIdNumber; // استخدام رقم الملف الموجود
                    Log::info('🔗 تم العثور على معيل موجود', [
                        'file_id' => $existingFileIdNumber,
                        'identity' => $guardianIdentity
                    ]);
                }
            }

            // 2️⃣ ثانياً: التحقق من عدم ارتباط المعيل بمعيل آخر
            if (!$useExistingGuardian && !empty($guardianIdentity)) {
                $linkedData = Data::where('data_id_number', $guardianIdentity)->first();
                if ($linkedData && $linkedData->file_id_number != $fileIdNumber) {
                    DB::rollBack();
                    $errorMsg = 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $errorMsg], 422);
                    }
                    return redirect()->back()->withInput()->with('error', $errorMsg);
                }

                $linkedRePeople = RePeople::where('person_id', $guardianIdentity)
                    ->where('registration_id', '!=', $fileIdNumber)
                    ->first();
                if ($linkedRePeople) {
                    DB::rollBack();
                    $errorMsg = 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $errorMsg], 422);
                    }
                    return redirect()->back()->withInput()->with('error', $errorMsg);
                }
            }

            // تحقق من عدم تكرار رقم الملف العام (إلا إذا كنا نستخدم معيل موجود)
            if (!$useExistingGuardian && \App\Models\Data::where('file_id_number', $fileIdNumber)->exists()) {
                // إذا كان الطلب AJAX أرجع رسالة واضحة
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'رقم الملف العام مستخدم مسبقاً. يرجى تحديث الصفحة أو استخدام رقم جديد.'
                    ], 422);
                }
                // إذا كان الطلب عادي
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'رقم الملف العام مستخدم مسبقاً. يرجى تحديث الصفحة أو استخدام رقم جديد.');
            }

            // 2. Store main Data record أو استخدام المعيل الموجود
            $data = null;

            if ($useExistingGuardian && $existingGuardianData) {
                // 🆕 تحديث بيانات المعيل الموجود
                $existingGuardianData->update([
                    'data_section_id' => $request->input('data_section_id'),
                    'data_id_number' => $request->input('data_id_number'),
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
                    'data_request_status' => 2, // تحديث طلب موجود
                ]);
                $data = $existingGuardianData;
                $fileIdNumber = $existingFileIdNumber;

                Log::info('✅ تم تحديث بيانات المعيل', [
                    'file_id_number' => $fileIdNumber,
                    'guardian_identity' => $guardianIdentity,
                ]);
            } else {
                // إنشاء سجل معيل جديد
                $data = Data::create([
                'file_id_number' => $fileIdNumber,
                'data_section_id' => $request->input('data_section_id'),
                'data_id_number' => $request->input('data_id_number'),
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
                'data_province' => $request->input('data_province'), // <-- تم التصحيح هنا
                'data_health_status' => $request->input('data_health_status'),
                'data_description_needs' => $request->input('data_description_needs'),
                'data_number_mail' => $request->input('data_number_mail'),
                'data_number_female' => $request->input('data_number_female'),
                'data_number_of_individuals_with_chronic_diseases' => $request->input('data_number_of_individuals_with_chronic_diseases'),
                'data_number_of_people_with_special_needs' => $request->input('data_number_of_people_with_special_needs'),
                'data_employment_status_breadwinner' => $request->input('data_employment_status_breadwinner'),
                'data_housing_status' => $request->input('data_housing_status'),
                'data_current_housing_type' => $request->input('data_current_housing_type'),
                'data_user_insert_data' => "N_user" ,
                'data_request_status' => 1, // تأكد من وجود هذا السطر دائماً
            ]);

                // وضع علامة على الرقم كمستخدم في جدول reserved_codes
                markCodeAsUsed($fileIdNumber);
                Log::info('✅ تم إنشاء سجل معيل جديد: ' . $fileIdNumber);
            } // نهاية else (إنشاء سجل جديد)

            // إضافة بيانات الحساب البنكي إذا وُجدت أي قيمة بنكية مع التحقق من التكرار
            $bankAccounts = $request->input('bank_accounts', []);
            $bankValidationService = app(BankAccountValidationService::class);
            $duplicateBankErrors = [];
            $guardianIdNumber = $request->input('data_id_number'); // رقم هوية المعيل

            Log::info('🟢 بيانات الحسابات البنكية المستلمة من الواجهة:', ['bank_accounts' => $bankAccounts]);
            if (is_array($bankAccounts) && count($bankAccounts) > 0) {
                foreach ($bankAccounts as $index => $bankAccount) {
                    Log::info('🔵 حساب بنكي فردي:', $bankAccount);
                    // تأكد من استقبال وتخزين person_owner_identity_number
                    $reIdNumber = $bankAccount['person_owner_identity_number'] ?? null;
                    if (
                        (!empty($bankAccount['bank_name'])) ||
                        (!empty($bankAccount['iban_usd'])) ||
                        (!empty($bankAccount['iban_shekel'])) ||
                        (!empty($bankAccount['re_guardian_name'])) ||
                        (!empty($bankAccount['re_phone_number'])) ||
                        (!empty($reIdNumber))
                    ) {
                        // 🔍 التحقق من عدم تكرار الحساب البنكي
                        $accountIdNumber = $reIdNumber ?? $guardianIdNumber;

                        $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
                            'guardian_registration' => $fileIdNumber,
                            're_phone_number' => $bankAccount['re_phone_number'] ?? null,
                            'bank_name' => $bankAccount['bank_name'] ?? null,
                            're_id_number' => $accountIdNumber
                        ]);

                        if ($duplicateCheck['is_duplicate']) {
                            $duplicateBankErrors[] = [
                                'index' => $index + 1,
                                'message' => $duplicateCheck['message']
                            ];
                            Log::warning('⚠️ محاولة إضافة حساب بنكي مكرر في التسجيل العام', [
                                'index' => $index,
                                'existing_account' => $duplicateCheck['existing_account']
                            ]);
                            continue; // تجاوز هذا الحساب المكرر
                        }

                        // 🆕 التحقق إذا كان هذا أول حساب بنكي للشخص (يُعتمد تلقائياً)
                        $existingAccountsCount = GuardianBankAccount::where('guardian_registration', $fileIdNumber)->count();
                        $isFirstAccount = ($existingAccountsCount == 0);

                        // البحث عن حساب بنكي موجود لنفس الشخص
                        $existingBankAccount = null;
                        if (!empty($reIdNumber)) {
                            $existingBankAccount = GuardianBankAccount::where('guardian_registration', $fileIdNumber)
                                ->where('person_owner_identity_number', $reIdNumber)
                                ->first();
                        }

                        $bankData = [
                            'guardian_registration' => $fileIdNumber,
                            'bank_name' => $bankAccount['bank_name'] ?? null,
                            'iban_usd' => $bankAccount['iban_usd'] ?? null,
                            'iban_shekel' => $bankAccount['iban_shekel'] ?? null,
                            'person_owner_identity_number' => $reIdNumber,
                            're_id_number' => $reIdNumber,
                            're_guardian_name' => $bankAccount['re_guardian_name'] ?? null,
                            're_phone_number' => $bankAccount['re_phone_number'] ?? null,
                            'check_account' => $isFirstAccount ? 1 : 0,
                        ];

                        if ($existingBankAccount) {
                            $existingBankAccount->update($bankData);
                            Log::info('✅ تم تحديث حساب بنكي موجود', ['person_id' => $reIdNumber]);
                        } else {
                            GuardianBankAccount::create($bankData);
                            Log::info('🟢 تم إنشاء حساب بنكي جديد', ['person_id' => $reIdNumber]);
                        }
                    } else {
                        Log::warning('⚠️ لم يتم تخزين حساب بنكي بسبب نقص البيانات', $bankAccount);
                    }
                }
            }


            // 3. Store deceased only إذا كان القسم أيتام ويوجد بيانات للأب أو الأم
            if ($request->input('data_section_id') == 1) {
                $fatherFilled = $request->filled('father_first_name') || $request->filled('father_last_name') || $request->filled('father_id');
                $motherFilled = $request->filled('deceased_mother_first_name') || $request->filled('deceased_mother_last_name') || $request->filled('deceased_mother_id');

                if ($fatherFilled || $motherFilled) {
                    // التحقق من صحة أرقام الهوية
                    $validationRules = [];
                    $validationMessages = [];

                    if ($request->filled('father_id')) {
                        $validationRules['father_id'] = 'required|digits:9';
                        $validationMessages['father_id.required'] = 'رقم هوية الأب مطلوب';
                        $validationMessages['father_id.digits'] = 'رقم هوية الأب يجب أن يكون 9 أرقام بالضبط';
                    }

                    if ($request->filled('deceased_mother_id')) {
                        $validationRules['deceased_mother_id'] = 'required|digits:9';
                        $validationMessages['deceased_mother_id.required'] = 'رقم هوية الأم مطلوب';
                        $validationMessages['deceased_mother_id.digits'] = 'رقم هوية الأم يجب أن يكون 9 أرقام بالضبط';
                    }

                    // تنفيذ التحقق إذا كان هناك قواعد
                    if (!empty($validationRules)) {
                        $request->validate($validationRules, $validationMessages);
                    }

                    // تحديث أو إنشاء بيانات الأب والأم المتوفين
                    $existingDeadRecord = DeadPepole::where('re_file_id', $fileIdNumber)->first();
                    $deceasedData = [
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
                        'mother_first_name' => $request->input('deceased_mother_first_name'),
                        'mother_second_name' => $request->input('deceased_mother_second_name'),
                        'mother_third_name' => $request->input('deceased_mother_third_name'),
                        'mother_last_name' => $request->input('deceased_mother_last_name'),
                        'mother_id' => $request->input('deceased_mother_id'),
                        'mother_death_date' => $request->input('mother_death_date'),
                        'mother_death_reason' => $request->input('mother_death_reason'),
                    ];

                    if ($existingDeadRecord) {
                        $existingDeadRecord->update($deceasedData);
                        Log::info('✅ تم تحديث بيانات الأب والأم المتوفين', ['file_id' => $fileIdNumber]);
                    } else {
                        DeadPepole::create($deceasedData);
                        Log::info('✅ تم إنشاء سجل بيانات الأب والأم المتوفين', ['file_id' => $fileIdNumber]);
                    }
                }

                // 🆕 حفظ المتوفين الإضافيين (غير الأب والأم) - يتم تنفيذه دائماً للقسم 1 (أيتام)
                $additionalDeceased = $request->input('additional_deceased', []);

                // تتبع البيانات المستلمة
                Log::info('📋 البيانات المستلمة من النموذج للمتوفين الإضافيين:', [
                    'additional_deceased_raw' => $request->input('additional_deceased'),
                    'additional_deceased_array' => $additionalDeceased,
                    'is_array' => is_array($additionalDeceased),
                    'count' => is_array($additionalDeceased) ? count($additionalDeceased) : 0,
                    'all_input_keys' => array_keys($request->all())
                ]);

                if (is_array($additionalDeceased) && count($additionalDeceased) > 0) {
                    Log::info('🟢 بدء حفظ المتوفين الإضافيين:', ['count' => count($additionalDeceased)]);

                    foreach ($additionalDeceased as $index => $deceased) {
                        // الحصول على رقم الهوية (يمكن أن يكون id_number أو person_id)
                        $personId = $deceased['id_number'] ?? $deceased['person_id'] ?? null;

                        // تجاهل السجلات الفارغة
                        if (empty($deceased['first_name']) && empty($deceased['last_name']) && empty($personId)) {
                            Log::info('⏭️ تجاهل متوفي إضافي فارغ:', ['index' => $index]);
                            continue;
                        }

                        // التحقق من صحة رقم الهوية إذا تم إدخاله
                        if (!empty($personId) && !preg_match('/^\d{9}$/', $personId)) {
                            Log::warning('⚠️ رقم هوية متوفي إضافي غير صالح:', [
                                'index' => $index,
                                'person_id' => $personId
                            ]);
                        }

                        // تحديث أو إنشاء متوفي إضافي
                        $existingAdditional = null;
                        if (!empty($personId)) {
                            $existingAdditional = \App\Models\AdditionalDeceased::where('re_file_id', $fileIdNumber)
                                ->where('person_id', $personId)
                                ->first();
                        }

                        $additionalData = [
                            're_file_id' => $fileIdNumber,
                            'person_id' => $personId,
                            'first_name' => $deceased['first_name'] ?? null,
                            'second_name' => $deceased['second_name'] ?? null,
                            'third_name' => $deceased['third_name'] ?? null,
                            'last_name' => $deceased['last_name'] ?? null,
                            'relationship' => $deceased['relationship'] ?? 'other',
                            'death_date' => $deceased['death_date'] ?? null,
                            'death_reason' => $deceased['death_reason'] ?? null,
                        ];

                        if ($existingAdditional) {
                            $existingAdditional->update($additionalData);
                            Log::info('✅ تم تحديث متوفي إضافي', ['person_id' => $personId]);
                        } else {
                            \App\Models\AdditionalDeceased::create($additionalData);
                            Log::info('✅ تم إنشاء متوفي إضافي جديد', ['person_id' => $personId]);
                        }
                    }
                }
            } // نهاية شرط if ($request->input('data_section_id') == 1)

            // ═══════════════════════════════════════════════════════════════
            // 3.5. معالجة بيانات الأم — ذكية (حية أو متوفية أو المعيل هي الأم)
            // ═══════════════════════════════════════════════════════════════
            $guardianRelationship = $request->input('data_relationship');
            $guardianRelationshipText = $request->input('data_relationship_text', '');
            $motherIsAlive = $request->input('mother_is_alive');
            $motherId = $request->input('mother_id') ?: $request->input('deceased_mother_id');
            $motherFilled = $request->filled('mother_first_name') || $request->filled('mother_last_name') || !empty($motherId)
                || $request->filled('deceased_mother_first_name') || $request->filled('deceased_mother_last_name')
                || $request->filled('deceased_mother_id');

            Log::info('🔍 [DEBUG] Mother data check:', [
                'guardian_relationship' => $guardianRelationship,
                'guardian_relationship_text' => $guardianRelationshipText,
                'mother_is_alive' => $motherIsAlive,
                'mother_id' => $motherId,
                'mother_first_name' => $request->input('mother_first_name'),
                'mother_last_name' => $request->input('mother_last_name'),
                'mother_second_name' => $request->input('mother_second_name'),
                'mother_third_name' => $request->input('mother_third_name'),
                'motherFilled' => $motherFilled,
                'file_id' => $fileIdNumber,
            ]);

            // تحديد ما إذا كان المعيل هو الأم
            $guardianIsMother = ($guardianRelationship == 1 || $guardianRelationshipText === 'أم' || $guardianRelationshipText === 'الأم');

            if ($guardianIsMother) {
                // ═══════════════════════════════════════════════════════════
                // الحالة 1: المعيل هو الأم — نسخ بيانات المعيل كبيانات للأم
                // ═══════════════════════════════════════════════════════════
                $this->saveLivingMotherToPortal($request, $fileIdNumber, null, true);
                Log::info('✅ تم نسخ بيانات المعيل (الأم) إلى portal_general_registration_field_values', [
                    'guardian_id' => $guardianIdentity,
                    'file_id' => $fileIdNumber
                ]);

            } elseif ($motherFilled) {
                // ═══════════════════════════════════════════════════════════
                // الحالة 2: المعيل ليس الأم — حفظ حسب الحالة (حية/متوفية)
                // ═══════════════════════════════════════════════════════════
                if ($motherIsAlive === '1') {
                    // الأم حية — حفظ البيانات في portal_general_registration_field_values
                    $this->saveLivingMotherToPortal($request, $fileIdNumber, $motherId, false);
                    Log::info('✅ تم حفظ بيانات الأم الحية في portal_general_registration_field_values', [
                        'mother_id' => $motherId,
                        'file_id' => $fileIdNumber
                    ]);
                } else {
                    // الأم متوفاة — حفظ البيانات في dead_people
                    $deadRecord = DeadPepole::where('re_file_id', $fileIdNumber)->first();

                    $motherData = [
                        'mother_first_name' => $request->input('deceased_mother_first_name'),
                        'mother_second_name' => $request->input('deceased_mother_second_name'),
                        'mother_third_name' => $request->input('deceased_mother_third_name'),
                        'mother_last_name' => $request->input('deceased_mother_last_name'),
                        'mother_id' => $motherId,
                        'mother_death_date' => $request->input('mother_death_date'),
                    ];

                    if ($request->filled('mother_death_reason')) {
                        $motherData['mother_death_reason'] = $request->input('mother_death_reason');
                    }

                    if ($deadRecord) {
                        $deadRecord->update($motherData);
                        Log::info('✅ تم تحديث بيانات الأم المتوفاة في dead_people:', ['mother_id' => $motherId, 'file_id' => $fileIdNumber]);
                    } else {
                        $motherData['re_file_id'] = $fileIdNumber;
                        DeadPepole::create($motherData);
                        Log::info('✅ تم إنشاء سجل بيانات الأم المتوفاة في dead_people:', ['mother_id' => $motherId, 'file_id' => $fileIdNumber]);
                    }

                    // حفظ بيانات الأم المتوفية也在 portal_general_registration_field_values
                    $this->saveDeceasedMotherToPortal($request, $fileIdNumber, $motherId);
                }
            }


            // 4. Store family members
            $familyMembers = $request->input('family_members');

            if (is_array($familyMembers)) {
                // التحقق من صحة أرقام الهوية لأفراد الأسرة ومنع التكرار
                foreach ($familyMembers as $index => $member) {
                    if (!empty($member['person_id'])) {
                        // التحقق من أن رقم الهوية 9 أرقام
                        if (!preg_match('/^\d{9}$/', $member['person_id'])) {
                            if ($request->ajax() || $request->wantsJson()) {
                                return response()->json([
                                    'success' => false,
                                    'errors' => [
                                        "family_members.{$index}.person_id" => [
                                            "رقم هوية فرد الأسرة رقم " . ($index + 1) . " يجب أن يكون 9 أرقام بالضبط"
                                        ]
                                    ]
                                ], 422);
                            }
                            throw new \Exception("رقم هوية فرد الأسرة رقم " . ($index + 1) . " يجب أن يكون 9 أرقام بالضبط");
                        }

                        // 🆕 التحقق من أن الشخص ليس مرتبطاً بمعيل آخر
                        $existingInData = Data::where('data_id_number', $member['person_id'])->first();
                        if ($existingInData && $existingInData->file_id_number != $fileIdNumber) {
                            $errorMsg = "الشخص برقم هوية " . $member['person_id'] . " مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.";
                            if ($request->ajax() || $request->wantsJson()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => $errorMsg
                                ], 422);
                            }
                            throw new \Exception($errorMsg);
                        }

                        $existingInRePeople = RePeople::where('person_id', $member['person_id'])
                            ->where('registration_id', '!=', $fileIdNumber)
                            ->first();
                        if ($existingInRePeople) {
                            $errorMsg = "الشخص برقم هوية " . $member['person_id'] . " مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.";
                            if ($request->ajax() || $request->wantsJson()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => $errorMsg
                                ], 422);
                            }
                            throw new \Exception($errorMsg);
                        }
                    }
                }

                // حفظ أفراد الأسرة (تحديث أو إنشاء)
                foreach ($familyMembers as $member) {
                    $memberData = [
                        'registration_id' => $fileIdNumber,
                        'sponsorship_status' => $member['sponsorship_status'] ?? null,
                        'first_name' => $member['first_name'] ?? null,
                        'second_name' => $member['second_name'] ?? null,
                        'third_name' => $member['third_name'] ?? null,
                        'last_name' => $member['last_name'] ?? null,
                        'person_id' => $member['person_id'] ?? null,
                        'person_birth_date' => $member['person_birth_date'] ?? null,
                        'person_age' => $member['person_age'] ?? null,
                        'person_gender' => $member['person_gender'] ?? null,
                        'person_health_status' => $member['person_health_status'] ?? null,
                        'person_type_of_guarantee' => $member['person_type_of_guarantee'] ?? null,
                        'person_note' => $member['person_note'] ?? null,
                    ];

                    // البحث عن سجل موجود لنفس الشخص في نفس الملف
                    $existingMember = null;
                    if (!empty($member['person_id'])) {
                        $existingMember = RePeople::where('registration_id', $fileIdNumber)
                            ->where('person_id', $member['person_id'])
                            ->first();
                    }

                    if ($existingMember) {
                        $existingMember->update($memberData);
                    } else {
                        RePeople::create($memberData);
                    }
                }
            }



            // معالجة المرفقات الجديدة كمصفوفة Laravel
            $attachmentsData = $request->input('attachments', []);
            $allFiles = $request->allFiles();
            Log::info('🟢 عدد المرفقات المستلمة من جميع البوابات:', [
                'count' => count($attachmentsData),
                'attachments' => $attachmentsData,
                'family_members' => $request->input('family_members', []),
                'all_files_keys' => array_keys($allFiles),
                'all_files_debug' => array_map(function($f){
                    if (is_array($f)) return array_map(function($ff){ return is_object($ff) && method_exists($ff, 'getClientOriginalName') ? $ff->getClientOriginalName() : 'NOT_FILE_OBJECT'; }, $f);
                    return is_object($f) && method_exists($f, 'getClientOriginalName') ? $f->getClientOriginalName() : 'NOT_FILE_OBJECT';
                }, $allFiles)
            ]);
            foreach ($attachmentsData as $index => $data) {
                // استقبال الملف بشكل صحيح
                $file = $request->hasFile("attachments.$index.file") ? $request->file("attachments.$index.file") : null;
                $tempPath = $data['temp_path'] ?? null;

                if (!$file && !$tempPath) {
                    Log::error('🔴 لم يتم استقبال الملف من الواجهة', [
                        'index' => $index,
                        'data' => $data,
                        'all_files' => $request->allFiles(),
                        'all_files_keys' => array_keys($allFiles),
                        'all_files_debug' => array_map(function($f){
                            if (is_array($f)) return array_map(function($ff){ return is_object($ff) && method_exists($ff, 'getClientOriginalName') ? $ff->getClientOriginalName() : 'NOT_FILE_OBJECT'; }, $f);
                            return is_object($f) && method_exists($f, 'getClientOriginalName') ? $f->getClientOriginalName() : 'NOT_FILE_OBJECT';
                        }, $allFiles)
                    ]);
                    continue;
                }
                if ($file) {
                    Log::info('📸 اسم الملف المستلم: ' . $file->getClientOriginalName() . ' | الحجم: ' . $file->getSize());
                } else if ($tempPath) {
                    Log::info('📸 مسار الملف المؤقت المستلم: ' . $tempPath);
                }
                Log::info('🟠 معالجة مرفق فرد أسرة', ['index' => $index, 'data' => $data, 'file' => $file, 'tempPath' => $tempPath]);
                $personType = $data['person_identity_number'] ?? null;
                $fileType = $data['file_type'] ?? null;
                $fileIdNumberAttach = isset($data['file_id_number']) && $data['file_id_number'] && $data['file_id_number'] !== 'undefined' && preg_match('/^\d+$/', $data['file_id_number'])
                    ? str_pad($data['file_id_number'], 6, '0', STR_PAD_LEFT)
                    : $fileIdNumber;
                if (!$fileIdNumberAttach || $fileIdNumberAttach === 'undefined') {
                    Log::warning('🚫 تجاهل مرفق بسبب عدم وجود رقم ملف عام صالح', ['index' => $index, 'data' => $data]);
                    continue;
                }
                $storedFileName = $data['stored_file_name'] ?? ($file ? $file->getClientOriginalName() : null);
                if (!$storedFileName && $file) {
                    $storedFileName = $file->getClientOriginalName();
                }
                // تجاهل أي صورة أصلية (غير مقصوصة)
                if ($file && $file->isValid() && strpos($file->getMimeType(), 'image/') === 0 && (strpos($file->getClientOriginalName(), '_cropped') === false && strpos($storedFileName, '_cropped') === false)) {
                    Log::warning('🚫 تجاهل صورة أصلية غير مقصوصة', ['index' => $index, 'file' => $file->getClientOriginalName(), 'data' => $data]);
                    continue;
                }
                if ((!$file && !$tempPath) || !$storedFileName) {
                    Log::error('🔴 تجاهل مرفق بسبب نقص البيانات', ['index' => $index, 'file' => $file, 'tempPath' => $tempPath, 'storedFileName' => $storedFileName, 'data' => $data]);
                    continue;
                }
                $realPersonId = null;
                if ($personType === 'main') {
                    $realPersonId = $request->input('data_id_number');
                } elseif ($personType === 'deceased_father') {
                    $realPersonId = $request->input('father_id');
                } elseif ($personType === 'deceased_mother') {
                    $realPersonId = $request->input('mother_id');
                } elseif (strpos($personType, 'family_') === 0) {
                    $familyIndex = (int)str_replace('family_', '', $personType);
                    $familyMembers = $request->input('family_members', []);
                    if (isset($familyMembers[$familyIndex]['person_id'])) {
                        $realPersonId = $familyMembers[$familyIndex]['person_id'];
                    } else {
                        foreach ($familyMembers as $member) {
                            if (isset($member['person_id']) && $member['person_id'] == $personType) {
                                $realPersonId = $member['person_id'];
                                break;
                            }
                        }
                    }
                } else {
                    $realPersonId = is_numeric($personType) ? $personType : null;
                }
                if (($file || $tempPath) && $realPersonId && $fileType && $fileIdNumberAttach && $storedFileName && preg_match('/^\d+$/', $realPersonId)) {
                    $extension = '';
                    $fileSize = 0;
                    if ($file) {
                        $extension = $file->getClientOriginalExtension();
                        $fileSize = $file->getSize();
                    } else {
                        // استخراج الامتداد من اسم الملف الأصلي المخزن
                        $extension = pathinfo($storedFileName, PATHINFO_EXTENSION);
                        if (empty($extension)) {
                            $extension = 'jpg'; // افتراضي
                        }
                    }

                    // اسم الملف: نوع الوثيقة _ رقم الملف الخاص بالشخص _ رقم هوية الشخص
                    // إضافة رقم تسلسلي لتجنب الكتابة فوق الملفات القديمة
                    $existingCount = Attachment::where('person_identity_number', $realPersonId)
                        ->where('file_type', $fileType)
                        ->count();
                    $serial = $existingCount > 0 ? '_' . ($existingCount + 1) : '';
                    $newFileName = "{$fileType}_{$fileIdNumberAttach}_{$realPersonId}{$serial}.{$extension}";
                    $folder = 'uploads/' . $fileIdNumberAttach;
                    if ($folder === 'public' || $folder === 'public/') {
                        throw new \Exception('خطأ في مسار التخزين: يجب تحديد مجلد فرعي داخل uploads');
                    }

                    $path = '';
                    if ($file) {
                        $path = $file->storeAs($folder, $newFileName, 'public');
                    } else {
                        $finalPath = $folder . '/' . $newFileName;
                        if (\Storage::disk('public')->exists($tempPath)) {
                            \Storage::disk('public')->move($tempPath, $finalPath);
                            $path = $finalPath;
                            $fileSize = \Storage::disk('public')->size($finalPath);
                            
                            // Delete the temporary directory if it's empty after move
                            $tempDir = dirname($tempPath);
                            if (\Storage::disk('public')->exists($tempDir)) {
                                $remainingFiles = \Storage::disk('public')->allFiles($tempDir);
                                if (empty($remainingFiles)) {
                                    \Storage::disk('public')->deleteDirectory($tempDir);
                                    Log::info('🗑️ تم حذف المجلد المؤقت الفارغ', ['dir' => $tempDir]);
                                }
                            }
                        } else {
                            Log::error('الملف المؤقت غير موجود', ['tempPath' => $tempPath]);
                            continue;
                        }
                    }

                    Attachment::create([
                        'person_identity_number' => $realPersonId,
                        'stored_file_name' => $newFileName,
                        'file_path' => 'storage/' . $path,
                        'file_type' => $fileType,
                        'file_size' => $fileSize,
                    ]);
                    Log::info('🟢 تم تخزين مرفق بنجاح', ['index' => $index, 'file' => $file, 'tempPath' => $tempPath, 'data' => $data, 'newFileName' => $newFileName]);
                } else {
                    Log::error('🔴 تجاهل مرفق بسبب شرط تحقق نهائي', ['index' => $index, 'file' => $file, 'tempPath' => $tempPath, 'data' => $data]);
                }
            }


            // إضافة مستخدم جديد عند التسجيل العام
            // توليد كلمة مرور عشوائية من 8 أحرف
            $randomPassword = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            // فحص وجود المستخدم قبل محاولة الإنشاء (يمنع Duplicate entry error)
            if (!\App\Models\User::where('email', $request->input('data_id_number'))->exists()) {
                $user = \App\Models\User::create([
                    'name' => $request->input('data_first_name'),
                    'phone' => $request->input('data_phone_number') ?? $request->input('data_id_number'), // قيمة افتراضية = رقم الهوية
                    'email' => $request->input('data_id_number'), // تخزين رقم الهوية مباشرة في عمود البريد الإلكتروني
                    'password' => bcrypt($randomPassword),
                    'role' => 'user',
                ]);
                Log::info('ℹ️ تم إنشاء حساب مستخدم جديد', ['identity' => $request->input('data_id_number')]);
            } else {
                Log::info('ℹ️ المستخدم مسجل مسبقاً، تخطي إنشاء حساب جديد', ['identity' => $request->input('data_id_number')]);
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'redirect' => route('user.thank.you.page')]);
            }
            return redirect()->route('user.thank.you.page');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في تخزين السجل: ' . $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء حفظ السجل: ' . $e->getMessage());
        }
    }
    // إضافة دالة الرفع المجزأ
    public function uploadChunk(Request $request)
    {
        try {
            $chunk = $request->file('chunk');
            $fileName = $request->input('file_name');
            $fileId = $request->input('file_id_number');
            $chunkIndex = $request->input('chunk_index');
            $totalChunks = $request->input('total_chunks');

            if (!$chunk || !$fileName || !$fileId) {
                return response()->json(['success' => false, 'error' => 'بيانات مفقودة للرفع المجزأ'], 400);
            }

            // تحديد مسار التخزين المؤقت
            $tempDir = storage_path('app/public/temp_uploads/' . $fileId);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            // تنظيف اسم الملف لتجنب أي مشاكل أمنية
            $safeFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
            $tempPath = $tempDir . '/' . $safeFileName;

            // إلحاق الجزء بالملف المؤقت (FILE_APPEND)
            file_put_contents($tempPath, file_get_contents($chunk->getRealPath()), FILE_APPEND);

            // إذا كان هذا هو الجزء الأخير
            if ($chunkIndex == $totalChunks - 1) {
                return response()->json([
                    'success' => true,
                    'complete' => true,
                    'path' => 'temp_uploads/' . $fileId . '/' . $safeFileName
                ]);
            }

            return response()->json(['success' => true, 'complete' => false]);
        } catch (\Exception $e) {
            Log::error('خطأ في الرفع المجزأ: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // فحص وجود رقم الهوية في الجداول المختلفة
    public function check(Request $request)
    {
        $id = $request->input('id_number');
        $exists = false;

        // تحقق في جدول البيانات الأساسية
        if (Data::where('data_id_number', $id)->exists()) $exists = true;

        // تحقق في جدول الأفراد المتوفين (الأب أو الأم)
        if (
            DeadPepole::where('father_id', $id)->orWhere('mother_id', $id)->exists()
        ) $exists = true;

        // تحقق في جدول أفراد الأسرة
        if (RePeople::where('person_id', $id)->exists()) $exists = true;

        return response()->json(['exists' => $exists]);
    }

    /**
     * البحث الشامل في جميع الجداول - محسّن بـ NormalizedSearchService
     */
    public function searchAllTables(Request $request)
    {
        $startTime = microtime(true);

        try {
            $searchTerm = $request->input('search_term');

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الرجاء إدخال كلمة البحث'
                ]);
            }

            $results = [
                'found' => false,
                'has_account' => false,
                'data' => [],
                'message' => '',
                'search_time' => 0
            ];

            // تحديد نوع البحث: رقم هوية (8-10 أرقام) أو اسم
            $isIdNumber = is_numeric($searchTerm) && strlen($searchTerm) >= 8 && strlen($searchTerm) <= 10;

            if ($isIdNumber) {
                // البحث برقم الهوية (9 أرقام) في الجداول: re_people, data, dead_people, persons (civil registry)

                // 1. البحث في جدول re_people
                $rePeopleResult = $this->searchInRePeopleOptimized($searchTerm);
                if ($rePeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 're_people';
                    $results['data'] = $rePeopleResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً  .';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 2. البحث في جدول data
                $dataResult = $this->searchInDataTableOptimized($searchTerm);
                if ($dataResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 'data';
                    $results['data'] = $dataResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً. يرجى تسجيل الدخول.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 3. البحث في جدول dead_people
                $deadPeopleResult = $this->searchInDeadPeopleOptimized($searchTerm);
                if ($deadPeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = false;
                    $results['source'] = 'dead_people';
                    $results['data'] = $deadPeopleResult;
                    $results['message'] = 'تم العثور على سجل في قائمة المتوفين.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 4. البحث في جدول persons في civilregistry
                $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
                $civilRegistryResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);
            } else {
                // البحث بالاسم في الجداول: dead_people, data, re_people فقط

                // 1. البحث في جدول dead_people
                $deadPeopleResult = $this->searchInDeadPeopleOptimized($searchTerm);
                if ($deadPeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = false;
                    $results['source'] = 'dead_people';
                    $results['data'] = $deadPeopleResult;
                    $results['message'] = 'تم العثور على سجل في قائمة المتوفين.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 2. البحث في جدول data
                $dataResult = $this->searchInDataTableOptimized($searchTerm);
                if ($dataResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 'data';
                    $results['data'] = $dataResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً. يرجى تسجيل الدخول.';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // 3. البحث في جدول re_people
                $rePeopleResult = $this->searchInRePeopleOptimized($searchTerm);
                if ($rePeopleResult) {
                    $results['found'] = true;
                    $results['has_account'] = true;
                    $results['source'] = 're_people';
                    $results['data'] = $rePeopleResult;
                    $results['message'] = 'تم العثور على سجل موجود مسبقاً  .';
                    $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                    return response()->json($results);
                }

                // لا نبحث في السجل المدني للأسماء
                $civilRegistryResults = null;
            }

            // معالجة نتائج السجل المدني (فقط لرقم الهوية)

            if ($civilRegistryResults && $civilRegistryResults->isNotEmpty()) {
                $personsResult = $civilRegistryResults->first();

                $results['found'] = true;
                $results['has_account'] = false;
                $results['source'] = 'civil_registry';

                // تحويل تاريخ الميلاد بشكل صحيح بالميلادي
                $birthDate = null;
                $birthDateDisplay = null;
                if ($personsResult->CI_BIRTH_DT) {
                    try {
                        $carbonDate = null;
                        if ($personsResult->CI_BIRTH_DT instanceof \Carbon\Carbon) {
                            $carbonDate = $personsResult->CI_BIRTH_DT;
                        } elseif (is_string($personsResult->CI_BIRTH_DT)) {
                            $carbonDate = \Carbon\Carbon::parse($personsResult->CI_BIRTH_DT);
                        }

                        if ($carbonDate) {
                            $birthDate = $carbonDate->format('Y-m-d');
                            $birthDateDisplay = $carbonDate->format('Y/m/d');
                        }
                    } catch (\Exception $e) {
                        Log::warning('خطأ في تحويل تاريخ الميلاد: ' . $e->getMessage());
                    }
                }

                // جلب اسم المدينة
                $cityName = null;
                if (isset($personsResult->CITY) && $personsResult->CITY) {
                    $city = \App\Models\City::find($personsResult->CITY);
                    $cityName = $city ? $city->city : null;
                }

                // جلب الحالة الاجتماعية
                $maritalStatusName = null;
                if (isset($personsResult->CI_PERSONAL_CD) && $personsResult->CI_PERSONAL_CD) {
                    $maritalStatus = \App\Models\CI_PERSONAL_CD::find($personsResult->CI_PERSONAL_CD);
                    $maritalStatusName = $maritalStatus ? $maritalStatus->CI_PERSONAL_CD : null;
                }

                $results['data'] = [
                    'id_number' => $personsResult->id_number ?? $personsResult->CI_ID_NUM,
                    'first_name' => $personsResult->CI_FIRST_ARB ?? null,
                    'father_name' => $personsResult->CI_FATHER_ARB ?? null,
                    'grand_father_name' => $personsResult->CI_GRAND_FATHER_ARB ?? null,
                    'family_name' => $personsResult->CI_FAMILY_ARB ?? null,
                    'mother_name' => $personsResult->MOTHER_NAME1 ?? null,
                    'birth_date' => $birthDate,
                    'birth_date_display' => $birthDateDisplay,
                    'birth_date_raw' => $personsResult->CI_BIRTH_DT ?? null,
                    'gender' => $personsResult->CI_SEX_CD ?? null,
                    'marital_status' => $personsResult->CI_PERSONAL_CD ?? null,
                    'marital_status_name' => $maritalStatusName,
                    'city' => $personsResult->CITY ?? null,
                    'city_name' => $cityName,
                    'street' => $personsResult->STREET ?? null,
                    'house_no' => $personsResult->HOUSE_NO ?? null,
                    'full_name' => trim(
                        ($personsResult->CI_FIRST_ARB ?? '') . ' ' .
                        ($personsResult->CI_FATHER_ARB ?? '') . ' ' .
                        ($personsResult->CI_GRAND_FATHER_ARB ?? '') . ' ' .
                        ($personsResult->CI_FAMILY_ARB ?? '')
                    ),
                    'type' => 'موجود في السجل المدني'
                ];
                $results['message'] = 'لا يوجد بيانات لهذه الأسرة. يمكنك المتابعة لإنشاء حساب جديد.';
                $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                return response()->json($results);
            }

            // إذا لم يتم العثور على نتائج
            $results['message'] = 'لم يتم العثور على أي بيانات مطابقة. يرجى التأكد من رقم الهوية والمحاولة مرة أخرى.';
            $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
            return response()->json($results);

        } catch (\Exception $e) {
            Log::error('خطأ في البحث: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage(),
                'search_time' => round((microtime(true) - $startTime) * 1000, 2) . ' ms'
            ], 500);
        }
    }

    /**
     * البحث المحسّن في جدول data
     */
    private function searchInDataTableOptimized($searchTerm)
    {
        // إذا كان رقماً، ابحث في أرقام الهوية والملفات
        if (is_numeric($searchTerm)) {
            $result = \App\Models\Data::where('data_id_number', $searchTerm)
                ->orWhere('file_id_number', $searchTerm)
                ->first();

            if ($result) {
                return [
                    'file_id_number' => $result->file_id_number,
                    'id_number' => $result->data_id_number,
                    'full_name' => trim(
                        ($result->data_first_name ?? '') . ' ' .
                        ($result->data_father_name ?? '') . ' ' .
                        ($result->data_grand_father_name ?? '') . ' ' .
                        ($result->data_family_name ?? '')
                    ),
                    'phone' => $result->data_phone_number,
                    'type' => 'معيل أسرة'
                ];
            }
        }

        // البحث بالاسم باستخدام NormalizedSearchService
        $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
        $results = $normalizedSearchService->searchDataTable($searchTerm, 1);

        if ($results && $results->isNotEmpty()) {
            $result = $results->first();
            return [
                'file_id_number' => $result->file_id_number,
                'id_number' => $result->data_id_number,
                'full_name' => trim(
                    ($result->data_first_name ?? '') . ' ' .
                    ($result->data_father_name ?? '') . ' ' .
                    ($result->data_grand_father_name ?? '') . ' ' .
                    ($result->data_family_name ?? '')
                ),
                'phone' => $result->data_phone_number ?? null,
                'type' => 'معيل أسرة'
            ];
        }

        return null;
    }

    /**
     * البحث المحسّن في جدول re_people
     */
    private function searchInRePeopleOptimized($searchTerm)
    {
        // البحث برقم الهوية أولاً (أسرع)
        if (is_numeric($searchTerm) && strlen($searchTerm) >= 9) {
            $result = \App\Models\RePeople::where('person_id', $searchTerm)->first();

            if ($result) {
                return [
                    'id_number' => $result->person_id,
                    'full_name' => trim(
                        ($result->first_name ?? '') . ' ' .
                        ($result->second_name ?? '') . ' ' .
                        ($result->third_name ?? '') . ' ' .
                        ($result->last_name ?? '')
                    ),
                    'type' => $result->person_type_id == 1 ? 'يتيم' : 'شخص مسجل'
                ];
            }
        }

        // البحث بالاسم
        $normalizedTerm = normalizeArabicText($searchTerm);
        $result = \App\Models\RePeople::where(function($query) use ($normalizedTerm) {
            $query->where('first_name', 'LIKE', $normalizedTerm . '%')
                  ->orWhere('last_name', 'LIKE', $normalizedTerm . '%');
        })->first();

        if ($result) {
            return [
                'id_number' => $result->person_id,
                'full_name' => trim(
                    ($result->first_name ?? '') . ' ' .
                    ($result->second_name ?? '') . ' ' .
                    ($result->third_name ?? '') . ' ' .
                    ($result->last_name ?? '')
                ),
                'type' => $result->person_type_id == 1 ? 'يتيم' : 'شخص مسجل'
            ];
        }

        return null;
    }

    /**
     * البحث المحسّن في جدول dead_people
     */
    private function searchInDeadPeopleOptimized($searchTerm)
    {
        // البحث برقم الهوية (الأب أو الأم)
        if (is_numeric($searchTerm) && strlen($searchTerm) >= 9) {
            $result = \App\Models\DeadPepole::where('father_id', $searchTerm)
                ->orWhere('mother_id', $searchTerm)
                ->first();

            if ($result) {
                $fullName = '';
                if ($result->father_id == $searchTerm) {
                    $fullName = trim(
                        ($result->father_first_name ?? '') . ' ' .
                        ($result->father_second_name ?? '') . ' ' .
                        ($result->father_third_name ?? '') . ' ' .
                        ($result->father_last_name ?? '')
                    );
                } else {
                    $fullName = trim(
                        ($result->mother_first_name ?? '') . ' ' .
                        ($result->mother_second_name ?? '') . ' ' .
                        ($result->mother_third_name ?? '') . ' ' .
                        ($result->mother_last_name ?? '')
                    );
                }

                return [
                    'id_number' => $searchTerm,
                    'full_name' => $fullName,
                    'type' => 'متوفى'
                ];
            }
        }

        // البحث بالاسم
        $normalizedTerm = normalizeArabicText($searchTerm);
        $result = \App\Models\DeadPepole::where(function($query) use ($normalizedTerm) {
            $query->where('father_first_name', 'LIKE', '%' . $normalizedTerm . '%')
                  ->orWhere('father_last_name', 'LIKE', '%' . $normalizedTerm . '%')
                  ->orWhere('mother_first_name', 'LIKE', '%' . $normalizedTerm . '%')
                  ->orWhere('mother_last_name', 'LIKE', '%' . $normalizedTerm . '%');
        })->first();

        if ($result) {
            $fullName = '';
            if (!empty($result->father_first_name)) {
                $fullName = trim(
                    ($result->father_first_name ?? '') . ' ' .
                    ($result->father_second_name ?? '') . ' ' .
                    ($result->father_third_name ?? '') . ' ' .
                    ($result->father_last_name ?? '')
                );
            } else {
                $fullName = trim(
                    ($result->mother_first_name ?? '') . ' ' .
                    ($result->mother_second_name ?? '') . ' ' .
                    ($result->mother_third_name ?? '') . ' ' .
                    ($result->mother_last_name ?? '')
                );
            }

            return [
                'id_number' => $result->father_id ?? $result->mother_id,
                'full_name' => $fullName,
                'type' => 'متوفى'
            ];
        }

        return null;
    }

    /**
     * ملف الحقول تلقائياً من السجل المدني
     */
    public function fillFromCivilRegistry(Request $request)
    {
        try {
            $idNumber = $request->input('id_number');

            if (empty($idNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الرجاء إدخال رقم الهوية'
                ]);
            }

            // البحث في السجل المدني باستخدام NormalizedSearchService (أسرع)
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $results = $normalizedSearchService->searchCivilRegistry($idNumber, 1);

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يلا يوجد بيانات لهذه الأسرة'
                ]);
            }

            $person = $results->first();

            // تحويل تاريخ الميلاد بشكل صحيح بالميلادي
            $birthDate = null;
            if ($person->CI_BIRTH_DT) {
                try {
                    $carbonDate = null;
                    if ($person->CI_BIRTH_DT instanceof \Carbon\Carbon) {
                        $carbonDate = $person->CI_BIRTH_DT;
                    } elseif (is_string($person->CI_BIRTH_DT)) {
                        $carbonDate = \Carbon\Carbon::parse($person->CI_BIRTH_DT);
                    }

                    if ($carbonDate) {
                        $birthDate = $carbonDate->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    Log::warning('خطأ في تحويل تاريخ الميلاد: ' . $e->getMessage());
                }
            }

            // جلب اسم المدينة
            $cityName = null;
            if ($person->CITY) {
                $city = \App\Models\City::find($person->CITY);
                $cityName = $city ? $city->city : null;
            }

            // إرجاع البيانات
            return response()->json([
                'success' => true,
                'data' => [
                    'data_id_number' => $person->id_number ?? $person->CI_ID_NUM,
                    'data_first_name' => $person->CI_FIRST_ARB,
                    'data_father_name' => $person->CI_FATHER_ARB,
                    'data_grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                    'data_family_name' => $person->CI_FAMILY_ARB,
                    'data_birth_date' => $birthDate,
                    'data_gender' => $person->CI_SEX_CD,
                    'data_marital_status' => $person->CI_PERSONAL_CD,
                    'data_city' => $person->CITY,
                    'data_city_name' => $cityName,
                    'data_current_address' => $person->STREET ?? null,
                    'mother_name' => $person->MOTHER_NAME1 ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب البيانات من السجل المدني: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث الذكي في جدول محدد مع تحسين الأداء
     */
    private function searchInTable($table, $searchTerm, $normalizedQuery, $noSpacesQuery, &$results)
    {
        $result = null;

        if ($table === 'data') {
            $result = Data::where(function($query) use ($searchTerm, $normalizedQuery) {
                $query->where('data_id_number', $searchTerm)
                      ->orWhere('file_id_number', $searchTerm);

                $fullName = "CONCAT(IFNULL(data_first_name, ''), ' ', IFNULL(data_father_name, ''), ' ', IFNULL(data_grand_father_name, ''), ' ', IFNULL(data_family_name, ''))";
                $query->orWhereRaw("({$this->buildCombinedNormSql($fullName)}) LIKE ?", ["%{$normalizedQuery}%"]);

                foreach (['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'] as $column) {
                    $query->orWhereRaw("({$this->buildCombinedNormSql($column)}) LIKE ?", ["%{$normalizedQuery}%"]);
                }
            })->first();

            if ($result) {
                $results['found'] = true;
                $results['has_account'] = true;
                $results['source'] = 'data';
                $results['data'] = [
                    'file_id_number' => $result->file_id_number,
                    'id_number' => $result->data_id_number,
                    'full_name' => trim(
                        ($result->data_first_name ?? '') . ' ' .
                        ($result->data_father_name ?? '') . ' ' .
                        ($result->data_grand_father_name ?? '') . ' ' .
                        ($result->data_family_name ?? '')
                    ),
                    'phone' => $result->data_phone_number,
                    'type' => 'معيل أسرة'
                ];
                $results['message'] = 'تم العثور على سجل موجود مسبقاً. يرجى تسجيل الدخول.';
            }
        } elseif ($table === 're_people') {
            $result = RePeople::where(function($query) use ($searchTerm, $normalizedQuery) {
                $query->where('person_id', $searchTerm);

                $fullName = "CONCAT(IFNULL(first_name, ''), ' ', IFNULL(second_name, ''), ' ', IFNULL(third_name, ''), ' ', IFNULL(last_name, ''))";
                $query->orWhereRaw("({$this->buildCombinedNormSql($fullName)}) LIKE ?", ["%{$normalizedQuery}%"]);

                foreach (['first_name', 'second_name', 'third_name', 'last_name'] as $column) {
                    $query->orWhereRaw("({$this->buildCombinedNormSql($column)}) LIKE ?", ["%{$normalizedQuery}%"]);
                }
            })->first();

            if ($result) {
                $results['found'] = true;
                $results['has_account'] = true;
                $results['source'] = 're_people';
                $results['data'] = [
                    'file_id_number' => $result->registration_id,
                    'id_number' => $result->person_id,
                    'full_name' => trim(
                        ($result->first_name ?? '') . ' ' .
                        ($result->second_name ?? '') . ' ' .
                        ($result->third_name ?? '') . ' ' .
                        ($result->last_name ?? '')
                    ),
                    'type' => 'يتيم / فرد من الأسرة'
                ];
                $results['message'] = 'تم العثور على سجل موجود مسبقاً. يرجى تسجيل الدخول.';
            }
        } elseif ($table === 'dead_people') {
            $result = DeadPepole::where(function($query) use ($searchTerm, $normalizedQuery) {
                $query->where('father_id', $searchTerm)
                      ->orWhere('mother_id', $searchTerm);

                $fatherFullName = "CONCAT(IFNULL(father_first_name, ''), ' ', IFNULL(father_second_name, ''), ' ', IFNULL(father_third_name, ''), ' ', IFNULL(father_last_name, ''))";
                $query->orWhereRaw("({$this->buildCombinedNormSql($fatherFullName)}) LIKE ?", ["%{$normalizedQuery}%"]);

                $motherFullName = "CONCAT(IFNULL(mother_first_name, ''), ' ', IFNULL(mother_second_name, ''), ' ', IFNULL(mother_third_name, ''), ' ', IFNULL(mother_last_name, ''))";
                $query->orWhereRaw("({$this->buildCombinedNormSql($motherFullName)}) LIKE ?", ["%{$normalizedQuery}%"]);

                foreach (['father_first_name', 'father_second_name', 'father_third_name', 'father_last_name',
                          'mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name'] as $column) {
                    $query->orWhereRaw("({$this->buildCombinedNormSql($column)}) LIKE ?", ["%{$normalizedQuery}%"]);
                }
            })->first();

            if ($result) {
                $results['found'] = true;
                $results['has_account'] = true;
                $results['source'] = 'dead_people';

                $isFather = ($result->father_id == $searchTerm ||
                            stripos($result->father_first_name ?? '', $searchTerm) !== false ||
                            stripos($result->father_last_name ?? '', $searchTerm) !== false);

                $results['data'] = [
                    'file_id_number' => $result->re_file_id,
                    'id_number' => $isFather ? $result->father_id : $result->mother_id,
                    'full_name' => $isFather ?
                        trim(
                            ($result->father_first_name ?? '') . ' ' .
                            ($result->father_second_name ?? '') . ' ' .
                            ($result->father_third_name ?? '') . ' ' .
                            ($result->father_last_name ?? '')
                        ) :
                        trim(
                            ($result->mother_first_name ?? '') . ' ' .
                            ($result->mother_second_name ?? '') . ' ' .
                            ($result->mother_third_name ?? '') . ' ' .
                            ($result->mother_last_name ?? '')
                        ),
                    'type' => 'متوفى'
                ];
                $results['message'] = 'تم العثور على سجل موجود مسبقاً. يرجى تسجيل الدخول.';
            }
        }
    }

    /**
     * بناء SQL inline للتطبيع مع المسافات (محسّن)
     * يجمع بين البحث بالمسافات وبدون مسافات في SQL واحدة
     */
    private function buildCombinedNormSql($column)
    {
        // تطبيع الأحرف وإزالة المسافات الزائدة
        return "TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', ''),
                '  ', ' '), '   ', ' '))";
    }

    /**
     * بناء SQL inline للتطبيع مع المسافات
     */
    private function buildNormSqlInline($column)
    {
        return "TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', ''),
                '  ', ' '), '   ', ' '))";
    }

    /**
     * بناء SQL inline لإزالة كل المسافات
     */
    private function buildNoSpacesSqlInline($column)
    {
        return "REPLACE(TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', '')),
                ' ', '')";
    }

    /**
     * 🆕 التحقق من وجود المعيل وجلب بياناته من الجداول المختلفة
     * هذه الدالة تُستخدم عبر API لجلب بيانات المعيل الموجود مسبقاً
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkExistingGuardian(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');

            if (empty($identityNumber) || strlen($identityNumber) != 9) {
                return response()->json([
                    'success' => false,
                    'exists' => false,
                    'message' => 'رقم الهوية يجب أن يكون 9 أرقام'
                ]);
            }

            $result = [
                'exists' => false,
                'source' => null,
                'file_id_number' => null,
                'guardian_data' => null,
                'bank_accounts' => [],
                'message' => ''
            ];

            // 1️⃣ البحث في جدول data (المعيلين)
            $dataRecord = Data::where('data_id_number', $identityNumber)->first();

            if ($dataRecord) {
                $result['exists'] = true;
                $result['source'] = 'data';
                $result['file_id_number'] = $dataRecord->file_id_number;
                $result['guardian_data'] = [
                    'file_id_number' => $dataRecord->file_id_number,
                    'identity_number' => $dataRecord->data_id_number,
                    'first_name' => $dataRecord->data_first_name,
                    'father_name' => $dataRecord->data_father_name,
                    'grand_father_name' => $dataRecord->data_grand_father_name,
                    'family_name' => $dataRecord->data_family_name,
                    'full_name' => trim(
                        ($dataRecord->data_first_name ?? '') . ' ' .
                        ($dataRecord->data_father_name ?? '') . ' ' .
                        ($dataRecord->data_grand_father_name ?? '') . ' ' .
                        ($dataRecord->data_family_name ?? '')
                    ),
                    'phone_number' => $dataRecord->data_phone_number,
                    'alt_phone_number' => $dataRecord->data_alt_phone_number,
                    'birth_date' => $dataRecord->data_birth_date,
                    'gender' => $dataRecord->data_gender,
                    'marital_status' => $dataRecord->data_marital_status,
                    'province' => $dataRecord->data_province,
                    'city' => $dataRecord->data_city,
                    'current_address' => $dataRecord->data_current_address,
                    'section_id' => $dataRecord->data_section_id,
                ];

                // جلب الحسابات البنكية
                $bankAccounts = GuardianBankAccount::where('guardian_registration', $dataRecord->file_id_number)->get();
                $result['bank_accounts'] = $bankAccounts->map(function($account) {
                    return [
                        'id' => $account->id,
                        'bank_name' => $account->bank_name,
                        'iban_usd' => $account->iban_usd,
                        'iban_shekel' => $account->iban_shekel,
                        'person_owner_identity_number' => $account->person_owner_identity_number,
                        're_guardian_name' => $account->re_guardian_name,
                        're_phone_number' => $account->re_phone_number,
                        'check_account' => $account->check_account,
                    ];
                })->toArray();

                $result['message'] = 'تم العثور على المعيل في قاعدة البيانات. سيتم ربط الشخص المكفول به.';

                Log::info('✅ تم العثور على معيل موجود في جدول data', [
                    'identity' => $identityNumber,
                    'file_id_number' => $dataRecord->file_id_number,
                    'bank_accounts_count' => count($result['bank_accounts'])
                ]);

                return response()->json($result);
            }

            // 2️⃣ البحث في جدول dead_people (الأب أو الأم المتوفي)
            $deadRecord = DeadPepole::where('father_id', $identityNumber)
                ->orWhere('mother_id', $identityNumber)
                ->first();

            if ($deadRecord) {
                $isFather = ($deadRecord->father_id == $identityNumber);
                $result['exists'] = true;
                $result['source'] = $isFather ? 'dead_people_father' : 'dead_people_mother';
                $result['file_id_number'] = $deadRecord->re_file_id;

                $result['guardian_data'] = [
                    'file_id_number' => $deadRecord->re_file_id,
                    'identity_number' => $identityNumber,
                    'first_name' => $isFather ? $deadRecord->father_first_name : $deadRecord->mother_first_name,
                    'father_name' => $isFather ? $deadRecord->father_second_name : $deadRecord->mother_second_name,
                    'grand_father_name' => $isFather ? $deadRecord->father_third_name : $deadRecord->mother_third_name,
                    'family_name' => $isFather ? $deadRecord->father_last_name : $deadRecord->mother_last_name,
                    'full_name' => $isFather ?
                        trim(
                            ($deadRecord->father_first_name ?? '') . ' ' .
                            ($deadRecord->father_second_name ?? '') . ' ' .
                            ($deadRecord->father_third_name ?? '') . ' ' .
                            ($deadRecord->father_last_name ?? '')
                        ) :
                        trim(
                            ($deadRecord->mother_first_name ?? '') . ' ' .
                            ($deadRecord->mother_second_name ?? '') . ' ' .
                            ($deadRecord->mother_third_name ?? '') . ' ' .
                            ($deadRecord->mother_last_name ?? '')
                        ),
                    'death_date' => $isFather ? $deadRecord->father_death_date : $deadRecord->mother_death_date,
                    'death_reason' => $isFather ? $deadRecord->father_death_reason : $deadRecord->mother_death_reason,
                    'is_deceased' => true,
                    'deceased_type' => $isFather ? 'father' : 'mother',
                ];

                // جلب البيانات الإضافية من portal_general_registration_field_values
                $portalFields = \App\Models\PortalGeneralRegistrationFieldValue::where('file_id_number', $deadRecord->re_file_id)
                    ->orWhere('identity_number', $identityNumber)
                    ->get()
                    ->keyBy('field_key');

                if ($portalFields->isNotEmpty()) {
                    $result['guardian_data']['phone_number'] = $portalFields->get('phone_number')?->field_value;
                    $result['guardian_data']['alt_phone_number'] = $portalFields->get('alt_phone_number')?->field_value;
                    $result['guardian_data']['current_address'] = $portalFields->get('current_address')?->field_value;
                    $result['guardian_data']['city'] = $portalFields->get('city')?->field_value;
                    $result['guardian_data']['province'] = $portalFields->get('province')?->field_value;
                }

                // جلب الحسابات البنكية
                $bankAccounts = GuardianBankAccount::where('guardian_registration', $deadRecord->re_file_id)->get();
                $result['bank_accounts'] = $bankAccounts->map(function($account) {
                    return [
                        'id' => $account->id,
                        'bank_name' => $account->bank_name,
                        'iban_usd' => $account->iban_usd,
                        'iban_shekel' => $account->iban_shekel,
                        'person_owner_identity_number' => $account->person_owner_identity_number,
                        're_guardian_name' => $account->re_guardian_name,
                        're_phone_number' => $account->re_phone_number,
                        'check_account' => $account->check_account,
                    ];
                })->toArray();

                $result['message'] = 'تم العثور على الشخص في سجل المتوفين. سيتم الربط بالملف الموجود.';

                Log::info('✅ تم العثور على شخص في جدول dead_people', [
                    'identity' => $identityNumber,
                    'file_id_number' => $deadRecord->re_file_id,
                    'type' => $isFather ? 'father' : 'mother',
                    'bank_accounts_count' => count($result['bank_accounts'])
                ]);

                return response()->json($result);
            }

            // 3️⃣ لم يتم العثور على الشخص
            $result['message'] = 'لم يتم العثور على الشخص في قاعدة البيانات. سيتم إنشاء سجل جديد.';

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في التحقق من وجود المعيل', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'exists' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث الشامل عن المعيل: يجلب كل البيانات من كل الجداول المرتبطة
     */
    public function lookupGuardian(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');

            if (empty($identityNumber) || strlen($identityNumber) < 9) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهوية يجب أن يكون 9 أرقام على الأقل'
                ]);
            }

            // البحث في السجل المدني فقط
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $civilResults = $normalizedSearchService->searchCivilRegistry($identityNumber, 1);

            if ($civilResults && $civilResults->isNotEmpty()) {
                $person = $civilResults->first();
                $birthDate = null;
                if ($person->CI_BIRTH_DT) {
                    try {
                        $carbonDate = $person->CI_BIRTH_DT instanceof \Carbon\Carbon
                            ? $person->CI_BIRTH_DT
                            : \Carbon\Carbon::parse($person->CI_BIRTH_DT);
                        $birthDate = $carbonDate->format('Y-m-d');
                    } catch (\Exception $e) {}
                }
                return response()->json([
                    'success' => true,
                    'source' => 'civil_registry',
                    'data' => [
                        'data_id_number' => $person->id_number,
                        'data_first_name' => $person->CI_FIRST_ARB,
                        'data_father_name' => $person->CI_FATHER_ARB,
                        'data_grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                        'data_family_name' => $person->CI_FAMILY_ARB,
                        'data_birth_date' => $birthDate,
                        'data_gender' => $person->CI_SEX_CD,
                        'is_alive' => empty($person->CI_DEAD_DT),
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'source' => 'not_found',
                'exists' => false,
                'data' => null,
                'message' => 'لم يتم العثور على بيانات لهذا الرقم في السجل المدني.'
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في البحث عن المعيل: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث عن بيانات الأم برقم الهوية في السجل المدني
     */
    public function lookupMother(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');

            if (empty($identityNumber) || strlen($identityNumber) < 9) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهوية يجب أن يكون 9 أرقام على الأقل'
                ]);
            }

            // البحث في السجل المدني
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $results = $normalizedSearchService->searchCivilRegistry($identityNumber, 1);

            if ($results && $results->isNotEmpty()) {
                $person = $results->first();
                $birthDate = null;
                if ($person->CI_BIRTH_DT) {
                    try {
                        $carbonDate = $person->CI_BIRTH_DT instanceof \Carbon\Carbon
                            ? $person->CI_BIRTH_DT
                            : \Carbon\Carbon::parse($person->CI_BIRTH_DT);
                        $birthDate = $carbonDate->format('Y-m-d');
                    } catch (\Exception $e) {}
                }
                return response()->json([
                    'success' => true,
                    'data' => [
                        'mother_id' => $person->id_number,
                        'mother_first_name' => $person->CI_FIRST_ARB,
                        'mother_second_name' => $person->CI_FATHER_ARB,
                        'mother_third_name' => $person->CI_GRAND_FATHER_ARB,
                        'mother_last_name' => $person->CI_FAMILY_ARB,
                        'mother_birth_date' => $birthDate,
                        'mother_gender' => $person->CI_SEX_CD,
                        'is_alive' => empty($person->CI_DEAD_DT),
                        'death_date' => !empty($person->CI_DEAD_DT) ? $person->CI_DEAD_DT : null,
                    ],
                    'message' => 'تم جلب بيانات الأم من السجل المدني.'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'لم يتم العثور على بيانات لهذا الرقم في السجل المدني.'
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في البحث عن الأم: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * التحقق مما إذا كان الشخص مرتبطاً بمعيل آخر
     */
    public function checkPersonLinked(Request $request)
    {
        try {
            $identityNumber = $request->input('identity_number');
            $currentFileId = $request->input('current_file_id');

            if (empty($identityNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الهوية مطلوب'
                ]);
            }

            // فحص في جدول data (هل هو معيل لملف آخر؟)
            $dataRecord = Data::where('data_id_number', $identityNumber)->first();
            if ($dataRecord && $dataRecord->file_id_number != $currentFileId) {
                return response()->json([
                    'success' => true,
                    'is_linked' => true,
                    'message' => 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.'
                ]);
            }

            // فحص في جدول re_people (هل هو فرد أسرة في ملف آخر؟)
            $rePeopleRecord = RePeople::where('person_id', $identityNumber)
                ->where('registration_id', '!=', $currentFileId)
                ->first();
            if ($rePeopleRecord) {
                return response()->json([
                    'success' => true,
                    'is_linked' => true,
                    'message' => 'هذا الشخص مسجل مسبقاً في النظام ومرتبط بمعيل آخر. لا يمكن ربطه بهذا الطلب. يرجى التواصل مع إدارة المؤسسة.'
                ]);
            }

            return response()->json([
                'success' => true,
                'is_linked' => false,
                'message' => ''
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في التحقق من ربط الشخص: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق'
            ], 500);
        }
    }

    /**
     * جلب بيانات المعيل الموجود مع الحسابات البنكية لعرضها في النموذج
     */
    public function getGuardianWithBankAccounts(Request $request)
    {
        try {
            $fileIdNumber = $request->input('file_id_number');
            $identityNumber = $request->input('identity_number');

            if (empty($fileIdNumber) && empty($identityNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب توفير رقم الملف أو رقم الهوية'
                ]);
            }

            // البحث بالأولوية: رقم الملف ثم رقم الهوية
            $guardian = null;

            if (!empty($fileIdNumber)) {
                $guardian = Data::where('file_id_number', $fileIdNumber)->first();
            }

            if (!$guardian && !empty($identityNumber)) {
                $guardian = Data::where('data_id_number', $identityNumber)->first();
            }

            if (!$guardian) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على المعيل'
                ]);
            }

            // جلب الحسابات البنكية
            $bankAccounts = GuardianBankAccount::where('guardian_registration', $guardian->file_id_number)
                ->with(['bankNameRelation'])
                ->get();

            return response()->json([
                'success' => true,
                'guardian' => [
                    'file_id_number' => $guardian->file_id_number,
                    'identity_number' => $guardian->data_id_number,
                    'full_name' => trim(
                        ($guardian->data_first_name ?? '') . ' ' .
                        ($guardian->data_father_name ?? '') . ' ' .
                        ($guardian->data_grand_father_name ?? '') . ' ' .
                        ($guardian->data_family_name ?? '')
                    ),
                    'phone_number' => $guardian->data_phone_number,
                    'alt_phone_number' => $guardian->data_alt_phone_number,
                ],
                'bank_accounts' => $bankAccounts->map(function($account) {
                    return [
                        'id' => $account->id,
                        'bank_name' => $account->bank_name,
                        'bank_description' => $account->bankNameRelation?->description ?? '',
                        'iban_usd' => $account->iban_usd,
                        'iban_shekel' => $account->iban_shekel,
                        'person_owner_identity_number' => $account->person_owner_identity_number,
                        're_guardian_name' => $account->re_guardian_name,
                        're_phone_number' => $account->re_phone_number,
                        'check_account' => $account->check_account,
                    ];
                })->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب بيانات المعيل مع الحسابات البنكية', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حفظ بيانات الأم الحية في portal_general_registration_field_values
     * 
     * @param Request $request
     * @param string $fileIdNumber رقم الملف
     * @param string|null $motherId رقم هوية الأم (إذا كانت المعيل ليست الأم)
     * @param bool $guardianIsMother هل المعيل هو الأم؟
     */
    private function saveLivingMotherToPortal(Request $request, string $fileIdNumber, ?string $motherId, bool $guardianIsMother = false): void
    {
        if ($guardianIsMother) {
            // ═══════════════════════════════════════════════════════════
            // الحالة 1: المعيل هو الأم — نسخ بيانات المعيل من جدول data
            // ═══════════════════════════════════════════════════════════
            $guardianData = \App\Models\Data::where('file_id_number', $fileIdNumber)->first();

            if ($guardianData) {
                $portalFields = [
                    'field_mother_status' => 'حية',
                    'field_living_mother_id' => $guardianData->data_id_number,
                    'field_living_mother_first_name' => $guardianData->data_first_name,
                    'field_living_mother_second_name' => $guardianData->data_father_name,
                    'field_living_mother_third_name' => $guardianData->data_grand_father_name,
                    'field_living_mother_last_name' => $guardianData->data_family_name,
                ];
                $guardianIdentity = $guardianData->data_id_number;
            } else {
                Log::warning('⚠️ لم يتم العثور على بيانات المعيل لنسخها كبيانات الأم', [
                    'file_id' => $fileIdNumber
                ]);
                return;
            }
        } else {
            // ═══════════════════════════════════════════════════════════
            // الحالة 2: المعيل ليس الأم — استخدام بيانات الأم من النموذج
            // ═══════════════════════════════════════════════════════════
            $portalFields = [
                'field_mother_status' => 'حية',
                'field_living_mother_id' => $motherId,
                'field_living_mother_first_name' => $request->input('mother_first_name'),
                'field_living_mother_second_name' => $request->input('mother_second_name'),
                'field_living_mother_third_name' => $request->input('mother_third_name'),
                'field_living_mother_last_name' => $request->input('mother_last_name'),
                'field_living_mother_birth_date' => $request->input('mother_birth_date'),
           //     'field_living_mother_phone' => $request->input('mother_phone'),
            ];
			
			
			
			
			
			
            $guardianIdentity = $request->input('data_id_number');
        }

        // جلب sponsorship_id من جدول sponsorships
        $sponsorship = \App\Models\Sponsorship::where('identity_number', $guardianIdentity)->first();
        $sponsorshipId = $sponsorship?->id;

        foreach ($portalFields as $fieldKey => $fieldValue) {
            if (is_null($fieldValue) || $fieldValue === '') {
                continue;
            }

            // البحث عن سجل موجود
            $existingRecord = \App\Models\PortalGeneralRegistrationFieldValue::where('file_id_number', $fileIdNumber)
                ->where('field_key', $fieldKey)
                ->first();

            if ($existingRecord) {
                $existingRecord->update([
                    'field_value' => $fieldValue,
                    'identity_number' => $guardianIdentity,
                    'updated_by_user_id' => auth()->id(),
                ]);
            } else {
                \App\Models\PortalGeneralRegistrationFieldValue::create([
                    'file_id_number' => $fileIdNumber,
                    'identity_number' => $guardianIdentity,
                    'sponsorship_id' => $sponsorshipId,
                    'field_key' => $fieldKey,
                    'field_value' => $fieldValue,
                    'updated_by_user_id' => auth()->id(),
                ]);
            }
        }

        Log::info('✅ تم حفظ بيانات الأم الحية في portal_general_registration_field_values', [
            'guardian_is_mother' => $guardianIsMother,
            'file_id' => $fileIdNumber,
            'fields_count' => count(array_filter($portalFields, fn($v) => !is_null($v) && $v !== ''))
        ]);
    }

    /**
     * حفظ بيانات الأم المتوفية في portal_general_registration_field_values
     */
    private function saveDeceasedMotherToPortal(Request $request, string $fileIdNumber, ?string $motherId): void
    {
        $guardianIdentity = $request->input('data_id_number');

        $portalFields = [
            'field_mother_status' => 'متوفية',
            'field_mother_id' => $motherId,
            'field_mother_first_name' => $request->input('deceased_mother_first_name'),
            'field_mother_second_name' => $request->input('deceased_mother_second_name'),
            'field_mother_third_name' => $request->input('deceased_mother_third_name'),
            'field_mother_last_name' => $request->input('deceased_mother_last_name'),
            'field_mother_death_date' => $request->input('mother_death_date'),
            'field_mother_death_reason' => $request->input('mother_death_reason'),
        ];

        $sponsorship = \App\Models\Sponsorship::where('identity_number', $guardianIdentity)->first();
        $sponsorshipId = $sponsorship?->id;

        foreach ($portalFields as $fieldKey => $fieldValue) {
            if (is_null($fieldValue) || $fieldValue === '') {
                continue;
            }

            $existingRecord = \App\Models\PortalGeneralRegistrationFieldValue::where('file_id_number', $fileIdNumber)
                ->where('field_key', $fieldKey)
                ->first();

            if ($existingRecord) {
                $existingRecord->update([
                    'field_value' => $fieldValue,
                    'identity_number' => $guardianIdentity,
                    'updated_by_user_id' => auth()->id(),
                ]);
            } else {
                \App\Models\PortalGeneralRegistrationFieldValue::create([
                    'file_id_number' => $fileIdNumber,
                    'identity_number' => $guardianIdentity,
                    'sponsorship_id' => $sponsorshipId,
                    'field_key' => $fieldKey,
                    'field_value' => $fieldValue,
                    'updated_by_user_id' => auth()->id(),
                ]);
            }
        }

        Log::info('✅ تم حفظ بيانات الأم المتوفية في portal_general_registration_field_values', [
            'file_id' => $fileIdNumber,
            'mother_id' => $motherId,
            'fields_count' => count(array_filter($portalFields, fn($v) => !is_null($v) && $v !== ''))
        ]);
    }
}

