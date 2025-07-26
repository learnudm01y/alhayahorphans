<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خدمة البحث الدقيق - للمطابقة التامة
 * Exact Match Search Service - For Precise Matching
 */
class ExactMatchSearchService
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
     * البحث الدقيق - مع ترتيب حسب المطابقة
     */
    public function search(string $query, int $limit = 20): array
    {
        $startTime = microtime(true);

        $cleanQuery = trim($query);
        if (empty($cleanQuery) || strlen($cleanQuery) < 2) {
            return $this->emptyResult($query, 0);
        }

        $words = array_filter(explode(' ', $cleanQuery), fn($w) => strlen(trim($w)) >= 2);
        $wordCount = count($words);

        if ($wordCount === 1) {
            $results = $this->singleWordExactSearch($words[0], $limit);
        } elseif ($wordCount === 2) {
            $results = $this->twoWordsExactSearch($words[0], $words[1], $limit);
        } elseif ($wordCount === 3) {
            $results = $this->threeWordsExactSearch($words[0], $words[1], $words[2], $limit);
        } elseif ($wordCount === 4) {
            $results = $this->fourWordsExactSearch($words[0], $words[1], $words[2], $words[3], $limit);
        } else {
            $results = $this->multiWordExactSearch($words, $limit);
        }

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'results' => $results,
            'count' => count($results),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Exact Match',
            'cached' => false
        ];
    }

    /**
     * بحث كلمة واحدة - مطابقة دقيقة أولاً
     */
    private function singleWordExactSearch(string $word, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY,
                       CASE
                           WHEN CI_FIRST_ARB = :exact_word THEN 1
                           WHEN CI_FATHER_ARB = :exact_word2 THEN 2
                           WHEN CI_FAMILY_ARB = :exact_word3 THEN 3
                           WHEN CI_FIRST_ARB LIKE :prefix_word THEN 4
                           WHEN CI_FATHER_ARB LIKE :prefix_word2 THEN 5
                           WHEN CI_FAMILY_ARB LIKE :prefix_word3 THEN 6
                           ELSE 7
                       END as match_priority
                FROM persons
                WHERE CI_FIRST_ARB = :exact_word4
                   OR CI_FATHER_ARB = :exact_word5
                   OR CI_FAMILY_ARB = :exact_word6
                   OR CI_FIRST_ARB LIKE :prefix_word4
                   OR CI_FATHER_ARB LIKE :prefix_word5
                   OR CI_FAMILY_ARB LIKE :prefix_word6
                ORDER BY match_priority ASC, ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':exact_word', $word, \PDO::PARAM_STR);
        $stmt->bindValue(':exact_word2', $word, \PDO::PARAM_STR);
        $stmt->bindValue(':exact_word3', $word, \PDO::PARAM_STR);
        $stmt->bindValue(':exact_word4', $word, \PDO::PARAM_STR);
        $stmt->bindValue(':exact_word5', $word, \PDO::PARAM_STR);
        $stmt->bindValue(':exact_word6', $word, \PDO::PARAM_STR);
        $stmt->bindValue(':prefix_word', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':prefix_word2', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':prefix_word3', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':prefix_word4', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':prefix_word5', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':prefix_word6', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث كلمتين - مطابقة دقيقة
     */
    private function twoWordsExactSearch(string $word1, string $word2, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY,
                       CASE
                           WHEN CI_FIRST_ARB = :word1_exact AND CI_FATHER_ARB = :word2_exact THEN 1
                           WHEN CI_FIRST_ARB = :word1_exact2 AND CI_FAMILY_ARB = :word2_exact2 THEN 2
                           WHEN CI_FATHER_ARB = :word1_exact3 AND CI_FAMILY_ARB = :word2_exact3 THEN 3
                           WHEN CI_FIRST_ARB LIKE :word1_prefix AND CI_FATHER_ARB LIKE :word2_prefix THEN 4
                           WHEN CI_FIRST_ARB LIKE :word1_prefix2 AND CI_FAMILY_ARB LIKE :word2_prefix2 THEN 5
                           ELSE 6
                       END as match_priority
                FROM persons
                WHERE (CI_FIRST_ARB = :word1_exact4 AND CI_FATHER_ARB = :word2_exact4)
                   OR (CI_FIRST_ARB = :word1_exact5 AND CI_FAMILY_ARB = :word2_exact5)
                   OR (CI_FATHER_ARB = :word1_exact6 AND CI_FAMILY_ARB = :word2_exact6)
                   OR (CI_FIRST_ARB LIKE :word1_prefix3 AND CI_FATHER_ARB LIKE :word2_prefix3)
                   OR (CI_FIRST_ARB LIKE :word1_prefix4 AND CI_FAMILY_ARB LIKE :word2_prefix4)
                ORDER BY match_priority ASC, ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);

        // Exact matches
        $stmt->bindValue(':word1_exact', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':word2_exact', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':word1_exact2', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':word2_exact2', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':word1_exact3', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':word2_exact3', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':word1_exact4', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':word2_exact4', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':word1_exact5', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':word2_exact5', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':word1_exact6', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':word2_exact6', $word2, \PDO::PARAM_STR);

        // Prefix matches
        $stmt->bindValue(':word1_prefix', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word2_prefix', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word1_prefix2', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word2_prefix2', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word1_prefix3', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word2_prefix3', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word1_prefix4', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word2_prefix4', "{$word2}%", \PDO::PARAM_STR);

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث ثلاث كلمات - مطابقة دقيقة
     */
    private function threeWordsExactSearch(string $word1, string $word2, string $word3, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY,
                       CASE
                           WHEN CI_FIRST_ARB = :w1_exact AND CI_FATHER_ARB = :w2_exact AND CI_FAMILY_ARB = :w3_exact THEN 1
                           WHEN CI_FIRST_ARB = :w1_exact2 AND CI_FATHER_ARB = :w2_exact2 AND CI_GRAND_FATHER_ARB = :w3_exact2 THEN 2
                           WHEN CI_FIRST_ARB LIKE :w1_prefix AND CI_FATHER_ARB LIKE :w2_prefix AND CI_FAMILY_ARB LIKE :w3_prefix THEN 3
                           ELSE 4
                       END as match_priority
                FROM persons
                WHERE (CI_FIRST_ARB = :w1_exact3 AND CI_FATHER_ARB = :w2_exact3 AND CI_FAMILY_ARB = :w3_exact3)
                   OR (CI_FIRST_ARB = :w1_exact4 AND CI_FATHER_ARB = :w2_exact4 AND CI_GRAND_FATHER_ARB = :w3_exact4)
                   OR (CI_FIRST_ARB LIKE :w1_prefix2 AND CI_FATHER_ARB LIKE :w2_prefix2 AND CI_FAMILY_ARB LIKE :w3_prefix2)
                ORDER BY match_priority ASC, ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);

        // Exact matches
        $stmt->bindValue(':w1_exact', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':w2_exact', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':w3_exact', $word3, \PDO::PARAM_STR);
        $stmt->bindValue(':w1_exact2', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':w2_exact2', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':w3_exact2', $word3, \PDO::PARAM_STR);
        $stmt->bindValue(':w1_exact3', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':w2_exact3', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':w3_exact3', $word3, \PDO::PARAM_STR);
        $stmt->bindValue(':w1_exact4', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':w2_exact4', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':w3_exact4', $word3, \PDO::PARAM_STR);

        // Prefix matches
        $stmt->bindValue(':w1_prefix', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w2_prefix', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w3_prefix', "{$word3}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w1_prefix2', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w2_prefix2', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w3_prefix2', "{$word3}%", \PDO::PARAM_STR);

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث أربع كلمات - مطابقة دقيقة
     */
    private function fourWordsExactSearch(string $word1, string $word2, string $word3, string $word4, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY,
                       CASE
                           WHEN CI_FIRST_ARB = :w1 AND CI_FATHER_ARB = :w2 AND CI_GRAND_FATHER_ARB = :w3 AND CI_FAMILY_ARB = :w4 THEN 1
                           WHEN CI_FIRST_ARB LIKE :w1p AND CI_FATHER_ARB LIKE :w2p AND CI_GRAND_FATHER_ARB LIKE :w3p AND CI_FAMILY_ARB LIKE :w4p THEN 2
                           ELSE 3
                       END as match_priority
                FROM persons
                WHERE (CI_FIRST_ARB = :w1_2 AND CI_FATHER_ARB = :w2_2 AND CI_GRAND_FATHER_ARB = :w3_2 AND CI_FAMILY_ARB = :w4_2)
                   OR (CI_FIRST_ARB LIKE :w1p_2 AND CI_FATHER_ARB LIKE :w2p_2 AND CI_GRAND_FATHER_ARB LIKE :w3p_2 AND CI_FAMILY_ARB LIKE :w4p_2)
                ORDER BY match_priority ASC, ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);

        // Exact matches
        $stmt->bindValue(':w1', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':w2', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':w3', $word3, \PDO::PARAM_STR);
        $stmt->bindValue(':w4', $word4, \PDO::PARAM_STR);
        $stmt->bindValue(':w1_2', $word1, \PDO::PARAM_STR);
        $stmt->bindValue(':w2_2', $word2, \PDO::PARAM_STR);
        $stmt->bindValue(':w3_2', $word3, \PDO::PARAM_STR);
        $stmt->bindValue(':w4_2', $word4, \PDO::PARAM_STR);

        // Prefix matches
        $stmt->bindValue(':w1p', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w2p', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w3p', "{$word3}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w4p', "{$word4}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w1p_2', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w2p_2', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w3p_2', "{$word3}%", \PDO::PARAM_STR);
        $stmt->bindValue(':w4p_2', "{$word4}%", \PDO::PARAM_STR);

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث متعدد الكلمات - للنصوص الطويلة
     */
    private function multiWordExactSearch(array $words, int $limit): array
    {
        // للنصوص الطويلة، نستخدم بحث أبسط
        $conditions = [];
        $params = [];

        foreach ($words as $index => $word) {
            $param = ":word{$index}";
            $conditions[] = "(CI_FIRST_ARB LIKE {$param} OR CI_FATHER_ARB LIKE {$param} OR CI_GRAND_FATHER_ARB LIKE {$param} OR CI_FAMILY_ARB LIKE {$param})";
            $params[$param] = "{$word}%";
        }

        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
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
            'match_priority' => $p['match_priority'] ?? 99
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
            'engine' => 'Exact Match',
            'cached' => false
        ];
    }
}
