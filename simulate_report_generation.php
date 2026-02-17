<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Log;

echo "=== محاكاة توليد التقرير ===\n\n";

$sponsorId = 1;

// جلب التصميم
$reportDesign = \App\Models\SponsorReportDesign::where('sponsor_id', $sponsorId)->first();

if (!$reportDesign) {
    echo "❌ لا يوجد تصميم للكفيل {$sponsorId}\n";
    exit;
}

echo "✓ تم جلب التصميم\n";
echo "نوع الخلفية: {$reportDesign->background_type}\n\n";

// إعداد بيانات التصميم كما في exportFamilyReport
$customDesign = [
    'background_type' => $reportDesign->background_type,
    'theme_colors' => $reportDesign->theme_colors ?? [
        'primary' => '#1a1a1a',
        'secondary' => '#4a4a4a',
        'accent' => '#007bff'
    ],
    'single_image' => $reportDesign->single_image_base64,
    'header_image' => $reportDesign->header_image_base64,
    'main_image' => $reportDesign->main_image_base64,
    'footer_image' => $reportDesign->footer_image_base64,
];

echo "=== محتوى customDesign ===\n";
echo "background_type: {$customDesign['background_type']}\n";
echo "single_image: " . ($customDesign['single_image'] ? "موجود (" . strlen($customDesign['single_image']) . " chars)" : "null") . "\n";
echo "header_image: " . ($customDesign['header_image'] ? "موجود (" . strlen($customDesign['header_image']) . " chars)" : "null") . "\n";
echo "main_image: " . ($customDesign['main_image'] ? "موجود (" . strlen($customDesign['main_image']) . " chars)" : "null") . "\n";
echo "footer_image: " . ($customDesign['footer_image'] ? "موجود (" . strlen($customDesign['footer_image']) . " chars)" : "null") . "\n\n";

// اختبار شروط Blade
echo "=== اختبار شروط Blade ===\n";

$backgroundType = $customDesign['background_type'] ?? 'single';
echo "backgroundType: {$backgroundType}\n";

if ($backgroundType === 'single') {
    echo "سيتم استخدام: background_type = single\n";
    $singleBg = $customDesign['single_image'] ?? 'fallback';
    echo "singleBg: " . ($singleBg === 'fallback' ? 'fallback' : "موجود (" . strlen($singleBg) . " chars)") . "\n";
} else {
    echo "سيتم استخدام: background_type = triple\n";
    echo "\nفحص الشروط:\n";

    echo "!empty(\$customDesign['header_image']): ";
    if (!empty($customDesign['header_image'])) {
        echo "true ✓ - سيتم عرض صورة الرأس\n";
        echo "  أول 50 حرف: " . substr($customDesign['header_image'], 0, 50) . "...\n";
    } else {
        echo "false ✗ - لن يتم عرض صورة الرأس\n";
    }

    echo "!empty(\$customDesign['main_image']): ";
    if (!empty($customDesign['main_image'])) {
        echo "true ✓ - سيتم عرض الصورة الرئيسية\n";
        echo "  أول 50 حرف: " . substr($customDesign['main_image'], 0, 50) . "...\n";
    } else {
        echo "false ✗ - لن يتم عرض الصورة الرئيسية\n";
    }

    echo "!empty(\$customDesign['footer_image']): ";
    if (!empty($customDesign['footer_image'])) {
        echo "true ✓ - سيتم عرض صورة التذييل\n";
        echo "  أول 50 حرف: " . substr($customDesign['footer_image'], 0, 50) . "...\n";
    } else {
        echo "false ✗ - لن يتم عرض صورة التذييل\n";
    }
}

echo "\n=== CSS سيتم توليده ===\n";

if ($backgroundType === 'triple') {
    echo "\n.page-header-bg {\n";
    if (!empty($customDesign['header_image'])) {
        echo "    background-image: url(" . substr($customDesign['header_image'], 0, 60) . "...);\n";
    } else {
        echo "    /* لا توجد صورة رأس */\n";
    }
    echo "}\n";

    echo "\n.page-main-bg {\n";
    if (!empty($customDesign['main_image'])) {
        echo "    background-image: url(" . substr($customDesign['main_image'], 0, 60) . "...);\n";
    } else {
        echo "    /* لا توجد صورة رئيسية */\n";
    }
    echo "}\n";

    echo "\n.page-footer-bg {\n";
    if (!empty($customDesign['footer_image'])) {
        echo "    background-image: url(" . substr($customDesign['footer_image'], 0, 60) . "...);\n";
    } else {
        echo "    /* لا توجد صورة تذييل */\n";
    }
    echo "}\n";
}

echo "\n=== الاختبار انتهى ===\n";
