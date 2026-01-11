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

                // التحقق من نجاح جلب البيانات
                if ($guardianIdentity && $guardianFileId) {
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

            // 1. محاولة البحث باستخدام guardian_identity_number
            if (!empty($sponsorship->guardian_identity_number)) {
                $guardianData = Data::where('data_id_number', $sponsorship->guardian_identity_number)->first();
                if ($guardianData) {
                    $guardianFileId = $guardianData->file_id_number;
                }
            }

            // 2. إذا لم نجد، نستخدم relation_id_number مباشرة
            if (!$guardianFileId && !empty($sponsorship->relation_id_number)) {
                $guardianFileId = $sponsorship->relation_id_number;
                // جلب بيانات المعيل من data باستخدام file_id_number
                if (!$guardianData) {
                    $guardianData = Data::where('file_id_number', $guardianFileId)->first();
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

            // إزالة sponsor_ids من البيانات لأنه سيتم معالجته بشكل منفصل
            $sponsorIds = $validatedData['sponsor_ids'] ?? [];
            unset($validatedData['sponsor_ids']);

            // ✅ تحديث sponsor_id في الحقل المباشر (أول جمعية في القائمة)
            if (!empty($sponsorIds)) {
                $validatedData['sponsor_id'] = is_array($sponsorIds) ? $sponsorIds[0] : $sponsorIds;
            } elseif (isset($validatedData['sponsor_id'])) {
                // في حالة إرسال sponsor_id مباشرة (من المودال)
                // نبقيه كما هو
            } else {
                // إذا تم حذف جميع الجمعيات، نحذف sponsor_id أيضاً
                $validatedData['sponsor_id'] = null;
            }

            $sponsorship->update($validatedData);

            // ✍️ إضافة المستخدم الحالي إلى قائمة المعدلين
            $sponsorship->addUpdater(auth()->id());

            // تحديث المؤسسات الكافلة
            if (!empty($sponsorIds)) {
                $sponsorship->sponsors()->sync($sponsorIds);
            } else {
                $sponsorship->sponsors()->detach();
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

                // التحقق من نجاح جلب البيانات
                if ($guardianIdentity && $guardianFileId) {
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

            // بناء الاستعلام مع الفلاتر
            $query = Sponsorship::with([
                'sponsor',
                'sponsors',
                'sponsorshipType',
                'sponsorshipStatus',
                'creator'
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

            $sponsorships = $query->orderBy('id', 'desc')->get();

            Log::info('✅ تم جلب البيانات للتصدير', [
                'count' => $sponsorships->count(),
                'export_type' => $exportType
            ]);

            // إنشاء ملف Excel باستخدام PhpSpreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // تحديد الرؤوس والبيانات حسب نوع التصدير
            if ($exportType === 'login') {
                // تصدير بيانات تسجيل الدخول
                $headers = [
                    'المؤسسة',
                    'اسم المكفول',
                    'اسم المستخدم',  // رقم الهوية
                    'كلمة المرور',    // رقم الملف (خارجي أو داخلي)
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
                $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

                // كتابة البيانات
                $row = 2;
                foreach ($sponsorships as $sponsorship) {
                    $sponsorNames = $sponsorship->sponsors->pluck('sponsor_name')->implode(' + ');

                    // 🔐 استخدام الرقم الداخلي (internal_file_number) ككلمة مرور
                    $fileNumber = $sponsorship->internal_file_number ?: $sponsorship->external_file_number;

                    $data = [
                        $sponsorNames ?: '-',
                        $sponsorship->orphan_name ?: '-',
                        $sponsorship->identity_number ?: '-',  // اسم المستخدم
                        $fileNumber ?: '-',                    // كلمة المرور (الرقم الداخلي)
                    ];

                    $sheet->fromArray($data, NULL, 'A' . $row);
                    $row++;
                }

                // ضبط عرض الأعمدة تلقائياً
                foreach (range('A', 'D') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = 'sponsorships_login_' . date('Y-m-d_His') . '.xlsx';

            } else {
                // تصدير كامل البيانات (الطريقة القديمة)
                $headers = [
                    '#',
                    'المؤسسة الكافلة',
                    'رقم ملف داخلي',
                    'رقم ملف خارجي',
                    'رقم هوية ولي الأمر',
                    'رقم هوية اليتيم',
                    'اسم اليتيم',
                    'اسم ولي الأمر',
                    'المؤسسة الراعية',
                    'تاريخ بدء الكفالة',
                    'تاريخ نهاية الكفالة',
                    'مدة الكفالة (أشهر)',
                    'نوع الكفالة',
                    'حالة الكفالة',
                    'المبلغ الشهري',
                    'ملاحظات',
                    'تم الإنشاء بواسطة',
                    'تاريخ الإنشاء',
                ];

                // كتابة رؤوس الأعمدة
                $sheet->fromArray($headers, NULL, 'A1');            // تنسيق رؤوس الأعمدة
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
            $sheet->getStyle('A1:R1')->applyFromArray($headerStyle);

            // كتابة البيانات
            $row = 2;
            foreach ($sponsorships as $sponsorship) {
                $sponsorNames = $sponsorship->sponsors->pluck('sponsor_name')->implode(' + ');

                $data = [
                    $sponsorship->id,
                    $sponsorNames ?: '-',
                    $sponsorship->internal_file_number ?: '-',
                    $sponsorship->external_file_number ?: '-',
                    $sponsorship->guardian_identity_number ?: '-',
                    $sponsorship->identity_number ?: '-',
                    $sponsorship->orphan_name ?: '-',
                    $sponsorship->guardian_name ?: '-',
                    $sponsorship->sponsoring_organization ?: '-',
                    $sponsorship->sponsorship_start_date ? date('Y-m-d', strtotime($sponsorship->sponsorship_start_date)) : '-',
                    $sponsorship->sponsorship_end_date ? date('Y-m-d', strtotime($sponsorship->sponsorship_end_date)) : '-',
                    $sponsorship->sponsorship_duration_months ?: '-',
                    $sponsorship->sponsorshipType?->description ?: '-',
                    $sponsorship->sponsorshipStatus?->description ?: '-',
                    $sponsorship->monthly_amount ?: '-',
                    $sponsorship->notes ?: '-',
                    $sponsorship->creator?->name ?: '-',
                    $sponsorship->created_at ? $sponsorship->created_at->format('Y-m-d H:i') : '-',
                ];

                $sheet->fromArray($data, NULL, 'A' . $row);
                $row++;
            }

                // ضبط عرض الأعمدة تلقائياً
                foreach (range('A', 'R') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
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

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                // تخطي الصفوف الفارغة
                if (empty(array_filter($row))) {
                    continue;
                }

                // قراءة البيانات بناءً على أسماء الأعمدة
                $personType = trim($row[$columnMap[$requiredColumns['person_type']] ?? -1] ?? '');
                $sponsoredIdentity = trim($row[$columnMap[$requiredColumns['sponsored_identity']] ?? 0] ?? '');
                $sponsoredName = trim($row[$columnMap[$requiredColumns['sponsored_name']] ?? 0] ?? '');
                $guardianIdentity = trim($row[$columnMap[$requiredColumns['guardian_identity_number']] ?? 0] ?? '');
                $guardianName = trim($row[$columnMap[$requiredColumns['guardian_name']] ?? 0] ?? '');
                $bankName = trim($row[$columnMap[$requiredColumns['bank_name']] ?? 0] ?? '');
                $phoneNumber = trim($row[$columnMap[$requiredColumns['data_phone_number']] ?? -1] ?? '');
                $altPhoneNumber = trim($row[$columnMap[$requiredColumns['data_alt_phone_number']] ?? -1] ?? '');

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
                            'name' => $guardianName,
                            'phone' => $phoneNumber,
                            'alt_phone' => $altPhoneNumber,
                            'full_row' => $row
                        ];
                        $uniquePersons[] = $guardianData;
                    } else {
                        // المعيل موجود مسبقاً، نحدّث أرقام الهاتف فقط إذا كانت الحالية أفضل (غير فارغة)
                        if (!empty($phoneNumber) && empty($uniquePersons[$guardianIndex]['phone'])) {
                            $uniquePersons[$guardianIndex]['phone'] = $phoneNumber;
                        }
                        if (!empty($altPhoneNumber) && empty($uniquePersons[$guardianIndex]['alt_phone'])) {
                            $uniquePersons[$guardianIndex]['alt_phone'] = $altPhoneNumber;
                        }
                    }
                }

                // جمع أسماء البنوك الفريدة
                if (!empty($bankName) && !in_array($bankName, $uniqueBanks)) {
                    $uniqueBanks[] = $bankName;
                }
            }

            // التحقق من وجود جميع الأشخاص في النظام حسب نوعهم
            $missingPersons = [];
            $updatedPhones = []; // قائمة الأشخاص الذين تم تحديث أرقامهم

            foreach ($uniquePersons as $personData) {
                $identity = $personData['identity'];
                $type = $personData['type'];
                $found = false;
                $targetTable = '';

                // تصنيف حسب نوع الشخص
                if (in_array($type, ['فرد عايله', 'فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة', 'فرد أسرة', 'فرد الع ائله'])) {
                    // البحث في re_people
                    $exists = RePeople::where('person_id', $identity)->exists();
                    $targetTable = 're_people';
                    $found = $exists;

                } elseif (in_array($type, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة'])) {
                    // البحث في data
                    $exists = Data::where('data_id_number', $identity)->exists();
                    $targetTable = 'data';
                    $found = $exists;

                } elseif (in_array($type, ['أب متوفي', 'اب متوفي', 'الاب المتوفي'])) {
                    // البحث في dead_people عمود father_id
                    $exists = DeadPepole::where('father_id', $identity)->exists();
                    $targetTable = 'dead_people (father)';
                    $found = $exists;

                } elseif (in_array($type, ['أم متوفيه', 'ام متوفيه', 'الام المتوفيه', 'أم متوفية', 'ام متوفية'])) {
                    // البحث في dead_people عمود mother_id
                    $exists = DeadPepole::where('mother_id', $identity)->exists();
                    $targetTable = 'dead_people (mother)';
                    $found = $exists;
                }

                // إذا لم يُعثر على الشخص، أضفه لقائمة المفقودين
                if (!$found && !empty($targetTable)) {
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
                'updated_phones' => count($updatedPhones),
                'missing_details' => $missingPersons,
                'updated_phones_details' => $updatedPhones
            ]);

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

            // إذا كان الطلب للفحص فقط، إرجاع النتائج مع قائمة الأشخاص المفقودين
            if ($request->has('check_only')) {
                Log::info('🔍 CHECK ONLY MODE - Validation Results:', [
                    'missing_persons_count' => count($missingPersons),
                    'missing_banks_count' => count($missingBanks),
                    'updated_phones_count' => count($updatedPhones),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم فحص الملف بنجاح',
                    'validation' => [
                        'total_rows' => count($rows),
                        'missing_persons' => $missingPersons, // قائمة الأشخاص غير الموجودين (سيتم إدخالهم بدون relation_id_number)
                        'missing_banks' => $missingBanks,
                        'updated_phones' => $updatedPhones, // قائمة المعيلين الذين تم تحديث أرقامهم
                        'allow_import_without_relation' => true, // 🆕 إشارة للسماح بالاستيراد بدون relation_id_number
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
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                try {
                    // تخطي الصفوف الفارغة
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // استخراج البيانات من الصف
                    $personType = trim($row[$columnMap[$requiredColumns['person_type']] ?? -1] ?? '');
                    $sponsoredIdentity = trim($row[$columnMap[$requiredColumns['sponsored_identity']] ?? 0] ?? '');
                    $sponsoredName = trim($row[$columnMap[$requiredColumns['sponsored_name']] ?? 0] ?? '');
                    $guardianIdentity = trim($row[$columnMap[$requiredColumns['guardian_identity_number']] ?? 0] ?? '');
                    $guardianName = trim($row[$columnMap[$requiredColumns['guardian_name']] ?? 0] ?? '');
                    $externalFileNumber = trim($row[$columnMap[$requiredColumns['external_file_number']] ?? 0] ?? '');
                    $sponsoringOrganization = trim($row[$columnMap[$requiredColumns['sponsoring_organization']] ?? 0] ?? '');
                    $phoneNumber = trim($row[$columnMap[$requiredColumns['data_phone_number']] ?? -1] ?? '');
                    $altPhoneNumber = trim($row[$columnMap[$requiredColumns['data_alt_phone_number']] ?? -1] ?? '');
                    $bankName = trim($row[$columnMap[$requiredColumns['bank_name']] ?? 0] ?? '');
                    $personOwnerIdentityNumber = trim($row[$columnMap[$requiredColumns['person_owner_identity_number']] ?? 0] ?? '');
                    $reGuardianName = trim($row[$columnMap[$requiredColumns['re_guardian_name']] ?? 0] ?? '');
                    $rePhoneNumber = trim($row[$columnMap[$requiredColumns['re_phone_number']] ?? 0] ?? '');

                    // التحقق من الحقول المطلوبة - تم تعديله للسماح بالإدخال بدون هوية المعيل
                    // سيتم البحث عن relation_id_number باستخدام رقم هوية المكفول أو المعيل
                    if (empty($guardianIdentity) && empty($sponsoredIdentity)) {
                        $errors[] = "الصف {$rowNumber}: يجب توفير هوية المكفول أو هوية المعيل على الأقل";
                        $errorCount++;
                        continue;
                    }

                    // 🆕 البحث الموسع في الجداول الثلاثة (data, dead_people, re_people)
                    // للعثور على relation_id_number
                    $internalFileNumber = null; // رقم الملف للربط الداخلي (relation_id_number)
                    $normalizedPersonType = $this->normalizeArabicText($personType);
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

                    // التحقق من وجود البنك
                    $bankId = null;
                    if (!empty($bankName)) {
                        $bank = BankName::where(function($query) use ($bankName) {
                            $this->addSmartSearch($query, 'description', $bankName, false);
                        })->first();

                        if ($bank) {
                            $bankId = $bank->id;
                        }
                    }

                    // إنشاء سجل الكفالة
                    DB::beginTransaction();

                    // ⚠️ توليد رقم ملف للعرض
                    $displayFileNumber = null;

                    // 🆕 تحسين: توليد رقم ملف للعرض في جميع الحالات
                    if (in_array($normalizedPersonType, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة'])) {
                        // المعيل: استخدام نفس الرقم الموجود إذا تم العثور عليه، وإلا توليد رقم جديد
                        $displayFileNumber = $internalFileNumber ?: generateUniqueReservedCode('data', 'file_id_number');
                        Log::info("📋 المعيل: تحديد رقم الملف للعرض", [
                            'row' => $rowNumber,
                            'file_number' => $displayFileNumber,
                            'was_found' => !empty($internalFileNumber)
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

                    // ⚠️ في حالة المعيل: يتم إدخال اسمه ورقم هويته فقط، وباقي البيانات تترك فارغة
                    $isGuardianType = in_array($normalizedPersonType, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة']);

                    $sponsorship = new Sponsorship();
                    // للمعيل: نخزن اسمه ورقمه فقط في حقول المكفول
                    $sponsorship->identity_number = $sponsoredIdentity ?: null;
                    $sponsorship->orphan_name = $sponsoredName ?: null;
                    // للمعيل: نترك هذه الحقول فارغة لأنه هو نفسه المكفول
                    $sponsorship->guardian_name = $isGuardianType ? null : ($guardianName ?: null);
                    $sponsorship->guardian_identity_number = $isGuardianType ? null : $guardianIdentity;
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

                    // ربط الكفالة بالمؤسسة الكافلة
                    $sponsorship->sponsors()->attach($request->sponsor_id);

                    // إضافة البيانات البنكية - استخدام رقم ملف المعيل الحقيقي (للربط الداخلي) مع التحقق من التكرار
                    if ($bankId) {
                        // 🎯 تحديد رقم هوية المعيل بشكل قاطع (بغض النظر عن نوع الشخص)
                        // في حالة المعيل: يكون نفسه المكفول (sponsoredIdentity)
                        // في الحالات الأخرى: نستخدم guardianIdentity
                        $actualGuardianIdentity = $isGuardianType ? $sponsoredIdentity : $guardianIdentity;

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
                            'guardian_registration' => $internalFileNumber,
                            'person_owner_identity_number' => $accountOwnerIdentity,
                            're_id_number' => $actualGuardianIdentity,
                            're_phone_number' => $rePhoneNumber ?: $phoneNumber,
                            'bank_name' => $bankId
                        ]);

                        if (!$duplicateCheck['is_duplicate']) {
                            $bankAccount = new GuardianBankAccount();
                            $bankAccount->guardian_registration = $internalFileNumber; // استخدام رقم ملف المعيل من جدول data

                            // ✅ person_owner_identity_number: رقم هوية صاحب الحساب البنكي (من عمود "هوية المحفظة")
                            $bankAccount->person_owner_identity_number = $accountOwnerIdentity;

                            // ✅ re_id_number: رقم هوية المعيل دائماً (بغض النظر عن نوع الشخص)
                            // - إذا كان معيل: نستخدم رقم هويته (sponsoredIdentity)
                            // - إذا كان فرد أسرة/متوفي: نستخدم رقم هوية المعيل (guardianIdentity)
                            $bankAccount->re_id_number = $actualGuardianIdentity;

                            $bankAccount->re_guardian_name = $reGuardianName ?: $guardianName;
                            $bankAccount->bank_name = $bankId;
                            $bankAccount->re_phone_number = $rePhoneNumber ?: $phoneNumber;
                            $bankAccount->save();

                            Log::info('💾 تم حفظ الحساب البنكي', [
                                'row' => $rowNumber,
                                'person_type' => $personType,
                                'guardian_registration' => $internalFileNumber,
                                're_id_number (المعيل)' => $actualGuardianIdentity,
                                'person_owner_identity_number (صاحب المحفظة)' => $accountOwnerIdentity,
                                'bank_name' => $bankId
                            ]);
                        } else {
                            Log::warning('🚫 تم منع إدخال حساب بنكي مكرر - جميع الأعمدة الخمسة متطابقة', [
                                'row' => $rowNumber,
                                'person_type' => $personType,
                                '1_guardian_registration' => $internalFileNumber,
                                '2_person_owner_identity_number' => $accountOwnerIdentity,
                                '3_re_id_number' => $actualGuardianIdentity,
                                '4_re_phone_number' => $rePhoneNumber ?: $phoneNumber,
                                '5_bank_name' => $bankId,
                                'duplicate_message' => $duplicateCheck['message'],
                                'existing_account_id' => $duplicateCheck['existing_account']['id'] ?? null
                            ]);
                        }
                    }

                    DB::commit();
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
                    'linked' => $linkedCount, // 🆕 عدد السجلات المرتبطة
                    'unlinked' => $unlinkedCount, // 🆕 عدد السجلات غير المرتبطة
                ],
                'errors' => $errors,
            ];

            Log::info('✅ اكتملت عملية الاستيراد', [
                'total' => count($rows),
                'success' => $successCount,
                'errors' => $errorCount,
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
}
