<?php
// اختبار سريع لفحص بيانات التقرير

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Sponsorship;
use App\Models\PortalGeneralRegistrationFieldValue;
use Illuminate\Support\Facades\DB;

// جلب sponsorship
$sponsorship = Sponsorship::find(198);

if (!$sponsorship) {
    echo "Sponsorship not found!\n";
    exit;
}

echo "=== Sponsorship Data ===\n";
echo "ID: {$sponsorship->id}\n";
echo "Orphan Name: {$sponsorship->orphan_name}\n";
echo "Identity: {$sponsorship->identity_number}\n";
echo "File Number: {$sponsorship->internal_file_number}\n";
echo "Relation ID: {$sponsorship->relation_id_number}\n\n";

echo "=== Portal Field Values ===\n";
$fields = PortalGeneralRegistrationFieldValue::where('sponsorship_id', 198)->get();
echo "Total Fields: " . $fields->count() . "\n";

if ($fields->count() > 0) {
    foreach($fields as $f) {
        echo "{$f->field_key}: {$f->field_value}\n";
    }
} else {
    echo "No portal fields found!\n";

    // جرب البحث برقم الملف
    $fields2 = PortalGeneralRegistrationFieldValue::where('file_id_number', $sponsorship->internal_file_number)->get();
    echo "\nSearching by file_id_number: " . $fields2->count() . " found\n";

    // جرب البحث برقم الهوية
    $fields3 = PortalGeneralRegistrationFieldValue::where('identity_number', $sponsorship->identity_number)->get();
    echo "Searching by identity_number: " . $fields3->count() . " found\n";
}

echo "\n=== Data Record ===\n";
$dataRecord = DB::table('data')->where('file_id_number', $sponsorship->internal_file_number)->first();
if ($dataRecord) {
    echo "Phone: {$dataRecord->data_phone_number}\n";
    echo "City: {$dataRecord->data_city}\n";
    echo "Province: {$dataRecord->data_province}\n";
} else {
    echo "No data record found!\n";
}

echo "\n=== Dead People Record ===\n";
$deadPeople = DB::table('dead_people')->where('re_file_id', $sponsorship->relation_id_number ?? $sponsorship->internal_file_number)->first();
if ($deadPeople) {
    echo "Mother: {$deadPeople->mother_first_name} {$deadPeople->mother_last_name}\n";
    echo "Father: {$deadPeople->father_first_name} {$deadPeople->father_last_name}\n";
} else {
    echo "No dead people record found!\n";
}
