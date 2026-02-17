<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== اختبار تحميل تصميم التقرير ===\n\n";

$sponsorId = 1;

$design = DB::table('sponsor_report_designs')->where('sponsor_id', $sponsorId)->first();

if (!$design) {
    echo "❌ لا يوجد تصميم للكفيل {$sponsorId}\n";
    exit;
}

echo "✓ تم جلب التصميم من قاعدة البيانات\n";
echo "ID: {$design->id}\n";
echo "نوع الخلفية: {$design->background_type}\n\n";

echo "=== الملفات في قاعدة البيانات ===\n";
echo "single_image: " . ($design->single_image ?? 'null') . "\n";
echo "header_image: " . ($design->header_image ?? 'null') . "\n";
echo "main_image: " . ($design->main_image ?? 'null') . "\n";
echo "footer_image: " . ($design->footer_image ?? 'null') . "\n\n";

// اختبار تحميل كل صورة
$imageFields = [
    'header_image' => $design->header_image,
    'main_image' => $design->main_image,
    'footer_image' => $design->footer_image,
];

echo "=== اختبار تحويل الصور إلى base64 ===\n";

foreach ($imageFields as $field => $path) {
    if (!$path) {
        echo "{$field}: تم تخطيه (null)\n";
        continue;
    }

    $fullPath = __DIR__ . '/storage/app/public/' . $path;

    echo "\n{$field}:\n";
    echo "  المسار في DB: {$path}\n";
    echo "  المسار الكامل: {$fullPath}\n";

    if (file_exists($fullPath)) {
        echo "  ✓ الملف موجود\n";

        $imageData = file_get_contents($fullPath);
        $mimeType = mime_content_type($fullPath);
        $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

        echo "  النوع: {$mimeType}\n";
        echo "  حجم الملف: " . filesize($fullPath) . " bytes\n";
        echo "  طول base64: " . strlen($base64) . " chars\n";
        echo "  أول 50 حرف: " . substr($base64, 0, 50) . "...\n";
    } else {
        echo "  ❌ الملف غير موجود!\n";
    }
}

// الآن اختبار استخدام Model
echo "\n\n=== اختبار استخدام Model ===\n";

$designModel = \App\Models\SponsorReportDesign::where('sponsor_id', $sponsorId)->first();

if ($designModel) {
    echo "✓ تم جلب التصميم عبر Model\n\n";

    echo "header_image_base64: ";
    $headerBase64 = $designModel->header_image_base64;
    if ($headerBase64) {
        echo "✓ موجود (" . strlen($headerBase64) . " chars)\n";
    } else {
        echo "❌ null\n";
    }

    echo "main_image_base64: ";
    $mainBase64 = $designModel->main_image_base64;
    if ($mainBase64) {
        echo "✓ موجود (" . strlen($mainBase64) . " chars)\n";
    } else {
        echo "❌ null\n";
    }

    echo "footer_image_base64: ";
    $footerBase64 = $designModel->footer_image_base64;
    if ($footerBase64) {
        echo "✓ موجود (" . strlen($footerBase64) . " chars)\n";
    } else {
        echo "❌ null\n";
    }
}

echo "\n=== الاختبار انتهى ===\n";
