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
            } elseif (!empty($sponsorship->orphan_name)) {
                // الاسم موجود في sponsorships - نحاول تقسيمه
                $nameParts = $this->splitArabicName($sponsorship->orphan_name);
                $result['orphan_first_name'] = $nameParts['first_name'];
                $result['orphan_father_name'] = $nameParts['father_name'];
                $result['orphan_grandfather_name'] = $nameParts['grand_father_name'];
                $result['orphan_family_name'] = $nameParts['family_name'];
                $result['orphan_name_combined'] = $sponsorship->orphan_name; // الاسم المدمج الأصلي
                $result['needs_orphan_name_input'] = true; // يحتاج تأكيد/تعديل
            } else {
                $result['needs_orphan_name_input'] = true;
                $result['orphan_name_message'] = 'لم يتم العثور على بيانات. يرجى إدخال الاسم الرباعي.';
            }
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
                        'guardian_bank_accounts.account_number',
                        'guardian_bank_accounts.iban',
                        'guardian_bank_accounts.account_holder_name',
                        'guardian_bank_accounts.bank_name as bank_id',
                        'bank_names.description as bank_name_text'
                    ])
                    ->get();

                $result['bank_accounts'] = $bankAccounts->toArray();
            } catch (\Exception $e) {
                Log::warning('Failed to get bank accounts', ['error' => $e->getMessage()]);
            }
        }

        // جلب بيانات الاتصال للمعيل (العنوان والهاتف) من جدول data
        if (!empty($sponsorship->relation_id_number)) {
            try {
                $guardianInfo = DB::table('data')
                    ->where('registration_id', $sponsorship->relation_id_number)
                    ->select(['detailed_address', 'phone', 'phone2'])
                    ->first();

                if ($guardianInfo) {
                    $result['guardian_detailed_address'] = $guardianInfo->detailed_address ?? '';
                    $result['guardian_phone'] = $guardianInfo->phone ?? '';
                    $result['guardian_phone2'] = $guardianInfo->phone2 ?? '';
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

            // تحديد الحقول المسموح بتحديثها
            $allowedFields = [
                'orphan_name', 'sponsored_birth_date', 'guardian_name',
                'notes', 'sponsorship_status_id'
            ];

            $filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));
            $filteredUpdates['updated_at'] = now();
            $filteredUpdates['updated_by'] = $request->user()->id;

            DB::table('sponsorships')
                ->where('id', $sponsorshipId)
                ->update($filteredUpdates);

            Log::info('Sponsorship updated from mobile', [
                'sponsorship_id' => $sponsorshipId,
                'user_id' => $request->user()->id,
                'updates' => array_keys($filteredUpdates)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
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
            'folder_path' => 'required|string',
            'sponsorship_id' => 'required|integer'
        ]);

        try {
            // فك تشفير البيانات
            $fileData = base64_decode($request->file_data);

            // تغيير المسار من alhayah إلى temp
            $folderPath = str_replace('alhayah/', 'temp/', $request->folder_path);
            if (!str_starts_with($folderPath, 'temp/')) {
                $folderPath = 'temp/' . ltrim($folderPath, '/');
            }

            // إنشاء اسم الملف
            $fileName = $request->file_name;

            // حفظ الملف محلياً أولاً
            $localPath = storage_path('app/mobile_uploads/' . $folderPath);
            if (!file_exists($localPath)) {
                mkdir($localPath, 0755, true);
            }

            $fullPath = $localPath . '/' . $fileName;
            file_put_contents($fullPath, $fileData);

            // حساب hash للملف
            $fileHash = hash_file('sha256', $fullPath);
            $fileSize = strlen($fileData);

            // تسجيل في قاعدة البيانات بالأعمدة الصحيحة
            $uploadId = DB::table('google_drive_uploads')->insertGetId([
                'local_file_path' => $fullPath,
                'local_file_hash' => $fileHash,
                'file_name' => $fileName,
                'file_size_bytes' => $fileSize,
                'mime_type' => $request->file_type,
                'google_drive_path' => $folderPath . '/' . $fileName,
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

            // محاولة الرفع إلى Google Drive
            try {
                if (class_exists(\App\Services\GoogleDriveService::class)) {
                    $driveService = app(\App\Services\GoogleDriveService::class);

                    $result = $driveService->uploadToPath($fullPath, $folderPath, $fileName);

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
                Log::warning('Google Drive upload failed, file saved locally', [
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
