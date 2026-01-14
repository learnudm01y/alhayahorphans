<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

$capsule = new DB;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'aso',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== فحص أعمدة الجداول لفهم البنية الصحيحة ===\n\n";

// فحص جدول data
echo "📋 أعمدة جدول data:\n";
$dataColumns = DB::select("DESCRIBE data");
foreach ($dataColumns as $col) {
    if (strpos($col->Field, 'name') !== false || strpos($col->Field, 'address') !== false || strpos($col->Field, 'city') !== false) {
        echo "  {$col->Field} - {$col->Type}\n";
    }
}

echo "\n📋 أعمدة جدول dead_people:\n";
$deadColumns = DB::select("DESCRIBE dead_people");
foreach ($deadColumns as $col) {
    if (strpos($col->Field, 'name') !== false || strpos($col->Field, 'address') !== false || strpos($col->Field, 'city') !== false) {
        echo "  {$col->Field} - {$col->Type}\n";
    }
}

echo "\n📋 أعمدة جدول re_people:\n";
$reColumns = DB::select("DESCRIBE re_people");
foreach ($reColumns as $col) {
    if (strpos($col->Field, 'name') !== false || strpos($col->Field, 'address') !== false || strpos($col->Field, 'city') !== false) {
        echo "  {$col->Field} - {$col->Type}\n";
    }
}

echo "\n📋 أعمدة جدول portal_general_registration_field_values:\n";
$portalColumns = DB::select("DESCRIBE portal_general_registration_field_values");
foreach ($portalColumns as $col) {
    if (strpos($col->Field, 'city') !== false || strpos($col->Field, 'address') !== false || strpos($col->Field, 'province') !== false) {
        echo "  {$col->Field} - {$col->Type}\n";
    }
}

// فحص بيانات حقيقية
echo "\n🔍 فحص بيانات حقيقية من جدول data:\n";
$dataRecord = DB::table('data')
    ->whereNotNull('data_first_name')
    ->where('data_first_name', '!=', '')
    ->first();

if ($dataRecord) {
    echo "  data_first_name: '{$dataRecord->data_first_name}'\n";
    echo "  data_father_name: '{$dataRecord->data_father_name}'\n";
    echo "  data_grand_father_name: '{$dataRecord->data_grand_father_name}'\n";
    echo "  data_family_name: '{$dataRecord->data_family_name}'\n";
    echo "  data_current_address: '{$dataRecord->data_current_address}'\n";
    echo "  data_city: '{$dataRecord->data_city}'\n";
}

echo "\n🔍 فحص بيانات حقيقية من جدول dead_people:\n";
$deadRecord = DB::table('dead_people')
    ->whereNotNull('father_first_name')
    ->where('father_first_name', '!=', '')
    ->first();

if ($deadRecord) {
    echo "أسماء الأب:\n";
    echo "  father_first_name: '{$deadRecord->father_first_name}'\n";
    echo "  father_second_name: '{$deadRecord->father_second_name}'\n";
    echo "  father_third_name: '{$deadRecord->father_third_name}'\n";
    echo "  father_last_name: '{$deadRecord->father_last_name}'\n";

    if (!empty($deadRecord->mother_first_name)) {
        echo "أسماء الأم:\n";
        echo "  mother_first_name: '{$deadRecord->mother_first_name}'\n";
        echo "  mother_second_name: '{$deadRecord->mother_second_name}'\n";
        echo "  mother_third_name: '{$deadRecord->mother_third_name}'\n";
        echo "  mother_last_name: '{$deadRecord->mother_last_name}'\n";
    }
}

echo "\n🔍 فحص بيانات حقيقية من جدول re_people:\n";
$reRecord = DB::table('re_people')
    ->whereNotNull('first_name')
    ->where('first_name', '!=', '')
    ->first();

if ($reRecord) {
    echo "  first_name: '{$reRecord->first_name}'\n";
    echo "  second_name: '{$reRecord->second_name}'\n";
    echo "  third_name: '{$reRecord->third_name}'\n";
    echo "  last_name: '{$reRecord->last_name}'\n";
}

echo "\n=== انتهى الفحص ===\n";
