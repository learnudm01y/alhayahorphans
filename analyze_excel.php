<?php
/**
 * تحليل ملف Excel لفهم بنية البيانات
 */

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = __DIR__ . '/template كفالات (2).xlsx';

if (!file_exists($filePath)) {
    echo "الملف غير موجود: $filePath\n";
    exit(1);
}

echo "=== تحليل ملف الكفالات ===\n\n";

$spreadsheet = IOFactory::load($filePath);
$worksheet = $spreadsheet->getActiveSheet();

// قراءة الصف الأول (العناوين)
$headers = [];
$highestColumn = $worksheet->getHighestColumn();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

echo "عدد الأعمدة: $highestColumnIndex\n\n";

echo "📋 أسماء الأعمدة:\n";
echo str_repeat('-', 60) . "\n";

for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $header = $worksheet->getCell($colLetter . '1')->getValue();
    $headers[$col] = $header;
    echo "[$col] العمود $colLetter: $header\n";
}

// قراءة أول 5 صفوف من البيانات
echo "\n📊 عينة من البيانات (أول 5 صفوف):\n";
echo str_repeat('=', 100) . "\n\n";

$highestRow = min(6, $worksheet->getHighestRow()); // أول 5 صفوف + الهيدر

for ($row = 2; $row <= $highestRow; $row++) {
    echo "🔸 الصف $row:\n";
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $value = $worksheet->getCell($colLetter . $row)->getValue();
        if (!empty($value)) {
            echo "   • {$headers[$col]}: $value\n";
        }
    }
    echo "\n";
}

// البحث عن أعمدة الأسماء
echo "\n🔍 تحليل أعمدة الأسماء:\n";
echo str_repeat('-', 60) . "\n";

$nameColumns = [];
foreach ($headers as $colIndex => $header) {
    $headerLower = mb_strtolower((string)$header, 'UTF-8');
    if (mb_strpos($headerLower, 'اسم') !== false ||
        mb_strpos($headerLower, 'الاسم') !== false ||
        mb_strpos($headerLower, 'name') !== false) {
        $nameColumns[$colIndex] = $header;
    }
}

echo "أعمدة تحتوي على 'اسم':\n";
foreach ($nameColumns as $colIndex => $header) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    echo "  [$colIndex] $colLetter: $header\n";
}

echo "\n✅ انتهى التحليل\n";
