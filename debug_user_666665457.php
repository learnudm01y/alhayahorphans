<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Quick Debug for User 666665457 ===\n\n";

$identityNumber = '666665457';

// Check user
$user = App\Models\User::where('email', $identityNumber)->first();
echo "1. User Check:\n";
if ($user) {
    echo "   ✓ User found: {$user->name} (ID: {$user->id})\n";
    echo "   ✓ Email: {$user->email}\n\n";
} else {
    echo "   ✗ User NOT found!\n\n";
}

// Check sponsorship - try different methods
echo "2. Sponsorship Search:\n";

$sponsorship1 = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();
echo "   By identity_number ($identityNumber): " . ($sponsorship1 ? "✓ FOUND (ID: {$sponsorship1->id})" : "✗ NOT FOUND") . "\n";

$sponsorship2 = App\Models\Sponsorship::where('internal_file_number', '002622')->first();
echo "   By internal_file_number (002622): " . ($sponsorship2 ? "✓ FOUND (ID: {$sponsorship2->id})" : "✗ NOT FOUND") . "\n";

// Check what controller is actually doing
echo "\n3. Controller Logic Simulation:\n";
$userIdNumber = $user ? $user->email : null;
if ($userIdNumber) {
    echo "   Using userIdNumber from Auth::user()->email: $userIdNumber\n";

    $sponsorship = App\Models\Sponsorship::where('identity_number', $userIdNumber)->first();

    if ($sponsorship) {
        echo "   ✓ Sponsorship FOUND!\n";
        echo "   - ID: {$sponsorship->id}\n";
        echo "   - Orphan Name: {$sponsorship->orphan_name}\n";
        echo "   - Identity Number: {$sponsorship->identity_number}\n";
        echo "   - Internal File: {$sponsorship->internal_file_number}\n";
        echo "   - Sponsor ID: {$sponsorship->sponsor_id}\n";
    } else {
        echo "   ✗ Sponsorship NOT FOUND!\n";

        // Check if data exists in old data table
        $data = App\Models\Data::where('data_id_number', $userIdNumber)->first();
        if ($data) {
            echo "   ! But found in DATA table (File: {$data->file_id_number})\n";
        }
    }
}

// List all sponsorships with similar identity numbers
echo "\n4. All Sponsorships with similar identity:\n";
$allSponsors = App\Models\Sponsorship::where('identity_number', 'LIKE', '%666665457%')
    ->orWhere('identity_number', 'LIKE', '%002622%')
    ->get();

if ($allSponsors->count() > 0) {
    foreach ($allSponsors as $s) {
        echo "   - ID: {$s->id}, Identity: {$s->identity_number}, File: {$s->internal_file_number}, Name: {$s->orphan_name}\n";
    }
} else {
    echo "   No sponsorships found!\n";
}

echo "\n=== Done ===\n";
