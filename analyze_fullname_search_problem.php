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

echo "=== تحليل مشكلة البحث بالاسم الكامل ===\n\n";

echo "1. عينة من البيانات في جدول data:\n";
echo "------------------------------------\n";
$dataSample = Capsule::table('data')
    ->select('id', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
    ->limit(5)
    ->get();

foreach ($dataSample as $record) {
    $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
    echo "ID: {$record->id}\n";
    echo "الاسم الكامل: {$fullName}\n";
    echo "الأعمدة المنفصلة:\n";
    echo "  - الاسم الأول: {$record->data_first_name}\n";
    echo "  - اسم الأب: {$record->data_father_name}\n";
    echo "  - اسم الجد: {$record->data_grand_father_name}\n";
    echo "  - اسم العائلة: {$record->data_family_name}\n";
    echo "---\n";
}

echo "\n2. عينة من البيانات في جدول re_people:\n";
echo "--------------------------------------------\n";
$familySample = Capsule::table('re_people')
    ->select('id', 'first_name', 'second_name', 'third_name', 'last_name')
    ->limit(5)
    ->get();

foreach ($familySample as $record) {
    $fullName = trim("{$record->first_name} {$record->second_name} {$record->third_name} {$record->last_name}");
    echo "ID: {$record->id}\n";
    echo "الاسم الكامل: {$fullName}\n";
    echo "الأعمدة المنفصلة:\n";
    echo "  - الاسم الأول: {$record->first_name}\n";
    echo "  - الاسم الثاني: {$record->second_name}\n";
    echo "  - الاسم الثالث: {$record->third_name}\n";
    echo "  - الاسم الأخير: {$record->last_name}\n";
    echo "---\n";
}

echo "\n3. اختبار البحث بالاسم الكامل:\n";
echo "--------------------------------\n";

// جرب البحث بالاسم الكامل الأول
if (!empty($dataSample) && $dataSample->count() > 0) {
    $firstRecord = $dataSample->first();
    $searchName = trim("{$firstRecord->data_first_name} {$firstRecord->data_father_name}");

    echo "البحث عن: {$searchName}\n";

    // البحث التقليدي
    $traditionalSearch = Capsule::table('data')
        ->where('data_first_name', 'LIKE', "%{$searchName}%")
        ->orWhere('data_father_name', 'LIKE', "%{$searchName}%")
        ->count();

    echo "البحث التقليدي: {$traditionalSearch} نتيجة\n";

    // البحث المحسن باستخدام CONCAT
    $concatSearch = Capsule::table('data')
        ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"])
        ->count();

    echo "البحث بـ CONCAT: {$concatSearch} نتيجة\n";

    // البحث المرن (كل كلمة منفصلة)
    $words = explode(' ', $searchName);
    $flexibleQuery = Capsule::table('data');

    foreach ($words as $word) {
        $flexibleQuery->where(function($q) use ($word) {
            $q->where('data_first_name', 'LIKE', "%{$word}%")
              ->orWhere('data_father_name', 'LIKE', "%{$word}%")
              ->orWhere('data_grand_father_name', 'LIKE', "%{$word}%")
              ->orWhere('data_family_name', 'LIKE', "%{$word}%");
        });
    }

    $flexibleCount = $flexibleQuery->count();
    echo "البحث المرن: {$flexibleCount} نتيجة\n";
}

echo "\n4. توصيات الحل:\n";
echo "---------------\n";
echo "✓ استخدام CONCAT للبحث في الاسم الكامل\n";
echo "✓ تقسيم النص المدخل إلى كلمات منفصلة\n";
echo "✓ البحث المرن في جميع الأعمدة\n";
echo "✓ دعم البحث الجزئي والكامل\n";

?>
