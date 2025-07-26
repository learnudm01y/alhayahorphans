<?php
/**
 * تحليل مشكلة الأداء في Laravel
 * Laravel Performance Issue Analysis
 */

echo "=== تحليل مشكلة الأداء في Laravel ===\n";
echo "Laravel Performance Issue Analysis\n";
echo "===================================\n\n";

// اختبار الاتصال المباشر بقاعدة البيانات
echo "1. اختبار الاتصال المباشر بقاعدة البيانات:\n";
echo "Direct Database Connection Test:\n\n";

try {
    $startTime = microtime(true);

    $pdo = new PDO(
        'mysql:host=localhost;dbname=aso;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]
    );

    $query = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
              FROM persons
              WHERE CI_FIRST_ARB LIKE 'محمد%'
                 OR CI_FATHER_ARB LIKE 'محمد%'
                 OR CI_FAMILY_ARB LIKE 'محمد%'
                 OR CI_ID_NUM LIKE 'محمد%'
              ORDER BY
                CASE
                    WHEN CI_ID_NUM LIKE 'محمد%' THEN 1
                    WHEN CI_FIRST_ARB LIKE 'محمد%' THEN 2
                    ELSE 3
                END ASC,
                ID DESC
              LIMIT 10";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll();

    $endTime = microtime(true);
    $queryTime = round(($endTime - $startTime) * 1000, 2);

    echo "✅ Direct PDO Query Results:\n";
    echo "   - Results Count: " . count($results) . "\n";
    echo "   - Query Time: " . $queryTime . " ms\n";
    echo "   - First Result: " . ($results[0]['CI_FIRST_ARB'] ?? 'N/A') . "\n\n";

} catch (Exception $e) {
    echo "❌ PDO Error: " . $e->getMessage() . "\n\n";
}

// اختبار بدون Laravel
echo "2. اختبار Laravel Eloquent:\n";
echo "Laravel Eloquent Test:\n\n";

try {
    // تحميل Laravel
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

    $startTime = microtime(true);

    $results = \Illuminate\Support\Facades\DB::table('persons')
        ->select('ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'MOTHER_NAME1', 'CI_BIRTH_DT', 'CI_SEX_CD', 'CITY')
        ->where(function ($q) {
            $q->where('CI_FIRST_ARB', 'LIKE', 'محمد%')
              ->orWhere('CI_FATHER_ARB', 'LIKE', 'محمد%')
              ->orWhere('CI_FAMILY_ARB', 'LIKE', 'محمد%')
              ->orWhere('CI_ID_NUM', 'LIKE', 'محمد%');
        })
        ->orderByRaw("
            CASE
                WHEN CI_ID_NUM LIKE 'محمد%' THEN 1
                WHEN CI_FIRST_ARB LIKE 'محمد%' THEN 2
                ELSE 3
            END ASC,
            ID DESC
        ")
        ->limit(10)
        ->get();

    $endTime = microtime(true);
    $eloquentTime = round(($endTime - $startTime) * 1000, 2);

    echo "✅ Laravel Eloquent Results:\n";
    echo "   - Results Count: " . count($results) . "\n";
    echo "   - Query Time: " . $eloquentTime . " ms\n";
    echo "   - First Result: " . ($results[0]->CI_FIRST_ARB ?? 'N/A') . "\n\n";

} catch (Exception $e) {
    echo "❌ Laravel Error: " . $e->getMessage() . "\n\n";
}

// اختبار تحويل البيانات
echo "3. اختبار تحويل البيانات:\n";
echo "Data Transformation Test:\n\n";

if (isset($results) && count($results) > 0) {
    $startTime = microtime(true);

    $mappedResults = [];
    foreach ($results as $person) {
        $mappedResults[] = [
            'id' => $person->ID,
            'id_num' => $person->CI_ID_NUM ?: '',
            'full_name' => trim(($person->CI_FIRST_ARB ?: '') . ' ' . ($person->CI_FATHER_ARB ?: '') . ' ' . ($person->CI_GRAND_FATHER_ARB ?: '') . ' ' . ($person->CI_FAMILY_ARB ?: '')),
            'first_name' => $person->CI_FIRST_ARB ?: '',
            'father_name' => $person->CI_FATHER_ARB ?: '',
            'grand_father_name' => $person->CI_GRAND_FATHER_ARB ?: '',
            'family_name' => $person->CI_FAMILY_ARB ?: '',
            'mother_name' => $person->MOTHER_NAME1 ?: '',
            'birth_date' => $person->CI_BIRTH_DT ?: '',
            'gender' => $person->CI_SEX_CD == 1 ? 'ذكر' : ($person->CI_SEX_CD == 2 ? 'أنثى' : ''),
            'city' => $person->CITY ?: '',
        ];
    }

    $endTime = microtime(true);
    $transformTime = round(($endTime - $startTime) * 1000, 2);

    echo "✅ Data Transformation Results:\n";
    echo "   - Mapped Results: " . count($mappedResults) . "\n";
    echo "   - Transform Time: " . $transformTime . " ms\n\n";
}

// تحليل النتائج
echo "=== تحليل النتائج ===\n";
echo "Results Analysis:\n\n";

if (isset($queryTime, $eloquentTime)) {
    echo "📊 Performance Comparison:\n";
    echo "   - Direct PDO: " . $queryTime . " ms\n";
    echo "   - Laravel Eloquent: " . $eloquentTime . " ms\n";
    echo "   - Laravel Overhead: " . round($eloquentTime - $queryTime, 2) . " ms\n\n";

    if ($eloquentTime > 1000) {
        echo "🚨 CRITICAL: Laravel query time exceeds 1 second!\n";
        echo "🔍 Possible causes:\n";
        echo "   - Laravel debugging is enabled\n";
        echo "   - Query logging is consuming resources\n";
        echo "   - Middleware is adding overhead\n";
        echo "   - Database connection pooling issues\n\n";
    } elseif ($eloquentTime > 100) {
        echo "⚠️ WARNING: Laravel query time is high\n";
        echo "🔍 Recommendations:\n";
        echo "   - Disable debug mode in production\n";
        echo "   - Optimize database connection settings\n";
        echo "   - Consider caching frequently used queries\n\n";
    } else {
        echo "✅ GOOD: Laravel performance is acceptable\n\n";
    }
}

echo "=== التوصيات ===\n";
echo "Recommendations:\n\n";
echo "1. 🚀 استخدم PDO المباشر للبحثات السريعة\n";
echo "   Use direct PDO for ultra-fast searches\n\n";
echo "2. 🎯 قم بتعطيل debug mode في الإنتاج\n";
echo "   Disable debug mode in production\n\n";
echo "3. 📊 استخدم caching للبحثات المتكررة\n";
echo "   Use caching for frequent searches\n\n";
echo "4. ⚡ قم بتحسين إعدادات قاعدة البيانات\n";
echo "   Optimize database connection settings\n\n";

echo "=== Analysis Complete ===\n";
