<?php

namespace App\Services;

use App\Models\CivilRegistryPerson;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CivilRegistryScoutSearchService
{
    protected $cachePrefix = 'civil_scout_search_';
    protected $cacheTimeout = 300; // 5 دقائق
    protected $normalizedSearchService;

    public function __construct(NormalizedSearchService $normalizedSearchService)
    {
        $this->normalizedSearchService = $normalizedSearchService;
    }

    /**
     * البحث السريع باستخدام Scout في قاعدة بيانات السجل المدني مع التطبيع
     */
    public function quickScoutSearch(string $query, int $limit = 50): array
    {
        $cacheKey = $this->cachePrefix . 'quick_v3_normalized_' . md5($query . $limit);

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($query, $limit, $cacheKey) {
            $startTime = microtime(true);

            try {
                // إذا كان البحث رقمي، ابحث في رقم الهوية فقط (بدون تطبيع)
                if (is_numeric($query)) {
                    $queryBuilder = DB::connection('civilregistry')->table('persons');

                    if (strlen($query) >= 9) {
                        // رقم هوية كامل أو شبه كامل
                        $results = $queryBuilder->where('CI_ID_NUM', $query)->limit($limit)->get();

                        // إذا لم نجد مطابقة تامة، ابحث جزئياً
                        if ($results->isEmpty()) {
                            $results = $queryBuilder->where('CI_ID_NUM', 'LIKE', $query . '%')->limit($limit)->get();
                        }
                    } else {
                        // بحث جزئي في رقم الهوية
                        $results = $queryBuilder->where('CI_ID_NUM', 'LIKE', $query . '%')->limit($limit)->get();
                    }
                } else {
                    // البحث النصي في الأسماء - مع التطبيع
                    $results = $this->normalizedSearchService->searchCivilRegistry($query, $limit);
                }

                $endTime = microtime(true);

                return [
                    'success' => true,
                    'data' => $results->toArray(),
                    'total_count' => $results->count(),
                    'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                    'engine' => 'Civil Registry with Arabic Normalization v3',
                    'cache_key' => $cacheKey,
                    'search_query' => $query,
                    'limit' => $limit,
                    'query_type' => is_numeric($query) ? 'numeric' : 'text_normalized'
                ];

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => [],
                    'total_count' => 0,
                    'execution_time' => '0 ms',
                    'engine' => 'Civil Registry Direct DB (Error)'
                ];
            }
        });
    }

    /**
     * البحث المتقدم مع معايير متعددة
     */
    public function advancedScoutSearch(array $filters, int $limit = 100): array
    {
        $cacheKey = $this->cachePrefix . 'advanced_' . md5(serialize($filters) . $limit);

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($filters, $limit, $cacheKey) {
            $startTime = microtime(true);

            try {
                $searchQuery = $filters['search_text'] ?? '';

                // بناء استعلام البحث
                $query = CivilRegistryPerson::search($searchQuery);

                // إضافة المرشحات
                if (!empty($filters['gender'])) {
                    $query->where('CI_SEX_CD', $filters['gender']);
                }

                if (!empty($filters['birth_year'])) {
                    $query->where('birth_year', $filters['birth_year']);
                }

                if (!empty($filters['city'])) {
                    $query->where('CITY', $filters['city']);
                }

                if (isset($filters['is_alive'])) {
                    $query->where('is_alive', (bool)$filters['is_alive']);
                }

                $results = $query->take($limit)->get();

                $endTime = microtime(true);

                return [
                    'success' => true,
                    'data' => $results->toArray(),
                    'total_count' => $results->count(),
                    'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                    'engine' => 'Civil Registry Scout Advanced',
                    'filters_applied' => $filters,
                    'cache_key' => $cacheKey
                ];

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => [],
                    'total_count' => 0,
                    'execution_time' => '0 ms',
                    'engine' => 'Civil Registry Scout Advanced (Error)',
                    'filters_applied' => $filters
                ];
            }
        });
    }

    /**
     * البحث برقم الهوية (دقيق جداً)
     */
    public function searchByIdNumber(string $idNumber): array
    {
        $cacheKey = $this->cachePrefix . 'id_' . md5($idNumber);

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($idNumber) {
            $startTime = microtime(true);

            try {
                // البحث المباشر باستخدام فهرس رقم الهوية
                $result = DB::connection('civilregistry')
                    ->table('persons')
                    ->where('CI_ID_NUM', $idNumber)
                    ->first();

                $endTime = microtime(true);

                return [
                    'success' => true,
                    'data' => $result ? [$result] : [],
                    'total_count' => $result ? 1 : 0,
                    'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                    'engine' => 'Civil Registry Direct Index',
                    'search_id' => $idNumber
                ];

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => [],
                    'total_count' => 0,
                    'execution_time' => '0 ms',
                    'engine' => 'Civil Registry Direct Index (Error)'
                ];
            }
        });
    }

    /**
     * البحث بالاسم الكامل
     */
    public function searchByFullName(string $fullName, int $limit = 50): array
    {
        $cacheKey = $this->cachePrefix . 'name_' . md5($fullName . $limit);

        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($fullName, $limit) {
            $startTime = microtime(true);

            try {
                $results = CivilRegistryPerson::searchByFullName($fullName, $limit);

                $endTime = microtime(true);

                return [
                    'success' => true,
                    'data' => $results->toArray(),
                    'total_count' => $results->count(),
                    'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                    'engine' => 'Civil Registry Scout Name Search',
                    'search_name' => $fullName
                ];

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => [],
                    'total_count' => 0,
                    'execution_time' => '0 ms',
                    'engine' => 'Civil Registry Scout Name Search (Error)'
                ];
            }
        });
    }

    /**
     * البحث الشامل (يجمع كل أنواع البحث)
     */
    public function comprehensiveSearch(array $criteria): array
    {
        $startTime = microtime(true);
        $results = [
            'main_records' => [],
            'by_id_results' => [],
            'by_name_results' => [],
            'total_count' => 0
        ];

        try {
            // البحث الرئيسي
            if (!empty($criteria['search_text'])) {
                $mainSearch = $this->quickScoutSearch($criteria['search_text'], 100);
                if ($mainSearch['success']) {
                    $results['main_records'] = $mainSearch['data'];
                }
            }

            // البحث برقم الهوية إذا كان النص يشبه رقم هوية
            if (!empty($criteria['search_text']) && is_numeric($criteria['search_text'])) {
                $idSearch = $this->searchByIdNumber($criteria['search_text']);
                if ($idSearch['success'] && !empty($idSearch['data'])) {
                    $results['by_id_results'] = $idSearch['data'];
                }
            }

            // البحث بالاسم إذا كان النص يحتوي على مسافات (اسم مركب)
            if (!empty($criteria['search_text']) && str_contains($criteria['search_text'], ' ')) {
                $nameSearch = $this->searchByFullName($criteria['search_text'], 50);
                if ($nameSearch['success']) {
                    $results['by_name_results'] = $nameSearch['data'];
                }
            }

            // حساب العدد الإجمالي
            $results['total_count'] = count($results['main_records']) +
                                     count($results['by_id_results']) +
                                     count($results['by_name_results']);

            $endTime = microtime(true);

            return [
                'success' => true,
                'data' => $results,
                'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                'engine' => 'Civil Registry Scout Comprehensive Search',
                'search_criteria' => $criteria
            ];

        } catch (\Exception $e) {
            $endTime = microtime(true);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => $results,
                'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                'engine' => 'Civil Registry Scout Comprehensive Search (Error)'
            ];
        }
    }

    /**
     * إحصائيات قاعدة البيانات
     */
    public function getDatabaseStats(): array
    {
        $cacheKey = $this->cachePrefix . 'db_stats';

        return Cache::remember($cacheKey, 3600, function () { // كاش لمدة ساعة
            try {
                // التحقق من الاتصال بقاعدة البيانات
                $totalRecords = DB::connection('civilregistry')->table('persons')->count();
                $aliveRecords = DB::connection('civilregistry')->table('persons')->whereNull('CI_DEAD_DT')->count();
                $maleRecords = DB::connection('civilregistry')->table('persons')->where('CI_SEX_CD', 1)->count();
                $femaleRecords = DB::connection('civilregistry')->table('persons')->where('CI_SEX_CD', 2)->count();

                return [
                    'success' => true,
                    'total_records' => $totalRecords,
                    'alive_records' => $aliveRecords,
                    'deceased_records' => $totalRecords - $aliveRecords,
                    'male_records' => $maleRecords,
                    'female_records' => $femaleRecords,
                    'connection_status' => 'Connected',
                    'database' => 'civilregistry',
                    'table' => 'persons'
                ];

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'connection_status' => 'Failed',
                    'database' => 'civilregistry',
                    'table' => 'persons'
                ];
            }
        });
    }

    /**
     * فهرسة البيانات لمحرك Scout
     */
    public function indexData(int $chunkSize = 1000): array
    {
        $startTime = microtime(true);

        try {
            $stats = $this->getDatabaseStats();

            if (!$stats['success']) {
                throw new \Exception('فشل في الاتصال بقاعدة البيانات: ' . $stats['error']);
            }

            $totalRecords = $stats['total_records'];
            $processedRecords = 0;

            // فهرسة البيانات على دفعات
            CivilRegistryPerson::chunk($chunkSize, function ($persons) use (&$processedRecords) {
                foreach ($persons as $person) {
                    if ($person->shouldBeSearchable()) {
                        $person->searchable();
                        $processedRecords++;
                    }
                }
            });

            $endTime = microtime(true);

            return [
                'success' => true,
                'total_records' => $totalRecords,
                'processed_records' => $processedRecords,
                'skipped_records' => $totalRecords - $processedRecords,
                'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                'chunk_size' => $chunkSize
            ];

        } catch (\Exception $e) {
            $endTime = microtime(true);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms'
            ];
        }
    }

    /**
     * مسح الكاش
     */
    public function clearCache(): bool
    {
        try {
            $tags = ['civil_scout_search'];
            Cache::tags($tags)->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * اختبار الاتصال والبحث
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);

        try {
            // اختبار الاتصال
            $stats = $this->getDatabaseStats();

            if (!$stats['success']) {
                throw new \Exception('فشل في الاتصال بقاعدة البيانات');
            }

            // اختبار البحث البسيط
            $testSearch = $this->quickScoutSearch('محمد', 5);

            $endTime = microtime(true);

            return [
                'success' => true,
                'connection_test' => $stats,
                'search_test' => $testSearch,
                'total_execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                'status' => 'Civil Registry Scout Service is working properly'
            ];

        } catch (\Exception $e) {
            $endTime = microtime(true);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'total_execution_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                'status' => 'Civil Registry Scout Service failed'
            ];
        }
    }
}
