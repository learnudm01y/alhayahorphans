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
use App\Services\RcloneGoogleDriveService;
use App\Services\GuardianFileService;
use Illuminate\Support\Facades\Log;


class ShowGeneralRegisrationController extends Controller
{
    /**
     * رابط الدخول المختصر
     * الرابط: /s/رقم_الهوية-رقم_الملف
     */
    public function autoLoginShort($credentials)
    {
        // فك تشفير البيانات
        $parts = explode('-', $credentials);

        if (count($parts) !== 2) {
            return redirect('/users/generalRegistration/login')->withErrors([
                'login_email' => 'رابط غير صالح.',
            ]);
        }

        $identityNumber = $parts[0];
        $fileNumber = $parts[1];

        // استخدام نفس منطق الدخول
        return $this->processAutoLogin($identityNumber, $fileNumber);
    }

    /**
     * الدخول المباشر لتحديث الكفالات
     * الرابط: /auto-login?email=رقم_الهوية&password=رقم_الملف
     */
    public function autoLogin(Request $request)
    {
        $identityNumber = $request->query('email');
        $fileNumber = $request->query('password');

        if (!$identityNumber || !$fileNumber) {
            return redirect('/users/generalRegistration/login')->withErrors([
                'login_email' => 'رابط غير صالح.',
            ]);
        }

        return $this->processAutoLogin($identityNumber, $fileNumber);
    }

