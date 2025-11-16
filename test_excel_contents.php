<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = 'I:\\unit test\\alhayahorphans\\ASO - Copy\\storage\\app\\public\\excel_files\\a0Kzz51H4OYdH6KTqT8iArtj1p6R4RQi4jOc6G0o.xlsx';

if (!file_exists($filePath)) {
    die("❌ الملف غير موجود\n");
}

echo "📂 قراءة ملف Excel...\n\n";

try {
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    echo "📊 معلومات الملف:\n";
    echo "  - عدد الصفوف: $highestRow\n";
    echo "  - آخر عمود: $highestColumn\n\n";

    // قراءة العناوين (السطر الأول)
    echo "📋 العناوين (السطر الأول):\n";
    $headers = [];
    foreach (range('A', $highestColumn) as $col) {
        $value = $sheet->getCell($col . '1')->getValue();
        $headers[] = $value;
        echo "  $col: " . ($value ?? 'null') . "\n";
    }

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // قراءة أول 3 صفوف بيانات
    echo "📝 عينة من البيانات (أول 3 صفوف):\n\n";

    for ($row = 2; $row <= min(4, $highestRow); $row++) {
        echo "الصف $row:\n";
        foreach (range('A', $highestColumn) as $i => $col) {
            $value = $sheet->getCell($col . $row)->getValue();
            $header = $headers[$i] ?? "عمود $col";
            echo "  $header: " . ($value ?? 'null') . "\n";
        }
        echo "\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ في قراءة الملف: " . $e->getMessage() . "\n";
}

echo "\n✅ انتهى الفحص\n";
