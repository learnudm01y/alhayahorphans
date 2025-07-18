<?php

// تجربة الـ API الجديد للإحصائيات الحقيقية
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 اختبار API الإحصائيات الحقيقية للملفات المكررة\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // اختبار الـ API مباشرة
    $url = 'http://localhost:8000/admin/duplicate-files/real-statistics';

    // إنشاء context مع headers
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'X-Requested-With: XMLHttpRequest'
            ]
        ]
    ]);

    echo "📡 استدعاء API: $url\n";
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        echo "❌ فشل في استدعاء API\n";
        exit(1);
    }

    $data = json_decode($response, true);

    if ($data === null) {
        echo "❌ استجابة API غير صالحة: $response\n";
        exit(1);
    }

    echo "✅ استجابة API:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // التحقق من قاعدة البيانات مباشرة
    echo "🔍 التحقق من قاعدة البيانات مباشرة:\n";
    echo "=" . str_repeat("-", 30) . "\n";

    $totalFiles = DB::table('duplicate_file_temps')->count();
    $activeFiles = DB::table('duplicate_file_temps')
        ->where('expires_at', '>', now())
        ->count();
    $expiredFiles = DB::table('duplicate_file_temps')
        ->where('expires_at', '<=', now())
        ->count();
    $totalImages = DB::table('duplicate_file_temps')
        ->where('mime_type', 'like', 'image/%')
        ->count();
    $totalSize = DB::table('duplicate_file_temps')->sum('file_size');

    echo "📊 إجمالي الملفات: $totalFiles\n";
    echo "🟢 الملفات النشطة: $activeFiles\n";
    echo "🟡 الملفات المنتهية: $expiredFiles\n";
    echo "🖼️ الصور: $totalImages\n";
    echo "💾 المساحة الإجمالية: " . number_format($totalSize / (1024*1024), 2) . " MB\n\n";

    // مقارنة النتائج
    if ($data['success'] && isset($data['data'])) {
        $apiData = $data['data'];
        echo "🔄 مقارنة النتائج:\n";
        echo "=" . str_repeat("-", 30) . "\n";

        $matches = [
            'إجمالي الملفات' => $apiData['total_files'] == $totalFiles,
            'الملفات النشطة' => $apiData['active_files'] == $activeFiles,
            'الملفات المنتهية' => $apiData['expired_files'] == $expiredFiles,
            'الصور' => $apiData['total_images'] == $totalImages,
            'المساحة' => $apiData['total_size'] == $totalSize
        ];

        foreach ($matches as $field => $match) {
            echo ($match ? "✅" : "❌") . " $field: " . ($match ? "متطابق" : "غير متطابق") . "\n";
        }

        $allMatch = array_reduce($matches, function($carry, $item) {
            return $carry && $item;
        }, true);

        echo "\n" . ($allMatch ? "🎉 جميع النتائج متطابقة!" : "⚠️ هناك اختلافات في النتائج") . "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ انتهى الاختبار\n";

?>
