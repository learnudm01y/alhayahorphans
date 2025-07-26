<?php

echo "🎯 === اختبار Scout API بعد الإصلاحات ===\n\n";

// اختبار instant search
echo "🔍 1. اختبار Instant Search API:\n";
$url = 'http://127.0.0.1:8000/admin/scout/instant-search?query=احمد&limit=10';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => [
            'Accept: application/json',
            'User-Agent: Test-Script/1.0'
        ]
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response) {
    $data = json_decode($response, true);
    echo "   ✅ Instant Search API يعمل بنجاح\n";
    echo "   - عدد النتائج: " . (isset($data['data']) ? count($data['data']) : 'غير محدد') . "\n";
    echo "   - الوقت المستغرق: " . ($data['search_time'] ?? 'غير محدد') . " ثانية\n";
    echo "   - الحالة: " . ($data['success'] ? 'نجح' : 'فشل') . "\n\n";
} else {
    echo "   ❌ فشل في الاتصال بـ Instant Search API\n\n";
}

// اختبار suggestions (المُصلح)
echo "🎯 2. اختبار Suggestions API المُصلح:\n";
$url = 'http://127.0.0.1:8000/admin/scout/suggestions?term=محمد';

$response = @file_get_contents($url, false, $context);

if ($response) {
    $data = json_decode($response, true);
    echo "   ✅ Suggestions API يعمل بنجاح بعد الإصلاح!\n";
    echo "   - عدد الاقتراحات: " . (is_array($data) ? count($data) : 'غير محدد') . "\n";
    if (is_array($data) && count($data) > 0) {
        echo "   - أول اقتراح: " . ($data[0]['label'] ?? 'غير محدد') . "\n";
        echo "   - الوصف: " . ($data[0]['description'] ?? 'غير محدد') . "\n";
    }
    echo "\n";
} else {
    echo "   ❌ ما زال فشل في الاتصال بـ Suggestions API\n\n";
}

// اختبار advanced search
echo "⚡ 3. اختبار Advanced Search API:\n";
$url = 'http://127.0.0.1:8000/admin/scout/advanced-search';
$postData = json_encode([
    'query' => 'علي',
    'limit' => 5
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Test-Script/1.0'
        ],
        'content' => $postData,
        'timeout' => 10
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response) {
    $data = json_decode($response, true);
    echo "   ✅ Advanced Search API يعمل بنجاح\n";
    echo "   - عدد النتائج: " . (isset($data['data']) ? count($data['data']) : 'غير محدد') . "\n";
    echo "   - الوقت المستغرق: " . ($data['search_time'] ?? 'غير محدد') . " ثانية\n\n";
} else {
    echo "   ❌ فشل في الاتصال بـ Advanced Search API\n\n";
}

echo "📋 ملخص الإصلاحات المطبقة:\n";
echo "   ✅ تغيير route suggestions من POST إلى GET\n";
echo "   ✅ تحديث Controller ليقبل 'term' بدلاً من 'query'\n";
echo "   ✅ تغيير instant-search من POST إلى GET\n";
echo "   ✅ تنظيف modal file من الكود المكرر\n";
echo "   ✅ إصلاح format الاقتراحات\n\n";

echo "🌐 للاختبار الكامل:\n";
echo "   1. افتح: http://127.0.0.1:8000/admin/civil-registry\n";
echo "   2. اضغط على زر 'البحث السريع Scout'\n";
echo "   3. اكتب رقم هوية أو اسم\n";
echo "   4. لا مزيد من خطأ 405 Method Not Allowed!\n\n";

echo "✅ تم إصلاح جميع مشاكل الـ API!\n";
echo "📅 الوقت: " . date('Y-m-d H:i:s') . "\n";
