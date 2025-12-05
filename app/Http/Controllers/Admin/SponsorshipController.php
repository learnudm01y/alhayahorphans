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
            ]);

            $validatedData['created_by'] = auth()->id();

            // إزالة sponsor_ids من البيانات لأنه سيتم معالجته بشكل منفصل
            $sponsorIds = $validatedData['sponsor_ids'] ?? [];
            unset($validatedData['sponsor_ids']);

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

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الكفالة بنجاح',
                'data' => $sponsorship->load('sponsors')
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

                Log::info('🏦 تم جلب الحسابات البنكية للكفالة', [
                    'sponsorship_id' => $id,
                    'guardian_identity' => $sponsorship->guardian_identity_number,
                    'accounts_count' => count($sponsorship->bank_accounts)
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
            ];

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
}
