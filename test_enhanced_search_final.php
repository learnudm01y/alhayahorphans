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

echo "=== اختبار البحث المحسن الجديد ===\n\n";

$searchName = "Kyle Christine Byers Brenda Mason Nayda Ayers";
echo "البحث عن: {$searchName}\n\n";

// 1. البحث بـ CONCAT للاسم الكامل (الطريقة الأساسية)
echo "1. البحث بـ CONCAT للاسم الكامل:\n";
$concatFull = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"])
    ->count();
echo "   النتائج: {$concatFull}\n";

// 2. البحث بـ CONCAT لأول كلمتين
echo "\n2. البحث بـ CONCAT لأول كلمتين (Kyle Christine):\n";
$firstTwo = "Kyle Christine";
$concatPartial = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$firstTwo}%"])
    ->count();
echo "   النتائج: {$concatPartial}\n";

// 3. البحث بـ CONCAT لآخر كلمتين
echo "\n3. البحث بـ CONCAT لآخر كلمتين (Nayda Ayers):\n";
$lastTwo = "Nayda Ayers";
$concatLast = Capsule::table('data')
    ->where('data_request_status', 2)
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$lastTwo}%"])
    ->count();
echo "   النتائج: {$concatLast}\n";

// 4. البحث المرن الجديد - البحث عن أي كلمة
echo "\n4. البحث المرن - أي كلمة:\n";
$terms = ["Kyle", "Christine", "Byers", "Brenda", "Mason", "Nayda", "Ayers"];
$flexibleQuery = Capsule::table('data')->where('data_request_status', 2);

$flexibleQuery->where(function($q) use ($terms) {
    foreach ($terms as $term) {
        $q->orWhere(function($termQuery) use ($term) {
            $termQuery->where('data_first_name', 'LIKE', "%{$term}%")
                     ->orWhere('data_father_name', 'LIKE', "%{$term}%")
                     ->orWhere('data_grand_father_name', 'LIKE', "%{$term}%")
                     ->orWhere('data_family_name', 'LIKE', "%{$term}%");
        });
    }
});

$flexibleCount = $flexibleQuery->count();
echo "   النتائج: {$flexibleCount}\n";

// 5. البحث بتركيبات متتالية
echo "\n5. البحث بتركيبات متتالية:\n";
$combinations = [];
for ($i = 0; $i < count($terms) - 1; $i++) {
    $combinations[] = $terms[$i] . ' ' . $terms[$i + 1];
}

$combinationQuery = Capsule::table('data')->where('data_request_status', 2);
$combinationQuery->where(function($q) use ($combinations) {
    foreach ($combinations as $combo) {
        $q->orWhere('data_first_name', 'LIKE', "%{$combo}%")
          ->orWhere('data_father_name', 'LIKE', "%{$combo}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$combo}%")
          ->orWhere('data_family_name', 'LIKE', "%{$combo}%");
    }
});

$combinationCount = $combinationQuery->count();
echo "   النتائج: {$combinationCount}\n";

// 6. البحث الشامل (محاكاة SearchService)
echo "\n6. البحث الشامل (محاكاة SearchService):\n";
$comprehensiveQuery = Capsule::table('data')->where('data_request_status', 2);

$comprehensiveQuery->where(function($q) use ($searchName, $firstTwo, $lastTwo, $terms, $combinations) {
    // البحث بـ CONCAT للاسم الكامل
    $q->orWhereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"]);

    // البحث بـ CONCAT لأجزاء
    $q->orWhereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$firstTwo}%"]);
    $q->orWhereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$lastTwo}%"]);

    // البحث التقليدي
    $q->orWhere('data_first_name', 'LIKE', "%{$searchName}%")
      ->orWhere('data_father_name', 'LIKE', "%{$searchName}%")
      ->orWhere('data_grand_father_name', 'LIKE', "%{$searchName}%")
      ->orWhere('data_family_name', 'LIKE', "%{$searchName}%");

    // البحث المرن
    foreach ($terms as $term) {
        $q->orWhere('data_first_name', 'LIKE', "%{$term}%")
          ->orWhere('data_father_name', 'LIKE', "%{$term}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$term}%")
          ->orWhere('data_family_name', 'LIKE', "%{$term}%");
    }

    // البحث بالتركيبات
    foreach ($combinations as $combo) {
        $q->orWhere('data_first_name', 'LIKE', "%{$combo}%")
          ->orWhere('data_father_name', 'LIKE', "%{$combo}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$combo}%")
          ->orWhere('data_family_name', 'LIKE', "%{$combo}%");
    }
});

$comprehensiveCount = $comprehensiveQuery->count();
echo "   النتائج: {$comprehensiveCount}\n";

// عرض تفاصيل النتائج إذا وجدت
if ($comprehensiveCount > 0) {
    echo "\n=== تفاصيل النتائج ===\n";
    $results = $comprehensiveQuery->limit(5)->get();
    foreach ($results as $record) {
        $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
        echo "- ID: {$record->id}, الاسم: {$fullName}\n";
    }
}

echo "\n=== ملخص النتائج ===\n";
echo "✅ البحث بـ CONCAT للاسم الكامل: {$concatFull}\n";
echo "✅ البحث بـ CONCAT لأول كلمتين: {$concatPartial}\n";
echo "✅ البحث بـ CONCAT لآخر كلمتين: {$concatLast}\n";
echo "✅ البحث المرن: {$flexibleCount}\n";
echo "✅ البحث بالتركيبات: {$combinationCount}\n";
echo "✅ البحث الشامل: {$comprehensiveCount}\n";

if ($comprehensiveCount > 0) {
    echo "\n🎯 النتيجة: البحث يعمل الآن! يجب أن تحصل على نتائج في Modal البحث.\n";
} else {
    echo "\n❌ لا تزال هناك مشكلة - تحتاج فحص إضافي.\n";
}

?>
