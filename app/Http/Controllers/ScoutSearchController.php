<?php

namespace App\Http\Controllers;

use App\Services\CustomScoutSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ScoutSearchController extends Controller
{
    protected $scoutService;

    public function __construct(CustomScoutSearchService $scoutService)
    {
        $this->scoutService = $scoutService;
    }

    /**
     * البحث الفوري
     */
    public function instantSearch(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2|max:255',
                'limit' => 'sometimes|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات البحث غير صحيحة',
                    'errors' => $validator->errors()
                ], 400);
            }

            $query = $request->input('query');
            $limit = $request->input('limit', 20);

            Log::info('Scout Instant Search Request', [
                'query' => $query,
                'limit' => $limit,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            $result = $this->scoutService->instantSearch($query, $limit);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Scout Instant Search Error', [
                'query' => $request->input('query'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث',
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ داخلي'
            ], 500);
        }
    }

    /**
     * الاقتراحات السريعة
     */
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'term' => 'required|string|min:1|max:255',
                'limit' => 'sometimes|integer|min:1|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الاقتراحات غير صحيحة',
                    'errors' => $validator->errors()
                ], 400);
            }

            $term = $request->input('term');
            $limit = $request->input('limit', 10);

            $suggestions = $this->scoutService->getSuggestions($term, $limit);

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'count' => count($suggestions)
            ]);

        } catch (\Exception $e) {
            Log::error('Scout Suggestions Error', [
                'term' => $request->input('term'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الاقتراحات',
                'data' => []
            ], 500);
        }
    }

    /**
     * البحث المتقدم
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'sometimes|string|max:255',
                'first_name' => 'sometimes|string|max:100',
                'father_name' => 'sometimes|string|max:100',
                'family_name' => 'sometimes|string|max:100',
                'mother_name' => 'sometimes|string|max:100',
                'id_number' => 'sometimes|string|max:50',
                'city' => 'sometimes|string|max:100',
                'gender' => 'sometimes|in:1,2,M,F,ذكر,أنثى',
                'birth_year' => 'sometimes|integer|min:1900|max:' . date('Y'),
                'limit' => 'sometimes|integer|min:1|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات البحث المتقدم غير صحيحة',
                    'errors' => $validator->errors()
                ], 400);
            }

            $filters = $request->only([
                'query', 'first_name', 'father_name', 'family_name',
                'mother_name', 'id_number', 'city', 'gender', 'birth_year'
            ]);

            // إزالة القيم الفارغة
            $filters = array_filter($filters, function($value) {
                return !empty(trim($value));
            });

            if (empty($filters)) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى تحديد معايير البحث',
                    'data' => []
                ], 400);
            }

            $limit = $request->input('limit', 50);

            Log::info('Scout Advanced Search Request', [
                'filters' => $filters,
                'limit' => $limit
            ]);

            $result = $this->scoutService->advancedScoutSearch($filters, $limit);

            return response()->json([
                'success' => true,
                'data' => $result['results'] ?? [],
                'total' => $result['count'] ?? 0,
                'search_time' => $result['search_time'] ?? 0,
                'filters' => $filters,
                'message' => $result['count'] > 0 ? "تم العثور على {$result['count']} نتيجة" : 'لا توجد نتائج مطابقة'
            ]);

        } catch (\Exception $e) {
            Log::error('Scout Advanced Search Error', [
                'filters' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث المتقدم',
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ داخلي'
            ], 500);
        }
    }

    /**
     * إحصائيات البحث
     */
    public function searchStats(): JsonResponse
    {
        try {
            $stats = [
                'total_searches_today' => 0, // يمكن تطويرها لاحقاً
                'average_search_time' => '< 0.1',
                'most_searched_terms' => [], // يمكن تطويرها لاحقاً
                'total_records' => DB::table('persons')->count(),
                'search_engines' => [
                    'instant' => 'البحث الفوري',
                    'advanced' => 'البحث المتقدم',
                    'suggestions' => 'الاقتراحات'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Scout Stats Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الإحصائيات'
            ], 500);
        }
    }
}
