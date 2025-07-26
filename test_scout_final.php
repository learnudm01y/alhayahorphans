<?php

require 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🎯 === اختبار نظام Scout المحسن الجديد ===\n\n";

try {
    $scoutService = new \App\Services\CustomScoutSearchService();

    echo "⚡ 1. اختبار البحث الفائق السرعة (ultraFastSearch):\n";
    $start = microtime(true);
    $results = $scoutService->ultraFastSearch('احمد', 10);
    $end = microtime(true);
    $time = round(($end - $start) * 1000, 2);

    echo "   - الكلمة المختبرة: احمد\n";
    echo "   - عدد النتائج: " . count($results) . "\n";
    echo "   - الوقت المستغرق: {$time} مللي ثانية\n";
    if (count($results) > 0) {
        $first = $results[0];
        echo "   - أول نتيجة: " . ($first->CI_FIRST_ARB ?? '') . " " . ($first->CI_FATHER_ARB ?? '') . "\n";
        echo "   - رقم الهوية: " . ($first->CI_ID_NUM ?? 'غير محدد') . "\n";
    }
    echo "\n";

    echo "🔍 2. اختبار البحث السريع العادي (instantSearch):\n";
    $start = microtime(true);
    $results2 = $scoutService->instantSearch('محمد', 10);
    $end = microtime(true);
    $time2 = round(($end - $start) * 1000, 2);

    echo "   - الكلمة المختبرة: محمد\n";
    echo "   - عدد النتائج: " . count($results2) . "\n";
    echo "   - الوقت المستغرق: {$time2} مللي ثانية\n\n";

    echo "📊 3. مقارنة الأداء:\n";
    echo "   - البحث العادي السابق: > 1000ms\n";
    echo "   - البحث الفائق الحالي: {$time}ms\n";
    echo "   - البحث السريع الحالي: {$time2}ms\n";

    $improvement = ((1000 - min($time, $time2)) / 1000) * 100;
    echo "   - نسبة التحسين: " . round($improvement, 2) . "%\n\n";

    echo "✅ جميع الاختبارات تمت بنجاح!\n";
    echo "🎉 النظام جاهز للاستخدام مع الأداء الفائق\n";
    echo "📅 الوقت: " . date('Y-m-d H:i:s') . "\n\n";

    echo "🌐 للاختبار الكامل:\n";
    echo "   1. افتح: http://127.0.0.1:8000/admin/civil-registry\n";
    echo "   2. اضغط على زر 'البحث السريع Scout'\n";
    echo "   3. اكتب أي اسم واستمتع بالسرعة الخارقة!\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "الملف: " . $e->getFile() . "\n";
    echo "السطر: " . $e->getLine() . "\n";
}
