<?php

use App\Services\SearchService;

// اختبار البحث بأسماء مختلفة الأطوال
try {
    echo "=== اختبار أنواع البحث المختلفة ===\n\n";

    $searchService = new SearchService();

    // 1. اختبار الاسم الطويل (البحث الدقيق)
    echo "1. البحث الدقيق - اسم طويل (7 كلمات):\n";
    $longName = [
        'search_type' => 'main_records',
        'search_text' => 'Kyle Christine Byers Brenda Mason Nayda Ayers'
    ];

    $results1 = $searchService->smartSearch($longName, 25);
    echo "   النص: {$longName['search_text']}\n";
    echo "   النتائج: " . count($results1['data']) . "\n";
    if (!empty($results1['data'])) {
        echo "   أول نتيجة: {$results1['data'][0]['full_name']}\n";
    }

    // 2. اختبار اسم متوسط (البحث الشامل)
    echo "\n2. البحث الشامل - اسم متوسط (3 كلمات):\n";
    $mediumName = [
        'search_type' => 'main_records',
        'search_text' => 'Kyle Christine Byers'
    ];

    $results2 = $searchService->smartSearch($mediumName, 25);
    echo "   النص: {$mediumName['search_text']}\n";
    echo "   النتائج: " . count($results2['data']) . "\n";
    if (!empty($results2['data'])) {
        echo "   أول نتيجة: {$results2['data'][0]['full_name']}\n";
    }

    // 3. اختبار اسم قصير (البحث الشامل)
    echo "\n3. البحث الشامل - اسم قصير (2 كلمة):\n";
    $shortName = [
        'search_type' => 'main_records',
        'search_text' => 'Kyle Christine'
    ];

    $results3 = $searchService->smartSearch($shortName, 25);
    echo "   النص: {$shortName['search_text']}\n";
    echo "   النتائج: " . count($results3['data']) . "\n";
    if (!empty($results3['data'])) {
        echo "   أول نتيجة: {$results3['data'][0]['full_name']}\n";
    }

    echo "\n=== الخلاصة ===\n";
    echo "✅ البحث الدقيق للأسماء الطويلة: " . count($results1['data']) . " نتيجة\n";
    echo "✅ البحث الشامل للأسماء المتوسطة: " . count($results2['data']) . " نتيجة\n";
    echo "✅ البحث الشامل للأسماء القصيرة: " . count($results3['data']) . " نتيجة\n";

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

?>
