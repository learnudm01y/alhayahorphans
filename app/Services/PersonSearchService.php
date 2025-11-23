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
     * تطبيع النص العربي (بما في ذلك المسافات)
     */
    private function normalizeArabic($text)
    {
        return normalizeArabicText($text);
    }

    /**
     * بناء SQL expression لتطبيع الأعمدة (بما في ذلك المسافات)
     */
    private function buildNormalizationSQL($column)
    {
        return "TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', ''),
                '  ', ' '), '   ', ' '))";
    }

    /**
     * بناء SQL expression لإزالة كل المسافات (للكلمات المركبة)
     */
    private function buildNoSpacesSQL($column)
    {
        return "REPLACE(TRIM(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                {$column},
                'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ـ', '')),
                ' ', '')";
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
     * البحث بالنص الكامل محسن (مع التطبيع والمسافات والكلمات المركبة)
     *
     * @param Builder $query
     * @param string $searchTerm
     */
    private function applyOptimizedFullTextSearch(Builder $query, $searchTerm)
    {
        $searchTerms = $this->prepareSearchTerms($searchTerm);
        $fullSearchTerm = trim($searchTerm);
        $normalizedTerm = $this->normalizeArabic($fullSearchTerm);
        $noSpacesTerm = str_replace(' ', '', $normalizedTerm);

        $query->where(function ($q) use ($searchTerms, $fullSearchTerm, $normalizedTerm, $noSpacesTerm) {
            // البحث المطبع - تطبيع الأعمدة والبحث (بما في ذلك المسافات)
            $firstNameExpr = $this->buildNormalizationSQL('CI_FIRST_ARB');
            $fatherNameExpr = $this->buildNormalizationSQL('CI_FATHER_ARB');
            $familyNameExpr = $this->buildNormalizationSQL('CI_FAMILY_ARB');

            $q->whereRaw("{$firstNameExpr} LIKE ?", ['%' . $normalizedTerm . '%'])
              ->orWhereRaw("{$fatherNameExpr} LIKE ?", ['%' . $normalizedTerm . '%'])
              ->orWhereRaw("{$familyNameExpr} LIKE ?", ['%' . $normalizedTerm . '%']);

            // البحث بدون مسافات (للكلمات المركبة مثل عبدالناصر / عبد الناصر)
            $firstNameNoSpaces = $this->buildNoSpacesSQL('CI_FIRST_ARB');
            $fatherNameNoSpaces = $this->buildNoSpacesSQL('CI_FATHER_ARB');
            $familyNameNoSpaces = $this->buildNoSpacesSQL('CI_FAMILY_ARB');

            $q->orWhereRaw("{$firstNameNoSpaces} LIKE ?", ['%' . $noSpacesTerm . '%'])
              ->orWhereRaw("{$fatherNameNoSpaces} LIKE ?", ['%' . $noSpacesTerm . '%'])
              ->orWhereRaw("{$familyNameNoSpaces} LIKE ?", ['%' . $noSpacesTerm . '%']);

            // البحث برقم الهوية (بدون تطبيع)
            $q->orWhere('CI_ID_NUM', 'LIKE', $fullSearchTerm . '%');

            // البحث بالاسم الكامل المحسن
            $this->addOptimizedFullNameSearches($q, $searchTerms, $normalizedTerm, $noSpacesTerm);
        });
    }

    /**
     * البحث بتركيبات الاسم الكامل محسن (مع التطبيع والمسافات والكلمات المركبة)
     *
     * @param Builder $query
     * @param array $searchTerms
     * @param string $normalizedTerm
     * @param string $noSpacesTerm
     */
    private function addOptimizedFullNameSearches(Builder $query, $searchTerms, $normalizedTerm, $noSpacesTerm = null)
    {
        if (count($searchTerms) >= 2) {
            // استخدام فهرس مركب للبحث السريع مع التطبيع
            $normalizedFullName = $this->buildNormalizationSQL(
                "CONCAT(COALESCE(CI_FIRST_ARB, ''), ' ', COALESCE(CI_FATHER_ARB, ''), ' ',
                       COALESCE(CI_GRAND_FATHER_ARB, ''), ' ', COALESCE(CI_FAMILY_ARB, ''))"
            );

            $query->orWhereRaw("{$normalizedFullName} LIKE ?", [$normalizedTerm . '%'])
                  ->orWhereRaw("{$normalizedFullName} LIKE ?", ['%' . $normalizedTerm . '%']);

            // البحث بدون مسافات (للكلمات المركبة)
            if ($noSpacesTerm) {
                $noSpacesFullName = $this->buildNoSpacesSQL(
                    "CONCAT(COALESCE(CI_FIRST_ARB, ''), COALESCE(CI_FATHER_ARB, ''),
                           COALESCE(CI_GRAND_FATHER_ARB, ''), COALESCE(CI_FAMILY_ARB, ''))"
                );
                $query->orWhereRaw("{$noSpacesFullName} LIKE ?", ['%' . $noSpacesTerm . '%']);
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
     * تطبيق الفلاتر المحددة (مع التطبيع)
     *
     * @param Builder $query
     * @param array $filters
     */
    private function applyFilters(Builder $query, $filters)
    {
        // فلتر رقم الهوية (بدون تطبيع)
        if (!empty($filters['ci_id_num'])) {
            $query->where('CI_ID_NUM', 'LIKE', "%{$filters['ci_id_num']}%");
        }

        // فلتر الاسم الأول (مع التطبيع والمسافات)
        if (!empty($filters['first_name'])) {
            $normalized = $this->normalizeArabic($filters['first_name']);
            $firstNameExpr = $this->buildNormalizationSQL('CI_FIRST_ARB');
            $query->whereRaw("{$firstNameExpr} LIKE ?", ['%' . $normalized . '%']);
        }

        // فلتر اسم الأب (مع التطبيع والمسافات)
        if (!empty($filters['father_name'])) {
            $normalized = $this->normalizeArabic($filters['father_name']);
            $fatherNameExpr = $this->buildNormalizationSQL('CI_FATHER_ARB');
            $query->whereRaw("{$fatherNameExpr} LIKE ?", ['%' . $normalized . '%']);
        }

        // فلتر اسم الجد (مع التطبيع والمسافات)
        if (!empty($filters['grandfather_name'])) {
            $normalized = $this->normalizeArabic($filters['grandfather_name']);
            $grandNameExpr = $this->buildNormalizationSQL('CI_GRAND_FATHER_ARB');
            $query->whereRaw("{$grandNameExpr} LIKE ?", ['%' . $normalized . '%']);
        }

        // فلتر اسم العائلة (مع التطبيع والمسافات)
        if (!empty($filters['family_name'])) {
            $normalized = $this->normalizeArabic($filters['family_name']);
            $familyNameExpr = $this->buildNormalizationSQL('CI_FAMILY_ARB');
            $query->whereRaw("{$familyNameExpr} LIKE ?", ['%' . $normalized . '%']);
        }

        // فلتر اسم الأم (مع التطبيع والمسافات)
        if (!empty($filters['mother_name'])) {
            $normalized = $this->normalizeArabic($filters['mother_name']);
            $motherNameExpr = $this->buildNormalizationSQL('MOTHER_NAME1');
            $query->whereRaw("{$motherNameExpr} LIKE ?", ['%' . $normalized . '%']);
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
     * البحث السريع (للاستخدام في AJAX) مع الكاش والتطبيع
     *
     * @param string $searchTerm
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function quickSearch($searchTerm, $limit = 10)
    {
        // استخدام الكاش للبحث السريع
        return $this->cacheService->getQuickSearchResults($searchTerm, $limit, function() use ($searchTerm, $limit) {
            $query = Persons::query();
            $normalizedTerm = $this->normalizeArabic($searchTerm);

            // البحث المطبع (مع المسافات)
            $query->where(function($q) use ($searchTerm, $normalizedTerm) {
                $firstNameExpr = $this->buildNormalizationSQL('CI_FIRST_ARB');
                $fatherNameExpr = $this->buildNormalizationSQL('CI_FATHER_ARB');
                $familyNameExpr = $this->buildNormalizationSQL('CI_FAMILY_ARB');

                // البحث برقم الهوية (بدون تطبيع)
                $q->where('CI_ID_NUM', 'LIKE', $searchTerm . '%')
                  // البحث في الأسماء مع التطبيع والمسافات
                  ->orWhereRaw("{$firstNameExpr} LIKE ?", [$normalizedTerm . '%'])
                  ->orWhereRaw("{$fatherNameExpr} LIKE ?", [$normalizedTerm . '%'])
                  ->orWhereRaw("{$familyNameExpr} LIKE ?", [$normalizedTerm . '%']);
            });

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
