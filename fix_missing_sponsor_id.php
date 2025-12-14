<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Fixing Missing sponsor_id ===\n\n";

// Find sponsorships with empty sponsor_id
$sponsorships = App\Models\Sponsorship::whereNull('sponsor_id')->get();

echo "Found {$sponsorships->count()} sponsorships without sponsor_id\n\n";

// Get default sponsor
$defaultSponsor = App\Models\Sponsor::find(5);
if (!$defaultSponsor) {
    echo "✗ Default sponsor (ID: 5) not found!\n";
    exit;
}

echo "Using default sponsor: {$defaultSponsor->id} - {$defaultSponsor->sponsor_name}\n\n";

$updated = 0;
foreach ($sponsorships as $sponsorship) {
    $sponsorship->sponsor_id = $defaultSponsor->id;
    $sponsorship->save();
    $updated++;

    if ($updated % 100 == 0) {
        echo "Updated: $updated...\n";
    }
}

echo "\n✓ Updated $updated sponsorships with sponsor_id = {$defaultSponsor->id}\n";
echo "\n=== Done ===\n";
