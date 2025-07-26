<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

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

echo "=== فحص بنية جدول data ===\n\n";

$columns = Capsule::schema()->getColumnListing('data');

echo "الأعمدة المتعلقة بالحالة والطلبات:\n";
foreach($columns as $col) {
    if(strpos($col, 'status') !== false || strpos($col, 'request') !== false) {
        echo "- {$col}\n";
    }
}

echo "\nجميع الأعمدة في جدول data:\n";
foreach($columns as $col) {
    echo "- {$col}\n";
}

// فحص عينة من البيانات
echo "\n=== عينة من البيانات (Kyle) ===\n";
$kyleRecord = Capsule::table('data')->where('data_first_name', 'Kyle')->first();
if ($kyleRecord) {
    foreach ($kyleRecord as $key => $value) {
        if (strpos($key, 'status') !== false || strpos($key, 'request') !== false) {
            echo "{$key}: {$value}\n";
        }
    }
}

?>
