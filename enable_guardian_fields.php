<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تفعيل الحقول الجديدة في sponsor_field_settings ===\n\n";

// تحديث جميع السجلات لتفعيل الحقول
$updated = DB::table('sponsor_field_settings')->update([
    'field_current_guardian' => 1,
    'field_relationship' => 1,
    'field_guardian_health' => 1,
    'field_guardian_job' => 1,
    'field_guardian_job_text' => 1,
    'field_dependents_female' => 1,
    'field_dependents_male' => 1,
]);

echo "✅ تم تحديث {$updated} سجل\n\n";

// عرض الحقول الحالية
$settings = DB::table('sponsor_field_settings')->first();
if ($settings) {
    echo "حالة الحقول:\n";
    echo "- field_current_guardian: " . ($settings->field_current_guardian ?? 'N/A') . "\n";
    echo "- field_relationship: " . ($settings->field_relationship ?? 'N/A') . "\n";
    echo "- field_guardian_health: " . ($settings->field_guardian_health ?? 'N/A') . "\n";
    echo "- field_guardian_job: " . ($settings->field_guardian_job ?? 'N/A') . "\n";
    echo "- field_guardian_job_text: " . ($settings->field_guardian_job_text ?? 'N/A') . "\n";
    echo "- field_dependents_female: " . ($settings->field_dependents_female ?? 'N/A') . "\n";
    echo "- field_dependents_male: " . ($settings->field_dependents_male ?? 'N/A') . "\n";
}

echo "\n✅ تم التفعيل بنجاح!\n";
