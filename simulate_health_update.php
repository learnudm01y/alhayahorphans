<?php
// Simulate Health Status Update for Person 444183149

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔧 Simulating Health Status Update\n";
echo "====================================================\n\n";

// استخدام نفس الدالة من Controller
function resolveLookupIdByDescription(string $table, string $description): ?int
{
    $result = DB::table($table)
        ->where('description', $description)
        ->first();

    if ($result && isset($result->id)) {
        return (int) $result->id;
    }

    return null;
}

// محاكاة التحديث
$personId = '444183149';
$healthStatusText = 'سليم'; // أو أي قيمة أخرى

echo "1️⃣ Converting health status text to ID...\n";
$healthStatusId = resolveLookupIdByDescription('health_statuses', $healthStatusText);

if ($healthStatusId !== null) {
    echo "   ✅ Found health status ID: {$healthStatusId} for '{$healthStatusText}'\n\n";

    echo "2️⃣ Finding re_people record...\n";
    $rePerson = DB::table('re_people')
        ->where('person_id', $personId)
        ->first();

    if ($rePerson) {
        echo "   ✅ Found re_people record (ID: {$rePerson->id})\n";
        echo "   - Current health status: " . ($rePerson->person_health_status ?? 'NULL') . "\n\n";

        echo "3️⃣ Updating health status...\n";
        $updated = DB::table('re_people')
            ->where('id', $rePerson->id)
            ->update([
                'person_health_status' => $healthStatusId,
                'updated_at' => now(),
            ]);

        if ($updated) {
            echo "   ✅ Successfully updated!\n\n";

            echo "4️⃣ Verifying update...\n";
            $updatedPerson = DB::table('re_people')
                ->where('person_id', $personId)
                ->first();

            echo "   - New health status: {$updatedPerson->person_health_status}\n";

            $healthStatus = DB::table('health_statuses')
                ->where('id', $updatedPerson->person_health_status)
                ->first();
            echo "   - Description: " . ($healthStatus->description ?? 'N/A') . "\n";
        } else {
            echo "   ❌ Update failed (no rows affected)\n";
        }
    } else {
        echo "   ❌ Person not found in re_people\n";
    }
} else {
    echo "   ❌ Could not find health status ID for '{$healthStatusText}'\n";
}

echo "\n====================================================\n";
echo "✅ Simulation completed\n";
