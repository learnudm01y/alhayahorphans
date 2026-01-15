<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// البحث عن كفالة تحتوي على بيانات معيل في جدول data
echo "=== البحث عن كفالة مع بيانات معيل من جدول data ===" . PHP_EOL;
$sponsorshipWithDataGuardian = DB::table('sponsorships')
    ->join('data', 'data.re_id_number', '=', 'sponsorships.relation_id_number')
    ->where('data.data_first_name', '!=', '')
    ->whereNotNull('data.data_first_name')
    ->select('sponsorships.id', 'sponsorships.relation_id_number',
             'data.data_first_name', 'data.data_family_name')
    ->first();

if ($sponsorshipWithDataGuardian) {
    echo "✅ وجدنا كفالة مع معيل في data:" . PHP_EOL;
    echo "   ID: {$sponsorshipWithDataGuardian->id}" . PHP_EOL;
    echo "   relation_id_number: {$sponsorshipWithDataGuardian->relation_id_number}" . PHP_EOL;
    echo "   Name: {$sponsorshipWithDataGuardian->data_first_name} {$sponsorshipWithDataGuardian->data_family_name}" . PHP_EOL;
} else {
    echo "❌ لم نجد كفالة مع معيل في جدول data" . PHP_EOL;
}

// البحث عن كفالة تحتوي على بيانات معيل في جدول re_people
echo PHP_EOL . "=== البحث عن كفالة مع بيانات معيل من جدول re_people ===" . PHP_EOL;
$sponsorshipWithRePeopleGuardian = DB::table('sponsorships')
    ->join('re_people', 're_people.registration_id', '=', 'sponsorships.relation_id_number')
    ->where('re_people.first_name', '!=', '')
    ->whereNotNull('re_people.first_name')
    ->select('sponsorships.id', 'sponsorships.relation_id_number',
             're_people.first_name', 're_people.last_name')
    ->first();

if ($sponsorshipWithRePeopleGuardian) {
    echo "✅ وجدنا كفالة مع معيل في re_people:" . PHP_EOL;
    echo "   ID: {$sponsorshipWithRePeopleGuardian->id}" . PHP_EOL;
    echo "   relation_id_number: {$sponsorshipWithRePeopleGuardian->relation_id_number}" . PHP_EOL;
    echo "   Name: {$sponsorshipWithRePeopleGuardian->first_name} {$sponsorshipWithRePeopleGuardian->last_name}" . PHP_EOL;
} else {
    echo "❌ لم نجد كفالة مع معيل في جدول re_people" . PHP_EOL;
}

// التحقق من الكفالة 100160 مباشرة
echo PHP_EOL . "=== التحقق من الكفالة 100160 ===" . PHP_EOL;
$s = DB::table('sponsorships')->where('id', 100160)->first();
echo "relation_id_number: " . ($s->relation_id_number ?? 'NULL') . PHP_EOL;

// البحث في data بـ relation_id_number
$guardian = DB::table('data')->where('re_id_number', $s->relation_id_number)->first();
if ($guardian) {
    echo "✅ وجدنا المعيل في data:" . PHP_EOL;
    echo "   data_first_name: " . ($guardian->data_first_name ?? 'NULL') . PHP_EOL;
} else {
    echo "❌ لم نجد المعيل في data بـ re_id_number = {$s->relation_id_number}" . PHP_EOL;
}

// البحث في re_people بـ relation_id_number
$guardianRP = DB::table('re_people')->where('registration_id', $s->relation_id_number)->first();
if ($guardianRP) {
    echo "✅ وجدنا المعيل في re_people:" . PHP_EOL;
    echo "   first_name: " . ($guardianRP->first_name ?? 'NULL') . PHP_EOL;
} else {
    echo "❌ لم نجد المعيل في re_people بـ registration_id = {$s->relation_id_number}" . PHP_EOL;
}
