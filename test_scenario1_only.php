<?php
/**
 * اختبار السيناريو الأول فقط
 * كفالة غير مربوطة - إنشاء كل شيء من جديد
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 السيناريو الأول: كفالة غير مربوطة - إنشاء كل شيء من جديد\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

Log::info('═══ السيناريو الأول: كفالة غير مربوطة ═══');

// البحث عن كفالة غير مربوطة
$sponsorship = DB::table('sponsorships')
    ->where(function($q) {
        $q->whereNull('relation_id_number')
          ->orWhere('relation_id_number', '');
    })
    ->whereNotNull('internal_file_number')
    ->where('internal_file_number', '!=', '')
    ->first();

if (!$sponsorship) {
    echo "❌ لا توجد كفالات غير مربوطة!\n";
    exit(1);
}

$personType = $sponsorship->person_type ?? 'orphan';

echo "📋 الكفالة المستهدفة:\n";
echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
echo "   - relation_id_number: [" . ($sponsorship->relation_id_number ?: 'فارغ') . "]\n";
echo "   - person_type: {$personType}\n";
echo "   - orphan_name: " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
echo "   - guardian_name: " . ($sponsorship->guardian_name ?? 'NULL') . "\n";

$internalFileNumber = $sponsorship->internal_file_number;

Log::info('📋 الكفالة المستهدفة', [
    'internal_file_number' => $internalFileNumber,
    'person_type' => $personType
]);

// ─────────────────────────────────────────────────────────
// الخطوة 1: توليد رقم ملف جديد
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 1: توليد رقم ملف جديد\n";
echo str_repeat("-", 50) . "\n";

$newFileIdNumber = generateFileIdFromDataTable();
echo "   ✅ رقم الملف الجديد: {$newFileIdNumber}\n";

Log::info('📋 رقم الملف الجديد', ['file_id_number' => $newFileIdNumber]);

// ─────────────────────────────────────────────────────────
// الخطوة 2: تجهيز البيانات الجديدة
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 2: تجهيز البيانات الجديدة\n";
echo str_repeat("-", 50) . "\n";

// بيانات المعيل
$guardianData = [
    'first_name' => 'ياسر',
    'father_name' => 'علي',
    'grandfather_name' => 'حسن',
    'family_name' => 'الجديد',
    'identity_number' => '802' . rand(100000, 999999),
    'phone' => '059' . rand(1000000, 9999999),
    'phone2' => '059' . rand(1000000, 9999999),
    'address' => 'غزة - النصيرات - السيناريو الأول الجديد'
];
$guardianFullName = "{$guardianData['first_name']} {$guardianData['father_name']} {$guardianData['grandfather_name']} {$guardianData['family_name']}";

// بيانات المكفول
$sponsoredData = [
    'first_name' => 'أنس',
    'second_name' => 'ياسر',
    'third_name' => 'علي',
    'last_name' => 'الجديد',
    'identity_number' => '402' . rand(100000, 999999),
    'birth_date' => '2013-11-05',
    'gender' => 1
];
$sponsoredFullName = "{$sponsoredData['first_name']} {$sponsoredData['second_name']} {$sponsoredData['third_name']} {$sponsoredData['last_name']}";

echo "   المعيل: {$guardianFullName} (هوية: {$guardianData['identity_number']})\n";
echo "   المكفول: {$sponsoredFullName} (هوية: {$sponsoredData['identity_number']})\n";

// ─────────────────────────────────────────────────────────
// الخطوة 3: إدخال المعيل في جدول data
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 3: إدخال المعيل في جدول data\n";
echo str_repeat("-", 50) . "\n";

$dataInsert = [
    'file_id_number' => $newFileIdNumber,
    'data_id_number' => (int)preg_replace('/[^0-9]/', '', $guardianData['identity_number']),
    'data_first_name' => $guardianData['first_name'],
    'data_father_name' => $guardianData['father_name'],
    'data_grand_father_name' => $guardianData['grandfather_name'],
    'data_family_name' => $guardianData['family_name'],
    'data_phone_number' => (int)preg_replace('/[^0-9]/', '', $guardianData['phone']),
    'data_alt_phone_number' => (int)preg_replace('/[^0-9]/', '', $guardianData['phone2']),
    'data_current_address' => $guardianData['address'],
    'created_at' => now(),
    'updated_at' => now()
];

try {
    $newDataId = DB::table('data')->insertGetId($dataInsert);
    echo "   ✅ تم إدخال المعيل في data! ID: {$newDataId}\n";
    Log::info('✅ إدخال المعيل في data', ['id' => $newDataId, 'file_id_number' => $newFileIdNumber]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إدخال المعيل', ['error' => $e->getMessage()]);
    exit(1);
}

// ─────────────────────────────────────────────────────────
// الخطوة 4: إدخال المكفول في الجدول المناسب
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 4: إدخال المكفول في الجدول المناسب\n";
echo str_repeat("-", 50) . "\n";

$newSponsoredId = null;
$targetTable = '';

switch ($personType) {
    case 'family_member':
    case 'orphan':
        $targetTable = 're_people';
        $sponsoredInsert = [
            'registration_id' => $newFileIdNumber,
            'first_name' => $sponsoredData['first_name'],
            'second_name' => $sponsoredData['second_name'],
            'third_name' => $sponsoredData['third_name'],
            'last_name' => $sponsoredData['last_name'],
            'person_id' => (int)preg_replace('/[^0-9]/', '', $sponsoredData['identity_number']),
            'person_birth_date' => $sponsoredData['birth_date'],
            'person_gender' => $sponsoredData['gender'],
            'created_at' => now(),
            'updated_at' => now()
        ];
        break;

    case 'deceased_father':
    case 'deceased_mother':
        $targetTable = 'dead_people';
        $sponsoredInsert = [
            're_file_id' => $newFileIdNumber,
            'father_first_name' => $sponsoredData['first_name'],
            'father_second_name' => $sponsoredData['second_name'],
            'father_third_name' => $sponsoredData['third_name'],
            'father_last_name' => $sponsoredData['last_name'],
            'father_id' => (int)preg_replace('/[^0-9]/', '', $sponsoredData['identity_number']),
            'created_at' => now(),
            'updated_at' => now()
        ];
        break;

    case 'breadwinner':
        $targetTable = 'data';
        echo "   - نوع breadwinner: يستخدم نفس سجل data\n";
        $newSponsoredId = $newDataId;
        break;

    default:
        $targetTable = 're_people';
        $sponsoredInsert = [
            'registration_id' => $newFileIdNumber,
            'first_name' => $sponsoredData['first_name'],
            'second_name' => $sponsoredData['second_name'],
            'third_name' => $sponsoredData['third_name'],
            'last_name' => $sponsoredData['last_name'],
            'person_id' => (int)preg_replace('/[^0-9]/', '', $sponsoredData['identity_number']),
            'created_at' => now(),
            'updated_at' => now()
        ];
}

echo "   - الجدول المستهدف: {$targetTable}\n";

if ($targetTable !== 'data' && isset($sponsoredInsert)) {
    try {
        $newSponsoredId = DB::table($targetTable)->insertGetId($sponsoredInsert);
        echo "   ✅ تم إدخال المكفول في {$targetTable}! ID: {$newSponsoredId}\n";
        Log::info("✅ إدخال المكفول في {$targetTable}", ['id' => $newSponsoredId]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        Log::error('❌ فشل إدخال المكفول', ['error' => $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────
// الخطوة 5: إدخال المعلومات البنكية
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 5: إدخال المعلومات البنكية\n";
echo str_repeat("-", 50) . "\n";

$bankInsert = [
    'guardian_registration' => $newFileIdNumber,
    'bank_name' => 4, // بنك فلسطين
    'iban_usd' => 'PS92PALS000000000NEW' . rand(10000, 99999) . 'USD',
    'iban_shekel' => 'PS92PALS000000000NEW' . rand(10000, 99999) . 'ILS',
    're_id_number' => $guardianData['identity_number'],
    're_guardian_name' => $guardianFullName,
    're_phone_number' => $guardianData['phone'],
    'person_owner_identity_number' => $guardianData['identity_number'],
    'check_account' => 1,
    'created_at' => now(),
    'updated_at' => now()
];

try {
    $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankInsert);
    echo "   ✅ تم إدخال المعلومات البنكية! ID: {$newBankId}\n";
    echo "   ✅ check_account = 1 (مؤكد)\n";
    Log::info('✅ إدخال المعلومات البنكية', ['id' => $newBankId]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إدخال المعلومات البنكية', ['error' => $e->getMessage()]);
    $newBankId = null;
}

// ─────────────────────────────────────────────────────────
// الخطوة 6: ربط الكفالة عبر relation_id_number + تحديث البيانات
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 6: ربط وتحديث جدول sponsorships\n";
echo str_repeat("-", 50) . "\n";

$sponsorshipUpdate = [
    'relation_id_number' => $newFileIdNumber,
    'guardian_name' => $guardianFullName,
    'guardian_identity_number' => $guardianData['identity_number'],
    'orphan_name' => $sponsoredFullName,
    'identity_number' => $sponsoredData['identity_number'],
    'updated_at' => now()
];

echo "   التحديثات:\n";
echo "      - relation_id_number: {$newFileIdNumber}\n";
echo "      - guardian_name: {$guardianFullName}\n";
echo "      - guardian_identity_number: {$guardianData['identity_number']}\n";
echo "      - orphan_name: {$sponsoredFullName}\n";
echo "      - identity_number: {$sponsoredData['identity_number']}\n";

try {
    $updated = DB::table('sponsorships')
        ->where('internal_file_number', $internalFileNumber)
        ->update($sponsorshipUpdate);

    echo "\n   ✅ تم تحديث وربط الكفالة! (Rows: {$updated})\n";
    Log::info('✅ تحديث sponsorships', ['internal_file_number' => $internalFileNumber, 'rows' => $updated]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل تحديث sponsorships', ['error' => $e->getMessage()]);
}

// ─────────────────────────────────────────────────────────
// التحقق النهائي
// ─────────────────────────────────────────────────────────
echo "\n📋 التحقق النهائي:\n";
echo str_repeat("═", 50) . "\n";

$updatedSponsorship = DB::table('sponsorships')->where('internal_file_number', $internalFileNumber)->first();

echo "\n🎯 الكفالة بعد التحديث:\n";
echo "   - internal_file_number: {$updatedSponsorship->internal_file_number}\n";
echo "   - relation_id_number: [{$updatedSponsorship->relation_id_number}]\n";
echo "   - guardian_name: {$updatedSponsorship->guardian_name}\n";
echo "   - guardian_identity_number: {$updatedSponsorship->guardian_identity_number}\n";
echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
echo "   - identity_number: {$updatedSponsorship->identity_number}\n";

$linkedData = DB::table('data')->where('file_id_number', $newFileIdNumber)->first();
$linkedSponsored = null;
if ($targetTable === 're_people') {
    $linkedSponsored = DB::table('re_people')->where('registration_id', $newFileIdNumber)->first();
} elseif ($targetTable === 'dead_people') {
    $linkedSponsored = DB::table('dead_people')->where('re_file_id', $newFileIdNumber)->first();
}
$linkedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileIdNumber)->first();

echo "\n🔗 الربط:\n";
echo "   sponsorships.relation_id_number → {$newFileIdNumber}\n";
echo "   data.file_id_number → " . ($linkedData ? $linkedData->file_id_number : 'NOT FOUND') . "\n";
echo "   {$targetTable} → " . ($linkedSponsored ? $newFileIdNumber : 'NOT FOUND') . "\n";
echo "   guardian_bank_accounts → " . ($linkedBank ? $linkedBank->guardian_registration : 'NOT FOUND') . "\n";

$allLinked = $updatedSponsorship->relation_id_number == $newFileIdNumber &&
             $linkedData &&
             $linkedBank;

if ($allLinked) {
    echo "\n✅✅✅ السيناريو الأول: نجاح تام! ✅✅✅\n";
} else {
    echo "\n❌ هناك مشكلة في الربط!\n";
}

// ─────────────────────────────────────────────────────────
// ملخص نهائي
// ─────────────────────────────────────────────────────────
echo "\n" . str_repeat("═", 50) . "\n";
echo "📊 ملخص السيناريو الأول:\n";
echo str_repeat("═", 50) . "\n";
echo "   🎯 الكفالة: {$internalFileNumber}\n";
echo "   🔗 رقم الملف الموحد: {$newFileIdNumber}\n";
echo "   🏠 المعيل في data: ID {$newDataId}\n";
echo "   👤 المكفول في {$targetTable}: ID " . ($newSponsoredId ?? 'N/A') . "\n";
echo "   🏦 الحساب البنكي: ID " . ($newBankId ?? 'N/A') . "\n";
echo str_repeat("═", 50) . "\n";

Log::info('═══ انتهى السيناريو الأول ═══');
