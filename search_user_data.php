<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Searching for User Data ===\n\n";

$identityNumber = '3865168918';

// Search in Data table
$data = App\Models\Data::where('data_id_number', $identityNumber)
    ->orWhere('data_id_number', 'LIKE', "%{$identityNumber}%")
    ->first();

if ($data) {
    echo "✓ Data Found:\n";
    echo "  - File ID: {$data->file_id_number}\n";
    echo "  - ID Number: {$data->data_id_number}\n";
    echo "  - Name: {$data->data_first_name} {$data->data_father_name} {$data->data_grand_father_name} {$data->data_family_name}\n";
    echo "  - Phone: {$data->data_phone_number}\n\n";

    // Check if sponsorship exists for this file_id
    $sponsorshipByFile = App\Models\Sponsorship::where('internal_file_number', $data->file_id_number)->first();

    if ($sponsorshipByFile) {
        echo "✓ Sponsorship exists by file number:\n";
        echo "  - ID: {$sponsorshipByFile->id}\n";
        echo "  - Identity: {$sponsorshipByFile->identity_number}\n";
        echo "  - Sponsor ID: " . ($sponsorshipByFile->sponsor_id ?? 'NULL') . "\n\n";

        if ($sponsorshipByFile->identity_number != $identityNumber) {
            echo "⚠ PROBLEM: Sponsorship identity_number ({$sponsorshipByFile->identity_number}) != User login ({$identityNumber})\n";
            echo "  Need to update sponsorship.identity_number to match!\n";
        }
    } else {
        echo "✗ No Sponsorship for this file number\n";
        echo "  Need to create Sponsorship record!\n";
    }
} else {
    echo "✗ No Data found in 'data' table\n\n";

    // Search in Sponsorship by partial match
    echo "Searching Sponsorships with similar identity...\n";
    $sponsorships = App\Models\Sponsorship::where('identity_number', 'LIKE', "%{$identityNumber}%")
        ->orWhere('orphan_name', 'LIKE', "%الفرا%")
        ->limit(5)
        ->get();

    if ($sponsorships->count() > 0) {
        echo "Found {$sponsorships->count()} similar sponsorships:\n";
        foreach ($sponsorships as $s) {
            echo "  - ID: {$s->id} | Identity: {$s->identity_number} | Name: {$s->orphan_name} | Sponsor: {$s->sponsor_id}\n";
        }
    } else {
        echo "No sponsorships found\n";
    }
}

echo "\n=== End Search ===\n";
