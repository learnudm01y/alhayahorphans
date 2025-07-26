<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomScoutSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScoutSearchController extends Controller
{
    protected $scoutSearchService;

    public function __construct(CustomScoutSearchService $scoutSearchService)
    {
        $this->scoutSearchService = $scoutSearchService;
    }

    /**
     * البحث السريع بتقنية Scout
     */
    public function instantSearch(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        $query = trim($request->input('query'));
        $limit = $request->input('limit', 50);

        try {
            $startTime = microtime(true);
            $results = $this->scoutSearchService->instantSearch($query, $limit);
            $endTime = microtime(true);

            $results['total_time'] = round(($endTime - $startTime) * 1000, 2);
            $results['success'] = true;
            $results['message'] = 'تم البحث بنجاح';

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البحث: ' . $e->getMessage(),
                'results' => [],
                'count' => 0
            ], 500);
        }
    }

    /**
     * البحث المتقدم بتقنية Scout
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:100',
            'gender' => 'sometimes|in:1,2',
            'birth_year' => 'sometimes|integer|min:1900|max:' . date('Y'),
            'city' => 'sometimes|integer',
            'limit' => 'sometimes|integer|min:1|max:200'
        ]);

        $filters = [
            'query' => trim($request->input('query')),
            'gender' => $request->input('gender'),
            'birth_year' => $request->input('birth_year'),
            'city' => $request->input('city')
        ];

        $limit = $request->input('limit', 100);

        try {
            $startTime = microtime(true);
            $results = $this->scoutSearchService->advancedScoutSearch($filters, $limit);
            $endTime = microtime(true);

            $results['total_time'] = round(($endTime - $startTime) * 1000, 2);
            $results['success'] = true;
            $results['message'] = 'تم البحث المتقدم بنجاح';

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البحث المتقدم: ' . $e->getMessage(),
                'results' => [],
                'count' => 0
            ], 500);
        }
    }

    /**
     * اقتراحات سريعة للكتابة التلقائية
     */
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'term' => 'required|string|min:2|max:50'
        ]);

        $term = trim($request->input('term'));

        try {
            // Cache للاقتراحات لمدة 30 دقيقة
            $cacheKey = "scout_suggestions_" . md5($term);
            $suggestions = Cache::remember($cacheKey, 1800, function () use ($term) {
                return $this->scoutSearchService->instantSearch($term, 10);
            });

            // تحويل النتائج إلى اقتراحات
            $formattedSuggestions = collect($suggestions)->map(function ($person) {
                $fullName = trim(($person->CI_FIRST_ARB ?? '') . ' ' .
                              ($person->CI_FATHER_ARB ?? '') . ' ' .
                              ($person->CI_FAMILY_ARB ?? ''));

                return [
                    'label' => $fullName,
                    'value' => $fullName,
                    'description' => ($person->CI_ID_NUM ?? '') . ' - ' . ($person->CITY ?? ''),
                    'id' => $person->ID
                ];
            })->filter(function ($suggestion) {
                return !empty(trim($suggestion['label']));
            })->unique('value')->take(5)->values()->toArray();

            return response()->json($formattedSuggestions);

        } catch (\Exception $e) {
            Log::error('Scout Suggestions Error: ' . $e->getMessage());

            return response()->json([]);
        }
    }

    /**
     * إحصائيات البحث
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->scoutSearchService->getSearchStats();

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * فهرسة البيانات
     */
    public function indexData(Request $request): JsonResponse
    {
        $request->validate([
            'batch_size' => 'sometimes|integer|min:100|max:5000'
        ]);

        $batchSize = $request->input('batch_size', 1000);

        try {
            set_time_limit(300); // 5 دقائق
            $result = $this->scoutSearchService->indexData($batchSize);

            return response()->json([
                'success' => true,
                'message' => 'تم فهرسة البيانات بنجاح',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في فهرسة البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * مسح الكاش
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->scoutSearchService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'تم مسح الكاش بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في مسح الكاش: ' . $e->getMessage()
            ], 500);
        }
    }
}
