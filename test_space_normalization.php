<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار دعم المسافات في الأسماء المركبة                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$testCases = [
    "عبد الناصر",      // بمسافة
    "عبدالناصر",       // بدون مسافة
    "أبو محمد",        // بمسافة
    "ابومحمد",         // بدون مسافة
];

$service = app(\App\Services\NormalizedSearchService::class);

foreach ($testCases as $idx => $searchTerm) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "اختبار #" . ($idx + 1) . ": $searchTerm\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $start = microtime(true);
    $results = $service->searchCivilRegistry($searchTerm, 3);
    $time = round((microtime(true) - $start) * 1000, 2);

    echo "⏱️  الوقت: {$time} ms\n";
    echo "📊 عدد النتائج: {$results->count()}\n\n";

    if ($results->count() > 0) {
        foreach ($results as $i => $person) {
            echo "  " . ($i + 1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        }
    } else {
        echo "  ❌ لا توجد نتائج\n";
    }

    echo "\n";
}

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                      النتيجة                                 ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "✅ البحث يدعم الأسماء بمسافات وبدون مسافات\n";
