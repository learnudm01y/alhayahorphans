<?php

namespace App\Services;

use App\Models\PersonSearchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class ScoutSearchService
{
    protected $cachePrefix = 'scout_search_';
    protected $cacheTTL = 600; // 10 دقائق للبحث السريع

    /**
     * بحث سريع بتقنية Scout
     */
    public function instantSearch(string $query, int $limit = 50): array
    {
        $cacheKey = $this->cachePrefix . 'instant_' . md5($query . '_' . $limit);

        return Cache::remember($cacheKey, $this->cacheTTL, function() use ($query, $limit) {
            $startTime = microtime(true);

            // بحث سريع مع Scout
            $results = PersonSearchable::search($query)
                ->take($limit)
                ->get()
                ->map(function ($person) {
                    return [
                        'id' => $person->ID,
                        'id_num' => $person->CI_ID_NUM,
                        'full_name' => trim(($person->CI_FIRST_ARB ?? '') . ' ' . ($person->CI_FATHER_ARB ?? '') . ' ' . ($person->CI_GRAND_FATHER_ARB ?? '') . ' ' . ($person->CI_FAMILY_ARB ?? '')),
                        'first_name' => $person->CI_FIRST_ARB,
                        'father_name' => $person->CI_FATHER_ARB,
                        'grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                        'family_name' => $person->CI_FAMILY_ARB,
                        'mother_name' => $person->MOTHER_NAME1,
                        'birth_date' => $person->CI_BIRTH_DT,
                        'gender' => $person->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى',
                        'city' => $person->CITY,
                    ];
                })
                ->toArray();

            $endTime = microtime(true);
            $searchTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'results' => $results,
                'count' => count($results),
                'search_time' => $searchTime,
                'query' => $query,
                'engine' => 'Scout',
                'cached' => false
            ];
        });
    }

    /**
     * البحث المتقدم بفلاتر مع Scout
     */
    public function advancedScoutSearch(array $filters, int $limit = 100): array
    {
        $cacheKey = $this->cachePrefix . 'advanced_' . md5(serialize($filters) . '_' . $limit);

        return Cache::remember($cacheKey, $this->cacheTTL, function() use ($filters, $limit) {
            $startTime = microtime(true);

            $searchQuery = PersonSearchable::search($filters['query'] ?? '');

            // تطبيق الفلاتر
            if (!empty($filters['gender'])) {
                $searchQuery = $searchQuery->where('CI_SEX_CD', $filters['gender']);
            }

            if (!empty($filters['birth_year'])) {
                $searchQuery = $searchQuery->where('birth_year', $filters['birth_year']);
            }

            if (!empty($filters['city'])) {
                $searchQuery = $searchQuery->where('CITY', $filters['city']);
            }

            $results = $searchQuery
                ->take($limit)
                ->get()
                ->map(function ($person) {
                    return [
                        'id' => $person->ID,
                        'id_num' => $person->CI_ID_NUM,
                        'full_name' => trim(($person->CI_FIRST_ARB ?? '') . ' ' . ($person->CI_FATHER_ARB ?? '') . ' ' . ($person->CI_GRAND_FATHER_ARB ?? '') . ' ' . ($person->CI_FAMILY_ARB ?? '')),
                        'first_name' => $person->CI_FIRST_ARB,
                        'father_name' => $person->CI_FATHER_ARB,
                        'grand_father_name' => $person->CI_GRAND_FATHER_ARB,
                        'family_name' => $person->CI_FAMILY_ARB,
                        'mother_name' => $person->MOTHER_NAME1,
                        'birth_date' => $person->CI_BIRTH_DT,
                        'gender' => $person->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى',
                        'city' => $person->CITY,
                    ];
                })
                ->toArray();

            $endTime = microtime(true);
            $searchTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'results' => $results,
                'count' => count($results),
                'search_time' => $searchTime,
                'filters' => $filters,
                'engine' => 'Scout Advanced',
                'cached' => false
            ];
        });
    }

    /**
     * اقتراحات سريعة للكتابة التلقائية
     */
    public function getSuggestions(string $query, int $limit = 10): array
    {
        $cacheKey = $this->cachePrefix . 'suggestions_' . md5($query . '_' . $limit);

        return Cache::remember($cacheKey, 300, function() use ($query, $limit) { // 5 دقائق للاقتراحات
            $results = PersonSearchable::search($query)
                ->take($limit)
                ->get(['ID', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_ID_NUM'])
                ->map(function ($person) {
                    $fullName = trim(($person->CI_FIRST_ARB ?? '') . ' ' . ($person->CI_FATHER_ARB ?? '') . ' ' . ($person->CI_FAMILY_ARB ?? ''));
                    return [
                        'id' => $person->ID,
                        'text' => $fullName,
                        'id_num' => $person->CI_ID_NUM,
                        'type' => 'person'
                    ];
                })
                ->filter(function ($item) {
                    return !empty($item['text']);
                })
                ->unique('text')
                ->values()
                ->toArray();

            return $results;
        });
    }

    /**
     * إحصائيات البحث
     */
    public function getSearchStats(): array
    {
        return Cache::remember($this->cachePrefix . 'stats', 3600, function() { // ساعة واحدة
            $totalRecords = PersonSearchable::count();
            $indexedRecords = PersonSearchable::whereNotNull('CI_FIRST_ARB')
                ->whereNotNull('CI_ID_NUM')
                ->count();

            return [
                'total_records' => $totalRecords,
                'indexed_records' => $indexedRecords,
                'index_percentage' => $totalRecords > 0 ? round(($indexedRecords / $totalRecords) * 100, 2) : 0,
                'engine' => config('scout.driver', 'database'),
                'last_updated' => now()->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * مسح الكاش
     */
    public function clearCache(): bool
    {
        $keys = [
            $this->cachePrefix . '*'
        ];

        foreach ($keys as $pattern) {
            $cacheKeys = Cache::getRedis()->keys($pattern);
            if (!empty($cacheKeys)) {
                Cache::getRedis()->del($cacheKeys);
            }
        }

        return true;
    }

    /**
     * فهرسة البيانات (للاستخدام عند الحاجة)
     */
    public function indexData(int $batchSize = 1000): array
    {
        $startTime = microtime(true);

        // فهرسة البيانات على دفعات
        $totalRecords = PersonSearchable::whereNotNull('CI_FIRST_ARB')
            ->whereNotNull('CI_ID_NUM')
            ->count();

        $batches = ceil($totalRecords / $batchSize);
        $processed = 0;

        for ($i = 0; $i < $batches; $i++) {
            $offset = $i * $batchSize;
            $records = PersonSearchable::whereNotNull('CI_FIRST_ARB')
                ->whereNotNull('CI_ID_NUM')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            // فهرسة الدفعة
            $records->searchable();
            $processed += $records->count();
        }

        $endTime = microtime(true);
        $indexTime = round($endTime - $startTime, 2);

        return [
            'total_records' => $totalRecords,
            'processed' => $processed,
            'batches' => $batches,
            'batch_size' => $batchSize,
            'index_time' => $indexTime,
            'success' => true
        ];
    }
}
