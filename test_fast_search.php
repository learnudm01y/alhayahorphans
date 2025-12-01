<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\FastSearchService;

$service = new FastSearchService();

echo "Testing FastSearchService...\n\n";

// Test 1: Search in data table
echo "Test 1: البحث في data عن 'محمد':\n";
$results = $service->searchData('محمد', 5);
echo "Found " . count($results) . " results\n";
if (!empty($results)) {
    foreach ($results as $result) {
        $result = (array)$result;
        echo "  - " . ($result['first_name'] ?? 'N/A') . " " . ($result['father_name'] ?? '') . "\n";
    }
}
echo "\n";

// Test 2: Search with ID
echo "Test 2: البحث برقم الهوية:\n";
$results = $service->searchData('400', 3);
echo "Found " . count($results) . " results\n";
echo "\n";

// Test 3: Search in re_people
echo "Test 3: البحث في re_people:\n";
$results = $service->searchRePeople('محمد', 3);
echo "Found " . count($results) . " results\n";
if (!empty($results)) {
    foreach ($results as $result) {
        $result = (array)$result;
        echo "  - " . ($result['first_name'] ?? 'N/A') . " " . ($result['second_name'] ?? '') . "\n";
    }
}

echo "\nDone!\n";
