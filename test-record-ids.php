<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "فحص السجلات وال IDs:\n";
echo "=====================\n";

// اختبار SearchService
$searchService = new App\Services\SearchService();
$results = $searchService->smartSearch(['search_type' => 'all'], 3);

if (!empty($results['main_records'])) {
    echo "السجلات الرئيسية:\n";
    foreach (array_slice($results['main_records'], 0, 3) as $record) {
        echo "- ID: " . ($record['id'] ?? 'غير موجود') . "\n";
        echo "  الاسم: " . ($record['full_name'] ?? 'غير محدد') . "\n";
        echo "  رقم الهوية: " . ($record['identity_number'] ?? 'غير محدد') . "\n";
        echo "---\n";
    }
}

if (!empty($results['family_members'])) {
    echo "\nأفراد الأسرة:\n";
    foreach (array_slice($results['family_members'], 0, 3) as $record) {
        echo "- ID: " . ($record['id'] ?? 'غير موجود') . "\n";
        echo "  الاسم: " . ($record['full_name'] ?? 'غير محدد') . "\n";
        echo "---\n";
    }
}

if (!empty($results['deceased'])) {
    echo "\nالمتوفين:\n";
    foreach (array_slice($results['deceased'], 0, 3) as $record) {
        echo "- ID: " . ($record['id'] ?? 'غير موجود') . "\n";
        echo "  الاسم: " . ($record['full_name'] ?? 'غير محدد') . "\n";
        echo "---\n";
    }
}

?>
