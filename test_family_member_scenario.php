<?php
/**
 * اختبار السيناريو الكامل لحفظ بيانات المكفول (فرد عائلة)
 *
 * السيناريو:
 * 1. إنشاء سجل المعيل في جدول data
 * 2. إنشاء سجل المكفول (فرد العائلة) في جدول re_people
 * 3. ربط الجداول: data.file_id_number = sponsorships.relation_id_number = re_people.registration_id
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "===========================================\n";
echo "🧪 اختبار السيناريو الكامل - فرد عائلة\n";
echo "===========================================\n\n";

Log::info('========================================');
Log::info('🧪 بدء اختبار السيناريو الكامل - فرد عائلة');
Log::info('========================================');

// ======================================
// 0. التحقق من هيكل جدول re_people
// ======================================
echo "📋 0. التحقق من أعمدة جدول re_people:\n";
echo str_repeat("-", 50) . "\n";

$rePeopleColumns = DB::select("SHOW COLUMNS FROM re_people");
$columnNames = array_column($rePeopleColumns, 'Field');
$columnTypes = [];
foreach ($rePeopleColumns as $col) {
    $columnTypes[$col->Field] = $col->Type;
}

$requiredColumns = [
    'registration_id',
    'first_name',
    'second_name',
    'third_name',
    'last_name',
    'person_id',
];

// البحث عن أعمدة إضافية
$optionalColumns = ['person_birth_date', 'birth_date', 'person_gender', 'gender', 'person_health_status', 'health_status'];

foreach ($requiredColumns as $col) {
    $exists = in_array($col, $columnNames);
    $type = $columnTypes[$col] ?? 'N/A';
    echo ($exists ? "✅" : "❌") . " {$col}: " . ($exists ? "موجود ({$type})" : "غير موجود!") . "\n";
}

echo "\nالأعمدة الاختيارية:\n";
foreach ($optionalColumns as $col) {
    $exists = in_array($col, $columnNames);
    if ($exists) {
        $type = $columnTypes[$col] ?? 'N/A';
        echo "✅ {$col}: موجود ({$type})\n";
    }
}

Log::info('📋 أعمدة جدول re_people', ['columns' => $columnNames]);

// ======================================
// 1. جلب كفالة موجودة من نوع "فرد عائلة"
// ======================================
echo "\n📋 1. البحث عن كفالة من نوع 'فرد عائلة':\n";
echo str_repeat("-", 50) . "\n";

$sponsorship = DB::table('sponsorships')
    ->where('person_type', 'family_member')
    ->first();

if (!$sponsorship) {
    // إذا لم توجد، نجلب أي كفالة
    $sponsorship = DB::table('sponsorships')->first();
    echo "⚠️ لم يتم العثور على كفالة 'فرد عائلة'، سنستخدم أي كفالة\n";
}

if ($sponsorship) {
    echo "✅ تم إيجاد كفالة:\n";
    echo "   - ID: {$sponsorship->id}\n";
    echo "   - person_type: " . ($sponsorship->person_type ?? 'NULL') . "\n";
    echo "   - relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n";
    echo "   - orphan_name: " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
    echo "   - identity_number: " . ($sponsorship->identity_number ?? 'NULL') . "\n";

    Log::info('📋 الكفالة المستخدمة للاختبار', [
        'sponsorship_id' => $sponsorship->id,
        'person_type' => $sponsorship->person_type ?? null,
        'relation_id_number' => $sponsorship->relation_id_number ?? null
    ]);
} else {
    echo "❌ لا توجد كفالات!\n";
    exit(1);
}

// ======================================
// 2. توليد رقم ملف جديد للمعيل
// ======================================
echo "\n📋 2. توليد رقم ملف جديد:\n";
echo str_repeat("-", 50) . "\n";

$newFileIdNumber = generateFileIdFromDataTable();
echo "✅ رقم الملف الجديد: {$newFileIdNumber}\n";

Log::info('📋 تم توليد رقم ملف جديد', [
    'new_file_id_number' => $newFileIdNumber
]);

// ======================================
// 3. إنشاء سجل المعيل في جدول data
// ======================================
echo "\n📋 3. إنشاء سجل المعيل في جدول data:\n";
echo str_repeat("-", 50) . "\n";

$guardianIdentity = rand(100000000, 999999999);
$guardianData = [
    'file_id_number' => $newFileIdNumber,
    'data_id_number' => $guardianIdentity,
    'data_first_name' => 'المعيل_اختبار',
    'data_father_name' => 'والد_المعيل',
    'data_grand_father_name' => 'جد_المعيل',
    'data_family_name' => 'عائلة_المعيل',
    'data_phone_number' => 599000111,
    'data_alt_phone_number' => 598000222,
    'data_current_address' => 'غزة - حي الرمال - اختبار',
    'created_at' => now(),
    'updated_at' => now()
];

echo "بيانات المعيل:\n";
foreach ($guardianData as $key => $value) {
    if (!in_array($key, ['created_at', 'updated_at'])) {
        echo "   - {$key}: {$value}\n";
    }
}

Log::info('📋 بيانات المعيل للإدراج في جدول data', ['guardian_data' => $guardianData]);

try {
    $newGuardianId = DB::table('data')->insertGetId($guardianData);
    echo "\n✅ تم إنشاء سجل المعيل! ID: {$newGuardianId}\n";

    Log::info('✅ تم إنشاء سجل المعيل في جدول data', [
        'guardian_id' => $newGuardianId,
        'file_id_number' => $newFileIdNumber,
        'data_id_number' => $guardianIdentity
    ]);
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء سجل المعيل', ['error' => $e->getMessage()]);
    exit(1);
}

// ======================================
// 4. إنشاء سجل المكفول (فرد العائلة) في جدول re_people
// ======================================
echo "\n📋 4. إنشاء سجل المكفول في جدول re_people:\n";
echo str_repeat("-", 50) . "\n";

$familyMemberIdentity = rand(100000000, 999999999);
$familyMemberData = [
    'registration_id' => $newFileIdNumber, // الربط مع data.file_id_number
    'first_name' => 'المكفول_اختبار',
    'second_name' => 'والد_المكفول',
    'third_name' => 'جد_المكفول',
    'last_name' => 'عائلة_المكفول',
    'person_id' => $familyMemberIdentity,
    'created_at' => now(),
    'updated_at' => now()
];

// إضافة الحقول الاختيارية إذا كانت موجودة
if (in_array('person_birth_date', $columnNames)) {
    $familyMemberData['person_birth_date'] = '2010-05-15';
} elseif (in_array('birth_date', $columnNames)) {
    $familyMemberData['birth_date'] = '2010-05-15';
}

if (in_array('person_gender', $columnNames)) {
    $familyMemberData['person_gender'] = 1; // ذكر
} elseif (in_array('gender', $columnNames)) {
    $familyMemberData['gender'] = 1;
}

if (in_array('person_health_status', $columnNames)) {
    $familyMemberData['person_health_status'] = 1; // سليم
} elseif (in_array('health_status', $columnNames)) {
    $familyMemberData['health_status'] = 1;
}

echo "بيانات المكفول (فرد العائلة):\n";
foreach ($familyMemberData as $key => $value) {
    if (!in_array($key, ['created_at', 'updated_at'])) {
        echo "   - {$key}: {$value}\n";
    }
}

Log::info('📋 بيانات المكفول للإدراج في جدول re_people', ['family_member_data' => $familyMemberData]);

try {
    $newFamilyMemberId = DB::table('re_people')->insertGetId($familyMemberData);
    echo "\n✅ تم إنشاء سجل المكفول! ID: {$newFamilyMemberId}\n";

    Log::info('✅ تم إنشاء سجل المكفول في جدول re_people', [
        'family_member_id' => $newFamilyMemberId,
        'registration_id' => $newFileIdNumber,
        'person_id' => $familyMemberIdentity
    ]);
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء سجل المكفول', ['error' => $e->getMessage()]);
    // تنظيف سجل المعيل
    DB::table('data')->where('id', $newGuardianId)->delete();
    exit(1);
}

// ======================================
// 5. إنشاء سجل الحساب البنكي في جدول guardian_bank_accounts
// ======================================
echo "\n📋 5. إنشاء سجل الحساب البنكي في guardian_bank_accounts:\n";
echo str_repeat("-", 50) . "\n";

$bankAccountData = [
    'guardian_registration' => $newFileIdNumber, // الربط مع data.file_id_number
    'bank_name' => 4, // ID بنك فلسطين من جدول bank_names
    'iban_usd' => 'PS92PALS0000000000123456789USD',
    'iban_shekel' => 'PS92PALS0000000000123456789ILS',
    're_id_number' => $guardianIdentity, // رقم هوية المعيل
    're_guardian_name' => 'المعيل_اختبار والد_المعيل جد_المعيل عائلة_المعيل',
    're_phone_number' => '0599000111',
    'person_owner_identity_number' => $guardianIdentity, // رقم هوية صاحب الحساب
    'check_account' => 1, // تأكيد الحساب البنكي
    'created_at' => now(),
    'updated_at' => now()
];

echo "بيانات الحساب البنكي:\n";
foreach ($bankAccountData as $key => $value) {
    if (!in_array($key, ['created_at', 'updated_at'])) {
        echo "   - {$key}: {$value}\n";
    }
}

Log::info('📋 بيانات الحساب البنكي للإدراج', ['bank_account_data' => $bankAccountData]);

try {
    $newBankAccountId = DB::table('guardian_bank_accounts')->insertGetId($bankAccountData);
    echo "\n✅ تم إنشاء سجل الحساب البنكي! ID: {$newBankAccountId}\n";
    echo "✅ الحساب مؤكد (check_account = 1)\n";

    Log::info('✅ تم إنشاء سجل الحساب البنكي في guardian_bank_accounts', [
        'bank_account_id' => $newBankAccountId,
        'guardian_registration' => $newFileIdNumber,
        'check_account' => 1
    ]);
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل إنشاء سجل الحساب البنكي', ['error' => $e->getMessage()]);
    $newBankAccountId = null;
}

// ======================================
// 6. تحديث جدول sponsorships للربط
// ======================================
echo "\n📋 6. تحديث جدول sponsorships للربط:\n";
echo str_repeat("-", 50) . "\n";

$oldRelationId = $sponsorship->relation_id_number;

// حفظ القيمة القديمة للاسترجاع لاحقاً
Log::info('📋 القيمة القديمة لـ relation_id_number', [
    'sponsorship_id' => $sponsorship->id,
    'old_relation_id_number' => $oldRelationId
]);

echo "   - القيمة القديمة: " . ($oldRelationId ?? 'NULL') . "\n";
echo "   - القيمة الجديدة: {$newFileIdNumber}\n";

try {
    DB::table('sponsorships')
        ->where('id', $sponsorship->id)
        ->update([
            'relation_id_number' => $newFileIdNumber,
            'updated_at' => now()
        ]);

    echo "\n✅ تم تحديث sponsorships.relation_id_number\n";

    Log::info('✅ تم تحديث sponsorships.relation_id_number', [
        'sponsorship_id' => $sponsorship->id,
        'new_relation_id_number' => $newFileIdNumber
    ]);
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    Log::error('❌ فشل تحديث sponsorships', ['error' => $e->getMessage()]);
}

// ======================================
// 7. التحقق من الربط بين الجداول
// ======================================
echo "\n📋 7. التحقق من الربط بين الجداول:\n";
echo str_repeat("-", 50) . "\n";

// جلب البيانات المحدثة
$updatedSponsorship = DB::table('sponsorships')->where('id', $sponsorship->id)->first();
$linkedGuardian = DB::table('data')->where('file_id_number', $newFileIdNumber)->first();
$linkedFamilyMember = DB::table('re_people')->where('registration_id', $newFileIdNumber)->first();
$linkedBankAccount = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileIdNumber)->first();

echo "الربط:\n";
echo "   sponsorships.relation_id_number = {$updatedSponsorship->relation_id_number}\n";
echo "   data.file_id_number = " . ($linkedGuardian ? $linkedGuardian->file_id_number : 'NOT FOUND') . "\n";
echo "   re_people.registration_id = " . ($linkedFamilyMember ? $linkedFamilyMember->registration_id : 'NOT FOUND') . "\n";
echo "   guardian_bank_accounts.guardian_registration = " . ($linkedBankAccount ? $linkedBankAccount->guardian_registration : 'NOT FOUND') . "\n";

$allLinked = $updatedSponsorship->relation_id_number == $newFileIdNumber &&
             $linkedGuardian && $linkedGuardian->file_id_number == $newFileIdNumber &&
             $linkedFamilyMember && $linkedFamilyMember->registration_id == $newFileIdNumber &&
             $linkedBankAccount && $linkedBankAccount->guardian_registration == $newFileIdNumber;

if ($allLinked) {
    echo "\n✅ جميع الجداول مرتبطة بشكل صحيح!\n";
    Log::info('✅ تم التحقق من الربط بنجاح', [
        'file_id_number' => $newFileIdNumber,
        'sponsorship_linked' => true,
        'data_linked' => true,
        're_people_linked' => true,
        'bank_account_linked' => true
    ]);
} else {
    echo "\n❌ هناك مشكلة في الربط!\n";
    Log::error('❌ فشل التحقق من الربط');
}

// ======================================
// 8. عرض ملخص البيانات المدخلة
// ======================================
echo "\n📋 8. ملخص البيانات المدخلة:\n";
echo str_repeat("=", 50) . "\n";

echo "\n🏠 جدول data (المعيل):\n";
if ($linkedGuardian) {
    echo "   - id: {$linkedGuardian->id}\n";
    echo "   - file_id_number: {$linkedGuardian->file_id_number}\n";
    echo "   - data_id_number: {$linkedGuardian->data_id_number}\n";
    echo "   - data_first_name: {$linkedGuardian->data_first_name}\n";
    echo "   - data_father_name: {$linkedGuardian->data_father_name}\n";
    echo "   - data_grand_father_name: {$linkedGuardian->data_grand_father_name}\n";
    echo "   - data_family_name: {$linkedGuardian->data_family_name}\n";
    echo "   - data_phone_number: {$linkedGuardian->data_phone_number}\n";
    echo "   - data_current_address: {$linkedGuardian->data_current_address}\n";
}

echo "\n👤 جدول re_people (المكفول - فرد العائلة):\n";
if ($linkedFamilyMember) {
    echo "   - id: {$linkedFamilyMember->id}\n";
    echo "   - registration_id: {$linkedFamilyMember->registration_id}\n";
    echo "   - person_id: {$linkedFamilyMember->person_id}\n";
    echo "   - first_name: {$linkedFamilyMember->first_name}\n";
    echo "   - second_name: {$linkedFamilyMember->second_name}\n";
    echo "   - third_name: {$linkedFamilyMember->third_name}\n";
    echo "   - last_name: {$linkedFamilyMember->last_name}\n";
}

echo "\n🏦 جدول guardian_bank_accounts (الحساب البنكي):\n";
if ($linkedBankAccount) {
    echo "   - id: {$linkedBankAccount->id}\n";
    echo "   - guardian_registration: {$linkedBankAccount->guardian_registration}\n";
    echo "   - bank_name: {$linkedBankAccount->bank_name}\n";
    echo "   - re_id_number: {$linkedBankAccount->re_id_number}\n";
    echo "   - re_guardian_name: {$linkedBankAccount->re_guardian_name}\n";
    echo "   - re_phone_number: {$linkedBankAccount->re_phone_number}\n";
    echo "   - iban_usd: {$linkedBankAccount->iban_usd}\n";
    echo "   - iban_shekel: {$linkedBankAccount->iban_shekel}\n";
    echo "   - person_owner_identity_number: {$linkedBankAccount->person_owner_identity_number}\n";
    echo "   - check_account: {$linkedBankAccount->check_account} (مؤكد)\n";
}

echo "\n📄 جدول sponsorships:\n";
echo "   - id: {$updatedSponsorship->id}\n";
echo "   - relation_id_number: {$updatedSponsorship->relation_id_number}\n";

Log::info('📋 ملخص البيانات المدخلة', [
    'guardian' => [
        'id' => $linkedGuardian->id ?? null,
        'file_id_number' => $newFileIdNumber,
        'data_id_number' => $guardianIdentity
    ],
    'family_member' => [
        'id' => $linkedFamilyMember->id ?? null,
        'registration_id' => $newFileIdNumber,
        'person_id' => $familyMemberIdentity
    ],
    'bank_account' => [
        'id' => $linkedBankAccount->id ?? null,
        'guardian_registration' => $newFileIdNumber,
        'check_account' => $linkedBankAccount->check_account ?? null
    ],
    'sponsorship' => [
        'id' => $sponsorship->id,
        'relation_id_number' => $newFileIdNumber
    ]
]);

// ======================================
// 9. استعادة القيمة الأصلية لـ relation_id_number
// ======================================
echo "\n📋 9. استعادة القيمة الأصلية لـ relation_id_number:\n";
echo str_repeat("-", 50) . "\n";

DB::table('sponsorships')
    ->where('id', $sponsorship->id)
    ->update([
        'relation_id_number' => $oldRelationId,
        'updated_at' => now()
    ]);

echo "✅ تم استعادة القيمة الأصلية: " . ($oldRelationId ?? 'NULL') . "\n";

Log::info('✅ تم استعادة القيمة الأصلية لـ relation_id_number', [
    'sponsorship_id' => $sponsorship->id,
    'restored_value' => $oldRelationId
]);

// ======================================
// النهاية
// ======================================
echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ انتهى الاختبار - السجلات محفوظة للمراجعة:\n";
echo "   - المعيل في data: ID = {$newGuardianId}\n";
echo "   - المكفول في re_people: ID = {$newFamilyMemberId}\n";
echo "   - الحساب البنكي في guardian_bank_accounts: ID = " . ($newBankAccountId ?? 'N/A') . "\n";
echo str_repeat("=", 50) . "\n";

Log::info('========================================');
Log::info('🏁 انتهى اختبار السيناريو الكامل - فرد عائلة');
Log::info('========================================');
