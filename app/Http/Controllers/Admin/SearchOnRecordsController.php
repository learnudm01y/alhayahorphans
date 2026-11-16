<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicDegree;
use App\Models\CategoryOfRelation;
use App\Models\City;
use App\Models\CI_PERSONAL_CD;
use App\Models\Employment;
use App\Models\GeneralCategory;
use App\Models\HealthStatus;
use App\Models\RequestStatus;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SearchOnRecordsController extends Controller
{
    public function __construct(private SearchService $searchService)
    {
    }

    public function index(Request $request)
    {
        $input = $request->validate(['search_text' => 'nullable|string|max:255', 'request_status_id' => 'nullable|integer|min:1']);
        $data = $this->lookupData();
        $data['initialSearchText'] = $input['search_text'] ?? '';
        if ($request->has('request_status_id')) {
            $data['defaultRequestStatusId'] = $input['request_status_id'] ?? null;
        }
        return view('admin.dashboard.records_management.searchOnRecords', $data);
    }

    public function modalContent()
    {
        return view('admin.dashboard.records_management.search_modal_content', $this->lookupData());
    }

    private function lookupData(): array
    {
        $statuses = RequestStatus::all();
        return [
            'sections' => GeneralCategory::all(),
            'requestStatuses' => $statuses,
            'defaultRequestStatusId' => null,
            'relationships' => CategoryOfRelation::all(),
            'maritalStatuses' => CI_PERSONAL_CD::all(),
            'academicQualifications' => AcademicDegree::all(),
            'cities' => City::all(),
            'healthStatuses' => HealthStatus::all(),
            'employmentStatuses' => Employment::all(),
        ];
    }

    public function search(Request $request)
    {
        $filters = $this->validatedFilters($request);
        try {
            $result = $this->searchService->smartSearch($filters, (int) ($filters['per_page'] ?? 25));
            return response()->json([
                'success' => true,
                'data' => $result,
                'search_info' => ['total_results' => $result['total_count'], 'cached' => $result['cached']],
            ]);
        } catch (\Throwable $e) {
            $this->logFailure('search', $e);
            return response()->json(['success' => false, 'message' => 'تعذر تنفيذ البحث. يرجى المحاولة مرة أخرى.'], 500);
        }
    }

    public function getSearchSuggestions(Request $request)
    {
        $request->validate(['q' => 'required|string|min:3|max:255']);
        $request->merge(['search_text' => $request->input('q'), 'search_type' => $request->input('search_type', 'all')]);
        $filters = $this->validatedFilters($request);
        $input = $request->validate(['limit' => 'nullable|integer|min:1|max:10']);
        try {
            return response()->json([
                'success' => true,
                'data' => $this->searchService->getSearchSuggestions($filters['search_text'] ?? '', (int) ($input['limit'] ?? 8), $filters),
            ]);
        } catch (\Throwable $e) {
            $this->logFailure('suggestions', $e);
            return response()->json(['success' => false, 'message' => 'تعذر تحميل الاقتراحات.', 'data' => []], 500);
        }
    }

    public function getSearchStats()
    {
        try {
            return response()->json(['success' => true, 'data' => $this->searchService->getSearchStatistics()]);
        } catch (\Throwable $e) {
            $this->logFailure('statistics', $e);
            return response()->json(['success' => false, 'message' => 'تعذر جلب الإحصائيات.'], 500);
        }
    }

    public function clearSearchCache()
    {
        try {
            $this->searchService->clearSearchCache();
            return response()->json(['success' => true, 'message' => 'تم إبطال نتائج البحث المخزنة مؤقتًا. سيجلب البحث التالي بيانات جديدة.']);
        } catch (\Throwable $e) {
            $this->logFailure('clear_cache', $e);
            return response()->json(['success' => false, 'message' => 'تعذر مسح ذاكرة البحث.'], 500);
        }
    }

    private function validatedFilters(Request $request): array
    {
        $rules = [
            'search_type' => 'required|in:all,main_records,family_members,deceased',
            'search_text' => 'nullable|string|max:255',
            'file_id' => 'nullable|string|max:50',
            'identity_number' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:50|regex:/^[0-9٠-٩۰-۹+().\s-]+$/u',
            'gender' => 'nullable|in:male,female',
            'per_page' => 'nullable|integer|in:25,50,100',
            'page' => 'nullable|integer|min:1|max:1000000',
        ];
        foreach (array_merge(array_keys(config('search.guardian_filters')), ['relationship_id', 'marital_status_id', 'academic_qualification_id', 'health_status_id', 'employment_status_id']) as $filter) {
            $rules[$filter] = 'nullable|integer|min:1';
        }
        foreach (['birth_date', 'death_date'] as $date) {
            $rules[$date.'_from'] = 'nullable|date_format:Y-m-d';
            $rules[$date.'_to'] = 'nullable|date_format:Y-m-d'.($request->filled($date.'_from') ? '|after_or_equal:'.$date.'_from' : '');
        }
        $validator = Validator::make($request->all(), $rules, [
            'phone_number.regex' => 'أدخل رقم جوال صالحًا؛ يسمح بالأرقام ومقدمة الدولة والمسافات والشرطات.',
            '*.after_or_equal' => 'تاريخ النهاية يجب ألا يسبق تاريخ البداية.',
        ]);
        $validator->after(function ($validator) use ($request, $rules) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $filters = array_filter($request->only(array_keys($rules)), fn ($value) => $value !== null && trim((string) $value) !== '');
            $criteria = array_diff_key($filters, array_flip(['search_type', 'per_page', 'page']));
            if (!$criteria) {
                $validator->errors()->add('search_text', 'أدخل نصًا للبحث أو اختر مرشحًا واحدًا على الأقل.');
            }
            $text = trim((string) $request->input('search_text', ''));
            if ($text !== '' && (mb_strlen($text) < config('search.min_search_length', 3) || !preg_match('/[\p{L}\p{N}]/u', $text))) {
                $validator->errors()->add('search_text', 'أدخل ثلاثة أحرف أو أرقام على الأقل للبحث العام.');
            }
            if ($request->filled('phone_number')) {
                $digits = preg_replace('/\D/', '', $this->searchService->normalizeDigits($request->input('phone_number')));
                if (strlen($digits) < 4) {
                    $validator->errors()->add('phone_number', 'أدخل أربعة أرقام على الأقل للبحث بالجوال.');
                }
            }
            $birth = $request->filled('birth_date_from') || $request->filled('birth_date_to');
            $death = $request->filled('death_date_from') || $request->filled('death_date_to');
            if ($birth && $death) {
                $validator->errors()->add('death_date_from', 'استخدم فترة الميلاد أو فترة الوفاة في البحث الواحد، وليس كليهما.');
            }
            $type = $request->input('search_type');
            if (($type === 'deceased' && $birth) || (in_array($type, ['main_records', 'family_members'], true) && $death)) {
                $validator->errors()->add('search_type', 'فترة التاريخ المختارة غير متاحة لنوع السجل المحدد.');
            }
            if ($type !== 'all') {
                $supported = config('search.searchable_tables.'.$type.'.filters', []);
                foreach (['relationship_id', 'marital_status_id', 'academic_qualification_id', 'health_status_id', 'employment_status_id'] as $filter) {
                    if ($request->filled($filter) && !isset($supported[$filter])) {
                        $validator->errors()->add($filter, 'هذا المرشح غير متاح لنوع السجل المحدد.');
                    }
                }
            }
        });
        return $validator->validate();
    }

    private function logFailure(string $operation, \Throwable $error): void
    {
        Log::error('Records search failed', ['operation' => $operation, 'exception' => get_class($error), 'code' => $error->getCode(), 'user_id' => auth()->id()]);
    }
}
