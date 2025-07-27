<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CivilRegistryScoutSearchService;
use Illuminate\Support\Facades\Cache;

// مسح الكاش
Cache::flush();

$service = new CivilRegistryScoutSearchService();

echo "🎯 اختبار البحث المحسن مع الترتيب الذكي:\n";
echo str_repeat('=', 60) . "\n";

$testName = 'لمياء ابراهيم زياد ابو دحيل';

echo "🔎 البحث عن: '$testName'\n";
echo str_repeat('-', 60) . "\n";

$start = microtime(true);
$result = $service->quickScoutSearch($testName, 20);
$time = (microtime(true) - $start) * 1000;

if ($result['success']) {
    echo "✅ النتائج: " . $result['total_count'] . " نتيجة\n";
    echo "⚡ الوقت: " . round($time, 2) . " ms\n\n";

    if ($result['total_count'] > 0) {
        echo "📋 أفضل النتائج:\n";
        foreach (array_slice($result['data'], 0, 5) as $i => $person) {
            $person = (array)$person;
            $fullName = trim(($person['CI_FIRST_ARB'] ?? '') . ' ' .
                            ($person['CI_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_GRAND_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_FAMILY_ARB'] ?? ''));

            $priority = $person['match_priority'] ?? 'غير محدد';

            echo ($i + 1) . ". رقم الهوية: " . ($person['CI_ID_NUM'] ?? 'غير محدد') . "\n";
            echo "   الاسم الكامل: $fullName\n";
            echo "   أولوية المطابقة: $priority\n";

            // تحليل المطابقة
            if (strpos(strtolower($fullName), 'لمياء') !== false &&
                strpos(strtolower($fullName), 'ابراهيم') !== false) {
                if (strpos(strtolower($fullName), 'زياد') !== false) {
                    echo "   🎯 مطابقة ممتازة: 3 كلمات\n";
                } else {
                    echo "   ✅ مطابقة جيدة: 2 كلمات\n";
                }
            } else {
                echo "   ⚠️ مطابقة جزئية\n";
            }
            echo "\n";
        }
    }
} else {
    echo "❌ خطأ: " . $result['error'] . "\n";
}

echo str_repeat('=', 60) . "\n";
echo "🚀 النتيجة: من 8820ms إلى " . round($time, 2) . "ms!\n";
echo "📈 تحسن الأداء: " . round((8820 - $time) / 8820 * 100, 1) . "%\n";
