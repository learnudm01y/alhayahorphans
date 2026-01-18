<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// دالة تطبيع النص العربي (نفس الدالة في Controller)
function normalizeArabicText($text) {
    if (empty($text)) {
        return '';
    }

    // إزالة التشكيل
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);

    // توحيد الهاء والتاء المربوطة
    $text = str_replace(['ة', 'ه'], 'ه', $text);

    // توحيد الألف
    $text = str_replace(['أ', 'إ', 'آ', 'ا'], 'ا', $text);

    // توحيد الياء
    $text = str_replace(['ى', 'ي', 'ئ'], 'ي', $text);

    // إزالة المسافات الزائدة
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // تحويل للحروف الصغيرة للمقارنة
    $text = mb_strtolower($text, 'UTF-8');

    return $text;
}

$spreadsheet = IOFactory::load('template كفالات (2).xlsx');
$sheet = $spreadsheet->getActiveSheet();

// قراءة Headers
$headers = [];
for ($col = 'A'; $col <= 'N'; $col++) {
    $headers[$col] = $sheet->getCell($col . '1')->getValue();
}

// بناء columnMap كما يفعل Controller
$columnMap = [];
$colIndex = 0;
foreach ($headers as $col => $header) {
    $normalizedHeader = normalizeArabicText(trim($header));
    $columnMap[$normalizedHeader] = $colIndex;
    $colIndex++;
}

// تعريف الأعمدة المطلوبة (كما في Controller)
$requiredColumns = [
    'person_owner_identity_number' => normalizeArabicText('هوية صاحب المحفظة'),
    'person_owner_identity_number_alt' => normalizeArabicText('هوية المحفظة'),
];

echo "=== اختبار الإصلاح الجديد ===" . PHP_EOL;
echo PHP_EOL;

// الطريقة الجديدة (بعد الإصلاح)
$personOwnerIdentityIndex = $columnMap[$requiredColumns['person_owner_identity_number']]
    ?? $columnMap[$requiredColumns['person_owner_identity_number_alt']]
    ?? -1;

echo "personOwnerIdentityIndex = $personOwnerIdentityIndex" . PHP_EOL;
echo PHP_EOL;

// قراءة أول 10 صفوف لاختبار القراءة
echo "=== اختبار قراءة البيانات بالطريقة الجديدة ===" . PHP_EOL;
$rows = $sheet->toArray();
array_shift($rows); // إزالة الـ header

for ($i = 0; $i < min(10, count($rows)); $i++) {
    $row = $rows[$i];
    $rowNumber = $i + 2;

    // الطريقة الجديدة
    $personOwnerIdentityNumber = $personOwnerIdentityIndex >= 0 ? trim($row[$personOwnerIdentityIndex] ?? '') : '';
    $guardianIdentity = trim($row[$columnMap[normalizeArabicText('هوية المعيل')] ?? 0] ?? '');

    echo "الصف $rowNumber:" . PHP_EOL;
    echo "  ✅ هوية المحفظة (من العمود J): '$personOwnerIdentityNumber'" . PHP_EOL;
    echo "  ✅ هوية المعيل (من العمود E): '$guardianIdentity'" . PHP_EOL;

    // التحقق من صلاحية القيمة
    $cleanedValue = preg_replace('/\D/', '', $personOwnerIdentityNumber);
    if (strlen($cleanedValue) >= 9) {
        echo "  ✅ رقم هوية صالح (طول: " . strlen($cleanedValue) . ")" . PHP_EOL;
    } elseif (!empty($personOwnerIdentityNumber)) {
        echo "  ⚠️ رقم غير صالح (طول: " . strlen($cleanedValue) . ") - سيتم استخدام هوية المعيل" . PHP_EOL;
    } else {
        echo "  ⚠️ فارغ - سيتم استخدام هوية المعيل" . PHP_EOL;
    }
    echo PHP_EOL;
}

echo "=== الخلاصة ===" . PHP_EOL;
echo "العمود 'هوية المحفظة' موجود في index: $personOwnerIdentityIndex (العمود J)" . PHP_EOL;
echo "الآن سيتم قراءة القيم الصحيحة من العمود J بدلاً من العمود A (ID)" . PHP_EOL;
