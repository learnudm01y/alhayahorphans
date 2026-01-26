<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use App\Models\Sponsorship;

echo "=== اختبار Job الفعلي ===\n";

$sponsorship = Sponsorship::find(198);
if (!$sponsorship) {
    echo "❌ لم يتم العثور على كفالة رقم 198\n";
    exit;
}

echo "✅ تم العثور على الكفالة ID: {$sponsorship->id}\n";

try {
    // تشغيل Job مباشرة
    $job = new GenerateOrphanReportPdf($sponsorship->id);
    $job->handle();

    echo "✅ تم تشغيل Job بنجاح\n";

    // فحص الملف المُولد
    $storagePath = storage_path('app/reports');
    $files = glob($storagePath . '/*.pdf');

    if (empty($files)) {
        echo "❌ لا يوجد ملفات PDF في مجلد reports\n";
        echo "مجلد التقارير: $storagePath\n";

        // إنشاء المجلد إذا لم يكن موجوداً
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
            echo "✅ تم إنشاء مجلد reports\n";
        }
    } else {
        echo "✅ تم العثور على " . count($files) . " ملف PDF\n";

        $latestFile = array_reduce($files, function($a, $b) {
            return filemtime($a) > filemtime($b) ? $a : $b;
        });

        $fileSize = filesize($latestFile);
        $fileName = basename($latestFile);

        echo "✅ آخر ملف: $fileName\n";
        echo "✅ حجم الملف: $fileSize bytes\n";
        echo "✅ تاريخ الإنشاء: " . date('Y-m-d H:i:s', filemtime($latestFile)) . "\n";

        if ($fileSize > 15000) {
            echo "✅ الملف يبدو بحجم جيد ويحتوي على بيانات!\n";
        } else {
            echo "❌ الملف قد يكون فارغاً أو صغير جداً\n";
        }

        // نسخ الملف للفحص
        copy($latestFile, 'latest_job_report.pdf');
        echo "✅ تم نسخ الملف إلى latest_job_report.pdf للفحص\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ في Job: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
