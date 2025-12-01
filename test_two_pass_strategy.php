<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار: مع مسافة vs بدون مسافة (Two-Pass Strategy)        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);

// Test 1: مع مسافة (يجب أن يكون سريع - first pass)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 اختبار 1: محمد عبد الناصر رفيق الفرا (مع مسافات)\n";

$start = microtime(true);
$results = $normalizedSearchService->searchCivilRegistry("محمد عبد الناصر رفيق الفرا", 1);
$time1 = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time1} ms\n";
if ($results && $results->isNotEmpty()) {
    echo "✅ وُجد - رقم الهوية: {$results->first()->id_number}\n";
} else {
    echo "❌ لم يُوجد\n";
}

// Test 2: بدون مسافة (يجب أن يستخدم second pass)
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 اختبار 2: محمد عبدالناصر رفيق الفرا (بدون مسافة)\n";

$start = microtime(true);
$results = $normalizedSearchService->searchCivilRegistry("محمد عبدالناصر رفيق الفرا", 1);
$time2 = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time2} ms\n";
if ($results && $results->isNotEmpty()) {
    echo "✅ وُجد - رقم الهوية: {$results->first()->id_number}\n";
} else {
    echo "❌ لم يُوجد\n";
}

// Test 3: "مصطفى" (مع ى)
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 اختبار 3: مصطفى نبيل مصطفى ابوعيد (مع ى)\n";

$start = microtime(true);
$results = $normalizedSearchService->searchCivilRegistry("مصطفى نبيل مصطفى ابوعيد", 1);
$time3 = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time3} ms\n";
if ($results && $results->isNotEmpty()) {
    echo "✅ وُجد - رقم الهوية: {$results->first()->id_number}\n";
} else {
    echo "❌ لم يُوجد\n";
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  الملخص                                                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "📊 مع مسافات: {$time1} ms (First Pass - يستخدم INDEX)\n";
echo "📊 بدون مسافات: {$time2} ms (Second Pass - يستخدم REPLACE)\n";
echo "📊 مصطفى: {$time3} ms (First Pass - normalization)\n\n";

if ($time1 < 500 && $time2 > 5000) {
    echo "✅ الاستراتيجية تعمل بشكل صحيح!\n";
    echo "   - البحث مع مسافات سريع (< 500ms)\n";
    echo "   - البحث بدون مسافات يعمل (> 5s لكن يعطي نتيجة صحيحة)\n";
} elseif ($time1 < 500 && $time2 < 500) {
    echo "✅✅ ممتاز! كلا البحثين سريعان!\n";
} else {
    echo "⚠️  هناك مشكلة في الأداء\n";
}
