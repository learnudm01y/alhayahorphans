<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار أفراد الأسرة بعد التعديل ===\n\n";

$sponsorship = DB::table('sponsorships')->where('id', 71)->first();
$data = DB::table('data')->where('file_id_number', $sponsorship->relation_id_number)->first();

echo "1. المكفول:\n";
echo "   - identity_number: {$sponsorship->identity_number}\n";
echo "   - relation_id_number: {$sponsorship->relation_id_number}\n\n";

echo "2. جميع أفراد re_people بنفس registration_id:\n";
$allPeople = DB::table('re_people')
    ->where('registration_id', $data->file_id_number)
    ->get();

foreach ($allPeople as $index => $person) {
    $isSponsoredPerson = ($person->person_id == $sponsorship->identity_number) ? ' (المكفول) ⚠️' : '';
    echo "   [" . ($index + 1) . "] {$person->first_name} {$person->second_name}{$isSponsoredPerson}\n";
    echo "       - person_id: {$person->person_id}\n";
    echo "       - person_age: " . ($person->person_age ?? 'NULL') . "\n";
}

echo "\n3. أفراد الأسرة (بعد استبعاد المكفول):\n";
$familyMembers = DB::table('re_people')
    ->where('registration_id', $data->file_id_number)
    ->where('person_id', '!=', $sponsorship->identity_number)
    ->get();

if ($familyMembers->count() > 0) {
    foreach ($familyMembers as $index => $member) {
        echo "   [" . ($index + 1) . "] {$member->first_name} {$member->second_name} {$member->third_name} {$member->last_name}\n";
        echo "       - العمر: " . ($member->person_age ?? 'NULL') . "\n";
    }
} else {
    echo "   ✓ لا يوجد أفراد أسرة آخرين (صحيح)\n";
}

echo "\n=== انتهى الاختبار ===\n";
