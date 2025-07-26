<?php

require_once __DIR__ . '/vendor/autoload.php';

// تشغيل Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 اختبار إصلاح خطأ البحث\n";
echo "==============================\n\n";

try {
    // تجربة إنشاء SearchService
    echo "1️⃣ إنشاء خدمة البحث...\n";
    $searchService = app(\App\Services\SearchService::class);
    echo "✅ تم إنشاء خدمة البحث بنجاح\n\n";

    // تجربة البحث البسيط
    echo "2️⃣ اختبار البحث البسيط...\n";
    $filters = [
        'search_type' => 'all',
        'search_text' => '800631285'
    ];

    $results = $searchService->smartSearch($filters, 10);
    echo "✅ تم تنفيذ البحث بنجاح\n";
    echo "📊 عدد النتائج: " . ($results['total_count'] ?? 0) . "\n\n";

    // تجربة اقتراحات البحث
    echo "3️⃣ اختبار اقتراحات البحث...\n";
    $suggestions = $searchService->getSearchSuggestions('800', 5);
    echo "✅ تم الحصول على الاقتراحات بنجاح\n";
    echo "💡 عدد الاقتراحات: " . count($suggestions) . "\n\n";

    echo "🎉 جميع الاختبارات نجحت! تم إصلاح الخطأ بنجاح\n";

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n";
    echo "📄 الملف: " . $e->getFile() . "\n";
}
