<?php

namespace App\Services;

/**
 * خدمة البحث الدقيق البسيط - للمطابقة التامة فقط
 * Simple Exact Search Service - For Perfect Match Only
 */
class SimpleExactSearchService
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
     * البحث الدقيق البسيط
     */
    public function search(string $query, int $limit = 10): array
    {
        $startTime = microtime(true);

        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return $this->emptyResult($query, 0);
        }

        // البحث بالاسم الكامل أولاً
        $exactResults = $this->exactFullNameSearch($cleanQuery, $limit);

        // إذا لم نجد، نجرب البحث بالكلمات
        if (empty($exactResults)) {
            $exactResults = $this->wordBasedSearch($cleanQuery, $limit);
        }

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'results' => $exactResults,
            'count' => count($exactResults),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Simple Exact Match',
            'cached' => false
        ];
    }

    /**
     * البحث بالاسم الكامل الدقيق
     */
    private function exactFullNameSearch(string $query, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
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

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * البحث بالكلمات المنفصلة
     */
    private function wordBasedSearch(string $query, int $limit): array
    {
        $words = array_filter(explode(' ', $query));
        $wordCount = count($words);

        if ($wordCount == 1) {
            return $this->singleWordSearch($words[0], $limit);
        } elseif ($wordCount == 2) {
            return $this->twoWordsSearch($words[0], $words[1], $limit);
        } elseif ($wordCount == 3) {
            return $this->threeWordsSearch($words[0], $words[1], $words[2], $limit);
        } elseif ($wordCount == 4) {
            return $this->fourWordsSearch($words[0], $words[1], $words[2], $words[3], $limit);
        } elseif ($wordCount == 5) {
            // حالة خاصة: "ايمان عبد الرحمن سليمان ابداح"
            // جرب: ايمان | عبد الرحمن | سليمان | ابداح
            return $this->fourWordsSearch($words[0], $words[1] . ' ' . $words[2], $words[3], $words[4], $limit);
        }

        return [];
    }

    /**
     * بحث كلمة واحدة
     */
    private function singleWordSearch(string $word, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE CI_FIRST_ARB = ?
                ORDER BY ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$word, $limit]);

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث كلمتين
     */
    private function twoWordsSearch(string $w1, string $w2, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ?
                ORDER BY ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$w1, $w2, $limit]);

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث ثلاث كلمات
     */
    private function threeWordsSearch(string $w1, string $w2, string $w3, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ?
                ORDER BY ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$w1, $w2, $w3, $limit]);

        return array_map([$this, 'mapPersonArray'], $stmt->fetchAll());
    }

    /**
     * بحث أربع كلمات
     */
    private function fourWordsSearch(string $w1, string $w2, string $w3, string $w4, int $limit): array
    {
        $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                       MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                FROM persons
                WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?
                ORDER BY ID DESC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$w1, $w2, $w3, $w4, $limit]);

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
            'engine' => 'Simple Exact Match',
            'cached' => false
        ];
    }
}
