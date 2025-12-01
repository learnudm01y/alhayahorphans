<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NormalizedSearchService;
$searchService = new NormalizedSearchService();

echo "البحث عن: نصرالله الفرا\n\n";
$results = $searchService->searchCivilRegistry('نصرالله الفرا');

echo "أول 10 نتائج:\n";
foreach (array_slice($results->toArray(), 0, 10) as $i => $person) {
    $fullName = "{$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}";
    $familyMatch = ($person->CI_FAMILY_ARB === 'الفرا') ? '✅ مطابقة دقيقة' : '⚠️ مطابقة جزئية';
    echo ($i+1) . ". {$fullName} - {$familyMatch}\n";
    echo "   العائلة: {$person->CI_FAMILY_ARB}\n";
}
