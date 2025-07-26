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

echo "=== فحص جدول request_status ===\n\n";

// 1. عرض بنية الجدول
echo "1. بنية جدول request_status:\n";
$columns = Capsule::select("DESCRIBE request_status");
foreach ($columns as $column) {
    echo "   {$column->Field} | {$column->Type} | NULL: {$column->Null} | Key: {$column->Key} | Default: {$column->Default}\n";
}

// 2. عرض جميع السجلات
echo "\n2. جميع سجلات request_status:\n";
$statuses = Capsule::table('request_status')->get();
foreach ($statuses as $status) {
    echo "   ID: {$status->id}";

    // طباعة جميع الحقول المتاحة
    foreach ((array)$status as $field => $value) {
        if ($field !== 'id') {
            echo ", {$field}: {$value}";
        }
    }
    echo "\n";
}

// 3. البحث عن حالة "مقبول"
echo "\n3. البحث عن حالة 'مقبول':\n";

// جرب حقول مختلفة
$possibleFields = ['description', 'status_name', 'name', 'status_description', 'title'];

foreach ($possibleFields as $field) {
    echo "   البحث في حقل '{$field}':\n";

    try {
        $accepted = Capsule::table('request_status')->where($field, 'مقبول')->first();
        if ($accepted) {
            echo "     ✅ وجد: ID {$accepted->id}\n";
        } else {
            // جرب البحث الجزئي
            $accepted = Capsule::table('request_status')->where($field, 'LIKE', '%مقبول%')->first();
            if ($accepted) {
                echo "     ✅ وجد جزئياً: ID {$accepted->id}, القيمة: " . $accepted->$field . "\n";
            } else {
                echo "     ❌ لم يوجد\n";
            }
        }
    } catch (Exception $e) {
        echo "     ❌ الحقل غير موجود\n";
    }
}

// 4. تحديد الحالة الصحيحة
echo "\n4. تحديد حالة 'مقبول' يدوياً:\n";
echo "   الحالة 2 (الأكثر شيوعاً): " . Capsule::table('data')->where('data_request_status', 2)->count() . " سجل\n";

// 5. فحص الحالة المستخدمة في السجل المطلوب
echo "\n5. حالة سجل Kyle:\n";
$kyle = Capsule::table('data')
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ['%Kyle Christine Byers%'])
    ->first();

if ($kyle) {
    echo "   ID: {$kyle->id}, الحالة: {$kyle->data_request_status}\n";

    // البحث عن معلومات هذه الحالة
    $statusInfo = Capsule::table('request_status')->where('id', $kyle->data_request_status)->first();
    if ($statusInfo) {
        echo "   معلومات الحالة:\n";
        foreach ((array)$statusInfo as $field => $value) {
            echo "     {$field}: {$value}\n";
        }
    }
}

echo "\n=== الخلاصة ===\n";
echo "يجب إصلاح فلتر data_request_status في SearchService\n";
echo "استخدم الحالة 2 كافتراضي أو أزل الفلتر تماماً\n";

?>
