<?php

require __DIR__ . '/vendor/autoload.php';

echo "=== فحص ملفات PDF المُنشأة ===\n\n";

$pdfs = [
    'test1_dejavu.pdf' => 'DejaVu Sans',
    'test2_traditional.pdf' => 'Traditional Arabic',
    'test3_direct.pdf' => 'wkhtmltopdf مباشر',
    'inspection_report.pdf' => 'التقرير الكامل',
];

foreach ($pdfs as $file => $desc) {
    if (!file_exists($file)) {
        echo "❌ {$desc}: الملف غير موجود\n";
        continue;
    }

    $size = filesize($file);
    $content = file_get_contents($file);

    echo "{$desc} ({$file}):\n";
    echo "  الحجم: {$size} bytes\n";

    // فحص وجود كلمات عربية في محتوى PDF الخام
    $searchTerms = ['يوسف', 'تقرير', 'اليتيم', 'البريج', '032454'];
    $foundTerms = [];

    foreach ($searchTerms as $term) {
        if (mb_strpos($content, $term, 0, 'UTF-8') !== false) {
            $foundTerms[] = $term;
        }
    }

    if (!empty($foundTerms)) {
        echo "  ✅ نصوص موجودة: " . implode(', ', $foundTerms) . "\n";
    } else {
        echo "  ❌ لا توجد نصوص عربية في محتوى PDF الخام\n";
    }

    // فحص إذا كان PDF يحتوي على فونتات مُدمجة
    if (strpos($content, '/FontDescriptor') !== false) {
        echo "  ✅ PDF يحتوي على فونتات مُدمجة\n";
    } else {
        echo "  ⚠️ لا توجد فونتات مُدمجة\n";
    }

    // فحص نوع الترميز
    if (strpos($content, 'Identity-H') !== false || strpos($content, 'Identity-V') !== false) {
        echo "  ✅ يستخدم Unicode Encoding\n";
    }

    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "الرجاء فتح الملفات يدوياً في قارئ PDF للتأكد من المحتوى\n";
echo "يُفضل فتحها في Adobe Reader أو متصفح Chrome\n";
