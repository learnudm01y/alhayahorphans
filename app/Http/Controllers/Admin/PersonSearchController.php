<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PersonSearchService;
use App\Models\City;
use App\Models\CI_PERSONAL_CD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PersonSearchController extends Controller
{
    protected $searchService;

    public function __construct(PersonSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * عرض صفحة البحث المتقدم
     */
    public function index()
    {
        $cities = City::all();
        $socialStatuses = CI_PERSONAL_CD::all();

        return view('admin.dashboard.civil_registry.search', compact('cities', 'socialStatuses'));
    }

    /**
     * البحث باستخدام AJAX
     */
    public function search(Request $request)
    {
        try {
            $searchTerm = $request->input('search_term');
            $filters = $this->prepareFilters($request);

            $query = $this->searchService->searchPersons($searchTerm, $filters);

            if ($request->expectsJson()) {
                return DataTables::of($query)
                    ->addColumn('full_name', function ($person) {
                        return $this->getFullName($person);
                    })
                    ->addColumn('city_name', function ($person) {
                        return $person->city->name ?? 'غير محدد';
                    })
                    ->addColumn('social_status_name', function ($person) {
                        return $person->socialStatus->name ?? 'غير محدد';
                    })
                    ->addColumn('gender_text', function ($person) {
                        return $person->CI_SEX_CD == 1 ? 'ذكر' : ($person->CI_SEX_CD == 2 ? 'أنثى' : '-');
                    })
                    ->addColumn('actions', function ($person) {
                        return $this->generateActionButtons($person);
                    })
                    ->rawColumns(['actions'])
                    ->make(true);
            }

            // إذا لم يكن طلب AJAX، إعادة توجيه لصفحة النتائج
            $persons = $query->paginate(15);
            $statistics = $this->searchService->getSearchStatistics($searchTerm, $filters);

            return view('admin.dashboard.civil_registry.search_results', compact(
                'persons', 'statistics', 'searchTerm', 'filters'
            ));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'حدث خطأ أثناء البحث'], 500);
            }

            return back()->withErrors(['error' => 'حدث خطأ أثناء البحث']);
        }
    }

    /**
     * البحث السريع للاستكمال التلقائي
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $searchTerm = $request->input('term');
        $limit = $request->input('limit', 10);

        if (strlen($searchTerm) < 2) {
            return response()->json([]);
        }

        $results = $this->searchService->quickSearch($searchTerm, $limit);

        return response()->json($results);
    }

    /**
     * الحصول على إحصائيات البحث
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $searchTerm = $request->input('search_term');
        $filters = $this->prepareFilters($request);

        $statistics = $this->searchService->getSearchStatistics($searchTerm, $filters);

        return response()->json($statistics);
    }

    /**
     * تصدير نتائج البحث
     */
    public function export(Request $request)
    {
        $searchTerm = $request->input('search_term');
        $filters = $this->prepareFilters($request);
        $format = $request->input('format', 'excel');

        $query = $this->searchService->searchPersons($searchTerm, $filters);
        $persons = $query->get();

        switch ($format) {
            case 'excel':
                return $this->exportToExcel($persons);
            case 'pdf':
                return $this->exportToPdf($persons);
            case 'csv':
                return $this->exportToCsv($persons);
            default:
                return response()->json(['error' => 'تنسيق غير مدعوم'], 400);
        }
    }

    /**
     * البحث المتقدم مع صفحات
     */
    public function advancedSearch(Request $request)
    {
        $searchTerm = $request->input('search_term');
        $filters = $this->prepareFilters($request);
        $perPage = $request->input('per_page', 15);

        $query = $this->searchService->searchPersons($searchTerm, $filters);
        $persons = $query->paginate($perPage);

        $statistics = $this->searchService->getSearchStatistics($searchTerm, $filters);

        if ($request->ajax()) {
            return response()->json([
                'data' => $persons->items(),
                'pagination' => [
                    'current_page' => $persons->currentPage(),
                    'last_page' => $persons->lastPage(),
                    'total' => $persons->total(),
                    'per_page' => $persons->perPage(),
                ],
                'statistics' => $statistics
            ]);
        }

        $cities = City::all();
        $socialStatuses = CI_PERSONAL_CD::all();

        return view('admin.dashboard.civil_registry.search_results', compact(
            'persons', 'statistics', 'cities', 'socialStatuses', 'searchTerm', 'filters'
        ));
    }

    /**
     * تحضير الفلاتر من الطلب
     */
    private function prepareFilters(Request $request): array
    {
        return array_filter([
            'ci_id_num' => $request->input('ci_id_num'),
            'first_name' => $request->input('first_name'),
            'father_name' => $request->input('father_name'),
            'grandfather_name' => $request->input('grandfather_name'),
            'family_name' => $request->input('family_name'),
            'mother_name' => $request->input('mother_name'),
            'gender' => $request->input('gender'),
            'city' => $request->input('city'),
            'birth_date_from' => $request->input('birth_date_from'),
            'birth_date_to' => $request->input('birth_date_to'),
            'social_status' => $request->input('social_status'),
        ]);
    }

    /**
     * الحصول على الاسم الكامل
     */
    private function getFullName($person): string
    {
        $nameParts = array_filter([
            $person->CI_FIRST_ARB,
            $person->CI_FATHER_ARB,
            $person->CI_GRAND_FATHER_ARB,
            $person->CI_FAMILY_ARB
        ]);

        return implode(' ', $nameParts);
    }

    /**
     * إنشاء أزرار الإجراءات
     */
    private function generateActionButtons($person): string
    {
        // استخدام ID بدلاً من id
        $personId = $person->ID ?? $person->id;
        $editUrl = route('admin.persons.edit', $personId);
        $deleteUrl = route('admin.persons.destroy', $personId);

        return '
            <div class="btn-group action-buttons" role="group">
                <a href="' . $editUrl . '" class="btn btn-sm btn-outline-primary" title="تعديل" target="_blank">
                    <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="' . $deleteUrl . '" style="display: inline;">
                    ' . csrf_field() . '
                    ' . method_field('DELETE') . '
                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="حذف">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
                <a href="' . $editUrl . '" class="btn btn-sm btn-outline-info" title="عرض وتعديل" target="_blank">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
        ';
    }

    /**
     * تصدير إلى Excel
     */
    private function exportToExcel($persons)
    {
        // يمكن استخدام مكتبة مثل Laravel Excel
        // هذا مثال بسيط للتصدير
        $filename = 'search_results_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($persons) {
            $file = fopen('php://output', 'w');

            // إضافة BOM للدعم العربي
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // العناوين
            fputcsv($file, [
                'رقم الهوية',
                'الاسم الكامل',
                'تاريخ الميلاد',
                'الجنس',
                'المدينة',
                'الحالة الاجتماعية'
            ]);

            foreach ($persons as $person) {
                fputcsv($file, [
                    $person->CI_ID_NUM,
                    $this->getFullName($person),
                    $person->CI_BIRTH_DT,
                    $person->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى',
                    $person->city->name ?? 'غير محدد',
                    $person->socialStatus->name ?? 'غير محدد'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * تصدير إلى PDF
     */
    private function exportToPdf($persons)
    {
        // يمكن استخدام مكتبة مثل DomPDF أو TCPDF
        // هذا مثال بسيط
        return response()->json(['message' => 'تصدير PDF قيد التطوير'], 501);
    }

    /**
     * تصدير إلى CSV
     */
    private function exportToCsv($persons)
    {
        return $this->exportToExcel($persons); // نفس الطريقة
    }
}
