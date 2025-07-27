<?php

namespace App\Http\Controllers;

use App\Services\CivilRegistryScoutSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CivilRegistrySearchController extends Controller
{
    protected $searchService;

    public function __construct(CivilRegistryScoutSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * صفحة البحث الرئيسية
     */
    public function index()
    {
        try {
            // الحصول على إحصائيات قاعدة البيانات
            $stats = $this->searchService->getDatabaseStats();

            return view('admin.dashboard.civil_registry_search.index', compact('stats'));
        } catch (\Exception $e) {
            return view('admin.dashboard.civil_registry_search.index', [
                'stats' => ['success' => false, 'error' => $e->getMessage()]
            ]);
        }
    }

    /**
     * البحث السريع
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required|string|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:200'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات البحث غير صحيحة',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->input('search_text');
            $limit = $request->input('limit', 50);

            $results = $this->searchService->quickScoutSearch($query, $limit);

            return response()->json([
                'success' => $results['success'],
                'message' => $results['success'] ?
                    'تم البحث بنجاح - تم العثور على ' . $results['total_count'] . ' نتيجة' :
                    'فشل في البحث: ' . ($results['error'] ?? 'خطأ غير معروف'),
                'data' => $results['data'] ?? [],
                'total_count' => $results['total_count'] ?? 0,
                'execution_time' => $results['execution_time'] ?? '0 ms',
                'engine' => $results['engine'] ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage(),
                'data' => [],
                'total_count' => 0
            ], 500);
        }
    }

    /**
     * البحث المتقدم
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'nullable|string|min:2|max:100',
            'gender' => 'nullable|integer|in:1,2',
            'birth_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'city' => 'nullable|integer',
            'is_alive' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات البحث غير صحيحة',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $request->only(['search_text', 'gender', 'birth_year', 'city', 'is_alive']);
            $limit = $request->input('limit', 100);

            // التأكد من وجود معايير للبحث
            if (empty(array_filter($filters))) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب تحديد معايير البحث',
                    'data' => [],
                    'total_count' => 0
                ], 422);
            }

            $results = $this->searchService->advancedScoutSearch($filters, $limit);

            return response()->json([
                'success' => $results['success'],
                'message' => $results['success'] ?
                    'تم البحث المتقدم بنجاح - تم العثور على ' . $results['total_count'] . ' نتيجة' :
                    'فشل في البحث المتقدم: ' . ($results['error'] ?? 'خطأ غير معروف'),
                'data' => $results['data'] ?? [],
                'total_count' => $results['total_count'] ?? 0,
                'execution_time' => $results['execution_time'] ?? '0 ms',
                'engine' => $results['engine'] ?? 'Unknown',
                'filters_applied' => $results['filters_applied'] ?? $filters
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث المتقدم: ' . $e->getMessage(),
                'data' => [],
                'total_count' => 0
            ], 500);
        }
    }

    /**
     * البحث برقم الهوية
     */
    public function searchById(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required|string|min:1|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهوية غير صحيح',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $idNumber = $request->input('search_text');
            $results = $this->searchService->searchByIdNumber($idNumber);

            return response()->json([
                'success' => $results['success'],
                'message' => $results['success'] ?
                    ($results['total_count'] > 0 ? 'تم العثور على السجل' : 'لم يتم العثور على سجل بهذا الرقم') :
                    'فشل في البحث: ' . ($results['error'] ?? 'خطأ غير معروف'),
                'data' => $results['data'] ?? [],
                'total_count' => $results['total_count'] ?? 0,
                'execution_time' => $results['execution_time'] ?? '0 ms',
                'engine' => $results['engine'] ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث برقم الهوية: ' . $e->getMessage(),
                'data' => [],
                'total_count' => 0
            ], 500);
        }
    }

    /**
     * البحث بالاسم الكامل
     */
    public function searchByName(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required|string|min:1|max:200',
            'limit' => 'nullable|integer|min:1|max:200'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الاسم غير صحيح',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $fullName = $request->input('search_text');
            $limit = $request->input('limit', 50);

            $results = $this->searchService->searchByFullName($fullName, $limit);

            return response()->json([
                'success' => $results['success'],
                'message' => $results['success'] ?
                    'تم البحث بالاسم بنجاح - تم العثور على ' . $results['total_count'] . ' نتيجة' :
                    'فشل في البحث بالاسم: ' . ($results['error'] ?? 'خطأ غير معروف'),
                'data' => $results['data'] ?? [],
                'total_count' => $results['total_count'] ?? 0,
                'execution_time' => $results['execution_time'] ?? '0 ms',
                'engine' => $results['engine'] ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث بالاسم: ' . $e->getMessage(),
                'data' => [],
                'total_count' => 0
            ], 500);
        }
    }

    /**
     * البحث الشامل
     */
    public function comprehensiveSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required|string|min:2|max:100',
            'include_id_search' => 'nullable|boolean',
            'include_name_search' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات البحث غير صحيحة',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $criteria = [
                'search_text' => $request->input('search_text'),
                'include_id_search' => $request->input('include_id_search', true),
                'include_name_search' => $request->input('include_name_search', true)
            ];

            $results = $this->searchService->comprehensiveSearch($criteria);

            return response()->json([
                'success' => $results['success'],
                'message' => $results['success'] ?
                    'تم البحث الشامل بنجاح - تم العثور على ' . $results['data']['total_count'] . ' نتيجة إجمالية' :
                    'فشل في البحث الشامل: ' . ($results['error'] ?? 'خطأ غير معروف'),
                'data' => $results['data'] ?? [],
                'execution_time' => $results['execution_time'] ?? '0 ms',
                'engine' => $results['engine'] ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث الشامل: ' . $e->getMessage(),
                'data' => [],
                'execution_time' => '0 ms'
            ], 500);
        }
    }

    /**
     * إحصائيات قاعدة البيانات
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->searchService->getDatabaseStats();

            return response()->json([
                'success' => $stats['success'],
                'message' => $stats['success'] ? 'تم جلب الإحصائيات بنجاح' : 'فشل في جلب الإحصائيات',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * فهرسة البيانات
     */
    public function indexData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chunk_size' => 'nullable|integer|min:100|max:5000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'حجم الدفعة غير صحيح',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $chunkSize = $request->input('chunk_size', 1000);
            $results = $this->searchService->indexData($chunkSize);

            return response()->json([
                'success' => $results['success'],
                'message' => $results['success'] ?
                    'تم فهرسة ' . $results['processed_records'] . ' سجل من أصل ' . $results['total_records'] :
                    'فشل في الفهرسة: ' . ($results['error'] ?? 'خطأ غير معروف'),
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الفهرسة: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * اختبار الاتصال والخدمة
     */
    public function testConnection(): JsonResponse
    {
        try {
            $results = $this->searchService->testConnection();

            return response()->json([
                'success' => $results['success'],
                'message' => $results['success'] ? 'جميع الاختبارات نجحت' : 'فشل في بعض الاختبارات',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء اختبار الاتصال: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * مسح الكاش
     */
    public function clearCache(): JsonResponse
    {
        try {
            $result = $this->searchService->clearCache();

            return response()->json([
                'success' => $result,
                'message' => $result ? 'تم مسح الكاش بنجاح' : 'فشل في مسح الكاش'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء مسح الكاش: ' . $e->getMessage()
            ], 500);
        }
    }
}
