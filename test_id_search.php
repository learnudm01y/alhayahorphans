<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== فحص أنواع الأعمدة ===\n\n";

$columns = DB::connection('civilregistry')->select('SHOW COLUMNS FROM persons WHERE Field = "CI_ID_NUM"');
if (!empty($columns)) {
    echo "CI_ID_NUM Type: " . $columns[0]->Type . "\n";
    echo "Nullable: " . $columns[0]->Null . "\n";
    echo "Key: " . $columns[0]->Key . "\n";
}

// اختبار سرعة البحث
echo "\n=== اختبار سرعة البحث برقم الهوية ===\n";

echo "\n1. البحث باستخدام = مع cast:\n";
$start = microtime(true);
$result = DB::connection('civilregistry')->table('persons')
    ->whereRaw("CAST(CI_ID_NUM AS UNSIGNED) = 407015692")
    ->first();
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   الوقت: {$duration} ms\n";
echo "   النتيجة: " . ($result ? "موجود" : "غير موجود") . "\n";

echo "\n2. البحث باستخدام = مع VARCHAR:\n";
$start = microtime(true);
$result = DB::connection('civilregistry')->table('persons')
    ->where('CI_ID_NUM', '407015692')
    ->first();
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   الوقت: {$duration} ms\n";
echo "   النتيجة: " . ($result ? "موجود" : "غير موجود") . "\n";

echo "\n3. EXPLAIN للبحث:\n";
$explain = DB::connection('civilregistry')->select("EXPLAIN SELECT * FROM persons WHERE CI_ID_NUM = '407015692'");
print_r($explain[0]);
