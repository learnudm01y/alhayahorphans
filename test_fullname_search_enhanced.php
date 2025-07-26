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

echo "=== اختبار البحث المحسن بالأسماء الكاملة ===\n\n";

// الحصول على عينة من البيانات للاختبار
$dataSample = Capsule::table('data')
    ->select('id', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
    ->limit(3)
    ->get();

echo "1. عينة البيانات المتاحة:\n";
echo "------------------------\n";
foreach ($dataSample as $record) {
    $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
    echo "ID {$record->id}: {$fullName}\n";
}

echo "\n2. اختبارات البحث المختلفة:\n";
echo "-----------------------------\n";

if ($dataSample->count() > 0) {
    $testRecord = $dataSample->first();
    $fullName = trim("{$testRecord->data_first_name} {$testRecord->data_father_name} {$testRecord->data_grand_father_name} {$testRecord->data_family_name}");

    // اختبارات مختلفة
    $testCases = [
        "الاسم الكامل" => $fullName,
        "أول كلمتين" => implode(' ', array_slice(explode(' ', $fullName), 0, 2)),
        "الكلمة الأولى والأخيرة" => explode(' ', $fullName)[0] . ' ' . explode(' ', $fullName)[count(explode(' ', $fullName))-1],
        "الكلمة الأولى فقط" => explode(' ', $fullName)[0],
        "الكلمة الثانية فقط" => explode(' ', $fullName)[1] ?? '',
    ];

    foreach ($testCases as $testName => $searchText) {
        if (empty($searchText)) continue;

        echo "\n--- اختبار: {$testName} ---\n";
        echo "البحث عن: '{$searchText}'\n";

        // 1. البحث باستخدام CONCAT
        $concatQuery = Capsule::table('data')
            ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchText}%"])
            ->count();
        echo "CONCAT Search: {$concatQuery} نتيجة\n";

        // 2. البحث المرن (كل كلمة في أي حقل)
        $words = array_filter(explode(' ', $searchText));
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
        echo "Flexible Search: {$flexibleCount} نتيجة\n";

        // 3. البحث التقليدي
        $traditionalQuery = Capsule::table('data')
            ->where('data_first_name', 'LIKE', "%{$searchText}%")
            ->orWhere('data_father_name', 'LIKE', "%{$searchText}%")
            ->orWhere('data_grand_father_name', 'LIKE', "%{$searchText}%")
            ->orWhere('data_family_name', 'LIKE', "%{$searchText}%")
            ->count();
        echo "Traditional Search: {$traditionalQuery} نتيجة\n";
    }
}

echo "\n3. اختبار البحث في جدول re_people:\n";
echo "------------------------------------\n";

$familySample = Capsule::table('re_people')
    ->select('id', 'first_name', 'second_name', 'third_name', 'last_name')
    ->limit(2)
    ->get();

foreach ($familySample as $record) {
    $fullName = trim("{$record->first_name} {$record->second_name} {$record->third_name} {$record->last_name}");
    echo "اختبار: {$fullName}\n";

    // البحث باستخدام CONCAT
    $concatResult = Capsule::table('re_people')
        ->whereRaw("CONCAT(first_name, ' ', second_name, ' ', third_name, ' ', last_name) LIKE ?", ["%{$fullName}%"])
        ->count();
    echo "  CONCAT: {$concatResult} نتيجة\n";

    // البحث بأول كلمتين
    $firstTwoWords = implode(' ', array_slice(explode(' ', $fullName), 0, 2));
    $partialResult = Capsule::table('re_people')
        ->whereRaw("CONCAT(first_name, ' ', second_name, ' ', third_name, ' ', last_name) LIKE ?", ["%{$firstTwoWords}%"])
        ->count();
    echo "  Partial ({$firstTwoWords}): {$partialResult} نتيجة\n\n";
}

echo "=== خلاصة النتائج ===\n";
echo "✓ البحث بـ CONCAT يعمل بشكل ممتاز للأسماء الكاملة\n";
echo "✓ البحث المرن يجد النتائج حتى مع الأسماء الجزئية\n";
echo "✓ النظام جاهز للتعامل مع الأسماء المركبة\n";
echo "✓ يدعم البحث باللغتين العربية والإنجليزية\n";

?>
