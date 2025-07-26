<?php

$baseUrl = 'http://127.0.0.1:8001/api/search/final-exact';
$query = 'ايمان عبد الرحمن سليمان ابداح';
$limit = 1;

$url = $baseUrl . '?' . http_build_query([
    'q' => $query,
    'limit' => $limit
]);

echo "🔍 اختبار API للبحث الدقيق:\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($error) {
    echo "❌ خطأ cURL: $error\n";
    exit;
}

if ($httpCode !== 200) {
    echo "❌ خطأ HTTP: $httpCode\n";
    echo "Response: $response\n";
    exit;
}

$result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ خطأ في JSON: " . json_last_error_msg() . "\n";
    echo "Raw Response: $response\n";
    exit;
}

echo "✅ النتيجة:\n";
echo "📊 عدد النتائج: " . ($result['count'] ?? 0) . "\n";
echo "⏱️ وقت البحث: " . ($result['search_time'] ?? 0) . " ms\n";
echo "🎯 المحرك: " . ($result['engine'] ?? 'غير محدد') . "\n\n";

if (!empty($result['results'])) {
    foreach ($result['results'] as $index => $person) {
        echo "👤 النتيجة " . ($index + 1) . ":\n";
        echo "   ID: " . ($person['id'] ?? 'غير محدد') . "\n";
        echo "   الاسم: " . ($person['full_name'] ?? 'غير محدد') . "\n";
        echo "   رقم الهوية: " . ($person['ci_id_num'] ?? 'غير محدد') . "\n\n";
    }
} else {
    echo "❌ لم يتم العثور على نتائج\n";
}
