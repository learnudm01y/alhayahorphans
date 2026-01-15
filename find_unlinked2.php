<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$unlinked = DB::table('sponsorships')
    ->where(function($q) {
        $q->whereNull('relation_id_number')
          ->orWhere('relation_id_number', '');
    })
    ->whereNotNull('internal_file_number')
    ->where('internal_file_number', '!=', '')
    ->limit(5)
    ->get(['internal_file_number', 'person_type', 'orphan_name']);

echo "=== كفالات غير مربوطة ===\n";
foreach ($unlinked as $s) {
    echo "internal: {$s->internal_file_number} - type: {$s->person_type} - {$s->orphan_name}\n";
}
if (count($unlinked) == 0) {
    echo "لا توجد كفالات غير مربوطة\n";
}
