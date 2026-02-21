<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// البحث عن الكفالة 805
$sponsorshipId = 805;

echo "=== فحص حقول Portal لكفالة {$sponsorshipId} ===\n\n";

$fields = DB::table('portal_general_registration_field_values')
    ->where('sponsorship_id', $sponsorshipId)
    ->orderBy('field_key')
    ->get();

echo "عدد الحقول: " . $fields->count() . "\n\n";

foreach ($fields as $field) {
    $value = $field->field_value ?? '(فارغ)';
    echo "{$field->field_key}: {$value}\n";
}
