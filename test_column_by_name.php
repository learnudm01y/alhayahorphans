<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

echo "========================================" . PHP_EOL;
echo "🧪 اختبار شامل لقراءة الأعمدة بالاسم" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

// دالة تطبيع النص العربي (نفس الدالة في Controller)
function normalizeArabicText($text) {
    if (empty($text)) return '';
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
    $text = str_replace(['ة', 'ه'], 'ه', $text);
    $text = str_replace(['أ', 'إ', 'آ', 'ا'], 'ا', $text);
    $text = str_replace(['ى', 'ي', 'ئ'], 'ي', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return mb_strtolower(trim($text), 'UTF-8');
}

// دالة للحصول على index العمود من الاسم
function getColumnIndex($columnMap, $primaryName, $altName = null) {
    $primary = normalizeArabicText($primaryName);
    $alt = $altName ? normalizeArabicText($altName) : null;

    if (isset($columnMap[$primary])) {
        return ['index' => $columnMap[$primary], 'matched' => $primaryName];
    }
    if ($alt && isset($columnMap[$alt])) {
        return ['index' => $columnMap[$alt], 'matched' => $altName];
    }
    return ['index' => -1, 'matched' => null];
}

$spreadsheet = IOFactory::load('template كفالات (2).xlsx');
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();
$headers = array_shift($rows);

// بناء columnMap
$columnMap = [];
foreach ($headers as $index => $header) {
    $normalizedHeader = normalizeArabicText(trim($header));
    $columnMap[$normalizedHeader] = $index;
}

echo "📋 الأعمدة المكتشفة في الملف:" . PHP_EOL;
echo "--------------------------------" . PHP_EOL;
foreach ($headers as $index => $header) {
    $letter = chr(65 + $index); // A, B, C, ...
    echo "  العمود $letter (index $index): '$header'" . PHP_EOL;
}
echo PHP_EOL;

// تعريف الأعمدة المطلوبة (كما في Controller)
$requiredColumns = [
    'id' => ['primary' => 'ID'],
    'sponsored_name' => ['primary' => 'اسم المكفول'],
    'sponsored_identity' => ['primary' => 'رقم هوية المكفول'],
    'person_type' => ['primary' => 'نوع الشخص'],
    'guardian_name' => ['primary' => 'اسم المعيل'],
    'guardian_identity_number' => ['primary' => 'هوية المعيل'],
    'data_phone_number' => ['primary' => 'الهاتف'],
    'data_alt_phone_number' => ['primary' => 'جوال بديل'],
    'sponsoring_organization' => ['primary' => 'اسم الكافل', 'alt' => 'المؤسسة'],
    'person_owner_identity_number' => ['primary' => 'هوية صاحب المحفظة', 'alt' => 'هوية المحفظة'],
    're_guardian_name' => ['primary' => 'صاحب المحفظة'],
    'bank_name' => ['primary' => 'المحفظة'],
    're_phone_number' => ['primary' => 'جوال المحفظة'],
];

echo "🔍 البحث عن الأعمدة بالاسم:" . PHP_EOL;
echo "--------------------------------" . PHP_EOL;

$columnIndexes = [];
foreach ($requiredColumns as $key => $names) {
    $result = getColumnIndex($columnMap, $names['primary'], $names['alt'] ?? null);
    $columnIndexes[$key] = $result['index'];

    if ($result['index'] >= 0) {
        $letter = chr(65 + $result['index']);
        echo "  ✅ $key => العمود $letter (index {$result['index']}) - تم المطابقة مع: '{$result['matched']}'" . PHP_EOL;
    } else {
        echo "  ❌ $key => غير موجود (بحثنا عن: '{$names['primary']}'" .
             (isset($names['alt']) ? " أو '{$names['alt']}'" : "") . ")" . PHP_EOL;
    }
}
echo PHP_EOL;

echo "🧪 اختبار قراءة 5 صفوف من البيانات:" . PHP_EOL;
echo "======================================" . PHP_EOL . PHP_EOL;

for ($i = 0; $i < min(5, count($rows)); $i++) {
    $row = $rows[$i];
    $rowNumber = $i + 2;

    echo "--- الصف $rowNumber ---" . PHP_EOL;

    foreach ($columnIndexes as $key => $index) {
        if ($index >= 0) {
            $value = trim($row[$index] ?? '');
            if (!empty($value) || in_array($key, ['person_owner_identity_number', 'guardian_identity_number', 'sponsored_identity'])) {
                // عرض القيم المهمة فقط
                if (in_array($key, ['sponsored_name', 'sponsored_identity', 'guardian_name', 'guardian_identity_number',
                                   'person_owner_identity_number', 'bank_name', 're_phone_number', 'person_type'])) {
                    echo "  $key: '$value'" . PHP_EOL;
                }
            }
        }
    }
    echo PHP_EOL;
}

echo "======================================" . PHP_EOL;
echo "✅ الاختبار مكتمل - الدالة تبحث بالاسم وليس بالترتيب" . PHP_EOL;
echo "======================================" . PHP_EOL;
