<?php

require 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🧪 === اختبار API endpoints للـ Scout ===\n\n";

// اختبار instant search
echo "🔍 1. اختبار Instant Search API:\n";
$url = 'http://127.0.0.1:8000/admin/scout/instant-search?query=احمد';

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
    echo "   ✅ API يعمل بنجاح\n";
    echo "   - عدد النتائج: " . (isset($data['data']) ? count($data['data']) : 'غير محدد') . "\n";
    echo "   - الوقت المستغرق: " . ($data['search_time'] ?? 'غير محدد') . " ثانية\n";
    echo "   - الحالة: " . ($data['success'] ? 'نجح' : 'فشل') . "\n\n";
} else {
    echo "   ❌ فشل في الاتصال بـ API\n";
    echo "   - تأكد من تشغيل الخادم على المنفذ 8000\n\n";
}

// اختبار suggestions
echo "🎯 2. اختبار Suggestions API:\n";
$url = 'http://127.0.0.1:8000/admin/scout/suggestions?term=محمد';

$response = @file_get_contents($url, false, $context);

if ($response) {
    $data = json_decode($response, true);
    echo "   ✅ API يعمل بنجاح\n";
    echo "   - عدد الاقتراحات: " . (is_array($data) ? count($data) : 'غير محدد') . "\n";
    if (is_array($data) && count($data) > 0) {
        echo "   - أول اقتراح: " . ($data[0]['label'] ?? 'غير محدد') . "\n";
    }
    echo "\n";
} else {
    echo "   ❌ فشل في الاتصال بـ API\n\n";
}

echo "🌐 3. معلومات النظام:\n";
echo "   - رابط الموقع: http://127.0.0.1:8000\n";
echo "   - صفحة البيانات المدنية: http://127.0.0.1:8000/admin/civil-registry\n";
echo "   - الوقت: " . date('Y-m-d H:i:s') . "\n\n";

echo "✅ انتهى الاختبار - يمكنك الآن فتح الموقع واختبار Modal Scout!\n";
