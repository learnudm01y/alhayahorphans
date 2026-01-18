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

echo "=== Headers الأصلية ===" . PHP_EOL;
foreach ($headers as $col => $header) {
    echo "$col: '$header'" . PHP_EOL;
}
echo PHP_EOL;

// بناء columnMap كما يفعل Controller
$columnMap = [];
$colIndex = 0;
foreach ($headers as $col => $header) {
    $normalizedHeader = normalizeArabicText(trim($header));
    $columnMap[$normalizedHeader] = $colIndex;
    $colIndex++;
}

echo "=== columnMap (بعد التطبيع) ===" . PHP_EOL;
foreach ($columnMap as $normalized => $index) {
    echo "'$normalized' => $index" . PHP_EOL;
}
echo PHP_EOL;

// تعريف الأعمدة المطلوبة (كما في Controller)
$requiredColumns = [
    'person_owner_identity_number' => normalizeArabicText('هوية صاحب المحفظة'),
    'person_owner_identity_number_alt' => normalizeArabicText('هوية المحفظة'),
];

echo "=== البحث عن عمود هوية المحفظة ===" . PHP_EOL;
echo "person_owner_identity_number: '" . $requiredColumns['person_owner_identity_number'] . "'" . PHP_EOL;
echo "person_owner_identity_number_alt: '" . $requiredColumns['person_owner_identity_number_alt'] . "'" . PHP_EOL;
echo PHP_EOL;

// التحقق من وجود الأعمدة في columnMap
echo "=== نتيجة البحث ===" . PHP_EOL;

$foundColumn = null;
$foundIndex = null;

// البحث بالاسم الأول
if (isset($columnMap[$requiredColumns['person_owner_identity_number']])) {
    $foundColumn = 'person_owner_identity_number';
    $foundIndex = $columnMap[$requiredColumns['person_owner_identity_number']];
    echo "✅ تم العثور على: 'هوية صاحب المحفظة' في العمود: $foundIndex" . PHP_EOL;
} else {
    echo "❌ لم يتم العثور على: 'هوية صاحب المحفظة'" . PHP_EOL;
}

// البحث بالاسم البديل
if (isset($columnMap[$requiredColumns['person_owner_identity_number_alt']])) {
    $foundColumn = 'person_owner_identity_number_alt';
    $foundIndex = $columnMap[$requiredColumns['person_owner_identity_number_alt']];
    echo "✅ تم العثور على: 'هوية المحفظة' في العمود: $foundIndex" . PHP_EOL;
} else {
    echo "❌ لم يتم العثور على: 'هوية المحفظة'" . PHP_EOL;
}

echo PHP_EOL;

// قراءة أول 5 صفوف لاختبار القراءة
echo "=== اختبار قراءة البيانات ===" . PHP_EOL;
$rows = $sheet->toArray();
array_shift($rows); // إزالة الـ header

for ($i = 0; $i < min(5, count($rows)); $i++) {
    $row = $rows[$i];
    $rowNumber = $i + 2;

    // طريقة Controller الحالية
    $personOwnerIdentityNumber = trim($row[$columnMap[$requiredColumns['person_owner_identity_number']] ?? 0] ?? '');

    echo "الصف $rowNumber:" . PHP_EOL;
    echo "  - العمود المستخدم (من columnMap): " . ($columnMap[$requiredColumns['person_owner_identity_number']] ?? 'غير موجود') . PHP_EOL;
    echo "  - القيمة المقروءة: '$personOwnerIdentityNumber'" . PHP_EOL;
    echo "  - القيمة الفعلية في J (العمود 9): '" . ($row[9] ?? 'NULL') . "'" . PHP_EOL;
    echo PHP_EOL;
}

echo "=== تحليل المشكلة ===" . PHP_EOL;
echo "العمود J (هوية المحفظة) يجب أن يكون index 9" . PHP_EOL;
echo "columnMap يعطي: " . ($columnMap[$requiredColumns['person_owner_identity_number_alt']] ?? 'غير موجود') . PHP_EOL;
