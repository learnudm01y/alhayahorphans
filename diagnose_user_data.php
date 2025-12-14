<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Diagnosing User Data (666665457 / 002622) ===\n\n";

// 1. Check Sponsorship
$sponsorship = App\Models\Sponsorship::where('identity_number', '666665457')->first();

if ($sponsorship) {
    echo "✓ Sponsorship Found:\n";
    echo "  - ID: {$sponsorship->id}\n";
    echo "  - Internal File: {$sponsorship->internal_file_number}\n";
    echo "  - Orphan Name: {$sponsorship->orphan_name}\n";
    echo "  - Birth Date: {$sponsorship->birth_date}\n";
    echo "  - Guardian Name: {$sponsorship->guardian_name}\n";
    echo "  - Guardian Phone: {$sponsorship->guardian_phone}\n";
    echo "  - Sponsor ID: {$sponsorship->sponsor_id}\n";
    echo "  - Relation ID Number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n\n";

    // 2. Check Relation Data
    if ($sponsorship->relation_id_number) {
        echo "=== Searching for Relation Data (relation_id_number: {$sponsorship->relation_id_number}) ===\n\n";

        // Check in data table (by file_id_number)
        $data = App\Models\Data::where('file_id_number', $sponsorship->relation_id_number)->first();
        if ($data) {
            echo "✓ Found in DATA table:\n";
            echo "  - File: {$data->file_id_number}\n";
            echo "  - ID Number: {$data->data_id_number}\n";
            echo "  - Name: {$data->data_first_name} {$data->data_father_name} {$data->data_family_name}\n";
            echo "  - Phone: {$data->data_phone_number}\n";
            echo "  - Birth Date: {$data->data_birth_date}\n";
            echo "  - Address: {$data->data_address}\n";
            echo "  - City: {$data->data_city}\n";
            echo "  - Guardian: {$data->re_guardian_name}\n";
            echo "  - Guardian Phone: {$data->re_guardian_phone}\n\n";
        } else {
            echo "✗ NOT found in DATA table\n\n";
        }

        // Check in dead_people table (by re_file_id)
        $deadPerson = DB::table('dead_people')->where('re_file_id', $sponsorship->relation_id_number)->first();
        if ($deadPerson) {
            echo "✓ Found in DEAD_PEOPLE table:\n";
            echo "  - Father: {$deadPerson->father_first_name} {$deadPerson->father_second_name} {$deadPerson->father_third_name} {$deadPerson->father_last_name}\n";
            echo "  - Father ID: {$deadPerson->father_id}\n";
            echo "  - Father Death Date: {$deadPerson->father_death_date}\n";
            echo "  - Mother: {$deadPerson->mother_first_name} {$deadPerson->mother_second_name} {$deadPerson->mother_third_name} {$deadPerson->mother_last_name}\n";
            echo "  - Mother ID: {$deadPerson->mother_id}\n";
            echo "  - Mother Death Date: {$deadPerson->mother_death_date}\n\n";
        } else {
            echo "✗ NOT found in DEAD_PEOPLE table\n\n";
        }

        // Check in re_people table (by registration_id)
        $rePerson = DB::table('re_people')->where('registration_id', $sponsorship->relation_id_number)->first();
        if ($rePerson) {
            echo "✓ Found in RE_PEOPLE table:\n";
            echo "  - Name: {$rePerson->first_name} {$rePerson->second_name} {$rePerson->third_name} {$rePerson->last_name}\n";
            echo "  - Person ID: {$rePerson->person_id}\n";
            echo "  - Birth Date: {$rePerson->person_birth_date}\n";
            echo "  - Age: {$rePerson->person_age}\n";
            echo "  - Gender: {$rePerson->person_gender}\n";
            echo "  - Health Status: {$rePerson->person_health_status}\n";
            echo "  - Note: {$rePerson->person_note}\n\n";
        } else {
            echo "✗ NOT found in RE_PEOPLE table\n\n";
        }
    } else {
        echo "✗ No relation_id_number set!\n\n";
    }

    // 3. Check all columns in sponsorship
    echo "=== All Sponsorship Columns ===\n";
    $attributes = $sponsorship->getAttributes();
    foreach ($attributes as $key => $value) {
        if ($value !== null && $value !== '') {
            echo "  ✓ $key: $value\n";
        } else {
            echo "  ✗ $key: (empty)\n";
        }
    }

} else {
    echo "✗ Sponsorship NOT Found for identity_number: 666665457\n";
}

echo "\n=== Done ===\n";
