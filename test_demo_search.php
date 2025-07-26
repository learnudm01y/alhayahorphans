<?php

echo "🔍 اختبار البحث عن: ديمو سليمان فيكتوري\n";
echo str_repeat("=", 60) . "\n";

$query = 'ديمو سليمان فيكتوري';
$limit = 10;

// اختبار البحث الدقيق
echo "\n🎯 البحث الدقيق:\n";
$url = "http://127.0.0.1:8001/api/search/final-exact?" . http_build_query(['q' => $query, 'limit' => $limit]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);

    echo "⏱️ الوقت: " . ($result['search_time'] ?? 0) . " ms\n";
    echo "📊 النتائج: " . ($result['count'] ?? 0) . "\n\n";

    if (!empty($result['results'])) {
        echo "📋 النتائج:\n";
        foreach ($result['results'] as $index => $person) {
            echo "   " . ($index + 1) . ". " . ($person['full_name'] ?? '') . "\n";
            echo "      ID: " . ($person['id'] ?? '') . "\n";
            echo "      رقم الهوية: " . ($person['id_num'] ?? 'غير محدد') . "\n";
            echo "      الجنس: " . ($person['gender'] ?? 'غير محدد') . "\n";
            echo "      تاريخ الميلاد: " . ($person['birth_date'] ?? 'غير محدد') . "\n";
            echo "      المدينة: " . ($person['city'] ?? 'غير محدد') . "\n";
            echo "      اسم الأم: " . ($person['mother_name'] ?? 'غير محدد') . "\n\n";
        }
    } else {
        echo "❌ لم يتم العثور على نتائج للبحث الدقيق\n\n";

        // جرب البحث العادي
        echo "🔍 البحث العادي (للمقارنة):\n";
        $url2 = "http://127.0.0.1:8001/api/search/ultra-fast?" . http_build_query(['q' => $query, 'limit' => $limit]);

        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $url2);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 30);

        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($httpCode2 === 200) {
            $result2 = json_decode($response2, true);
            echo "⏱️ الوقت: " . ($result2['search_time'] ?? 0) . " ms\n";
            echo "📊 النتائج: " . ($result2['count'] ?? 0) . "\n\n";

            if (!empty($result2['results'])) {
                echo "📋 النتائج (أول 5 نتائج):\n";
                $count = 0;
                foreach ($result2['results'] as $person) {
                    $count++;
                    echo "   $count. " . ($person['full_name'] ?? '') . " (ID: " . ($person['id'] ?? '') . ")\n";
                    if ($count >= 5) break;
                }
            }
        }
    }
} else {
    echo "❌ خطأ في الاتصال: HTTP $httpCode\n";
}
