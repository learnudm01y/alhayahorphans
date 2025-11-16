<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$excelFile = 'I:\unit test\alhayahorphans\ASO - Copy\mohammed brother\Enet_Kids_9-11-2025.xlsx';

echo "🔍 فحص ملف Excel: Enet_Kids_9-11-2025.xlsx\n";
echo str_repeat("=", 80) . "\n\n";

$excel = IOFactory::load($excelFile);
$sheet = $excel->getActiveSheet();

// قراءة العناوين
$headers = [];
foreach ($sheet->getRowIterator(1, 1) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    foreach ($cellIterator as $cell) {
        $value = $cell->getValue();
        if ($value !== null && $value !== '') {
            $headers[] = $value;
        }
    }
}

echo "📋 عناوين الأعمدة في Excel:\n";
foreach ($headers as $index => $header) {
    echo "  [$index] $header\n";
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// قراءة أول 5 صفوف
echo "📊 بيانات أول 5 صفوف:\n\n";

for ($rowNum = 2; $rowNum <= 6; $rowNum++) {
    echo "الصف $rowNum:\n";
    $rowData = [];
    foreach ($sheet->getRowIterator($rowNum, $rowNum) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $colIndex = 0;
        foreach ($cellIterator as $cell) {
            if ($colIndex < count($headers)) {
                $value = $cell->getValue();
                $rowData[$headers[$colIndex]] = $value;
                $colIndex++;
            }
        }
    }

    foreach ($rowData as $key => $value) {
        $displayValue = ($value === null || $value === '') ? '(EMPTY)' : $value;
        echo "  $key: $displayValue\n";
    }
    echo "\n";
}

echo str_repeat("=", 80) . "\n\n";

// التحقق من أنواع البيانات
echo "🔬 تحليل أنواع البيانات:\n\n";

$row2Data = [];
foreach ($sheet->getRowIterator(2, 2) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $colIndex = 0;
    foreach ($cellIterator as $cell) {
        if ($colIndex < count($headers)) {
            $value = $cell->getValue();
            $row2Data[$headers[$colIndex]] = [
                'value' => $value,
                'type' => gettype($value),
                'is_numeric' => is_numeric($value),
                'is_string' => is_string($value),
                'empty_check' => empty($value),
                'strict_empty' => ($value === '' || $value === null)
            ];
            $colIndex++;
        }
    }
}

foreach ($row2Data as $columnName => $info) {
    echo "[$columnName]:\n";
    echo "  القيمة: " . ($info['value'] ?? 'NULL') . "\n";
    echo "  النوع: {$info['type']}\n";
    echo "  is_numeric: " . ($info['is_numeric'] ? 'YES' : 'NO') . "\n";
    echo "  empty(): " . ($info['empty_check'] ? 'TRUE (سيتحول لـ NULL!)' : 'FALSE (صحيح)') . "\n";
    echo "  !== '' && !== null: " . (!$info['strict_empty'] ? 'TRUE (صحيح)' : 'FALSE (سيتحول لـ NULL!)') . "\n";
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
