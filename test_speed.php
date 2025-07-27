<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CivilRegistryScoutSearchService;

echo "اختبار سرعة البحث المحسن في السجل المدني:\n";
echo str_repeat("=", 60) . "\n";

$service = new CivilRegistryScoutSearchService();

// اختبارات متعددة
$tests = [
    ['407015692', 'رقم هوية'],
    ['محمد', 'اسم'],
    ['نصر', 'جزء من اسم'],
    ['976815878', 'رقم هوية آخر'],
    ['لمياء ابراهيم زياد ابو دحيل', 'اسم كامل']
];

foreach ($tests as [$query, $description]) {
    echo "\nاختبار البحث: $description ($query)\n";
    echo str_repeat("-", 40) . "\n";

    $result = $service->quickScoutSearch($query, 10);

    if ($result['success']) {
        echo "✅ النتائج: " . $result['total_count'] . " نتيجة\n";
        echo "⚡ الوقت: " . $result['execution_time'] . "\n";
        echo "🔧 المحرك: " . $result['engine'] . "\n";

        if ($result['total_count'] > 0) {
            echo "📋 أول نتيجة:\n";
            $first = (array)$result['data'][0];
            echo "   - رقم الهوية: " . ($first['CI_ID_NUM'] ?? 'غير محدد') . "\n";
            echo "   - الاسم الكامل: " . trim(($first['CI_FIRST_ARB'] ?? '') . " " . ($first['CI_FATHER_ARB'] ?? '') . " " . ($first['CI_GRAND_FATHER_ARB'] ?? '') . " " . ($first['CI_FAMILY_ARB'] ?? '')) . "\n";
            if (!empty($first['MOTHER_NAME1'])) {
                echo "   - اسم الأم: " . $first['MOTHER_NAME1'] . "\n";
            }
        }
    } else {
        echo "❌ خطأ: " . $result['error'] . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "انتهى الاختبار! 🎯\n";
