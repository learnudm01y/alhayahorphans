<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::connection('civilregistry')->enableQueryLog();

use App\Services\NormalizedSearchService;
$searchService = new NormalizedSearchService();

echo "\n=== تتبع البحث بالاسم الكامل ===\n\n";

$searchTerm = 'نصرالله عبد الناصر رفيق الفرا';
echo "البحث عن: {$searchTerm}\n";

$normalized = normalizeArabicText($searchTerm);
echo "بعد التطبيع: {$normalized}\n";

$words = preg_split('/\s+/', trim($normalized));
echo "عدد الكلمات: " . count($words) . "\n";
echo "الكلمات: " . json_encode($words, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "تنفيذ البحث...\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry($searchTerm);
$duration = round((microtime(true) - $start) * 1000, 2);
echo "الوقت: {$duration} ms\n";
echo "عدد النتائج: " . count($results) . "\n\n";

$queries = DB::connection('civilregistry')->getQueryLog();
echo "الاستعلامات المنفذة:\n\n";

foreach ($queries as $i => $query) {
    echo "Query " . ($i+1) . ":\n";
    echo $query['query'] . "\n";
    echo "Bindings: " . json_encode($query['bindings'], JSON_UNESCAPED_UNICODE) . "\n";
    echo "Time: " . $query['time'] . " ms\n\n";
}

// اختبار البحث المباشر
echo "\n=== اختبار البحث المباشر ===\n";
$result = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_FIRST_ARB', 'LIKE', 'نصرالله%')
    ->where('CI_FATHER_ARB', 'LIKE', 'عبد الناصر%')
    ->where('CI_GRAND_FATHER_ARB', 'LIKE', 'رفيق%')
    ->where('CI_FAMILY_ARB', 'LIKE', 'الفرا%')
    ->first();

if ($result) {
    echo "✅ النتيجة: {$result->CI_FIRST_ARB} {$result->CI_FATHER_ARB} {$result->CI_GRAND_FATHER_ARB} {$result->CI_FAMILY_ARB}\n";
} else {
    echo "❌ لم يتم العثور على نتيجة\n";
}
