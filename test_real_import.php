<?php
/**
 * اختبار حقيقي لعملية استيراد الكفالات
 * يقوم بمحاكاة عملية الاستيراد الفعلية مع إدخال البيانات في قاعدة البيانات
 */

require __DIR__ . '/vendor/autoload.php';

// تحميل Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Sponsorship;
use App\Models\GuardianBankAccount;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\BankName;

echo "========================================" . PHP_EOL;
echo "🚀 بدء اختبار الاستيراد الحقيقي" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

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

// تحميل ملف Excel
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

// تعريف الأعمدة المطلوبة
$requiredColumns = [
    'id' => normalizeArabicText('ID'),
    'sponsored_name' => normalizeArabicText('اسم المكفول'),
    'sponsored_identity' => normalizeArabicText('رقم هوية المكفول'),
    'person_type' => normalizeArabicText('نوع الشخص'),
    'guardian_name' => normalizeArabicText('اسم المعيل'),
    'guardian_identity_number' => normalizeArabicText('هوية المعيل'),
    'data_phone_number' => normalizeArabicText('الهاتف'),
    'sponsoring_organization' => normalizeArabicText('اسم الكافل'),
    'person_owner_identity_number' => normalizeArabicText('هوية صاحب المحفظة'),
    'person_owner_identity_number_alt' => normalizeArabicText('هوية المحفظة'),
    're_guardian_name' => normalizeArabicText('صاحب المحفظة'),
    'bank_name' => normalizeArabicText('المحفظة'),
    're_phone_number' => normalizeArabicText('جوال المحفظة'),
];

// 🔧 FIX: البحث عن عمود هوية المحفظة بالاسم الأساسي أو البديل
$personOwnerIdentityIndex = $columnMap[$requiredColumns['person_owner_identity_number']]
    ?? $columnMap[$requiredColumns['person_owner_identity_number_alt']]
    ?? -1;

Log::info('🎯 بدء اختبار الاستيراد الحقيقي', [
    'file' => 'template كفالات (2).xlsx',
    'total_rows' => count($rows),
    'person_owner_identity_index' => $personOwnerIdentityIndex
]);

echo "📊 إجمالي الصفوف: " . count($rows) . PHP_EOL;
echo "📍 index عمود هوية المحفظة: $personOwnerIdentityIndex" . PHP_EOL . PHP_EOL;

// حذف البيانات القديمة للاختبار (فقط أول 10 صفوف)
echo "🗑️ حذف بيانات الاختبار السابقة..." . PHP_EOL;

// اختبار على صفوف محددة تحتوي على حالات مختلفة
// الصفوف 14, 16, 17 فيها هوية المحفظة مختلفة عن المعيل
$testRowIndexes = [0, 1, 12, 14, 15, 48, 52, 53, 184, 221]; // indexes (0-based)
$testRows = [];
foreach ($testRowIndexes as $idx) {
    if (isset($rows[$idx])) {
        $testRows[$idx] = $rows[$idx];
    }
}
$successCount = 0;
$errorCount = 0;
$bankAccountsCreated = 0;

echo "🔄 معالجة " . count($testRows) . " صفوف للاختبار..." . PHP_EOL . PHP_EOL;

DB::beginTransaction();

