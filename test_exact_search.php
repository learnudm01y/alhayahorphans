<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 اختبار البحث الدقيق الجديد\n\n";

// اختبار مباشر للـ API مع الاسم الكامل
$searchQuery = "Kyle Christine Byers Brenda Mason Nayda Ayers";

echo "🎯 البحث عن: {$searchQuery}\n";
echo str_repeat("=", 60) . "\n\n";

$request = new \Illuminate\Http\Request(['query' => $searchQuery]);

try {
    $searchService = new \App\Services\SearchService();
    $controller = new \App\Http\Controllers\Admin\ProfileSearchController($searchService);
    $response = $controller->quickSearch($request);
    $responseData = json_decode($response->getContent(), true);

    echo "📤 حالة API: " . ($responseData['success'] ? '✅ نجح' : '❌ فشل') . "\n";
    echo "📊 عدد النتائج: " . ($responseData['total'] ?? 0) . "\n\n";

    if (isset($responseData['data']) && count($responseData['data']) > 0) {
        foreach ($responseData['data'] as $index => $result) {
            echo "📋 نتيجة " . ($index + 1) . ":\n";
            echo "   🏷️  العنوان: {$result['title']}\n";
            echo "   📁 النوع: {$result['type']}\n";
            echo "   ⭐ درجة الصلة: {$result['relevance']}\n\n";
        }
    } else {
        echo "❌ لا توجد نتائج\n";
    }

    // اختبارات إضافية
    $testCases = [
        'Kyle',
        'Christine',
        'Kyle Christine',
        'Byers Mason'
    ];

    echo str_repeat("=", 60) . "\n";
    echo "🧪 اختبارات إضافية:\n\n";

    foreach ($testCases as $testQuery) {
        $request = new \Illuminate\Http\Request(['query' => $testQuery]);
        $response = $controller->quickSearch($request);
        $responseData = json_decode($response->getContent(), true);

        echo "🔍 البحث عن: \"{$testQuery}\"\n";
        echo "📊 عدد النتائج: " . ($responseData['total'] ?? 0) . "\n";

        if (isset($responseData['data']) && count($responseData['data']) > 0) {
            echo "   🥇 أفضل نتيجة: {$responseData['data'][0]['title']} (درجة الصلة: {$responseData['data'][0]['relevance']})\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في API: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
}

echo "🎉 الاختبار مكتمل!\n";
