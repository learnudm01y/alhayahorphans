<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * خدمة بحث فائقة السرعة - تستخدم PDO مباشرة
 * Lightning Fast Search Service - Using Direct PDO
 */
class LightningSearchService
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
     * البحث الفائق - مباشرة في قاعدة البيانات
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
            $results = $this->singleWordSearchPDO($words[0], $limit);
        } elseif ($wordCount === 2) {
            $results = $this->twoWordsSearchPDO($words[0], $words[1], $limit);
        } elseif ($wordCount === 3) {
            $results = $this->threeWordsSearchPDO($words[0], $words[1], $words[2], $limit);
        } else {
            $results = $this->multiWordSearchPDO($words, $limit);
        }

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'results' => $results,
            'count' => count($results),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Lightning PDO',
            'cached' => false
        ];
    }

    /**
     * بحث كلمة واحدة - PDO مباشر
     */
    private function singleWordSearchPDO(string $word, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE CI_FIRST_ARB LIKE :word1
                   OR CI_FATHER_ARB LIKE :word2
                   OR CI_FAMILY_ARB LIKE :word3
                ORDER BY ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':word1', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word2', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word3', "{$word}%", \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث كلمتين - PDO محسن
     */
    private function twoWordsSearchPDO(string $word1, string $word2, int $limit): array
    {
        // أولاً: بحث دقيق (الاسم الأول + الأب)
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE CI_FIRST_ARB LIKE :word1 AND CI_FATHER_ARB LIKE :word2
                ORDER BY ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':word1', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word2', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll();

        // إذا لم نجد نتائج، جرب بحث أوسع
        if (count($results) < $limit) {
            $sql2 = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE (CI_FIRST_ARB LIKE :word1 AND CI_FAMILY_ARB LIKE :word2)
                       OR (CI_FATHER_ARB LIKE :word1 AND CI_FAMILY_ARB LIKE :word2)
                    ORDER BY ID DESC
                    LIMIT :remaining";

            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->bindValue(':word1', "{$word1}%", \PDO::PARAM_STR);
            $stmt2->bindValue(':word2', "{$word2}%", \PDO::PARAM_STR);
            $stmt2->bindValue(':remaining', $limit - count($results), \PDO::PARAM_INT);
            $stmt2->execute();

            $results = array_merge($results, $stmt2->fetchAll());
        }

        return array_map([$this, 'mapPersonArray'], $results);
    }

    /**
     * بحث ثلاث كلمات - PDO سريع
     */
    private function threeWordsSearchPDO(string $word1, string $word2, string $word3, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE CI_FIRST_ARB LIKE :word1
                  AND CI_FATHER_ARB LIKE :word2
                  AND CI_FAMILY_ARB LIKE :word3
                ORDER BY ID DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':word1', "{$word1}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word2', "{$word2}%", \PDO::PARAM_STR);
        $stmt->bindValue(':word3', "{$word3}%", \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث متعدد الكلمات - PDO
     */
    private function multiWordSearchPDO(array $words, int $limit): array
    {
        $conditions = [];
        $params = [];

        foreach ($words as $index => $word) {
            $param = ":word{$index}";
            $conditions[] = "(CI_FIRST_ARB LIKE {$param} OR CI_FATHER_ARB LIKE {$param} OR CI_FAMILY_ARB LIKE {$param})";
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
            'engine' => 'Lightning PDO',
            'cached' => false
        ];
    }
}
