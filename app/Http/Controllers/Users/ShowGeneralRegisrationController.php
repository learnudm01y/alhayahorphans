<?php

namespace App\Http\Controllers\Users;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Data;
use App\Models\Sponsorship;
use App\Models\SponsorFieldSetting;
use App\Models\GuardianBankAccount;
use App\Models\RePeople;
use App\Models\Attachment;
use App\Models\PortalGeneralRegistrationFieldValue;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Log;


class ShowGeneralRegisrationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $userIdNumber = trim($user->email); // رقم الهوية من الإيميل

        Log::info('=== USER DATA LOADING ===', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'identity_search' => $userIdNumber
        ]);

        // البحث عن الكفالة بطرق متعددة
        $sponsorship = Sponsorship::where('identity_number', $userIdNumber)
            ->orWhere('identity_number', 'LIKE', "%{$userIdNumber}%")
            ->first();

        // إذا لم نجد، نبحث برقم الملف
        if (!$sponsorship && is_numeric($userIdNumber)) {
            $sponsorship = Sponsorship::where('internal_file_number', $userIdNumber)->first();
        }

        if (!$sponsorship) {
            Log::error('SPONSORSHIP NOT FOUND', ['identity' => $userIdNumber]);
            return view('user.dashboard.component.generalRegisrationIndex', [
                'sponsorship' => null,
                'error' => 'لم يتم العثور على بيانات الكفالة'
            ]);
        }

        // تحميل جميع العلاقات
        $sponsorship->load([
            'sponsor',
            'relationData.province',
            'relationData.city',
            'relationData.healthStatus',
            'relationData.maritalStatus',
            'relationData.academicQualification',
            'relationData.housingStatus',
            'relationData.currentHousingType',
            'relationData.rePeople',
            'relationData.deadPepole',
        ]);

        Log::info('SPONSORSHIP FOUND', [
            'sponsorship_id' => $sponsorship->id,
            'orphan_name' => $sponsorship->orphan_name,
            'sponsor_id' => $sponsorship->sponsor_id
        ]);

        // جلب إعدادات الحقول للجمعية
        $sponsorId = $sponsorship->sponsor_id;
        $fieldSettings = null;
        $enabledFields = [];
        $fieldsConfig = config('sponsor_fields.fields', []);

        if ($sponsorId) {
            $fieldSettings = SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

            if ($fieldSettings) {
                // جلب الحقول المفعلة فقط (القيمة = 1)
                foreach ($fieldsConfig as $fieldKey => $fieldInfo) {
                    if (isset($fieldSettings->{$fieldKey}) && $fieldSettings->{$fieldKey} == 1) {
                        $enabledFields[$fieldKey] = $fieldInfo;
                    }
                }
            } else {
                // إذا لم توجد إعدادات، نعرض كل الحقول
                $enabledFields = $fieldsConfig;
            }
        }

        // جلب الحساب البنكي المعتمد فقط
        $approvedBankAccount = GuardianBankAccount::where('guardian_registration', $sponsorship->internal_file_number)
            ->where('check_account', 1)
            ->first();

        // تجميع الحقول حسب الفئات
        $groupedFields = [];
        $categories = config('sponsor_fields.categories', []);

        foreach ($enabledFields as $fieldKey => $fieldInfo) {
            $categoryId = $fieldInfo['category_id'];
            if (!isset($groupedFields[$categoryId])) {
                $groupedFields[$categoryId] = [
                    'name' => $categories[$categoryId] ?? 'غير محدد',
                    'fields' => []
                ];
            }
            $groupedFields[$categoryId]['fields'][] = $fieldInfo;
        }

        // ترتيب الحقول داخل كل فئة حسب order
        foreach ($groupedFields as $categoryId => $categoryData) {
            usort($groupedFields[$categoryId]['fields'], function($a, $b) {
                return ($a['order'] ?? 999) - ($b['order'] ?? 999);
            });
        }

        // ترتيب الفئات حسب الترتيب
        ksort($groupedFields);

        // Prepare field values from all sources
        $fieldValues = $this->extractFieldValues($sponsorship);

        // جلب قوائم السكن
        $housingStatuses = \App\Models\HousingStatus::all();
        $housingTypes = \App\Models\TypeOfAccommodation::all();

        // جلب قائمة الحالات الصحية
        $healthStatuses = DB::table('health_statuses')->get();

        // جلب قائمة البنوك
        $bankNames = DB::table('bank_names')->get();

        // جلب قائمة أسباب الوفاة
        $deathReasons = DB::table('death_reasons')->get();

        // جلب قوائم المحافظات والمدن
        $provinces = DB::table('provinces')->get();
        $cities = DB::table('city')->get();

        // جلب أفراد الأسرة (استبعاد المكفول نفسه)
        $familyMembers = collect();
        if ($sponsorship->relationData) {
            $familyMembers = DB::table('re_people')
                ->where('registration_id', $sponsorship->relationData->file_id_number)
                ->where('person_id', '!=', $sponsorship->identity_number)
                ->get();
        }

        // جلب الحساب البنكي المعتمد فقط مع اسم البنك
        $approvedBankAccount = null;
        if ($sponsorship->relationData) {
            $approvedBankAccount = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $sponsorship->relationData->file_id_number)
                ->where('check_account', 1)
                ->first();

            // إضافة اسم البنك
            if ($approvedBankAccount) {
                $bankName = DB::table('bank_names')
                    ->where('id', $approvedBankAccount->bank_name)
                    ->value('description');
                $approvedBankAccount->bank_name_text = $bankName;
            }
        }

        // جلب أنواع المرفقات المفعلة للجمعية فقط
        $documentTypes = collect();
        if ($sponsorId && $fieldSettings) {
            // جلب معرفات الوثائق المفعلة من sponsor_field_settings
            $enabledDocumentIds = $fieldSettings->enabled_documents ?? [];

            if (!empty($enabledDocumentIds)) {
                $documentTypes = \App\Models\DocumentType::whereIn('id', $enabledDocumentIds)->get();
            }

            Log::info('ENABLED DOCUMENTS FOR SPONSOR', [
                'sponsor_id' => $sponsorId,
                'enabled_document_ids' => $enabledDocumentIds,
                'documents_count' => $documentTypes->count(),
                'documents' => $documentTypes->pluck('description', 'id')
            ]);
        }

        // جلب المرفقات الموجودة حالياً
        $existingAttachments = collect();
        if ($sponsorship->identity_number) {
            $existingAttachments = \App\Models\Attachment::where('person_identity_number', $sponsorship->identity_number)
                ->get()
                ->groupBy('file_type');
        }

        return view('user.dashboard.component.generalRegisrationIndex', compact(
            'sponsorship',
            'enabledFields',
            'groupedFields',
            'approvedBankAccount',
            'fieldsConfig',
            'fieldValues',
            'housingStatuses',
            'housingTypes',
            'healthStatuses',
            'familyMembers',
            'bankNames',
            'deathReasons',
            'provinces',
            'cities',
            'documentTypes',
            'existingAttachments'
        ));
    }

    /**
     * استخراج قيم الحقول من جميع المصادر
     */
    private function extractFieldValues($sponsorship)
    {
        $values = [];
        $identityNumber = $sponsorship->identity_number;

        // From sponsorships table
        $values['field_sponsor_name'] = $sponsorship->orphan_name; // إسم المكفول
        $values['field_orphan_name'] = $sponsorship->orphan_name;
        $values['field_identity_number'] = $sponsorship->identity_number;
        $values['field_internal_file_number'] = $sponsorship->internal_file_number;

        // تحديد نوع الشخص المكفول
        $personType = null; // data, dead_people, re_people
        $guardianData = null; // بيانات المعيل

        // 1. البحث في data (معيل)
        $dataByIdentity = DB::table('data')->where('data_id_number', $identityNumber)->first();
        if ($dataByIdentity) {
            $personType = 'data';
            $guardianData = $dataByIdentity;
        }

        // 2. البحث في dead_people (متوفي)
        if (!$personType) {
            $deadPerson = DB::table('dead_people')
                ->where('father_id', $identityNumber)
                ->orWhere('mother_id', $identityNumber)
                ->first();
            if ($deadPerson) {
                $personType = 'dead_people';
                // جلب بيانات المعيل من data
                $guardianData = DB::table('data')->where('file_id_number', $deadPerson->re_file_id)->first();
            }
        }

        // 3. البحث في re_people (فرد أسرة)
        $rePerson = null;
        if (!$personType) {
            $rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
            if ($rePerson) {
                $personType = 're_people';
                // جلب بيانات المعيل من data بناءً على registration_id
                $guardianData = DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
            }
        }

        Log::info('PERSON TYPE DETERMINED', [
            'identity' => $identityNumber,
            'type' => $personType,
            'has_guardian_data' => $guardianData ? true : false
        ]);

        // تاريخ الميلاد
        if ($personType == 're_people' && $rePerson && $rePerson->person_birth_date) {
            $values['field_person_birth_date'] = $rePerson->person_birth_date;
        } elseif ($personType == 'data' && $dataByIdentity && $dataByIdentity->data_birth_date) {
            $values['field_person_birth_date'] = $dataByIdentity->data_birth_date;
        }

        // الحالة الصحية
        $healthStatusValue = '';
        if ($personType == 're_people' && $rePerson && $rePerson->person_health_status) {
            $healthStatus = DB::table('health_statuses')->where('id', $rePerson->person_health_status)->first();
            $healthStatusValue = $healthStatus->description ?? '';
        } elseif ($personType == 'data' && $dataByIdentity && $dataByIdentity->data_health_status) {
            $healthStatus = DB::table('health_statuses')->where('id', $dataByIdentity->data_health_status)->first();
            $healthStatusValue = $healthStatus->description ?? '';
        }
        $values['field_health_status'] = $healthStatusValue;

        // حالة السكن ونوع السكن - دائماً من بيانات المعيل (data)
        if ($guardianData) {
            $housingStatus = DB::table('housing_status')->where('id', $guardianData->data_housing_status)->first();
            $values['field_housing_status'] = $housingStatus->description ?? '';

            $housingType = DB::table('type_of_accommodation')->where('id', $guardianData->data_current_housing_type)->first();
            $values['field_housing_type'] = $housingType->description ?? '';

            Log::info('HOUSING DATA EXTRACTED', [
                'housing_status_id' => $guardianData->data_housing_status,
                'housing_status' => $values['field_housing_status'],
                'housing_type_id' => $guardianData->data_current_housing_type,
                'housing_type' => $values['field_housing_type']
            ]);
        }

        $values['field_internal_file_number'] = $sponsorship->internal_file_number;
        $values['field_external_file_number'] = $sponsorship->external_file_number;
        $values['field_guardian_name'] = $sponsorship->guardian_name;
        $values['field_guardian_identity_number'] = $sponsorship->guardian_identity_number;
        $values['field_sponsoring_organization'] = $sponsorship->sponsoring_organization;
        $values['field_sponsorship_start_date'] = $sponsorship->sponsorship_start_date;
        $values['field_sponsorship_end_date'] = $sponsorship->sponsorship_end_date;
        $values['field_sponsorship_duration_months'] = $sponsorship->sponsorship_duration_months;
        $values['field_notes'] = $sponsorship->notes;

        // From relationData (data table)
        if ($sponsorship->relationData) {
            $data = $sponsorship->relationData;
            $values['field_data_id_number'] = $data->data_id_number;
            $values['field_data_first_name'] = $data->data_first_name;
            $values['field_data_father_name'] = $data->data_father_name;
            $values['field_data_grand_father_name'] = $data->data_grand_father_name;
            $values['field_data_family_name'] = $data->data_family_name;
            $values['field_data_birth_date'] = $data->data_birth_date;
            $values['field_data_phone_number'] = $data->data_phone_number;
            $values['field_data_address'] = $data->data_address ?? $data->data_current_address;

            // جلب المحافظة والمدينة
            $values['field_data_province'] = optional($data->province)->description ?? '';
            $values['field_data_city'] = optional($data->city)->city ?? $data->data_city;

            // Dead people info
            if ($data->deadPepole) {
                $dead = $data->deadPepole;
                $values['field_father_first_name'] = trim("{$dead->father_first_name} {$dead->father_second_name} {$dead->father_third_name} {$dead->father_last_name}");
                $values['field_father_id'] = $dead->father_id;
                $values['field_father_death_date'] = $dead->father_death_date;

                // جلب سبب وفاة الأب
                if ($dead->father_death_reason) {
                    $fatherDeathReason = DB::table('death_reasons')
                        ->where('id', $dead->father_death_reason)
                        ->first();
                    $values['field_father_death_reason'] = $fatherDeathReason ? $fatherDeathReason->description : '';
                }

                $values['field_mother_first_name'] = trim("{$dead->mother_first_name} {$dead->mother_second_name} {$dead->mother_third_name} {$dead->mother_last_name}");
                $values['field_mother_id'] = $dead->mother_id;
                $values['field_mother_death_date'] = $dead->mother_death_date;

                // جلب سبب وفاة الأم
                if ($dead->mother_death_reason) {
                    $motherDeathReason = DB::table('death_reasons')
                        ->where('id', $dead->mother_death_reason)
                        ->first();
                    $values['field_mother_death_reason'] = $motherDeathReason ? $motherDeathReason->description : '';
                }
            }

            // Re people info (orphan data) - نأخذ أول سجل من المجموعة
            if ($data->rePeople && $data->rePeople->count() > 0) {
                $re = $data->rePeople->first();
                $values['field_first_name'] = trim("{$re->first_name} {$re->second_name} {$re->third_name} {$re->last_name}");
                $values['field_person_id'] = $re->person_id;
                $values['field_person_birth_date'] = $re->person_birth_date;
                $values['field_person_age'] = $re->person_age;
                $values['field_person_gender'] = $re->person_gender == 1 ? 'ذكر' : ($re->person_gender == 2 ? 'أنثى' : '');
                $values['field_person_note'] = $re->person_note;
            }
        }

        // جلب الحساب البنكي المعتمد من guardian_bank_accounts
        if ($guardianData && $guardianData->file_id_number) {
            $approvedBankAccount = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardianData->file_id_number)
                ->where('check_account', 1)
                ->first();

            if ($approvedBankAccount) {
                // استخدام أسماء الحقول الجديدة بنفس الترتيب في الصورة
                $values['field_guardian_account_owner_name'] = $approvedBankAccount->re_guardian_name;
                $values['field_guardian_bank_name'] = $approvedBankAccount->bank_name; // سيتم عرضه كـ dropdown
                $values['field_guardian_id_owner'] = $approvedBankAccount->person_owner_identity_number;
                $values['field_guardian_phone_number'] = $approvedBankAccount->re_phone_number;
                $values['field_guardian_iban_usd'] = $approvedBankAccount->iban_usd;
                $values['field_guardian_iban_shekel'] = $approvedBankAccount->iban_shekel;

                Log::info('BANK ACCOUNT DATA LOADED', [
                    'account_owner' => $approvedBankAccount->re_guardian_name,
                    'bank_id' => $approvedBankAccount->bank_name,
                    'iban_usd' => $approvedBankAccount->iban_usd
                ]);
            } else {
                Log::warning('NO APPROVED BANK ACCOUNT FOUND', [
                    'guardian_file' => $guardianData->file_id_number
                ]);
            }
        }

        Log::info('FIELD VALUES EXTRACTED', [
            'total_values' => count($values),
            'has_bank_account' => isset($bankAccount)
        ]);

        // تحميل قيم الحقول العامة من جدول مخصص للبوابة (Key/Value)
        try {
            $fileIdNumber = $sponsorship->relationData?->file_id_number;
            if ($fileIdNumber) {
                $stored = PortalGeneralRegistrationFieldValue::query()
                    ->where('file_id_number', (string) $fileIdNumber)
                    ->pluck('field_value', 'field_key');

                foreach ($stored as $k => $v) {
                    // لا نكسر القيم المحسوبة إن كانت موجودة، لكن نملأ القيم التي لا نعرف مصدرها
                    if (!array_key_exists($k, $values) || $values[$k] === null || $values[$k] === '') {
                        $values[$k] = $v;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PORTAL_FIELDS_LOAD_FAILED', [
                'sponsorship_id' => $sponsorship->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $values;
    }

    /**
     * تحديث بيانات الكفالة
     */
    public function updateSponsorshipData(Request $request)
    {
        try {
            $user = Auth::user();
            $sponsorshipId = $request->input('sponsorship_id');

            // Logging مبكر لمعرفة هل الملفات وصلت فعلاً للسيرفر
            $allFiles = $request->allFiles();
            $attachmentsFiles = $allFiles['attachments'] ?? null;
            $attachmentsSummary = [];
            $attachmentsTotal = 0;
            $attachmentsValidTotal = 0;
            $attachmentsInvalidSummary = [];
            $validAttachments = [];

            $fieldsIncoming = $request->input('fields', []);
            $fieldsIncomingCount = is_array($fieldsIncoming) ? count($fieldsIncoming) : 0;
            $fieldsIncomingKeysSample = is_array($fieldsIncoming) ? array_slice(array_keys($fieldsIncoming), 0, 25) : [];

            if (is_array($attachmentsFiles)) {
                foreach ($attachmentsFiles as $docTypeId => $files) {
                    $count = 0;
                    $names = [];
                    $validCount = 0;
                    $invalid = [];
                    foreach ((array) $files as $file) {
                        if ($file instanceof \Illuminate\Http\UploadedFile) {
                            $count++;
                            $names[] = $file->getClientOriginalName();

                            if ($file->isValid()) {
                                $validCount++;
                                $validAttachments[(string) $docTypeId][] = $file;
                            } else {
                                $invalid[] = [
                                    'name' => $file->getClientOriginalName(),
                                    'error' => $file->getError(),
                                    'size' => $file->getSize(),
                                ];
                            }
                        }
                    }
                    $attachmentsTotal += $count;
                    $attachmentsValidTotal += $validCount;
                    $attachmentsSummary[(string) $docTypeId] = [
                        'count' => $count,
                        'names' => array_slice($names, 0, 3),
                    ];

                    if (!empty($invalid)) {
                        $attachmentsInvalidSummary[(string) $docTypeId] = array_slice($invalid, 0, 5);
                    }
                }
            }

            Log::info('UPDATE_SPONSORSHIP_SUBMIT', [
                'user_id' => $user?->id,
                'sponsorship_id' => $sponsorshipId,
                // hasFile() يعتمد على validity وقد يرجع false حتى لو allFiles يحتوي عناصر غير صالحة
                // مع الـ inputs المتداخلة attachments[docTypeId][] لا يمكن الاعتماد على hasFile('attachments')
                'has_attachments_valid' => $attachmentsValidTotal > 0,
                'attachments_total_files' => $attachmentsTotal,
                'attachments_valid_files' => $attachmentsValidTotal,
                'attachments_summary' => $attachmentsSummary,
                'attachments_invalid_summary' => $attachmentsInvalidSummary,
                'fields_count' => $fieldsIncomingCount,
                'fields_keys_sample' => $fieldsIncomingKeysSample,
                'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                'php_post_max_size' => ini_get('post_max_size'),
                'php_max_file_uploads' => ini_get('max_file_uploads'),
            ]);

            if ($fieldsIncomingCount === 0) {
                Log::warning('UPDATE_SPONSORSHIP_NO_FIELDS_RECEIVED', [
                    'user_id' => $user?->id,
                    'sponsorship_id' => $sponsorshipId,
                    'note' => 'لم تصل أي حقول ضمن fields[]. تحقق من name="fields[...]" داخل form ومن عدم وجود عناصر disabled/عدم وجود JS يمنع الإرسال.',
                ]);
            }

            // إذا تم اختيار ملفات ولكن لم يصل أي ملف صالح، نوقف العملية برسالة واضحة
            if ($attachmentsTotal > 0 && $attachmentsValidTotal === 0) {
                throw new \Exception('تم اختيار ملفات ولكن لم تصل للسيرفر كملفات صالحة (قد تكون أكبر من upload_max_filesize/post_max_size أو حدث خطأ أثناء الرفع).');
            }

            // التحقق من أن المستخدم يملك هذه الكفالة
            $sponsorship = Sponsorship::with(['relationData', 'sponsor'])
                ->where('id', $sponsorshipId)
                ->where('identity_number', $user->email)
                ->firstOrFail();

            // التحقق من البيانات
            $request->validate([
                'fields' => 'array',
                'family_members' => 'array',
                // attachments[documentTypeId][] => uploaded files
                'attachments' => 'sometimes|array',
                'attachments.*' => 'sometimes|array',
                'attachments.*.*' => 'file|mimes:pdf,jpg,jpeg,png,gif,webp,mp4,avi,mov,wmv,webm|max:51200', // 50MB
            ]);

            DB::beginTransaction();

            // كاش لأعمدة جدول data لتجنب Schema::hasColumn داخل loop (أسرع بكثير)
            $dataColumnMap = [];
            try {
                $dataColumnMap = array_fill_keys(Schema::getColumnListing('data'), true);
            } catch (\Throwable $e) {
                $dataColumnMap = [];
            }

            // تحديث الحقول الأساسية في جدول sponsorships
            $sponsorshipFields = ['orphan_name', 'identity_number', 'birth_date', 'internal_file_number',
                                 'guardian_name', 'guardian_phone', 'guardian_relationship'];

            $fieldsData = $request->input('fields', []);

            // تخزين جميع الحقول الواردة في جدول مخصص (حتى لو لم يكن لها عمود/جدول بعد)
            try {
                $fileIdNumberForPortal = (string) ($sponsorship->relationData?->file_id_number ?: $sponsorship->internal_file_number ?: '');
                $identityForPortal = (string) ($sponsorship->identity_number ?: '');
                $storedCount = 0;

                if (is_array($fieldsData) && $fileIdNumberForPortal !== '') {
                    foreach ($fieldsData as $fieldKey => $fieldValue) {
                        // نخزن القيم كسلسلة أو JSON إذا كانت مصفوفة
                        if (is_array($fieldValue)) {
                            $fieldValue = json_encode($fieldValue, JSON_UNESCAPED_UNICODE);
                        } elseif (is_bool($fieldValue)) {
                            $fieldValue = $fieldValue ? '1' : '0';
                        } elseif ($fieldValue !== null) {
                            $fieldValue = (string) $fieldValue;
                        }

                        PortalGeneralRegistrationFieldValue::query()->updateOrCreate(
                            [
                                'file_id_number' => $fileIdNumberForPortal,
                                'field_key' => (string) $fieldKey,
                            ],
                            [
                                'sponsorship_id' => $sponsorship->id,
                                'identity_number' => $identityForPortal,
                                'field_value' => $fieldValue,
                                'updated_by_user_id' => $user?->id,
                            ]
                        );

                        $storedCount++;
                    }
                }

                Log::info('PORTAL_FIELDS_STORED', [
                    'sponsorship_id' => $sponsorship->id,
                    'file_id_number' => $fileIdNumberForPortal,
                    'stored_count' => $storedCount,
                    'fields_received_count' => is_array($fieldsData) ? count($fieldsData) : 0,
                ]);
            } catch (\Throwable $e) {
                Log::error('PORTAL_FIELDS_STORE_FAILED', [
                    'sponsorship_id' => $sponsorship->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // حقول البنك الجديدة
            $bankFields = [];
            // حقول أسباب الوفاة
            $deathReasonFields = [];

            // تجميع الحقول التي لم نستطع ربطها بعمود في DB
            $unmappedFieldKeys = [];
            $mappedToSponsorship = [];
            $mappedToData = [];

            foreach ($fieldsData as $fieldKey => $fieldValue) {
                $cleanFieldKey = str_replace('field_', '', $fieldKey);

                // بعض الحقول يتم إرسالها كنص (description) من الـ UI بينما تُحفظ كـ ID في جدول data
                if ($sponsorship->relationData && $fieldValue !== null && $fieldValue !== '') {
                    // provinces.description -> data_province (INT)
                    if ($cleanFieldKey === 'data_province') {
                        $raw = trim((string) $fieldValue);
                        if ($raw !== '') {
                            if (is_numeric($raw)) {
                                $sponsorship->relationData->data_province = (int) $raw;
                            } else {
                                $resolved = (int) (DB::table('provinces')->where('description', $raw)->value('id') ?? 0);
                                if ($resolved > 0) {
                                    $sponsorship->relationData->data_province = $resolved;
                                } else {
                                    Log::warning('LOOKUP_ID_NOT_FOUND', [
                                        'field' => 'data_province',
                                        'value' => $raw,
                                        'sponsorship_id' => $sponsorship->id,
                                        'table' => 'provinces',
                                        'column' => 'description',
                                    ]);
                                }
                            }
                        }
                        continue;
                    }

                    // city.city -> data_city (INT)
                    if ($cleanFieldKey === 'data_city') {
                        $raw = trim((string) $fieldValue);
                        if ($raw !== '') {
                            if (is_numeric($raw)) {
                                $sponsorship->relationData->data_city = (int) $raw;
                            } else {
                                $resolved = (int) (DB::table('city')->where('city', $raw)->value('id') ?? 0);
                                if ($resolved <= 0) {
                                    // بعض قواعد البيانات تستخدم description بدل city
                                    $resolved = (int) (DB::table('city')->where('description', $raw)->value('id') ?? 0);
                                }
                                if ($resolved > 0) {
                                    $sponsorship->relationData->data_city = $resolved;
                                } else {
                                    Log::warning('LOOKUP_ID_NOT_FOUND', [
                                        'field' => 'data_city',
                                        'value' => $raw,
                                        'sponsorship_id' => $sponsorship->id,
                                        'table' => 'city',
                                        'columns_tried' => ['city', 'description'],
                                    ]);
                                }
                            }
                        }
                        continue;
                    }

                    // health_statuses.description -> data_health_status
                    if ($cleanFieldKey === 'health_status') {
                        $resolved = $this->resolveLookupIdByDescription('health_statuses', (string) $fieldValue);
                        if ($resolved !== null) {
                            $sponsorship->relationData->data_health_status = $resolved;
                        } else {
                            Log::warning('LOOKUP_ID_NOT_FOUND', [
                                'field' => 'health_status',
                                'value' => (string) $fieldValue,
                                'sponsorship_id' => $sponsorship->id,
                            ]);
                        }
                        continue;
                    }

                    // housing_status.description -> data_housing_status
                    if ($cleanFieldKey === 'housing_status') {
                        $resolved = $this->resolveLookupIdByDescription('housing_status', (string) $fieldValue);
                        if ($resolved !== null) {
                            $sponsorship->relationData->data_housing_status = $resolved;
                        } else {
                            Log::warning('LOOKUP_ID_NOT_FOUND', [
                                'field' => 'housing_status',
                                'value' => (string) $fieldValue,
                                'sponsorship_id' => $sponsorship->id,
                            ]);
                        }
                        continue;
                    }

                    // type_of_accommodation.description -> data_current_housing_type
                    if ($cleanFieldKey === 'housing_type') {
                        $resolved = $this->resolveLookupIdByDescription('type_of_accommodation', (string) $fieldValue);
                        if ($resolved !== null) {
                            $sponsorship->relationData->data_current_housing_type = $resolved;
                        } else {
                            Log::warning('LOOKUP_ID_NOT_FOUND', [
                                'field' => 'housing_type',
                                'value' => (string) $fieldValue,
                                'sponsorship_id' => $sponsorship->id,
                            ]);
                        }
                        continue;
                    }
                }

                // جمع حقول البنك
                if (in_array($fieldKey, [
                    'field_guardian_account_owner_name',
                    'field_guardian_bank_name',
                    'field_guardian_id_owner',
                    'field_guardian_phone_number',
                    'field_guardian_iban_usd',
                    'field_guardian_iban_shekel'
                ])) {
                    $bankFields[$fieldKey] = $fieldValue;
                    continue;
                }

                // جمع حقول أسباب الوفاة
                if (in_array($fieldKey, ['field_father_death_reason', 'field_mother_death_reason'])) {
                    $deathReasonFields[$fieldKey] = $fieldValue;
                    continue;
                }

                // تحديث في جدول sponsorships
                if (in_array($cleanFieldKey, $sponsorshipFields)) {
                    $sponsorship->$cleanFieldKey = $fieldValue;
                    $mappedToSponsorship[] = $fieldKey;
                }
                // تحديث في جدول data (relationData)
                else if ($sponsorship->relationData) {
                    // بعض الحقول تأتي بالفعل بصيغة data_xxx (مثال: field_data_city)
                    // لذا لا نضيف data_ مرة ثانية.
                    $candidateColumns = [];
                    if (str_starts_with($cleanFieldKey, 'data_')) {
                        $candidateColumns[] = $cleanFieldKey;
                    } else {
                        $candidateColumns[] = 'data_' . $cleanFieldKey;
                    }

                    foreach ($candidateColumns as $dataColumn) {
                        $exists = !empty($dataColumnMap)
                            ? isset($dataColumnMap[$dataColumn])
                            : Schema::hasColumn('data', $dataColumn);

                        if ($exists) {
                            $sponsorship->relationData->$dataColumn = $fieldValue;
                            $mappedToData[] = $fieldKey;
                            break;
                        }
                    }

                    // إذا لم يتم إيجاد أي عمود مناسب
                    if (empty($candidateColumns)) {
                        $unmappedFieldKeys[] = $fieldKey;
                    } else {
                        $found = false;
                        foreach ($candidateColumns as $col) {
                            $exists = !empty($dataColumnMap) ? isset($dataColumnMap[$col]) : Schema::hasColumn('data', $col);
                            if ($exists) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $unmappedFieldKeys[] = $fieldKey;
                        }
                    }
                }
            }

            if (!empty($unmappedFieldKeys)) {
                Log::warning('UPDATE_SPONSORSHIP_UNMAPPED_FIELDS', [
                    'sponsorship_id' => $sponsorship->id,
                    'unmapped_count' => count($unmappedFieldKeys),
                    'unmapped_keys_sample' => array_slice($unmappedFieldKeys, 0, 30),
                    'note' => 'هذه الحقول وصلت من الفورم لكن لا يوجد عمود مطابق لها في جدول data أو لم يتم دعمها في mapping.',
                ]);
            }

            // تحديث البيانات البنكية
            if (!empty($bankFields) && $sponsorship->relationData) {
                $guardianFileNumber = $sponsorship->relationData->file_id_number;

                // البحث عن الحساب المعتمد
                $bankAccount = DB::table('guardian_bank_accounts')
                    ->where('guardian_registration', $guardianFileNumber)
                    ->where('check_account', 1)
                    ->first();

                if ($bankAccount) {
                    // تحديث البيانات البنكية
                    DB::table('guardian_bank_accounts')
                        ->where('id', $bankAccount->id)
                        ->update([
                            're_guardian_name' => $bankFields['field_guardian_account_owner_name'] ?? $bankAccount->re_guardian_name,
                            'bank_name' => $bankFields['field_guardian_bank_name'] ?? $bankAccount->bank_name,
                            'person_owner_identity_number' => $bankFields['field_guardian_id_owner'] ?? $bankAccount->person_owner_identity_number,
                            're_phone_number' => $bankFields['field_guardian_phone_number'] ?? $bankAccount->re_phone_number,
                            'iban_usd' => $bankFields['field_guardian_iban_usd'] ?? $bankAccount->iban_usd,
                            'iban_shekel' => $bankFields['field_guardian_iban_shekel'] ?? $bankAccount->iban_shekel,
                        ]);
                }
            }

            // تحديث أسباب الوفاة في جدول dead_people
            if (!empty($deathReasonFields) && $sponsorship->relationData) {
                $deadPeople = DB::table('dead_people')
                    ->where('re_file_id', $sponsorship->relationData->file_id_number)
                    ->first();

                if ($deadPeople) {
                    $updates = [];

                    // تحويل وصف سبب الوفاة إلى ID
                    if (isset($deathReasonFields['field_father_death_reason'])) {
                        $reason = DB::table('death_reasons')
                            ->where('description', $deathReasonFields['field_father_death_reason'])
                            ->first();
                        if ($reason) {
                            $updates['father_death_reason'] = $reason->id;
                        }
                    }

                    if (isset($deathReasonFields['field_mother_death_reason'])) {
                        $reason = DB::table('death_reasons')
                            ->where('description', $deathReasonFields['field_mother_death_reason'])
                            ->first();
                        if ($reason) {
                            $updates['mother_death_reason'] = $reason->id;
                        }
                    }

                    if (!empty($updates)) {
                        DB::table('dead_people')
                            ->where('id', $deadPeople->id)
                            ->update($updates);
                    }
                }
            }

            $sponsorship->save();

            if ($sponsorship->relationData) {
                $sponsorship->relationData->save();
            }

            Log::info('UPDATE_SPONSORSHIP_FIELDS_SAVED', [
                'sponsorship_id' => $sponsorship->id,
                'user_id' => $user?->id,
                // لا نسجل القيم الحساسة، فقط أسماء الحقول التي تم تعديلها
                'sponsorship_dirty' => array_keys($sponsorship->getChanges()),
                'data_dirty' => $sponsorship->relationData ? array_keys($sponsorship->relationData->getChanges()) : [],
                'fields_received_count' => is_array($fieldsData) ? count($fieldsData) : 0,
                'fields_mapped_sponsorship_count' => count($mappedToSponsorship),
                'fields_mapped_data_count' => count($mappedToData),
                'bank_fields_count' => count($bankFields),
                'death_reason_fields_count' => count($deathReasonFields),
            ]);

            // تحديث أفراد الأسرة من جدول re_people
            if ($request->has('family_members')) {
                $familyMembers = $request->input('family_members');

                foreach ($familyMembers as $memberData) {
                    // تحقق إذا كان فرد موجود أو جديد
                    if (isset($memberData['is_new']) && $memberData['is_new'] == 1) {
                        // إضافة فرد جديد
                        if ($sponsorship->relationData && !empty($memberData['name'])) {
                            // تقسيم الاسم
                            $nameParts = explode(' ', trim($memberData['name']), 4);

                            DB::table('re_people')->insert([
                                'registration_id' => $sponsorship->relationData->file_id_number,
                                'person_id' => rand(700000000, 799999999), // رقم هوية عشوائي مؤقت
                                'first_name' => $nameParts[0] ?? '',
                                'second_name' => $nameParts[1] ?? '',
                                'third_name' => $nameParts[2] ?? '',
                                'last_name' => $nameParts[3] ?? '',
                                'person_birth_date' => $memberData['birthdate'] ?? null,
                                'person_note' => $memberData['notes'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    } elseif (isset($memberData['id']) && $memberData['id']) {
                        // تحديث فرد موجود
                        if (!empty($memberData['name'])) {
                            $nameParts = explode(' ', trim($memberData['name']), 4);

                            DB::table('re_people')
                                ->where('id', $memberData['id'])
                                ->update([
                                    'first_name' => $nameParts[0] ?? '',
                                    'second_name' => $nameParts[1] ?? '',
                                    'third_name' => $nameParts[2] ?? '',
                                    'last_name' => $nameParts[3] ?? '',
                                    'person_birth_date' => $memberData['birthdate'] ?? null,
                                    'person_note' => $memberData['notes'] ?? null,
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }
            }

            // معالجة المرفقات الجديدة
            if ($attachmentsValidTotal > 0) {
                $driveParentInput = (string) env('GOOGLE_DRIVE_GENERAL_REGISTRATION_PARENT_ID', '');
                if ($driveParentInput === '') {
                    throw new \Exception('إعداد GOOGLE_DRIVE_GENERAL_REGISTRATION_PARENT_ID غير موجود في .env');
                }

                $driveParentId = $this->extractGoogleDriveFolderId($driveParentInput);

                Log::info('GOOGLE_DRIVE_UPLOAD_START', [
                    'sponsorship_id' => $sponsorship->id,
                    'identity' => $sponsorship->identity_number,
                    'drive_parent_input' => $driveParentInput,
                    'drive_parent_id' => $driveParentId,
                    'attachments_total_files' => $attachmentsTotal,
                    'attachments_valid_files' => $attachmentsValidTotal,
                    'attachments_summary' => $attachmentsSummary,
                    'attachments_invalid_summary' => $attachmentsInvalidSummary,
                ]);

                $driveService = new GoogleDriveService();

                // تحقق فعلي عبر Google Drive API أن المعرف هو Folder ID صالح
                try {
                    $parentInfo = $driveService->getFileWithFields($driveParentId, 'id,mimeType,name');
                    $mimeType = $parentInfo['mimeType'] ?? null;
                    if ($mimeType !== 'application/vnd.google-apps.folder') {
                        throw new \Exception('القيمة ليست Folder (mimeType غير صحيح)');
                    }
                } catch (\Exception $e) {
                    $serviceAccountEmail = $this->getGoogleDriveServiceAccountEmail();
                    $emailHint = $serviceAccountEmail ? (" (Service Account: {$serviceAccountEmail})") : '';
                    throw new \Exception(
                        'قيمة GOOGLE_DRIVE_GENERAL_REGISTRATION_PARENT_ID غير صالحة كـ Folder ID أو لا يمكن الوصول إليها.'
                        . $emailHint
                        . ' ضع Folder ID لمجلد داخل Shared Drive ومشارك مع Service Account. مثال رابط: https://drive.google.com/drive/folders/{FOLDER_ID}'
                    );
                }

                $organizationName = $sponsorship->sponsor?->sponsor_name ?: ($sponsorship->sponsoring_organization ?: 'غير محدد');
                $orphanName = $sponsorship->orphan_name ?: $sponsorship->identity_number;

                $temproryFolder = $driveService->getOrCreateFolder($this->sanitizeDriveName('temprory'), $driveParentId);
                $orgFolder = $driveService->getOrCreateFolder($this->sanitizeDriveName($organizationName), $temproryFolder['id']);
                $personFolder = $driveService->getOrCreateFolder($this->sanitizeDriveName($orphanName), $orgFolder['id']);

                $uploadedCount = 0;

                foreach ($validAttachments as $docTypeId => $files) {
                    // الحصول على نوع المستند
                    $documentType = \App\Models\DocumentType::find($docTypeId);

                    if (!$documentType || !$sponsorship->identity_number) {
                        continue;
                    }

                    // معالجة كل ملف
                    $fileIndex = 0;
                    foreach ((array)$files as $file) {
                        $fileIndex++;
                        $extension = strtolower($file->getClientOriginalExtension() ?: '');
                        $baseName = $this->sanitizeDriveName($documentType->description ?: 'وثيقة');

                        // اسم الملف يكون نفس اسم نوع الوثيقة، وإذا كان هناك أكثر من ملف لنفس النوع نضيف رقم
                        $finalBaseName = $fileIndex === 1 ? $baseName : ($baseName . '_' . $fileIndex);
                        $filename = $extension ? ($finalBaseName . '.' . $extension) : $finalBaseName;

                        $uploadResult = $driveService->uploadFile($file->getRealPath(), $filename, $personFolder['id']);
                        $fileId = $uploadResult['id'] ?? null;
                        if (!$fileId) {
                            throw new \Exception('فشل رفع الملف إلى Google Drive: لم يتم إرجاع file id');
                        }

                        // مشاركة للقراءة حتى يتمكن المستخدم من فتح الرابط
                        $driveService->makePublicReadOnly($fileId);
                        $webViewLink = $uploadResult['webViewLink'] ?? "https://drive.google.com/file/d/{$fileId}/view";

                        Attachment::create([
                            'person_identity_number' => $sponsorship->identity_number,
                            'stored_file_name' => $filename,
                            'file_path' => $webViewLink,
                            'file_type' => $docTypeId,
                        ]);

                        $uploadedCount++;

                        Log::info('ATTACHMENT_UPLOADED_TO_GOOGLE_DRIVE', [
                            'identity' => $sponsorship->identity_number,
                            'doc_type' => $documentType->description,
                            'filename' => $filename,
                            'drive_file_id' => $fileId,
                            'drive_folder_id' => $personFolder['id'],
                        ]);
                    }
                }

                Log::info('GOOGLE_DRIVE_ATTACHMENTS_UPLOAD_COMPLETED', [
                    'sponsorship_id' => $sponsorship->id,
                    'identity' => $sponsorship->identity_number,
                    'uploaded_count' => $uploadedCount,
                    'drive_parent_id' => $driveParentId,
                    'temprory_folder_id' => $temproryFolder['id'] ?? null,
                    'organization_folder_id' => $orgFolder['id'] ?? null,
                    'orphan_folder_id' => $personFolder['id'] ?? null,
                ]);
            }

            if ($attachmentsTotal > 0 && $attachmentsValidTotal === 0) {
                Log::warning('GOOGLE_DRIVE_ATTACHMENTS_ALL_INVALID', [
                    'sponsorship_id' => $sponsorship->id,
                    'identity' => $sponsorship->identity_number,
                    'attachments_total_files' => $attachmentsTotal,
                    'attachments_valid_files' => $attachmentsValidTotal,
                    'attachments_invalid_summary' => $attachmentsInvalidSummary,
                    'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                    'php_post_max_size' => ini_get('post_max_size'),
                ]);
            }

            if ($attachmentsTotal === 0) {
                Log::warning('GOOGLE_DRIVE_NO_ATTACHMENTS_RECEIVED', [
                    'sponsorship_id' => $sponsorship->id,
                    'identity' => $sponsorship->identity_number,
                    'attachments_total_files' => $attachmentsTotal,
                    'attachments_summary' => $attachmentsSummary,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('user.generalRegistration.index')
                ->with('success', 'تم حفظ التغييرات بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UPDATE_SPONSORSHIP_ERROR', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'sponsorship_id' => $request->input('sponsorship_id')
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage());
        }
    }

    private function sanitizeDriveName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        // ممنوع / \ : * ? " < > |
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $name);
        $name = trim($name, " .\t\n\r\0\x0B-");
        if ($name === '') {
            return 'غير_مسمى';
        }
        // حد عملي لاسم المجلد/الملف
        return mb_substr($name, 0, 120);
    }

    private function resolveLookupIdByDescription(string $table, string $description): ?int
    {
        $description = trim($description);
        if ($description === '') {
            return null;
        }

        $id = DB::table($table)
            ->where('description', $description)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function extractGoogleDriveFolderId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Accept full folder URL: https://drive.google.com/drive/folders/{ID}
        if (preg_match('~drive\.google\.com/drive/folders/([^/?#]+)~i', $value, $m)) {
            return $m[1];
        }

        // Accept open?id={ID}
        if (preg_match('~[?&]id=([^&]+)~i', $value, $m)) {
            return $m[1];
        }

        return $value;
    }

    private function getGoogleDriveServiceAccountEmail(): ?string
    {
        try {
            $credentialsPath = storage_path('app/google/credentials.json');
            if (!file_exists($credentialsPath)) {
                return null;
            }

            $json = json_decode((string) file_get_contents($credentialsPath), true);
            $email = $json['client_email'] ?? null;
            return is_string($email) && $email !== '' ? $email : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
