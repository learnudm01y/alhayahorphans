<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  فحص: لماذا يعيد 400009692 بدل 933046774؟                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// فحص رقم الهوية الصحيح
$correct = DB::connection('civilregistry')->table('persons')->where('CI_ID_NUM', '933046774')->first();
if ($correct) {
    echo "✅ رقم الهوية الصحيح: 933046774\n";
    echo "   الاسم: {$correct->CI_FIRST_ARB}\n";
    echo "   الأب: {$correct->CI_FATHER_ARB}\n";
    echo "   الجد: {$correct->CI_GRAND_FATHER_ARB}\n";
    echo "   العائلة: {$correct->CI_FAMILY_ARB}\n";
    echo "   الاسم المطبّع: {$correct->CI_FIRST_ARB_NORMALIZED}\n";
} else {
    echo "❌ لم يُوجد رقم الهوية 933046774\n";
}

echo "\n";

// فحص رقم الهوية الخاطئ
$wrong = DB::connection('civilregistry')->table('persons')->where('CI_ID_NUM', '400009692')->first();
if ($wrong) {
    echo "❌ رقم الهوية الخاطئ: 400009692\n";
    echo "   الاسم: {$wrong->CI_FIRST_ARB}\n";
    echo "   الأب: {$wrong->CI_FATHER_ARB}\n";
    echo "   الجد: {$wrong->CI_GRAND_FATHER_ARB}\n";
    echo "   العائلة: {$wrong->CI_FAMILY_ARB}\n";
    echo "   الاسم المطبّع: {$wrong->CI_FIRST_ARB_NORMALIZED}\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 البحث عن: مصطفى نبيل مصطفى ابوعيد\n\n";

// البحث باستخدام NormalizedSearchService
$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
$results = $normalizedSearchService->searchCivilRegistry("مصطفى نبيل مصطفى ابوعيد", 10);

echo "عدد النتائج: " . $results->count() . "\n\n";

foreach ($results as $index => $result) {
    echo "نتيجة " . ($index + 1) . ":\n";
    echo "   رقم الهوية: {$result->id_number}\n";
    echo "   الاسم: {$result->CI_FIRST_ARB} {$result->CI_FATHER_ARB} {$result->CI_GRAND_FATHER_ARB} {$result->CI_FAMILY_ARB}\n";
    echo "\n";
}
