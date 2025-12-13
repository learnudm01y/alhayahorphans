<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test extractSearchTerms function
echo "Testing extractSearchTerms function:\n";
echo "====================================\n\n";

$testCases = [
    'أحمد محمد',
    'إبراهيم',
    'فاطمة',
    '123456',
    'محمد أحمد علي'
];

foreach ($testCases as $test) {
    echo "Input: {$test}\n";
    $result = extractSearchTerms($test);
    echo "Result: ";
    print_r($result);
    echo "\n";
}

echo "\n✅ All tests completed!\n";
