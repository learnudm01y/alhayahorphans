<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\RecordsManagementeDataTable;
use App\Http\Controllers\Controller;
use App\Models\AcademicDegree;
use App\Models\Attachment;
use App\Models\CategoryOfRelation;
use App\Models\City;
use App\Models\CI_PERSONAL_CD;
use App\Models\GeneralCategory;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\DeathReason;
use App\Models\DisplacementStatus;
use App\Models\DocumentType;
use App\Models\Employment;
use App\Models\HealthStatus;
use App\Models\HousingStatus;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\RePeople;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfAccommodation;
use App\Models\TypeOfGuarantee;
use App\Models\GuardianBankAccount;
use App\Models\BankName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpParser\Comment\Doc;
use App\Services\RecordsExportService;

class RecordsManagementController extends Controller
{
    public function index(RecordsManagementeDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.records_management.index');
    }

    // Export all records into a single Excel file with three sheets
    public function exportAll(RecordsExportService $exportService)
    {
        return $exportService->exportAll();
    }
       public function create()
    {
        $generalSection = GeneralCategory::all();
        // $file_id_number = generateFiveDigitCode(Data::class, 'file_id_number');
        $file_id_number = generateUniqueReservedCode('data', 'file_id_number');
        $category_of_relationship = CategoryOfRelation::all();
        $marital_status = CI_PERSONAL_CD::all(); // الحالة الاجتماعية
        $academic_qualification = AcademicDegree::all();
        $displacement_status = DisplacementStatus::all();
        $city = City::all();
        $province = Province::all();
        $health_status = HealthStatus::all();
        $employment_status_breadwinner = Employment::all();
        $HousingStatus = HousingStatus::all();
        $TypeOfAccommodation = TypeOfAccommodation::all();
        $documentTypes = DocumentType::all(); // Assuming you have a DocumentType model
        $sponsorship_status = SponsorshipStatus::all();
        $guarantee_types = TypeOfGuarantee::all(); // Assuming you have a TypeOfGuarantee model
        $death_reasons = DeathReason::all(); // Assuming you have a DeathReason model
        $bank_name = BankName::all(); // قائمة البنوك
        return view(
            'admin.dashboard.records_management.create',
            compact(
                'generalSection',
                'file_id_number',
                'category_of_relationship',
                'marital_status',
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
                'bank_name',
                'guarantee_types',
                'death_reasons'
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
                'data_phone_number' => 'nullable|integer',
                'data_alt_phone_number' => 'nullable|integer',
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
                'data_user_insert_data' => 'nullable|string',
                // Family members (if any)
                'family_members' => 'sometimes|array',
                // Attachments
                // 'person_identity_number' => 'required|string',
                'file_type' => 'required|string',
                'document_file.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ], [
                'file_id_number.required' => 'رقم الملف الموحد مطلوب.',
                'document_file.*.mimes' => 'يجب أن تكون صيغة الملف jpg أو jpeg أو png أو pdf.',
                'document_file.*.max' => 'حجم الملف لا يجوز أن يتجاوز 5 ميغابايت.',
            ]);


            DB::beginTransaction();

            // استخدم رقم الملف مع الأصفار البادئة دائماً
            $fileIdNumber = str_pad($request->input('file_id_number'), 6, '0', STR_PAD_LEFT);

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
                'data_user_insert_data' => $request->input('data_user_insert_data'),
                'data_request_status' => 2, // تأكد من وجود هذا السطر دائماً
            ]);

            // وضع علامة على الرقم كمستخدم في جدول reserved_codes
            markCodeAsUsed($fileIdNumber);

            // 2.5 Store bank accounts إذا وُجدت
            $bankAccounts = $request->input('bank_accounts', []);
            Log::info('🟢 بيانات الحسابات البنكية المستلمة من Admin:', ['bank_accounts' => $bankAccounts]);
            if (is_array($bankAccounts) && count($bankAccounts) > 0) {
                foreach ($bankAccounts as $bankAccount) {
                    Log::info('🔵 حساب بنكي فردي من Admin:', $bankAccount);
                    $reIdNumber = $bankAccount['person_owner_identity_number'] ?? null;
                    if (
                        (!empty($bankAccount['bank_name'])) ||
                        (!empty($bankAccount['iban_usd'])) ||
                        (!empty($bankAccount['iban_shekel'])) ||
                        (!empty($bankAccount['re_guardian_name'])) ||
                        (!empty($bankAccount['re_phone_number'])) ||
                        (!empty($reIdNumber))
                    ) {
                        GuardianBankAccount::create([
                            'guardian_registration' => $fileIdNumber,
                            'bank_name' => $bankAccount['bank_name'] ?? null,
                            'iban_usd' => $bankAccount['iban_usd'] ?? null,
                            'iban_shekel' => $bankAccount['iban_shekel'] ?? null,
                            're_id_number' => $reIdNumber,
                            're_guardian_name' => $bankAccount['re_guardian_name'] ?? null,
                            're_phone_number' => $bankAccount['re_phone_number'] ?? null,
                        ]);
                        Log::info('✅ تم تخزين حساب بنكي من Admin بنجاح', ['file_id' => $fileIdNumber, 'bank_name' => $bankAccount['bank_name'] ?? 'N/A']);
                    } else {
                        Log::warning('⚠️ لم يتم تخزين حساب بنكي من Admin بسبب نقص البيانات', $bankAccount);
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
                    ]);
                }
            }



            // عند التخزين، Laravel سيحفظ في storage/app/public/$folder
            // تأكد أن $folder لا يساوي 'public' فقط، بل دائماً يوجد مجلد فرعي (مثل 'uploads/000123')
            // تأكد من صلاحيات الكتابة على storage/app/public ووجود الرابط الرمزي public/storage

            if ($request->hasFile('document_file')) {
                $files = $request->file('document_file');
                $names = $request->input('stored_file_name', []);
                $types = $request->input('file_type', []);
                $personIds = $request->input('person_identity_number', []);

                foreach ($files as $index => $file) {
                    $storedFileName = $names[$index] ?? $file->getClientOriginalName();
                    $personId = $personIds[$index] ?? null;
                    $type = $types[$index] ?? null;

                    // تحقق من وجود اسم ملف صالح
                    if (!$storedFileName) {
                        $storedFileName = $file->getClientOriginalName();
                    }
                    // إذا بقي الاسم فارغًا لأي سبب، تجاهل التخزين
                    if (!$file || !$storedFileName) {
                        continue;
                    }

                    $folder = 'uploads/' . $fileIdNumber; // يجب أن يكون دائماً مجلد فرعي
                    // منع أي محاولة لتخزين في مجلد public مباشرة
                    if ($folder === 'public' || $folder === 'public/') {
                        throw new \Exception('خطأ في مسار التخزين: يجب تحديد مجلد فرعي داخل uploads');
                    }
                    $path = $file->storeAs($folder, $storedFileName, 'public');

                    Attachment::create([
                        'person_identity_number' => $personId,
                        'stored_file_name' => $storedFileName,
                        'file_path' => 'storage/' . $path,
                        'file_type' => $type,
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // معالجة المرفقات الجديدة كمصفوفة Laravel
            $attachments = $request->file('attachments') ?? [];
            $attachmentsData = $request->input('attachments', []);

            foreach ($attachments as $index => $fileArray) {
                // إذا كان $fileArray عبارة عن مصفوفة فيها 'file' => UploadedFile
                $file = is_array($fileArray) && isset($fileArray['file']) ? $fileArray['file'] : $fileArray;
                $personType = $attachmentsData[$index]['person_identity_number'] ?? null; // هذا قد يكون نص مثل main أو family_0 أو رقم
                $fileType = $attachmentsData[$index]['file_type'] ?? null;
                // استخدم رقم الملف مع الأصفار البادئة دائماً
                $fileIdNumberAttach = isset($attachmentsData[$index]['file_id_number'])
                    ? str_pad($attachmentsData[$index]['file_id_number'], 6, '0', STR_PAD_LEFT)
                    : $fileIdNumber;
                $storedFileName = $attachmentsData[$index]['stored_file_name'] ?? ($file ? $file->getClientOriginalName() : null);

                // تحقق من وجود اسم ملف صالح
                if (!$storedFileName && $file) {
                    $storedFileName = $file->getClientOriginalName();
                }
                // إذا بقي الاسم فارغًا لأي سبب، تجاهل التخزين
                if (!$file || !$storedFileName) {
                    continue;
                }

                // استخراج رقم الهوية الحقيقي حسب نوع الشخص
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
                    $realPersonId = isset($familyMembers[$familyIndex]['person_id']) ? $familyMembers[$familyIndex]['person_id'] : null;
                } else {
                    // إذا كان رقم فعلي بالفعل
                    $realPersonId = is_numeric($personType) ? $personType : null;
                }

                // فقط إذا كان رقم الهوية رقمي وغير فارغ
                if ($file && $realPersonId && $fileType && $fileIdNumberAttach && $storedFileName && preg_match('/^\d+$/', $realPersonId)) {
                    $extension = $file->getClientOriginalExtension();
                    $newFileName = "{$fileType}_{$fileIdNumberAttach}_{$realPersonId}.{$extension}";

                    $folder = 'uploads/' . $fileIdNumberAttach; // يجب أن يكون دائماً مجلد فرعي
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
                }
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->route('admin.records.management.create')
                ->with('success', 'تم حفظ السجل بنجاح');
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


}


