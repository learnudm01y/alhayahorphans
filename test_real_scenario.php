<?php
/**
 * اختبار السيناريو الواقعي الكامل
 *
 * يبدأ من internal_file_number موجود في جدول sponsorships
 * ويحاكي ما يفعله التطبيق عند إرسال بيانات جديدة
 *
 * السيناريو:
 * 1. جلب الكفالة بـ internal_file_number = 003405
 * 2. التحقق من relation_id_number الحالي
 * 3. البحث في الجداول (data, dead_people, re_people) عن سجلات مرتبطة
 * 4. إذا لم توجد سجلات: إنشاء سجلات جديدة
 * 5. تحديث relation_id_number للربط
 * 6. إنشاء حساب بنكي مرتبط
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "===========================================\n";
echo "🧪 اختبار السيناريو الواقعي الكامل\n";
echo "===========================================\n\n";

Log::info('========================================');
Log::info('🧪 بدء اختبار السيناريو الواقعي');
Log::info('========================================');

// ======================================
// المتغيرات الأساسية
// ======================================
$targetInternalFileNumber = '003405';

// ======================================
// 1. جلب الكفالة بـ internal_file_number
// ======================================
echo "📋 1. البحث عن الكفالة برقم الملف الداخلي:\n";
echo str_repeat("-", 50) . "\n";

$sponsorship = DB::table('sponsorships')
    ->where('internal_file_number', $targetInternalFileNumber)
    ->first();

if (!$sponsorship) {
    echo "❌ الكفالة غير موجودة! internal_file_number = {$targetInternalFileNumber}\n";
    exit(1);
}

echo "✅ تم إيجاد الكفالة:\n";
echo "   - ID: {$sponsorship->id}\n";
echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
echo "   - relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n";
echo "   - person_type: " . ($sponsorship->person_type ?? 'NULL') . "\n";
echo "   - orphan_name: " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
echo "   - identity_number: " . ($sponsorship->identity_number ?? 'NULL') . "\n";
echo "   - guardian_identity_number: " . ($sponsorship->guardian_identity_number ?? 'NULL') . "\n";
echo "   - guardian_name: " . ($sponsorship->guardian_name ?? 'NULL') . "\n";

// حفظ القيم الأصلية للاستعادة
$originalRelationIdNumber = $sponsorship->relation_id_number;

Log::info('📋 الكفالة المستهدفة', [
    'sponsorship_id' => $sponsorship->id,
    'internal_file_number' => $sponsorship->internal_file_number,
    'relation_id_number' => $sponsorship->relation_id_number,
    'person_type' => $sponsorship->person_type
]);

// ======================================
// 2. التحقق من السجلات المرتبطة حالياً
// ======================================
echo "\n📋 2. البحث عن سجلات مرتبطة بـ relation_id_number:\n";
echo str_repeat("-", 50) . "\n";

$currentRelationId = $sponsorship->relation_id_number;

// البحث في جدول data
$dataRecord = null;
if (!empty($currentRelationId)) {
    $dataRecord = DB::table('data')
        ->where('file_id_number', $currentRelationId)
        ->first();
}
echo "   - جدول data: " . ($dataRecord ? "موجود (ID: {$dataRecord->id})" : "غير موجود") . "\n";

// البحث في جدول re_people
$rePeopleRecord = null;
if (!empty($currentRelationId)) {
    $rePeopleRecord = DB::table('re_people')
        ->where('registration_id', $currentRelationId)
        ->first();
}
echo "   - جدول re_people: " . ($rePeopleRecord ? "موجود (ID: {$rePeopleRecord->id})" : "غير موجود") . "\n";

// البحث في جدول dead_people
$deadPeopleRecord = null;
if (!empty($currentRelationId)) {
    $deadPeopleRecord = DB::table('dead_people')
        ->where('re_file_id', $currentRelationId)
        ->first();
}
echo "   - جدول dead_people: " . ($deadPeopleRecord ? "موجود (ID: {$deadPeopleRecord->id})" : "غير موجود") . "\n";

// البحث في جدول guardian_bank_accounts
$bankAccount = null;
if (!empty($currentRelationId)) {
    $bankAccount = DB::table('guardian_bank_accounts')
        ->where('guardian_registration', $currentRelationId)
        ->first();
}
echo "   - جدول guardian_bank_accounts: " . ($bankAccount ? "موجود (ID: {$bankAccount->id})" : "غير موجود") . "\n";

Log::info('📋 السجلات المرتبطة حالياً', [
    'relation_id_number' => $currentRelationId,
    'data_exists' => $dataRecord ? true : false,
    're_people_exists' => $rePeopleRecord ? true : false,
    'dead_people_exists' => $deadPeopleRecord ? true : false,
    'bank_account_exists' => $bankAccount ? true : false
]);

// ======================================
// 3. تحديد نوع المكفول وتجهيز البيانات الجديدة
// ======================================
echo "\n📋 3. تجهيز البيانات الجديدة للاختبار:\n";
echo str_repeat("-", 50) . "\n";

$personType = $sponsorship->person_type ?? 'family_member';
echo "   - نوع المكفول: {$personType}\n";

// بيانات المعيل الجديدة (تحاكي ما يرسله التطبيق)
$guardianData = [
    'guardian_first_name' => 'اختبار_المعيل',
    'guardian_father_name' => 'والد_اختبار',
    'guardian_grandfather_name' => 'جد_اختبار',
    'guardian_family_name' => 'عائلة_اختبار',
    'guardian_identity_number' => rand(100000000, 999999999),
    'guardian_phone' => '0599' . rand(100000, 999999),
    'guardian_phone2' => '0598' . rand(100000, 999999),
    'guardian_detailed_address' => 'غزة - حي الرمال - اختبار السيناريو الواقعي'
];

// بيانات المكفول الجديدة
$sponsoredData = [
    'first_name' => 'مكفول_اختبار',
    'second_name' => 'والد_مكفول',
    'third_name' => 'جد_مكفول',
    'last_name' => 'عائلة_مكفول',
    'identity_number' => rand(100000000, 999999999),
    'birth_date' => '2012-06-15',
    'orphan_gender' => 'ذكر'
];

// بيانات الحساب البنكي
$bankData = [
    'bank_name' => 4, // بنك فلسطين
    'iban_usd' => 'PS92PALS000000000TEST12345USD',
    'iban_shekel' => 'PS92PALS000000000TEST12345ILS',
    're_guardian_name' => $guardianData['guardian_first_name'] . ' ' .
                         $guardianData['guardian_father_name'] . ' ' .
                         $guardianData['guardian_grandfather_name'] . ' ' .
                         $guardianData['guardian_family_name'],
    're_phone_number' => $guardianData['guardian_phone'],
    'person_owner_identity_number' => $guardianData['guardian_identity_number'],
    'check_account' => 1
];

echo "   بيانات المعيل:\n";
foreach ($guardianData as $key => $value) {
    echo "      - {$key}: {$value}\n";
}

echo "\n   بيانات المكفول:\n";
foreach ($sponsoredData as $key => $value) {
    echo "      - {$key}: {$value}\n";
}

echo "\n   بيانات الحساب البنكي:\n";
foreach ($bankData as $key => $value) {
    echo "      - {$key}: {$value}\n";
}

Log::info('📋 البيانات الجديدة للاختبار', [
    'guardian_data' => $guardianData,
    'sponsored_data' => $sponsoredData,
    'bank_data' => $bankData
]);

// ======================================
// 4. إنشاء سجل المعيل في جدول data
// ======================================
echo "\n📋 4. إنشاء سجل المعيل في جدول data:\n";
echo str_repeat("-", 50) . "\n";

// توليد رقم ملف جديد
$newFileIdNumber = generateFileIdFromDataTable();
echo "   - رقم الملف الجديد: {$newFileIdNumber}\n";

// تحويل الأرقام لـ bigint
$dataIdNumber = preg_replace('/[^0-9]/', '', $guardianData['guardian_identity_number']);
$phoneNumber = preg_replace('/[^0-9]/', '', $guardianData['guardian_phone']);
$altPhoneNumber = preg_replace('/[^0-9]/', '', $guardianData['guardian_phone2']);

$insertDataRecord = [
    'file_id_number' => $newFileIdNumber,
    'data_id_number' => (int)$dataIdNumber,
    'data_first_name' => $guardianData['guardian_first_name'],
    'data_father_name' => $guardianData['guardian_father_name'],
    'data_grand_father_name' => $guardianData['guardian_grandfather_name'],
    'data_family_name' => $guardianData['guardian_family_name'],
    'data_phone_number' => (int)$phoneNumber,
    'data_alt_phone_number' => (int)$altPhoneNumber,
    'data_current_address' => $guardianData['guardian_detailed_address'],
    'created_at' => now(),
    'updated_at' => now()
];

Log::info('📋 بيانات المعيل للإدراج', ['insert_data' => $insertDataRecord]);

try {
    $newDataId = DB::table('data')->insertGetId($insertDataRecord);
    echo "   ✅ تم إنشاء سجل المعيل! ID: {$newDataId}\n";

    Log::info('✅ تم إنشاء سجل المعيل في data', [
        'data_id' => $newDataId,
        'file_id_number' => $newFileIdNumber
    ]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء سجل المعيل', ['error' => $e->getMessage()]);
    exit(1);
}

// ======================================
// 5. إنشاء سجل المكفول حسب person_type
// ======================================
echo "\n📋 5. إنشاء سجل المكفول في الجدول المناسب:\n";
echo str_repeat("-", 50) . "\n";

$sponsoredRecordId = null;
$targetTable = '';

switch ($personType) {
    case 'family_member':
    case 'orphan':
        $targetTable = 're_people';
        $insertSponsoredRecord = [
            'registration_id' => $newFileIdNumber,
            'first_name' => $sponsoredData['first_name'],
            'second_name' => $sponsoredData['second_name'],
            'third_name' => $sponsoredData['third_name'],
            'last_name' => $sponsoredData['last_name'],
            'person_id' => (int)preg_replace('/[^0-9]/', '', $sponsoredData['identity_number']),
            'person_birth_date' => $sponsoredData['birth_date'],
            'person_gender' => $sponsoredData['orphan_gender'] === 'ذكر' ? 1 : 2,
            'created_at' => now(),
            'updated_at' => now()
        ];
        break;

    case 'deceased_father':
    case 'deceased_mother':
        $targetTable = 'dead_people';
        $insertSponsoredRecord = [
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
        // المعيل يُخزن في نفس السجل الذي أنشأناه
        echo "   - نوع المكفول 'معيل': يستخدم نفس سجل data\n";
        $sponsoredRecordId = $newDataId;
        break;

    default:
        $targetTable = 're_people';
        $insertSponsoredRecord = [
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

if ($targetTable !== 'data' && isset($insertSponsoredRecord)) {
    Log::info('📋 بيانات المكفول للإدراج', [
        'table' => $targetTable,
        'insert_data' => $insertSponsoredRecord
    ]);

    try {
        $sponsoredRecordId = DB::table($targetTable)->insertGetId($insertSponsoredRecord);
        echo "   ✅ تم إنشاء سجل المكفول! ID: {$sponsoredRecordId}\n";

        Log::info('✅ تم إنشاء سجل المكفول', [
            'table' => $targetTable,
            'record_id' => $sponsoredRecordId,
            'registration_id' => $newFileIdNumber
        ]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        Log::error('❌ فشل إنشاء سجل المكفول', ['error' => $e->getMessage()]);
    }
}

// ======================================
// 6. تحديث جدول sponsorships للربط
// ======================================
echo "\n📋 6. تحديث جدول sponsorships للربط:\n";
echo str_repeat("-", 50) . "\n";

echo "   - القيمة القديمة لـ relation_id_number: " . ($originalRelationIdNumber ?? 'NULL') . "\n";
echo "   - القيمة الجديدة: {$newFileIdNumber}\n";

try {
    DB::table('sponsorships')
        ->where('id', $sponsorship->id)
        ->update([
            'relation_id_number' => $newFileIdNumber,
            'updated_at' => now()
        ]);

    echo "   ✅ تم تحديث sponsorships.relation_id_number\n";

    Log::info('✅ تم تحديث relation_id_number', [
        'sponsorship_id' => $sponsorship->id,
        'old_value' => $originalRelationIdNumber,
        'new_value' => $newFileIdNumber
    ]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل تحديث sponsorships', ['error' => $e->getMessage()]);
}

// ======================================
// 7. إنشاء الحساب البنكي
// ======================================
echo "\n📋 7. إنشاء الحساب البنكي:\n";
echo str_repeat("-", 50) . "\n";

$insertBankAccount = [
    'guardian_registration' => $newFileIdNumber,
    'bank_name' => $bankData['bank_name'],
    'iban_usd' => $bankData['iban_usd'],
    'iban_shekel' => $bankData['iban_shekel'],
    're_id_number' => $guardianData['guardian_identity_number'],
    're_guardian_name' => $bankData['re_guardian_name'],
    're_phone_number' => $bankData['re_phone_number'],
    'person_owner_identity_number' => $bankData['person_owner_identity_number'],
    'check_account' => $bankData['check_account'],
    'created_at' => now(),
    'updated_at' => now()
];

Log::info('📋 بيانات الحساب البنكي للإدراج', ['bank_data' => $insertBankAccount]);

try {
    $newBankAccountId = DB::table('guardian_bank_accounts')->insertGetId($insertBankAccount);
    echo "   ✅ تم إنشاء الحساب البنكي! ID: {$newBankAccountId}\n";
    echo "   ✅ check_account = 1 (مؤكد)\n";

    Log::info('✅ تم إنشاء الحساب البنكي', [
        'bank_account_id' => $newBankAccountId,
        'guardian_registration' => $newFileIdNumber,
        'check_account' => 1
    ]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء الحساب البنكي', ['error' => $e->getMessage()]);
    $newBankAccountId = null;
}

// ======================================
// 8. التحقق من الربط بين الجداول
// ======================================
echo "\n📋 8. التحقق من الربط بين الجداول:\n";
echo str_repeat("-", 50) . "\n";

// إعادة جلب البيانات
$updatedSponsorship = DB::table('sponsorships')->where('id', $sponsorship->id)->first();
$linkedData = DB::table('data')->where('file_id_number', $newFileIdNumber)->first();
$linkedSponsored = DB::table($targetTable)
    ->where($targetTable === 'data' ? 'file_id_number' : ($targetTable === 're_people' ? 'registration_id' : 're_file_id'), $newFileIdNumber)
    ->first();
$linkedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileIdNumber)->first();

echo "الربط:\n";
echo "   sponsorships.relation_id_number = {$updatedSponsorship->relation_id_number}\n";
echo "   data.file_id_number = " . ($linkedData ? $linkedData->file_id_number : 'NOT FOUND') . "\n";
echo "   {$targetTable}." . ($targetTable === 're_people' ? 'registration_id' : 're_file_id') . " = " .
     ($linkedSponsored ? ($targetTable === 're_people' ? $linkedSponsored->registration_id : $linkedSponsored->re_file_id) : 'NOT FOUND') . "\n";
echo "   guardian_bank_accounts.guardian_registration = " . ($linkedBank ? $linkedBank->guardian_registration : 'NOT FOUND') . "\n";

$allLinked = $updatedSponsorship->relation_id_number == $newFileIdNumber &&
             $linkedData && $linkedData->file_id_number == $newFileIdNumber &&
             $linkedBank && $linkedBank->guardian_registration == $newFileIdNumber;

if ($allLinked) {
    echo "\n✅ جميع الجداول مرتبطة بشكل صحيح!\n";
    Log::info('✅ التحقق من الربط نجح');
} else {
    echo "\n❌ هناك مشكلة في الربط!\n";
    Log::error('❌ فشل التحقق من الربط');
}

// ======================================
// 9. ملخص البيانات المدخلة
// ======================================
echo "\n📋 9. ملخص البيانات المدخلة:\n";
echo str_repeat("=", 50) . "\n";

echo "\n🎯 الكفالة المستهدفة:\n";
echo "   - internal_file_number: {$targetInternalFileNumber}\n";
echo "   - sponsorship_id: {$sponsorship->id}\n";

echo "\n🏠 جدول data (المعيل):\n";
echo "   - ID: {$newDataId}\n";
echo "   - file_id_number: {$newFileIdNumber}\n";
echo "   - data_id_number: {$insertDataRecord['data_id_number']}\n";
echo "   - الاسم: {$insertDataRecord['data_first_name']} {$insertDataRecord['data_father_name']}\n";

echo "\n👤 جدول {$targetTable} (المكفول):\n";
echo "   - ID: " . ($sponsoredRecordId ?? 'N/A') . "\n";

echo "\n🏦 جدول guardian_bank_accounts:\n";
echo "   - ID: " . ($newBankAccountId ?? 'N/A') . "\n";
echo "   - guardian_registration: {$newFileIdNumber}\n";
echo "   - check_account: 1 (مؤكد)\n";

echo "\n🔗 الربط:\n";
echo "   - sponsorships.relation_id_number → {$newFileIdNumber}\n";
echo "   - data.file_id_number → {$newFileIdNumber}\n";
echo "   - {$targetTable} → {$newFileIdNumber}\n";
echo "   - guardian_bank_accounts.guardian_registration → {$newFileIdNumber}\n";

Log::info('📋 ملخص الاختبار', [
    'internal_file_number' => $targetInternalFileNumber,
    'sponsorship_id' => $sponsorship->id,
    'new_file_id_number' => $newFileIdNumber,
    'data_id' => $newDataId,
    'sponsored_table' => $targetTable,
    'sponsored_id' => $sponsoredRecordId,
    'bank_account_id' => $newBankAccountId
]);

// ======================================
// 10. استعادة القيمة الأصلية (اختياري)
// ======================================
echo "\n📋 10. استعادة القيمة الأصلية لـ relation_id_number:\n";
echo str_repeat("-", 50) . "\n";

DB::table('sponsorships')
    ->where('id', $sponsorship->id)
    ->update([
        'relation_id_number' => $originalRelationIdNumber,
        'updated_at' => now()
    ]);

echo "✅ تم استعادة القيمة الأصلية: " . ($originalRelationIdNumber ?? 'NULL') . "\n";

Log::info('✅ تم استعادة القيمة الأصلية', [
    'sponsorship_id' => $sponsorship->id,
    'restored_value' => $originalRelationIdNumber
]);

// ======================================
// النهاية
// ======================================
echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ انتهى الاختبار - السجلات محفوظة للمراجعة:\n";
echo "   - المعيل في data: ID = {$newDataId}\n";
echo "   - المكفول في {$targetTable}: ID = " . ($sponsoredRecordId ?? 'N/A') . "\n";
echo "   - الحساب البنكي: ID = " . ($newBankAccountId ?? 'N/A') . "\n";
echo "   - رقم الملف الموحد: {$newFileIdNumber}\n";
echo str_repeat("=", 50) . "\n";

Log::info('========================================');
Log::info('🏁 انتهى اختبار السيناريو الواقعي');
Log::info('========================================');
