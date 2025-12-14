<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Creating User for Sponsorship ===\n\n";

$identityNumber = '666665457';
$password = '002622';

// Check if user exists
$user = App\Models\User::where('email', $identityNumber)->first();

if ($user) {
    echo "✓ User already exists (ID: {$user->id})\n";
} else {
    echo "Creating new user...\n";

    // Get sponsorship data
    $sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();

    if (!$sponsorship) {
        echo "✗ Sponsorship not found for identity_number: $identityNumber\n";
        exit;
    }

    $user = App\Models\User::create([
        'name' => $sponsorship->orphan_name,
        'email' => $identityNumber,
        'password' => bcrypt($password),
        'role' => 2, // User role
    ]);

    echo "✓ User created successfully!\n";
    echo "   - ID: {$user->id}\n";
    echo "   - Name: {$user->name}\n";
    echo "   - Email: {$user->email}\n";
}

echo "\nLOGIN CREDENTIALS:\n";
echo "  Username: $identityNumber\n";
echo "  Password: $password\n";

echo "\n=== Done ===\n";
