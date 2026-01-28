<?php
// Test Health Status Update for Person 444183149

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Testing Health Status Update for Person 444183149\n";
echo "====================================================\n\n";

// Check current status in re_people
echo "1️⃣ Current status in re_people table:\n";
$rePerson = DB::table('re_people')
    ->where('person_id', '444183149')
    ->first();

if ($rePerson) {
    echo "   ✅ Found person in re_people:\n";
    echo "   - ID: {$rePerson->id}\n";
    echo "   - Registration ID: {$rePerson->registration_id}\n";
    echo "   - Person Health Status: " . ($rePerson->person_health_status ?? 'NULL') . "\n";

    if ($rePerson->person_health_status) {
        $healthStatus = DB::table('health_statuses')
            ->where('id', $rePerson->person_health_status)
            ->first();
        echo "   - Health Status Description: " . ($healthStatus->description ?? 'N/A') . "\n";
    }
} else {
    echo "   ❌ Person not found in re_people table\n";
}

echo "\n2️⃣ Checking sponsorship record:\n";
$sponsorship = DB::table('sponsorships')
    ->where('identity_number', '444183149')
    ->first();

if ($sponsorship) {
    echo "   ✅ Found sponsorship:\n";
    echo "   - ID: {$sponsorship->id}\n";
    echo "   - Person Type: {$sponsorship->person_type}\n";
    $sponsorshipId = $sponsorship->id;
} else {
    echo "   ❌ No sponsorship found\n";
    $sponsorshipId = 0;
}

echo "\n3️⃣ Available health statuses in database:\n";
$healthStatuses = DB::table('health_statuses')->get();
foreach ($healthStatuses as $status) {
    echo "   - ID: {$status->id} => {$status->description}\n";
}

echo "\n====================================================\n";
echo "✅ Test completed\n";
echo "\n📝 Summary:\n";
echo "   - Person 444183149 is in re_people (ID: {$rePerson->id})\n";
echo "   - Current health status: " . ($rePerson->person_health_status ?? 'NULL') . "\n";
echo "   - Person type: " . ($sponsorship->person_type ?? 'N/A') . "\n";
echo "\n💡 Next step: Submit the form to test if UPDATE works\n";
