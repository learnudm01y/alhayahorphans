<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship;
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
        Log::info('🔐 [MOBILE LOGIN] طلب تسجيل دخول', ['username' => $request->username]);

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string'
        ]);

        try {
            $user = User::where('email', $request->username)
                ->orWhere('name', $request->username)
                ->orWhere('phone', $request->username)
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الدخول غير صحيحة'
                ], 401);
            }

            $token = $user->createToken('mobile-token', ['*'], now()->addDays(30))->plainTextToken;

            if ($request->device_id) {
                $user->device_id = $request->device_id;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'token' => $token,
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [MOBILE LOGIN] خطأ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في الخادم'
            ], 500);
        }
    }

    /**
     * POST /api/mobile/refresh-token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);
        
        $refreshToken = clone DB::table('refresh_tokens')
            ->where('token', hash('sha256', $request->refresh_token))
            ->where('expires_at', '>', now())
            ->first();
        
        if (!$refreshToken) {
            return response()->json(['success' => false, 'message' => 'Refresh token expired or invalid'], 401);
        }
        
        $user = User::find($refreshToken->user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 401);
        }

        $user->tokens()->where('name', 'mobile-app-token')->delete();
        $newToken = $user->createToken('mobile-app-token', ['*'])->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token' => $newToken,
            'expires_at' => now()->addDays(30)->toISOString()
        ]);
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
                    'sponsorships.sponsoring_organization', // اسم الكافل
                    'sponsorships.notes',
                    'sponsorships.created_at',
                    'sponsorships.updated_at'
                ]);

            // (تم إزالة استبعاد الحالات "تم الصرف" و "أرسل للصرف" بناءً على طلب المستخدم)

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
                try {
                    $lastSyncFormatted = \Carbon\Carbon::parse($lastSync)->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
                    $query->where('sponsorships.updated_at', '>', $lastSyncFormatted);
                } catch (\Exception $e) {
                    $query->where('sponsorships.updated_at', '>', $lastSync);
                }
            }

            // ترتيب وتقسيم
            $total = $query->count();
            $sponsorships = $query->orderBy('sponsorships.updated_at', 'desc')
                ->orderBy('sponsorships.id', 'desc') // حل مشكلة تخطي بعض الكفالات في الـ Pagination
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

    public static function getSingleEnrichedSponsorship($id)
    {
        $sponsorship = DB::table('sponsorships')
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
                'sponsorships.sponsoring_organization',
                'sponsorships.notes',
                'sponsorships.created_at',
                'sponsorships.updated_at'
            ])
            ->where('sponsorships.id', $id)
            ->first();

        if (!$sponsorship) {
            return null;
        }

        $controller = new self();
        return $controller->enrichSponsorshipData($sponsorship);
    }

    /**
     * إثراء بيانات الكفالة من السجل المدني والبيانات البنكية
     */
    public function enrichSponsorshipData($sponsorship)
    {
        $result = (array) $sponsorship;
        $result['orphan_data_source'] = 'sponsorships';
        $result['guardian_data_source'] = 'sponsorships';
        $result['needs_orphan_name_input'] = false;
        $result['needs_guardian_name_input'] = false;

        // التأكد من وجود حقل اسم الكافل
        if (!isset($result['sponsoring_organization'])) {
            $result['sponsoring_organization'] = $sponsorship->sponsoring_organization ?? '';
        }

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

        // ===================================================================
        // جلب بيانات المعيل - البحث الشامل في جميع الجداول
        // الأولوية: 1) جدول data، 2) جدول re_people، 3) جدول dead_people
        //           4) السجل المدني، 5) تقسيم الاسم
        // ===================================================================
        $guardianDataFound = false;
        $guardianPersonType = $sponsorship->person_type ?? 'breadwinner';

        // 1) البحث في جدول data (المعيلين/أرباب الأسر)
        if (!$guardianDataFound && !empty($sponsorship->relation_id_number)) {
            $guardianFromData = DB::table('data')
                ->where('file_id_number', $sponsorship->relation_id_number)
                ->select(['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name',
                         'data_phone_number', 'data_alt_phone_number', 'data_current_address', 'data_city',
                         'data_health_status', 'data_id_number'])
                ->first();

            if ($guardianFromData && !empty($guardianFromData->data_first_name)) {
                $result['guardian_first_name'] = $guardianFromData->data_first_name ?? '';
                $result['guardian_father_name'] = $guardianFromData->data_father_name ?? '';
                $result['guardian_grandfather_name'] = $guardianFromData->data_grand_father_name ?? '';
                $result['guardian_family_name'] = $guardianFromData->data_family_name ?? '';
                $result['guardian_data_source'] = 'data_table';
                $guardianDataFound = true;

                // تحديث بيانات الاتصال من data أيضاً
                if (!isset($result['guardian_phone']) || empty($result['guardian_phone'])) {
                    $result['guardian_phone'] = $guardianFromData->data_phone_number ?? '';
                }
                if (!isset($result['guardian_phone2']) || empty($result['guardian_phone2'])) {
                    $result['guardian_phone2'] = $guardianFromData->data_alt_phone_number ?? '';
                }
                if (!isset($result['guardian_detailed_address']) || empty($result['guardian_detailed_address'])) {
                    $result['guardian_detailed_address'] = $guardianFromData->data_current_address ?? '';
                }
            }
        }

        // 2) البحث في جدول re_people (أفراد العائلة) - مهم لـ family_member
        if (!$guardianDataFound && !empty($sponsorship->relation_id_number)) {
            $guardianFromRePeople = DB::table('re_people')
                ->where('registration_id', $sponsorship->relation_id_number)
                ->select(['first_name', 'second_name', 'third_name', 'last_name', 'person_id',
                         'person_birth_date', 'person_gender', 'person_health_status'])
                ->first();

            if ($guardianFromRePeople && !empty($guardianFromRePeople->first_name)) {
                $result['guardian_first_name'] = $guardianFromRePeople->first_name ?? '';
                $result['guardian_father_name'] = $guardianFromRePeople->second_name ?? '';
                $result['guardian_grandfather_name'] = $guardianFromRePeople->third_name ?? '';
                $result['guardian_family_name'] = $guardianFromRePeople->last_name ?? '';
                $result['guardian_data_source'] = 're_people';
                $guardianDataFound = true;

                Log::info('✅ تم جلب بيانات المعيل من جدول re_people', [
                    'sponsorship_id' => $sponsorship->id,
                    'relation_id_number' => $sponsorship->relation_id_number,
                    'name' => trim(implode(' ', array_filter([
                        $guardianFromRePeople->first_name,
                        $guardianFromRePeople->second_name,
                        $guardianFromRePeople->third_name,
                        $guardianFromRePeople->last_name
                    ])))
                ]);
            }
        }

        // 2.1) البحث في جدول re_people برقم هوية المعيل إذا لم نجده بـ relation_id
        if (!$guardianDataFound && !empty($sponsorship->guardian_identity_number)) {
            $guardianFromRePeopleById = DB::table('re_people')
                ->where('person_id', $sponsorship->guardian_identity_number)
                ->select(['first_name', 'second_name', 'third_name', 'last_name', 'registration_id',
                         'person_birth_date', 'person_gender', 'person_health_status'])
                ->first();

            if ($guardianFromRePeopleById && !empty($guardianFromRePeopleById->first_name)) {
                $result['guardian_first_name'] = $guardianFromRePeopleById->first_name ?? '';
                $result['guardian_father_name'] = $guardianFromRePeopleById->second_name ?? '';
                $result['guardian_grandfather_name'] = $guardianFromRePeopleById->third_name ?? '';
                $result['guardian_family_name'] = $guardianFromRePeopleById->last_name ?? '';
                $result['guardian_data_source'] = 're_people';
                $guardianDataFound = true;

                Log::info('✅ تم جلب بيانات المعيل من جدول re_people (برقم الهوية)', [
                    'sponsorship_id' => $sponsorship->id,
                    'guardian_identity_number' => $sponsorship->guardian_identity_number
                ]);
            }
        }

        // 3) البحث في جدول dead_people (المتوفين)
        if (!$guardianDataFound && !empty($sponsorship->relation_id_number)) {
            $guardianFromDeadPeople = DB::table('dead_people')
                ->where('re_file_id', $sponsorship->relation_id_number)
                ->first();

            if ($guardianFromDeadPeople) {
                // تحديد إذا كان الأب أو الأم المتوفي
                if (!empty($guardianFromDeadPeople->father_first_name)) {
                    $result['guardian_first_name'] = $guardianFromDeadPeople->father_first_name ?? '';
                    $result['guardian_father_name'] = $guardianFromDeadPeople->father_second_name ?? '';
                    $result['guardian_grandfather_name'] = $guardianFromDeadPeople->father_third_name ?? '';
                    $result['guardian_family_name'] = $guardianFromDeadPeople->father_last_name ?? '';
                    $result['guardian_data_source'] = 'dead_people';
                    $guardianDataFound = true;
                } elseif (!empty($guardianFromDeadPeople->mother_first_name)) {
                    $result['guardian_first_name'] = $guardianFromDeadPeople->mother_first_name ?? '';
                    $result['guardian_father_name'] = $guardianFromDeadPeople->mother_second_name ?? '';
                    $result['guardian_grandfather_name'] = $guardianFromDeadPeople->mother_third_name ?? '';
                    $result['guardian_family_name'] = $guardianFromDeadPeople->mother_last_name ?? '';
                    $result['guardian_data_source'] = 'dead_people';
                    $guardianDataFound = true;
                }
            }
        }

        // 3.1) البحث في dead_people برقم هوية المعيل
        if (!$guardianDataFound && !empty($sponsorship->guardian_identity_number)) {
            // البحث كأب متوفي
            $deadFather = DB::table('dead_people')
                ->where('father_id', $sponsorship->guardian_identity_number)
                ->first();

            if ($deadFather && !empty($deadFather->father_first_name)) {
                $result['guardian_first_name'] = $deadFather->father_first_name ?? '';
                $result['guardian_father_name'] = $deadFather->father_second_name ?? '';
                $result['guardian_grandfather_name'] = $deadFather->father_third_name ?? '';
                $result['guardian_family_name'] = $deadFather->father_last_name ?? '';
                $result['guardian_data_source'] = 'dead_people';
                $guardianDataFound = true;
            }

            // البحث كأم متوفية إذا لم نجد الأب
            if (!$guardianDataFound) {
                $deadMother = DB::table('dead_people')
                    ->where('mother_id', $sponsorship->guardian_identity_number)
                    ->first();

                if ($deadMother && !empty($deadMother->mother_first_name)) {
                    $result['guardian_first_name'] = $deadMother->mother_first_name ?? '';
                    $result['guardian_father_name'] = $deadMother->mother_second_name ?? '';
                    $result['guardian_grandfather_name'] = $deadMother->mother_third_name ?? '';
                    $result['guardian_family_name'] = $deadMother->mother_last_name ?? '';
                    $result['guardian_data_source'] = 'dead_people';
                    $guardianDataFound = true;
                }
            }
        }

        // 4) نحاول من السجل المدني
        if (!$guardianDataFound && !empty($sponsorship->guardian_identity_number)) {
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
                $guardianDataFound = true;
            } else {
                Log::warning('❌ لم يتم العثور على بيانات المعيل في السجل المدني', [
                    'guardian_identity_number' => $sponsorship->guardian_identity_number
                ]);
            }
        }

        // 5) عند عدم وجود بيانات في الجداول، استخدام guardian_name كما هو بدون تقسيم
        if (!$guardianDataFound && !empty($sponsorship->guardian_name)) {
            $result['guardian_name_combined'] = $sponsorship->guardian_name;
            $result['needs_guardian_name_input'] = true;
            $result['guardian_data_source'] = 'guardian_name_only';
            $guardianDataFound = true;

            Log::info('⚠️ لم يتم العثور على بيانات المعيل في الجداول، استخدام guardian_name', [
                'guardian_name' => $sponsorship->guardian_name,
                'sponsorship_id' => $sponsorship->id
            ]);
        }

        // ===================================================================
        // جلب البيانات البنكية للمعيل - البحث الشامل
        // ===================================================================
        $result['bank_accounts'] = [];
        $bankAccountsFound = false;

        // 1) البحث باستخدام relation_id_number
        if (!$bankAccountsFound && !empty($sponsorship->relation_id_number)) {
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
                        'guardian_bank_accounts.check_account',
                        'bank_names.description as bank_name_text'
                    ])
                    ->get();

                if ($bankAccounts->isNotEmpty()) {
                    $result['bank_accounts'] = $bankAccounts->toArray();
                    $bankAccountsFound = true;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get bank accounts by relation_id', ['error' => $e->getMessage()]);
            }
        }

        // 2) البحث باستخدام guardian_identity_number (re_id_number في guardian_bank_accounts)
        if (!$bankAccountsFound && !empty($sponsorship->guardian_identity_number)) {
            try {
                $bankAccounts = DB::table('guardian_bank_accounts')
                    ->leftJoin('bank_names', 'guardian_bank_accounts.bank_name', '=', 'bank_names.id')
                    ->where('guardian_bank_accounts.re_id_number', $sponsorship->guardian_identity_number)
                    ->select([
                        'guardian_bank_accounts.id',
                        'guardian_bank_accounts.iban_usd',
                        'guardian_bank_accounts.iban_shekel',
                        'guardian_bank_accounts.re_guardian_name',
                        'guardian_bank_accounts.re_phone_number',
                        'guardian_bank_accounts.person_owner_identity_number',
                        'guardian_bank_accounts.bank_name',
                        'guardian_bank_accounts.check_account',
                        'guardian_bank_accounts.guardian_registration',
                        'bank_names.description as bank_name_text'
                    ])
                    ->get();

                if ($bankAccounts->isNotEmpty()) {
                    $result['bank_accounts'] = $bankAccounts->toArray();
                    $bankAccountsFound = true;

                    Log::info('✅ تم جلب الحسابات البنكية برقم هوية المعيل', [
                        'sponsorship_id' => $sponsorship->id,
                        'guardian_identity_number' => $sponsorship->guardian_identity_number,
                        'accounts_count' => $bankAccounts->count()
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get bank accounts by guardian_identity', ['error' => $e->getMessage()]);
            }
        }

        // ===================================================================
        // جلب بيانات الاتصال للمعيل (العنوان والهاتف) - البحث الشامل
        // ===================================================================
        $contactInfoFound = false;

        // 1) البحث في جدول data باستخدام relation_id_number
        if (!$contactInfoFound && !empty($sponsorship->relation_id_number)) {
            try {
                $guardianInfo = DB::table('data')
                    ->where('file_id_number', $sponsorship->relation_id_number)
                    ->select(['data_current_address', 'data_phone_number', 'data_alt_phone_number',
                             'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name',
                             'data_gender', 'data_birth_date', 'data_id_number', 'data_health_status', 'data_city'])
                    ->first();

                if ($guardianInfo) {
                    if (!empty($guardianInfo->data_phone_number) || !empty($guardianInfo->data_current_address)) {
                        $result['guardian_detailed_address'] = $guardianInfo->data_current_address ?? '';
                        $result['guardian_phone'] = $guardianInfo->data_phone_number ?? '';
                        $result['guardian_phone2'] = $guardianInfo->data_alt_phone_number ?? '';
                        $result['guardian_city_id'] = $guardianInfo->data_city ?? '';
                        $contactInfoFound = true;
                    }

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
                Log::warning('Failed to get guardian contact info from data', ['error' => $e->getMessage()]);
            }
        }

        // 2) البحث في جدول data باستخدام guardian_identity_number
        if (!$contactInfoFound && !empty($sponsorship->guardian_identity_number)) {
            try {
                $guardianInfo = DB::table('data')
                    ->where('data_id_number', $sponsorship->guardian_identity_number)
                    ->select(['data_current_address', 'data_phone_number', 'data_alt_phone_number',
                             'data_city', 'data_health_status', 'file_id_number'])
                    ->first();

                if ($guardianInfo && (!empty($guardianInfo->data_phone_number) || !empty($guardianInfo->data_current_address))) {
                    $result['guardian_detailed_address'] = $guardianInfo->data_current_address ?? '';
                    $result['guardian_phone'] = $guardianInfo->data_phone_number ?? '';
                    $result['guardian_phone2'] = $guardianInfo->data_alt_phone_number ?? '';
                    $result['guardian_city_id'] = $guardianInfo->data_city ?? '';
                    $contactInfoFound = true;

                    Log::info('✅ تم جلب بيانات الاتصال برقم هوية المعيل', [
                        'sponsorship_id' => $sponsorship->id,
                        'guardian_identity_number' => $sponsorship->guardian_identity_number
                    ]);

                    // جلب الحالة الصحية للمعيل (إذا لم تكن موجودة بالفعل)
                    if (empty($result['health_status_id'])) {
                        $result['health_status_id'] = $guardianInfo->data_health_status ?? '';
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get guardian contact info by identity', ['error' => $e->getMessage()]);
            }
        }

        // 3) البحث في الحسابات البنكية للحصول على رقم الهاتف إذا لم نجده
        if (!$contactInfoFound && !empty($result['bank_accounts'])) {
            foreach ($result['bank_accounts'] as $account) {
                $accountData = (array) $account;
                if (!empty($accountData['re_phone_number'])) {
                    $result['guardian_phone'] = $accountData['re_phone_number'];
                    $contactInfoFound = true;

                    Log::info('✅ تم جلب رقم الهاتف من الحساب البنكي', [
                        'sponsorship_id' => $sponsorship->id
                    ]);
                    break;
                }
            }
        }

        // تأكد من وجود قيم افتراضية لبيانات الاتصال
        if (!isset($result['guardian_phone'])) $result['guardian_phone'] = '';
        if (!isset($result['guardian_phone2'])) $result['guardian_phone2'] = '';
        if (!isset($result['guardian_detailed_address'])) $result['guardian_detailed_address'] = '';
        if (!isset($result['guardian_city_id'])) $result['guardian_city_id'] = '';

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

                    // ===================================================================
                    // جلب بيانات المعيل المتوفي من portal_general_registration_field_values
                    // ===================================================================
                    if (in_array($personType, ['deceased_father', 'deceased_mother'])) {
                        try {
                            $portalFields = DB::table('portal_general_registration_field_values')
                                ->where('sponsorship_id', $sponsorship->id)
                                ->get()
                                ->keyBy('field_key');

                            if ($portalFields->isNotEmpty()) {
                                // جلب العنوان التفصيلي
                                if (isset($portalFields['guardian_detailed_address'])) {
                                    $result['guardian_detailed_address'] = $portalFields['guardian_detailed_address']->field_value ?? '';
                                }
                                // جلب رقم الهاتف
                                if (isset($portalFields['guardian_phone'])) {
                                    $result['guardian_phone'] = $portalFields['guardian_phone']->field_value ?? '';
                                }
                                // جلب رقم الهاتف الثاني
                                if (isset($portalFields['guardian_phone2'])) {
                                    $result['guardian_phone2'] = $portalFields['guardian_phone2']->field_value ?? '';
                                }
                                // جلب اسم المعيل (إذا كان مخزناً)
                                if (isset($portalFields['guardian_first_name'])) {
                                    $result['guardian_first_name'] = $portalFields['guardian_first_name']->field_value ?? '';
                                }
                                if (isset($portalFields['guardian_father_name'])) {
                                    $result['guardian_father_name'] = $portalFields['guardian_father_name']->field_value ?? '';
                                }
                                if (isset($portalFields['guardian_grandfather_name'])) {
                                    $result['guardian_grandfather_name'] = $portalFields['guardian_grandfather_name']->field_value ?? '';
                                }
                                if (isset($portalFields['guardian_family_name'])) {
                                    $result['guardian_family_name'] = $portalFields['guardian_family_name']->field_value ?? '';
                                }

                                Log::info('✅ تم جلب بيانات المعيل المتوفي من portal_general_registration_field_values', [
                                    'sponsorship_id' => $sponsorship->id,
                                    'fields_count' => $portalFields->count()
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::warning('فشل جلب بيانات المتوفي من portal_general_registration_field_values', [
                                'error' => $e->getMessage()
                            ]);
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
            DB::beginTransaction();

            $data = $request->all();
            $type = $data['type'] ?? null;
            $payload = $data['payload'] ?? [];

            if (!$type || empty($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صالحة'
                ], 400);
            }

            $result = match($type) {
                'sponsorship' => $this->syncSponsorship($payload),
                'person_data' => $this->syncPersonData($payload),
                'bank_account' => $this->syncBankAccount($payload),
                default => null
            };

            if ($result === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'نوع بيانات غير معروف: ' . $type
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تمت المزامنة بنجاح',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ [UPLOAD SYNC] خطأ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشلت المزامنة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/mobile/sync/photos/metadata
     * مزامنة بيانات الصور المرفوعة
     */
    public function syncPhotoMetadata(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            Log::info('📸 [PHOTO METADATA SYNC] تم استلام بيانات صورة جديدة', $data);

            // يمكنك هنا حفظ البيانات في جدول الصور أو المرفقات إذا لزم الأمر
            
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات الصورة بنجاح',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [PHOTO METADATA SYNC] خطأ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل حفظ بيانات الصورة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/mobile/photos/manifest
     * قائمة جميع صور الكفالات لتنزيلها للعرض دون إنترنت
     */
    public function photosManifest(Request $request): JsonResponse
    {
        try {
            // صور الموقع الحقيقية في جدول attachments بنوع file_type = 12
            // (مفتاحها رقم الهوية person_identity_number كما يفعل الموقع نفسه).
            $photoIdentityNumbers = [];
            DB::table('attachments')
                ->where('file_type', 12)
                ->whereNotNull('person_identity_number')
                ->where('person_identity_number', '<>', '')
                ->distinct()
                ->pluck('person_identity_number')
                ->each(function ($idNum) use (&$photoIdentityNumbers) {
                    $photoIdentityNumbers[(string) $idNum] = true;
                });

            $photoBase = url('/api/mobile/registration/photo');
            $pathBase = url('/api/mobile/photos');

            $rows = DB::table('sponsorships')
                ->select([
                    'sponsorships.id',
                    'sponsorships.internal_file_number',
                    'sponsorships.identity_number',
                    'sponsorships.guardian_identity_number',
                    'sponsorships.orphan_name',
                    'sponsorships.orphan_photo_path',
                    'sponsorships.guardian_photo_path'
                ])
                ->orderBy('sponsorships.id')
                ->cursor();

            $items = [];
            foreach ($rows as $r) {
                $orphanId = (string) ($r->identity_number ?: '');
                $guardianId = (string) ($r->guardian_identity_number ?: '');

                $orphanUrl = null;
                if (!empty($r->orphan_photo_path)) {
                    $orphanUrl = "{$pathBase}/{$r->id}?type=orphan";
                } elseif ($orphanId && !empty($photoIdentityNumbers[$orphanId])) {
                    $orphanUrl = "{$photoBase}/{$orphanId}";
                }

                $guardianUrl = null;
                if (!empty($r->guardian_photo_path)) {
                    $guardianUrl = "{$pathBase}/{$r->id}?type=guardian";
                } elseif ($guardianId && !empty($photoIdentityNumbers[$guardianId])) {
                    $guardianUrl = "{$photoBase}/{$guardianId}";
                }

                if ($orphanUrl || $guardianUrl) {
                    $items[] = [
                        'sponsorship_id' => $r->id,
                        'file_number' => $r->internal_file_number,
                        'identity_number' => $orphanId,
                        'guardian_identity_number' => $guardianId,
                        'orphan_name' => $r->orphan_name,
                        'orphan_photo_url' => $orphanUrl,
                        'orphan_identity' => $orphanId ?: null,
                        'guardian_photo_url' => $guardianUrl,
                        'guardian_identity' => $guardianId ?: null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'total' => count($items),
                'items' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('photos manifest error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل تحميل قائمة الصور: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/mobile/photos/{id}?type=orphan|guardian
     * بثّ صورة الكفالة (مع تفعيل CORS عبر مسار api/*)
     */
    public function photoFile(int $id, Request $request)
    {
        try {
            $sponsorship = DB::table('sponsorships')
                ->where('id', $id)
                ->select('id', 'orphan_photo_path', 'guardian_photo_path')
                ->first();

            $path = null;
            if ($request->get('type') === 'guardian') {
                $path = $sponsorship->guardian_photo_path ?? null;
            } elseif ($request->get('type') === 'orphan') {
                $path = $sponsorship->orphan_photo_path ?? null;
            } else {
                $path = $sponsorship->orphan_photo_path ?? $sponsorship->guardian_photo_path ?? null;
            }

            if (!$sponsorship || empty($path)) {
                return response()->json(['success' => false, 'message' => 'لا توجد صورة'], 404);
            }

            $full = storage_path('app/public/' . ltrim($path, '/'));
            if (!is_file($full)) {
                return response()->json(['success' => false, 'message' => 'ملف الصورة غير موجود'], 404);
            }

            $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'application/octet-stream',
            };

            return response()->file($full, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\Exception $e) {
            Log::error('photo file error', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل جلب الصورة'], 500);
        }
    }

    private function syncSponsorship(array $payload): array
    {
        $identityNumber = $payload['identity_number'] ?? null;
        $sponsorshipData = $payload['sponsorship'] ?? [];

        if (!$identityNumber) {
            throw new \Exception('رقم الهوية مطلوب');
        }

        $sponsorship = Sponsorship::where('identity_number', $identityNumber)->first();

        if ($sponsorship) {
            $sponsorship->update($sponsorshipData);
        } else {
            $sponsorship = Sponsorship::create(array_merge($sponsorshipData, [
                'identity_number' => $identityNumber
            ]));
        }

        return ['sponsorship_id' => $sponsorship->id];
    }

    private function syncPersonData(array $payload): array
    {
        $personType = $payload['person_type'] ?? 'breadwinner';
        $data = $payload['data'] ?? [];
        $identityNumber = $payload['identity_number'] ?? null;

        if (!$identityNumber) {
            throw new \Exception('رقم الهوية مطلوب');
        }

        $result = match($personType) {
            'breadwinner' => $this->syncBreadwinner($identityNumber, $data),
            'orphan', 'family_member' => $this->syncFamilyMember($identityNumber, $data),
            'deceased_father', 'deceased_mother' => $this->syncDeceased($identityNumber, $data, $personType),
            default => throw new \Exception('نوع شخص غير معروف')
        };

        return $result;
    }

    private function syncBreadwinner(string $identityNumber, array $data): array
    {
        $record = \App\Models\Data::updateOrCreate(
            ['data_id_number' => $identityNumber],
            $data
        );
        return ['data_id' => $record->id, 'file_id_number' => $record->file_id_number];
    }

    private function syncFamilyMember(string $identityNumber, array $data): array
    {
        $record = \App\Models\RePeople::updateOrCreate(
            ['person_id' => $identityNumber],
            $data
        );
        return ['person_id' => $record->person_id];
    }

    private function syncDeceased(string $identityNumber, array $data, string $type): array
    {
        $record = \App\Models\DeadPepole::where('father_id', $identityNumber)
            ->orWhere('mother_id', $identityNumber)
            ->first();

        if ($record) {
            $field = $type === 'deceased_father' ? 'father' : 'mother';
            foreach ($data as $key => $value) {
                $record->{$field . '_' . $key} = $value;
            }
            $record->save();
        }

        return ['deceased_id' => $record ? $record->re_file_id : null];
    }

    private function syncBankAccount(array $payload): array
    {
        $identityNumber = $payload['identity_number'] ?? null;
        $accountData = $payload['account'] ?? [];

        if (!$identityNumber) {
            throw new \Exception('رقم الهوية مطلوب');
        }

        $account = \App\Models\GuardianBankAccount::updateOrCreate(
            ['guardian_registration' => $identityNumber],
            $accountData
        );

        return ['account_id' => $account->id];
    }

    private function renameDriveFolder(string $sponsorName, string $oldName, string $newName): bool
    {
        try {
            $useRclone = config('services.google.use_rclone', false);
            if ($useRclone) {
                $remoteName = config('services.google.rclone_remote_name', 'alhayahorphans');
                $rootFolder = config('services.google.rclone_root_folder', 'temp');
                $oldPath = "{$remoteName}:{$rootFolder}/{$sponsorName}/{$oldName}";
                $newPath = "{$remoteName}:{$rootFolder}/{$sponsorName}/{$newName}";
                $command = "rclone moveto \"{$oldPath}\" \"{$newPath}\" 2>&1";
                $output = shell_exec($command);
                Log::info('Rclone rename folder', ['command' => $command, 'output' => $output]);
                return true;
            } else {
                $googleDriveService = app(\App\Services\GoogleDriveService::class);
                return $googleDriveService->renameFolder($sponsorName, $oldName, $newName);
            }
        } catch (\Exception $e) {
            Log::error('Rename folder failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * تحديث الحسابات البنكية - بحث شامل وإنشاء إذا لم تكن موجودة
     */
    private function updateBankAccounts($sponsorship, array $bankUpdates): void
    {
        try {
            // تحديد guardian_registration للبحث
            $guardianRegistration = $sponsorship->relation_id_number;
            $guardianIdentity = $sponsorship->guardian_identity_number ?? null;

            // محاولة جلب الحسابات البنكية بطرق متعددة
            $accounts = collect();

            // 1) البحث باستخدام relation_id_number
            if (!empty($guardianRegistration)) {
                $accounts = DB::table('guardian_bank_accounts')
                    ->where('guardian_registration', $guardianRegistration)
                    ->orderBy('id')
                    ->get()
                    ->values();
            }

            // 2) البحث باستخدام guardian_identity_number إذا لم نجد
            if ($accounts->isEmpty() && !empty($guardianIdentity)) {
                $accounts = DB::table('guardian_bank_accounts')
                    ->where('re_id_number', $guardianIdentity)
                    ->orderBy('id')
                    ->get()
                    ->values();

                // إذا وجدنا حسابات، نحدث guardian_registration
                if ($accounts->isNotEmpty() && !empty($guardianRegistration)) {
                    foreach ($accounts as $acc) {
                        if (empty($acc->guardian_registration)) {
                            DB::table('guardian_bank_accounts')
                                ->where('id', $acc->id)
                                ->update(['guardian_registration' => $guardianRegistration, 'updated_at' => now()]);
                        }
                    }
                }
            }

            Log::info('🏦 تحديث الحسابات البنكية', [
                'sponsorship_id' => $sponsorship->id,
                'relation_id_number' => $guardianRegistration,
                'guardian_identity_number' => $guardianIdentity,
                'accounts_count' => $accounts->count(),
                'updates_count' => count($bankUpdates),
                'bank_updates_received' => $bankUpdates
            ]);

            foreach ($bankUpdates as $index => $updates) {
                $updateData = [];
                $existingAccount = $accounts[$index] ?? null;

                Log::info('🔍 معالجة تحديث الحساب البنكي', [
                    'index' => $index,
                    'updates_keys' => array_keys($updates),
                    'updates_values' => $updates,
                    'existing_account_id' => $existingAccount ? $existingAccount->id : null
                ]);

                // دعم أسماء الحقول من التطبيق (data-bank-field) وأسماء بديلة
                // السماح بالتحديث حتى لو كانت القيمة فارغة (للحسابات الجديدة)
                if (isset($updates['bank_name'])) {
                    $updateData['bank_name'] = $updates['bank_name'];
                } elseif (isset($updates['bank_name_id'])) {
                    $updateData['bank_name'] = $updates['bank_name_id'];
                }

                // اسم صاحب الحساب - السماح بالقيم الفارغة
                if (isset($updates['re_guardian_name'])) {
                    $updateData['re_guardian_name'] = $updates['re_guardian_name'];
                } elseif (isset($updates['account_holder_name'])) {
                    $updateData['re_guardian_name'] = $updates['account_holder_name'];
                }

                // رقم هوية صاحب الحساب - السماح بالقيم الفارغة
                if (isset($updates['person_owner_identity_number'])) {
                    $updateData['person_owner_identity_number'] = $updates['person_owner_identity_number'];
                } elseif (isset($updates['account_holder_identity'])) {
                    $updateData['person_owner_identity_number'] = $updates['account_holder_identity'];
                }

                // رقم هاتف صاحب الحساب - السماح بالقيم الفارغة
                if (isset($updates['re_phone_number'])) {
                    $updateData['re_phone_number'] = $updates['re_phone_number'];
                } elseif (isset($updates['account_holder_phone'])) {
                    $updateData['re_phone_number'] = $updates['account_holder_phone'];
                }

                // IBAN - السماح بالقيم الفارغة
                if (isset($updates['iban_usd'])) $updateData['iban_usd'] = $updates['iban_usd'];
                elseif (isset($updates['iban'])) $updateData['iban_usd'] = $updates['iban'];

                if (isset($updates['iban_shekel'])) $updateData['iban_shekel'] = $updates['iban_shekel'];

                // السماح بإنشاء حساب جديد حتى لو كانت بعض الحقول فارغة
                // الهدف: إنشاء سجل placeholder يُكمله المستخدم لاحقاً
                $isNewAccount = !isset($accounts[$index]);

                // للحسابات الجديدة: حتى لو كانت كل الحقول فارغة، نسمح بالإنشاء
                // للحسابات الموجودة: فقط إذا كان هناك تحديث فعلي
                if (empty($updateData) && !$isNewAccount) {
                    Log::info('⏭️ تخطي التحديث - لا يوجد بيانات جديدة', [
                        'index' => $index,
                        'is_new_account' => false
                    ]);
                    continue;
                }

                // للحسابات الجديدة: إذا لم يتم إرسال أي بيانات، نضع قيم افتراضية
                if ($isNewAccount && empty($updateData)) {
                    Log::info('🔧 حساب جديد بدون بيانات - إنشاء placeholder', [
                        'index' => $index,
                        'guardian_name' => $sponsorship->guardian_name ?? '',
                        'guardian_identity' => $guardianIdentity ?? ''
                    ]);

                    // قيم افتراضية من بيانات الكفالة
                    $updateData['re_guardian_name'] = $sponsorship->guardian_name ?? '';
                    $updateData['person_owner_identity_number'] = $guardianIdentity ?? $sponsorship->identity_number ?? '';
                    $updateData['re_phone_number'] = $sponsorship->guardian_phone ?? '';
                }

                // التحقق من وجود حساب للفهرس
                if (isset($accounts[$index])) {
                    // تحديث الحساب الموجود
                    $account = $accounts[$index];
                    $updateData['updated_at'] = now();
                    DB::table('guardian_bank_accounts')
                        ->where('id', $account->id)
                        ->update($updateData);

                    Log::info('✅ تم تحديث الحساب البنكي', [
                        'account_id' => $account->id,
                        'index' => $index,
                        'updated_fields' => array_keys($updateData)
                    ]);
                } else {
                    // إنشاء حساب جديد
                    Log::info('🆕 إنشاء حساب بنكي جديد', [
                        'index' => $index,
                        'sponsorship_id' => $sponsorship->id,
                        'guardian_registration' => $guardianRegistration,
                        'guardian_identity' => $guardianIdentity,
                        'update_data' => $updateData
                    ]);

                    // التحقق من أن guardian_registration موجود في جدول data (بسبب قيد الـ foreign key)
                    $validGuardianRegistration = null;

                    if (!empty($guardianRegistration)) {
                        // التحقق من وجود السجل في جدول data
                        $dataRecord = DB::table('data')
                            ->where('file_id_number', $guardianRegistration)
                            ->first();

                        if ($dataRecord) {
                            $validGuardianRegistration = $guardianRegistration;
                        }
                    }

                    // إذا لم يكن guardian_registration صالحاً، نبحث عن سجل مناسب في data
                    if (empty($validGuardianRegistration)) {
                        // محاولة إيجاد سجل في data بناءً على هوية الولي
                        if (!empty($guardianIdentity)) {
                            $dataByIdentity = DB::table('data')
                                ->where('data_id_number', $guardianIdentity)
                                ->first();

                            if ($dataByIdentity) {
                                $validGuardianRegistration = $dataByIdentity->file_id_number;

                                // تحديث الكفالة بالقيمة الصحيحة
                                DB::table('sponsorships')
                                    ->where('id', $sponsorship->id)
                                    ->update(['relation_id_number' => $validGuardianRegistration, 'updated_at' => now()]);

                                Log::info('🔄 تم تصحيح relation_id_number من جدول data', [
                                    'old_value' => $guardianRegistration,
                                    'new_value' => $validGuardianRegistration,
                                    'guardian_identity' => $guardianIdentity
                                ]);
                            }
                        }
                    }

                    // إذا لم نجد سجلاً صالحاً، ننشئ سجلاً جديداً في data
                    // ✅ لكن أولاً: إذا كان relation_id_number موجود (تم إنشاؤه للمتوفي/المعيل)، نستخدمه
                    if (empty($validGuardianRegistration) && !empty($guardianRegistration)) {
                        // relation_id_number موجود لكن لا يوجد سجل في data
                        // ننشئ سجل في data بنفس الرقم للحفاظ على التماثل
                        Log::info('🔨 إنشاء سجل جديد في data للولي (بنفس relation_id_number)', [
                            'guardian_identity' => $guardianIdentity,
                            'sponsorship_identity' => $sponsorship->identity_number,
                            'existing_relation_id_number' => $guardianRegistration,
                            'reason' => 'استخدام نفس رقم الملف للحفاظ على التماثل'
                        ]);

                        // استخدام نفس رقم الملف الموجود في relation_id_number
                        $newFileIdNumber = $guardianRegistration;

                        // إنشاء سجل جديد في جدول data للولي مع الاسم الكامل
                        $guardianInsertData = [
                            'file_id_number' => $newFileIdNumber,
                            'data_id_number' => $guardianIdentity ?? $sponsorship->identity_number,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];

                        // إضافة الاسم الكامل إذا كان متاحاً
                        if (isset($updates['guardian_first_name'])) $guardianInsertData['data_first_name'] = $updates['guardian_first_name'];
                        if (isset($updates['guardian_father_name'])) $guardianInsertData['data_father_name'] = $updates['guardian_father_name'];
                        if (isset($updates['guardian_grandfather_name'])) $guardianInsertData['data_grand_father_name'] = $updates['guardian_grandfather_name'];
                        if (isset($updates['guardian_family_name'])) $guardianInsertData['data_family_name'] = $updates['guardian_family_name'];
                        if (isset($updates['guardian_phone'])) $guardianInsertData['data_phone_number'] = $updates['guardian_phone'];
                        if (isset($updates['guardian_phone2'])) $guardianInsertData['data_alt_phone_number'] = $updates['guardian_phone2'];
                        if (isset($updates['guardian_detailed_address'])) $guardianInsertData['data_current_address'] = $updates['guardian_detailed_address'];
                        if (isset($updates['guardian_city_id'])) $guardianInsertData['data_city'] = $updates['guardian_city_id'];

                        DB::table('data')->insert($guardianInsertData);

                        // ✅ لا حاجة لتعليم الكود لأنه نفس الرقم المُستخدم مسبقاً

                        $validGuardianRegistration = $newFileIdNumber;

                        Log::info('✅ تم إنشاء سجل جديد في data للولي بنفس relation_id_number', [
                            'file_id_number' => $newFileIdNumber,
                            'identity' => $guardianIdentity ?? $sponsorship->identity_number,
                            'sponsorship_id' => $sponsorship->id
                        ]);
                    }
                    // إذا لم يكن هناك relation_id_number أصلاً، ننشئ رقم جديد
                    else if (empty($validGuardianRegistration)) {
                        Log::info('🔨 إنشاء سجل جديد في data للولي (رقم جديد)', [
                            'guardian_identity' => $guardianIdentity,
                            'sponsorship_identity' => $sponsorship->identity_number,
                            'reason' => 'لم يتم العثور على سجل موجود ولا يوجد relation_id_number'
                        ]);

                        $newFileIdNumber = generateFileIdFromDataTable();

                        // إنشاء سجل جديد في جدول data للولي مع الاسم الكامل
                        $guardianInsertData = [
                            'file_id_number' => $newFileIdNumber,
                            'data_id_number' => $guardianIdentity ?? $sponsorship->identity_number,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];

                        // إضافة الاسم الكامل إذا كان متاحاً
                        if (isset($updates['guardian_first_name'])) $guardianInsertData['data_first_name'] = $updates['guardian_first_name'];
                        if (isset($updates['guardian_father_name'])) $guardianInsertData['data_father_name'] = $updates['guardian_father_name'];
                        if (isset($updates['guardian_grandfather_name'])) $guardianInsertData['data_grand_father_name'] = $updates['guardian_grandfather_name'];
                        if (isset($updates['guardian_family_name'])) $guardianInsertData['data_family_name'] = $updates['guardian_family_name'];
                        if (isset($updates['guardian_phone'])) $guardianInsertData['data_phone_number'] = $updates['guardian_phone'];
                        if (isset($updates['guardian_phone2'])) $guardianInsertData['data_alt_phone_number'] = $updates['guardian_phone2'];
                        if (isset($updates['guardian_detailed_address'])) $guardianInsertData['data_current_address'] = $updates['guardian_detailed_address'];
                        if (isset($updates['guardian_city_id'])) $guardianInsertData['data_city'] = $updates['guardian_city_id'];

                        DB::table('data')->insert($guardianInsertData);

                        // ✅ تعليم الكود كمستخدم
                        markCodeAsUsed($newFileIdNumber, null, 'ولي جديد في data للبنك');

                        $validGuardianRegistration = $newFileIdNumber;

                        // تحديث الكفالة بالرقم الجديد
                        DB::table('sponsorships')
                            ->where('id', $sponsorship->id)
                            ->update(['relation_id_number' => $validGuardianRegistration, 'updated_at' => now()]);

                        Log::info('✅ تم إنشاء سجل جديد في data للولي بنجاح (رقم جديد)', [
                            'file_id_number' => $newFileIdNumber,
                            'identity' => $guardianIdentity ?? $sponsorship->identity_number,
                            'sponsorship_id' => $sponsorship->id
                        ]);
                    }

                    // ✅ التحقق من وجود bank_name (مطلوب في قاعدة البيانات - NOT NULL)
                    // إذا لم يتم إرسال bank_name، لا يمكن إنشاء الحساب
                    if (!isset($updateData['bank_name']) || empty($updateData['bank_name'])) {
                        Log::warning('⚠️ لا يمكن إنشاء حساب بنكي جديد بدون تحديد اسم البنك', [
                            'sponsorship_id' => $sponsorship->id,
                            'index' => $index,
                            'received_data' => $updates
                        ]);
                        continue; // تخطي هذا الحساب والمتابعة للحساب التالي
                    }

                    $insertData = array_merge($updateData, [
                        'guardian_registration' => $validGuardianRegistration,
                        're_id_number' => $updates['person_owner_identity_number'] ?? $guardianIdentity ?? $sponsorship->identity_number, // رقم الهوية الذي يدخله المستخدم
                        'check_account' => 1, // ✅ دائماً 1 لكل الحسابات
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    Log::info('📝 بيانات الحساب البنكي الجديد قبل الإدراج', [
                        'insert_data' => $insertData,
                        'valid_guardian_registration' => $validGuardianRegistration
                    ]);

                    try {
                        $newAccountId = DB::table('guardian_bank_accounts')->insertGetId($insertData);

                        Log::info('✅ تم إنشاء حساب بنكي جديد بنجاح', [
                            'new_account_id' => $newAccountId,
                            'sponsorship_id' => $sponsorship->id,
                            'guardian_registration' => $validGuardianRegistration,
                            'index' => $index,
                            'inserted_fields' => array_keys($insertData)
                        ]);
                    } catch (\Exception $insertError) {
                        Log::error('❌ فشل إدراج الحساب البنكي', [
                            'error' => $insertError->getMessage(),
                            'insert_data' => $insertData,
                            'sponsorship_id' => $sponsorship->id
                        ]);
                        throw $insertError;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ فشل تحديث الحسابات البنكية', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sponsorship_id' => $sponsorship->id ?? null
            ]);
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

        // ✅ تم إزالة الشرط الذي يمنع المتابعة إذا كان relation_id_number فارغاً
        // لأننا نبحث أيضاً بـ identity_number وننشئ سجل جديد إذا لزم الأمر
        // (نفس منطق OfflineTestController)

        switch ($personType) {
            // ============================================
            // حالة المعيل (breadwinner) - جدول data
            // (نفس منطق OfflineTestController)
            // ============================================
            case 'breadwinner':
                $result['table'] = 'data';
                $record = null;

                // البحث بـ relation_id_number أولاً
                if (!empty($relationIdNumber)) {
                    $record = DB::table('data')->where('file_id_number', $relationIdNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على السجل بـ file_id_number', ['file_id_number' => $relationIdNumber]);
                    }
                }

                // البحث بـ identity_number إذا لم نجد
                if (!$record && !empty($identityNumber)) {
                    $record = DB::table('data')->where('data_id_number', $identityNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على السجل بـ data_id_number', ['data_id_number' => $identityNumber]);
                    }
                }

                // تحضير بيانات التحديث
                $updateData = [];
                if (isset($updates['first_name'])) $updateData['data_first_name'] = $updates['first_name'];
                if (isset($updates['second_name'])) $updateData['data_father_name'] = $updates['second_name'];
                if (isset($updates['third_name'])) $updateData['data_grand_father_name'] = $updates['third_name'];
                if (isset($updates['last_name'])) $updateData['data_family_name'] = $updates['last_name'];
                if (isset($updates['orphan_gender'])) {
                    $updateData['data_gender'] = $this->convertGenderToInt($updates['orphan_gender']);
                }
                // دعم sponsored_birth_date أيضاً
                if (isset($updates['birth_date'])) $updateData['data_birth_date'] = $updates['birth_date'];
                elseif (isset($updates['sponsored_birth_date'])) $updateData['data_birth_date'] = $updates['sponsored_birth_date'];
                if (isset($updates['identity_number'])) $updateData['data_id_number'] = $updates['identity_number'];
                if (isset($updates['health_status_id'])) $updateData['data_health_status'] = $updates['health_status_id'];

                // حفظ الحقول الإضافية للمعيل (الهاتف والعنوان)
                if (isset($updates['orphan_phone']) || isset($updates['guardian_phone'])) {
                    $phone = $updates['orphan_phone'] ?? $updates['guardian_phone'];
                    $updateData['data_phone_number'] = !empty($phone) ? (int)preg_replace('/[^0-9]/', '', $phone) : null;
                }
                if (isset($updates['orphan_phone2']) || isset($updates['guardian_phone2'])) {
                    $altPhone = $updates['orphan_phone2'] ?? $updates['guardian_phone2'];
                    $updateData['data_alt_phone_number'] = !empty($altPhone) ? (int)preg_replace('/[^0-9]/', '', $altPhone) : null;
                }
                if (isset($updates['orphan_detailed_address']) || isset($updates['guardian_detailed_address'])) {
                    $updateData['data_current_address'] = $updates['orphan_detailed_address'] ?? $updates['guardian_detailed_address'];
                }
                // ✅ حفظ المدينة للمعيل
                if (isset($updates['orphan_city_id']) || isset($updates['guardian_city_id'])) {
                    $updateData['data_city'] = $updates['orphan_city_id'] ?? $updates['guardian_city_id'];
                }

                if ($record) {
                    // تحديث سجل موجود
                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('data')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث المعيل في جدول data';
                        $result['updated_fields'] = array_keys($updateData);
                    }
                } else {
                    // إنشاء سجل جديد للمعيل
                    $newFileId = generateFileIdFromDataTable();

                    // تحضير أرقام الهاتف والعنوان والمدينة
                    $phone = $updates['orphan_phone'] ?? $updates['guardian_phone'] ?? null;
                    $altPhone = $updates['orphan_phone2'] ?? $updates['guardian_phone2'] ?? null;
                    $address = $updates['orphan_detailed_address'] ?? $updates['guardian_detailed_address'] ?? null;
                    $cityId = $updates['orphan_city_id'] ?? $updates['guardian_city_id'] ?? null;

                    // استخدام identity_number من التحديثات أو من المتغير الممرر
                    $dataIdNumber = $updates['identity_number'] ?? $identityNumber ?? null;

                    DB::table('data')->insert([
                        'file_id_number' => $newFileId,
                        'data_first_name' => $updates['first_name'] ?? null,
                        'data_father_name' => $updates['second_name'] ?? null,
                        'data_grand_father_name' => $updates['third_name'] ?? null,
                        'data_family_name' => $updates['last_name'] ?? null,
                        'data_gender' => isset($updates['orphan_gender']) ? $this->convertGenderToInt($updates['orphan_gender']) : null,
                        'data_birth_date' => $updates['birth_date'] ?? $updates['sponsored_birth_date'] ?? null,
                        'data_id_number' => $dataIdNumber,
                        'data_phone_number' => !empty($phone) ? (int)preg_replace('/[^0-9]/', '', $phone) : null,
                        'data_alt_phone_number' => !empty($altPhone) ? (int)preg_replace('/[^0-9]/', '', $altPhone) : null,
                        'data_current_address' => $address,
                        'data_city' => $cityId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // ✅ تعليم الكود كمستخدم
                    markCodeAsUsed($newFileId, null, 'breadwinner جديد في data');

                    // ✅ تحديث relation_id_number في sponsorships دائماً بالرقم الجديد
                    DB::table('sponsorships')->where('id', $sponsorship->id)->update([
                        'relation_id_number' => $newFileId,
                        'updated_at' => now()
                    ]);

                    Log::info('✅ تم تحديث relation_id_number في sponsorships للمعيل', [
                        'sponsorship_id' => $sponsorship->id,
                        'new_relation_id_number' => $newFileId
                    ]);

                    Log::info('✅ تم إنشاء سجل جديد للمكفول (breadwinner) في data', [
                        'sponsorship_id' => $sponsorship->id,
                        'new_file_id' => $newFileId,
                        'data_id_number' => $dataIdNumber
                    ]);

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

                // ✅ البحث أولاً برقم الهوية (person_id) لأنه المعرف الفريد
                $record = null;

                // 1. البحث برقم الهوية أولاً
                if (!empty($identityNumber)) {
                    $record = DB::table('re_people')->where('person_id', $identityNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على المكفول بـ person_id', ['person_id' => $identityNumber]);
                    }
                }

                // 2. إذا لم نجد برقم الهوية، نبحث بـ registration_id (إذا كان موجوداً ومختلفاً عن رقم المعيل)
                if (!$record && !empty($relationIdNumber)) {
                    // تحقق أن هذا الـ registration_id موجود في re_people وليس في data
                    $checkInRePeople = DB::table('re_people')->where('registration_id', $relationIdNumber)->exists();
                    if ($checkInRePeople) {
                        $record = DB::table('re_people')->where('registration_id', $relationIdNumber)->first();
                        Log::info('🔍 العثور على المكفول بـ registration_id', ['registration_id' => $relationIdNumber]);
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
                    // دعم sponsored_birth_date أيضاً
                    if (isset($updates['birth_date'])) $updateData['person_birth_date'] = $updates['birth_date'];
                    elseif (isset($updates['sponsored_birth_date'])) $updateData['person_birth_date'] = $updates['sponsored_birth_date'];
                    if (isset($updates['identity_number'])) $updateData['person_id'] = $updates['identity_number'];
                    if (isset($updates['health_status_id'])) $updateData['person_health_status'] = $updates['health_status_id'];

                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('re_people')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث فرد العائلة في جدول re_people';
                        $result['updated_fields'] = array_keys($updateData);

                        // تحديث sponsorships بالاسم الكامل وهوية المكفول
                        $sponsorshipUpdateData = ['updated_at' => now()];

                        // تحديث orphan_name إذا تم تعديل الأسماء
                        if (isset($updates['first_name']) || isset($updates['second_name']) ||
                            isset($updates['third_name']) || isset($updates['last_name'])) {
                            $fullName = trim(
                                ($updates['first_name'] ?? $record->first_name ?? '') . ' ' .
                                ($updates['second_name'] ?? $record->second_name ?? '') . ' ' .
                                ($updates['third_name'] ?? $record->third_name ?? '') . ' ' .
                                ($updates['last_name'] ?? $record->last_name ?? '')
                            );
                            $sponsorshipUpdateData['orphan_name'] = $fullName;
                        }

                        // تحديث identity_number إذا تم تعديله
                        if (isset($updates['identity_number'])) {
                            $sponsorshipUpdateData['identity_number'] = $updates['identity_number'];
                        }

                        if (count($sponsorshipUpdateData) > 1) {
                            DB::table('sponsorships')
                                ->where('id', $sponsorship->id)
                                ->update($sponsorshipUpdateData);

                            Log::info('✅ تم تحديث sponsorships بعد تحديث re_people', [
                                'sponsorship_id' => $sponsorship->id,
                                'updated_fields' => array_keys($sponsorshipUpdateData)
                            ]);
                        }
                    }
                } else {
                    // إنشاء سجل جديد في re_people
                    // ✅ استخدام نفس relation_id_number كـ registration_id (هذا هو التصميم الصحيح)
                    // relation_id_number يُنشأ عند إنشاء سجل المعيل في data
                    $newRegistrationId = $relationIdNumber;

                    // إذا كان relation_id_number فارغاً، نُنشئ رقم جديد
                    if (empty($newRegistrationId)) {
                        $newRegistrationId = generateFileIdFromDataTable();
                        Log::info('🆕 تم توليد رقم جديد للمكفول (لم يوجد relation_id_number)', [
                            'new_registration_id' => $newRegistrationId
                        ]);
                    }

                    $insertData = [
                        'registration_id' => $newRegistrationId,
                        'first_name' => $updates['first_name'] ?? null,
                        'second_name' => $updates['second_name'] ?? null,
                        'third_name' => $updates['third_name'] ?? null,
                        'last_name' => $updates['last_name'] ?? null,
                        'person_id' => $updates['identity_number'] ?? $identityNumber,
                        'person_gender' => isset($updates['orphan_gender']) ? $this->convertGenderToInt($updates['orphan_gender']) : null,
                        'person_birth_date' => $updates['birth_date'] ?? $updates['sponsored_birth_date'] ?? null,
                        'person_health_status' => $updates['health_status_id'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $newId = DB::table('re_people')->insertGetId($insertData);
                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد في re_people';
                    $result['new_record_id'] = $newId;
                    $result['new_registration_id'] = $newRegistrationId;

                    // ✅ ربط الكفالة بالمكفول الجديد (لا نُغير relation_id_number لأنه للمعيل)
                    // بل نحفظ registration_id في حقل منفصل أو نتركه للمعيل

                    // تحديث sponsorships بالاسم الكامل وهوية المكفول
                    $fullName = trim(
                        ($insertData['first_name'] ?? '') . ' ' .
                        ($insertData['second_name'] ?? '') . ' ' .
                        ($insertData['third_name'] ?? '') . ' ' .
                        ($insertData['last_name'] ?? '')
                    );

                    $sponsorshipUpdateData = ['updated_at' => now()];
                    if (!empty($fullName)) {
                        $sponsorshipUpdateData['orphan_name'] = $fullName;
                    }
                    if (!empty($insertData['person_id'])) {
                        $sponsorshipUpdateData['identity_number'] = $insertData['person_id'];
                    }

                    if (count($sponsorshipUpdateData) > 1) {
                        DB::table('sponsorships')
                            ->where('id', $sponsorship->id)
                            ->update($sponsorshipUpdateData);
                    }

                    Log::info('✅ تم إنشاء سجل جديد في re_people', [
                        'new_id' => $newId,
                        'registration_id' => $newRegistrationId,
                        'person_id' => $identityNumber,
                        'fields' => array_keys(array_filter($insertData))
                    ]);
                }
                break;

            // ============================================
            // حالة الأب المتوفي - جدول dead_people (حقول father_*)
            // (نفس منطق OfflineTestController)
            // ============================================
            case 'deceased_father':
                $result['table'] = 'dead_people';
                $record = null;

                // ✅ تسجيل البيانات المُستلمة للمتوفي
                Log::info('⚰️ بيانات الأب المتوفي المُستلمة', [
                    'sponsorship_id' => $sponsorship->id,
                    'first_name' => $updates['first_name'] ?? 'NOT SET',
                    'second_name' => $updates['second_name'] ?? 'NOT SET',
                    'third_name' => $updates['third_name'] ?? 'NOT SET',
                    'last_name' => $updates['last_name'] ?? 'NOT SET',
                    'identity_number' => $updates['identity_number'] ?? 'NOT SET',
                    'all_updates_keys' => array_keys($updates)
                ]);

                // البحث بـ re_file_id أولاً
                if (!empty($relationIdNumber)) {
                    $record = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على سجل الأب المتوفي بـ re_file_id', ['re_file_id' => $relationIdNumber]);
                    }
                }

                // البحث بـ father_id إذا لم نجد
                if (!$record && !empty($identityNumber)) {
                    $record = DB::table('dead_people')->where('father_id', $identityNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على سجل الأب المتوفي بـ father_id', ['father_id' => $identityNumber]);
                    }
                }

                if ($record) {
                    // ===== السيناريو 4أ: تحديث سجل موجود =====
                    $updateData = [];

                    // ✅ تحديث حقول الأب (father_*) - حفظ الأسماء كما هي بدون تقسيم
                    if (isset($updates['first_name'])) $updateData['father_first_name'] = $updates['first_name'];
                    if (isset($updates['second_name'])) $updateData['father_second_name'] = $updates['second_name'];
                    if (isset($updates['third_name'])) $updateData['father_third_name'] = $updates['third_name'];
                    if (isset($updates['last_name'])) $updateData['father_last_name'] = $updates['last_name'];

                    // ⚠️ استخدام guardian_name/full_name فقط إذا لم تكن الأسماء المنفصلة موجودة
                    if (!isset($updates['first_name']) && !isset($updates['second_name']) &&
                        !isset($updates['third_name']) && !isset($updates['last_name'])) {
                        $fullName = null;
                        if (isset($updates['guardian_name']) && !empty(trim($updates['guardian_name']))) {
                            $fullName = trim($updates['guardian_name']);
                        } elseif (isset($updates['full_name']) && !empty(trim($updates['full_name']))) {
                            $fullName = trim($updates['full_name']);
                        }

                        if ($fullName) {
                            $nameParts = explode(' ', $fullName);
                            $nameParts = array_filter($nameParts);
                            $nameParts = array_values($nameParts);

                            $updateData['father_first_name'] = $nameParts[0] ?? '';
                            $updateData['father_second_name'] = $nameParts[1] ?? '';
                            $updateData['father_third_name'] = $nameParts[2] ?? '';
                            $updateData['father_last_name'] = $nameParts[3] ?? '';
                        }
                    }

                    if (isset($updates['identity_number'])) $updateData['father_id'] = (int)preg_replace('/[^0-9]/', '', $updates['identity_number']);
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
                    // ===== السيناريو 4ب: إنشاء سجل جديد للأب المتوفي =====
                    Log::info('🆕 لا يوجد سجل للأب المتوفي - سيتم إنشاء سجل جديد');

                    // ✅ توليد رقم ملف جديد دائماً للمتوفين (لأن dead_people يحتاج re_file_id فريد)
                    $newFileId = generateFileIdFromDataTable();

                    // ✅ تعليم الكود كمستخدم في reserved_codes لمنع التضارب
                    markCodeAsUsed($newFileId, null, 'deceased_father في dead_people - sponsorship_id: ' . $sponsorship->id);

                    $insertData = [
                        're_file_id' => $newFileId,
                        'father_first_name' => $updates['first_name'] ?? '',
                        'father_second_name' => $updates['second_name'] ?? '',
                        'father_third_name' => $updates['third_name'] ?? '',
                        'father_last_name' => $updates['last_name'] ?? '',
                        'father_id' => !empty($updates['identity_number']) ? (int)preg_replace('/[^0-9]/', '', $updates['identity_number']) : null,
                        'father_death_date' => $updates['birth_date'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $newDeadId = DB::table('dead_people')->insertGetId($insertData);

                    // ✅ تحديث relation_id_number في sponsorships دائماً بالرقم الجديد
                    DB::table('sponsorships')->where('id', $sponsorship->id)->update([
                        'relation_id_number' => $newFileId,
                        'updated_at' => now()
                    ]);

                    Log::info('✅ تم تحديث relation_id_number في sponsorships', [
                        'sponsorship_id' => $sponsorship->id,
                        'new_relation_id_number' => $newFileId
                    ]);

                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد للأب المتوفي';
                    $result['new_record_id'] = $newDeadId;
                    $result['new_file_id'] = $newFileId;

                    // حفظ البيانات الإضافية للمتوفين
                    $sponsorship->relation_id_number = $newFileId; // تحديث للاستخدام في saveDeceasedExtraData
                    $this->saveDeceasedExtraData($sponsorship, $updates, 'deceased_father');

                    Log::info('✅ تم إنشاء سجل جديد للأب المتوفي', [
                        'new_dead_id' => $newDeadId,
                        're_file_id' => $newFileId,
                        'father_id' => $insertData['father_id']
                    ]);
                }
                break;

            // ============================================
            // حالة الأم المتوفية - جدول dead_people (حقول mother_*)
            // (نفس منطق OfflineTestController)
            // ============================================
            case 'deceased_mother':
                $result['table'] = 'dead_people';
                $record = null;

                // ✅ تسجيل البيانات المُستلمة للمتوفية
                Log::info('⚰️ بيانات الأم المتوفية المُستلمة', [
                    'sponsorship_id' => $sponsorship->id,
                    'first_name' => $updates['first_name'] ?? 'NOT SET',
                    'second_name' => $updates['second_name'] ?? 'NOT SET',
                    'third_name' => $updates['third_name'] ?? 'NOT SET',
                    'last_name' => $updates['last_name'] ?? 'NOT SET',
                    'identity_number' => $updates['identity_number'] ?? 'NOT SET',
                    'all_updates_keys' => array_keys($updates)
                ]);

                // البحث بـ re_file_id أولاً
                if (!empty($relationIdNumber)) {
                    $record = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على سجل الأم المتوفية بـ re_file_id', ['re_file_id' => $relationIdNumber]);
                    }
                }

                // البحث بـ mother_id إذا لم نجد
                if (!$record && !empty($identityNumber)) {
                    $record = DB::table('dead_people')->where('mother_id', $identityNumber)->first();
                    if ($record) {
                        Log::info('🔍 العثور على سجل الأم المتوفية بـ mother_id', ['mother_id' => $identityNumber]);
                    }
                }

                if ($record) {
                    // ===== السيناريو 4أ: تحديث سجل موجود =====
                    $updateData = [];

                    // تحديث حقول الأم (mother_*) مع ضمان حفظ الاسم في 4 حقول

                    // ✅ تحديث حقول الأم (mother_*) - حفظ الأسماء كما هي بدون تقسيم
                    if (isset($updates['first_name'])) $updateData['mother_first_name'] = $updates['first_name'];
                    if (isset($updates['second_name'])) $updateData['mother_second_name'] = $updates['second_name'];
                    if (isset($updates['third_name'])) $updateData['mother_third_name'] = $updates['third_name'];
                    if (isset($updates['last_name'])) $updateData['mother_last_name'] = $updates['last_name'];

                    // ⚠️ استخدام guardian_name/full_name فقط إذا لم تكن الأسماء المنفصلة موجودة
                    if (!isset($updates['first_name']) && !isset($updates['second_name']) &&
                        !isset($updates['third_name']) && !isset($updates['last_name'])) {
                        $fullName = null;
                        if (isset($updates['guardian_name']) && !empty(trim($updates['guardian_name']))) {
                            $fullName = trim($updates['guardian_name']);
                        } elseif (isset($updates['full_name']) && !empty(trim($updates['full_name']))) {
                            $fullName = trim($updates['full_name']);
                        }

                        if ($fullName) {
                            $nameParts = explode(' ', $fullName);
                            $nameParts = array_filter($nameParts);
                            $nameParts = array_values($nameParts);

                            $updateData['mother_first_name'] = $nameParts[0] ?? '';
                            $updateData['mother_second_name'] = $nameParts[1] ?? '';
                            $updateData['mother_third_name'] = $nameParts[2] ?? '';
                            $updateData['mother_last_name'] = $nameParts[3] ?? '';
                        }
                    }

                    if (isset($updates['identity_number'])) $updateData['mother_id'] = (int)preg_replace('/[^0-9]/', '', $updates['identity_number']);
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
                    // ===== السيناريو 4ب: إنشاء سجل جديد للأم المتوفية =====
                    Log::info('🆕 لا يوجد سجل للأم المتوفية - سيتم إنشاء سجل جديد');

                    // ✅ توليد رقم ملف جديد دائماً للمتوفين (لأن dead_people يحتاج re_file_id فريد)
                    $newFileId = generateFileIdFromDataTable();

                    // ✅ تعليم الكود كمستخدم في reserved_codes لمنع التضارب
                    markCodeAsUsed($newFileId, null, 'deceased_mother في dead_people - sponsorship_id: ' . $sponsorship->id);

                    $insertData = [
                        're_file_id' => $newFileId,
                        'mother_first_name' => $updates['first_name'] ?? '',
                        'mother_second_name' => $updates['second_name'] ?? '',
                        'mother_third_name' => $updates['third_name'] ?? '',
                        'mother_last_name' => $updates['last_name'] ?? '',
                        'mother_id' => !empty($updates['identity_number']) ? (int)preg_replace('/[^0-9]/', '', $updates['identity_number']) : null,
                        'mother_death_date' => $updates['birth_date'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $newDeadId = DB::table('dead_people')->insertGetId($insertData);

                    // ✅ تحديث relation_id_number في sponsorships دائماً بالرقم الجديد
                    DB::table('sponsorships')->where('id', $sponsorship->id)->update([
                        'relation_id_number' => $newFileId,
                        'updated_at' => now()
                    ]);

                    Log::info('✅ تم تحديث relation_id_number في sponsorships', [
                        'sponsorship_id' => $sponsorship->id,
                        'new_relation_id_number' => $newFileId
                    ]);

                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد للأم المتوفية';
                    $result['new_record_id'] = $newDeadId;
                    $result['new_file_id'] = $newFileId;

                    // حفظ البيانات الإضافية للمتوفين
                    $sponsorship->relation_id_number = $newFileId; // تحديث للاستخدام في saveDeceasedExtraData
                    $this->saveDeceasedExtraData($sponsorship, $updates, 'deceased_mother');

                    Log::info('✅ تم إنشاء سجل جديد للأم المتوفية', [
                        'new_dead_id' => $newDeadId,
                        're_file_id' => $newFileId,
                        'mother_id' => $insertData['mother_id']
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

                            // ✅ تعليم الكود كمستخدم
                            markCodeAsUsed($newFileId, null, 'سجل جديد في data (بحث تلقائي)');

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
     * حفظ بيانات المعيل (الولي/الكفيل) في الجدول المناسب حسب نوعه
     *
     * guardian_person_type يحدد الجدول المستهدف:
     * - breadwinner: جدول data
     * - family_member: جدول re_people
     * - deceased_father: جدول dead_people (كأب متوفي)
     * - deceased_mother: جدول dead_people (كأم متوفية)
     *
     * نبحث أولاً في جميع الجداول، وإذا لم نجد ننشئ في الجدول المناسب
     */
    private function saveGuardianToAppropriateTable($sponsorship, string $identityNumber, array $names, string $guardianPersonType, array $updates, int $userId): array
    {
        $result = [
            'action' => 'none',
            'table' => null,
            'record_id' => null,
            'created' => false,
            'file_id_number' => null
        ];

        try {
            // البحث عن سجل موجود للمعيل في جميع الجداول
            $existingRecord = null;
            $foundInTable = null;
            $searchedBy = null;

            // 1. البحث بـ relation_id_number من الكفالة (في جدول data فقط)
            if (!empty($sponsorship->relation_id_number)) {
                $existingRecord = DB::table('data')
                    ->where('file_id_number', $sponsorship->relation_id_number)
                    ->first();
                if ($existingRecord) {
                    $foundInTable = 'data';
                    $searchedBy = 'relation_id_number';
                    Log::info('✅ تم إيجاد المعيل في جدول data بواسطة relation_id_number', [
                        'relation_id_number' => $sponsorship->relation_id_number,
                        'data_id' => $existingRecord->id
                    ]);
                }
            }

            // 2. البحث برقم الهوية في جميع الجداول إذا لم نجد
            if (!$existingRecord && !empty($identityNumber)) {
                // البحث في جدول data
                $existingRecord = DB::table('data')
                    ->where('data_id_number', $identityNumber)
                    ->first();
                if ($existingRecord) {
                    $foundInTable = 'data';
                    $searchedBy = 'data_id_number';
                    Log::info('✅ تم إيجاد المعيل في جدول data برقم الهوية', [
                        'identity_number' => $identityNumber
                    ]);
                }

                // البحث في جدول dead_people (أب متوفي)
                if (!$existingRecord) {
                    $existingRecord = DB::table('dead_people')
                        ->where('father_id', $identityNumber)
                        ->first();
                    if ($existingRecord) {
                        $foundInTable = 'dead_people_father';
                        $searchedBy = 'father_id';
                        Log::info('✅ تم إيجاد المعيل في جدول dead_people (أب متوفي)', [
                            'identity_number' => $identityNumber,
                            'dead_people_id' => $existingRecord->id
                        ]);
                    }
                }

                // البحث في جدول dead_people (أم متوفية)
                if (!$existingRecord) {
                    $existingRecord = DB::table('dead_people')
                        ->where('mother_id', $identityNumber)
                        ->first();
                    if ($existingRecord) {
                        $foundInTable = 'dead_people_mother';
                        $searchedBy = 'mother_id';
                        Log::info('✅ تم إيجاد المعيل في جدول dead_people (أم متوفية)', [
                            'identity_number' => $identityNumber,
                            'dead_people_id' => $existingRecord->id
                        ]);
                    }
                }

                // البحث في جدول re_people
                if (!$existingRecord) {
                    $existingRecord = DB::table('re_people')
                        ->where('person_id', $identityNumber)
                        ->first();
                    if ($existingRecord) {
                        $foundInTable = 're_people';
                        $searchedBy = 'person_id';
                        Log::info('✅ تم إيجاد المعيل في جدول re_people', [
                            'identity_number' => $identityNumber,
                            're_people_id' => $existingRecord->id
                        ]);
                    }
                }
            }

            if ($existingRecord) {
                // تحديث السجل الموجود حسب الجدول
                $result = $this->updateExistingGuardianInTable(
                    $sponsorship,
                    $existingRecord,
                    $foundInTable,
                    $names,
                    $updates,
                    $userId,
                    $searchedBy
                );

            } else {
                // إنشاء سجل جديد للمعيل في الجدول المناسب حسب guardian_person_type
                $result = $this->createNewGuardianRecord(
                    $sponsorship,
                    $identityNumber,
                    $names,
                    $guardianPersonType,
                    $updates,
                    $userId
                );
            }

        } catch (\Exception $e) {
            Log::error('❌ خطأ في حفظ بيانات المعيل', [
                'sponsorship_id' => $sponsorship->id,
                'guardian_person_type' => $guardianPersonType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * إنشاء سجل جديد للمعيل في الجدول المناسب حسب نوعه
     */
    private function createNewGuardianRecord($sponsorship, string $identityNumber, array $names, string $guardianPersonType, array $updates, int $userId): array
    {
        $result = [
            'action' => 'created',
            'table' => null,
            'record_id' => null,
            'created' => true,
            'file_id_number' => null
        ];

        switch ($guardianPersonType) {
            case 'breadwinner':
            default:
                // إنشاء في جدول data
                $fileIdNumber = $this->generateNewGuardianFileId();

                $insertData = [
                    'file_id_number' => $fileIdNumber,
                    'data_id_number' => $identityNumber ?: null,
                    'data_first_name' => $names['first_name'] ?? '',
                    'data_father_name' => $names['father_name'] ?? '',
                    'data_grand_father_name' => $names['grandfather_name'] ?? '',
                    'data_family_name' => $names['family_name'] ?? '',
                    'data_phone_number' => $updates['guardian_phone'] ?? null,
                    'data_alt_phone_number' => $updates['guardian_phone2'] ?? null,
                    'data_current_address' => $updates['guardian_detailed_address'] ?? null,
                    'data_city' => $updates['guardian_city_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $newId = DB::table('data')->insertGetId($insertData);

                // تحديث relation_id_number في الكفالة
                DB::table('sponsorships')
                    ->where('id', $sponsorship->id)
                    ->update([
                        'relation_id_number' => $fileIdNumber,
                        'updated_at' => now()
                    ]);

                $result['table'] = 'data';
                $result['record_id'] = $newId;
                $result['file_id_number'] = $fileIdNumber;

                Log::info('🆕 تم إنشاء سجل جديد للمعيل في جدول data', [
                    'sponsorship_id' => $sponsorship->id,
                    'new_data_id' => $newId,
                    'file_id_number' => $fileIdNumber,
                    'identity_number' => $identityNumber,
                    'phone' => $updates['guardian_phone'] ?? null,
                    'alt_phone' => $updates['guardian_phone2'] ?? null,
                    'address' => $updates['guardian_detailed_address'] ?? null
                ]);
                break;

            case 'family_member':
                // إنشاء في جدول re_people
                $registrationId = $this->generateNewRePeopleFileId();

                $insertData = [
                    'registration_id' => $registrationId,
                    'person_id' => $identityNumber ?: null,
                    'first_name' => $names['first_name'] ?? '',
                    'second_name' => $names['father_name'] ?? '',
                    'third_name' => $names['grandfather_name'] ?? '',
                    'last_name' => $names['family_name'] ?? '',
                    // phone غير موجود في re_people - سيُحفظ في EAV
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $newId = DB::table('re_people')->insertGetId($insertData);

                // ✅ تحديث relation_id_number في الكفالة بـ registration_id الجديد
                DB::table('sponsorships')
                    ->where('id', $sponsorship->id)
                    ->update([
                        'relation_id_number' => $registrationId,
                        'updated_at' => now()
                    ]);

                // حفظ البيانات الإضافية في portal_general_registration_field_values
                $this->saveGuardianExtraFieldValues(
                    $sponsorship->id,
                    $registrationId,
                    $identityNumber,
                    $updates,
                    $userId
                );

                // تحديث الكفالة (حفظ البيانات الإضافية في EAV بدلاً من الجدول)
                // guardian_person_type غير موجود في جدول sponsorships

                $result['table'] = 're_people';
                $result['record_id'] = $newId;
                $result['file_id_number'] = $registrationId;

                Log::info('🆕 تم إنشاء سجل جديد للمعيل في جدول re_people', [
                    'sponsorship_id' => $sponsorship->id,
                    'new_re_people_id' => $newId,
                    'registration_id' => $registrationId,
                    'identity_number' => $identityNumber
                ]);
                break;

            case 'deceased_father':
                // إنشاء أو تحديث في جدول dead_people كأب متوفي
                // البحث باستخدام re_file_id (relation_id_number من الكفالة) أو father_id
                $deadPeopleRecord = null;

                if (!empty($sponsorship->relation_id_number)) {
                    $deadPeopleRecord = DB::table('dead_people')
                        ->where('re_file_id', $sponsorship->relation_id_number)
                        ->first();
                }

                // إذا لم نجد بـ re_file_id، نبحث بـ father_id
                if (!$deadPeopleRecord && !empty($identityNumber)) {
                    $deadPeopleRecord = DB::table('dead_people')
                        ->where('father_id', $identityNumber)
                        ->first();
                }

                if ($deadPeopleRecord) {
                    // تحديث سجل موجود
                    $updateData = [
                        'father_id' => $identityNumber ?: null,
                        'father_first_name' => $names['first_name'] ?? '',
                        'father_second_name' => $names['father_name'] ?? '',
                        'father_third_name' => $names['grandfather_name'] ?? '',
                        'father_last_name' => $names['family_name'] ?? '',
                        'updated_at' => now()
                    ];

                    DB::table('dead_people')
                        ->where('id', $deadPeopleRecord->id)
                        ->update($updateData);

                    $result['action'] = 'updated';
                    $result['created'] = false;
                    $result['record_id'] = $deadPeopleRecord->id;

                    Log::info('✅ تم تحديث بيانات الأب المتوفي في جدول dead_people', [
                        'sponsorship_id' => $sponsorship->id,
                        'dead_people_id' => $deadPeopleRecord->id
                    ]);
                } else {
                    // إنشاء سجل جديد - نحتاج re_file_id صالح
                    // إذا لم يوجد relation_id_number، نحتاج إنشاء سجل في data أولاً
                    $reFileId = $sponsorship->relation_id_number;
                    if (empty($reFileId)) {
                        // إنشاء سجل data أولاً
                        $newDataFileId = $this->generateNewGuardianFileId();
                        DB::table('data')->insert([
                            'file_id_number' => $newDataFileId,
                            'data_id_number' => $identityNumber,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $reFileId = $newDataFileId;

                        // تحديث relation_id_number في الكفالة
                        DB::table('sponsorships')
                            ->where('id', $sponsorship->id)
                            ->update(['relation_id_number' => $reFileId]);
                    }

                    $insertData = [
                        're_file_id' => $reFileId,
                        'father_id' => $identityNumber ?: null,
                        'father_first_name' => $names['first_name'] ?? '',
                        'father_second_name' => $names['father_name'] ?? '',
                        'father_third_name' => $names['grandfather_name'] ?? '',
                        'father_last_name' => $names['family_name'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $newId = DB::table('dead_people')->insertGetId($insertData);
                    $result['record_id'] = $newId;

                    Log::info('🆕 تم إنشاء سجل جديد للأب المتوفي في جدول dead_people', [
                        'sponsorship_id' => $sponsorship->id,
                        'new_dead_people_id' => $newId,
                        're_file_id' => $reFileId
                    ]);
                }

                // حفظ البيانات الإضافية (الهاتف، العنوان) في portal_general_registration_field_values
                // لأن جدول dead_people لا يحتوي على هذه الأعمدة
                $this->saveGuardianExtraFieldValues(
                    $sponsorship->id,
                    null, // لا يوجد file_id_number للمتوفين
                    $identityNumber,
                    $updates,
                    $userId
                );

                // تحديث الكفالة (حفظ البيانات الإضافية في EAV بدلاً من الجدول)
                // guardian_person_type غير موجود في جدول sponsorships

                $result['table'] = 'dead_people';
                break;

            case 'deceased_mother':
                // إنشاء أو تحديث في جدول dead_people كأم متوفية
                // البحث باستخدام re_file_id (relation_id_number من الكفالة) أو mother_id
                $deadPeopleRecord = null;

                if (!empty($sponsorship->relation_id_number)) {
                    $deadPeopleRecord = DB::table('dead_people')
                        ->where('re_file_id', $sponsorship->relation_id_number)
                        ->first();
                }

                // إذا لم نجد بـ re_file_id، نبحث بـ mother_id
                if (!$deadPeopleRecord && !empty($identityNumber)) {
                    $deadPeopleRecord = DB::table('dead_people')
                        ->where('mother_id', $identityNumber)
                        ->first();
                }

                if ($deadPeopleRecord) {
                    // تحديث سجل موجود
                    $updateData = [
                        'mother_id' => $identityNumber ?: null,
                        'mother_first_name' => $names['first_name'] ?? '',
                        'mother_second_name' => $names['father_name'] ?? '',
                        'mother_third_name' => $names['grandfather_name'] ?? '',
                        'mother_last_name' => $names['family_name'] ?? '',
                        'updated_at' => now()
                    ];

                    DB::table('dead_people')
                        ->where('id', $deadPeopleRecord->id)
                        ->update($updateData);

                    $result['action'] = 'updated';
                    $result['created'] = false;
                    $result['record_id'] = $deadPeopleRecord->id;

                    Log::info('✅ تم تحديث بيانات الأم المتوفية في جدول dead_people', [
                        'sponsorship_id' => $sponsorship->id,
                        'dead_people_id' => $deadPeopleRecord->id
                    ]);
                } else {
                    // إنشاء سجل جديد - نحتاج re_file_id صالح
                    $reFileId = $sponsorship->relation_id_number;
                    if (empty($reFileId)) {
                        // إنشاء سجل data أولاً
                        $newDataFileId = $this->generateNewGuardianFileId();
                        DB::table('data')->insert([
                            'file_id_number' => $newDataFileId,
                            'data_id_number' => $identityNumber,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $reFileId = $newDataFileId;

                        // تحديث relation_id_number في الكفالة
                        DB::table('sponsorships')
                            ->where('id', $sponsorship->id)
                            ->update(['relation_id_number' => $reFileId]);
                    }

                    $insertData = [
                        're_file_id' => $reFileId,
                        'mother_id' => $identityNumber ?: null,
                        'mother_first_name' => $names['first_name'] ?? '',
                        'mother_second_name' => $names['father_name'] ?? '',
                        'mother_third_name' => $names['grandfather_name'] ?? '',
                        'mother_last_name' => $names['family_name'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $newId = DB::table('dead_people')->insertGetId($insertData);
                    $result['record_id'] = $newId;

                    Log::info('🆕 تم إنشاء سجل جديد للأم المتوفية في جدول dead_people', [
                        'sponsorship_id' => $sponsorship->id,
                        'new_dead_people_id' => $newId,
                        're_file_id' => $reFileId
                    ]);
                }

                // حفظ البيانات الإضافية (الهاتف، العنوان) في portal_general_registration_field_values
                // لأن جدول dead_people لا يحتوي على هذه الأعمدة
                $this->saveGuardianExtraFieldValues(
                    $sponsorship->id,
                    null, // لا يوجد file_id_number للمتوفين
                    $identityNumber,
                    $updates,
                    $userId
                );

                // تحديث الكفالة (حفظ البيانات الإضافية في EAV بدلاً من الجدول)
                // guardian_person_type غير موجود في جدول sponsorships

                $result['table'] = 'dead_people';
                break;
        }

        return $result;
    }

    /**
     * توليد رقم ملف جديد لـ re_people (registration_id)
     */
    private function generateNewRePeopleFileId(): string
    {
        // registration_id هو رقم فقط وليس له بادئة
        $maxRegistrationId = DB::table('re_people')
            ->max('registration_id');

        // تحويل إلى int للتأكد من إمكانية الجمع
        $nextNumber = (int)($maxRegistrationId ?? 0) + 1;
        return (string) $nextNumber;
    }

    /**
     * حفظ البيانات الإضافية للمعيل (الهاتف، الهاتف البديل، العنوان) في جدول portal_general_registration_field_values
     *
     * يستخدم هذا عندما يكون المعيل في جدول لا يحتوي على هذه الأعمدة (مثل dead_people أو re_people)
     */
    private function saveGuardianExtraFieldValues(int $sponsorshipId, ?string $fileIdNumber, ?string $identityNumber, array $updates, int $userId): void
    {
        $fieldsToSave = [
            'guardian_phone' => $updates['guardian_phone'] ?? null,
            'guardian_phone2' => $updates['guardian_phone2'] ?? null,
            'guardian_detailed_address' => $updates['guardian_detailed_address'] ?? null,
        ];

        foreach ($fieldsToSave as $fieldKey => $fieldValue) {
            if ($fieldValue === null || $fieldValue === '') {
                continue;
            }

            try {
                // استخدام updateOrInsert لتجنب التكرار
                // الـ unique key هو على (file_id_number, field_key) وليس (sponsorship_id, field_key)
                DB::table('portal_general_registration_field_values')
                    ->updateOrInsert(
                        [
                            'file_id_number' => $fileIdNumber,
                            'field_key' => $fieldKey,
                        ],
                        [
                            'sponsorship_id' => $sponsorshipId,
                            'identity_number' => $identityNumber,
                            'field_value' => $fieldValue,
                            'updated_by_user_id' => $userId,
                            'updated_at' => now(),
                        ]
                    );

                Log::info('✅ تم حفظ حقل إضافي للمعيل', [
                    'sponsorship_id' => $sponsorshipId,
                    'field_key' => $fieldKey,
                    'field_value' => $fieldValue
                ]);
            } catch (\Exception $e) {
                Log::error('❌ خطأ في حفظ حقل إضافي للمعيل', [
                    'sponsorship_id' => $sponsorshipId,
                    'field_key' => $fieldKey,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * تحديث سجل المعيل الموجود في الجدول المناسب
     */
    private function updateExistingGuardianInTable($sponsorship, $existingRecord, string $table, array $names, array $updates, int $userId, string $searchedBy): array
    {
        $result = [
            'action' => 'updated',
            'table' => $table,
            'record_id' => $existingRecord->id,
            'created' => false,
            'file_id_number' => null
        ];

        switch ($table) {
            case 'data':
                $updateData = [
                    'updated_at' => now()
                ];

                if (!empty($names['first_name'])) $updateData['data_first_name'] = $names['first_name'];
                if (!empty($names['father_name'])) $updateData['data_father_name'] = $names['father_name'];
                if (!empty($names['grandfather_name'])) $updateData['data_grand_father_name'] = $names['grandfather_name'];
                if (!empty($names['family_name'])) $updateData['data_family_name'] = $names['family_name'];

                // الهاتف والعنوان والمدينة
                if (!empty($updates['guardian_phone'])) {
                    $updateData['data_phone_number'] = $updates['guardian_phone'];
                }
                if (!empty($updates['guardian_phone2'])) {
                    $updateData['data_alt_phone_number'] = $updates['guardian_phone2'];
                }
                if (!empty($updates['guardian_detailed_address'])) {
                    $updateData['data_current_address'] = $updates['guardian_detailed_address'];
                }
                // ✅ إضافة المدينة للمعيل/الولي
                if (isset($updates['guardian_city_id'])) {
                    $updateData['data_city'] = $updates['guardian_city_id'];
                }

                DB::table('data')->where('id', $existingRecord->id)->update($updateData);
                $result['file_id_number'] = $existingRecord->file_id_number;

                Log::info('✅ تم تحديث بيانات المعيل في جدول data', [
                    'sponsorship_id' => $sponsorship->id,
                    'data_id' => $existingRecord->id,
                    'file_id_number' => $existingRecord->file_id_number,
                    'searched_by' => $searchedBy,
                    'updated_fields' => array_keys($updateData)
                ]);
                break;

            case 'dead_people_father':
                $updateData = ['updated_at' => now()];

                if (!empty($names['first_name'])) $updateData['father_first_name'] = $names['first_name'];
                if (!empty($names['father_name'])) $updateData['father_second_name'] = $names['father_name'];
                if (!empty($names['grandfather_name'])) $updateData['father_third_name'] = $names['grandfather_name'];
                if (!empty($names['family_name'])) $updateData['father_last_name'] = $names['family_name'];

                DB::table('dead_people')->where('id', $existingRecord->id)->update($updateData);

                // حفظ البيانات الإضافية في portal_general_registration_field_values
                $this->saveGuardianExtraFieldValues(
                    $sponsorship->id,
                    null,
                    $existingRecord->father_id ?? null,
                    $updates,
                    $userId
                );

                Log::info('✅ تم تحديث بيانات المعيل (أب متوفي) في جدول dead_people', [
                    'sponsorship_id' => $sponsorship->id,
                    'dead_people_id' => $existingRecord->id,
                    'updated_fields' => array_keys($updateData)
                ]);
                break;

            case 'dead_people_mother':
                $updateData = ['updated_at' => now()];

                if (!empty($names['first_name'])) $updateData['mother_first_name'] = $names['first_name'];
                if (!empty($names['father_name'])) $updateData['mother_second_name'] = $names['father_name'];
                if (!empty($names['grandfather_name'])) $updateData['mother_third_name'] = $names['grandfather_name'];
                if (!empty($names['family_name'])) $updateData['mother_last_name'] = $names['family_name'];

                DB::table('dead_people')->where('id', $existingRecord->id)->update($updateData);

                // حفظ البيانات الإضافية في portal_general_registration_field_values
                $this->saveGuardianExtraFieldValues(
                    $sponsorship->id,
                    null,
                    $existingRecord->mother_id ?? null,
                    $updates,
                    $userId
                );

                Log::info('✅ تم تحديث بيانات المعيل (أم متوفية) في جدول dead_people', [
                    'sponsorship_id' => $sponsorship->id,
                    'dead_people_id' => $existingRecord->id,
                    'updated_fields' => array_keys($updateData)
                ]);
                break;

            case 're_people':
                $updateData = ['updated_at' => now()];

                if (!empty($names['first_name'])) $updateData['first_name'] = $names['first_name'];
                if (!empty($names['father_name'])) $updateData['second_name'] = $names['father_name'];
                if (!empty($names['grandfather_name'])) $updateData['third_name'] = $names['grandfather_name'];
                if (!empty($names['family_name'])) $updateData['last_name'] = $names['family_name'];

                // جدول re_people لا يحتوي على عمود phone - سيُحفظ في EAV

                DB::table('re_people')->where('id', $existingRecord->id)->update($updateData);
                $result['file_id_number'] = $existingRecord->registration_id ?? null;

                // حفظ البيانات الإضافية في portal_general_registration_field_values
                $this->saveGuardianExtraFieldValues(
                    $sponsorship->id,
                    $existingRecord->registration_id ?? null,
                    $existingRecord->person_id ?? null,
                    $updates,
                    $userId
                );

                Log::info('✅ تم تحديث بيانات المعيل في جدول re_people', [
                    'sponsorship_id' => $sponsorship->id,
                    're_people_id' => $existingRecord->id,
                    'updated_fields' => array_keys($updateData)
                ]);
                break;
        }

        return $result;
    }

    /**
     * توليد رقم ملف جديد للمعيل
     */
    private function generateNewGuardianFileId(): string
    {
        $maxFileId = DB::table('data')
            ->selectRaw("CAST(SUBSTRING(file_id_number, 2) AS UNSIGNED) as num")
            ->whereRaw("file_id_number LIKE 'M%' AND file_id_number REGEXP '^M[0-9]+$'")
            ->orderByDesc('num')
            ->value('num');

        $nextNumber = ($maxFileId ?? 0) + 1;
        return 'M' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
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
     * توليد رقم ملف جديد فريد - يستخدم الخوارزمية الرسمية للموقع
     * يجب أن يكون الرقم متسلسل ومتوافق مع النظام الحالي
     */
    private function generateNewFileId(string $tableType): string
    {
        // استخدام الدالة الرسمية لتوليد رقم الملف من جدول data
        // هذا يضمن أن الرقم متسلسل ومتوافق مع بقية النظام
        return generateFileIdFromDataTable();
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
            
            // إضافة مصفوفة بجميع المعرفات السليمة للحذف المحلي (Pruning)
            $validSponsorshipIds = DB::table('sponsorships')->pluck('id')->toArray();

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
                    'valid_sponsorship_ids' => $validSponsorshipIds,
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
                    'sponsorships.sponsoring_organization', // اسم الكافل
                    'sponsorships.notes',
                    'sponsorships.created_at',
                    'sponsorships.updated_at'
                ]);

            // (تم إزالة استبعاد الحالات المكتملة بناءً على طلب المستخدم لمزامنة جميع البيانات)

            // مزامنة تزايدية إذا تم توفير last_sync
            if ($lastSync) {
                try {
                    $lastSyncFormatted = \Carbon\Carbon::parse($lastSync)->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
                    $query->where('sponsorships.updated_at', '>', $lastSyncFormatted);
                } catch (\Exception $e) {
                    $query->where('sponsorships.updated_at', '>', $lastSync);
                }
            }

            // إجمالي السجلات
            $total = $query->count();

            // جلب البيانات
            $sponsorships = $query
                ->orderBy('sponsorships.updated_at', 'desc')
                ->orderBy('sponsorships.id', 'desc') // إضافة فرز ثانوي لضمان استقرار الصفحات
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
    // Related Data Tables Sync
    // ========================================

    /**
     * GET /api/mobile/sync/data-table
     * جلب جميع بيانات جدول data (المعيلين/أرباب الأسر)
     */
    public function getSyncDataTable(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 200), 500);
            $lastSync = $request->get('last_sync');

            $query = DB::table('data')
                ->select([
                    'id', 'file_id_number', 'data_section_id', 'data_request_status',
                    'data_id_number', 'data_first_name', 'data_father_name',
                    'data_grand_father_name', 'data_family_name',
                    'data_relationship', 'data_birth_date', 'data_gender',
                    'data_phone_number', 'data_alt_phone_number',
                    'data_number_of_individuals', 'data_marital_status',
                    'data_academic_qualification', 'data_displacement_status',
                    'data_address_before_displacement', 'data_current_address',
                    'data_city', 'data_province', 'data_health_status',
                    'data_description_needs',
                    'data_employment_status_breadwinner', 'data_housing_status',
                    'data_current_housing_type', 'data_user_insert_data',
                    'created_at', 'updated_at'
                ]);

            if ($lastSync) {
                $query->where('updated_at', '>', $lastSync);
            }

            $total = $query->count();
            $data = $query->orderBy('id', 'asc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / $perPage)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/sync/re-people
     * جلب جميع بيانات جدول re_people (أفراد العائلة)
     */
    public function getSyncRePeople(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 200), 500);
            $lastSync = $request->get('last_sync');

            $query = DB::table('re_people')
                ->select([
                    'id', 'registration_id', 'sponsorship_status',
                    'first_name', 'second_name', 'third_name', 'last_name',
                    'person_id', 'person_birth_date', 'person_age',
                    'person_gender', 'person_health_status',
                    'person_type_of_guarantee',
                    'person_note', 'created_at', 'updated_at'
                ]);

            if ($lastSync) {
                $query->where('updated_at', '>', $lastSync);
            }

            $total = $query->count();
            $data = $query->orderBy('id', 'asc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / $perPage)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/sync/dead-people
     * جلب جميع بيانات جدول dead_people (المتوفون)
     */
    public function getSyncDeadPeople(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 200), 500);
            $lastSync = $request->get('last_sync');

            $query = DB::table('dead_people')
                ->select([
                    'id', 're_file_id', 'sponsorship_status',
                    'father_first_name', 'father_second_name', 'father_third_name', 'father_last_name',
                    'father_id', 'father_death_date', 'father_death_reason',
                    'mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name',
                    'mother_id', 'mother_death_reason',
                    'created_at', 'updated_at'
                ]);

            if ($lastSync) {
                $query->where('updated_at', '>', $lastSync);
            }

            $total = $query->count();
            $data = $query->orderBy('id', 'asc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / $perPage)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/sync/bank-accounts
     * جلب جميع بيانات جدول guardian_bank_accounts (الحسابات البنكية)
     */
    public function getSyncBankAccounts(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 200), 500);
            $lastSync = $request->get('last_sync');

            $query = DB::table('guardian_bank_accounts')
                ->leftJoin('bank_names', 'guardian_bank_accounts.bank_name', '=', 'bank_names.id')
                ->select([
                    'guardian_bank_accounts.id',
                    'guardian_bank_accounts.guardian_registration',
                    'guardian_bank_accounts.bank_name',
                    'guardian_bank_accounts.iban_usd',
                    'guardian_bank_accounts.iban_shekel',
                    'guardian_bank_accounts.check_account',
                    'guardian_bank_accounts.re_id_number',
                    'guardian_bank_accounts.re_guardian_name',
                    'guardian_bank_accounts.re_phone_number',
                    'guardian_bank_accounts.person_owner_identity_number',
                    'bank_names.description as bank_name_text',
                    'guardian_bank_accounts.created_at',
                    'guardian_bank_accounts.updated_at'
                ]);

            if ($lastSync) {
                $query->where('guardian_bank_accounts.updated_at', '>', $lastSync);
            }

            $total = $query->count();
            $data = $query->orderBy('guardian_bank_accounts.id', 'asc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / $perPage)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/sync/death-reasons
     * جلب أسباب الوفاة
     */
    public function getSyncDeathReasons(Request $request): JsonResponse
    {
        try {
            $data = DB::table('death_reasons')
                ->select(['id', 'description'])
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ========================================
    // File Upload
    // ========================================

    /**
     * POST /api/mobile/upload-file
     * رفع ملف إلى Google Drive
     * يدعم كلا من multipart (FileSyncWorker.java) و base64 (JavaScript القديم)
     */
    public function uploadFile(Request $request): JsonResponse
    {
        try {
            // Check if files or file was provided. Note: files[] in form data becomes 'files' array in PHP
            if (!$request->hasFile('files') && !$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم إرسال ملف'
                ], 400);
            }

            // Extract the first file from array if it's 'files', else get 'file'
            $file = $request->hasFile('files') 
                ? (is_array($request->file('files')) ? $request->file('files')[0] : $request->file('files'))
                : $request->file('file');
                
            $recordNumber = $request->input('record_number');
            $personId = $request->input('person_id');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'الملف تالف أو غير صالح'
                ], 400);
            }

            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $filePath = $file->storeAs('uploads/' . date('Y/m'), $fileName, 'public');

            $attachment = \App\Models\Attachment::create([
                'person_identity_number' => $personId ?? $recordNumber,
                'stored_file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => $file->getMimeType()
            ]);

            // Dispatch Google Drive Upload Job
            $sponsorship = \App\Models\Sponsorship::find($personId);
            $sponsorName = $sponsorship ? $sponsorship->sponsor_name : 'Unknown_Sponsor';
            $orphanName = $sponsorship ? $sponsorship->orphan_name : 'Unknown_Orphan';

            \App\Jobs\UploadToGoogleDriveJob::dispatch(
                $attachment->id,
                $filePath, // e.g. "uploads/2026/07/filename.jpg"
                $fileName,
                $sponsorName,
                $orphanName
            );

            return response()->json([
                'success' => true,
                'file_id' => $attachment->id,
                'file_name' => $fileName,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [UPLOAD FILE] خطأ: ' . $e->getMessage());
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
            // استخدام updateOrInsert لتجنب التكرار
            // الـ unique key هو على (file_id_number, field_key)
            DB::table('portal_general_registration_field_values')
                ->updateOrInsert(
                    [
                        'file_id_number' => $fileIdNumber,
                        'field_key' => $fieldKey,
                    ],
                    [
                        'sponsorship_id' => $sponsorshipId,
                        'identity_number' => $identityNumber,
                        'field_value' => $fieldValue,
                        'updated_by_user_id' => $userId,
                        'updated_at' => now()
                    ]
                );

            Log::info('✅ تم حفظ/تحديث قيمة الحقل في portal_general_registration_field_values', [
                'sponsorship_id' => $sponsorshipId,
                'file_id_number' => $fileIdNumber,
                'field_key' => $fieldKey,
                'field_value' => $fieldValue
            ]);

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
        // ✅ للمتوفين: استخدام relation_id_number أولاً (لأنه يحتوي على re_file_id الجديد)
        // لأن internal_file_number قد يكون قديماً أو فارغاً
        $fileIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number ?? '';

        Log::info('📝 saveDeceasedExtraData - بدء حفظ البيانات الإضافية للمتوفي', [
            'person_type' => $personType,
            'sponsorship_id' => $sponsorship->id,
            'file_id_number_used' => $fileIdNumber,
            'relation_id_number' => $sponsorship->relation_id_number,
            'internal_file_number' => $sponsorship->internal_file_number ?? 'NOT SET'
        ]);

        // حفظ العنوان التفصيلي - دعم أسماء حقول متعددة
        $address = $updates['orphan_detailed_address'] ?? $updates['guardian_detailed_address'] ?? null;
        if (!empty($address)) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $fileIdNumber,
                $updates['identity_number'] ?? $sponsorship->identity_number ?? null,
                'field_housing_address_detail',
                $address,
                $userId
            );
        }

        // حفظ رقم الهاتف - دعم أسماء حقول متعددة
        $phone = $updates['orphan_phone'] ?? $updates['guardian_phone'] ?? null;
        if (!empty($phone)) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $fileIdNumber,
                $updates['identity_number'] ?? $sponsorship->identity_number ?? null,
                'field_data_phone_number',
                $phone,
                $userId
            );
        }

        // حفظ رقم الهاتف البديل - دعم أسماء حقول متعددة
        $altPhone = $updates['orphan_phone2'] ?? $updates['guardian_phone2'] ?? null;
        if (!empty($altPhone)) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $fileIdNumber,
                $updates['identity_number'] ?? $sponsorship->identity_number ?? null,
                'field_data_alt_phone_number',
                $altPhone,
                $userId
            );
        }

        // حفظ الحالة الصحية
        if (isset($updates['health_status_id'])) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $fileIdNumber,
                $updates['identity_number'] ?? $sponsorship->identity_number ?? null,
                'field_health_status',
                $updates['health_status_id'],
                $userId
            );
        }

        // ✅ حفظ المدينة للمتوفين
        $cityId = $updates['orphan_city_id'] ?? $updates['guardian_city_id'] ?? null;
        if (!empty($cityId)) {
            $this->savePortalFieldValue(
                $sponsorship->id,
                $fileIdNumber,
                $updates['identity_number'] ?? $sponsorship->identity_number ?? null,
                'field_data_city',
                $cityId,
                $userId
            );
        }

        Log::info('✅ تم حفظ البيانات الإضافية للمتوفي', [
            'person_type' => $personType,
            'sponsorship_id' => $sponsorship->id,
            'phone' => $phone,
            'alt_phone' => $altPhone,
            'address' => $address,
            'city_id' => $cityId
        ]);
    }

    /**
     * تغيير حالة الكفالة إلى "انتظار الصرف" عند أي تعديل من التطبيق المحمول
     *
     * @param int $sponsorshipId معرف الكفالة
     * @param int|null $userId معرف المستخدم الذي قام بالتعديل
     * @return bool نجاح العملية
     */
    private function updateSponsorshipStatusToWaitingPayment(int $sponsorshipId, ?int $userId = null): bool
    {
        try {
            // البحث عن حالة "انتظار الصرف" في قاعدة البيانات
            $waitingPaymentStatus = DB::table('sponsorship_statuses')
                ->where('description', 'LIKE', '%انتظار الصرف%')
                ->orWhere('description', 'LIKE', '%انتظار%الصرف%')
                ->first();

            // إذا لم نجد حالة "انتظار الصرف"، نبحث عن حالة "محدث"
            if (!$waitingPaymentStatus) {
                $waitingPaymentStatus = DB::table('sponsorship_statuses')
                    ->where('description', 'LIKE', '%محدث%')
                    ->orWhere('id', 3) // ID = 3 للحالة "محدث" كافتراضي
                    ->first();
            }

            if (!$waitingPaymentStatus) {
                Log::warning('⚠️ لم يتم العثور على حالة "انتظار الصرف" أو "محدث"', [
                    'sponsorship_id' => $sponsorshipId
                ]);
                return false;
            }

            // تحديث حالة الكفالة مهما كانت الحالة الحالية
            DB::table('sponsorships')
                ->where('id', $sponsorshipId)
                ->update([
                    'sponsorship_status_id' => $waitingPaymentStatus->id,
                    'updated_at' => now(),
                    'updated_by' => $userId
                ]);

            Log::info('✅ تم تغيير حالة الكفالة إلى "انتظار الصرف"', [
                'sponsorship_id' => $sponsorshipId,
                'new_status_id' => $waitingPaymentStatus->id,
                'new_status_name' => $waitingPaymentStatus->description,
                'user_id' => $userId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('❌ فشل تغيير حالة الكفالة', [
                'sponsorship_id' => $sponsorshipId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * حفظ أو تحديث بيانات المعيل في جدول data
     */
    private function saveOrUpdateInDataTable($sponsorship, $dataUpdates, $sponsorshipId)
    {
        if (empty($dataUpdates)) {
            return false;
        }

        Log::info('🔄 بدء حفظ/تحديث بيانات المعيل في جدول data', [
            'relation_id_number' => $sponsorship->relation_id_number ?? null,
            'guardian_identity_number' => $sponsorship->guardian_identity_number ?? null,
            'updates' => array_keys($dataUpdates)
        ]);

        $guardianUpdated = false;
        $guardianIdentity = $sponsorship->guardian_identity_number;

        // 1) محاولة التحديث باستخدام relation_id_number
        if (!$guardianUpdated && !empty($sponsorship->relation_id_number) && !empty($dataUpdates)) {
            $existingData = DB::table('data')
                ->where('file_id_number', $sponsorship->relation_id_number)
                ->first();

            if ($existingData) {
                $dataUpdates['updated_at'] = now();
                DB::table('data')
                    ->where('file_id_number', $sponsorship->relation_id_number)
                    ->update($dataUpdates);

                Log::info('✅ تم تحديث بيانات المعيل في جدول data (بـ relation_id)', [
                    'relation_id_number' => $sponsorship->relation_id_number,
                    'updates' => array_keys($dataUpdates)
                ]);
                $guardianUpdated = true;
            }
        }

        // 2) محاولة التحديث/الإنشاء باستخدام guardian_identity_number
        if (!$guardianUpdated && !empty($guardianIdentity) && !empty($dataUpdates)) {
            $existingData = DB::table('data')
                ->where('data_id_number', $guardianIdentity)
                ->first();

            if ($existingData) {
                $dataUpdates['updated_at'] = now();
                DB::table('data')
                    ->where('data_id_number', $guardianIdentity)
                    ->update($dataUpdates);

                // تحديث relation_id_number في sponsorships إذا كان فارغاً
                if (empty($sponsorship->relation_id_number) && !empty($existingData->file_id_number)) {
                    DB::table('sponsorships')
                        ->where('id', $sponsorshipId)
                        ->update(['relation_id_number' => $existingData->file_id_number, 'updated_at' => now()]);
                }

                $guardianUpdated = true;
            } else {
                // إنشاء سجل جديد في data
                $newFileId = generateFileIdFromDataTable();
                $insertData = array_merge($dataUpdates, [
                    'file_id_number' => $newFileId,
                    'data_id_number' => $guardianIdentity,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                DB::table('data')->insert($insertData);

                // تحديث relation_id_number في sponsorships
                DB::table('sponsorships')
                    ->where('id', $sponsorshipId)
                    ->update(['relation_id_number' => $newFileId, 'updated_at' => now()]);

                Log::info('✅ تم إنشاء سجل جديد في جدول data', [
                    'file_id_number' => $newFileId,
                    'guardian_identity_number' => $guardianIdentity
                ]);
                $guardianUpdated = true;
            }
        }

        return $guardianUpdated;
    }

    /**
     * حفظ أو تحديث بيانات المعيل المتوفي في جدول dead_people
     */
    private function saveOrUpdateInDeadPeopleTable($sponsorship, $deadUpdates, $guardianPersonType)
    {
        if (empty($deadUpdates)) {
            return false;
        }

        Log::info('🔄 بدء حفظ/تحديث بيانات المعيل المتوفي في جدول dead_people', [
            'guardian_person_type' => $guardianPersonType,
            'updates' => array_keys($deadUpdates)
        ]);

        $guardianIdentity = $sponsorship->guardian_identity_number;
        $updated = false;

        if (!empty($guardianIdentity)) {
            // محاولة العثور على السجل الموجود
            $existingRecord = DB::table('dead_people')
                ->where(function($q) use ($guardianIdentity, $guardianPersonType) {
                    if ($guardianPersonType === 'deceased_father') {
                        $q->where('father_id', $guardianIdentity);
                    } else {
                        $q->where('mother_id', $guardianIdentity);
                    }
                })
                ->first();

            if ($existingRecord) {
                $deadUpdates['updated_at'] = now();
                DB::table('dead_people')
                    ->where('id', $existingRecord->id)
                    ->update($deadUpdates);
                $updated = true;
            } else {
                // إنشاء سجل جديد
                $deadUpdates['created_at'] = now();
                $deadUpdates['updated_at'] = now();

                DB::table('dead_people')->insert($deadUpdates);
                $updated = true;
            }

            if ($updated) {
                Log::info('✅ تم حفظ/تحديث بيانات المعيل المتوفي', [
                    'guardian_person_type' => $guardianPersonType,
                    'guardian_identity' => $guardianIdentity
                ]);
            }
        }

        return $updated;
    }

    /**
     * حفظ أو تحديث بيانات المعيل في جدول re_people
     */
    private function saveOrUpdateInRePeopleTable($sponsorship, $reUpdates)
    {
        if (empty($reUpdates)) {
            return false;
        }

        Log::info('🔄 بدء حفظ/تحديث بيانات المعيل في جدول re_people', [
            'updates' => array_keys($reUpdates)
        ]);

        $guardianIdentity = $sponsorship->guardian_identity_number;
        $updated = false;

        if (!empty($guardianIdentity)) {
            // محاولة العثور على السجل الموجود
            $existingRecord = DB::table('re_people')
                ->where('person_id', $guardianIdentity)
                ->first();

            if ($existingRecord) {
                $reUpdates['updated_at'] = now();
                DB::table('re_people')
                    ->where('person_id', $guardianIdentity)
                    ->update($reUpdates);
                $updated = true;
            } else {
                // إنشاء سجل جديد
                $reUpdates['created_at'] = now();
                $reUpdates['updated_at'] = now();

                DB::table('re_people')->insert($reUpdates);
                $updated = true;
            }

            if ($updated) {
                Log::info('✅ تم حفظ/تحديث بيانات المعيل في re_people', [
                    'guardian_identity' => $guardianIdentity
                ]);
            }
        }

        return $updated;
    }

    // ========================================
    // Additional Methods for Online Mode
    // ========================================

    /**
     * POST /api/online/update-sponsorship
     * تحديث بيانات كفالة مباشرة
     */
    public function updateSponsorship(Request $request): JsonResponse
    {
        try {
            $sponsorshipId = $request->input('sponsorship_id');
            $updates = $request->input('updates', []);

            if (!$sponsorshipId) {
                return response()->json(['success' => false, 'message' => 'معرف الكفالة مطلوب'], 400);
            }

            $sponsorship = DB::table('sponsorships')->where('id', $sponsorshipId)->first();
            if (!$sponsorship) {
                return response()->json(['success' => false, 'message' => 'الكفالة غير موجودة'], 404);
            }

            $updates['updated_at'] = now();
            DB::table('sponsorships')->where('id', $sponsorshipId)->update($updates);

            Log::info('Sponsorship updated via online mode', ['id' => $sponsorshipId, 'user' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'تم التحديث بنجاح',
                'sponsorship_id' => $sponsorshipId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update sponsorship', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل التحديث'], 500);
        }
    }

    /**
     * POST /api/online/update-photo
     * رفع صورة وتحديثها مباشرة
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        try {
            $sponsorshipId = $request->input('sponsorship_id');
            $photoType = $request->input('photo_type'); // 'orphan' or 'guardian'

            if (!$request->hasFile('photo')) {
                return response()->json(['success' => false, 'message' => 'الصورة مطلوبة'], 400);
            }

            $file = $request->file('photo');
            $path = $file->store('photos/' . $photoType, 'public');

            // تحديث قاعدة البيانات
            $column = $photoType === 'orphan' ? 'orphan_photo_path' : 'guardian_photo_path';
            DB::table('sponsorships')
                ->where('id', $sponsorshipId)
                ->update([$column => $path, 'updated_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'تم رفع الصورة بنجاح',
                'path' => $path
            ]);

        } catch (\Exception $e) {
            Log::error('Photo upload failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل رفع الصورة'], 500);
        }
    }

    /**
     * POST /api/online/register-device
     * تسجيل جهاز لإشعارات Push
     */
    public function registerDevice(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $deviceToken = $request->input('device_token');
            $deviceId = $request->input('device_id');
            $platform = $request->input('platform', 'android'); // android or ios

            if (!$deviceToken) {
                return response()->json(['success' => false, 'message' => 'Device token مطلوب'], 400);
            }

            // حفظ في جدول devices (يجب إنشاؤه)
            DB::table('user_devices')->updateOrInsert(
                ['device_id' => $deviceId],
                [
                    'user_id' => $userId,
                    'device_token' => $deviceToken,
                    'platform' => $platform,
                    'last_active' => now(),
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())')
                ]
            );

            Log::info('Device registered for push notifications', [
                'user_id' => $userId,
                'device_id' => $deviceId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الجهاز بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('Device registration failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل تسجيل الجهاز'], 500);
        }
    }

    /**
     * POST /api/online/unregister-device
     * إلغاء تسجيل جهاز
     */
    public function unregisterDevice(Request $request): JsonResponse
    {
        try {
            $deviceId = $request->input('device_id');

            DB::table('user_devices')->where('device_id', $deviceId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء تسجيل الجهاز'
            ]);

        } catch (\Exception $e) {
            Log::error('Device unregistration failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'فشل إلغاء التسجيل'], 500);
        }
    }
    /**
     * POST /api/mobile/sync/bulk-upload
     * المزامنة الجماعية للتحديثات
     */
    public function bulkUpsert(Request $request): JsonResponse
    {
        $request->validate([
            'changes' => 'required|array',
            'changes.*.sponsorship_id' => 'required|integer',
            'changes.*.updates' => 'required|array',
        ]);
        
        $allUpdates = collect($request->changes)->map(function ($change) use ($request) {
            return array_merge(
                ['id' => $change['sponsorship_id']],
                array_intersect_key($change['updates'], array_flip([
                    'orphan_name', 'identity_number', 'guardian_name',
                    'guardian_identity_number', 'notes', 'sponsorship_status_id'
                ])),
                [
                    'updated_at' => now(),
                    'updated_by' => json_encode([
                        'user_id' => $request->user()->id,
                        'timestamp' => now()->toISOString()
                    ])
                ]
            );
        })->toArray();
        
        $chunks = array_chunk($allUpdates, 5000);
        $totalUpdated = 0;
        
        foreach ($chunks as $chunk) {
            DB::table('sponsorships')->upsert(
                $chunk,
                ['id'], // Unique key
                ['orphan_name', 'identity_number', 'guardian_name',
                 'guardian_identity_number', 'notes', 'sponsorship_status_id',
                 'updated_at', 'updated_by'] // Updatable columns
            );
            $totalUpdated += count($chunk);
        }
        
        return response()->json([
            'success' => true,
            'updated' => $totalUpdated
        ]);
    }
}

