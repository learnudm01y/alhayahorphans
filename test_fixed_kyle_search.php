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

echo "=== اختبار البحث المصحح لـ Kyle ===\n\n";

$searchName = "Kyle Christine Byers Brenda Mason Nayda Ayers";
$searchTerms = ["Kyle", "Christine", "Byers", "Brenda", "Mason", "Nayda", "Ayers"];

echo "البحث عن: {$searchName}\n\n";

// 1. البحث بدون شرط الحالة
echo "1. البحث بدون شرط الحالة:\n";
$withoutStatus = Capsule::table('data')
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"])
    ->count();
echo "   النتائج: {$withoutStatus}\n";

// 2. البحث مع شرط الحالة = مقبول (ID = 2)
echo "\n2. البحث مع شرط الحالة = مقبول:\n";
$withStatus = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"])
    ->count();
echo "   النتائج: {$withStatus}\n";

// 3. البحث المرن مع شرط الحالة
echo "\n3. البحث المرن مع شرط الحالة:\n";
$flexibleQuery = Capsule::table('data')->where('data_request_status', 2);

foreach ($searchTerms as $term) {
    $flexibleQuery->where(function($q) use ($term) {
        $q->where('data_first_name', 'LIKE', "%{$term}%")
          ->orWhere('data_father_name', 'LIKE', "%{$term}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$term}%")
          ->orWhere('data_family_name', 'LIKE', "%{$term}%");
    });
}

$flexibleResults = $flexibleQuery->count();
echo "   النتائج: {$flexibleResults}\n";

// 4. البحث بكلمة Kyle فقط مع شرط الحالة
echo "\n4. البحث بـ Kyle فقط مع شرط الحالة:\n";
$kyleOnly = Capsule::table('data')
    ->where('data_request_status', 2)
    ->where(function($q) {
        $q->where('data_first_name', 'LIKE', '%Kyle%')
          ->orWhere('data_father_name', 'LIKE', '%Kyle%')
          ->orWhere('data_grand_father_name', 'LIKE', '%Kyle%')
          ->orWhere('data_family_name', 'LIKE', '%Kyle%');
    })
    ->count();
echo "   النتائج: {$kyleOnly}\n";

// 5. اختبار البحث بأول كلمتين
echo "\n5. البحث بـ 'Kyle Christine' مع شرط الحالة:\n";
$partialName = "Kyle Christine";
$partialResults = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$partialName}%"])
    ->count();
echo "   النتائج: {$partialResults}\n";

echo "\n=== خلاصة الاختبار ===\n";
if ($withStatus > 0) {
    echo "✅ البحث يعمل بشكل صحيح الآن مع شرط الحالة\n";
    echo "✅ المشكلة محلولة - النظام سيجد Kyle الآن\n";
} else {
    echo "❌ لا يزال هناك مشكلة في البحث\n";
}

?>
