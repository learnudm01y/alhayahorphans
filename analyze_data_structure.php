<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تحليل بنية البيانات ===" . PHP_EOL . PHP_EOL;

// جلب كفالة
$s = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->first();

echo "📋 بيانات الكفالة:" . PHP_EOL;
echo "   ID: " . $s->id . PHP_EOL;
echo "   internal_file_number: " . ($s->internal_file_number ?? 'NULL') . PHP_EOL;
echo "   relation_id_number: " . $s->relation_id_number . PHP_EOL;
echo "   identity_number: " . ($s->identity_number ?? 'NULL') . PHP_EOL;

// ما هي الأعمدة التي قد تربط بالمعيل في sponsorships؟
echo PHP_EOL . "🔍 أعمدة sponsorships المتعلقة بالمعيل:" . PHP_EOL;
$cols = DB::select('SHOW COLUMNS FROM sponsorships');
foreach($cols as $c) {
    if (stripos($c->Field, 'guardian') !== false ||
        stripos($c->Field, 'relation') !== false ||
        stripos($c->Field, 'internal') !== false ||
        stripos($c->Field, 'file') !== false) {
        echo "   " . $c->Field . PHP_EOL;
    }
}

// البحث في جدول data باستخدام file_id_number = internal_file_number
echo PHP_EOL . "🔗 البحث عن المعيل في data باستخدام file_id_number:" . PHP_EOL;
$dataGuardian = DB::table('data')
    ->where('file_id_number', $s->internal_file_number)
    ->first();

if ($dataGuardian) {
    echo "✅ وجدنا المعيل في data:" . PHP_EOL;
    echo "   data_first_name: " . ($dataGuardian->data_first_name ?? 'NULL') . PHP_EOL;
    echo "   data_father_name: " . ($dataGuardian->data_father_name ?? 'NULL') . PHP_EOL;
    echo "   data_family_name: " . ($dataGuardian->data_family_name ?? 'NULL') . PHP_EOL;
    echo "   data_phone_number: " . ($dataGuardian->data_phone_number ?? 'NULL') . PHP_EOL;
    echo "   data_id_number: " . ($dataGuardian->data_id_number ?? 'NULL') . PHP_EOL;
} else {
    echo "❌ لم نجد المعيل في data بـ file_id_number = {$s->internal_file_number}" . PHP_EOL;
}

// البحث في re_people باستخدام registration_id = relation_id_number
echo PHP_EOL . "🔗 البحث عن المكفول في re_people باستخدام registration_id:" . PHP_EOL;
$sponsored = DB::table('re_people')
    ->where('registration_id', $s->relation_id_number)
    ->first();

if ($sponsored) {
    echo "✅ وجدنا المكفول في re_people:" . PHP_EOL;
    echo "   first_name: " . ($sponsored->first_name ?? 'NULL') . PHP_EOL;
    echo "   second_name: " . ($sponsored->second_name ?? 'NULL') . PHP_EOL;
    echo "   last_name: " . ($sponsored->last_name ?? 'NULL') . PHP_EOL;
    echo "   person_type_of_guarantee: " . ($sponsored->person_type_of_guarantee ?? 'NULL') . PHP_EOL;
} else {
    echo "❌ لم نجد في re_people" . PHP_EOL;
}

// التحقق من إجمالي العدد
echo PHP_EOL . "📊 إحصائيات:" . PHP_EOL;
$totalData = DB::table('data')->count();
$totalRePeople = DB::table('re_people')->count();
$totalSponsorships = DB::table('sponsorships')->count();
$withInternalFile = DB::table('sponsorships')->whereNotNull('internal_file_number')->where('internal_file_number', '!=', '')->count();
$withRelationId = DB::table('sponsorships')->whereNotNull('relation_id_number')->where('relation_id_number', '!=', '')->count();

echo "   إجمالي data: $totalData" . PHP_EOL;
echo "   إجمالي re_people: $totalRePeople" . PHP_EOL;
echo "   إجمالي sponsorships: $totalSponsorships" . PHP_EOL;
echo "   كفالات لها internal_file_number: $withInternalFile" . PHP_EOL;
echo "   كفالات لها relation_id_number: $withRelationId" . PHP_EOL;
