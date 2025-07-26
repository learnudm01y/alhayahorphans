<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Exact Search Only
|--------------------------------------------------------------------------
*/

// البحث الدقيق الوحيد - لإرجاع نتيجة واحدة فقط
Route::get('/search/exact-only', function (Request $request) {
    $query = $request->get('q', '');
    $limit = min($request->get('limit', 1), 1); // حد أقصى نتيجة واحدة

    $startTime = microtime(true);

    if (empty($query)) {
        return response()->json([
            'success' => false,
            'message' => 'يرجى إدخال اسم للبحث',
            'results' => [],
            'count' => 0,
            'search_time' => 0,
            'query' => $query,
            'engine' => 'Exact Only Search'
        ]);
    }

    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=aso;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // تحليل الاسم إلى أجزاء
        $words = array_filter(explode(' ', trim($query)));
        $wordCount = count($words);

        $results = [];

        // البحث الدقيق حسب عدد الكلمات
        if ($wordCount == 5) {
            // 5 كلمات: اسم + عبد + الرحمن + جد + عائلة
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1] . ' ' . $words[2], $words[3], $words[4]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 4) {
            // 4 كلمات: اسم + أب + جد + عائلة
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ? AND CI_FAMILY_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1], $words[2], $words[3]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 3) {
            // 3 كلمات: اسم + أب + جد
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ? AND CI_GRAND_FATHER_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1], $words[2]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 2) {
            // كلمتان: اسم + أب
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ? AND CI_FATHER_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0], $words[1]]);
            $results = $stmt->fetchAll();

        } elseif ($wordCount == 1) {
            // كلمة واحدة: الاسم الأول فقط
            $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB,
                           MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
                    FROM persons
                    WHERE CI_FIRST_ARB = ?
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$words[0]]);
            $results = $stmt->fetchAll();
        }

        // تحويل النتائج
        $mappedResults = [];
        if (!empty($results)) {
            $person = $results[0]; // نتيجة واحدة فقط
            $mappedResults[] = [
                'id' => $person['ID'],
                'id_num' => $person['CI_ID_NUM'] ?: 'غير محدد',
                'full_name' => trim(($person['CI_FIRST_ARB'] ?: '') . ' ' .
                                  ($person['CI_FATHER_ARB'] ?: '') . ' ' .
                                  ($person['CI_GRAND_FATHER_ARB'] ?: '') . ' ' .
                                  ($person['CI_FAMILY_ARB'] ?: '')),
                'first_name' => $person['CI_FIRST_ARB'] ?: 'غير محدد',
                'father_name' => $person['CI_FATHER_ARB'] ?: 'غير محدد',
                'grand_father_name' => $person['CI_GRAND_FATHER_ARB'] ?: 'غير محدد',
                'family_name' => $person['CI_FAMILY_ARB'] ?: 'غير محدد',
                'mother_name' => $person['MOTHER_NAME1'] ?: 'غير محدد',
                'birth_date' => $person['CI_BIRTH_DT'] ?: 'غير محدد',
                'gender' => $person['CI_SEX_CD'] == 1 ? 'ذكر' : ($person['CI_SEX_CD'] == 2 ? 'أنثى' : 'غير محدد'),
                'city' => $person['CITY'] ?: 'غير محدد',
            ];
        }

        $searchTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'success' => count($mappedResults) > 0,
            'message' => count($mappedResults) > 0 ? 'تم العثور على مطابقة دقيقة' : 'لم يتم العثور على مطابقة دقيقة',
            'results' => $mappedResults,
            'count' => count($mappedResults),
            'search_time' => $searchTime,
            'query' => $query,
            'engine' => 'Exact Only Search',
            'word_count' => $wordCount,
            'search_strategy' => $wordCount . ' كلمات - بحث دقيق تماماً'
        ]);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ في البحث: ' . $e->getMessage(),
            'results' => [],
            'count' => 0,
            'search_time' => round((microtime(true) - $startTime) * 1000, 2),
            'query' => $query,
            'engine' => 'Exact Only Search'
        ], 500);
    }
});

// البحث الدقيق بمعاينة - لمقارنة النتائج
Route::get('/search/preview-comparison', function (Request $request) {
    $query = $request->get('q', '');

    if (empty($query)) {
        return response()->json(['error' => 'يرجى إدخال اسم للبحث']);
    }

    $results = [];

    // جرب البحث الدقيق الجديد
    $exactUrl = url("/api/search/exact-only?q=" . urlencode($query));
    $exactResponse = @file_get_contents($exactUrl);
    if ($exactResponse) {
        $results['exact_only'] = json_decode($exactResponse, true);
    }

    // جرب البحث العادي (للمقارنة)
    $normalUrl = url("/api/search/final-exact?q=" . urlencode($query) . "&limit=10");
    $normalResponse = @file_get_contents($normalUrl);
    if ($normalResponse) {
        $results['normal_search'] = json_decode($normalResponse, true);
    }

    return response()->json([
        'query' => $query,
        'comparison' => $results,
        'recommendation' => 'استخدم exact-only للحصول على نتيجة واحدة دقيقة فقط'
    ]);
});
