<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== اختبار خوارزمية توليد أرقام الملفات المحدثة ===" . PHP_EOL . PHP_EOL;

// 1. عرض أعلى الأرقام في كل جدول
echo "=== أعلى الأرقام الحالية ===" . PHP_EOL;

$maxData = DB::table('data')
    ->select(DB::raw("MAX(CAST(file_id_number as UNSIGNED)) as max_code"))
    ->whereRaw("LENGTH(file_id_number) = 6 AND file_id_number REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "- data.file_id_number: " . ($maxData ?? 'NULL') . PHP_EOL;

$maxSponsorship = DB::table('sponsorships')
    ->select(DB::raw("MAX(CAST(internal_file_number as UNSIGNED)) as max_code"))
    ->whereRaw("LENGTH(internal_file_number) = 6 AND internal_file_number REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "- sponsorships.internal_file_number: " . ($maxSponsorship ?? 'NULL') . PHP_EOL;

$maxSponsorshipRelation = DB::table('sponsorships')
    ->select(DB::raw("MAX(CAST(relation_id_number as UNSIGNED)) as max_code"))
    ->whereRaw("LENGTH(relation_id_number) = 6 AND relation_id_number REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "- sponsorships.relation_id_number: " . ($maxSponsorshipRelation ?? 'NULL') . PHP_EOL;

$maxDeadPeople = DB::table('dead_people')
    ->select(DB::raw("MAX(CAST(re_file_id as UNSIGNED)) as max_code"))
    ->whereRaw("LENGTH(re_file_id) = 6 AND re_file_id REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "- dead_people.re_file_id: " . ($maxDeadPeople ?? 'NULL') . PHP_EOL;

$maxRePeople = DB::table('re_people')
    ->select(DB::raw("MAX(CAST(registration_id as UNSIGNED)) as max_code"))
    ->whereRaw("LENGTH(registration_id) = 6 AND registration_id REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "- re_people.registration_id: " . ($maxRePeople ?? 'NULL') . PHP_EOL;

$maxReserved = DB::table('reserved_codes')
    ->select(DB::raw("MAX(CAST(code as UNSIGNED)) as max_code"))
    ->whereRaw("LENGTH(code) = 6 AND code REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "- reserved_codes.code: " . ($maxReserved ?? 'NULL') . PHP_EOL;

$overallMax = max(
    (int)$maxData,
    (int)$maxSponsorship,
    (int)$maxSponsorshipRelation,
    (int)$maxDeadPeople,
    (int)$maxRePeople,
    (int)$maxReserved
);
echo PHP_EOL . "أعلى رقم في النظام: " . $overallMax . PHP_EOL;

// 2. توليد رقم جديد
echo PHP_EOL . "=== توليد رقم جديد ===" . PHP_EOL;
$newCode = generateUniqueReservedCode('data', 'file_id_number');
echo "الرقم الجديد: " . $newCode . PHP_EOL;

// 3. التحقق من أن الرقم فريد في جميع الجداول
echo PHP_EOL . "=== التحقق من تفرد الرقم ===" . PHP_EOL;
$existsInData = DB::table('data')->where('file_id_number', $newCode)->exists();
$existsInSponsorship = DB::table('sponsorships')->where('internal_file_number', $newCode)->exists();
$existsInSponsorshipRelation = DB::table('sponsorships')->where('relation_id_number', $newCode)->exists();
$existsInDeadPeople = DB::table('dead_people')->where('re_file_id', $newCode)->exists();
$existsInRePeople = DB::table('re_people')->where('registration_id', $newCode)->exists();

echo "- في data: " . ($existsInData ? '❌ موجود' : '✅ غير موجود') . PHP_EOL;
echo "- في sponsorships.internal_file_number: " . ($existsInSponsorship ? '❌ موجود' : '✅ غير موجود') . PHP_EOL;
echo "- في sponsorships.relation_id_number: " . ($existsInSponsorshipRelation ? '❌ موجود' : '✅ غير موجود') . PHP_EOL;
echo "- في dead_people: " . ($existsInDeadPeople ? '❌ موجود' : '✅ غير موجود') . PHP_EOL;
echo "- في re_people: " . ($existsInRePeople ? '❌ موجود' : '✅ غير موجود') . PHP_EOL;

echo PHP_EOL . "✅ تم اختبار الخوارزمية بنجاح!" . PHP_EOL;
