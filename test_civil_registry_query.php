<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$searchTerm = "محمد الفرا";
$normalizedTerm = normalizeArabicText($searchTerm);
echo "البحث عن: $searchTerm\n";
echo "البحث المطبع: $normalizedTerm\n\n";

$words = preg_split('/\s+/', trim($normalizedTerm));
echo "الكلمات: " . implode(' | ', $words) . "\n\n";

// اختبار 1: استعلام بسيط جداً (INDEX مباشر)
echo "=== اختبار 1: بحث بسيط مع INDEX ===\n";
$start = microtime(true);
$result = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_FIRST_ARB', 'LIKE', $words[0] . '%')
    ->where('CI_FAMILY_ARB', 'LIKE', $words[1] . '%')
    ->limit(1)
    ->get();
$time = round((microtime(true) - $start) * 1000, 2);
echo "⏱️  الوقت: $time ms\n";
echo "النتائج: " . $result->count() . "\n";
if ($result->count() > 0) {
    echo "الاسم: {$result[0]->CI_FIRST_ARB} {$result[0]->CI_FAMILY_ARB}\n";
}
echo "\n";

// اختبار 2: استخدام NormalizedSearchService
echo "=== اختبار 2: استخدام NormalizedSearchService ===\n";
$start = microtime(true);
$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
$result2 = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);
$time2 = round((microtime(true) - $start) * 1000, 2);
echo "⏱️  الوقت: $time2 ms\n";
echo "النتائج: " . $result2->count() . "\n";
if ($result2->count() > 0) {
    echo "الاسم: {$result2[0]->CI_FIRST_ARB} {$result2[0]->CI_FAMILY_ARB}\n";
}
echo "\n";

// اختبار 3: بحث مباشر بدون orderByRaw
echo "=== اختبار 3: بحث بدون ترتيب معقد ===\n";
$start = microtime(true);
$result3 = DB::connection('civilregistry')
    ->table('persons')
    ->select('CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_FAMILY_ARB')
    ->where(function($q) use ($words) {
        $q->where('CI_FIRST_ARB', 'LIKE', $words[0] . '%')
          ->where('CI_FAMILY_ARB', 'LIKE', $words[1] . '%');
    })
    ->orWhere(function($q) use ($words) {
        $q->where('CI_FIRST_ARB', 'LIKE', $words[0] . '%')
          ->where('CI_FATHER_ARB', 'LIKE', $words[1] . '%');
    })
    ->limit(1)
    ->get();
$time3 = round((microtime(true) - $start) * 1000, 2);
echo "⏱️  الوقت: $time3 ms\n";
echo "النتائج: " . $result3->count() . "\n";
if ($result3->count() > 0) {
    echo "الاسم: {$result3[0]->CI_FIRST_ARB} {$result3[0]->CI_FAMILY_ARB}\n";
}
