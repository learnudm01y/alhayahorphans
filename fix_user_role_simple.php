<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Fixing User Role (Simple) ===\n\n";

$user = App\Models\User::where('email', '666665457')->first();

if ($user) {
    echo "Current role: {$user->role}\n";

    $user->role = 2;
    $user->save();

    echo "✓ Updated role to: 2 (sponsorship user)\n\n";

    // Verify
    $user->refresh();
    echo "Verified - Role: {$user->role}\n";
} else {
    echo "✗ User not found!\n";
}

echo "\n=== Done ===\n";
echo "\nNow login with:\n";
echo "  Username: 666665457\n";
echo "  Password: 002622\n";
