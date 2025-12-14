<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار منطق تحديد نوع الشخص ===\n\n";

$identityNumber = '666665457';

echo "البحث عن رقم الهوية: {$identityNumber}\n\n";

// 1. data
$dataByIdentity = DB::table('data')->where('data_id_number', $identityNumber)->first();
if ($dataByIdentity) {
    echo "✓ موجود في data (معيل):\n";
    echo "  - file_id_number: {$dataByIdentity->file_id_number}\n";
    echo "  - data_housing_status: {$dataByIdentity->data_housing_status}\n";
    echo "  - data_current_housing_type: {$dataByIdentity->data_current_housing_type}\n\n";
} else {
    echo "✗ غير موجود في data\n\n";
}

// 2. dead_people
$deadPerson = DB::table('dead_people')
    ->where('father_id', $identityNumber)
    ->orWhere('mother_id', $identityNumber)
    ->first();
if ($deadPerson) {
    echo "✓ موجود في dead_people (متوفي):\n";
    echo "  - re_file_id: {$deadPerson->re_file_id}\n\n";

    $guardianData = DB::table('data')->where('file_id_number', $deadPerson->re_file_id)->first();
    if ($guardianData) {
        echo "  ✓ بيانات المعيل:\n";
        echo "    - file_id_number: {$guardianData->file_id_number}\n";
        echo "    - data_housing_status: {$guardianData->data_housing_status}\n";
        echo "    - data_current_housing_type: {$guardianData->data_current_housing_type}\n\n";
    }
} else {
    echo "✗ غير موجود في dead_people\n\n";
}

// 3. re_people
$rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
if ($rePerson) {
    echo "✓ موجود في re_people (فرد أسرة):\n";
    echo "  - registration_id: {$rePerson->registration_id}\n";
    echo "  - person_health_status: {$rePerson->person_health_status}\n\n";

    $guardianData = DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
    if ($guardianData) {
        echo "  ✓ بيانات المعيل:\n";
        echo "    - file_id_number: {$guardianData->file_id_number}\n";
        echo "    - الاسم: {$guardianData->data_first_name} {$guardianData->data_father_name}\n";
        echo "    - data_housing_status: {$guardianData->data_housing_status}\n";
        echo "    - data_current_housing_type: {$guardianData->data_current_housing_type}\n\n";

        // حالة السكن
        $housingStatus = DB::table('housing_status')->where('id', $guardianData->data_housing_status)->first();
        echo "    - حالة السكن: " . ($housingStatus->description ?? 'N/A') . "\n";

        // نوع السكن
        $housingType = DB::table('type_of_accommodation')->where('id', $guardianData->data_current_housing_type)->first();
        echo "    - نوع السكن: " . ($housingType->description ?? 'N/A') . "\n\n";
    }

    // الحالة الصحية
    $healthStatus = DB::table('health_statuses')->where('id', $rePerson->person_health_status)->first();
    echo "  ✓ الحالة الصحية للشخص: " . ($healthStatus->description ?? 'N/A') . "\n";
} else {
    echo "✗ غير موجود في re_people\n\n";
}

echo "\n=== تم ===\n";
