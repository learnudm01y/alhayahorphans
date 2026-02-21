<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\DeadPepole;

echo "=== فحص dead_people للكفالة 1295 ===\n\n";

$relationIdNumber = '003537';

$deadPeople = DeadPepole::where('re_file_id', $relationIdNumber)->first();

if ($deadPeople) {
    echo "✅ تم العثور على سجل:\n";
    echo "  - re_file_id: {$deadPeople->re_file_id}\n";
    echo "  - father_id: " . ($deadPeople->father_id ?? 'NULL') . "\n";
    echo "  - mother_id: " . ($deadPeople->mother_id ?? 'NULL') . "\n";
    echo "  - relationship_id: " . ($deadPeople->relationship_id ?? 'NULL') . "\n\n";

    // بيانات الأب
    echo "📋 بيانات الأب المتوفي:\n";
    echo "  - father_first_name: " . ($deadPeople->father_first_name ?? 'NULL') . "\n";
    echo "  - father_second_name: " . ($deadPeople->father_second_name ?? 'NULL') . "\n";
    echo "  - father_third_name: " . ($deadPeople->father_third_name ?? 'NULL') . "\n";
    echo "  - father_last_name: " . ($deadPeople->father_last_name ?? 'NULL') . "\n\n";

    // بيانات الأم
    echo "📋 بيانات الأم:\n";
    echo "  - mother_first_name: " . ($deadPeople->mother_first_name ?? 'NULL') . "\n";
    echo "  - mother_second_name: " . ($deadPeople->mother_second_name ?? 'NULL') . "\n";
    echo "  - mother_third_name: " . ($deadPeople->mother_third_name ?? 'NULL') . "\n";
    echo "  - mother_last_name: " . ($deadPeople->mother_last_name ?? 'NULL') . "\n\n";

    // تحديد المعيل
    echo "🔍 تحديد المعيل:\n";
    $guardianIdentity = '803315837';

    if ($deadPeople->mother_id == $guardianIdentity) {
        echo "  ✅ المعيل هو الأم (mother_id مطابق)\n";
    } elseif ($deadPeople->father_id == $guardianIdentity) {
        echo "  ⚠️ المعيل هو الأب المتوفي (father_id مطابق)\n";
    } else {
        echo "  ⚠️ المعيل ليس الأب ول⁣ا الأم في جدول dead_people\n";
        echo "  - guardian_identity: {$guardianIdentity}\n";
        echo "  - father_id: {$deadPeople->father_id}\n";
        echo "  - mother_id: " . ($deadPeople->mother_id ?? 'NULL') . "\n";
    }
} else {
    echo "❌ لا يوجد سجل\n";
}
