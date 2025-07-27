<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CivilRegistryScoutSearchService;

$service = new CivilRegistryScoutSearchService();

echo "اختبار البحث المحسن للاسم: لمياء ابراهيم زياد ابو دحيل\n";
echo str_repeat('=', 60) . "\n";

$result = $service->quickScoutSearch('لمياء ابراهيم زياد ابو دحيل', 20);

if ($result['success']) {
    echo "✅ النتائج: " . $result['total_count'] . " نتيجة\n";
    echo "⚡ الوقت: " . $result['execution_time'] . "\n";

    if ($result['total_count'] > 0) {
        foreach ($result['data'] as $i => $person) {
            $person = (array)$person;
            $fullName = trim(($person['CI_FIRST_ARB'] ?? '') . ' ' .
                            ($person['CI_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_GRAND_FATHER_ARB'] ?? '') . ' ' .
                            ($person['CI_FAMILY_ARB'] ?? ''));

            echo ($i + 1) . ". رقم الهوية: " . ($person['CI_ID_NUM'] ?? 'غير محدد') . "\n";
            echo "   الاسم الكامل: $fullName\n";
            if (!empty($person['MOTHER_NAME1'])) {
                echo "   اسم الأم: " . $person['MOTHER_NAME1'] . "\n";
            }
            echo "\n";
        }
    } else {
        echo "❌ لا توجد نتائج\n";
    }
} else {
    echo "❌ خطأ: " . $result['error'] . "\n";
}
