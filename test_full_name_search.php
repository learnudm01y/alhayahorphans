<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 اختبار البحث بالاسم الكامل المحدث\n\n";

// اختبار مباشر للـ API مع الاسم الكامل
$searchQuery = "Kyle Christine Byers Brenda Mason Nayda Ayers";

$request = new \Illuminate\Http\Request(['query' => $searchQuery]);

try {
    $searchService = new \App\Services\SearchService();
    $controller = new \App\Http\Controllers\Admin\ProfileSearchController($searchService);
    $response = $controller->quickSearch($request);
    $responseData = json_decode($response->getContent(), true);

    echo "🎯 نتائج البحث عن: {$searchQuery}\n";
    echo "📤 حالة API: " . ($responseData['success'] ? '✅ نجح' : '❌ فشل') . "\n";
    echo "📊 عدد النتائج: " . ($responseData['total'] ?? 0) . "\n\n";

    if (isset($responseData['data']) && count($responseData['data']) > 0) {
        foreach ($responseData['data'] as $index => $result) {
            echo "📋 نتيجة " . ($index + 1) . ":\n";
            echo "   🏷️  العنوان: {$result['title']}\n";
            echo "   📁 العنوان الفرعي: {$result['subtitle']}\n";
            echo "   📝 الوصف: {$result['description']}\n";
            echo "   🏷️  النوع: {$result['type']}\n";
            echo "   ⭐ درجة الصلة: {$result['relevance']}\n";
            echo "   🔗 الرابط: {$result['url']}\n\n";
        }
    } else {
        echo "❌ لا توجد نتائج\n";
    }

    // اختبار مع كلمات منفصلة
    echo "=".str_repeat("=", 50)."\n";
    echo "🔍 اختبار البحث بكلمات منفصلة:\n\n";

    $testWords = ['Kyle', 'Christine', 'Mason'];

    foreach ($testWords as $word) {
        $request = new \Illuminate\Http\Request(['query' => $word]);
        $response = $controller->quickSearch($request);
        $responseData = json_decode($response->getContent(), true);

        echo "🎯 البحث عن: {$word}\n";
        echo "📊 عدد النتائج: " . ($responseData['total'] ?? 0) . "\n";

        if (isset($responseData['data']) && count($responseData['data']) > 0) {
            foreach ($responseData['data'] as $result) {
                if (stripos($result['title'], $word) !== false) {
                    echo "   ✅ {$result['title']} ({$result['type']}) - درجة الصلة: {$result['relevance']}\n";
                }
            }
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في API: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
}

echo "\n🎉 الاختبار مكتمل!\n";
