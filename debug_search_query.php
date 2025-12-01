<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NormalizedSearchService;

DB::connection('civilregistry')->enableQueryLog();

$searchService = new NormalizedSearchService();

echo "=== اختبار البحث برقم الهوية ===\n";
$results = $searchService->searchCivilRegistry('428818907');

$queries = DB::connection('civilregistry')->getQueryLog();
foreach ($queries as $q) {
    echo "\n📝 SQL Query:\n";
    echo $q['query'] . "\n";
    echo "\n⏱️ Time: " . $q['time'] . "ms\n";
    echo "\n🔍 Bindings:\n";
    print_r($q['bindings']);
}

echo "\n📊 عدد النتائج: " . count($results) . "\n";
