<?php
/**
 * اختبار شامل لملف Excel بالكامل
 * إدخال جميع الـ 580 صف في قاعدة البيانات
 */

require __DIR__ . '/vendor/autoload.php';

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
use App\Models\BankName;

echo "========================================" . PHP_EOL;
echo "🚀 اختبار شامل - إدخال ملف Excel بالكامل" . PHP_EOL;
echo "========================================" . PHP_EOL . PHP_EOL;

// مسح البيانات السابقة للاختبار
echo "🗑️ حذف بيانات الاختبار السابقة..." . PHP_EOL;
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
GuardianBankAccount::truncate();
DB::table('sponsorship_sponsor')->truncate();
Sponsorship::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1;');
echo "✅ تم حذف البيانات السابقة" . PHP_EOL . PHP_EOL;

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

// تعريف الأعمدة
$requiredColumns = [
    'id' => normalizeArabicText('ID'),
    'sponsored_name' => normalizeArabicText('اسم المكفول'),
    'sponsored_identity' => normalizeArabicText('رقم هوية المكفول'),
    'person_type' => normalizeArabicText('نوع الشخص'),
    'guardian_name' => normalizeArabicText('اسم المعيل'),
    'guardian_identity_number' => normalizeArabicText('هوية المعيل'),
    'data_phone_number' => normalizeArabicText('الهاتف'),
    'person_owner_identity_number' => normalizeArabicText('هوية صاحب المحفظة'),
    'person_owner_identity_number_alt' => normalizeArabicText('هوية المحفظة'),
    're_guardian_name' => normalizeArabicText('صاحب المحفظة'),
    'bank_name' => normalizeArabicText('المحفظة'),
    're_phone_number' => normalizeArabicText('جوال المحفظة'),
];

$personOwnerIdentityIndex = $columnMap[$requiredColumns['person_owner_identity_number']]
    ?? $columnMap[$requiredColumns['person_owner_identity_number_alt']]
    ?? -1;

$totalRows = count($rows);
echo "📊 إجمالي الصفوف: $totalRows" . PHP_EOL;
echo "📍 index عمود هوية المحفظة: $personOwnerIdentityIndex" . PHP_EOL . PHP_EOL;

Log::info('🎯 بدء الاختبار الشامل', [
    'total_rows' => $totalRows,
    'person_owner_identity_index' => $personOwnerIdentityIndex
]);

$stats = [
    'sponsorships_created' => 0,
    'bank_accounts_created' => 0,
    'wallet_id_valid' => 0,
    'wallet_id_empty' => 0,
    'wallet_id_invalid' => 0,
    'wallet_different_from_guardian' => 0,
    'wallet_same_as_guardian' => 0,
    'errors' => 0,
];

$startTime = microtime(true);

DB::beginTransaction();

