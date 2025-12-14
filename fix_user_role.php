<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Fixing User Role ===\n\n";

$user = App\Models\User::where('email', '666665457')->first();

if ($user) {
    echo "Current role: {$user->role}\n";

    $user->role = 2;
    $user->is_active = 1;
    $user->save();

    echo "✓ Updated role to: 2 (sponsorship user)\n";
    echo "✓ Activated user\n\n";

    // Verify
    $user->refresh();
    echo "Verified:\n";
    echo "  - Role: {$user->role}\n";
    echo "  - Active: {$user->is_active}\n";
} else {
    echo "✗ User not found!\n";
}

echo "\n=== Done ===\n";
