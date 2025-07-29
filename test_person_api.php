<?php
// اختبار بسيط لجلب بيانات من جدول data
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing data table:\n";
echo "Total records: " . DB::table('data')->count() . "\n";

echo "\nSample records:\n";
$samples = DB::table('data')
    ->select('data_id_number', 'data_first_name', 'data_father_name', 'data_family_name')
    ->whereNotNull('data_id_number')
    ->take(5)
    ->get();

foreach ($samples as $sample) {
    echo "ID: " . $sample->data_id_number . " - Name: " .
         trim(($sample->data_first_name ?: '') . ' ' .
              ($sample->data_father_name ?: '') . ' ' .
              ($sample->data_family_name ?: '')) . "\n";
}

echo "\nTesting API endpoint:\n";
$testId = $samples->first()->data_id_number ?? '9';
echo "Using ID: $testId\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/person-name/$testId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
