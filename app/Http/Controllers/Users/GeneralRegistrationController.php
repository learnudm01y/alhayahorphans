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
        $file_id_number = generateUniqueReservedCode('data', 'file_id_number');
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
                'bank_name'
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
                'data_id_number' => 'required|string',
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
                // Family members (if any)
                'family_members' => 'sometimes|array',
                // Attachments
                // 'person_identity_number' => 'required|string',
                'document_file.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ], [
                'file_id_number.required' => 'رقم الملف الموحد مطلوب.',
                'document_file.*.mimes' => 'يجب أن تكون صيغة الملف jpg أو jpeg أو png أو pdf.',
                'document_file.*.max' => 'حجم الملف لا يجوز أن يتجاوز 5 ميغابايت.',
            ]);


            DB::beginTransaction();

            // استخدم رقم الملف مع الأصفار البادئة دائماً
            $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);

            // تحقق من عدم تكرار رقم الملف العام
            if (\App\Models\Data::where('file_id_number', $fileIdNumber)->exists()) {
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

            // 2. Store main Data record
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
            Log::info('✅ تم حفظ الرقم بنجاح: ' . $fileIdNumber);

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

                        GuardianBankAccount::create([
                            'guardian_registration' => $fileIdNumber,
                            'bank_name' => $bankAccount['bank_name'] ?? null,
                            'iban_usd' => $bankAccount['iban_usd'] ?? null,
                            'iban_shekel' => $bankAccount['iban_shekel'] ?? null,
                            'person_owner_identity_number' => $reIdNumber, // رقم هوية صاحب الحساب
                            're_id_number' => $reIdNumber,
                            're_guardian_name' => $bankAccount['re_guardian_name'] ?? null,
                            're_phone_number' => $bankAccount['re_phone_number'] ?? null,
                        ]);
                        Log::info('🟢 تم تخزين حساب بنكي مع رقم هوية صاحب الحساب:', [
                            'person_owner_identity_number' => $reIdNumber,
                            're_id_number' => $reIdNumber
                        ]);
                    } else {
                        Log::warning('⚠️ لم يتم تخزين حساب بنكي بسبب نقص البيانات', $bankAccount);
                    }
                }
            }


            // 3. Store deceased only إذا كان القسم أيتام ويوجد بيانات للأب أو الأم
            if ($request->input('data_section_id') == 1) {
                $fatherFilled = $request->filled('father_first_name') || $request->filled('father_last_name') || $request->filled('father_id');
                $motherFilled = $request->filled('mother_first_name') || $request->filled('mother_last_name') || $request->filled('mother_id');
                if ($fatherFilled || $motherFilled) {
                    DeadPepole::create([
                        're_file_id' => $fileIdNumber,

                        // بيانات الأب
                        'father_first_name' => $request->input('father_first_name'),
                        'father_second_name' => $request->input('father_second_name'),
                        'father_third_name' => $request->input('father_third_name'),
                        'father_last_name' => $request->input('father_last_name'),
                        'father_id' => $request->input('father_id'),
                        'father_death_date' => $request->input('father_death_date'),
                        'father_death_reason' => $request->input('father_death_reason'),

                        // بيانات الأم (قد تكون فارغة)
                        'mother_first_name' => $request->input('mother_first_name'),
                        'mother_second_name' => $request->input('mother_second_name'),
                        'mother_third_name' => $request->input('mother_third_name'),
                        'mother_last_name' => $request->input('mother_last_name'),
                        'mother_id' => $request->input('mother_id'),
                        'mother_death_date' => $request->input('mother_death_date'),
                        'mother_death_reason' => $request->input('mother_death_reason'),
                    ]);
                }
            }


            // 4. Store family members
            $familyMembers = $request->input('family_members');

            if (is_array($familyMembers)) {
                foreach ($familyMembers as $member) {
                    // حفظ في جدول re_people
                    RePeople::create([
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
                    ]);
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
                if (!$file) {
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
                Log::info('📸 اسم الملف المستلم: ' . $file->getClientOriginalName() . ' | الحجم: ' . $file->getSize());
                Log::info('🟠 معالجة مرفق فرد أسرة', ['index' => $index, 'data' => $data, 'file' => $file]);
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
                if (!$file || !$storedFileName) {
                    Log::error('🔴 تجاهل مرفق بسبب نقص البيانات', ['index' => $index, 'file' => $file, 'storedFileName' => $storedFileName, 'data' => $data]);
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
                if ($file && $realPersonId && $fileType && $fileIdNumberAttach && $storedFileName && preg_match('/^\d+$/', $realPersonId)) {
                    $extension = $file->getClientOriginalExtension();
                    // اسم الملف: نوع الوثيقة _ رقم الملف الخاص بالشخص _ رقم هوية الشخص
                    $newFileName = "{$fileType}_{$fileIdNumberAttach}_{$realPersonId}.{$extension}";
                    $folder = 'uploads/' . $fileIdNumberAttach;
                    if ($folder === 'public' || $folder === 'public/') {
                        throw new \Exception('خطأ في مسار التخزين: يجب تحديد مجلد فرعي داخل uploads');
                    }
                    $path = $file->storeAs($folder, $newFileName, 'public');
                    Attachment::create([
                        'person_identity_number' => $realPersonId,
                        'stored_file_name' => $newFileName,
                        'file_path' => 'storage/' . $path,
                        'file_type' => $fileType,
                        'file_size' => $file->getSize(),
                    ]);
                    Log::info('🟢 تم تخزين مرفق بنجاح', ['index' => $index, 'file' => $file, 'data' => $data, 'newFileName' => $newFileName]);
                } else {
                    Log::error('🔴 تجاهل مرفق بسبب شرط تحقق نهائي', ['index' => $index, 'file' => $file, 'data' => $data]);
                }
            }


            // إضافة مستخدم جديد عند التسجيل العام
            // توليد كلمة مرور عشوائية من 8 أحرف
            $randomPassword = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            $user = \App\Models\User::create([
                'name' => $request->input('data_first_name'),
                'phone' => $request->input('data_phone_number'),
                'email' => $request->input('data_id_number'), // تخزين رقم الهوية مباشرة في عمود البريد الإلكتروني
                'password' => bcrypt($randomPassword),
                'role' => 'user',
            ]);

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return response()->json(['success' => true, 'redirect' => route('user.login.page.index', ['success' => 1])]);
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

            // 1. البحث في جدول data (بيانات المستفيدين) - محسّن
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

            // 2. البحث في جدول re_people - محسّن
            $rePeopleResult = $this->searchInRePeopleOptimized($searchTerm);
            if ($rePeopleResult) {
                $results['found'] = true;
                $results['has_account'] = true;
                $results['source'] = 're_people';
                $results['data'] = $rePeopleResult;
                $results['message'] = 'تم العثور على سجل موجود مسبقاً.';
                $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                return response()->json($results);
            }

            // 3. البحث في جدول dead_people - محسّن
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

            // 4. البحث في السجل المدني - محسّن باستخدام NormalizedSearchService
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $civilRegistryResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);

            // 4. البحث في السجل المدني - محسّن باستخدام NormalizedSearchService
            $normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
            $civilRegistryResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);

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
                        \Log::warning('خطأ في تحويل تاريخ الميلاد: ' . $e->getMessage());
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
                $results['message'] = 'تم العثور على بيانات في السجل المدني. يمكنك المتابعة لإنشاء حساب جديد.';
                $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
                return response()->json($results);
            }

            // إذا لم يتم العثور على نتائج
            $results['message'] = 'لم يتم العثور على أي بيانات مطابقة. يرجى التأكد من رقم الهوية والمحاولة مرة أخرى.';
            $results['search_time'] = round((microtime(true) - $startTime) * 1000, 2) . ' ms';
            return response()->json($results);

        } catch (\Exception $e) {
            \Log::error('خطأ في البحث: ' . $e->getMessage());
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
                    'message' => 'لم يتم العثور على بيانات في السجل المدني'
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
                    \Log::warning('خطأ في تحويل تاريخ الميلاد: ' . $e->getMessage());
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
            \Log::error('خطأ في جلب البيانات من السجل المدني: ' . $e->getMessage());
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
}

