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

echo "=== أعمدة الأسماء في جدول dead_pepole ===\n";
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

echo "\n=== ملخص جميع أعمدة الأسماء ===\n";

echo "\nجدول data:\n";
echo "- data_first_name\n";
echo "- data_father_name\n";
echo "- data_grand_father_name\n";
echo "- data_family_name\n";

echo "\nجدول re_people:\n";
echo "- first_name\n";
echo "- second_name\n";
echo "- third_name\n";
echo "- last_name\n";

echo "\nجدول dead_pepole:\n";
foreach ($deadNameColumns as $col) {
    echo "- {$col}\n";
}

?>
