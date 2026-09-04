<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\DataTables\SponsorshipsDataTable;
use App\DataTables\RecordsManagementeDataTable;
use App\DataTables\UnifiedPeopleDataTable;
use App\Models\Sponsorship;
use App\Models\Sponsor;
use App\Models\TypeOfGuarantee;
use App\Models\SponsorshipStatus;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\BankName;
use App\Models\GuardianBankAccount;
use App\Services\BankAccountValidationService;
use App\Helpers\NameSegmentation; // 🆕 خوارزمية تقسيم الأسماء العربية
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SponsorshipController extends Controller
{
    /**
     * Display a listing of sponsorships
     */
    public function index(SponsorshipsDataTable $dataTable)
    {
        $sponsors = Sponsor::all();
        $sponsorshipTypes = TypeOfGuarantee::all();
        $sponsorshipStatuses = SponsorshipStatus::all();
        $bankNames = BankName::all();

        return $dataTable->render('admin.dashboard.sponsorships.index', compact(
            'sponsors',
            'sponsorshipTypes',
            'sponsorshipStatuses',
            'bankNames'
        ));
    }

    /**
     * Store a newly created sponsorship
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validatedData = $request->validate([
                'sponsor_ids' => 'nullable|array',
                'sponsor_ids.*' => 'exists:sponsors,id',
                'sponsor_id' => 'nullable|exists:sponsors,id',
                'sponsoring_organization' => 'nullable|string|max:255',
                'internal_file_number' => 'nullable|string|max:100',
                'external_file_number' => 'nullable|string|max:100',
                'identity_number' => 'nullable|string|max:50',
                'orphan_name' => 'nullable|string|max:255',
                'orphan_first_name' => 'nullable|string|max:50',
                'orphan_father_name' => 'nullable|string|max:50',
                'orphan_grandfather_name' => 'nullable|string|max:50',
                'orphan_family_name' => 'nullable|string|max:50',
                'sponsored_birth_date' => 'nullable|date', // تاريخ ميلاد المكفول
                'guardian_name' => 'nullable|string|max:255',
                'guardian_first_name' => 'nullable|string|max:50',
                'guardian_father_name' => 'nullable|string|max:50',
                'guardian_grandfather_name' => 'nullable|string|max:50',
                'guardian_family_name' => 'nullable|string|max:50',
                'guardian_identity_number' => 'nullable|string|max:50',
                'guardian_birth_date' => 'nullable|date', // تاريخ ميلاد المعيل
                'sponsorship_duration_months' => 'nullable|integer',
                'sponsorship_start_date' => 'nullable|date',
                'sponsorship_end_date' => 'nullable|date',
                'sponsorship_type_id' => 'nullable|exists:type_of_guarantee,id',
                'sponsorship_status_id' => 'nullable|exists:sponsorship_statuses,id',
                'person_type' => 'nullable|string|in:breadwinner,family_member,deceased_father,deceased_mother',
                'notes' => 'nullable|string',
                'record_id' => 'nullable|string',
                'record_type' => 'nullable|string|in:re_people,dead_people,data',
                'reserved_file_id' => 'nullable|string|max:20',
            ]);

            $validatedData['created_by'] = auth()->id();

            // 🆕 معالجة الاسم الرباعي للمكفول - دمج 4 حقول في حقل واحد
            if ($request->has('orphan_first_name') || $request->has('orphan_father_name') ||
                $request->has('orphan_grandfather_name') || $request->has('orphan_family_name')) {
                $nameParts = array_filter([
                    $validatedData['orphan_first_name'] ?? '',
                    $validatedData['orphan_father_name'] ?? '',
                    $validatedData['orphan_grandfather_name'] ?? '',
                    $validatedData['orphan_family_name'] ?? ''
                ]);
                $validatedData['orphan_name'] = implode(' ', $nameParts);
            }

            // 🆕 معالجة الاسم الرباعي للمعيل - دمج 4 حقول في حقل واحد
            if ($request->has('guardian_first_name') || $request->has('guardian_father_name') ||
                $request->has('guardian_grandfather_name') || $request->has('guardian_family_name')) {
                $guardianNameParts = array_filter([
                    $validatedData['guardian_first_name'] ?? '',
                    $validatedData['guardian_father_name'] ?? '',
                    $validatedData['guardian_grandfather_name'] ?? '',
                    $validatedData['guardian_family_name'] ?? ''
                ]);
                $validatedData['guardian_name'] = implode(' ', $guardianNameParts);
            }

            // حفظ بيانات المعيل الرباعية للاستخدام لاحقاً
            $guardianFirstName = $validatedData['guardian_first_name'] ?? '';
            $guardianFatherName = $validatedData['guardian_father_name'] ?? '';
            $guardianGrandFatherName = $validatedData['guardian_grandfather_name'] ?? '';
            $guardianFamilyName = $validatedData['guardian_family_name'] ?? '';
            $guardianBirthDate = $validatedData['guardian_birth_date'] ?? null;

            // حفظ بيانات المكفول الرباعية للاستخدام لاحقاً
            $orphanFirstName = $validatedData['orphan_first_name'] ?? '';
            $orphanFatherName = $validatedData['orphan_father_name'] ?? '';
            $orphanGrandFatherName = $validatedData['orphan_grandfather_name'] ?? '';
            $orphanFamilyName = $validatedData['orphan_family_name'] ?? '';

            // إزالة الحقول الفردية لأنها غير موجودة في جدول sponsorships
            unset($validatedData['orphan_first_name']);
            unset($validatedData['orphan_father_name']);
            unset($validatedData['orphan_grandfather_name']);
            unset($validatedData['orphan_family_name']);
            unset($validatedData['guardian_first_name']);
            unset($validatedData['guardian_father_name']);
            unset($validatedData['guardian_grandfather_name']);
            unset($validatedData['guardian_family_name']);
            unset($validatedData['guardian_birth_date']);

            // 🆕 تعيين الحالة الافتراضية "جديد" (ID = 4) إذا لم يتم تحديد حالة
            if (!isset($validatedData['sponsorship_status_id']) || empty($validatedData['sponsorship_status_id'])) {
                $validatedData['sponsorship_status_id'] = 4; // حالة "جديد"
                Log::info('✅ تم تعيين الحالة الافتراضية: جديد');
            }

            // 🆕 معالجة توليد file_id_number للأشخاص من re_people و dead_people
            $recordType = $request->input('record_type');
            $recordId = $request->input('record_id');
            $reservedFileId = $request->input('reserved_file_id'); // الرقم المحجوز من المودال

            if (in_array($recordType, ['re_people', 'dead_people']) && $recordId) {
                // استخدام الرقم المحجوز أو توليد رقم جديد
                $newFileId = $reservedFileId ?: generateUniqueReservedCode('data', 'file_id_number');

                if (!$newFileId) {
                    throw new \Exception('فشل في توليد رقم ملف فريد');
                }

                Log::info('🆕 توليد file_id_number للكفالة (سيتم حفظه في جدول sponsorships فقط)', [
                    'record_type' => $recordType,
                    'record_id' => $recordId,
                    'file_id' => $newFileId,
                    'was_reserved' => !empty($reservedFileId),
                    'identity_number' => $validatedData['identity_number'] ?? null
                ]);

                // ✅ تحديث internal_file_number في validatedData (سيتم حفظه في جدول sponsorships فقط)
                // ⚠️ لن يتم إضافة الشخص إلى جدول data - هو موجود بالفعل في re_people أو dead_people
                $validatedData['internal_file_number'] = $newFileId;

                // وضع علامة على الرقم كمستخدم
                markCodeAsUsed($newFileId);

                Log::info('✅ تم حجز file_id_number وحفظه في الكفالة', [
                    'file_id_number' => $newFileId,
                    'record_type' => $recordType,
                    'record_id' => $recordId
                ]);
            }

            // 🆕 ملء relation_id_number برقم ملف المعيل من جدول data
            $relationIdNumber = null;
            if (!empty($validatedData['guardian_identity_number'])) {
                // البحث عن المعيل في جدول data
                $guardianData = Data::where('data_id_number', $validatedData['guardian_identity_number'])
                                    ->first();

                if ($guardianData) {
                    $relationIdNumber = $guardianData->file_id_number;
                    Log::info('✅ تم جلب relation_id_number من جدول data', [
                        'guardian_identity' => $validatedData['guardian_identity_number'],
                        'relation_id_number' => $relationIdNumber
                    ]);
                }
            } elseif (!empty($validatedData['identity_number'])) {
                // إذا لم يكن هناك معيل، نستخدم رقم هوية الشخص نفسه (حالة المعيل)
                $guardianData = Data::where('data_id_number', $validatedData['identity_number'])
                                    ->first();

                if ($guardianData) {
                    $relationIdNumber = $guardianData->file_id_number;
                    Log::info('✅ تم جلب relation_id_number من رقم هوية الشخص (معيل)', [
                        'identity_number' => $validatedData['identity_number'],
                        'relation_id_number' => $relationIdNumber
                    ]);
                }
            }

            if ($relationIdNumber) {
                $validatedData['relation_id_number'] = $relationIdNumber;
            }

            // ============================================
            // 🆕 معالجة إدخال الأشخاص حسب نوع الشخص (person_type)
            // ============================================
            $personType = $validatedData['person_type'] ?? null;
            $sponsoredIdentity = $validatedData['identity_number'] ?? null;
            $guardianIdentity = $validatedData['guardian_identity_number'] ?? null;
            $orphanName = $validatedData['orphan_name'] ?? null;
            $guardianName = $validatedData['guardian_name'] ?? null;
            $sponsoredBirthDate = $validatedData['sponsored_birth_date'] ?? null;

            Log::info('🎯 معالجة الكفالة حسب نوع الشخص', [
                'person_type' => $personType,
                'sponsored_identity' => $sponsoredIdentity,
                'guardian_identity' => $guardianIdentity,
                'orphan_name' => $orphanName,
                'guardian_name' => $guardianName,
                'sponsored_birth_date' => $sponsoredBirthDate,
                'guardian_birth_date' => $guardianBirthDate ?? null
            ]);

            // ============================================
            // 📌 حالة: معيل (breadwinner)
            // ============================================
            if ($personType === 'breadwinner') {
                // المعيل يدخل في جدول data مع رقم ملف جديد
                $existingGuardian = Data::where('data_id_number', $sponsoredIdentity)->first();

                if (!$existingGuardian && $sponsoredIdentity && $orphanName) {
                    // توليد رقم ملف فريد جديد للمعيل
                    $uniqueFileId = generateUniqueReservedCode('data', 'file_id_number');

                    if (!$uniqueFileId) {
                        throw new \Exception('فشل في توليد رقم ملف فريد');
                    }

                    Log::info('🆕 توليد رقم ملف فريد للمعيل الجديد', [
                        'file_id' => $uniqueFileId,
                        'breadwinner_identity' => $sponsoredIdentity
                    ]);

                    // إضافة المعيل إلى جدول data
                    $newGuardianData = Data::create([
                        'file_id_number' => $uniqueFileId,
                        'data_id_number' => $sponsoredIdentity,
                        'data_first_name' => $orphanFirstName ?: null,
                        'data_father_name' => $orphanFatherName ?: null,
                        'data_grand_father_name' => $orphanGrandFatherName ?: null,
                        'data_family_name' => $orphanFamilyName ?: null,
                        'data_birth_date' => $validatedData['sponsored_birth_date'] ?? null,
                        'data_section_id' => 1,
                        'data_request_status' => 4,
                        'data_user_insert_data' => auth()->user()->name ?? 'System',
                    ]);

                    Log::info('✅ تم إضافة المعيل إلى جدول data', [
                        'data_id' => $newGuardianData->id,
                        'file_id_number' => $uniqueFileId,
                        'identity' => $sponsoredIdentity,
                        'birth_date' => $validatedData['sponsored_birth_date'] ?? null
                    ]);

                    // تخزين أرقام الملفات للكفالة
                    $validatedData['relation_id_number'] = $uniqueFileId;
                    // استخدام الرقم المحجوز أو توليد رقم جديد
                    $validatedData['internal_file_number'] = $reservedFileId ?: generateUniqueReservedCode('sponsorships', 'internal_file_number');
                    markCodeAsUsed($uniqueFileId);
                    if ($validatedData['internal_file_number']) {
                        markCodeAsUsed($validatedData['internal_file_number']);
                    }
                } else if ($existingGuardian) {
                    // المعيل موجود بالفعل - نستخدم رقم ملف الكفالة المحجوز
                    $validatedData['relation_id_number'] = $existingGuardian->file_id_number;

                    // 🆕 استخدام الرقم المحجوز من المودال أو توليد رقم جديد
                    $validatedData['internal_file_number'] = $reservedFileId ?: generateUniqueReservedCode('sponsorships', 'internal_file_number');
                    if ($validatedData['internal_file_number']) {
                        markCodeAsUsed($validatedData['internal_file_number']);
                    }

                    Log::info('✅ المعيل موجود بالفعل في جدول data - تم تعيين internal_file_number', [
                        'file_id_number' => $existingGuardian->file_id_number,
                        'internal_file_number' => $validatedData['internal_file_number'],
                        'was_reserved' => !empty($reservedFileId)
                    ]);
                }
            }
            // ============================================
            // 📌 حالة: فرد عائلة (family_member)
            // ============================================
            elseif ($personType === 'family_member') {
                // يجب أن يكون المعيل موجوداً أو يتم إنشاؤه
                if (!$guardianIdentity || !$guardianName) {
                    throw new \Exception('يجب إدخال بيانات المعيل كاملة لإضافة فرد عائلة');
                }

                // التحقق من وجود المعيل أو إنشائه
                $existingGuardian = Data::where('data_id_number', $guardianIdentity)->first();

                if (!$existingGuardian) {
                    // إنشاء المعيل أولاً
                    $uniqueFileId = generateUniqueReservedCode('data', 'file_id_number');

                    if (!$uniqueFileId) {
                        throw new \Exception('فشل في توليد رقم ملف فريد للمعيل');
                    }

                    $existingGuardian = Data::create([
                        'file_id_number' => $uniqueFileId,
                        'data_id_number' => $guardianIdentity,
                        'data_first_name' => $guardianFirstName ?: null,
                        'data_father_name' => $guardianFatherName ?: null,
                        'data_grand_father_name' => $guardianGrandFatherName ?: null,
                        'data_family_name' => $guardianFamilyName ?: null,
                        'data_birth_date' => $guardianBirthDate ?? null,
                        'data_section_id' => 1,
                        'data_request_status' => 4,
                        'data_user_insert_data' => auth()->user()->name ?? 'System',
                    ]);

                    markCodeAsUsed($uniqueFileId);

                    Log::info('✅ تم إنشاء المعيل لفرد العائلة', [
                        'guardian_file_id' => $uniqueFileId,
                        'guardian_identity' => $guardianIdentity
                    ]);
                }

                // إضافة فرد العائلة إلى re_people
                $existingFamilyMember = DB::table('re_people')->where('person_id', $sponsoredIdentity)->first();

                if (!$existingFamilyMember && $sponsoredIdentity) {
                    RePeople::create([
                        'registration_id' => $existingGuardian->file_id_number,
                        'person_id' => $sponsoredIdentity,
                        'first_name' => $orphanFirstName ?: null,
                        'second_name' => $orphanFatherName ?: null,
                        'third_name' => $orphanGrandFatherName ?: null,
                        'last_name' => $orphanFamilyName ?: null,
                        'person_birth_date' => $validatedData['sponsored_birth_date'] ?? null,
                    ]);

                    Log::info('✅ تم إضافة فرد العائلة إلى re_people', [
                        'registration_id' => $existingGuardian->file_id_number,
                        'person_id' => $sponsoredIdentity,
                        'person_birth_date' => $validatedData['sponsored_birth_date'] ?? null
                    ]);
                }

                $validatedData['relation_id_number'] = $existingGuardian->file_id_number;
                $validatedData['internal_file_number'] = $validatedData['internal_file_number'] ?: generateUniqueReservedCode('sponsorships', 'internal_file_number');
                if ($validatedData['internal_file_number']) {
                    markCodeAsUsed($validatedData['internal_file_number']);
                }
            }
            // ============================================
            // 📌 حالة: أب متوفي أو أم متوفية (deceased_father/deceased_mother)
            // 🆕 يتم تخزين البيانات في sponsorships فقط حالياً
            // بدون إنشاء ملف في dead_people - سيتم ذلك لاحقاً من بوابة المستخدم
            // ============================================
            elseif (in_array($personType, ['deceased_father', 'deceased_mother'])) {
                // 🆕 بيانات المعيل اختيارية - لا نجبر على إدخالها
                Log::info('📌 إضافة شخص متوفي - التخزين في sponsorships فقط', [
                    'person_type' => $personType,
                    'sponsored_identity' => $sponsoredIdentity,
                    'has_guardian' => !empty($guardianIdentity),
                ]);

                // إذا تم إدخال بيانات المعيل، نتحقق من وجوده أو ننشئه
                if ($guardianIdentity) {
                    $existingGuardian = Data::where('data_id_number', $guardianIdentity)->first();

                    if (!$existingGuardian) {
                        // إنشاء المعيل إذا لم يكن موجوداً
                        $uniqueFileId = generateUniqueReservedCode('data', 'file_id_number');

                        if ($uniqueFileId) {
                            $existingGuardian = Data::create([
                                'file_id_number' => $uniqueFileId,
                                'data_id_number' => $guardianIdentity,
                                'data_first_name' => $guardianFirstName ?: null,
                                'data_father_name' => $guardianFatherName ?: null,
                                'data_grand_father_name' => $guardianGrandFatherName ?: null,
                                'data_family_name' => $guardianFamilyName ?: null,
                                'data_birth_date' => $guardianBirthDate ?? null,
                                'data_section_id' => 1,
                                'data_request_status' => 4,
                                'data_user_insert_data' => auth()->user()->name ?? 'System',
                            ]);

                            markCodeAsUsed($uniqueFileId);

                            Log::info('✅ تم إنشاء المعيل للشخص المتوفي (اختياري)', [
                                'guardian_file_id' => $uniqueFileId,
                                'guardian_identity' => $guardianIdentity
                            ]);
                        }
                    }

                    // ربط رقم الملف إذا وُجد المعيل
                    if ($existingGuardian) {
                        $validatedData['relation_id_number'] = $existingGuardian->file_id_number;
                    }
                }

                // 🆕 توليد رقم ملف داخلي للكفالة إذا لم يكن موجوداً
                $validatedData['internal_file_number'] = $validatedData['internal_file_number'] ?: generateUniqueReservedCode('sponsorships', 'internal_file_number');
                if ($validatedData['internal_file_number']) {
                    markCodeAsUsed($validatedData['internal_file_number']);
                }

                Log::info('✅ تم تجهيز بيانات المتوفي للتخزين في sponsorships', [
                    'person_type' => $personType,
                    'internal_file_number' => $validatedData['internal_file_number'],
                    'relation_id_number' => $validatedData['relation_id_number'] ?? null,
                    'note' => 'لن يتم إنشاء ملف في dead_people حالياً - سيتم ذلك من بوابة المستخدم'
                ]);
            }
            // ============================================
            // 📌 حالة: بدون نوع محدد (المنطق القديم)
            // ============================================
            elseif ($sponsoredIdentity && $guardianIdentity && $orphanName && $guardianName) {

                // التحقق من عدم وجود المكفول في re_people
                $existingSponsored = DB::table('re_people')->where('person_id', $sponsoredIdentity)->first();

                // التحقق من عدم وجود المعيل في data
                $existingGuardian = Data::where('data_id_number', $guardianIdentity)->first();

                // إذا لم يكن أي منهما موجوداً، ننشئ سجلات جديدة
                if (!$existingSponsored && !$existingGuardian) {

                    // توليد رقم ملف فريد جديد
                    $uniqueFileId = generateUniqueReservedCode('data', 'file_id_number');

                    if (!$uniqueFileId) {
                        throw new \Exception('فشل في توليد رقم ملف فريد');
                    }

                    Log::info('🆕 توليد رقم ملف فريد لكفالة جديدة من السجل المدني', [
                        'file_id' => $uniqueFileId,
                        'sponsored_identity' => $sponsoredIdentity,
                        'guardian_identity' => $guardianIdentity
                    ]);

                    // تقسيم اسم المكفول إلى 4 أجزاء
                    $sponsoredNameParts = explode(' ', trim($orphanName));
                    $sponsoredFirstName = $sponsoredNameParts[0] ?? '';
                    $sponsoredSecondName = $sponsoredNameParts[1] ?? '';
                    $sponsoredThirdName = $sponsoredNameParts[2] ?? '';
                    $sponsoredLastName = $sponsoredNameParts[3] ?? (count($sponsoredNameParts) > 3 ? implode(' ', array_slice($sponsoredNameParts, 3)) : '');

                    // تقسيم اسم المعيل إلى 4 أجزاء
                    $guardianNameParts = explode(' ', trim($guardianName));
                    $guardianFirstName = $guardianNameParts[0] ?? '';
                    $guardianFatherName = $guardianNameParts[1] ?? '';
                    $guardianGrandFatherName = $guardianNameParts[2] ?? '';
                    $guardianFamilyName = $guardianNameParts[3] ?? (count($guardianNameParts) > 3 ? implode(' ', array_slice($guardianNameParts, 3)) : '');

                    // جلب تاريخ ميلاد المعيل من السجل المدني
                    $guardianBirthDate = null;
                    try {
                        $guardianFromCivilRegistry = DB::connection('civilregistry')
                            ->table('persons')
                            ->where('CI_ID_NUM', $guardianIdentity)
                            ->first();

                        if ($guardianFromCivilRegistry && !empty($guardianFromCivilRegistry->CI_BIRTH_DT)) {
                            $guardianBirthDate = $guardianFromCivilRegistry->CI_BIRTH_DT;
                            Log::info('✅ تم جلب تاريخ ميلاد المعيل من السجل المدني', [
                                'guardian_identity' => $guardianIdentity,
                                'birth_date' => $guardianBirthDate
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('⚠️ فشل في جلب تاريخ ميلاد المعيل من السجل المدني', [
                            'guardian_identity' => $guardianIdentity,
                            'error' => $e->getMessage()
                        ]);
                    }

                    // إضافة المعيل إلى جدول data
                    $newGuardianData = Data::create([
                        'file_id_number' => $uniqueFileId,
                        'data_id_number' => $guardianIdentity,
                        'data_first_name' => $guardianFirstName,
                        'data_father_name' => $guardianFatherName,
                        'data_grand_father_name' => $guardianGrandFatherName,
                        'data_family_name' => $guardianFamilyName,
                        'data_birth_date' => $guardianBirthDate,
                        'data_section_id' => 1,
                        'data_request_status' => 4,
                        'data_user_insert_data' => auth()->user()->name ?? 'System',
                    ]);

                    Log::info('✅ تم إضافة المعيل إلى جدول data', [
                        'data_id' => $newGuardianData->id,
                        'file_id_number' => $uniqueFileId,
                        'guardian_identity' => $guardianIdentity,
                        'name' => $guardianName
                    ]);

                    // إضافة المكفول إلى جدول re_people
                    $newSponsoredPerson = RePeople::create([
                        'registration_id' => $uniqueFileId,
                        'person_id' => $sponsoredIdentity,
                        'first_name' => $sponsoredFirstName,
                        'second_name' => $sponsoredSecondName,
                        'third_name' => $sponsoredThirdName,
                        'last_name' => $sponsoredLastName,
                        'person_birth_date' => $validatedData['sponsored_birth_date'] ?? null,
                    ]);

                    Log::info('✅ تم إضافة المكفول إلى جدول re_people', [
                        're_people_id' => $newSponsoredPerson->id,
                        'registration_id' => $uniqueFileId,
                        'person_id' => $sponsoredIdentity,
                        'name' => $orphanName
                    ]);

                    // تخزين رقم الملف في relation_id_number (للربط بين data و re_people)
                    $validatedData['relation_id_number'] = $uniqueFileId;

                    // توليد رقم ملف داخلي منفصل للكفالة (مختلف عن relation_id_number)
                    $internalFileNumber = generateUniqueReservedCode('sponsorships', 'internal_file_number');
                    if ($internalFileNumber) {
                        $validatedData['internal_file_number'] = $internalFileNumber;
                        markCodeAsUsed($internalFileNumber);
                    }

                    // وضع علامة على الرقم كمستخدم
                    markCodeAsUsed($uniqueFileId);

                } elseif ($existingGuardian && !$existingSponsored) {
                    // المعيل موجود بالفعل، نضيف المكفول فقط
                    $uniqueFileId = $existingGuardian->file_id_number;

                    // تقسيم اسم المكفول
                    $sponsoredNameParts = explode(' ', trim($orphanName));
                    $sponsoredFirstName = $sponsoredNameParts[0] ?? '';
                    $sponsoredSecondName = $sponsoredNameParts[1] ?? '';
                    $sponsoredThirdName = $sponsoredNameParts[2] ?? '';
                    $sponsoredLastName = $sponsoredNameParts[3] ?? (count($sponsoredNameParts) > 3 ? implode(' ', array_slice($sponsoredNameParts, 3)) : '');

                    // إضافة المكفول إلى re_people
                    $newSponsoredPerson = RePeople::create([
                        'registration_id' => $uniqueFileId,
                        'person_id' => $sponsoredIdentity,
                        'first_name' => $sponsoredFirstName,
                        'second_name' => $sponsoredSecondName,
                        'third_name' => $sponsoredThirdName,
                        'last_name' => $sponsoredLastName,
                        'person_birth_date' => $validatedData['sponsored_birth_date'] ?? null,
                    ]);

                    Log::info('✅ تم إضافة المكفول إلى جدول re_people (المعيل موجود مسبقاً)', [
                        're_people_id' => $newSponsoredPerson->id,
                        'registration_id' => $uniqueFileId,
                        'person_id' => $sponsoredIdentity
                    ]);

                    $validatedData['relation_id_number'] = $uniqueFileId;
                }
            }

            // إزالة sponsor_ids من البيانات لأنه سيتم معالجته بشكل منفصل
            $sponsorIds = $validatedData['sponsor_ids'] ?? [];
            unset($validatedData['sponsor_ids']);
            unset($validatedData['record_id']);
            unset($validatedData['record_type']);

            // ✅ حفظ sponsor_id في الحقل المباشر (أول جمعية في القائمة)
            if (!empty($sponsorIds)) {
                $validatedData['sponsor_id'] = is_array($sponsorIds) ? $sponsorIds[0] : $sponsorIds;
            }

            $sponsorship = Sponsorship::create($validatedData);

            // ربط المؤسسات الكافلة إذا تم اختيارها
            if (!empty($sponsorIds)) {
                $sponsorship->sponsors()->sync($sponsorIds);
            }

            // 🏦 حفظ الحسابات البنكية مع التحقق من التكرار
            if ($request->has('bank_accounts') && !empty($request->bank_accounts)) {
                $bankValidationService = app(BankAccountValidationService::class);
                $duplicateErrors = [];

                // 🆕 جلب رقم هوية المعيل ورقم الملف من جدول data
                $guardianIdentity = null;
                $guardianFileId = null;
                $guardianData = null;

                // أولاً: إذا كان هناك guardian_identity_number (معيل محدد)، نبحث به
                if (!empty($sponsorship->guardian_identity_number)) {
                    $guardianData = Data::where('data_id_number', $sponsorship->guardian_identity_number)
                                        ->select('data_id_number', 'file_id_number')
                                        ->first();

                    if ($guardianData) {
                        $guardianIdentity = $guardianData->data_id_number; // رقم هوية المعيل
                        $guardianFileId = $guardianData->file_id_number;   // رقم ملف المعيل

                        Log::info('✅ تم جلب بيانات المعيل من جدول data باستخدام guardian_identity_number', [
                            'guardian_identity_number' => $sponsorship->guardian_identity_number,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId
                        ]);
                    }
                }

                // ثانياً: إذا لم يكن هناك معيل محدد (حالة المعيل نفسه)، نستخدم internal_file_number
                if (!$guardianData && !empty($sponsorship->internal_file_number)) {
                    $guardianData = Data::where('file_id_number', $sponsorship->internal_file_number)
                                        ->select('data_id_number', 'file_id_number')
                                        ->first();

                    if ($guardianData) {
                        $guardianIdentity = $guardianData->data_id_number; // رقم هوية المعيل
                        $guardianFileId = $guardianData->file_id_number;   // رقم ملف المعيل

                        Log::info('✅ تم جلب بيانات المعيل من جدول data باستخدام internal_file_number (معيل نفسه)', [
                            'internal_file_number' => $sponsorship->internal_file_number,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId
                        ]);
                    }
                }

                // ثالثاً: محاولة أخيرة باستخدام relation_id_number
                if (!$guardianData && !empty($sponsorship->relation_id_number)) {
                    $guardianData = Data::where('file_id_number', $sponsorship->relation_id_number)
                                        ->select('data_id_number', 'file_id_number')
                                        ->first();

                    if ($guardianData) {
                        $guardianIdentity = $guardianData->data_id_number; // رقم هوية المعيل
                        $guardianFileId = $guardianData->file_id_number;   // رقم ملف المعيل

                        Log::info('✅ تم جلب بيانات المعيل من جدول data باستخدام relation_id_number', [
                            'relation_id_number' => $sponsorship->relation_id_number,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId
                        ]);
                    }
                }

                // 🆕 رابعاً: إذا لم يوجد في data، نستخدم internal_file_number مباشرة كـ fallback
                // هذا يحل مشكلة الأشخاص من re_people أو dead_people الذين ليس لديهم سجل في data
                if (!$guardianData && !empty($sponsorship->internal_file_number)) {
                    $guardianFileId = $sponsorship->internal_file_number;
                    // نستخدم identity_number من الكفالة إذا كان متاحاً
                    $guardianIdentity = $sponsorship->identity_number ?: $sponsorship->guardian_identity_number;

                    Log::info('🔄 استخدام internal_file_number مباشرة (الشخص غير موجود في جدول data)', [
                        'internal_file_number' => $sponsorship->internal_file_number,
                        'guardian_identity' => $guardianIdentity,
                        'note' => 'الربط يتم عبر internal_file_number بدلاً من file_id_number'
                    ]);
                }

                // التحقق من نجاح جلب البيانات - نحتاج فقط guardianFileId
                if ($guardianFileId) {
                        Log::info('🏦 البدء في حفظ الحسابات البنكية للكفالة', [
                            'sponsorship_id' => $sponsorship->id,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId,
                            'accounts_count' => count($request->bank_accounts)
                        ]);

                        foreach ($request->bank_accounts as $index => $account) {
                            // التحقق من أن هناك حقل واحد على الأقل مملوء
                            $hasData = !empty($account['bank_name']) ||
                                       !empty($account['re_guardian_name']) ||
                                       !empty($account['person_owner_identity_number']) ||
                                       !empty($account['re_phone_number']) ||
                                       !empty($account['iban_usd']) ||
                                       !empty($account['iban_shekel']);

                            if ($hasData) {
                                // 🔍 التحقق من عدم تكرار الحساب البنكي
                                $excludeId = !empty($account['id']) ? $account['id'] : null;

                                $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
                                    'guardian_registration' => $guardianFileId,
                                    'person_owner_identity_number' => $account['person_owner_identity_number'] ?? null,
                                    're_phone_number' => $account['re_phone_number'] ?? null,
                                    'bank_name' => $account['bank_name'] ?? null,
                                    're_id_number' => $guardianIdentity
                                ], $excludeId);

                                if ($duplicateCheck['is_duplicate']) {
                                    $duplicateErrors[] = [
                                        'index' => $index + 1,
                                        'message' => $duplicateCheck['message']
                                    ];
                                    Log::warning('⚠️ محاولة إضافة حساب بنكي مكرر', [
                                        'index' => $index,
                                        'existing_account' => $duplicateCheck['existing_account']
                                    ]);
                                    continue; // تجاوز هذا الحساب المكرر
                                }

                                // التحقق من وجود bank_name (إلزامي)
                                if (empty($account['bank_name'])) {
                                    Log::warning('⚠️ تم تجاهل حساب بنكي - bank_name مطلوب', ['index' => $index]);
                                    continue;
                                }

                                // 🆕 التحقق إذا كان هذا أول حساب بنكي للشخص (يُعتمد تلقائياً)
                                $existingAccountsCount = GuardianBankAccount::where('guardian_registration', $guardianFileId)->count();
                                $isFirstAccount = ($existingAccountsCount == 0);

                                $bankAccountData = [
                                    'guardian_registration' => $guardianFileId, // استخدام file_id_number بدلاً من identity_number
                                    're_id_number' => $guardianIdentity, // رقم هوية ولي الأمر (يتم جلبه تلقائياً من data)
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
                                    Log::info('✅ تم تحديث الحساب البنكي', ['account_id' => $account['id']]);
                                } else {
                                    // إنشاء حساب جديد
                                    GuardianBankAccount::create($bankAccountData);
                                    Log::info('🟢 تم إنشاء حساب بنكي جديد', $bankAccountData);
                                }
                            }
                        }
                } else {
                    Log::error('❌ فشل جلب بيانات المعيل من جدول data', [
                        'sponsorship_id' => $sponsorship->id,
                        'guardian_identity_number' => $sponsorship->guardian_identity_number,
                        'internal_file_number' => $sponsorship->internal_file_number,
                        'relation_id_number' => $sponsorship->relation_id_number,
                        'guardian_identity' => $guardianIdentity,
                        'guardian_file_id' => $guardianFileId
                    ]);
                }
            }

            DB::commit();

            // إعداد الرسالة مع رقم الملف
            $message = 'تم إضافة الكفالة بنجاح';
            $additionalInfo = [];

            // إضافة تحذيرات التكرار البنكي إن وجدت
            if (!empty($duplicateErrors)) {
                $additionalInfo['bank_duplicates'] = $duplicateErrors;
                $message .= '. تم تجاهل ' . count($duplicateErrors) . ' حساب بنكي مكرر';
            }

            // إذا تم توليد رقم ملف جديد، أضفه للرسالة
            if (isset($newFileId)) {
                $message .= ' - تم توليد رقم ملف جديد';
                $additionalInfo['new_file_id'] = $newFileId;
                $additionalInfo['file_id_generated'] = true;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $sponsorship->load('sponsors'),
                'info' => $additionalInfo
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في إضافة الكفالة:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified sponsorship
     */
    public function edit($id)
    {
        try {
            $sponsorship = Sponsorship::with(['sponsor', 'sponsors', 'sponsorshipType', 'sponsorshipStatus'])
                ->findOrFail($id);

            // إضافة قائمة IDs المؤسسات المرتبطة
            $sponsorship->sponsor_ids = $sponsorship->sponsors->pluck('id')->toArray();

            // 🏦 جلب الحسابات البنكية - البحث باستخدام file_id_number من data أو relation_id_number
            $guardianFileId = null;
            $guardianData = null;
            $guardianFromRePeople = null;

            // 1. محاولة البحث باستخدام guardian_identity_number
            if (!empty($sponsorship->guardian_identity_number)) {
                // البحث في جدول data أولاً
                $guardianData = Data::where('data_id_number', $sponsorship->guardian_identity_number)->first();

                // إذا لم نجد، نبحث في جدول re_people
                if (!$guardianData) {
                    $guardianFromRePeople = DB::table('re_people')
                        ->where('person_id', $sponsorship->guardian_identity_number)
                        ->first();
                }
                if ($guardianData) {
                    $guardianFileId = $guardianData->file_id_number;
                }
                // استخدام registration_id من re_people كـ guardianFileId
                elseif ($guardianFromRePeople && !empty($guardianFromRePeople->registration_id)) {
                    $guardianFileId = $guardianFromRePeople->registration_id;
                }
            }

            // 2. إذا لم نجد، نستخدم relation_id_number مباشرة
            if (!$guardianFileId && !empty($sponsorship->relation_id_number)) {
                $guardianFileId = $sponsorship->relation_id_number;
                // جلب بيانات المعيل من data باستخدام file_id_number
                if (!$guardianData) {
                    $guardianData = Data::where('file_id_number', $guardianFileId)->first();
                }
                // أو من re_people
                if (!$guardianData && !$guardianFromRePeople) {
                    $guardianFromRePeople = DB::table('re_people')
                        ->where('registration_id', $guardianFileId)
                        ->first();
                }
            }

            // 3. محاولة أخيرة: استخدام internal_file_number
            if (!$guardianFileId && !empty($sponsorship->internal_file_number)) {
                $guardianFileId = $sponsorship->internal_file_number;
                if (!$guardianData) {
                    $guardianData = Data::where('file_id_number', $guardianFileId)->first();
                }
            }

            // جلب الحسابات البنكية باستخدام file_id_number
            if ($guardianFileId) {
                $sponsorship->bank_accounts = GuardianBankAccount::with('bank')
                    ->where('guardian_registration', $guardianFileId)
                    ->get()
                    ->toArray();

                // 📞 جلب معلومات المعيل من جدول data (بما في ذلك أرقام الهاتف)
                if ($guardianData) {
                    $sponsorship->guardian_phone = $guardianData->data_phone_number;
                    $sponsorship->guardian_alt_phone = $guardianData->data_alt_phone_number;
                    $sponsorship->guardian_first_name = $guardianData->data_first_name;
                    $sponsorship->guardian_father_name = $guardianData->data_father_name;
                    $sponsorship->guardian_grandfather_name = $guardianData->data_grand_father_name;
                    $sponsorship->guardian_family_name = $guardianData->data_family_name;
                    $sponsorship->guardian_data_source = 'data_table';
                }
                // أو من re_people إذا لم نجد في data
                elseif ($guardianFromRePeople) {
                    $sponsorship->guardian_first_name = $guardianFromRePeople->first_name;
                    $sponsorship->guardian_father_name = $guardianFromRePeople->second_name;
                    $sponsorship->guardian_grandfather_name = $guardianFromRePeople->third_name;
                    $sponsorship->guardian_family_name = $guardianFromRePeople->last_name;
                    $sponsorship->guardian_data_source = 're_people';

                    Log::info('✅ تم جلب بيانات المعيل من re_people', [
                        'sponsorship_id' => $id,
                        'guardian_identity' => $sponsorship->guardian_identity_number,
                        'name' => trim(implode(' ', array_filter([
                            $guardianFromRePeople->first_name,
                            $guardianFromRePeople->second_name,
                            $guardianFromRePeople->third_name,
                            $guardianFromRePeople->last_name
                        ])))
                    ]);
                }

                // إضافة guardian_file_id للاستجابة
                $sponsorship->guardian_file_id = $guardianFileId;

                Log::info('🏦 تم جلب الحسابات البنكية للكفالة', [
                    'sponsorship_id' => $id,
                    'guardian_identity' => $sponsorship->guardian_identity_number,
                    'guardian_file_id' => $guardianFileId,
                    'accounts_count' => count($sponsorship->bank_accounts),
                    'guardian_phone' => $sponsorship->guardian_phone ?? null,
                    'guardian_alt_phone' => $sponsorship->guardian_alt_phone ?? null
                ]);
            } else {
                $sponsorship->bank_accounts = [];
                $sponsorship->guardian_file_id = null;
            }

            // تحويل إلى مصفوفة
            $sponsorshipData = $sponsorship->toArray();

            // 📅 إعادة تطبيق صيغة التواريخ الصحيحة (Y-m-d) بعد التحويل
            if ($sponsorship->sponsorship_start_date) {
                $sponsorshipData['sponsorship_start_date'] = $sponsorship->sponsorship_start_date->format('Y-m-d');
            }
            if ($sponsorship->sponsorship_end_date) {
                $sponsorshipData['sponsorship_end_date'] = $sponsorship->sponsorship_end_date->format('Y-m-d');
            }
            if ($sponsorship->sponsored_birth_date) {
                $sponsorshipData['sponsored_birth_date'] = $sponsorship->sponsored_birth_date->format('Y-m-d');
            }

            return response()->json($sponsorshipData);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الكفالة'
            ], 404);
        }
    }

    /**
     * Update the specified sponsorship
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $sponsorship = Sponsorship::findOrFail($id);

            $validatedData = $request->validate([
                'sponsor_ids' => 'nullable|array',
                'sponsor_ids.*' => 'exists:sponsors,id',
                'sponsor_id' => 'nullable|exists:sponsors,id',
                'sponsoring_organization' => 'nullable|string|max:255',
                'internal_file_number' => 'nullable|string|max:100',
                'external_file_number' => 'nullable|string|max:100',
                'identity_number' => 'nullable|string|max:50',
                'orphan_name' => 'nullable|string|max:255',
                'orphan_first_name' => 'nullable|string|max:50',
                'orphan_father_name' => 'nullable|string|max:50',
                'orphan_grandfather_name' => 'nullable|string|max:50',
                'orphan_family_name' => 'nullable|string|max:50',
                'sponsored_birth_date' => 'nullable|date', // تاريخ ميلاد المكفول
                'guardian_name' => 'nullable|string|max:255',
                'guardian_first_name' => 'nullable|string|max:50',
                'guardian_father_name' => 'nullable|string|max:50',
                'guardian_grandfather_name' => 'nullable|string|max:50',
                'guardian_family_name' => 'nullable|string|max:50',
                'guardian_identity_number' => 'nullable|string|max:50',
                'guardian_birth_date' => 'nullable|date', // تاريخ ميلاد المعيل
                'sponsorship_duration_months' => 'nullable|integer',
                'sponsorship_start_date' => 'nullable|date',
                'sponsorship_end_date' => 'nullable|date',
                'sponsorship_type_id' => 'nullable|exists:type_of_guarantee,id',
                'sponsorship_status_id' => 'nullable|exists:sponsorship_statuses,id',
                'person_type' => 'nullable|string|in:breadwinner,family_member,deceased_father,deceased_mother',
                'notes' => 'nullable|string',
            ]);

            // 🆕 معالجة الاسم الرباعي للمكفول - دمج 4 حقول في حقل واحد (في حالة التعديل)
            if ($request->has('orphan_first_name') || $request->has('orphan_father_name') ||
                $request->has('orphan_grandfather_name') || $request->has('orphan_family_name')) {
                $nameParts = array_filter([
                    $validatedData['orphan_first_name'] ?? '',
                    $validatedData['orphan_father_name'] ?? '',
                    $validatedData['orphan_grandfather_name'] ?? '',
                    $validatedData['orphan_family_name'] ?? ''
                ]);
                $validatedData['orphan_name'] = implode(' ', $nameParts);
            }

            // 🆕 معالجة الاسم الرباعي للمعيل - دمج 4 حقول في حقل واحد (في حالة التعديل)
            if ($request->has('guardian_first_name') || $request->has('guardian_father_name') ||
                $request->has('guardian_grandfather_name') || $request->has('guardian_family_name')) {
                $guardianNameParts = array_filter([
                    $validatedData['guardian_first_name'] ?? '',
                    $validatedData['guardian_father_name'] ?? '',
                    $validatedData['guardian_grandfather_name'] ?? '',
                    $validatedData['guardian_family_name'] ?? ''
                ]);
                $validatedData['guardian_name'] = implode(' ', $guardianNameParts);
            }

            // حفظ بيانات المعيل للاستخدام لاحقاً (لإنشائه في جدول data إذا لزم)
            $guardianFirstName = $validatedData['guardian_first_name'] ?? '';
            $guardianFatherName = $validatedData['guardian_father_name'] ?? '';
            $guardianGrandFatherName = $validatedData['guardian_grandfather_name'] ?? '';
            $guardianFamilyName = $validatedData['guardian_family_name'] ?? '';
            $guardianBirthDate = $validatedData['guardian_birth_date'] ?? null;
            $guardianIdentityNumber = $validatedData['guardian_identity_number'] ?? null;

            // 🆕 حفظ بيانات المكفول للاستخدام لاحقاً (لتحديثها في الجداول المرتبطة)
            $orphanFirstName = $validatedData['orphan_first_name'] ?? '';
            $orphanFatherName = $validatedData['orphan_father_name'] ?? '';
            $orphanGrandFatherName = $validatedData['orphan_grandfather_name'] ?? '';
            $orphanFamilyName = $validatedData['orphan_family_name'] ?? '';
            $sponsoredBirthDate = $validatedData['sponsored_birth_date'] ?? null;

            // إزالة الحقول الفردية لأنها غير موجودة في جدول sponsorships
            unset($validatedData['orphan_first_name']);
            unset($validatedData['orphan_father_name']);
            unset($validatedData['orphan_grandfather_name']);
            unset($validatedData['orphan_family_name']);
            unset($validatedData['guardian_first_name']);
            unset($validatedData['guardian_father_name']);
            unset($validatedData['guardian_grandfather_name']);
            unset($validatedData['guardian_family_name']);
            unset($validatedData['guardian_birth_date']);

            // Check if the request explicitly wants to update the sponsor/association
            $hasSponsorsInRequest = $request->has('sponsor_ids') || $request->has('sponsor_id');
            $sponsorIds = [];

            if ($hasSponsorsInRequest) {
                $sponsorIds = $validatedData['sponsor_ids'] ?? [];
                unset($validatedData['sponsor_ids']);

                if (!empty($sponsorIds)) {
                    $validatedData['sponsor_id'] = is_array($sponsorIds) ? $sponsorIds[0] : $sponsorIds;
                } elseif (isset($validatedData['sponsor_id'])) {
                    // If sponsor_id is sent directly, use it
                    $sponsorIds = [$validatedData['sponsor_id']];
                } else {
                    // If explicitly cleared
                    $validatedData['sponsor_id'] = null;
                }
            } else {
                // Keep existing sponsor_id untouched
                unset($validatedData['sponsor_ids']);
                unset($validatedData['sponsor_id']);
            }

            $sponsorship->update($validatedData);

            // ✍️ إضافة المستخدم الحالي إلى قائمة المعدلين
            $sponsorship->addUpdater(auth()->id());

            // Only sync/detach sponsors if they were sent in the request
            if ($hasSponsorsInRequest) {
                if (!empty($sponsorIds)) {
                    $sponsorship->sponsors()->sync($sponsorIds);
                } else {
                    $sponsorship->sponsors()->detach();
                }
            }

            // ============================================
            // 🆕 تحديث الجداول المرتبطة بناءً على نوع المكفول
            // ============================================
            $personType = $sponsorship->person_type;
            $sponsoredIdentity = $sponsorship->identity_number;

            if ($sponsoredIdentity) {
                if (in_array($personType, ['orphan', 'family_member'])) {
                    // Update re_people
                    DB::table('re_people')
                        ->where('person_id', $sponsoredIdentity)
                        ->update([
                            'first_name' => $orphanFirstName ?: DB::raw('first_name'),
                            'second_name' => $orphanFatherName ?: DB::raw('second_name'),
                            'third_name' => $orphanGrandFatherName ?: DB::raw('third_name'),
                            'last_name' => $orphanFamilyName ?: DB::raw('last_name'),
                            'person_birth_date' => $sponsoredBirthDate ?: DB::raw('person_birth_date'),
                            'updated_at' => now(),
                        ]);
                    Log::info('✅ تم تحديث بيانات المكفول في جدول re_people (Admin Edit)', ['identity' => $sponsoredIdentity]);
                } elseif (in_array($personType, ['deceased_father', 'deceased_mother'])) {
                    // Update dead_people
                    DB::table('dead_people')
                        ->where('dead_id_number', $sponsoredIdentity)
                        ->update([
                            'dead_first_name' => $orphanFirstName ?: DB::raw('dead_first_name'),
                            'dead_second_name' => $orphanFatherName ?: DB::raw('dead_second_name'),
                            'dead_third_name' => $orphanGrandFatherName ?: DB::raw('dead_third_name'),
                            'dead_last_name' => $orphanFamilyName ?: DB::raw('dead_last_name'),
                            'updated_at' => now(),
                        ]);
                    Log::info('✅ تم تحديث بيانات المكفول في جدول dead_people (Admin Edit)', ['identity' => $sponsoredIdentity]);
                } elseif ($personType === 'breadwinner') {
                    // Update data
                    DB::table('data')
                        ->where('data_id_number', $sponsoredIdentity)
                        ->update([
                            'data_first_name' => $orphanFirstName ?: DB::raw('data_first_name'),
                            'data_father_name' => $orphanFatherName ?: DB::raw('data_father_name'),
                            'data_grand_father_name' => $orphanGrandFatherName ?: DB::raw('data_grand_father_name'),
                            'data_family_name' => $orphanFamilyName ?: DB::raw('data_family_name'),
                            'data_birth_date' => $sponsoredBirthDate ?: DB::raw('data_birth_date'),
                            'updated_at' => now(),
                        ]);
                    Log::info('✅ تم تحديث بيانات المكفول في جدول data (Admin Edit)', ['identity' => $sponsoredIdentity]);
                }
            }

            // ============================================
            // 🆕 إنشاء/تحديث سجل المعيل في جدول data إذا لم يكن موجوداً
            // ============================================
            $guardianCreatedOrUpdated = false;
            if (!empty($guardianIdentityNumber) && !empty($validatedData['guardian_name'])) {
                $existingGuardian = Data::where('data_id_number', $guardianIdentityNumber)->first();

                if (!$existingGuardian) {
                    // إنشاء المعيل في جدول data
                    $uniqueFileId = generateUniqueReservedCode('data', 'file_id_number');

                    if ($uniqueFileId) {
                        Data::create([
                            'file_id_number' => $uniqueFileId,
                            'data_id_number' => $guardianIdentityNumber,
                            'data_first_name' => $guardianFirstName ?: null,
                            'data_father_name' => $guardianFatherName ?: null,
                            'data_grand_father_name' => $guardianGrandFatherName ?: null,
                            'data_family_name' => $guardianFamilyName ?: null,
                            'data_birth_date' => $guardianBirthDate ?? null,
                            'data_section_id' => 1,
                            'data_request_status' => 4,
                            'data_user_insert_data' => auth()->user()->name ?? 'System',
                        ]);

                        markCodeAsUsed($uniqueFileId);

                        // تحديث relation_id_number في الكفالة
                        $sponsorship->update(['relation_id_number' => $uniqueFileId]);

                        $guardianCreatedOrUpdated = true;

                        Log::info('✅ تم إنشاء المعيل في جدول data أثناء التحديث', [
                            'sponsorship_id' => $sponsorship->id,
                            'guardian_identity' => $guardianIdentityNumber,
                            'file_id_number' => $uniqueFileId
                        ]);
                    }
                } else {
                    // تحديث بيانات المعيل الموجود
                    $existingGuardian->update([
                        'data_first_name' => $guardianFirstName ?: $existingGuardian->data_first_name,
                        'data_father_name' => $guardianFatherName ?: $existingGuardian->data_father_name,
                        'data_grand_father_name' => $guardianGrandFatherName ?: $existingGuardian->data_grand_father_name,
                        'data_family_name' => $guardianFamilyName ?: $existingGuardian->data_family_name,
                        'data_birth_date' => $guardianBirthDate ?? $existingGuardian->data_birth_date,
                    ]);

                    // تحديث relation_id_number في الكفالة
                    if (empty($sponsorship->relation_id_number)) {
                        $sponsorship->update(['relation_id_number' => $existingGuardian->file_id_number]);
                    }

                    Log::info('✅ تم تحديث بيانات المعيل في جدول data', [
                        'sponsorship_id' => $sponsorship->id,
                        'guardian_identity' => $guardianIdentityNumber,
                        'file_id_number' => $existingGuardian->file_id_number
                    ]);
                }
            }

            // 🏦 تحديث الحسابات البنكية مع التحقق من التكرار
            if ($request->has('bank_accounts') && !empty($request->bank_accounts)) {
                $bankValidationService = app(BankAccountValidationService::class);
                $duplicateErrors = [];

                // 🆕 جلب رقم هوية المعيل ورقم الملف من جدول data
                $guardianIdentity = null;
                $guardianFileId = null;
                $guardianData = null;

                // أولاً: إذا كان هناك guardian_identity_number (معيل محدد)، نبحث به
                if (!empty($sponsorship->guardian_identity_number)) {
                    $guardianData = Data::where('data_id_number', $sponsorship->guardian_identity_number)
                                        ->select('data_id_number', 'file_id_number')
                                        ->first();

                    if ($guardianData) {
                        $guardianIdentity = $guardianData->data_id_number; // رقم هوية المعيل
                        $guardianFileId = $guardianData->file_id_number;   // رقم ملف المعيل

                        Log::info('✅ تم جلب بيانات المعيل من جدول data باستخدام guardian_identity_number (update)', [
                            'guardian_identity_number' => $sponsorship->guardian_identity_number,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId
                        ]);
                    }
                }

                // ثانياً: إذا لم يكن هناك معيل محدد (حالة المعيل نفسه)، نستخدم internal_file_number
                if (!$guardianData && !empty($sponsorship->internal_file_number)) {
                    $guardianData = Data::where('file_id_number', $sponsorship->internal_file_number)
                                        ->select('data_id_number', 'file_id_number')
                                        ->first();

                    if ($guardianData) {
                        $guardianIdentity = $guardianData->data_id_number; // رقم هوية المعيل
                        $guardianFileId = $guardianData->file_id_number;   // رقم ملف المعيل

                        Log::info('✅ تم جلب بيانات المعيل من جدول data باستخدام internal_file_number (معيل نفسه - update)', [
                            'internal_file_number' => $sponsorship->internal_file_number,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId
                        ]);
                    }
                }

                // ثالثاً: محاولة أخيرة باستخدام relation_id_number
                if (!$guardianData && !empty($sponsorship->relation_id_number)) {
                    $guardianData = Data::where('file_id_number', $sponsorship->relation_id_number)
                                        ->select('data_id_number', 'file_id_number')
                                        ->first();

                    if ($guardianData) {
                        $guardianIdentity = $guardianData->data_id_number; // رقم هوية المعيل
                        $guardianFileId = $guardianData->file_id_number;   // رقم ملف المعيل

                        Log::info('✅ تم جلب بيانات المعيل من جدول data باستخدام relation_id_number (update)', [
                            'relation_id_number' => $sponsorship->relation_id_number,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId
                        ]);
                    }
                }

                // 🆕 رابعاً: إذا لم يوجد في data، نستخدم internal_file_number مباشرة كـ fallback
                // هذا يحل مشكلة الأشخاص من re_people أو dead_people الذين ليس لديهم سجل في data
                if (!$guardianData && !empty($sponsorship->internal_file_number)) {
                    $guardianFileId = $sponsorship->internal_file_number;
                    // نستخدم identity_number من الكفالة إذا كان متاحاً
                    $guardianIdentity = $sponsorship->identity_number ?: $sponsorship->guardian_identity_number;

                    Log::info('🔄 استخدام internal_file_number مباشرة (الشخص غير موجود في جدول data - update)', [
                        'internal_file_number' => $sponsorship->internal_file_number,
                        'guardian_identity' => $guardianIdentity,
                        'note' => 'الربط يتم عبر internal_file_number بدلاً من file_id_number'
                    ]);
                }

                // التحقق من نجاح جلب البيانات - نحتاج فقط guardianFileId
                if ($guardianFileId) {
                        Log::info('🏦 البدء في تحديث الحسابات البنكية للكفالة', [
                            'sponsorship_id' => $sponsorship->id,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId,
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
                                // 🔍 التحقق من عدم تكرار الحساب البنكي
                                $excludeId = !empty($account['id']) ? $account['id'] : null;

                                $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
                                    'guardian_registration' => $guardianFileId,
                                    'person_owner_identity_number' => $account['person_owner_identity_number'] ?? null,
                                    're_phone_number' => $account['re_phone_number'] ?? null,
                                    'bank_name' => $account['bank_name'] ?? null,
                                    're_id_number' => $guardianIdentity
                                ], $excludeId);

                                if ($duplicateCheck['is_duplicate']) {
                                    $duplicateErrors[] = [
                                        'index' => $index + 1,
                                        'message' => $duplicateCheck['message']
                                    ];
                                    Log::warning('⚠️ محاولة تحديث إلى حساب بنكي مكرر', [
                                        'index' => $index,
                                        'existing_account' => $duplicateCheck['existing_account']
                                    ]);
                                    continue; // تجاوز هذا الحساب المكرر
                                }

                                // التحقق من وجود bank_name (إلزامي)
                                if (empty($account['bank_name'])) {
                                    Log::warning('⚠️ تم تجاهل حساب بنكي - bank_name مطلوب', ['index' => $index]);
                                    continue;
                                }

                                // 🆕 التحقق إذا كان هذا أول حساب بنكي للشخص (يُعتمد تلقائياً)
                                $existingAccountsCount = GuardianBankAccount::where('guardian_registration', $guardianFileId)->count();
                                $isFirstAccount = ($existingAccountsCount == 0);

                                $bankAccountData = [
                                    'guardian_registration' => $guardianFileId, // استخدام file_id_number بدلاً من identity_number
                                    're_id_number' => $guardianIdentity, // رقم هوية ولي الأمر (يتم جلبه تلقائياً من data)
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
                } else {
                    Log::error('❌ فشل جلب بيانات المعيل من جدول data (update)', [
                        'sponsorship_id' => $sponsorship->id,
                        'guardian_identity_number' => $sponsorship->guardian_identity_number,
                        'internal_file_number' => $sponsorship->internal_file_number,
                        'relation_id_number' => $sponsorship->relation_id_number,
                        'guardian_identity' => $guardianIdentity,
                        'guardian_file_id' => $guardianFileId
                    ]);
                }
            }

            DB::commit();

            $message = 'تم تحديث الكفالة بنجاح';
            $additionalInfo = [];

            // إضافة تحذيرات التكرار البنكي إن وجدت
            if (!empty($duplicateErrors)) {
                $additionalInfo['bank_duplicates'] = $duplicateErrors;
                $message .= '. تم تجاهل ' . count($duplicateErrors) . ' حساب بنكي مكرر';
            }

            // 📢 تسجيل التحديث في طابور المزامنة للهواتف (طبقة الـ Action)
            try {
                \App\Models\ServerSyncAction::create([
                    'action_type' => 'sponsorship_update',
                    'entity_id' => $id,
                    'payload' => json_encode(['sponsorship_id' => $id]),
                    'status' => 'pending'
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('❌ فشل في إنشاء ServerSyncAction', ['error' => $e->getMessage()]);
            }

            // 📢 بث حدث التحديث المباشر للموبايل (السيرفر المحلي الخاص)
            try {
                $payloadData = \App\Http\Controllers\Api\SponsorshipSyncController::getSingleEnrichedSponsorship($id);
                if ($payloadData) {
                    $payloadData['event'] = 'SponsorshipUpdated';
                } else {
                    $payloadData = [
                        'event' => 'SponsorshipUpdated',
                        'id' => $id
                    ];
                }
                \Illuminate\Support\Facades\Http::post('http://127.0.0.1:6001/broadcast', $payloadData);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('❌ فشل في بث الحدث WebSocket المحلي', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'info' => $additionalInfo
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في التحديث:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified sponsorship
     */
    public function destroy($id)
    {
        try {
            $sponsorship = Sponsorship::findOrFail($id);

            // 🔥 FIX: حذف علاقات الجمعيات أولاً قبل حذف الكفالة
            // هذا يضمن عدم بقاء سجلات يتيمة في جدول sponsorship_sponsor
            $sponsorship->sponsors()->detach();

            $sponsorship->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الكفالة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display sponsored people - Shows sponsorships table
     */
    public function sponsored(SponsorshipsDataTable $dataTable)
    {
        $sponsors = Sponsor::all();
        $sponsorshipTypes = TypeOfGuarantee::all();
        $sponsorshipStatuses = SponsorshipStatus::all();
        $bankNames = BankName::all();

        return $dataTable->render('admin.dashboard.sponsorships.sponsored', compact(
            'sponsors',
            'sponsorshipTypes',
            'sponsorshipStatuses',
            'bankNames'
        ));
    }

    /**
     * Display unsponsored people - Shows unified people table
     */
    public function unsponsored(UnifiedPeopleDataTable $dataTable)
    {
        $sponsors = Sponsor::all();
        $sponsorshipTypes = TypeOfGuarantee::all();
        $sponsorshipStatuses = SponsorshipStatus::all();
        $bankNames = BankName::all();

        return $dataTable->render('admin.dashboard.sponsorships.unsponsored', compact(
            'sponsors',
            'sponsorshipTypes',
            'sponsorshipStatuses',
            'bankNames'
        ));
    }

    /**
     * Get person details for sponsorship modal
     */
    public function getPersonDetails(Request $request)
    {
        try {
            $recordId = $request->input('record_id');
            $recordType = $request->input('record_type');

            $personData = [
                'success' => true,
                'person_type' => '',
                'needs_guardian' => false,
                'identity_number' => '',
                'full_name' => '',
                'first_name' => '', // 🆕 الاسم الأول
                'second_name' => '', // 🆕 اسم الأب
                'third_name' => '', // 🆕 اسم الجد
                'last_name' => '', // 🆕 اسم العائلة
                'guardian_name' => '',
                'guardian_identity' => '',
                'guardian_file_id' => '', // 🔥 إضافة رقم ملف المعيل
                'file_id' => '',
                'new_file_id_generated' => false,
                'reserved_file_id' => null,
                'birth_date' => null, // 🆕 إضافة تاريخ الميلاد
                'birth_date_source' => null, // 🆕 مصدر تاريخ الميلاد (database أو civil_registry)
            ];

            // 🆕 توليد file_id_number جديد للأشخاص من re_people و dead_people
            if (in_array($recordType, ['re_people', 'dead_people'])) {
                $newFileId = generateUniqueReservedCode('data', 'file_id_number');

                if ($newFileId) {
                    $personData['reserved_file_id'] = $newFileId;
                    $personData['new_file_id_generated'] = true;

                    Log::info('🆕 تم توليد وحجز file_id_number جديد', [
                        'record_type' => $recordType,
                        'record_id' => $recordId,
                        'new_file_id' => $newFileId,
                        'status' => 'محجوز - في انتظار إنشاء الكفالة'
                    ]);
                }
            }

            if ($recordType === 'data') {
                // معيل من جدول data
                $record = Data::find($recordId);
                if ($record) {
                    $personData['person_type'] = 'breadwinner';
                    $personData['needs_guardian'] = false; // المعيل لا يحتاج معيل
                    $personData['identity_number'] = $record->data_id_number;
                    $personData['full_name'] = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
                    $personData['first_name'] = $record->data_first_name ?? '';
                    $personData['second_name'] = $record->data_father_name ?? '';
                    $personData['third_name'] = $record->data_grand_father_name ?? '';
                    $personData['last_name'] = $record->data_family_name ?? '';
                    $personData['file_id'] = $record->file_id_number;

                    // 🆕 جلب تاريخ الميلاد
                    if (!empty($record->data_birth_date)) {
                        $personData['birth_date'] = $record->data_birth_date;
                        $personData['birth_date_source'] = 'database';
                    } else {
                        // البحث في السجل المدني إذا لم يكن تاريخ الميلاد موجوداً
                        $civilData = $this->searchCivilRegistryForBirthDate($record->data_id_number);
                        if ($civilData) {
                            $personData['birth_date'] = $civilData['birth_date'];
                            $personData['birth_date_source'] = 'civil_registry';
                            // تحديث الاسم من السجل المدني إذا كان فارغاً
                            if (empty($personData['full_name']) && !empty($civilData['full_name'])) {
                                $personData['full_name'] = $civilData['full_name'];
                                $personData['first_name'] = $civilData['first_name'];
                                $personData['second_name'] = $civilData['second_name'];
                                $personData['third_name'] = $civilData['third_name'];
                                $personData['last_name'] = $civilData['last_name'];
                            }
                        }
                    }

                    // 🆕 توليد رقم ملف جديد للمعيل (سيتم استخدامه كـ internal_file_number في الكفالة)
                    $newFileId = generateUniqueReservedCode('sponsorships', 'internal_file_number');
                    if ($newFileId) {
                        $personData['reserved_file_id'] = $newFileId;
                        $personData['new_file_id_generated'] = true;

                        Log::info('🆕 تم توليد وحجز internal_file_number جديد للمعيل', [
                            'record_type' => $recordType,
                            'record_id' => $recordId,
                            'data_file_id' => $record->file_id_number,
                            'new_internal_file_id' => $newFileId,
                            'status' => 'محجوز - في انتظار إنشاء الكفالة'
                        ]);
                    }
                }
            }
            elseif ($recordType === 're_people') {
                // يتيم أو فرد عائلة من جدول re_people
                $record = RePeople::with(['dataRecord', 'guaranteeType'])->where('person_id', $recordId)->first();

                if (!$record) {
                    $record = RePeople::with(['dataRecord', 'guaranteeType'])->where('registration_id', $recordId)->first();
                }

                if ($record) {
                    // تحديد نوع الشخص
                    $guaranteeType = optional($record->guaranteeType)->description ?? '';
                    $isOrphan = stripos($guaranteeType, 'يتيم') !== false;

                    $personData['person_type'] = $isOrphan ? 'orphan' : 'family_member';
                    $personData['needs_guardian'] = true; // اليتيم وفرد العائلة يحتاجون معيل
                    $personData['identity_number'] = $record->person_id;
                    $personData['full_name'] = trim("{$record->first_name} {$record->second_name} {$record->third_name} {$record->last_name}");
                    $personData['first_name'] = $record->first_name ?? '';
                    $personData['second_name'] = $record->second_name ?? '';
                    $personData['third_name'] = $record->third_name ?? '';
                    $personData['last_name'] = $record->last_name ?? '';
                    $personData['file_id'] = $record->registration_id;

                    // 🆕 جلب تاريخ الميلاد
                    if (!empty($record->birth_date)) {
                        $personData['birth_date'] = $record->birth_date;
                        $personData['birth_date_source'] = 'database';
                    } else {
                        // البحث في السجل المدني إذا لم يكن تاريخ الميلاد موجوداً
                        $civilData = $this->searchCivilRegistryForBirthDate($record->person_id);
                        if ($civilData) {
                            $personData['birth_date'] = $civilData['birth_date'];
                            $personData['birth_date_source'] = 'civil_registry';
                            // تحديث الأسماء من السجل المدني إذا كانت فارغة
                            if (empty($personData['first_name']) && !empty($civilData['first_name'])) {
                                $personData['first_name'] = $civilData['first_name'];
                                $personData['second_name'] = $civilData['second_name'];
                                $personData['third_name'] = $civilData['third_name'];
                                $personData['last_name'] = $civilData['last_name'];
                                $personData['full_name'] = $civilData['full_name'];
                            }
                        }
                    }

                    // جلب معلومات المعيل
                    if ($record->dataRecord) {
                        $personData['guardian_name'] = trim("{$record->dataRecord->data_first_name} {$record->dataRecord->data_father_name} {$record->dataRecord->data_grand_father_name} {$record->dataRecord->data_family_name}");
                        $personData['guardian_identity'] = $record->dataRecord->data_id_number;
                        $personData['guardian_file_id'] = $record->dataRecord->file_id_number; // 🔥 إضافة رقم ملف المعيل
                    }
                }
            }
            elseif ($recordType === 'dead_people') {
                // متوفي من جدول dead_people
                // استخراج نوع المتوفي (father أو mother) من record_id
                $parts = explode('_', $recordId);
                $parentType = $parts[0] ?? 'father';
                $fileId = $parts[1] ?? null;

                if ($fileId) {
                    $record = DeadPepole::where('re_file_id', $fileId)->first();

                    if ($record) {
                        if ($parentType === 'father') {
                            $personData['person_type'] = 'deceased_father';
                            $personData['identity_number'] = $record->father_id;
                            $personData['full_name'] = trim("{$record->father_first_name} {$record->father_second_name} {$record->father_third_name} {$record->father_last_name}");
                            $personData['first_name'] = $record->father_first_name ?? '';
                            $personData['second_name'] = $record->father_second_name ?? '';
                            $personData['third_name'] = $record->father_third_name ?? '';
                            $personData['last_name'] = $record->father_last_name ?? '';

                            // 🆕 البحث عن تاريخ الميلاد في السجل المدني
                            if (!empty($record->father_id)) {
                                $civilData = $this->searchCivilRegistryForBirthDate($record->father_id);
                                if ($civilData) {
                                    $personData['birth_date'] = $civilData['birth_date'];
                                    $personData['birth_date_source'] = 'civil_registry';
                                    // تحديث الأسماء من السجل المدني إذا كانت فارغة
                                    if (empty($personData['first_name']) && !empty($civilData['first_name'])) {
                                        $personData['first_name'] = $civilData['first_name'];
                                        $personData['second_name'] = $civilData['second_name'];
                                        $personData['third_name'] = $civilData['third_name'];
                                        $personData['last_name'] = $civilData['last_name'];
                                        $personData['full_name'] = $civilData['full_name'];
                                    }
                                }
                            }
                        } else {
                            $personData['person_type'] = 'deceased_mother';
                            $personData['identity_number'] = $record->mother_id;
                            $personData['full_name'] = trim("{$record->mother_first_name} {$record->mother_second_name} {$record->mother_third_name} {$record->mother_last_name}");
                            $personData['first_name'] = $record->mother_first_name ?? '';
                            $personData['second_name'] = $record->mother_second_name ?? '';
                            $personData['third_name'] = $record->mother_third_name ?? '';
                            $personData['last_name'] = $record->mother_last_name ?? '';

                            // 🆕 البحث عن تاريخ الميلاد في السجل المدني
                            if (!empty($record->mother_id)) {
                                $civilData = $this->searchCivilRegistryForBirthDate($record->mother_id);
                                if ($civilData) {
                                    $personData['birth_date'] = $civilData['birth_date'];
                                    $personData['birth_date_source'] = 'civil_registry';
                                    // تحديث الأسماء من السجل المدني إذا كانت فارغة
                                    if (empty($personData['first_name']) && !empty($civilData['first_name'])) {
                                        $personData['first_name'] = $civilData['first_name'];
                                        $personData['second_name'] = $civilData['second_name'];
                                        $personData['third_name'] = $civilData['third_name'];
                                        $personData['last_name'] = $civilData['last_name'];
                                        $personData['full_name'] = $civilData['full_name'];
                                    }
                                }
                            }
                        }
                        $personData['needs_guardian'] = false; // المتوفي لا يحتاج معيل
                        $personData['file_id'] = $record->re_file_id;
                    }
                }
            }

            return response()->json($personData);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب معلومات الشخص:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب معلومات الشخص'
            ], 500);
        }
    }

    /**
     * Update sponsorship status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'sponsorship_status_id' => 'required|exists:sponsorship_statuses,id'
            ]);

            $sponsorship = Sponsorship::findOrFail($id);
            $oldStatus = $sponsorship->sponsorshipStatus ? $sponsorship->sponsorshipStatus->description : 'غير محدد';

            $sponsorship->sponsorship_status_id = $request->sponsorship_status_id;
            $sponsorship->save();

            // ✍️ إضافة المستخدم الحالي إلى قائمة المعدلين عند تغيير الحالة
            $sponsorship->addUpdater(auth()->id());

            $newStatus = $sponsorship->fresh()->sponsorshipStatus->description;

            Log::info('✅ تم تحديث حالة الكفالة', [
                'sponsorship_id' => $id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user_id' => auth()->id()
            ]);

            // 📢 بث حدث التحديث المباشر للموبايل (السيرفر المحلي الخاص)
            try {
                $payloadData = \App\Http\Controllers\Api\SponsorshipSyncController::getSingleEnrichedSponsorship($id);
                if ($payloadData) {
                    $payloadData['event'] = 'SponsorshipUpdated';
                } else {
                    $payloadData = [
                        'event' => 'SponsorshipUpdated',
                        'id' => $id
                    ];
                }
                \Illuminate\Support\Facades\Http::post('http://127.0.0.1:6001/broadcast', $payloadData);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('❌ فشل في بث الحدث WebSocket المحلي', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => "تم تحديث حالة الكفالة من '{$oldStatus}' إلى '{$newStatus}'"
            ]);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في تحديث حالة الكفالة:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'sponsorship_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة الكفالة'
            ], 500);
        }
    }

    /**
     * إعادة توليد رقم الملف الداخلي لكفالة
     */
    public function regenerateFileNumber(Request $request, $id)
    {
        try {
            $sponsorship = Sponsorship::findOrFail($id);
            $oldNumber = $sponsorship->internal_file_number;

            if (empty($oldNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الملف الداخلي فارغ بالفعل'
                ], 400);
            }

            $oldNumberFormatted = $oldNumber;

            // تحرير الرقم القديم من reserved_codes (تعيين used = false)
            DB::table('reserved_codes')
                ->where('code', $oldNumber)
                ->update(['used' => false, 'updated_at' => now()]);

            // توليد رقم جديد فريد
            $newNumber = generateUniqueReservedCode('sponsorships', 'internal_file_number');

            if (empty($newNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في توليد رقم ملف جديد'
                ], 500);
            }

            // تحديث الكفالة بالرقم الجديد
            $sponsorship->internal_file_number = $newNumber;
            $sponsorship->save();
            $sponsorship->addUpdater(auth()->id());

            // تعليم الرقم الجديد كمستخدم
            markCodeAsUsed($newNumber, auth()->id());

            Log::info('🔄 إعادة توليد رقم الملف الداخلي', [
                'sponsorship_id' => $id,
                'old_number' => $oldNumberFormatted,
                'new_number' => $newNumber,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم تغيير رقم الملف من '{$oldNumberFormatted}' إلى '{$newNumber}'",
                'old_number' => $oldNumberFormatted,
                'new_number' => $newNumber
            ]);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في إعادة توليد رقم الملف الداخلي:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'sponsorship_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إعادة توليد رقم الملف الداخلي'
            ], 500);
        }
    }

    /**
     * تصدير بيانات الكفالات إلى Excel مع الفلاتر
     */
    public function export(Request $request)
    {
        try {
            $exportType = $request->get('export_type', 'full'); // full أو login

            Log::info('🎯 بدء عملية تصدير الكفالات', [
                'export_type' => $exportType,
                'filters' => $request->all()
            ]);

            // بناء الاستعلام مع الفلاتر - INCLUDING BANKING DATA
            $query = Sponsorship::with([
                'sponsor',
                'sponsors',
                'sponsorshipType',
                'sponsorshipStatus',
                'creator',
                'guardianData',
                'guardianData.city',
                'guardianData.province',
                'relationData',
                'relationData.city',
                'relationData.province'
            ]);

            // تطبيق فلتر المؤسسة الكافلة
            if ($request->has('sponsor_id') && !empty($request->get('sponsor_id'))) {
                $sponsorId = $request->get('sponsor_id');
                $query->whereHas('sponsors', function($q) use ($sponsorId) {
                    $q->where('sponsors.id', $sponsorId);
                });
            }

            // تطبيق فلتر نوع الكفالة
            if ($request->has('sponsorship_type_id') && !empty($request->get('sponsorship_type_id'))) {
                $query->where('sponsorship_type_id', $request->get('sponsorship_type_id'));
            }

            // تطبيق فلتر حالة الكفالة
            if ($request->has('sponsorship_status_id') && !empty($request->get('sponsorship_status_id'))) {
                $query->where('sponsorship_status_id', $request->get('sponsorship_status_id'));
            }

            // تطبيق فلتر البحث
            if ($request->has('search') && !empty($request->get('search'))) {
                $searchTerm = $request->get('search');
                $searchWords = array_filter(array_map('trim', explode(' ', $searchTerm)));

                if (!empty($searchWords)) {
                    $query->where(function ($q) use ($searchTerm) {
                        // استخدام البحث الذكي في جميع الحقول النصية
                        $this->addSmartSearch($q, 'orphan_name', $searchTerm, false);
                        $this->addSmartSearch($q, 'guardian_name', $searchTerm, false);
                        $this->addSmartSearch($q, 'sponsoring_organization', $searchTerm, false);

                        // البحث في الأرقام (بدون normalization)
                        $q->orWhere('identity_number', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('guardian_identity_number', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('internal_file_number', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('external_file_number', 'LIKE', "%{$searchTerm}%");
                    });
                }
            }

            // ✅ استخدام get() بدلاً من paginate() لجلب جميع السجلات
            $sponsorships = $query->orderBy('id', 'desc')->get();

            Log::info('✅ تم جلب البيانات للتصدير', [
                'count' => $sponsorships->count(),
                'export_type' => $exportType
            ]);

            // إنشاء ملف Excel باستخدام PhpSpreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // ✅ جلب الأعمدة المرئية من الطلب
            $visibleColumns = $request->get('visible_columns', []);

            Log::info('📋 الأعمدة المرئية المستلمة', [
                'visible_columns' => $visibleColumns,
                'count' => count($visibleColumns)
            ]);

            // تحديد الرؤوس والبيانات حسب نوع التصدير
            if ($exportType === 'login') {
                // تصدير بيانات تسجيل الدخول
                $headers = [
                    'المؤسسة',
                    'اسم المكفول',
                    'اسم المستخدم',  // رقم الهوية
                    'كلمة المرور',    // رقم الملف (خارجي أو داخلي)
                    'رقم هاتف المعيل', // 🆕 رقم الهاتف من جدول data
                    'رابط الدخول',    // 🆕 رابط الدخول المباشر
                ];

                $sheet->fromArray($headers, NULL, 'A1');

                // تنسيق رؤوس الأعمدة
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '009EF7']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ];
                $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

                // كتابة البيانات
                $row = 2;
                foreach ($sponsorships as $sponsorship) {
                    $sponsorNames = $sponsorship->sponsors->pluck('sponsor_name')->implode(' + ');

                    // 🔐 استخدام الرقم الداخلي (internal_file_number) ككلمة مرور
                    $fileNumber = $sponsorship->internal_file_number ?: $sponsorship->external_file_number;

                    // 🆕 جلب رقم هاتف المعيل من جدول data أو portal_general_registration_field_values
                    $guardianPhone = $this->getGuardianPhone($sponsorship->guardian_identity_number);

                    // 🆕 بناء رابط الدخول المباشر (المختصر)
                    $autoLoginUrl = '';
                    if ($sponsorship->identity_number && $fileNumber) {
                        $autoLoginUrl = url('/s/' . $sponsorship->identity_number . '-' . $fileNumber);
                    }

                    $data = [
                        $sponsorNames ?: '-',
                        $sponsorship->orphan_name ?: '-',
                        $sponsorship->identity_number ?: '-',  // اسم المستخدم
                        $fileNumber ?: '-',                    // كلمة المرور (الرقم الداخلي)
                        $guardianPhone ?: '-',                 // 🆕 رقم هاتف المعيل
                        $autoLoginUrl ?: '-',                  // 🆕 رابط الدخول المباشر
                    ];

                    $sheet->fromArray($data, NULL, 'A' . $row);
                    $row++;
                }

                // ضبط عرض الأعمدة تلقائياً
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = 'sponsorships_login_' . date('Y-m-d_His') . '.xlsx';

            } else {
                // ✅ تصدير كامل البيانات - بناءً على الأعمدة المرئية

                // تعريف جميع الأعمدة المتاحة
                $allColumns = [
                    'orphan_name' => ['title' => 'الإسم', 'callback' => function($s) { return $s->orphan_name ?: '-'; }],
                    'identity_number' => ['title' => 'رقم الهوية', 'callback' => function($s) { return $s->identity_number ?: '-'; }],
                    'guardian_name' => ['title' => 'إسم المعيل', 'callback' => function($s) { return $s->guardian_name ?: '-'; }],
                    'guardian_identity' => ['title' => 'رقم هوية المعيل', 'callback' => function($s) { return $s->guardian_identity_number ?: '-'; }],
                    'sponsor_name' => ['title' => 'إسم المؤسسة الكافلة', 'callback' => function($s) {
                        return $s->sponsors && $s->sponsors->count() > 0 ? $s->sponsors->pluck('sponsor_name')->implode(' + ') : ($s->sponsor ? $s->sponsor->sponsor_name : '-');
                    }],
                    'sponsoring_organization' => ['title' => 'إسم الكافل', 'callback' => function($s) { return $s->sponsoring_organization ?: '-'; }],
                    'internal_file_number' => ['title' => 'رقم الملف الداخلي', 'callback' => function($s) { return $s->internal_file_number ?: '-'; }],
                    'external_file_number' => ['title' => 'رقم الملف الخارجي', 'callback' => function($s) { return $s->external_file_number ?: '-'; }],
                    'sponsorship_duration' => ['title' => 'مدة الكفالة', 'callback' => function($s) {
                        return $s->sponsorship_duration_months ? $s->sponsorship_duration_months . ' شهر' : '-';
                    }],
                    'sponsorship_period' => ['title' => 'فترة الكفالة', 'callback' => function($s) {
                        $start = $s->sponsorship_start_date ? $s->sponsorship_start_date->format('Y-m-d') : '-';
                        $end = $s->sponsorship_end_date ? $s->sponsorship_end_date->format('Y-m-d') : '-';
                        return $start . ' → ' . $end;
                    }],
                    'sponsorship_type' => ['title' => 'نوع الكفالة', 'callback' => function($s) { return $s->sponsorshipType?->description ?: '-'; }],
                    'sponsorship_status' => ['title' => 'حالة الكفالة', 'callback' => function($s) { return $s->sponsorshipStatus?->description ?: '-'; }],
                    'city' => ['title' => 'المدينة', 'callback' => function($s) {
                        if ($s->relationData && $s->relationData->city) return $s->relationData->city->city;
                        if ($s->guardianData && $s->guardianData->city) return $s->guardianData->city->city;
                        return '-';
                    }],
                    'address' => ['title' => 'العنوان', 'callback' => function($s) {
                        if ($s->relationData && $s->relationData->data_current_address) return $s->relationData->data_current_address;
                        if ($s->guardianData && $s->guardianData->data_current_address) return $s->guardianData->data_current_address;
                        return '-';
                    }],
                    'bank_name' => ['title' => 'إسم البنك', 'callback' => function($s) {
                        $fileId = $s->relationData?->file_id_number ?: $s->guardianData?->file_id_number;
                        if ($fileId) {
                            $account = GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                            if ($account) {
                                $bankName = $account->bank_name;
                                if (is_numeric($bankName)) {
                                    $bankModel = \App\Models\BankName::find($bankName);
                                    return $bankModel ? $bankModel->description : $bankName;
                                }
                                return $bankName;
                            }
                        }
                        return '-';
                    }],
                    'account_holder_name' => ['title' => 'إسم صاحب الحساب', 'callback' => function($s) {
                        $fileId = $s->relationData?->file_id_number ?: $s->guardianData?->file_id_number;
                        if ($fileId) {
                            $account = GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                            return $account?->re_guardian_name ?: '-';
                        }
                        return '-';
                    }],
                    'account_holder_id' => ['title' => 'رقم هوية صاحب الحساب', 'callback' => function($s) {
                        $fileId = $s->relationData?->file_id_number ?: $s->guardianData?->file_id_number;
                        if ($fileId) {
                            $account = GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                            return $account?->person_owner_identity_number ?: '-';
                        }
                        return '-';
                    }],
                    'account_phone' => ['title' => 'رقم الجوال المربوط بالحساب', 'callback' => function($s) {
                        $fileId = $s->relationData?->file_id_number ?: $s->guardianData?->file_id_number;
                        if ($fileId) {
                            $account = GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                            return $account?->re_phone_number ?: '-';
                        }
                        return '-';
                    }],
                    'iban_shekel_export' => ['title' => 'حساب شيكل (IBAN)', 'callback' => function($s) {
                        $fileId = $s->relationData?->file_id_number ?: $s->guardianData?->file_id_number;
                        if ($fileId) {
                            $account = GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                            return $account?->iban_shekel ?: '-';
                        }
                        return '-';
                    }],
                    'iban_usd_export' => ['title' => 'حساب دولار (IBAN)', 'callback' => function($s) {
                        $fileId = $s->relationData?->file_id_number ?: $s->guardianData?->file_id_number;
                        if ($fileId) {
                            $account = GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                            return $account?->iban_usd ?: '-';
                        }
                        return '-';
                    }],
                    'remaining_days' => ['title' => 'المتبقي', 'callback' => function($s) {
                        if ($s->remaining_days !== null) {
                            if ($s->remaining_days == 0) return 'منتهية';
                            return $s->remaining_days . ' يوم';
                        }
                        return 'غير محدد';
                    }],
                    'created_at' => ['title' => 'تاريخ الإضافة', 'callback' => function($s) { return $s->created_at->format('Y-m-d H:i:s'); }],
                ];

                // ✅ تحديد الأعمدة المراد تصديرها بناءً على الأعمدة المرئية
                $columnsToExport = [];

                if (!empty($visibleColumns) && is_array($visibleColumns)) {
                    // استخدام الأعمدة المرئية المرسلة من الواجهة
                    Log::info('✅ استخدام الأعمدة المرئية من الواجهة');
                    foreach ($visibleColumns as $colName) {
                        if (isset($allColumns[$colName])) {
                            $columnsToExport[$colName] = $allColumns[$colName];
                        } else {
                            Log::warning('⚠️ عمود غير موجود في التعريفات', ['column' => $colName]);
                        }
                    }
                } else {
                    // إذا لم يتم إرسال أعمدة، استخدم جميع الأعمدة
                    Log::info('⚠️ لم يتم إرسال أعمدة مرئية، سيتم استخدام جميع الأعمدة');
                    $columnsToExport = $allColumns;
                }

                Log::info('📊 عدد الأعمدة للتصدير', ['count' => count($columnsToExport)]);

                // إنشاء رؤوس الأعمدة
                $headers = array_map(function($col) { return $col['title']; }, $columnsToExport);
                $sheet->fromArray($headers, NULL, 'A1');

                // تنسيق رؤوس الأعمدة
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '009EF7']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ];

                $lastColumn = chr(64 + count($columnsToExport)); // A=65, so we use 64+count
                $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);

                // كتابة البيانات
                $row = 2;
                foreach ($sponsorships as $sponsorship) {
                    $data = [];
                    foreach ($columnsToExport as $colKey => $colDef) {
                        $data[] = $colDef['callback']($sponsorship);
                    }
                    $sheet->fromArray($data, NULL, 'A' . $row);
                    $row++;
                }

                // ضبط عرض الأعمدة تلقائياً
                $colIndex = 'A';
                for ($i = 0; $i < count($columnsToExport); $i++) {
                    $sheet->getColumnDimension($colIndex)->setAutoSize(true);
                    $colIndex++;
                }

                $filename = 'sponsorships_' . date('Y-m-d_His') . '.xlsx';
            }

            // إنشاء الملف
            $writer = new Xlsx($spreadsheet);

            // حفظ الملف مؤقتاً
            $tempFile = tempnam(sys_get_temp_dir(), 'sponsorships_');
            $writer->save($tempFile);

            Log::info('✅ تم إنشاء ملف Excel بنجاح', [
                'filename' => $filename,
                'rows' => $sponsorships->count()
            ]);

            // إرجاع الملف للتحميل
            return response()->download($tempFile, $filename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في تصدير الكفالات:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'حدث خطأ أثناء تصدير البيانات: ' . $e->getMessage());
        }
    }

    /**
     * استيراد بيانات الكفالات من ملف Excel
     */
    public function import(Request $request)
    {
        try {
            // التحقق من صحة الملف أولاً
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
            ]);

            // التحقق اليدوي من وجود البيانات المرجعية
            $validationErrors = [];

            // 1. التحقق من المؤسسة الكافلة
            if (empty($request->sponsor_id)) {
                $validationErrors[] = 'يجب اختيار المؤسسة الكافلة';
            } else {
                $sponsor = \App\Models\Sponsor::find($request->sponsor_id);
                if (!$sponsor) {
                    $validationErrors[] = "المؤسسة الكافلة المحددة (ID: {$request->sponsor_id}) غير موجودة في النظام";
                }
            }

            // 2. التحقق من نوع الكفالة
            if (empty($request->sponsorship_type_id)) {
                $validationErrors[] = 'يجب اختيار نوع الكفالة';
            } else {
                $sponsorshipType = \App\Models\TypeOfGuarantee::find($request->sponsorship_type_id);
                if (!$sponsorshipType) {
                    $validationErrors[] = "نوع الكفالة المحدد (ID: {$request->sponsorship_type_id}) غير موجود في النظام";
                }
            }

            // 3. التحقق من حالة الكفالة (اختياري - سيتم استخدام "جديد" كافتراضي)
            if (!empty($request->sponsorship_status_id)) {
                $sponsorshipStatus = \App\Models\SponsorshipStatus::find($request->sponsorship_status_id);
                if (!$sponsorshipStatus) {
                    $validationErrors[] = "حالة الكفالة المحددة (ID: {$request->sponsorship_status_id}) غير موجودة في النظام";
                }
            }

            // إذا كانت هناك أخطاء في التحقق، إرجاعها للمستخدم
            if (!empty($validationErrors)) {
                return back()->with('error', implode('<br>', $validationErrors));
            }

            Log::info('🎯 بدء عملية فحص/استيراد الكفالات من Excel', [
                'sponsor_id' => $request->sponsor_id,
                'sponsorship_type_id' => $request->sponsorship_type_id,
                'sponsorship_status_id' => $request->sponsorship_status_id,
                'check_only' => $request->has('check_only'),
            ]);

            $file = $request->file('excel_file');

            // قراءة ملف Excel
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // إزالة صف الرؤوس والبحث عن الأعمدة حسب الاسم
            $headers = array_shift($rows);

            // إنشاء map للأعمدة بناءً على الأسماء (normalize لمطابقة أفضل)
            $columnMap = [];
            foreach ($headers as $index => $header) {
                $normalizedHeader = $this->normalizeArabicText(trim($header));
                $columnMap[$normalizedHeader] = $index;
            }

            Log::info('📋 Headers Found:', [
                'headers' => $headers,
                'columnMap' => $columnMap
            ]);

            // تعريف أسماء الأعمدة المطلوبة (بعد normalization)
            $requiredColumns = [
                'id' => $this->normalizeArabicText('ID'),
                'external_file_number' => $this->normalizeArabicText('ID'), // نفس ID
                'sponsored_name' => $this->normalizeArabicText('اسم المكفول'), // تغيير من "اسم اليتيم"
                'sponsored_identity' => $this->normalizeArabicText('رقم هوية المكفول'), // تغيير من "رقم هوية اليتيم"
                'person_type' => $this->normalizeArabicText('نوع الشخص'), // عمود جديد للتصنيف
                'guardian_name' => $this->normalizeArabicText('اسم المعيل'),
                'guardian_identity_number' => $this->normalizeArabicText('هوية المعيل'),
                'data_phone_number' => $this->normalizeArabicText('الهاتف'),
                'data_alt_phone_number' => $this->normalizeArabicText('جوال بديل'),
                'sponsoring_organization' => $this->normalizeArabicText('اسم الكافل'),
                'sponsoring_organization_alt' => $this->normalizeArabicText('المؤسسة'),
                'person_owner_identity_number' => $this->normalizeArabicText('هوية صاحب المحفظة'),
                'person_owner_identity_number_alt' => $this->normalizeArabicText('هوية المحفظة'),
                're_guardian_name' => $this->normalizeArabicText('صاحب المحفظة'),
                'bank_name' => $this->normalizeArabicText('المحفظة'),
                're_phone_number' => $this->normalizeArabicText('جوال المحفظة'),
            ];

            // الخطوة 1: التحقق المسبق من جميع البيانات قبل البدء بالاستيراد
            $preValidationErrors = [];
            $uniqueBanks = [];
            $uniquePersons = []; // تغيير من uniqueGuardians لتشمل جميع الأشخاص
            $personsToCreate = []; // قائمة الأشخاص الذين يحتاجون للإنشاء
            $incompleteBankData = []; // 🆕 قائمة الصفوف التي بها بيانات بنك ناقصة
            $existingActiveAccounts = []; // 🆕 قائمة الحسابات البنكية النشطة التي ستتأثر (سيتم تحويلها إلى 0)

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                // تخطي الصفوف الفارغة
                if (empty(array_filter($row))) {
                    continue;
                }

                // قراءة البيانات بناءً على أسماء الأعمدة
                // 🔥 FIX: استخدام null بدلاً من 0 عند عدم وجود العمود لتجنب قراءة قيم خاطئة
                $personTypeIndex = $columnMap[$requiredColumns['person_type']] ?? null;
                $sponsoredIdentityIndex = $columnMap[$requiredColumns['sponsored_identity']] ?? null;
                $sponsoredNameIndex = $columnMap[$requiredColumns['sponsored_name']] ?? null;
                $guardianIdentityIndex = $columnMap[$requiredColumns['guardian_identity_number']] ?? null;
                $guardianNameIndex = $columnMap[$requiredColumns['guardian_name']] ?? null;
                $bankNameIndex = $columnMap[$requiredColumns['bank_name']] ?? null;
                $phoneNumberIndex = $columnMap[$requiredColumns['data_phone_number']] ?? null;
                $altPhoneNumberIndex = $columnMap[$requiredColumns['data_alt_phone_number']] ?? null;

                $personType = ($personTypeIndex !== null) ? trim($row[$personTypeIndex] ?? '') : '';
                $sponsoredIdentity = ($sponsoredIdentityIndex !== null) ? trim($row[$sponsoredIdentityIndex] ?? '') : '';
                $sponsoredName = ($sponsoredNameIndex !== null) ? trim($row[$sponsoredNameIndex] ?? '') : '';
                $guardianIdentity = ($guardianIdentityIndex !== null) ? trim($row[$guardianIdentityIndex] ?? '') : '';
                $guardianName = ($guardianNameIndex !== null) ? trim($row[$guardianNameIndex] ?? '') : '';
                $bankName = ($bankNameIndex !== null) ? trim($row[$bankNameIndex] ?? '') : '';
                $phoneNumber = ($phoneNumberIndex !== null) ? trim($row[$phoneNumberIndex] ?? '') : '';
                $altPhoneNumber = ($altPhoneNumberIndex !== null) ? trim($row[$altPhoneNumberIndex] ?? '') : '';

                // Log first 3 rows
                if ($index < 3) {
                    Log::info("📊 Reading Row $rowNumber:", [
                        'person_type' => $personType,
                        'sponsored_identity' => $sponsoredIdentity,
                        'sponsored_name' => $sponsoredName,
                        'guardian_identity' => $guardianIdentity,
                        'guardian_name' => $guardianName,
                        'bank_name' => $bankName,
                    ]);
                }

                // تجميع الأشخاص حسب نوعهم للتحقق
                if (!empty($personType) && !empty($sponsoredIdentity)) {
                    $normalizedPersonType = $this->normalizeArabicText($personType);

                    $personData = [
                        'row' => $rowNumber,
                        'type' => $normalizedPersonType,
                        'identity' => $sponsoredIdentity,
                        'name' => $sponsoredName,
                        'guardian_identity' => $guardianIdentity,
                        'guardian_name' => $guardianName,
                        'phone' => $phoneNumber,
                        'alt_phone' => $altPhoneNumber,
                        'full_row' => $row
                    ];

                    $uniquePersons[] = $personData;
                }

                // جمع المعيلين أيضاً للتحقق (فقط إذا لم يكن نوع الشخص "معيل")
                // لأنه إذا كان نوع الشخص "معيل"، فهو نفسه المكفول وتم إضافته في الخطوة السابقة
                $normalizedPersonType = $this->normalizeArabicText($personType);
                $isGuardianType = in_array($normalizedPersonType, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة']);

                if (!empty($guardianIdentity) && !$isGuardianType) {
                    // 🆕 إذا كان اسم المعيل فارغاً ولكن الهوية موجودة، نحاول جلب الاسم من السجل المدني
                    $guardianNameToUse = $guardianName;
                    $guardianNameSource = 'excel';

                    if (empty($guardianName) && !empty($guardianIdentity)) {
                        // البحث في السجل المدني عن اسم المعيل
                        $civilRegistryData = $this->searchCivilRegistryForGuardian($guardianIdentity);
                        if ($civilRegistryData && !empty($civilRegistryData['full_name'])) {
                            $guardianNameToUse = $civilRegistryData['full_name'];
                            $guardianNameSource = 'civil_registry';
                            Log::info('🔍 تم جلب اسم المعيل من السجل المدني (الاسم كان فارغاً في Excel)', [
                                'row' => $rowNumber,
                                'guardian_identity' => $guardianIdentity,
                                'civil_name' => $guardianNameToUse,
                                'sponsored_identity' => $sponsoredIdentity,
                                'sponsored_name' => $sponsoredName
                            ]);
                        }
                    }

                    // تجنب التكرار - التحقق أولاً إذا كان المعيل موجود مسبقاً
                    $guardianExists = false;
                    $guardianIndex = -1;

                    foreach ($uniquePersons as $idx => $person) {
                        if ($person['identity'] === $guardianIdentity && $person['type'] === 'معيل') {
                            $guardianExists = true;
                            $guardianIndex = $idx;
                            break;
                        }
                    }

                    if (!$guardianExists) {
                        // إضافة المعيل لأول مرة مع جميع بياناته
                        $guardianData = [
                            'row' => $rowNumber,
                            'type' => 'معيل',
                            'identity' => $guardianIdentity,
                            'name' => $guardianNameToUse,
                            'name_source' => $guardianNameSource, // 🆕 مصدر الاسم
                            'phone' => $phoneNumber,
                            'alt_phone' => $altPhoneNumber,
                            'full_row' => $row
                        ];
                        $uniquePersons[] = $guardianData;
                    } else {
                        // المعيل موجود مسبقاً، نحدّث الاسم إذا كان فارغاً والآن لدينا اسم من السجل المدني
                        if (empty($uniquePersons[$guardianIndex]['name']) && !empty($guardianNameToUse)) {
                            $uniquePersons[$guardianIndex]['name'] = $guardianNameToUse;
                            $uniquePersons[$guardianIndex]['name_source'] = $guardianNameSource;
                        }
                        // نحدّث أرقام الهاتف فقط إذا كانت الحالية أفضل (غير فارغة)
                        if (!empty($phoneNumber) && empty($uniquePersons[$guardianIndex]['phone'])) {
                            $uniquePersons[$guardianIndex]['phone'] = $phoneNumber;
                        }
                        if (!empty($altPhoneNumber) && empty($uniquePersons[$guardianIndex]['alt_phone'])) {
                            $uniquePersons[$guardianIndex]['alt_phone'] = $altPhoneNumber;
                        }
                    }
                }

                // 🆕 فحص بيانات البنك المفقودة
                // قراءة بيانات البنك
                $personOwnerIdentityIndex = $columnMap[$requiredColumns['person_owner_identity_number']]
                    ?? $columnMap[$requiredColumns['person_owner_identity_number_alt']]
                    ?? -1;
                $personOwnerIdentityNumber = $personOwnerIdentityIndex >= 0 ? trim($row[$personOwnerIdentityIndex] ?? '') : '';
                $reGuardianName = trim($row[$columnMap[$requiredColumns['re_guardian_name']] ?? -1] ?? '');
                $rePhoneNumber = trim($row[$columnMap[$requiredColumns['re_phone_number']] ?? -1] ?? '');

                // التحقق من اكتمال بيانات البنك إذا كان هناك أي بيانات بنك
                $hasBankName = !empty($bankName);
                $hasBankOwnerIdentity = !empty($personOwnerIdentityNumber);
                $hasBankOwnerName = !empty($reGuardianName);
                $hasBankPhone = !empty($rePhoneNumber);

                // إذا كان هناك أي بيانات بنك، نتحقق من اكتمالها
                if ($hasBankName || $hasBankOwnerIdentity || $hasBankOwnerName || $hasBankPhone) {
                    $missingBankFields = [];
                    if (!$hasBankName) $missingBankFields[] = 'اسم المحفظة/البنك';
                    if (!$hasBankOwnerIdentity) $missingBankFields[] = 'هوية صاحب المحفظة';
                    if (!$hasBankOwnerName) $missingBankFields[] = 'اسم صاحب المحفظة';
                    if (!$hasBankPhone) $missingBankFields[] = 'جوال المحفظة';

                    if (!empty($missingBankFields)) {
                        $incompleteBankData[] = [
                            'row' => $rowNumber,
                            'identity' => $sponsoredIdentity,
                            'name' => $sponsoredName,
                            'missing_fields' => $missingBankFields,
                            'has_fields' => [
                                'bank_name' => $hasBankName ? $bankName : null,
                                'owner_identity' => $hasBankOwnerIdentity ? $personOwnerIdentityNumber : null,
                                'owner_name' => $hasBankOwnerName ? $reGuardianName : null,
                                'phone' => $hasBankPhone ? $rePhoneNumber : null,
                            ]
                        ];
                    }
                }

                // جمع أسماء البنوك الفريدة
                if (!empty($bankName) && !in_array($bankName, $uniqueBanks)) {
                    $uniqueBanks[] = $bankName;
                }

                // 🆕 فحص الحسابات البنكية النشطة القديمة التي ستتأثر
                // إذا كان هناك بيانات بنك جديدة وكاملة، نتحقق من وجود حسابات نشطة قديمة مختلفة
                if ($hasBankName && $hasBankOwnerIdentity && $hasBankPhone) {
                    // نحتاج البحث عن relation_id_number للشخص
                    $fileNumberForSearch = null;
                    $actualGuardianIdentityForCheck = null;

                    // البحث حسب نوع الشخص
                    $normalizedType = $this->normalizeArabicText($personType);
                    $isPersonAsGuardianType = in_array($normalizedType, [
                        'معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة',
                        'أب متوفي', 'اب متوفي', 'الاب المتوفي', 'الأب المتوفي',
                        'أم متوفيه', 'ام متوفيه', 'أم متوفية', 'ام متوفية', 'الأم المتوفية', 'الام المتوفية'
                    ]);

                    if (in_array($normalizedType, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة'])) {
                        // المعيل: البحث في data
                        $dataRecord = Data::where('data_id_number', $sponsoredIdentity)->first();
                        if ($dataRecord) {
                            $fileNumberForSearch = $dataRecord->file_id_number;
                            $actualGuardianIdentityForCheck = $sponsoredIdentity;
                        }
                    } elseif (in_array($normalizedType, ['أب متوفي', 'اب متوفي', 'الاب المتوفي', 'الأب المتوفي'])) {
                        // الأب المتوفي: البحث في dead_people
                        $deadRecord = DeadPepole::where('father_id', $sponsoredIdentity)->first();
                        if ($deadRecord) {
                            $fileNumberForSearch = $deadRecord->re_file_id;
                            $actualGuardianIdentityForCheck = $sponsoredIdentity;
                        }
                    } elseif (in_array($normalizedType, ['أم متوفيه', 'ام متوفيه', 'أم متوفية', 'ام متوفية', 'الأم المتوفية', 'الام المتوفية'])) {
                        // الأم المتوفية: البحث في dead_people
                        $deadRecord = DeadPepole::where('mother_id', $sponsoredIdentity)->first();
                        if ($deadRecord) {
                            $fileNumberForSearch = $deadRecord->re_file_id;
                            $actualGuardianIdentityForCheck = $sponsoredIdentity;
                        }
                    } else {
                        // فرد عائلة أو آخر: البحث بهوية المعيل
                        if (!empty($guardianIdentity)) {
                            $guardianRecord = Data::where('data_id_number', $guardianIdentity)->first();
                            if ($guardianRecord) {
                                $fileNumberForSearch = $guardianRecord->file_id_number;
                                $actualGuardianIdentityForCheck = $guardianIdentity;
                            }
                        }
                    }

                    // إذا وجدنا رقم ملف، نبحث عن حسابات بنكية
                    if ($fileNumberForSearch) {
                        // 🔍 البحث عن البنك الجديد في النظام
                        $newBankRecord = BankName::where(function($query) use ($bankName) {
                            $this->addSmartSearch($query, 'description', $bankName, false);
                        })->first();
                        $newBankId = $newBankRecord ? $newBankRecord->id : null;

                        // 🔍 تحديد هوية صاحب المحفظة للحساب الجديد
                        $newAccountOwnerIdentity = $isPersonAsGuardianType ? $sponsoredIdentity : $guardianIdentity;
                        $cleanedOwnerIdentity = preg_replace('/\D/', '', $personOwnerIdentityNumber);
                        if (!empty($personOwnerIdentityNumber) && strlen($cleanedOwnerIdentity) >= 9) {
                            $newAccountOwnerIdentity = $personOwnerIdentityNumber;
                        }

                        // ✅ التحقق من وجود الحساب الجديد بالـ 4 أعمدة الأساسية
                        // bank_name, re_id_number, re_phone_number, person_owner_identity_number
                        $existingExactAccount = GuardianBankAccount::where('bank_name', $newBankId)
                            ->where('re_id_number', $actualGuardianIdentityForCheck)
                            ->where('re_phone_number', $rePhoneNumber)
                            ->where('person_owner_identity_number', $newAccountOwnerIdentity)
                            ->first();

                        if ($existingExactAccount) {
                            // ✅ الحساب موجود مسبقاً بنفس الـ 4 أعمدة
                            // إذا كان check_account = 0 سيتم تحويله إلى 1
                            if ($existingExactAccount->check_account == 0) {
                                // تجنب التكرار
                                $alreadyAdded = false;
                                foreach ($existingActiveAccounts as $existing) {
                                    if ($existing['account_id'] === $existingExactAccount->id) {
                                        $alreadyAdded = true;
                                        break;
                                    }
                                }

                                if (!$alreadyAdded) {
                                    $oldBankName = '-';
                                    if ($existingExactAccount->bank_name) {
                                        $oldBankRecord = BankName::find($existingExactAccount->bank_name);
                                        if ($oldBankRecord) {
                                            $oldBankName = $oldBankRecord->description;
                                        }
                                    }

                                    $existingActiveAccounts[] = [
                                        'row' => $rowNumber,
                                        'account_id' => $existingExactAccount->id,
                                        'guardian_registration' => $existingExactAccount->guardian_registration,
                                        'identity' => $sponsoredIdentity,
                                        'name' => $sponsoredName,
                                        'old_bank_name' => $oldBankName,
                                        'old_bank_id' => $existingExactAccount->bank_name,
                                        'old_phone' => $existingExactAccount->re_phone_number,
                                        'old_owner_identity' => $existingExactAccount->person_owner_identity_number,
                                        'old_check_account' => $existingExactAccount->check_account,
                                        'new_bank_name' => $bankName,
                                        'new_bank_id' => $newBankId,
                                        'new_phone' => $rePhoneNumber,
                                        'new_owner_identity' => $newAccountOwnerIdentity,
                                        'action' => 'reactivate', // سيتم إعادة تفعيله من 0 إلى 1
                                        'will_be_deactivated' => false,
                                        'will_be_reactivated' => true,
                                        'difference' => []
                                    ];

                                    Log::info('🔄 تم اكتشاف حساب بنكي غير نشط سيتم إعادة تفعيله', [
                                        'row' => $rowNumber,
                                        'account_id' => $existingExactAccount->id,
                                        'guardian_registration' => $existingExactAccount->guardian_registration,
                                        'bank' => $oldBankName
                                    ]);
                                }
                            }
                            // إذا كان check_account = 1 فلا حاجة لفعل شيء (الحساب نشط بالفعل)
                        }

                        // 🔍 البحث عن حسابات نشطة أخرى بنفس guardian_registration لتعطيلها
                        // (لأن الأولوية للحساب الأخير المُدخل)
                        $otherActiveAccounts = GuardianBankAccount::where('guardian_registration', $fileNumberForSearch)
                            ->where('check_account', 1)
                            ->where(function($query) use ($newBankId, $actualGuardianIdentityForCheck, $rePhoneNumber, $newAccountOwnerIdentity) {
                                // استثناء الحساب المطابق تماماً (نفس الـ 4 أعمدة)
                                $query->where('bank_name', '!=', $newBankId)
                                    ->orWhere('re_id_number', '!=', $actualGuardianIdentityForCheck)
                                    ->orWhere('re_phone_number', '!=', $rePhoneNumber)
                                    ->orWhere('person_owner_identity_number', '!=', $newAccountOwnerIdentity);
                            })
                            ->get();

                        foreach ($otherActiveAccounts as $activeAccount) {
                            // تجنب التكرار
                            $alreadyAdded = false;
                            foreach ($existingActiveAccounts as $existing) {
                                if ($existing['account_id'] === $activeAccount->id) {
                                    $alreadyAdded = true;
                                    break;
                                }
                            }

                            if (!$alreadyAdded) {
                                $oldBankName = '-';
                                if ($activeAccount->bank_name) {
                                    $oldBankRecord = BankName::find($activeAccount->bank_name);
                                    if ($oldBankRecord) {
                                        $oldBankName = $oldBankRecord->description;
                                    }
                                }

                                $existingActiveAccounts[] = [
                                    'row' => $rowNumber,
                                    'account_id' => $activeAccount->id,
                                    'guardian_registration' => $fileNumberForSearch,
                                    'identity' => $sponsoredIdentity,
                                    'name' => $sponsoredName,
                                    'old_bank_name' => $oldBankName,
                                    'old_bank_id' => $activeAccount->bank_name,
                                    'old_phone' => $activeAccount->re_phone_number,
                                    'old_owner_identity' => $activeAccount->person_owner_identity_number,
                                    'old_check_account' => $activeAccount->check_account,
                                    'new_bank_name' => $bankName,
                                    'new_bank_id' => $newBankId,
                                    'new_phone' => $rePhoneNumber,
                                    'new_owner_identity' => $newAccountOwnerIdentity,
                                    'action' => 'deactivate', // سيتم تعطيله من 1 إلى 0
                                    'will_be_deactivated' => true,
                                    'will_be_reactivated' => false,
                                    'difference' => [
                                        'bank_changed' => ($activeAccount->bank_name != $newBankId),
                                        'phone_changed' => ($activeAccount->re_phone_number != $rePhoneNumber),
                                        'owner_changed' => ($activeAccount->person_owner_identity_number != $newAccountOwnerIdentity),
                                        're_id_changed' => ($activeAccount->re_id_number != $actualGuardianIdentityForCheck)
                                    ]
                                ];

                                Log::info('🔄 تم اكتشاف حساب بنكي نشط سيتم تعطيله', [
                                    'row' => $rowNumber,
                                    'account_id' => $activeAccount->id,
                                    'guardian_registration' => $fileNumberForSearch,
                                    'old_bank' => $oldBankName,
                                    'new_bank' => $bankName,
                                    'old_phone' => $activeAccount->re_phone_number,
                                    'new_phone' => $rePhoneNumber
                                ]);
                            }
                        }
                    }
                }
            }

            // 🆕 تسجيل الحسابات البنكية التي ستتأثر
            if (!empty($existingActiveAccounts)) {
                Log::info('🔄 حسابات بنكية نشطة ستتحول إلى غير نشطة عند الاستيراد:', [
                    'count' => count($existingActiveAccounts),
                    'accounts' => array_slice($existingActiveAccounts, 0, 5) // أول 5 فقط
                ]);
            }

            // 🆕 كشف التكرارات في ملف Excel (نفس الشخص مكرر أكثر من مرة)
            $duplicatesInExcel = [];
            $identityOccurrences = [];

            foreach ($uniquePersons as $personData) {
                $identity = $personData['identity'];
                if (empty($identity)) continue;

                if (!isset($identityOccurrences[$identity])) {
                    $identityOccurrences[$identity] = [];
                }
                $identityOccurrences[$identity][] = [
                    'row' => $personData['row'],
                    'type' => $personData['type'],
                    'name' => $personData['name'] ?? '',
                ];
            }

            // تصفية التكرارات (أكثر من ظهور واحد)
            foreach ($identityOccurrences as $identity => $occurrences) {
                if (count($occurrences) > 1) {
                    $duplicatesInExcel[] = [
                        'identity' => $identity,
                        'count' => count($occurrences),
                        'rows' => array_column($occurrences, 'row'),
                        'types' => array_unique(array_column($occurrences, 'type')),
                        'names' => array_unique(array_column($occurrences, 'name')),
                    ];
                }
            }

            if (!empty($duplicatesInExcel)) {
                Log::warning('⚠️ تم اكتشاف تكرارات في ملف Excel:', [
                    'count' => count($duplicatesInExcel),
                    'duplicates' => $duplicatesInExcel
                ]);
            }

            // 🆕 كشف التكرارات في قاعدة البيانات (نفس الشخص مسجل مسبقاً مع نفس الجمعية)
            $duplicatesInDatabase = [];
            $sponsorId = $request->sponsor_id;

            if ($sponsorId) {
                foreach ($uniquePersons as $personData) {
                    $identity = $personData['identity'];
                    if (empty($identity)) continue;

                    // البحث عن كفالة موجودة مسبقاً لنفس الشخص مع نفس الجمعية
                    $existingSponsorship = Sponsorship::where('identity_number', $identity)
                        ->where('sponsor_id', $sponsorId)
                        ->first();

                    if ($existingSponsorship) {
                        $duplicatesInDatabase[] = [
                            'row' => $personData['row'],
                            'identity' => $identity,
                            'name' => $personData['name'] ?? '',
                            'type' => $personData['type'],
                            'existing_sponsorship_id' => $existingSponsorship->id,
                            'existing_file_number' => $existingSponsorship->internal_file_number ?? '-',
                            'existing_date' => $existingSponsorship->created_at ? $existingSponsorship->created_at->format('Y-m-d') : '-',
                        ];
                    }
                }

                if (!empty($duplicatesInDatabase)) {
                    Log::warning('⚠️ تم اكتشاف سجلات مكررة في قاعدة البيانات:', [
                        'count' => count($duplicatesInDatabase),
                        'sponsor_id' => $sponsorId,
                        'duplicates' => $duplicatesInDatabase
                    ]);
                }
            }

            // التحقق من وجود جميع الأشخاص في النظام حسب نوعهم
            $missingPersons = [];
            $updatedPhones = []; // قائمة الأشخاص الذين تم تحديث أرقامهم

            // 🆕 قائمة المعيلين المتاحين للإنشاء من السجل المدني
            $guardiansToCreate = [];
            // 🆕 قائمة المكفولين (أفراد العائلة) المتاحين للإنشاء من السجل المدني
            $sponsoredToCreate = [];
            // 🆕 قائمة المتوفين (أب/أم) المتاحين للإنشاء من السجل المدني
            $deceasedToCreate = [];
            // 🆕 قائمة الأشخاص الموجودين مسبقاً في قاعدة البيانات
            $existingPersons = [];
            // 🆕 قائمة أفراد العائلة بدون معيل (guardian_identity فارغ أو معيلهم غير موجود)
            $familyMembersWithoutGuardian = [];

            foreach ($uniquePersons as $personData) {
                $identity = $personData['identity'];
                $type = $personData['type'];
                $found = false;
                $targetTable = '';
                $civilRegistryData = null; // 🆕 بيانات السجل المدني
                $existingRecord = null; // 🆕 السجل الموجود

                // تصنيف حسب نوع الشخص
                if (in_array($type, ['فرد عايله', 'فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة', 'فرد أسرة', 'فرد الع ائله'])) {
                    // البحث في re_people
                    $existingRecord = RePeople::where('person_id', $identity)->first();
                    $targetTable = 're_people';
                    $found = $existingRecord !== null;

                    // 🆕 إذا وُجد الشخص، أضفه لقائمة الموجودين مسبقاً
                    if ($found && $existingRecord) {
                        $existingPersons[] = [
                            'row' => $personData['row'],
                            'type' => $type,
                            'table' => 're_people',
                            'identity' => $identity,
                            'name' => $personData['name'] ?? '',
                            'existing_name' => trim(($existingRecord->first_name ?? '') . ' ' . ($existingRecord->second_name ?? '') . ' ' . ($existingRecord->third_name ?? '') . ' ' . ($existingRecord->last_name ?? '')),
                            'registration_id' => $existingRecord->registration_id ?? '',
                            'guardian_identity' => $personData['guardian_identity'] ?? '',
                        ];
                    }

                    // التحقق من وجود رقم هوية المعيل
                    $guardianIdentity = $personData['guardian_identity'] ?? '';

                    // 🆕 إذا لم يُعثر عليه في re_people وله معيل، نبحث في السجل المدني
                    // ملاحظة: تم حذف الكتلة المكررة التي كانت تضيف أفراد العائلة بدون معيل هنا
                    // لأن هناك فحص شامل في نهاية الدالة يقوم بنفس المهمة بدقة أكبر
                    if (!$found && !empty($identity)) {
                        $civilRegistryData = $this->searchCivilRegistryForGuardian($identity);
                        if ($civilRegistryData) {
                            // ✅ المكفول وُجد في السجل المدني - يمكن إنشاؤه في re_people
                            $sponsoredToCreate[$identity] = [
                                'row' => $personData['row'],
                                'identity' => $identity,
                                'name' => $personData['name'] ?? '',
                                'guardian_identity' => $personData['guardian_identity'] ?? '',
                                'guardian_name' => $personData['guardian_name'] ?? '',
                                'phone' => $personData['phone'] ?? '',
                                'alt_phone' => $personData['alt_phone'] ?? '',
                                'civil_registry' => $civilRegistryData,
                                'source' => 'civil_registry',
                                'type' => 'family_member'
                            ];
                            Log::info('✅ تم العثور على المكفول (فرد عائلة) في السجل المدني', [
                                'identity' => $identity,
                                'civil_name' => $civilRegistryData['full_name'],
                                'birth_date' => $civilRegistryData['birth_date'] ?? null, // 🆕 تاريخ الميلاد من السجل المدني
                                'guardian_identity' => $personData['guardian_identity'] ?? ''
                            ]);
                        } else {
                            // 🆕 المكفول غير موجود في السجل المدني - نستخدم NameSegmentation لتقسيم الاسم من Excel
                            $excelName = $personData['name'] ?? '';
                            if (!empty($excelName)) {
                                $segmentedName = NameSegmentation::segment($excelName);
                                if ($segmentedName['segments_count'] > 0) {
                                    // ✅ سيتم إنشاء سجل في re_people مع الاسم المقسم وربطه بالمعيل
                                    $sponsoredToCreate[$identity] = [
                                        'row' => $personData['row'],
                                        'identity' => $identity,
                                        'name' => $excelName,
                                        'guardian_identity' => $personData['guardian_identity'] ?? '',
                                        'guardian_name' => $personData['guardian_name'] ?? '',
                                        'phone' => $personData['phone'] ?? '',
                                        'alt_phone' => $personData['alt_phone'] ?? '',
                                        'segmented_name' => $segmentedName,
                                        'source' => 'excel_segmentation',
                                        'type' => 'family_member',
                                        'skip_re_people' => false // ✅ سيتم إنشاء سجل في re_people
                                    ];
                                    Log::info('📛 تم تقسيم اسم المكفول من Excel (غير موجود في السجل المدني)', [
                                        'identity' => $identity,
                                        'original_name' => $excelName,
                                        'segmented' => $segmentedName,
                                        'note' => 'سيتم إنشاء سجل في re_people مع الاسم المقسم'
                                    ]);
                                }
                            }
                        }
                    }

                } elseif (in_array($type, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة'])) {
                    // 🆕 البحث في data أولاً
                    $existingRecord = Data::where('data_id_number', $identity)->first();
                    $targetTable = 'data';
                    $found = $existingRecord !== null;

                    // 🆕 إذا وُجد المعيل، أضفه لقائمة الموجودين مسبقاً
                    if ($found && $existingRecord) {
                        $existingPersons[] = [
                            'row' => $personData['row'],
                            'type' => $type,
                            'table' => 'data',
                            'identity' => $identity,
                            'name' => $personData['name'] ?? '',
                            'existing_name' => trim(($existingRecord->data_first_name ?? '') . ' ' . ($existingRecord->data_father_name ?? '') . ' ' . ($existingRecord->data_grand_father_name ?? '') . ' ' . ($existingRecord->data_family_name ?? '')),
                            'file_id_number' => $existingRecord->file_id_number ?? '',
                            'phone' => $existingRecord->data_phone_number ?? '',
                        ];
                    }

                    // 🆕 إذا لم يُعثر عليه في data، نبحث في dead_people
                    if (!$found) {
                        $deadRecord = DeadPepole::where('father_id', $identity)
                            ->orWhere('mother_id', $identity)
                            ->first();
                        if ($deadRecord) {
                            $found = true;
                            $targetTable = 'dead_people';
                            // إضافة للموجودين
                            $existingPersons[] = [
                                'row' => $personData['row'],
                                'type' => $type,
                                'table' => 'dead_people',
                                'identity' => $identity,
                                'name' => $personData['name'] ?? '',
                                'existing_name' => trim(($deadRecord->father_first_name ?? $deadRecord->mother_first_name ?? '') . ' ' . ($deadRecord->father_second_name ?? $deadRecord->mother_second_name ?? '') . ' ' . ($deadRecord->father_family_name ?? $deadRecord->mother_family_name ?? '')),
                                'registration_id' => $deadRecord->registration_id ?? '',
                            ];
                        }
                    }

                    // 🆕 إذا لم يُعثر عليه في أي من الجدولين، نبحث في السجل المدني
                    if (!$found && !empty($identity)) {
                        $civilRegistryData = $this->searchCivilRegistryForGuardian($identity);
                        if ($civilRegistryData) {
                            // ✅ وُجد في السجل المدني - يمكن إنشاؤه تلقائياً
                            // 🆕 استخدام اسم السجل المدني إذا كان اسم Excel فارغاً
                            $nameToUse = !empty($personData['name']) ? $personData['name'] : $civilRegistryData['full_name'];
                            $guardiansToCreate[$identity] = [
                                'row' => $personData['row'],
                                'identity' => $identity,
                                'name' => $nameToUse,
                                'phone' => $personData['phone'] ?? '',
                                'alt_phone' => $personData['alt_phone'] ?? '',
                                'civil_registry' => $civilRegistryData,
                                'source' => 'civil_registry'
                            ];
                            Log::info('✅ تم العثور على المعيل في السجل المدني', [
                                'identity' => $identity,
                                'civil_name' => $civilRegistryData['full_name'],
                                'excel_name' => $personData['name'] ?? '',
                                'name_used' => $nameToUse
                            ]);
                        } else {
                            // 🆕 المعيل غير موجود في السجل المدني - نستخدم NameSegmentation لتقسيم الاسم من Excel
                            $excelName = $personData['name'] ?? '';
                            if (!empty($excelName)) {
                                $segmentedName = NameSegmentation::segment($excelName);
                                if ($segmentedName['segments_count'] > 0) {
                                    // ✅ يمكن إنشاؤه باستخدام الاسم المقسم من Excel
                                    $guardiansToCreate[$identity] = [
                                        'row' => $personData['row'],
                                        'identity' => $identity,
                                        'name' => $excelName,
                                        'phone' => $personData['phone'] ?? '',
                                        'alt_phone' => $personData['alt_phone'] ?? '',
                                        'segmented_name' => $segmentedName,
                                        'source' => 'excel_segmentation'
                                    ];
                                    Log::info('📛 تم تقسيم اسم المعيل من Excel (غير موجود في السجل المدني)', [
                                        'identity' => $identity,
                                        'original_name' => $excelName,
                                        'segmented' => $segmentedName
                                    ]);
                                }
                            } else {
                                // 🆕 الاسم فارغ وغير موجود في السجل المدني - لا نستطيع فتح سجل له
                                // سيتم فقط إدخال البيانات في جدول sponsorships
                                Log::warning('⚠️ المعيل غير موجود في السجل المدني واسمه فارغ في Excel', [
                                    'identity' => $identity,
                                    'row' => $personData['row'],
                                    'note' => 'سيتم إدخال البيانات المتوفرة فقط في جدول sponsorships بدون فتح سجل داخلي'
                                ]);
                            }
                        }
                    }

                } elseif (in_array($type, ['أب متوفي', 'اب متوفي', 'الاب المتوفي', 'الأب المتوفي'])) {
                    // البحث في dead_people عمود father_id
                    $exists = DeadPepole::where('father_id', $identity)->exists();
                    $targetTable = 'dead_people (father)';
                    $found = $exists;

                    // 🆕 إذا لم يُعثر عليه، نبحث في السجل المدني لإنشائه
                    if (!$found && !empty($identity)) {
                        $civilRegistryData = $this->searchCivilRegistryForGuardian($identity);
                        if ($civilRegistryData) {
                            // ✅ تم العثور عليه في السجل المدني - سيتم إنشاؤه في dead_people
                            $deceasedToCreate[$identity] = [
                                'row' => $personData['row'],
                                'identity' => $identity,
                                'name' => $personData['name'] ?? '',
                                'phone' => $personData['phone'] ?? '',
                                'alt_phone' => $personData['alt_phone'] ?? '',
                                'civil_registry' => $civilRegistryData,
                                'source' => 'civil_registry',
                                'type' => 'deceased_father'
                            ];
                            Log::info('✅ تم العثور على الأب المتوفي في السجل المدني', [
                                'identity' => $identity,
                                'civil_name' => $civilRegistryData['full_name'],
                                'birth_date' => $civilRegistryData['birth_date'] ?? null
                            ]);
                        } else {
                            // 🆕 غير موجود في السجل المدني - نستخدم NameSegmentation
                            $excelName = $personData['name'] ?? '';
                            $segmentedName = !empty($excelName) ? NameSegmentation::segment($excelName) : null;

                            // ✅ دائماً إضافة المتوفي حتى لو فشل التقسيم (سيتم استخدام الاسم الكامل)
                            $deceasedToCreate[$identity] = [
                                'row' => $personData['row'],
                                'identity' => $identity,
                                'name' => $excelName,
                                'phone' => $personData['phone'] ?? '',
                                'alt_phone' => $personData['alt_phone'] ?? '',
                                'segmented_name' => $segmentedName,
                                'source' => 'excel_segmentation',
                                'type' => 'deceased_father'
                            ];
                            Log::info('📛 تم إضافة الأب المتوفي للإنشاء (من Excel)', [
                                'identity' => $identity,
                                'original_name' => $excelName,
                                'segmented' => $segmentedName,
                                'has_valid_segments' => ($segmentedName && $segmentedName['segments_count'] > 0)
                            ]);
                        }
                    }

                } elseif (in_array($type, ['أم متوفيه', 'ام متوفيه', 'الام المتوفيه', 'أم متوفية', 'ام متوفية', 'الأم المتوفية', 'الام المتوفية'])) {
                    // البحث في dead_people عمود mother_id
                    $exists = DeadPepole::where('mother_id', $identity)->exists();
                    $targetTable = 'dead_people (mother)';
                    $found = $exists;

                    // 🆕 إذا لم يُعثر عليها، نبحث في السجل المدني لإنشائها
                    if (!$found && !empty($identity)) {
                        $civilRegistryData = $this->searchCivilRegistryForGuardian($identity);
                        if ($civilRegistryData) {
                            // ✅ تم العثور عليها في السجل المدني - سيتم إنشاؤها في dead_people
                            $deceasedToCreate[$identity] = [
                                'row' => $personData['row'],
                                'identity' => $identity,
                                'name' => $personData['name'] ?? '',
                                'phone' => $personData['phone'] ?? '',
                                'alt_phone' => $personData['alt_phone'] ?? '',
                                'civil_registry' => $civilRegistryData,
                                'source' => 'civil_registry',
                                'type' => 'deceased_mother'
                            ];
                            Log::info('✅ تم العثور على الأم المتوفية في السجل المدني', [
                                'identity' => $identity,
                                'civil_name' => $civilRegistryData['full_name'],
                                'birth_date' => $civilRegistryData['birth_date'] ?? null
                            ]);
                        } else {
                            // 🆕 غير موجودة في السجل المدني - نستخدم NameSegmentation
                            $excelName = $personData['name'] ?? '';
                            $segmentedName = !empty($excelName) ? NameSegmentation::segment($excelName) : null;

                            // ✅ دائماً إضافة المتوفية حتى لو فشل التقسيم (سيتم استخدام الاسم الكامل)
                            $deceasedToCreate[$identity] = [
                                'row' => $personData['row'],
                                'identity' => $identity,
                                'name' => $excelName,
                                'phone' => $personData['phone'] ?? '',
                                'alt_phone' => $personData['alt_phone'] ?? '',
                                'segmented_name' => $segmentedName,
                                'source' => 'excel_segmentation',
                                'type' => 'deceased_mother'
                            ];
                            Log::info('📛 تم إضافة الأم المتوفية للإنشاء (من Excel)', [
                                'identity' => $identity,
                                'original_name' => $excelName,
                                'segmented' => $segmentedName,
                                'has_valid_segments' => ($segmentedName && $segmentedName['segments_count'] > 0)
                            ]);
                        }
                    }
                }

                // 🆕 للأشخاص من نوع "فرد عائلة" - نتحقق من وجود المعيل
                if (in_array($type, ['فرد عايله', 'فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة', 'فرد أسرة', 'فرد الع ائله'])) {
                    $guardianIdentity = $personData['guardian_identity'] ?? '';
                    if (!empty($guardianIdentity) && !isset($guardiansToCreate[$guardianIdentity])) {
                        // التحقق من وجود المعيل في data أو dead_people
                        $guardianExists = Data::where('data_id_number', $guardianIdentity)->exists()
                            || DeadPepole::where('father_id', $guardianIdentity)->exists()
                            || DeadPepole::where('mother_id', $guardianIdentity)->exists();

                        if (!$guardianExists) {
                            // البحث عن المعيل في السجل المدني
                            $guardianCivilData = $this->searchCivilRegistryForGuardian($guardianIdentity);
                            if ($guardianCivilData) {
                                // 🆕 استخدام اسم السجل المدني إذا كان اسم Excel فارغاً
                                $guardianNameFromExcel = $personData['guardian_name'] ?? '';
                                $guardianNameToUse = !empty($guardianNameFromExcel) ? $guardianNameFromExcel : $guardianCivilData['full_name'];

                                $guardiansToCreate[$guardianIdentity] = [
                                    'row' => $personData['row'],
                                    'identity' => $guardianIdentity,
                                    'name' => $guardianNameToUse,
                                    'phone' => $personData['phone'] ?? '',
                                    'alt_phone' => $personData['alt_phone'] ?? '',
                                    'civil_registry' => $guardianCivilData,
                                    'source' => 'civil_registry',
                                    'for_family_member' => $identity // رقم هوية فرد العائلة
                                ];
                                Log::info('✅ تم العثور على معيل فرد العائلة في السجل المدني', [
                                    'guardian_identity' => $guardianIdentity,
                                    'family_member_identity' => $identity,
                                    'civil_name' => $guardianCivilData['full_name'],
                                    'excel_guardian_name' => $guardianNameFromExcel,
                                    'name_used' => $guardianNameToUse
                                ]);
                            } else {
                                // 🆕 المعيل غير موجود في السجل المدني
                                // إذا كان اسم المعيل فارغاً في Excel، نسجل ذلك
                                $guardianNameFromExcel = $personData['guardian_name'] ?? '';
                                if (empty($guardianNameFromExcel)) {
                                    Log::warning('⚠️ معيل فرد العائلة غير موجود في السجل المدني واسمه فارغ في Excel', [
                                        'guardian_identity' => $guardianIdentity,
                                        'family_member_identity' => $identity,
                                        'family_member_name' => $personData['name'] ?? '',
                                        'row' => $personData['row'],
                                        'note' => 'سيتم إدخال بيانات فرد العائلة في sponsorships فقط بدون فتح سجل للمعيل'
                                    ]);
                                }
                            }
                        }
                    }
                }

                // إذا لم يُعثر على الشخص وليس في السجل المدني/قائمة الإنشاء، أضفه لقائمة المفقودين
                // 🆕 تحسين: استبعاد المتوفين الذين سيتم إنشاؤهم تلقائياً
                $willBeCreated = isset($guardiansToCreate[$identity]) ||
                    isset($sponsoredToCreate[$identity]) ||
                    isset($deceasedToCreate[$identity]) || // 🆕 المتوفين الذين سيتم إنشاؤهم
                    (isset($personData['guardian_identity']) && isset($guardiansToCreate[$personData['guardian_identity']]));

                if (!$found && !empty($targetTable) && !$willBeCreated) {
                    $missingPersons[] = [
                        'row' => $personData['row'],
                        'type' => $type,
                        'target_table' => $targetTable,
                        'identity' => $identity,
                        'name' => $personData['name'] ?? '',
                        'guardian_identity' => $personData['guardian_identity'] ?? '',
                        'guardian_name' => $personData['guardian_name'] ?? '',
                        'phone' => $personData['phone'] ?? '',
                        'alt_phone' => $personData['alt_phone'] ?? '',
                        'phone_status' => '',
                        'data' => $personData
                    ];
                }

                // إذا كان الشخص معيل موجود، التحقق من أرقام الهاتف وتحديثها إذا اختلفت
                if ($found && $targetTable === 'data') {
                    $guardian = Data::where('data_id_number', $identity)->first();
                    if ($guardian) {
                        $phoneChanged = false;
                        $phoneChanges = [];

                        // تنظيف وتطبيع الأرقام للمقارنة
                        $newPhone = trim($personData['phone'] ?? '');
                        $newAltPhone = trim($personData['alt_phone'] ?? '');
                        $oldPhone = trim($guardian->data_phone_number ?? '');
                        $oldAltPhone = trim($guardian->data_alt_phone_number ?? '');

                        // مقارنة رقم الهاتف الأساسي (فقط إذا كان الرقم الجديد غير فارغ)
                        if (!empty($newPhone) && $oldPhone !== $newPhone) {
                            $phoneChanges['phone'] = [
                                'old' => $oldPhone ?: 'غير موجود',
                                'new' => $newPhone
                            ];
                            $guardian->data_phone_number = $newPhone;
                            $phoneChanged = true;
                        }

                        // مقارنة رقم الهاتف البديل (فقط إذا كان الرقم الجديد غير فارغ)
                        if (!empty($newAltPhone) && $oldAltPhone !== $newAltPhone) {
                            $phoneChanges['alt_phone'] = [
                                'old' => $oldAltPhone ?: 'غير موجود',
                                'new' => $newAltPhone
                            ];
                            $guardian->data_alt_phone_number = $newAltPhone;
                            $phoneChanged = true;
                        }

                        // حفظ التغييرات فقط إذا كان هناك تغيير فعلي
                        if ($phoneChanged) {
                            $guardian->save();
                            Log::info('📞 تحديث أرقام الهاتف للمعيل:', [
                                'guardian_identity' => $identity,
                                'guardian_name' => $personData['name'],
                                'changes' => $phoneChanges
                            ]);

                            // إضافة إلى قائمة المحدثين فقط إذا كان هناك تغيير
                            $updatedPhones[] = [
                                'identity' => $identity,
                                'name' => $personData['name'] ?? '',
                                'changes' => $phoneChanges,
                                'row' => $personData['row']
                            ];
                        }
                    }
                }
            }

            Log::info('🔍 نتائج البحث عن الأشخاص:', [
                'total_persons_checked' => count($uniquePersons),
                'missing_persons' => count($missingPersons),
                'guardians_to_create' => count($guardiansToCreate),
                'sponsored_to_create' => count($sponsoredToCreate),
                'deceased_to_create' => count($deceasedToCreate), // 🆕 المتوفين
                'updated_phones' => count($updatedPhones),
                'missing_details' => $missingPersons,
                'guardians_from_civil_registry' => array_values($guardiansToCreate),
                'sponsored_from_civil_registry' => array_values($sponsoredToCreate),
                'deceased_from_civil_registry' => array_values($deceasedToCreate), // 🆕 المتوفين
                'updated_phones_details' => $updatedPhones
            ]);

            // 🆕 فحص أفراد العائلة الذين ليس لديهم معيل (بعد معرفة جميع المعيلين المتاحين)
            foreach ($uniquePersons as $personData) {
                $type = $personData['type'];

                // فقط لأفراد العائلة
                if (!in_array($type, ['فرد عايله', 'فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة', 'فرد أسرة', 'فرد الع ائله'])) {
                    continue;
                }

                $guardianIdentity = $personData['guardian_identity'] ?? '';
                $hasGuardian = false;
                $guardianFoundIn = null;

                if (!empty($guardianIdentity)) {
                    // البحث عن المعيل في data
                    $guardianInData = Data::where('data_id_number', $guardianIdentity)->exists();
                    if ($guardianInData) {
                        $hasGuardian = true;
                        $guardianFoundIn = 'data';
                    } else {
                        // البحث عن المعيل في dead_people (باستخدام father_id أو mother_id)
                        $guardianInDeadPeople = DeadPepole::where('father_id', $guardianIdentity)
                            ->orWhere('mother_id', $guardianIdentity)
                            ->exists();
                        if ($guardianInDeadPeople) {
                            $hasGuardian = true;
                            $guardianFoundIn = 'dead_people';
                        } else {
                            // البحث في قائمة المعيلين الذين سيتم إنشاؤهم
                            if (isset($guardiansToCreate[$guardianIdentity])) {
                                $hasGuardian = true;
                                $guardianFoundIn = 'to_be_created';
                            }
                        }
                    }
                }

                // إذا لم يكن للفرد معيل أو معيله غير موجود
                if (empty($guardianIdentity) || !$hasGuardian) {
                    $familyMembersWithoutGuardian[] = [
                        'row' => $personData['row'],
                        'identity' => $personData['identity'],
                        'name' => $personData['name'] ?? '',
                        'guardian_identity' => $guardianIdentity,
                        'guardian_name' => $personData['guardian_name'] ?? '',
                        'reason' => empty($guardianIdentity) ? 'لم يتم تحديد رقم هوية المعيل' : 'المعيل غير موجود في النظام',
                        'will_be_created_without_link' => true
                    ];
                }
            }

            if (!empty($familyMembersWithoutGuardian)) {
                Log::warning('⚠️ أفراد عائلة بدون معيل:', [
                    'count' => count($familyMembersWithoutGuardian),
                    'details' => $familyMembersWithoutGuardian
                ]);
            }

            // التحقق من وجود جميع البنوك في النظام
            $missingBanks = [];
            if (!empty($uniqueBanks)) {
                foreach ($uniqueBanks as $bankName) {
                    // استخدام البحث الذكي للعثور على البنك
                    $bank = BankName::where(function($query) use ($bankName) {
                        $this->addSmartSearch($query, 'description', $bankName, false);
                    })->first();

                    if (!$bank) {
                        $missingBanks[] = $bankName;
                        Log::warning("⚠️ Bank not found:", [
                            'original_name' => $bankName,
                            'normalized_name' => $this->normalizeArabicText($bankName)
                        ]);
                    } else {
                        Log::info("✅ Bank found:", [
                            'searched_for' => $bankName,
                            'found_bank_id' => $bank->id,
                            'found_bank_description' => $bank->description
                        ]);
                    }
                }
            }

            // إذا كان الطلب للفحص فقط، إرجاع النتائج مع قائمة الأشخاص المفقودين والمعيلين من السجل المدني
            if ($request->has('check_only')) {
                // 🆕 فصل الأشخاص الذين لم يُعثر عليهم في السجل المدني (سيستخدم NameSegmentation)
                $guardiansFromCivilRegistry = [];
                $guardiansFromSegmentation = [];
                $sponsoredFromCivilRegistry = [];
                $sponsoredFromSegmentation = [];
                $deceasedFromCivilRegistry = []; // 🆕 المتوفين من السجل المدني
                $deceasedFromSegmentation = []; // 🆕 المتوفين من تقسيم الأسماء

                foreach ($guardiansToCreate as $guardian) {
                    if (($guardian['source'] ?? '') === 'civil_registry') {
                        $guardiansFromCivilRegistry[] = $guardian;
                    } else {
                        $guardiansFromSegmentation[] = $guardian;
                    }
                }

                foreach ($sponsoredToCreate as $sponsored) {
                    if (($sponsored['source'] ?? '') === 'civil_registry') {
                        $sponsoredFromCivilRegistry[] = $sponsored;
                    } else {
                        $sponsoredFromSegmentation[] = $sponsored;
                    }
                }

                // 🆕 فصل المتوفين حسب المصدر
                foreach ($deceasedToCreate as $deceased) {
                    if (($deceased['source'] ?? '') === 'civil_registry') {
                        $deceasedFromCivilRegistry[] = $deceased;
                    } else {
                        $deceasedFromSegmentation[] = $deceased;
                    }
                }

                Log::info('🔍 CHECK ONLY MODE - Validation Results:', [
                    'missing_persons_count' => count($missingPersons),
                    'guardians_from_civil_registry' => count($guardiansFromCivilRegistry),
                    'guardians_from_segmentation' => count($guardiansFromSegmentation),
                    'sponsored_from_civil_registry' => count($sponsoredFromCivilRegistry),
                    'sponsored_from_segmentation' => count($sponsoredFromSegmentation),
                    'deceased_from_civil_registry' => count($deceasedFromCivilRegistry), // 🆕
                    'deceased_from_segmentation' => count($deceasedFromSegmentation), // 🆕
                    'missing_banks_count' => count($missingBanks),
                    'updated_phones_count' => count($updatedPhones),
                    'duplicates_in_excel' => count($duplicatesInExcel), // 🆕
                    'duplicates_in_database' => count($duplicatesInDatabase), // 🆕 التكرارات في قاعدة البيانات
                    'family_members_without_guardian' => count($familyMembersWithoutGuardian), // 🆕
                    'incomplete_bank_data' => count($incompleteBankData), // 🆕
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم فحص الملف بنجاح',
                    'validation' => [
                        'total_rows' => count($rows),
                        'existing_persons' => $existingPersons, // 🆕 الأشخاص الموجودون مسبقاً في قاعدة البيانات
                        'missing_persons' => $missingPersons, // قائمة الأشخاص غير الموجودين نهائياً
                        'guardians_to_create' => $guardiansFromCivilRegistry, // 🆕 المعيلين من السجل المدني
                        'sponsored_to_create' => $sponsoredFromCivilRegistry, // 🆕 المكفولين من السجل المدني
                        'guardians_from_segmentation' => $guardiansFromSegmentation, // 🆕 معيلين سيتم تقسيم أسمائهم
                        'sponsored_from_segmentation' => $sponsoredFromSegmentation, // 🆕 مكفولين سيتم تقسيم أسمائهم
                        'deceased_to_create' => $deceasedFromCivilRegistry, // 🆕 المتوفين من السجل المدني
                        'deceased_from_segmentation' => $deceasedFromSegmentation, // 🆕 المتوفين من تقسيم الأسماء
                        'missing_banks' => $missingBanks,
                        'updated_phones' => $updatedPhones, // قائمة المعيلين الذين تم تحديث أرقامهم
                        'duplicates_in_excel' => $duplicatesInExcel, // 🆕 الأشخاص المكررين في ملف Excel
                        'duplicates_in_database' => $duplicatesInDatabase, // 🆕 السجلات المكررة في قاعدة البيانات
                        'family_members_without_guardian' => $familyMembersWithoutGuardian, // 🆕 أفراد العائلة بدون معيل
                        'incomplete_bank_data' => $incompleteBankData, // 🆕 بيانات البنك الناقصة
                        'existing_active_accounts' => $existingActiveAccounts, // 🆕 الحسابات البنكية النشطة التي ستتحول إلى غير نشطة
                        'allow_import_without_relation' => true, // 🆕 إشارة للسماح بالاستيراد بدون relation_id_number
                        'can_auto_create_guardians' => count($guardiansFromCivilRegistry) > 0 || count($guardiansFromSegmentation) > 0, // 🆕 إشارة لإمكانية الإنشاء التلقائي
                        'can_auto_create_deceased' => count($deceasedFromCivilRegistry) > 0 || count($deceasedFromSegmentation) > 0, // 🆕 إشارة لإمكانية الإنشاء التلقائي للمتوفين
                        'will_deactivate_old_accounts' => count($existingActiveAccounts) > 0, // 🆕 إشارة لوجود حسابات ستُعطّل
                    ]
                ]);
            }

            // 🆕 تحسين: السماح بالاستيراد حتى لو كان الأشخاص غير موجودين
            // سيتم إدخال البيانات في جدول sponsorships مع حفظ person_type
            // وسيتم محاولة البحث عن relation_id_number من الجداول الثلاثة عند الإدخال

            // تسجيل معلومات الأشخاص المفقودين (للتقرير فقط)
            if (!empty($missingPersons)) {
                Log::info('📋 أشخاص غير موجودين في النظام (سيتم إدخالهم بدون relation_id_number):', [
                    'count' => count($missingPersons),
                    'details' => array_slice($missingPersons, 0, 5) // أول 5 فقط للتوضيح
                ]);
            }

            // 🆕 إنشاء المعيلين من السجل المدني قبل الاستيراد
            $createdGuardians = [];
            if (!empty($guardiansToCreate)) {
                foreach ($guardiansToCreate as $guardianIdentity => $guardianData) {
                    try {
                        $createdGuardian = $this->createGuardianFromCivilRegistry($guardianData);
                        if ($createdGuardian) {
                            $createdGuardians[$guardianIdentity] = $createdGuardian;
                            Log::info('✅ تم إنشاء المعيل من السجل المدني', [
                                'identity' => $guardianIdentity,
                                'file_id_number' => $createdGuardian->file_id_number,
                                'name' => $createdGuardian->data_first_name . ' ' . $createdGuardian->data_father_name
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ فشل إنشاء المعيل من السجل المدني', [
                            'identity' => $guardianIdentity,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // 🆕 إنشاء المتوفين (أب/أم) من السجل المدني قبل الاستيراد
            $createdDeceased = [];
            if (!empty($deceasedToCreate)) {
                foreach ($deceasedToCreate as $deceasedIdentity => $deceasedData) {
                    try {
                        $createdDeceasedRecord = $this->createDeceasedFromCivilRegistry($deceasedData);
                        if ($createdDeceasedRecord) {
                            $createdDeceased[$deceasedIdentity] = $createdDeceasedRecord;
                            Log::info('✅ تم إنشاء المتوفي من السجل المدني', [
                                'identity' => $deceasedIdentity,
                                're_file_id' => $createdDeceasedRecord->re_file_id,
                                'type' => $deceasedData['type'],
                                'name' => ($createdDeceasedRecord->father_first_name ?? $createdDeceasedRecord->mother_first_name)
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ فشل إنشاء المتوفي من السجل المدني', [
                            'identity' => $deceasedIdentity,
                            'type' => $deceasedData['type'] ?? 'unknown',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // 🆕 إنشاء المكفولين (أفراد العائلة) من السجل المدني قبل الاستيراد
            // ⚠️ مهم: يجب أن يكون المعيل موجوداً أو تم إنشاؤه بنجاح قبل إنشاء المكفول
            $createdSponsored = [];
            $skippedSponsored = []; // المكفولين الذين تم تخطيهم بسبب عدم وجود معيل
            if (!empty($sponsoredToCreate)) {
                foreach ($sponsoredToCreate as $sponsoredIdentity => $sponsoredData) {
                    try {
                        // الحصول على رقم ملف المعيل (إما موجود أو تم إنشاؤه)
                        $guardianFileId = null;
                        $guardianIdentityNum = $sponsoredData['guardian_identity'] ?? '';
                        $guardianFound = false;

                        if (!empty($guardianIdentityNum)) {
                            // البحث عن المعيل في data
                            $guardian = Data::where('data_id_number', $guardianIdentityNum)->first();
                            if ($guardian) {
                                $guardianFileId = $guardian->file_id_number;
                                $guardianFound = true;
                            } elseif (isset($createdGuardians[$guardianIdentityNum])) {
                                // المعيل تم إنشاؤه للتو
                                $guardianFileId = $createdGuardians[$guardianIdentityNum]->file_id_number;
                                $guardianFound = true;
                            }
                        }

                        // ⛔ منع إنشاء المكفول إذا لم يكن المعيل موجوداً
                        if (!$guardianFound || empty($guardianFileId)) {
                            Log::warning('⛔ تم تخطي إنشاء المكفول - المعيل غير موجود أو فشل إنشاؤه', [
                                'sponsored_identity' => $sponsoredIdentity,
                                'guardian_identity' => $guardianIdentityNum,
                                'sponsored_name' => $sponsoredData['name'] ?? '',
                                'row' => $sponsoredData['row'] ?? ''
                            ]);
                            $skippedSponsored[$sponsoredIdentity] = [
                                'identity' => $sponsoredIdentity,
                                'name' => $sponsoredData['name'] ?? '',
                                'guardian_identity' => $guardianIdentityNum,
                                'reason' => 'المعيل غير موجود أو فشل إنشاؤه',
                                'row' => $sponsoredData['row'] ?? ''
                            ];
                            continue; // تخطي هذا المكفول
                        }

                        $createdSponsoredPerson = $this->createSponsoredFromCivilRegistry($sponsoredData, $guardianFileId);
                        if ($createdSponsoredPerson) {
                            $createdSponsored[$sponsoredIdentity] = $createdSponsoredPerson;
                            Log::info('✅ تم إنشاء المكفول (فرد عائلة) من السجل المدني', [
                                'identity' => $sponsoredIdentity,
                                'registration_id' => $createdSponsoredPerson->registration_id,
                                'name' => $createdSponsoredPerson->first_name . ' ' . $createdSponsoredPerson->second_name,
                                'linked_to_guardian' => $guardianFileId
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ فشل إنشاء المكفول من السجل المدني', [
                            'identity' => $sponsoredIdentity,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // تسجيل ملخص المكفولين المتخطين
            if (!empty($skippedSponsored)) {
                Log::warning('📋 ملخص المكفولين المتخطين بسبب عدم وجود معيل', [
                    'count' => count($skippedSponsored),
                    'details' => array_values($skippedSponsored)
                ]);
            }

            // البنوك المفقودة فقط تمنع الاستيراد (اختياري - يمكن تعليقها لاحقاً)
            if (!empty($missingBanks)) {
                $preValidationErrors[] = "<strong>بنوك غير موجودة في النظام (" . count($missingBanks) . "):</strong><br>"
                    . implode(', ', $missingBanks);
            }

            // إذا كانت هناك أخطاء في التحقق المسبق (البنوك فقط)، أخبر المستخدم
            if (!empty($preValidationErrors)) {
                $errorMessage = "<div style='text-align: right;'>";
                $errorMessage .= "<p><strong>⚠️ لا يمكن بدء الاستيراد بسبب وجود بيانات مفقودة:</strong></p>";
                $errorMessage .= implode('<br><br>', $preValidationErrors);
                $errorMessage .= "<br><br><p><strong>يرجى القيام بما يلي:</strong></p>";
                $errorMessage .= "<ul style='text-align: right; direction: rtl;'>";

                if (!empty($missingBanks)) {
                    $errorMessage .= "<li>إضافة البنوك المفقودة من قسم إدارة البنوك</li>";
                }

                $errorMessage .= "</ul></div>";

                return back()->with('error', $errorMessage);
            }

            // الخطوة 2: بدء عملية الاستيراد الفعلية
            $successCount = 0;
            $errorCount = 0;
            $linkedCount = 0; // 🆕 عداد للسجلات التي تم ربطها بـ relation_id_number
            $unlinkedCount = 0; // 🆕 عداد للسجلات التي تم إدخالها بدون ربط
            $skippedCount = 0; // 🆕 عداد للسجلات المتخطية (مكررة في قاعدة البيانات)
            $skippedRows = []; // 🆕 قائمة الصفوف المتخطية
            $bankAccountsAddedForSkipped = 0; // 🆕 عداد للحسابات البنكية المضافة للكفالات المكررة
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                try {
                    // تخطي الصفوف الفارغة
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // استخراج البيانات من الصف
                    // 🔥 FIX: استخدام null بدلاً من 0 عند عدم وجود العمود لتجنب قراءة قيم خاطئة
                    $personTypeIndex = $columnMap[$requiredColumns['person_type']] ?? null;
                    $sponsoredIdentityIndex = $columnMap[$requiredColumns['sponsored_identity']] ?? null;
                    $sponsoredNameIndex = $columnMap[$requiredColumns['sponsored_name']] ?? null;
                    $guardianIdentityIndex = $columnMap[$requiredColumns['guardian_identity_number']] ?? null;
                    $guardianNameIndex = $columnMap[$requiredColumns['guardian_name']] ?? null;
                    $externalFileNumberIndex = $columnMap[$requiredColumns['external_file_number']] ?? null;
                    $sponsoringOrgIndex = $columnMap[$requiredColumns['sponsoring_organization']] ?? $columnMap[$requiredColumns['sponsoring_organization_alt']] ?? null;
                    $phoneNumberIndex = $columnMap[$requiredColumns['data_phone_number']] ?? null;
                    $altPhoneNumberIndex = $columnMap[$requiredColumns['data_alt_phone_number']] ?? null;
                    $bankNameIndex = $columnMap[$requiredColumns['bank_name']] ?? null;
                    $reGuardianNameIndex = $columnMap[$requiredColumns['re_guardian_name']] ?? null;
                    $rePhoneNumberIndex = $columnMap[$requiredColumns['re_phone_number']] ?? null;

                    $personType = ($personTypeIndex !== null) ? trim($row[$personTypeIndex] ?? '') : '';
                    $sponsoredIdentity = ($sponsoredIdentityIndex !== null) ? trim($row[$sponsoredIdentityIndex] ?? '') : '';
                    $sponsoredName = ($sponsoredNameIndex !== null) ? trim($row[$sponsoredNameIndex] ?? '') : '';
                    $guardianIdentity = ($guardianIdentityIndex !== null) ? trim($row[$guardianIdentityIndex] ?? '') : '';
                    $guardianName = ($guardianNameIndex !== null) ? trim($row[$guardianNameIndex] ?? '') : '';
                    $externalFileNumber = ($externalFileNumberIndex !== null) ? trim($row[$externalFileNumberIndex] ?? '') : '';
                    $sponsoringOrganization = ($sponsoringOrgIndex !== null) ? trim($row[$sponsoringOrgIndex] ?? '') : '';
                    $phoneNumber = ($phoneNumberIndex !== null) ? trim($row[$phoneNumberIndex] ?? '') : '';
                    $altPhoneNumber = ($altPhoneNumberIndex !== null) ? trim($row[$altPhoneNumberIndex] ?? '') : '';
                    $bankName = ($bankNameIndex !== null) ? trim($row[$bankNameIndex] ?? '') : '';
                    $reGuardianName = ($reGuardianNameIndex !== null) ? trim($row[$reGuardianNameIndex] ?? '') : '';
                    $rePhoneNumber = ($rePhoneNumberIndex !== null) ? trim($row[$rePhoneNumberIndex] ?? '') : '';

                    // 🆕 إذا كان اسم المعيل فارغاً ولكن الهوية موجودة، نحاول جلبه من السجل المدني
                    if (empty($guardianName) && !empty($guardianIdentity)) {
                        // أولاً: البحث في المعيلين الذين تم إنشاؤهم
                        if (isset($createdGuardians[$guardianIdentity])) {
                            $createdGuardian = $createdGuardians[$guardianIdentity];
                            $guardianName = trim(
                                ($createdGuardian->data_first_name ?? '') . ' ' .
                                ($createdGuardian->data_father_name ?? '') . ' ' .
                                ($createdGuardian->data_grand_father_name ?? '') . ' ' .
                                ($createdGuardian->data_family_name ?? '')
                            );
                            Log::info('🔍 تم جلب اسم المعيل من المعيلين المُنشأين حديثاً', [
                                'row' => $rowNumber,
                                'guardian_identity' => $guardianIdentity,
                                'guardian_name' => $guardianName
                            ]);
                        }
                        // ثانياً: البحث في جدول data الموجود
                        elseif (empty($guardianName)) {
                            $existingGuardian = Data::where('data_id_number', $guardianIdentity)->first();
                            if ($existingGuardian) {
                                $guardianName = trim(
                                    ($existingGuardian->data_first_name ?? '') . ' ' .
                                    ($existingGuardian->data_father_name ?? '') . ' ' .
                                    ($existingGuardian->data_grand_father_name ?? '') . ' ' .
                                    ($existingGuardian->data_family_name ?? '')
                                );
                                Log::info('🔍 تم جلب اسم المعيل من جدول data', [
                                    'row' => $rowNumber,
                                    'guardian_identity' => $guardianIdentity,
                                    'guardian_name' => $guardianName
                                ]);
                            }
                        }
                        // ثالثاً: البحث في السجل المدني
                        if (empty($guardianName)) {
                            $civilData = $this->searchCivilRegistryForGuardian($guardianIdentity);
                            if ($civilData && !empty($civilData['full_name'])) {
                                $guardianName = $civilData['full_name'];
                                Log::info('🔍 تم جلب اسم المعيل من السجل المدني (أثناء الاستيراد)', [
                                    'row' => $rowNumber,
                                    'guardian_identity' => $guardianIdentity,
                                    'civil_name' => $guardianName
                                ]);
                            }
                        }
                    }

                    // 🔧 FIX: البحث عن عمود هوية المحفظة بالاسم الأساسي أو البديل
                    $personOwnerIdentityIndex = $columnMap[$requiredColumns['person_owner_identity_number']]
                        ?? $columnMap[$requiredColumns['person_owner_identity_number_alt']]
                        ?? null;
                    $personOwnerIdentityNumber = ($personOwnerIdentityIndex !== null) ? trim($row[$personOwnerIdentityIndex] ?? '') : '';

                    // التحقق من الحقول المطلوبة - تم تعديله للسماح بالإدخال بدون هوية المعيل
                    // سيتم البحث عن relation_id_number باستخدام رقم هوية المكفول أو المعيل
                    if (empty($guardianIdentity) && empty($sponsoredIdentity)) {
                        $errors[] = "الصف {$rowNumber}: يجب توفير هوية المكفول أو هوية المعيل على الأقل";
                        $errorCount++;
                        continue;
                    }

                    // 🆕 تحديد نوع الشخص للاستخدام لاحقاً
                    $normalizedPersonType = $this->normalizeArabicText($personType);
                    $isFamilyMemberType = in_array($normalizedPersonType, ['فرد عايله', 'فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة', 'فرد أسرة', 'فرد الع ائله']);

                    // ✅ أفراد العائلة بدون معيل: يتم إدخالهم في جدول الكفالات فقط (بدون ربط)
                    // لكن يتم تسجيلهم للإعلام
                    if ($isFamilyMemberType && empty($guardianIdentity)) {
                        Log::info('ℹ️ فرد عائلة بدون معيل - سيتم إدخاله في جدول الكفالات فقط (بدون ربط)', [
                            'row' => $rowNumber,
                            'identity' => $sponsoredIdentity,
                            'name' => $sponsoredName,
                            'person_type' => $personType
                        ]);
                        // لا نتخطى - نستمر في الإدخال
                    }

                    // 🆕 البحث الموسع في الجداول الثلاثة (data, dead_people, re_people)
                    // للعثور على relation_id_number
                    $internalFileNumber = null; // رقم الملف للربط الداخلي (relation_id_number)
                    $foundInTable = null; // لتتبع الجدول الذي تم العثور على الشخص فيه

                    // ========================================
                    // 🔍 البحث في الجداول الثلاثة باستخدام رقم هوية المكفول
                    // ========================================
                    if (!empty($sponsoredIdentity)) {
                        // 1. البحث في جدول data (المعيلين)
                        $dataRecord = Data::where('data_id_number', $sponsoredIdentity)->first();
                        if ($dataRecord) {
                            $internalFileNumber = $dataRecord->file_id_number;
                            $foundInTable = 'data';
                            Log::info("✅ تم العثور على الشخص في جدول data", [
                                'row' => $rowNumber,
                                'identity' => $sponsoredIdentity,
                                'file_id_number' => $internalFileNumber
                            ]);
                        }

                        // 2. البحث في جدول re_people (أفراد العائلة) إذا لم يتم العثور
                        if (!$internalFileNumber) {
                            $rePeopleRecord = RePeople::where('person_id', $sponsoredIdentity)->first();
                            if ($rePeopleRecord) {
                                $internalFileNumber = $rePeopleRecord->registration_id;
                                $foundInTable = 're_people';
                                Log::info("✅ تم العثور على الشخص في جدول re_people", [
                                    'row' => $rowNumber,
                                    'identity' => $sponsoredIdentity,
                                    'registration_id' => $internalFileNumber
                                ]);
                            }
                        }

                        // 🆕 2.5 البحث في المكفولين الذين تم إنشاؤهم من السجل المدني
                        if (!$internalFileNumber && isset($createdSponsored[$sponsoredIdentity])) {
                            $internalFileNumber = $createdSponsored[$sponsoredIdentity]->registration_id;
                            $foundInTable = 're_people (created from civil registry)';
                            Log::info("✅ تم استخدام المكفول المُنشأ من السجل المدني", [
                                'row' => $rowNumber,
                                'sponsored_identity' => $sponsoredIdentity,
                                'registration_id' => $internalFileNumber
                            ]);
                        }

                        // 3. البحث في جدول dead_people (المتوفين) إذا لم يتم العثور
                        if (!$internalFileNumber) {
                            // البحث بهوية الأب أو الأم
                            $deadRecord = DeadPepole::where('father_id', $sponsoredIdentity)
                                ->orWhere('mother_id', $sponsoredIdentity)
                                ->first();
                            if ($deadRecord) {
                                $internalFileNumber = $deadRecord->re_file_id;
                                $foundInTable = 'dead_people';
                                Log::info("✅ تم العثور على الشخص في جدول dead_people", [
                                    'row' => $rowNumber,
                                    'identity' => $sponsoredIdentity,
                                    're_file_id' => $internalFileNumber
                                ]);
                            }
                        }

                        // 🆕 3.5 البحث في المتوفين الذين تم إنشاؤهم من السجل المدني
                        if (!$internalFileNumber && isset($createdDeceased[$sponsoredIdentity])) {
                            $internalFileNumber = $createdDeceased[$sponsoredIdentity]->re_file_id;
                            $foundInTable = 'dead_people (created from civil registry)';
                            Log::info("✅ تم استخدام المتوفي المُنشأ من السجل المدني", [
                                'row' => $rowNumber,
                                'sponsored_identity' => $sponsoredIdentity,
                                're_file_id' => $internalFileNumber
                            ]);
                        }
                    }

                    // ========================================
                    // 🔍 البحث باستخدام رقم هوية المعيل إذا لم يتم العثور بهوية المكفول
                    // ========================================
                    if (!$internalFileNumber && !empty($guardianIdentity)) {
                        // 1. البحث في جدول data (المعيلين)
                        $guardianRecord = Data::where('data_id_number', $guardianIdentity)->first();
                        if ($guardianRecord) {
                            $internalFileNumber = $guardianRecord->file_id_number;
                            $foundInTable = 'data (guardian)';
                            Log::info("✅ تم العثور على المعيل في جدول data", [
                                'row' => $rowNumber,
                                'guardian_identity' => $guardianIdentity,
                                'file_id_number' => $internalFileNumber
                            ]);
                        }

                        // 2. البحث في جدول re_people إذا لم يتم العثور
                        if (!$internalFileNumber) {
                            $rePeopleRecord = RePeople::where('person_id', $guardianIdentity)->first();
                            if ($rePeopleRecord) {
                                $internalFileNumber = $rePeopleRecord->registration_id;
                                $foundInTable = 're_people (guardian)';
                                Log::info("✅ تم العثور على المعيل في جدول re_people", [
                                    'row' => $rowNumber,
                                    'guardian_identity' => $guardianIdentity,
                                    'registration_id' => $internalFileNumber
                                ]);
                            }
                        }

                        // 3. البحث في جدول dead_people إذا لم يتم العثور
                        if (!$internalFileNumber) {
                            $deadRecord = DeadPepole::where('father_id', $guardianIdentity)
                                ->orWhere('mother_id', $guardianIdentity)
                                ->first();
                            if ($deadRecord) {
                                $internalFileNumber = $deadRecord->re_file_id;
                                $foundInTable = 'dead_people (guardian)';
                                Log::info("✅ تم العثور على المعيل في جدول dead_people", [
                                    'row' => $rowNumber,
                                    'guardian_identity' => $guardianIdentity,
                                    're_file_id' => $internalFileNumber
                                ]);
                            }
                        }

                        // 🆕 3.5 البحث في المتوفين الذين تم إنشاؤهم من السجل المدني (بهوية المعيل)
                        if (!$internalFileNumber && isset($createdDeceased[$guardianIdentity])) {
                            $internalFileNumber = $createdDeceased[$guardianIdentity]->re_file_id;
                            $foundInTable = 'dead_people (guardian - created from civil registry)';
                            Log::info("✅ تم استخدام المتوفي المُنشأ من السجل المدني (كمعيل)", [
                                'row' => $rowNumber,
                                'guardian_identity' => $guardianIdentity,
                                're_file_id' => $internalFileNumber
                            ]);
                        }

                        // 🆕 4. البحث في المعيلين الذين تم إنشاؤهم من السجل المدني
                        if (!$internalFileNumber && isset($createdGuardians[$guardianIdentity])) {
                            $internalFileNumber = $createdGuardians[$guardianIdentity]->file_id_number;
                            $foundInTable = 'data (created from civil registry)';
                            Log::info("✅ تم استخدام المعيل المُنشأ من السجل المدني", [
                                'row' => $rowNumber,
                                'guardian_identity' => $guardianIdentity,
                                'file_id_number' => $internalFileNumber
                            ]);
                        }
                    }

                    // ========================================
                    // 🆕 إذا لم يتم العثور على الشخص - إدخال البيانات بدون relation_id_number
                    // ========================================
                    if (!$internalFileNumber) {
                        Log::info("📝 لم يتم العثور على الشخص في أي جدول - سيتم الإدخال بدون relation_id_number", [
                            'row' => $rowNumber,
                            'sponsored_identity' => $sponsoredIdentity,
                            'guardian_identity' => $guardianIdentity,
                            'person_type' => $personType
                        ]);
                        // سيتم ترك relation_id_number فارغاً
                    }

                    // تسجيل نتيجة البحث

                    // 🆕 التحقق من عدم تكرار الكفالة لنفس الجمعية
                    $sponsorshipSkipped = false; // 🆕 علامة لتتبع ما إذا تم تخطي إنشاء الكفالة
                    $duplicateSponsorship = Sponsorship::where('identity_number', $sponsoredIdentity)
                        ->where('sponsor_id', $request->sponsor_id)
                        ->first();

                    // التحقق من وجود البنك أولاً (نحتاجه حتى لو الكفالة مكررة)
                    $bankId = null;
                    if (!empty($bankName)) {
                        $bank = BankName::where(function($query) use ($bankName) {
                            $this->addSmartSearch($query, 'description', $bankName, false);
                        })->first();

                        if ($bank) {
                            $bankId = $bank->id;
                        }
                    }

                    // 🆕 تحديد نوع الشخص مبكراً (نحتاجه للبيانات البنكية)
                    $isGuardianType = in_array($normalizedPersonType, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة']);

                    // 🆕 تحديد إذا كان المتوفي (أب/أم) - يعامل مثل المعيل (هو نفسه المكفول والمعيل)
                    $isDeceasedType = in_array($normalizedPersonType, [
                        'أب متوفي', 'اب متوفي', 'الاب المتوفي', 'الأب المتوفي',
                        'أم متوفيه', 'ام متوفيه', 'الام المتوفيه', 'أم متوفية', 'ام متوفية', 'الأم المتوفية', 'الام المتوفية'
                    ]);

                    // 🆕 للمتوفين: هم أنفسهم المكفول والمعيل - guardian_identity_number = identity_number
                    $isPersonAsGuardian = $isGuardianType || $isDeceasedType;

                    if ($duplicateSponsorship) {
                        Log::warning('⚠️ الكفالة موجودة مسبقاً - سيتم تخطي إنشاء كفالة جديدة ومحاولة إدخال البيانات البنكية', [
                            'row' => $rowNumber,
                            'identity_number' => $sponsoredIdentity,
                            'sponsor_id' => $request->sponsor_id,
                            'existing_sponsorship_id' => $duplicateSponsorship->id
                        ]);
                        $skippedRows[] = [
                            'row' => $rowNumber,
                            'reason' => 'كفالة مكررة - تم محاولة إدخال البيانات البنكية فقط',
                            'identity' => $sponsoredIdentity
                        ];
                        $skippedCount++;
                        $sponsorshipSkipped = true;

                        // 🆕 استخدام بيانات الكفالة الموجودة للحساب البنكي
                        $displayFileNumber = $duplicateSponsorship->internal_file_number;
                        $bankAccountFileNumber = $duplicateSponsorship->relation_id_number ?: $duplicateSponsorship->internal_file_number;

                        // 🆕 إدخال البيانات البنكية للكفالة المكررة إذا كانت متوفرة
                        if ($bankId && $bankAccountFileNumber) {
                            // 🆕 للمعيل/المتوفي: هو نفسه المعيل
                            $actualGuardianIdentity = $isPersonAsGuardian ? $sponsoredIdentity : $guardianIdentity;
                            $accountOwnerIdentity = $actualGuardianIdentity;

                            if (!empty($personOwnerIdentityNumber)) {
                                $cleanedValue = preg_replace('/\D/', '', $personOwnerIdentityNumber);
                                if (strlen($cleanedValue) >= 9) {
                                    $accountOwnerIdentity = $personOwnerIdentityNumber;
                                }
                            }

                            $bankValidationService = app(BankAccountValidationService::class);
                            $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
                                'guardian_registration' => $bankAccountFileNumber,
                                'person_owner_identity_number' => $accountOwnerIdentity,
                                're_id_number' => $actualGuardianIdentity,
                                're_phone_number' => $rePhoneNumber ?: $phoneNumber,
                                'bank_name' => $bankId
                            ]);

                            if (!$duplicateCheck['is_duplicate']) {
                                // 🆕 قبل إضافة الحساب الجديد، نعطّل جميع الحسابات النشطة القديمة لنفس رقم الملف
                                // التي لا تتطابق مع الحساب الجديد (بالـ 4 أعمدة)
                                $deactivatedCount = GuardianBankAccount::where('guardian_registration', $bankAccountFileNumber)
                                    ->where('check_account', 1)
                                    ->where(function($query) use ($bankId, $actualGuardianIdentity, $rePhoneNumber, $phoneNumber, $accountOwnerIdentity) {
                                        // استثناء الحساب المطابق تماماً
                                        $query->where('bank_name', '!=', $bankId)
                                            ->orWhere('re_id_number', '!=', $actualGuardianIdentity)
                                            ->orWhere('re_phone_number', '!=', ($rePhoneNumber ?: $phoneNumber))
                                            ->orWhere('person_owner_identity_number', '!=', $accountOwnerIdentity);
                                    })
                                    ->update(['check_account' => 0]);

                                if ($deactivatedCount > 0) {
                                    Log::info('🔄 تم تعطيل الحسابات البنكية القديمة (كفالة مكررة)', [
                                        'row' => $rowNumber,
                                        'guardian_registration' => $bankAccountFileNumber,
                                        'deactivated_count' => $deactivatedCount,
                                        'reason' => 'إضافة حساب جديد من Excel للكفالة المكررة'
                                    ]);
                                }

                                $bankAccount = new GuardianBankAccount();
                                $bankAccount->guardian_registration = $bankAccountFileNumber;
                                $bankAccount->person_owner_identity_number = $accountOwnerIdentity;
                                $bankAccount->re_id_number = $actualGuardianIdentity;
                                $bankAccount->re_guardian_name = $reGuardianName ?: $guardianName;
                                $bankAccount->bank_name = $bankId;
                                $bankAccount->re_phone_number = $rePhoneNumber ?: $phoneNumber;
                                $bankAccount->check_account = 1; // ✅ اعتماد الحساب تلقائياً عند الاستيراد من Excel
                                $bankAccount->save();

                                $bankAccountsAddedForSkipped++; // 🆕 زيادة عداد الحسابات البنكية المضافة

                                Log::info('💾 تم حفظ الحساب البنكي (للكفالة المكررة)', [
                                    'row' => $rowNumber,
                                    'person_type' => $personType,
                                    'guardian_registration' => $bankAccountFileNumber,
                                    're_id_number (المعيل)' => $actualGuardianIdentity,
                                    'person_owner_identity_number (صاحب المحفظة)' => $accountOwnerIdentity,
                                    'bank_name' => $bankId,
                                    'existing_sponsorship_id' => $duplicateSponsorship->id,
                                    'deactivated_old_accounts' => $deactivatedCount
                                ]);
                            } else {
                                // ✅ الحساب موجود مسبقاً - نتحقق إذا كان غير نشط ونعيد تفعيله
                                $existingAccount = $duplicateCheck['existing_account'] ?? null;
                                if ($existingAccount && isset($existingAccount['id'])) {
                                    $accountToReactivate = GuardianBankAccount::find($existingAccount['id']);
                                    if ($accountToReactivate && $accountToReactivate->check_account == 0) {
                                        // 🔄 تعطيل أي حسابات نشطة أخرى لنفس guardian_registration أولاً
                                        $otherDeactivatedCount = GuardianBankAccount::where('guardian_registration', $bankAccountFileNumber)
                                            ->where('check_account', 1)
                                            ->where('id', '!=', $accountToReactivate->id)
                                            ->update(['check_account' => 0]);

                                        // ✅ إعادة تفعيل الحساب الموجود
                                        $accountToReactivate->check_account = 1;
                                        $accountToReactivate->save();

                                        Log::info('✅ تم إعادة تفعيل حساب بنكي موجود (كفالة مكررة)', [
                                            'row' => $rowNumber,
                                            'account_id' => $accountToReactivate->id,
                                            'guardian_registration' => $bankAccountFileNumber,
                                            'other_deactivated' => $otherDeactivatedCount
                                        ]);
                                    }
                                }
                                Log::info('🔄 الحساب البنكي موجود مسبقاً - لا حاجة لإعادة الإدخال', [
                                    'row' => $rowNumber,
                                    'existing_account_id' => $duplicateCheck['existing_account']['id'] ?? null
                                ]);
                            }
                        }

                        continue; // الآن نتخطى بعد معالجة البيانات البنكية
                    }

                    // إنشاء سجل الكفالة
                    DB::beginTransaction();

                    // ⚠️ توليد رقم ملف للعرض
                    $displayFileNumber = null;

                    // 🆕 تحسين: توليد رقم ملف للعرض في جميع الحالات
                    if ($isGuardianType) {
                        // المعيل: استخدام نفس الرقم الموجود إذا تم العثور عليه، وإلا توليد رقم جديد
                        $displayFileNumber = $internalFileNumber ?: generateUniqueReservedCode('data', 'file_id_number');
                        Log::info("📋 المعيل: تحديد رقم الملف للعرض", [
                            'row' => $rowNumber,
                            'file_number' => $displayFileNumber,
                            'was_found' => !empty($internalFileNumber)
                        ]);
                    } elseif ($isDeceasedType) {
                        // 🆕 المتوفي (أب/أم): استخدام رقم الملف من dead_people إذا تم إنشاؤه، وإلا توليد رقم جديد
                        $displayFileNumber = $internalFileNumber ?: generateUniqueReservedCode('dead_people', 're_file_id');
                        Log::info("🕯️ المتوفي: تحديد رقم الملف للعرض", [
                            'row' => $rowNumber,
                            'file_number' => $displayFileNumber,
                            'was_found' => !empty($internalFileNumber),
                            'person_type' => $personType
                        ]);
                    } else {
                        // الأنواع الأخرى: توليد رقم جديد للعرض دائماً
                        $displayFileNumber = generateUniqueReservedCode('data', 'file_id_number');
                        Log::info("📋 توليد رقم ملف للعرض", [
                            'row' => $rowNumber,
                            'relation_id' => $internalFileNumber, // الرقم الداخلي للربط (قد يكون null)
                            'display_file_number' => $displayFileNumber // الرقم المعروض للمستخدم
                        ]);
                    }

                    // ⚠️ في حالة المعيل/المتوفي: يتم إدخال اسمه ورقم هويته فقط، وباقي البيانات تترك فارغة
                    // ملاحظة: $isGuardianType و $isDeceasedType و $isPersonAsGuardian تم تعريفهم مسبقاً

                    $sponsorship = new Sponsorship();
                    // للمعيل/المتوفي: نخزن اسمه ورقمه في حقول المكفول
                    $sponsorship->identity_number = $sponsoredIdentity ?: null;
                    $sponsorship->orphan_name = $sponsoredName ?: null;

                    // 🆕 للمعيل/المتوفي: هم أنفسهم المكفول والمعيل
                    // guardian_identity_number = identity_number (رقم هويتهم)
                    // guardian_name = orphan_name (نفس الاسم)
                    if ($isPersonAsGuardian) {
                        $sponsorship->guardian_name = $sponsoredName ?: null; // نفس الاسم
                        $sponsorship->guardian_identity_number = $sponsoredIdentity; // نفس رقم الهوية
                    } else {
                        $sponsorship->guardian_name = $guardianName ?: null;
                        $sponsorship->guardian_identity_number = $guardianIdentity;
                    }
                    $sponsorship->relation_id_number = $internalFileNumber; // رقم الربط الداخلي (مخفي)
                    $sponsorship->internal_file_number = $displayFileNumber; // الرقم المعروض للمستخدم
                    $sponsorship->external_file_number = $externalFileNumber ?: null;
                    $sponsorship->sponsoring_organization = $sponsoringOrganization ?: null;
                    $sponsorship->sponsor_id = $request->sponsor_id; // ✅ حفظ رقم الجمعية
                    $sponsorship->sponsorship_type_id = $request->sponsorship_type_id;
                    // استخدام الحالة المحددة أو "جديد" (ID = 4) كحالة افتراضية
                    $sponsorship->sponsorship_status_id = $request->sponsorship_status_id ?: 4;
                    // ✅ حفظ نوع الشخص بالإنجليزية (تحويل من العربية)
                    $sponsorship->person_type = $this->convertPersonTypeToEnglish($personType);
                    $sponsorship->created_by = auth()->id();
                    $sponsorship->save();

                    // 🆕 تسجيل معلومات الكفالة للمتوفين
                    if ($isDeceasedType) {
                        Log::info('🕯️ تم إنشاء كفالة للمتوفي', [
                            'row' => $rowNumber,
                            'sponsorship_id' => $sponsorship->id,
                            'identity_number' => $sponsorship->identity_number,
                            'guardian_identity_number' => $sponsorship->guardian_identity_number,
                            'relation_id_number' => $sponsorship->relation_id_number,
                            'internal_file_number' => $sponsorship->internal_file_number,
                            'person_type' => $sponsorship->person_type
                        ]);
                    }

                    // ربط الكفالة بالمؤسسة الكافلة
                    // 🔥 FIX: استخدام sync بدلاً من syncWithoutDetaching
                    // لضمان أن الكفالة الجديدة تنتمي فقط للجمعية المحددة وليس جمعيات سابقة
                    $sponsorship->sponsors()->sync([$request->sponsor_id]);

                    // إضافة البيانات البنكية - استخدام رقم ملف المعيل الحقيقي (للربط الداخلي) مع التحقق من التكرار
                    // 🔥 FIX: استخدام displayFileNumber إذا كان internalFileNumber فارغ
                    // هذا يضمن أن guardian_registration لن يكون null أبداً
                    $bankAccountFileNumber = $internalFileNumber ?: $displayFileNumber;

                    if ($bankId && $bankAccountFileNumber) {
                        // 🎯 تحديد رقم هوية المعيل بشكل قاطع (بغض النظر عن نوع الشخص)
                        // في حالة المعيل/المتوفي: يكون نفسه المكفول (sponsoredIdentity)
                        // في الحالات الأخرى: نستخدم guardianIdentity
                        $actualGuardianIdentity = $isPersonAsGuardian ? $sponsoredIdentity : $guardianIdentity;

                        // ✅ person_owner_identity_number: رقم هوية صاحب الحساب البنكي (من عمود "هوية المحفظة")
                        // 🔥 CRITICAL FIX: التأكد من أن القيمة هي رقم هوية وليس رقم ملف خارجي
                        // - رقم الهوية يجب أن يكون 10 أرقام
                        // - رقم الملف الخارجي عادة 5 أرقام فقط (مثل 80004)
                        $accountOwnerIdentity = $actualGuardianIdentity; // القيمة الافتراضية: هوية المعيل

                        if (!empty($personOwnerIdentityNumber)) {
                            // التحقق من أن القيمة ليست رقم ملف خارجي (أقل من 6 أرقام)
                            $cleanedValue = preg_replace('/\D/', '', $personOwnerIdentityNumber); // إزالة أي رموز غير رقمية

                            if (strlen($cleanedValue) >= 9) {
                                // هذا رقم هوية صالح (9-10 أرقام)
                                $accountOwnerIdentity = $personOwnerIdentityNumber;
                                Log::info('✅ رقم هوية صالح لصاحب المحفظة', [
                                    'row' => $rowNumber,
                                    'identity' => $accountOwnerIdentity,
                                    'length' => strlen($cleanedValue)
                                ]);
                            } else {
                                // هذا رقم ملف خارجي (أقل من 9 أرقام) - سنستخدم هوية المعيل
                                Log::warning('⚠️ القيمة في عمود "هوية المحفظة" ليست رقم هوية صالح - استخدام هوية المعيل', [
                                    'row' => $rowNumber,
                                    'invalid_value' => $personOwnerIdentityNumber,
                                    'length' => strlen($cleanedValue),
                                    'using_guardian_identity' => $actualGuardianIdentity
                                ]);
                            }
                        }

                        // 🔍 التحقق من عدم تكرار الحساب البنكي
                        // ✅ نمرر جميع الأعمدة الخمسة التي يجب أن تكون متطابقة لاعتبار الحساب مكرر:
                        // 1. guardian_registration (رقم ملف المعيل الداخلي)
                        // 2. person_owner_identity_number (رقم هوية صاحب الحساب)
                        // 3. re_id_number (رقم هوية المعيل)
                        // 4. re_phone_number (رقم جوال المحفظة)
                        // 5. bank_name (اسم البنك/المحفظة)
                        $bankValidationService = app(BankAccountValidationService::class);

                        $duplicateCheck = $bankValidationService->checkDuplicateBankAccount([
                            'guardian_registration' => $bankAccountFileNumber,
                            'person_owner_identity_number' => $accountOwnerIdentity,
                            're_id_number' => $actualGuardianIdentity,
                            're_phone_number' => $rePhoneNumber ?: $phoneNumber,
                            'bank_name' => $bankId
                        ]);

                        if (!$duplicateCheck['is_duplicate']) {
                            // 🆕 قبل إضافة الحساب الجديد، نعطّل جميع الحسابات النشطة القديمة لنفس رقم الملف
                            // التي لا تتطابق مع الحساب الجديد (بالـ 4 أعمدة)
                            $deactivatedCount = GuardianBankAccount::where('guardian_registration', $bankAccountFileNumber)
                                ->where('check_account', 1)
                                ->where(function($query) use ($bankId, $actualGuardianIdentity, $rePhoneNumber, $phoneNumber, $accountOwnerIdentity) {
                                    // استثناء الحساب المطابق تماماً
                                    $query->where('bank_name', '!=', $bankId)
                                        ->orWhere('re_id_number', '!=', $actualGuardianIdentity)
                                        ->orWhere('re_phone_number', '!=', ($rePhoneNumber ?: $phoneNumber))
                                        ->orWhere('person_owner_identity_number', '!=', $accountOwnerIdentity);
                                })
                                ->update(['check_account' => 0]);

                            if ($deactivatedCount > 0) {
                                Log::info('🔄 تم تعطيل الحسابات البنكية القديمة', [
                                    'row' => $rowNumber,
                                    'guardian_registration' => $bankAccountFileNumber,
                                    'deactivated_count' => $deactivatedCount,
                                    'reason' => 'إضافة حساب جديد من Excel'
                                ]);
                            }

                            $bankAccount = new GuardianBankAccount();
                            $bankAccount->guardian_registration = $bankAccountFileNumber; // استخدام رقم ملف المعيل (أصلي أو مولد)

                            // ✅ person_owner_identity_number: رقم هوية صاحب الحساب البنكي (من عمود "هوية المحفظة")
                            $bankAccount->person_owner_identity_number = $accountOwnerIdentity;

                            // ✅ re_id_number: رقم هوية المعيل دائماً (بغض النظر عن نوع الشخص)
                            // - إذا كان معيل: نستخدم رقم هويته (sponsoredIdentity)
                            // - إذا كان فرد أسرة/متوفي: نستخدم رقم هوية المعيل (guardianIdentity)
                            $bankAccount->re_id_number = $actualGuardianIdentity;

                            $bankAccount->re_guardian_name = $reGuardianName ?: $guardianName;
                            $bankAccount->bank_name = $bankId;
                            $bankAccount->re_phone_number = $rePhoneNumber ?: $phoneNumber;
                            $bankAccount->check_account = 1; // ✅ اعتماد الحساب تلقائياً عند الاستيراد من Excel
                            $bankAccount->save();

                            Log::info('💾 تم حفظ الحساب البنكي', [
                                'row' => $rowNumber,
                                'person_type' => $personType,
                                'guardian_registration' => $bankAccountFileNumber,
                                'original_internal_number' => $internalFileNumber,
                                're_id_number (المعيل)' => $actualGuardianIdentity,
                                'person_owner_identity_number (صاحب المحفظة)' => $accountOwnerIdentity,
                                'bank_name' => $bankId
                            ]);
                        } else {
                            // ✅ الحساب موجود مسبقاً بنفس الـ 4 أعمدة - نتحقق إذا كان غير نشط ونعيد تفعيله
                            $existingAccount = $duplicateCheck['existing_account'] ?? null;
                            if ($existingAccount && isset($existingAccount['id'])) {
                                $accountToReactivate = GuardianBankAccount::find($existingAccount['id']);
                                if ($accountToReactivate && $accountToReactivate->check_account == 0) {
                                    // 🔄 تعطيل أي حسابات نشطة أخرى لنفس guardian_registration أولاً
                                    $otherDeactivatedCount = GuardianBankAccount::where('guardian_registration', $bankAccountFileNumber)
                                        ->where('check_account', 1)
                                        ->where('id', '!=', $accountToReactivate->id)
                                        ->update(['check_account' => 0]);

                                    // ✅ إعادة تفعيل الحساب الموجود
                                    $accountToReactivate->check_account = 1;
                                    $accountToReactivate->save();

                                    Log::info('✅ تم إعادة تفعيل حساب بنكي موجود', [
                                        'row' => $rowNumber,
                                        'account_id' => $accountToReactivate->id,
                                        'guardian_registration' => $bankAccountFileNumber,
                                        'other_deactivated' => $otherDeactivatedCount
                                    ]);
                                } else {
                                    // الحساب موجود ونشط بالفعل، لكن تأكد من تعطيل أي حسابات أخرى
                                    $otherDeactivatedCount = GuardianBankAccount::where('guardian_registration', $bankAccountFileNumber)
                                        ->where('check_account', 1)
                                        ->where('id', '!=', $accountToReactivate->id)
                                        ->update(['check_account' => 0]);

                                    if ($otherDeactivatedCount > 0) {
                                        Log::info('🔄 تم تعطيل حسابات أخرى لنفس رقم الملف', [
                                            'row' => $rowNumber,
                                            'active_account_id' => $accountToReactivate->id,
                                            'guardian_registration' => $bankAccountFileNumber,
                                            'deactivated_count' => $otherDeactivatedCount
                                        ]);
                                    }
                                }
                            }

                            Log::warning('🚫 تم منع إدخال حساب بنكي مكرر - الأعمدة الأربعة متطابقة', [
                                'row' => $rowNumber,
                                'person_type' => $personType,
                                '1_bank_name' => $bankId,
                                '2_re_id_number' => $actualGuardianIdentity,
                                '3_re_phone_number' => $rePhoneNumber ?: $phoneNumber,
                                '4_person_owner_identity_number' => $accountOwnerIdentity,
                                'duplicate_message' => $duplicateCheck['message'],
                                'existing_account_id' => $duplicateCheck['existing_account']['id'] ?? null
                            ]);
                        }
                    }

                    DB::commit();

                    // ✅ تحديث جدول reserved_codes لتمييز الأرقام المستخدمة
                    // 🎯 displayFileNumber: الرقم الذي تم توليده وحفظه في internal_file_number
                    if ($displayFileNumber && function_exists('markCodeAsUsed')) {
                        markCodeAsUsed($displayFileNumber, auth()->id(), 'استيراد كفالة من Excel - صف ' . $rowNumber);
                    }

                    $successCount++;

                    // 🆕 تحديث عدادات الربط
                    if ($internalFileNumber) {
                        $linkedCount++;
                    } else {
                        $unlinkedCount++;
                    }

                    Log::info("✅ تم استيراد الصف {$rowNumber} بنجاح", [
                        'person_type' => $personType,
                        'sponsored_identity' => $sponsoredIdentity,
                        'guardian_identity' => $guardianIdentity,
                        'relation_id_number' => $internalFileNumber, // الرقم الداخلي للربط
                        'internal_file_number' => $displayFileNumber, // الرقم المعروض
                        'was_linked' => !empty($internalFileNumber),
                        'found_in_table' => $foundInTable ?? 'none'
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    $errorCount++;
                    $errors[] = "الصف {$rowNumber}: {$e->getMessage()}";

                    Log::error("❌ خطأ في استيراد الصف {$rowNumber}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // إعداد النتيجة
            $result = [
                'success' => true,
                'message' => 'تمت عملية الاستيراد',
                'summary' => [
                    'total' => count($rows),
                    'success' => $successCount,
                    'errors' => $errorCount,
                    'skipped' => $skippedCount, // 🆕 عدد السجلات المتخطية (مكررة)
                    'bank_accounts_added_for_skipped' => $bankAccountsAddedForSkipped, // 🆕 حسابات بنكية مضافة للكفالات المكررة
                    'linked' => $linkedCount, // 🆕 عدد السجلات المرتبطة
                    'unlinked' => $unlinkedCount, // 🆕 عدد السجلات غير المرتبطة
                ],
                'skipped_rows' => $skippedRows, // 🆕 تفاصيل الصفوف المتخطية
                'errors' => $errors,
            ];

            Log::info('✅ اكتملت عملية الاستيراد', [
                'total' => count($rows),
                'success' => $successCount,
                'errors' => $errorCount,
                'skipped' => $skippedCount,
                'bank_accounts_added_for_skipped' => $bankAccountsAddedForSkipped,
                'linked_with_relation_id' => $linkedCount,
                'without_relation_id' => $unlinkedCount
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('❌ خطأ في عملية الاستيراد:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء استيراد البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🚫 هذه الدالة معطلة - إنشاء الأشخاص يجب أن يتم من البوابات المخصصة فقط
     * لمنع إدخال بيانات خاطئة أو ناقصة في جداول (data, re_people, dead_people)
     *
     * @deprecated هذه الدالة معطلة ولا يجب استخدامها
     */
    public function createMissingPersons(Request $request)
    {
        Log::warning('🚫 محاولة استخدام دالة createMissingPersons المعطلة', [
            'user_id' => auth()->id(),
            'ip' => $request->ip()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'هذه الوظيفة معطلة. يجب إنشاء سجلات الأشخاص من خلال بوابة إدارة الملفات المخصصة لضمان صحة البيانات.'
        ], 403);
    }

    /**
     * 🆕 البحث في السجل المدني عن تاريخ الميلاد والبيانات الأساسية
     *
     * @param string $identityNumber رقم الهوية
     * @return array|null البيانات المُرجعة أو null إذا لم يُعثر على الشخص
     */
    private function searchCivilRegistryForBirthDate($identityNumber)
    {
        if (empty($identityNumber)) {
            return null;
        }

        try {
            $person = DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $identityNumber)
                ->first();

            if ($person) {
                // تحويل تاريخ الميلاد إلى صيغة Y-m-d
                $birthDate = null;
                if (!empty($person->CI_BIRTH_DT)) {
                    try {
                        $birthDate = date('Y-m-d', strtotime($person->CI_BIRTH_DT));
                    } catch (\Exception $e) {
                        $birthDate = null;
                    }
                }

                $fullName = trim(
                    ($person->CI_FIRST_ARB ?? '') . ' ' .
                    ($person->CI_FATHER_ARB ?? '') . ' ' .
                    ($person->CI_GRAND_FATHER_ARB ?? '') . ' ' .
                    ($person->CI_FAMILY_ARB ?? '')
                );

                Log::info('🔍 تم جلب بيانات من السجل المدني', [
                    'identity_number' => $identityNumber,
                    'birth_date' => $birthDate,
                    'full_name' => $fullName
                ]);

                return [
                    'identity_number' => $person->CI_ID_NUM,
                    'first_name' => $person->CI_FIRST_ARB ?? '',
                    'second_name' => $person->CI_FATHER_ARB ?? '',
                    'third_name' => $person->CI_GRAND_FATHER_ARB ?? '',
                    'last_name' => $person->CI_FAMILY_ARB ?? '',
                    'full_name' => $fullName,
                    'birth_date' => $birthDate,
                    'gender' => $person->CI_SEX ?? '',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('⚠️ فشل البحث في السجل المدني', [
                'identity_number' => $identityNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * تطبيع النص العربي للبحث المتقدم
     * يدعم: الألف بأشكالها، التاء المربوطة، الياء، حذف المسافات الزائدة
     */
    private function normalizeArabicText($text)
    {
        // تحويل جميع أشكال الألف إلى ألف عادية
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);

        // تحويل التاء المربوطة إلى هاء
        $text = str_replace(['ة'], 'ه', $text);

        // تحويل الياء المختلفة
        $text = str_replace(['ى'], 'ي', $text);

        // إزالة التشكيل (الحركات)
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);

        // إزالة المسافات الزائدة
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * 🆕 تحويل نوع الشخص من العربية إلى الإنجليزية
     * للتوافق مع قاعدة البيانات والجداول المرتبطة
     *
     * فرد عائلة => family_member (re_people)
     * معيل => breadwinner (data)
     * أب متوفي => deceased_father (dead_people)
     * أم متوفية => deceased_mother (dead_people)
     *
     * @param string $arabicType نوع الشخص بالعربية من ملف Excel
     * @return string|null نوع الشخص بالإنجليزية
     */
    private function convertPersonTypeToEnglish($arabicType)
    {
        if (empty($arabicType)) {
            return null;
        }

        // تطبيع النص للمقارنة
        $normalized = $this->normalizeArabicText($arabicType);

        // خريطة التحويل من العربية إلى الإنجليزية
        $typeMapping = [
            // فرد عائلة => family_member (re_people)
            'فرد عايله' => 'family_member',
            'فرد عائله' => 'family_member',
            'فرد عائلة' => 'family_member',
            'فرد اسره' => 'family_member',
            'فرد اسرة' => 'family_member',
            'فرد أسرة' => 'family_member',
            'فرد الع ائله' => 'family_member',

            // معيل => breadwinner (data)
            'معيل' => 'breadwinner',
            'معيل اسره' => 'breadwinner',
            'معيل اسرة' => 'breadwinner',
            'معيل أسرة' => 'breadwinner',
            'معيل عائله' => 'breadwinner',
            'معيل عائلة' => 'breadwinner',

            // أب متوفي => deceased_father (dead_people)
            'أب متوفي' => 'deceased_father',
            'اب متوفي' => 'deceased_father',
            'الاب المتوفي' => 'deceased_father',
            'الأب المتوفي' => 'deceased_father',

            // أم متوفية => deceased_mother (dead_people)
            'أم متوفيه' => 'deceased_mother',
            'ام متوفيه' => 'deceased_mother',
            'الام المتوفيه' => 'deceased_mother',
            'الأم المتوفية' => 'deceased_mother',
            'أم متوفية' => 'deceased_mother',
            'ام متوفية' => 'deceased_mother',
        ];

        // البحث في الخريطة بالنص الأصلي
        if (isset($typeMapping[$arabicType])) {
            return $typeMapping[$arabicType];
        }

        // البحث في الخريطة بالنص المطبّع
        foreach ($typeMapping as $arabic => $english) {
            if ($this->normalizeArabicText($arabic) === $normalized) {
                return $english;
            }
        }

        // إذا لم يتم العثور على تطابق، نسجل تحذير ونرجع القيمة الأصلية
        Log::warning('⚠️ نوع شخص غير معروف - لم يتم تحويله', [
            'original' => $arabicType,
            'normalized' => $normalized
        ]);

        return $arabicType; // إرجاع القيمة الأصلية إذا لم يتم التعرف عليها
    }

    /**
     * بحث ذكي في حقل نصي مع دعم normalization
     * يستخدم في البحث عن الأسماء، المؤسسات، البنوك، إلخ
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $column اسم العمود
     * @param string $searchValue القيمة المراد البحث عنها
     * @param bool $exactMatch هل البحث دقيق أم جزئي (default: false = جزئي)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function addSmartSearch($query, $column, $searchValue, $exactMatch = false)
    {
        $normalized = $this->normalizeArabicText($searchValue);

        return $query->where(function($q) use ($column, $searchValue, $normalized, $exactMatch) {
            // 1. البحث الدقيق أولاً
            $q->where($column, $searchValue);

            if ($exactMatch) {
                // 2. البحث مع normalization فقط
                $q->orWhereRaw(
                    'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(' . $column . ', "أ", "ا"), "إ", "ا"), "آ", "ا"), "ٱ", "ا"), "ة", "ه"), "ى", "ي") = ?',
                    [$normalized]
                );
            } else {
                // 2. البحث الجزئي
                $q->orWhere($column, 'LIKE', "%{$searchValue}%");

                // 3. البحث الجزئي مع normalization
                $q->orWhereRaw(
                    'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(' . $column . ', "أ", "ا"), "إ", "ا"), "آ", "ا"), "ٱ", "ا"), "ة", "ه"), "ى", "ي") LIKE ?',
                    ["%{$normalized}%"]
                );
            }
        });
    }

    /**
     * تحويل تاريخ Excel إلى تنسيق قاعدة البيانات
     */
    private function convertExcelDate($excelDate)
    {
        if (empty($excelDate)) {
            return null;
        }

        try {
            // إذا كان التاريخ رقمياً (Excel serial date)
            if (is_numeric($excelDate)) {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($excelDate);
                return $date->format('Y-m-d');
            }

            // إذا كان التاريخ نصياً
            $date = \Carbon\Carbon::parse($excelDate);
            return $date->format('Y-m-d');

        } catch (\Exception $e) {
            Log::warning('تحذير: تعذر تحويل التاريخ', [
                'date' => $excelDate,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 🆕 البحث عن المعيل في السجل المدني (civilregistry)
     * يستخدم للتحقق من وجود الشخص وجلب بياناته الكاملة
     *
     * @param string $identityNumber رقم الهوية
     * @return array|null البيانات الكاملة أو null إذا لم يُعثر عليه
     */
    private function searchCivilRegistryForGuardian($identityNumber)
    {
        if (empty($identityNumber)) {
            return null;
        }

        try {
            $person = DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $identityNumber)
                ->first();

            if ($person) {
                // تجميع الاسم الكامل من الأعمدة الأربعة
                $fullName = trim(
                    ($person->CI_FIRST_ARB ?? '') . ' ' .
                    ($person->CI_FATHER_ARB ?? '') . ' ' .
                    ($person->CI_GRAND_FATHER_ARB ?? '') . ' ' .
                    ($person->CI_FAMILY_ARB ?? '')
                );

                // تحويل تاريخ الميلاد إلى صيغة Y-m-d
                $birthDate = null;
                if (!empty($person->CI_BIRTH_DT)) {
                    try {
                        $birthDate = date('Y-m-d', strtotime($person->CI_BIRTH_DT));
                    } catch (\Exception $e) {
                        $birthDate = null;
                    }
                }

                Log::info('🔍 تم العثور على الشخص في السجل المدني', [
                    'identity_number' => $identityNumber,
                    'full_name' => $fullName,
                    'birth_date' => $birthDate
                ]);

                return [
                    'identity_number' => $person->CI_ID_NUM,
                    'first_name' => $person->CI_FIRST_ARB ?? '',
                    'father_name' => $person->CI_FATHER_ARB ?? '',
                    'grand_father_name' => $person->CI_GRAND_FATHER_ARB ?? '',
                    'family_name' => $person->CI_FAMILY_ARB ?? '',
                    'full_name' => $fullName,
                    'birth_date' => $birthDate,
                    'gender' => $person->CI_SEX_CD ?? null,
                    'city' => !empty($person->CITY) ? $person->CITY : null, // 🔧 تحويل القيمة الفارغة إلى null
                    'found_in' => 'civil_registry'
                ];
            }

            Log::info('⚠️ لم يُعثر على الشخص في السجل المدني', [
                'identity_number' => $identityNumber
            ]);

            return null;

        } catch (\Exception $e) {
            Log::warning('⚠️ فشل البحث في السجل المدني', [
                'identity_number' => $identityNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 🆕 إنشاء معيل جديد في جدول data من بيانات السجل المدني أو من تقسيم الاسم
     * يتم حجز رقم ملف جديد باستخدام خوارزمية generateUniqueReservedCode
     *
     * @param array $guardianData بيانات المعيل (من السجل المدني أو من Excel مع تقسيم الاسم)
     * @return \App\Models\Data|null سجل المعيل المُنشأ أو null في حالة الفشل
     */
    private function createGuardianFromCivilRegistry(array $guardianData)
    {
        // التحقق من توفر البيانات (إما من السجل المدني أو من تقسيم الاسم)
        $hasCivilData = !empty($guardianData['civil_registry']);
        $hasSegmentedName = !empty($guardianData['segmented_name']);

        if (!$hasCivilData && !$hasSegmentedName) {
            Log::warning('⚠️ بيانات ناقصة لإنشاء المعيل', [
                'guardian_data' => $guardianData
            ]);
            return null;
        }

        if (empty($guardianData['identity'])) {
            Log::warning('⚠️ رقم الهوية مفقود لإنشاء المعيل', [
                'guardian_data' => $guardianData
            ]);
            return null;
        }

        try {
            // التحقق مرة أخرى من عدم وجود المعيل في جدول data
            $existingGuardian = Data::where('data_id_number', $guardianData['identity'])->first();
            if ($existingGuardian) {
                Log::info('✅ المعيل موجود مسبقاً في جدول data', [
                    'identity' => $guardianData['identity'],
                    'file_id_number' => $existingGuardian->file_id_number
                ]);
                return $existingGuardian;
            }

            // حجز رقم ملف جديد باستخدام الخوارزمية المركزية
            $fileIdNumber = generateUniqueReservedCode('data', 'file_id_number');
            if (!$fileIdNumber) {
                Log::error('❌ فشل حجز رقم ملف جديد للمعيل', [
                    'identity' => $guardianData['identity']
                ]);
                return null;
            }

            // إنشاء سجل المعيل في جدول data
            $newGuardian = new Data();
            $newGuardian->file_id_number = $fileIdNumber;
            $newGuardian->data_id_number = $guardianData['identity'];

            // تحديد مصدر البيانات للاسم
            if ($hasCivilData) {
                // 📋 من السجل المدني
                $civilData = $guardianData['civil_registry'];
                $newGuardian->data_first_name = $civilData['first_name'] ?? '';
                $newGuardian->data_father_name = $civilData['father_name'] ?? '';
                $newGuardian->data_grand_father_name = $civilData['grand_father_name'] ?? '';
                $newGuardian->data_family_name = $civilData['family_name'] ?? '';
                $newGuardian->data_birth_date = $civilData['birth_date'] ?? null;
                // 🔧 data_city هو unsignedBigInteger - يجب أن يكون null وليس '' (نص فارغ)
                $cityValue = $civilData['city'] ?? null;
                $newGuardian->data_city = !empty($cityValue) && is_numeric($cityValue) ? (int) $cityValue : null;

                // تحديد الجنس (1 = ذكر، 2 = أنثى)
                if (!empty($civilData['gender'])) {
                    $newGuardian->data_gender = (int) $civilData['gender'];
                }

                $source = 'civil_registry';
                $fullName = $civilData['full_name'] ?? '';
            } else {
                // 📛 من تقسيم الاسم (NameSegmentation)
                $segmented = $guardianData['segmented_name'];
                $newGuardian->data_first_name = $segmented['first_name'] ?? '';
                $newGuardian->data_father_name = $segmented['father_name'] ?? '';
                $newGuardian->data_grand_father_name = $segmented['grand_father_name'] ?? '';
                $newGuardian->data_family_name = $segmented['family_name'] ?? '';

                $source = 'excel_segmentation';
                $fullName = $segmented['full_name'] ?? $guardianData['name'] ?? '';

                Log::info('📛 استخدام الاسم المقسم من Excel للمعيل', [
                    'identity' => $guardianData['identity'],
                    'original_name' => $guardianData['name'] ?? '',
                    'segmented' => [
                        'first_name' => $segmented['first_name'],
                        'father_name' => $segmented['father_name'],
                        'grand_father_name' => $segmented['grand_father_name'],
                        'family_name' => $segmented['family_name']
                    ]
                ]);
            }

            // ✅ تحويل أرقام الهاتف - bigint لا يقبل نص فارغ
            $phone = $guardianData['phone'] ?? '';
            $altPhone = $guardianData['alt_phone'] ?? '';
            $newGuardian->data_phone_number = !empty($phone) && is_numeric($phone) ? (int) $phone : null;
            $newGuardian->data_alt_phone_number = !empty($altPhone) && is_numeric($altPhone) ? (int) $altPhone : null;

            // تعيين قيم افتراضية (أرقام وليس نصوص)
            $newGuardian->data_request_status = 4; // 4 = مقبول على الاستضافة
            // data_relationship يُترك null لأنه foreign key ويحتاج قيمة صحيحة من جدول category_of_relations

            $newGuardian->save();

            // تحديث جدول reserved_codes لتمييز الرقم كمستخدم
            if (function_exists('markCodeAsUsed')) {
                markCodeAsUsed($fileIdNumber, auth()->id(), 'guardian_created_from_civil_registry');
            }

            Log::info('✅ تم إنشاء المعيل بنجاح', [
                'identity' => $guardianData['identity'],
                'file_id_number' => $fileIdNumber,
                'name' => $fullName,
                'source' => $source
            ]);

            return $newGuardian;

        } catch (\Exception $e) {
            Log::error('❌ فشل إنشاء المعيل', [
                'identity' => $guardianData['identity'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 🆕 إنشاء مكفول جديد (فرد عائلة) في جدول re_people من بيانات السجل المدني أو تقسيم الاسم
     * يتم ربطه بالمعيل من خلال registration_id
     *
     * ⚠️ ملاحظة: إذا كان skip_re_people = true، لا يتم إنشاء سجل في re_people
     *
     * @param array $sponsoredData بيانات المكفول (من السجل المدني أو من Excel مع تقسيم الاسم)
     * @param string|null $guardianFileId رقم ملف المعيل للربط
     * @return \App\Models\RePeople|null سجل المكفول المُنشأ أو null في حالة الفشل أو التخطي
     */
    private function createSponsoredFromCivilRegistry(array $sponsoredData, ?string $guardianFileId = null)
    {
        // 🚫 التحقق من علامة تخطي إنشاء re_people
        if (!empty($sponsoredData['skip_re_people'])) {
            Log::info('🚫 تم تخطي إنشاء سجل في re_people (المكفول غير موجود في السجل المدني)', [
                'identity' => $sponsoredData['identity'] ?? 'unknown',
                'name' => $sponsoredData['name'] ?? '',
                'note' => 'سيتم إدخاله في sponsorships فقط'
            ]);
            return null;
        }

        // التحقق من توفر البيانات (إما من السجل المدني أو من تقسيم الاسم)
        $hasCivilData = !empty($sponsoredData['civil_registry']);
        $hasSegmentedName = !empty($sponsoredData['segmented_name']);

        if (!$hasCivilData && !$hasSegmentedName) {
            Log::warning('⚠️ بيانات ناقصة لإنشاء المكفول', [
                'sponsored_data' => $sponsoredData
            ]);
            return null;
        }

        if (empty($sponsoredData['identity'])) {
            Log::warning('⚠️ رقم الهوية مفقود لإنشاء المكفول', [
                'sponsored_data' => $sponsoredData
            ]);
            return null;
        }

        try {
            // التحقق مرة أخرى من عدم وجود المكفول في جدول re_people
            $existingSponsored = RePeople::where('person_id', $sponsoredData['identity'])->first();
            if ($existingSponsored) {
                Log::info('✅ المكفول موجود مسبقاً في جدول re_people', [
                    'identity' => $sponsoredData['identity'],
                    'registration_id' => $existingSponsored->registration_id
                ]);
                return $existingSponsored;
            }

            // إنشاء سجل المكفول في جدول re_people
            $newSponsored = new RePeople();

            // ربط المكفول بالمعيل (إذا كان متوفراً)
            $newSponsored->registration_id = $guardianFileId;

            // بيانات الهوية
            $newSponsored->person_id = $sponsoredData['identity'];

            // تحديد مصدر البيانات للاسم
            if ($hasCivilData) {
                // 📋 من السجل المدني
                $civilData = $sponsoredData['civil_registry'];
                $newSponsored->first_name = $civilData['first_name'] ?? '';
                $newSponsored->second_name = $civilData['father_name'] ?? '';
                $newSponsored->third_name = $civilData['grand_father_name'] ?? '';
                $newSponsored->last_name = $civilData['family_name'] ?? '';
                $newSponsored->person_birth_date = $civilData['birth_date'] ?? null;

                // حساب العمر إذا كان تاريخ الميلاد متوفراً
                if (!empty($civilData['birth_date'])) {
                    try {
                        $birthDate = new \DateTime($civilData['birth_date']);
                        $now = new \DateTime();
                        $age = $now->diff($birthDate)->y;
                        $newSponsored->person_age = $age;
                    } catch (\Exception $e) {
                        $newSponsored->person_age = null;
                    }
                }

                // تحديد الجنس (1 = ذكر، 2 = أنثى)
                if (!empty($civilData['gender'])) {
                    $newSponsored->person_gender = (int) $civilData['gender'];
                }

                $source = 'civil_registry';
                $fullName = $civilData['full_name'] ?? '';
            } else {
                // 📛 من تقسيم الاسم (NameSegmentation)
                $segmented = $sponsoredData['segmented_name'];
                $newSponsored->first_name = $segmented['first_name'] ?? '';
                $newSponsored->second_name = $segmented['father_name'] ?? '';
                $newSponsored->third_name = $segmented['grand_father_name'] ?? '';
                $newSponsored->last_name = $segmented['family_name'] ?? '';

                $source = 'excel_segmentation';
                $fullName = $segmented['full_name'] ?? $sponsoredData['name'] ?? '';

                Log::info('📛 استخدام الاسم المقسم من Excel للمكفول', [
                    'identity' => $sponsoredData['identity'],
                    'original_name' => $sponsoredData['name'] ?? '',
                    'segmented' => [
                        'first_name' => $segmented['first_name'],
                        'second_name' => $segmented['father_name'],
                        'third_name' => $segmented['grand_father_name'],
                        'last_name' => $segmented['family_name']
                    ]
                ]);
            }

            // تعيين قيم افتراضية (أرقام وليس نصوص)
            $newSponsored->sponsorship_status = 4; // 4 = جديد (من جدول sponsorship_statuses)
            $newSponsored->person_type_of_guarantee = null; // نوع الكفالة - يُترك null لأن العمود int ولا نعرف القيمة الصحيحة

            $newSponsored->save();

            Log::info('✅ تم إنشاء المكفول (فرد عائلة) بنجاح', [
                'identity' => $sponsoredData['identity'],
                'registration_id' => $guardianFileId,
                'name' => $fullName,
                'birth_date' => $newSponsored->person_birth_date ?? null, // 🆕 تاريخ الميلاد
                'age' => $newSponsored->person_age ?? null, // 🆕 العمر المحسوب
                'source' => $source
            ]);

            return $newSponsored;

        } catch (\Exception $e) {
            Log::error('❌ فشل إنشاء المكفول', [
                'identity' => $sponsoredData['identity'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 🆕 إنشاء متوفي جديد (أب/أم) في جدول dead_people من بيانات السجل المدني أو تقسيم الاسم
     * يتم حجز رقم ملف جديد باستخدام خوارزمية generateUniqueReservedCode
     *
     * @param array $deceasedData بيانات المتوفي (من السجل المدني أو من Excel مع تقسيم الاسم)
     * @return \App\Models\DeadPepole|null سجل المتوفي المُنشأ أو null في حالة الفشل
     */
    private function createDeceasedFromCivilRegistry(array $deceasedData)
    {
        // التحقق من توفر البيانات (إما من السجل المدني أو من تقسيم الاسم أو من اسم Excel)
        $hasCivilData = !empty($deceasedData['civil_registry']);
        $hasSegmentedName = !empty($deceasedData['segmented_name']) &&
                            isset($deceasedData['segmented_name']['segments_count']) &&
                            $deceasedData['segmented_name']['segments_count'] > 0;
        $hasExcelName = !empty($deceasedData['name']); // 🆕 الاسم من Excel مباشرة

        if (!$hasCivilData && !$hasSegmentedName && !$hasExcelName) {
            Log::warning('⚠️ بيانات ناقصة لإنشاء المتوفي (لا يوجد سجل مدني أو اسم مقسم أو اسم Excel)', [
                'deceased_data' => $deceasedData
            ]);
            return null;
        }

        if (empty($deceasedData['identity'])) {
            Log::warning('⚠️ رقم الهوية مفقود لإنشاء المتوفي', [
                'deceased_data' => $deceasedData
            ]);
            return null;
        }

        $deceasedType = $deceasedData['type'] ?? 'deceased_father'; // افتراضي: أب متوفي
        $isFather = ($deceasedType === 'deceased_father');

        try {
            // التحقق مرة أخرى من عدم وجود المتوفي في جدول dead_people
            if ($isFather) {
                $existingDeceased = DeadPepole::where('father_id', $deceasedData['identity'])->first();
            } else {
                $existingDeceased = DeadPepole::where('mother_id', $deceasedData['identity'])->first();
            }

            if ($existingDeceased) {
                Log::info('✅ المتوفي موجود مسبقاً في جدول dead_people', [
                    'identity' => $deceasedData['identity'],
                    're_file_id' => $existingDeceased->re_file_id
                ]);
                return $existingDeceased;
            }

            // توليد رقم ملف جديد
            $fileIdNumber = generateUniqueReservedCode('dead_people', 're_file_id');
            if (!$fileIdNumber) {
                Log::error('❌ فشل في توليد رقم ملف للمتوفي', [
                    'identity' => $deceasedData['identity']
                ]);
                return null;
            }

            // إنشاء سجل المتوفي في جدول dead_people
            $newDeceased = new DeadPepole();
            $newDeceased->re_file_id = $fileIdNumber;

            // تحديد مصدر البيانات للاسم
            if ($hasCivilData) {
                // 📋 من السجل المدني
                $civilData = $deceasedData['civil_registry'];

                if ($isFather) {
                    $newDeceased->father_id = $deceasedData['identity'];
                    $newDeceased->father_first_name = $civilData['first_name'] ?? '';
                    $newDeceased->father_second_name = $civilData['father_name'] ?? '';
                    $newDeceased->father_third_name = $civilData['grand_father_name'] ?? '';
                    $newDeceased->father_last_name = $civilData['family_name'] ?? '';
                    // ℹ️ father_birth_date غير موجود في جدول dead_people
                } else {
                    $newDeceased->mother_id = $deceasedData['identity'];
                    $newDeceased->mother_first_name = $civilData['first_name'] ?? '';
                    $newDeceased->mother_second_name = $civilData['father_name'] ?? '';
                    $newDeceased->mother_third_name = $civilData['grand_father_name'] ?? '';
                    $newDeceased->mother_last_name = $civilData['family_name'] ?? '';
                    // ℹ️ mother_birth_date غير موجود في جدول dead_people
                }

                $source = 'civil_registry';
                $fullName = $civilData['full_name'] ?? '';
            } else {
                // 📛 من تقسيم الاسم (NameSegmentation) أو من اسم Excel مباشرة
                $segmented = $deceasedData['segmented_name'] ?? null;
                $hasValidSegments = $segmented && isset($segmented['segments_count']) && $segmented['segments_count'] > 0;

                // 🆕 إذا لم يتوفر تقسيم صحيح، نستخدم الاسم الكامل من Excel
                $firstName = '';
                $secondName = '';
                $thirdName = '';
                $familyName = '';

                if ($hasValidSegments) {
                    $firstName = $segmented['first_name'] ?? '';
                    $secondName = $segmented['father_name'] ?? '';
                    $thirdName = $segmented['grand_father_name'] ?? '';
                    $familyName = $segmented['family_name'] ?? '';
                } else {
                    // 🆕 استخدام الاسم الكامل كاسم أول (مؤقتاً)
                    $firstName = $deceasedData['name'] ?? '';
                    Log::info('⚠️ لا يوجد تقسيم صحيح للاسم - استخدام الاسم الكامل كاسم أول', [
                        'identity' => $deceasedData['identity'],
                        'full_name' => $firstName
                    ]);
                }

                if ($isFather) {
                    $newDeceased->father_id = $deceasedData['identity'];
                    $newDeceased->father_first_name = $firstName;
                    $newDeceased->father_second_name = $secondName;
                    $newDeceased->father_third_name = $thirdName;
                    $newDeceased->father_last_name = $familyName;
                } else {
                    $newDeceased->mother_id = $deceasedData['identity'];
                    $newDeceased->mother_first_name = $firstName;
                    $newDeceased->mother_second_name = $secondName;
                    $newDeceased->mother_third_name = $thirdName;
                    $newDeceased->mother_last_name = $familyName;
                }

                $source = 'excel_segmentation';
                $fullName = ($hasValidSegments && isset($segmented['full_name'])) ? $segmented['full_name'] : ($deceasedData['name'] ?? '');

                Log::info('📛 استخدام الاسم من Excel للمتوفي', [
                    'identity' => $deceasedData['identity'],
                    'type' => $deceasedType,
                    'original_name' => $deceasedData['name'] ?? '',
                    'segmented' => $segmented,
                    'has_valid_segments' => $hasValidSegments,
                    'used_first_name' => $firstName,
                    'used_second_name' => $secondName,
                    'used_family_name' => $familyName
                ]);
            }

            $newDeceased->save();

            // تحديث جدول reserved_codes لتمييز الرقم كمستخدم
            if (function_exists('markCodeAsUsed')) {
                markCodeAsUsed($fileIdNumber, auth()->id(), $deceasedType . '_created_from_civil_registry');
            }

            Log::info('✅ تم إنشاء المتوفي بنجاح', [
                'identity' => $deceasedData['identity'],
                're_file_id' => $fileIdNumber,
                'type' => $deceasedType,
                'name' => $fullName,
                'source' => $source
            ]);

            return $newDeceased;

        } catch (\Exception $e) {
            Log::error('❌ فشل إنشاء المتوفي', [
                'identity' => $deceasedData['identity'] ?? 'unknown',
                'type' => $deceasedType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 🆕 جلب رقم هاتف المعيل من جدول data أو portal_general_registration_field_values
     *
     * @param string|null $guardianIdentityNumber رقم هوية المعيل
     * @return string|null رقم الهاتف (الأساسي أو الثانوي)
     */
    private function getGuardianPhone($guardianIdentityNumber)
    {
        if (empty($guardianIdentityNumber)) {
            return null;
        }

        try {
            // 🔍 البحث في جدول data باستخدام رقم هوية المعيل
            $guardianData = Data::where('data_id_number', $guardianIdentityNumber)->first();

            if ($guardianData) {
                // ✅ الأولوية للرقم الأساسي (data_phone_number)
                if (!empty($guardianData->data_phone_number)) {
                    Log::info('📞 تم جلب رقم الهاتف الأساسي من جدول data', [
                        'guardian_identity' => $guardianIdentityNumber,
                        'phone' => $guardianData->data_phone_number
                    ]);
                    return $guardianData->data_phone_number;
                }

                // ⚠️ إذا لم يكن موجود، استخدام الرقم الثانوي (data_alt_phone_number)
                if (!empty($guardianData->data_alt_phone_number)) {
                    Log::info('📞 تم جلب رقم الهاتف الثانوي من جدول data', [
                        'guardian_identity' => $guardianIdentityNumber,
                        'alt_phone' => $guardianData->data_alt_phone_number
                    ]);
                    return $guardianData->data_alt_phone_number;
                }
            }

            // 🔍 البحث في جدول portal_general_registration_field_values
            $phoneField = DB::table('portal_general_registration_field_values')
                ->where('identity_number', $guardianIdentityNumber)
                ->where(function($query) {
                    $query->where('field_key', 'phone')
                        ->orWhere('field_key', 'data_phone_number')
                        ->orWhere('field_key', 'primary_phone')
                        ->orWhere('field_key', 'guardian_phone');
                })
                ->whereNotNull('field_value')
                ->where('field_value', '!=', '')
                ->first();

            if ($phoneField && !empty($phoneField->field_value)) {
                Log::info('📞 تم جلب رقم الهاتف من جدول portal_general_registration_field_values', [
                    'guardian_identity' => $guardianIdentityNumber,
                    'field_key' => $phoneField->field_key,
                    'phone' => $phoneField->field_value
                ]);
                return $phoneField->field_value;
            }

            // البحث عن الرقم الثانوي في portal_general_registration_field_values
            $altPhoneField = DB::table('portal_general_registration_field_values')
                ->where('identity_number', $guardianIdentityNumber)
                ->where(function($query) {
                    $query->where('field_key', 'alt_phone')
                        ->orWhere('field_key', 'data_alt_phone_number')
                        ->orWhere('field_key', 'secondary_phone')
                        ->orWhere('field_key', 'guardian_alt_phone');
                })
                ->whereNotNull('field_value')
                ->where('field_value', '!=', '')
                ->first();

            if ($altPhoneField && !empty($altPhoneField->field_value)) {
                Log::info('📞 تم جلب رقم الهاتف الثانوي من جدول portal_general_registration_field_values', [
                    'guardian_identity' => $guardianIdentityNumber,
                    'field_key' => $altPhoneField->field_key,
                    'alt_phone' => $altPhoneField->field_value
                ]);
                return $altPhoneField->field_value;
            }

            Log::warning('⚠️ لم يتم العثور على رقم هاتف للمعيل', [
                'guardian_identity' => $guardianIdentityNumber
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('❌ خطأ في جلب رقم هاتف المعيل', [
                'guardian_identity' => $guardianIdentityNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
