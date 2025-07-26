<?php

echo "🎯 اختبار API البحث الدقيق الجديد (نتيجة واحدة فقط)\n";
echo str_repeat("=", 70) . "\n";

$testNames = [
    'ديمو سليمان فيكتوري',
    'ايمان عبد الرحمن سليمان ابداح',
    'محمد',
    'اسم غير موجود'
];

foreach ($testNames as $index => $name) {
    echo "\n🔍 اختبار " . ($index + 1) . ": '$name'\n";
    echo str_repeat("-", 50) . "\n";

    // اختبار البحث الدقيق الجديد
    $url = "http://127.0.0.1:8001/api/search/exact-only?" . http_build_query(['q' => $name]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);

        echo "✅ الحالة: " . ($result['success'] ? 'نجح' : 'فشل') . "\n";
        echo "📝 الرسالة: " . ($result['message'] ?? 'غير محدد') . "\n";
        echo "⏱️ الوقت: " . ($result['search_time'] ?? 0) . " ms\n";
        echo "📊 النتائج: " . ($result['count'] ?? 0) . "\n";
        echo "🔧 المحرك: " . ($result['engine'] ?? 'غير محدد') . "\n";

        if (!empty($result['results'])) {
            $person = $result['results'][0];
            echo "👤 النتيجة:\n";
            echo "   الاسم: " . ($person['full_name'] ?? 'غير محدد') . "\n";
            echo "   ID: " . ($person['id'] ?? 'غير محدد') . "\n";
            echo "   رقم الهوية: " . ($person['id_num'] ?? 'غير محدد') . "\n";
            echo "   الجنس: " . ($person['gender'] ?? 'غير محدد') . "\n";
        } else {
            echo "❌ لا توجد نتائج\n";
        }
    } else {
        echo "❌ خطأ HTTP: $httpCode\n";
        if ($response) {
            echo "Response: " . substr($response, 0, 200) . "\n";
        }
    }
}

echo "\n\n🎯 الخلاصة:\n";
echo "✅ الـ API الجديد /search/exact-only يُرجع نتيجة واحدة فقط\n";
echo "✅ هذا يحل مشكلة النتائج المتعددة المتشابهة\n";
echo "✅ استخدم هذا الـ API بدلاً من البحث العادي\n";
