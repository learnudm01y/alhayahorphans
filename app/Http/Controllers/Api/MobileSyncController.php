<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RePeople;
use App\Models\Data;
use App\Models\SponsorshipStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Controller: MobileSyncController
 *
 * Purpose: Handle mobile app authentication and initial data sync
 *
 * Features:
 * - User login with role verification (admin only)
 * - Fetch associations (representative_of_association)
 * - Fetch sponsorship statuses
 * - Fetch orphans by association and status with smart search
 */
class MobileSyncController extends Controller
{
    /**
     * POST /api/mobile/login
     *
     * Authenticate user with role check
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string'
        ]);

        try {
            // Find user by name, email, or phone
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

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور غير صحيحة',
                    'error_code' => 'INVALID_PASSWORD'
                ], 401);
            }

            // Check role (admin only)
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية الدخول. مطلوب صلاحية مدير.',
                    'error_code' => 'INSUFFICIENT_PERMISSIONS',
                    'current_role' => $user->role
                ], 403);
            }

            // Create API token
            $token = $user->createToken('mobile-app-token', ['*'])->plainTextToken;

            // Log successful login
            Log::info('Mobile user logged in', [
                'user_id' => $user->id,
                'username' => $user->name,
                'device_id' => $request->device_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'avatar' => $user->avatar
                ],
                'token' => $token,
                'expires_at' => now()->addDays(30)->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Mobile login failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تسجيل الدخول',
                'error_code' => 'SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * POST /api/mobile/logout
     *
     * Logout user and revoke tokens
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الخروج بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تسجيل الخروج'
            ], 500);
        }
    }

    /**
     * GET /api/mobile/associations
     *
     * Get all associations (sponsors) for filtering
     */
    public function getAssociations(): JsonResponse
    {
        try {
            // الجمعيات موجودة في جدول sponsors
            $associations = DB::table('sponsors')
                ->select('id', 'sponsor_name as name', 'sponsor_short_name as short_name')
                ->whereNotNull('sponsor_name')
                ->orderBy('sponsor_name')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $associations->count(),
                'associations' => $associations
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch associations', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => true,
                'count' => 0,
                'associations' => [],
                'note' => 'جدول الجمعيات غير متاح: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/mobile/sponsorship-statuses
     *
     * Get all sponsorship statuses for filtering
     */
    public function getSponsorshipStatuses(): JsonResponse
    {
        try {
            $statuses = SponsorshipStatus::select('id', 'description')
                ->orderBy('id')
                ->get()
                ->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'name' => $status->description
                    ];
                });

            return response()->json([
                'success' => true,
                'count' => $statuses->count(),
                'statuses' => $statuses
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sponsorship statuses', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'فشل جلب حالات الكفالة'
            ], 500);
        }
    }

    /**
     * GET /api/mobile/initial-sync
     *
     * Get all initial data needed by the mobile app in one call
     */
    public function getInitialSync(Request $request): JsonResponse
    {
        try {
            // Associations (sponsors)
            $associations = DB::table('sponsors')
                ->select('id', 'sponsor_name as name', 'sponsor_short_name as short_name')
                ->whereNotNull('sponsor_name')
                ->orderBy('sponsor_name')
                ->get();

            // Sponsorship statuses
            $statuses = SponsorshipStatus::select('id', 'description as name')
                ->orderBy('id')
                ->get();

            // Count of orphans by status
            $orphanCounts = RePeople::select('sponsorship_status', DB::raw('count(*) as count'))
                ->groupBy('sponsorship_status')
                ->get();

            return response()->json([
                'success' => true,
                'sync_timestamp' => now()->toISOString(),
                'data' => [
                    'associations' => $associations,
                    'sponsorship_statuses' => $statuses,
                    'orphan_counts' => $orphanCounts
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
     * GET /api/mobile/orphans
     *
     * Get orphans filtered by sponsor and status with smart search
     */
    public function getOrphans(Request $request): JsonResponse
    {
        try {
            $sponsorId = $request->get('sponsor_id');
            $statusId = $request->get('status_id');
            $search = $request->get('search', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50);

            $query = RePeople::query();

            // Filter by sponsorship status
            if ($statusId !== null && $statusId !== '') {
                $query->where('sponsorship_status', $statusId);
            }

            // Smart search
            if (!empty($search)) {
                $normalizedSearch = $this->normalizeArabicText($search);

                $query->where(function ($q) use ($search, $normalizedSearch) {
                    // Search by identity number (exact or partial)
                    $q->where('person_id', 'LIKE', "%{$search}%")
                      // Search by registration ID (file number)
                      ->orWhere('registration_id', 'LIKE', "%{$search}%")
                      // Search by normalized first name
                      ->orWhere('first_name_normalized', 'LIKE', "%{$normalizedSearch}%")
                      // Search by original first name
                      ->orWhere('first_name', 'LIKE', "%{$search}%")
                      // Search by last name
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      // Search by second name
                      ->orWhere('second_name', 'LIKE', "%{$search}%")
                      // Search by third name
                      ->orWhere('third_name', 'LIKE', "%{$search}%");
                });
            }

            // Paginate results
            $orphans = $query->select([
                'id',
                'registration_id',
                'first_name',
                'second_name',
                'third_name',
                'last_name',
                'person_id',
                'person_gender',
                'person_birth_date',
                'person_age',
                'sponsorship_status',
                'person_health_status'
            ])
            ->orderBy('first_name')
            ->paginate($perPage, ['*'], 'page', $page);

            // Format response
            $formattedOrphans = $orphans->map(function ($orphan) {
                return [
                    'id' => $orphan->id,
                    'registration_id' => $orphan->registration_id,
                    'full_name' => trim(
                        ($orphan->first_name ?? '') . ' ' .
                        ($orphan->second_name ?? '') . ' ' .
                        ($orphan->third_name ?? '') . ' ' .
                        ($orphan->last_name ?? '')
                    ),
                    'first_name' => $orphan->first_name,
                    'second_name' => $orphan->second_name,
                    'third_name' => $orphan->third_name,
                    'last_name' => $orphan->last_name,
                    'identity_number' => $orphan->person_id,
                    'gender' => $orphan->person_gender == 1 ? 'ذكر' : ($orphan->person_gender == 2 ? 'أنثى' : ''),
                    'birth_date' => $orphan->person_birth_date,
                    'age' => $orphan->person_age,
                    'sponsorship_status' => $orphan->sponsorship_status,
                    'health_status' => $orphan->person_health_status
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedOrphans,
                'pagination' => [
                    'current_page' => $orphans->currentPage(),
                    'per_page' => $orphans->perPage(),
                    'total' => $orphans->total(),
                    'last_page' => $orphans->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch orphans', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'فشل جلب بيانات الأيتام: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/mobile/orphan/{registrationId}
     *
     * Get full orphan details with guardian and bank account
     */
    public function getOrphanDetails(string $registrationId): JsonResponse
    {
        try {
            // Get orphan data
            $orphan = RePeople::where('registration_id', $registrationId)->first();

            if (!$orphan) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على اليتيم'
                ], 404);
            }

            // Get guardian data
            $guardian = Data::where('file_id_number', $registrationId)->first();

            // Get bank accounts
            $bankAccounts = [];
            if ($guardian) {
                $bankAccounts = DB::table('guardian_bank_accounts')
                    ->where('guardian_registration', $registrationId)
                    ->get();
            }

            // Get sponsorship status name
            $statusName = null;
            if ($orphan->sponsorship_status) {
                $status = SponsorshipStatus::find($orphan->sponsorship_status);
                $statusName = $status ? $status->description : null;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'orphan' => [
                        'registration_id' => $orphan->registration_id,
                        'full_name' => trim(
                            ($orphan->first_name ?? '') . ' ' .
                            ($orphan->second_name ?? '') . ' ' .
                            ($orphan->third_name ?? '') . ' ' .
                            ($orphan->last_name ?? '')
                        ),
                        'first_name' => $orphan->first_name,
                        'second_name' => $orphan->second_name,
                        'third_name' => $orphan->third_name,
                        'last_name' => $orphan->last_name,
                        'identity_number' => $orphan->person_id,
                        'gender' => $orphan->person_gender,
                        'birth_date' => $orphan->person_birth_date,
                        'age' => $orphan->person_age,
                        'sponsorship_status' => $orphan->sponsorship_status,
                        'sponsorship_status_name' => $statusName,
                        'health_status' => $orphan->person_health_status,
                        'academic_degree' => $orphan->acadimic_degree,
                        'note' => $orphan->person_note
                    ],
                    'guardian' => $guardian ? [
                        'file_id' => $guardian->file_id_number,
                        'full_name' => trim(
                            ($guardian->data_first_name ?? '') . ' ' .
                            ($guardian->data_father_name ?? '') . ' ' .
                            ($guardian->data_grand_father_name ?? '') . ' ' .
                            ($guardian->data_family_name ?? '')
                        ),
                        'first_name' => $guardian->data_first_name,
                        'father_name' => $guardian->data_father_name,
                        'grand_father_name' => $guardian->data_grand_father_name,
                        'family_name' => $guardian->data_family_name,
                        'identity_number' => $guardian->data_id_number,
                        'phone' => $guardian->data_phone_number,
                        'alt_phone' => $guardian->data_alt_phone_number,
                        'province' => $guardian->data_province,
                        'city' => $guardian->data_city,
                        'address' => $guardian->data_current_address
                    ] : null,
                    'bank_accounts' => $bankAccounts
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch orphan details', [
                'registration_id' => $registrationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل جلب تفاصيل اليتيم'
            ], 500);
        }
    }

    /**
     * Normalize Arabic text for smart search
     */
    private function normalizeArabicText(string $text): string
    {
        // Remove diacritics (tashkeel)
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);

        // Normalize alef variations
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);

        // Normalize ya and alef maqsura
        $text = str_replace(['ى', 'ئ'], 'ي', $text);

        // Normalize ha and ta marbuta
        $text = str_replace('ة', 'ه', $text);

        // Normalize waw with hamza
        $text = str_replace('ؤ', 'و', $text);

        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }
}
