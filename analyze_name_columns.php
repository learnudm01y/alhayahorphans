<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// تكوين قاعدة البيانات
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'aso',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== تحليل أعمدة الأسماء في الجداول الثلاثة ===\n\n";

// تحليل جدول data
echo "1. جدول data (السجلات الرئيسية):\n";
echo "------------------------------------\n";
$dataColumns = Capsule::schema()->getColumnListing('data');
$nameColumns = array_filter($dataColumns, function($col) {
    return strpos($col, 'name') !== false || strpos($col, 'first') !== false ||
           strpos($col, 'father') !== false || strpos($col, 'grand') !== false ||
           strpos($col, 'family') !== false;
});

foreach ($nameColumns as $col) {
    echo "- {$col}\n";
}

// عينة من البيانات
echo "\nعينة من البيانات:\n";
$dataSample = Capsule::table('data')->select($nameColumns)->limit(3)->get();
foreach ($dataSample as $record) {
    foreach ($nameColumns as $col) {
        echo "{$col}: " . ($record->$col ?? 'NULL') . " | ";
    }
    echo "\n";
}

echo "\n\n";

// تحليل جدول re_people
echo "2. جدول re_people (أفراد الأسرة):\n";
echo "------------------------------------\n";
$repeopleColumns = Capsule::schema()->getColumnListing('re_people');
$familyNameColumns = array_filter($repeopleColumns, function($col) {
    return strpos($col, 'name') !== false || strpos($col, 'first') !== false ||
           strpos($col, 'second') !== false || strpos($col, 'third') !== false ||
           strpos($col, 'last') !== false;
});

foreach ($familyNameColumns as $col) {
    echo "- {$col}\n";
}

// عينة من البيانات
echo "\nعينة من البيانات:\n";
$familySample = Capsule::table('re_people')->select($familyNameColumns)->limit(3)->get();
foreach ($familySample as $record) {
    foreach ($familyNameColumns as $col) {
        echo "{$col}: " . ($record->$col ?? 'NULL') . " | ";
    }
    echo "\n";
}

echo "\n\n";

// تحليل جدول dead_pepole
echo "3. جدول dead_pepole (المتوفين):\n";
echo "------------------------------------\n";
$deadColumns = Capsule::schema()->getColumnListing('dead_pepole');
$deadNameColumns = array_filter($deadColumns, function($col) {
    return strpos($col, 'name') !== false || strpos($col, 'first') !== false ||
           strpos($col, 'second') !== false || strpos($col, 'third') !== false ||
           strpos($col, 'last') !== false || strpos($col, 'father') !== false ||
           strpos($col, 'mother') !== false;
});

foreach ($deadNameColumns as $col) {
    echo "- {$col}\n";
}

// عينة من البيانات
echo "\nعينة من البيانات:\n";
$deadSample = Capsule::table('dead_pepole')->select($deadNameColumns)->limit(3)->get();
foreach ($deadSample as $record) {
    foreach ($deadNameColumns as $col) {
        echo "{$col}: " . ($record->$col ?? 'NULL') . " | ";
    }
    echo "\n";
}

echo "\n\n=== ملخص الأعمدة المكتشفة ===\n";
echo "جدول data: " . count($nameColumns) . " أعمدة\n";
echo "جدول re_people: " . count($familyNameColumns) . " أعمدة\n";
echo "جدول dead_pepole: " . count($deadNameColumns) . " أعمدة\n";

echo "\n=== اختبار البحث المرن ===\n";
echo "البحث عن 'محمد أحمد' في جدول data:\n";

// اختبار البحث المرن
$searchTerms = explode(' ', 'محمد أحمد');
$query = Capsule::table('data');

$query->where(function($q) use ($searchTerms, $nameColumns) {
    foreach ($searchTerms as $term) {
        $q->where(function($subQ) use ($term, $nameColumns) {
            foreach ($nameColumns as $col) {
                $subQ->orWhere($col, 'LIKE', "%{$term}%");
            }
        });
    }
});

$flexibleResults = $query->select($nameColumns)->limit(5)->get();
echo "النتائج: " . count($flexibleResults) . " سجل\n";

foreach ($flexibleResults as $result) {
    $fullName = '';
    foreach ($nameColumns as $col) {
        if (!empty($result->$col)) {
            $fullName .= $result->$col . ' ';
        }
    }
    echo "- " . trim($fullName) . "\n";
}

?>
