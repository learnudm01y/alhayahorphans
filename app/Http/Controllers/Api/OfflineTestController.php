<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Sponsorship;
use App\Models\Sponsor;
use App\Models\SponsorshipStatus;
use App\Models\HealthStatus;
use App\Models\City;
use App\Models\BankName;
use App\Models\TypeOfGuarantee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * OfflineTestController - للاختبار المحلي فقط
 *
 * ⚠️ تحذير: لا تستخدم هذا في الإنتاج - للتطوير والاختبار فقط!
 *
 * أسماء الأعمدة الصحيحة:
 * - health_statuses: id, description
 * - city: id, city
 * - bank_names: id, description
 * - sponsorship_statuses: id, description
 * - type_of_guarantee: id, description
 * - sponsors: id, sponsor_name, sponsor_short_name
 */
class OfflineTestController extends Controller
{
    /**
     * فحص صحة الاتصال
     */
    public function health(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'healthy',
                'message' => 'الخادم يعمل بشكل صحيح',
                'database' => 'متصل',
                'timestamp' => now()->toISOString(),
                'version' => 'offline-test-v1.1'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'message' => 'خطأ في الاتصال بقاعدة البيانات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تسجيل الدخول
     */
    public function login(Request $request): JsonResponse
    {
        Log::info('🧪 Offline Test Login Attempt', [
            'username' => $request->username,
            'ip' => $request->ip()
        ]);

        try {
            $username = $request->input('username', 'admin');
            $password = $request->input('password', '');

            $user = User::where('name', $username)
                ->orWhere('email', $username)
                ->first();

            if (!$user) {
                $user = User::where('role', 'admin')->first();

                if (!$user) {
                    return response()->json([
                        'success' => true,
                        'message' => 'تم تسجيل الدخول (وضع الاختبار)',
                        'user' => [
                            'id' => 1,
                            'name' => $username ?: 'مستخدم اختبار',
                            'email' => 'test@test.com',
                            'role' => 'admin'
                        ],
                        'token' => 'test-token-' . time(),
                        'test_mode' => true
                    ]);
                }
            }

            Log::info('🧪 Offline Test Login Success', [
                'user_id' => $user->id,
                'username' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email ?? 'test@test.com',
                    'role' => $user->role ?? 'admin'
                ],
                'token' => 'offline-test-token-' . $user->id . '-' . time(),
                'test_mode' => true
            ]);

        } catch (\Exception $e) {
            Log::error('🧪 Offline Test Login Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تسجيل الدخول: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب الكفلاء (sponsors)
     * الأعمدة: id, sponsor_name, sponsor_short_name
     */
    public function getSponsors(): JsonResponse
    {
        try {
            $sponsors = Sponsor::select('id', 'sponsor_name', 'sponsor_short_name')
                ->whereNotNull('sponsor_name')
                ->orderBy('sponsor_name')
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->sponsor_name,
                    'short_name' => $s->sponsor_short_name
                ]);

            return response()->json([
                'success' => true,
                'count' => $sponsors->count(),
                'sponsors' => $sponsors
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sponsors', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الكفلاء',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب حالات الكفالة (sponsorship_statuses)
     * الأعمدة: id, description
     */
    public function getStatuses(): JsonResponse
    {
        try {
            $statuses = SponsorshipStatus::select('id', 'description')
                ->orderBy('id')
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->description
                ]);

            return response()->json([
                'success' => true,
                'count' => $statuses->count(),
                'statuses' => $statuses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب حالات الكفالة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب الحالات الصحية (health_statuses)
     * الأعمدة: id, description
     */
    public function getHealthStatuses(): JsonResponse
    {
        try {
            $healthStatuses = HealthStatus::select('id', 'description')
                ->orderBy('id')
                ->get()
                ->map(fn($h) => [
                    'id' => $h->id,
                    'name' => $h->description
                ]);

            return response()->json([
                'success' => true,
                'count' => $healthStatuses->count(),
                'health_statuses' => $healthStatuses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الحالات الصحية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب المدن (city)
     * الأعمدة: id, city
     */
    public function getCities(): JsonResponse
    {
        try {
            $cities = City::select('id', 'city')
                ->orderBy('city')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->city
                ]);

            return response()->json([
                'success' => true,
                'count' => $cities->count(),
                'cities' => $cities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب المدن',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب أسماء البنوك (bank_names)
     * الأعمدة: id, description
     */
    public function getBanks(): JsonResponse
    {
        try {
            $banks = BankName::select('id', 'description')
                ->orderBy('description')
                ->get()
                ->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->description
                ]);

            return response()->json([
                'success' => true,
                'count' => $banks->count(),
                'banks' => $banks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب البنوك',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب البيانات الأولية للمزامنة
     */
    public function initialSync(): JsonResponse
    {
        Log::info('🔄 [OfflineTest] initialSync - بدء المزامنة الأولية');

        try {
            // الكفلاء - sponsors: id, sponsor_name, sponsor_short_name
            $sponsors = Sponsor::select('id', 'sponsor_name', 'sponsor_short_name')
                ->whereNotNull('sponsor_name')
                ->orderBy('sponsor_name')
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->sponsor_name,
                    'short_name' => $s->sponsor_short_name
                ]);

            // حالات الكفالة - sponsorship_statuses: id, description
            $statuses = SponsorshipStatus::select('id', 'description')
                ->orderBy('id')
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->description
                ]);

            // الحالات الصحية - health_statuses: id, description
            $healthStatuses = HealthStatus::select('id', 'description')
                ->orderBy('id')
                ->get()
                ->map(fn($h) => [
                    'id' => $h->id,
                    'name' => $h->description
                ]);

            // المدن - city: id, city
            $cities = City::select('id', 'city')
                ->orderBy('city')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->city
                ]);

            // البنوك - bank_names: id, description
            $banks = BankName::select('id', 'description')
                ->orderBy('description')
                ->get()
                ->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->description
                ]);

            Log::info('✅ [OfflineTest] المزامنة الأولية - تم بنجاح', [
                'sponsors' => $sponsors->count(),
                'statuses' => $statuses->count(),
                'health_statuses' => $healthStatuses->count(),
                'cities' => $cities->count(),
                'banks' => $banks->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'sponsors' => $sponsors,
                    'statuses' => $statuses,
                    'health_statuses' => $healthStatuses,
                    'cities' => $cities,
                    'banks' => $banks
                ],
                'counts' => [
                    'sponsors' => $sponsors->count(),
                    'statuses' => $statuses->count(),
                    'health_statuses' => $healthStatuses->count(),
                    'cities' => $cities->count(),
                    'banks' => $banks->count()
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [OfflineTest] فشل المزامنة الأولية', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في المزامنة الأولية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب الكفالات - مع بيانات المعيل والحسابات البنكية
     */
    public function getSponsorships(Request $request): JsonResponse
    {
        Log::info('🔄 [OfflineTest] getSponsorships - بدء جلب الكفالات', [
            'sponsor_id' => $request->sponsor_id,
            'status_id' => $request->status_id,
            'search' => $request->search,
            'page' => $request->get('page', 1),
            'per_page' => $request->get('per_page', 50)
        ]);

        try {
            $query = Sponsorship::query()
                ->with(['sponsor:id,sponsor_name', 'sponsorshipStatus:id,description']);

            if ($request->has('sponsor_id') && $request->sponsor_id) {
                $query->where('sponsor_id', $request->sponsor_id);
            }

            if ($request->has('status_id') && $request->status_id) {
                $query->where('sponsorship_status_id', $request->status_id);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('orphan_name', 'like', "%{$search}%")
                      ->orWhere('guardian_name', 'like', "%{$search}%")
                      ->orWhere('identity_number', 'like', "%{$search}%")
                      ->orWhere('internal_file_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);

            $total = $query->count();

            $sponsorships = $query
                ->orderBy('updated_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            Log::info('✅ [OfflineTest] تم جلب الكفالات', [
                'total' => $total,
                'fetched' => $sponsorships->count(),
                'page' => $page
            ]);

            // جلب بيانات المعيل والحسابات البنكية لكل كفالة
            $mappedSponsorships = $sponsorships->map(function($s) {
                $guardianData = $this->getGuardianDataForSponsorship($s);
                $bankAccounts = $this->getBankAccountsForSponsorship($s);

                return [
                    'id' => $s->id,
                    'orphan_name' => $s->orphan_name,
                    'first_name' => $guardianData['sponsored_first_name'] ?? null,
                    'second_name' => $guardianData['sponsored_father_name'] ?? null,
                    'third_name' => $guardianData['sponsored_grandfather_name'] ?? null,
                    'last_name' => $guardianData['sponsored_family_name'] ?? null,
                    'orphan_gender' => $guardianData['sponsored_gender'] ?? null,
                    'health_status_id' => $guardianData['health_status_id'] ?? null,
                    'guardian_name' => $s->guardian_name,
                    'guardian_first_name' => $guardianData['guardian_first_name'] ?? null,
                    'guardian_father_name' => $guardianData['guardian_father_name'] ?? null,
                    'guardian_grandfather_name' => $guardianData['guardian_grandfather_name'] ?? null,
                    'guardian_family_name' => $guardianData['guardian_family_name'] ?? null,
                    'guardian_phone' => $guardianData['guardian_phone'] ?? null,
                    'guardian_phone2' => $guardianData['guardian_phone2'] ?? null,
                    'guardian_city_id' => $guardianData['guardian_city_id'] ?? null,
                    'guardian_detailed_address' => $guardianData['guardian_address'] ?? null,
                    // ✅ حقول المكفول الإضافية (المدينة والعنوان للـ breadwinner و deceased)
                    'orphan_city_id' => $guardianData['orphan_city_id'] ?? null,
                    'orphan_phone' => $guardianData['orphan_phone'] ?? null,
                    'orphan_phone2' => $guardianData['orphan_phone2'] ?? null,
                    'orphan_detailed_address' => $guardianData['orphan_detailed_address'] ?? null,
                    'identity_number' => $s->identity_number,
                    'guardian_identity_number' => $s->guardian_identity_number,
                    'internal_file_number' => $s->internal_file_number,
                    'external_file_number' => $s->external_file_number,
                    'relation_id_number' => $s->relation_id_number,
                    'person_type' => $s->person_type,
                    'sponsored_birth_date' => $s->sponsored_birth_date?->format('Y-m-d'),
                    'sponsorship_start_date' => $s->sponsorship_start_date?->format('Y-m-d'),
                    'sponsorship_end_date' => $s->sponsorship_end_date?->format('Y-m-d'),
                    'sponsorship_duration_months' => $s->sponsorship_duration_months,
                    'sponsor_id' => $s->sponsor_id,
                    'sponsor_name' => $s->sponsor?->sponsor_name,
                    'sponsorship_status_id' => $s->sponsorship_status_id,
                    'status_name' => $s->sponsorshipStatus?->description,
                    'notes' => $s->notes,
                    'bank_accounts' => $bankAccounts,
                    'created_at' => $s->created_at?->toISOString(),
                    'updated_at' => $s->updated_at?->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'sponsorships' => $mappedSponsorships,
                'pagination' => [
                    'total' => $total,
                    'per_page' => (int)$perPage,
                    'current_page' => (int)$page,
                    'last_page' => ceil($total / $perPage),
                    'has_more' => ($page * $perPage) < $total
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [OfflineTest] فشل جلب الكفالات', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الكفالات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب بيانات المعيل للكفالة
     * ملاحظة: جدول data يحتوي على بيانات المعيل مباشرة (data_first_name, etc.)
     * وجدول re_people يحتوي على بيانات المكفول
     */
    private function getGuardianDataForSponsorship(Sponsorship $sponsorship): array
    {
        $guardianData = [];

        try {
            // 1. محاولة جلب بيانات المعيل من جدول data
            // الربط الصحيح: relation_id_number (في sponsorships) = file_id_number (في data)
            // أسماء الأعمدة الصحيحة: data_first_name, data_father_name, data_grand_father_name, data_family_name
            $dataRecord = null;

            // محاولة 1: البحث بـ relation_id_number أولاً (الأكثر دقة حسب تحليل قاعدة البيانات)
            if ($sponsorship->relation_id_number) {
                $dataRecord = DB::table('data')
                    ->where('file_id_number', $sponsorship->relation_id_number)
                    ->first();

                if ($dataRecord) {
                    Log::info('📋 [OfflineTest] وجدنا المعيل في data عبر relation_id_number', [
                        'sponsorship_id' => $sponsorship->id,
                        'relation_id_number' => $sponsorship->relation_id_number
                    ]);
                }
            }

            // محاولة 2: إذا لم نجد، نبحث بـ internal_file_number
            if (!$dataRecord && $sponsorship->internal_file_number) {
                $dataRecord = DB::table('data')
                    ->where('file_id_number', $sponsorship->internal_file_number)
                    ->first();

                if ($dataRecord) {
                    Log::info('📋 [OfflineTest] وجدنا المعيل في data عبر internal_file_number', [
                        'sponsorship_id' => $sponsorship->id,
                        'internal_file_number' => $sponsorship->internal_file_number
                    ]);
                }
            }

            if ($dataRecord) {
                $guardianData = [
                    'guardian_first_name' => $dataRecord->data_first_name ?? null,
                    'guardian_father_name' => $dataRecord->data_father_name ?? null,
                    'guardian_grandfather_name' => $dataRecord->data_grand_father_name ?? null,
                    'guardian_family_name' => $dataRecord->data_family_name ?? null,
                    'guardian_phone' => $dataRecord->data_phone_number ?? null,
                    'guardian_phone2' => $dataRecord->data_alt_phone_number ?? null,
                    'guardian_address' => $dataRecord->data_current_address ?? null,
                    // ✅ إضافة المدينة للمعيل/الولي (family_member) من جدول data
                    'guardian_city_id' => $dataRecord->data_city ?? null,
                ];

                Log::info('✅ [OfflineTest] بيانات المعيل من جدول data', [
                    'sponsorship_id' => $sponsorship->id,
                    'guardian_first_name' => $guardianData['guardian_first_name'],
                    'guardian_family_name' => $guardianData['guardian_family_name'],
                    'guardian_phone' => $guardianData['guardian_phone'],
                    'guardian_city_id' => $guardianData['guardian_city_id']
                ]);
            } else {
                Log::debug('⚠️ [OfflineTest] لا يوجد سجل في data', [
                    'sponsorship_id' => $sponsorship->id,
                    'relation_id_number' => $sponsorship->relation_id_number,
                    'internal_file_number' => $sponsorship->internal_file_number
                ]);
            }

            // 2. محاولة جلب بيانات المكفول من re_people
            // الأولوية لـ registration_id لأن معظم person_id فارغة
            $rePeople = null;

            // محاولة 1: البحث بـ registration_id (الأكثر موثوقية)
            if ($sponsorship->relation_id_number) {
                $rePeople = DB::table('re_people')
                    ->where('registration_id', $sponsorship->relation_id_number)
                    ->first();

                if ($rePeople) {
                    Log::info('👤 [OfflineTest] بيانات المكفول من re_people (عبر registration_id)', [
                        'sponsorship_id' => $sponsorship->id,
                        'registration_id' => $sponsorship->relation_id_number
                    ]);
                }
            }

            // محاولة 2: البحث بـ person_id إذا لم نجد بـ registration_id
            if (!$rePeople && $sponsorship->identity_number) {
                $rePeople = DB::table('re_people')
                    ->where('person_id', $sponsorship->identity_number)
                    ->first();

                if ($rePeople) {
                    Log::info('👤 [OfflineTest] بيانات المكفول من re_people (عبر person_id)', [
                        'sponsorship_id' => $sponsorship->id,
                        'identity_number' => $sponsorship->identity_number
                    ]);
                }
            }

            if ($rePeople) {
                $guardianData['sponsored_first_name'] = $rePeople->first_name ?? null;
                $guardianData['sponsored_father_name'] = $rePeople->second_name ?? null;
                $guardianData['sponsored_grandfather_name'] = $rePeople->third_name ?? null;
                $guardianData['sponsored_family_name'] = $rePeople->last_name ?? null;
                $guardianData['sponsored_gender'] = $rePeople->person_gender ?? null;
                $guardianData['health_status_id'] = $rePeople->person_health_status ?? null;

                Log::info('👤 [OfflineTest] بيانات المكفول المستخرجة', [
                    'sponsorship_id' => $sponsorship->id,
                    'first_name' => $guardianData['sponsored_first_name'],
                    'gender' => $guardianData['sponsored_gender']
                ]);
            } else {
                Log::debug('⚠️ [OfflineTest] لا يوجد سجل في re_people', [
                    'sponsorship_id' => $sponsorship->id,
                    'identity_number' => $sponsorship->identity_number,
                    'relation_id_number' => $sponsorship->relation_id_number
                ]);
            }

            // ========================================
            // 3. جلب المدينة والعنوان حسب person_type
            // ========================================
            $personType = $sponsorship->person_type;
            $fileIdNumber = $sponsorship->relation_id_number ?: $sponsorship->internal_file_number;

            // للمعيل (breadwinner): المدينة من جدول data
            if ($personType === 'breadwinner' && $dataRecord) {
                $guardianData['orphan_city_id'] = $dataRecord->data_city ?? null;
                $guardianData['orphan_detailed_address'] = $dataRecord->data_current_address ?? null;
                $guardianData['orphan_phone'] = $dataRecord->data_phone_number ?? null;
                $guardianData['orphan_phone2'] = $dataRecord->data_alt_phone_number ?? null;

                Log::info('🏙️ [OfflineTest] المدينة للمعيل (breadwinner) من data', [
                    'sponsorship_id' => $sponsorship->id,
                    'orphan_city_id' => $guardianData['orphan_city_id']
                ]);
            }
            // للمتوفين (deceased_father, deceased_mother): المدينة والعنوان من portal_general_registration_field_values
            elseif (in_array($personType, ['deceased_father', 'deceased_mother']) && $fileIdNumber) {
                $portalFields = DB::table('portal_general_registration_field_values')
                    ->where('file_id_number', $fileIdNumber)
                    ->whereIn('field_key', ['field_data_city', 'field_housing_address_detail', 'field_data_phone_number', 'field_data_alt_phone_number'])
                    ->pluck('field_value', 'field_key');

                $guardianData['orphan_city_id'] = $portalFields['field_data_city'] ?? null;
                $guardianData['orphan_detailed_address'] = $portalFields['field_housing_address_detail'] ?? null;
                $guardianData['orphan_phone'] = $portalFields['field_data_phone_number'] ?? null;
                $guardianData['orphan_phone2'] = $portalFields['field_data_alt_phone_number'] ?? null;

                Log::info('🏙️ [OfflineTest] المدينة للمتوفي من portal_general_registration_field_values', [
                    'sponsorship_id' => $sponsorship->id,
                    'person_type' => $personType,
                    'orphan_city_id' => $guardianData['orphan_city_id'],
                    'fields_count' => count($portalFields)
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('⚠️ [OfflineTest] خطأ في جلب بيانات المعيل', [
                'sponsorship_id' => $sponsorship->id,
                'error' => $e->getMessage()
            ]);
        }

        return $guardianData;
    }

    /**
     * جلب الحسابات البنكية للكفالة
     */
    private function getBankAccountsForSponsorship(Sponsorship $sponsorship): array
    {
        $bankAccounts = [];

        try {
            // 1. البحث باستخدام relation_id_number
            if ($sponsorship->relation_id_number) {
                $accounts = DB::table('guardian_bank_accounts')
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

                if ($accounts->count() > 0) {
                    $bankAccounts = $accounts->toArray();
                    Log::debug('🏦 [OfflineTest] حسابات بنكية عبر relation_id_number', [
                        'sponsorship_id' => $sponsorship->id,
                        'relation_id_number' => $sponsorship->relation_id_number,
                        'count' => count($bankAccounts)
                    ]);
                }
            }

            // 2. إذا لم نجد، البحث باستخدام guardian_identity_number
            if (empty($bankAccounts) && $sponsorship->guardian_identity_number) {
                $accounts = DB::table('guardian_bank_accounts')
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
                        'bank_names.description as bank_name_text'
                    ])
                    ->get();

                if ($accounts->count() > 0) {
                    $bankAccounts = $accounts->toArray();
                    Log::debug('🏦 [OfflineTest] حسابات بنكية عبر guardian_identity_number', [
                        'sponsorship_id' => $sponsorship->id,
                        'guardian_identity_number' => $sponsorship->guardian_identity_number,
                        'count' => count($bankAccounts)
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::warning('⚠️ [OfflineTest] خطأ في جلب الحسابات البنكية', [
                'sponsorship_id' => $sponsorship->id,
                'error' => $e->getMessage()
            ]);
        }

        return $bankAccounts;
    }

    /**
     * جلب كفالة واحدة - مع بيانات المعيل والحسابات البنكية
     */
    public function getSponsorship($id): JsonResponse
    {
        Log::info('🔍 [OfflineTest] getSponsorship - جلب كفالة واحدة', [
            'sponsorship_id' => $id
        ]);

        try {
            $s = Sponsorship::with([
                'sponsor:id,sponsor_name,sponsor_short_name',
                'sponsorshipStatus:id,description',
                'sponsorshipType:id,description'
            ])->find($id);

            if (!$s) {
                Log::warning('⚠️ [OfflineTest] الكفالة غير موجودة', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'الكفالة غير موجودة'
                ], 404);
            }

            // جلب بيانات المعيل والحسابات البنكية
            $guardianData = $this->getGuardianDataForSponsorship($s);
            $bankAccounts = $this->getBankAccountsForSponsorship($s);

            Log::info('✅ [OfflineTest] تم جلب الكفالة بنجاح', [
                'sponsorship_id' => $id,
                'orphan_name' => $s->orphan_name,
                'guardian_name' => $s->guardian_name,
                'has_guardian_data' => !empty($guardianData),
                'guardian_first_name' => $guardianData['guardian_first_name'] ?? 'NULL',
                'bank_accounts_count' => count($bankAccounts)
            ]);

            return response()->json([
                'success' => true,
                'sponsorship' => [
                    'id' => $s->id,
                    'orphan_name' => $s->orphan_name,
                    // بيانات المكفول التفصيلية
                    'first_name' => $guardianData['sponsored_first_name'] ?? null,
                    'second_name' => $guardianData['sponsored_father_name'] ?? null,
                    'third_name' => $guardianData['sponsored_grandfather_name'] ?? null,
                    'last_name' => $guardianData['sponsored_family_name'] ?? null,
                    'orphan_gender' => $guardianData['sponsored_gender'] ?? null,
                    'health_status_id' => $guardianData['health_status_id'] ?? null,
                    // بيانات المعيل
                    'guardian_name' => $s->guardian_name,
                    'guardian_first_name' => $guardianData['guardian_first_name'] ?? null,
                    'guardian_father_name' => $guardianData['guardian_father_name'] ?? null,
                    'guardian_grandfather_name' => $guardianData['guardian_grandfather_name'] ?? null,
                    'guardian_family_name' => $guardianData['guardian_family_name'] ?? null,
                    'guardian_phone' => $guardianData['guardian_phone'] ?? null,
                    'guardian_phone2' => $guardianData['guardian_phone2'] ?? null,
                    'guardian_city_id' => $guardianData['guardian_city_id'] ?? null,
                    'guardian_detailed_address' => $guardianData['guardian_address'] ?? null,
                    // ✅ حقول المكفول الإضافية (المدينة والعنوان للـ breadwinner و deceased)
                    'orphan_city_id' => $guardianData['orphan_city_id'] ?? null,
                    'orphan_phone' => $guardianData['orphan_phone'] ?? null,
                    'orphan_phone2' => $guardianData['orphan_phone2'] ?? null,
                    'orphan_detailed_address' => $guardianData['orphan_detailed_address'] ?? null,
                    // بيانات الهوية
                    'identity_number' => $s->identity_number,
                    'guardian_identity_number' => $s->guardian_identity_number,
                    'internal_file_number' => $s->internal_file_number,
                    'external_file_number' => $s->external_file_number,
                    'relation_id_number' => $s->relation_id_number,
                    'person_type' => $s->person_type,
                    // التواريخ
                    'sponsored_birth_date' => $s->sponsored_birth_date?->format('Y-m-d'),
                    'sponsorship_start_date' => $s->sponsorship_start_date?->format('Y-m-d'),
                    'sponsorship_end_date' => $s->sponsorship_end_date?->format('Y-m-d'),
                    'sponsorship_duration_months' => $s->sponsorship_duration_months,
                    // الكفيل
                    'sponsor_id' => $s->sponsor_id,
                    'sponsor' => $s->sponsor ? [
                        'id' => $s->sponsor->id,
                        'name' => $s->sponsor->sponsor_name,
                        'short_name' => $s->sponsor->sponsor_short_name
                    ] : null,
                    // الحالة
                    'sponsorship_status_id' => $s->sponsorship_status_id,
                    'status' => $s->sponsorshipStatus ? [
                        'id' => $s->sponsorshipStatus->id,
                        'name' => $s->sponsorshipStatus->description
                    ] : null,
                    // نوع الكفالة
                    'sponsorship_type_id' => $s->sponsorship_type_id,
                    'sponsorship_type' => $s->sponsorshipType ? [
                        'id' => $s->sponsorshipType->id,
                        'name' => $s->sponsorshipType->description
                    ] : null,
                    // الحسابات البنكية
                    'bank_accounts' => $bankAccounts,
                    // معلومات إضافية
                    'notes' => $s->notes,
                    'created_by' => $s->created_by,
                    'updated_by' => $s->updated_by,
                    'created_at' => $s->created_at?->toISOString(),
                    'updated_at' => $s->updated_at?->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [OfflineTest] فشل جلب الكفالة', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الكفالة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث كفالة
     */
    public function updateSponsorship(Request $request, $id): JsonResponse
    {
        try {
            $sponsorship = Sponsorship::find($id);

            if (!$sponsorship) {
                return response()->json([
                    'success' => false,
                    'message' => 'الكفالة غير موجودة'
                ], 404);
            }

            $allowedFields = [
                'orphan_name',
                'guardian_name',
                'identity_number',
                'guardian_identity_number',
                'internal_file_number',
                'external_file_number',
                'person_type',
                'sponsored_birth_date',
                'sponsorship_start_date',
                'sponsorship_end_date',
                'sponsorship_duration_months',
                'sponsor_id',
                'sponsorship_status_id',
                'sponsorship_type_id',
                'notes'
            ];

            $updateData = $request->only($allowedFields);
            $sponsorship->addUpdater($request->input('user_id', 1));
            $sponsorship->update($updateData);

            Log::info('🧪 Sponsorship updated via offline test', [
                'sponsorship_id' => $id,
                'updated_fields' => array_keys($updateData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الكفالة بنجاح',
                'sponsorship' => $sponsorship->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update sponsorship', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحديث الكفالة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * رفع التغييرات المعلقة
     */
    public function uploadChanges(Request $request): JsonResponse
    {
        try {
            $changes = $request->input('changes', []);
            $results = [];

            Log::info('[OfflineTest] ===== بدء رفع التغييرات =====', [
                'total_changes' => count($changes),
                'timestamp' => now()->toISOString(),
                'user_agent' => $request->header('User-Agent'),
                'ip' => $request->ip()
            ]);

            foreach ($changes as $index => $change) {
                $sponsorshipId = $change['sponsorship_id'] ?? null;
                $data = $change['data'] ?? [];
                $changeType = $change['type'] ?? 'update';

                Log::info("[OfflineTest] معالجة التغيير #{$index}", [
                    'sponsorship_id' => $sponsorshipId,
                    'change_type' => $changeType,
                    'fields_to_update' => array_keys($data),
                    'data_values' => $data
                ]);

                if (!$sponsorshipId) {
                    Log::warning('[OfflineTest] ❌ فشل: معرف الكفالة مفقود', ['change_index' => $index]);
                    $results[] = ['sponsorship_id' => null, 'success' => false, 'message' => 'معرف الكفالة مفقود'];
                    continue;
                }

                $sponsorship = Sponsorship::find($sponsorshipId);

                if (!$sponsorship) {
                    Log::warning('[OfflineTest] ❌ فشل: الكفالة غير موجودة', [
                        'sponsorship_id' => $sponsorshipId,
                        'change_index' => $index
                    ]);
                    $results[] = ['sponsorship_id' => $sponsorshipId, 'success' => false, 'message' => 'الكفالة غير موجودة'];
                    continue;
                }

                // تسجيل الحالة قبل التحديث
                Log::info('[OfflineTest] البيانات قبل التحديث', [
                    'sponsorship_id' => $sponsorshipId,
                    'orphan_name' => $sponsorship->orphan_name,
                    'guardian_name' => $sponsorship->guardian_name,
                    'relation_id_number' => $sponsorship->relation_id_number,
                    'internal_file_number' => $sponsorship->internal_file_number
                ]);

                try {
                    // ========================================
                    // 🔢 الحصول على person_type لتحديد الجدول الصحيح
                    // ========================================
                    $personType = $data['person_type'] ?? $sponsorship->person_type ?? null;
                    $relationIdNumber = $sponsorship->relation_id_number;
                    $identityNumber = $data['identity_number'] ?? $sponsorship->identity_number ?? null;

                    Log::info('[OfflineTest] 📋 بدء معالجة التحديث حسب person_type', [
                        'sponsorship_id' => $sponsorshipId,
                        'person_type' => $personType,
                        'relation_id_number' => $relationIdNumber,
                        'identity_number' => $identityNumber
                    ]);

                    // متغير لتخزين رقم الملف الموحد الجديد (إذا لزم الأمر)
                    $unifiedFileIdNumber = null;

                    // ========================================
                    // 1. تحديث بيانات المعيل/الولي في جدول data
                    // (هذا دائماً للمعيل/الولي وليس المكفول)
                    // ========================================
                    $guardianFields = ['guardian_first_name', 'guardian_father_name', 'guardian_grandfather_name',
                                       'guardian_family_name', 'guardian_phone', 'guardian_phone2',
                                       'guardian_detailed_address', 'guardian_identity_number', 'guardian_city_id'];

                    $guardianDataToUpdate = [];
                    foreach ($guardianFields as $field) {
                        if (isset($data[$field])) {
                            $dataField = str_replace('guardian_', 'data_', $field);
                            if ($field === 'guardian_detailed_address') {
                                $dataField = 'data_current_address';
                            } elseif ($field === 'guardian_grandfather_name') {
                                $dataField = 'data_grand_father_name';
                            } elseif ($field === 'guardian_identity_number') {
                                $dataField = 'data_id_number';
                            } elseif ($field === 'guardian_phone') {
                                $dataField = 'data_phone_number';
                            } elseif ($field === 'guardian_phone2') {
                                $dataField = 'data_alt_phone_number';
                            } elseif ($field === 'guardian_city_id') {
                                $dataField = 'data_city';
                            }
                            $guardianDataToUpdate[$dataField] = $data[$field];
                        }
                    }

                    if (!empty($guardianDataToUpdate)) {
                        $dataRecord = null;
                        if ($relationIdNumber) {
                            $dataRecord = DB::table('data')
                                ->where('file_id_number', $relationIdNumber)
                                ->first();
                        }
                        if (!$dataRecord && $sponsorship->internal_file_number) {
                            $dataRecord = DB::table('data')
                                ->where('file_id_number', $sponsorship->internal_file_number)
                                ->first();
                        }

                        if ($dataRecord) {
                            $guardianDataToUpdate['updated_at'] = now();
                            DB::table('data')->where('id', $dataRecord->id)->update($guardianDataToUpdate);
                            Log::info('[OfflineTest] ✅ تم تحديث بيانات المعيل في جدول data', [
                                'sponsorship_id' => $sponsorshipId,
                                'data_record_id' => $dataRecord->id
                            ]);
                        } else {
                            // إنشاء سجل جديد للمعيل
                            if (!$unifiedFileIdNumber) {
                                $unifiedFileIdNumber = generateFileIdFromDataTable();
                            }
                            $newDataRecord = array_merge($guardianDataToUpdate, [
                                'file_id_number' => $unifiedFileIdNumber,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $newDataId = DB::table('data')->insertGetId($newDataRecord);

                            // ✅ تعليم الكود كمستخدم (للمعيل/الولي)
                            markCodeAsUsed($unifiedFileIdNumber, null, 'معيل/ولي في data');

                            Log::info('[OfflineTest] ✅ تم إنشاء سجل جديد للمعيل', [
                                'sponsorship_id' => $sponsorshipId,
                                'new_data_id' => $newDataId,
                                'file_id_number' => $unifiedFileIdNumber
                            ]);
                        }
                    }

                    // ========================================
                    // 2. تحديث بيانات المكفول حسب person_type
                    // ========================================
                    $sponsoredFields = ['first_name', 'second_name', 'third_name', 'last_name',
                                        'orphan_gender', 'birth_date', 'health_status_id',
                                        'orphan_phone', 'orphan_phone2', 'orphan_detailed_address', 'orphan_city_id'];

                    $hasSponsoredUpdate = false;
                    foreach ($sponsoredFields as $field) {
                        if (isset($data[$field])) {
                            $hasSponsoredUpdate = true;
                            break;
                        }
                    }

                    if ($hasSponsoredUpdate) {
                        $sponsoredResult = $this->updateSponsoredByPersonType(
                            $personType,
                            $relationIdNumber,
                            $identityNumber,
                            $data,
                            $sponsorship,
                            $unifiedFileIdNumber
                        );

                        if ($sponsoredResult['success']) {
                            Log::info('[OfflineTest] ✅ تم تحديث بيانات المكفول', $sponsoredResult);

                            // إذا تم إنشاء سجل جديد، نحفظ رقم الملف
                            if (isset($sponsoredResult['new_file_id'])) {
                                $unifiedFileIdNumber = $sponsoredResult['new_file_id'];
                            }
                        } else {
                            Log::warning('[OfflineTest] ⚠️ فشل تحديث بيانات المكفول', $sponsoredResult);
                        }
                    }

                    // ✅ تحديث relation_id_number في sponsorships بالرقم الموحد (مرة واحدة فقط)
                    if ($unifiedFileIdNumber) {
                        DB::table('sponsorships')
                            ->where('id', $sponsorshipId)
                            ->update([
                                'relation_id_number' => $unifiedFileIdNumber,
                                'updated_at' => now()
                            ]);

                        // ✅ تحديث الكود كمستخدم في reserved_codes لمنع التضارب
                        markCodeAsUsed($unifiedFileIdNumber, null, 'استخدم في sponsorship_id: ' . $sponsorshipId);

                        Log::info('[OfflineTest] ✅ تم تحديث relation_id_number وتعليم الكود كمستخدم', [
                            'sponsorship_id' => $sponsorshipId,
                            'relation_id_number' => $unifiedFileIdNumber
                        ]);
                        $sponsorship = $sponsorship->fresh();
                    }

                    // ========================================
                    // 3. تحديث الحسابات البنكية
                    // ========================================
                    if (isset($data['bank_accounts_updates']) && is_array($data['bank_accounts_updates'])) {
                        foreach ($data['bank_accounts_updates'] as $accountIndex => $bankData) {
                            $this->updateOrCreateBankAccount($sponsorship, $bankData, $accountIndex);
                        }
                    }

                    // ========================================
                    // 4. تحديث جدول sponsorships
                    // ========================================
                    // فلترة الحقول التي تنتمي لجدول sponsorships فقط
                    $sponsorshipFields = ['orphan_name', 'guardian_name', 'sponsor_id',
                                          'sponsorship_status_id', 'sponsorship_type', 'notes',
                                          'identity_number', 'internal_file_number', 'relation_id_number',
                                          'guardian_identity_number'];

                    $sponsorshipDataToUpdate = [];
                    foreach ($sponsorshipFields as $field) {
                        if (isset($data[$field])) {
                            $sponsorshipDataToUpdate[$field] = $data[$field];
                        }
                    }

                    // ✅ إضافة guardian_identity_number إذا تم إرساله في بيانات المعيل
                    if (isset($data['guardian_identity_number']) && !empty($data['guardian_identity_number'])) {
                        $sponsorshipDataToUpdate['guardian_identity_number'] = $data['guardian_identity_number'];
                        Log::info('[OfflineTest] ✅ سيتم تحديث guardian_identity_number', [
                            'sponsorship_id' => $sponsorshipId,
                            'guardian_identity_number' => $data['guardian_identity_number']
                        ]);
                    }

                    // إضافة guardian_name المركب إذا تم تحديث بيانات المعيل
                    if (isset($data['guardian_name'])) {
                        $sponsorshipDataToUpdate['guardian_name'] = $data['guardian_name'];
                    }

                    if (!empty($sponsorshipDataToUpdate)) {
                        $sponsorship->update($sponsorshipDataToUpdate);
                    }

                    // ✅ تحديث حالة الكفالة إلى "انتظار الصرف" (6)
                    $sponsorship->update(['sponsorship_status_id' => 6]);
                    Log::info('[OfflineTest] ✅ تم تحديث حالة الكفالة إلى انتظار الصرف', [
                        'sponsorship_id' => $sponsorshipId,
                        'new_status_id' => 6
                    ]);

                    // تسجيل النجاح مع البيانات بعد التحديث
                    $updatedSponsorship = $sponsorship->fresh();
                    Log::info('[OfflineTest] ✅ نجاح التحديث الشامل', [
                        'sponsorship_id' => $sponsorshipId,
                        'updated_fields' => array_keys($data),
                        'orphan_name_after' => $updatedSponsorship->orphan_name,
                        'guardian_name_after' => $updatedSponsorship->guardian_name,
                        'guardian_updated' => !empty($guardianDataToUpdate),
                        'sponsored_updated' => !empty($rePeopleDataToUpdate),
                        'bank_updated' => isset($data['bank_accounts_updates'])
                    ]);

                    $results[] = ['sponsorship_id' => $sponsorshipId, 'success' => true, 'message' => 'تم التحديث'];
                } catch (\Exception $e) {
                    Log::error('[OfflineTest] ❌ خطأ في التحديث', [
                        'sponsorship_id' => $sponsorshipId,
                        'error' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString()
                    ]);
                    $results[] = ['sponsorship_id' => $sponsorshipId, 'success' => false, 'message' => $e->getMessage()];
                }
            }

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failedCount = count($results) - $successCount;

            Log::info('[OfflineTest] ===== انتهاء رفع التغييرات =====', [
                'total_processed' => count($results),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'success_rate' => count($results) > 0 ? round(($successCount / count($results)) * 100, 2) . '%' : '0%'
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم معالجة {$successCount} تغيير بنجاح",
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failed' => $failedCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[OfflineTest] ❌ خطأ عام في رفع التغييرات', [
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في رفع التغييرات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث أو إنشاء حساب بنكي
     */
    private function updateOrCreateBankAccount(Sponsorship $sponsorship, array $bankData, $accountIndex): void
    {
        try {
            // تحديد المعرف للبحث عن الحساب
            $guardianRegistration = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number;

            if (!$guardianRegistration) {
                Log::warning('[OfflineTest] ⚠️ لا يمكن حفظ الحساب البنكي - لا يوجد معرف', [
                    'sponsorship_id' => $sponsorship->id
                ]);
                return;
            }

            // البحث عن الحساب الموجود
            $existingAccounts = DB::table('guardian_bank_accounts')
                ->where(function ($query) use ($guardianRegistration) {
                    $query->where('guardian_registration', $guardianRegistration)
                          ->orWhere('re_id_number', $guardianRegistration);
                })
                ->get();

            // تحضير البيانات للحفظ
            // re_id_number يجب أن يكون رقم الهوية الذي يدخله المستخدم وليس رقم الملف
            $personOwnerIdentity = $bankData['person_owner_identity_number'] ?? $sponsorship->guardian_identity_number;

            // ✅ التحقق من وجود bank_name للحسابات الجديدة (مطلوب في قاعدة البيانات - NOT NULL)
            $isNewAccount = !isset($existingAccounts[$accountIndex]);
            if ($isNewAccount && (!isset($bankData['bank_name']) || empty($bankData['bank_name']))) {
                Log::warning('[OfflineTest] ⚠️ لا يمكن إنشاء حساب بنكي جديد بدون تحديد اسم البنك', [
                    'sponsorship_id' => $sponsorship->id,
                    'account_index' => $accountIndex,
                    'received_data' => $bankData
                ]);
                return; // لا يمكن إنشاء حساب جديد بدون bank_name
            }

            $bankRecord = [
                'guardian_registration' => $guardianRegistration,
                're_id_number' => $personOwnerIdentity, // رقم هوية صاحب الحساب
                're_guardian_name' => $bankData['re_guardian_name'] ?? $sponsorship->guardian_name,
                'person_owner_identity_number' => $personOwnerIdentity,
                're_phone_number' => $bankData['re_phone_number'] ?? null,
                'bank_name' => $bankData['bank_name'] ?? null, // للتحديث فقط - الإنشاء يتطلب قيمة
                'iban_usd' => $bankData['iban_usd'] ?? null,
                'iban_shekel' => $bankData['iban_shekel'] ?? null,
                'check_account' => 1, // ✅ دائماً 1 عند إدخال حساب بنكي
                'updated_at' => now()
            ];

            // إذا كان الحساب موجود بالفعل في هذا الـ index
            if (isset($existingAccounts[$accountIndex])) {
                $existingAccount = $existingAccounts[$accountIndex];

                DB::table('guardian_bank_accounts')
                    ->where('id', $existingAccount->id)
                    ->update($bankRecord);

                Log::info('[OfflineTest] ✅ تم تحديث الحساب البنكي', [
                    'sponsorship_id' => $sponsorship->id,
                    'bank_account_id' => $existingAccount->id,
                    'account_index' => $accountIndex,
                    'bank_name' => $bankRecord['bank_name'],
                    're_id_number' => $bankRecord['re_id_number'],
                    'person_owner_identity_number' => $bankRecord['person_owner_identity_number']
                ]);
            } else {
                // إنشاء حساب جديد
                $bankRecord['created_at'] = now();

                $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankRecord);

                Log::info('[OfflineTest] ✅ تم إنشاء حساب بنكي جديد', [
                    'sponsorship_id' => $sponsorship->id,
                    'new_bank_id' => $newBankId,
                    'account_index' => $accountIndex,
                    'bank_name' => $bankRecord['bank_name'],
                    're_id_number' => $bankRecord['re_id_number'],
                    'person_owner_identity_number' => $bankRecord['person_owner_identity_number']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[OfflineTest] ❌ خطأ في حفظ الحساب البنكي', [
                'sponsorship_id' => $sponsorship->id,
                'account_index' => $accountIndex,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * تحديث بيانات المكفول حسب نوعه (person_type)
     *
     * السيناريوهات:
     * - breadwinner (معيل): جدول data
     * - family_member / orphan: جدول re_people
     * - deceased_father: جدول dead_people (حقول father_*)
     * - deceased_mother: جدول dead_people (حقول mother_*)
     */
    private function updateSponsoredByPersonType(
        ?string $personType,
        ?string $relationIdNumber,
        ?string $identityNumber,
        array $data,
        Sponsorship $sponsorship,
        ?string $unifiedFileIdNumber = null
    ): array {
        $result = [
            'success' => false,
            'person_type' => $personType,
            'table' => null,
            'message' => ''
        ];

        switch ($personType) {
            // ============================================
            // حالة المعيل (breadwinner) - جدول data
            // ============================================
            case 'breadwinner':
                $result['table'] = 'data';
                $record = null;

                if ($relationIdNumber) {
                    $record = DB::table('data')->where('file_id_number', $relationIdNumber)->first();
                }
                if (!$record && $identityNumber) {
                    $record = DB::table('data')->where('data_id_number', $identityNumber)->first();
                }

                $updateData = [];
                if (isset($data['first_name'])) $updateData['data_first_name'] = $data['first_name'];
                if (isset($data['second_name'])) $updateData['data_father_name'] = $data['second_name'];
                if (isset($data['third_name'])) $updateData['data_grand_father_name'] = $data['third_name'];
                if (isset($data['last_name'])) $updateData['data_family_name'] = $data['last_name'];
                if (isset($data['orphan_gender'])) {
                    $updateData['data_gender'] = $this->convertGenderToInt($data['orphan_gender']);
                }
                if (isset($data['birth_date'])) $updateData['data_birth_date'] = $data['birth_date'];
                if (isset($data['identity_number'])) $updateData['data_id_number'] = $data['identity_number'];
                if (isset($data['orphan_phone'])) {
                    $updateData['data_phone_number'] = (int)preg_replace('/[^0-9]/', '', $data['orphan_phone']);
                }
                if (isset($data['orphan_phone2'])) {
                    $updateData['data_alt_phone_number'] = (int)preg_replace('/[^0-9]/', '', $data['orphan_phone2']);
                }
                if (isset($data['orphan_detailed_address'])) {
                    $updateData['data_current_address'] = $data['orphan_detailed_address'];
                }
                // ✅ إضافة المدينة للمعيل (breadwinner) في جدول data
                if (isset($data['orphan_city_id'])) {
                    $updateData['data_city'] = $data['orphan_city_id'];
                    Log::info('[OfflineTest] 🏙️ تحديث المدينة للمعيل (breadwinner)', [
                        'sponsorship_id' => $sponsorship->id,
                        'city_id' => $data['orphan_city_id']
                    ]);
                }

                if ($record) {
                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('data')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث المعيل في جدول data';
                    }
                } else {
                    // إنشاء سجل جديد
                    $newFileId = $unifiedFileIdNumber ?? generateFileIdFromDataTable();
                    $updateData['file_id_number'] = $newFileId;
                    $updateData['created_at'] = now();
                    $updateData['updated_at'] = now();

                    DB::table('data')->insert($updateData);

                    // ✅ تعليم الكود كمستخدم
                    markCodeAsUsed($newFileId, null, 'breadwinner في data');

                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد للمعيل في data';
                    $result['new_file_id'] = $newFileId;
                }
                break;

            // ============================================
            // حالة فرد العائلة أو اليتيم - جدول re_people
            // ============================================
            case 'family_member':
            case 'orphan':
            case 'repeople':
            case null:
            case '':
                $result['table'] = 're_people';
                $record = null;

                if ($relationIdNumber) {
                    $record = DB::table('re_people')->where('registration_id', $relationIdNumber)->first();
                }
                if (!$record && $identityNumber) {
                    $record = DB::table('re_people')->where('person_id', $identityNumber)->first();
                }

                $updateData = [];
                if (isset($data['first_name'])) $updateData['first_name'] = $data['first_name'];
                if (isset($data['second_name'])) $updateData['second_name'] = $data['second_name'];
                if (isset($data['third_name'])) $updateData['third_name'] = $data['third_name'];
                if (isset($data['last_name'])) $updateData['last_name'] = $data['last_name'];
                if (isset($data['orphan_gender'])) {
                    $updateData['person_gender'] = $this->convertGenderToInt($data['orphan_gender']);
                }
                if (isset($data['birth_date'])) $updateData['person_birth_date'] = $data['birth_date'];
                if (isset($data['identity_number'])) $updateData['person_id'] = $data['identity_number'];
                if (isset($data['health_status_id'])) $updateData['person_health_status'] = $data['health_status_id'];

                if ($record) {
                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('re_people')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث فرد العائلة في re_people';
                    }
                } else {
                    // إنشاء سجل جديد
                    $newFileId = $unifiedFileIdNumber ?? generateFileIdFromDataTable();
                    $updateData['registration_id'] = $newFileId;
                    $updateData['person_id'] = $identityNumber;
                    $updateData['created_at'] = now();
                    $updateData['updated_at'] = now();

                    DB::table('re_people')->insert($updateData);

                    // ✅ تعليم الكود كمستخدم
                    markCodeAsUsed($newFileId, null, 'orphan/family_member في re_people');

                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد في re_people';
                    $result['new_file_id'] = $newFileId;
                }
                break;

            // ============================================
            // حالة الأب المتوفي - جدول dead_people
            // ============================================
            case 'deceased_father':
                $result['table'] = 'dead_people';
                $record = null;

                if ($relationIdNumber) {
                    $record = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
                }
                if (!$record && $identityNumber) {
                    $record = DB::table('dead_people')->where('father_id', $identityNumber)->first();
                }

                $updateData = [];
                // ✅ حفظ الأسماء كما هي بالضبط بدون تقسيم
                if (isset($data['first_name'])) $updateData['father_first_name'] = $data['first_name'];
                if (isset($data['second_name'])) $updateData['father_second_name'] = $data['second_name'];
                if (isset($data['third_name'])) $updateData['father_third_name'] = $data['third_name'];
                if (isset($data['last_name'])) $updateData['father_last_name'] = $data['last_name'];
                if (isset($data['identity_number'])) {
                    $updateData['father_id'] = (int)preg_replace('/[^0-9]/', '', $data['identity_number']);
                }
                if (isset($data['birth_date'])) $updateData['father_death_date'] = $data['birth_date'];

                // ⚠️ استخدام orphan_name فقط إذا لم تكن الأسماء المنفصلة موجودة
                if (!isset($data['first_name']) && !isset($data['second_name']) &&
                    !isset($data['third_name']) && !isset($data['last_name']) &&
                    isset($data['orphan_name']) && !empty($data['orphan_name'])) {
                    $nameParts = explode(' ', trim($data['orphan_name']));
                    $updateData['father_first_name'] = $nameParts[0] ?? '';
                    $updateData['father_second_name'] = $nameParts[1] ?? '';
                    $updateData['father_third_name'] = $nameParts[2] ?? '';
                    $updateData['father_last_name'] = $nameParts[3] ?? '';
                }

                if ($record) {
                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('dead_people')->where('id', $record->id)->update($updateData);

                        // ✅ log للتحقق من الأسماء المحفوظة
                        Log::info('[OfflineTest] 📝 بيانات الأب المتوفي المحفوظة', [
                            'record_id' => $record->id,
                            'father_names' => [
                                'first' => $updateData['father_first_name'] ?? 'لم يتغير',
                                'second' => $updateData['father_second_name'] ?? 'لم يتغير',
                                'third' => $updateData['father_third_name'] ?? 'لم يتغير',
                                'last' => $updateData['father_last_name'] ?? 'لم يتغير'
                            ]
                        ]);

                        $result['success'] = true;
                        $result['message'] = 'تم تحديث بيانات الأب المتوفي';
                    }
                } else {
                    // إنشاء سجل جديد
                    $newFileId = $unifiedFileIdNumber ?? generateFileIdFromDataTable();
                    $updateData['re_file_id'] = $newFileId;
                    $updateData['created_at'] = now();
                    $updateData['updated_at'] = now();

                    DB::table('dead_people')->insert($updateData);

                    // ✅ log للتحقق من الأسماء المحفوظة
                    Log::info('[OfflineTest] 📝 بيانات الأب المتوفي الجديد', [
                        'new_file_id' => $newFileId,
                        'father_names' => [
                            'first' => $updateData['father_first_name'] ?? '',
                            'second' => $updateData['father_second_name'] ?? '',
                            'third' => $updateData['father_third_name'] ?? '',
                            'last' => $updateData['father_last_name'] ?? ''
                        ]
                    ]);

                    // ✅ تعليم الكود كمستخدم
                    markCodeAsUsed($newFileId, null, 'deceased_father في dead_people');

                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد للأب المتوفي';
                    $result['new_file_id'] = $newFileId;
                }

                // ✅ حفظ الحقول الإضافية (الهاتف، العنوان) في portal_general_registration_field_values
                $fileIdToUse = $result['new_file_id'] ?? $relationIdNumber;
                if ($fileIdToUse) {
                    $extraFields = $this->saveDeceasedExtraFields(
                        $sponsorship->id,
                        $fileIdToUse,
                        $identityNumber,
                        $data
                    );
                    if (!empty($extraFields)) {
                        $result['extra_fields_saved'] = $extraFields;
                        Log::info('[OfflineTest] ✅ تم حفظ الحقول الإضافية للأب المتوفي', [
                            'sponsorship_id' => $sponsorship->id,
                            'fields_count' => count($extraFields)
                        ]);
                    }
                }
                break;

            // ============================================
            // حالة الأم المتوفية - جدول dead_people
            // ============================================
            case 'deceased_mother':
                $result['table'] = 'dead_people';
                $record = null;

                if ($relationIdNumber) {
                    $record = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
                }
                if (!$record && $identityNumber) {
                    $record = DB::table('dead_people')->where('mother_id', $identityNumber)->first();
                }

                $updateData = [];
                // ✅ حفظ الأسماء كما هي بالضبط بدون تقسيم
                if (isset($data['first_name'])) $updateData['mother_first_name'] = $data['first_name'];
                if (isset($data['second_name'])) $updateData['mother_second_name'] = $data['second_name'];
                if (isset($data['third_name'])) $updateData['mother_third_name'] = $data['third_name'];
                if (isset($data['last_name'])) $updateData['mother_last_name'] = $data['last_name'];
                if (isset($data['identity_number'])) {
                    $updateData['mother_id'] = (int)preg_replace('/[^0-9]/', '', $data['identity_number']);
                }
                if (isset($data['birth_date'])) $updateData['mother_death_date'] = $data['birth_date'];

                // ⚠️ استخدام orphan_name فقط إذا لم تكن الأسماء المنفصلة موجودة
                if (!isset($data['first_name']) && !isset($data['second_name']) &&
                    !isset($data['third_name']) && !isset($data['last_name']) &&
                    isset($data['orphan_name']) && !empty($data['orphan_name'])) {
                    $nameParts = explode(' ', trim($data['orphan_name']));
                    $updateData['mother_first_name'] = $nameParts[0] ?? '';
                    $updateData['mother_second_name'] = $nameParts[1] ?? '';
                    $updateData['mother_third_name'] = $nameParts[2] ?? '';
                    $updateData['mother_last_name'] = $nameParts[3] ?? '';
                }

                if ($record) {
                    if (!empty($updateData)) {
                        $updateData['updated_at'] = now();
                        DB::table('dead_people')->where('id', $record->id)->update($updateData);
                        $result['success'] = true;
                        $result['message'] = 'تم تحديث بيانات الأم المتوفية';
                    }
                } else {
                    // إنشاء سجل جديد
                    $newFileId = $unifiedFileIdNumber ?? generateFileIdFromDataTable();
                    $updateData['re_file_id'] = $newFileId;
                    $updateData['created_at'] = now();
                    $updateData['updated_at'] = now();

                    DB::table('dead_people')->insert($updateData);

                    // ✅ تعليم الكود كمستخدم
                    markCodeAsUsed($newFileId, null, 'deceased_mother في dead_people');

                    $result['success'] = true;
                    $result['message'] = 'تم إنشاء سجل جديد للأم المتوفية';
                    $result['new_file_id'] = $newFileId;
                }

                // ✅ حفظ الحقول الإضافية (الهاتف، العنوان) في portal_general_registration_field_values
                $fileIdToUse = $result['new_file_id'] ?? $relationIdNumber;
                if ($fileIdToUse) {
                    $extraFields = $this->saveDeceasedExtraFields(
                        $sponsorship->id,
                        $fileIdToUse,
                        $identityNumber,
                        $data
                    );
                    if (!empty($extraFields)) {
                        $result['extra_fields_saved'] = $extraFields;
                        Log::info('[OfflineTest] ✅ تم حفظ الحقول الإضافية للأم المتوفية', [
                            'sponsorship_id' => $sponsorship->id,
                            'fields_count' => count($extraFields)
                        ]);
                    }
                }
                break;

            default:
                // بحث تلقائي في الجداول
                $result['message'] = "نوع الشخص غير معروف: {$personType}";
                Log::warning('[OfflineTest] ⚠️ person_type غير معروف، محاولة البحث التلقائي', [
                    'person_type' => $personType,
                    'relation_id_number' => $relationIdNumber
                ]);

                // محاولة البحث في re_people كـ fallback
                if ($relationIdNumber) {
                    $record = DB::table('re_people')->where('registration_id', $relationIdNumber)->first();
                    if ($record) {
                        // تطبيق منطق re_people
                        return $this->updateSponsoredByPersonType('orphan', $relationIdNumber, $identityNumber, $data, $sponsorship, $unifiedFileIdNumber);
                    }
                }
                break;
        }

        return $result;
    }

    /**
     * تحويل قيمة الجنس إلى رقم
     */
    private function convertGenderToInt($gender): ?int
    {
        if ($gender === 'ذكر' || $gender === 'male' || $gender === '1' || $gender === 1) {
            return 1;
        } elseif ($gender === 'أنثى' || $gender === 'female' || $gender === '2' || $gender === 2) {
            return 2;
        }
        return null;
    }

    /**
     * حفظ الحقول الإضافية للمتوفين في portal_general_registration_field_values
     *
     * هذه الحقول غير موجودة في جدول dead_people لذا تُحفظ هنا:
     * - field_data_phone_number: رقم الهاتف
     * - field_data_alt_phone_number: رقم الهاتف البديل
     * - field_data_city: المدينة
     * - field_housing_address_detail: العنوان التفصيلي
     */
    private function saveDeceasedExtraFields(
        int $sponsorshipId,
        string $fileIdNumber,
        ?string $identityNumber,
        array $data
    ): array {
        $savedFields = [];

        $fieldMappings = [
            'orphan_phone' => 'field_data_phone_number',
            'orphan_phone2' => 'field_data_alt_phone_number',
            'orphan_city_id' => 'field_data_city',
            'orphan_detailed_address' => 'field_housing_address_detail'
        ];

        foreach ($fieldMappings as $dataKey => $fieldKey) {
            if (isset($data[$dataKey]) && !empty($data[$dataKey])) {
                try {
                    $fieldValue = $data[$dataKey];

                    // البحث عن سجل موجود
                    $existing = DB::table('portal_general_registration_field_values')
                        ->where('sponsorship_id', $sponsorshipId)
                        ->where('field_key', $fieldKey)
                        ->first();

                    if ($existing) {
                        // تحديث القيمة الموجودة
                        DB::table('portal_general_registration_field_values')
                            ->where('id', $existing->id)
                            ->update([
                                'field_value' => $fieldValue,
                                'updated_at' => now()
                            ]);

                        $savedFields[$fieldKey] = [
                            'action' => 'updated',
                            'id' => $existing->id,
                            'value' => $fieldValue
                        ];
                    } else {
                        // إدخال قيمة جديدة
                        $newId = DB::table('portal_general_registration_field_values')->insertGetId([
                            'sponsorship_id' => $sponsorshipId,
                            'file_id_number' => $fileIdNumber,
                            'identity_number' => $identityNumber,
                            'field_key' => $fieldKey,
                            'field_value' => $fieldValue,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        $savedFields[$fieldKey] = [
                            'action' => 'inserted',
                            'id' => $newId,
                            'value' => $fieldValue
                        ];
                    }

                    Log::info("[OfflineTest] ✅ تم حفظ الحقل الإضافي للمتوفي", [
                        'sponsorship_id' => $sponsorshipId,
                        'field_key' => $fieldKey,
                        'action' => $savedFields[$fieldKey]['action']
                    ]);

                } catch (\Exception $e) {
                    Log::error("[OfflineTest] ❌ خطأ في حفظ الحقل الإضافي", [
                        'sponsorship_id' => $sponsorshipId,
                        'field_key' => $fieldKey,
                        'error' => $e->getMessage()
                    ]);
                    $savedFields[$fieldKey] = [
                        'action' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
        }

        return $savedFields;
    }

    /**
     * إحصائيات سريعة
     */
    public function getStats(): JsonResponse
    {
        try {
            $totalSponsorships = Sponsorship::count();
            $totalSponsors = Sponsor::count();

            $statusCounts = Sponsorship::select('sponsorship_status_id', DB::raw('count(*) as count'))
                ->groupBy('sponsorship_status_id')
                ->get()
                ->pluck('count', 'sponsorship_status_id');

            $personTypeCounts = Sponsorship::select('person_type', DB::raw('count(*) as count'))
                ->whereNotNull('person_type')
                ->groupBy('person_type')
                ->get()
                ->pluck('count', 'person_type');

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_sponsorships' => $totalSponsorships,
                    'total_sponsors' => $totalSponsors,
                    'by_status' => $statusCounts,
                    'by_person_type' => $personTypeCounts
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
