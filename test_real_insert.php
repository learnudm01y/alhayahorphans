<?php
/**
 * اختبار حقيقي للإدخال في قاعدة البيانات
 * يقوم بإدخال البيانات فعلياً في الجداول
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
echo "🚀 إدخال حقيقي في قاعدة البيانات" . PHP_EOL;
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

Log::info('🎯 بدء الإدخال الحقيقي في قاعدة البيانات');

// اختبار على 5 صفوف فقط مع حالات مختلفة
$testRowIndexes = [0, 12, 15, 52, 53]; // صفوف تحتوي على حالات مختلفة
$testRows = [];
foreach ($testRowIndexes as $idx) {
    if (isset($rows[$idx])) {
        $testRows[$idx] = $rows[$idx];
    }
}

echo "📊 سيتم إدخال " . count($testRows) . " سجلات" . PHP_EOL;
echo "📍 index عمود هوية المحفظة: $personOwnerIdentityIndex" . PHP_EOL . PHP_EOL;

$sponsorshipsCreated = 0;
$bankAccountsCreated = 0;

DB::beginTransaction();

try {
    foreach ($testRows as $index => $row) {
        $rowNumber = $index + 2;

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

        echo "--- الصف $rowNumber ---" . PHP_EOL;
        echo "  المكفول: $sponsoredName ($sponsoredIdentity)" . PHP_EOL;
        echo "  المعيل: $guardianName ($guardianIdentity)" . PHP_EOL;
        echo "  هوية المحفظة من Excel: $personOwnerIdentityNumber" . PHP_EOL;

        // البحث عن relation_id_number
        $internalFileNumber = null;

        // البحث في re_people
        $rePeopleRecord = RePeople::where('person_id', $sponsoredIdentity)->first();
        if ($rePeopleRecord) {
            $internalFileNumber = $rePeopleRecord->registration_id;
        }

        // البحث بهوية المعيل في data
        if (!$internalFileNumber && !empty($guardianIdentity)) {
            $guardianRecord = Data::where('data_id_number', $guardianIdentity)->first();
            if ($guardianRecord) {
                $internalFileNumber = $guardianRecord->file_id_number;
            }
        }

        // توليد رقم ملف عرض
        $displayFileNumber = str_pad(rand(90000, 99999), 6, '0', STR_PAD_LEFT);

        // تحديد هوية صاحب الحساب
        $accountOwnerIdentity = $guardianIdentity;

        if (!empty($personOwnerIdentityNumber)) {
            $cleanedValue = preg_replace('/\D/', '', $personOwnerIdentityNumber);
            if (strlen($cleanedValue) >= 9) {
                $accountOwnerIdentity = $personOwnerIdentityNumber;
                echo "  ✅ هوية المحفظة صالحة: $accountOwnerIdentity" . PHP_EOL;
            } else {
                echo "  ⚠️ هوية المحفظة غير صالحة - استخدام هوية المعيل" . PHP_EOL;
            }
        }

        // إيجاد البنك
        $bankId = null;
        if (!empty($bankName)) {
            $bank = BankName::where('description', 'LIKE', "%$bankName%")->first();
            if ($bank) {
                $bankId = $bank->id;
            }
        }

        // ====== إدخال في جدول sponsorships ======
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
        // $sponsorship->created_by = 1; // تجاهل هذا الحقل لتجنب مشاكل FK
        $sponsorship->save();

        $sponsorshipsCreated++;
        echo "  💾 تم إنشاء Sponsorship ID: {$sponsorship->id}" . PHP_EOL;

        Log::info("💾 تم إنشاء Sponsorship", [
            'id' => $sponsorship->id,
            'identity_number' => $sponsoredIdentity,
            'guardian_identity' => $guardianIdentity
        ]);

        // ====== إدخال في جدول guardian_bank_accounts ======
        if ($bankId && !empty($accountOwnerIdentity)) {
            $bankAccount = new GuardianBankAccount();
            $bankAccount->guardian_registration = $internalFileNumber ?? $displayFileNumber;
            $bankAccount->person_owner_identity_number = $accountOwnerIdentity;
            $bankAccount->re_id_number = $guardianIdentity;
            $bankAccount->re_guardian_name = $reGuardianName ?: $guardianName;
            $bankAccount->bank_name = $bankId;
            $bankAccount->re_phone_number = $rePhoneNumber ?: $phoneNumber;
            $bankAccount->save();

            $bankAccountsCreated++;
            echo "  🏦 تم إنشاء BankAccount ID: {$bankAccount->id}" . PHP_EOL;
            echo "     - person_owner_identity_number: {$bankAccount->person_owner_identity_number}" . PHP_EOL;
            echo "     - re_id_number (المعيل): {$bankAccount->re_id_number}" . PHP_EOL;

            Log::info("🏦 تم إنشاء BankAccount", [
                'id' => $bankAccount->id,
                'person_owner_identity_number' => $accountOwnerIdentity,
                're_id_number' => $guardianIdentity,
                'bank_name' => $bankId
            ]);
        }

        echo PHP_EOL;
    }

    DB::commit();
    echo "✅ تم الحفظ بنجاح في قاعدة البيانات!" . PHP_EOL;

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ خطأ: " . $e->getMessage() . PHP_EOL;
    Log::error("❌ خطأ", ['message' => $e->getMessage(), 'line' => $e->getLine()]);
}

echo PHP_EOL;
echo "========================================" . PHP_EOL;
echo "📊 النتائج:" . PHP_EOL;
echo "  - Sponsorships: $sponsorshipsCreated" . PHP_EOL;
echo "  - Bank Accounts: $bankAccountsCreated" . PHP_EOL;
echo "========================================" . PHP_EOL;

// التحقق من قاعدة البيانات
echo PHP_EOL . "🔍 التحقق من قاعدة البيانات:" . PHP_EOL;
echo "================================" . PHP_EOL;

$lastSponsorships = Sponsorship::orderBy('id', 'desc')->take(5)->get();
echo PHP_EOL . "📋 آخر 5 سجلات في sponsorships:" . PHP_EOL;
foreach ($lastSponsorships as $s) {
    echo "  ID={$s->id}: {$s->orphan_name} | هوية المكفول: {$s->identity_number} | هوية المعيل: {$s->guardian_identity_number}" . PHP_EOL;
}

$lastBankAccounts = GuardianBankAccount::orderBy('id', 'desc')->take(5)->get();
echo PHP_EOL . "🏦 آخر 5 سجلات في guardian_bank_accounts:" . PHP_EOL;
foreach ($lastBankAccounts as $b) {
    echo "  ID={$b->id}: person_owner_identity={$b->person_owner_identity_number} | re_id_number={$b->re_id_number} | bank={$b->bank_name}" . PHP_EOL;
}
