<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$spreadsheet = IOFactory::load('template كفالات (2).xlsx');
$sheet = $spreadsheet->getActiveSheet();

echo "=== تحليل ملف الإكسل للكفالات ===" . PHP_EOL . PHP_EOL;

// قراءة أسماء الأعمدة
echo "📋 أسماء الأعمدة:" . PHP_EOL;
$highestColumn = $sheet->getHighestColumn();
$highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
$headers = [];

for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $cellValue = $sheet->getCellByColumnAndRow($col, 1)->getValue();
    $colLetter = Coordinate::stringFromColumnIndex($col);
    $headers[$colLetter] = $cellValue;
    if ($cellValue) {
        echo "  {$colLetter}: {$cellValue}" . PHP_EOL;
    }
}

echo PHP_EOL . "📊 عدد الصفوف: " . $sheet->getHighestRow() . PHP_EOL;

// تحليل الأسماء في الملف
echo PHP_EOL . "=== تحليل الأسماء في الملف ===" . PHP_EOL . PHP_EOL;

// البحث عن أعمدة الأسماء
$nameColumns = [];
foreach ($headers as $col => $header) {
    if ($header && (
        stripos($header, 'اسم') !== false ||
        stripos($header, 'المكفول') !== false ||
        stripos($header, 'المعيل') !== false ||
        stripos($header, 'name') !== false
    )) {
        $nameColumns[$col] = $header;
    }
}

echo "🔍 أعمدة الأسماء المكتشفة:" . PHP_EOL;
foreach ($nameColumns as $col => $header) {
    echo "  {$col}: {$header}" . PHP_EOL;
}

// قراءة عينة من البيانات (أول 10 صفوف)
echo PHP_EOL . "=== عينة من الأسماء (أول 10 صفوف) ===" . PHP_EOL;

$highestRow = min($sheet->getHighestRow(), 15);

for ($row = 2; $row <= $highestRow; $row++) {
    echo PHP_EOL . "📌 الصف {$row}:" . PHP_EOL;
    foreach ($nameColumns as $col => $header) {
        $colIndex = Coordinate::columnIndexFromString($col);
        $value = $sheet->getCellByColumnAndRow($colIndex, $row)->getValue();
        if ($value) {
            $segments = preg_split('/\s+/', trim($value));
            $segmentCount = count($segments);
            echo "  [{$header}]: {$value}" . PHP_EOL;
            echo "    → عدد المقاطع: {$segmentCount}" . PHP_EOL;
            echo "    → المقاطع: " . implode(' | ', $segments) . PHP_EOL;
        }
    }
}

// تحليل إحصائي لعدد مقاطع الأسماء
echo PHP_EOL . "=== تحليل إحصائي لمقاطع الأسماء ===" . PHP_EOL;

$segmentStats = [];
$allNames = [];

for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
    foreach ($nameColumns as $col => $header) {
        $colIndex = Coordinate::columnIndexFromString($col);
        $value = $sheet->getCellByColumnAndRow($colIndex, $row)->getValue();
        if ($value) {
            $segments = preg_split('/\s+/', trim($value));
            $segmentCount = count($segments);

            if (!isset($segmentStats[$header])) {
                $segmentStats[$header] = [];
            }
            if (!isset($segmentStats[$header][$segmentCount])) {
                $segmentStats[$header][$segmentCount] = 0;
            }
            $segmentStats[$header][$segmentCount]++;

            // حفظ الأسماء الطويلة للتحليل
            if ($segmentCount > 5) {
                $allNames[] = [
                    'column' => $header,
                    'name' => $value,
                    'segments' => $segmentCount,
                    'parts' => $segments
                ];
            }
        }
    }
}

foreach ($segmentStats as $header => $stats) {
    echo PHP_EOL . "📊 {$header}:" . PHP_EOL;
    ksort($stats);
    foreach ($stats as $count => $freq) {
        echo "  {$count} مقاطع: {$freq} اسم" . PHP_EOL;
    }
}

// عرض الأسماء الطويلة (أكثر من 5 مقاطع)
if (!empty($allNames)) {
    echo PHP_EOL . "=== الأسماء الطويلة (أكثر من 5 مقاطع) ===" . PHP_EOL;
    foreach ($allNames as $name) {
        echo PHP_EOL . "📌 [{$name['column']}] - {$name['segments']} مقاطع:" . PHP_EOL;
        echo "  الاسم: {$name['name']}" . PHP_EOL;
        echo "  المقاطع: " . implode(' | ', $name['parts']) . PHP_EOL;
    }
}

echo PHP_EOL . "=== انتهى التحليل ===" . PHP_EOL;
