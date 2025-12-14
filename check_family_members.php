<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== فحص أفراد الأسرة ===\n\n";

// Get sponsorship 71
$sponsorship = DB::table('sponsorships')->where('id', 71)->first();
if (!$sponsorship) {
    echo "❌ لم يتم العثور على الكفالة 71\n";
    exit;
}

echo "1. الكفالة 71:\n";
echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
echo "   - relation_id_number: {$sponsorship->relation_id_number}\n\n";

// Get data record
$data = DB::table('data')->where('file_id_number', $sponsorship->relation_id_number)->first();
if (!$data) {
    echo "❌ لم يتم العثور على سجل data\n";
    exit;
}

echo "2. سجل المعيل:\n";
echo "   - file_id_number: {$data->file_id_number}\n\n";

// Get family members using registration_id
echo "3. أفراد الأسرة من re_people (باستخدام registration_id):\n";
$familyMembers = DB::table('re_people')
    ->where('registration_id', $data->file_id_number)
    ->get();

if ($familyMembers->count() > 0) {
    foreach ($familyMembers as $index => $member) {
        echo "   [" . ($index + 1) . "] {$member->first_name} {$member->second_name} {$member->third_name} {$member->last_name}\n";
        echo "       - person_id: {$member->person_id}\n";
        echo "       - person_age: " . ($member->person_age ?? 'NULL') . "\n";
        echo "       - person_gender: " . ($member->person_gender ?? 'NULL') . "\n";
        echo "       - person_birth_date: " . ($member->person_birth_date ?? 'NULL') . "\n";
        echo "       - registration_id: {$member->registration_id}\n\n";
    }
} else {
    echo "   ❌ لم يتم العثور على أفراد أسرة\n\n";
}

// Try with internal_file_number
echo "4. محاولة البحث باستخدام internal_file_number:\n";
$familyMembers2 = DB::table('re_people')
    ->where('registration_id', $sponsorship->internal_file_number)
    ->get();

if ($familyMembers2->count() > 0) {
    echo "   ✓ تم العثور على {$familyMembers2->count()} فرد\n";
    foreach ($familyMembers2 as $index => $member) {
        echo "   [" . ($index + 1) . "] {$member->first_name} {$member->second_name} - العمر: " . ($member->person_age ?? 'NULL') . "\n";
    }
} else {
    echo "   ❌ لم يتم العثور على أفراد\n";
}

echo "\n=== انتهى الفحص ===\n";
