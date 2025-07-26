<?php

// اختبار البحث عن "ديمو سليمان فيكتوري" بطرق مختلفة
echo "🔍 اختبار أنواع البحث المختلفة:\n";
echo str_repeat("=", 70) . "\n";

$searchName = 'ديمو سليمان فيكتوري';

// الطرق المختلفة للبحث
$searchTypes = [
    'final-exact' => 'البحث الدقيق النهائي',
    'ultra-fast' => 'البحث السريع',
    'exact' => 'البحث الدقيق',
    'smart' => 'البحث الذكي'
];

foreach ($searchTypes as $type => $typeName) {
    echo "\n📊 $typeName ($type):\n";
    echo str_repeat("-", 50) . "\n";

    $url = "http://127.0.0.1:8001/api/search/$type?" . http_build_query([
        'q' => $searchName,
        'limit' => 5
    ]);

    $response = @file_get_contents($url);

    if ($response) {
        $result = json_decode($response, true);

        echo "⏱️ الوقت: " . ($result['search_time'] ?? 0) . " ms\n";
        echo "📈 عدد النتائج: " . ($result['count'] ?? 0) . "\n";

        if (!empty($result['results'])) {
            echo "🎯 النتائج:\n";
            foreach (array_slice($result['results'], 0, 3) as $index => $person) {
                $name = $person['full_name'] ?? $person['name'] ?? 'غير محدد';
                $id = $person['id'] ?? 'غير محدد';
                echo "   " . ($index + 1) . ". $name (ID: $id)\n";
            }

            if (($result['count'] ?? 0) > 3) {
                echo "   ... و " . (($result['count'] ?? 0) - 3) . " نتيجة أخرى\n";
            }
        } else {
            echo "❌ لا توجد نتائج\n";
        }
    } else {
        echo "❌ خطأ في الاتصال\n";
    }
}

echo "\n\n🎯 الخلاصة:\n";
echo "- إذا كان البحث الدقيق النهائي يُرجع نتيجة واحدة = ممتاز ✅\n";
echo "- إذا كانت الطرق الأخرى تُرجع نتائج كثيرة = هذا هو سبب المشكلة ❌\n";
echo "- الحل: استخدام البحث الدقيق النهائي فقط\n";
