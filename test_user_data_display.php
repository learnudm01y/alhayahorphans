<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Data Display for User 666665457 ===\n\n";

$identityNumber = '666665457';

// Get sponsorship
$sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();

if (!$sponsorship) {
    echo "✗ No sponsorship found!\n";
    exit;
}

echo "✓ Sponsorship Found (ID: {$sponsorship->id})\n\n";
echo "=== SPONSORSHIP DATA ===\n";
echo "Orphan Name: {$sponsorship->orphan_name}\n";
echo "Guardian Name: {$sponsorship->guardian_name}\n";
echo "Guardian ID: {$sponsorship->guardian_identity_number}\n";
echo "External File: {$sponsorship->external_file_number}\n";
echo "Sponsoring Org: {$sponsorship->sponsoring_organization}\n";
echo "Start Date: {$sponsorship->sponsorship_start_date}\n";
echo "End Date: {$sponsorship->sponsorship_end_date}\n";
echo "Duration: {$sponsorship->sponsorship_duration_months} months\n";
echo "Notes: {$sponsorship->notes}\n\n";

// Get relation data
if ($sponsorship->relation_id_number) {
    echo "=== RELATION DATA (File: {$sponsorship->relation_id_number}) ===\n\n";

    // From data table
    $relationData = App\Models\Data::where('file_id_number', $sponsorship->relation_id_number)->first();
    if ($relationData) {
        echo "--- DATA Table ---\n";
        echo "Full Name: {$relationData->data_first_name} {$relationData->data_father_name} {$relationData->data_grand_father_name} {$relationData->data_family_name}\n";
        echo "ID Number: {$relationData->data_id_number}\n";
        echo "Birth Date: {$relationData->data_birth_date}\n";
        echo "Phone: {$relationData->data_phone_number}\n";
        echo "Address: {$relationData->data_address}\n";
        echo "City ID: {$relationData->data_city}\n";
        echo "Province ID: {$relationData->data_province}\n";
        echo "Health Status ID: {$relationData->data_health_status}\n";
        echo "Marital Status ID: {$relationData->data_marital_status}\n";
        echo "Academic Qualification ID: {$relationData->data_academic_qualification}\n";
        echo "Housing Status ID: {$relationData->data_housing_status}\n";
        echo "Housing Type ID: {$relationData->data_current_housing_type}\n\n";
    }

    // From dead_people table
    $deadPeople = DB::table('dead_people')->where('re_file_id', $sponsorship->relation_id_number)->first();
    if ($deadPeople) {
        echo "--- DEAD_PEOPLE Table ---\n";
        echo "Father: {$deadPeople->father_first_name} {$deadPeople->father_second_name} {$deadPeople->father_third_name} {$deadPeople->father_last_name}\n";
        echo "Father ID: {$deadPeople->father_id}\n";
        echo "Father Death Date: {$deadPeople->father_death_date}\n";
        echo "Mother: {$deadPeople->mother_first_name} {$deadPeople->mother_second_name} {$deadPeople->mother_third_name} {$deadPeople->mother_last_name}\n";
        echo "Mother ID: {$deadPeople->mother_id}\n";
        echo "Mother Death Date: {$deadPeople->mother_death_date}\n\n";
    }

    // From re_people table
    $rePeople = DB::table('re_people')->where('registration_id', $sponsorship->relation_id_number)->first();
    if ($rePeople) {
        echo "--- RE_PEOPLE Table ---\n";
        echo "Name: {$rePeople->first_name} {$rePeople->second_name} {$rePeople->third_name} {$rePeople->last_name}\n";
        echo "Person ID: {$rePeople->person_id}\n";
        echo "Birth Date: {$rePeople->person_birth_date}\n";
        echo "Age: {$rePeople->person_age}\n";
        echo "Gender: {$rePeople->person_gender}\n";
        echo "Health Status: {$rePeople->person_health_status}\n";
        echo "Note: {$rePeople->person_note}\n\n";
    }
}

// Check enabled fields
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();
if ($fieldSettings) {
    echo "=== ENABLED FIELDS ({$fieldSettings->getActiveFieldsCount()}) ===\n";
    $activeFields = $fieldSettings->getActiveFields();
    foreach ($activeFields as $i => $field) {
        echo ($i + 1) . ". $field\n";
        if ($i >= 20) {
            echo "... and " . (count($activeFields) - 21) . " more\n";
            break;
        }
    }
}

echo "\n=== Done ===\n";
