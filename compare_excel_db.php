<?php
/**
 * مقارنة تفصيلية بين ملف Excel وقاعدة البيانات
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\GuardianBankAccount;

function normalizeArabicText($text) {
    if (empty($text)) return '';
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
    $text = str_replace(['ة', 'ه'], 'ه', $text);
    $text = str_replace(['أ', 'إ', 'آ', 'ا'], 'ا', $text);
    $text = str_replace(['ى', 'ي', 'ئ'], 'ي', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return mb_strtolower(trim($text), 'UTF-8');
}

echo "========================================" . PHP_EOL;
echo "🔍 مقارنة تفصيلية: Excel vs قاعدة البيانات" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

// تحميل Excel
$spreadsheet = IOFactory::load('template كفالات (2).xlsx');
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();
$headers = array_shift($rows);

$columnMap = [];
foreach ($headers as $index => $header) {
    $normalizedHeader = normalizeArabicText(trim($header));
    $columnMap[$normalizedHeader] = $index;
}

$walletIdIndex = $columnMap[normalizeArabicText('هوية المحفظة')] ?? -1;
$guardianIdIndex = $columnMap[normalizeArabicText('هوية المعيل')] ?? -1;

// الحصول على سجلات مختلفة من قاعدة البيانات
$differentAccounts = GuardianBankAccount::whereRaw('person_owner_identity_number != re_id_number')->get();

echo "📊 عدد السجلات حيث صاحب المحفظة ≠ المعيل: " . $differentAccounts->count() . PHP_EOL . PHP_EOL;

echo "========================================" . PHP_EOL;
echo "🔄 مقارنة تفصيلية للحالات المختلفة:" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

$matchCount = 0;
$mismatchCount = 0;

foreach ($differentAccounts as $account) {
    // البحث عن الصف المقابل في Excel بواسطة re_id_number
    $foundInExcel = false;

    foreach ($rows as $index => $row) {
        $excelGuardianId = trim($row[$guardianIdIndex] ?? '');
        $excelWalletId = trim($row[$walletIdIndex] ?? '');

        if ($excelGuardianId == $account->re_id_number && !empty($excelWalletId)) {
            $foundInExcel = true;
            $rowNumber = $index + 2;

            $isMatch = ($excelWalletId == $account->person_owner_identity_number);

            if ($isMatch) {
                $matchCount++;
            } else {
                $mismatchCount++;
                echo "❌ عدم تطابق في الصف $rowNumber:" . PHP_EOL;
                echo "   Excel هوية المحفظة: $excelWalletId" . PHP_EOL;
                echo "   DB person_owner_identity: {$account->person_owner_identity_number}" . PHP_EOL;
                echo PHP_EOL;
            }
            break;
        }
    }
}

echo "========================================" . PHP_EOL;
echo "📊 نتيجة المقارنة:" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo "  ✅ متطابقة (Excel = DB): $matchCount" . PHP_EOL;
echo "  ❌ غير متطابقة: $mismatchCount" . PHP_EOL;
echo PHP_EOL;

if ($mismatchCount == 0) {
    echo "🎉 جميع القيم متطابقة! الإصلاح يعمل بشكل صحيح 100%" . PHP_EOL;
} else {
    echo "⚠️ يوجد $mismatchCount قيم غير متطابقة" . PHP_EOL;
}

// عرض بعض الأمثلة للتأكيد
echo PHP_EOL . "========================================" . PHP_EOL;
echo "📋 أمثلة للتأكيد (أول 5 حالات مختلفة):" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

$count = 0;
foreach ($rows as $index => $row) {
    if ($count >= 5) break;

    $excelGuardianId = trim($row[$guardianIdIndex] ?? '');
    $excelWalletId = trim($row[$walletIdIndex] ?? '');
    $sponsoredName = trim($row[1] ?? '');

    if (!empty($excelWalletId) && $excelWalletId != $excelGuardianId) {
        $rowNumber = $index + 2;
        $count++;

        // البحث في قاعدة البيانات
        $dbAccount = GuardianBankAccount::where('re_id_number', $excelGuardianId)
            ->where('person_owner_identity_number', $excelWalletId)
            ->first();

        echo "الصف $rowNumber: $sponsoredName" . PHP_EOL;
        echo "  📄 Excel:" . PHP_EOL;
        echo "     - هوية المعيل: $excelGuardianId" . PHP_EOL;
        echo "     - هوية المحفظة: $excelWalletId" . PHP_EOL;
        echo "  🗄️ قاعدة البيانات:" . PHP_EOL;

        if ($dbAccount) {
            echo "     - re_id_number: {$dbAccount->re_id_number}" . PHP_EOL;
            echo "     - person_owner_identity: {$dbAccount->person_owner_identity_number}" . PHP_EOL;
            echo "  ✅ متطابق!" . PHP_EOL;
        } else {
            echo "     - غير موجود بهذه القيم" . PHP_EOL;
        }
        echo PHP_EOL;
    }
}
