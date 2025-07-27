<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CivilRegistryScoutSearchService;
use Illuminate\Support\Facades\Cache;

// مسح الكاش للاختبار
Cache::flush();

$service = new CivilRegistryScoutSearchService();

echo "🔍 اختبار البحث الشامل النهائي:\n";
echo str_repeat('=', 50) . "\n";

$tests = [
    'لمياء ابراهيم زياد ابو دحيل',
    'محمد أحمد',
    'نصر',
    '407015692'
];

foreach ($tests as $query) {
    echo "\n🔎 البحث عن: '$query'\n";
    echo str_repeat('-', 30) . "\n";

    $start = microtime(true);
    $result = $service->quickScoutSearch($query, 5);
    $time = (microtime(true) - $start) * 1000;

    if ($result['success']) {
        echo "✅ النتائج: " . $result['total_count'] . " نتيجة\n";
        echo "⚡ الوقت الفعلي: " . round($time, 2) . " ms\n";

        if ($result['total_count'] > 0) {
            $person = (array)$result['data'][0];
            $fullName = trim(($person['CI_FIRST_ARB'] ?? '') . ' ' .
                            ($person['CI_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_GRAND_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_FAMILY_ARB'] ?? ''));

            echo "📋 أول نتيجة:\n";
            echo "   رقم الهوية: " . ($person['CI_ID_NUM'] ?? 'غير محدد') . "\n";
            echo "   الاسم الكامل: $fullName\n";
        }
    } else {
        echo "❌ خطأ: " . $result['error'] . "\n";
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "🎯 انتهى الاختبار النهائي!\n";
