<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CivilRegistryScoutSearchService;
use Illuminate\Support\Facades\Cache;

// مسح الكاش للاختبار الجديد
Cache::flush();

$service = new CivilRegistryScoutSearchService();

echo "⚡ اختبار البحث السريع المحسن:\n";
echo str_repeat('=', 50) . "\n";

$testName = 'لمياء ابراهيم زياد ابو دحيل';

echo "🔎 البحث عن: '$testName'\n";
echo str_repeat('-', 50) . "\n";

// تشغيل البحث مع قياس الوقت
$totalStart = microtime(true);

for ($i = 1; $i <= 3; $i++) {
    $start = microtime(true);
    $result = $service->quickScoutSearch($testName, 10);
    $time = (microtime(true) - $start) * 1000;

    echo "🔄 تشغيل #$i:\n";

    if ($result['success']) {
        echo "✅ النتائج: " . $result['total_count'] . " نتيجة\n";
        echo "⚡ الوقت: " . round($time, 2) . " ms\n";

        if ($result['total_count'] > 0) {
            $person = (array)$result['data'][0];
            $fullName = trim(($person['CI_FIRST_ARB'] ?? '') . ' ' .
                            ($person['CI_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_GRAND_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_FAMILY_ARB'] ?? ''));

            echo "👤 النتيجة: رقم الهوية " . ($person['CI_ID_NUM'] ?? 'غير محدد') . "\n";
            echo "📝 الاسم: $fullName\n";
        }
    } else {
        echo "❌ خطأ: " . $result['error'] . "\n";
    }
    echo "\n";
}

$totalTime = (microtime(true) - $totalStart) * 1000;

echo str_repeat('=', 50) . "\n";
echo "📊 المتوسط العام: " . round($totalTime / 3, 2) . " ms\n";
echo "🎯 التحسن: من 8820ms إلى ~" . round($totalTime / 3, 2) . "ms\n";
echo "📈 نسبة التحسن: " . round((8820 - ($totalTime / 3)) / 8820 * 100, 1) . "%\n";
