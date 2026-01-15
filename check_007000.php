<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$s = DB::table('sponsorships')->where('internal_file_number', '007000')->first();
if ($s) {
    echo "موجودة\n";
    echo "  - person_type: " . ($s->person_type ?? 'NULL') . "\n";
    echo "  - relation_id_number: " . ($s->relation_id_number ?? 'NULL') . "\n";
    echo "  - orphan_name: " . ($s->orphan_name ?? 'NULL') . "\n";
    echo "  - guardian_name: " . ($s->guardian_name ?? 'NULL') . "\n";
} else {
    echo "غير موجودة - يرجى إنشاؤها أولاً\n";
}
