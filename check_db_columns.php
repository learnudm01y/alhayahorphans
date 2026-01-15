<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== أعمدة جدول data ===" . PHP_EOL;
$cols = DB::select('SHOW COLUMNS FROM data');
foreach($cols as $c) {
    echo $c->Field . PHP_EOL;
}

echo PHP_EOL . "=== أعمدة جدول re_people ===" . PHP_EOL;
$cols = DB::select('SHOW COLUMNS FROM re_people');
foreach($cols as $c) {
    echo $c->Field . PHP_EOL;
}

echo PHP_EOL . "=== البحث عن كفالة مع معيل عبر relation_id_number ===" . PHP_EOL;

// أولاً نحصل على كفالة لها relation_id_number
$s = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->first();

if ($s) {
    echo "الكفالة: " . $s->id . PHP_EOL;
    echo "internal_file_number: " . ($s->internal_file_number ?? 'NULL') . PHP_EOL;
    echo "relation_id_number: " . $s->relation_id_number . PHP_EOL;

    // البحث في re_people بـ registration_id
    $guardian = DB::table('re_people')
        ->where('registration_id', $s->relation_id_number)
        ->first();

    if ($guardian) {
        echo PHP_EOL . "✅ وجدنا المعيل في re_people:" . PHP_EOL;
        echo "   first_name: " . ($guardian->first_name ?? 'NULL') . PHP_EOL;
        echo "   second_name: " . ($guardian->second_name ?? 'NULL') . PHP_EOL;
        echo "   last_name: " . ($guardian->last_name ?? 'NULL') . PHP_EOL;
    } else {
        echo "❌ لم نجد المعيل في re_people" . PHP_EOL;
    }
}
