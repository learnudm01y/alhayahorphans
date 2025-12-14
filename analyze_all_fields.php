<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Comprehensive Field Analysis ===\n\n";

// Get all columns from data table
$dataColumns = DB::select("SHOW COLUMNS FROM data");
$dataFields = array_map(fn($col) => $col->Field, $dataColumns);

// Get all columns from dead_people table
$deadColumns = DB::select("SHOW COLUMNS FROM dead_people");
$deadFields = array_map(fn($col) => $col->Field, $deadColumns);

// Get all columns from re_people table
$reColumns = DB::select("SHOW COLUMNS FROM re_people");
$reFields = array_map(fn($col) => $col->Field, $reColumns);

// Get all columns from sponsorships table
$sponsorColumns = DB::select("SHOW COLUMNS FROM sponsorships");
$sponsorFields = array_map(fn($col) => $col->Field, $sponsorColumns);

// Get current sponsor_field_settings fields
$fieldSettingsModel = new App\Models\SponsorFieldSetting();
$currentFields = $fieldSettingsModel->getFillable();

echo "=== AVAILABLE FIELDS IN TABLES ===\n\n";

echo "DATA table ({count($ fields)}):\n";
$relevantDataFields = array_filter($dataFields, fn($f) => !in_array($f, ['id', 'created_at', 'updated_at']));
foreach ($relevantDataFields as $field) {
    echo "  - $field\n";
}

echo "\nDEAD_PEOPLE table (" . count($deadFields) . "):\n";
$relevantDeadFields = array_filter($deadFields, fn($f) => !in_array($f, ['id', 'created_at', 'updated_at']));
foreach ($relevantDeadFields as $field) {
    echo "  - $field\n";
}

echo "\nRE_PEOPLE table (" . count($reFields) . "):\n";
$relevantReFields = array_filter($reFields, fn($f) => !in_array($f, ['id', 'created_at', 'updated_at']));
foreach ($relevantReFields as $field) {
    echo "  - $field\n";
}

echo "\nSPONSORSHIPS table (" . count($sponsorFields) . "):\n";
$relevantSponsorFields = array_filter($sponsorFields, fn($f) => !in_array($f, ['id', 'created_at', 'updated_at', 'sponsor_id', 'created_by', 'updated_by']));
foreach ($relevantSponsorFields as $field) {
    echo "  - $field\n";
}

echo "\n\n=== CURRENT SPONSOR_FIELD_SETTINGS FIELDS ===\n";
echo "Total: " . (count($currentFields) - 1) . " (excluding sponsor_id)\n\n";
foreach ($currentFields as $field) {
    if ($field !== 'sponsor_id') {
        echo "  - $field\n";
    }
}

echo "\n\n=== MISSING FIELDS (Need to be added) ===\n\n";

// Map data table fields to possible missing fields
$missingFields = [];

// From sponsorships
$sponsorshipImportant = [
    'sponsoring_organization',
    'external_file_number',
    'guardian_identity_number',
    'sponsorship_duration_months',
    'sponsorship_start_date',
    'sponsorship_end_date',
    'sponsorship_type_id',
    'sponsorship_status_id',
];

foreach ($sponsorshipImportant as $field) {
    $fieldName = 'field_' . $field;
    if (!in_array($fieldName, $currentFields)) {
        $missingFields[] = $fieldName . " (from sponsorships)";
    }
}

// From data table - important fields
$dataImportant = [
    'data_id_number',
    'data_first_name',
    'data_father_name',
    'data_grand_father_name',
    'data_family_name',
    'data_birth_date',
    'data_phone_number',
    'data_address',
    'data_city',
    'data_neighborhood',
    're_guardian_name',
    're_guardian_phone',
    're_guardian_id',
];

foreach ($dataImportant as $field) {
    $fieldName = 'field_' . $field;
    if (!in_array($fieldName, $currentFields)) {
        $missingFields[] = $fieldName . " (from data)";
    }
}

// From dead_people
$deadImportant = [
    'father_first_name',
    'father_id',
    'father_death_date',
    'father_death_reason',
    'mother_first_name',
    'mother_id',
    'mother_death_date',
    'mother_death_reason',
];

foreach ($deadImportant as $field) {
    $fieldName = 'field_' . $field;
    if (!in_array($fieldName, $currentFields)) {
        $missingFields[] = $fieldName . " (from dead_people)";
    }
}

// From re_people
$reImportant = [
    'first_name',
    'person_id',
    'person_birth_date',
    'person_age',
    'person_gender',
    'person_health_status',
    'person_type_of_guarantee',
    'person_note',
];

foreach ($reImportant as $field) {
    $fieldName = 'field_' . $field;
    if (!in_array($fieldName, $currentFields)) {
        $missingFields[] = $fieldName . " (from re_people)";
    }
}

if (count($missingFields) > 0) {
    echo "Found " . count($missingFields) . " missing fields:\n\n";
    foreach ($missingFields as $field) {
        echo "  ✗ $field\n";
    }
} else {
    echo "✓ No missing fields!\n";
}

echo "\n=== Done ===\n";
