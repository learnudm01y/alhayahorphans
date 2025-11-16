<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$excelFile = 'I:\unit test\alhayahorphans\ASO - Copy\mohammed brother\Enet_Kids_9-11-2025.xlsx';

echo "🔍 التحقق من مطابقة عناوين Excel مع الـ Mapping\n";
echo str_repeat("=", 80) . "\n\n";

$excel = IOFactory::load($excelFile);
$sheet = $excel->getActiveSheet();

// قراءة العناوين من Excel
$excelHeaders = [];
foreach ($sheet->getRowIterator(1, 1) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    foreach ($cellIterator as $cell) {
        $value = $cell->getValue();
        if ($value !== null && $value !== '') {
            $excelHeaders[] = $value;
        }
    }
}

// الـ Mapping المتوقع من الكود
$expectedMapping = [
    'registration_id' => 'registration_id',
    'first_name' => 'first_name',
    'second_name' => 'second_name',
    'third_name' => 'third_name',
    'last_name' => 'last_name',
    'person_id' => 'person_id',
    'person_birth_date' => 'person_birth_date',
    'person_age' => 'person_age',
    'person_gender' => 'person_gender',
    'person_health_status' => 'person_health_status',
    'person_type_of_guarantee' => 'person_type_of_guarantee',
    'person_note' => 'person_note',
    'sponsorship_status' => 'sponsorship_status'
];

echo "📋 عناوين Excel الموجودة:\n";
foreach ($excelHeaders as $index => $header) {
    echo "  [$index] '$header'\n";
}

echo "\n📋 الأعمدة المتوقعة في الـ Mapping:\n";
foreach (array_keys($expectedMapping) as $index => $column) {
    echo "  [$index] '$column'\n";
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// التحقق من التطابق
echo "🔍 فحص التطابق:\n\n";

$missingInExcel = [];
$missingInMapping = [];

foreach (array_keys($expectedMapping) as $expectedColumn) {
    if (!in_array($expectedColumn, $excelHeaders)) {
        $missingInExcel[] = $expectedColumn;
    }
}

foreach ($excelHeaders as $excelHeader) {
    if (!isset($expectedMapping[$excelHeader])) {
        $missingInMapping[] = $excelHeader;
    }
}

if (empty($missingInExcel) && empty($missingInMapping)) {
    echo "✅ جميع العناوين متطابقة!\n";
} else {
    if (!empty($missingInExcel)) {
        echo "❌ أعمدة موجودة في الـ Mapping لكن مفقودة في Excel:\n";
        foreach ($missingInExcel as $missing) {
            echo "  - $missing\n";
        }
        echo "\n";
    }

    if (!empty($missingInMapping)) {
        echo "⚠️ أعمدة موجودة في Excel لكن مفقودة في الـ Mapping:\n";
        foreach ($missingInMapping as $missing) {
            echo "  - $missing\n";
        }
        echo "\n";
    }
}

echo str_repeat("-", 80) . "\n\n";

// قراءة صف واحد وتطبيق الـ Mapping يدوياً
echo "🔬 محاكاة قراءة الصف 2 كما يفعل الكود:\n\n";

$row2 = [];
foreach ($sheet->getRowIterator(2, 2) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $colIndex = 0;
    foreach ($cellIterator as $cell) {
        if ($colIndex < count($excelHeaders)) {
            $row2[] = $cell->getValue();
            $colIndex++;
        }
    }
}

// تطبيق الـ Mapping كما يفعل الكود
$mappedData = [];
foreach ($excelHeaders as $index => $header) {
    $value = $row2[$index] ?? null;
    $dbColumn = $expectedMapping[$header] ?? null;

    if ($dbColumn) {
        $mappedData[$dbColumn] = $value;
    }
}

echo "البيانات بعد تطبيق الـ Mapping:\n";
foreach ($mappedData as $column => $value) {
    $displayValue = ($value === null || $value === '') ? '(NULL)' : $value;
    $isEmpty = empty($value);
    $isStrictEmpty = ($value === '' || $value === null);

    echo "  $column: $displayValue";
    echo " [empty()=" . ($isEmpty ? 'TRUE❌' : 'FALSE✓') . "]";
    echo " [!==''&&!==null=" . (!$isStrictEmpty ? 'TRUE✓' : 'FALSE❌') . "]";
    echo "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
