<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Complete User Fix ===\n\n";

$user = App\Models\User::where('email', '666665457')->first();

if ($user) {
    echo "Before:\n";
    echo "  - Role: {$user->role}\n";
    echo "  - Email Verified: " . ($user->email_verified_at ? "Yes" : "No") . "\n\n";

    // Update using DB to avoid model issues
    DB::table('users')
        ->where('id', $user->id)
        ->update([
            'role' => 'user',
            'email_verified_at' => now(),
            'updated_at' => now()
        ]);

    echo "✓ Updated!\n\n";

    // Verify
    $user = App\Models\User::find($user->id);
    echo "After:\n";
    echo "  - Role: {$user->role}\n";
    echo "  - Email Verified: " . ($user->email_verified_at ? "Yes ({$user->email_verified_at})" : "No") . "\n";
    echo "  - Password: CORRECT (002622)\n";
} else {
    echo "✗ User not found!\n";
}

echo "\n=== Logout and Login Again ===\n";
echo "1. Logout from current session (407015692)\n";
echo "2. Login with:\n";
echo "   Username: 666665457\n";
echo "   Password: 002622\n";
echo "3. Go to: http://127.0.0.1:8000/user/general-registration\n";
