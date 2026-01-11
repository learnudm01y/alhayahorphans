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

        // تقسيم اسم المكفول إلى أربعة حقول
        $result['orphan_first_name'] = '';
        $result['orphan_father_name'] = '';
        $result['orphan_grandfather_name'] = '';
        $result['orphan_family_name'] = '';

        // تقسيم اسم المعيل إلى أربعة حقول
        $result['guardian_first_name'] = '';
        $result['guardian_father_name'] = '';
        $result['guardian_grandfather_name'] = '';
        $result['guardian_family_name'] = '';

        // جلب بيانات المكفول من السجل المدني
        if (!empty($sponsorship->identity_number)) {
            $civilData = $this->getPersonFromCivilRegistry($sponsorship->identity_number);
            if ($civilData) {
                // الاسم موجود في السجل المدني - مقسم
                $result['orphan_first_name'] = $civilData['first_name'];
                $result['orphan_father_name'] = $civilData['father_name'];
                $result['orphan_grandfather_name'] = $civilData['grand_father_name'];
                $result['orphan_family_name'] = $civilData['family_name'];
                $result['orphan_name'] = $civilData['full_name'];
                $result['sponsored_birth_date'] = $civilData['birth_date'] ?? $sponsorship->sponsored_birth_date;
                $result['orphan_gender'] = $civilData['gender'];
                $result['orphan_data_source'] = 'civil_registry';
            } else {
                // الاسم غير موجود في السجل المدني - نبحث في جداول أخرى
                if (!empty($sponsorship->orphan_name)) {
                    $nameParts = $this->splitArabicName($sponsorship->orphan_name);
                    $result['orphan_first_name'] = $nameParts['first_name'];
                    $result['orphan_father_name'] = $nameParts['father_name'];
                    $result['orphan_grandfather_name'] = $nameParts['grand_father_name'];
                    $result['orphan_family_name'] = $nameParts['family_name'];
                    $result['orphan_name_combined'] = $sponsorship->orphan_name;
                    $result['needs_orphan_name_input'] = true;
                }

                // جلب الجنس من جداول بديلة
                $result['orphan_gender'] = $this->getGenderFromAlternativeSources($sponsorship->identity_number);
            }
        }

        // إذا لم يتم جلب الجنس بعد، نحاول من جداول أخرى
        if (empty($result['orphan_gender']) && !empty($sponsorship->identity_number)) {
            $result['orphan_gender'] = $this->getGenderFromAlternativeSources($sponsorship->identity_number);
        }

        // جلب بيانات المعيل من السجل المدني
        if (!empty($sponsorship->guardian_identity_number)) {
            $guardianData = $this->getPersonFromCivilRegistry($sponsorship->guardian_identity_number);
            if ($guardianData) {
                $result['guardian_first_name'] = $guardianData['first_name'];
                $result['guardian_father_name'] = $guardianData['father_name'];
                $result['guardian_grandfather_name'] = $guardianData['grand_father_name'];
                $result['guardian_family_name'] = $guardianData['family_name'];
                $result['guardian_name'] = $guardianData['full_name'];
                $result['guardian_data_source'] = 'civil_registry';
            } elseif (!empty($sponsorship->guardian_name)) {
                $nameParts = $this->splitArabicName($sponsorship->guardian_name);
                $result['guardian_first_name'] = $nameParts['first_name'];
                $result['guardian_father_name'] = $nameParts['father_name'];
                $result['guardian_grandfather_name'] = $nameParts['grand_father_name'];
                $result['guardian_family_name'] = $nameParts['family_name'];
                $result['guardian_name_combined'] = $sponsorship->guardian_name;
                $result['needs_guardian_name_input'] = true;
            }
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
                    ->select(['data_current_address', 'data_phone_number', 'data_alt_phone_number'])
                    ->first();

                if ($guardianInfo) {
                    $result['guardian_detailed_address'] = $guardianInfo->data_current_address ?? '';
                    $result['guardian_phone'] = $guardianInfo->data_phone_number ?? '';
                    $result['guardian_phone2'] = $guardianInfo->data_alt_phone_number ?? '';
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get guardian contact info', ['error' => $e->getMessage()]);
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

            // تحديد الحقول المسموح بتحديثها
            $allowedFields = [
                // بيانات المكفول
                'orphan_name', 'orphan_first_name', 'orphan_father_name',
                'orphan_grandfather_name', 'orphan_family_name',
                'identity_number', 'sponsored_birth_date', 'orphan_gender',
                // بيانات المعيل (فقط الحقول الموجودة في جدول sponsorships)
                'guardian_name', 'guardian_identity_number',
                // بيانات أخرى
                'notes', 'sponsorship_status_id', 'person_type'
            ];

            // الحقول التي تذهب إلى جدول data وليس sponsorships
            $dataOnlyFields = [
                'health_status_id', 'guardian_phone', 'guardian_phone2',
                'guardian_city_id', 'guardian_detailed_address',
                'guardian_first_name', 'guardian_father_name',
                'guardian_grandfather_name', 'guardian_family_name',
                'guardian_person_type'
            ];

            $filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));
            $filteredUpdates['updated_at'] = now();
            $filteredUpdates['updated_by'] = $request->user()->id;

            // التحقق من تغيير اسم المكفول لتحديث مجلد Google Drive
            $oldOrphanName = $updates['old_orphan_name'] ?? null;
            $newOrphanName = $filteredUpdates['orphan_name'] ?? null;
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

            foreach ($bankUpdates as $index => $updates) {
                if (!isset($accounts[$index])) continue;

                $account = $accounts[$index];
                $updateData = [];

                if (isset($updates['bank_name_id'])) {
                    $updateData['bank_name'] = $updates['bank_name_id'];
                }
                if (isset($updates['account_holder_name'])) {
                    $updateData['re_guardian_name'] = $updates['account_holder_name'];
                }
                if (isset($updates['account_holder_identity'])) {
                    $updateData['person_owner_identity_number'] = $updates['account_holder_identity'];
                }
                if (isset($updates['account_holder_phone'])) {
                    $updateData['account_holder_phone'] = $updates['account_holder_phone'];
                }
                if (isset($updates['iban_usd'])) {
                    $updateData['iban_usd'] = $updates['iban_usd'];
                }
                if (isset($updates['iban_shekel'])) {
                    $updateData['iban_shekel'] = $updates['iban_shekel'];
                }

                if (!empty($updateData)) {
                    $updateData['updated_at'] = now();
                    DB::table('guardian_bank_accounts')
                        ->where('id', $account->id)
                        ->update($updateData);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to update bank accounts', ['error' => $e->getMessage()]);
        }
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

            // نحتاج إلى file_id للربط، نستخدم relation_id_number من الكفالة أو ننشئ سجل data أولاً
            $fileId = $sponsorship->relation_id_number;
            if (empty($fileId)) {
                // إنشاء سجل data أولاً إذا لم يكن موجوداً
                $dataResult = $this->handleBreadwinnerRecord($sponsorship, $identityNumber, $updates, $userId);
                $fileId = $dataResult['file_id'] ?? null;
            }

            $insertData = [
                'registration_id' => $newRegistrationId,
                'person_id' => $identityNumber,
                'file_id' => $fileId,
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
}
