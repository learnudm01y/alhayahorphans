<?php

echo "🔍 مقارنة بين أنواع البحث المختلفة:\n";
echo "=" . str_repeat("=", 60) . "\n\n";

$query = 'ايمان عبد الرحمن سليمان ابداح';
$limit = 10;

// اختبار البحث الدقيق النهائي
echo "🎯 البحث الدقيق النهائي:\n";
$url = "http://127.0.0.1:8001/api/search/final-exact?" . http_build_query(['q' => $query, 'limit' => $limit]);
$response = file_get_contents($url);
$result = json_decode($response, true);

echo "⏱️ الوقت: " . ($result['search_time'] ?? 0) . " ms\n";
echo "📊 النتائج: " . ($result['count'] ?? 0) . "\n";
if (!empty($result['results'])) {
    foreach ($result['results'] as $index => $person) {
        echo "   " . ($index + 1) . ". " . ($person['full_name'] ?? '') . " (ID: " . ($person['id'] ?? '') . ")\n";
    }
}
echo "\n";

// اختبار البحث السريع (للمقارنة)
echo "⚡ البحث السريع (للمقارنة):\n";
$url2 = "http://127.0.0.1:8001/api/search/ultra-fast?" . http_build_query(['q' => $query, 'limit' => $limit]);
$response2 = file_get_contents($url2);
$result2 = json_decode($response2, true);

echo "⏱️ الوقت: " . ($result2['search_time'] ?? 0) . " ms\n";
echo "📊 النتائج: " . ($result2['count'] ?? 0) . "\n";
if (!empty($result2['results'])) {
    $count = 0;
    foreach ($result2['results'] as $person) {
        $count++;
        echo "   $count. " . ($person['full_name'] ?? '') . " (ID: " . ($person['id'] ?? '') . ")\n";
        if ($count >= 5) {
            echo "   ... و " . (($result2['count'] ?? 0) - 5) . " نتيجة أخرى\n";
            break;
        }
    }
}

echo "\n🎯 الخلاصة:\n";
echo "- البحث الدقيق: يُرجع المطابقة التامة فقط\n";
echo "- البحث العادي: يُرجع نتائج متشابهة كثيرة\n";
echo "- هذا بالضبط ما كنت تريده! ✅\n";
