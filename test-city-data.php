<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// اختبار بيانات المدينة
$records = App\Models\Data::with(['city', 'province'])
    ->whereNotNull('data_city')
    ->limit(5)
    ->get();

echo "اختبار بيانات المدينة والمحافظة:\n";
echo "====================================\n\n";

foreach ($records as $record) {
    echo "Record ID: " . $record->id . "\n";
    echo "الاسم: " . $record->data_first_name . " " . $record->data_father_name . "\n";
    echo "data_city (رقم): " . $record->data_city . "\n";
    echo "City relation (اسم): " . ($record->city ? $record->city->description : 'غير موجود') . "\n";
    echo "data_province (رقم): " . $record->data_province . "\n";
    echo "Province relation (اسم): " . ($record->province ? $record->province->description : 'غير موجود') . "\n";
    echo "---\n\n";
}

// اختبار SearchService
echo "اختبار SearchService:\n";
echo "===================\n\n";

$searchService = new App\Services\SearchService();
$results = $searchService->smartSearch(['search_type' => 'all'], 3);

if (!empty($results['main_records'])) {
    $firstRecord = $results['main_records'][0];
    echo "أول نتيجة بحث:\n";
    echo "الاسم: " . $firstRecord['full_name'] . "\n";
    echo "المدينة: " . ($firstRecord['city_name'] ?? 'غير محدد') . "\n";
    echo "المحافظة: " . ($firstRecord['province_name'] ?? 'غير محدد') . "\n";
}

?>
