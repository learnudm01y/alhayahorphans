<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== التحقق من بيانات المعيل في sponsorships ===" . PHP_EOL . PHP_EOL;

// جلب كفالة لها guardian_name
$s = DB::table('sponsorships')
    ->whereNotNull('guardian_name')
    ->where('guardian_name', '!=', '')
    ->first();

if ($s) {
    echo "✅ وجدنا كفالة بها guardian_name:" . PHP_EOL;
    echo "   ID: " . $s->id . PHP_EOL;
    echo "   guardian_name: " . $s->guardian_name . PHP_EOL;
    echo "   guardian_identity_number: " . ($s->guardian_identity_number ?? 'NULL') . PHP_EOL;
    echo "   internal_file_number: " . ($s->internal_file_number ?? 'NULL') . PHP_EOL;
    echo "   relation_id_number: " . ($s->relation_id_number ?? 'NULL') . PHP_EOL;

    // البحث في data
    if ($s->internal_file_number) {
        $data = DB::table('data')->where('file_id_number', $s->internal_file_number)->first();
        if ($data) {
            echo PHP_EOL . "✅ وجدنا المعيل في data:" . PHP_EOL;
            echo "   data_first_name: " . ($data->data_first_name ?? 'NULL') . PHP_EOL;
            echo "   data_father_name: " . ($data->data_father_name ?? 'NULL') . PHP_EOL;
            echo "   data_family_name: " . ($data->data_family_name ?? 'NULL') . PHP_EOL;
            echo "   data_phone_number: " . ($data->data_phone_number ?? 'NULL') . PHP_EOL;
        } else {
            echo PHP_EOL . "❌ لم نجد في data بـ file_id_number" . PHP_EOL;
        }
    }
} else {
    echo "❌ لم نجد كفالة بها guardian_name" . PHP_EOL;
}

// إحصائيات
echo PHP_EOL . "📊 إحصائيات guardian:" . PHP_EOL;
$withGuardianName = DB::table('sponsorships')
    ->whereNotNull('guardian_name')
    ->where('guardian_name', '!=', '')
    ->count();

$withGuardianId = DB::table('sponsorships')
    ->whereNotNull('guardian_identity_number')
    ->where('guardian_identity_number', '!=', '')
    ->count();

echo "   كفالات لها guardian_name: $withGuardianName" . PHP_EOL;
echo "   كفالات لها guardian_identity_number: $withGuardianId" . PHP_EOL;

// البحث عن أول كفالة لها بيانات معيل في data عبر internal_file_number
echo PHP_EOL . "=== البحث عن كفالة مع معيل في data ===" . PHP_EOL;
$match = DB::table('sponsorships as s')
    ->join('data as d', 'd.file_id_number', '=', 's.internal_file_number')
    ->whereNotNull('d.data_first_name')
    ->where('d.data_first_name', '!=', '')
    ->select('s.id', 's.internal_file_number', 'd.data_first_name', 'd.data_family_name', 'd.data_phone_number')
    ->first();

if ($match) {
    echo "✅ وجدنا كفالة مع معيل في data:" . PHP_EOL;
    echo "   Sponsorship ID: " . $match->id . PHP_EOL;
    echo "   internal_file_number: " . $match->internal_file_number . PHP_EOL;
    echo "   data_first_name: " . $match->data_first_name . PHP_EOL;
    echo "   data_family_name: " . $match->data_family_name . PHP_EOL;
    echo "   data_phone_number: " . $match->data_phone_number . PHP_EOL;
} else {
    echo "❌ لم نجد كفالة مع معيل في data" . PHP_EOL;
}
