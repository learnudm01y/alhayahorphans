<?php
/**
 * اختبار البحث البسيط مباشرة
 */

require_once __DIR__ . '/app/Services/SimpleExactSearchService.php';

use App\Services\SimpleExactSearchService;

echo "=== اختبار البحث البسيط مباشرة ===\n";

try {
    $service = new SimpleExactSearchService();
    $result = $service->search("ايمان عبد الرحمن سليمان ابداح", 3);

    echo "🎯 النتيجة:\n";
    echo "Count: " . $result['count'] . "\n";
    echo "Time: " . $result['search_time'] . " ms\n";
    echo "Engine: " . $result['engine'] . "\n\n";

    if ($result['count'] > 0) {
        echo "📋 النتائج:\n";
        foreach ($result['results'] as $person) {
            echo "✅ " . $person['full_name'] . " (ID: " . $person['id'] . ")\n";
        }
    } else {
        echo "❌ لم يتم العثور على نتائج\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "في السطر: " . $e->getLine() . "\n";
    echo "في الملف: " . $e->getFile() . "\n";
}

echo "\n=== انتهى الاختبار ===\n";
