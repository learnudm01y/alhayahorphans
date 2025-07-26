<?php

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "=== اختبار أداء البحث ===\n\n";

// اختبار 1: البحث بالاسم الأول
echo "1. اختبار البحث بالاسم الأول:\n";
$start = microtime(true);
$results1 = DB::table('persons')
    ->where('CI_FIRST_ARB', 'LIKE', 'احمد%')
    ->limit(100)
    ->get();
$time1 = (microtime(true) - $start) * 1000;
echo "   الوقت: " . round($time1, 2) . " مللي ثانية\n";
echo "   النتائج: " . count($results1) . " سجل\n\n";

// اختبار 2: البحث المركب
echo "2. اختبار البحث المركب (الاسم + اسم الأب):\n";
$start = microtime(true);
$results2 = DB::table('persons')
    ->where('CI_FIRST_ARB', 'LIKE', 'محمد%')
    ->where('CI_FATHER_ARB', 'LIKE', 'علي%')
    ->limit(100)
    ->get();
$time2 = (microtime(true) - $start) * 1000;
echo "   الوقت: " . round($time2, 2) . " مللي ثانية\n";
echo "   النتائج: " . count($results2) . " سجل\n\n";

// اختبار 3: البحث برقم الهوية
echo "3. اختبار البحث برقم الهوية:\n";
$start = microtime(true);
$results3 = DB::table('persons')
    ->where('CI_ID_NUM', '123456')
    ->first();
$time3 = (microtime(true) - $start) * 1000;
echo "   الوقت: " . round($time3, 2) . " مللي ثانية\n";
echo "   النتيجة: " . ($results3 ? "موجود" : "غير موجود") . "\n\n";

// اختبار 4: البحث بالنص الكامل (إذا كان متوفر)
echo "4. اختبار البحث بالنص الكامل:\n";
$start = microtime(true);
try {
    $results4 = DB::select("
        SELECT * FROM persons
        WHERE MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1)
        AGAINST('احمد محمد' IN BOOLEAN MODE)
        LIMIT 100
    ");
    $time4 = (microtime(true) - $start) * 1000;
    echo "   الوقت: " . round($time4, 2) . " مللي ثانية\n";
    echo "   النتائج: " . count($results4) . " سجل\n";
} catch (Exception $e) {
    echo "   فهرس النص الكامل غير متوفر: " . $e->getMessage() . "\n";
}

echo "\n=== ملخص الأداء ===\n";
echo "البحث بالاسم الأول: " . round($time1, 2) . " مللي ثانية\n";
echo "البحث المركب: " . round($time2, 2) . " مللي ثانية\n";
echo "البحث برقم الهوية: " . round($time3, 2) . " مللي ثانية\n";

// اختبار Cache
echo "\n=== اختبار نظام التخزين المؤقت ===\n";

$cacheKey = 'test_search_performance';
Cache::forget($cacheKey);

// البحث الأول (بدون cache)
$start = microtime(true);
$data = DB::table('persons')->where('CI_FIRST_ARB', 'LIKE', 'احمد%')->limit(50)->get();
Cache::put($cacheKey, $data, 300); // 5 دقائق
$timeNoCache = (microtime(true) - $start) * 1000;

// البحث الثاني (مع cache)
$start = microtime(true);
$cachedData = Cache::get($cacheKey);
$timeWithCache = (microtime(true) - $start) * 1000;

echo "بدون Cache: " . round($timeNoCache, 2) . " مللي ثانية\n";
echo "مع Cache: " . round($timeWithCache, 2) . " مللي ثانية\n";
echo "التحسن: " . round((($timeNoCache - $timeWithCache) / $timeNoCache) * 100, 1) . "%\n";

echo "\n=== تحليل قاعدة البيانات ===\n";

// إحصائيات الجدول
$stats = DB::select("
    SELECT
        COUNT(*) as total_records,
        COUNT(DISTINCT CI_FIRST_ARB) as unique_first_names,
        COUNT(DISTINCT CI_FATHER_ARB) as unique_father_names,
        COUNT(DISTINCT CI_FAMILY_ARB) as unique_family_names
    FROM persons
");

if (!empty($stats)) {
    $stat = $stats[0];
    echo "إجمالي السجلات: " . number_format($stat->total_records) . "\n";
    echo "أسماء أولى مميزة: " . number_format($stat->unique_first_names) . "\n";
    echo "أسماء آباء مميزة: " . number_format($stat->unique_father_names) . "\n";
    echo "أسماء عائلات مميزة: " . number_format($stat->unique_family_names) . "\n";
}

// فحص الفهارس
echo "\n=== الفهارس المتوفرة ===\n";
$indexes = DB::select("SHOW INDEX FROM persons");
foreach ($indexes as $index) {
    if ($index->Key_name !== 'PRIMARY') {
        echo "- " . $index->Key_name . " على العمود: " . $index->Column_name . "\n";
    }
}

echo "\n=== انتهى الاختبار ===\n";
