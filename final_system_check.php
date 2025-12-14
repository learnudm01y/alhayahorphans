<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== FINAL SYSTEM CHECK ===\n\n";

// 1. Check user credentials
$username = '666665457';
$password = '002622';

$user = App\Models\User::where('email', $username)->first();

echo "1. USER CHECK:\n";
if ($user) {
    echo "   ✓ User found (ID: {$user->id})\n";
    echo "   ✓ Email: {$user->email}\n";
} else {
    echo "   ✗ User NOT found!\n";
    exit;
}

// 2. Check sponsorship
$sponsorship = App\Models\Sponsorship::where('identity_number', $username)->first();

echo "\n2. SPONSORSHIP CHECK:\n";
if ($sponsorship) {
    echo "   ✓ Sponsorship found (ID: {$sponsorship->id})\n";
    echo "   ✓ Sponsor ID: {$sponsorship->sponsor_id}\n";
    echo "   ✓ Internal File: {$sponsorship->internal_file_number}\n";
    echo "   ✓ Orphan Name: {$sponsorship->orphan_name}\n";
} else {
    echo "   ✗ Sponsorship NOT found!\n";
    exit;
}

// 3. Check field settings
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorship->sponsor_id)->first();

echo "\n3. FIELD SETTINGS CHECK:\n";
if ($fieldSettings) {
    $activeCount = $fieldSettings->getActiveFieldsCount();
    echo "   ✓ Field settings found\n";
    echo "   ✓ Active fields: $activeCount\n";
} else {
    echo "   ✗ Field settings NOT found!\n";
}

// 4. Check relation data
echo "\n4. RELATION DATA CHECK:\n";
if ($sponsorship->relation_id_number) {
    echo "   ✓ Relation file: {$sponsorship->relation_id_number}\n";

    $relationData = App\Models\Data::where('file_id_number', $sponsorship->relation_id_number)->first();
    if ($relationData) {
        echo "   ✓ Data found: {$relationData->data_first_name} {$relationData->data_father_name}\n";
        echo "   ✓ Phone: {$relationData->data_phone_number}\n";

        // Check dead people
        $dead = DB::table('dead_people')->where('re_file_id', $sponsorship->relation_id_number)->first();
        echo "   " . ($dead ? "✓" : "✗") . " Dead people record: " . ($dead ? "Found" : "Not found") . "\n";

        // Check re people
        $re = DB::table('re_people')->where('registration_id', $sponsorship->relation_id_number)->first();
        echo "   " . ($re ? "✓" : "✗") . " Re people record: " . ($re ? "Found" : "Not found") . "\n";
    } else {
        echo "   ✗ Data NOT found!\n";
    }
} else {
    echo "   ✗ No relation_id_number set!\n";
}

// 5. Check config
echo "\n5. CONFIG CHECK:\n";
$fields = config('sponsor_fields.fields');
$categories = config('sponsor_fields.categories');
echo "   ✓ Total fields in config: " . count($fields) . "\n";
echo "   ✓ Total categories: " . count($categories) . "\n";

// 6. Sample field values
echo "\n6. SAMPLE DATA EXTRACTION:\n";
if ($sponsorship->relationData) {
    echo "   ✓ Orphan Name (sponsorship): {$sponsorship->orphan_name}\n";
    echo "   ✓ Guardian Name (data): {$sponsorship->relationData->data_first_name} {$sponsorship->relationData->data_father_name}\n";
    echo "   ✓ Guardian Phone (data): {$sponsorship->relationData->data_phone_number}\n";
    echo "   ✓ Guardian ID (data): {$sponsorship->relationData->data_id_number}\n";
}

echo "\n✓ SYSTEM CHECK COMPLETE!\n";
echo "\nRECOMMENDATIONS:\n";
echo "1. Login with: $username / $password\n";
echo "2. Navigate to: http://127.0.0.1:8000/user/general-registration\n";
echo "3. You should see $activeCount enabled fields with actual data\n";
echo "\n=== Done ===\n";
