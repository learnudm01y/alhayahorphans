<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// فحص الكفالة
$s = DB::table('sponsorships')->first();
if ($s) {
    echo "ID: " . $s->id . "\n";
    echo "identity_number: " . $s->identity_number . "\n";
    echo "sponsored_birth_date: " . ($s->sponsored_birth_date ?? 'null') . "\n";

    // تحديث بتاريخ ميلاد
    DB::table('sponsorships')->where('id', $s->id)->update(['sponsored_birth_date' => '2010-05-15']);

    $s2 = DB::table('sponsorships')->where('id', $s->id)->first();
    echo "بعد التحديث - sponsored_birth_date: " . ($s2->sponsored_birth_date ?? 'null') . "\n";
} else {
    echo "No sponsorship found\n";
}
