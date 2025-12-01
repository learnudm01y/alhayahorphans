<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::connection('civilregistry')->enableQueryLog();

use App\Services\NormalizedSearchService;
$searchService = new NormalizedSearchService();

echo "\n=== تتبع الـ queries ===\n\n";

echo "1️⃣ البحث برقم الهوية 407015692:\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('407015692');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   الوقت: {$duration} ms\n";
echo "   عدد النتائج: " . count($results) . "\n\n";

$queries = DB::connection('civilregistry')->getQueryLog();
echo "عدد الاستعلامات المنفذة: " . count($queries) . "\n\n";

foreach ($queries as $i => $query) {
    echo "Query " . ($i+1) . ":\n";
    echo "SQL: " . $query['query'] . "\n";
    echo "Bindings: " . json_encode($query['bindings'], JSON_UNESCAPED_UNICODE) . "\n";
    echo "Time: " . $query['time'] . " ms\n\n";
}