    /**
     * معالجة الدخول التلقائي
     */
    private function processAutoLogin($identityNumber, $fileNumber)
    {
        // البحث عن كفالة تطابق رقم الهوية ورقم الملف
        $sponsorship = Sponsorship::with('relationData')
            ->where('identity_number', $identityNumber)
            ->where('internal_file_number', $fileNumber)
            ->first();

        if (!$sponsorship) {
            Log::warning('Auto-login failed', ['identity_number' => $identityNumber]);
            return redirect('/users/generalRegistration/login')->withErrors([
                'login_email' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        // البحث عن المستخدم أو إنشائه
        $user = \App\Models\User::where('email', $identityNumber)->first();

        if (!$user) {
            $user = \App\Models\User::create([
                'name' => $sponsorship->orphan_name ?: 'مستخدم',
                'phone' => $sponsorship->relationData->data_phone_number ?? '0000000000',
                'email' => $identityNumber,
                'password' => bcrypt($fileNumber),
                'role' => 'user',
                'email_verified_at' => now(),
            ]);
        }

        // إلغاء أي جلسة سابقة
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        // تسجيل الدخول الجديد
        Auth::login($user);
        request()->session()->regenerate();
        request()->session()->put('active_sponsorship_id', $sponsorship->id);
        request()->session()->put('active_internal_file_number', $sponsorship->internal_file_number);

        Log::info('Auto-login successful', ['identity_number' => $identityNumber, 'sponsorship_id' => $sponsorship->id]);

        return redirect()->route('user.generalRegistration.index');
    }

    /**
     * 🆕 التحقق من المرفقات الإجبارية
     * يتحقق من أن كل وثيقة مفعلة في البوابة يغطيها ملف واحد على الأقل
     * (ملف رُفع في هذه الجلسة، أو ملف موجود مسبقاً في قاعدة البيانات)
     */
    private function validateRequiredAttachments($sponsorship, array $validAttachments): array
    {
        $missingDocs = [];

        if (!$sponsorship || !$sponsorship->sponsor_id) {
            return $missingDocs;
        }

        // جلب إعدادات الحقول للجمعية
        $fieldSettings = \App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();
        if (!$fieldSettings) {
            return $missingDocs;
        }

        // جلب الوثائق المفعلة للجمعية
        $enabledDocumentIds = $fieldSettings->enabled_documents ?? [];
        if (empty($enabledDocumentIds) || !is_array($enabledDocumentIds)) {
            return $missingDocs;
        }

        $documentTypes = \App\Models\DocumentType::whereIn('id', $enabledDocumentIds)->get();
        if ($documentTypes->isEmpty()) {
            return $missingDocs;
        }

        // تحديد البوابة بناءً على نوع الشخص
        $personType = $sponsorship->person_type;
        $portal = 'basic'; // الافتراضي: البيانات الأساسية

        if ($personType === 'family_member') {
            $portal = 'family';
        } elseif (in_array($personType, ['deceased_father', 'deceased_mother'])) {
            $portal = 'deceased';
        }

        $enabledColumn = $portal . '_enabled';

        // 🆕 جمع معرفات الوثائق المرفوعة حالياً (من الطلب الحالي)
        $uploadedDocTypeIds = array_map('strval', array_keys($validAttachments));

        // 🆕 البند 8: الوثائق المحفوظة مسبقاً لهذه الشخص تكفي أيضاً
        $existingDocTypeIds = [];
        if ($sponsorship->identity_number) {
            $existingDocTypeIds = Attachment::where('person_identity_number', $sponsorship->identity_number)
                ->whereIn('file_type', $enabledDocumentIds)
                ->pluck('file_type')
                ->map(static fn ($value) => (string) $value)
                ->unique()
                ->all();
        }

        // 🆕 التحقق من كل وثيقة مفعلة في البوابة (ملف جديد أو ملف موجود مسبقاً)
        foreach ($documentTypes as $docType) {
            // التحقق من أن الوثيقة مفعلة في البوابة الحالية
            if (!$docType->{$enabledColumn}) {
                continue;
            }

            $docTypeId = (string) $docType->id;

            if (!in_array($docTypeId, $uploadedDocTypeIds, true)
                && !in_array($docTypeId, $existingDocTypeIds, true)) {
                $missingDocs[] = $docType->description;
            }
        }

        if (!empty($missingDocs)) {
            Log::warning('MISSING_REQUIRED_ATTACHMENTS', [
                'sponsorship_id' => $sponsorship->id,
                'portal' => $portal,
                'person_type' => $personType,
                'missing_documents' => $missingDocs,
                'new_uploads' => $uploadedDocTypeIds,
                'already_saved_documents' => $existingDocTypeIds,
            ]);
        }

        return $missingDocs;
    }

    public function index()
    {
        $user = Auth::user();
        $userIdNumber = trim($user->email); // رقم الهوية من الإيميل

        Log::info('=== USER DATA LOADING ===', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'identity_search' => $userIdNumber
        ]);

        // 🆕 أولاً: محاولة جلب الكفالة من الجلسة (الكفالة المحددة عند تسجيل الدخول)
        $sponsorshipId = session('active_sponsorship_id');
        $sponsorship = null;

        if ($sponsorshipId) {
            $sponsorship = Sponsorship::find($sponsorshipId);
            Log::info('SPONSORSHIP FROM SESSION', [
                'session_sponsorship_id' => $sponsorshipId,
                'found' => $sponsorship ? true : false
            ]);
        }

        // إذا لم نجد الكفالة من الجلسة، نبحث بالطريقة التقليدية
        if (!$sponsorship) {
            $sponsorship = Sponsorship::where('identity_number', $userIdNumber)
                ->orWhere('identity_number', 'LIKE', "%{$userIdNumber}%")
                ->first();
        }

        // إذا لم نجد، نبحث برقم الملف
        if (!$sponsorship && is_numeric($userIdNumber)) {
            $sponsorship = Sponsorship::where('internal_file_number', $userIdNumber)->first();
        }

        if (!$sponsorship) {
            Log::error('SPONSORSHIP NOT FOUND', ['identity' => $userIdNumber]);
            session()->now('error', 'لم يتم العثور على بيانات الكفالة');
            return view('user.dashboard.component.generalRegisrationIndex', [
                'sponsorship' => null,
                'error' => 'لم يتم العثور على بيانات الكفالة'
            ]);
        }

        // ✅ التحقق من حالة الكفالة - لا يُسمح بالتحديث إلا إذا كانت الحالة "جارية" (1)
        if ($sponsorship->sponsorship_status_id != 1) {
            Log::info('SPONSORSHIP_CLOSED', [
                'sponsorship_id' => $sponsorship->id,
                'status_id' => $sponsorship->sponsorship_status_id,
                'user_id' => $user->id
            ]);
            session()->now('error', 'تم تحديث البيانات مسبقاً وإغلاق الملف');
            return view('user.dashboard.component.generalRegisrationIndex', [
                'sponsorship' => null,
                'error' => 'تم تحديث البيانات مسبقاً وإغلاق الملف'
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
        $legacyUnmanagedFields = [
            'field_re_guardian_name',
            'field_re_guardian_phone',
            'field_re_guardian_id',
            'field_family_members_count',
            'field_mother_name',
        ];

        if ($sponsorId) {
            $fieldSettings = SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();
            $settingsTable = (new SponsorFieldSetting())->getTable();
            $hasDataRelationshipColumn = Schema::hasColumn($settingsTable, 'field_data_relationship');
            $hasLegacyRelationshipColumn = Schema::hasColumn($settingsTable, 'field_relationship');

            if ($fieldSettings) {
                // جلب الحقول المفعلة فقط (القيمة = 1)
                foreach ($fieldsConfig as $fieldKey => $fieldInfo) {
                    if (in_array($fieldKey, $legacyUnmanagedFields, true)) {
                        continue;
                    }

                    $isEnabled = isset($fieldSettings->{$fieldKey}) && $fieldSettings->{$fieldKey} == 1;
                    if ($fieldKey === 'field_data_relationship') {
                        if ($hasDataRelationshipColumn) {
                            $isEnabled = (int) ($fieldSettings->field_data_relationship ?? 0) === 1;
                        } elseif ($hasLegacyRelationshipColumn) {
                            $isEnabled = (int) ($fieldSettings->field_relationship ?? 0) === 1;
                        }
                    }

                    if ($isEnabled) {
                        $enabledFields[$fieldKey] = $fieldInfo;
                    }
                }
            } else {
                // إذا لم توجد إعدادات، نعرض كل الحقول
                foreach ($fieldsConfig as $fieldKey => $fieldInfo) {
                    if (in_array($fieldKey, $legacyUnmanagedFields, true)) {
                        continue;
                    }
                    $enabledFields[$fieldKey] = $fieldInfo;
                }
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
        $groupedFields += array_diff_key($groupedFields, []);
$orderedCategoryIds = [1, 8, 13, 13.5, 14, 15, 16, 2, 3, 4, 5, 6, 7, 9, 10, 17];
$sorted = [];
foreach ($orderedCategoryIds as $id) {
    if (isset($groupedFields[$id])) $sorted[$id] = $groupedFields[$id];
}
foreach ($groupedFields as $id => $data) {
    if (!isset($sorted[$id])) $sorted[$id] = $data;
}
$groupedFields = $sorted;

        // Prepare field values from all sources
        $fieldValues = $this->extractFieldValues($sponsorship);
        // البند 7: إبقاء ما كتبه المستخدم عند إعادة العرض بعد فشل الحفظ
        $fieldValues = $this->mergeOldInputFields($fieldValues);

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

        // جلب قائمة صلة القرابة
        $categoryOfRelations = DB::table('category_of_relations')->get();

        // جلب قائمة الدرجات العلمية للصف
        $academicDegrees = \App\Models\AcademicDegree::all();

        // جلب قائمة الوظائف
        $employmentStatuses = \App\Models\Employment::all();

        // جلب قائمة احتياجات المكفول
        $orphanNeeds = \App\Models\OrphanNeed::all();

        // جلب قائمة جوانب الإبداع
        $creativityAspects = \App\Models\CreativityAspect::all();

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

        // 🆕 التحكم في إظهار/إخفاء الأقسام
        $showFamilyMembersSection = true; // افتراضياً مفعل
        $showAttachmentsSection = true;   // افتراضياً مفعل

        if ($fieldSettings) {
            // التحقق من إعداد قسم أفراد الأسرة
            if (isset($fieldSettings->field_family_members_section)) {
                $showFamilyMembersSection = (bool) $fieldSettings->field_family_members_section;
            }

            // التحقق من إعداد قسم المرفقات
            if (isset($fieldSettings->field_attachments_section)) {
                $showAttachmentsSection = (bool) $fieldSettings->field_attachments_section;
            }

            // 🆕 إذا كان قسم المرفقات مفعل لكن لا توجد وثائق مفعلة، نخفيه تلقائياً
            if ($showAttachmentsSection && $documentTypes->isEmpty()) {
                $showAttachmentsSection = false;
            }

            // 🆕 التحقق من إعداد ضغط الصور
            $compressAttachments = true; // القيمة الافتراضية: مفعّل
            if (isset($fieldSettings->compress_attachments_images)) {
                $compressAttachments = (bool) $fieldSettings->compress_attachments_images;
            }

            Log::info('SECTION_VISIBILITY_SETTINGS', [
                'sponsor_id' => $sponsorId,
                'show_family_members_section' => $showFamilyMembersSection,
                'show_attachments_section' => $showAttachmentsSection,
                'compress_attachments' => $compressAttachments,
                'documents_count' => $documentTypes->count()
            ]);
        } else {
            // القيمة الافتراضية إذا لم تكن هناك إعدادات
            $compressAttachments = true;
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
            'categoryOfRelations',
            'academicDegrees',
            'employmentStatuses',
            'orphanNeeds',
            'creativityAspects',
            'documentTypes',
            'existingAttachments',
            'showFamilyMembersSection',
            'showAttachmentsSection',
            'compressAttachments'
        ));
    }

    /**
     * البند 7: إعادة أي قيمة كتبها المستخدم قبل إعادة عرض النموذج بعد الخطأ
     * (يغطي الحقول التي لا تستخدم old() في القالب نفسها)
     */
    private function mergeOldInputFields(array $fieldValues): array
    {
        $oldFields = old('fields', []);

        if (!is_array($oldFields)) {
            return $fieldValues;
        }

        foreach ($oldFields as $key => $value) {
            if (!is_string($key) || $value === null) {
                continue;
            }

            // لا نسمح بتغيير المفاتيح الداخلية (مثل نوع الشخص المخزَّن)
            if (str_starts_with($key, '_')) {
                continue;
            }

            // لا نسمح بتغيير القيم غير المكدّسة
            if (isset($fieldValues[$key]) && !is_scalar($fieldValues[$key])) {
                continue;
            }

            $fieldValues[$key] = $value;
        }

        return $fieldValues;
    }

    /**
     * استخراج قيم الحقول من جميع المصادر
     * 🆕 يتم الآن استخدام حقل person_type من جدول sponsorships لتحديد الجدول الصحيح
     */
    private function extractFieldValues($sponsorship)
    {
        $values = [];
        $identityNumber = $sponsorship->identity_number;
        $guardianIdentityNumber = $sponsorship->guardian_identity_number;

        // 🆕 جلب نوع الشخص من جدول sponsorships
        $storedPersonType = $sponsorship->person_type; // breadwinner, family_member, deceased_father, deceased_mother
        $values['_stored_person_type'] = $storedPersonType; // تخزين النوع للاستخدام في العرض

        // تحديد نوع الشخص المكفول ومصدر الاسم
        $personType = null; // data, dead_people, re_people, civil_registry
        $guardianData = null; // بيانات المعيل
        $guardianDataSource = null; // مصدر بيانات المعيل: data, civil_registry, sponsorship_only, none
        $sponsoredPersonName = $sponsorship->orphan_name; // القيمة الافتراضية
        $civilRegistryData = null; // بيانات السجل المدني للمكفول
        $guardianCivilRegistryData = null; // بيانات السجل المدني للمعيل
        $rePerson = null;
        $dataByIdentity = null;
        $deadPerson = null;

        // 🆕 البحث بناءً على نوع الشخص المحفوظ
        if ($storedPersonType === 'breadwinner') {
            // المعيل - البحث في جدول data
            $dataByIdentity = DB::table('data')->where('data_id_number', $identityNumber)->first();
            if ($dataByIdentity) {
                $personType = 'data';
                $guardianData = $dataByIdentity;
                $guardianDataSource = 'data';
                $sponsoredPersonName = trim("{$dataByIdentity->data_first_name} {$dataByIdentity->data_father_name} {$dataByIdentity->data_grand_father_name} {$dataByIdentity->data_family_name}");
            } else {
                // 🆕 البحث في السجل المدني إذا لم نجد في data
                $civilRegistryData = $this->searchCivilRegistry($identityNumber);
                if ($civilRegistryData) {
                    $personType = 'civil_registry';
                    $sponsoredPersonName = trim("{$civilRegistryData['first_name']} {$civilRegistryData['second_name']} {$civilRegistryData['third_name']} {$civilRegistryData['last_name']}");
                    Log::info('BREADWINNER FOUND IN CIVIL REGISTRY', [
                        'identity' => $identityNumber,
                        'name' => $sponsoredPersonName
                    ]);
                }
            }
        } elseif ($storedPersonType === 'family_member') {
            // فرد عائلة - البحث في جدول re_people
            $rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
            if ($rePerson) {
                $personType = 're_people';
                $sponsoredPersonName = trim("{$rePerson->first_name} {$rePerson->second_name} {$rePerson->third_name} {$rePerson->last_name}");
                $guardianData = DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
                if ($guardianData) {
                    $guardianDataSource = 'data';
                }
            } else {
                // 🆕 البحث في السجل المدني إذا لم نجد في re_people
                $civilRegistryData = $this->searchCivilRegistry($identityNumber);
                if ($civilRegistryData) {
                    $personType = 'civil_registry';
                    $sponsoredPersonName = trim("{$civilRegistryData['first_name']} {$civilRegistryData['second_name']} {$civilRegistryData['third_name']} {$civilRegistryData['last_name']}");
                    Log::info('FAMILY_MEMBER FOUND IN CIVIL REGISTRY', [
                        'identity' => $identityNumber,
                        'name' => $sponsoredPersonName
                    ]);
                }
            }
        } elseif ($storedPersonType === 'deceased_father') {
            // أب متوفي - البحث في جدول dead_people
            $deadPerson = DB::table('dead_people')->where('father_id', $identityNumber)->first();
            if ($deadPerson) {
                $personType = 'dead_people';
                $sponsoredPersonName = trim("{$deadPerson->father_first_name} {$deadPerson->father_second_name} {$deadPerson->father_third_name} {$deadPerson->father_last_name}");
                $guardianData = DB::table('data')->where('file_id_number', $deadPerson->re_file_id)->first();
                if ($guardianData) {
                    $guardianDataSource = 'data';
                }
            } else {
                // 🆕 البحث في السجل المدني إذا لم نجد في dead_people
                $civilRegistryData = $this->searchCivilRegistry($identityNumber);
                if ($civilRegistryData) {
                    $personType = 'civil_registry';
                    $sponsoredPersonName = trim("{$civilRegistryData['first_name']} {$civilRegistryData['second_name']} {$civilRegistryData['third_name']} {$civilRegistryData['last_name']}");
                    Log::info('DECEASED_FATHER FOUND IN CIVIL REGISTRY', [
                        'identity' => $identityNumber,
                        'name' => $sponsoredPersonName
                    ]);
                }
            }
        } elseif ($storedPersonType === 'deceased_mother') {
            // أم متوفية - البحث في جدول dead_people
            $deadPerson = DB::table('dead_people')->where('mother_id', $identityNumber)->first();
            if ($deadPerson) {
                $personType = 'dead_people';
                $sponsoredPersonName = trim("{$deadPerson->mother_first_name} {$deadPerson->mother_second_name} {$deadPerson->mother_third_name} {$deadPerson->mother_last_name}");
                $guardianData = DB::table('data')->where('file_id_number', $deadPerson->re_file_id)->first();
                if ($guardianData) {
                    $guardianDataSource = 'data';
                }
            } else {
                // 🆕 البحث في السجل المدني إذا لم نجد في dead_people
                $civilRegistryData = $this->searchCivilRegistry($identityNumber);
                if ($civilRegistryData) {
                    $personType = 'civil_registry';
                    $sponsoredPersonName = trim("{$civilRegistryData['first_name']} {$civilRegistryData['second_name']} {$civilRegistryData['third_name']} {$civilRegistryData['last_name']}");
                    Log::info('DECEASED_MOTHER FOUND IN CIVIL REGISTRY', [
                        'identity' => $identityNumber,
                        'name' => $sponsoredPersonName
                    ]);
                }
            }
        } else {
            // 🔄 الطريقة القديمة - البحث في كل الجداول (للتوافق مع البيانات القديمة)
            // 1. البحث في re_people (فرد أسرة/مكفول) - الأكثر شيوعاً
            $rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
            if ($rePerson) {
                $personType = 're_people';
                // بناء الاسم من 4 أعمدة منفصلة
                $sponsoredPersonName = trim("{$rePerson->first_name} {$rePerson->second_name} {$rePerson->third_name} {$rePerson->last_name}");
                // جلب بيانات المعيل من data بناءً على registration_id
                $guardianData = DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
                if ($guardianData) {
                    $guardianDataSource = 'data';
                }
            }

            // 2. البحث في data (معيل)
            if (!$personType) {
                $dataByIdentity = DB::table('data')->where('data_id_number', $identityNumber)->first();
                if ($dataByIdentity) {
                    $personType = 'data';
                    $guardianData = $dataByIdentity;
                    $guardianDataSource = 'data';
                    // بناء الاسم من 4 أعمدة منفصلة
                    $sponsoredPersonName = trim("{$dataByIdentity->data_first_name} {$dataByIdentity->data_father_name} {$dataByIdentity->data_grand_father_name} {$dataByIdentity->data_family_name}");
                }
            }

            // 3. البحث في dead_people (متوفي)
            if (!$personType) {
                $deadPerson = DB::table('dead_people')
                    ->where('father_id', $identityNumber)
                    ->orWhere('mother_id', $identityNumber)
                    ->first();
                if ($deadPerson) {
                    $personType = 'dead_people';
                    // تحديد إذا كان أب أو أم
                    if ($deadPerson->father_id == $identityNumber) {
                        $sponsoredPersonName = trim("{$deadPerson->father_first_name} {$deadPerson->father_second_name} {$deadPerson->father_third_name} {$deadPerson->father_last_name}");
                    } else {
                        $sponsoredPersonName = trim("{$deadPerson->mother_first_name} {$deadPerson->mother_second_name} {$deadPerson->mother_third_name} {$deadPerson->mother_last_name}");
                    }
                    // جلب بيانات المعيل من data
                    $guardianData = DB::table('data')->where('file_id_number', $deadPerson->re_file_id)->first();
                    if ($guardianData) {
                        $guardianDataSource = 'data';
                    }
                }
            }

            // 4. إذا لم نجد الشخص في أي جدول، نبحث في السجل المدني (persons)
            if (!$personType && $identityNumber) {
                $civilRegistryData = $this->searchCivilRegistry($identityNumber);
                if ($civilRegistryData) {
                    $personType = 'civil_registry';
                    $sponsoredPersonName = trim("{$civilRegistryData['first_name']} {$civilRegistryData['second_name']} {$civilRegistryData['third_name']} {$civilRegistryData['last_name']}");

                    Log::info('FOUND IN CIVIL REGISTRY (legacy search)', [
                        'identity' => $identityNumber,
                        'name' => $sponsoredPersonName
                    ]);
                }
            }
        }

        // 5. البحث عن بيانات المعيل في السجل المدني إذا لم نجده في data
        if (!$guardianData && $guardianIdentityNumber) {
            // أولاً نبحث في جدول data برقم هوية المعيل
            $guardianData = DB::table('data')->where('data_id_number', $guardianIdentityNumber)->first();

            if ($guardianData) {
                $guardianDataSource = 'data';
            } else {
                // إذا لم نجده، نبحث في السجل المدني
                $guardianCivilRegistryData = $this->searchCivilRegistry($guardianIdentityNumber);
                if ($guardianCivilRegistryData) {
                    $guardianDataSource = 'civil_registry';
                    Log::info('GUARDIAN FOUND IN CIVIL REGISTRY', [
                        'identity' => $guardianIdentityNumber,
                        'name' => "{$guardianCivilRegistryData['first_name']} {$guardianCivilRegistryData['last_name']}"
                    ]);
                }
            }
        }

        // تحديد مصدر بيانات المعيل إذا لم يتم تحديده بعد
        if (!$guardianDataSource) {
            // إذا لدينا اسم المعيل في sponsorship فقط
            if ($sponsorship->guardian_name && !empty(trim($sponsorship->guardian_name))) {
                $guardianDataSource = 'sponsorship_only';
            } else {
                $guardianDataSource = 'none';
            }
        }

        // From sponsorships table - استخدام الاسم المجلوب من الجدول الصحيح
        $values['field_sponsor_name'] = $sponsoredPersonName ?: $sponsorship->orphan_name; // إسم المكفول
        $values['field_orphan_name'] = $sponsoredPersonName ?: $sponsorship->orphan_name;
        $values['field_identity_number'] = $sponsorship->identity_number;
        $values['field_internal_file_number'] = $sponsorship->internal_file_number;

        // === متغيرات التحكم في عرض الحقول ===
        // مصدر بيانات المكفول: re_people, data, dead_people, civil_registry, sponsorship_only, none
        $values['_sponsored_data_source'] = $personType ?: ($sponsorship->orphan_name ? 'sponsorship_only' : 'none');
        // مصدر بيانات المعيل: data, civil_registry, sponsorship_only, none
        $values['_guardian_data_source'] = $guardianDataSource;

        // 🆕 متغير مهم: هل نحتاج لإنشاء بيانات مركزية جديدة؟
        // نعم إذا: لا يوجد personType (لم نجد في أي جدول) ولا يوجد relation_id_number
        $needsCentralDataEntry = !$personType && !$sponsorship->relation_id_number;
        $values['_needs_central_data_entry'] = $needsCentralDataEntry;

        // اسم المكفول من جدول sponsorships (للعرض فقط عند عدم وجود بيانات مركزية)
        $values['_sponsorship_orphan_name'] = $sponsorship->orphan_name ?? '';
        $values['_sponsorship_guardian_name'] = $sponsorship->guardian_name ?? '';

        // هل يجب عرض 4 حقول منفصلة أم حقل واحد؟
        // للمكفول: 4 حقول إذا البيانات من (re_people, data, dead_people, civil_registry)
        // أو إذا كانت حالة "sponsorship_only" ونحتاج لإدخال البيانات المركزية
        $values['_sponsored_show_4_fields'] = in_array($personType, ['re_people', 'data', 'dead_people', 'civil_registry']) || $needsCentralDataEntry;
        // للمعيل: 4 حقول إذا البيانات من (data, civil_registry) - حقل واحد إذا من (sponsorship_only, none)
        // أو إذا كانت حالة "sponsorship_only" ونحتاج لإدخال البيانات المركزية
        $values['_guardian_show_4_fields'] = in_array($guardianDataSource, ['data', 'civil_registry']) || $needsCentralDataEntry;

        // هل نحتاج البحث في السجل المدني للمعيل؟
        // نعرض زر البحث فقط عندما:
        // 1. لا يوجد رقم هوية للمعيل (guardian_identity_number فارغ أو null)
        // 2. أو عندما لا توجد بيانات تفصيلية للمعيل (none)
        $guardianHasNoIdentity = empty($guardianIdentityNumber);
        $values['_guardian_needs_civil_search'] = $guardianHasNoIdentity;

        Log::info('PERSON TYPE DETERMINED', [
            'identity' => $identityNumber,
            'type' => $personType,
            'has_guardian_data' => $guardianData ? true : false,
            'has_civil_registry_data' => $civilRegistryData ? true : false,
            'sponsored_person_name' => $sponsoredPersonName,
            'guardian_data_source' => $guardianDataSource,
            'sponsored_show_4_fields' => $values['_sponsored_show_4_fields'],
            'guardian_show_4_fields' => $values['_guardian_show_4_fields'],
            'needs_central_data_entry' => $needsCentralDataEntry,
        ]);

        // تاريخ الميلاد - نبحث في عدة مصادر
        if ($personType == 're_people' && $rePerson && $rePerson->person_birth_date) {
            $values['field_person_birth_date'] = $rePerson->person_birth_date;
        } elseif ($personType == 'data' && $dataByIdentity && $dataByIdentity->data_birth_date) {
            $values['field_person_birth_date'] = $dataByIdentity->data_birth_date;
        } elseif ($personType == 'civil_registry' && $civilRegistryData && !empty($civilRegistryData['birth_date'])) {
            $values['field_person_birth_date'] = $civilRegistryData['birth_date'];
        } elseif ($sponsorship->sponsored_birth_date) {
            // 🆕 استخدام تاريخ الميلاد المخزن في جدول sponsorships
            $values['field_person_birth_date'] = $sponsorship->sponsored_birth_date;
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
        // ✅ عرض الحالة الصحية للمكفول في field_health_status
        $values['field_health_status'] = $healthStatusValue;

        // ✅ الحالة الصحية للمعيل - من data.data_health_status
        if ($guardianData && $guardianData->data_health_status) {
            $guardianHealthStatus = DB::table('health_statuses')->where('id', $guardianData->data_health_status)->first();
            $values['field_guardian_health'] = $guardianHealthStatus->description ?? '';
            Log::info('GUARDIAN_HEALTH_LOADED', [
                'health_status_id' => $guardianData->data_health_status,
                'health_status_text' => $values['field_guardian_health']
            ]);
        }

        // 🆕 تحديد ما إذا كان الشخص متوفي
        $isDeceased = in_array($storedPersonType, ['deceased_father', 'deceased_mother']);

        // حالة السكن ونوع السكن
        if ($isDeceased) {
            // 🆕 للمتوفين: جلب بيانات السكن من portal_general_registration_field_values
            // ⚠️ نفس تسلسل المفتاح المستخدم عند الحفظ
            $fileIdForHousing = (string) ($sponsorship->relationData?->file_id_number
                ?: $sponsorship->relation_id_number
                ?: $sponsorship->internal_file_number
                ?: '');
            $housingFieldKeys = [
                'field_housing_status', 'field_housing_type', 'field_data_address',
                'field_data_province', 'field_data_city', 'field_data_neighborhood',
                'field_housing_address', 'field_housing_address_detail',
                'field_house_demolition', 'field_house_repair_need',
            ];

            $storedHousingValues = PortalGeneralRegistrationFieldValue::query()
                ->where(function ($query) use ($fileIdForHousing, $sponsorship) {
                    $query->where('sponsorship_id', $sponsorship->id);
                    if ($fileIdForHousing !== '') {
                        $query->orWhere('file_id_number', $fileIdForHousing);
                    }
                })
                ->orderByDesc('updated_at')
                ->whereIn('field_key', $housingFieldKeys)
                ->get(['field_key', 'field_value']);

            $storedHousingMap = [];
            foreach ($storedHousingValues as $housingRow) {
                if (!array_key_exists($housingRow->field_key, $storedHousingMap)) {
                    $storedHousingMap[$housingRow->field_key] = $housingRow->field_value;
                }
            }

            foreach ($housingFieldKeys as $fieldKey) {
                $values[$fieldKey] = $storedHousingMap[$fieldKey] ?? '';
            }

            Log::info('DECEASED_HOUSING_DATA_EXTRACTED', [
                'sponsorship_id' => $sponsorship->id,
                'person_type' => $storedPersonType,
                'file_id_number' => $fileIdForHousing,
                'housing_fields_count' => count($storedHousingValues),
            ]);
        } elseif ($guardianData) {
            // للمعيل وفرد العائلة: جلب من جدول data
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

        // === بيانات المعيل التفصيلية ===
        // نستخدم $guardianData إذا من جدول data، أو $guardianCivilRegistryData إذا من السجل المدني
        // أو نرجع لـ relationData كخيار أخير

        if ($guardianDataSource === 'data' && $guardianData) {
            // المعيل موجود في جدول data
            $values['field_data_id_number'] = $guardianData->data_id_number;
            $values['field_data_first_name'] = $guardianData->data_first_name;
            $values['field_data_father_name'] = $guardianData->data_father_name;
            $values['field_data_grand_father_name'] = $guardianData->data_grand_father_name;
            $values['field_data_family_name'] = $guardianData->data_family_name;
            $values['field_data_birth_date'] = $guardianData->data_birth_date;
            $values['field_data_phone_number'] = $guardianData->data_phone_number;
            $values['field_data_address'] = $guardianData->data_current_address ?? '';

            // 🆕 جلب حقل السكن من جدول data
            $values['field_housing_address_detail'] = $guardianData->data_current_address ?? '';

            // 🆕 جلب حقول الوظيفة والمعالين من data بالأسماء الصحيحة
            // field_guardian_job -> data_employment_status_breadwinner (ID)
            if (isset($guardianData->data_employment_status_breadwinner) && $guardianData->data_employment_status_breadwinner) {
                $employment = DB::table('employment')->where('id', $guardianData->data_employment_status_breadwinner)->first();
                $values['field_guardian_job'] = $employment->description ?? '';
            }
            // field_dependents_female -> data_number_female
            $values['field_dependents_female'] = $guardianData->data_number_female ?? '';
            // field_dependents_male -> data_number_mail
            $values['field_dependents_male'] = $guardianData->data_number_mail ?? '';

            // جلب المحافظة والمدينة
            $province = DB::table('provinces')->where('id', $guardianData->data_province)->first();
            $city = DB::table('city')->where('id', $guardianData->data_city)->first();
            $values['field_data_province'] = $province->description ?? '';
            $values['field_data_city'] = $city->city ?? '';

        } elseif ($guardianDataSource === 'civil_registry' && $guardianCivilRegistryData) {
            // المعيل موجود في السجل المدني
            $values['field_data_id_number'] = $guardianIdentityNumber;
            $values['field_data_first_name'] = $guardianCivilRegistryData['first_name'] ?? '';
            $values['field_data_father_name'] = $guardianCivilRegistryData['second_name'] ?? '';
            $values['field_data_grand_father_name'] = $guardianCivilRegistryData['third_name'] ?? '';
            $values['field_data_family_name'] = $guardianCivilRegistryData['last_name'] ?? '';
            $values['field_data_birth_date'] = $guardianCivilRegistryData['birth_date'] ?? '';
            $values['field_data_phone_number'] = ''; // غير متوفر في السجل المدني
            $values['field_data_address'] = ''; // غير متوفر في السجل المدني
            $values['field_data_province'] = '';
            $values['field_data_city'] = '';

        } elseif ($sponsorship->relationData) {
            // خيار بديل: استخدام relationData
            $data = $sponsorship->relationData;
            $values['field_data_id_number'] = $data->data_id_number;
            $values['field_data_first_name'] = $data->data_first_name;
            $values['field_data_father_name'] = $data->data_father_name;
            $values['field_data_grand_father_name'] = $data->data_grand_father_name;
            $values['field_data_family_name'] = $data->data_family_name;
            $values['field_data_birth_date'] = $data->data_birth_date;
            $values['field_data_phone_number'] = $data->data_phone_number;
            $values['field_data_address'] = $data->data_current_address ?? '';

            // 🆕 جلب حقل السكن من جدول data
            $values['field_housing_address_detail'] = $data->data_current_address ?? '';

            // 🆕 جلب حقول الوظيفة والمعالين من data بالأسماء الصحيحة
            // field_guardian_job -> data_employment_status_breadwinner (ID)
            if (isset($data->data_employment_status_breadwinner) && $data->data_employment_status_breadwinner) {
                $employment = DB::table('employment')->where('id', $data->data_employment_status_breadwinner)->first();
                $values['field_guardian_job'] = $employment->description ?? '';
            }
            // field_dependents_female -> data_number_female
            $values['field_dependents_female'] = $data->data_number_female ?? '';
            // field_dependents_male -> data_number_mail
            $values['field_dependents_male'] = $data->data_number_mail ?? '';

            // جلب المحافظة والمدينة
            $values['field_data_province'] = optional($data->province)->description ?? '';
            $values['field_data_city'] = optional($data->city)->city ?? $data->data_city;

            // Dead people info
            if ($data->deadPepole) {
                $dead = $data->deadPepole;
                // حقول الأب المتوفى - 4 أعمدة منفصلة
                $values['field_father_first_name'] = $dead->father_first_name ?? '';
                $values['field_father_second_name'] = $dead->father_second_name ?? '';
                $values['field_father_third_name'] = $dead->father_third_name ?? '';
                $values['field_father_last_name'] = $dead->father_last_name ?? '';
                $values['field_father_id'] = $dead->father_id;
                $values['field_father_death_date'] = $dead->father_death_date;

                // جلب سبب وفاة الأب
                if ($dead->father_death_reason) {
                    $fatherDeathReason = DB::table('death_reasons')
                        ->where('id', $dead->father_death_reason)
                        ->first();
                    $values['field_father_death_reason'] = $fatherDeathReason ? $fatherDeathReason->description : '';
                }

                // حقول الأم - 4 أعمدة منفصلة
                $values['field_mother_first_name'] = $dead->mother_first_name ?? '';
                $values['field_mother_second_name'] = $dead->mother_second_name ?? '';
                $values['field_mother_third_name'] = $dead->mother_third_name ?? '';
                $values['field_mother_last_name'] = $dead->mother_last_name ?? '';
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
                // حقول اليتيم/المكفول - 4 أعمدة منفصلة
                $values['field_orphan_first_name'] = $re->first_name ?? '';
                $values['field_orphan_second_name'] = $re->second_name ?? '';
                $values['field_orphan_third_name'] = $re->third_name ?? '';
                $values['field_orphan_last_name'] = $re->last_name ?? '';
                $values['field_person_id'] = $re->person_id;
                $values['field_person_birth_date'] = $re->person_birth_date;
                $values['field_person_age'] = $re->person_age;
                $values['field_person_gender'] = $re->person_gender == 1 ? 'ذكر' : ($re->person_gender == 2 ? 'أنثى' : '');
                $values['field_person_note'] = $re->person_note;
            }
        }

        // ============================================
        // 🆕 جلب بيانات المتوفين مباشرة من dead_people باستخدام relation_id_number
        // هذا يضمن جلب البيانات الصحيحة حتى لو لم تكن العلاقات محملة
        // ============================================
        if ($sponsorship->relation_id_number && !isset($values['field_father_first_name'])) {
            $deadPeopleRecord = DB::table('dead_people')
                ->where('re_file_id', $sponsorship->relation_id_number)
                ->first();

            if ($deadPeopleRecord) {
                Log::info('DEAD_PEOPLE_LOADED_DIRECTLY', [
                    'sponsorship_id' => $sponsorship->id,
                    'relation_id_number' => $sponsorship->relation_id_number,
                    'father_name' => $deadPeopleRecord->father_first_name
                ]);

                // حقول الأب المتوفى - 4 أعمدة منفصلة
                $values['field_father_first_name'] = $deadPeopleRecord->father_first_name ?? '';
                $values['field_father_second_name'] = $deadPeopleRecord->father_second_name ?? '';
                $values['field_father_third_name'] = $deadPeopleRecord->father_third_name ?? '';
                $values['field_father_last_name'] = $deadPeopleRecord->father_last_name ?? '';
                $values['field_father_id'] = $deadPeopleRecord->father_id ?? '';
                $values['field_father_death_date'] = $deadPeopleRecord->father_death_date ?? '';

                // جلب سبب وفاة الأب
                if ($deadPeopleRecord->father_death_reason) {
                    $fatherDeathReason = DB::table('death_reasons')
                        ->where('id', $deadPeopleRecord->father_death_reason)
                        ->first();
                    $values['field_father_death_reason'] = $fatherDeathReason ? $fatherDeathReason->description : '';
                }

                // حقول الأم المتوفية - 4 أعمدة منفصلة
                $values['field_mother_first_name'] = $deadPeopleRecord->mother_first_name ?? '';
                $values['field_mother_second_name'] = $deadPeopleRecord->mother_second_name ?? '';
                $values['field_mother_third_name'] = $deadPeopleRecord->mother_third_name ?? '';
                $values['field_mother_last_name'] = $deadPeopleRecord->mother_last_name ?? '';
                $values['field_mother_id'] = $deadPeopleRecord->mother_id ?? '';
                $values['field_mother_death_date'] = $deadPeopleRecord->mother_death_date ?? '';

                // جلب سبب وفاة الأم
                if ($deadPeopleRecord->mother_death_reason) {
                    $motherDeathReason = DB::table('death_reasons')
                        ->where('id', $deadPeopleRecord->mother_death_reason)
                        ->first();
                    $values['field_mother_death_reason'] = $motherDeathReason ? $motherDeathReason->description : '';
                }
            }
        }

        // إضافة بيانات المكفول من 4 أعمدة منفصلة بناءً على نوع الشخص
        if ($personType == 're_people' && $rePerson) {
            $values['field_sponsored_first_name'] = $rePerson->first_name ?? '';
            $values['field_sponsored_second_name'] = $rePerson->second_name ?? '';
            $values['field_sponsored_third_name'] = $rePerson->third_name ?? '';
            $values['field_sponsored_last_name'] = $rePerson->last_name ?? '';
        } elseif ($personType == 'data' && $dataByIdentity) {
            $values['field_sponsored_first_name'] = $dataByIdentity->data_first_name ?? '';
            $values['field_sponsored_second_name'] = $dataByIdentity->data_father_name ?? '';
            $values['field_sponsored_third_name'] = $dataByIdentity->data_grand_father_name ?? '';
            $values['field_sponsored_last_name'] = $dataByIdentity->data_family_name ?? '';
        } elseif ($personType == 'dead_people' && $deadPerson) {
            if ($deadPerson->father_id == $identityNumber) {
                $values['field_sponsored_first_name'] = $deadPerson->father_first_name ?? '';
                $values['field_sponsored_second_name'] = $deadPerson->father_second_name ?? '';
                $values['field_sponsored_third_name'] = $deadPerson->father_third_name ?? '';
                $values['field_sponsored_last_name'] = $deadPerson->father_last_name ?? '';
            } else {
                $values['field_sponsored_first_name'] = $deadPerson->mother_first_name ?? '';
                $values['field_sponsored_second_name'] = $deadPerson->mother_second_name ?? '';
                $values['field_sponsored_third_name'] = $deadPerson->mother_third_name ?? '';
                $values['field_sponsored_last_name'] = $deadPerson->mother_last_name ?? '';
            }
        } elseif ($personType == 'civil_registry' && $civilRegistryData) {
            // بيانات المكفول من السجل المدني
            $values['field_sponsored_first_name'] = $civilRegistryData['first_name'] ?? '';
            $values['field_sponsored_second_name'] = $civilRegistryData['second_name'] ?? '';
            $values['field_sponsored_third_name'] = $civilRegistryData['third_name'] ?? '';
            $values['field_sponsored_last_name'] = $civilRegistryData['last_name'] ?? '';
            $values['field_person_birth_date'] = $civilRegistryData['birth_date'] ?? '';
            $values['field_person_gender'] = $civilRegistryData['gender'] ?? '';
            $values['field_data_city'] = $civilRegistryData['city'] ?? '';
        }

        // بيانات المعيل من السجل المدني إذا لم توجد في data
        if (!$guardianData && $guardianCivilRegistryData) {
            $values['field_guardian_first_name'] = $guardianCivilRegistryData['first_name'] ?? '';
            $values['field_guardian_second_name'] = $guardianCivilRegistryData['second_name'] ?? '';
            $values['field_guardian_third_name'] = $guardianCivilRegistryData['third_name'] ?? '';
            $values['field_guardian_last_name'] = $guardianCivilRegistryData['last_name'] ?? '';
            // بناء الاسم الكامل للمعيل
            $fullGuardianName = trim("{$guardianCivilRegistryData['first_name']} {$guardianCivilRegistryData['second_name']} {$guardianCivilRegistryData['third_name']} {$guardianCivilRegistryData['last_name']}");
            $values['field_guardian_name'] = $fullGuardianName;
        }

        // استخراج صلة القرابة وتحديد ما إذا يجب عرض حقول الأم المتوفية
        $guardianRelationship = null;
        $guardianRelationshipText = '';
        $showMotherDeathFields = true; // افتراضياً نعرض حقول الأم المتوفية

        if ($guardianData && isset($guardianData->data_relationship)) {
            $guardianRelationship = $guardianData->data_relationship;
            // جلب نص صلة القرابة
            $relationCategory = DB::table('category_of_relations')
                ->where('id', $guardianRelationship)
                ->first();
            $guardianRelationshipText = $relationCategory ? $relationCategory->attribute : '';

            // إذا كانت صلة القرابة "أم" (id = 2 أو attribute = 'أم')
            // لا نعرض حقول الأم المتوفية لأن المعيلة هي الأم نفسها (الأم حية)
            if ($guardianRelationship == 2 || $guardianRelationshipText === 'أم') {
                $showMotherDeathFields = false;
            }
        }

        // إضافة قيم صلة القرابة للحقول
        $values['field_data_relationship'] = $guardianRelationship;
        $values['show_mother_death_fields'] = $showMotherDeathFields;

        // 🆕 معالجة ذكية لمعلومات الأم (حية أو متوفية)
        $guardianIsMother = ($guardianRelationship == 1 || $guardianRelationshipText === 'أم' || $guardianRelationshipText === 'الأم');
        $showMotherSection = true; // افتراضياً نعرض قسم الأم
        $showLivingMotherFields = false; // افتراضياً لا نعرض حقول الأم الحية
        $motherIsGuardian = false;

        if ($guardianIsMother) {
            // المعيل هو الأم - استخدام بيانات المعيل
            $motherIsGuardian = true;
            $showMotherSection = false; // لا نعرض قسم منفصل للأم
            $values['field_mother_status'] = 'حية';

            // نسخ بيانات المعيل كبيانات للأم
            $values['field_living_mother_first_name'] = $guardianData->data_first_name ?? '';
            $values['field_living_mother_second_name'] = $guardianData->data_father_name ?? '';
            $values['field_living_mother_third_name'] = $guardianData->data_grand_father_name ?? '';
            $values['field_living_mother_last_name'] = $guardianData->data_family_name ?? '';
            $values['field_living_mother_id'] = $guardianData->data_id_number ?? '';
        } else {
            // المعيل ليس الأم - فحص حالة الأم من البيانات المحفوظة
            $motherStatus = $values['field_mother_status'] ?? ''; // فارغة افتراضياً
            $values['field_mother_status'] = $motherStatus;

            if ($motherStatus === 'حية') {
                $showLivingMotherFields = true;
                $showMotherDeathFields = false;
            } elseif ($motherStatus === 'متوفية') {
                $showLivingMotherFields = false;
                $showMotherDeathFields = true;
            } else {
                // لم يتم الاختيار بعد - عرض جميع الحقول
                $showLivingMotherFields = true;
                $showMotherDeathFields = true;
            }
        }

        $values['_mother_is_guardian'] = $motherIsGuardian;
        $values['_show_mother_section'] = $showMotherSection;
        $values['_show_living_mother_fields'] = $showLivingMotherFields;

        Log::info('MOTHER STATUS', [
            'guardian_is_mother' => $guardianIsMother,
            'mother_is_guardian' => $motherIsGuardian,
            'show_mother_section' => $showMotherSection,
            'show_living_mother_fields' => $showLivingMotherFields,
            'show_mother_death_fields' => $showMotherDeathFields,
            'mother_status' => $values['field_mother_status'] ?? 'غير محدد'
        ]);

        Log::info('GUARDIAN RELATIONSHIP', [
            'relationship_id' => $guardianRelationship,
            'relationship_text' => $guardianRelationshipText,
            'show_mother_death_fields' => $showMotherDeathFields
        ]);

        // 🆕 جلب الحساب البنكي المعتمد من guardian_bank_accounts - لجميع أنواع الأشخاص
        $bankAccountFileNumber = null;

        // 1. للمعيل أو فرد العائلة: استخدام file_id_number من guardianData
        if ($guardianData && $guardianData->file_id_number) {
            $bankAccountFileNumber = $guardianData->file_id_number;
        }
        // 2. للمتوفين: استخدام re_file_id من dead_people
        elseif (in_array($storedPersonType, ['deceased_father', 'deceased_mother'])) {
            if (isset($deadPerson) && $deadPerson && isset($deadPerson->re_file_id)) {
                $bankAccountFileNumber = $deadPerson->re_file_id;
            } elseif ($sponsorship->relation_id_number) {
                $bankAccountFileNumber = $sponsorship->relation_id_number;
            }
        }
        // 3. fallback: استخدام relation_id_number
        if (!$bankAccountFileNumber && $sponsorship->relation_id_number) {
            $bankAccountFileNumber = $sponsorship->relation_id_number;
        }

        Log::info('BANK_ACCOUNT_LOOKUP', [
            'sponsorship_id' => $sponsorship->id,
            'person_type' => $storedPersonType,
            'bank_account_file_number' => $bankAccountFileNumber
        ]);

        if ($bankAccountFileNumber) {
            $approvedBankAccount = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $bankAccountFileNumber)
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
                    'iban_usd' => $approvedBankAccount->iban_usd,
                    'person_type' => $storedPersonType
                ]);
            } else {
                Log::warning('NO APPROVED BANK ACCOUNT FOUND', [
                    'file_number' => $bankAccountFileNumber,
                    'person_type' => $storedPersonType
                ]);
            }
        }

        Log::info('FIELD VALUES EXTRACTED', [
            'total_values' => count($values),
            'has_bank_account' => isset($approvedBankAccount)
        ]);

        // تحميل قيم الحقول العامة من جدول مخصص للبوابة (Key/Value)
        // ⚠️ يجب أن يطابق هذا المفتاح تماماً المفتاح المستخدم عند الحفظ داخل updateSponsorshipData()
        try {
            $fileIdNumber = (string) ($sponsorship->relationData?->file_id_number
                ?: $sponsorship->relation_id_number
                ?: $sponsorship->internal_file_number
                ?: '');

            $portalRows = PortalGeneralRegistrationFieldValue::query()
                ->where(function ($query) use ($fileIdNumber, $sponsorship) {
                    $query->where('sponsorship_id', $sponsorship->id);
                    if ($fileIdNumber !== '') {
                        $query->orWhere('file_id_number', $fileIdNumber);
                    }
                })
                ->orderByDesc('updated_at')
                ->get(['field_key', 'field_value']);

            $stored = [];
            foreach ($portalRows as $portalRow) {
                if (!array_key_exists($portalRow->field_key, $stored)) {
                    $stored[$portalRow->field_key] = $portalRow->field_value;
                }
            }

            foreach ($stored as $k => $v) {
                // لا نكسر القيم المحسوبة إن كانت موجودة، لكن نملأ القيم التي لا نعرف مصدرها
                if (!array_key_exists($k, $values) || $values[$k] === null || $values[$k] === '') {
                    $values[$k] = $v;
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

            // ✅ التحقق من ملكية الكفالة قبل أي فحص آخر (ownership / authorization)
            // المستخدم → الكفالة النشطة في الجلسة → مطابقة sponsorship_id المطلوب
            $sponsorship = $this->authorizeSponsorshipForUpdate($sponsorshipId, $user);

            if ($fieldsIncomingCount === 0) {
                // 🆕 البند 13: لا نجعل غياب الحقول خطأً عالمياً إذا كانت الجمعية
                // لا تدير أي حقل بيانات أصلاً (عندها لا يرسل النموذج fields[] إطلاقاً)
                if ($this->sponsorHasEnabledFields($sponsorship)) {
                    Log::error('UPDATE_SPONSORSHIP_NO_FIELDS_RECEIVED', [
                        'user_id' => $user?->id,
                        'sponsorship_id' => $sponsorshipId,
                        'note' => 'لم تصل أي حقول ضمن fields[]. تحقق من name="fields[...]" داخل form ومن عدم وجود عناصر disabled/عدم وجود JS يمنع الإرسال.',
                    ]);

                    // نوقف العملية برسالة واضحة بدل المتابعة والنجاح الصامت دون حفظ أي بيانات
                    throw new \Exception('لم يتم استقبال أي حقول بيانات من النموذج (fields[])، لذا لم يتم الحفظ. يرجى إعادة تحميل الصفحة وحاول مرة أخرى.');
                }

                Log::warning('UPDATE_SPONSORSHIP_NO_FIELDS_RECEIVED_SPONSOR_HAS_NONE', [
                    'user_id' => $user?->id,
                    'sponsorship_id' => $sponsorshipId,
                    'sponsor_id' => $sponsorship->sponsor_id,
                    'fields_count' => $fieldsIncomingCount,
                ]);
            }

            // إذا تم اختيار ملفات ولكن لم يصل أي ملف صالح، نوقف العملية برسالة واضحة
            if ($attachmentsTotal > 0 && $attachmentsValidTotal === 0) {
                throw new \Exception('تم اختيار ملفات ولكن لم تصل للسيرفر كملفات صالحة (قد تكون أكبر من upload_max_filesize/post_max_size أو حدث خطأ أثناء الرفع).');
            }

            // التحقق من البيانات
            $request->validate([
                'fields' => 'array',
                'family_members' => 'array',
                'attachments' => 'sometimes|array',
                'attachments.*' => 'sometimes|array',
                'attachments.*.*' => 'file|mimes:pdf,jpg,jpeg,png,gif,webp,heic,heif,mp4,avi,mov,wmv,webm|max:51200', // 50MB
            ]);

            // 🆕 التحقق من رفع ملف واحد على الأقل لكل حقل مرفق ظاهر في الصفحة
            $missingRequiredDocs = $this->validateRequiredAttachments($sponsorship, $validAttachments);
            if (!empty($missingRequiredDocs)) {
                $missingDocsMessage = 'يجب رفع ملف واحد على الأقل لكل نوع وثيقة. الوثائق الناقصة: ' . implode('، ', $missingRequiredDocs);

                // 🆕 البند 10: النموذج يُرسل كصفحة عادية (form.submit) وليس كطلب JSON،
                // لذا نعيد التوجيه برسالة عربية مع الحفاظ على المدخلات بدل عرض JSON خام
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $missingDocsMessage,
                        'missing_documents' => $missingRequiredDocs,
                    ], 422);
                }

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $missingDocsMessage);
            }

            DB::beginTransaction();

            // 🆕 التحقق من وجود بيانات مركزية - إذا لم تكن موجودة، نُنشئها
            $namesData = $request->input('names', []);
            $fieldsData = $request->input('fields', []);
            $personType = $sponsorship->person_type;

            // ================================================
            // 🆕 البحث عن المعيل في قاعدة البيانات أولاً
            // عند العثور على ملف للمعيل يتم إعادة ربط كل السجلات
            // التابعة بالكفالة (الأفراد، المتوفون، الكفالات، البنك، البوابة)
            // برقم ملف المعيل الموجود - وبصمت دون إخطار المستخدم
            // ================================================
            $isDeceasedPerson = in_array($personType, ['deceased_father', 'deceased_mother']);

            // هوية المعيل المأخوذة من النموذج (تُستخدم للربط وللإنشاء معاً)
            $guardianIdentityForLink = trim((string) ($fieldsData['field_data_id_number'] ?? ''));
            $existingGuardianFile = null;

            if (!$isDeceasedPerson) {
                if ($guardianIdentityForLink === '') {
                    $guardianIdentityForLink = trim((string) ($sponsorship->guardian_identity_number ?? ''));
                }

                if ($guardianIdentityForLink === '' && $personType === 'breadwinner') {
                    $guardianIdentityForLink = trim((string) ($sponsorship->identity_number ?? ''));
                }

                if ($guardianIdentityForLink !== '') {
                    $guardianFileService = app(GuardianFileService::class);
                    $existingGuardianFile = $guardianFileService->findFileByIdentity($guardianIdentityForLink);

                    if ($existingGuardianFile) {
                        $guardianFileService->relinkSponsorship(
                            $sponsorship,
                            $existingGuardianFile,
                            $guardianIdentityForLink
                        );

                        // إعادة تحميل الكفالة وعلاقاتها بعد إعادة الربط
                        $sponsorship->refresh();
                        $sponsorship->load(['relationData', 'sponsor']);
                    } else {
                        Log::info('GUARDIAN_NOT_FOUND_IN_DATA', [
                            'sponsorship_id' => $sponsorship->id,
                            'guardian_identity' => $guardianIdentityForLink,
                            'note' => 'لا يوجد ملف سابق للمعيل - يتم إنشاء سجل جديد كالمعتاد',
                        ]);
                    }
                }
            }

            $needsCentralDataCreation = !$sponsorship->relation_id_number;

            // 🆕 التحقق بناءً على نوع الشخص
            if (!$needsCentralDataCreation) {
                if (in_array($personType, ['deceased_father', 'deceased_mother'])) {
                    // للمتوفين: التحقق من وجود سجل في dead_people بناءً على رقم الهوية
                    $existingDeadPeople = DB::table('dead_people')
                        ->where(function($query) use ($sponsorship) {
                            $query->where('father_id', $sponsorship->identity_number)
                                  ->orWhere('mother_id', $sponsorship->identity_number);
                        })
                        ->first();

                    if (!$existingDeadPeople) {
                        // أيضاً التحقق بواسطة re_file_id
                        $existingDeadPeople = DB::table('dead_people')
                            ->where('re_file_id', $sponsorship->relation_id_number)
                            ->first();
                    }

                    if (!$existingDeadPeople) {
                        $needsCentralDataCreation = true;
                        Log::info('DECEASED_NEEDS_CREATION', [
                            'sponsorship_id' => $sponsorship->id,
                            'identity_number' => $sponsorship->identity_number,
                            'person_type' => $personType,
                            'reason' => 'لا يوجد سجل في dead_people لهذا الشخص'
                        ]);
                    }
                } else {
                    // للمعيل أو فرد العائلة: التحقق من وجود سجل في جدول data
                    $existingData = DB::table('data')
                        ->where('file_id_number', $sponsorship->relation_id_number)
                        ->first();
                    if (!$existingData) {
                        $needsCentralDataCreation = true;
                    }

                    // 🆕 لفرد العائلة: يجب أن يوجد أيضاً سجله في re_people
                    // (ملف المعيل قد يكون موجوداً بينما فرد العائلة لم يُنشأ بعد)
                    if (!$needsCentralDataCreation && $personType === 'family_member') {
                        $existingRePeople = DB::table('re_people')
                            ->where('person_id', $sponsorship->identity_number)
                            ->where('registration_id', $sponsorship->relation_id_number)
                            ->exists();

                        if (!$existingRePeople) {
                            $needsCentralDataCreation = true;
                            Log::info('FAMILY_MEMBER_RE_PEOPLE_MISSING', [
                                'sponsorship_id' => $sponsorship->id,
                                'identity_number' => $sponsorship->identity_number,
                                'relation_id_number' => $sponsorship->relation_id_number,
                            ]);
                        }
                    }
                }
            }

            if ($needsCentralDataCreation && (!empty($namesData) || !empty($fieldsData))) {
                Log::info('CENTRAL_DATA_CREATION_NEEDED', [
                    'sponsorship_id' => $sponsorship->id,
                    'identity_number' => $sponsorship->identity_number,
                    'current_relation_id_number' => $sponsorship->relation_id_number,
                ]);

                // إنشاء السجلات المركزية
                // تُمرَّر هوية المعيل وملفه الموجود لمنع إنشاء ملف مكرر للمعيل
                $newFileNumber = $this->createCentralDataRecords(
                    $sponsorship,
                    $namesData,
                    $fieldsData,
                    $existingGuardianFile,
                    $guardianIdentityForLink
                );

                // إعادة تحميل الكفالة مع العلاقات الجديدة
                $sponsorship->refresh();
                $sponsorship->load(['relationData', 'sponsor']);

                Log::info('CENTRAL_DATA_CREATED', [
                    'sponsorship_id' => $sponsorship->id,
                    'new_file_number' => $newFileNumber,
                    'relation_id_number' => $sponsorship->relation_id_number,
                ]);
            } else {
                // معالجة حقول الأسماء المنفصلة (4 حقول لكل اسم) - فقط إذا كانت البيانات المركزية موجودة
                if (!empty($namesData) || !empty($fieldsData)) {
                    $this->updateSeparateNameFields($sponsorship, $namesData, $fieldsData);
                }
            }

            // كاش لأعمدة جدول data لتجنب Schema::hasColumn داخل loop (أسرع بكثير)
            $dataColumnMap = [];
            try {
                $dataColumnMap = array_fill_keys(Schema::getColumnListing('data'), true);
            } catch (\Throwable $e) {
                $dataColumnMap = [];
            }

            // تحديث الحقول الأساسية في جدول sponsorships
            // ملاحظة: guardian_relationship غير موجود في جدول sponsorships - يتم تخزينه في portal_general_registration_field_values
            // ⚠️ يجب ألا يحتوي هذا القائمة إلا على أعمدة موجودة فعلاً في جدول sponsorships
            //    (أي عمود غير موجود هنا يسبب خطأ SQL عند save() ويرجّع المعاملة كاملة)
            $sponsorshipFields = ['orphan_name', 'identity_number', 'internal_file_number', 'guardian_name'];

            // $fieldsData تم تعريفه مسبقاً

            // ⚠️ أصبحت جميع الحقول الواردة تُحفظ في portal_general_registration_field_values
            // بدون أي استثناء: portal هو ضمانة ألّا يضيع أي حقل يصل من الفورم،
            // بالإضافة إلى الحفظ في جداولها المخصصة (data / re_people / dead_people / bank).
            // الحقول المحفوظة في جداول مخصصة تظل تُقرأ من هناك أولاً عند العرض،
            // وportal يُستخدم كبديل عندما لا تتوفر قيمة في المصدر.
            $fileIdNumberForPortal = (string) ($sponsorship->relationData?->file_id_number ?: $sponsorship->relation_id_number ?: $sponsorship->internal_file_number ?: '');
            $identityForPortal = (string) ($sponsorship->identity_number ?: '');
            $storedCount = 0;

            if (!is_array($fieldsData) || count($fieldsData) === 0) {
                throw new \Exception('حقل fields[] فارغ أو غير مصفوفة، تم إيقاف الحفظ.');
            }

            if ($fileIdNumberForPortal === '') {
                Log::error('PORTAL_FIELDS_NO_FILE_NUMBER', [
                    'sponsorship_id' => $sponsorship->id,
                    'relation_id_number' => $sponsorship->relation_id_number,
                    'internal_file_number' => $sponsorship->internal_file_number,
                ]);
                throw new \Exception('تعذّر تحديد رقم ملف الكفالة لحفظ بيانات البوابة، تم إيقاف الحفظ.');
            }

            foreach ($fieldsData as $fieldKey => $fieldValue) {
                // 🆕 تحديد identity_number بناءً على نوع الحقل
                $targetIdentityNumber = $identityForPortal;

                // حقول الأم الحية - استخدام رقم هوية الأم
                $livingMotherFields = [
                    'field_living_mother_first_name',
                    'field_living_mother_second_name',
                    'field_living_mother_third_name',
                    'field_living_mother_last_name',
                    'field_living_mother_id',
                ];

                if (in_array($fieldKey, $livingMotherFields)) {
                    // استخدام رقم هوية الأم من field_living_mother_id
                    $motherIdNumber = $fieldsData['field_living_mother_id'] ?? '';
                    if (!empty($motherIdNumber)) {
                        $targetIdentityNumber = (string) $motherIdNumber;
                    }
                }

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
                        'identity_number' => $targetIdentityNumber,
                        'field_value' => $fieldValue,
                        'updated_by_user_id' => $user?->id,
                    ]
                );

                $storedCount++;
            }

            Log::info('PORTAL_FIELDS_STORED', [
                'sponsorship_id' => $sponsorship->id,
                'file_id_number' => $fileIdNumberForPortal,
                'stored_count' => $storedCount,
                'fields_received_count' => count($fieldsData),
            ]);

            // 🛡️ ضمانة إضافية: لو لم يُحفظ أي حقل فلا يجوز المتابعة للنجاح
            if ($storedCount === 0) {
                throw new \Exception('لم يتم حفظ أي حقل في portal_general_registration_field_values، تم إيقاف الحفظ.');
            }

            // حقول البنك الجديدة
            $bankFields = [];
            // 🆕 حقول بيانات المتوفين (الأب والأم) - يتم حفظها مباشرة في dead_people
            $deadPeopleFields = [];
            // 🆕 حقول السكن للمتوفين (تُخزن في portal_general_registration_field_values)
            $housingFieldsForDeceased = [];

            // تجميع الحقول التي لم نستطع ربطها بعمود في DB
            $unmappedFieldKeys = [];
            $mappedToSponsorship = [];
            $mappedToData = [];
            $mappedToRePeople = [];

            // 🔒 مفاتيح لا يجوز تفريغها مهما كانت قيمتها الفارغة (مفاتيح بحث/ربط أساسية)
            $neverClearKeys = [
                'field_identity_number',
                'field_sponsor_name',
                'field_data_id_number',
                'field_internal_file_number',
                'field_external_file_number',
            ];

            // 🧹 قرار القيمة: null = تفريغ العمود فعلاً، $SKIP = لا تغيير
            $SKIP = '__SKIP__';
            $fieldOrClear = function ($value, string $key) use ($neverClearKeys, $SKIP) {
                if ($value === null) {
                    return $SKIP;
                }
                if ($value === '' || $value === []) {
                    return in_array($key, $neverClearKeys, true) ? $SKIP : null;
                }
                return $value;
            };

            // 🆕 تحديد ما إذا كان الشخص متوفي
            $isDeceased = in_array($sponsorship->person_type, ['deceased_father', 'deceased_mother']);

            // 🆕 قائمة حقول السكن
            $housingFieldKeys = [
                'field_housing_status', 'field_housing_type', 'field_data_address',
                'field_data_province', 'field_data_city', 'field_data_neighborhood',
                'field_housing_address', 'field_housing_address_detail',
                'field_house_demolition', 'field_house_repair_need',
            ];

            foreach ($fieldsData as $fieldKey => $fieldValue) {
                $cleanFieldKey = str_replace('field_', '', $fieldKey);

                // 🆕 للمتوفين: تخزين حقول السكن في portal_general_registration_field_values
                if ($isDeceased && in_array($fieldKey, $housingFieldKeys)) {
                    if ($fieldValue !== null) {
                        $housingFieldsForDeceased[$fieldKey] = $fieldValue;
                    }
                    continue;
                }

                // 🆕 معالجة field_guardian_health -> data_health_status
                if ($fieldKey === 'field_guardian_health' && $sponsorship->relationData) {
                    $healthValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($healthValue !== $SKIP) {
                        if ($healthValue !== null) {
                            $resolvedHealthId = $this->resolveLookupIdByDescription('health_statuses', (string) $healthValue);
                            if ($resolvedHealthId === null) {
                                Log::warning('LOOKUP_ID_NOT_FOUND', [
                                    'field' => 'field_guardian_health',
                                    'value' => (string) $healthValue,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                                $healthValue = $SKIP;
                            } else {
                                $healthValue = $resolvedHealthId;
                            }
                        }
                        if ($healthValue !== $SKIP) {
                            $sponsorship->relationData->data_health_status = $healthValue;
                            $mappedToData[] = $fieldKey;
                        }
                    }
                    continue;
                }

                // 🆕 معالجة field_data_relationship -> data_relationship
                if ($fieldKey === 'field_data_relationship' && $sponsorship->relationData) {
                    $relationshipValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($relationshipValue !== $SKIP) {
                        if ($relationshipValue === null) {
                            $sponsorship->relationData->data_relationship = null;
                            $mappedToData[] = $fieldKey;
                        } elseif (is_numeric($relationshipValue)) {
                            $sponsorship->relationData->data_relationship = (int) $relationshipValue;
                            $mappedToData[] = $fieldKey;
                        } else {
                            $relationId = DB::table('category_of_relations')->where('attribute', $relationshipValue)->value('id');
                            if ($relationId) {
                                $sponsorship->relationData->data_relationship = (int) $relationId;
                                $mappedToData[] = $fieldKey;
                            } else {
                                Log::warning('LOOKUP_ID_NOT_FOUND', [
                                    'field' => 'field_data_relationship',
                                    'value' => (string) $relationshipValue,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                            }
                        }
                    }
                    continue;
                }

                // 🆕 معالجة field_guardian_job -> data_employment_status_breadwinner
                if ($fieldKey === 'field_guardian_job' && $sponsorship->relationData) {
                    $employmentValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($employmentValue !== $SKIP) {
                        if ($employmentValue === null) {
                            $sponsorship->relationData->data_employment_status_breadwinner = null;
                            $mappedToData[] = $fieldKey;
                        } elseif (is_numeric($employmentValue)) {
                            $sponsorship->relationData->data_employment_status_breadwinner = (int) $employmentValue;
                            $mappedToData[] = $fieldKey;
                        } else {
                            $employmentId = DB::table('employment')->where('description', $employmentValue)->value('id');
                            if ($employmentId) {
                                $sponsorship->relationData->data_employment_status_breadwinner = (int) $employmentId;
                                $mappedToData[] = $fieldKey;
                            } else {
                                Log::warning('LOOKUP_ID_NOT_FOUND', [
                                    'field' => 'field_guardian_job',
                                    'value' => (string) $employmentValue,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                            }
                        }
                    }
                    continue;
                }

                // 🆕 معالجة field_dependents_female -> data_number_female
                if ($fieldKey === 'field_dependents_female' && $sponsorship->relationData) {
                    $femaleValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($femaleValue !== $SKIP) {
                        $sponsorship->relationData->data_number_female = $femaleValue === null ? null : (int) $femaleValue;
                        $mappedToData[] = $fieldKey;
                    }
                    continue;
                }

                // 🆕 معالجة field_dependents_male -> data_number_mail
                if ($fieldKey === 'field_dependents_male' && $sponsorship->relationData) {
                    $maleValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($maleValue !== $SKIP) {
                        $sponsorship->relationData->data_number_mail = $maleValue === null ? null : (int) $maleValue;
                        $mappedToData[] = $fieldKey;
                    }
                    continue;
                }

                // 🆕 معالجة field_housing_address_detail -> data_current_address
                if ($fieldKey === 'field_housing_address_detail' && $sponsorship->relationData) {
                    $addressValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($addressValue !== $SKIP) {
                        $sponsorship->relationData->data_current_address = $addressValue;
                        $mappedToData[] = $fieldKey;
                    }
                    continue;
                }

                // 🆕 معالجة الحقول الأساسية في جدول data
                if ($sponsorship->relationData) {
                    $basicFieldsMapping = [
                        'field_data_id_number' => 'data_id_number',
                        'field_data_first_name' => 'data_first_name',
                        'field_data_father_name' => 'data_father_name',
                        'field_data_grand_father_name' => 'data_grand_father_name',
                        'field_data_family_name' => 'data_family_name',
                        'field_data_birth_date' => 'data_birth_date',
                        'field_data_phone_number' => 'data_phone_number',
                    ];

                    if (isset($basicFieldsMapping[$fieldKey])) {
                        $basicValue = $fieldOrClear($fieldValue, $fieldKey);
                        if ($basicValue !== $SKIP) {
                            $sponsorship->relationData->{$basicFieldsMapping[$fieldKey]} = $basicValue;
                            $mappedToData[] = $fieldKey;
                        }
                        continue;
                    }
                }

                // معالجة حقل إسم المكفول - تحديث في الجدول الصحيح بناءً على رقم الهوية
                if ($fieldKey === 'field_sponsor_name' && $fieldValue !== null && $fieldValue !== '') {
                    $this->updateSponsoredPersonName($sponsorship, $fieldValue);
                    // تحديث أيضاً في جدول sponsorships
                    $sponsorship->orphan_name = $fieldValue;
                    $mappedToSponsorship[] = $fieldKey;
                    continue;
                }

                // 🆕 حقل تاريخ ميلاد المكفول - يتم حفظه في re_people إذا كان فرد عائلة
                if ($fieldKey === 'field_person_birth_date') {
                    $birthDateValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($birthDateValue !== $SKIP) {
                        // إذا كان الشخص المكفول فرد عائلة، نحفظ التاريخ في re_people
                        if ($sponsorship->person_type === 'family_member') {
                            // البحث عن سجل الشخص في re_people
                            $rePerson = DB::table('re_people')
                                ->where('person_id', $sponsorship->identity_number)
                                ->first();

                            if ($rePerson) {
                                // تحديث تاريخ الميلاد في re_people
                                DB::table('re_people')
                                    ->where('id', $rePerson->id)
                                    ->update([
                                        'person_birth_date' => $birthDateValue,
                                        'updated_at' => now(),
                                    ]);

                                Log::info('FAMILY_MEMBER_BIRTH_DATE_UPDATED', [
                                    'sponsorship_id' => $sponsorship->id,
                                    'person_id' => $sponsorship->identity_number,
                                    're_people_id' => $rePerson->id,
                                    'birth_date' => $birthDateValue,
                                ]);
                            } else {
                                Log::warning('RE_PEOPLE_NOT_FOUND_FOR_BIRTH_DATE', [
                                    'sponsorship_id' => $sponsorship->id,
                                    'person_id' => $sponsorship->identity_number,
                                    'birth_date' => $birthDateValue,
                                ]);
                            }
                        }

                        // تحديث أيضاً في sponsorship (جميع الأنواع)
                        $sponsorship->sponsored_birth_date = $birthDateValue;
                        $mappedToSponsorship[] = $fieldKey;
                        Log::info('BIRTH_DATE_SAVED_TO_SPONSORSHIP', [
                            'sponsorship_id' => $sponsorship->id,
                            'person_type' => $sponsorship->person_type,
                            'birth_date' => $birthDateValue,
                        ]);
                    }
                    continue;
                }

                // ✅ حقل الحالة الصحية - يعمل لجميع أنواع الأشخاص (فرد عائلة / معيل / متوفي)
                if ($fieldKey === 'field_health_status') {
                    $healthFieldValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($healthFieldValue !== $SKIP) {
                        $healthStatusId = $healthFieldValue === null
                            ? null
                            : $this->resolveLookupIdByDescription('health_statuses', (string) $healthFieldValue);

                        if ($healthFieldValue !== null && $healthStatusId === null) {
                            Log::warning('HEALTH_STATUS_ID_NOT_FOUND', [
                                'field_value' => $healthFieldValue,
                                'sponsorship_id' => $sponsorship->id,
                            ]);
                        } else {
                            if ($sponsorship->person_type === 'family_member') {
                                // فرد عائلة: تحديث في re_people.person_health_status
                                $rePerson = DB::table('re_people')
                                    ->where('person_id', $sponsorship->identity_number)
                                    ->first();

                                if ($rePerson) {
                                    DB::table('re_people')
                                        ->where('id', $rePerson->id)
                                        ->update([
                                            'person_health_status' => $healthStatusId,
                                            'updated_at' => now(),
                                        ]);

                                    Log::info('FAMILY_MEMBER_HEALTH_STATUS_UPDATED', [
                                        'sponsorship_id' => $sponsorship->id,
                                        'person_id' => $sponsorship->identity_number,
                                        're_people_id' => $rePerson->id,
                                        'health_status_id' => $healthStatusId,
                                        'health_status_text' => $healthFieldValue,
                                    ]);
                                } else {
                                    Log::warning('RE_PEOPLE_NOT_FOUND_FOR_HEALTH_STATUS', [
                                        'sponsorship_id' => $sponsorship->id,
                                        'person_id' => $sponsorship->identity_number,
                                        'health_status' => $healthFieldValue,
                                    ]);
                                }
                            } elseif ($sponsorship->relationData) {
                                // المعيل: تحديث في data.data_health_status
                                $sponsorship->relationData->data_health_status = $healthStatusId;
                                $mappedToData[] = $fieldKey;
                            } elseif ($isDeceased) {
                                // المتوفى: لا يوجد صف في data/re_people - القيمة تبقى في portal فقط
                                Log::info('HEALTH_STATUS_STORED_IN_PORTAL_ONLY', [
                                    'sponsorship_id' => $sponsorship->id,
                                    'person_type' => $sponsorship->person_type,
                                    'health_status_id' => $healthStatusId,
                                ]);
                            }

                            $mappedToRePeople[] = $fieldKey;
                        }
                    }
                    continue;
                }

                // 🆕 حقل رقم هوية المكفول - يتم تحديثه في re_people إذا كان فرد عائلة
                if ($fieldKey === 'field_identity_number' && $fieldValue !== null && $fieldValue !== '' && $sponsorship->person_type === 'family_member') {
                    $oldIdentityNumber = $sponsorship->identity_number;
                    $newIdentityNumber = $fieldValue;

                    // البحث عن سجل الشخص في re_people بالرقم القديم
                    $rePerson = DB::table('re_people')
                        ->where('person_id', $oldIdentityNumber)
                        ->first();

                    if ($rePerson) {
                        // تحديث رقم الهوية في re_people
                        DB::table('re_people')
                            ->where('id', $rePerson->id)
                            ->update([
                                'person_id' => $newIdentityNumber,
                                'updated_at' => now(),
                            ]);

                        // تحديث رقم الهوية في sponsorships
                        $sponsorship->identity_number = $newIdentityNumber;

                        // تحديث رقم الهوية في attachments إذا كانت موجودة
                        DB::table('attachments')
                            ->where('person_identity_number', $oldIdentityNumber)
                            ->update(['person_identity_number' => $newIdentityNumber]);

                        Log::info('FAMILY_MEMBER_IDENTITY_UPDATED', [
                            'sponsorship_id' => $sponsorship->id,
                            'old_identity' => $oldIdentityNumber,
                            'new_identity' => $newIdentityNumber,
                            're_people_id' => $rePerson->id,
                        ]);

                        $mappedToSponsorship[] = $fieldKey;
                    } else {
                        Log::warning('RE_PEOPLE_NOT_FOUND_FOR_IDENTITY_UPDATE', [
                            'sponsorship_id' => $sponsorship->id,
                            'old_identity' => $oldIdentityNumber,
                            'new_identity' => $newIdentityNumber,
                        ]);

                        // تحديث في sponsorship فقط إذا لم يوجد سجل في re_people
                        $sponsorship->identity_number = $newIdentityNumber;
                        $mappedToSponsorship[] = $fieldKey;
                    }
                    continue;
                }

                // بعض الحقول يتم إرسالها كنص (description) من الـ UI بينما تُحفظ كـ ID في جدول data
                if ($sponsorship->relationData) {
                    // provinces.description -> data_province (INT)
                    if ($cleanFieldKey === 'data_province') {
                        $provinceValue = $fieldOrClear($fieldValue, $fieldKey);
                        if ($provinceValue !== $SKIP && $provinceValue === null) {
                            $sponsorship->relationData->data_province = null;
                            $mappedToData[] = $fieldKey;
                        } elseif ($provinceValue !== $SKIP) {
                            $raw = trim((string) $provinceValue);
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
                                        continue;
                                    }
                                }
                                $mappedToData[] = $fieldKey;
                            }
                        }
                        continue;
                    }

                    // city.city -> data_city (INT)
                    if ($cleanFieldKey === 'data_city') {
                        $cityValue = $fieldOrClear($fieldValue, $fieldKey);
                        if ($cityValue !== $SKIP && $cityValue === null) {
                            $sponsorship->relationData->data_city = null;
                            $mappedToData[] = $fieldKey;
                        } elseif ($cityValue !== $SKIP) {
                            $raw = trim((string) $cityValue);
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
                                        continue;
                                    }
                                }
                                $mappedToData[] = $fieldKey;
                            }
                        }
                        continue;
                    }

                    // health_statuses.description -> data_health_status
                    if ($cleanFieldKey === 'health_status') {
                        $healthLookupValue = $fieldOrClear($fieldValue, $fieldKey);
                        if ($healthLookupValue !== $SKIP && $healthLookupValue === null) {
                            $sponsorship->relationData->data_health_status = null;
                            $mappedToData[] = $fieldKey;
                        } elseif ($healthLookupValue !== $SKIP) {
                            $resolved = $this->resolveLookupIdByDescription('health_statuses', (string) $healthLookupValue);
                            if ($resolved !== null) {
                                $sponsorship->relationData->data_health_status = $resolved;
                                $mappedToData[] = $fieldKey;
                            } else {
                                Log::warning('LOOKUP_ID_NOT_FOUND', [
                                    'field' => 'health_status',
                                    'value' => (string) $healthLookupValue,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                            }
                        }
                        continue;
                    }

                    // housing_status.description -> data_housing_status
                    if ($cleanFieldKey === 'housing_status') {
                        $housingStatusValue = $fieldOrClear($fieldValue, $fieldKey);
                        if ($housingStatusValue !== $SKIP && $housingStatusValue === null) {
                            $sponsorship->relationData->data_housing_status = null;
                            $mappedToData[] = $fieldKey;
                        } elseif ($housingStatusValue !== $SKIP) {
                            $resolved = $this->resolveLookupIdByDescription('housing_status', (string) $housingStatusValue);
                            if ($resolved !== null) {
                                $sponsorship->relationData->data_housing_status = $resolved;
                                $mappedToData[] = $fieldKey;
                            } else {
                                Log::warning('LOOKUP_ID_NOT_FOUND', [
                                    'field' => 'housing_status',
                                    'value' => (string) $housingStatusValue,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                            }
                        }
                        continue;
                    }

                    // type_of_accommodation.description -> data_current_housing_type
                    if ($cleanFieldKey === 'housing_type') {
                        $housingTypeValue = $fieldOrClear($fieldValue, $fieldKey);
                        if ($housingTypeValue !== $SKIP && $housingTypeValue === null) {
                            $sponsorship->relationData->data_current_housing_type = null;
                            $mappedToData[] = $fieldKey;
                        } elseif ($housingTypeValue !== $SKIP) {
                            $resolved = $this->resolveLookupIdByDescription('type_of_accommodation', (string) $housingTypeValue);
                            if ($resolved !== null) {
                                $sponsorship->relationData->data_current_housing_type = $resolved;
                                $mappedToData[] = $fieldKey;
                            } else {
                                Log::warning('LOOKUP_ID_NOT_FOUND', [
                                    'field' => 'housing_type',
                                    'value' => (string) $housingTypeValue,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                            }
                        }
                        continue;
                    }
                }

                // 🆕 معالجة field_house_repair_need و field_house_demolition - يجب ربطهم برقم هوية المعيل
                if (in_array($fieldKey, ['field_house_repair_need', 'field_house_demolition'])) {
                    // في حالة فرد عائلة، نربط الحقول برقم هوية المعيل (guardian)
                    // وليس برقم هوية المكفول
                    $targetFileNumber = $fileIdNumberForPortal;
                    $targetIdentity = $identityForPortal;

                    // إذا كان الشخص فرد عائلة، نستخدم رقم ملف المعيل
                    if ($sponsorship->person_type === 'family_member' && $sponsorship->relationData) {
                        $targetFileNumber = (string) $sponsorship->relationData->file_id_number;
                        $targetIdentity = (string) ($sponsorship->guardian_identity_number ?: $sponsorship->relationData->data_id_number);
                    }

                    // حفظ في portal_general_registration_field_values مرتبط برقم المعيل
                    PortalGeneralRegistrationFieldValue::query()->updateOrCreate(
                        [
                            'file_id_number' => $targetFileNumber,
                            'field_key' => (string) $fieldKey,
                        ],
                        [
                            'sponsorship_id' => $sponsorship->id,
                            'identity_number' => $targetIdentity,
                            'field_value' => is_array($fieldValue) ? json_encode($fieldValue, JSON_UNESCAPED_UNICODE) : (string) $fieldValue,
                            'updated_by_user_id' => $user?->id,
                        ]
                    );

                    Log::info('HOUSE_REPAIR_DEMOLITION_SAVED', [
                        'sponsorship_id' => $sponsorship->id,
                        'field_key' => $fieldKey,
                        'person_type' => $sponsorship->person_type,
                        'target_file_number' => $targetFileNumber,
                        'target_identity' => $targetIdentity,
                    ]);

                    continue;
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

                // 🆕 جمع حقول بيانات المتوفين (dead_people) - الأب والأم
                if (in_array($fieldKey, [
                    'field_father_first_name', 'field_father_second_name', 'field_father_third_name', 'field_father_last_name',
                    'field_father_id', 'field_father_death_date', 'field_father_death_reason',
                    'field_mother_first_name', 'field_mother_second_name', 'field_mother_third_name', 'field_mother_last_name',
                    'field_mother_id', 'field_mother_death_date', 'field_mother_death_reason'
                ])) {
                    $deadPeopleFields[$fieldKey] = $fieldValue;
                    continue;
                }

                // تحديث في جدول sponsorships
                if (in_array($cleanFieldKey, $sponsorshipFields)) {
                    $sponsorshipValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($sponsorshipValue !== $SKIP) {
                        $sponsorship->$cleanFieldKey = $sponsorshipValue;
                        $mappedToSponsorship[] = $fieldKey;
                    }
                }
                // تحديث في جدول data (relationData)
                else if ($sponsorship->relationData) {
                    $dataValue = $fieldOrClear($fieldValue, $fieldKey);
                    if ($dataValue === $SKIP) {
                        continue;
                    }

                    // بعض الحقول تأتي بالفعل بصيغة data_xxx (مثال: field_data_city)
                    // لذا لا نضيف data_ مرة ثانية.
                    $candidateColumns = [];
                    if (str_starts_with($cleanFieldKey, 'data_')) {
                        $candidateColumns[] = $cleanFieldKey;
                    } else {
                        $candidateColumns[] = 'data_' . $cleanFieldKey;
                    }

                    $assignedColumn = null;
                    foreach ($candidateColumns as $dataColumn) {
                        $exists = !empty($dataColumnMap)
                            ? isset($dataColumnMap[$dataColumn])
                            : Schema::hasColumn('data', $dataColumn);

                        if ($exists) {
                            $sponsorship->relationData->$dataColumn = $dataValue;
                            $assignedColumn = $dataColumn;
                            break;
                        }
                    }

                    if ($assignedColumn !== null) {
                        $mappedToData[] = $fieldKey;
                    } else {
                        $unmappedFieldKeys[] = $fieldKey;
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

            // تحديث البيانات البنكية - يجب استخدام guardian_bank_accounts فقط لجميع أنواع الأشخاص
            if (!empty($bankFields)) {
                // 🆕 تحديد رقم التسجيل (file_id_number) بناءً على نوع الشخص
                $guardianFileNumber = null;
                $personType = $sponsorship->person_type;

                // 1. للمعيل أو فرد العائلة: استخدام file_id_number من relationData
                if (in_array($personType, ['breadwinner', 'family_member', null]) && $sponsorship->relationData) {
                    $guardianFileNumber = $sponsorship->relationData->file_id_number;
                }
                // 2. للمتوفين (أب أو أم): استخدام re_file_id من dead_people
                elseif (in_array($personType, ['deceased_father', 'deceased_mother'])) {
                    $deadPeopleRecord = DB::table('dead_people')
                        ->where(function($query) use ($sponsorship) {
                            $query->where('father_id', $sponsorship->identity_number)
                                  ->orWhere('mother_id', $sponsorship->identity_number);
                        })
                        ->first();

                    if ($deadPeopleRecord) {
                        $guardianFileNumber = $deadPeopleRecord->re_file_id;
                    } elseif ($sponsorship->relation_id_number) {
                        // استخدام relation_id_number كبديل
                        $guardianFileNumber = $sponsorship->relation_id_number;
                    }
                }
                // 3. fallback: استخدام relation_id_number
                if (!$guardianFileNumber && $sponsorship->relation_id_number) {
                    $guardianFileNumber = $sponsorship->relation_id_number;
                }

                Log::info('BANK_ACCOUNT_PROCESSING', [
                    'sponsorship_id' => $sponsorship->id,
                    'person_type' => $personType,
                    'guardian_file_number' => $guardianFileNumber,
                    'identity_number' => $sponsorship->identity_number
                ]);

                if ($guardianFileNumber) {
                    // bank_name عمود NOT NULL (bigint) - لا يجوز إدخال قيمة فارغة
                    $incomingBankName = trim((string) ($bankFields['field_guardian_bank_name'] ?? ''));
                    if ($incomingBankName === '' || !is_numeric($incomingBankName)) {
                        unset($bankFields['field_guardian_bank_name']);
                    }

                    // البحث عن الحساب المعتمد أولاً
                    $bankAccount = DB::table('guardian_bank_accounts')
                        ->where('guardian_registration', $guardianFileNumber)
                        ->where('check_account', 1)
                        ->first();

                    // إذا وجد حساب معتمد - تحديثه
                    if ($bankAccount) {
                        DB::table('guardian_bank_accounts')
                            ->where('id', $bankAccount->id)
                            ->update([
                                're_guardian_name' => $bankFields['field_guardian_account_owner_name'] ?? $bankAccount->re_guardian_name,
                                'bank_name' => $bankFields['field_guardian_bank_name'] ?? $bankAccount->bank_name,
                                'person_owner_identity_number' => $bankFields['field_guardian_id_owner'] ?? $bankAccount->person_owner_identity_number,
                                're_phone_number' => $bankFields['field_guardian_phone_number'] ?? $bankAccount->re_phone_number,
                                'iban_usd' => $bankFields['field_guardian_iban_usd'] ?? $bankAccount->iban_usd,
                                'iban_shekel' => $bankFields['field_guardian_iban_shekel'] ?? $bankAccount->iban_shekel,
                                'updated_at' => now(),
                            ]);

                        Log::info('BANK_ACCOUNT_UPDATED', [
                            'bank_account_id' => $bankAccount->id,
                            'guardian_file_number' => $guardianFileNumber
                        ]);
                    } else {
                        // 🆕 إذا لم يوجد حساب - إنشاء حساب جديد
                        // التحقق من عدم وجود حساب آخر (حتى لو غير معتمد) لنفس الشخص
                        $existingAccount = DB::table('guardian_bank_accounts')
                            ->where('guardian_registration', $guardianFileNumber)
                            ->first();

                        if ($existingAccount) {
                            // تحديث الحساب الموجود وجعله معتمداً
                            DB::table('guardian_bank_accounts')
                                ->where('id', $existingAccount->id)
                                ->update([
                                    're_guardian_name' => $bankFields['field_guardian_account_owner_name'] ?? $existingAccount->re_guardian_name,
                                    'bank_name' => $bankFields['field_guardian_bank_name'] ?? $existingAccount->bank_name,
                                    'person_owner_identity_number' => $bankFields['field_guardian_id_owner'] ?? $existingAccount->person_owner_identity_number,
                                    're_phone_number' => $bankFields['field_guardian_phone_number'] ?? $existingAccount->re_phone_number,
                                    'iban_usd' => $bankFields['field_guardian_iban_usd'] ?? $existingAccount->iban_usd,
                                    'iban_shekel' => $bankFields['field_guardian_iban_shekel'] ?? $existingAccount->iban_shekel,
                                    'check_account' => 1, // جعله الحساب المعتمد
                                    'updated_at' => now(),
                                ]);

                            Log::info('BANK_ACCOUNT_MADE_APPROVED', [
                                'bank_account_id' => $existingAccount->id,
                                'guardian_file_number' => $guardianFileNumber
                            ]);
                        } else {
                            // إنشاء حساب جديد - فقط إذا كان bank_name موجوداً (حقل إلزامي في قاعدة البيانات)
                            $bankNameValue = $bankFields['field_guardian_bank_name'] ?? null;

                            if (!empty($bankNameValue)) {
                                $newBankAccountId = DB::table('guardian_bank_accounts')->insertGetId([
                                    'guardian_registration' => $guardianFileNumber,
                                    're_id_number' => $sponsorship->identity_number,
                                    're_guardian_name' => $bankFields['field_guardian_account_owner_name'] ?? '',
                                    'bank_name' => $bankNameValue,
                                    'person_owner_identity_number' => $bankFields['field_guardian_id_owner'] ?? '',
                                    're_phone_number' => $bankFields['field_guardian_phone_number'] ?? '',
                                    'iban_usd' => $bankFields['field_guardian_iban_usd'] ?? '',
                                    'iban_shekel' => $bankFields['field_guardian_iban_shekel'] ?? '',
                                    'check_account' => 1, // الحساب المعتمد الأول
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                Log::info('BANK_ACCOUNT_CREATED', [
                                    'new_bank_account_id' => $newBankAccountId,
                                    'guardian_file_number' => $guardianFileNumber,
                                    'person_type' => $personType
                                ]);
                            } else {
                                Log::warning('BANK_ACCOUNT_SKIPPED_NO_BANK_NAME', [
                                    'guardian_file_number' => $guardianFileNumber,
                                    'person_type' => $personType,
                                    'reason' => 'bank_name is required but was not provided'
                                ]);
                            }
                        }
                    }

                    // 🔒 ضمان وجود حساب معتمد واحد فقط لكل guardian_registration
                    $this->ensureSingleApprovedAccount($guardianFileNumber);
                } else {
                    Log::warning('BANK_ACCOUNT_NO_FILE_NUMBER', [
                        'sponsorship_id' => $sponsorship->id,
                        'person_type' => $personType
                    ]);
                }
            }

            // 🆕 معالجة بيانات المتوفين (dead_people) - CRUD كامل
            // يجب معالجتها باستخدام relation_id_number مباشرة من sponsorship
            // ⚠️ أسماء الأب/الأم المتوفين لا تصل أبداً في fields[] بل في names[father]/names[mother]
            //   (البليد يرسمها باسم names[...] وليس fields[field_father_first_name])
            $hasDeadParentNames = isset($namesData['father']) || isset($namesData['mother']);
            if (!empty($deadPeopleFields) || $hasDeadParentNames) {
                $relationIdNumber = $sponsorship->relation_id_number;

                // إذا لا يوجد relation_id_number، نستخدم file_id_number من relationData
                if (!$relationIdNumber && $sponsorship->relationData) {
                    $relationIdNumber = $sponsorship->relationData->file_id_number;
                }

                if ($relationIdNumber) {
                    $deadPeopleRecord = DB::table('dead_people')
                        ->where('re_file_id', $relationIdNumber)
                        ->first();

                    $deadPeopleUpdates = [];

                    // 🧹 تحويل القيم الفارغة إلى null (الأعمدة nullable) ومنع ''
                    // في الأعمدة الرقمية/التاريخية التي ترفض MySQL قيم '' في الوضع الصارم
                    $deadOrNull = function ($raw) {
                        if ($raw === null || $raw === '' || $raw === []) {
                            return null;
                        }
                        return is_string($raw) ? trim($raw) : $raw;
                    };

                    // حقول الأب المتوفى
                    if (isset($deadPeopleFields['field_father_first_name'])) {
                        $deadPeopleUpdates['father_first_name'] = $deadOrNull($deadPeopleFields['field_father_first_name']);
                    }
                    if (isset($deadPeopleFields['field_father_second_name'])) {
                        $deadPeopleUpdates['father_second_name'] = $deadOrNull($deadPeopleFields['field_father_second_name']);
                    }
                    if (isset($deadPeopleFields['field_father_third_name'])) {
                        $deadPeopleUpdates['father_third_name'] = $deadOrNull($deadPeopleFields['field_father_third_name']);
                    }
                    if (isset($deadPeopleFields['field_father_last_name'])) {
                        $deadPeopleUpdates['father_last_name'] = $deadOrNull($deadPeopleFields['field_father_last_name']);
                    }
                    if (isset($deadPeopleFields['field_father_id'])) {
                        $deadPeopleUpdates['father_id'] = $deadOrNull($deadPeopleFields['field_father_id']);
                    }
                    if (isset($deadPeopleFields['field_father_death_date'])) {
                        $deadPeopleUpdates['father_death_date'] = $deadOrNull($deadPeopleFields['field_father_death_date']);
                    }
                    // سبب وفاة الأب - تحويل من نص إلى ID
                    if (isset($deadPeopleFields['field_father_death_reason'])) {
                        $deathReason = $deadOrNull($deadPeopleFields['field_father_death_reason']);
                        if ($deathReason !== null && !is_numeric($deathReason)) {
                            $reason = DB::table('death_reasons')->where('description', $deathReason)->first();
                            if ($reason) {
                                $deathReason = $reason->id;
                            } else {
                                Log::warning('DEATH_REASON_NOT_FOUND', [
                                    'field' => 'field_father_death_reason',
                                    'value' => $deathReason,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                                $deathReason = null;
                            }
                        }
                        $deadPeopleUpdates['father_death_reason'] = $deathReason;
                    }

                    // حقول الأم المتوفية
                    if (isset($deadPeopleFields['field_mother_first_name'])) {
                        $deadPeopleUpdates['mother_first_name'] = $deadOrNull($deadPeopleFields['field_mother_first_name']);
                    }
                    if (isset($deadPeopleFields['field_mother_second_name'])) {
                        $deadPeopleUpdates['mother_second_name'] = $deadOrNull($deadPeopleFields['field_mother_second_name']);
                    }
                    if (isset($deadPeopleFields['field_mother_third_name'])) {
                        $deadPeopleUpdates['mother_third_name'] = $deadOrNull($deadPeopleFields['field_mother_third_name']);
                    }
                    if (isset($deadPeopleFields['field_mother_last_name'])) {
                        $deadPeopleUpdates['mother_last_name'] = $deadOrNull($deadPeopleFields['field_mother_last_name']);
                    }
                    if (isset($deadPeopleFields['field_mother_id'])) {
                        $deadPeopleUpdates['mother_id'] = $deadOrNull($deadPeopleFields['field_mother_id']);
                    }
                    if (isset($deadPeopleFields['field_mother_death_date'])) {
                        $deadPeopleUpdates['mother_death_date'] = $deadOrNull($deadPeopleFields['field_mother_death_date']);
                    }
                    // سبب وفاة الأم - تحويل من نص إلى ID
                    if (isset($deadPeopleFields['field_mother_death_reason'])) {
                        $deathReason = $deadOrNull($deadPeopleFields['field_mother_death_reason']);
                        if ($deathReason !== null && !is_numeric($deathReason)) {
                            $reason = DB::table('death_reasons')->where('description', $deathReason)->first();
                            if ($reason) {
                                $deathReason = $reason->id;
                            } else {
                                Log::warning('DEATH_REASON_NOT_FOUND', [
                                    'field' => 'field_mother_death_reason',
                                    'value' => $deathReason,
                                    'sponsorship_id' => $sponsorship->id,
                                ]);
                                $deathReason = null;
                            }
                        }
                        $deadPeopleUpdates['mother_death_reason'] = $deathReason;
                    }

                    // ✅ أسماء الأب والأم المتوفين من names[father]/names[mother]
                    // تُطبَّق بعد حقول fields[] لتصبح هي المعتمدة عندما يرسلها الفورم
                    foreach (['father', 'mother'] as $deadNamePrefix) {
                        if (!isset($namesData[$deadNamePrefix]) || !is_array($namesData[$deadNamePrefix])) {
                            continue;
                        }

                        $deadNames = $namesData[$deadNamePrefix];
                        $deadNameColumns = [];
                        foreach (['first_name', 'second_name', 'third_name', 'last_name'] as $deadNamePart) {
                            if (!array_key_exists($deadNamePart, $deadNames)) {
                                continue;
                            }
                            // الأعمدة هي father_first_name وليس father_first_name_name
                            $deadPeopleUpdates[$deadNamePrefix . '_' . $deadNamePart] = $deadOrNull($deadNames[$deadNamePart]);
                            $deadNameColumns[] = $deadNamePrefix . '_' . $deadNamePart;
                        }

                        if (!empty($deadNameColumns)) {
                            Log::info('DEAD_PEOPLE_NAMES_FROM_NAMES_INPUT', [
                                'sponsorship_id' => $sponsorship->id,
                                'relation_id_number' => $relationIdNumber,
                                'prefix' => $deadNamePrefix,
                                'columns' => $deadNameColumns,
                                'has_record' => !empty($deadPeopleRecord),
                            ]);
                        }
                    }

                    if (!empty($deadPeopleUpdates)) {
                        if ($deadPeopleRecord) {
                            // تحديث السجل الموجود
                            DB::table('dead_people')
                                ->where('id', $deadPeopleRecord->id)
                                ->update($deadPeopleUpdates);

                            Log::info('DEAD_PEOPLE_UPDATED', [
                                'sponsorship_id' => $sponsorship->id,
                                'relation_id_number' => $relationIdNumber,
                                'dead_people_id' => $deadPeopleRecord->id,
                                'updated_fields' => array_keys($deadPeopleUpdates)
                            ]);
                        } else {
                            // إنشاء سجل جديد
                            $deadPeopleUpdates['re_file_id'] = $relationIdNumber;
                            $deadPeopleUpdates['created_at'] = now();
                            $deadPeopleUpdates['updated_at'] = now();

                            $newDeadPeopleId = DB::table('dead_people')->insertGetId($deadPeopleUpdates);

                            Log::info('DEAD_PEOPLE_CREATED', [
                                'sponsorship_id' => $sponsorship->id,
                                'relation_id_number' => $relationIdNumber,
                                'new_dead_people_id' => $newDeadPeopleId,
                                'created_fields' => array_keys($deadPeopleUpdates)
                            ]);
                        }
                    }
                }
            }

            // ✅ إصلاح: منع إرسال '' أو null إلى أعمدة لا تسمح بها (integer/bigint/date...)
            // "" → NULL فقط إذا كان العمود nullable، وإلا تُسقط القيمة وتبقى القيمة القديمة
            $this->dropUnsafeDirtyAttributes($sponsorship, 'sponsorships');

            if ($sponsorship->relationData) {
                $this->dropUnsafeDirtyAttributes($sponsorship->relationData, 'data');
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
                'dead_people_fields_count' => count($deadPeopleFields),
            ]);

            // تحديث أفراد الأسرة من جدول re_people
            // 📌 نسجّل دائماً ما وصل من الفورم: غياب family_members في الطلب يعني أن
            //    لا توجد صفوف في DOM وقت الحفظ (صفوف JS لا تُستعاد عبر old() عند أي redirect)
            $familyMembersInput = $request->input('family_members');
            Log::info('FAMILY_MEMBERS_SUBMIT', [
                'sponsorship_id' => $sponsorship->id,
                'in_request' => $request->has('family_members'),
                'rows_received' => is_array($familyMembersInput) ? count($familyMembersInput) : 0,
                'person_type' => $sponsorship->person_type,
            ]);

            if (is_array($familyMembersInput) && !empty($familyMembersInput)) {
                $familyMembers = $familyMembersInput;

                // 🆕 تحديد رقم الملف للربط - يعمل مع جميع أنواع الأشخاص بما فيهم المتوفين
                $fileIdForFamilyMembers = $sponsorship->relationData?->file_id_number
                    ?: $sponsorship->relation_id_number
                    ?: null;

                $skippedFamilyMembers = [];

                // أسماء حقول الفورم -> أعمدة جدول re_people
                $familyColumnMap = [
                    'first_name' => 'first_name',
                    'second_name' => 'second_name',
                    'third_name' => 'third_name',
                    'last_name' => 'last_name',
                    'birthdate' => 'person_birth_date',
                    'gender' => 'person_gender',
                    'notes' => 'person_note',
                ];

                foreach ($familyMembers as $memberIndex => $memberData) {
                    if (!is_array($memberData)) {
                        $skippedFamilyMembers[] = [
                            'index' => $memberIndex,
                            'reason' => 'invalid_row',
                        ];
                        Log::warning('FAMILY_MEMBER_SKIPPED_INVALID_ROW', [
                            'sponsorship_id' => $sponsorship->id,
                            'member_index' => $memberIndex,
                        ]);
                        continue;
                    }

                    // 🆕 صف جديد: موسوم بـ is_new أو بلا id أصلاً
                    // (صفوف المتصفح قد تُبنى بدون is_new - كانت تُسقط صامتاً قبل هذا الإصلاح)
                    $isNewFamilyMember = (isset($memberData['is_new']) && $memberData['is_new'] == 1)
                        || empty($memberData['id']);

                    // ✅ قاعدة عدم التكرار: نفس الملف + نفس الهوية = الفرد نفسه
                    // (يغطي الحالة التي يضيف فيها المستخدم صفاً جديداً بهوية موجودة مسبقاً)
                    $memberPersonId = trim((string) ($memberData['person_id'] ?? $memberData['identity_number'] ?? ''));
                    $existingFamilyMember = null;
                    if ($fileIdForFamilyMembers && preg_match('/^\d{9}$/', $memberPersonId)) {
                        $existingFamilyMember = DB::table('re_people')
                            ->where('registration_id', $fileIdForFamilyMembers)
                            ->where('person_id', $memberPersonId)
                            ->first();
                    }

                    if ($existingFamilyMember) {
                        // موجود مسبقاً ← تحديث بياناته فقط، ولا يُضاف نسخة ثانية
                        $memberUpdates = [];
                        foreach ($familyColumnMap as $inputKey => $dbColumn) {
                            if (!array_key_exists($inputKey, $memberData)) {
                                continue;
                            }

                            $newValue = $memberData[$inputKey];
                            if ($newValue === '' || $newValue === null) {
                                $newValue = in_array($dbColumn, ['person_birth_date', 'person_gender'], true)
                                    ? null
                                    : ($newValue === null ? null : $newValue);
                            }

                            $memberUpdates[$dbColumn] = $newValue;
                        }

                        if (!empty($memberUpdates)) {
                            $memberUpdates['updated_at'] = now();

                            DB::table('re_people')
                                ->where('id', $existingFamilyMember->id)
                                ->update($memberUpdates);
                        }

                        Log::info('FAMILY_MEMBER_UPDATED_EXISTING', [
                            'sponsorship_id' => $sponsorship->id,
                            're_people_id' => $existingFamilyMember->id,
                            'file_id_number' => $fileIdForFamilyMembers,
                            'person_id' => $memberPersonId,
                            'submitted_row_id' => $memberData['id'] ?? null,
                            'is_new_flag' => $memberData['is_new'] ?? null,
                            'updated_columns' => array_keys($memberUpdates),
                        ]);
                        continue;
                    }

                    // تحقق إذا كان فرد موجود أو جديد
                    if ($isNewFamilyMember) {
                        // إضافة فرد جديد - استخدام الحقول الأربعة المنفصلة
                        if (!$fileIdForFamilyMembers) {
                            $skippedFamilyMembers[] = [
                                'index' => $memberIndex,
                                'reason' => 'no_file_number',
                            ];
                            Log::warning('FAMILY_MEMBER_SKIPPED_NO_FILE', [
                                'sponsorship_id' => $sponsorship->id,
                                'member_index' => $memberIndex,
                                'person_type' => $sponsorship->person_type,
                            ]);
                            continue;
                        }

                        $hasName = !empty($memberData['first_name']) || !empty($memberData['second_name']) ||
                                   !empty($memberData['third_name']) || !empty($memberData['last_name']);

                        $personId = trim($memberData['person_id'] ?? $memberData['identity_number'] ?? '');
                        if (!preg_match('/^\d{9}$/', $personId)) {
                            $skippedFamilyMembers[] = [
                                'index' => $memberIndex,
                                'reason' => 'invalid_person_id',
                                'person_id' => $personId,
                            ];
                            Log::warning('FAMILY_MEMBER_SKIPPED_INVALID_ID', [
                                'sponsorship_id' => $sponsorship->id,
                                'member_index' => $memberIndex,
                                'person_id' => $personId,
                                'note' => 'رقم الهوية غير صالح (يجب أن يكون 9 أرقام) - لم يتم إضافة الفرد.',
                            ]);
                            continue;
                        }

                        if (!$hasName) {
                            $skippedFamilyMembers[] = [
                                'index' => $memberIndex,
                                'reason' => 'missing_name',
                                'person_id' => $personId,
                            ];
                            Log::warning('FAMILY_MEMBER_SKIPPED_NO_NAME', [
                                'sponsorship_id' => $sponsorship->id,
                                'member_index' => $memberIndex,
                                'person_id' => $personId,
                            ]);
                            continue;
                        }

                        DB::table('re_people')->insert([
                            'registration_id' => $fileIdForFamilyMembers,
                            'person_id' => $personId,
                            'first_name' => $memberData['first_name'] ?? '',
                            'second_name' => $memberData['second_name'] ?? '',
                            'third_name' => $memberData['third_name'] ?? '',
                            'last_name' => $memberData['last_name'] ?? '',
                            'person_birth_date' => ($memberData['birthdate'] ?? '') !== '' ? $memberData['birthdate'] : null,
                            'person_gender' => ($memberData['gender'] ?? '') !== '' ? $memberData['gender'] : null,
                            'person_note' => ($memberData['notes'] ?? '') !== '' ? $memberData['notes'] : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        Log::info('FAMILY_MEMBER_ADDED', [
                            'sponsorship_id' => $sponsorship->id,
                            'person_type' => $sponsorship->person_type,
                            'file_id_number' => $fileIdForFamilyMembers,
                            'person_id' => $personId,
                            'member_name' => trim("{$memberData['first_name']} {$memberData['last_name']}"),
                        ]);
                    } elseif (isset($memberData['id']) && $memberData['id']) {
                        // تحديث فرد موجود
                        // 🧠 لا نصفّر الحقول غير المرسلة: نحدّث فقط الحقول الموجودة فعلياً في الطلب
                        $memberUpdates = [];
                        foreach ($familyColumnMap as $inputKey => $dbColumn) {
                            if (!array_key_exists($inputKey, $memberData)) {
                                continue;
                            }

                            $newValue = $memberData[$inputKey];
                            if ($newValue === '' || $newValue === null) {
                                $newValue = in_array($dbColumn, ['person_birth_date', 'person_gender'], true)
                                    ? null
                                    : ($newValue === null ? null : $newValue);
                            }

                            $memberUpdates[$dbColumn] = $newValue;
                        }

                        if (empty($memberUpdates)) {
                            continue;
                        }

                        $memberUpdates['updated_at'] = now();

                        DB::table('re_people')
                            ->where('id', $memberData['id'])
                            ->update($memberUpdates);

                        Log::info('FAMILY_MEMBER_UPDATED', [
                            'sponsorship_id' => $sponsorship->id,
                            're_people_id' => $memberData['id'],
                            'updated_columns' => array_keys($memberUpdates),
                        ]);
                    }
                }

                if (!empty($skippedFamilyMembers)) {
                    Log::warning('FAMILY_MEMBERS_PARTIALLY_SKIPPED', [
                        'sponsorship_id' => $sponsorship->id,
                        'skipped_count' => count($skippedFamilyMembers),
                        'skipped' => $skippedFamilyMembers,
                    ]);
                }
            }

            // معالجة المرفقات الجديدة
            if ($attachmentsValidTotal > 0) {
                // التحقق من استخدام Rclone أو Google Drive API أو التخزين المحلي
                $useRclone = config('services.rclone.enabled', env('USE_RCLONE_FOR_UPLOADS', false));
                $useLocalStorage = env('USE_LOCAL_STORAGE_FOR_UPLOADS', false);

                // 🆕 إذا كان Google Drive معطّل للجمعية، نحفظ محلياً فقط
                $googleDriveEnabled = $sponsorship->sponsor?->google_drive_enabled ?? false;
                if (!$googleDriveEnabled) {
                    $useRclone = false;
                    $useLocalStorage = true;
                    Log::info('GOOGLE_DRIVE_DISABLED_FOR_SPONSOR', [
                        'sponsor_id' => $sponsorship->sponsor_id,
                        'identity' => $sponsorship->identity_number,
                    ]);
                }

                $organizationName = $sponsorship->sponsor?->sponsor_name ?: ($sponsorship->sponsoring_organization ?: 'غير محدد');
                $orphanName = $sponsorship->orphan_name ?: $sponsorship->identity_number;

                Log::info('ATTACHMENTS_UPLOAD_START', [
                    'sponsorship_id' => $sponsorship->id,
                    'identity' => $sponsorship->identity_number,
                    'use_rclone' => $useRclone,
                    'use_local_storage' => $useLocalStorage,
                    'organization_name' => $organizationName,
                    'orphan_name' => $orphanName,
                    'attachments_total_files' => $attachmentsTotal,
                    'attachments_valid_files' => $attachmentsValidTotal,
                    'attachments_summary' => $attachmentsSummary,
                    'attachments_invalid_summary' => $attachmentsInvalidSummary,
                ]);

                $uploadedCount = 0;

                // قراءة إعداد الضغط للجمعية
                $compressEnabled = true;
                if ($sponsorship->sponsor_id) {
                    $fieldSettingsForCompress = \App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();
                    $compressEnabled = (bool) ($fieldSettingsForCompress->compress_attachments_images ?? true);
                }

                Log::info('ATTACHMENTS_COMPRESS_SETTING', [
                    'sponsor_id' => $sponsorship->sponsor_id,
                    'compress_enabled' => $compressEnabled,
                ]);

                // معالجة ضغط الصور قبل الرفع (إذا كان مفعّلاً)
                if ($compressEnabled) {
                    $imageProcessor = new \App\Services\ImageProcessingService();
                    foreach ($validAttachments as $docTypeId => $files) {
                        foreach ($files as $index => $file) {
                            if (str_starts_with($file->getMimeType(), 'image/')) {
                                $compressed = $imageProcessor->compressImage($file);
                                if ($compressed) {
                                    $validAttachments[$docTypeId][$index] = $compressed;
                                    Log::info('IMAGE_COMPRESSED_BEFORE_UPLOAD', [
                                        'original_size' => $file->getSize(),
                                        'compressed_size' => $compressed->getSize(),
                                        'doc_type_id' => $docTypeId,
                                    ]);
                                }
                            }
                        }
                    }
                }

                if ($useRclone) {
                    // ===== استخدام Rclone لرفع الملفات + نسخة محلية =====
                    $rcloneService = new RcloneGoogleDriveService();

                    // التحقق من اتصال Rclone
                    if (!($rcloneService->testConnection()['success'] ?? false)) {
                        throw new \Exception('فشل الاتصال بـ Rclone. تأكد من إعدادات RCLONE_PATH و RCLONE_REMOTE_NAME في ملف .env');
                    }

                    // إنشاء مسار المجلد: temp/اسم الجمعية/اسم الشخص
                    $basePath = $rcloneService->createFolderStructure($organizationName, $orphanName);

                    // مسار التخزين المحلي
                    $fileIdNumber = $sponsorship->relation_id_number ?: $sponsorship->internal_file_number;
                    $fileIdNumberAttach = str_pad($fileIdNumber, 6, '0', STR_PAD_LEFT);

                    // قراءة إعداد الحفظ المحلي من إعدادات الجمعية (قائمة بمعرفات الوثائق)
                    $saveLocalDocIds = [];
                    if ($sponsorship->sponsor_id) {
                        $fieldSettingsForLocal = \App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();
                        $saveLocalDocIds = $fieldSettingsForLocal->save_local_attachments ?? [];
                        if (!is_array($saveLocalDocIds)) {
                            $saveLocalDocIds = json_decode($saveLocalDocIds, true) ?: [];
                        }
                    }

                    foreach ($validAttachments as $docTypeId => $files) {
                        $documentType = \App\Models\DocumentType::find($docTypeId);

                        if (!$documentType || !$sponsorship->identity_number) {
                            continue;
                        }

                        $saveLocalForDoc = in_array((int)$docTypeId, $saveLocalDocIds);

                        $fileIndex = 0;
                        foreach ((array)$files as $file) {
                            $fileIndex++;
                            $extension = strtolower($file->getClientOriginalExtension() ?: '');
                            $documentTypeName = $documentType->pref ?: 'وثيقة';
                            $driveTimestamp = date('Ymd_His');
                            $driveFileName = "{$documentType->pref}_{$fileIdNumberAttach}_{$sponsorship->identity_number}_{$driveTimestamp}";

                            // رفع الملف عبر Rclone إلى Google Drive (دائماً)
                            $uploadResult = $rcloneService->uploadFile(
                                $file->getRealPath(),
                                $organizationName,
                                $orphanName,
                                $driveFileName,
                                $extension,
                                $fileIndex
                            );

                            if (!$uploadResult['success']) {
                                Log::error('RCLONE_UPLOAD_FAILED', [
                                    'identity' => $sponsorship->identity_number,
                                    'document_type' => $documentTypeName,
                                    'error' => $uploadResult['message'] ?? 'Unknown error',
                                ]);
                                continue;
                            }

                            // حفظ محلي + DB فقط إذا كان مفعّلاً لهذا النوع من الوثائق
                            if ($saveLocalForDoc) {
                                $existingLocalCount = Attachment::where('person_identity_number', $sponsorship->identity_number)
                                    ->where('file_type', $docTypeId)
                                    ->count();
                                $localSerial = $existingLocalCount > 0 ? '_' . ($existingLocalCount + 1) : '';
                                $localTimestamp = date('Ymd_His');
                                $localFileName = "{$documentTypeName}_{$fileIdNumberAttach}_{$sponsorship->identity_number}_{$localTimestamp}{$localSerial}.{$extension}";
                                $localFolder = 'uploads/' . $fileIdNumberAttach;
                                $localPath = $file->storeAs($localFolder, $localFileName, 'public');

                                $filePath = 'storage/' . $localPath;
                                $storedFileName = $localFileName;

                                Attachment::create([
                                    'person_identity_number' => $sponsorship->identity_number,
                                    'stored_file_name' => $storedFileName,
                                    'file_path' => $filePath,
                                    'file_type' => $docTypeId,
                                    'file_size' => $file->getSize(),
                                ]);

                                $uploadedCount++;

                                Log::info('ATTACHMENT_SAVED_LOCAL', [
                                    'identity' => $sponsorship->identity_number,
                                    'doc_type' => $documentTypeName,
                                    'local_path' => $filePath,
                                ]);
                            } else {
                                Log::info('ATTACHMENT_SKIPPED_NO_SAVE_LOCAL', [
                                    'identity' => $sponsorship->identity_number,
                                    'doc_type' => $documentTypeName,
                                    'rclone_remote_path' => $uploadResult['remote_path'] ?? null,
                                ]);
                            }

                            Log::info('ATTACHMENT_UPLOADED_VIA_RCLONE', [
                                'identity' => $sponsorship->identity_number,
                                'doc_type' => $documentTypeName,
                                'saved_local' => $saveLocalForDoc,
                            ]);
                        }
                    }

                    Log::info('RCLONE_ATTACHMENTS_UPLOAD_COMPLETED', [
                        'sponsorship_id' => $sponsorship->id,
                        'identity' => $sponsorship->identity_number,
                        'uploaded_count' => $uploadedCount,
                        'base_path' => $basePath,
                    ]);

                } elseif ($useLocalStorage) {
                    // ===== التخزين المحلي (محلي على السيرفر) =====
                    $fileIdNumber = $sponsorship->relation_id_number ?: $sponsorship->internal_file_number;
                    $fileIdNumberAttach = str_pad($fileIdNumber, 6, '0', STR_PAD_LEFT);

                    foreach ($validAttachments as $docTypeId => $files) {
                        $documentType = \App\Models\DocumentType::find($docTypeId);

                        if (!$documentType || !$sponsorship->identity_number) {
                            continue;
                        }

                        $fileIndex = 0;
                        foreach ((array)$files as $file) {
                            $fileIndex++;
                            $extension = strtolower($file->getClientOriginalExtension() ?: '');
                            $fileType = $documentType->pref ?: 'وثيقة';

                            // اسم الملف: pref_رقم_الملف_رقم_الهوية + رقم تسلسلي لتجنب الكتابة فوق القديم
                            $existingCount = Attachment::where('person_identity_number', $sponsorship->identity_number)
                                ->where('file_type', $fileType)
                                ->count();
                            $serial = $existingCount > 0 ? '_' . ($existingCount + 1) : '';
                            $timestamp = date('Ymd_His');
                            $newFileName = "{$fileType}_{$fileIdNumberAttach}_{$sponsorship->identity_number}_{$timestamp}{$serial}.{$extension}";
                            $folder = 'uploads/' . $fileIdNumberAttach;

                            if ($folder === 'public' || $folder === 'public/') {
                                throw new \Exception('خطأ في مسار التخزين: يجب تحديد مجلد فرعي داخل uploads');
                            }

                            $path = $file->storeAs($folder, $newFileName, 'public');

                            // حفظ في قاعدة البيانات
                            Attachment::create([
                                'person_identity_number' => $sponsorship->identity_number,
                                'stored_file_name' => $newFileName,
                                'file_path' => 'storage/' . $path,
                                'file_type' => $docTypeId,
                                'file_size' => $file->getSize(),
                            ]);

                            $uploadedCount++;

                            Log::info('ATTACHMENT_UPLOADED_LOCAL', [
                                'identity' => $sponsorship->identity_number,
                                'doc_type' => $documentType->description,
                                'filename' => $newFileName,
                                'folder' => $folder,
                                'file_size' => $file->getSize(),
                            ]);
                        }
                    }

                    Log::info('LOCAL_STORAGE_ATTACHMENTS_UPLOAD_COMPLETED', [
                        'sponsorship_id' => $sponsorship->id,
                        'identity' => $sponsorship->identity_number,
                        'uploaded_count' => $uploadedCount,
                    ]);

                } else {
                    // ===== استخدام Google Drive API (الطريقة القديمة) =====
                    $driveParentInput = (string) env('GOOGLE_DRIVE_GENERAL_REGISTRATION_PARENT_ID', '');
                    if ($driveParentInput === '') {
                        throw new \Exception('إعداد GOOGLE_DRIVE_GENERAL_REGISTRATION_PARENT_ID غير موجود في .env');
                    }

                    $driveParentId = $this->extractGoogleDriveFolderId($driveParentInput);

                    Log::info('GOOGLE_DRIVE_UPLOAD_START', [
                        'drive_parent_input' => $driveParentInput,
                        'drive_parent_id' => $driveParentId,
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

                    $temporaryFolder = $driveService->getOrCreateFolder($this->sanitizeDriveName('temporary'), $driveParentId);
                    $orgFolder = $driveService->getOrCreateFolder($this->sanitizeDriveName($organizationName), $temporaryFolder['id']);
                    $personFolder = $driveService->getOrCreateFolder($this->sanitizeDriveName($orphanName), $orgFolder['id']);

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
                } // نهاية else (Google Drive API)
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

            // 🆕 تخزين حقول السكن للمتوفين في portal_general_registration_field_values
            if (!empty($housingFieldsForDeceased)) {
                $fileIdNumberForHousing = (string) ($sponsorship->relationData?->file_id_number ?: $sponsorship->relation_id_number ?: $sponsorship->internal_file_number ?: '');
                $identityForHousing = (string) ($sponsorship->identity_number ?: '');

                foreach ($housingFieldsForDeceased as $fieldKey => $fieldValue) {
                    PortalGeneralRegistrationFieldValue::query()->updateOrCreate(
                        [
                            'file_id_number' => $fileIdNumberForHousing,
                            'field_key' => (string) $fieldKey,
                        ],
                        [
                            'sponsorship_id' => $sponsorship->id,
                            'identity_number' => $identityForHousing,
                            'field_value' => is_array($fieldValue) ? json_encode($fieldValue, JSON_UNESCAPED_UNICODE) : (string) $fieldValue,
                            'updated_by_user_id' => $user?->id,
                        ]
                    );
                }

                Log::info('DECEASED_HOUSING_FIELDS_STORED', [
                    'sponsorship_id' => $sponsorship->id,
                    'person_type' => $sponsorship->person_type,
                    'file_id_number' => $fileIdNumberForHousing,
                    'housing_fields_count' => count($housingFieldsForDeceased),
                ]);
            }

            // ✅ تحديث حالة الكفالة بعد حفظ البيانات
            // لا نصل إلى هنا إلا إذا تم حفظ بيانات فعلاً (fields_count > 0 و storedCount > 0)
            $sponsorship->sponsorship_status_id = 2; // 2 = تدقيق
            $sponsorship->save();

            Log::info('SPONSORSHIP_STATUS_UPDATED', [
                'sponsorship_id' => $sponsorship->id,
                'new_status_id' => 2,
                'status_name' => 'تدقيق',
                'portal_fields_stored' => $storedCount,
                'mapped_sponsorship' => count($mappedToSponsorship),
                'mapped_data' => count($mappedToData),
                'mapped_re_people' => count($mappedToRePeople),
            ]);

            DB::commit();

            // 🆕 إضافة Job لإنشاء تقرير PDF إلى الطابور بعد حفظ البيانات
            \App\Jobs\GenerateOrphanReportPdf::dispatch($sponsorship->id);

            Log::info('PDF_REPORT_JOB_DISPATCHED', [
                'sponsorship_id' => $sponsorship->id,
                'relation_id_number' => $sponsorship->relation_id_number,
            ]);

            // ✅ تسجيل خروج مباشرة بعد الحفظ
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return redirect()
                ->route('user.thank.you.page')
                ->with('success', 'تم حفظ التغييرات بنجاح');

        } catch (\Throwable $e) {
            // ✅ رسائل HTTP (403/404/422) يجب أن تصل للعميل كما هي
            // بدل تحويلها إلى رسالة خطأ عام داخل الجلسة
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }

            // 🆕 البند 12: لا نلغي معاملة غير موجودة أصلاً (الخطأ قد يقع قبل beginTransaction)
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('UPDATE_SPONSORSHIP_ERROR', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => Auth::id(),
                'sponsorship_id' => $request->input('sponsorship_id')
            ]);

            // 🆕 البنود 11 و20: رسالة عربية واضحة بدون SQL أو stack trace
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $friendlyMessage = 'تعذر حفظ البيانات: ' . implode(
                    '، ',
                    collect($e->errors())->flatten()->unique()->values()->all()
                );
            } else {
                $friendlyMessage = $this->friendlyErrorMessage($e);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $friendlyMessage);
        }
    }

    /**
     * 🆕 البنود 11 و20: تحويل أي استثناء إلى رسالة عربية ودّية قابلة للعرض للمستخدم.
     * التفاصيل التقنية (SQL، الملف، السطر) تبقى مسجَّلة في Log::error أعلاه.
     */
    private function friendlyErrorMessage(\Throwable $e): string
    {
        $message = trim($e->getMessage());

        $isRawTechnicalError = $e instanceof \Illuminate\Database\QueryException
            || $e instanceof \PDOException
            || $e->getPrevious() instanceof \PDOException
            || str_contains($message, 'SQLSTATE')
            || str_contains($message, 'SQL[');

        if ($isRawTechnicalError) {
            return 'حدث خطأ أثناء حفظ البيانات في قاعدة البيانات ولم يتم حفظ أي تغييرات. يرجى المحاولة مرة أخرى.';
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 'لم يتم العثور على السجل المطلوب. يرجى العودة والمحاولة مرة أخرى.';
        }

        // رسائلنا العربية (المرمية عبر throw) نعرضها للمستخدم كما هي
        if ($message !== '' && preg_match('/\p{Arabic}/u', $message) === 1) {
            return $message;
        }

        return 'حدث خطأ غير متوقع أثناء حفظ البيانات ولم يتم حفظ أي تغييرات. يرجى المحاولة مرة أخرى.';
    }

    /**
     * 🆕 البند 13: هل تدير جمعية الكفالة أي حقل بيانات مفعّل؟
     * يُستخدم فقط للتمييز بين "النموذج لم يرسل fields" (عطل حقيقي)
     * و"الجمعية لا تدير أي حقل" (سلوك صحيح وليس خطأ).
     */
    private function sponsorHasEnabledFields($sponsorship): bool
    {
        if (!$sponsorship || !$sponsorship->sponsor_id) {
            // لا توجد إعدادات مخصصة => النموذج يعرض كل الحقول
            return true;
        }

        $fieldSettings = \App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();
        if (!$fieldSettings) {
            return true;
        }

        $legacyUnmanagedFields = [
            'field_re_guardian_name',
            'field_re_guardian_phone',
            'field_re_guardian_id',
            'field_family_members_count',
            'field_mother_name',
        ];

        foreach ($fieldSettings->getAttributes() as $key => $value) {
            // أقسام المرفقات/أفراد الأسرة والحقول القديمة غير المعروضة لا تُحسب
            if (!str_starts_with($key, 'field_')
                || str_ends_with($key, '_section')
                || in_array($key, $legacyUnmanagedFields, true)) {
                continue;
            }

            if ($value == 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * ✅ التحقق من أن الكفالة المطلوب تعديلها تخص المستخدم الحالي.
     *
     * القاعدة:
     *   Current User → Current Session (active_sponsorship_id) → Verify requested sponsorship_id → Allow
     *
     * يعيد الكفالة عند الصلاحية، ويوقف الطلب بـ 403 عند عدم الصلاحية.
     * لا يغيّر Authentication ولا Session architecture ولا العلاقات.
     */
    private function authorizeSponsorshipForUpdate($sponsorshipId, $user)
    {
        if (!$user) {
            abort(403, 'غير مصرح لك بتعديل بيانات الكفالة');
        }

        if ($sponsorshipId === null || $sponsorshipId === '' || !is_numeric($sponsorshipId)) {
            Log::warning('SPONSORSHIP_UPDATE_BAD_REQUEST', [
                'user_id' => $user->id,
                'requested_sponsorship_id' => $sponsorshipId,
            ]);

            abort(403, 'طلب التعديل غير صالح: معرّف الكفالة غير صحيح');
        }

        $sponsorship = Sponsorship::with(['relationData', 'sponsor'])
            ->where('id', $sponsorshipId)
            ->firstOrFail();

        // 1) الكفالة النشطة في الجلسة (تُخزَّن عند تسجيل الدخول)
        $activeSponsorshipId = session('active_sponsorship_id');
        if ($activeSponsorshipId && (int) $activeSponsorshipId === (int) $sponsorship->id) {
            return $sponsorship;
        }

        // 2) بدون كفالة نشطة في الجلسة: مطابقة هوية المستخدم (نفس مصادر index())
        $userIdNumber = trim((string) ($user->email ?? ''));
        if ($userIdNumber !== ''
            && (
                (string) $sponsorship->identity_number === $userIdNumber
                || (string) $sponsorship->internal_file_number === $userIdNumber
            )
        ) {
            return $sponsorship;
        }

        Log::warning('SPONSORSHIP_UPDATE_FORBIDDEN', [
            'user_id' => $user->id,
            'requested_sponsorship_id' => $sponsorship->id,
            'session_active_sponsorship_id' => $activeSponsorshipId,
        ]);

        abort(403, 'غير مصرح لك بتعديل هذه الكفالة');
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

    /**
     * تحديث اسم المكفول في الجدول الصحيح بناءً على رقم الهوية
     * يتم البحث في: re_people.person_id, data.data_id_number, dead_people.father_id, dead_people.mother_id
     *
     * @param Sponsorship $sponsorship
     * @param string $fullName الاسم الكامل (مدمج)
     * @return void
     */
    private function updateSponsoredPersonName($sponsorship, string $fullName): void
    {
        $identityNumber = $sponsorship->identity_number;

        if (empty($identityNumber) || empty(trim($fullName))) {
            Log::warning('UPDATE_SPONSORED_NAME_SKIPPED', [
                'sponsorship_id' => $sponsorship->id,
                'reason' => 'missing identity_number or fullName',
                'identity_number' => $identityNumber,
                'fullName' => $fullName,
            ]);
            return;
        }

        // تقسيم الاسم إلى 4 أجزاء
        $nameParts = $this->splitFullNameTo4Parts($fullName);

        Log::info('UPDATE_SPONSORED_NAME_ATTEMPT', [
            'sponsorship_id' => $sponsorship->id,
            'identity_number' => $identityNumber,
            'full_name' => $fullName,
            'name_parts' => $nameParts,
        ]);

        $updated = false;

        // 1. البحث في re_people (أفراد الأسرة/المكفولين)
        $rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
        if ($rePerson) {
            DB::table('re_people')
                ->where('id', $rePerson->id)
                ->update([
                    'first_name' => $nameParts['first_name'],
                    'second_name' => $nameParts['second_name'],
                    'third_name' => $nameParts['third_name'],
                    'last_name' => $nameParts['last_name'],
                    'updated_at' => now(),
                ]);

            Log::info('UPDATE_SPONSORED_NAME_SUCCESS', [
                'table' => 're_people',
                'record_id' => $rePerson->id,
                'identity_number' => $identityNumber,
            ]);
            $updated = true;
        }

        // 2. البحث في data (المعيلين)
        $dataRecord = DB::table('data')->where('data_id_number', $identityNumber)->first();
        if ($dataRecord) {
            DB::table('data')
                ->where('id', $dataRecord->id)
                ->update([
                    'data_first_name' => $nameParts['first_name'],
                    'data_father_name' => $nameParts['second_name'],
                    'data_grand_father_name' => $nameParts['third_name'],
                    'data_family_name' => $nameParts['last_name'],
                    'updated_at' => now(),
                ]);

            Log::info('UPDATE_SPONSORED_NAME_SUCCESS', [
                'table' => 'data',
                'record_id' => $dataRecord->id,
                'identity_number' => $identityNumber,
            ]);
            $updated = true;
        }

        // 3. البحث في dead_people (الأب المتوفى)
        $deadFather = DB::table('dead_people')->where('father_id', $identityNumber)->first();
        if ($deadFather) {
            DB::table('dead_people')
                ->where('id', $deadFather->id)
                ->update([
                    'father_first_name' => $nameParts['first_name'],
                    'father_second_name' => $nameParts['second_name'],
                    'father_third_name' => $nameParts['third_name'],
                    'father_last_name' => $nameParts['last_name'],
                    'updated_at' => now(),
                ]);

            Log::info('UPDATE_SPONSORED_NAME_SUCCESS', [
                'table' => 'dead_people (father)',
                'record_id' => $deadFather->id,
                'identity_number' => $identityNumber,
            ]);
            $updated = true;
        }

        // 4. البحث في dead_people (الأم المتوفاة)
        $deadMother = DB::table('dead_people')->where('mother_id', $identityNumber)->first();
        if ($deadMother) {
            DB::table('dead_people')
                ->where('id', $deadMother->id)
                ->update([
                    'mother_first_name' => $nameParts['first_name'],
                    'mother_second_name' => $nameParts['second_name'],
                    'mother_third_name' => $nameParts['third_name'],
                    'mother_last_name' => $nameParts['last_name'],
                    'updated_at' => now(),
                ]);

            Log::info('UPDATE_SPONSORED_NAME_SUCCESS', [
                'table' => 'dead_people (mother)',
                'record_id' => $deadMother->id,
                'identity_number' => $identityNumber,
            ]);
            $updated = true;
        }

        if (!$updated) {
            Log::warning('UPDATE_SPONSORED_NAME_NO_RECORD_FOUND', [
                'sponsorship_id' => $sponsorship->id,
                'identity_number' => $identityNumber,
                'searched_tables' => ['re_people', 'data', 'dead_people'],
            ]);
        }
    }

    /**
     * تقسيم الاسم الكامل إلى 4 أجزاء
     *
     * @param string $fullName
     * @return array ['first_name', 'second_name', 'third_name', 'last_name']
     */
    private function splitFullNameTo4Parts(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
        $parts = explode(' ', $fullName);

        // التأكد من وجود 4 أجزاء على الأقل
        while (count($parts) < 4) {
            $parts[] = '';
        }

        // إذا كان أكثر من 4 أجزاء، ندمج الأجزاء الزائدة في الأخير
        if (count($parts) > 4) {
            $lastParts = array_slice($parts, 3);
            $parts = array_slice($parts, 0, 3);
            $parts[] = implode(' ', $lastParts);
        }

        return [
            'first_name' => $parts[0] ?? '',
            'second_name' => $parts[1] ?? '',
            'third_name' => $parts[2] ?? '',
            'last_name' => $parts[3] ?? '',
        ];
    }

    /**
     * تحديث حقول الأسماء المنفصلة (4 حقول لكل اسم) في الجداول الصحيحة
     * وتحديث بيانات المتوفين (رقم الهوية، تاريخ الوفاة، سبب الوفاة)
     *
     * @param Sponsorship $sponsorship
     * @param array $namesData ['sponsored' => [...], 'father' => [...], 'mother' => [...], 'orphan' => [...]]
     * @param array $fieldsData حقول إضافية (field_father_id, field_father_death_date, إلخ)
     * @return void
     */
    private function updateSeparateNameFields($sponsorship, array $namesData, array $fieldsData = []): void
    {
        $identityNumber = $sponsorship->identity_number;

        // 🆕 استخدام relation_id_number مباشرة للربط مع dead_people
        $relationIdNumber = $sponsorship->relation_id_number;
        if (!$relationIdNumber && $sponsorship->relationData) {
            $relationIdNumber = $sponsorship->relationData->file_id_number;
        }

        Log::info('UPDATE_SEPARATE_NAMES_START', [
            'sponsorship_id' => $sponsorship->id,
            'names_keys' => array_keys($namesData),
            'fields_keys' => array_keys($fieldsData),
            'relation_id_number' => $relationIdNumber,
        ]);

        // 1. تحديث اسم المكفول (sponsored)
        if (isset($namesData['sponsored']) && is_array($namesData['sponsored'])) {
            $names = $namesData['sponsored'];
            $fullName = trim("{$names['first_name']} {$names['second_name']} {$names['third_name']} {$names['last_name']}");

            // تحديث في جدول sponsorships
            $sponsorship->orphan_name = $fullName;

            // البحث في re_people وتحديث الاسم
            $rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
            if ($rePerson) {
                DB::table('re_people')
                    ->where('id', $rePerson->id)
                    ->update([
                        'first_name' => $names['first_name'] ?? '',
                        'second_name' => $names['second_name'] ?? '',
                        'third_name' => $names['third_name'] ?? '',
                        'last_name' => $names['last_name'] ?? '',
                        'updated_at' => now(),
                    ]);
                Log::info('UPDATE_SPONSORED_NAME_RE_PEOPLE', ['id' => $rePerson->id]);
            }

            // البحث في data وتحديث الاسم
            $dataRecord = DB::table('data')->where('data_id_number', $identityNumber)->first();
            if ($dataRecord) {
                DB::table('data')
                    ->where('id', $dataRecord->id)
                    ->update([
                        'data_first_name' => $names['first_name'] ?? '',
                        'data_father_name' => $names['second_name'] ?? '',
                        'data_grand_father_name' => $names['third_name'] ?? '',
                        'data_family_name' => $names['last_name'] ?? '',
                        'updated_at' => now(),
                    ]);
                Log::info('UPDATE_SPONSORED_NAME_DATA', ['id' => $dataRecord->id]);
            }
        }

        // 2. تحديث بيانات الأب المتوفى (father) - باستخدام relation_id_number مباشرة
        // نتحقق إذا كان هناك أي بيانات للأب (من namesData أو fieldsData)
        $hasFatherNames = isset($namesData['father']) && is_array($namesData['father']);
        $hasFatherId = isset($fieldsData['field_father_id']) && !empty($fieldsData['field_father_id']);
        $hasFatherDeathDate = isset($fieldsData['field_father_death_date']) && !empty($fieldsData['field_father_death_date']);
        $hasFatherDeathReason = isset($fieldsData['field_father_death_reason']) && !empty($fieldsData['field_father_death_reason']);

        if (($hasFatherNames || $hasFatherId || $hasFatherDeathDate || $hasFatherDeathReason) && $relationIdNumber) {
            $names = $namesData['father'] ?? [];
            $deadPeople = DB::table('dead_people')
                ->where('re_file_id', $relationIdNumber)
                ->first();

            // تحضير البيانات للتحديث
            $fatherUpdates = [
                'updated_at' => now(),
            ];

            // إضافة الأسماء إذا وجدت
            if (!empty($names)) {
                $fatherUpdates['father_first_name'] = $names['first_name'] ?? '';
                $fatherUpdates['father_second_name'] = $names['second_name'] ?? '';
                $fatherUpdates['father_third_name'] = $names['third_name'] ?? '';
                $fatherUpdates['father_last_name'] = $names['last_name'] ?? '';
            }

            // إضافة رقم الهوية إذا وجد
            if ($hasFatherId) {
                $fatherUpdates['father_id'] = $fieldsData['field_father_id'];
            }

            // إضافة تاريخ الوفاة إذا وجد
            if ($hasFatherDeathDate) {
                $fatherUpdates['father_death_date'] = $fieldsData['field_father_death_date'];
            }

            // إضافة سبب الوفاة إذا وجد (تحويل من نص إلى ID)
            if ($hasFatherDeathReason) {
                $deathReason = $fieldsData['field_father_death_reason'];
                if (!is_numeric($deathReason)) {
                    $reason = DB::table('death_reasons')->where('description', $deathReason)->first();
                    if ($reason) {
                        $fatherUpdates['father_death_reason'] = $reason->id;
                    }
                } else {
                    $fatherUpdates['father_death_reason'] = $deathReason;
                }
            }

            if ($deadPeople) {
                DB::table('dead_people')
                    ->where('id', $deadPeople->id)
                    ->update($fatherUpdates);
                Log::info('UPDATE_FATHER_DEAD_PEOPLE', [
                    'id' => $deadPeople->id,
                    'relation_id_number' => $relationIdNumber,
                    'updated_fields' => array_keys($fatherUpdates)
                ]);
            } else {
                // 🆕 إنشاء سجل dead_people جديد إذا لم يكن موجوداً
                $fatherUpdates['re_file_id'] = $relationIdNumber;
                $fatherUpdates['created_at'] = now();
                $newId = DB::table('dead_people')->insertGetId($fatherUpdates);
                Log::info('CREATE_FATHER_DEAD_PEOPLE', [
                    'new_id' => $newId,
                    'relation_id_number' => $relationIdNumber,
                    'created_fields' => array_keys($fatherUpdates)
                ]);
            }
        }

        // 3. تحديث بيانات الأم المتوفية (mother) - باستخدام relation_id_number مباشرة
        // نتحقق إذا كان هناك أي بيانات للأم (من namesData أو fieldsData)
        $hasMotherNames = isset($namesData['mother']) && is_array($namesData['mother']);
        $hasMotherId = isset($fieldsData['field_mother_id']) && !empty($fieldsData['field_mother_id']);
        $hasMotherDeathDate = isset($fieldsData['field_mother_death_date']) && !empty($fieldsData['field_mother_death_date']);
        $hasMotherDeathReason = isset($fieldsData['field_mother_death_reason']) && !empty($fieldsData['field_mother_death_reason']);

        if (($hasMotherNames || $hasMotherId || $hasMotherDeathDate || $hasMotherDeathReason) && $relationIdNumber) {
            $names = $namesData['mother'] ?? [];
            $deadPeople = DB::table('dead_people')
                ->where('re_file_id', $relationIdNumber)
                ->first();

            // تحضير البيانات للتحديث
            $motherUpdates = [
                'updated_at' => now(),
            ];

            // إضافة الأسماء إذا وجدت
            if (!empty($names)) {
                $motherUpdates['mother_first_name'] = $names['first_name'] ?? '';
                $motherUpdates['mother_second_name'] = $names['second_name'] ?? '';
                $motherUpdates['mother_third_name'] = $names['third_name'] ?? '';
                $motherUpdates['mother_last_name'] = $names['last_name'] ?? '';
            }

            // إضافة رقم الهوية إذا وجد
            if ($hasMotherId) {
                $motherUpdates['mother_id'] = $fieldsData['field_mother_id'];
            }

            // إضافة تاريخ الوفاة إذا وجد
            if ($hasMotherDeathDate) {
                $motherUpdates['mother_death_date'] = $fieldsData['field_mother_death_date'];
            }

            // إضافة سبب الوفاة إذا وجد (تحويل من نص إلى ID)
            if ($hasMotherDeathReason) {
                $deathReason = $fieldsData['field_mother_death_reason'];
                if (!is_numeric($deathReason)) {
                    $reason = DB::table('death_reasons')->where('description', $deathReason)->first();
                    if ($reason) {
                        $motherUpdates['mother_death_reason'] = $reason->id;
                    }
                } else {
                    $motherUpdates['mother_death_reason'] = $deathReason;
                }
            }

            if ($deadPeople) {
                DB::table('dead_people')
                    ->where('id', $deadPeople->id)
                    ->update($motherUpdates);
                Log::info('UPDATE_MOTHER_DEAD_PEOPLE', [
                    'id' => $deadPeople->id,
                    'relation_id_number' => $relationIdNumber,
                    'updated_fields' => array_keys($motherUpdates)
                ]);
            } else {
                // 🆕 إنشاء سجل dead_people جديد إذا لم يكن موجوداً
                $motherUpdates['re_file_id'] = $relationIdNumber;
                $motherUpdates['created_at'] = now();
                $newId = DB::table('dead_people')->insertGetId($motherUpdates);
                Log::info('CREATE_MOTHER_DEAD_PEOPLE', [
                    'new_id' => $newId,
                    'relation_id_number' => $relationIdNumber,
                    'created_fields' => array_keys($motherUpdates)
                ]);
            }
        }

        // 4. تحديث اسم اليتيم (orphan) - أول شخص في re_people
        if (isset($namesData['orphan']) && is_array($namesData['orphan']) && $relationIdNumber) {
            $names = $namesData['orphan'];
            $rePerson = DB::table('re_people')
                ->where('registration_id', $relationIdNumber)
                ->first();

            if ($rePerson) {
                DB::table('re_people')
                    ->where('id', $rePerson->id)
                    ->update([
                        'first_name' => $names['first_name'] ?? '',
                        'second_name' => $names['second_name'] ?? '',
                        'third_name' => $names['third_name'] ?? '',
                        'last_name' => $names['last_name'] ?? '',
                        'updated_at' => now(),
                    ]);
                Log::info('UPDATE_ORPHAN_NAME_RE_PEOPLE', ['id' => $rePerson->id]);
            }
        }
    }

    /**
     * 🔒 ضمان وجود حساب بنكي معتمد واحد فقط لكل guardian_registration
     * هذه الدالة تتأكد من أنه لا يوجد أكثر من حساب معتمد (check_account = 1)
     * لنفس الشخص لمنع التكرار
     *
     * @param string $guardianRegistration رقم تسجيل المعيل
     */
    private function ensureSingleApprovedAccount(string $guardianRegistration): void
    {
        try {
            // عدد الحسابات المعتمدة لهذا الشخص
            $approvedAccounts = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardianRegistration)
                ->where('check_account', 1)
                ->orderBy('updated_at', 'desc')
                ->get();

            if ($approvedAccounts->count() > 1) {
                // الإبقاء على آخر حساب محدث فقط كمعتمد
                $keepId = $approvedAccounts->first()->id;

                DB::table('guardian_bank_accounts')
                    ->where('guardian_registration', $guardianRegistration)
                    ->where('check_account', 1)
                    ->where('id', '!=', $keepId)
                    ->update(['check_account' => 0]);

                Log::info('DUPLICATE_APPROVED_ACCOUNTS_FIXED', [
                    'guardian_registration' => $guardianRegistration,
                    'kept_account_id' => $keepId,
                    'removed_approval_count' => $approvedAccounts->count() - 1
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('ENSURE_SINGLE_APPROVED_ACCOUNT_ERROR', [
                'guardian_registration' => $guardianRegistration,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * البحث في السجل المدني (جدول persons) برقم الهوية
     *
     * @param string $identityNumber رقم الهوية للبحث عنه
     * @return array|null بيانات الشخص إذا وُجد، أو null
     */
    private function searchCivilRegistry(string $identityNumber): ?array
    {
        try {
            // استخدام اتصال civilregistry للبحث في السجل المدني
            $person = DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $identityNumber)
                ->first();

            if (!$person) {
                return null;
            }

            // تحويل رمز الجنس إلى نص
            $genderText = '';
            if (isset($person->CI_SEX_CD)) {
                if ($person->CI_SEX_CD == 1 || $person->CI_SEX_CD == 'M') {
                    $genderText = 'ذكر';
                } elseif ($person->CI_SEX_CD == 2 || $person->CI_SEX_CD == 'F') {
                    $genderText = 'أنثى';
                }
            }

            return [
                'identity_number' => $person->CI_ID_NUM ?? '',
                'first_name' => $person->CI_FIRST_ARB ?? '',
                'second_name' => $person->CI_FATHER_ARB ?? '',
                'third_name' => $person->CI_GRAND_FATHER_ARB ?? '',
                'last_name' => $person->CI_FAMILY_ARB ?? '',
                'mother_name' => $person->MOTHER_NAME1 ?? '',
                'birth_date' => $person->CI_BIRTH_DT ?? '',
                'gender' => $genderText,
                'city' => $person->CITY ?? '',
            ];

        } catch (\Throwable $e) {
            Log::warning('CIVIL_REGISTRY_SEARCH_ERROR', [
                'identity' => $identityNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * البحث عن بيانات الأب أو الأم من السجل المدني برقم الهوية
     *
     * @param string $identityNumber رقم هوية الأب أو الأم
     * @return array|null بيانات الشخص المتوفى
     */
    public function searchDeadPersonInCivilRegistry(Request $request)
    {
        $identityNumber = $request->input('identity_number');
        $personType = $request->input('person_type', 'father'); // father or mother

        if (empty($identityNumber)) {
            return response()->json(['success' => false, 'message' => 'رقم الهوية مطلوب']);
        }

        // ================================================
        // 🆕 أولاً: البحث عن المعيل في قاعدة البيانات المركزية (جدول data)
        // عند وجود ملف للمعيل تُجلب بياناته وجميع المرتبطين بالملف
        // ================================================
        if ($personType === 'guardian') {
            $guardianFileService = app(GuardianFileService::class);
            $guardianRecord = $guardianFileService->findLatestDataByIdentity((string) $identityNumber);

            if ($guardianRecord) {
                Log::info('GUARDIAN_FETCHED_FROM_DATA_TABLE', [
                    'identity' => $identityNumber,
                    'file_id_number' => $guardianRecord->file_id_number,
                ]);

                return response()->json([
                    'success' => true,
                    'source' => 'data',
                    'data' => $guardianFileService->buildGuardianPayload($guardianRecord),
                    'linked' => $guardianFileService->buildLinkedFilePayload((string) $guardianRecord->file_id_number),
                ]);
            }
        }

        // غير ذلك: البحث في السجل المدني كالمعتاد (بدون أي تغيير)
        $civilData = $this->searchCivilRegistry($identityNumber);

        if (!$civilData) {
            return response()->json(['success' => false, 'message' => 'لم يلا يوجد بيانات لهذه الأسرة']);
        }

        return response()->json([
            'success' => true,
            'source' => 'civil_registry',
            'data' => [
                'first_name' => $civilData['first_name'],
                'second_name' => $civilData['second_name'],
                'third_name' => $civilData['third_name'],
                'last_name' => $civilData['last_name'],
                'birth_date' => $civilData['birth_date'],
                'person_type' => $personType
            ]
        ]);
    }

    /**
     * البحث في السجل المدني عبر AJAX (GET request)
     * يُستخدم لأفراد العائلة والبحث التلقائي
     */
    public function searchCivilRegistryAjax(Request $request)
    {
        $identityNumber = $request->input('identity_number');

        if (empty($identityNumber) || strlen($identityNumber) < 8) {
            return response()->json(['found' => false, 'message' => 'رقم الهوية غير صالح']);
        }

        $civilData = $this->searchCivilRegistry($identityNumber);

        if (!$civilData) {
            return response()->json(['found' => false, 'message' => 'لم يتم العثور على بيانات']);
        }

        return response()->json([
            'found' => true,
            'first_name' => $civilData['first_name'] ?? '',
            'second_name' => $civilData['second_name'] ?? '',
            'third_name' => $civilData['third_name'] ?? '',
            'last_name' => $civilData['last_name'] ?? '',
            'birth_date' => $civilData['birth_date'] ?? '',
            'gender' => $civilData['gender'] ?? '',
        ]);
    }

    /**
     * عرض ملف مرفق من Google Drive عبر Rclone
     *
     * @param int $attachmentId معرف المرفق
     * @return \Illuminate\Http\Response
     */
    public function serveRcloneAttachment($attachmentId)
    {
        $attachment = Attachment::find($attachmentId);

        if (!$attachment) {
            abort(404, 'المرفق غير موجود');
        }

        $filePath = $attachment->file_path;

        // التحقق مما إذا كان مسار Rclone
        if (!$this->isRclonePath($filePath)) {
            // إذا كان مسار محلي، قم بإعادة التوجيه إليه
            return redirect(asset('storage/' . $filePath));
        }

        // استخدام Rclone لتحميل الملف
        $rcloneService = new RcloneGoogleDriveService();
        $result = $rcloneService->getFileContent($filePath);

        if (!$result['success']) {
            Log::warning('RCLONE_FILE_NOT_FOUND', [
                'attachment_id' => $attachmentId,
                'file_path' => $filePath,
                'error' => $result['message'] ?? 'Unknown error'
            ]);
            abort(404, 'الملف غير موجود في Google Drive');
        }

        // إرجاع الملف مع نوع MIME الصحيح
        return response($result['content'])
            ->header('Content-Type', $result['mime_type'])
            ->header('Content-Disposition', 'inline; filename="' . $attachment->stored_file_name . '"')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * التحقق مما إذا كان المسار هو مسار Rclone
     *
     * @param string $path المسار للتحقق منه
     * @return bool
     */
    private function isRclonePath(string $path): bool
    {
        // مسارات Rclone تحتوي على ":" بعد اسم الـ remote
        // مثال: alhayah:temp/folder/file.jpg
        return preg_match('/^[a-zA-Z0-9_-]+:/', $path) === 1;
    }

    /**
     * توليد رقم ملف جديد فريد
     * يتم البحث عن أعلى رقم ملف في الجداول الثلاثة (data, re_people, dead_people)
     * ثم إضافة 1 للحصول على الرقم الجديد
     *
     * @return string رقم الملف الجديد بتنسيق 6 أرقام (مثل: 002666)
     */
    private function generateNewFileNumber(): string
    {
        // البحث عن أعلى رقم في جميع الجداول
        $maxFromData = DB::table('data')
            ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
            ->max(DB::raw('CAST(file_id_number AS UNSIGNED)'));

        $maxFromDeadPeople = DB::table('dead_people')
            ->whereRaw("re_file_id REGEXP '^[0-9]+$'")
            ->max(DB::raw('CAST(re_file_id AS UNSIGNED)'));

        $maxFromRePeople = DB::table('re_people')
            ->whereRaw("registration_id REGEXP '^[0-9]+$'")
            ->max(DB::raw('CAST(registration_id AS UNSIGNED)'));

        // حساب الرقم الجديد
        $nextNumber = max(
            intval($maxFromData ?? 0),
            intval($maxFromDeadPeople ?? 0),
            intval($maxFromRePeople ?? 0)
        ) + 1;

        // تنسيق الرقم كـ 6 أرقام مع أصفار في البداية
        $formattedNumber = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        Log::info('GENERATED_NEW_FILE_NUMBER', [
            'max_from_data' => $maxFromData,
            'max_from_dead_people' => $maxFromDeadPeople,
            'max_from_re_people' => $maxFromRePeople,
            'new_number' => $formattedNumber
        ]);

        return $formattedNumber;
    }

    /**
     * ✅ إسقاط القيم غير الآمنة من الحقول المعدَّلة قبل الحفظ (البند 6).
     *
     * القواعد:
     *   - عمود نصّي (char/text/enum/json...) : '' مسموح، وnull مسموح فقط إذا كان العمود nullable.
     *   - عمود رقمي أو تاريخي (int/bigint/date/datetime...) :
     *       '' → NULL إذا كان العمود nullable، وإلا تُسقط القيمة وتبقى القيمة القديمة.
     *
     * لا يغيّر بنية القاعدة ولا نوع أي عمود - فقط يمنع فشل الحفظ بخطأ SQL.
     */
    private function dropUnsafeDirtyAttributes($model, string $table): void
    {
        static $columnsMeta = [];

        if (!array_key_exists($table, $columnsMeta)) {
            $meta = [];

            try {
                foreach (Schema::getColumns($table) as $column) {
                    $name = (string) ($column['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $typeName = strtolower((string) ($column['type_name'] ?? ''));
                    $nullableRaw = $column['nullable'] ?? false;
                    // Schema::getColumns() يعيد nullable كـ bool، وقد يكون نص YES/NO في بعض الإصدارات
                    $isNullable = is_bool($nullableRaw)
                        ? $nullableRaw
                        : strtoupper((string) $nullableRaw) === 'YES';
                    $isNumericOrTemporal = (bool) preg_match(
                        '/^(tinyint|smallint|mediumint|bigint|int|integer|decimal|numeric|float|double|bit|date|time|datetime|timestamp|year)$/',
                        $typeName
                    );

                    $meta[$name] = [
                        'nullable' => $isNullable,
                        'is_string' => !$isNumericOrTemporal,
                    ];
                }
            } catch (\Throwable $e) {
                // تعذّرت قراءة بنية الجدول: لا نغيّر السلوك الحالي
                $meta = [];
                Log::warning('COLUMN_META_UNAVAILABLE', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }

            $columnsMeta[$table] = $meta;
        }

        $meta = $columnsMeta[$table];

        if ($meta === [] || !$model->isDirty()) {
            return;
        }

        $attributes = $model->getAttributes();
        $dropped = [];
        $converted = [];

        foreach ($model->getDirty() as $column => $value) {
            if (!isset($meta[$column])) {
                continue;
            }

            $isNullable = $meta[$column]['nullable'];
            $isString = $meta[$column]['is_string'];

            if ($value === null) {
                if ($isNullable) {
                    continue;
                }

                $dropped[$column] = 'null';
            } elseif ($value === '') {
                if ($isString) {
                    continue; // '' صالح داخل الأعمدة النصية
                }

                if ($isNullable) {
                    $attributes[$column] = null;
                    $converted[$column] = "''→NULL";
                    continue;
                }

                $dropped[$column] = "''";
            } else {
                continue;
            }

            unset($attributes[$column]);
        }

        if ($dropped === [] && $converted === []) {
            return;
        }

        $model->setRawAttributes($attributes, false);

        if ($dropped !== []) {
            Log::warning('UNSAFE_COLUMN_VALUE_DROPPED', [
                'table' => $table,
                'columns' => $dropped,
                'note' => 'العمود لا يسمح بالقيمة الفارغة - تم إبقاء القيمة القديمة بدل فشل الحفظ',
            ]);
        }

        if ($converted !== []) {
            Log::info('EMPTY_STRING_CONVERTED_TO_NULL', [
                'table' => $table,
                'columns' => $converted,
            ]);
        }
    }

    /**
     * 🆕 إدراج سجل في جدول data أو تحديث السجل الموجود بنفس رقم الملف
     * يُستخدم عند إعادة استخدام ملف معيل موجود بدلاً من توليد رقم جديد
     * لتفادي انتهاك فهرس file_id_number الفريد ومنع تكرار المعيل
     */
    private function insertOrUpdateDataRecord(array $dataRecord, ?string $reusedFileNumber): int
    {
        // ✅ إصلاح 1364: data_section_id عمود NOT NULL بدون default في جدول data،
        // وغيابه يمنع إنشاء أي سجل جديد. نفس المنطق المعتمد في المسارات الصحيحة:
        //   Admin\SponsorshipController      => 'data_section_id' => 1
        //   General/Mobile Registration      => من الطلب إن وُجد (required|integer) وإلا 1
        if (!isset($dataRecord['data_section_id']) || $dataRecord['data_section_id'] === null || $dataRecord['data_section_id'] === '') {
            $dataRecord['data_section_id'] = 1;
        }

        $fileIdNumber = (string) ($dataRecord['file_id_number'] ?? '');

        if (!empty($reusedFileNumber) && $fileIdNumber !== '' && $fileIdNumber === $reusedFileNumber) {
            $existing = DB::table('data')->where('file_id_number', $fileIdNumber)->first();

            if ($existing) {
                $recordIdentity = trim((string) ($dataRecord['data_id_number'] ?? ''));
                $existingIdentity = trim((string) ($existing->data_id_number ?? ''));

                // حماية: لا نمحو هوية المعيل المخزنة إذا كانت هوية الطلب فارغة
                if ($recordIdentity === '' && $existingIdentity !== '') {
                    unset($dataRecord['data_id_number']);
                    $recordIdentity = $existingIdentity;
                }

                $identitiesMismatch = $recordIdentity !== ''
                    && $existingIdentity !== ''
                    && $recordIdentity !== $existingIdentity;

                if ($identitiesMismatch) {
                    // حماية: الملف يخص هوية أخرى - لا ن overwritten، نُنشئ ملفاً جديداً
                    Log::warning('DATA_RECORD_IDENTITY_MISMATCH', [
                        'file_id_number' => $fileIdNumber,
                        'existing_identity' => $existingIdentity,
                        'record_identity' => $recordIdentity,
                    ]);

                    $dataRecord['file_id_number'] = $this->generateNewFileNumber();
                } else {
                    $updates = $dataRecord;
                    unset($updates['created_at']);
                    $updates['updated_at'] = now();

                    DB::table('data')->where('id', $existing->id)->update($updates);

                    Log::info('UPDATED_EXISTING_DATA_RECORD', [
                        'data_id' => $existing->id,
                        'file_id_number' => $fileIdNumber,
                    ]);

                    return (int) $existing->id;
                }
            }
        }

        return (int) DB::table('data')->insertGetId($dataRecord);
    }

    /**
     * 🆕 إدراج فرد أسرة في re_people أو تحديث السجل الموجود بنفس الملف والهوية
     */
    private function insertOrUpdateRePeopleRecord(array $rePeopleRecord, ?string $reusedFileNumber): int
    {
        $fileIdNumber = (string) ($rePeopleRecord['registration_id'] ?? '');
        $personId = trim((string) ($rePeopleRecord['person_id'] ?? ''));

        // ✅ قاعدة عدم التكرار: الفرد الموجود بنفس الملف والهوية يُحدَّث ولا يُضاف من جديد
        // (سابقاً كان الفحص مشروطاً بتطابق الملف مع $reusedFileNumber، فتُنشَأ نسخة ثانية
        //  عند توليد رقم ملف جديد ثم إعادة ربطه لاحقاً عبر GUARDIAN_FILE_RELINK)
        if ($fileIdNumber !== '' && $personId !== '') {
            $existing = DB::table('re_people')
                ->where('registration_id', $fileIdNumber)
                ->where('person_id', $personId)
                ->first();

            if ($existing) {
                $updates = $rePeopleRecord;
                unset($updates['created_at']);
                $updates['updated_at'] = now();

                DB::table('re_people')->where('id', $existing->id)->update($updates);

                Log::info('UPDATED_EXISTING_RE_PEOPLE_RECORD', [
                    're_people_id' => $existing->id,
                    'registration_id' => $fileIdNumber,
                    'person_id' => $personId,
                ]);

                return (int) $existing->id;
            }
        }

        return (int) DB::table('re_people')->insertGetId($rePeopleRecord);
    }

    /**
     * ✅ قاعدة عدم التكرار لسجل المتوفى (dead_people)
     *
     * يبحث أولاً عن سجل لنفس رقم الملف، ثم عن سجل للملف المرتبط بالكفالة
     * ($fallbackRelationFile)؛ فإن وجد ← تحديثه بمحتوى السجل الجديد، وإلا ← إنشاء سجل.
     * نفس منطق المساعدات القائمة (تحديث/إنشاء حسب re_file_id) لكن بلا إنشاء ثانٍ
     * عند توليد رقم ملف جديد ثم إعادة ربطه لاحقاً.
     */
    private function upsertDeadPeopleForFile(array $deadPeopleRecord, ?string $fallbackRelationFile): int
    {
        $fileIdNumber = trim((string) ($deadPeopleRecord['re_file_id'] ?? ''));

        $existing = null;

        if ($fileIdNumber !== '') {
            $existing = DB::table('dead_people')
                ->where('re_file_id', $fileIdNumber)
                ->first();
        }

        $fallback = trim((string) ($fallbackRelationFile ?? ''));

        if (!$existing && $fallback !== '' && $fallback !== $fileIdNumber) {
            $existing = DB::table('dead_people')
                ->where('re_file_id', $fallback)
                ->first();
        }

        if (!$existing) {
            return (int) DB::table('dead_people')->insertGetId($deadPeopleRecord);
        }

        $updates = $deadPeopleRecord;
        unset($updates['re_file_id'], $updates['created_at']);
        $updates['updated_at'] = now();

        DB::table('dead_people')->where('id', $existing->id)->update($updates);

        Log::info('DEAD_PEOPLE_UPDATED_EXISTING', [
            'dead_people_id' => $existing->id,
            'existing_re_file_id' => $existing->re_file_id,
            'submitted_re_file_id' => $fileIdNumber,
            'fallback_relation_file' => $fallback,
            'updated_fields' => array_keys($updates),
        ]);

        return (int) $existing->id;
    }

    /**
     * إنشاء السجلات المركزية للمكفول الجديد
     * 🆕 يتم الآن إنشاء السجل في الجدول الصحيح فقط بناءً على person_type:
     * - breadwinner: جدول data
     * - family_member: جدول re_people
     * - deceased_father/deceased_mother: جدول dead_people
     *
     * @param Sponsorship $sponsorship الكفالة
     * @param array $namesData بيانات الأسماء المنفصلة
     * @param array $fieldsData بيانات الحقول الأخرى
     * @return string رقم الملف الجديد
     */
    private function createCentralDataRecords(
        Sponsorship $sponsorship,
        array $namesData,
        array $fieldsData,
        ?string $knownGuardianFile = null,
        ?string $knownGuardianIdentity = null
    ): string {
        $personType = $sponsorship->person_type;
        $identityNumber = $sponsorship->identity_number;

        // ================================================
        // 🆕 تحديد هوية المعيل بشكل صحيح قبل أي إنشاء
        // الأهم: لـ family_member هو identity_number المكفول وليس المعيل،
        // فلا يجوز استخدامه أبداً للبحث عن ملف المعيل
        // ================================================
        $guardianIdentityCandidates = [];

        if ($knownGuardianIdentity !== null && trim($knownGuardianIdentity) !== '') {
            $guardianIdentityCandidates[] = trim($knownGuardianIdentity);
        }

        if ($personType === 'breadwinner' && trim((string) $identityNumber) !== '') {
            $guardianIdentityCandidates[] = trim((string) $identityNumber);
        }

        if (trim((string) ($sponsorship->guardian_identity_number ?? '')) !== '') {
            $guardianIdentityCandidates[] = trim((string) $sponsorship->guardian_identity_number);
        }

        $guardianIdentityForRecord = $guardianIdentityCandidates[0] ?? '';

        // ================================================
        // 🆕 البحث عن ملف المعيل الموجود مسبقاً قبل توليد رقم جديد
        // عند وجود ملف للمعيل يتم إعادة استخدامه بدل إنشاء ملف مكرر
        // ================================================
        $reusedGuardianFile = null;

        if (in_array($personType, ['breadwinner', 'family_member'], true)) {
            if ($knownGuardianFile !== null && $knownGuardianFile !== '') {
                $reusedGuardianFile = $knownGuardianFile;
            } elseif ($guardianIdentityForRecord !== '') {
                $reusedGuardianFile = app(GuardianFileService::class)->findFileByIdentity($guardianIdentityForRecord);
            }
        }

        // توليد رقم ملف جديد فقط إذا لم يتوفر ملف للمعيل
        $newFileNumber = $reusedGuardianFile ?: $this->generateNewFileNumber();

        Log::info('CREATING_CENTRAL_DATA_RECORDS', [
            'sponsorship_id' => $sponsorship->id,
            'identity_number' => $identityNumber,
            'new_file_number' => $newFileNumber,
            'reused_guardian_file' => $reusedGuardianFile,
            'guardian_identity_for_record' => $guardianIdentityForRecord,
            'person_type' => $personType,
            'names_data_keys' => array_keys($namesData),
        ]);

        // 🆕 تحديد الجدول الصحيح بناءً على person_type
        if ($personType === 'breadwinner') {
            // === المعيل: إنشاء سجل في جدول data فقط ===
            $dataRecord = [
                'file_id_number' => $newFileNumber,
                'data_id_number' => $identityNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // إضافة اسم المعيل من الأسماء المنفصلة
            if (isset($namesData['sponsored']) && is_array($namesData['sponsored'])) {
                $sponsoredNames = $namesData['sponsored'];
                $dataRecord['data_first_name'] = $sponsoredNames['first_name'] ?? '';
                $dataRecord['data_father_name'] = $sponsoredNames['second_name'] ?? '';
                $dataRecord['data_grand_father_name'] = $sponsoredNames['third_name'] ?? '';
                $dataRecord['data_family_name'] = $sponsoredNames['last_name'] ?? '';

                // تحديث orphan_name في sponsorship
                $fullName = trim("{$sponsoredNames['first_name']} {$sponsoredNames['second_name']} {$sponsoredNames['third_name']} {$sponsoredNames['last_name']}");
                if (!empty($fullName)) {
                    $sponsorship->orphan_name = $fullName;
                }
            } elseif ($sponsorship->orphan_name) {
                $nameParts = explode(' ', $sponsorship->orphan_name);
                $dataRecord['data_first_name'] = $nameParts[0] ?? '';
                $dataRecord['data_father_name'] = $nameParts[1] ?? '';
                $dataRecord['data_grand_father_name'] = $nameParts[2] ?? '';
                $dataRecord['data_family_name'] = $nameParts[3] ?? '';
            }

            // إضافة تاريخ الميلاد
            if (isset($fieldsData['field_person_birth_date']) && $fieldsData['field_person_birth_date'] !== '') {
                $dataRecord['data_birth_date'] = $fieldsData['field_person_birth_date'];
                $sponsorship->sponsored_birth_date = $fieldsData['field_person_birth_date'];
            }

            // إضافة الحقول الإضافية
            $dataFieldsMapping = [
                'field_data_phone_number' => 'data_phone_number',
            ];

            foreach ($dataFieldsMapping as $fieldKey => $dbColumn) {
                if (isset($fieldsData[$fieldKey]) && $fieldsData[$fieldKey] !== '') {
                    $dataRecord[$dbColumn] = $fieldsData[$fieldKey];
                }
            }

            // تحويل حقول lookup من نصوص إلى IDs
            // تحويل المحافظة
            if (isset($fieldsData['field_data_province']) && $fieldsData['field_data_province'] !== '') {
                $provinceId = DB::table('provinces')->where('description', $fieldsData['field_data_province'])->value('id');
                if ($provinceId !== null) {
                    $dataRecord['data_province'] = $provinceId;
                }
            }
            // تحويل المدينة
            if (isset($fieldsData['field_data_city']) && $fieldsData['field_data_city'] !== '') {
                $cityId = DB::table('city')->where('city', $fieldsData['field_data_city'])->value('id');
                if ($cityId !== null) {
                    $dataRecord['data_city'] = $cityId;
                }
            }
            if (isset($fieldsData['field_health_status']) && $fieldsData['field_health_status'] !== '') {
                $healthStatusId = $this->resolveLookupIdByDescription('health_statuses', $fieldsData['field_health_status']);
                if ($healthStatusId !== null) {
                    $dataRecord['data_health_status'] = $healthStatusId;
                }
            }
            if (isset($fieldsData['field_housing_status']) && $fieldsData['field_housing_status'] !== '') {
                $housingStatusId = $this->resolveLookupIdByDescription('housing_status', $fieldsData['field_housing_status']);
                if ($housingStatusId !== null) {
                    $dataRecord['data_housing_status'] = $housingStatusId;
                }
            }
            if (isset($fieldsData['field_housing_type']) && $fieldsData['field_housing_type'] !== '') {
                $housingTypeId = $this->resolveLookupIdByDescription('type_of_accommodation', $fieldsData['field_housing_type']);
                if ($housingTypeId !== null) {
                    $dataRecord['data_current_housing_type'] = $housingTypeId;
                }
            }

            $dataId = $this->insertOrUpdateDataRecord($dataRecord, $reusedGuardianFile);

            Log::info('CREATED_DATA_RECORD_FOR_BREADWINNER', [
                'data_id' => $dataId,
                'file_id_number' => $newFileNumber
            ]);

        } elseif ($personType === 'family_member') {
            // === فرد عائلة: إنشاء سجل في جدول data (للمعيل) + re_people (للفرد) ===

            // 1. إنشاء سجل المعيل في data
            // هوية المعيل المحفوظة في الكفالة قد تكون فارغة - نعتمد هوية النموذج أولاً
            $guardianIdNumber = $guardianIdentityForRecord !== ''
                ? $guardianIdentityForRecord
                : $sponsorship->guardian_identity_number;

            // التحقق من أن رقم هوية المعيل ليس فارغًا
            if (empty($guardianIdNumber)) {
                $guardianIdNumber = null;
            }

            $dataRecord = [
                'file_id_number' => $newFileNumber,
                'data_id_number' => $guardianIdNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (isset($namesData['guardian']) && is_array($namesData['guardian'])) {
                $guardianNames = $namesData['guardian'];
                $dataRecord['data_first_name'] = $guardianNames['first_name'] ?? '';
                $dataRecord['data_father_name'] = $guardianNames['second_name'] ?? '';
                $dataRecord['data_grand_father_name'] = $guardianNames['third_name'] ?? '';
                $dataRecord['data_family_name'] = $guardianNames['last_name'] ?? '';

                $fullGuardianName = trim("{$guardianNames['first_name']} {$guardianNames['second_name']} {$guardianNames['third_name']} {$guardianNames['last_name']}");
                if (!empty($fullGuardianName)) {
                    $sponsorship->guardian_name = $fullGuardianName;
                }
            } elseif ($sponsorship->guardian_name) {
                $nameParts = explode(' ', $sponsorship->guardian_name);
                $dataRecord['data_first_name'] = $nameParts[0] ?? '';
                $dataRecord['data_father_name'] = $nameParts[1] ?? '';
                $dataRecord['data_grand_father_name'] = $nameParts[2] ?? '';
                $dataRecord['data_family_name'] = $nameParts[3] ?? '';
            }

            // إضافة الحقول الإضافية للمعيل
            $dataFieldsMapping = [
                'field_data_phone_number' => 'data_phone_number',
            ];

            foreach ($dataFieldsMapping as $fieldKey => $dbColumn) {
                if (isset($fieldsData[$fieldKey]) && $fieldsData[$fieldKey] !== '') {
                    $dataRecord[$dbColumn] = $fieldsData[$fieldKey];
                }
            }

            // تحويل حقول lookup من نصوص إلى IDs
            // تحويل المحافظة
            if (isset($fieldsData['field_data_province']) && $fieldsData['field_data_province'] !== '') {
                $provinceId = DB::table('provinces')->where('description', $fieldsData['field_data_province'])->value('id');
                if ($provinceId !== null) {
                    $dataRecord['data_province'] = $provinceId;
                }
            }
            // تحويل المدينة
            if (isset($fieldsData['field_data_city']) && $fieldsData['field_data_city'] !== '') {
                $cityId = DB::table('city')->where('city', $fieldsData['field_data_city'])->value('id');
                if ($cityId !== null) {
                    $dataRecord['data_city'] = $cityId;
                }
            }
            if (isset($fieldsData['field_health_status']) && $fieldsData['field_health_status'] !== '') {
                $healthStatusId = $this->resolveLookupIdByDescription('health_statuses', $fieldsData['field_health_status']);
                if ($healthStatusId !== null) {
                    $dataRecord['data_health_status'] = $healthStatusId;
                }
            }
            if (isset($fieldsData['field_housing_status']) && $fieldsData['field_housing_status'] !== '') {
                $housingStatusId = $this->resolveLookupIdByDescription('housing_status', $fieldsData['field_housing_status']);
                if ($housingStatusId !== null) {
                    $dataRecord['data_housing_status'] = $housingStatusId;
                }
            }
            if (isset($fieldsData['field_housing_type']) && $fieldsData['field_housing_type'] !== '') {
                $housingTypeId = $this->resolveLookupIdByDescription('type_of_accommodation', $fieldsData['field_housing_type']);
                if ($housingTypeId !== null) {
                    $dataRecord['data_current_housing_type'] = $housingTypeId;
                }
            }

            $dataId = $this->insertOrUpdateDataRecord($dataRecord, $reusedGuardianFile);

            // 2. إنشاء سجل فرد العائلة في re_people
            $rePeopleRecord = [
                'registration_id' => $newFileNumber,
                'person_id' => $identityNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (isset($namesData['sponsored']) && is_array($namesData['sponsored'])) {
                $sponsoredNames = $namesData['sponsored'];
                $rePeopleRecord['first_name'] = $sponsoredNames['first_name'] ?? '';
                $rePeopleRecord['second_name'] = $sponsoredNames['second_name'] ?? '';
                $rePeopleRecord['third_name'] = $sponsoredNames['third_name'] ?? '';
                $rePeopleRecord['last_name'] = $sponsoredNames['last_name'] ?? '';

                $fullSponsoredName = trim("{$sponsoredNames['first_name']} {$sponsoredNames['second_name']} {$sponsoredNames['third_name']} {$sponsoredNames['last_name']}");
                if (!empty($fullSponsoredName)) {
                    $sponsorship->orphan_name = $fullSponsoredName;
                }
            } elseif ($sponsorship->orphan_name) {
                $nameParts = explode(' ', $sponsorship->orphan_name);
                $rePeopleRecord['first_name'] = $nameParts[0] ?? '';
                $rePeopleRecord['second_name'] = $nameParts[1] ?? '';
                $rePeopleRecord['third_name'] = $nameParts[2] ?? '';
                $rePeopleRecord['last_name'] = $nameParts[3] ?? '';
            }

            // تاريخ الميلاد
            if (isset($fieldsData['field_person_birth_date']) && $fieldsData['field_person_birth_date'] !== '') {
                $rePeopleRecord['person_birth_date'] = $fieldsData['field_person_birth_date'];
                $sponsorship->sponsored_birth_date = $fieldsData['field_person_birth_date'];
            }

            // الجنس
            if (isset($fieldsData['field_person_gender'])) {
                $gender = $fieldsData['field_person_gender'];
                if ($gender == 'ذكر' || $gender == 1) {
                    $rePeopleRecord['person_gender'] = 1;
                } elseif ($gender == 'أنثى' || $gender == 2) {
                    $rePeopleRecord['person_gender'] = 2;
                }
            }

            // ✅ حفظ الحالة الصحية للمكفول (family_member) في re_people.person_health_status
            // الحقل الصحيح: field_health_status من النموذج name="fields[field_health_status]"
            if (isset($fieldsData['field_health_status']) && $fieldsData['field_health_status'] !== '') {
                $healthStatusId = $this->resolveLookupIdByDescription('health_statuses', $fieldsData['field_health_status']);
                if ($healthStatusId !== null) {
                    $rePeopleRecord['person_health_status'] = $healthStatusId;
                    Log::info('SAVING_ORPHAN_HEALTH_TO_RE_PEOPLE', [
                        'person_id' => $identityNumber,
                        'health_status_id' => $healthStatusId,
                        'health_status_text' => $fieldsData['field_health_status']
                    ]);
                }
            }

            $rePeopleId = $this->insertOrUpdateRePeopleRecord($rePeopleRecord, $reusedGuardianFile);

            Log::info('CREATED_RECORDS_FOR_FAMILY_MEMBER', [
                'data_id' => $dataId,
                're_people_id' => $rePeopleId,
                'file_id_number' => $newFileNumber
            ]);

        } elseif (in_array($personType, ['deceased_father', 'deceased_mother'])) {
            // === أب متوفي أو أم متوفية ===
            // 🆕 للمتوفين: لا يتم إنشاء سجل في جدول data
            // 🆕 يتم إنشاء سجل في dead_people فقط مع رقم ملف جديد
            // 🆕 بيانات السكن تُخزن في portal_general_registration_field_values
            // 🆕 يتم ربط أفراد الأسرة لاحقاً برقم ملف المتوفي (re_file_id)

            // تحديد الأعمدة الصحيحة بناءً على نوع المتوفي
            $prefix = ($personType === 'deceased_father') ? 'father' : 'mother';

            // 1. إنشاء سجل المتوفي في dead_people (بدون إنشاء سجل في data)
            $deadPeopleRecord = [
                're_file_id' => $newFileNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // إضافة اسم المتوفي
            if (isset($namesData['sponsored']) && is_array($namesData['sponsored'])) {
                $sponsoredNames = $namesData['sponsored'];
                $deadPeopleRecord[$prefix . '_first_name'] = $sponsoredNames['first_name'] ?? '';
                $deadPeopleRecord[$prefix . '_second_name'] = $sponsoredNames['second_name'] ?? '';
                $deadPeopleRecord[$prefix . '_third_name'] = $sponsoredNames['third_name'] ?? '';
                $deadPeopleRecord[$prefix . '_last_name'] = $sponsoredNames['last_name'] ?? '';

                $fullName = trim("{$sponsoredNames['first_name']} {$sponsoredNames['second_name']} {$sponsoredNames['third_name']} {$sponsoredNames['last_name']}");
                if (!empty($fullName)) {
                    $sponsorship->orphan_name = $fullName;
                }
            } elseif ($sponsorship->orphan_name) {
                $nameParts = explode(' ', $sponsorship->orphan_name);
                $deadPeopleRecord[$prefix . '_first_name'] = $nameParts[0] ?? '';
                $deadPeopleRecord[$prefix . '_second_name'] = $nameParts[1] ?? '';
                $deadPeopleRecord[$prefix . '_third_name'] = $nameParts[2] ?? '';
                $deadPeopleRecord[$prefix . '_last_name'] = $nameParts[3] ?? '';
            }

            // رقم الهوية
            $deadPeopleRecord[$prefix . '_id'] = $identityNumber;

            // تاريخ الوفاة وسبب الوفاة
            if (isset($fieldsData['field_' . $prefix . '_death_date']) && $fieldsData['field_' . $prefix . '_death_date'] !== '') {
                $deadPeopleRecord[$prefix . '_death_date'] = $fieldsData['field_' . $prefix . '_death_date'];
            }

            if (isset($fieldsData['field_' . $prefix . '_death_reason']) && $fieldsData['field_' . $prefix . '_death_reason'] !== '') {
                $deathReason = $fieldsData['field_' . $prefix . '_death_reason'];
                if (!is_numeric($deathReason)) {
                    $reason = DB::table('death_reasons')->where('description', $deathReason)->first();
                    if ($reason) {
                        $deathReason = $reason->id;
                    }
                }
                $deadPeopleRecord[$prefix . '_death_reason'] = $deathReason;
            }

            // ✅ قاعدة عدم التكرار: سجل متوفٍ موجود للملف يُحدَّث ولا يُضاف من جديد
            $deadPeopleId = $this->upsertDeadPeopleForFile($deadPeopleRecord, $sponsorship->relation_id_number);

            // 2. 🆕 تخزين بيانات السكن في portal_general_registration_field_values
            $housingFields = [
                'field_housing_status',
                'field_housing_type',
                'field_data_address',
                'field_data_province',
                'field_data_city',
                'field_data_neighborhood',
                'field_housing_address',
                'field_housing_address_detail',
                'field_house_demolition',
                'field_house_repair_need',
            ];

            foreach ($housingFields as $fieldKey) {
                if (isset($fieldsData[$fieldKey]) && $fieldsData[$fieldKey] !== '') {
                    PortalGeneralRegistrationFieldValue::query()->updateOrCreate(
                        [
                            'file_id_number' => (string) $newFileNumber,
                            'field_key' => (string) $fieldKey,
                        ],
                        [
                            'sponsorship_id' => $sponsorship->id,
                            'identity_number' => (string) $identityNumber,
                            'field_value' => is_array($fieldsData[$fieldKey]) ? json_encode($fieldsData[$fieldKey], JSON_UNESCAPED_UNICODE) : (string) $fieldsData[$fieldKey],
                            'updated_by_user_id' => auth()->id(),
                        ]
                    );
                }
            }

            Log::info('CREATED_RECORDS_FOR_DECEASED_NO_DATA_TABLE', [
                'dead_people_id' => $deadPeopleId,
                'person_type' => $personType,
                'file_id_number' => $newFileNumber,
                'housing_fields_stored' => count(array_filter($housingFields, fn($f) => isset($fieldsData[$f]) && $fieldsData[$f] !== '')),
                'note' => 'تم إنشاء سجل في dead_people فقط (بدون data) + بيانات السكن في portal_general_registration_field_values',
            ]);

        } else {
            // === الطريقة القديمة للتوافق: إنشاء سجلات في كل الجداول ===
            Log::warning('LEGACY_CREATION_MODE', [
                'sponsorship_id' => $sponsorship->id,
                'person_type' => $personType,
                'reason' => 'person_type is null or unknown, using legacy mode'
            ]);

            // 1. إنشاء سجل في جدول data (بيانات المعيل/العائلة)
            $dataRecord = [
                'file_id_number' => $newFileNumber,
                'data_id_number' => $identityNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (isset($namesData['guardian']) && is_array($namesData['guardian'])) {
                $guardianNames = $namesData['guardian'];
                $dataRecord['data_first_name'] = $guardianNames['first_name'] ?? '';
                $dataRecord['data_father_name'] = $guardianNames['second_name'] ?? '';
                $dataRecord['data_grand_father_name'] = $guardianNames['third_name'] ?? '';
                $dataRecord['data_family_name'] = $guardianNames['last_name'] ?? '';

                $fullGuardianName = trim("{$guardianNames['first_name']} {$guardianNames['second_name']} {$guardianNames['third_name']} {$guardianNames['last_name']}");
                if (!empty($fullGuardianName)) {
                    $sponsorship->guardian_name = $fullGuardianName;
                }
            } elseif ($sponsorship->guardian_name) {
                $nameParts = explode(' ', $sponsorship->guardian_name);
                $dataRecord['data_first_name'] = $nameParts[0] ?? '';
                $dataRecord['data_father_name'] = $nameParts[1] ?? '';
                $dataRecord['data_grand_father_name'] = $nameParts[2] ?? '';
                $dataRecord['data_family_name'] = $nameParts[3] ?? '';
            }

            $dataFieldsMapping = [
                'field_data_phone_number' => 'data_phone_number',
            ];

            foreach ($dataFieldsMapping as $fieldKey => $dbColumn) {
                if (isset($fieldsData[$fieldKey]) && $fieldsData[$fieldKey] !== '') {
                    $dataRecord[$dbColumn] = $fieldsData[$fieldKey];
                }
            }

            // تحويل حقول lookup من نصوص إلى IDs
            // تحويل المحافظة
            if (isset($fieldsData['field_data_province']) && $fieldsData['field_data_province'] !== '') {
                $provinceId = DB::table('provinces')->where('description', $fieldsData['field_data_province'])->value('id');
                if ($provinceId !== null) {
                    $dataRecord['data_province'] = $provinceId;
                }
            }
            // تحويل المدينة
            if (isset($fieldsData['field_data_city']) && $fieldsData['field_data_city'] !== '') {
                $cityId = DB::table('city')->where('city', $fieldsData['field_data_city'])->value('id');
                if ($cityId !== null) {
                    $dataRecord['data_city'] = $cityId;
                }
            }
            if (isset($fieldsData['field_health_status']) && $fieldsData['field_health_status'] !== '') {
                $healthStatusId = $this->resolveLookupIdByDescription('health_statuses', $fieldsData['field_health_status']);
                if ($healthStatusId !== null) {
                    $dataRecord['data_health_status'] = $healthStatusId;
                }
            }
            if (isset($fieldsData['field_housing_status']) && $fieldsData['field_housing_status'] !== '') {
                $housingStatusId = $this->resolveLookupIdByDescription('housing_status', $fieldsData['field_housing_status']);
                if ($housingStatusId !== null) {
                    $dataRecord['data_housing_status'] = $housingStatusId;
                }
            }
            if (isset($fieldsData['field_housing_type']) && $fieldsData['field_housing_type'] !== '') {
                $housingTypeId = $this->resolveLookupIdByDescription('type_of_accommodation', $fieldsData['field_housing_type']);
                if ($housingTypeId !== null) {
                    $dataRecord['data_current_housing_type'] = $housingTypeId;
                }
            }

            $dataId = $this->insertOrUpdateDataRecord($dataRecord, $reusedGuardianFile);

            Log::info('CREATED_DATA_RECORD_LEGACY', [
                'data_id' => $dataId,
                'file_id_number' => $newFileNumber
            ]);

            // 2. إنشاء سجل في جدول re_people
            $rePeopleRecord = [
                'registration_id' => $newFileNumber,
                'person_id' => $identityNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (isset($namesData['sponsored']) && is_array($namesData['sponsored'])) {
                $sponsoredNames = $namesData['sponsored'];
                $rePeopleRecord['first_name'] = $sponsoredNames['first_name'] ?? '';
                $rePeopleRecord['second_name'] = $sponsoredNames['second_name'] ?? '';
                $rePeopleRecord['third_name'] = $sponsoredNames['third_name'] ?? '';
                $rePeopleRecord['last_name'] = $sponsoredNames['last_name'] ?? '';

                $fullSponsoredName = trim("{$sponsoredNames['first_name']} {$sponsoredNames['second_name']} {$sponsoredNames['third_name']} {$sponsoredNames['last_name']}");
                if (!empty($fullSponsoredName)) {
                    $sponsorship->orphan_name = $fullSponsoredName;
                }
            } elseif ($sponsorship->orphan_name) {
                $nameParts = explode(' ', $sponsorship->orphan_name);
                $rePeopleRecord['first_name'] = $nameParts[0] ?? '';
                $rePeopleRecord['second_name'] = $nameParts[1] ?? '';
                $rePeopleRecord['third_name'] = $nameParts[2] ?? '';
                $rePeopleRecord['last_name'] = $nameParts[3] ?? '';
            }

            if (isset($fieldsData['field_person_birth_date']) && $fieldsData['field_person_birth_date'] !== '') {
                $rePeopleRecord['person_birth_date'] = $fieldsData['field_person_birth_date'];
                $sponsorship->sponsored_birth_date = $fieldsData['field_person_birth_date'];
            }

            if (isset($fieldsData['field_person_gender'])) {
                $gender = $fieldsData['field_person_gender'];
                if ($gender == 'ذكر' || $gender == 1) {
                    $rePeopleRecord['person_gender'] = 1;
                } elseif ($gender == 'أنثى' || $gender == 2) {
                    $rePeopleRecord['person_gender'] = 2;
                }
            }

            // ✅ قاعدة عدم التكرار: الفرد موجود بنفس الملف والهوية ⇒ تحديث فقط
            $rePeopleId = $this->insertOrUpdateRePeopleRecord($rePeopleRecord, $reusedGuardianFile);

            Log::info('CREATED_RE_PEOPLE_RECORD_LEGACY', [
                're_people_id' => $rePeopleId,
                'registration_id' => $newFileNumber,
                'person_id' => $identityNumber
            ]);

            // 3. إنشاء سجل في جدول dead_people (بيانات المتوفين)
            $deadPeopleRecord = [
                're_file_id' => $newFileNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (isset($namesData['father']) && is_array($namesData['father'])) {
                $fatherNames = $namesData['father'];
                $deadPeopleRecord['father_first_name'] = $fatherNames['first_name'] ?? '';
                $deadPeopleRecord['father_second_name'] = $fatherNames['second_name'] ?? '';
                $deadPeopleRecord['father_third_name'] = $fatherNames['third_name'] ?? '';
                $deadPeopleRecord['father_last_name'] = $fatherNames['last_name'] ?? '';
            }

            if (isset($namesData['mother']) && is_array($namesData['mother'])) {
                $motherNames = $namesData['mother'];
                $deadPeopleRecord['mother_first_name'] = $motherNames['first_name'] ?? '';
                $deadPeopleRecord['mother_second_name'] = $motherNames['second_name'] ?? '';
                $deadPeopleRecord['mother_third_name'] = $motherNames['third_name'] ?? '';
                $deadPeopleRecord['mother_last_name'] = $motherNames['last_name'] ?? '';
            }

            $deadPeopleFieldsMapping = [
                'field_father_id' => 'father_id',
                'field_father_death_date' => 'father_death_date',
                'field_father_death_reason' => 'father_death_reason',
                'field_mother_id' => 'mother_id',
                'field_mother_death_date' => 'mother_death_date',
                'field_mother_death_reason' => 'mother_death_reason',
            ];

            foreach ($deadPeopleFieldsMapping as $fieldKey => $dbColumn) {
                if (isset($fieldsData[$fieldKey]) && $fieldsData[$fieldKey] !== '') {
                    if (str_contains($dbColumn, 'death_reason') && !is_numeric($fieldsData[$fieldKey])) {
                        $reason = DB::table('death_reasons')
                            ->where('description', $fieldsData[$fieldKey])
                            ->first();
                        if ($reason) {
                            $deadPeopleRecord[$dbColumn] = $reason->id;
                        }
                    } else {
                        $deadPeopleRecord[$dbColumn] = $fieldsData[$fieldKey];
                    }
                }
            }

            // ✅ قاعدة عدم التكرار: سجل متوفٍ موجود للملف يُحدَّث ولا يُضاف من جديد
            $deadPeopleId = $this->upsertDeadPeopleForFile($deadPeopleRecord, $sponsorship->relation_id_number);

            Log::info('CREATED_DEAD_PEOPLE_RECORD_LEGACY', [
                'dead_people_id' => $deadPeopleId,
                're_file_id' => $newFileNumber
            ]);
        }

        // 4. تحديث relation_id_number في sponsorship
        $sponsorship->relation_id_number = $newFileNumber;
        $sponsorship->save();

        Log::info('UPDATED_SPONSORSHIP_WITH_NEW_FILE_NUMBER', [
            'sponsorship_id' => $sponsorship->id,
            'relation_id_number' => $newFileNumber,
            'person_type' => $personType,
            'orphan_name' => $sponsorship->orphan_name,
            'guardian_name' => $sponsorship->guardian_name
        ]);

        return $newFileNumber;
    }
}
