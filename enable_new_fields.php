<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Enabling All New Fields for Sponsor ID: 5 ===\n\n";

$sponsorId = 5;
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

if ($fieldSettings) {
    $newFields = [
        // From sponsorships table
        'field_sponsoring_organization' => 1,
        'field_external_file_number' => 1,
        'field_guardian_identity_number' => 1,
        'field_sponsorship_duration_months' => 1,
        'field_sponsorship_start_date' => 1,
        'field_sponsorship_end_date' => 1,
        'field_sponsorship_type_id' => 1,
        'field_sponsorship_status_id' => 1,
        // From data table
        'field_data_id_number' => 1,
        'field_data_first_name' => 1,
        'field_data_father_name' => 1,
        'field_data_grand_father_name' => 1,
        'field_data_family_name' => 1,
        'field_data_birth_date' => 1,
        'field_data_phone_number' => 1,
        'field_data_address' => 1,
        'field_data_city' => 1,
        'field_data_neighborhood' => 1,
        'field_re_guardian_name' => 1,
        'field_re_guardian_phone' => 1,
        'field_re_guardian_id' => 1,
        // From dead_people table
        'field_father_first_name' => 1,
        'field_father_id' => 1,
        'field_father_death_reason' => 1,
        'field_mother_first_name' => 1,
        'field_mother_id' => 1,
        'field_mother_death_date' => 1,
        'field_mother_death_reason' => 1,
        // From re_people table
        'field_first_name' => 1,
        'field_person_id' => 1,
        'field_person_birth_date' => 1,
        'field_person_age' => 1,
        'field_person_gender' => 1,
        'field_person_health_status' => 1,
        'field_person_type_of_guarantee' => 1,
        'field_person_note' => 1,
    ];

    $fieldSettings->update($newFields);

    echo "✓ All new fields have been enabled!\n";
    echo "✓ Total new fields enabled: " . count($newFields) . "\n";
    echo "\n✓ Total fields now: " . ($fieldSettings->getActiveFieldsCount()) . " enabled fields\n";
} else {
    echo "✗ Field settings not found for Sponsor ID: $sponsorId!\n";
}

echo "\n=== Done ===\n";
