<?php
/**
 * اختبار البحث الذكي مباشرة
 */

require_once __DIR__ . '/app/Services/SmartExactSearchService.php';

use App\Services\SmartExactSearchService;

echo "=== اختبار البحث الذكي مباشرة ===\n";

try {
    $service = new SmartExactSearchService();
    $result = $service->search("ايمان عبد الرحمن سليمان ابداح", 3);

    echo "🎯 النتيجة:\n";
    echo "Count: " . $result['count'] . "\n";
    echo "Time: " . $result['search_time'] . " ms\n";
    echo "Engine: " . $result['engine'] . "\n\n";

    foreach ($result['results'] as $person) {
        echo "✅ " . $person['full_name'] . " (ID: " . $person['id'] . ")\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "في السطر: " . $e->getLine() . "\n";
    echo "في الملف: " . $e->getFile() . "\n";
}

echo "\n=== انتهى الاختبار ===\n";
