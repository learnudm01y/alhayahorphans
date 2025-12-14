<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking User Login System ===\n\n";

// Check user 666665457
$correctUser = App\Models\User::where('email', '666665457')->first();
if ($correctUser) {
    echo "✓ Correct User Found:\n";
    echo "  - ID: {$correctUser->id}\n";
    echo "  - Email: {$correctUser->email}\n";
    echo "  - Role: {$correctUser->role}\n";
    echo "  - Active: {$correctUser->is_active}\n\n";

    // Check sponsorship
    $sponsorship = App\Models\Sponsorship::where('identity_number', $correctUser->email)
        ->orWhere('internal_file_number', $correctUser->email)
        ->first();

    if ($sponsorship) {
        echo "  ✓ Sponsorship Found: ID {$sponsorship->id}\n";
        echo "    - Name: {$sponsorship->first_name} {$sponsorship->father_name} {$sponsorship->grandfather_name} {$sponsorship->family_name}\n";
        echo "    - File: {$sponsorship->internal_file_number}\n";
        echo "    - Identity: {$sponsorship->identity_number}\n\n";
    } else {
        echo "  ✗ No sponsorship found!\n\n";
    }
} else {
    echo "✗ User 666665457 not found!\n\n";
}

// Check wrong user 407015692
$wrongUser = App\Models\User::where('email', '407015692')->first();
if ($wrongUser) {
    echo "Wrong User (407015692):\n";
    echo "  - ID: {$wrongUser->id}\n";
    echo "  - Email: {$wrongUser->email}\n";
    echo "  - Role: {$wrongUser->role}\n\n";

    $sponsorship = App\Models\Sponsorship::where('identity_number', $wrongUser->email)
        ->orWhere('internal_file_number', $wrongUser->email)
        ->first();

    if ($sponsorship) {
        echo "  - Has sponsorship: ID {$sponsorship->id}\n\n";
    } else {
        echo "  - No sponsorship (THIS IS THE PROBLEM!)\n\n";
    }
}

// Check password for correct user
if ($correctUser) {
    $passwordCheck = Hash::check('002622', $correctUser->password);
    echo "Password Check for 666665457:\n";
    echo "  - Password '002622' is " . ($passwordCheck ? "CORRECT ✓" : "WRONG ✗") . "\n\n";
}

echo "=== Solution ===\n";
echo "You need to login with:\n";
echo "  Username: 666665457\n";
echo "  Password: 002622\n\n";
