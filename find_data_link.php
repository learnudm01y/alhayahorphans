<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== البحث عن طريقة الربط الصحيحة ===" . PHP_EOL . PHP_EOL;

// هل relation_id_number في sponsorships يتطابق مع شيء في data؟
echo "1. التحقق من relation_id_number في data:" . PHP_EOL;
$s = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->first();

echo "   Sponsorship relation_id_number: " . $s->relation_id_number . PHP_EOL;

// البحث في كل أعمدة data
$dataRow = DB::table('data')->first();
$dataColumns = array_keys((array)$dataRow);

foreach (['file_id_number', 'original_file_id_from_excel', 'data_id_number'] as $col) {
    $match = DB::table('data')
        ->where($col, $s->relation_id_number)
        ->first();

    if ($match) {
        echo "   ✅ وجدنا تطابق في data.$col" . PHP_EOL;
        echo "      data_first_name: " . $match->data_first_name . PHP_EOL;
    }
}

// هل internal_file_number يتطابق مع شيء في data؟
echo PHP_EOL . "2. التحقق من internal_file_number في data:" . PHP_EOL;
echo "   Sponsorship internal_file_number: " . $s->internal_file_number . PHP_EOL;

foreach (['file_id_number', 'original_file_id_from_excel', 'data_id_number'] as $col) {
    $match = DB::table('data')
        ->where($col, $s->internal_file_number)
        ->first();

    if ($match) {
        echo "   ✅ وجدنا تطابق في data.$col" . PHP_EOL;
        echo "      data_first_name: " . $match->data_first_name . PHP_EOL;
    }
}

// التحقق من أمثلة فعلية في data
echo PHP_EOL . "3. أمثلة من data:" . PHP_EOL;
$dataExamples = DB::table('data')
    ->whereNotNull('data_first_name')
    ->where('data_first_name', '!=', '')
    ->limit(5)
    ->get();

foreach ($dataExamples as $d) {
    echo "   file_id_number: " . $d->file_id_number .
         ", original: " . $d->original_file_id_from_excel .
         ", Name: " . $d->data_first_name . " " . $d->data_family_name . PHP_EOL;
}

// التحقق من أمثلة sponsorships
echo PHP_EOL . "4. أمثلة من sponsorships:" . PHP_EOL;
$sponsorshipExamples = DB::table('sponsorships')
    ->limit(5)
    ->get();

foreach ($sponsorshipExamples as $s) {
    echo "   id: " . $s->id .
         ", internal_file: " . $s->internal_file_number .
         ", relation_id: " . ($s->relation_id_number ?? 'NULL') . PHP_EOL;
}

// محاولة إيجاد أي تطابق بين sponsorships و data
echo PHP_EOL . "5. البحث عن أي تطابق:" . PHP_EOL;

// جلب كل internal_file_number
$sponsorshipFiles = DB::table('sponsorships')
    ->whereNotNull('internal_file_number')
    ->pluck('internal_file_number')
    ->toArray();

// جلب كل file_id_number من data
$dataFiles = DB::table('data')
    ->whereNotNull('file_id_number')
    ->pluck('file_id_number')
    ->toArray();

$intersection = array_intersect($sponsorshipFiles, $dataFiles);
echo "   عدد التطابقات بين internal_file_number و file_id_number: " . count($intersection) . PHP_EOL;

if (count($intersection) > 0) {
    $example = array_values($intersection)[0];
    echo "   مثال للتطابق: $example" . PHP_EOL;

    $matchedSponsorship = DB::table('sponsorships')
        ->where('internal_file_number', $example)
        ->first();
    $matchedData = DB::table('data')
        ->where('file_id_number', $example)
        ->first();

    echo "   Sponsorship ID: " . $matchedSponsorship->id . PHP_EOL;
    echo "   Guardian Name: " . ($matchedData->data_first_name ?? 'NULL') . " " . ($matchedData->data_family_name ?? 'NULL') . PHP_EOL;
    echo "   Guardian Phone: " . ($matchedData->data_phone_number ?? 'NULL') . PHP_EOL;
}
