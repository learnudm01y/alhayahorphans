<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\AcademicDegree;
use App\Models\Attachment;
use App\Models\BankName;
use App\Models\CategoryOfRelation;
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

class GeneralRegistrationController extends Controller
{
    public function index(): View
    {
        $generalSection = GeneralCategory::all();
        // $file_id_number = generateFiveDigitCode(Data::class, 'file_id_number');
        $file_id_number = generateUniqueReservedCode('data', 'file_id_number');
        $category_of_relationship = CategoryOfRelation::all();
        $marital_status = MaritalStatus::all();
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
        $bank_name = BankName::all(); // Assuming you have a BankName model
        return view(
            'user.generalRegistration.create',
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
                'user_password' => 'required|digits:4|confirmed',
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
                // Family members (if any)
                'family_members' => 'sometimes|array',
                // Attachments
                // 'person_identity_number' => 'required|string',
                'document_file.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ], [
                'file_id_number.required' => 'رقم الملف الموحد مطلوب.',
                'document_file.*.mimes' => 'يجب أن تكون صيغة الملف jpg أو jpeg أو png أو pdf.',
                'document_file.*.max' => 'حجم الملف لا يجوز أن يتجاوز 5 ميغابايت.',
                'user_password.required' => 'كلمة المرور مطلوبة.',
                'user_password.digits' => 'يجب أن تتكون كلمة المرور من 4 أرقام فقط.',
                'user_password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
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
                'data_user_insert_data' => $request->input('data_user_insert_data'),
                'data_request_status' => 2, // تأكد من وجود هذا السطر دائماً
            ]);

            // تحديث حالة الرقم في جدول reserved_codes ليصبح مستخدم فعلياً
            DB::table('reserved_codes')
                ->where('code', $fileIdNumber)
                ->update(['used' => true]);

            // إضافة بيانات الحساب البنكي إذا وُجدت أي قيمة بنكية
            $bankAccounts = $request->input('bank_accounts', []);
            Log::info('🟢 بيانات الحسابات البنكية المستلمة من الواجهة:', ['bank_accounts' => $bankAccounts]);
            if (is_array($bankAccounts) && count($bankAccounts) > 0) {
                foreach ($bankAccounts as $bankAccount) {
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
                        GuardianBankAccount::create([
                            'guardian_registration' => $fileIdNumber,
                            'bank_name' => $bankAccount['bank_name'] ?? null,
                            'iban_usd' => $bankAccount['iban_usd'] ?? null,
                            'iban_shekel' => $bankAccount['iban_shekel'] ?? null,
                            're_id_number' => $reIdNumber,
                            're_guardian_name' => $bankAccount['re_guardian_name'] ?? null,
                            're_phone_number' => $bankAccount['re_phone_number'] ?? null,
                        ]);
                        Log::info('🟢 تم تخزين حساب بنكي مع رقم هوية صاحب الحساب:', ['re_id_number' => $reIdNumber]);
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
            Log::info('🟢 عدد المرفقات المستلمة من جميع البوابات:', ['count' => count($attachmentsData), 'attachments' => $attachmentsData, 'family_members' => $request->input('family_members', [])]);
            foreach ($attachmentsData as $index => $data) {
                // استقبال الملف بشكل صحيح
                $file = $request->hasFile("attachments.$index.file") ? $request->file("attachments.$index.file") : null;
                if (!$file) {
                    Log::error('🔴 لم يتم استقبال الملف من الواجهة', ['index' => $index, 'data' => $data, 'all_files' => $request->allFiles()]);
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
                    ]);
                    Log::info('🟢 تم تخزين مرفق بنجاح', ['index' => $index, 'file' => $file, 'data' => $data]);
                } else {
                    Log::error('🔴 تجاهل مرفق بسبب شرط تحقق نهائي', ['index' => $index, 'file' => $file, 'data' => $data]);
                }
            }


            // إضافة مستخدم جديد عند التسجيل العام
            $user = \App\Models\User::create([
                'name' => $request->input('data_first_name'),
                'phone' => $request->input('data_phone_number'),
                'email' => $request->input('data_id_number'), // تخزين رقم الهوية مباشرة في عمود البريد الإلكتروني
                'password' => bcrypt($request->input('user_password')),
                'role' => 'user',
            ]);

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->back()->with('success', 'تم حفظ السجل بنجاح');
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

