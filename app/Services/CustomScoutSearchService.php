<?php

namespace App\Services;

use App\Models\PersonSearchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CustomScoutSearchService
{
    protected $cachePrefix = 'custom_scout_';
    protected $cacheTTL = 600; // 10 دقائق

    /**
     * بحث سريع مخصص للجدول الموجود - محسن للأداء الفائق
     */
    public function instantSearch(string $query, int $limit = 50): array
    {
        $startTime = microtime(true);

        // تنظيف النص وإعداد الكلمات
        $cleanQuery = trim($query);
        $words = array_filter(explode(' ', $cleanQuery), function($word) {
            return strlen(trim($word)) >= 2;
        });

        Log::info("Ultra Fast Search", [
            'query' => $query,
            'words_count' => count($words)
        ]);

        // استراتيجية بحث محسنة حسب عدد الكلمات
        if (count($words) === 1) {
            // بحث كلمة واحدة - أسرع طريقة
            $results = $this->singleWordSearch($cleanQuery, $limit);
        } elseif (count($words) <= 3) {
            // بحث 2-3 كلمات - محسن
            $results = $this->multiWordSearch($words, $limit);
        } else {
            // بحث متقدم للنصوص الطويلة
            $results = $this->advancedWordSearch($words, $limit);
        }

        $endTime = microtime(true);
        $searchTime = round(($endTime - $startTime) * 1000, 2);

        Log::info("Ultra Fast Search Results", [
            'query' => $query,
            'results_count' => count($results),
            'search_time_ms' => $searchTime
        ]);

        return [
            'results' => $results,
            'count' => count($results),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Custom Scout Ultra Fast',
            'cached' => false
        ];
    }

    /**
     * بحث كلمة واحدة - أسرع طريقة
     */
    private function singleWordSearch(string $word, int $limit): array
    {
        return DB::table('persons')
            ->where(function ($q) use ($word) {
                $q->where('CI_FIRST_ARB', 'LIKE', "{$word}%")
                  ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                  ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%")
                  ->orWhere('CI_ID_NUM', '=', $word);
            })
            ->orderByRaw("
                CASE
                    WHEN CI_ID_NUM = ? THEN 1
                    WHEN CI_FIRST_ARB LIKE ? THEN 2
                    WHEN CI_FATHER_ARB LIKE ? THEN 3
                    ELSE 4
                END ASC,
                ID DESC
            ", [$word, "{$word}%", "{$word}%"])
            ->limit($limit)
            ->get(['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY'])
            ->map([$this, 'mapPersonData'])
            ->toArray();
    }

    /**
     * بحث متعدد الكلمات (2-3 كلمات)
     */
    private function multiWordSearch(array $words, int $limit): array
    {
        $firstWord = $words[0] ?? '';
        $secondWord = $words[1] ?? '';
        $thirdWord = $words[2] ?? '';

        return DB::table('persons')
            ->where(function ($q) use ($words, $firstWord, $secondWord, $thirdWord) {
                // بحث بالكلمات كـ prefix في أماكن مختلفة
                if (count($words) === 2) {
                    $q->where(function($sub) use ($firstWord, $secondWord) {
                        $sub->where('CI_FIRST_ARB', 'LIKE', "{$firstWord}%")
                           ->where('CI_FATHER_ARB', 'LIKE', "{$secondWord}%");
                    })
                    ->orWhere(function($sub) use ($firstWord, $secondWord) {
                        $sub->where('CI_FIRST_ARB', 'LIKE', "{$firstWord}%")
                           ->where('CI_FAMILY_ARB', 'LIKE', "{$secondWord}%");
                    })
                    ->orWhere(function($sub) use ($firstWord, $secondWord) {
                        $sub->where('CI_FATHER_ARB', 'LIKE', "{$firstWord}%")
                           ->where('CI_FAMILY_ARB', 'LIKE', "{$secondWord}%");
                    });
                } else {
                    // 3 كلمات
                    $q->where(function($sub) use ($firstWord, $secondWord, $thirdWord) {
                        $sub->where('CI_FIRST_ARB', 'LIKE', "{$firstWord}%")
                           ->where('CI_FATHER_ARB', 'LIKE', "{$secondWord}%")
                           ->where('CI_FAMILY_ARB', 'LIKE', "{$thirdWord}%");
                    })
                    ->orWhere(function($sub) use ($firstWord, $secondWord, $thirdWord) {
                        $sub->where('CI_FIRST_ARB', 'LIKE', "{$firstWord}%")
                           ->where('CI_FATHER_ARB', 'LIKE', "{$secondWord}%")
                           ->where('CI_GRAND_FATHER_ARB', 'LIKE', "{$thirdWord}%");
                    });
                }

                // بحث احتياطي بكل كلمة منفصلة
                foreach ($words as $word) {
                    $q->orWhere('CI_FIRST_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%");
                }
            })
            ->orderByRaw("
                CASE
                    WHEN CI_FIRST_ARB LIKE ? THEN 1
                    WHEN CI_FATHER_ARB LIKE ? THEN 2
                    ELSE 3
                END ASC,
                ID DESC
            ", ["{$firstWord}%", "{$firstWord}%"])
            ->limit($limit)
            ->get(['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY'])
            ->map([$this, 'mapPersonData'])
            ->toArray();
    }

    /**
     * بحث متقدم للنصوص الطويلة
     */
    private function advancedWordSearch(array $words, int $limit): array
    {
        return DB::table('persons')
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('CI_FIRST_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%");
                }
            })
            ->orderBy('ID', 'DESC')
            ->limit($limit)
            ->get(['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY'])
            ->map([$this, 'mapPersonData'])
            ->toArray();
    }

    /**
     * تحويل بيانات الشخص إلى تنسيق موحد - محسن للأداء
     */
    public function mapPersonData($person): array
    {
        // تحسين تجميع الاسم الكامل بدون CONCAT
        $nameParts = array_filter([
            $person->CI_FIRST_ARB ?? '',
            $person->CI_FATHER_ARB ?? '',
            $person->CI_GRAND_FATHER_ARB ?? '',
            $person->CI_FAMILY_ARB ?? ''
        ]);

        return [
            'id' => $person->ID,
            'id_num' => $person->CI_ID_NUM ?? '',
            'full_name' => implode(' ', $nameParts),
            'first_name' => $person->CI_FIRST_ARB ?? '',
            'father_name' => $person->CI_FATHER_ARB ?? '',
            'grand_father_name' => $person->CI_GRAND_FATHER_ARB ?? '',
            'family_name' => $person->CI_FAMILY_ARB ?? '',
            'mother_name' => $person->MOTHER_NAME1 ?? '',
            'birth_date' => $person->CI_BIRTH_DT ?? '',
            'gender' => $this->getGenderText($person->CI_SEX_CD ?? 0),
            'city' => $person->CITY ?? '',
        ];
    }

    /**
     * تحويل رمز الجنس إلى نص
     */
    private function getGenderText($sexCode): string
    {
        switch ($sexCode) {
            case 1: return 'ذكر';
            case 2: return 'أنثى';
            default: return 'غير محدد';
        }
    }

    /**
     * البحث المتقدم مع فلاتر
     */
    public function advancedScoutSearch(array $filters, int $limit = 100): array
    {
        $cacheKey = $this->cachePrefix . 'advanced_' . md5(serialize($filters) . '_' . $limit);

        return Cache::remember($cacheKey, $this->cacheTTL, function() use ($filters, $limit) {
            $startTime = microtime(true);

            $query = DB::table('persons');

            // البحث النصي
            if (!empty($filters['query'])) {
                $searchTerm = $filters['query'];
                $query->where(function($q) use ($searchTerm) {
                    $q->where('CI_FIRST_ARB', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('CI_FATHER_ARB', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('CI_GRAND_FATHER_ARB', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('MOTHER_NAME1', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('CI_ID_NUM', 'LIKE', "%{$searchTerm}%");
                });
            }

            // فلتر الجنس
            if (!empty($filters['gender'])) {
                $query->where('CI_SEX_CD', $filters['gender']);
            }

            // فلتر سنة الميلاد
            if (!empty($filters['birth_year'])) {
                $query->whereYear('CI_BIRTH_DT', $filters['birth_year']);
            }

            // فلتر المدينة
            if (!empty($filters['city'])) {
                $query->where('CITY', $filters['city']);
            }

            $results = $query
                ->orderBy('ID', 'DESC')
                ->limit($limit)
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
                'engine' => 'Custom Scout Advanced',
                'cached' => false
            ];
        });
    }

    /**
     * اقتراحات سريعة
     */
    public function getSuggestions(string $query, int $limit = 10): array
    {
        $cacheKey = $this->cachePrefix . 'suggestions_' . md5($query . '_' . $limit);

        return Cache::remember($cacheKey, 300, function() use ($query, $limit) {
            $results = DB::table('persons')
                ->where(function($q) use ($query) {
                    $q->where('CI_FIRST_ARB', 'LIKE', "%{$query}%")
                      ->orWhere('CI_FATHER_ARB', 'LIKE', "%{$query}%")
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', "%{$query}%");
                })
                ->select('ID', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_ID_NUM')
                ->limit($limit)
                ->get()
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
     * إحصائيات النظام
     */
    public function getSearchStats(): array
    {
        return Cache::remember($this->cachePrefix . 'stats', 3600, function() {
            $totalRecords = DB::table('persons')->count();
            $indexedRecords = DB::table('persons')
                ->whereNotNull('CI_FIRST_ARB')
                ->whereNotNull('CI_ID_NUM')
                ->where('CI_FIRST_ARB', '!=', '')
                ->count();

            return [
                'total_records' => $totalRecords,
                'indexed_records' => $indexedRecords,
                'index_percentage' => $totalRecords > 0 ? round(($indexedRecords / $totalRecords) * 100, 2) : 0,
                'engine' => 'Custom Database Scout',
                'last_updated' => now()
            ];
        });
    }

    /**
     * البحث السريع بالفهارس المحسنة
     */
    public function ultraFastSearch(string $query, int $limit = 50): array
    {
        $cacheKey = $this->cachePrefix . 'ultrafast_' . md5($query . '_' . $limit);

        return Cache::remember($cacheKey, $this->cacheTTL, function() use ($query, $limit) {
            $startTime = microtime(true);

            // استخدام الفهارس FULLTEXT المحسنة
            $results = DB::select("
                (SELECT *, 'first_name' as match_type, 3 as relevance
                 FROM persons
                 WHERE MATCH(CI_FIRST_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, 'father_name' as match_type, 2 as relevance
                 FROM persons
                 WHERE MATCH(CI_FATHER_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, 'family_name' as match_type, 2 as relevance
                 FROM persons
                 WHERE MATCH(CI_FAMILY_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                ORDER BY relevance DESC, ID DESC
                LIMIT ?
            ", [
                "+{$query}", $limit,
                "+{$query}", $limit,
                "+{$query}", $limit,
                $limit
            ]);

            $formattedResults = collect($results)->map(function ($person) {
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
                    'match_type' => $person->match_type,
                    'relevance' => $person->relevance
                ];
            })->toArray();

            $endTime = microtime(true);
            $searchTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'results' => $formattedResults,
                'count' => count($formattedResults),
                'search_time' => $searchTime,
                'query' => $query,
                'engine' => 'Ultra Fast FULLTEXT',
                'cached' => false
            ];
        });
    }

    /**
     * مسح الكاش
     */
    public function clearCache(): bool
    {
        try {
            // مسح جميع مفاتيح الكاش المتعلقة بالبحث
            Cache::flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * فهرسة البيانات (محاكاة للتوافق مع Scout)
     */
    public function indexData(int $batchSize = 1000): array
    {
        $startTime = microtime(true);

        // في هذه الحالة لا نحتاج فهرسة فعلية لأننا نستخدم البحث المباشر
        // لكن يمكننا تحسين الفهارس الموجودة
        try {
            DB::statement('ANALYZE TABLE persons');
            $totalRecords = DB::table('persons')->count();

            $endTime = microtime(true);
            $indexTime = round($endTime - $startTime, 2);

            return [
                'total_records' => $totalRecords,
                'processed' => $totalRecords,
                'batches' => 1,
                'batch_size' => $batchSize,
                'index_time' => $indexTime,
                'success' => true,
                'message' => 'تم تحديث إحصائيات الجدول بنجاح'
            ];
        } catch (\Exception $e) {
            return [
                'total_records' => 0,
                'processed' => 0,
                'batches' => 0,
                'batch_size' => $batchSize,
                'index_time' => 0,
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage()
            ];
        }
    }
}