try {
    foreach ($rows as $index => $row) {
        $rowNumber = $index + 2;

        // تخطي الصفوف الفارغة
        if (empty(array_filter($row))) {
            continue;
        }

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

        // قراءة هوية المحفظة بالطريقة الصحيحة
        $personOwnerIdentityNumber = $personOwnerIdentityIndex >= 0 ? trim($row[$personOwnerIdentityIndex] ?? '') : '';

        // البحث عن relation_id_number
        $internalFileNumber = null;

        $rePeopleRecord = RePeople::where('person_id', $sponsoredIdentity)->first();
        if ($rePeopleRecord) {
            $internalFileNumber = $rePeopleRecord->registration_id;
        }

        if (!$internalFileNumber && !empty($guardianIdentity)) {
            $guardianRecord = Data::where('data_id_number', $guardianIdentity)->first();
            if ($guardianRecord) {
                $internalFileNumber = $guardianRecord->file_id_number;
            }
        }

        $displayFileNumber = str_pad($rowNumber, 6, '0', STR_PAD_LEFT);

        // تحديد هوية صاحب الحساب
        $accountOwnerIdentity = $guardianIdentity;

        if (!empty($personOwnerIdentityNumber)) {
            $cleanedValue = preg_replace('/\D/', '', $personOwnerIdentityNumber);
            if (strlen($cleanedValue) >= 9) {
                $accountOwnerIdentity = $personOwnerIdentityNumber;
                $stats['wallet_id_valid']++;

                if ($personOwnerIdentityNumber != $guardianIdentity) {
                    $stats['wallet_different_from_guardian']++;
                } else {
                    $stats['wallet_same_as_guardian']++;
                }
            } else {
                $stats['wallet_id_invalid']++;
            }
        } else {
            $stats['wallet_id_empty']++;
        }

        // إيجاد البنك
        $bankId = null;
        if (!empty($bankName)) {
            $bank = BankName::where('description', 'LIKE', "%$bankName%")->first();
            if ($bank) {
                $bankId = $bank->id;
            }
        }

        // إدخال في جدول sponsorships
        $sponsorship = new Sponsorship();
        $sponsorship->identity_number = $sponsoredIdentity;
        $sponsorship->orphan_name = $sponsoredName;
        $sponsorship->guardian_identity_number = $guardianIdentity;
        $sponsorship->guardian_name = $guardianName;
        $sponsorship->relation_id_number = $internalFileNumber;
        $sponsorship->internal_file_number = $displayFileNumber;
        $sponsorship->external_file_number = $externalFileNumber;
        $sponsorship->person_type = 'family_member';
        $sponsorship->sponsor_id = 1;
        $sponsorship->sponsorship_type_id = 1;
        $sponsorship->sponsorship_status_id = 4;
        $sponsorship->save();

        $stats['sponsorships_created']++;

        // إدخال في جدول guardian_bank_accounts
        if ($bankId && !empty($accountOwnerIdentity)) {
            $bankAccount = new GuardianBankAccount();
            $bankAccount->guardian_registration = $internalFileNumber ?? $displayFileNumber;
            $bankAccount->person_owner_identity_number = $accountOwnerIdentity;
            $bankAccount->re_id_number = $guardianIdentity;
            $bankAccount->re_guardian_name = $reGuardianName ?: $guardianName;
            $bankAccount->bank_name = $bankId;
            $bankAccount->re_phone_number = $rePhoneNumber ?: $phoneNumber;
            $bankAccount->save();

            $stats['bank_accounts_created']++;
        }

        // عرض التقدم كل 50 صف
        if ($rowNumber % 50 == 0) {
            echo "  ⏳ تم معالجة $rowNumber / $totalRows صف..." . PHP_EOL;
        }
    }

    DB::commit();
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo PHP_EOL . "✅ تم الإدخال بنجاح!" . PHP_EOL;
    echo "⏱️ الوقت المستغرق: {$duration} ثانية" . PHP_EOL;

} catch (\Exception $e) {
    DB::rollBack();
    $stats['errors']++;
    echo "❌ خطأ: " . $e->getMessage() . PHP_EOL;
    Log::error("❌ خطأ", ['message' => $e->getMessage()]);
}

echo PHP_EOL;
echo "========================================" . PHP_EOL;
echo "📊 النتائج النهائية:" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo "  📋 Sponsorships تم إنشاؤها: {$stats['sponsorships_created']}" . PHP_EOL;
echo "  🏦 Bank Accounts تم إنشاؤها: {$stats['bank_accounts_created']}" . PHP_EOL;
echo PHP_EOL;
echo "  📱 هوية المحفظة:" . PHP_EOL;
echo "     - صالحة (9+ أرقام): {$stats['wallet_id_valid']}" . PHP_EOL;
echo "     - فارغة: {$stats['wallet_id_empty']}" . PHP_EOL;
echo "     - غير صالحة: {$stats['wallet_id_invalid']}" . PHP_EOL;
echo PHP_EOL;
echo "  🔄 مقارنة هوية المحفظة مع المعيل:" . PHP_EOL;
echo "     - متطابقة: {$stats['wallet_same_as_guardian']}" . PHP_EOL;
echo "     - مختلفة: {$stats['wallet_different_from_guardian']}" . PHP_EOL;
echo "========================================" . PHP_EOL;

// التحقق من قاعدة البيانات
echo PHP_EOL . "🔍 التحقق من قاعدة البيانات:" . PHP_EOL;
echo "================================" . PHP_EOL;

$totalSponsorships = Sponsorship::count();
$totalBankAccounts = GuardianBankAccount::count();
$differentWallets = GuardianBankAccount::whereRaw('person_owner_identity_number != re_id_number')->count();
$sameWallets = $totalBankAccounts - $differentWallets;

echo "  جدول sponsorships: $totalSponsorships سجل" . PHP_EOL;
echo "  جدول guardian_bank_accounts: $totalBankAccounts سجل" . PHP_EOL;
echo "    - صاحب المحفظة = المعيل: $sameWallets" . PHP_EOL;
echo "    - صاحب المحفظة ≠ المعيل: $differentWallets" . PHP_EOL;

// عرض أمثلة على الحالات المختلفة
echo PHP_EOL . "📋 أمثلة على حالات صاحب المحفظة ≠ المعيل:" . PHP_EOL;
$examples = GuardianBankAccount::whereRaw('person_owner_identity_number != re_id_number')
    ->take(5)->get();
foreach ($examples as $ex) {
    echo "  ID={$ex->id}: صاحب المحفظة={$ex->person_owner_identity_number}, المعيل={$ex->re_id_number}" . PHP_EOL;
}

Log::info('📊 ملخص الاختبار الشامل', $stats);
