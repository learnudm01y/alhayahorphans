<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * خدمة بحث سريعة جداً - مبسطة للحد الأقصى من الأداء
 * Ultra Fast Search Service - Simplified for Maximum Performance
 */
class UltraFastSearchService
{
    /**
     * بحث سريع جداً - مخصص للإنتاج
     */
    public function search(string $query, int $limit = 20): array
    {
        $startTime = microtime(true);

        // تنظيف النص
        $cleanQuery = trim($query);
        if (empty($cleanQuery) || strlen($cleanQuery) < 2) {
            return $this->emptyResult($query, 0);
        }

        $words = array_filter(explode(' ', $cleanQuery), fn($w) => strlen(trim($w)) >= 2);
        $wordCount = count($words);

        // استراتيجية بحث حسب عدد الكلمات
        if ($wordCount === 1) {
            $results = $this->singleWordSearch($cleanQuery, $limit);
        } elseif ($wordCount === 2) {
            $results = $this->twoWordsSearch($words[0], $words[1], $limit);
        } elseif ($wordCount === 3) {
            $results = $this->threeWordsSearch($words[0], $words[1], $words[2], $limit);
        } else {
            $results = $this->multiWordSearch($words, $limit);
        }

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'results' => $results,
            'count' => count($results),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Ultra Fast',
            'cached' => false
        ];
    }

    /**
     * بحث كلمة واحدة - أسرع طريقة ممكنة (بدون ORDER BY معقد)
     */
    private function singleWordSearch(string $word, int $limit): array
    {
        return DB::table('persons')
            ->select('ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY')
            ->where(function ($q) use ($word) {
                // بحث في رقم الهوية أولاً (الأسرع)
                if (is_numeric($word)) {
                    $q->where('CI_ID_NUM', 'LIKE', "{$word}%");
                }
                // ثم بحث في الأسماء
                $q->orWhere('CI_FIRST_ARB', 'LIKE', "{$word}%")
                  ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                  ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%");
            })
            ->orderBy('ID', 'DESC') // ترتيب بسيط بدلاً من CASE المعقد
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->mapPerson($p))
            ->toArray();
    }

    /**
     * بحث كلمتين - محسن
     */
    private function twoWordsSearch(string $word1, string $word2, int $limit): array
    {
        return DB::table('persons')
            ->select('ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY')
            ->where(function ($q) use ($word1, $word2) {
                // الاسم الأول + اسم الأب
                $q->where(function($sub) use ($word1, $word2) {
                    $sub->where('CI_FIRST_ARB', 'LIKE', "{$word1}%")
                       ->where('CI_FATHER_ARB', 'LIKE', "{$word2}%");
                })
                // الاسم الأول + اسم العائلة
                ->orWhere(function($sub) use ($word1, $word2) {
                    $sub->where('CI_FIRST_ARB', 'LIKE', "{$word1}%")
                       ->where('CI_FAMILY_ARB', 'LIKE', "{$word2}%");
                })
                // اسم الأب + اسم العائلة
                ->orWhere(function($sub) use ($word1, $word2) {
                    $sub->where('CI_FATHER_ARB', 'LIKE', "{$word1}%")
                       ->where('CI_FAMILY_ARB', 'LIKE', "{$word2}%");
                });
            })
            ->orderBy('ID', 'DESC')
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->mapPerson($p))
            ->toArray();
    }

    /**
     * بحث ثلاث كلمات - محسن للسرعة القصوى
     */
    private function threeWordsSearch(string $word1, string $word2, string $word3, int $limit): array
    {
        return DB::table('persons')
            ->select('ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY')
            ->where('CI_FIRST_ARB', 'LIKE', "{$word1}%")
            ->where('CI_FATHER_ARB', 'LIKE', "{$word2}%")
            ->where('CI_FAMILY_ARB', 'LIKE', "{$word3}%")
            ->orderBy('ID', 'DESC') // ترتيب بسيط
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->mapPerson($p))
            ->toArray();
    }

    /**
     * بحث متعدد الكلمات - للنصوص الطويلة
     */
    private function multiWordSearch(array $words, int $limit): array
    {
        return DB::table('persons')
            ->select('ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY')
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where(function($sub) use ($word) {
                        $sub->where('CI_FIRST_ARB', 'LIKE', "{$word}%")
                           ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                           ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%");
                    });
                }
            })
            ->orderBy('ID', 'DESC')
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->mapPerson($p))
            ->toArray();
    }

    /**
     * تحويل بيانات الشخص - مبسط جداً
     */
    private function mapPerson($p): array
    {
        return [
            'id' => $p->ID,
            'id_num' => $p->CI_ID_NUM ?: '',
            'full_name' => trim(($p->CI_FIRST_ARB ?: '') . ' ' . ($p->CI_FATHER_ARB ?: '') . ' ' . ($p->CI_GRAND_FATHER_ARB ?: '') . ' ' . ($p->CI_FAMILY_ARB ?: '')),
            'first_name' => $p->CI_FIRST_ARB ?: '',
            'father_name' => $p->CI_FATHER_ARB ?: '',
            'grand_father_name' => $p->CI_GRAND_FATHER_ARB ?: '',
            'family_name' => $p->CI_FAMILY_ARB ?: '',
            'mother_name' => $p->MOTHER_NAME1 ?: '',
            'birth_date' => $p->CI_BIRTH_DT ?: '',
            'gender' => $p->CI_SEX_CD == 1 ? 'ذكر' : ($p->CI_SEX_CD == 2 ? 'أنثى' : ''),
            'city' => $p->CITY ?: '',
        ];
    }

    /**
     * نتيجة فارغة
     */
    private function emptyResult(string $query, float $time): array
    {
        return [
            'results' => [],
            'count' => 0,
            'search_time' => $time,
            'query' => $query,
            'engine' => 'Ultra Fast',
            'cached' => false
        ];
    }
}
