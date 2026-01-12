<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * SponsorshipSyncController
 *
 * Controller للمزامنة الصحيحة مع تطبيق الموبايل
 *
 * المنطق:
 * 1. البيانات تأتي من جدول sponsorships فقط
 * 2. لا يتم تنزيل data, re_people, dead_people مباشرة
 * 3. إذا وجد relation_id_number → جلب البيانات المرتبطة
 * 4. إذا لم يوجد → البحث في civilregistry.persons بـ identity_number
 */
class SponsorshipSyncController extends Controller
{
    // ========================================
    // Authentication
    // ========================================

    /**
     * POST /api/mobile/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string'
        ]);

        try {
            $user = User::where('name', $request->username)
                ->orWhere('email', $request->username)
                ->orWhere('phone', $request->username)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'اسم المستخدم غير موجود',
                    'error_code' => 'USER_NOT_FOUND'
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور غير صحيحة',
                    'error_code' => 'INVALID_PASSWORD'
                ], 401);
            }

            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية الدخول. مطلوب صلاحية مدير.',
                    'error_code' => 'INSUFFICIENT_PERMISSIONS'
                ], 403);
            }

            $token = $user->createToken('mobile-app-token', ['*'])->plainTextToken;

            Log::info('Mobile user logged in', ['user_id' => $user->id, 'username' => $user->name]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'token' => $token,
                'expires_at' => now()->addDays(30)->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Mobile login failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تسجيل الدخول'
            ], 500);
        }
    }

    /**
     * POST /api/mobile/logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ'], 500);
        }
    }

    /**
     * POST /api/verify-password
     * التحقق من كلمة مرور المستخدم للعمليات الحساسة (مثل الحذف)
     */
    public function verifyPassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'password' => 'required|string'
            ]);

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'valid' => false,
                    'message' => 'يرجى تسجيل الدخول أولاً'
                ], 401);
            }

            $isValid = Hash::check($request->password, $user->password);

            if ($isValid) {
                Log::info('Password verified successfully', ['user_id' => $user->id]);
                return response()->json([
                    'valid' => true,
                    'message' => 'كلمة المرور صحيحة'
                ]);
            } else {
                Log::warning('Password verification failed', ['user_id' => $user->id]);
                return response()->json([
                    'valid' => false,
                    'message' => 'كلمة المرور غير صحيحة'
                ], 200); // نرجع 200 لكن valid = false
            }

        } catch (\Exception $e) {
            Log::error('Password verification error', ['error' => $e->getMessage()]);
            return response()->json([
                'valid' => false,
                'message' => 'حدث خطأ في التحقق من كلمة المرور'
            ], 500);
        }
    }

    // ========================================
    // Lookup Tables (للفلترة)
    // ========================================

    /**
     * GET /api/mobile/sponsors
     * جلب قائمة الجمعيات/المؤسسات
     */
    public function getSponsors(): JsonResponse
    {
        try {
            $sponsors = DB::table('sponsors')
                ->select('id', 'sponsor_name as name', 'sponsor_short_name as short_name')
                ->whereNotNull('sponsor_name')
                ->orderBy('sponsor_name')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $sponsors->count(),
                'data' => $sponsors
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch sponsors', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل جلب الجمعيات', 'data' => []], 500);
        }
    }

    /**
     * GET /api/mobile/sponsorship-statuses
     * جلب حالات الكفالة
     */
    public function getSponsorshipStatuses(): JsonResponse
    {
        try {
            $statuses = DB::table('sponsorship_statuses')
                ->select('id', 'description as name')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $statuses->count(),
                'data' => $statuses
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch statuses', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل جلب الحالات', 'data' => []], 500);
        }
    }

    // ========================================
    // Main Sync - من جدول sponsorships فقط
    // ========================================

    /**
     * GET /api/mobile/sync/sponsorships
     *
     * جلب الكفالات من جدول sponsorships فقط
     * مع إثراء البيانات من السجل المدني إذا لزم
     * استبعاد الحالات: "تم الصرف" و "أرسل للصرف"
     */
    public function getSponsorships(Request $request): JsonResponse
    {
        try {
            $sponsorId = $request->get('sponsor_id');
            $statusId = $request->get('status_id');
            $search = $request->get('search', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50);
            $lastSync = $request->get('last_sync'); // للمزامنة التزايدية
            $excludeStatuses = $request->get('exclude_statuses', true); // استبعاد الحالات المكتملة

            // بناء الاستعلام
            $query = DB::table('sponsorships')
                ->leftJoin('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
                ->leftJoin('sponsorship_statuses', 'sponsorships.sponsorship_status_id', '=', 'sponsorship_statuses.id')
                ->select([
                    'sponsorships.id',
                    'sponsorships.sponsor_id',
                    'sponsors.sponsor_name',
                    'sponsors.sponsor_short_name',
                    'sponsorships.internal_file_number',
                    'sponsorships.external_file_number',
                    'sponsorships.relation_id_number',
                    'sponsorships.identity_number',
                    'sponsorships.orphan_name',
                    'sponsorships.sponsored_birth_date',
                    'sponsorships.guardian_name',
                    'sponsorships.guardian_identity_number',
                    'sponsorships.sponsorship_status_id',
                    'sponsorship_statuses.description as status_name',
                    'sponsorships.sponsorship_start_date',
                    'sponsorships.sponsorship_end_date',
                    'sponsorships.person_type',
                    'sponsorships.notes',
                    'sponsorships.created_at',
                    'sponsorships.updated_at'
                ]);

            // استبعاد الحالات "تم الصرف" و "أرسل للصرف"
            if ($excludeStatuses) {
                $excludedStatusIds = DB::table('sponsorship_statuses')
                    ->whereIn('description', ['تم الصرف', 'أرسل للصرف'])
                    ->pluck('id')
                    ->toArray();

                if (!empty($excludedStatusIds)) {
                    $query->whereNotIn('sponsorships.sponsorship_status_id', $excludedStatusIds);
                }
            }

            // فلترة بالجمعية (اختيارية للمزامنة الكاملة)
            if ($sponsorId) {
                $query->where('sponsorships.sponsor_id', $sponsorId);
            }

            // فلترة بالحالة (اختيارية)
            if ($statusId !== null && $statusId !== '') {
                $query->where('sponsorships.sponsorship_status_id', $statusId);
            }

            // بحث
            if (!empty($search)) {
                $normalizedSearch = $this->normalizeArabicText($search);
                $query->where(function ($q) use ($search, $normalizedSearch) {
                    $q->where('sponsorships.orphan_name', 'LIKE', "%{$search}%")
                      ->orWhere('sponsorships.identity_number', 'LIKE', "%{$search}%")
                      ->orWhere('sponsorships.internal_file_number', 'LIKE', "%{$search}%")
                      ->orWhere('sponsorships.external_file_number', 'LIKE', "%{$search}%")
                      ->orWhere('sponsorships.guardian_name', 'LIKE', "%{$search}%")
                      ->orWhere('sponsorships.guardian_identity_number', 'LIKE', "%{$search}%");
                });
            }

            // مزامنة تزايدية
            if ($lastSync) {
                $query->where('sponsorships.updated_at', '>', $lastSync);
            }

            // ترتيب وتقسيم
            $total = $query->count();
            $sponsorships = $query->orderBy('sponsorships.updated_at', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            // إثراء البيانات من السجل المدني والحسابات البنكية
            $enrichedData = $sponsorships->map(function ($item) {
                return $this->enrichSponsorshipData($item);
            });

            return response()->json([
                'success' => true,
                'data' => $enrichedData,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ],
                'sync_timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sponsorships', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب بيانات الكفالات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إثراء بيانات الكفالة من السجل المدني والبيانات البنكية
     */
    private function enrichSponsorshipData($sponsorship)
    {
        $result = (array) $sponsorship;
        $result['orphan_data_source'] = 'sponsorships';
        $result['guardian_data_source'] = 'sponsorships';
        $result['needs_orphan_name_input'] = false;
        $result['needs_guardian_name_input'] = false;

        // إضافة نوع الشخص (المعيل) - للتمييز في التطبيق
        $result['guardian_person_type'] = $sponsorship->person_type ?? 'breadwinner';

        // تهيئة القيم الافتراضية للحقول المهمة (الجنس، تاريخ الميلاد، الحالة الصحية، المدينة)
        $result['orphan_gender'] = '';
        $result['health_status_id'] = '';
        $result['guardian_city_id'] = '';

        // تقسيم اسم المكفول إلى أربعة حقول
        $result['orphan_first_name'] = '';
        $result['orphan_father_name'] = '';
        $result['orphan_grandfather_name'] = '';
        $result['orphan_family_name'] = '';

        // إضافة حقول موحدة للتطبيق (first_name, second_name, third_name, last_name)
        $result['first_name'] = '';
        $result['second_name'] = '';
        $result['third_name'] = '';
        $result['last_name'] = '';

        // تقسيم اسم المعيل إلى أربعة حقول
        $result['guardian_first_name'] = '';
        $result['guardian_father_name'] = '';
        $result['guardian_grandfather_name'] = '';
        $result['guardian_family_name'] = '';

        // جلب بيانات المكفول - الأولوية: 1) جداول محلية (re_people, dead_people)، 2) السجل المدني، 3) تقسيم الاسم
        $personType = $sponsorship->person_type ?? 'orphan';
        $orphanDataFound = false;

        // أولاً: البحث في الجداول المحلية حسب نوع الشخص
        if (!empty($sponsorship->identity_number)) {
            // البحث في re_people (للأيتام وأفراد الأسرة)
            // الأعمدة الصحيحة: first_name, second_name, third_name, last_name, person_id, person_birth_date, person_gender, person_health_status
            if (in_array($personType, ['orphan', 'family_member'])) {
                $localPerson = DB::table('re_people')
                    ->where('person_id', $sponsorship->identity_number)
                    ->select(['first_name', 'second_name', 'third_name', 'last_name', 'person_birth_date', 'person_gender', 'person_health_status'])
                    ->first();

                if ($localPerson && !empty($localPerson->first_name)) {
                    $result['orphan_first_name'] = $localPerson->first_name;
                    $result['orphan_father_name'] = $localPerson->second_name ?? '';
                    $result['orphan_grandfather_name'] = $localPerson->third_name ?? '';
                    $result['orphan_family_name'] = $localPerson->last_name ?? '';
                    $result['first_name'] = $localPerson->first_name;
                    $result['second_name'] = $localPerson->second_name ?? '';
                    $result['third_name'] = $localPerson->third_name ?? '';
                    $result['last_name'] = $localPerson->last_name ?? '';
                    $result['sponsored_birth_date'] = $localPerson->person_birth_date ?? $sponsorship->sponsored_birth_date;
                    $result['orphan_gender'] = $this->convertGenderToString($localPerson->person_gender ?? '');
                    $result['health_status_id'] = $localPerson->person_health_status ?? '';
                    $result['orphan_data_source'] = 're_people';
                    $orphanDataFound = true;
                }
            }

            // البحث في dead_people (للمتوفين)
            // الأعمدة الصحيحة: father_first_name, father_second_name, father_third_name, father_last_name
            // و mother_first_name, mother_second_name, mother_third_name, mother_last_name
            if (!$orphanDataFound && in_array($personType, ['deceased_father', 'deceased_mother'])) {
                $deadPerson = DB::table('dead_people')
                    ->where(function($q) use ($sponsorship) {
                        $q->where('father_id', $sponsorship->identity_number)
                          ->orWhere('mother_id', $sponsorship->identity_number);
                    })
                    ->first();

                if ($deadPerson) {
                    if ($personType === 'deceased_father' && !empty($deadPerson->father_first_name)) {
                        $result['orphan_first_name'] = $deadPerson->father_first_name;
                        $result['orphan_father_name'] = $deadPerson->father_second_name ?? '';
                        $result['orphan_grandfather_name'] = $deadPerson->father_third_name ?? '';
                        $result['orphan_family_name'] = $deadPerson->father_last_name ?? '';
                        $result['first_name'] = $deadPerson->father_first_name;
                        $result['second_name'] = $deadPerson->father_second_name ?? '';
                        $result['third_name'] = $deadPerson->father_third_name ?? '';
                        $result['last_name'] = $deadPerson->father_last_name ?? '';
                        $result['orphan_data_source'] = 'dead_people';
                        $orphanDataFound = true;
                    } elseif ($personType === 'deceased_mother' && !empty($deadPerson->mother_first_name)) {
                        $result['orphan_first_name'] = $deadPerson->mother_first_name;
                        $result['orphan_father_name'] = $deadPerson->mother_second_name ?? '';
                        $result['orphan_grandfather_name'] = $deadPerson->mother_third_name ?? '';
                        $result['orphan_family_name'] = $deadPerson->mother_last_name ?? '';
                        $result['first_name'] = $deadPerson->mother_first_name;
                        $result['second_name'] = $deadPerson->mother_second_name ?? '';
                        $result['third_name'] = $deadPerson->mother_third_name ?? '';
                        $result['last_name'] = $deadPerson->mother_last_name ?? '';
                        $result['orphan_data_source'] = 'dead_people';
                        $orphanDataFound = true;
                    }
                }
            }
        }

        // ثانياً: إذا لم يوجد في الجداول المحلية، نبحث في السجل المدني
        if (!$orphanDataFound && !empty($sponsorship->identity_number)) {
            $civilData = $this->getPersonFromCivilRegistry($sponsorship->identity_number);
            if ($civilData) {
                $result['orphan_first_name'] = $civilData['first_name'];
                $result['orphan_father_name'] = $civilData['father_name'];
                $result['orphan_grandfather_name'] = $civilData['grand_father_name'];
                $result['orphan_family_name'] = $civilData['family_name'];
                $result['first_name'] = $civilData['first_name'];
                $result['second_name'] = $civilData['father_name'];
                $result['third_name'] = $civilData['grand_father_name'];
                $result['last_name'] = $civilData['family_name'];
                $result['orphan_name'] = $civilData['full_name'];
                $result['sponsored_birth_date'] = $civilData['birth_date'] ?? $sponsorship->sponsored_birth_date;
                $result['orphan_gender'] = $civilData['gender'];
                $result['orphan_data_source'] = 'civil_registry';
                $orphanDataFound = true;
            }
        }

        // ثالثاً: إذا لم يوجد في أي مكان، نقسم الاسم
        if (!$orphanDataFound && !empty($sponsorship->orphan_name)) {
            $nameParts = $this->splitArabicName($sponsorship->orphan_name);
            $result['orphan_first_name'] = $nameParts['first_name'];
            $result['orphan_father_name'] = $nameParts['father_name'];
            $result['orphan_grandfather_name'] = $nameParts['grand_father_name'];
            $result['orphan_family_name'] = $nameParts['family_name'];
            $result['first_name'] = $nameParts['first_name'];
            $result['second_name'] = $nameParts['father_name'];
            $result['third_name'] = $nameParts['grand_father_name'];
            $result['last_name'] = $nameParts['family_name'];
            $result['orphan_name_combined'] = $sponsorship->orphan_name;
            $result['needs_orphan_name_input'] = true;
        }

        // جلب الجنس من جداول بديلة إذا غير موجود
        if (empty($result['orphan_gender']) && !empty($sponsorship->identity_number)) {
            $result['orphan_gender'] = $this->getGenderFromAlternativeSources($sponsorship->identity_number);
        }

        // جلب بيانات المعيل - الأولوية: 1) جدول data، 2) السجل المدني، 3) تقسيم الاسم
        $guardianDataFromTable = null;
        if (!empty($sponsorship->relation_id_number)) {
            $guardianDataFromTable = DB::table('data')
                ->where('file_id_number', $sponsorship->relation_id_number)
                ->select(['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'])
                ->first();
        }

        // إذا وجدنا بيانات في جدول data، نستخدمها
        if ($guardianDataFromTable && !empty($guardianDataFromTable->data_first_name)) {
            $result['guardian_first_name'] = $guardianDataFromTable->data_first_name ?? '';
            $result['guardian_father_name'] = $guardianDataFromTable->data_father_name ?? '';
            $result['guardian_grandfather_name'] = $guardianDataFromTable->data_grand_father_name ?? '';
            $result['guardian_family_name'] = $guardianDataFromTable->data_family_name ?? '';
            $result['guardian_data_source'] = 'data_table';
        }
        // وإلا نحاول من السجل المدني
        elseif (!empty($sponsorship->guardian_identity_number)) {
            Log::info('🔍 محاولة جلب بيانات المعيل من السجل المدني', [
                'sponsorship_id' => $sponsorship->id,
                'guardian_identity_number' => $sponsorship->guardian_identity_number
            ]);

            $guardianData = $this->getPersonFromCivilRegistry($sponsorship->guardian_identity_number);
            if ($guardianData) {
                Log::info('✅ تم جلب بيانات المعيل من السجل المدني', [
                    'name' => $guardianData['full_name'],
                    'guardian_identity_number' => $sponsorship->guardian_identity_number
                ]);

                $result['guardian_first_name'] = $guardianData['first_name'];
                $result['guardian_father_name'] = $guardianData['father_name'];
                $result['guardian_grandfather_name'] = $guardianData['grand_father_name'];
                $result['guardian_family_name'] = $guardianData['family_name'];
                $result['guardian_name'] = $guardianData['full_name'];
                $result['guardian_data_source'] = 'civil_registry';
            } else {
                Log::warning('❌ لم يتم العثور على بيانات المعيل في السجل المدني', [
                    'guardian_identity_number' => $sponsorship->guardian_identity_number
                ]);

                if (!empty($sponsorship->guardian_name)) {
                    $nameParts = $this->splitArabicName($sponsorship->guardian_name);
                    $result['guardian_first_name'] = $nameParts['first_name'];
                    $result['guardian_father_name'] = $nameParts['father_name'];
                    $result['guardian_grandfather_name'] = $nameParts['grand_father_name'];
                    $result['guardian_family_name'] = $nameParts['family_name'];
                    $result['guardian_name_combined'] = $sponsorship->guardian_name;
                    $result['needs_guardian_name_input'] = true;
                }
            }
        }
        // وإلا نقسم الاسم من guardian_name
        elseif (!empty($sponsorship->guardian_name)) {
            $nameParts = $this->splitArabicName($sponsorship->guardian_name);
            $result['guardian_first_name'] = $nameParts['first_name'];
            $result['guardian_father_name'] = $nameParts['father_name'];
            $result['guardian_grandfather_name'] = $nameParts['grand_father_name'];
            $result['guardian_family_name'] = $nameParts['family_name'];
            $result['guardian_name_combined'] = $sponsorship->guardian_name;
            $result['needs_guardian_name_input'] = true;
        }

        // جلب البيانات البنكية للمعيل
        $result['bank_accounts'] = [];
        if (!empty($sponsorship->relation_id_number)) {
            try {
                $bankAccounts = DB::table('guardian_bank_accounts')
                    ->leftJoin('bank_names', 'guardian_bank_accounts.bank_name', '=', 'bank_names.id')
                    ->where('guardian_bank_accounts.guardian_registration', $sponsorship->relation_id_number)
                    ->select([
                        'guardian_bank_accounts.id',
                        'guardian_bank_accounts.iban_usd',
                        'guardian_bank_accounts.iban_shekel',
                        'guardian_bank_accounts.re_guardian_name',
                        'guardian_bank_accounts.re_phone_number',
                        'guardian_bank_accounts.person_owner_identity_number',
                        'guardian_bank_accounts.bank_name',
                        'bank_names.description as bank_name_text'
                    ])
                    ->get();

                // إرسال البيانات كما هي في قاعدة البيانات
                $result['bank_accounts'] = $bankAccounts->toArray();
            } catch (\Exception $e) {
                Log::warning('Failed to get bank accounts', ['error' => $e->getMessage()]);
            }
        }

        // جلب بيانات الاتصال للمعيل (العنوان والهاتف) من جدول data
        if (!empty($sponsorship->relation_id_number)) {
            try {
                $guardianInfo = DB::table('data')
                    ->where('file_id_number', $sponsorship->relation_id_number)
                    ->select(['data_current_address', 'data_phone_number', 'data_alt_phone_number',
                             'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name',
                             'data_gender', 'data_birth_date', 'data_id_number', 'data_health_status', 'data_city'])
                    ->first();

                if ($guardianInfo) {
                    $result['guardian_detailed_address'] = $guardianInfo->data_current_address ?? '';
                    $result['guardian_phone'] = $guardianInfo->data_phone_number ?? '';
                    $result['guardian_phone2'] = $guardianInfo->data_alt_phone_number ?? '';
                    // إضافة المدينة للنتيجة
                    $result['guardian_city_id'] = $guardianInfo->data_city ?? '';

                    // جلب الحالة الصحية للمعيل (إذا لم تكن موجودة بالفعل)
                    if (empty($result['health_status_id'])) {
                        $result['health_status_id'] = $guardianInfo->data_health_status ?? '';
                    }

                    // إذا كان المكفول من نوع breadwinner، فهو نفسه المعيل
                    if ($sponsorship->person_type === 'breadwinner') {
                        $result['first_name'] = $guardianInfo->data_first_name ?? '';
                        $result['second_name'] = $guardianInfo->data_father_name ?? '';
                        $result['third_name'] = $guardianInfo->data_grand_father_name ?? '';
                        $result['last_name'] = $guardianInfo->data_family_name ?? '';
                        $result['orphan_gender'] = $this->convertGenderToString($guardianInfo->data_gender ?? '');
                        $result['sponsored_birth_date'] = $guardianInfo->data_birth_date ?? '';
                        $result['health_status_id'] = $guardianInfo->data_health_status ?? '';
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get guardian contact info', ['error' => $e->getMessage()]);
            }
        }

        // جلب بيانات المكفول من re_people إذا كان من نوع repeople
        if ($sponsorship->person_type === 'repeople' && !empty($sponsorship->identity_number)) {
            try {
                $repeopleInfo = DB::table('re_people')
                    ->where('person_id', $sponsorship->identity_number)
                    ->first();

                if ($repeopleInfo) {
                    $result['first_name'] = $repeopleInfo->first_name ?? '';
                    $result['second_name'] = $repeopleInfo->second_name ?? '';
                    $result['third_name'] = $repeopleInfo->third_name ?? '';
                    $result['last_name'] = $repeopleInfo->last_name ?? '';
                    $result['orphan_gender'] = $this->convertGenderToString($repeopleInfo->person_gender ?? '');
                    $result['sponsored_birth_date'] = $repeopleInfo->person_birth_date ?? '';
                    $result['health_status_id'] = $repeopleInfo->person_health_status ?? '';
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get repeople info', ['error' => $e->getMessage()]);
            }
        }

        // جلب بيانات المكفول من dead_people إذا كان في هذا الجدول
        if (!empty($sponsorship->relation_id_number)) {
            try {
                $deadPeopleInfo = DB::table('dead_people')
                    ->where('re_file_id', $sponsorship->relation_id_number)
                    ->first();

                if ($deadPeopleInfo) {
                    $personType = $sponsorship->person_type ?? '';

                    // للأب المتوفي - استخدام حقول father_*
                    if ($personType === 'deceased_father') {
                        $result['first_name'] = $deadPeopleInfo->father_first_name ?? '';
                        $result['second_name'] = $deadPeopleInfo->father_second_name ?? '';
                        $result['third_name'] = $deadPeopleInfo->father_third_name ?? '';
                        $result['last_name'] = $deadPeopleInfo->father_last_name ?? '';
                        $result['orphan_gender'] = 'ذكر'; // الأب دائماً ذكر
                        $result['sponsored_birth_date'] = $deadPeopleInfo->father_death_date ?? '';
                    }
                    // للأم المتوفية - استخدام حقول mother_*
                    elseif ($personType === 'deceased_mother') {
                        $result['first_name'] = $deadPeopleInfo->mother_first_name ?? '';
                        $result['second_name'] = $deadPeopleInfo->mother_second_name ?? '';
                        $result['third_name'] = $deadPeopleInfo->mother_third_name ?? '';
                        $result['last_name'] = $deadPeopleInfo->mother_last_name ?? '';
                        $result['orphan_gender'] = 'أنثى'; // الأم دائماً أنثى
                        $result['sponsored_birth_date'] = $deadPeopleInfo->mother_death_date ?? '';
                    }
                    // افتراضياً - محاولة الأب أولاً
                    elseif (empty($result['first_name'])) {
                        if (!empty($deadPeopleInfo->father_first_name)) {
                            $result['first_name'] = $deadPeopleInfo->father_first_name;
                            $result['second_name'] = $deadPeopleInfo->father_second_name ?? '';
                            $result['third_name'] = $deadPeopleInfo->father_third_name ?? '';
                            $result['last_name'] = $deadPeopleInfo->father_last_name ?? '';
                            $result['orphan_gender'] = 'ذكر';
                            $result['sponsored_birth_date'] = $deadPeopleInfo->father_death_date ?? '';
                        } elseif (!empty($deadPeopleInfo->mother_first_name)) {
                            $result['first_name'] = $deadPeopleInfo->mother_first_name;
                            $result['second_name'] = $deadPeopleInfo->mother_second_name ?? '';
                            $result['third_name'] = $deadPeopleInfo->mother_third_name ?? '';
                            $result['last_name'] = $deadPeopleInfo->mother_last_name ?? '';
                            $result['orphan_gender'] = 'أنثى';
                            $result['sponsored_birth_date'] = $deadPeopleInfo->mother_death_date ?? '';
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get dead_people info', ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * تقسيم الاسم العربي إلى أربعة أجزاء
     */
    private function splitArabicName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));
        return [
            'first_name' => $parts[0] ?? '',
            'father_name' => $parts[1] ?? '',
            'grand_father_name' => $parts[2] ?? '',
            'family_name' => $parts[3] ?? (count($parts) > 3 ? implode(' ', array_slice($parts, 3)) : '')
        ];
    }

    /**
     * جلب بيانات شخص من السجل المدني
     */
    private function getPersonFromCivilRegistry(string $identityNumber): ?array
    {
        try {
            $person = DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $identityNumber)
                ->first();

            if (!$person) {
                return null;
            }

            $fullName = trim(
                ($person->CI_FIRST_ARB ?? '') . ' ' .
                ($person->CI_FATHER_ARB ?? '') . ' ' .
                ($person->CI_GRAND_FATHER_ARB ?? '') . ' ' .
                ($person->CI_FAMILY_ARB ?? '')
            );

            return [
                'full_name' => $fullName,
                'first_name' => $person->CI_FIRST_ARB ?? '',
                'father_name' => $person->CI_FATHER_ARB ?? '',
                'grand_father_name' => $person->CI_GRAND_FATHER_ARB ?? '',
                'family_name' => $person->CI_FAMILY_ARB ?? '',
                'birth_date' => $person->CI_BIRTH_DT ?? null,
                'gender' => $person->CI_SEX_CD == 1 ? 'ذكر' : ($person->CI_SEX_CD == 2 ? 'أنثى' : null)
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to get person from civil registry', [
                'identity' => $identityNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * جلب الجنس من مصادر بديلة (data, re_people)
     */
    private function getGenderFromAlternativeSources(string $identityNumber): ?string
    {
        // 1. البحث في جدول data
        try {
            $data = DB::table('data')
                ->where('data_id_number', $identityNumber)
                ->first();

            if ($data && !empty($data->data_gender)) {
                return $data->data_gender == 1 ? 'ذكر' : ($data->data_gender == 2 ? 'أنثى' : null);
            }
        } catch (\Exception $e) {
            Log::debug('Gender lookup in data failed', ['error' => $e->getMessage()]);
        }

        // 2. البحث في جدول re_people
        try {
            $rePerson = DB::table('re_people')
                ->where('person_id', $identityNumber)
                ->first();

            if ($rePerson && !empty($rePerson->person_gender)) {
                return $rePerson->person_gender == 1 ? 'ذكر' : ($rePerson->person_gender == 2 ? 'أنثى' : null);
            }
        } catch (\Exception $e) {
            Log::debug('Gender lookup in re_people failed', ['error' => $e->getMessage()]);
        }

        // 3. البحث في جدول dead_people (أب متوفي = ذكر، أم متوفية = أنثى)
        try {
            // البحث إذا كان الشخص أب متوفي
            $deadFather = DB::table('dead_people')
                ->where('father_id', $identityNumber)
                ->first();

            if ($deadFather) {
                return 'ذكر'; // أب متوفي = ذكر
            }

            // البحث إذا كان الشخص أم متوفية
            $deadMother = DB::table('dead_people')
                ->where('mother_id', $identityNumber)
                ->first();

            if ($deadMother) {
                return 'أنثى'; // أم متوفية = أنثى
            }
        } catch (\Exception $e) {
            Log::debug('Gender lookup in dead_people failed', ['error' => $e->getMessage()]);
        }

        // 4. البحث في السجل المدني مباشرة
        try {
            $person = DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $identityNumber)
                ->first();

            if ($person && !empty($person->CI_SEX_CD)) {
                return $person->CI_SEX_CD == 1 ? 'ذكر' : ($person->CI_SEX_CD == 2 ? 'أنثى' : null);
            }
        } catch (\Exception $e) {
            Log::debug('Gender lookup in civil registry failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * GET /api/mobile/sync/sponsorship/{id}
     * جلب تفاصيل كفالة واحدة
     */
    public function getSponsorshipDetails(int $id): JsonResponse
    {
        try {
            $sponsorship = DB::table('sponsorships')
                ->leftJoin('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
                ->leftJoin('sponsorship_statuses', 'sponsorships.sponsorship_status_id', '=', 'sponsorship_statuses.id')
                ->where('sponsorships.id', $id)
                ->select([
                    'sponsorships.*',
                    'sponsors.sponsor_name',
                    'sponsors.sponsor_short_name',
                    'sponsorship_statuses.description as status_name'
                ])
                ->first();

            if (!$sponsorship) {
                return response()->json([
                    'success' => false,
                    'message' => 'الكفالة غير موجودة'
                ], 404);
            }

            $enriched = $this->enrichSponsorshipData($sponsorship);

            // جلب الحسابات البنكية إذا وجد relation_id_number
            $bankAccounts = [];
            if (!empty($sponsorship->relation_id_number)) {
                $bankAccounts = DB::table('guardian_bank_accounts')
                    ->leftJoin('bank_names', 'guardian_bank_accounts.bank_name', '=', 'bank_names.id')
                    ->where('guardian_bank_accounts.guardian_registration', $sponsorship->relation_id_number)
                    ->select([
                        'guardian_bank_accounts.*',
                        'bank_names.description as bank_name_text'
                    ])
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'sponsorship' => $enriched,
                    'bank_accounts' => $bankAccounts
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sponsorship details', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب تفاصيل الكفالة'
            ], 500);
        }
    }

    // ========================================
    // Upload Sync - رفع البيانات من الموبايل
    // ========================================

    /**
     * POST /api/mobile/sync/upload
     * رفع بيانات محدثة من الموبايل
     */
    public function uploadSyncData(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'sponsorship_id' => 'required|integer',
                'updates' => 'required|array'
            ]);

            $sponsorshipId = $data['sponsorship_id'];
            $updates = $data['updates'];

            // جلب بيانات الكفالة الحالية
            $sponsorship = DB::table('sponsorships')->where('id', $sponsorshipId)->first();
            if (!$sponsorship) {
                return response()->json([
                    'success' => false,
                    'message' => 'الكفالة غير موجودة'
                ], 404);
            }

            // تحديد الحقول المسموح بتحديثها في جدول sponsorships فقط
            $allowedFields = [
                // بيانات أساسية موجودة في sponsorships
                'orphan_name',
                'identity_number',
                'sponsored_birth_date',
                // orphan_gender ليس في sponsorships - يذهب للجدول المناسب
                'guardian_name',
                'guardian_identity_number',
                'notes',
                'sponsorship_status_id',
                'person_type'
            ];

            // الحقول التي تذهب إلى جدول data وليس sponsorships
            $dataOnlyFields = [
                'health_status_id', 'guardian_phone', 'guardian_phone2',
                'guardian_city_id', 'guardian_detailed_address',
                'guardian_first_name', 'guardian_father_name',
                'guardian_grandfather_name', 'guardian_family_name',
                'guardian_person_type',
                // حقول اسم المكفول الأربعة - تذهب للجدول المناسب حسب person_type
                'orphan_first_name', 'orphan_father_name',
                'orphan_grandfather_name', 'orphan_family_name',
                // orphan_gender يذهب للجدول المناسب حسب person_type
                'orphan_gender'
            ];

            // تصفية التحديثات: فقط allowedFields وليس dataOnlyFields
            $filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));

            // إزالة أي حقول من dataOnlyFields قد تكون تسللت
            foreach ($dataOnlyFields as $dataField) {
                unset($filteredUpdates[$dataField]);
            }

            $filteredUpdates['updated_at'] = now();
            $filteredUpdates['updated_by'] = $request->user()->id;

            // تحديث اسم المكفول الكامل إذا تم تعديل الأجزاء
            if (isset($updates['orphan_first_name']) || isset($updates['orphan_father_name']) ||
                isset($updates['orphan_grandfather_name']) || isset($updates['orphan_family_name'])) {

                $firstName = $updates['orphan_first_name'] ?? '';
                $fatherName = $updates['orphan_father_name'] ?? '';
                $grandfatherName = $updates['orphan_grandfather_name'] ?? '';
                $familyName = $updates['orphan_family_name'] ?? '';

                $filteredUpdates['orphan_name'] = trim("$firstName $fatherName $grandfatherName $familyName");
            }

            // تحديث اسم المعيل الكامل إذا تم تعديل الأجزاء
            if (isset($updates['guardian_first_name']) || isset($updates['guardian_father_name']) ||
                isset($updates['guardian_grandfather_name']) || isset($updates['guardian_family_name'])) {

                $firstName = $updates['guardian_first_name'] ?? '';
                $fatherName = $updates['guardian_father_name'] ?? '';
                $grandfatherName = $updates['guardian_grandfather_name'] ?? '';
                $familyName = $updates['guardian_family_name'] ?? '';

                $filteredUpdates['guardian_name'] = trim("$firstName $fatherName $grandfatherName $familyName");
            }

            // التحقق من تغيير اسم المكفول لتحديث مجلد Google Drive
            $oldOrphanName = $updates['old_orphan_name'] ?? null;
            // التحقق من orphan_name في filteredUpdates أو updates مباشرة
            $newOrphanName = $filteredUpdates['orphan_name'] ?? $updates['orphan_name'] ?? null;
            $folderRenamed = false;

            if ($oldOrphanName && $newOrphanName && $oldOrphanName !== $newOrphanName) {
                try {
                    // الحصول على اسم الجمعية
                    $sponsor = DB::table('sponsors')->where('id', $sponsorship->sponsor_id)->first();
                    $sponsorName = $sponsor ? $sponsor->sponsor_name : 'غير محدد';

                    // إعادة تسمية المجلد على Google Drive
                    $folderRenamed = $this->renameDriveFolder($sponsorName, $oldOrphanName, $newOrphanName);

                    Log::info('تم إعادة تسمية مجلد المكفول على Google Drive', [
                        'sponsorship_id' => $sponsorshipId,
                        'old_name' => $oldOrphanName,
                        'new_name' => $newOrphanName,
                        'success' => $folderRenamed
                    ]);
                } catch (\Exception $e) {
                    Log::warning('فشل إعادة تسمية مجلد Google Drive', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // تحديث جدول sponsorships
            DB::table('sponsorships')
                ->where('id', $sponsorshipId)
                ->update($filteredUpdates);

            // تحديث جدول data إذا كان هناك relation_id_number
            if ($sponsorship->relation_id_number) {
                $dataUpdates = [];

                if (isset($updates['guardian_first_name'])) {
                    $dataUpdates['data_first_name'] = $updates['guardian_first_name'];
                }
                if (isset($updates['guardian_father_name'])) {
                    $dataUpdates['data_father_name'] = $updates['guardian_father_name'];
                }
                if (isset($updates['guardian_grandfather_name'])) {
                    $dataUpdates['data_grand_father_name'] = $updates['guardian_grandfather_name'];
                }
                if (isset($updates['guardian_family_name'])) {
                    $dataUpdates['data_family_name'] = $updates['guardian_family_name'];
                }
                if (isset($updates['guardian_phone'])) {
                    $dataUpdates['data_phone_number'] = $updates['guardian_phone'];
                }
                if (isset($updates['guardian_phone2'])) {
                    $dataUpdates['data_alt_phone_number'] = $updates['guardian_phone2'];
                }
                if (isset($updates['guardian_detailed_address'])) {
                    $dataUpdates['data_current_address'] = $updates['guardian_detailed_address'];
                }
                if (isset($updates['guardian_city_id'])) {
                    $dataUpdates['data_city'] = $updates['guardian_city_id'];
                }
                if (isset($updates['health_status_id'])) {
                    $dataUpdates['data_health_status'] = $updates['health_status_id'];
                }

                if (!empty($dataUpdates)) {
                    $dataUpdates['updated_at'] = now();
                    DB::table('data')
                        ->where('file_id_number', $sponsorship->relation_id_number)
                        ->update($dataUpdates);

                    Log::info('تم تحديث بيانات المعيل في جدول data', [
                        'relation_id_number' => $sponsorship->relation_id_number,
                        'updates' => array_keys($dataUpdates)
                    ]);
                }
            }

            // ===================================================
            // خوارزمية ذكية لحفظ بيانات المكفول في الجدول الصحيح
            // باستخدام person_type لتحديد الجدول المستهدف
            // ===================================================

            $hasOrphanDataUpdate = isset($updates['first_name']) || isset($updates['second_name']) ||
                                   isset($updates['third_name']) || isset($updates['last_name']) ||
                                   isset($updates['orphan_gender']) || isset($updates['birth_date']) ||
                                   isset($updates['identity_number']);

            if ($hasOrphanDataUpdate) {
                // الحصول على person_type من التحديثات أو من الكفالة
                $personType = $updates['person_type'] ?? $sponsorship->person_type ?? null;
                $relationIdNumber = $sponsorship->relation_id_number;
                $identityNumber = $updates['identity_number'] ?? $sponsorship->identity_number ?? null;

                Log::info('🔍 بدء تحديث بيانات المكفول', [
                    'person_type' => $personType,
                    'relation_id_number' => $relationIdNumber,
                    'identity_number' => $identityNumber
                ]);

                // تحديد الجدول والحقول المستهدفة بناءً على person_type
                $updateResult = $this->updatePersonByType(
                    $personType,
                    $relationIdNumber,
                    $identityNumber,
                    $updates,
                    $sponsorship
                );

                if ($updateResult['success']) {
                    Log::info('✅ تم تحديث بيانات المكفول بنجاح', $updateResult);
                } else {
                    Log::warning('⚠️ فشل تحديث بيانات المكفول', $updateResult);
                }
            }

            // معالجة نوع الشخص (المعيل) وإنشاء/تحديث السجل المناسب
            if (isset($updates['guardian_person_type']) && isset($updates['guardian_identity_number'])) {
                $this->handleGuardianPersonType(
                    $sponsorship,
                    $updates['guardian_person_type'],
                    $updates['guardian_identity_number'],
                    $updates,
                    $request->user()->id
                );
            }

            // تحديث الحسابات البنكية إذا وجدت
            if (isset($updates['bank_accounts_updates']) && is_array($updates['bank_accounts_updates'])) {
                $this->updateBankAccounts($sponsorship, $updates['bank_accounts_updates']);
            }

            Log::info('Sponsorship updated from mobile', [
                'sponsorship_id' => $sponsorshipId,
                'user_id' => $request->user()->id,
                'updates' => array_keys($filteredUpdates),
                'folder_renamed' => $folderRenamed
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
                'folder_renamed' => $folderRenamed,
                'sync_timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload sync data', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل رفع البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إعادة تسمية مجلد على Google Drive
     */
    private function renameDriveFolder(string $sponsorName, string $oldName, string $newName): bool
    {
        try {
            $useRclone = config('services.google.use_rclone', false);

            if ($useRclone) {
                // استخدام Rclone لإعادة التسمية
                $remoteName = config('services.google.rclone_remote_name', 'alhayahorphans');
                $rootFolder = config('services.google.rclone_root_folder', 'temp');

                $oldPath = "{$remoteName}:{$rootFolder}/{$sponsorName}/{$oldName}";
                $newPath = "{$remoteName}:{$rootFolder}/{$sponsorName}/{$newName}";

                $command = "rclone moveto \"{$oldPath}\" \"{$newPath}\" 2>&1";
                $output = shell_exec($command);

                Log::info('Rclone rename folder', [
                    'command' => $command,
                    'output' => $output
                ]);

                return true;
            } else {
                // استخدام Google Drive API
                $googleDriveService = app(\App\Services\GoogleDriveService::class);
                return $googleDriveService->renameFolder($sponsorName, $oldName, $newName);
            }
        } catch (\Exception $e) {
            Log::error('Rename folder failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * تحديث الحسابات البنكية
     */
    private function updateBankAccounts($sponsorship, array $bankUpdates): void
    {
        try {
            // جلب الحسابات البنكية الحالية
            // جدول guardian_bank_accounts يخزن مرجع الملف في عمود guardian_registration
            $accounts = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $sponsorship->relation_id_number)
                ->orderBy('id')
                ->get()
                ->values();

            Log::info('🏦 تحديث الحسابات البنكية', [
                'sponsorship_id' => $sponsorship->id,
                'relation_id_number' => $sponsorship->relation_id_number,
                'accounts_count' => $accounts->count(),
                'updates_count' => count($bankUpdates)
            ]);

            foreach ($bankUpdates as $index => $updates) {
                if (!isset($accounts[$index])) {
                    Log::warning('⚠️ لا يوجد حساب بنكي للفهرس', ['index' => $index]);
                    continue;
                }

                $account = $accounts[$index];
                $updateData = [];

                // دعم أسماء الحقول من التطبيق (data-bank-field) وأسماء بديلة
                // bank_name - اسم/رقم البنك
                if (isset($updates['bank_name'])) {
                    $updateData['bank_name'] = $updates['bank_name'];
                } elseif (isset($updates['bank_name_id'])) {
                    $updateData['bank_name'] = $updates['bank_name_id'];
                }

                // re_guardian_name - اسم صاحب الحساب
                if (isset($updates['re_guardian_name'])) {
                    $updateData['re_guardian_name'] = $updates['re_guardian_name'];
                } elseif (isset($updates['account_holder_name'])) {
                    $updateData['re_guardian_name'] = $updates['account_holder_name'];
                }

                // person_owner_identity_number - رقم هوية صاحب الحساب
                if (isset($updates['person_owner_identity_number'])) {
                    $updateData['person_owner_identity_number'] = $updates['person_owner_identity_number'];
                } elseif (isset($updates['account_holder_identity'])) {
                    $updateData['person_owner_identity_number'] = $updates['account_holder_identity'];
                }

                // re_phone_number - رقم هاتف صاحب الحساب
                if (isset($updates['re_phone_number'])) {
                    $updateData['re_phone_number'] = $updates['re_phone_number'];
                } elseif (isset($updates['account_holder_phone'])) {
                    $updateData['re_phone_number'] = $updates['account_holder_phone'];
                }

                // iban_usd - رقم IBAN بالدولار
                if (isset($updates['iban_usd'])) {
                    $updateData['iban_usd'] = $updates['iban_usd'];
                } elseif (isset($updates['iban'])) {
                    $updateData['iban_usd'] = $updates['iban'];
                }

                // iban_shekel - رقم IBAN بالشيكل
                if (isset($updates['iban_shekel'])) {
                    $updateData['iban_shekel'] = $updates['iban_shekel'];
                }

                if (!empty($updateData)) {
                    $updateData['updated_at'] = now();
                    DB::table('guardian_bank_accounts')
                        ->where('id', $account->id)
                        ->update($updateData);

                    Log::info('✅ تم تحديث الحساب البنكي', [
                        'account_id' => $account->id,
                        'index' => $index,
                        'updated_fields' => array_keys($updateData)
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ فشل تحديث الحسابات البنكية', ['error' => $e->getMessage()]);
        }
    }

    /**
     * تحديث بيانات الشخص المكفول بناءً على نوعه (person_type)
     *
     * خوارزمية المطابقة:
     * - breadwinner (معيل): data.file_id_number = relation_id_number AND data.data_id_number = identity_number
     * - family_member (فرد عائلة): re_people.registration_id = relation_id_number AND re_people.person_id = identity_number
     * - orphan (يتيم): نفس منطق family_member
     * - deceased_father (أب متوفي): dead_people.re_file_id = relation_id_number AND dead_people.father_id = identity_number
     * - deceased_mother (أم متوفية): dead_people.re_file_id = relation_id_number AND dead_people.mother_id = identity_number
     */
    private function updatePersonByType($personType, $relationIdNumber, $identityNumber, array $updates, $sponsorship): array
    {
        $result = [
            'success' => false,
            'person_type' => $personType,
            'table' => null,
            'message' => ''
        ];

        // إذا لم يكن هناك relation_id_number، لا يمكن التحديث
        if (empty($relationIdNumber)) {
            $result['message'] = 'relation_id_number فارغ - لا يمكن تحديد السجل';
            Log::warning('⚠️ updatePersonByType: relation_id_number فارغ');
            return $result;
        }

        switch ($personType) {
            // ============================================
            // حالة المعيل (breadwinner) - جدول data
            // ============================================
            case 'breadwinner':
                $result['table'] = 'data';

                // البحث عن السجل باستخدام المطابقة المزدوجة
                $query = DB::table('data')->where('file_id_number', $relationIdNumber);
                if (!empty($identityNumber)) {
                    $query->where('data_id_number', $identityNumber);
                }
                $record = $query->first();

                if (!$record) {
                    // محاولة البحث فقط باستخدام file_id_number
                    $record = DB::table('data')->where('file_id_number', $relationIdNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على السجل بـ file_id_number فقط', ['file_id_number' => $relationIdNumber]);
                    }
                }

                if ($record) {
                    $updateData = [];
                    if (isset($updates['first_name'])) $updateData['data_first_name'] = $updates['first_name'];
                    if (isset($updates['second_name'])) $updateData['data_father_name'] = $updates['second_name'];
                    if (isset($updates['third_name'])) $updateData['data_grand_father_name'] = $updates['third_name'];
                    if (isset($updates['last_name'])) $updateData['data_family_name'] = $updates['last_name'];
                    if (isset($updates['orphan_gender'])) {
                        $updateData['data_gender'] = $this->convertGenderToInt($updates['orphan_gender']);
                    }
                    if (isset($updates['birth_date'])) $updateData['data_birth_date'] = $updates['birth_date'];
                    if (isset($updates['identity_number'])) $updateData['data_id_number'] = $updates['identity_number'];
                    if (isset($updates['health_status_id'])) $updateData['data_health_status'] = $updates['health_status_id'];

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('data')->where('file_id_number', $relationIdNumber)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث المعيل في جدول data';
                        $result['updated_fields'] = array_keys($updateData);
                    }
                } else {
                    // إنشاء سجل جديد للمعيل
                    $newFileId = generateFileIdFromDataTable();
                    DB::table('data')->insert([
                        'file_id_number' => $newFileId,
                        'data_first_name' => $updates['first_name'] ?? null,
                        'data_father_name' => $updates['second_name'] ?? null,
                        'data_grand_father_name' => $updates['third_name'] ?? null,
                        'data_family_name' => $updates['last_name'] ?? null,
                        'data_gender' => isset($updates['orphan_gender']) ? $this->convertGenderToInt($updates['orphan_gender']) : null,
                        'data_birth_date' => $updates['birth_date'] ?? null,
                        'data_id_number' => $updates['identity_number'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // تحديث relation_id_number في sponsorships
                    DB::table('sponsorships')->where('id', $sponsorship->id)->update(['relation_id_number' => $newFileId]);

                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد للمعيل في جدول data';
                    $result['new_file_id'] = $newFileId;
                }
                break;

            // ============================================
            // حالة فرد العائلة أو اليتيم - جدول re_people
            // ============================================
            case 'family_member':
            case 'orphan':
                $result['table'] = 're_people';

                // البحث عن السجل باستخدام المطابقة المزدوجة
                $query = DB::table('re_people')->where('registration_id', $relationIdNumber);
                if (!empty($identityNumber)) {
                    $query->where('person_id', $identityNumber);
                }
                $record = $query->first();

                if (!$record) {
                    // محاولة البحث فقط باستخدام registration_id
                    $record = DB::table('re_people')->where('registration_id', $relationIdNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على السجل بـ registration_id فقط', ['registration_id' => $relationIdNumber]);
                    }
                }

                if ($record) {
                    $updateData = [];
                    if (isset($updates['first_name'])) $updateData['first_name'] = $updates['first_name'];
                    if (isset($updates['second_name'])) $updateData['second_name'] = $updates['second_name'];
                    if (isset($updates['third_name'])) $updateData['third_name'] = $updates['third_name'];
                    if (isset($updates['last_name'])) $updateData['last_name'] = $updates['last_name'];
                    if (isset($updates['orphan_gender'])) {
                        $updateData['person_gender'] = $this->convertGenderToInt($updates['orphan_gender']);
                    }
                    if (isset($updates['birth_date'])) $updateData['person_birth_date'] = $updates['birth_date'];
                    if (isset($updates['identity_number'])) $updateData['person_id'] = $updates['identity_number'];
                    if (isset($updates['health_status_id'])) $updateData['person_health_status'] = $updates['health_status_id'];

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('re_people')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث فرد العائلة في جدول re_people';
                        $result['updated_fields'] = array_keys($updateData);
                    }
                } else {
                    $result['message'] = 'لم يتم العثور على سجل فرد العائلة';
                    Log::warning('⚠️ لم يتم العثور على سجل في re_people', [
                        'registration_id' => $relationIdNumber,
                        'person_id' => $identityNumber
                    ]);
                }
                break;

            // ============================================
            // حالة الأب المتوفي - جدول dead_people (حقول father_*)
            // ============================================
            case 'deceased_father':
                $result['table'] = 'dead_people';

                // البحث عن السجل باستخدام re_file_id و father_id
                $query = DB::table('dead_people')->where('re_file_id', $relationIdNumber);
                if (!empty($identityNumber)) {
                    $query->where('father_id', $identityNumber);
                }
                $record = $query->first();

                if (!$record) {
                    // محاولة البحث فقط باستخدام re_file_id
                    $record = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على السجل بـ re_file_id فقط', ['re_file_id' => $relationIdNumber]);
                    }
                }

                if ($record) {
                    $updateData = [];
                    // تحديث حقول الأب فقط (father_*)
                    if (isset($updates['first_name'])) $updateData['father_first_name'] = $updates['first_name'];
                    if (isset($updates['second_name'])) $updateData['father_second_name'] = $updates['second_name'];
                    if (isset($updates['third_name'])) $updateData['father_third_name'] = $updates['third_name'];
                    if (isset($updates['last_name'])) $updateData['father_last_name'] = $updates['last_name'];
                    if (isset($updates['identity_number'])) $updateData['father_id'] = $updates['identity_number'];
                    if (isset($updates['birth_date'])) $updateData['father_death_date'] = $updates['birth_date']; // ملاحظة: للمتوفي هو تاريخ الوفاة
                    // الجنس للأب دائماً ذكر - لا حاجة لتحديثه

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('dead_people')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث بيانات الأب المتوفي';
                        $result['updated_fields'] = array_keys($updateData);

                        // حفظ البيانات الإضافية للمتوفين (العنوان، الهاتف، إلخ)
                        $this->saveDeceasedExtraData($sponsorship, $updates, 'deceased_father');
                    }
                } else {
                    $result['message'] = 'لم يتم العثور على سجل الأب المتوفي';
                    Log::warning('⚠️ لم يتم العثور على سجل في dead_people للأب', [
                        're_file_id' => $relationIdNumber,
                        'father_id' => $identityNumber
                    ]);
                }
                break;

            // ============================================
            // حالة الأم المتوفية - جدول dead_people (حقول mother_*)
            // ============================================
            case 'deceased_mother':
                $result['table'] = 'dead_people';

                // البحث عن السجل باستخدام re_file_id و mother_id
                $query = DB::table('dead_people')->where('re_file_id', $relationIdNumber);
                if (!empty($identityNumber)) {
                    $query->where('mother_id', $identityNumber);
                }
                $record = $query->first();

                if (!$record) {
                    // محاولة البحث فقط باستخدام re_file_id
                    $record = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على السجل بـ re_file_id فقط', ['re_file_id' => $relationIdNumber]);
                    }
                }

                if ($record) {
                    $updateData = [];
                    // تحديث حقول الأم فقط (mother_*)
                    if (isset($updates['first_name'])) $updateData['mother_first_name'] = $updates['first_name'];
                    if (isset($updates['second_name'])) $updateData['mother_second_name'] = $updates['second_name'];
                    if (isset($updates['third_name'])) $updateData['mother_third_name'] = $updates['third_name'];
                    if (isset($updates['last_name'])) $updateData['mother_last_name'] = $updates['last_name'];
                    if (isset($updates['identity_number'])) $updateData['mother_id'] = $updates['identity_number'];
                    if (isset($updates['birth_date'])) $updateData['mother_death_date'] = $updates['birth_date']; // للمتوفية هو تاريخ الوفاة
                    // الجنس للأم دائماً أنثى - لا حاجة لتحديثه

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('dead_people')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث بيانات الأم المتوفية';
                        $result['updated_fields'] = array_keys($updateData);

                        // حفظ البيانات الإضافية للمتوفين (العنوان، الهاتف، إلخ)
                        $this->saveDeceasedExtraData($sponsorship, $updates, 'deceased_mother');
                    }
                } else {
                    $result['message'] = 'لم يتم العثور على سجل الأم المتوفية';
                    Log::warning('⚠️ لم يتم العثور على سجل في dead_people للأم', [
                        're_file_id' => $relationIdNumber,
                        'mother_id' => $identityNumber
                    ]);
                }
                break;

            // ============================================
            // حالة غير معروفة - بحث تلقائي في الجداول
            // ============================================
            default:
                $result['message'] = 'نوع الشخص غير محدد - سيتم البحث تلقائياً';
                Log::info('🔍 person_type غير محدد، البحث في الجداول تلقائياً', ['person_type' => $personType]);

                // البحث بالترتيب: data → re_people → dead_people
                $dataRecord = DB::table('data')->where('file_id_number', $relationIdNumber)->first();
                if ($dataRecord) {
                    $result['table'] = 'data';
                    $updateData = $this->prepareDataTableUpdate($updates);
                    if (!empty($updateData)) {
                        DB::table('data')->where('file_id_number', $relationIdNumber)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث السجل في جدول data (بحث تلقائي)';
                    }
                } else {
                    $repeopleRecord = DB::table('re_people')->where('registration_id', $relationIdNumber)->first();
                    if ($repeopleRecord) {
                        $result['table'] = 're_people';
                        $updateData = $this->prepareRePeopleUpdate($updates);
                        if (!empty($updateData)) {
                            DB::table('re_people')->where('id', $repeopleRecord->id)->update($updateData);
                            $result['success'] = true;
                            $result['message'] = 'تم تحديث السجل في جدول re_people (بحث تلقائي)';
                        }
                    } else {
                        $deadRecord = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
                        if ($deadRecord) {
                            $result['table'] = 'dead_people';
                            // للبحث التلقائي في dead_people، نحاول تحديد إذا كان أب أو أم
                            if (!empty($identityNumber)) {
                                if ($deadRecord->father_id == $identityNumber) {
                                    $updateData = $this->prepareDeadPeopleUpdate($updates, 'father');
                                } elseif ($deadRecord->mother_id == $identityNumber) {
                                    $updateData = $this->prepareDeadPeopleUpdate($updates, 'mother');
                                } else {
                                    $updateData = $this->prepareDeadPeopleUpdate($updates, 'father'); // افتراضي
                                }
                            } else {
                                $updateData = $this->prepareDeadPeopleUpdate($updates, 'father'); // افتراضي
                            }
                            if (!empty($updateData)) {
                                DB::table('dead_people')->where('id', $deadRecord->id)->update($updateData);
                                $result['success'] = true;
                                $result['message'] = 'تم تحديث السجل في جدول dead_people (بحث تلقائي)';
                            }
                        } else {
                            // لم يتم العثور على أي سجل - إنشاء جديد في data
                            $newFileId = generateFileIdFromDataTable();
                            DB::table('data')->insert([
                                'file_id_number' => $newFileId,
                                'data_first_name' => $updates['first_name'] ?? null,
                                'data_father_name' => $updates['second_name'] ?? null,
                                'data_grand_father_name' => $updates['third_name'] ?? null,
                                'data_family_name' => $updates['last_name'] ?? null,
                                'data_gender' => isset($updates['orphan_gender']) ? $this->convertGenderToInt($updates['orphan_gender']) : null,
                                'data_birth_date' => $updates['birth_date'] ?? null,
                                'data_id_number' => $updates['identity_number'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            DB::table('sponsorships')->where('id', $sponsorship->id)->update(['relation_id_number' => $newFileId]);
                            $result['success'] = true;
                            $result['table'] = 'data';
                            $result['message'] = 'تم إنشاء سجل جديد في data (بحث تلقائي)';
                            $result['new_file_id'] = $newFileId;
                        }
                    }
                }
                break;
        }

        return $result;
    }

    /**
     * تحويل قيمة الجنس إلى رقم صحيح
     * @param mixed $gender - يمكن أن يكون نص أو رقم
     * @return int - 1 = ذكر، 2 = أنثى
     */
    private function convertGenderToInt($gender): int
    {
        if (is_int($gender)) {
            return $gender;
        }
        return ($gender === 'ذكر' || $gender === 'male' || $gender === '1' || $gender === 1) ? 1 : 2;
    }

    /**
     * تحويل قيمة الجنس من رقم إلى نص
     * @param mixed $gender - يمكن أن يكون نص أو رقم
     * @return string - "ذكر" أو "أنثى" أو فارغ
     */
    private function convertGenderToString($gender): string
    {
        if (empty($gender)) {
            return '';
        }
        if ($gender === 'ذكر' || $gender === 'أنثى') {
            return $gender; // إذا كان نصاً بالفعل
        }
        if ($gender == 1 || $gender === '1') {
            return 'ذكر';
        }
        if ($gender == 2 || $gender === '2') {
            return 'أنثى';
        }
        return '';
    }

    /**
     * تحضير بيانات التحديث لجدول data
     */
    private function prepareDataTableUpdate(array $updates): array
    {
        $updateData = [];
        if (isset($updates['first_name'])) $updateData['data_first_name'] = $updates['first_name'];
        if (isset($updates['second_name'])) $updateData['data_father_name'] = $updates['second_name'];
        if (isset($updates['third_name'])) $updateData['data_grand_father_name'] = $updates['third_name'];
        if (isset($updates['last_name'])) $updateData['data_family_name'] = $updates['last_name'];
        if (isset($updates['orphan_gender'])) {
            $updateData['data_gender'] = $this->convertGenderToInt($updates['orphan_gender']);
        }
        if (isset($updates['birth_date'])) $updateData['data_birth_date'] = $updates['birth_date'];
        if (isset($updates['identity_number'])) $updateData['data_id_number'] = $updates['identity_number'];
        if (isset($updates['health_status_id'])) $updateData['data_health_status'] = $updates['health_status_id'];
        if (!empty($updateData)) $updateData['updated_at'] = now();
        return $updateData;
    }

    /**
     * تحضير بيانات التحديث لجدول re_people
     */
    private function prepareRePeopleUpdate(array $updates): array
    {
        $updateData = [];
        if (isset($updates['first_name'])) $updateData['first_name'] = $updates['first_name'];
        if (isset($updates['second_name'])) $updateData['second_name'] = $updates['second_name'];
        if (isset($updates['third_name'])) $updateData['third_name'] = $updates['third_name'];
        if (isset($updates['last_name'])) $updateData['last_name'] = $updates['last_name'];
        if (isset($updates['orphan_gender'])) {
            $updateData['person_gender'] = $this->convertGenderToInt($updates['orphan_gender']);
        }
        if (isset($updates['birth_date'])) $updateData['person_birth_date'] = $updates['birth_date'];
        if (isset($updates['identity_number'])) $updateData['person_id'] = $updates['identity_number'];
        if (isset($updates['health_status_id'])) $updateData['person_health_status'] = $updates['health_status_id'];
        if (!empty($updateData)) $updateData['updated_at'] = now();
        return $updateData;
    }

    /**
     * تحضير بيانات التحديث لجدول dead_people
     * @param string $type - 'father' أو 'mother'
     */
    private function prepareDeadPeopleUpdate(array $updates, string $type = 'father'): array
    {
        $prefix = $type === 'mother' ? 'mother_' : 'father_';
        $updateData = [];
        if (isset($updates['first_name'])) $updateData[$prefix . 'first_name'] = $updates['first_name'];
        if (isset($updates['second_name'])) $updateData[$prefix . 'second_name'] = $updates['second_name'];
        if (isset($updates['third_name'])) $updateData[$prefix . 'third_name'] = $updates['third_name'];
        if (isset($updates['last_name'])) $updateData[$prefix . 'last_name'] = $updates['last_name'];
        if (isset($updates['identity_number'])) $updateData[$prefix . 'id'] = $updates['identity_number'];
        if (isset($updates['birth_date'])) $updateData[$prefix . 'death_date'] = $updates['birth_date'];
        if (!empty($updateData)) $updateData['updated_at'] = now();
        return $updateData;
    }

    /**
     * معالجة نوع الشخص (المعيل) - إنشاء أو تحديث السجل في الجدول المناسب
     *
     * الأنواع المدعومة:
     * - breadwinner: معيل → جدول data
     * - family_member: فرد عائلة → جدول re_people
     * - deceased_father: أب متوفي → جدول dead_people
     * - deceased_mother: أم متوفية → جدول dead_people
     */
    private function handleGuardianPersonType($sponsorship, string $personType, string $identityNumber, array $updates, int $userId): array
    {
        $result = [
            'action' => 'none',
            'table' => null,
            'record_id' => null,
            'created' => false,
            'duplicate_check' => null
        ];

        if (empty($identityNumber)) {
            Log::warning('Guardian identity number is empty, skipping person type handling');
            return $result;
        }

        try {
            // ⚠️ التحقق الشامل من تكرار رقم الهوية في جميع الجداول
            $duplicateCheck = $this->checkIdentityDuplication($identityNumber);
            $result['duplicate_check'] = $duplicateCheck;

            if ($duplicateCheck['exists']) {
                // الشخص موجود - نقوم بتحديث السجل الموجود فقط
                Log::info('Guardian already exists, updating existing record', [
                    'identity_number' => $identityNumber,
                    'found_in' => $duplicateCheck['found_in'],
                    'file_id' => $duplicateCheck['file_id']
                ]);

                // تحديث السجل الموجود حسب الجدول الذي وجد فيه
                $result = $this->updateExistingGuardianRecord(
                    $sponsorship,
                    $duplicateCheck,
                    $updates,
                    $userId
                );
            } else {
                // الشخص غير موجود - إنشاء سجل جديد حسب النوع المحدد
                switch ($personType) {
                    case 'breadwinner':
                        $result = $this->handleBreadwinnerRecord($sponsorship, $identityNumber, $updates, $userId);
                        break;

                    case 'family_member':
                        $result = $this->handleFamilyMemberRecord($sponsorship, $identityNumber, $updates, $userId);
                        break;

                    case 'deceased_father':
                        $result = $this->handleDeceasedRecord($sponsorship, $identityNumber, $updates, $userId, 'father');
                        break;

                    case 'deceased_mother':
                        $result = $this->handleDeceasedRecord($sponsorship, $identityNumber, $updates, $userId, 'mother');
                        break;

                    default:
                        Log::warning('Unknown guardian person type', ['type' => $personType]);
                }
            }

            // تحديث نوع الشخص في جدول sponsorships
            DB::table('sponsorships')
                ->where('id', $sponsorship->id)
                ->update([
                    'person_type' => $personType,
                    'updated_at' => now()
                ]);

            Log::info('Guardian person type handled', [
                'sponsorship_id' => $sponsorship->id,
                'person_type' => $personType,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle guardian person type', [
                'error' => $e->getMessage(),
                'person_type' => $personType,
                'identity_number' => $identityNumber
            ]);
        }

        return $result;
    }

    /**
     * التحقق الشامل من تكرار رقم الهوية في جميع الجداول
     * يبحث في: data, dead_people, re_people
     */
    private function checkIdentityDuplication(string $identityNumber): array
    {
        $result = [
            'exists' => false,
            'found_in' => null,
            'file_id' => null,
            'record_id' => null,
            'person_data' => null
        ];

        // 1. البحث في جدول data (المعيلين)
        $dataRecord = DB::table('data')
            ->where('data_id_number', $identityNumber)
            ->first();

        if ($dataRecord) {
            return [
                'exists' => true,
                'found_in' => 'data',
                'file_id' => $dataRecord->file_id_number,
                'record_id' => $dataRecord->id,
                'person_data' => $dataRecord
            ];
        }

        // 2. البحث في جدول dead_people (المتوفين)
        // البحث كأب متوفي
        $deadFather = DB::table('dead_people')
            ->where('father_id', $identityNumber)
            ->first();

        if ($deadFather) {
            return [
                'exists' => true,
                'found_in' => 'dead_people',
                'found_as' => 'father',
                'file_id' => $deadFather->re_file_id,
                'record_id' => $deadFather->id,
                'person_data' => $deadFather
            ];
        }

        // البحث كأم متوفية
        $deadMother = DB::table('dead_people')
            ->where('mother_id', $identityNumber)
            ->first();

        if ($deadMother) {
            return [
                'exists' => true,
                'found_in' => 'dead_people',
                'found_as' => 'mother',
                'file_id' => $deadMother->re_file_id,
                'record_id' => $deadMother->id,
                'person_data' => $deadMother
            ];
        }

        // 3. البحث في جدول re_people (أفراد العائلة)
        $rePeopleRecord = DB::table('re_people')
            ->where('person_id', $identityNumber)
            ->first();

        if ($rePeopleRecord) {
            return [
                'exists' => true,
                'found_in' => 're_people',
                'file_id' => $rePeopleRecord->registration_id,
                'record_id' => $rePeopleRecord->id,
                'person_data' => $rePeopleRecord
            ];
        }

        return $result;
    }

    /**
     * تحديث سجل المعيل الموجود (منع التكرار)
     */
    private function updateExistingGuardianRecord($sponsorship, array $duplicateCheck, array $updates, int $userId): array
    {
        $result = [
            'action' => 'updated_existing',
            'table' => $duplicateCheck['found_in'],
            'record_id' => $duplicateCheck['record_id'],
            'created' => false,
            'duplicate_prevented' => true
        ];

        try {
            switch ($duplicateCheck['found_in']) {
                case 'data':
                    // تحديث سجل في جدول data
                    $updateData = $this->buildDataUpdateArray($updates);
                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('data')
                            ->where('id', $duplicateCheck['record_id'])
                            ->update($updateData);
                    }

                    // تحديث relation_id_number في sponsorships
                    if (!empty($duplicateCheck['file_id'])) {
                        DB::table('sponsorships')
                            ->where('id', $sponsorship->id)
                            ->update([
                                'relation_id_number' => $duplicateCheck['file_id'],
                                'updated_at' => now()
                            ]);
                    }
                    break;

                case 'dead_people':
                    // تحديث سجل في جدول dead_people
                    $fullName = trim(implode(' ', array_filter([
                        $updates['guardian_first_name'] ?? '',
                        $updates['guardian_father_name'] ?? '',
                        $updates['guardian_grandfather_name'] ?? '',
                        $updates['guardian_family_name'] ?? ''
                    ])));

                    $nameColumn = ($duplicateCheck['found_as'] ?? 'father') === 'father' ? 'father_name' : 'mother_name';

                    DB::table('dead_people')
                        ->where('id', $duplicateCheck['record_id'])
                        ->update([
                            $nameColumn => $fullName,
                            'updated_at' => now()
                        ]);

                    // تحديث relation_id_number في sponsorships
                    if (!empty($duplicateCheck['file_id'])) {
                        DB::table('sponsorships')
                            ->where('id', $sponsorship->id)
                            ->update([
                                'relation_id_number' => $duplicateCheck['file_id'],
                                'updated_at' => now()
                            ]);
                    }
                    break;

                case 're_people':
                    // تحديث سجل في جدول re_people
                    $updateData = [];
                    if (isset($updates['guardian_first_name'])) $updateData['first_name'] = $updates['guardian_first_name'];
                    if (isset($updates['guardian_father_name'])) $updateData['second_name'] = $updates['guardian_father_name'];
                    if (isset($updates['guardian_grandfather_name'])) $updateData['third_name'] = $updates['guardian_grandfather_name'];
                    if (isset($updates['guardian_family_name'])) $updateData['last_name'] = $updates['guardian_family_name'];

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('re_people')
                            ->where('id', $duplicateCheck['record_id'])
                            ->update($updateData);
                    }

                    // تحديث relation_id_number في sponsorships
                    if (!empty($duplicateCheck['file_id'])) {
                        DB::table('sponsorships')
                            ->where('id', $sponsorship->id)
                            ->update([
                                'relation_id_number' => $duplicateCheck['file_id'],
                                'updated_at' => now()
                            ]);
                    }
                    break;
            }

            Log::info('Updated existing guardian record (duplicate prevented)', [
                'sponsorship_id' => $sponsorship->id,
                'found_in' => $duplicateCheck['found_in'],
                'record_id' => $duplicateCheck['record_id'],
                'file_id' => $duplicateCheck['file_id']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update existing guardian record', [
                'error' => $e->getMessage(),
                'duplicate_check' => $duplicateCheck
            ]);
        }

        return $result;
    }

    /**
     * معالجة سجل المعيل (جدول data)
     */
    private function handleBreadwinnerRecord($sponsorship, string $identityNumber, array $updates, int $userId): array
    {
        $result = ['action' => 'none', 'table' => 'data', 'record_id' => null, 'created' => false];

        // ⚠️ التحقق المزدوج من عدم التكرار قبل الإنشاء
        $duplicateCheck = $this->checkIdentityDuplication($identityNumber);
        if ($duplicateCheck['exists']) {
            Log::warning('Duplicate prevention: Identity already exists', [
                'identity_number' => $identityNumber,
                'found_in' => $duplicateCheck['found_in']
            ]);
            return $this->updateExistingGuardianRecord($sponsorship, $duplicateCheck, $updates, $userId);
        }

        // البحث عن السجل الموجود بواسطة رقم الهوية
        $existing = DB::table('data')
            ->where('data_id_number', $identityNumber)
            ->first();

        if ($existing) {
            // تحديث السجل الموجود
            $updateData = $this->buildDataUpdateArray($updates);
            if (!empty($updateData)) {
                $updateData['updated_at'] = now();
                DB::table('data')
                    ->where('id', $existing->id)
                    ->update($updateData);
            }

            $result['action'] = 'updated';
            $result['record_id'] = $existing->id;

            // تحديث relation_id_number في sponsorships إذا لم يكن موجوداً
            if (empty($sponsorship->relation_id_number) && !empty($existing->file_id_number)) {
                DB::table('sponsorships')
                    ->where('id', $sponsorship->id)
                    ->update(['relation_id_number' => $existing->file_id_number, 'updated_at' => now()]);
            }
        } else {
            // إنشاء سجل جديد
            $newFileId = $this->generateNewFileId('data');

            $insertData = [
                'file_id_number' => $newFileId,
                'data_id_number' => $identityNumber,
                'data_first_name' => $updates['guardian_first_name'] ?? '',
                'data_father_name' => $updates['guardian_father_name'] ?? '',
                'data_grand_father_name' => $updates['guardian_grandfather_name'] ?? '',
                'data_family_name' => $updates['guardian_family_name'] ?? '',
                'data_phone_number' => $updates['guardian_phone'] ?? null,
                'data_alt_phone_number' => $updates['guardian_phone2'] ?? null,
                'data_current_address' => $updates['guardian_detailed_address'] ?? null,
                'data_city' => $updates['guardian_city_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ];

            $newId = DB::table('data')->insertGetId($insertData);

            // تحديث relation_id_number في sponsorships
            DB::table('sponsorships')
                ->where('id', $sponsorship->id)
                ->update(['relation_id_number' => $newFileId, 'updated_at' => now()]);

            $result['action'] = 'created';
            $result['record_id'] = $newId;
            $result['created'] = true;
            $result['file_id'] = $newFileId;

            Log::info('New breadwinner record created', [
                'file_id' => $newFileId,
                'identity_number' => $identityNumber,
                'sponsorship_id' => $sponsorship->id
            ]);
        }

        return $result;
    }

    /**
     * معالجة سجل فرد العائلة (جدول re_people)
     */
    private function handleFamilyMemberRecord($sponsorship, string $identityNumber, array $updates, int $userId): array
    {
        $result = ['action' => 'none', 'table' => 're_people', 'record_id' => null, 'created' => false];

        // ⚠️ التحقق المزدوج من عدم التكرار قبل الإنشاء
        $duplicateCheck = $this->checkIdentityDuplication($identityNumber);
        if ($duplicateCheck['exists']) {
            Log::warning('Duplicate prevention (family_member): Identity already exists', [
                'identity_number' => $identityNumber,
                'found_in' => $duplicateCheck['found_in']
            ]);
            return $this->updateExistingGuardianRecord($sponsorship, $duplicateCheck, $updates, $userId);
        }

        // البحث عن السجل الموجود بواسطة رقم الهوية
        $existing = DB::table('re_people')
            ->where('person_id', $identityNumber)
            ->first();

        if ($existing) {
            // تحديث السجل الموجود
            $updateData = [];
            if (isset($updates['guardian_first_name'])) $updateData['first_name'] = $updates['guardian_first_name'];
            if (isset($updates['guardian_father_name'])) $updateData['second_name'] = $updates['guardian_father_name'];
            if (isset($updates['guardian_grandfather_name'])) $updateData['third_name'] = $updates['guardian_grandfather_name'];
            if (isset($updates['guardian_family_name'])) $updateData['last_name'] = $updates['guardian_family_name'];

            if (!empty($updateData)) {
                $updateData['updated_at'] = now();
                DB::table('re_people')
                    ->where('id', $existing->id)
                    ->update($updateData);
            }

            $result['action'] = 'updated';
            $result['record_id'] = $existing->id;

            // تحديث relation_id_number في sponsorships إذا لم يكن موجوداً
            if (empty($sponsorship->relation_id_number) && !empty($existing->registration_id)) {
                DB::table('sponsorships')
                    ->where('id', $sponsorship->id)
                    ->update(['relation_id_number' => $existing->registration_id, 'updated_at' => now()]);
            }
        } else {
            // إنشاء سجل جديد
            $newRegistrationId = $this->generateNewFileId('re_people');

            $insertData = [
                'registration_id' => $newRegistrationId,
                'person_id' => $identityNumber,
                'first_name' => $updates['guardian_first_name'] ?? '',
                'second_name' => $updates['guardian_father_name'] ?? '',
                'third_name' => $updates['guardian_grandfather_name'] ?? '',
                'last_name' => $updates['guardian_family_name'] ?? '',
                'created_at' => now(),
                'updated_at' => now()
            ];

            $newId = DB::table('re_people')->insertGetId($insertData);

            $result['action'] = 'created';
            $result['record_id'] = $newId;
            $result['created'] = true;
            $result['registration_id'] = $newRegistrationId;

            Log::info('New family member record created', [
                'registration_id' => $newRegistrationId,
                'identity_number' => $identityNumber,
                'sponsorship_id' => $sponsorship->id
            ]);
        }

        return $result;
    }

    /**
     * معالجة سجل المتوفي (جدول dead_people)
     */
    private function handleDeceasedRecord($sponsorship, string $identityNumber, array $updates, int $userId, string $type): array
    {
        $result = ['action' => 'none', 'table' => 'dead_people', 'record_id' => null, 'created' => false];

        // ⚠️ التحقق المزدوج من عدم التكرار قبل الإنشاء
        $duplicateCheck = $this->checkIdentityDuplication($identityNumber);
        if ($duplicateCheck['exists']) {
            Log::warning('Duplicate prevention (deceased): Identity already exists', [
                'identity_number' => $identityNumber,
                'found_in' => $duplicateCheck['found_in']
            ]);
            return $this->updateExistingGuardianRecord($sponsorship, $duplicateCheck, $updates, $userId);
        }

        // تحديد العمود حسب النوع (أب أو أم)
        $identityColumn = $type === 'father' ? 'father_id' : 'mother_id';
        $nameColumn = $type === 'father' ? 'father_name' : 'mother_name';

        // البحث عن السجل الموجود
        $existing = DB::table('dead_people')
            ->where($identityColumn, $identityNumber)
            ->first();

        // بناء الاسم الكامل
        $fullName = trim(implode(' ', array_filter([
            $updates['guardian_first_name'] ?? '',
            $updates['guardian_father_name'] ?? '',
            $updates['guardian_grandfather_name'] ?? '',
            $updates['guardian_family_name'] ?? ''
        ])));

        if ($existing) {
            // تحديث السجل الموجود
            $updateData = [$nameColumn => $fullName, 'updated_at' => now()];

            DB::table('dead_people')
                ->where('id', $existing->id)
                ->update($updateData);

            $result['action'] = 'updated';
            $result['record_id'] = $existing->id;

            // تحديث relation_id_number في sponsorships إذا لم يكن موجوداً
            if (empty($sponsorship->relation_id_number) && !empty($existing->re_file_id)) {
                DB::table('sponsorships')
                    ->where('id', $sponsorship->id)
                    ->update(['relation_id_number' => $existing->re_file_id, 'updated_at' => now()]);
            }
        } else {
            // إنشاء سجل جديد
            $newFileId = $this->generateNewFileId('dead_people');

            // نحتاج إلى ربط بسجل data موجود أو إنشائه
            $reFileId = $sponsorship->relation_id_number;
            if (empty($reFileId)) {
                // إنشاء سجل data أولاً
                $dataResult = $this->handleBreadwinnerRecord($sponsorship, $identityNumber, $updates, $userId);
                $reFileId = $dataResult['file_id'] ?? $newFileId;
            }

            $insertData = [
                're_file_id' => $reFileId,
                $identityColumn => $identityNumber,
                $nameColumn => $fullName,
                'created_at' => now(),
                'updated_at' => now()
            ];

            $newId = DB::table('dead_people')->insertGetId($insertData);

            $result['action'] = 'created';
            $result['record_id'] = $newId;
            $result['created'] = true;

            Log::info('New deceased record created', [
                'type' => $type,
                'identity_number' => $identityNumber,
                'sponsorship_id' => $sponsorship->id
            ]);
        }

        return $result;
    }

    /**
     * بناء مصفوفة التحديث لجدول data
     */
    private function buildDataUpdateArray(array $updates): array
    {
        $updateData = [];

        if (isset($updates['guardian_first_name'])) $updateData['data_first_name'] = $updates['guardian_first_name'];
        if (isset($updates['guardian_father_name'])) $updateData['data_father_name'] = $updates['guardian_father_name'];
        if (isset($updates['guardian_grandfather_name'])) $updateData['data_grand_father_name'] = $updates['guardian_grandfather_name'];
        if (isset($updates['guardian_family_name'])) $updateData['data_family_name'] = $updates['guardian_family_name'];
        if (isset($updates['guardian_phone'])) $updateData['data_phone_number'] = $updates['guardian_phone'];
        if (isset($updates['guardian_phone2'])) $updateData['data_alt_phone_number'] = $updates['guardian_phone2'];
        if (isset($updates['guardian_detailed_address'])) $updateData['data_current_address'] = $updates['guardian_detailed_address'];
        if (isset($updates['guardian_city_id'])) $updateData['data_city'] = $updates['guardian_city_id'];

        return $updateData;
    }

    /**
     * توليد رقم ملف جديد فريد
     */
    private function generateNewFileId(string $tableType): string
    {
        $prefix = match($tableType) {
            'data' => 'D',
            're_people' => 'R',
            'dead_people' => 'DP',
            default => 'X'
        };

        $timestamp = now()->format('ymdHis');
        $random = str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$timestamp}{$random}";
    }

    // ========================================
    // Initial Sync - المزامنة الأولية
    // ========================================

    /**
     * GET /api/mobile/sync/initial
     * المزامنة الأولية - جلب كل البيانات المطلوبة
     */
    public function getInitialSync(Request $request): JsonResponse
    {
        try {
            // الجمعيات
            $sponsors = DB::table('sponsors')
                ->select('id', 'sponsor_name as name', 'sponsor_short_name as short_name')
                ->whereNotNull('sponsor_name')
                ->orderBy('sponsor_name')
                ->get();

            // حالات الكفالة
            $statuses = DB::table('sponsorship_statuses')
                ->select('id', 'description as name')
                ->orderBy('id')
                ->get();

            // أسماء البنوك
            $bankNames = DB::table('bank_names')
                ->select('id', 'description')
                ->orderBy('description')
                ->get();

            // الحالات الصحية
            $healthStatuses = DB::table('health_statuses')
                ->select('id', 'description')
                ->orderBy('id')
                ->get();

            // المدن
            $cities = DB::table('city')
                ->select('id', 'city')
                ->orderBy('city')
                ->get();

            // أنواع الكفالة
            $sponsorshipTypes = DB::table('type_of_guarantee')
                ->select('id', 'description')
                ->orderBy('id')
                ->get();

            // إحصائيات الكفالات لكل جمعية وحالة
            $stats = DB::table('sponsorships')
                ->select(
                    'sponsor_id',
                    'sponsorship_status_id',
                    DB::raw('count(*) as count')
                )
                ->groupBy('sponsor_id', 'sponsorship_status_id')
                ->get();

            $totalSponsorships = DB::table('sponsorships')->count();

            return response()->json([
                'success' => true,
                'sync_timestamp' => now()->toISOString(),
                'data' => [
                    'sponsors' => $sponsors,
                    'sponsorship_statuses' => $statuses,
                    'bank_names' => $bankNames,
                    'health_statuses' => $healthStatuses,
                    'cities' => $cities,
                    'sponsorship_types' => $sponsorshipTypes,
                    'statistics' => [
                        'total_sponsorships' => $totalSponsorships,
                        'by_sponsor_status' => $stats
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Initial sync failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل المزامنة الأولية: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/mobile/sync/full
     * المزامنة الكاملة - جلب جميع الكفالات مع استبعاد (تم الصرف، أرسل للصرف)
     * يتم استدعاؤها عند الضغط على زر "مزامنة الآن"
     */
    public function getFullSync(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 100); // عدد أكبر للمزامنة الكاملة
            $lastSync = $request->get('last_sync');

            // الحصول على IDs الحالات المستبعدة
            $excludedStatusIds = DB::table('sponsorship_statuses')
                ->whereIn('description', ['تم الصرف', 'أرسل للصرف'])
                ->pluck('id')
                ->toArray();

            // بناء الاستعلام
            $query = DB::table('sponsorships')
                ->leftJoin('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
                ->leftJoin('sponsorship_statuses', 'sponsorships.sponsorship_status_id', '=', 'sponsorship_statuses.id')
                ->select([
                    'sponsorships.id',
                    'sponsorships.sponsor_id',
                    'sponsors.sponsor_name',
                    'sponsors.sponsor_short_name',
                    'sponsorships.internal_file_number',
                    'sponsorships.external_file_number',
                    'sponsorships.relation_id_number',
                    'sponsorships.identity_number',
                    'sponsorships.orphan_name',
                    'sponsorships.sponsored_birth_date',
                    'sponsorships.guardian_name',
                    'sponsorships.guardian_identity_number',
                    'sponsorships.sponsorship_status_id',
                    'sponsorship_statuses.description as status_name',
                    'sponsorships.sponsorship_start_date',
                    'sponsorships.sponsorship_end_date',
                    'sponsorships.person_type',
                    'sponsorships.notes',
                    'sponsorships.created_at',
                    'sponsorships.updated_at'
                ]);

            // استبعاد الحالات المكتملة
            if (!empty($excludedStatusIds)) {
                $query->whereNotIn('sponsorships.sponsorship_status_id', $excludedStatusIds);
            }

            // مزامنة تزايدية إذا تم توفير last_sync
            if ($lastSync) {
                $query->where('sponsorships.updated_at', '>', $lastSync);
            }

            // إجمالي السجلات
            $total = $query->count();

            // جلب البيانات
            $sponsorships = $query
                ->orderBy('sponsorships.updated_at', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            // إثراء البيانات
            $enrichedData = $sponsorships->map(function ($item) {
                return $this->enrichSponsorshipData($item);
            });

            // جلب IDs الكفالات التي يجب حذفها من الجهاز (تم الصرف أو أرسل للصرف)
            $deletedIds = [];
            if ($lastSync) {
                $deletedIds = DB::table('sponsorships')
                    ->whereIn('sponsorship_status_id', $excludedStatusIds)
                    ->where('updated_at', '>', $lastSync)
                    ->pluck('id')
                    ->toArray();
            }

            Log::info('Full sync request', [
                'page' => $page,
                'total' => $total,
                'downloaded' => $sponsorships->count(),
                'deleted_ids' => count($deletedIds)
            ]);

            return response()->json([
                'success' => true,
                'sync_timestamp' => now()->toISOString(),
                'data' => $enrichedData,
                'deleted_ids' => $deletedIds, // الكفالات التي يجب حذفها من الجهاز
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'has_more' => $page * $perPage < $total
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Full sync failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل المزامنة الكاملة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/mobile/sync/stats
     * إحصائيات المزامنة
     */
    public function getSyncStats(Request $request): JsonResponse
    {
        try {
            $lastSync = $request->get('last_sync');

            $totalSponsorships = DB::table('sponsorships')->count();
            $pendingUpdates = 0;

            if ($lastSync) {
                $pendingUpdates = DB::table('sponsorships')
                    ->where('updated_at', '>', $lastSync)
                    ->count();
            }

            // إحصائيات بالجمعية
            $bySponsors = DB::table('sponsorships')
                ->join('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
                ->select('sponsors.sponsor_name', DB::raw('count(*) as count'))
                ->groupBy('sponsors.sponsor_name')
                ->get();

            // إحصائيات بالحالة
            $byStatus = DB::table('sponsorships')
                ->join('sponsorship_statuses', 'sponsorships.sponsorship_status_id', '=', 'sponsorship_statuses.id')
                ->select('sponsorship_statuses.description as status_name', DB::raw('count(*) as count'))
                ->groupBy('sponsorship_statuses.description')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_sponsorships' => $totalSponsorships,
                    'pending_updates' => $pendingUpdates,
                    'by_sponsors' => $bySponsors,
                    'by_status' => $byStatus,
                    'last_check' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب الإحصائيات'
            ], 500);
        }
    }

    // ========================================
    // File Upload
    // ========================================

    /**
     * POST /api/mobile/upload-file
     * رفع ملف إلى Google Drive
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string',
            'file_type' => 'required|string',
            'file_data' => 'required|string',
            'folder_path' => 'nullable|string',
            'sponsorship_id' => 'required|integer'
        ]);

        try {
            // جلب بيانات الكفالة للحصول على اسم الجمعية واسم المكفول
            $sponsorship = DB::table('sponsorships')
                ->leftJoin('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
                ->where('sponsorships.id', $request->sponsorship_id)
                ->select([
                    'sponsorships.identity_number',
                    'sponsorships.orphan_name',
                    'sponsors.sponsor_name'
                ])
                ->first();

            if (!$sponsorship) {
                return response()->json([
                    'success' => false,
                    'message' => 'الكفالة غير موجودة'
                ], 404);
            }

            // بناء هيكل المجلدات الصحيح: temp/[اسم الجمعية]/[اسم المكفول أو رقم هويته]
            $organizationName = $sponsorship->sponsor_name ?: 'غير محدد';
            $orphanName = $sponsorship->orphan_name ?: $sponsorship->identity_number ?: 'غير محدد';

            // فك تشفير البيانات
            $fileData = base64_decode($request->file_data);

            // إنشاء اسم الملف
            $fileName = $request->file_name;

            // حفظ الملف محلياً أولاً
            $localPath = storage_path('app/mobile_uploads/' . $this->sanitizeFolderName($organizationName) . '/' . $this->sanitizeFolderName($orphanName));
            if (!file_exists($localPath)) {
                mkdir($localPath, 0755, true);
            }

            $fullPath = $localPath . '/' . $fileName;
            file_put_contents($fullPath, $fileData);

            // حساب hash للملف
            $fileHash = hash_file('sha256', $fullPath);
            $fileSize = strlen($fileData);

            // المسار في Google Drive
            $googleDrivePath = "temp/{$this->sanitizeFolderName($organizationName)}/{$this->sanitizeFolderName($orphanName)}/{$fileName}";

            // تسجيل في قاعدة البيانات
            $uploadId = DB::table('google_drive_uploads')->insertGetId([
                'local_file_path' => $fullPath,
                'local_file_hash' => $fileHash,
                'file_name' => $fileName,
                'file_size_bytes' => $fileSize,
                'mime_type' => $request->file_type,
                'google_drive_path' => $googleDrivePath,
                'upload_status' => 'pending',
                'upload_progress' => 0,
                'entity_type' => 'sponsorship',
                'entity_id' => (string)$request->sponsorship_id,
                'attachment_type' => str_contains($request->file_type, 'video') ? 'video' : 'photo',
                'device_id' => $request->header('X-Device-ID', 'unknown'),
                'uploaded_by' => $request->user()->id ?? 0,
                'retry_count' => 0,
                'synced_to_server' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // محاولة الرفع باستخدام Rclone (الطريقة الرئيسية على الخادم)
            $useRclone = env('USE_RCLONE_FOR_UPLOADS', false);

            if ($useRclone) {
                try {
                    $rcloneService = new \App\Services\RcloneGoogleDriveService();

                    // التحقق من اتصال Rclone
                    if ($rcloneService->testConnection()) {
                        // رفع الملف عبر Rclone
                        $result = $rcloneService->uploadFile(
                            $fullPath,
                            $organizationName,
                            $orphanName,
                            pathinfo($fileName, PATHINFO_FILENAME), // اسم الملف بدون الامتداد
                            pathinfo($fileName, PATHINFO_EXTENSION) // الامتداد
                        );

                        if ($result['success']) {
                            DB::table('google_drive_uploads')
                                ->where('id', $uploadId)
                                ->update([
                                    'google_drive_file_id' => $result['remote_path'] ?? null,
                                    'upload_status' => 'completed',
                                    'upload_progress' => 100,
                                    'synced_to_server' => true,
                                    'updated_at' => now()
                                ]);

                            Log::info('File uploaded via Rclone', [
                                'file' => $fileName,
                                'path' => $result['remote_path']
                            ]);

                            return response()->json([
                                'success' => true,
                                'message' => 'تم رفع الملف بنجاح',
                                'remote_path' => $result['remote_path'] ?? null,
                                'upload_id' => $uploadId
                            ]);
                        }
                    }
                } catch (\Exception $rcloneError) {
                    Log::warning('Rclone upload failed', [
                        'error' => $rcloneError->getMessage(),
                        'file' => $fileName
                    ]);
                }
            }

            // محاولة الرفع باستخدام Google Drive API (البيئة المحلية)
            try {
                if (class_exists(\App\Services\GoogleDriveService::class)) {
                    $driveService = app(\App\Services\GoogleDriveService::class);

                    $result = $driveService->uploadToPath($fullPath, "temp/{$organizationName}/{$orphanName}", $fileName);

                    if ($result) {
                        DB::table('google_drive_uploads')
                            ->where('id', $uploadId)
                            ->update([
                                'google_drive_file_id' => $result['id'] ?? null,
                                'upload_status' => 'completed',
                                'upload_progress' => 100,
                                'synced_to_server' => true,
                                'updated_at' => now()
                            ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'تم رفع الملف بنجاح',
                            'file_id' => $result['id'] ?? null,
                            'upload_id' => $uploadId
                        ]);
                    }
                }
            } catch (\Exception $driveError) {
                Log::warning('Google Drive API upload failed, file saved locally', [
                    'error' => $driveError->getMessage(),
                    'file' => $fileName
                ]);
            }

            // الملف محفوظ محلياً في انتظار الرفع
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ الملف محلياً وفي انتظار الرفع إلى Google Drive',
                'upload_id' => $uploadId,
                'status' => 'pending'
            ]);

        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل رفع الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تنظيف اسم المجلد من الأحرف غير المسموحة
     */
    private function sanitizeFolderName(string $name): string
    {
        // إزالة الأحرف غير المسموحة في أسماء المجلدات
        $name = preg_replace('/[<>:"\/\\|?*]/', '_', $name);
        // إزالة المسافات الزائدة
        $name = preg_replace('/\s+/', ' ', $name);
        // إزالة النقاط في البداية والنهاية
        $name = trim($name, '. ');

        return $name ?: 'unnamed';
    }

    // ========================================
    // Utilities
    // ========================================

    /**
     * Normalize Arabic text for search
     */
    private function normalizeArabicText(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = str_replace(['ى', 'ئ'], 'ي', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ؤ', 'و', $text);
        return trim($text);
    }

    /**
     * حفظ أو تحديث قيم الحقول المخصصة في جدول portal_general_registration_field_values
     * يستخدم لحفظ بيانات المتوفين كالعنوان والهاتف وغيرها
     *
     * @param int $sponsorshipId معرف الكفالة
     * @param string $fileIdNumber رقم الملف
     * @param string|null $identityNumber رقم الهوية
     * @param string $fieldKey مفتاح الحقل (مثل: deceased_address, deceased_phone)
     * @param mixed $fieldValue قيمة الحقل
     * @param int|null $userId معرف المستخدم الذي يقوم بالتحديث
     * @return bool نجاح العملية
     */
    private function savePortalFieldValue(
        int $sponsorshipId,
        string $fileIdNumber,
        ?string $identityNumber,
        string $fieldKey,
        $fieldValue,
        ?int $userId = null
    ): bool {
        try {
            // التحقق من وجود السجل
            $existing = DB::table('portal_general_registration_field_values')
                ->where('sponsorship_id', $sponsorshipId)
                ->where('field_key', $fieldKey)
                ->first();

            if ($existing) {
                // تحديث السجل الموجود
                DB::table('portal_general_registration_field_values')
                    ->where('id', $existing->id)
                    ->update([
                        'field_value' => $fieldValue,
                        'updated_by_user_id' => $userId,
                        'updated_at' => now()
                    ]);

                Log::info('✅ تم تحديث قيمة الحقل في portal_general_registration_field_values', [
                    'sponsorship_id' => $sponsorshipId,
                    'field_key' => $fieldKey,
                    'old_value' => $existing->field_value,
                    'new_value' => $fieldValue
                ]);
            } else {
                // إنشاء سجل جديد
                DB::table('portal_general_registration_field_values')->insert([
                    'sponsorship_id' => $sponsorshipId,
                    'file_id_number' => $fileIdNumber,
                    'identity_number' => $identityNumber,
                    'field_key' => $fieldKey,
                    'field_value' => $fieldValue,
                    'updated_by_user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info('✅ تم إنشاء قيمة حقل جديدة في portal_general_registration_field_values', [
                    'sponsorship_id' => $sponsorshipId,
                    'field_key' => $fieldKey,
                    'field_value' => $fieldValue
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('❌ فشل حفظ قيمة الحقل في portal_general_registration_field_values', [
                'error' => $e->getMessage(),
                'sponsorship_id' => $sponsorshipId,
                'field_key' => $fieldKey
            ]);
            return false;
        }
    }

    /**
     * حفظ بيانات المتوفين الإضافية (العنوان، الهاتف، إلخ) في portal_general_registration_field_values
     *
     * @param object $sponsorship كائن الكفالة
     * @param array $updates التحديثات
     * @param string $personType نوع الشخص (deceased_father أو deceased_mother)
     * @param int|null $userId معرف المستخدم
     */
    private function saveDeceasedExtraData($sponsorship, array $updates, string $personType, ?int $userId = null): void
    {
        $prefix = ($personType === 'deceased_father') ? 'deceased_father_' : 'deceased_mother_';

        // حفظ العنوان
        if (isset($updates['guardian_detailed_address'])) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $sponsorship->relation_id_number ?? '',
                $updates['identity_number'] ?? null,
                $prefix . 'address',
                $updates['guardian_detailed_address'],
                $userId
            );
        }

        // حفظ رقم الهاتف
        if (isset($updates['guardian_phone'])) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $sponsorship->relation_id_number ?? '',
                $updates['identity_number'] ?? null,
                $prefix . 'phone',
                $updates['guardian_phone'],
                $userId
            );
        }

        // حفظ رقم الهاتف البديل
        if (isset($updates['guardian_phone2'])) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $sponsorship->relation_id_number ?? '',
                $updates['identity_number'] ?? null,
                $prefix . 'phone2',
                $updates['guardian_phone2'],
                $userId
            );
        }

        // حفظ المدينة
        if (isset($updates['guardian_city_id'])) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $sponsorship->relation_id_number ?? '',
                $updates['identity_number'] ?? null,
                $prefix . 'city_id',
                $updates['guardian_city_id'],
                $userId
            );
        }

        // حفظ الحالة الصحية
        if (isset($updates['health_status_id'])) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $sponsorship->relation_id_number ?? '',
                $updates['identity_number'] ?? null,
                $prefix . 'health_status',
                $updates['health_status_id'],
                $userId
            );
        }
    }
}
