<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Sponsorship;
use Illuminate\Support\Facades\DB;

echo "=== فحص guardian_relation_id في sponsorships ===\n\n";

$sponsorship = Sponsorship::find(1295);

if ($sponsorship) {
    echo "✅ معلومات الكفالة:\n";
    echo "  - ID: {$sponsorship->id}\n";
    echo "  - اسم اليتيم: {$sponsorship->orphan_name}\n";
    echo "  - اسم المعيل: {$sponsorship->guardian_name}\n";
    echo "  - guardian_identity_number: {$sponsorship->guardian_identity_number}\n";
    echo "  - guardian_relation_id: " . ($sponsorship->guardian_relation_id ?? 'NULL') . "\n";
    echo "\n";

    // فحص الحقول الأخرى
    echo "📊 الحقول الأخرى:\n";
    echo "  - relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n";
    echo "  - internal_file_number: {$sponsorship->internal_file_number}\n";
    echo "\n";

    // فحص portal fields
    echo "🔍 فحص portal_general_registration_field_values:\n";
    $portalFields = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', 1295)
        ->whereIn('field_key', [
            'field_guardian_relationship',
            'field_mother_status',
            'field_guardian_health',
            'field_guardian_job'
        ])
        ->get();

    if ($portalFields->isNotEmpty()) {
        foreach ($portalFields as $field) {
            echo "  - {$field->field_key}: {$field->field_value}\n";
        }
    } else {
        echo "  ❌ لا توجد حقول\n";
    }
}
