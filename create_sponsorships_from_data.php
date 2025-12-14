<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Creating Sponsorship Records from Data ===\n\n";

// Get all data records that don't have sponsorships
$dataRecords = App\Models\Data::whereNotNull('data_id_number')
    ->whereNotNull('file_id_number')
    ->get();

echo "Found {$dataRecords->count()} data records\n\n";

$created = 0;
$skipped = 0;
$errors = 0;

foreach ($dataRecords as $data) {
    // Check if sponsorship exists
    $existingSponsorship = App\Models\Sponsorship::where('internal_file_number', $data->file_id_number)->first();

    if ($existingSponsorship) {
        $skipped++;
        continue;
    }

    try {
        // Get default sponsor (first one)
        $defaultSponsor = App\Models\Sponsor::first();

        if (!$defaultSponsor) {
            echo "✗ No sponsors found in database! Please create at least one sponsor.\n";
            break;
        }

        // Create sponsorship
        $sponsorship = App\Models\Sponsorship::create([
            'internal_file_number' => $data->file_id_number,
            'identity_number' => $data->data_id_number,
            'orphan_name' => trim("{$data->data_first_name} {$data->data_father_name} {$data->data_grand_father_name} {$data->data_family_name}"),
            'birth_date' => $data->data_birth_date,
            'guardian_name' => $data->re_guardian_name ?? 'غير محدد',
            'guardian_phone' => $data->data_phone_number,
            'sponsor_id' => $defaultSponsor->id, // Assign to default sponsor
            'start_date' => now(),
        ]);

        $created++;
        echo "✓ Created sponsorship for file: {$data->file_id_number} (ID: {$sponsorship->id})\n";

    } catch (\Exception $e) {
        $errors++;
        echo "✗ Error creating sponsorship for file {$data->file_id_number}: {$e->getMessage()}\n";
    }
}

echo "\n=== Summary ===\n";
echo "Created: {$created}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Errors: {$errors}\n";
echo "\n=== Done ===\n";
