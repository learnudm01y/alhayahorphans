<?php
/**
 * اختبار السيناريو الثاني
 * كفالة مربوطة مسبقاً - تحديث البيانات الموجودة وإضافة الناقص
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 السيناريو الثاني: كفالة مربوطة - تحديث البيانات الموجودة\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

Log::info('═══ السيناريو الثاني: كفالة مربوطة ═══');

// البحث عن كفالة مربوطة
$sponsorship = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->whereNotNull('internal_file_number')
    ->where('internal_file_number', '!=', '')
    ->first();

if (!$sponsorship) {
    echo "❌ لا توجد كفالات مربوطة!\n";
    exit(1);
}

$internalFileNumber = $sponsorship->internal_file_number;
$relationIdNumber = $sponsorship->relation_id_number;
$personType = $sponsorship->person_type ?? 'orphan';

echo "📋 الكفالة المستهدفة:\n";
echo "   - internal_file_number: {$internalFileNumber}\n";
echo "   - relation_id_number: [{$relationIdNumber}]\n";
echo "   - person_type: {$personType}\n";
echo "   - orphan_name (حالي): " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
echo "   - guardian_name (حالي): " . ($sponsorship->guardian_name ?? 'NULL') . "\n";

Log::info('📋 الكفالة المستهدفة', [
    'internal_file_number' => $internalFileNumber,
    'relation_id_number' => $relationIdNumber,
    'person_type' => $personType
]);

// ─────────────────────────────────────────────────────────
// الخطوة 1: جلب السجلات المرتبطة الحالية
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 1: جلب السجلات المرتبطة الحالية\n";
echo str_repeat("-", 50) . "\n";

$existingData = DB::table('data')->where('file_id_number', $relationIdNumber)->first();
$existingSponsored = null;
$sponsoredTable = '';

switch ($personType) {
    case 'family_member':
    case 'orphan':
        $sponsoredTable = 're_people';
        $existingSponsored = DB::table('re_people')->where('registration_id', $relationIdNumber)->first();
        break;
    case 'deceased_father':
    case 'deceased_mother':
        $sponsoredTable = 'dead_people';
        $existingSponsored = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
        break;
    case 'breadwinner':
        $sponsoredTable = 'data';
        $existingSponsored = $existingData;
        break;
    default:
        $sponsoredTable = 're_people';
        $existingSponsored = DB::table('re_people')->where('registration_id', $relationIdNumber)->first();
}

$existingBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $relationIdNumber)->first();

echo "   - data: " . ($existingData ? "موجود (ID: {$existingData->id})" : "❌ غير موجود") . "\n";
echo "   - {$sponsoredTable}: " . ($existingSponsored ? "موجود" : "❌ غير موجود") . "\n";
echo "   - guardian_bank_accounts: " . ($existingBank ? "موجود (ID: {$existingBank->id})" : "❌ غير موجود") . "\n";

// ─────────────────────────────────────────────────────────
// الخطوة 2: تجهيز البيانات المحدثة
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 2: تجهيز البيانات المحدثة\n";
echo str_repeat("-", 50) . "\n";

// بيانات المعيل المحدثة
$guardianData = [
    'first_name' => 'عبدالرحمن',
    'father_name' => 'سليمان',
    'grandfather_name' => 'يوسف',
    'family_name' => 'المحدث',
    'identity_number' => '803' . rand(100000, 999999),
    'phone' => '059' . rand(1000000, 9999999),
    'phone2' => '059' . rand(1000000, 9999999),
    'address' => 'غزة - خان يونس - السيناريو الثاني المحدث'
];
$guardianFullName = "{$guardianData['first_name']} {$guardianData['father_name']} {$guardianData['grandfather_name']} {$guardianData['family_name']}";

// بيانات المكفول المحدثة
$sponsoredData = [
    'first_name' => 'محمود',
    'second_name' => 'عبدالرحمن',
    'third_name' => 'سليمان',
    'last_name' => 'المحدث',
    'identity_number' => '403' . rand(100000, 999999),
    'birth_date' => '2014-06-15',
    'gender' => 1
];
$sponsoredFullName = "{$sponsoredData['first_name']} {$sponsoredData['second_name']} {$sponsoredData['third_name']} {$sponsoredData['last_name']}";

echo "   المعيل الجديد: {$guardianFullName} (هوية: {$guardianData['identity_number']})\n";
echo "   المكفول الجديد: {$sponsoredFullName} (هوية: {$sponsoredData['identity_number']})\n";

// ─────────────────────────────────────────────────────────
// الخطوة 3: تحديث المعيل في جدول data
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 3: تحديث المعيل في جدول data\n";
echo str_repeat("-", 50) . "\n";

if ($existingData) {
    $dataUpdate = [
        'data_id_number' => (int)preg_replace('/[^0-9]/', '', $guardianData['identity_number']),
        'data_first_name' => $guardianData['first_name'],
        'data_father_name' => $guardianData['father_name'],
        'data_grand_father_name' => $guardianData['grandfather_name'],
        'data_family_name' => $guardianData['family_name'],
        'data_phone_number' => (int)preg_replace('/[^0-9]/', '', $guardianData['phone']),
        'data_alt_phone_number' => (int)preg_replace('/[^0-9]/', '', $guardianData['phone2']),
        'data_current_address' => $guardianData['address'],
        'updated_at' => now()
    ];

    try {
        $updatedRows = DB::table('data')
            ->where('file_id_number', $relationIdNumber)
            ->update($dataUpdate);
        echo "   ✅ تم تحديث المعيل في data! (Rows: {$updatedRows})\n";
        Log::info('✅ تحديث المعيل في data', ['file_id_number' => $relationIdNumber]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        Log::error('❌ فشل تحديث المعيل', ['error' => $e->getMessage()]);
    }
} else {
    echo "   ⚠️ لا يوجد سجل data للتحديث\n";
}

// ─────────────────────────────────────────────────────────
// الخطوة 4: تحديث المكفول في الجدول المناسب
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 4: تحديث المكفول في {$sponsoredTable}\n";
echo str_repeat("-", 50) . "\n";

if ($existingSponsored && $sponsoredTable !== 'data') {
    if ($sponsoredTable === 're_people') {
        $sponsoredUpdate = [
            'first_name' => $sponsoredData['first_name'],
            'second_name' => $sponsoredData['second_name'],
            'third_name' => $sponsoredData['third_name'],
            'last_name' => $sponsoredData['last_name'],
            'person_id' => (int)preg_replace('/[^0-9]/', '', $sponsoredData['identity_number']),
            'person_birth_date' => $sponsoredData['birth_date'],
            'person_gender' => $sponsoredData['gender'],
            'updated_at' => now()
        ];

        try {
            $updatedRows = DB::table('re_people')
                ->where('registration_id', $relationIdNumber)
                ->update($sponsoredUpdate);
            echo "   ✅ تم تحديث المكفول في re_people! (Rows: {$updatedRows})\n";
            Log::info('✅ تحديث المكفول في re_people', ['registration_id' => $relationIdNumber]);
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    } elseif ($sponsoredTable === 'dead_people') {
        $sponsoredUpdate = [
            'father_first_name' => $sponsoredData['first_name'],
            'father_second_name' => $sponsoredData['second_name'],
            'father_third_name' => $sponsoredData['third_name'],
            'father_last_name' => $sponsoredData['last_name'],
            'father_id' => (int)preg_replace('/[^0-9]/', '', $sponsoredData['identity_number']),
            'updated_at' => now()
        ];

        try {
            $updatedRows = DB::table('dead_people')
                ->where('re_file_id', $relationIdNumber)
                ->update($sponsoredUpdate);
            echo "   ✅ تم تحديث المكفول في dead_people! (Rows: {$updatedRows})\n";
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    }
} elseif ($sponsoredTable === 'data') {
    echo "   - نوع breadwinner: تم التحديث مع المعيل\n";
} else {
    echo "   ⚠️ لا يوجد سجل مكفول للتحديث\n";
}

// ─────────────────────────────────────────────────────────
// الخطوة 5: تحديث/إضافة المعلومات البنكية
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 5: تحديث/إضافة المعلومات البنكية\n";
echo str_repeat("-", 50) . "\n";

$bankData = [
    'bank_name' => 4, // بنك فلسطين
    'iban_usd' => 'PS92PALS000000000UPD' . rand(10000, 99999) . 'USD',
    'iban_shekel' => 'PS92PALS000000000UPD' . rand(10000, 99999) . 'ILS',
    're_id_number' => $guardianData['identity_number'],
    're_guardian_name' => $guardianFullName,
    're_phone_number' => $guardianData['phone'],
    'person_owner_identity_number' => $guardianData['identity_number'],
    'check_account' => 1,
    'updated_at' => now()
];

if ($existingBank) {
    // تحديث
    try {
        $updatedRows = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $relationIdNumber)
            ->update($bankData);
        echo "   ✅ تم تحديث المعلومات البنكية! (Rows: {$updatedRows})\n";
        Log::info('✅ تحديث المعلومات البنكية', ['guardian_registration' => $relationIdNumber]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    }
} else {
    // إضافة جديد
    $bankData['guardian_registration'] = $relationIdNumber;
    $bankData['created_at'] = now();

    try {
        $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankData);
        echo "   ✅ تم إضافة معلومات بنكية جديدة! ID: {$newBankId}\n";
        Log::info('✅ إضافة معلومات بنكية', ['id' => $newBankId]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    }
}

// ─────────────────────────────────────────────────────────
// الخطوة 6: تحديث جدول sponsorships
// ─────────────────────────────────────────────────────────
echo "\n📋 الخطوة 6: تحديث جدول sponsorships\n";
echo str_repeat("-", 50) . "\n";

$sponsorshipUpdate = [
    'guardian_name' => $guardianFullName,
    'guardian_identity_number' => $guardianData['identity_number'],
    'orphan_name' => $sponsoredFullName,
    'identity_number' => $sponsoredData['identity_number'],
    'updated_at' => now()
];

echo "   التحديثات:\n";
echo "      - guardian_name: {$guardianFullName}\n";
echo "      - guardian_identity_number: {$guardianData['identity_number']}\n";
echo "      - orphan_name: {$sponsoredFullName}\n";
echo "      - identity_number: {$sponsoredData['identity_number']}\n";

try {
    $updated = DB::table('sponsorships')
        ->where('internal_file_number', $internalFileNumber)
        ->update($sponsorshipUpdate);

    echo "\n   ✅ تم تحديث الكفالة! (Rows: {$updated})\n";
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
$updatedData = DB::table('data')->where('file_id_number', $relationIdNumber)->first();
$updatedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $relationIdNumber)->first();

echo "\n🎯 الكفالة بعد التحديث:\n";
echo "   - internal_file_number: {$updatedSponsorship->internal_file_number}\n";
echo "   - relation_id_number: [{$updatedSponsorship->relation_id_number}]\n";
echo "   - guardian_name: {$updatedSponsorship->guardian_name}\n";
echo "   - guardian_identity_number: {$updatedSponsorship->guardian_identity_number}\n";
echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
echo "   - identity_number: {$updatedSponsorship->identity_number}\n";

echo "\n🏠 المعيل (data):\n";
if ($updatedData) {
    $dataFullName = "{$updatedData->data_first_name} {$updatedData->data_father_name} {$updatedData->data_grand_father_name} {$updatedData->data_family_name}";
    echo "   - الاسم: {$dataFullName}\n";
    echo "   - الهوية: {$updatedData->data_id_number}\n";
    echo "   - العنوان: {$updatedData->data_current_address}\n";
}

echo "\n🏦 الحساب البنكي:\n";
if ($updatedBank) {
    echo "   - البنك: {$updatedBank->bank_name}\n";
    echo "   - IBAN USD: {$updatedBank->iban_usd}\n";
    echo "   - check_account: {$updatedBank->check_account}\n";
}

$allUpdated = $updatedSponsorship->guardian_name == $guardianFullName &&
              $updatedSponsorship->orphan_name == $sponsoredFullName;

if ($allUpdated) {
    echo "\n✅✅✅ السيناريو الثاني: نجاح تام! ✅✅✅\n";
} else {
    echo "\n❌ هناك مشكلة في التحديث!\n";
}

// ─────────────────────────────────────────────────────────
// ملخص نهائي
// ─────────────────────────────────────────────────────────
echo "\n" . str_repeat("═", 50) . "\n";
echo "📊 ملخص السيناريو الثاني:\n";
echo str_repeat("═", 50) . "\n";
echo "   🎯 الكفالة: {$internalFileNumber}\n";
echo "   🔗 رقم الملف الموحد: {$relationIdNumber}\n";
echo "   🏠 المعيل المحدث: {$guardianFullName}\n";
echo "   👤 المكفول المحدث: {$sponsoredFullName}\n";
echo "   🏦 الحساب البنكي: محدث/مضاف\n";
echo str_repeat("═", 50) . "\n";

Log::info('═══ انتهى السيناريو الثاني ═══');
