<?php

namespace App\Services;

use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchService
{
    private $config;

    public function __construct()
    {
        $this->config = config('search');

        // التأكد من وجود التكوين أو استخدام القيم الافتراضية
        if (!$this->config) {
            $this->config = [
                'enable_cache' => true,
                'cache_duration' => 15,
                'max_per_page' => 100,
                'max_all_results' => 60,
                'min_search_length' => 3,
                'search_timeout' => 30,
                'rate_limit_per_minute' => 60,
                'searchable_tables' => [
                    'data' => [
                        'model' => \App\Models\Data::class,
                        'name' => 'السجلات الرئيسية',
                        'fields' => [
                            'text_search' => ['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name', 'data_id_number', 'file_id_number'],
                            'exact_match' => ['file_id_number', 'data_id_number'],
                            'name_fields' => ['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'],
                            'relationship_fields' => [
                                'section',
                                'requestStatus',
                                'categoryOfRelation',
                                'healthStatus',
                                'maritalStatus',
                                'academicQualification',
                                'displacementStatus',
                                'city',
                                'province',
                                'employmentStatusBreadwinner',
                                'housingStatus',
                                'currentHousingType',
                                'userInserted'
                            ]
                        ]
                    ],
                    'family_members' => [
                        'model' => \App\Models\RePeople::class,
                        'name' => 'أفراد الأسرة',
                        'fields' => [
                            'text_search' => ['first_name', 'second_name', 'third_name', 'last_name', 'person_id', 'registration_id'],
                            'exact_match' => ['person_id', 'registration_id'],
                            'name_fields' => ['first_name', 'second_name', 'third_name', 'last_name'],
                            'relationship_fields' => [
                                'healthStatus',
                                'sponsorshipStatus',
                                'guaranteeType',
                                'documentType'
                            ]
                        ]
                    ],
                    'deceased' => [
                        'model' => \App\Models\DeadPepole::class,
                        'name' => 'المتوفين',
                        'fields' => [
                            'text_search' => [
                                'father_first_name', 'father_second_name', 'father_third_name', 'father_last_name', 'father_id',
                                'mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name', 'mother_id',
                                're_file_id'
                            ],
                            'exact_match' => ['father_id', 'mother_id', 're_file_id'],
                            'name_fields' => [
                                'father_first_name', 'father_second_name', 'father_third_name', 'father_last_name',
                                'mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name'
                            ],
                            'relationship_fields' => [
                                'fatherDeathReason',
                                'motherDeathReason'
                            ]
                        ]
                    ]
                ]
            ];
        }
    }

    /**
     * البحث الشامل الذكي
     */
    public function smartSearch(array $filters, int $perPage = 25): array
    {
        $searchType = $filters['search_type'] ?? 'all';

        // تنظيف وتحسين نص البحث
        if (!empty($filters['search_text'])) {
            $filters['search_text'] = $this->cleanSearchText($filters['search_text']);
        }

        $cacheKey = $this->generateCacheKey($filters);

        // التحقق من وجود النتائج في الكاش
        if (isset($this->config['enable_cache']) && $this->config['enable_cache']) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::info('Search results served from cache', ['cache_key' => $cacheKey]);
                return $cached;
            }
        }

        $results = $this->executeSearch($filters, $perPage, $searchType);

        // حفظ النتائج في الكاش
        if (isset($this->config['enable_cache']) && $this->config['enable_cache']) {
            $cacheDuration = $this->config['cache_duration'] ?? 15;
            Cache::put($cacheKey, $results, $cacheDuration * 60);
        }

        return $results;
    }

    /**
     * تنظيف وتحسين نص البحث
     */
    private function cleanSearchText(string $searchText): string
    {
        // إزالة المسافات الزائدة
        $cleaned = preg_replace('/\s+/', ' ', trim($searchText));

        // معالجة النصوص العربية: إزالة التشكيل
        $cleaned = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $cleaned);

        // إزالة الأحرف الخاصة غير المرغوب فيها مع الحفاظ على العربية والإنجليزية
        $cleaned = preg_replace('/[^\p{Arabic}\p{Latin}\p{N}\s\-\.]/u', ' ', $cleaned);

        // إزالة المسافات الزائدة مرة أخرى بعد التنظيف
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return $cleaned;
    }

    /**
     * معالجة خاصة للنصوص العربية والإنجليزية
     */
    private function normalizeSearchTerms(array $terms): array
    {
        return array_map(function($term) {
            // تطبيع النص العربي
            $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $term);
            $normalized = str_replace(['ة'], 'ه', $normalized);
            $normalized = str_replace(['ي'], 'ى', $normalized);

            return trim($normalized);
        }, $terms);
    }

    /**
     * تنفيذ البحث الفعلي
     */
    private function executeSearch(array $filters, int $perPage, string $searchType): array
    {
        switch ($searchType) {
            case 'all':
                return $this->searchAllTables($filters);
            case 'main_records':
                return $this->searchSingleTable($filters, $perPage, 'data');
            case 'family_members':
                return $this->searchSingleTable($filters, $perPage, 'family_members');
            case 'deceased':
                return $this->searchSingleTable($filters, $perPage, 'deceased');
            default:
                throw new \InvalidArgumentException("نوع بحث غير صحيح: {$searchType}");
        }
    }

    /**
     * البحث في جميع الجداول
     */
    private function searchAllTables(array $filters): array
    {
        $results = [
            'main_records' => [],
            'family_members' => [],
            'deceased' => [],
            'total_count' => 0
        ];

        // البحث المتوازي في جميع الجداول
        $queries = [
            'main_records' => $this->buildQuery('data', $filters),
            'family_members' => $this->buildQuery('family_members', $filters),
            'deceased' => $this->buildQuery('deceased', $filters)
        ];

        foreach ($queries as $type => $query) {
            $records = $query->limit(20)->get();
            $results[$type] = $this->formatResults($records, $type);
            $results['total_count'] += $records->count();
        }

        return $results;
    }

    /**
     * البحث في جدول واحد مع التصفح
     */
    private function searchSingleTable(array $filters, int $perPage, string $tableType): array
    {
        $query = $this->buildQuery($tableType, $filters);
        $results = $query->paginate($perPage);

        return [
            'data' => $this->formatResults($results->items(), $tableType),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'total_pages' => $results->lastPage(),
                'total_records' => $results->total(),
                'per_page' => $results->perPage(),
                'has_more' => $results->hasMorePages()
            ]
        ];
    }

    /**
     * بناء استعلام ذكي
     */
    private function buildQuery(string $tableType, array $filters): Builder
    {
        // التأكد من وجود التكوين للجدول المطلوب
        if (!isset($this->config['searchable_tables'][$tableType])) {
            throw new \InvalidArgumentException("نوع جدول غير مدعوم: {$tableType}");
        }

        $tableConfig = $this->config['searchable_tables'][$tableType];
        $model = $tableConfig['model'];

        Log::info('Building query', [
            'table_type' => $tableType,
            'model' => $model,
            'has_search_text' => !empty($filters['search_text']),
            'search_text' => $filters['search_text'] ?? null
        ]);

        $query = $model::query();

        // إضافة العلاقات المطلوبة
        if (isset($tableConfig['fields']['relationship_fields'])) {
            $relationships = array_values($tableConfig['fields']['relationship_fields']);
            $query->with($relationships);
        }

        // البحث النصي الذكي
        if (!empty($filters['search_text'])) {
            Log::info('Adding text search to query', [
                'table_type' => $tableType,
                'search_text' => $filters['search_text']
            ]);
            $this->addTextSearch($query, $tableConfig, $filters['search_text']);
        }

        // البحث المحدد
        $this->addSpecificFilters($query, $tableConfig, $filters);

        // البحث بالتاريخ
        $this->addDateFilters($query, $tableConfig, $filters);

        // إضافة فلاتر العلاقات
        $this->addRelationshipFilters($query, $tableConfig, $filters);

        // إضافة شرط السجلات المقبولة فقط للسجلات الرئيسية
        if ($tableType === 'data') {
            // البحث عن ID حالة "مقبول" في جدول request_status
            $acceptedStatusId = Cache::remember('accepted_status_id', 3600, function() {
                return DB::table('request_status')->where('description', 'مقبول')->value('id');
            });

            Log::info('Applying status filter', [
                'table_type' => $tableType,
                'accepted_status_id' => $acceptedStatusId
            ]);

            if ($acceptedStatusId) {
                $query->where('data_request_status', $acceptedStatusId);
            } else {
                // إذا لم نجد حالة "مقبول", نعرض جميع السجلات
                Log::warning('Accepted status not found in request_status table');
            }
        }

        Log::info('Query built successfully', [
            'table_type' => $tableType,
            'final_sql' => $query->toSql()
        ]);

        return $query->orderBy('id', 'desc');
    }

    /**
     * إضافة البحث النصي الذكي والمرن للأسماء المركبة
     */
    private function addTextSearch(Builder $query, array $tableConfig, string $searchText): void
    {
        $searchFields = $tableConfig['fields']['text_search'] ?? [];

        if (empty($searchFields)) {
            return;
        }

        // تقسيم النص المدخل إلى كلمات
        $searchTerms = array_filter(array_map('trim', explode(' ', trim($searchText))));

        // تطبيع المصطلحات العربية والإنجليزية
        $normalizedTerms = $this->normalizeSearchTerms($searchTerms);

        // إضافة logging للتتبع
        Log::info('Enhanced search terms processing', [
            'original_text' => $searchText,
            'search_terms' => $searchTerms,
            'normalized_terms' => $normalizedTerms,
            'search_fields' => $searchFields,
            'table_model' => $tableConfig['model'] ?? 'unknown'
        ]);

        $query->where(function($q) use ($searchFields, $searchText, $searchTerms, $normalizedTerms, $tableConfig) {
            // إذا كان البحث يحتوي على أكثر من 4 كلمات، استخدم البحث الدقيق فقط
            if (count($searchTerms) > 4) {
                $this->addPreciseSearch($q, $tableConfig, $searchText, $searchTerms);
            } else {
                // للبحث القصير - البحث العادي الشامل
                $this->addComprehensiveSearch($q, $tableConfig, $searchText, $searchTerms, $normalizedTerms, $searchFields);
            }
        });
    }

    /**
     * البحث الدقيق للأسماء الطويلة (أكثر من 4 كلمات)
     */
    private function addPreciseSearch(Builder $query, array $tableConfig, string $searchText, array $searchTerms): void
    {
        Log::info('Using precise search for long name', [
            'search_text' => $searchText,
            'terms_count' => count($searchTerms)
        ]);

        // 1. البحث بالاسم الكامل فقط باستخدام CONCAT
        $this->addConcatenatedNameSearch($query, $tableConfig, $searchText);

        // 2. البحث بأول 3 كلمات وآخر 3 كلمات (للأسماء الطويلة جداً)
        if (count($searchTerms) >= 6) {
            $firstThree = implode(' ', array_slice($searchTerms, 0, 3));
            $lastThree = implode(' ', array_slice($searchTerms, -3));
            $this->addConcatenatedNameSearch($query, $tableConfig, $firstThree);
            $this->addConcatenatedNameSearch($query, $tableConfig, $lastThree);
        }

        // 3. البحث بأول كلمتين وآخر كلمتين
        $firstTwo = implode(' ', array_slice($searchTerms, 0, 2));
        $lastTwo = implode(' ', array_slice($searchTerms, -2));
        $this->addConcatenatedNameSearch($query, $tableConfig, $firstTwo);
        $this->addConcatenatedNameSearch($query, $tableConfig, $lastTwo);
    }

    /**
     * البحث الشامل للأسماء القصيرة (4 كلمات أو أقل)
     */
    private function addComprehensiveSearch(Builder $query, array $tableConfig, string $searchText, array $searchTerms, array $normalizedTerms, array $searchFields): void
    {
        // 1. البحث في الاسم الكامل المركب باستخدام CONCAT (أعلى أولوية)
        $this->addConcatenatedNameSearch($query, $tableConfig, $searchText);

        // 2. البحث في جزء من الاسم الكامل (للأسماء الجزئية)
        if (count($searchTerms) >= 2) {
            // البحث بأول كلمتين
            $firstTwo = implode(' ', array_slice($searchTerms, 0, 2));
            $this->addConcatenatedNameSearch($query, $tableConfig, $firstTwo);

            // البحث بآخر كلمتين
            if (count($searchTerms) > 2) {
                $lastTwo = implode(' ', array_slice($searchTerms, -2));
                $this->addConcatenatedNameSearch($query, $tableConfig, $lastTwo);
            }
        }

        // 3. البحث التقليدي في كل حقل منفصل
        foreach ($searchFields as $field) {
            $query->orWhere($field, 'LIKE', "%{$searchText}%");
        }

        // 4. البحث المرن: محاولة العثور على تطابقات جزئية
        if (count($searchTerms) > 1) {
            $this->addFlexibleWordSearch($query, $tableConfig, $searchTerms);

            // البحث أيضًا بالمصطلحات المطبعة
            if ($normalizedTerms !== $searchTerms) {
                $this->addFlexibleWordSearch($query, $tableConfig, $normalizedTerms);
            }
        }

        // 5. البحث في تركيبات الأسماء (للأسماء المزدوجة)
        if (count($searchTerms) >= 2) {
            $nameFields = $this->getNameFieldsForModel($tableConfig['model']);
            $this->addNameCombinationSearch($query, $nameFields, $searchTerms);
        }

        // 6. بحث بدون مسافات للأرقام
        if (is_numeric(str_replace(' ', '', $searchText))) {
            $numericSearch = str_replace(' ', '', $searchText);
            foreach ($searchFields as $field) {
                if (str_contains($field, 'id') || str_contains($field, 'number')) {
                    $query->orWhere($field, 'LIKE', "%{$numericSearch}%");
                }
            }
        }
    }

    /**
     * البحث في الاسم الكامل المركب باستخدام CONCAT
     */
    private function addConcatenatedNameSearch(Builder $query, array $tableConfig, string $searchText): void
    {
        $model = $tableConfig['model'];
        $nameFields = $this->getNameFieldsForModel($model);

        Log::info('CONCAT search attempt', [
            'model' => $model,
            'name_fields' => $nameFields,
            'search_text' => $searchText
        ]);

        if (empty($nameFields) || count($nameFields) < 2) {
            Log::info('CONCAT search skipped - insufficient name fields', [
                'model' => $model,
                'name_fields_count' => count($nameFields)
            ]);
            return;
        }

        // إنشاء CONCAT لجميع أعمدة الأسماء
        $concatFields = implode(", ' ', ", $nameFields);
        $concatQuery = "CONCAT({$concatFields}) LIKE ?";

        Log::info('CONCAT search executing', [
            'model' => $model,
            'concat_query' => $concatQuery,
            'search_parameter' => "%{$searchText}%"
        ]);

        $query->orWhereRaw($concatQuery, ["%{$searchText}%"]);

        // إضافة بحث بدون مسافات إضافية
        $cleanSearchText = preg_replace('/\s+/', ' ', trim($searchText));
        if ($cleanSearchText !== $searchText) {
            $query->orWhereRaw($concatQuery, ["%{$cleanSearchText}%"]);

            Log::info('CONCAT search with cleaned text', [
                'original' => $searchText,
                'cleaned' => $cleanSearchText
            ]);
        }
    }

    /**
     * البحث المرن: محاولة العثور على أكبر عدد من الكلمات
     */
    private function addFlexibleWordSearch(Builder $query, array $tableConfig, array $searchTerms): void
    {
        $model = $tableConfig['model'];
        $nameFields = $this->getNameFieldsForModel($model);

        if (empty($nameFields)) {
            return;
        }

        // البحث المرن: يكفي أن توجد معظم الكلمات أو تركيبة منها
        $query->orWhere(function($subQuery) use ($searchTerms, $nameFields) {
            // محاولة 1: البحث عن كل كلمة في أي حقل (مرن جداً)
            foreach ($searchTerms as $term) {
                $subQuery->orWhere(function($termQuery) use ($term, $nameFields) {
                    foreach ($nameFields as $field) {
                        $termQuery->orWhere($field, 'LIKE', "%{$term}%");
                    }
                });
            }

            // محاولة 2: البحث عن تركيبات من كلمتين
            if (count($searchTerms) >= 2) {
                for ($i = 0; $i < count($searchTerms) - 1; $i++) {
                    $word1 = $searchTerms[$i];
                    $word2 = $searchTerms[$i + 1];
                    $twoWords = $word1 . ' ' . $word2;

                    // البحث عن الكلمتين المتتاليتين في أي حقل
                    foreach ($nameFields as $field) {
                        $subQuery->orWhere($field, 'LIKE', "%{$twoWords}%");
                    }
                }
            }

            // محاولة 3: البحث عن أول وآخر كلمة
            if (count($searchTerms) >= 2) {
                $firstWord = $searchTerms[0];
                $lastWord = $searchTerms[count($searchTerms) - 1];

                $subQuery->orWhere(function($pairQuery) use ($nameFields, $firstWord, $lastWord) {
                    // الأول في أي حقل والأخير في أي حقل
                    $pairQuery->where(function($firstQuery) use ($nameFields, $firstWord) {
                        foreach ($nameFields as $field) {
                            $firstQuery->orWhere($field, 'LIKE', "%{$firstWord}%");
                        }
                    })->where(function($lastQuery) use ($nameFields, $lastWord) {
                        foreach ($nameFields as $field) {
                            $lastQuery->orWhere($field, 'LIKE', "%{$lastWord}%");
                        }
                    });
                });
            }
        });
    }

    /**
     * البحث في تركيبات مختلفة من الأسماء المركبة
     */
    private function addNameCombinationSearch(Builder $query, array $nameFields, array $searchTerms): void
    {
        if (empty($nameFields) || count($searchTerms) < 2) {
            return;
        }

        $query->orWhere(function($combQuery) use ($nameFields, $searchTerms) {
            // جرب تركيبات مختلفة من الكلمات
            for ($i = 0; $i < count($searchTerms) - 1; $i++) {
                for ($j = $i + 1; $j < count($searchTerms); $j++) {
                    $term1 = $searchTerms[$i];
                    $term2 = $searchTerms[$j];

                    // البحث في أي حقلين مختلفين
                    for ($fieldIndex1 = 0; $fieldIndex1 < count($nameFields); $fieldIndex1++) {
                        for ($fieldIndex2 = $fieldIndex1 + 1; $fieldIndex2 < count($nameFields); $fieldIndex2++) {
                            $combQuery->orWhere(function($pairQuery) use ($nameFields, $fieldIndex1, $fieldIndex2, $term1, $term2) {
                                $pairQuery->where($nameFields[$fieldIndex1], 'LIKE', "%{$term1}%")
                                         ->where($nameFields[$fieldIndex2], 'LIKE', "%{$term2}%");
                            });

                            // عكس الترتيب
                            $combQuery->orWhere(function($pairQuery) use ($nameFields, $fieldIndex1, $fieldIndex2, $term1, $term2) {
                                $pairQuery->where($nameFields[$fieldIndex1], 'LIKE', "%{$term2}%")
                                         ->where($nameFields[$fieldIndex2], 'LIKE', "%{$term1}%");
                            });
                        }
                    }
                }
            }
        });
    }

    /**
     * الحصول على أعمدة الأسماء حسب النموذج
     */
    private function getNameFieldsForModel(string $model): array
    {
        // أولاً: البحث في التكوين عن أعمدة الأسماء
        foreach ($this->config['searchable_tables'] as $table => $config) {
            if ($config['model'] === $model && isset($config['fields']['name_fields'])) {
                $nameFields = $config['fields']['name_fields'];

                Log::info('Name fields from config', [
                    'model' => $model,
                    'table' => $table,
                    'name_fields' => $nameFields
                ]);

                if (!empty($nameFields)) {
                    return $nameFields;
                }
            }
        }

        // ثانياً: fallback للنماذج المعروفة
        $fallbackFields = [];
        switch ($model) {
            case \App\Models\Data::class:
            case 'App\\Models\\Data':
                $fallbackFields = ['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'];
                break;

            case \App\Models\RePeople::class:
            case 'App\\Models\\RePeople':
                $fallbackFields = ['first_name', 'second_name', 'third_name', 'last_name'];
                break;

            case \App\Models\DeadPepole::class:
            case 'App\\Models\\DeadPepole':
                $fallbackFields = ['father_first_name', 'father_second_name', 'father_third_name', 'father_last_name'];
                break;

            default:
                $fallbackFields = [];
                break;
        }

        Log::info('Name fields from fallback', [
            'model' => $model,
            'fallback_fields' => $fallbackFields
        ]);

        return $fallbackFields;
    }

    /**
     * إضافة المرشحات المحددة
     */
    private function addSpecificFilters(Builder $query, array $tableConfig, array $filters): void
    {
        $exactFields = $tableConfig['fields']['exact_match'] ?? [];

        foreach ($exactFields as $field) {
            $filterKey = $this->getFilterKey($field);
            if (!empty($filters[$filterKey])) {
                $query->where($field, 'LIKE', "%{$filters[$filterKey]}%");
            }
        }

        // فلتر الجنس
        if (!empty($filters['gender'])) {
            $genderValue = $filters['gender'] === 'male' ? 'ذكر' : 'أنثى';
            $genderField = $this->getGenderField($tableConfig);
            if ($genderField) {
                $query->where($genderField, $genderValue);
            }
        }
    }

    /**
     * إضافة فلاتر التاريخ
     */
    private function addDateFilters(Builder $query, array $tableConfig, array $filters): void
    {
        $dateFields = $tableConfig['fields']['date_fields'] ?? [];

        foreach ($dateFields as $field) {
            if (!empty($filters['birth_date_from'])) {
                $query->where($field, '>=', $filters['birth_date_from']);
            }

            if (!empty($filters['birth_date_to'])) {
                $query->where($field, '<=', $filters['birth_date_to']);
            }
        }
    }

    /**
     * إضافة فلاتر العلاقات
     */
    private function addRelationshipFilters(Builder $query, array $tableConfig, array $filters): void
    {
        $relationshipFields = $tableConfig['fields']['relationship_fields'] ?? [];

        foreach ($relationshipFields as $field => $relation) {
            $filterKey = str_replace('data_', '', $field) . '_id';
            if (!empty($filters[$filterKey])) {
                $query->where($field, $filters[$filterKey]);
            }
        }
    }

    /**
     * تنسيق النتائج
     */
    private function formatResults($records, string $type): array
    {
        if (empty($records)) {
            return [];
        }

        return collect($records)->map(function($record) use ($type) {
            switch ($type) {
                case 'main_records':
                case 'data':
                    return $this->formatMainRecord($record);
                case 'family_members':
                    return $this->formatFamilyMember($record);
                case 'deceased':
                    return $this->formatDeceasedRecord($record);
                default:
                    return $record->toArray();
            }
        })->toArray();
    }

    /**
     * تنسيق السجل الرئيسي - نسخة محسنة شاملة
     */
    private function formatMainRecord($record): array
    {
        // تحضير الاسم الكامل
        $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");

        // تحويل الجنس من رقم إلى نص
        $gender = '-';
        if ($record->data_gender == 1) {
            $gender = 'ذكر';
        } elseif ($record->data_gender == 2) {
            $gender = 'أنثى';
        }

        // حساب العمر إذا كان تاريخ الميلاد متوفراً
        $age = null;
        if ($record->data_birth_date) {
            try {
                $birthDate = new \DateTime($record->data_birth_date);
                $today = new \DateTime();
                $age = $today->diff($birthDate)->y;
            } catch (\Exception $e) {
                $age = null;
            }
        }

        return [
            // البيانات الأساسية
            'id' => $record->id,
            'type' => 'main_record',
            'file_id_number' => $record->file_id_number,
            'file_id' => $record->file_id_number, // alias للتوافق
            'original_file_id_from_excel' => $record->original_file_id_from_excel ?? null,

            // بيانات الهوية والاسم
            'data_id_number' => $record->data_id_number,
            'identity_number' => $record->data_id_number, // alias للتوافق
            'data_first_name' => $record->data_first_name,
            'data_father_name' => $record->data_father_name,
            'data_grand_father_name' => $record->data_grand_father_name,
            'data_family_name' => $record->data_family_name,
            'full_name' => $fullName,

            // البيانات الشخصية
            'data_birth_date' => $record->data_birth_date,
            'birth_date' => $record->data_birth_date, // alias للتوافق
            'age' => $age,
            'data_gender' => $gender,
            'gender' => $gender, // alias للتوافق

            // معلومات الاتصال
            'data_phone_number' => $record->data_phone_number,
            'phone' => $record->data_phone_number, // alias للتوافق
            'data_alt_phone_number' => $record->data_alt_phone_number,
            'alt_phone' => $record->data_alt_phone_number, // alias للتوافق

            // معلومات العنوان والموقع
            'data_current_address' => $record->data_current_address ?? null,
            'address' => $record->data_current_address ?? null, // alias للتوافق
            'data_address_before_displacement' => $record->data_address_before_displacement ?? null,

            // الأرقام والإحصائيات
            'data_number_of_individuals' => $record->data_number_of_individuals ?? null,
            'family_size' => $record->data_number_of_individuals ?? null, // alias للتوافق
            'data_number_mail' => $record->data_number_mail ?? null,
            'data_number_female' => $record->data_number_female ?? null,
            'data_number_of_individuals_with_chronic_diseases' => $record->data_number_of_individuals_with_chronic_diseases ?? null,
            'data_number_of_people_with_special_needs' => $record->data_number_of_people_with_special_needs ?? null,

            // البيانات التفصيلية
            'data_relationship' => $record->data_relationship ?? null,
            'data_description_needs' => $record->data_description_needs ?? null,
            'data_displacement_status' => $record->data_displacement_status ?? null,
            'data_housing_status' => $record->data_housing_status ?? null,
            'data_current_housing_type' => $record->data_current_housing_type ?? null,
            'data_user_insert_data' => $record->data_user_insert_data ?? null,

            // العلاقات والبيانات المرتبطة
            'section' => $record->section,
            'section_name' => optional($record->section)->description,
            'section_id' => $record->data_section_id ?? null,

            'city' => $record->city,
            'city_name' => optional($record->city)->city ?? $record->data_city,
            'city_id' => $record->data_city ?? null,

            'province' => $record->province,
            'province_name' => optional($record->province)->description ?? $record->data_province,
            'province_id' => $record->data_province ?? null,

            'requestStatus' => $record->requestStatus,
            'request_status' => optional($record->requestStatus)->description,
            'request_status_name' => optional($record->requestStatus)->description,
            'request_status_id' => $record->data_request_status ?? null,

            'healthStatus' => $record->healthStatus,
            'health_status' => optional($record->healthStatus)->description,
            'health_status_name' => optional($record->healthStatus)->description,
            'health_status_id' => $record->data_health_status ?? null,

            'maritalStatus' => $record->maritalStatus,
            'marital_status' => optional($record->maritalStatus)->description,
            'marital_status_name' => optional($record->maritalStatus)->description,
            'marital_status_id' => $record->data_marital_status ?? null,

            'academicQualification' => $record->academicQualification,
            'academic_qualification' => optional($record->academicQualification)->description,
            'education_level' => optional($record->academicQualification)->description, // alias للتوافق
            'academic_qualification_name' => optional($record->academicQualification)->description,
            'academic_qualification_id' => $record->data_academic_qualification ?? null,

            'categoryOfRelation' => $record->categoryOfRelation,
            'relationship' => optional($record->categoryOfRelation)->attribute,
            'relationship_name' => optional($record->categoryOfRelation)->attribute,
            'relationship_id' => $record->data_relationship ?? null,

            'employmentStatusBreadwinner' => $record->employmentStatusBreadwinner,
            'employment_status' => optional($record->employmentStatusBreadwinner)->description,
            'employment_status_name' => optional($record->employmentStatusBreadwinner)->description,
            'employment_status_id' => $record->data_employment_status_breadwinner ?? null,

            'housingStatus' => $record->housingStatus ?? null,
            'housing_status' => optional($record->housingStatus)->description,
            'housing_status_name' => optional($record->housingStatus)->description,

            'currentHousingType' => $record->currentHousingType ?? null,
            'housing_type' => optional($record->currentHousingType)->description,
            'housing_type_name' => optional($record->currentHousingType)->description,

            'displacementStatus' => $record->displacementStatus ?? null,
            'displacement_status' => optional($record->displacementStatus)->description,
            'displacement_status_name' => optional($record->displacementStatus)->description,

            // معلومات إضافية للتوافق
            'email' => null, // قد لا يكون متوفراً في هذا الجدول
            'monthly_income' => null, // قد يحتاج إلى حساب منفصل

            // روابط العمل
            'view_url' => url("/admin/records-management/{$record->id}/show"),
            'edit_url' => url("/admin/records-management/{$record->id}/edit"),

            // معلومات التوقيت
            'created_at' => $record->created_at ?? null,
            'updated_at' => $record->updated_at ?? null,
        ];
    }

    /**
     * تنسيق فرد من الأسرة - نسخة محسنة شاملة
     */
    private function formatFamilyMember($record): array
    {
        // تحويل الجنس: 1 = ذكر، 2 = أنثى
        $gender = '-';
        if ($record->person_gender == 1) {
            $gender = 'ذكر';
        } elseif ($record->person_gender == 2) {
            $gender = 'أنثى';
        }

        // تحضير الاسم الكامل
        $fullName = trim("{$record->first_name} {$record->second_name} {$record->third_name} {$record->last_name}");

        return [
            // البيانات الأساسية
            'id' => $record->id,
            'type' => 'family_member',
            'file_id_number' => $record->registration_id,
            'file_id' => $record->registration_id,

            // بيانات الهوية والاسم
            'data_id_number' => $record->person_id,
            'identity_number' => $record->person_id, // alias للتوافق
            'person_id' => $record->person_id,
            'first_name' => $record->first_name,
            'second_name' => $record->second_name,
            'third_name' => $record->third_name,
            'last_name' => $record->last_name,
            'data_first_name' => $record->first_name, // alias للتوافق
            'data_father_name' => $record->second_name, // alias للتوافق
            'data_grand_father_name' => $record->third_name, // alias للتوافق
            'data_family_name' => $record->last_name, // alias للتوافق
            'full_name' => $fullName,

            // البيانات الشخصية
            'person_birth_date' => $record->person_birth_date,
            'data_birth_date' => $record->person_birth_date, // alias للتوافق
            'birth_date' => $record->person_birth_date, // alias للتوافق
            'person_age' => $record->person_age,
            'age' => $record->person_age, // alias للتوافق
            'person_gender' => $gender,
            'gender' => $gender, // alias للتوافق

            // البيانات الإضافية
            'person_note' => $record->person_note,
            'notes' => $record->person_note, // alias للتوافق
            'acadimic_degree' => $record->acadimic_degree,
            'academic_degree' => $record->acadimic_degree, // alias للتوافق
            'education_level' => $record->acadimic_degree, // alias للتوافق

            // العلاقات والحالات
            'healthStatus' => $record->healthStatus,
            'health_status' => optional($record->healthStatus)->description,
            'health_status_name' => optional($record->healthStatus)->description,
            'health_status_id' => $record->person_health_status ?? null,

            'sponsorshipStatus' => $record->sponsorshipStatus,
            'sponsorship_status' => optional($record->sponsorshipStatus)->description,
            'sponsorship_status_name' => optional($record->sponsorshipStatus)->description,
            'sponsorship_status_id' => $record->sponsorship_status ?? null,

            'guaranteeType' => $record->guaranteeType,
            'guarantee_type' => optional($record->guaranteeType)->description,
            'guarantee_type_name' => optional($record->guaranteeType)->description,
            'guarantee_type_id' => $record->person_type_of_guarantee ?? null,

            // معلومات إضافية للتوافق مع النموذج الموحد
            'relationship' => 'فرد من الأسرة', // قيمة افتراضية
            'marital_status' => null, // غير متوفر في هذا الجدول
            'phone' => null, // غير متوفر في هذا الجدول
            'city' => null, // غير متوفر في هذا الجدول
            'section' => null, // غير متوفر في هذا الجدول

            // معلومات التوقيت
            'created_at' => $record->created_at ?? null,
            'updated_at' => $record->updated_at ?? null,
        ];
    }

    /**
     * تنسيق سجل متوفى - نسخة محسنة شاملة
     */
    private function formatDeceasedRecord($record): array
    {
        // تحضير بيانات الأب
        $fatherData = null;
        if ($record->father_first_name || $record->father_id) {
            $fatherFullName = trim("{$record->father_first_name} {$record->father_second_name} {$record->father_third_name} {$record->father_last_name}");

            // حساب العمر عند الوفاة إذا كان التاريخ متوفراً
            $fatherAgeAtDeath = null;
            if ($record->father_death_date && $record->father_birth_date) {
                try {
                    $birthDate = new \DateTime($record->father_birth_date);
                    $deathDate = new \DateTime($record->father_death_date);
                    $fatherAgeAtDeath = $deathDate->diff($birthDate)->y;
                } catch (\Exception $e) {
                    $fatherAgeAtDeath = null;
                }
            }

            $fatherData = [
                'identity_number' => $record->father_id,
                'first_name' => $record->father_first_name,
                'second_name' => $record->father_second_name,
                'third_name' => $record->father_third_name,
                'last_name' => $record->father_last_name,
                'full_name' => $fatherFullName,
                'death_date' => $record->father_death_date,
                'death_reason' => optional($record->fatherDeathReason)->description,
                'death_reason_id' => $record->father_death_reason,
                'death_certificate' => $record->father_death_certificate,
                'age_at_death' => $fatherAgeAtDeath,
                'gender' => 'ذكر',
            ];
        }

        // تحضير بيانات الأم
        $motherData = null;
        if ($record->mother_first_name || $record->mother_id) {
            $motherFullName = trim("{$record->mother_first_name} {$record->mother_second_name} {$record->mother_third_name} {$record->mother_last_name}");

            // حساب العمر عند الوفاة إذا كان التاريخ متوفراً
            $motherAgeAtDeath = null;
            if ($record->mother_death_date && $record->mother_birth_date) {
                try {
                    $birthDate = new \DateTime($record->mother_birth_date);
                    $deathDate = new \DateTime($record->mother_death_date);
                    $motherAgeAtDeath = $deathDate->diff($birthDate)->y;
                } catch (\Exception $e) {
                    $motherAgeAtDeath = null;
                }
            }

            $motherData = [
                'identity_number' => $record->mother_id,
                'first_name' => $record->mother_first_name,
                'second_name' => $record->mother_second_name,
                'third_name' => $record->mother_third_name,
                'last_name' => $record->mother_last_name,
                'full_name' => $motherFullName,
                'death_date' => $record->mother_death_date,
                'death_reason' => optional($record->motherDeathReason)->description,
                'death_reason_id' => $record->mother_death_reason,
                'death_certificate' => $record->mother_death_certificate,
                'age_at_death' => $motherAgeAtDeath,
                'gender' => 'أنثى',
            ];
        }

        // تحديد الاسم الكامل للعرض (أولوية للأب ثم الأم)
        $displayName = 'غير محدد';
        $primaryGender = 'غير محدد';
        $primaryIdentity = null;

        if ($fatherData && $fatherData['full_name']) {
            $displayName = $fatherData['full_name'];
            $primaryGender = 'ذكر';
            $primaryIdentity = $fatherData['identity_number'];
        } elseif ($motherData && $motherData['full_name']) {
            $displayName = $motherData['full_name'];
            $primaryGender = 'أنثى';
            $primaryIdentity = $motherData['identity_number'];
        }

        return [
            // البيانات الأساسية
            'id' => $record->re_file_id,
            'type' => 'deceased',
            'file_id' => $record->re_file_id,
            'file_id_number' => $record->re_file_id, // alias للتوافق

            // البيانات للعرض الموحد
            'full_name' => $displayName,
            'identity_number' => $primaryIdentity,
            'data_id_number' => $primaryIdentity, // alias للتوافق
            'gender' => $primaryGender,
            'relationship' => 'متوفى',

            // بيانات الأب
            'father' => $fatherData,
            'father_id' => $record->father_id,
            'father_first_name' => $record->father_first_name,
            'father_second_name' => $record->father_second_name,
            'father_third_name' => $record->father_third_name,
            'father_last_name' => $record->father_last_name,
            'father_death_date' => $record->father_death_date,
            'father_death_reason' => $record->father_death_reason,
            'father_death_certificate' => $record->father_death_certificate,

            // بيانات الأم
            'mother' => $motherData,
            'mother_id' => $record->mother_id,
            'mother_first_name' => $record->mother_first_name,
            'mother_second_name' => $record->mother_second_name,
            'mother_third_name' => $record->mother_third_name,
            'mother_last_name' => $record->mother_last_name,
            'mother_death_date' => $record->mother_death_date,
            'mother_death_reason' => $record->mother_death_reason,
            'mother_death_certificate' => $record->mother_death_certificate,

            // معلومات إضافية للتوافق
            'phone' => null, // غير متوفر في هذا الجدول
            'city' => null, // غير متوفر في هذا الجدول
            'section' => null, // غير متوفر في هذا الجدول
            'health_status' => 'متوفى',
            'age' => null, // يحتاج لحساب منفصل
            'birth_date' => null, // غير متوفر في هذا الجدول

            // معلومات التوقيت
            'created_at' => $record->created_at ?? null,
            'updated_at' => $record->updated_at ?? null,

            // ملاحظة حول نوع السجل
            'record_note' => 'سجل وفاة - يحتوي على بيانات الأب و/أو الأم المتوفين',
        ];
    }

    /**
     * الحصول على مفتاح الفلتر من اسم الحقل
     */
    private function getFilterKey(string $field): string
    {
        $mapping = [
            'file_id_number' => 'file_id',
            'data_id_number' => 'identity_number',
            'person_id' => 'identity_number',
            'data_phone_number' => 'phone_number',
            'registration_id' => 'file_id',
            're_file_id' => 'file_id',
            'father_id' => 'identity_number',
            'mother_id' => 'identity_number',
        ];

        return $mapping[$field] ?? $field;
    }

    /**
     * الحصول على حقل الجنس حسب الجدول
     */
    private function getGenderField(array $tableConfig): ?string
    {
        $model = $tableConfig['model'];

        if ($model === Data::class) {
            return 'data_gender';
        } elseif ($model === RePeople::class) {
            return 'person_gender';
        }

        return null;
    }

    /**
     * توليد مفتاح الكاش
     */
    private function generateCacheKey(array $filters): string
    {
        ksort($filters);
        return 'search_' . md5(serialize($filters));
    }

    /**
     * الحصول على إحصائيات البحث
     */
    public function getSearchStatistics(): array
    {
        $cacheKey = 'search_statistics';

        return Cache::remember($cacheKey, 60 * 60, function() {
            return [
                'main_records' => Data::count(),
                'family_members' => RePeople::count(),
                'deceased_records' => DeadPepole::count(),
                'last_updated' => now()->toDateTimeString()
            ];
        });
    }

    /**
     * مسح الكاش
     */
    public function clearSearchCache(): void
    {
        $tags = ['search_*', 'search_statistics'];
        foreach ($tags as $tag) {
            Cache::forget($tag);
        }
    }

    /**
     * اقتراحات البحث التلقائي
     */
    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        // استخدام الحد الأدنى المعرّف أو القيمة الافتراضية
        $minLength = $this->config['advanced_search']['autocomplete_min_length'] ?? 2;

        if (strlen($query) < $minLength) {
            return [];
        }

        $suggestions = [];

        // اقتراحات من الأسماء
        $nameSuggestions = Data::select(
                DB::raw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) as suggestion")
            )
            ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$query}%"])
            ->limit($limit)
            ->pluck('suggestion')
            ->toArray();

        $suggestions = array_merge($suggestions, $nameSuggestions);

        // اقتراحات من أرقام الهوية
        if (is_numeric($query)) {
            $idSuggestions = Data::select('data_id_number as suggestion')
                ->where('data_id_number', 'LIKE', "%{$query}%")
                ->limit($limit)
                ->pluck('suggestion')
                ->toArray();

            $suggestions = array_merge($suggestions, $idSuggestions);
        }

        return array_unique(array_slice($suggestions, 0, $limit));
    }
}
