<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use App\Models\Sponsorship;

echo "=== فحص نوع السكن ===\n\n";

$sponsorship = Sponsorship::find(198);
if (!$sponsorship) {
    echo "❌ لم يتم العثور على الكفالة\n";
    exit;
}

$job = new GenerateOrphanReportPdf($sponsorship->id);
$ref = new ReflectionClass($job);
$method = $ref->getMethod('collectReportData');
$method->setAccessible(true);
$reportData = $method->invokeArgs($job, [$sponsorship]);

echo "الكفالة ID: {$sponsorship->id}\n";
echo "relation_id_number: {$sponsorship->relation_id_number}\n\n";

echo "بيانات السكن في التقرير:\n";
echo str_repeat("-", 50) . "\n";
echo "housing_status: " . ($reportData['housing_status'] ?? 'غير موجود') . "\n";
echo "housing_type: " . ($reportData['housing_type'] ?? 'غير موجود') . "\n";
echo "current_housing_type: " . ($reportData['current_housing_type'] ?? 'غير موجود') . "\n";
echo str_repeat("-", 50) . "\n";

if (isset($reportData['housing_type']) && $reportData['housing_type'] !== 'غير متوفر' && $reportData['housing_type'] !== '/|\\') {
    echo "✅ نوع السكن موجود: {$reportData['housing_type']}\n";
} else {
    echo "❌ نوع السكن غير متوفر\n";

    // فحص البيانات الأولية
    echo "\nفحص المصادر:\n";

    // فحص portal fields
    $portalFields = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', $sponsorship->id)
        ->get()
        ->keyBy('field_key');

    echo "Portal Fields:\n";
    $housingTypeField = $portalFields->get('field_housing_type');
    if ($housingTypeField) {
        echo "  field_housing_type: {$housingTypeField->field_value}\n";
    } else {
        echo "  ❌ field_housing_type غير موجود\n";
    }

    // فحص data table
    $dataRecord = DB::table('data')
        ->where('relation_id_number', $sponsorship->relation_id_number)
        ->orWhere('relation_id_number', $sponsorship->internal_file_number)
        ->first();

    if ($dataRecord && isset($dataRecord->data_current_housing_type)) {
        echo "\nData Table:\n";
        echo "  data_current_housing_type ID: {$dataRecord->data_current_housing_type}\n";

        // جلب الوصف من جدول type_of_accommodation
        $housingTypeDesc = DB::table('type_of_accommodation')
            ->where('id', $dataRecord->data_current_housing_type)
            ->value('description');
        echo "  الوصف: " . ($housingTypeDesc ?? 'غير موجود') . "\n";
    }
}
