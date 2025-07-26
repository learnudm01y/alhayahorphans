<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * خدمة البحث الذكي الدقيق - تتعامل مع الأسماء المركبة
 * Smart Exact Match Search Service - Handles Compound Names
 */
class SmartExactSearchService
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = new \PDO(
            'mysql:host=localhost;dbname=aso;charset=utf8mb4',
            'root',
            '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ]
        );
    }

    /**
     * البحث الذكي الدقيق - يفهم تركيب الأسماء العربية
     */
    public function search(string $query, int $limit = 10): array
    {
        $startTime = microtime(true);

        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return $this->emptyResult($query, 0);
        }

        // تجربة طرق مختلفة للبحث
        $results = [];

        // 1. البحث بالاسم الكامل كما هو
        $fullNameResults = $this->searchFullName($cleanQuery, $limit);

        // 2. البحث الذكي بالكلمات
        $smartResults = $this->smartWordSearch($cleanQuery, $limit);

        // 3. البحث المرن
        $flexibleResults = $this->flexibleSearch($cleanQuery, $limit);

        // دمج النتائج وترتيبها حسب الأولوية
        $allResults = array_merge($fullNameResults, $smartResults, $flexibleResults);

        // إزالة المكررات وترتيب حسب الأولوية
        $uniqueResults = $this->removeDuplicatesAndRank($allResults, $cleanQuery);

        // أخذ أفضل النتائج
        $finalResults = array_slice($uniqueResults, 0, $limit);

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'results' => $finalResults,
            'count' => count($finalResults),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Smart Exact Match',
            'cached' => false
        ];
    }

    /**
     * البحث بالاسم الكامل مع CONCAT
     */
    private function searchFullName(string $query, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY, 1 as search_priority
                FROM persons
                WHERE CONCAT(
                    COALESCE(CI_FIRST_ARB, ''), ' ',
                    COALESCE(CI_FATHER_ARB, ''), ' ',
                    COALESCE(CI_GRAND_FATHER_ARB, ''), ' ',
                    COALESCE(CI_FAMILY_ARB, '')
                ) = ?
                ORDER BY ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$query, $limit]);

        return $stmt->fetchAll();
    }

    /**
     * البحث الذكي - يجرب ترتيبات مختلفة للكلمات
     */
    private function smartWordSearch(string $query, int $limit): array
    {
        $words = array_filter(explode(' ', $query));
        $wordCount = count($words);

        if ($wordCount < 2) {
            return $this->singleWordSearch($words[0] ?? '', $limit);
        }

        $results = [];

        // جرب ترتيبات مختلفة
        if ($wordCount >= 4) {
            // ترتيب 1: كلمة - كلمة - كلمة - كلمة
            $results = array_merge($results, $this->tryFourWords($words[0], $words[1], $words[2], $words[3], $limit));

            // ترتيب 2: كلمة - كلمتين مدمجتين - كلمة - كلمة
            if ($wordCount >= 5) {
                $combinedSecond = $words[1] . ' ' . $words[2];
                $results = array_merge($results, $this->tryFourWords($words[0], $combinedSecond, $words[3], $words[4], $limit));
            }

            // ترتيب 3: كلمة - كلمة - كلمتين مدمجتين - كلمة
            if ($wordCount >= 5) {
                $combinedThird = $words[2] . ' ' . $words[3];
                $results = array_merge($results, $this->tryFourWords($words[0], $words[1], $combinedThird, $words[4], $limit));
            }
        }

        return $results;
    }

    /**
     * تجربة البحث بأربع كلمات
     */
    private function tryFourWords(string $w1, string $w2, string $w3, string $w4, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY,
                       CASE
                           WHEN CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ? THEN 2
                           ELSE 3
                       END as search_priority
                FROM persons
                WHERE (CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?)
                   OR (CI_FIRST_ARB LIKE ? AND CI_FATHER_ARB LIKE ? AND CI_GRAND_FATHER_ARB LIKE ? AND CI_FAMILY_ARB LIKE ?)
                ORDER BY search_priority ASC, ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $w1, $w2, $w3, $w4,  // for CASE calculation
            $w1, $w2, $w3, $w4,  // for first WHERE condition
            "{$w1}%", "{$w2}%", "{$w3}%", "{$w4}%",  // for second WHERE condition
            $limit
        ]);

        return $stmt->fetchAll();
    }

    /**
     * البحث بكلمة واحدة
     */
    private function singleWordSearch(string $word, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY, 5 as search_priority
                FROM persons
                WHERE CI_FIRST_ARB = ?
                   OR CI_FATHER_ARB = ?
                   OR CI_GRAND_FATHER_ARB = ?
                   OR CI_FAMILY_ARB = ?
                ORDER BY ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$word, $word, $word, $word, $limit]);

        return $stmt->fetchAll();
    }

    /**
     * البحث المرن - للحالات المعقدة
     */
    private function flexibleSearch(string $query, int $limit): array
    {
        $words = array_filter(explode(' ', $query));

        if (count($words) < 2) {
            return [];
        }

        // البحث بكل الكلمات في أي ترتيب
        $conditions = [];
        $params = [];

        foreach ($words as $index => $word) {
            $param = ":word{$index}";
            $conditions[] = "(CI_FIRST_ARB LIKE {$param} OR CI_FATHER_ARB LIKE {$param} OR CI_GRAND_FATHER_ARB LIKE {$param} OR CI_FAMILY_ARB LIKE {$param})";
            $params[$param] = "%{$word}%";
        }

        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY, 6 as search_priority
                FROM persons
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * إزالة المكررات وترتيب حسب الأولوية
     */
    private function removeDuplicatesAndRank(array $results, string $query): array
    {
        $unique = [];

        foreach ($results as $result) {
            $id = $result['ID'];

            if (!isset($unique[$id])) {
                $unique[$id] = $this->mapPersonArray($result);

                // حساب درجة الصلة
                $unique[$id]['relevance_score'] = $this->calculateRelevance($result, $query);
            }
        }

        // ترتيب حسب درجة الصلة
        usort($unique, function($a, $b) {
            return $a['relevance_score'] <=> $b['relevance_score'];
        });

        return array_values($unique);
    }

    /**
     * حساب درجة الصلة
     */
    private function calculateRelevance(array $result, string $query): int
    {
        $fullName = trim(($result['CI_FIRST_ARB'] ?: '') . ' ' . ($result['CI_FATHER_ARB'] ?: '') . ' ' . ($result['CI_GRAND_FATHER_ARB'] ?: '') . ' ' . ($result['CI_FAMILY_ARB'] ?: ''));

        // المطابقة التامة لها أقل score (أفضل)
        if (strtolower($fullName) === strtolower($query)) {
            return 1;
        }

        // استخدام search_priority إذا كان متاحاً
        $priority = $result['search_priority'] ?? 10;

        return $priority;
    }

    /**
     * تحويل مصفوفة إلى كائن
     */
    public function mapPersonArray(array $p): array
    {
        return [
            'id' => $p['ID'],
            'id_num' => $p['CI_ID_NUM'] ?: '',
            'full_name' => trim(($p['CI_FIRST_ARB'] ?: '') . ' ' . ($p['CI_FATHER_ARB'] ?: '') . ' ' . ($p['CI_GRAND_FATHER_ARB'] ?: '') . ' ' . ($p['CI_FAMILY_ARB'] ?: '')),
            'first_name' => $p['CI_FIRST_ARB'] ?: '',
            'father_name' => $p['CI_FATHER_ARB'] ?: '',
            'grand_father_name' => $p['CI_GRAND_FATHER_ARB'] ?: '',
            'family_name' => $p['CI_FAMILY_ARB'] ?: '',
            'mother_name' => $p['MOTHER_NAME1'] ?: '',
            'birth_date' => $p['CI_BIRTH_DT'] ?: '',
            'gender' => $p['CI_SEX_CD'] == 1 ? 'ذكر' : ($p['CI_SEX_CD'] == 2 ? 'أنثى' : ''),
            'city' => $p['CITY'] ?: '',
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
            'engine' => 'Smart Exact Match',
            'cached' => false
        ];
    }
}
