<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص بيانات الكفالة للهوية 666665457 ===\n\n";

// البحث في sponsorships
$sponsorship = DB::table('sponsorships')
    ->where('identity_number', '666665457')
    ->first();

if ($sponsorship) {
    echo "الكفالة #" . $sponsorship->id . ":\n";
    echo "  - internal_file_number: " . ($sponsorship->internal_file_number ?? 'NULL') . "\n";
    echo "  - relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n";
    echo "  - orphan_name: " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
} else {
    echo "لم يتم العثور على كفالة\n";
}

echo "\n=== البحث في re_people ===\n";
$rePerson = DB::table('re_people')
    ->where('person_id', '666665457')
    ->first();

if ($rePerson) {
    echo "re_people:\n";
    echo "  - registration_id: " . ($rePerson->registration_id ?? 'NULL') . "\n";
    echo "  - first_name: " . ($rePerson->first_name ?? 'NULL') . "\n";
}

echo "\n=== جميع الكفالات للهوية 666665457 ===\n";
$allSponsorships = DB::table('sponsorships')
    ->where('identity_number', '666665457')
    ->get();

foreach ($allSponsorships as $s) {
    echo "  - ID: {$s->id}, internal: " . ($s->internal_file_number ?? 'NULL') . ", relation: " . ($s->relation_id_number ?? 'NULL') . "\n";
}

echo "\n=== انتهى ===\n";
