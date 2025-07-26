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

echo "=== فحص فلتر request_status ===\n\n";

// 1. فحص جدول request_status
echo "1. جدول request_status:\n";
$statuses = Capsule::table('request_status')->get();
foreach ($statuses as $status) {
    echo "   ID: {$status->id}, الاسم: {$status->status_name}\n";
}

// 2. فحص قيم data_request_status في جدول data
echo "\n2. قيم data_request_status في جدول data:\n";
$statusCounts = Capsule::table('data')
    ->select('data_request_status', Capsule::raw('COUNT(*) as count'))
    ->groupBy('data_request_status')
    ->orderBy('data_request_status')
    ->get();

foreach ($statusCounts as $count) {
    echo "   Status {$count->data_request_status}: {$count->count} سجل\n";
}

// 3. فحص سجل Kyle بدون فلتر الحالة
echo "\n3. البحث عن Kyle بدون فلتر الحالة:\n";
$kyleWithoutFilter = Capsule::table('data')
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ['%Kyle%'])
    ->get();

echo "   عدد النتائج: " . count($kyleWithoutFilter) . "\n";
foreach ($kyleWithoutFilter as $record) {
    $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
    echo "   - ID: {$record->id}, الاسم: {$fullName}, الحالة: {$record->data_request_status}\n";
}

// 4. فحص سجل Kyle مع فلتر الحالة 2
echo "\n4. البحث عن Kyle مع فلتر الحالة 2:\n";
$kyleWithFilter = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ['%Kyle%'])
    ->get();

echo "   عدد النتائج: " . count($kyleWithFilter) . "\n";
foreach ($kyleWithFilter as $record) {
    $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
    echo "   - ID: {$record->id}, الاسم: {$fullName}, الحالة: {$record->data_request_status}\n";
}

// 5. البحث عن الاسم الكامل بدون فلتر
$searchName = "Kyle Christine Byers Brenda Mason Nayda Ayers";
echo "\n5. البحث عن الاسم الكامل '{$searchName}' بدون فلتر:\n";
$fullNameNoFilter = Capsule::table('data')
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"])
    ->get();

echo "   عدد النتائج: " . count($fullNameNoFilter) . "\n";
foreach ($fullNameNoFilter as $record) {
    $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
    echo "   - ID: {$record->id}, الاسم: {$fullName}, الحالة: {$record->data_request_status}\n";
}

// 6. البحث عن الاسم الكامل مع فلتر الحالة
echo "\n6. البحث عن الاسم الكامل '{$searchName}' مع فلتر الحالة 2:\n";
$fullNameWithFilter = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"])
    ->get();

echo "   عدد النتائج: " . count($fullNameWithFilter) . "\n";
foreach ($fullNameWithFilter as $record) {
    $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
    echo "   - ID: {$record->id}, الاسم: {$fullName}, الحالة: {$record->data_request_status}\n";
}

echo "\n=== خلاصة التحليل ===\n";
if (count($fullNameWithFilter) > 0) {
    echo "✅ السجل موجود ويمكن العثور عليه\n";
    echo "المشكلة قد تكون في SearchService أو في تطبيق الفلاتر\n";
} elseif (count($fullNameNoFilter) > 0) {
    echo "⚠️  السجل موجود لكن بحالة مختلفة عن 2\n";
    echo "تحقق من فلتر request_status في البحث\n";
} else {
    echo "❌ السجل غير موجود في قاعدة البيانات\n";
}

?>
