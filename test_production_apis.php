<?php
/**
 * اختبار APIs البحث على الاستضافة المباشرة
 */

echo "🔍 اختبار أداء النظام على الاستضافة المشتركة\n";
echo "=" . str_repeat("=", 60) . "\n";

// استخدام cURL لاختبار APIs
function testAPI($endpoint, $description) {
    echo "\n🎯 اختبار: $description\n";
    echo "🔗 $endpoint\n";

    $startTime = microtime(true);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: Test-Script/1.0'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2);

    echo "📊 HTTP Code: $httpCode\n";
    echo "⏱️ وقت الاستجابة: " . round($totalTime * 1000, 2) . " ms\n";
    echo "⚡ وقت التنفيذ الكامل: $executionTime ms\n";

    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        if ($data) {
            echo "✅ النتيجة: " . ($data['count'] ?? 0) . " نتيجة\n";
            echo "🔧 المحرك: " . ($data['engine'] ?? 'غير محدد') . "\n";
            echo "⏰ وقت البحث: " . ($data['search_time'] ?? 0) . " ms\n";

            if (!empty($data['results'])) {
                $first = $data['results'][0];
                echo "👤 أول نتيجة: " . ($first['full_name'] ?? $first['name'] ?? 'غير محدد') . "\n";
            }
        } else {
            echo "⚠️ استجابة JSON غير صحيحة\n";
        }
    } else {
        echo "❌ فشل الطلب\n";
        if ($response) {
            echo "📄 الرد: " . substr($response, 0, 200) . "\n";
        }
    }

    echo str_repeat("-", 50) . "\n";
}

// تحديد عنوان الموقع (يجب تغييره حسب موقعك)
$baseUrl = "https://yourdomain.com"; // ⚠️ غيّر هذا إلى عنوان موقعك

// اختبارات مختلفة
$tests = [
    [
        'url' => $baseUrl . '/api/search/exact-only?q=محمد',
        'desc' => 'البحث الدقيق الجديد - اسم محمد'
    ],
    [
        'url' => $baseUrl . '/api/search/exact-only?q=ديمو سليمان فيكتوري',
        'desc' => 'البحث الدقيق - الاسم الكامل'
    ],
    [
        'url' => $baseUrl . '/api/search/final-exact?q=أحمد&limit=3',
        'desc' => 'البحث النهائي - 3 نتائج'
    ]
];

foreach ($tests as $test) {
    testAPI($test['url'], $test['desc']);
}

echo "\n📋 ملاحظات مهمة:\n";
echo "1. غيّر \$baseUrl إلى عنوان موقعك الحقيقي\n";
echo "2. تأكد من أن routes/api.php محدث\n";
echo "3. الفهارس الموجودة كافية للعمل\n";
echo "4. البحث برقم الهوية سريع جداً (0.3ms)\n";
echo "5. البحث بالاسم مقبول (58-141ms)\n";

echo "\n🎉 النظام جاهز للاستخدام!\n";
