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

echo "=== فحص حالات الطلبات ===\n\n";

// جميع حالات الطلبات
$statuses = Capsule::table('request_status')->get();
echo "جميع حالات الطلبات:\n";
foreach ($statuses as $status) {
    echo "- ID: {$status->id}, الوصف: '{$status->description}'\n";
}

// البحث عن "مقبول"
$acceptedStatus = Capsule::table('request_status')->where('description', 'مقبول')->first();
if ($acceptedStatus) {
    echo "\n✅ حالة 'مقبول' موجودة: ID = {$acceptedStatus->id}\n";
} else {
    echo "\n❌ حالة 'مقبول' غير موجودة\n";

    // البحث عن حالات مشابهة
    $similarStatuses = Capsule::table('request_status')
        ->where('description', 'LIKE', '%قبول%')
        ->orWhere('description', 'LIKE', '%موافق%')
        ->orWhere('description', 'LIKE', '%accepted%')
        ->get();

    echo "حالات مشابهة:\n";
    foreach ($similarStatuses as $status) {
        echo "- ID: {$status->id}, الوصف: '{$status->description}'\n";
    }
}

// فحص حالة Kyle
echo "\n=== فحص حالة Kyle ===\n";
$kyleRecord = Capsule::table('data')->where('data_first_name', 'Kyle')->first();
if ($kyleRecord) {
    echo "Kyle - data_request_status: {$kyleRecord->data_request_status}\n";

    $kyleStatus = Capsule::table('request_status')->where('id', $kyleRecord->data_request_status)->first();
    if ($kyleStatus) {
        echo "Kyle - حالة الطلب: '{$kyleStatus->description}'\n";
    }

    // عدد السجلات لكل حالة
    echo "\nإحصائيات السجلات:\n";
    foreach ($statuses as $status) {
        $count = Capsule::table('data')->where('data_request_status', $status->id)->count();
        echo "- '{$status->description}' (ID: {$status->id}): {$count} سجل\n";
    }
}

?>
