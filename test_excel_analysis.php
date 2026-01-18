<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('template كفالات (2).xlsx');
$sheet = $spreadsheet->getActiveSheet();
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();

echo "=== معلومات الملف ===" . PHP_EOL;
echo "عدد الصفوف: " . $highestRow . PHP_EOL;
echo "أعلى عمود: " . $highestColumn . PHP_EOL;
echo PHP_EOL;

echo "=== Headers (الصف الأول) ===" . PHP_EOL;
$headers = [];
for ($col = 'A'; $col <= $highestColumn; $col++) {
    $value = $sheet->getCell($col . '1')->getValue();
    $headers[$col] = $value;
    echo $col . ': "' . $value . '"' . PHP_EOL;
}
echo PHP_EOL;

echo "=== تحليل أول 15 صف من البيانات ===" . PHP_EOL;
for ($row = 2; $row <= min(16, $highestRow); $row++) {
    echo "--- الصف " . $row . " ---" . PHP_EOL;

    // الأعمدة المهمة للتحليل
    $id = $sheet->getCell('A' . $row)->getValue();
    $sponsoredName = $sheet->getCell('B' . $row)->getValue();
    $sponsoredId = $sheet->getCell('C' . $row)->getValue();
    $guardianName = $sheet->getCell('D' . $row)->getValue();
    $guardianId = $sheet->getCell('E' . $row)->getValue();
    $phone = $sheet->getCell('F' . $row)->getValue();
    $altPhone = $sheet->getCell('G' . $row)->getValue();
    $province = $sheet->getCell('H' . $row)->getValue();
    $sponsorName = $sheet->getCell('I' . $row)->getValue();
    $walletId = $sheet->getCell('J' . $row)->getValue(); // هوية المحفظة
    $walletOwner = $sheet->getCell('K' . $row)->getValue(); // صاحب المحفظة
    $walletName = $sheet->getCell('L' . $row)->getValue(); // اسم المحفظة
    $walletPhone = $sheet->getCell('M' . $row)->getValue(); // جوال المحفظة
    $personType = $sheet->getCell('N' . $row)->getValue(); // نوع الشخص

    echo "  ID: " . $id . PHP_EOL;
    echo "  اسم المكفول: " . $sponsoredName . PHP_EOL;
    echo "  هوية المكفول: " . $sponsoredId . PHP_EOL;
    echo "  اسم المعيل: " . $guardianName . PHP_EOL;
    echo "  هوية المعيل: " . $guardianId . PHP_EOL;
    echo "  الهاتف: " . $phone . PHP_EOL;
    echo "  📱 هوية المحفظة (J): " . $walletId . PHP_EOL;
    echo "  👤 صاحب المحفظة (K): " . $walletOwner . PHP_EOL;
    echo "  🏦 اسم المحفظة (L): " . $walletName . PHP_EOL;
    echo "  📞 جوال المحفظة (M): " . $walletPhone . PHP_EOL;
    echo "  نوع الشخص (N): " . $personType . PHP_EOL;
    echo PHP_EOL;
}

echo "=== تحليل عمود هوية المحفظة (J) ===" . PHP_EOL;
$walletIdStats = [
    'empty' => 0,
    'valid_9_digits' => 0,
    'invalid_short' => 0,
    'other' => 0
];
$invalidExamples = [];

for ($row = 2; $row <= $highestRow; $row++) {
    $walletId = $sheet->getCell('J' . $row)->getValue();

    if (empty($walletId)) {
        $walletIdStats['empty']++;
    } elseif (strlen((string)$walletId) == 9 && is_numeric($walletId)) {
        $walletIdStats['valid_9_digits']++;
    } elseif (strlen((string)$walletId) < 9) {
        $walletIdStats['invalid_short']++;
        if (count($invalidExamples) < 10) {
            $guardianId = $sheet->getCell('E' . $row)->getValue();
            $walletOwner = $sheet->getCell('K' . $row)->getValue();
            $invalidExamples[] = [
                'row' => $row,
                'wallet_id' => $walletId,
                'guardian_id' => $guardianId,
                'wallet_owner' => $walletOwner
            ];
        }
    } else {
        $walletIdStats['other']++;
    }
}

echo "فارغ: " . $walletIdStats['empty'] . PHP_EOL;
echo "صالح (9 أرقام): " . $walletIdStats['valid_9_digits'] . PHP_EOL;
echo "قصير (أقل من 9): " . $walletIdStats['invalid_short'] . PHP_EOL;
echo "أخرى: " . $walletIdStats['other'] . PHP_EOL;
echo PHP_EOL;

echo "=== أمثلة على قيم هوية المحفظة القصيرة ===" . PHP_EOL;
foreach ($invalidExamples as $ex) {
    echo "الصف " . $ex['row'] . ": هوية المحفظة=" . $ex['wallet_id'] .
         ", هوية المعيل=" . $ex['guardian_id'] .
         ", صاحب المحفظة=" . $ex['wallet_owner'] . PHP_EOL;
}
