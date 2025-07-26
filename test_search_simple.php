<?php

echo "🔍 اختبار البحث الشامل\n";

// تحديد مسار Laravel
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$searchTerm = 'سعاد صلاح رضوان بلال';
echo "البحث عن: {$searchTerm}\n\n";

// اختبار البحث في الجدول
$results = DB::table('persons')
    ->where('CI_FIRST_ARB', 'like', '%سعاد%')
    ->orWhere('CI_FATHER_ARB', 'like', '%صلاح%')
    ->orWhere('CI_FAMILY_ARB', 'like', '%رضوان%')
    ->limit(10)
    ->get();

echo "عدد النتائج: " . $results->count() . "\n";

if ($results->count() > 0) {
    foreach ($results as $result) {
        echo "- " . ($result->CI_FIRST_ARB ?? '') . " " . ($result->CI_FATHER_ARB ?? '') . " " . ($result->CI_FAMILY_ARB ?? '') . "\n";
    }
} else {
    echo "لا توجد نتائج\n";

    // اختبار عدد السجلات الإجمالي
    $total = DB::table('persons')->count();
    echo "إجمالي السجلات: {$total}\n";
}

echo "\nاختبار CustomScoutSearchService:\n";
try {
    $service = new App\Services\CustomScoutSearchService();
    $scoutResults = $service->instantSearch('سعاد', 5);
    echo "نتائج Scout: " . count($scoutResults) . "\n";
} catch (Exception $e) {
    echo "خطأ في Scout: " . $e->getMessage() . "\n";
}
