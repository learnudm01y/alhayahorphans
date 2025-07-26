<?php
/**
 * اختبار الأداء الفائق للـ API المحسن
 * Test Ultra Fast API Performance
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== اختبار الأداء الفائق للـ API المحسن ===\n";
echo "Testing Ultra Fast API Performance\n";
echo "================================================\n\n";

// تعريف الاختبارات
$tests = [
    [
        'name' => 'بحث كلمة واحدة - Single Word Search',
        'query' => 'محمد',
        'expected_time' => 50 // أقل من 50ms
    ],
    [
        'name' => 'بحث كلمتين - Two Words Search',
        'query' => 'محمد أحمد',
        'expected_time' => 100 // أقل من 100ms
    ],
    [
        'name' => 'بحث ثلاث كلمات - Three Words Search',
        'query' => 'محمد أحمد علي',
        'expected_time' => 150 // أقل من 150ms
    ],
    [
        'name' => 'بحث رقم هوية - ID Number Search',
        'query' => '12345',
        'expected_time' => 30 // أقل من 30ms
    ],
    [
        'name' => 'بحث طويل - Long Search',
        'query' => 'محمد أحمد علي السعدي',
        'expected_time' => 200 // أقل من 200ms
    ]
];

$results = [];
$totalTests = 0;
$passedTests = 0;

foreach ($tests as $test) {
    echo "🧪 " . $test['name'] . "\n";
    echo "Query: " . $test['query'] . "\n";

    $totalTests++;

    try {
        // قياس زمن API
        $apiStartTime = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/ASO/ASO%20-%20Copy/api/search?q=' . urlencode($test['query']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $apiEndTime = microtime(true);
        $apiTime = round(($apiEndTime - $apiStartTime) * 1000, 2);

        if ($curlError) {
            echo "❌ cURL Error: " . $curlError . "\n";
            continue;
        }

        if ($httpCode !== 200) {
            echo "❌ HTTP Error: " . $httpCode . "\n";
            continue;
        }

        $data = json_decode($response, true);

        if (!$data) {
            echo "❌ JSON Error: Invalid response\n";
            continue;
        }

        // النتائج
        echo "📊 النتائج / Results:\n";
        echo "   - Results Count: " . ($data['count'] ?? 0) . "\n";
        echo "   - Database Time: " . ($data['search_time'] ?? 'N/A') . " ms\n";
        echo "   - Total API Time: " . $apiTime . " ms\n";
        echo "   - Expected Time: < " . $test['expected_time'] . " ms\n";

        // تقييم النتيجة
        if ($apiTime <= $test['expected_time']) {
            echo "✅ PASS - Performance Target Met!\n";
            $passedTests++;
        } else {
            echo "⚠️  WARNING - Performance Target Missed\n";
        }

        $results[] = [
            'test' => $test['name'],
            'query' => $test['query'],
            'api_time' => $apiTime,
            'db_time' => $data['search_time'] ?? 0,
            'count' => $data['count'] ?? 0,
            'expected_time' => $test['expected_time'],
            'passed' => $apiTime <= $test['expected_time']
        ];

    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
}

// تقرير شامل
echo "=== تقرير الأداء النهائي / Final Performance Report ===\n\n";

echo "📈 Statistics:\n";
echo "   - Total Tests: " . $totalTests . "\n";
echo "   - Passed Tests: " . $passedTests . "\n";
echo "   - Failed Tests: " . ($totalTests - $passedTests) . "\n";
echo "   - Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

echo "📋 Detailed Results:\n";
foreach ($results as $result) {
    $status = $result['passed'] ? '✅ PASS' : '❌ FAIL';
    echo "   {$status} {$result['test']}: {$result['api_time']}ms (DB: {$result['db_time']}ms, Count: {$result['count']})\n";
}

echo "\n=== Performance Analysis ===\n";

$avgApiTime = array_sum(array_column($results, 'api_time')) / count($results);
$avgDbTime = array_sum(array_column($results, 'db_time')) / count($results);

echo "📊 Average Times:\n";
echo "   - Average API Time: " . round($avgApiTime, 2) . " ms\n";
echo "   - Average DB Time: " . round($avgDbTime, 2) . " ms\n";
echo "   - Framework Overhead: " . round($avgApiTime - $avgDbTime, 2) . " ms\n\n";

if ($avgApiTime < 100) {
    echo "🎉 EXCELLENT! API performance is under 100ms average\n";
} elseif ($avgApiTime < 500) {
    echo "✅ GOOD! API performance is acceptable\n";
} else {
    echo "⚠️  WARNING! API performance needs optimization\n";
}

echo "\n=== Next Steps ===\n";
if ($passedTests < $totalTests) {
    echo "🔧 Optimization needed for failing tests\n";
    echo "🚀 Consider implementing caching for complex queries\n";
    echo "📊 Profile Laravel framework overhead\n";
} else {
    echo "🎯 All performance targets met!\n";
    echo "🚀 Ready for production deployment\n";
}

echo "\n=== Test Completed ===\n";
