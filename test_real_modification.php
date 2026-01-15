<?php
/**
 * اختبار السيناريو الواقعي الكامل - بدون استعادة
 *
 * يبدأ من internal_file_number موجود ويُعدّل كل البيانات:
 * 1. تحديث بيانات الكفالة في sponsorships (الاسم، الهوية، المعيل، المكفول)
 * 2. إنشاء سجلات في الجداول المرتبطة (data, re_people)
 * 3. ربط الكفالة بالسجلات الجديدة عبر relation_id_number
 * 4. إنشاء الحساب البنكي
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "===========================================\n";
echo "🧪 اختبار السيناريو الكامل - تعديل حقيقي\n";
echo "===========================================\n\n";

Log::info('========================================');
Log::info('🧪 بدء اختبار السيناريو الكامل - تعديل حقيقي');
Log::info('========================================');

// ======================================
// المتغيرات الأساسية
// ======================================
$targetInternalFileNumber = '003405';

// ======================================
// 1. جلب الكفالة الحالية
// ======================================
echo "📋 1. جلب الكفالة الحالية:\n";
echo str_repeat("-", 50) . "\n";

$sponsorship = DB::table('sponsorships')
    ->where('internal_file_number', $targetInternalFileNumber)
    ->first();

if (!$sponsorship) {
    echo "❌ الكفالة غير موجودة!\n";
    exit(1);
}

echo "✅ الكفالة الحالية:\n";
echo "   - ID: {$sponsorship->id}\n";
echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
echo "   - relation_id_number: [" . ($sponsorship->relation_id_number ?: 'EMPTY') . "]\n";
echo "   - person_type: " . ($sponsorship->person_type ?? 'NULL') . "\n";
echo "   - guardian_name: " . ($sponsorship->guardian_name ?? 'NULL') . "\n";
echo "   - guardian_identity_number: " . ($sponsorship->guardian_identity_number ?? 'NULL') . "\n";
echo "   - orphan_name: " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
echo "   - identity_number: " . ($sponsorship->identity_number ?? 'NULL') . "\n";

Log::info('📋 الكفالة الحالية', [
    'sponsorship_id' => $sponsorship->id,
    'internal_file_number' => $sponsorship->internal_file_number,
    'relation_id_number' => $sponsorship->relation_id_number,
    'guardian_name' => $sponsorship->guardian_name,
    'orphan_name' => $sponsorship->orphan_name
]);

// ======================================
// 2. تجهيز البيانات الجديدة
// ======================================
echo "\n📋 2. تجهيز البيانات الجديدة:\n";
echo str_repeat("-", 50) . "\n";

// بيانات المعيل الجديدة
$newGuardianData = [
    'first_name' => 'أحمد',
    'father_name' => 'محمود',
    'grandfather_name' => 'خالد',
    'family_name' => 'الاختبار',
    'identity_number' => '900' . rand(100000, 999999),
    'phone' => '059' . rand(1000000, 9999999),
    'phone2' => '059' . rand(1000000, 9999999),
    'address' => 'غزة - الرمال - شارع الاختبار'
];
$newGuardianFullName = "{$newGuardianData['first_name']} {$newGuardianData['father_name']} {$newGuardianData['grandfather_name']} {$newGuardianData['family_name']}";

// بيانات المكفول الجديدة
$newSponsoredData = [
    'first_name' => 'محمد',
    'second_name' => 'أحمد',
    'third_name' => 'محمود',
    'last_name' => 'الاختبار',
    'identity_number' => '400' . rand(100000, 999999),
    'birth_date' => '2015-03-20',
    'gender' => 1 // ذكر
];
$newSponsoredFullName = "{$newSponsoredData['first_name']} {$newSponsoredData['second_name']} {$newSponsoredData['third_name']} {$newSponsoredData['last_name']}";

echo "   بيانات المعيل الجديدة:\n";
echo "      - الاسم الكامل: {$newGuardianFullName}\n";
echo "      - رقم الهوية: {$newGuardianData['identity_number']}\n";
echo "      - الهاتف: {$newGuardianData['phone']}\n";

echo "\n   بيانات المكفول الجديدة:\n";
echo "      - الاسم الكامل: {$newSponsoredFullName}\n";
echo "      - رقم الهوية: {$newSponsoredData['identity_number']}\n";
echo "      - تاريخ الميلاد: {$newSponsoredData['birth_date']}\n";

Log::info('📋 البيانات الجديدة', [
    'guardian' => $newGuardianData,
    'sponsored' => $newSponsoredData
]);

// ======================================
// 3. توليد رقم ملف جديد
// ======================================
echo "\n📋 3. توليد رقم ملف جديد:\n";
echo str_repeat("-", 50) . "\n";

$newFileIdNumber = generateFileIdFromDataTable();
echo "   ✅ رقم الملف الجديد: {$newFileIdNumber}\n";

Log::info('📋 رقم الملف الجديد', ['file_id_number' => $newFileIdNumber]);

// ======================================
// 4. إنشاء سجل المعيل في جدول data
// ======================================
echo "\n📋 4. إنشاء سجل المعيل في جدول data:\n";
echo str_repeat("-", 50) . "\n";

$dataInsert = [
    'file_id_number' => $newFileIdNumber,
    'data_id_number' => (int)preg_replace('/[^0-9]/', '', $newGuardianData['identity_number']),
    'data_first_name' => $newGuardianData['first_name'],
    'data_father_name' => $newGuardianData['father_name'],
    'data_grand_father_name' => $newGuardianData['grandfather_name'],
    'data_family_name' => $newGuardianData['family_name'],
    'data_phone_number' => (int)preg_replace('/[^0-9]/', '', $newGuardianData['phone']),
    'data_alt_phone_number' => (int)preg_replace('/[^0-9]/', '', $newGuardianData['phone2']),
    'data_current_address' => $newGuardianData['address'],
    'created_at' => now(),
    'updated_at' => now()
];

try {
    $newDataId = DB::table('data')->insertGetId($dataInsert);
    echo "   ✅ تم إنشاء سجل المعيل في data! ID: {$newDataId}\n";
    Log::info('✅ سجل المعيل في data', ['id' => $newDataId, 'file_id_number' => $newFileIdNumber]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء سجل المعيل', ['error' => $e->getMessage()]);
    exit(1);
}

// ======================================
// 5. إنشاء سجل المكفول في جدول re_people
// ======================================
echo "\n📋 5. إنشاء سجل المكفول في جدول re_people:\n";
echo str_repeat("-", 50) . "\n";

$rePeopleInsert = [
    'registration_id' => $newFileIdNumber,
    'first_name' => $newSponsoredData['first_name'],
    'second_name' => $newSponsoredData['second_name'],
    'third_name' => $newSponsoredData['third_name'],
    'last_name' => $newSponsoredData['last_name'],
    'person_id' => (int)preg_replace('/[^0-9]/', '', $newSponsoredData['identity_number']),
    'person_birth_date' => $newSponsoredData['birth_date'],
    'person_gender' => $newSponsoredData['gender'],
    'created_at' => now(),
    'updated_at' => now()
];

try {
    $newRePeopleId = DB::table('re_people')->insertGetId($rePeopleInsert);
    echo "   ✅ تم إنشاء سجل المكفول في re_people! ID: {$newRePeopleId}\n";
    Log::info('✅ سجل المكفول في re_people', ['id' => $newRePeopleId, 'registration_id' => $newFileIdNumber]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء سجل المكفول', ['error' => $e->getMessage()]);
}

// ======================================
// 6. تحديث جدول sponsorships بالكامل
// ======================================
echo "\n📋 6. تحديث جدول sponsorships بالكامل:\n";
echo str_repeat("-", 50) . "\n";

$sponsorshipUpdate = [
    // ربط الكفالة بالسجلات الجديدة
    'relation_id_number' => $newFileIdNumber,

    // بيانات المعيل
    'guardian_name' => $newGuardianFullName,
    'guardian_identity_number' => $newGuardianData['identity_number'],

    // بيانات المكفول
    'orphan_name' => $newSponsoredFullName,
    'identity_number' => $newSponsoredData['identity_number'],

    // تاريخ التحديث
    'updated_at' => now()
];

echo "   التحديثات:\n";
foreach ($sponsorshipUpdate as $key => $value) {
    if ($key !== 'updated_at') {
        echo "      - {$key}: {$value}\n";
    }
}

try {
    $updated = DB::table('sponsorships')
        ->where('id', $sponsorship->id)
        ->update($sponsorshipUpdate);

    echo "\n   ✅ تم تحديث جدول sponsorships! (Rows affected: {$updated})\n";

    Log::info('✅ تم تحديث sponsorships', [
        'sponsorship_id' => $sponsorship->id,
        'updates' => $sponsorshipUpdate,
        'rows_affected' => $updated
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

$bankInsert = [
    'guardian_registration' => $newFileIdNumber,
    'bank_name' => 4, // بنك فلسطين
    'iban_usd' => 'PS92PALS000000000TEST' . rand(10000, 99999) . 'USD',
    'iban_shekel' => 'PS92PALS000000000TEST' . rand(10000, 99999) . 'ILS',
    're_id_number' => $newGuardianData['identity_number'],
    're_guardian_name' => $newGuardianFullName,
    're_phone_number' => $newGuardianData['phone'],
    'person_owner_identity_number' => $newGuardianData['identity_number'],
    'check_account' => 1,
    'created_at' => now(),
    'updated_at' => now()
];

try {
    $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankInsert);
    echo "   ✅ تم إنشاء الحساب البنكي! ID: {$newBankId}\n";
    echo "   ✅ check_account = 1 (مؤكد)\n";
    Log::info('✅ الحساب البنكي', ['id' => $newBankId, 'guardian_registration' => $newFileIdNumber]);
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء الحساب البنكي', ['error' => $e->getMessage()]);
    $newBankId = null;
}

// ======================================
// 8. التحقق من النتائج
// ======================================
echo "\n📋 8. التحقق من النتائج:\n";
echo str_repeat("=", 50) . "\n";

// إعادة جلب الكفالة
$updatedSponsorship = DB::table('sponsorships')
    ->where('id', $sponsorship->id)
    ->first();

echo "\n🎯 الكفالة بعد التحديث:\n";
echo "   - ID: {$updatedSponsorship->id}\n";
echo "   - internal_file_number: {$updatedSponsorship->internal_file_number}\n";
echo "   - relation_id_number: [{$updatedSponsorship->relation_id_number}]\n";
echo "   - guardian_name: {$updatedSponsorship->guardian_name}\n";
echo "   - guardian_identity_number: {$updatedSponsorship->guardian_identity_number}\n";
echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
echo "   - identity_number: {$updatedSponsorship->identity_number}\n";

// التحقق من الربط
$linkedData = DB::table('data')->where('file_id_number', $newFileIdNumber)->first();
$linkedRePeople = DB::table('re_people')->where('registration_id', $newFileIdNumber)->first();
$linkedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileIdNumber)->first();

echo "\n🔗 التحقق من الربط:\n";
echo "   - sponsorships.relation_id_number = {$updatedSponsorship->relation_id_number}\n";
echo "   - data.file_id_number = " . ($linkedData ? $linkedData->file_id_number : 'NOT FOUND') . "\n";
echo "   - re_people.registration_id = " . ($linkedRePeople ? $linkedRePeople->registration_id : 'NOT FOUND') . "\n";
echo "   - guardian_bank_accounts.guardian_registration = " . ($linkedBank ? $linkedBank->guardian_registration : 'NOT FOUND') . "\n";

$allLinked = $updatedSponsorship->relation_id_number == $newFileIdNumber &&
             $linkedData && $linkedData->file_id_number == $newFileIdNumber &&
             $linkedRePeople && $linkedRePeople->registration_id == $newFileIdNumber &&
             $linkedBank && $linkedBank->guardian_registration == $newFileIdNumber;

if ($allLinked) {
    echo "\n✅✅✅ جميع الجداول مرتبطة بشكل صحيح! ✅✅✅\n";
} else {
    echo "\n❌ هناك مشكلة في الربط!\n";
}

// ======================================
// ملخص نهائي
// ======================================
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 ملخص التعديلات:\n";
echo str_repeat("=", 50) . "\n";
echo "   🎯 الكفالة: {$targetInternalFileNumber} (ID: {$sponsorship->id})\n";
echo "   🔗 رقم الملف الموحد: {$newFileIdNumber}\n";
echo "   🏠 المعيل في data: ID {$newDataId}\n";
echo "   👤 المكفول في re_people: ID " . ($newRePeopleId ?? 'N/A') . "\n";
echo "   🏦 الحساب البنكي: ID " . ($newBankId ?? 'N/A') . "\n";
echo str_repeat("=", 50) . "\n";

Log::info('========================================');
Log::info('🏁 انتهى الاختبار - التعديلات مُطبّقة');
Log::info('========================================');
