<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use App\Models\GeneralCategory;
use App\Models\RequestStatus;
use App\Models\CategoryOfRelation;
use App\Models\MaritalStatus;
use App\Models\AcademicDegree;
use App\Models\City;
use App\Models\Province;
use App\Models\HealthStatus;
use App\Models\Employment;
use App\Models\HousingStatus;
use App\Models\TypeOfAccommodation;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfGuarantee;
use App\Models\DeathReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchOnRecordsController extends Controller
{
    private $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * عرض صفحة البحث في السجلات
     */
    public function index()
    {
        // جلب البيانات المرجعية للمرشحات
        $sections = GeneralCategory::all();
        $requestStatuses = RequestStatus::all();
        $relationships = CategoryOfRelation::all();
        $maritalStatuses = MaritalStatus::all();
        $academicQualifications = AcademicDegree::all();
        $cities = City::all();
        $provinces = Province::all();
        $healthStatuses = HealthStatus::all();
        $employmentStatuses = Employment::all();
        $housingStatuses = HousingStatus::all();
        $accommodationTypes = TypeOfAccommodation::all();
        $sponsorshipStatuses = SponsorshipStatus::all();
        $guaranteeTypes = TypeOfGuarantee::all();
        $deathReasons = DeathReason::all();

        return view('admin.dashboard.records_management.searchOnRecords', compact(
            'sections', 'requestStatuses', 'relationships', 'maritalStatuses',
            'academicQualifications', 'cities', 'provinces', 'healthStatuses',
            'employmentStatuses', 'housingStatuses', 'accommodationTypes',
            'sponsorshipStatuses', 'guaranteeTypes', 'deathReasons'
        ));
    }

    /**
     * عرض محتوى البحث للـ Modal
     */
    public function modalContent()
    {
        // جلب البيانات المرجعية المطلوبة للمرشحات
        $sections = GeneralCategory::all();
        $cities = City::all();

        return view('admin.dashboard.records_management.search_modal_content', compact(
            'sections', 'cities'
        ));
    }

    /**
     * تنفيذ البحث الشامل عبر AJAX
     */
    public function search(Request $request)
    {
        try {
            // التحقق من صحة البيانات
            $validated = $request->validate([
                'search_type' => 'required|in:all,main_records,family_members,deceased',
                'search_text' => 'nullable|string|max:255',
                'file_id' => 'nullable|string|max:50',
                'identity_number' => 'nullable|string|max:50',
                'phone_number' => 'nullable|string|max:50',
                'section_id' => 'nullable|integer',
                'city_id' => 'nullable|integer',
                'request_status_id' => 'nullable|integer',
                'relationship_id' => 'nullable|integer',
                'marital_status_id' => 'nullable|integer',
                'academic_qualification_id' => 'nullable|integer',
                'health_status_id' => 'nullable|integer',
                'employment_status_id' => 'nullable|integer',
                'gender' => 'nullable|in:male,female',
                'birth_date_from' => 'nullable|date',
                'birth_date_to' => 'nullable|date',
                'per_page' => 'nullable|integer|min:10|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            $perPage = $validated['per_page'] ?? 25;

            // تسجيل عملية البحث
            Log::info('Search request initiated', [
                'user_id' => auth()->id(),
                'search_type' => $validated['search_type'],
                'has_search_text' => !empty($validated['search_text']),
                'filters_count' => count(array_filter($validated))
            ]);

            // تنفيذ البحث باستخدام Service
            $results = $this->searchService->smartSearch($validated, $perPage);

            // إضافة logging للتحقق من البيانات
            Log::info('Search results data sample', [
                'search_type' => $validated['search_type'],
                'total_results' => $this->getTotalResultsCount($results),
                'first_record_sample' => isset($results['data']) && !empty($results['data']) ?
                    array_slice($results['data'], 0, 1) : 'No data'
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'تم تنفيذ البحث بنجاح',
                'search_info' => [
                    'search_type' => $validated['search_type'],
                    'total_results' => $this->getTotalResultsCount($results),
                    'cached' => false // سيتم تحديثها في Service
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات البحث غير صحيحة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.',
                'error_code' => 'SEARCH_ERROR_' . time()
            ], 500);
        }
    }

    /**
     * إحصائيات البحث السريعة
     */
    public function getSearchStats()
    {
        try {
            $stats = $this->searchService->getSearchStatistics();

            // إضافة الإجمالي
            $stats['total_records'] = $stats['main_records'] +
                                     $stats['family_members'] +
                                     $stats['deceased_records'];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching search statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات'
            ], 500);
        }
    }

    /**
     * اقتراحات البحث التلقائي
     */
    public function getSearchSuggestions(Request $request)
    {
        try {
            $query = $request->get('q', '');
            $limit = $request->get('limit', 10);

            if (strlen($query) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $suggestions = $this->searchService->getSearchSuggestions($query, $limit);

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching search suggestions', [
                'error' => $e->getMessage(),
                'query' => $request->get('q')
            ]);

            return response()->json([
                'success' => false,
                'data' => []
            ]);
        }
    }

    /**
     * مسح cache البحث (للمديرين فقط)
     */
    public function clearSearchCache()
    {
        try {
            $this->searchService->clearSearchCache();

            return response()->json([
                'success' => true,
                'message' => 'تم مسح ذاكرة التخزين المؤقت للبحث بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing search cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في مسح ذاكرة التخزين المؤقت'
            ], 500);
        }
    }

    /**
     * الحصول على العدد الإجمالي للنتائج
     */
    private function getTotalResultsCount(array $results): int
    {
        if (isset($results['total_count'])) {
            return $results['total_count'];
        }

        if (isset($results['pagination'])) {
            return $results['pagination']['total_records'];
        }

        if (isset($results['data']) && is_array($results['data'])) {
            return count($results['data']);
        }

        return 0;
    }
}
