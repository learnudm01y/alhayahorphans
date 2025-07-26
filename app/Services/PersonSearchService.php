<?php

namespace App\Services;

use App\Models\Persons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PersonSearchService
{
    protected $cacheService;

    public function __construct(SearchCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }
    /**
     * البحث الشامل في جدول الأشخاص
     *
     * @param string $searchTerm
     * @param array $filters
     * @return Builder
     */
    public function searchPersons($searchTerm = null, $filters = [])
    {
        $query = Persons::query();

        // تحديد الحقول المطلوبة فقط لتحسين الأداء
        $query->select([
            'ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB',
            'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_BIRTH_DT',
            'CI_SEX_CD', 'CITY', 'CI_PERSONAL_CD', 'MOTHER_NAME1'
        ]);

        // البحث الشامل بالنص مع تحسين
        if (!empty($searchTerm)) {
            $this->applyOptimizedFullTextSearch($query, $searchTerm);
        }

        // تطبيق الفلاتر المحددة
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * البحث بالنص الكامل محسن
     *
     * @param Builder $query
     * @param string $searchTerm
     */
    private function applyOptimizedFullTextSearch(Builder $query, $searchTerm)
    {
        $searchTerms = $this->prepareSearchTerms($searchTerm);
        $fullSearchTerm = trim($searchTerm);

        $query->where(function ($q) use ($searchTerms, $fullSearchTerm) {
            // استخدام البحث النصي الكامل للنصوص الطويلة
            if (strlen($fullSearchTerm) >= 3) {
                $q->whereRaw('MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1) AGAINST(? IN BOOLEAN MODE)', [$fullSearchTerm . '*']);
            }

            // البحث التقليدي المحسن
            $q->orWhere('CI_ID_NUM', 'LIKE', $fullSearchTerm . '%')
              ->orWhere('CI_FIRST_ARB', 'LIKE', $fullSearchTerm . '%')
              ->orWhere('CI_FATHER_ARB', 'LIKE', $fullSearchTerm . '%')
              ->orWhere('CI_FAMILY_ARB', 'LIKE', $fullSearchTerm . '%');

            // البحث بالاسم الكامل المحسن
            $this->addOptimizedFullNameSearches($q, $searchTerms, $fullSearchTerm);
        });
    }

    /**
     * البحث بتركيبات الاسم الكامل محسن
     *
     * @param Builder $query
     * @param array $searchTerms
     * @param string $fullSearchTerm
     */
    private function addOptimizedFullNameSearches(Builder $query, $searchTerms, $fullSearchTerm)
    {
        if (count($searchTerms) >= 2) {
            // استخدام فهرس مركب للبحث السريع
            $nameVariations = [
                'CONCAT(CI_FIRST_ARB, " ", CI_FATHER_ARB)',
                'CONCAT(CI_FIRST_ARB, " ", CI_FAMILY_ARB)',
                'CONCAT(CI_FIRST_ARB, " ", CI_FATHER_ARB, " ", CI_GRAND_FATHER_ARB)',
                'CONCAT(CI_FIRST_ARB, " ", CI_FATHER_ARB, " ", CI_FAMILY_ARB)',
            ];

            foreach ($nameVariations as $variation) {
                $query->orWhereRaw("{$variation} LIKE ?", [$fullSearchTerm . '%']);
                $query->orWhereRaw("{$variation} LIKE ?", ['%' . $fullSearchTerm . '%']);
            }
        }
    }

    /**
     * البحث بتركيبات الاسم الكامل
     *
     * @param Builder $query
     * @param array $searchTerms
     * @param string $fullSearchTerm
     */
    private function addFullNameSearches(Builder $query, $searchTerms, $fullSearchTerm)
    {
        // البحث بالنص الكامل كما هو
        $query->orWhereRaw('CONCAT(COALESCE(CI_FIRST_ARB, ""), " ", COALESCE(CI_FATHER_ARB, ""), " ", COALESCE(CI_GRAND_FATHER_ARB, ""), " ", COALESCE(CI_FAMILY_ARB, "")) LIKE ?', ["%{$fullSearchTerm}%"]);

        // البحث بدون مسافات إضافية
        $cleanSearchTerm = preg_replace('/\s+/', ' ', $fullSearchTerm);
        if ($cleanSearchTerm !== $fullSearchTerm) {
            $query->orWhereRaw('CONCAT(COALESCE(CI_FIRST_ARB, ""), " ", COALESCE(CI_FATHER_ARB, ""), " ", COALESCE(CI_GRAND_FATHER_ARB, ""), " ", COALESCE(CI_FAMILY_ARB, "")) LIKE ?', ["%{$cleanSearchTerm}%"]);
        }

        if (count($searchTerms) >= 2) {
            // تركيبات مختلفة من الاسم
            $nameVariations = [
                // الاسم + الأب
                'CONCAT(COALESCE(CI_FIRST_ARB, ""), " ", COALESCE(CI_FATHER_ARB, ""))',
                // الاسم + العائلة
                'CONCAT(COALESCE(CI_FIRST_ARB, ""), " ", COALESCE(CI_FAMILY_ARB, ""))',
                // الاسم + الأب + الجد
                'CONCAT(COALESCE(CI_FIRST_ARB, ""), " ", COALESCE(CI_FATHER_ARB, ""), " ", COALESCE(CI_GRAND_FATHER_ARB, ""))',
                // الاسم + الأب + العائلة
                'CONCAT(COALESCE(CI_FIRST_ARB, ""), " ", COALESCE(CI_FATHER_ARB, ""), " ", COALESCE(CI_FAMILY_ARB, ""))',
                // الأب + الجد + العائلة
                'CONCAT(COALESCE(CI_FATHER_ARB, ""), " ", COALESCE(CI_GRAND_FATHER_ARB, ""), " ", COALESCE(CI_FAMILY_ARB, ""))'
            ];

            foreach ($nameVariations as $variation) {
                $query->orWhereRaw("TRIM({$variation}) LIKE ?", ["%{$fullSearchTerm}%"]);

                // البحث العكسي
                $reversedTerm = implode(' ', array_reverse($searchTerms));
                if ($reversedTerm !== $fullSearchTerm) {
                    $query->orWhereRaw("TRIM({$variation}) LIKE ?", ["%{$reversedTerm}%"]);
                }
            }

            // البحث بترتيب مختلط للكلمات
            if (count($searchTerms) >= 3) {
                foreach ($searchTerms as $i => $term1) {
                    foreach ($searchTerms as $j => $term2) {
                        if ($i !== $j) {
                            $twoTermSearch = $term1 . ' ' . $term2;
                            $query->orWhereRaw('CONCAT(COALESCE(CI_FIRST_ARB, ""), " ", COALESCE(CI_FATHER_ARB, ""), " ", COALESCE(CI_GRAND_FATHER_ARB, ""), " ", COALESCE(CI_FAMILY_ARB, "")) LIKE ?', ["%{$twoTermSearch}%"]);
                        }
                    }
                }
            }
        }
    }

    /**
     * تطبيق الفلاتر المحددة
     *
     * @param Builder $query
     * @param array $filters
     */
    private function applyFilters(Builder $query, $filters)
    {
        // فلتر رقم الهوية
        if (!empty($filters['ci_id_num'])) {
            $query->where('CI_ID_NUM', 'LIKE', "%{$filters['ci_id_num']}%");
        }

        // فلتر الاسم الأول
        if (!empty($filters['first_name'])) {
            $query->where('CI_FIRST_ARB', 'LIKE', "%{$filters['first_name']}%");
        }

        // فلتر اسم الأب
        if (!empty($filters['father_name'])) {
            $query->where('CI_FATHER_ARB', 'LIKE', "%{$filters['father_name']}%");
        }

        // فلتر اسم الجد
        if (!empty($filters['grandfather_name'])) {
            $query->where('CI_GRAND_FATHER_ARB', 'LIKE', "%{$filters['grandfather_name']}%");
        }

        // فلتر اسم العائلة
        if (!empty($filters['family_name'])) {
            $query->where('CI_FAMILY_ARB', 'LIKE', "%{$filters['family_name']}%");
        }

        // فلتر اسم الأم
        if (!empty($filters['mother_name'])) {
            $query->where('MOTHER_NAME1', 'LIKE', "%{$filters['mother_name']}%");
        }

        // فلتر الجنس
        if (!empty($filters['gender'])) {
            $query->where('CI_SEX_CD', $filters['gender']);
        }

        // فلتر المدينة
        if (!empty($filters['city'])) {
            $query->where('CITY', $filters['city']);
        }

        // فلتر تاريخ الميلاد (من)
        if (!empty($filters['birth_date_from'])) {
            $query->where('CI_BIRTH_DT', '>=', $filters['birth_date_from']);
        }

        // فلتر تاريخ الميلاد (إلى)
        if (!empty($filters['birth_date_to'])) {
            $query->where('CI_BIRTH_DT', '<=', $filters['birth_date_to']);
        }

        // فلتر الحالة الاجتماعية
        if (!empty($filters['social_status'])) {
            $query->where('CI_PERSONAL_CD', $filters['social_status']);
        }
    }

    /**
     * تحضير مصطلحات البحث
     *
     * @param string $searchTerm
     * @return array
     */
    private function prepareSearchTerms($searchTerm)
    {
        // إزالة المسافات الإضافية وتقسيم النص
        $terms = array_filter(explode(' ', trim($searchTerm)));

        // إزالة الكلمات الصغيرة جداً (أقل من حرفين)
        $terms = array_filter($terms, function($term) {
            return mb_strlen(trim($term)) >= 2;
        });

        return array_values($terms);
    }

    /**
     * البحث السريع (للاستخدام في AJAX) مع الكاش
     *
     * @param string $searchTerm
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function quickSearch($searchTerm, $limit = 10)
    {
        // استخدام الكاش للبحث السريع
        return $this->cacheService->getQuickSearchResults($searchTerm, $limit, function() use ($searchTerm, $limit) {
            // تحسين الاستعلام للاستفادة من الفهارس
            $query = Persons::query();

            // استخدام الفهرس النصي الكامل للبحث السريع
            if (strlen($searchTerm) >= 3) {
                $query->whereRaw('MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1) AGAINST(? IN BOOLEAN MODE)', [$searchTerm . '*']);
            } else {
                // للنصوص القصيرة، استخدام الفهارس العادية
                $query->where(function($q) use ($searchTerm) {
                    $q->where('CI_ID_NUM', 'LIKE', $searchTerm . '%')
                      ->orWhere('CI_FIRST_ARB', 'LIKE', $searchTerm . '%')
                      ->orWhere('CI_FATHER_ARB', 'LIKE', $searchTerm . '%')
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', $searchTerm . '%');
                });
            }

            // ترتيب محسن للنتائج
            $query->orderByRaw('
                CASE
                    WHEN CI_ID_NUM LIKE ? THEN 1
                    WHEN CI_FIRST_ARB LIKE ? THEN 2
                    WHEN CONCAT(CI_FIRST_ARB, " ", CI_FATHER_ARB) LIKE ? THEN 3
                    ELSE 4
                END, ID DESC
            ', [$searchTerm . '%', $searchTerm . '%', '%' . $searchTerm . '%']);

            // تحديد الحقول المطلوبة فقط لتحسين الأداء
            $query->select([
                'ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB',
                'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_BIRTH_DT', 'CITY'
            ]);

            return $query->limit($limit)->get()->map(function ($person) {
                return [
                    'id' => $person->ID ?? $person->id,
                    'full_name' => $this->getFullName($person),
                    'ci_id_num' => $person->CI_ID_NUM,
                    'city' => $person->city->name ?? $person->CITY ?? 'غير محدد',
                    'birth_date' => $person->CI_BIRTH_DT,
                ];
            });
        });
    }

    /**
     * الحصول على الاسم الكامل
     *
     * @param Persons $person
     * @return string
     */
    private function getFullName(Persons $person)
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
     * إحصائيات البحث
     *
     * @param string $searchTerm
     * @param array $filters
     * @return array
     */
    public function getSearchStatistics($searchTerm = null, $filters = [])
    {
        $query = $this->searchPersons($searchTerm, $filters);

        $total = $query->count();
        $maleCount = (clone $query)->where('CI_SEX_CD', 1)->count();
        $femaleCount = (clone $query)->where('CI_SEX_CD', 2)->count();

        return [
            'total' => $total,
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'cities_count' => (clone $query)->distinct('CITY')->count(),
        ];
    }
}
