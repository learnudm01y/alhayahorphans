<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking Sponsor Field Settings ===\n\n";

// Check if field settings exist for sponsor ID 5
$sponsorId = 5;
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

if ($fieldSettings) {
    echo "✓ Field Settings Found for Sponsor ID: $sponsorId\n\n";

    // Count enabled fields
    $enabled = [];
    $disabled = [];

    $fields = [
        'orphan_name', 'birth_date', 'gender', 'identity_number',
        'guardian_name', 'guardian_phone', 'address', 'city',
        'neighborhood', 'guarantor_name', 'guarantor_relationship',
        'guarantor_phone', 'marital_status', 'residence_type',
        'health_status', 'educational_level', 'notes'
    ];

    foreach ($fields as $field) {
        if (isset($fieldSettings->{$field}) && $fieldSettings->{$field} == 1) {
            $enabled[] = $field;
        } else {
            $disabled[] = $field;
        }
    }

    echo "Enabled Fields (" . count($enabled) . "):\n";
    foreach ($enabled as $field) {
        echo "  ✓ $field\n";
    }

    echo "\nDisabled Fields (" . count($disabled) . "):\n";
    foreach ($disabled as $field) {
        echo "  ✗ $field\n";
    }
} else {
    echo "✗ NO Field Settings Found for Sponsor ID: $sponsorId\n";
    echo "Need to create default field settings!\n\n";

    echo "Creating default field settings (all fields enabled)...\n";

    $created = App\Models\SponsorFieldSetting::create([
        'sponsor_id' => $sponsorId,
        'orphan_name' => 1,
        'birth_date' => 1,
        'gender' => 1,
        'identity_number' => 1,
        'guardian_name' => 1,
        'guardian_phone' => 1,
        'address' => 1,
        'city' => 1,
        'neighborhood' => 1,
        'guarantor_name' => 1,
        'guarantor_relationship' => 1,
        'guarantor_phone' => 1,
        'marital_status' => 1,
        'residence_type' => 1,
        'health_status' => 1,
        'educational_level' => 1,
        'notes' => 1,
    ]);

    if ($created) {
        echo "✓ Default field settings created successfully!\n";
        echo "All fields are now enabled for Sponsor ID: $sponsorId\n";
    } else {
        echo "✗ Failed to create field settings!\n";
    }
}

echo "\n=== Done ===\n";
