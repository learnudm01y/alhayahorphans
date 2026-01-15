<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص الربط بين الجداول ===\n\n";

// جلب عينة من الكفالات
$sponsorships = DB::table('sponsorships')->limit(5)->get(['id', 'internal_file_number', 'identity_number', 'relation_id_number', 'orphan_name', 'guardian_name']);

foreach ($sponsorships as $s) {
    echo "📋 Sponsorship ID: {$s->id}\n";
    echo "   orphan_name: {$s->orphan_name}\n";
    echo "   guardian_name: {$s->guardian_name}\n";
    echo "   internal_file_number: {$s->internal_file_number}\n";
    echo "   identity_number: {$s->identity_number}\n";
    echo "   relation_id_number: {$s->relation_id_number}\n";

    // فحص في data
    if ($s->internal_file_number) {
        $dataExists = DB::table('data')->where('file_id_number', $s->internal_file_number)->exists();
        echo "   → data (file_id_number): " . ($dataExists ? "✅" : "❌") . "\n";
    }

    // فحص في re_people
    if ($s->identity_number) {
        $rePeopleExists = DB::table('re_people')->where('person_id', $s->identity_number)->exists();
        echo "   → re_people (person_id): " . ($rePeopleExists ? "✅" : "❌") . "\n";
    }

    // فحص في re_people بـ registration_id
    if ($s->relation_id_number) {
        $rePeopleExists2 = DB::table('re_people')->where('registration_id', $s->relation_id_number)->exists();
        echo "   → re_people (registration_id): " . ($rePeopleExists2 ? "✅" : "❌") . "\n";
    }

    // فحص الحسابات البنكية
    if ($s->relation_id_number) {
        $bankCount = DB::table('guardian_bank_accounts')->where('guardian_registration', $s->relation_id_number)->count();
        echo "   → bank_accounts (guardian_registration): {$bankCount}\n";
    }

    echo "\n";
}

// فحص عينة من data
echo "\n=== عينة من file_id_number في data ===\n";
$dataSample = DB::table('data')->limit(5)->pluck('file_id_number');
foreach ($dataSample as $fid) {
    echo "   - {$fid}\n";
}

// فحص عينة من re_people
echo "\n=== عينة من person_id في re_people ===\n";
$rePeopleSample = DB::table('re_people')->limit(5)->pluck('person_id');
foreach ($rePeopleSample as $pid) {
    echo "   - {$pid}\n";
}

// فحص عينة من registration_id
echo "\n=== عينة من registration_id في re_people ===\n";
$regIdSample = DB::table('re_people')->whereNotNull('registration_id')->limit(5)->pluck('registration_id');
foreach ($regIdSample as $rid) {
    echo "   - {$rid}\n";
}
