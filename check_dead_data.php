<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص بيانات المتوفين للكفالة ===\n\n";

$identityNumber = '666665457';
$internalFileNumber = '002671';
$relationIdNumber = '002190';

echo "رقم الهوية: $identityNumber\n";
echo "رقم الملف الداخلي: $internalFileNumber\n";
echo "رقم العلاقة: $relationIdNumber\n\n";

// 1. البحث في sponsorships
echo "=== الكفالة ===\n";
$sponsorship = DB::table('sponsorships')
    ->where('identity_number', $identityNumber)
    ->where('internal_file_number', $internalFileNumber)
    ->first();

if ($sponsorship) {
    echo "الكفالة #" . $sponsorship->id . ":\n";
    echo "  - relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n";
} else {
    echo "لم يتم العثور على الكفالة\n";
}

// 2. البحث في dead_people باستخدام re_file_id
echo "\n=== dead_people (باستخدام relation_id_number = $relationIdNumber) ===\n";
$deadPeople = DB::table('dead_people')
    ->where('re_file_id', $relationIdNumber)
    ->first();

if ($deadPeople) {
    echo "dead_people:\n";
    echo "  - re_file_id: " . $deadPeople->re_file_id . "\n";
    echo "  - father_first_name: " . ($deadPeople->father_first_name ?? 'NULL') . "\n";
    echo "  - father_second_name: " . ($deadPeople->father_second_name ?? 'NULL') . "\n";
    echo "  - father_third_name: " . ($deadPeople->father_third_name ?? 'NULL') . "\n";
    echo "  - father_last_name: " . ($deadPeople->father_last_name ?? 'NULL') . "\n";
    echo "  - father_id: " . ($deadPeople->father_id ?? 'NULL') . "\n";
    echo "  - father_death_date: " . ($deadPeople->father_death_date ?? 'NULL') . "\n";
    echo "  - father_death_reason: " . ($deadPeople->father_death_reason ?? 'NULL') . "\n";
    echo "  - mother_first_name: " . ($deadPeople->mother_first_name ?? 'NULL') . "\n";
    echo "  - mother_id: " . ($deadPeople->mother_id ?? 'NULL') . "\n";
    echo "  - mother_death_date: " . ($deadPeople->mother_death_date ?? 'NULL') . "\n";
} else {
    echo "لم يتم العثور على dead_people بـ re_file_id = $relationIdNumber\n";
}

// 3. البحث في data
echo "\n=== data (باستخدام file_id_number = $relationIdNumber) ===\n";
$dataRecord = DB::table('data')
    ->where('file_id_number', $relationIdNumber)
    ->first();

if ($dataRecord) {
    echo "data:\n";
    echo "  - file_id_number: " . $dataRecord->file_id_number . "\n";
    echo "  - data_id_number: " . ($dataRecord->data_id_number ?? 'NULL') . "\n";
    echo "  - data_first_name: " . ($dataRecord->data_first_name ?? 'NULL') . "\n";
}

// 4. البحث في re_people
echo "\n=== re_people (باستخدام registration_id = $relationIdNumber) ===\n";
$rePeople = DB::table('re_people')
    ->where('registration_id', $relationIdNumber)
    ->get();

foreach ($rePeople as $re) {
    echo "  - person_id: " . ($re->person_id ?? 'NULL') . " | name: " . ($re->first_name ?? '') . " " . ($re->last_name ?? '') . "\n";
}

echo "\n=== انتهى ===\n";
