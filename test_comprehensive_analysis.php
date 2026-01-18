<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// دالة تطبيع النص العربي
function normalizeArabicText($text) {
    if (empty($text)) return '';
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
    $text = str_replace(['ة', 'ه'], 'ه', $text);
    $text = str_replace(['أ', 'إ', 'آ', 'ا'], 'ا', $text);
    $text = str_replace(['ى', 'ي', 'ئ'], 'ي', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return mb_strtolower(trim($text), 'UTF-8');
}

$spreadsheet = IOFactory::load('template كفالات (2).xlsx');
$sheet = $spreadsheet->getActiveSheet();
$highestRow = $sheet->getHighestRow();

// بناء columnMap
$columnMap = [];
for ($col = 'A', $i = 0; $col <= 'N'; $col++, $i++) {
    $header = normalizeArabicText(trim($sheet->getCell($col . '1')->getValue()));
    $columnMap[$header] = $i;
}

$personOwnerIdentityIndex = $columnMap[normalizeArabicText('هوية المحفظة')] ?? -1;
$guardianIdentityIndex = $columnMap[normalizeArabicText('هوية المعيل')] ?? -1;

echo "=== تحليل شامل لجميع الصفوف ===" . PHP_EOL;
echo "إجمالي الصفوف: " . ($highestRow - 1) . PHP_EOL;
echo PHP_EOL;

$rows = $sheet->toArray();
array_shift($rows);

$stats = [
    'total' => 0,
    'wallet_id_valid' => 0,
    'wallet_id_empty' => 0,
    'wallet_id_matches_guardian' => 0,
    'wallet_id_different_from_guardian' => 0,
    'guardian_empty' => 0,
];

$differentWalletExamples = [];

foreach ($rows as $i => $row) {
    $rowNumber = $i + 2;
    $stats['total']++;

    $walletId = trim($row[$personOwnerIdentityIndex] ?? '');
    $guardianId = trim($row[$guardianIdentityIndex] ?? '');

    if (empty($walletId)) {
        $stats['wallet_id_empty']++;
    } else {
        $stats['wallet_id_valid']++;

        if (empty($guardianId)) {
            $stats['guardian_empty']++;
        } elseif ($walletId === $guardianId) {
            $stats['wallet_id_matches_guardian']++;
        } else {
            $stats['wallet_id_different_from_guardian']++;
            if (count($differentWalletExamples) < 10) {
                $differentWalletExamples[] = [
                    'row' => $rowNumber,
                    'wallet_id' => $walletId,
                    'guardian_id' => $guardianId,
                    'sponsored_name' => trim($row[1] ?? ''),
                    'guardian_name' => trim($row[3] ?? ''),
                    'wallet_owner' => trim($row[10] ?? ''),
                ];
            }
        }
    }
}

echo "=== الإحصائيات ===" . PHP_EOL;
echo "إجمالي السجلات: {$stats['total']}" . PHP_EOL;
echo "هوية المحفظة موجودة: {$stats['wallet_id_valid']}" . PHP_EOL;
echo "هوية المحفظة فارغة: {$stats['wallet_id_empty']}" . PHP_EOL;
echo PHP_EOL;

echo "من السجلات التي فيها هوية محفظة:" . PHP_EOL;
echo "  - متطابقة مع هوية المعيل: {$stats['wallet_id_matches_guardian']}" . PHP_EOL;
echo "  - مختلفة عن هوية المعيل: {$stats['wallet_id_different_from_guardian']}" . PHP_EOL;
echo "  - هوية المعيل فارغة: {$stats['guardian_empty']}" . PHP_EOL;
echo PHP_EOL;

if (!empty($differentWalletExamples)) {
    echo "=== أمثلة على سجلات هوية المحفظة مختلفة عن المعيل ===" . PHP_EOL;
    foreach ($differentWalletExamples as $ex) {
        echo "الصف {$ex['row']}:" . PHP_EOL;
        echo "  اسم المكفول: {$ex['sponsored_name']}" . PHP_EOL;
        echo "  هوية المحفظة: {$ex['wallet_id']}" . PHP_EOL;
        echo "  هوية المعيل: {$ex['guardian_id']}" . PHP_EOL;
        echo "  اسم المعيل: {$ex['guardian_name']}" . PHP_EOL;
        echo "  صاحب المحفظة: {$ex['wallet_owner']}" . PHP_EOL;
        echo PHP_EOL;
    }
}

echo "=== تحليل السجلات الفارغة ===" . PHP_EOL;
$emptyWalletExamples = [];
foreach ($rows as $i => $row) {
    $rowNumber = $i + 2;
    $walletId = trim($row[$personOwnerIdentityIndex] ?? '');
    $guardianId = trim($row[$guardianIdentityIndex] ?? '');

    if (empty($walletId) && count($emptyWalletExamples) < 5) {
        $emptyWalletExamples[] = [
            'row' => $rowNumber,
            'guardian_id' => $guardianId,
            'sponsored_name' => trim($row[1] ?? ''),
            'guardian_name' => trim($row[3] ?? ''),
        ];
    }
}

echo "أمثلة على سجلات هوية المحفظة فارغة:" . PHP_EOL;
foreach ($emptyWalletExamples as $ex) {
    echo "  الصف {$ex['row']}: المكفول={$ex['sponsored_name']}, المعيل={$ex['guardian_name']} ({$ex['guardian_id']})" . PHP_EOL;
}
