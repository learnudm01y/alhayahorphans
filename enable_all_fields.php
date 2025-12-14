<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Enabling All Fields for Sponsor ID: 5 ===\n\n";

$sponsorId = 5;
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

if ($fieldSettings) {
    $fieldSettings->update([
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

    echo "✓ All fields have been enabled!\n";
    echo "✓ Total: 17 fields enabled\n";
} else {
    echo "✗ Field settings not found!\n";
}

echo "\n=== Done ===\n";
