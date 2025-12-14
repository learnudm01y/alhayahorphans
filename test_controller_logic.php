<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Controller Logic Manually ===\n\n";

// Simulate logged-in user
$user = App\Models\User::where('email', '666665457')->first();

if (!$user) {
    echo "✗ User not found!\n";
    exit;
}

echo "✓ User: {$user->name}\n";
echo "  Email: {$user->email}\n\n";

// Simulate controller logic
$userIdNumber = $user->email;

echo "Searching for sponsorship with identity_number = $userIdNumber\n\n";

$sponsorship = App\Models\Sponsorship::with([
    'sponsor',
    'relationData.province',
    'relationData.city',
    'relationData.healthStatus',
    'relationData.maritalStatus',
    'relationData.academicQualification',
    'relationData.housingStatus',
    'relationData.currentHousingType',
    'relationData.employmentStatusBreadwinner',
    'relationData.categoryOfRelation',
    'relationData.rePeople',
    'relationData.deadPepole',
])
->where('identity_number', $userIdNumber)
->first();

if (!$sponsorship) {
    echo "✗ Sponsorship NOT FOUND!\n";
    echo "This is what the user sees on the page.\n\n";

    // Try without relationships
    $sponsorshipSimple = App\Models\Sponsorship::where('identity_number', $userIdNumber)->first();
    if ($sponsorshipSimple) {
        echo "! But sponsorship EXISTS in database!\n";
        echo "  Problem might be with relationships loading.\n";
    }
    exit;
}

echo "✓ Sponsorship FOUND!\n";
echo "  ID: {$sponsorship->id}\n";
echo "  Orphan Name: {$sponsorship->orphan_name}\n";
echo "  Sponsor ID: {$sponsorship->sponsor_id}\n";
echo "  Sponsor Name: " . (optional($sponsorship->sponsor)->sponsor_name ?? 'NULL') . "\n\n";

// Check field settings
$sponsorId = $sponsorship->sponsor_id;
echo "Checking field settings for sponsor_id = $sponsorId\n";

$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

if (!$fieldSettings) {
    echo "✗ Field settings NOT FOUND!\n";
} else {
    echo "✓ Field settings FOUND\n";
    echo "  Active fields: {$fieldSettings->getActiveFieldsCount()}\n\n";
}

// Check relation data
echo "Checking relation data:\n";
if ($sponsorship->relationData) {
    echo "✓ relationData loaded\n";
    echo "  Guardian: {$sponsorship->relationData->data_first_name}\n";
} else {
    echo "✗ relationData NOT loaded\n";
    echo "  relation_id_number: {$sponsorship->relation_id_number}\n";
}

echo "\n=== Controller should work correctly! ===\n";
