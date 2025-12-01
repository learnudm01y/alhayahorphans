<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$searchTerm = "محمد عبد الناصر رفيق الفرا";
$normalized = normalizeArabicText($searchTerm);

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  تحليل المشكلة في البحث الخارجي                             ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
echo "🔍 البحث عن: $searchTerm\n";
echo "📝 المطبّع: $normalized\n\n";

// 1. البحث في data table
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  البحث في data table\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$words = preg_split('/\s+/', trim($normalized));
echo "الكلمات: " . implode(' | ', $words) . "\n\n";

// البحث باستخدام searchDataTable
$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
$start = microtime(true);
$dataResults = $normalizedSearchService->searchDataTable($searchTerm, 5);
$time = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time} ms\n";
echo "📊 عدد النتائج: " . $dataResults->count() . "\n\n";

if ($dataResults->count() > 0) {
    foreach ($dataResults as $idx => $result) {
        echo "نتيجة #" . ($idx + 1) . ":\n";
        echo "  🆔 رقم الهوية: {$result->data_id_number}\n";
        echo "  👤 الاسم: {$result->data_first_name} {$result->data_father_name} {$result->data_grand_father_name} {$result->data_family_name}\n";
        echo "  📁 رقم الملف: {$result->file_id_number}\n\n";
    }

    echo "⚠️  المشكلة: searchDataTable يُرجع نتيجة من data\n";
    echo "   لذلك لا يستمر البحث في civil_registry!\n\n";
}

// 2. البحث المباشر في civil_registry لمقارنة
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  البحث الصحيح في civil_registry\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$start = microtime(true);
$civilResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);
$time = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time} ms\n";
echo "📊 عدد النتائج: " . $civilResults->count() . "\n\n";

if ($civilResults->count() > 0) {
    $person = $civilResults->first();
    echo "✅ النتيجة الصحيحة:\n";
    echo "  🆔 رقم الهوية: {$person->id_number}\n";
    echo "  👤 الاسم: {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
    echo "  📅 تاريخ الميلاد: {$person->CI_BIRTH_DT}\n\n";
}

// 3. التحليل
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📝 التحليل:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($dataResults->count() > 0 && $civilResults->count() > 0) {
    $dataId = $dataResults->first()->data_id_number;
    $civilId = $civilResults->first()->id_number;

    if ($dataId != $civilId) {
        echo "❌ المشكلة مؤكدة:\n";
        echo "   - data يُرجع: {$dataId}\n";
        echo "   - civil_registry يُرجع: {$civilId}\n\n";
        echo "💡 الحل:\n";
        echo "   يجب تحسين searchDataTable لتطابق الاسم الكامل بدقة\n";
        echo "   بدلاً من البحث الجزئي الذي يُرجع أول تطابق\n";
    } else {
        echo "✅ النتائج متطابقة\n";
    }
}
