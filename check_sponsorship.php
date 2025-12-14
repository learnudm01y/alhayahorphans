<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Sponsorship Data ===\n\n";

$identityNumber = '3865168918';

// Check User
$user = App\Models\User::where('email', $identityNumber)->first();
if ($user) {
    echo "✓ User Found: ID = {$user->id}, Email = {$user->email}\n\n";
} else {
    echo "✗ User NOT Found\n\n";
}

// Check Sponsorship
$sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)
    ->with('sponsor', 'relationData')
    ->first();

if ($sponsorship) {
    echo "✓ Sponsorship Found:\n";
    echo "  - ID: {$sponsorship->id}\n";
    echo "  - Sponsor ID: " . ($sponsorship->sponsor_id ?? 'NULL') . "\n";
    echo "  - Internal File: {$sponsorship->internal_file_number}\n";
    echo "  - Orphan Name: {$sponsorship->orphan_name}\n";

    if ($sponsorship->sponsor) {
        echo "  - Sponsor Name: {$sponsorship->sponsor->sponsor_name}\n";
    } else {
        echo "  - ✗ Sponsor NOT FOUND (NULL or missing)\n";
    }

    if ($sponsorship->relationData) {
        echo "  - ✓ Relation Data exists (file_id_number: {$sponsorship->relationData->file_id_number})\n";
    } else {
        echo "  - ✗ Relation Data NOT FOUND\n";
    }

    echo "\n";

    // Check Field Settings
    if ($sponsorship->sponsor_id) {
        $fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();

        if ($fieldSettings) {
            echo "✓ Field Settings Found for Sponsor ID {$sponsorship->sponsor_id}\n";

            // Count enabled fields
            $enabledCount = 0;
            $fieldsConfig = config('sponsor_fields.fields', []);

            foreach ($fieldsConfig as $fieldKey => $fieldInfo) {
                if (isset($fieldSettings->{$fieldKey}) && $fieldSettings->{$fieldKey} == 1) {
                    $enabledCount++;
                }
            }

            echo "  - Total Fields Config: " . count($fieldsConfig) . "\n";
            echo "  - Enabled Fields: {$enabledCount}\n";

            if ($enabledCount > 0) {
                echo "  - Sample Enabled Fields:\n";
                $count = 0;
                foreach ($fieldsConfig as $fieldKey => $fieldInfo) {
                    if (isset($fieldSettings->{$fieldKey}) && $fieldSettings->{$fieldKey} == 1 && $count < 5) {
                        echo "    * {$fieldInfo['display_name']} ({$fieldKey})\n";
                        $count++;
                    }
                }
            }
        } else {
            echo "✗ NO Field Settings Found for Sponsor ID {$sponsorship->sponsor_id}\n";
            echo "  This is the PROBLEM - no field settings exist!\n";
        }
    } else {
        echo "✗ Sponsorship has NULL sponsor_id - Cannot load field settings\n";
    }
} else {
    echo "✗ NO Sponsorship Found for identity_number: {$identityNumber}\n";
}

echo "\n=== End Check ===\n";
