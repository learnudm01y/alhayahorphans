<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Cleaning Up Users ===\n\n";

// Check both users
$correctUser = App\Models\User::where('email', '666665457')->first();
$wrongUser = App\Models\User::where('email', '407015692')->first();

echo "Current State:\n";
if ($correctUser) {
    echo "  ✓ Correct User (666665457): ID {$correctUser->id}\n";
}
if ($wrongUser) {
    echo "  ✗ Wrong User (407015692): ID {$wrongUser->id}\n";
}

// Delete wrong user if exists
if ($wrongUser) {
    echo "\nDeleting wrong user (407015692)...\n";
    $wrongUser->delete();
    echo "  ✓ Deleted!\n";
}

// Ensure correct user exists and is properly configured
if ($correctUser) {
    DB::table('users')
        ->where('id', $correctUser->id)
        ->update([
            'email_verified_at' => now(),
            'role' => 'user',
            'updated_at' => now()
        ]);
    echo "\n✓ Updated correct user\n";
} else {
    echo "\n✗ Correct user not found - will be created on login\n";
}

echo "\n=== Final State ===\n";
$users = App\Models\User::whereIn('email', ['666665457', '407015692'])->get();
foreach ($users as $user) {
    echo "User ID {$user->id}:\n";
    echo "  - Email: {$user->email}\n";
    echo "  - Role: {$user->role}\n";
    echo "  - Verified: " . ($user->email_verified_at ? "Yes" : "No") . "\n\n";
}

echo "=== Now Logout and Login Again ===\n";
echo "1. Logout completely\n";
echo "2. Clear browser cache/cookies\n";
echo "3. Login with:\n";
echo "   Username: 666665457\n";
echo "   Password: 002622\n";