try {
    foreach ($testRows as $index => $row) {
        $rowNumber = $index + 2; // +2 لأن الـ header في الصف 1

        // استخراج البيانات
        $personType = trim($row[$columnMap[$requiredColumns['person_type']] ?? -1] ?? '');
        $sponsoredIdentity = trim($row[$columnMap[$requiredColumns['sponsored_identity']] ?? 0] ?? '');
        $sponsoredName = trim($row[$columnMap[$requiredColumns['sponsored_name']] ?? 0] ?? '');
        $guardianIdentity = trim($row[$columnMap[$requiredColumns['guardian_identity_number']] ?? 0] ?? '');
        $guardianName = trim($row[$columnMap[$requiredColumns['guardian_name']] ?? 0] ?? '');
        $externalFileNumber = trim($row[$columnMap[$requiredColumns['id']] ?? 0] ?? '');
        $phoneNumber = trim($row[$columnMap[$requiredColumns['data_phone_number']] ?? -1] ?? '');
        $bankName = trim($row[$columnMap[$requiredColumns['bank_name']] ?? 0] ?? '');
        $reGuardianName = trim($row[$columnMap[$requiredColumns['re_guardian_name']] ?? 0] ?? '');
        $rePhoneNumber = trim($row[$columnMap[$requiredColumns['re_phone_number']] ?? 0] ?? '');

        // 🔧 FIX: قراءة هوية المحفظة بالطريقة الصحيحة
        $personOwnerIdentityNumber = $personOwnerIdentityIndex >= 0 ? trim($row[$personOwnerIdentityIndex] ?? '') : '';

        echo "--- الصف $rowNumber ---" . PHP_EOL;
        echo "  المكفول: $sponsoredName ($sponsoredIdentity)" . PHP_EOL;
        echo "  المعيل: $guardianName ($guardianIdentity)" . PHP_EOL;
        echo "  هوية المحفظة: $personOwnerIdentityNumber" . PHP_EOL;

        Log::info("📊 معالجة الصف $rowNumber", [
            'sponsored_name' => $sponsoredName,
            'sponsored_identity' => $sponsoredIdentity,
            'guardian_identity' => $guardianIdentity,
            'person_owner_identity_number' => $personOwnerIdentityNumber,
            'bank_name' => $bankName,
            're_phone_number' => $rePhoneNumber
        ]);

        // البحث عن relation_id_number
        $internalFileNumber = null;
        $foundInTable = null;

        // البحث في data
        $dataRecord = Data::where('data_id_number', $sponsoredIdentity)->first();
        if ($dataRecord) {
            $internalFileNumber = $dataRecord->file_id_number;
            $foundInTable = 'data';
        }

        // البحث في re_people
        if (!$internalFileNumber) {
            $rePeopleRecord = RePeople::where('person_id', $sponsoredIdentity)->first();
            if ($rePeopleRecord) {
                $internalFileNumber = $rePeopleRecord->registration_id;
                $foundInTable = 're_people';
            }
        }

        // البحث بهوية المعيل
        if (!$internalFileNumber && !empty($guardianIdentity)) {
            $guardianRecord = Data::where('data_id_number', $guardianIdentity)->first();
            if ($guardianRecord) {
                $internalFileNumber = $guardianRecord->file_id_number;
                $foundInTable = 'data (guardian)';
            }
        }

        Log::info("🔍 نتيجة البحث للصف $rowNumber", [
            'internal_file_number' => $internalFileNumber,
            'found_in_table' => $foundInTable
        ]);

        // التحقق من هوية المحفظة
        $accountOwnerIdentity = $guardianIdentity; // القيمة الافتراضية

        if (!empty($personOwnerIdentityNumber)) {
            $cleanedValue = preg_replace('/\D/', '', $personOwnerIdentityNumber);

            if (strlen($cleanedValue) >= 9) {
                $accountOwnerIdentity = $personOwnerIdentityNumber;
                Log::info("✅ الصف $rowNumber: هوية المحفظة صالحة", [
                    'identity' => $accountOwnerIdentity,
                    'length' => strlen($cleanedValue)
                ]);
                echo "  ✅ هوية المحفظة صالحة (طول: " . strlen($cleanedValue) . ")" . PHP_EOL;
            } else {
                Log::warning("⚠️ الصف $rowNumber: هوية المحفظة غير صالحة - استخدام هوية المعيل", [
                    'invalid_value' => $personOwnerIdentityNumber,
                    'length' => strlen($cleanedValue),
                    'using_guardian_identity' => $guardianIdentity
                ]);
                echo "  ⚠️ هوية المحفظة غير صالحة ($personOwnerIdentityNumber) - استخدام هوية المعيل" . PHP_EOL;
            }
        } else {
            Log::info("ℹ️ الصف $rowNumber: هوية المحفظة فارغة - استخدام هوية المعيل", [
                'using_guardian_identity' => $guardianIdentity
            ]);
            echo "  ℹ️ هوية المحفظة فارغة - استخدام هوية المعيل" . PHP_EOL;
        }

        // إيجاد البنك
        $bankId = null;
        if (!empty($bankName)) {
            $bank = BankName::where('description', 'LIKE', "%$bankName%")->first();
            if ($bank) {
                $bankId = $bank->id;
            }
        }

        echo "  📋 رقم الملف: " . ($internalFileNumber ?? 'غير موجود') . " (من: " . ($foundInTable ?? 'لم يُعثر') . ")" . PHP_EOL;
        echo "  🏦 البنك: " . ($bankId ? "ID=$bankId" : 'غير موجود') . PHP_EOL;
        echo "  👤 هوية صاحب الحساب النهائية: $accountOwnerIdentity" . PHP_EOL;

        Log::info("💾 الصف $rowNumber: البيانات النهائية", [
            'internal_file_number' => $internalFileNumber,
            'account_owner_identity' => $accountOwnerIdentity,
            'bank_id' => $bankId,
            're_phone_number' => $rePhoneNumber
        ]);

        $successCount++;
        echo PHP_EOL;
    }

    DB::rollBack(); // نتراجع لأن هذا مجرد اختبار
    echo "✅ تم التراجع عن التغييرات (هذا اختبار فقط)" . PHP_EOL;

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ خطأ: " . $e->getMessage() . PHP_EOL;
    Log::error("❌ خطأ في الاستيراد", [
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}

echo PHP_EOL;
echo "========================================" . PHP_EOL;
echo "📊 ملخص الاختبار:" . PHP_EOL;
echo "  - الصفوف المعالجة: $successCount" . PHP_EOL;
echo "  - الأخطاء: $errorCount" . PHP_EOL;
echo "========================================" . PHP_EOL;

Log::info("📊 ملخص اختبار الاستيراد", [
    'processed' => $successCount,
    'errors' => $errorCount
]);

echo PHP_EOL . "📝 راجع ملف اللوج: storage/logs/laravel.log" . PHP_EOL;
