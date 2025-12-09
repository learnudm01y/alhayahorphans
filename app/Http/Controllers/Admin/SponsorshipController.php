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
                'guardian_name' => 'nullable|string|max:255',
                'guardian_identity_number' => 'nullable|string|max:50',
                'sponsorship_duration_months' => 'nullable|integer',
                'sponsorship_start_date' => 'nullable|date',
                'sponsorship_end_date' => 'nullable|date',
                'sponsorship_type_id' => 'nullable|exists:type_of_guarantee,id',
                'sponsorship_status_id' => 'nullable|exists:sponsorship_statuses,id',
                'notes' => 'nullable|string',
                'record_id' => 'nullable|string',
                'record_type' => 'nullable|string|in:re_people,dead_people,data',
                'reserved_file_id' => 'nullable|string|max:20',
            ]);

            $validatedData['created_by'] = auth()->id();

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

            // إزالة sponsor_ids من البيانات لأنه سيتم معالجته بشكل منفصل
            $sponsorIds = $validatedData['sponsor_ids'] ?? [];
            unset($validatedData['sponsor_ids']);
            unset($validatedData['record_id']);
            unset($validatedData['record_type']);

            $sponsorship = Sponsorship::create($validatedData);

            // ربط المؤسسات الكافلة إذا تم اختيارها
            if (!empty($sponsorIds)) {
                $sponsorship->sponsors()->sync($sponsorIds);
            }

            // 🏦 حفظ الحسابات البنكية
            if ($request->has('bank_accounts') && !empty($request->bank_accounts)) {
                // محاولة الحصول على رقم هوية المعيل من عدة مصادر
                $guardianIdentity = $validatedData['guardian_identity_number'] ??
                                   $request->input('guardian_identity_number') ??
                                   $sponsorship->guardian_identity_number ??
                                   null;

                // إذا لم نجد رقم هوية المعيل، نحاول استخدام رقم هوية الشخص نفسه (للمعيلين)
                if (!$guardianIdentity && !empty($validatedData['identity_number'])) {
                    $guardianIdentity = $validatedData['identity_number'];
                    Log::info('🔄 استخدام رقم هوية الشخص كرقم هوية المعيل', [
                        'identity_number' => $guardianIdentity
                    ]);
                }

                if ($guardianIdentity) {
                    // 🔍 البحث عن file_id_number من جدول data باستخدام identity_number
                    $guardianFileId = Data::where('data_id_number', $guardianIdentity)
                                         ->value('file_id_number');

                    if (!$guardianFileId) {
                        Log::warning('⚠️ لم يتم العثور على file_id_number للهوية', [
                            'guardian_identity' => $guardianIdentity
                        ]);

                        // محاولة أخيرة: البحث في جدول data باستخدام file_id_number مباشرة
                        $existsInData = Data::where('file_id_number', $guardianIdentity)->exists();
                        if ($existsInData) {
                            $guardianFileId = $guardianIdentity;
                            Log::info('✅ تم العثور على السجل باستخدام file_id_number مباشرة');
                        }
                    }

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
                                $bankAccountData = [
                                    'guardian_registration' => $guardianFileId, // استخدام file_id_number بدلاً من identity_number
                                    'bank_name' => $account['bank_name'] ?? null,
                                    're_guardian_name' => $account['re_guardian_name'] ?? null,
                                    'person_owner_identity_number' => $account['person_owner_identity_number'] ?? null,
                                    're_phone_number' => $account['re_phone_number'] ?? null,
                                    'iban_usd' => $account['iban_usd'] ?? null,
                                    'iban_shekel' => $account['iban_shekel'] ?? null,
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
                        Log::error('❌ فشل العثور على file_id_number في جدول data', [
                            'guardian_identity' => $guardianIdentity,
                            'sponsorship_id' => $sponsorship->id
                        ]);
                    }
                } else {
                    Log::warning('⚠️ لا يوجد رقم هوية للمعيل أو الشخص، لن يتم حفظ الحسابات البنكية', [
                        'request_data' => [
                            'guardian_identity_number' => $request->input('guardian_identity_number'),
                            'identity_number' => $request->input('identity_number')
                        ]
                    ]);
                }
            }

            DB::commit();

            // إعداد الرسالة مع رقم الملف
            $message = 'تم إضافة الكفالة بنجاح';
            $additionalInfo = [];

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

            // 🏦 جلب الحسابات البنكية إذا كان هناك رقم هوية للمعيل
            if (!empty($sponsorship->guardian_identity_number)) {
                $sponsorship->bank_accounts = GuardianBankAccount::where('guardian_registration', $sponsorship->guardian_identity_number)
                    ->get()
                    ->toArray();

                // 📞 جلب معلومات المعيل من جدول data (بما في ذلك أرقام الهاتف)
                $guardianData = Data::where('data_id_number', $sponsorship->guardian_identity_number)->first();
                if ($guardianData) {
                    $sponsorship->guardian_phone = $guardianData->data_phone_number;
                    $sponsorship->guardian_alt_phone = $guardianData->data_alt_phone_number;
                }

                Log::info('🏦 تم جلب الحسابات البنكية للكفالة', [
                    'sponsorship_id' => $id,
                    'guardian_identity' => $sponsorship->guardian_identity_number,
                    'accounts_count' => count($sponsorship->bank_accounts),
                    'guardian_phone' => $sponsorship->guardian_phone ?? null,
                    'guardian_alt_phone' => $sponsorship->guardian_alt_phone ?? null
                ]);
            } else {
                $sponsorship->bank_accounts = [];
            }

            return response()->json($sponsorship);
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
                'guardian_name' => 'nullable|string|max:255',
                'guardian_identity_number' => 'nullable|string|max:50',
                'sponsorship_duration_months' => 'nullable|integer',
                'sponsorship_start_date' => 'nullable|date',
                'sponsorship_end_date' => 'nullable|date',
                'sponsorship_type_id' => 'nullable|exists:type_of_guarantee,id',
                'sponsorship_status_id' => 'nullable|exists:sponsorship_statuses,id',
                'notes' => 'nullable|string',
            ]);

            // إزالة sponsor_ids من البيانات لأنه سيتم معالجته بشكل منفصل
            $sponsorIds = $validatedData['sponsor_ids'] ?? [];
            unset($validatedData['sponsor_ids']);

            $sponsorship->update($validatedData);

            // تحديث المؤسسات الكافلة
            if (!empty($sponsorIds)) {
                $sponsorship->sponsors()->sync($sponsorIds);
            } else {
                $sponsorship->sponsors()->detach();
            }

            // 🏦 تحديث الحسابات البنكية
            if ($request->has('bank_accounts') && !empty($request->bank_accounts)) {
                // محاولة الحصول على رقم هوية المعيل من عدة مصادر
                $guardianIdentity = $validatedData['guardian_identity_number'] ??
                                   $request->input('guardian_identity_number') ??
                                   $sponsorship->guardian_identity_number ??
                                   null;

                // إذا لم نجد رقم هوية المعيل، نحاول استخدام رقم هوية الشخص نفسه
                if (!$guardianIdentity && !empty($validatedData['identity_number'])) {
                    $guardianIdentity = $validatedData['identity_number'];
                    Log::info('🔄 استخدام رقم هوية الشخص كرقم هوية المعيل', [
                        'identity_number' => $guardianIdentity
                    ]);
                }

                if ($guardianIdentity) {
                    // 🔍 البحث عن file_id_number من جدول data باستخدام identity_number
                    $guardianFileId = Data::where('data_id_number', $guardianIdentity)
                                         ->value('file_id_number');

                    if (!$guardianFileId) {
                        Log::warning('⚠️ لم يتم العثور على file_id_number للهوية', [
                            'guardian_identity' => $guardianIdentity
                        ]);

                        // محاولة أخيرة: البحث في جدول data باستخدام file_id_number مباشرة
                        $existsInData = Data::where('file_id_number', $guardianIdentity)->exists();
                        if ($existsInData) {
                            $guardianFileId = $guardianIdentity;
                            Log::info('✅ تم العثور على السجل باستخدام file_id_number مباشرة');
                        }
                    }

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
                                $bankAccountData = [
                                    'guardian_registration' => $guardianFileId, // استخدام file_id_number بدلاً من identity_number
                                    'bank_name' => $account['bank_name'] ?? null,
                                    're_guardian_name' => $account['re_guardian_name'] ?? null,
                                    'person_owner_identity_number' => $account['person_owner_identity_number'] ?? null,
                                    're_phone_number' => $account['re_phone_number'] ?? null,
                                    'iban_usd' => $account['iban_usd'] ?? null,
                                    'iban_shekel' => $account['iban_shekel'] ?? null,
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

                    // حذف الحسابات التي لم تعد موجودة (إذا تم حذفها من النموذج)
                    if (!empty($processedIds)) {
                        $deletedCount = GuardianBankAccount::where('guardian_registration', $guardianIdentity)
                            ->whereNotIn('id', $processedIds)
                            ->delete();

                        if ($deletedCount > 0) {
                            Log::info('🗑️ تم حذف حسابات بنكية قديمة', ['deleted_count' => $deletedCount]);
                        }
                    }
                } else {
                    Log::warning('⚠️ لا يوجد رقم هوية للمعيل أو الشخص، لن يتم تحديث الحسابات البنكية', [
                        'request_data' => [
                            'guardian_identity_number' => $request->input('guardian_identity_number'),
                            'identity_number' => $request->input('identity_number')
                        ]
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الكفالة بنجاح'
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
                'guardian_name' => '',
                'guardian_identity' => '',
                'file_id' => '',
                'new_file_id_generated' => false,
                'reserved_file_id' => null,
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
                    $personData['file_id'] = $record->file_id_number;
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
                    $personData['file_id'] = $record->registration_id;

                    // جلب معلومات المعيل
                    if ($record->dataRecord) {
                        $personData['guardian_name'] = trim("{$record->dataRecord->data_first_name} {$record->dataRecord->data_father_name} {$record->dataRecord->data_grand_father_name} {$record->dataRecord->data_family_name}");
                        $personData['guardian_identity'] = $record->dataRecord->data_id_number;
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
                        } else {
                            $personData['person_type'] = 'deceased_mother';
                            $personData['identity_number'] = $record->mother_id;
                            $personData['full_name'] = trim("{$record->mother_first_name} {$record->mother_second_name} {$record->mother_third_name} {$record->mother_last_name}");
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

                    // تحديد رقم الملف (خارجي أو داخلي)
                    $fileNumber = $sponsorship->external_file_number ?: $sponsorship->internal_file_number;

                    $data = [
                        $sponsorNames ?: '-',
                        $sponsorship->orphan_name ?: '-',
                        $sponsorship->identity_number ?: '-',  // اسم المستخدم
                        $fileNumber ?: '-',                    // كلمة المرور
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

            // 3. التحقق من حالة الكفالة
            if (empty($request->sponsorship_status_id)) {
                $validationErrors[] = 'يجب اختيار حالة الكفالة';
            } else {
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
                if ($found && $targetTable === 'data' && !empty($personData['phone'])) {
                    $guardian = Data::where('data_id_number', $identity)->first();
                    if ($guardian) {
                        $phoneChanged = false;
                        $phoneChanges = [];

                        // مقارنة رقم الهاتف الأساسي
                        if (!empty($personData['phone']) && $guardian->data_phone_number !== $personData['phone']) {
                            $phoneChanges['phone'] = [
                                'old' => $guardian->data_phone_number ?? 'غير موجود',
                                'new' => $personData['phone']
                            ];
                            $guardian->data_phone_number = $personData['phone'];
                            $phoneChanged = true;
                        }

                        // مقارنة رقم الهاتف البديل
                        if (!empty($personData['alt_phone']) && $guardian->data_alt_phone_number !== $personData['alt_phone']) {
                            $phoneChanges['alt_phone'] = [
                                'old' => $guardian->data_alt_phone_number ?? 'غير موجود',
                                'new' => $personData['alt_phone']
                            ];
                            $guardian->data_alt_phone_number = $personData['alt_phone'];
                            $phoneChanged = true;
                        }

                        // حفظ التغييرات
                        if ($phoneChanged) {
                            $guardian->save();
                            Log::info('📞 تحديث أرقام الهاتف للمعيل:', [
                                'guardian_identity' => $identity,
                                'guardian_name' => $personData['name'],
                                'changes' => $phoneChanges
                            ]);

                            // إضافة إلى قائمة المحدثين
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
                        'missing_persons' => $missingPersons, // قائمة الأشخاص المفقودين مع تفاصيلهم
                        'missing_banks' => $missingBanks,
                        'updated_phones' => $updatedPhones, // قائمة المعيلين الذين تم تحديث أرقامهم
                    ]
                ]);
            }

            // إذا كانت هناك بيانات مفقودة، أخبر المستخدم (في حالة الاستيراد المباشر)
            if (!empty($missingPersons)) {
                $preValidationErrors[] = "<strong>أشخاص غير موجودين في النظام (" . count($missingPersons) . "):</strong><br>"
                    . "يجب إنشاء سجلات لهؤلاء الأشخاص أولاً";
            }

            if (!empty($missingBanks)) {
                $preValidationErrors[] = "<strong>بنوك غير موجودة في النظام (" . count($missingBanks) . "):</strong><br>"
                    . implode(', ', $missingBanks);
            }

            // إذا كانت هناك أخطاء في التحقق المسبق، أخبر المستخدم
            if (!empty($preValidationErrors)) {
                $errorMessage = "<div style='text-align: right;'>";
                $errorMessage .= "<p><strong>⚠️ لا يمكن بدء الاستيراد بسبب وجود بيانات مفقودة:</strong></p>";
                $errorMessage .= implode('<br><br>', $preValidationErrors);
                $errorMessage .= "<br><br><p><strong>يرجى القيام بما يلي:</strong></p>";
                $errorMessage .= "<ul style='text-align: right; direction: rtl;'>";

                if (!empty($missingPersons)) {
                    $errorMessage .= "<li>إنشاء سجلات للأشخاص المفقودين أولاً</li>";
                }

                if (!empty($missingBanks)) {
                    $errorMessage .= "<li>إضافة البنوك المفقودة من قسم إدارة البنوك</li>";
                }

                $errorMessage .= "</ul></div>";

                return back()->with('error', $errorMessage);
            }

            // الخطوة 2: بدء عملية الاستيراد الفعلية
            $successCount = 0;
            $errorCount = 0;
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

                    // التحقق من الحقول المطلوبة
                    if (empty($guardianIdentity)) {
                        $errors[] = "الصف {$rowNumber}: هوية المعيل مطلوبة";
                        $errorCount++;
                        continue;
                    }

                    // تحديد رقم الملف بناءً على نوع الشخص المكفول
                    $internalFileNumber = null;
                    $normalizedPersonType = $this->normalizeArabicText($personType);

                    // تحديد رقم الملف حسب نوع الشخص
                    if (in_array($normalizedPersonType, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة'])) {
                        // 🎯 حالة خاصة: المعيل هو نفسه المكفول
                        // البحث عن المعيل في data باستخدام رقم هوية المكفول
                        $guardianRecord = Data::where('data_id_number', $sponsoredIdentity)->first();

                        if ($guardianRecord) {
                            // المعيل موجود - استخدام رقم ملفه
                            $internalFileNumber = $guardianRecord->file_id_number;
                            Log::info("✅ المعيل موجود - استخدام رقم ملفه", [
                                'row' => $rowNumber,
                                'guardian_identity' => $sponsoredIdentity,
                                'file_id' => $internalFileNumber
                            ]);
                        } else {
                            // المعيل غير موجود - إنشاء ملف جديد له
                            $internalFileNumber = generateUniqueReservedCode('data', 'file_id_number');
                            Log::info("➕ المعيل غير موجود - تم توليد رقم ملف جديد", [
                                'row' => $rowNumber,
                                'guardian_identity' => $sponsoredIdentity,
                                'new_file_id' => $internalFileNumber
                            ]);
                        }

                    } else {
                        // الحصول على رقم ملف المعيل من جدول data
                        $guardianFileId = null;
                        $guardianRecord = Data::where('data_id_number', $guardianIdentity)->first();
                        if ($guardianRecord) {
                            $guardianFileId = $guardianRecord->file_id_number;

                            Log::info("🔍 معلومات المعيل", [
                                'row' => $rowNumber,
                                'guardian_identity' => $guardianIdentity,
                                'guardian_file_id' => $guardianFileId
                            ]);
                        } else {
                            Log::warning("⚠️ المعيل غير موجود في جدول data", [
                                'row' => $rowNumber,
                                'guardian_identity' => $guardianIdentity
                            ]);
                        }

                        if (in_array($normalizedPersonType, ['فرد عايله', 'فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة', 'فرد أسرة', 'فرد الع ائله'])) {
                            // فرد عائلة: استخدام رقم ملف المعيل (الربط العائلي)
                            if ($guardianFileId) {
                                $internalFileNumber = $guardianFileId;
                                Log::info("✅ استخدام رقم ملف المعيل لفرد الأسرة", [
                                    'row' => $rowNumber,
                                    'guardian_file_id' => $guardianFileId
                                ]);
                            } else {
                                // المعيل غير موجود - توليد رقم جديد (حالة استثنائية)
                                $internalFileNumber = generateUniqueReservedCode('data', 'file_id_number');
                                Log::warning("⚠️ المعيل غير موجود - تم توليد رقم جديد لفرد الأسرة", [
                                    'row' => $rowNumber,
                                    'new_file_id' => $internalFileNumber
                                ]);
                            }

                        } elseif (in_array($normalizedPersonType, ['أب متوفي', 'اب متوفي', 'الاب المتوفي', 'أم متوفيه', 'ام متوفيه', 'الام المتوفيه', 'أم متوفية', 'ام متوفية'])) {
                            // متوفى: استخدام رقم ملف المعيل (الربط العائلي)
                            if ($guardianFileId) {
                                $internalFileNumber = $guardianFileId;
                                Log::info("✅ استخدام رقم ملف المعيل للمتوفي", [
                                    'row' => $rowNumber,
                                    'guardian_file_id' => $guardianFileId
                                ]);
                            } else {
                                // المعيل غير موجود - توليد رقم جديد (حالة استثنائية)
                                $internalFileNumber = generateUniqueReservedCode('data', 'file_id_number');
                                Log::warning("⚠️ المعيل غير موجود - تم توليد رقم جديد للمتوفي", [
                                    'row' => $rowNumber,
                                    'new_file_id' => $internalFileNumber
                                ]);
                            }
                        }
                    }

                    if (!$internalFileNumber) {
                        $errors[] = "الصف {$rowNumber}: فشل في تحديد رقم الملف - نوع الشخص: '{$personType}' (normalized: '{$normalizedPersonType}')";
                        $errorCount++;

                        Log::error("❌ فشل في تحديد رقم الملف", [
                            'row' => $rowNumber,
                            'person_type' => $personType,
                            'normalized_person_type' => $normalizedPersonType,
                            'guardian_identity' => $guardianIdentity,
                            'guardian_file_id' => $guardianFileId
                        ]);

                        continue;
                    }

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

                    // توليد رقم ملف داخلي جديد (يُعرض للمستخدم)
                    $displayFileNumber = generateUniqueReservedCode('data', 'file_id_number');

                    Log::info("📋 توليد أرقام الملفات", [
                        'row' => $rowNumber,
                        'relation_id' => $internalFileNumber, // الرقم الداخلي للربط (مخفي)
                        'display_file_number' => $displayFileNumber // الرقم المعروض للمستخدم
                    ]);

                    $sponsorship = new Sponsorship();
                    $sponsorship->identity_number = $sponsoredIdentity ?: null;
                    $sponsorship->orphan_name = $sponsoredName ?: null;
                    $sponsorship->guardian_name = $guardianName ?: null;
                    $sponsorship->guardian_identity_number = $guardianIdentity;
                    $sponsorship->relation_id_number = $internalFileNumber; // رقم الربط الداخلي (مخفي)
                    $sponsorship->internal_file_number = $displayFileNumber; // الرقم المعروض للمستخدم
                    $sponsorship->external_file_number = $externalFileNumber ?: null;
                    $sponsorship->sponsoring_organization = $sponsoringOrganization ?: null;
                    $sponsorship->sponsorship_type_id = $request->sponsorship_type_id;
                    $sponsorship->sponsorship_status_id = $request->sponsorship_status_id;
                    $sponsorship->created_by = auth()->id();
                    $sponsorship->save();

                    // ربط الكفالة بالمؤسسة الكافلة
                    $sponsorship->sponsors()->attach($request->sponsor_id);

                    // إضافة البيانات البنكية - استخدام رقم ملف المعيل الحقيقي (للربط الداخلي)
                    if ($bankId) {
                        $bankAccount = new GuardianBankAccount();
                        $bankAccount->guardian_registration = $internalFileNumber; // استخدام رقم ملف المعيل من جدول data
                        $bankAccount->person_owner_identity_number = $personOwnerIdentityNumber ?: $guardianIdentity;
                        $bankAccount->re_id_number = $sponsoredIdentity ?: null;
                        $bankAccount->re_guardian_name = $reGuardianName ?: $guardianName;
                        $bankAccount->bank_name = $bankId;
                        $bankAccount->re_phone_number = $rePhoneNumber ?: $phoneNumber;
                        $bankAccount->save();
                    }

                    DB::commit();
                    $successCount++;

                    Log::info("✅ تم استيراد الصف {$rowNumber} بنجاح", [
                        'person_type' => $personType,
                        'sponsored_identity' => $sponsoredIdentity,
                        'guardian_identity' => $guardianIdentity,
                        'relation_id_number' => $internalFileNumber, // الرقم الداخلي للربط
                        'internal_file_number' => $displayFileNumber // الرقم المعروض
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
                ],
                'errors' => $errors,
            ];

            Log::info('✅ اكتملت عملية الاستيراد', $result['summary']);

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
     * إنشاء الأشخاص المفقودين في قاعدة البيانات
     */
    public function createMissingPersons(Request $request)
    {
        try {
            DB::beginTransaction();

            $persons = $request->input('persons', []);
            $createdPersons = [];
            $errors = [];
            $familyFileIds = []; // لتتبع أرقام الملفات الموحدة للعائلات

            foreach ($persons as $personData) {
                try {
                    $type = $personData['type'];
                    $identity = $personData['identity'];
                    $name = $personData['name'];
                    $guardianIdentity = $personData['guardian_identity'] ?? null;

                    // الحصول على رقم الملف الموحد للعائلة
                    $fileIdNumber = null;

                    // حالة خاصة: إذا كان النوع "معيل"، فهو نفسه المكفول
                    if (in_array($type, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة'])) {
                        // البحث عن المعيل في جدول data باستخدام identity (رقم هوية المعيل)
                        $guardianRecord = Data::where('data_id_number', $identity)->first();
                        if ($guardianRecord) {
                            // المعيل موجود - استخدام رقم ملفه
                            $fileIdNumber = $guardianRecord->file_id_number;
                            Log::info('✅ المعيل موجود - استخدام رقم ملفه', [
                                'identity' => $identity,
                                'file_id_number' => $fileIdNumber
                            ]);
                        } else {
                            // المعيل غير موجود - توليد رقم جديد
                            $fileIdNumber = generateUniqueReservedCode('data', 'file_id_number');
                            Log::info('➕ المعيل غير موجود - تم توليد رقم ملف جديد', [
                                'identity' => $identity,
                                'new_file_id' => $fileIdNumber
                            ]);
                        }
                    } else {
                        // للأنواع الأخرى: البحث عن رقم ملف المعيل في جدول data
                        if ($guardianIdentity) {
                            if (isset($familyFileIds[$guardianIdentity])) {
                                // استخدام رقم الملف المحفوظ مسبقاً
                                $fileIdNumber = $familyFileIds[$guardianIdentity];
                            } else {
                                // البحث عن المعيل في قاعدة البيانات
                                $guardianRecord = Data::where('data_id_number', $guardianIdentity)->first();
                                if ($guardianRecord) {
                                    // استخدام رقم ملف المعيل الموجود
                                    $fileIdNumber = $guardianRecord->file_id_number;
                                    $familyFileIds[$guardianIdentity] = $fileIdNumber;

                                    Log::info('✅ استخدام رقم ملف المعيل الموجود', [
                                        'guardian_identity' => $guardianIdentity,
                                        'file_id_number' => $fileIdNumber
                                    ]);
                                } else {
                                    // المعيل غير موجود - توليد رقم جديد (حالة نادرة)
                                    $fileIdNumber = generateUniqueReservedCode('data', 'file_id_number');
                                    $familyFileIds[$guardianIdentity] = $fileIdNumber;

                                    Log::warning('⚠️ المعيل غير موجود - تم توليد رقم جديد', [
                                        'guardian_identity' => $guardianIdentity,
                                        'new_file_id' => $fileIdNumber
                                    ]);
                                }
                            }
                        } else {
                            // لا يوجد معيل - توليد رقم جديد
                            $fileIdNumber = generateUniqueReservedCode('data', 'file_id_number');
                        }
                    }

                    // تصنيف وإنشاء السجل حسب نوع الشخص
                    if (in_array($type, ['فرد عايله', 'فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة', 'فرد أسرة', 'فرد الع ائله'])) {
                        // إنشاء سجل في re_people
                        $nameParts = explode(' ', $name);
                        RePeople::create([
                            'registration_id' => $fileIdNumber,
                            'first_name' => $nameParts[0] ?? '',
                            'second_name' => $nameParts[1] ?? null,
                            'third_name' => $nameParts[2] ?? null,
                            'last_name' => $nameParts[3] ?? null,
                            'person_id' => $identity,
                        ]);

                        Log::info('✅ تم إنشاء فرد أسرة', [
                            'identity' => $identity,
                            'name' => $name,
                            'file_id' => $fileIdNumber,
                            'guardian_identity' => $guardianIdentity
                        ]);

                        $createdPersons[] = [
                            'type' => 'family_member',
                            'identity' => $identity,
                            'name' => $name,
                            'file_id' => $fileIdNumber
                        ];

                    } elseif (in_array($type, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل أسرة', 'معيل عائله', 'معيل عائلة'])) {
                        // إنشاء سجل في data
                        $nameParts = explode(' ', $name);
                        Data::create([
                            'file_id_number' => $fileIdNumber,
                            'data_id_number' => $identity,
                            'data_first_name' => $nameParts[0] ?? '',
                            'data_father_name' => $nameParts[1] ?? null,
                            'data_grand_father_name' => $nameParts[2] ?? null,
                            'data_family_name' => $nameParts[3] ?? null,
                            'data_section_id' => 1, // قسم افتراضي
                            'data_request_status' => 2,
                        ]);

                        Log::info('✅ تم إنشاء معيل', [
                            'identity' => $identity,
                            'name' => $name,
                            'file_id' => $fileIdNumber
                        ]);

                        $createdPersons[] = [
                            'type' => 'guardian',
                            'identity' => $identity,
                            'name' => $name,
                            'file_id' => $fileIdNumber
                        ];

                    } elseif (in_array($type, ['أب متوفي', 'اب متوفي', 'الاب المتوفي'])) {
                        // إنشاء/تحديث سجل في dead_people
                        $nameParts = explode(' ', $name);
                        $existing = DeadPepole::where('re_file_id', $fileIdNumber)->first();

                        if ($existing) {
                            $existing->update([
                                'father_first_name' => $nameParts[0] ?? '',
                                'father_second_name' => $nameParts[1] ?? null,
                                'father_third_name' => $nameParts[2] ?? null,
                                'father_last_name' => $nameParts[3] ?? null,
                                'father_id' => $identity,
                            ]);

                            Log::info('✅ تم تحديث بيانات أب متوفي', [
                                'identity' => $identity,
                                'file_id' => $fileIdNumber
                            ]);
                        } else {
                            DeadPepole::create([
                                're_file_id' => $fileIdNumber,
                                'father_first_name' => $nameParts[0] ?? '',
                                'father_second_name' => $nameParts[1] ?? null,
                                'father_third_name' => $nameParts[2] ?? null,
                                'father_last_name' => $nameParts[3] ?? null,
                                'father_id' => $identity,
                            ]);

                            Log::info('✅ تم إنشاء بيانات أب متوفي', [
                                'identity' => $identity,
                                'file_id' => $fileIdNumber,
                                'guardian_identity' => $guardianIdentity
                            ]);
                        }

                        $createdPersons[] = [
                            'type' => 'deceased_father',
                            'identity' => $identity,
                            'name' => $name,
                            'file_id' => $fileIdNumber
                        ];

                    } elseif (in_array($type, ['أم متوفيه', 'ام متوفيه', 'الام المتوفيه', 'أم متوفية', 'ام متوفية'])) {
                        // إنشاء/تحديث سجل في dead_people
                        $nameParts = explode(' ', $name);
                        $existing = DeadPepole::where('re_file_id', $fileIdNumber)->first();

                        if ($existing) {
                            $existing->update([
                                'mother_first_name' => $nameParts[0] ?? '',
                                'mother_second_name' => $nameParts[1] ?? null,
                                'mother_third_name' => $nameParts[2] ?? null,
                                'mother_last_name' => $nameParts[3] ?? null,
                                'mother_id' => $identity,
                            ]);

                            Log::info('✅ تم تحديث بيانات أم متوفية', [
                                'identity' => $identity,
                                'file_id' => $fileIdNumber
                            ]);
                        } else {
                            DeadPepole::create([
                                're_file_id' => $fileIdNumber,
                                'mother_first_name' => $nameParts[0] ?? '',
                                'mother_second_name' => $nameParts[1] ?? null,
                                'mother_third_name' => $nameParts[2] ?? null,
                                'mother_last_name' => $nameParts[3] ?? null,
                                'mother_id' => $identity,
                            ]);

                            Log::info('✅ تم إنشاء بيانات أم متوفية', [
                                'identity' => $identity,
                                'file_id' => $fileIdNumber,
                                'guardian_identity' => $guardianIdentity
                            ]);
                        }

                        $createdPersons[] = [
                            'type' => 'deceased_mother',
                            'identity' => $identity,
                            'name' => $name,
                            'file_id' => $fileIdNumber
                        ];
                    }

                } catch (\Exception $e) {
                    $errors[] = "فشل إنشاء {$personData['name']} (هوية: {$personData['identity']}): {$e->getMessage()}";
                    Log::error('خطأ في إنشاء شخص:', [
                        'person' => $personData,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الأشخاص بنجاح',
                'created_count' => count($createdPersons),
                'created_persons' => $createdPersons,
                'errors' => $errors,
                'family_file_ids' => $familyFileIds
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في إنشاء الأشخاص المفقودين:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
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
