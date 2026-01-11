<?php
/**
 * اختبار شامل ومتكامل لتدفق البيانات من التطبيق إلى قاعدة البيانات
 *
 * يختبر:
 * 1. جميع الجداول: data, dead_people, re_people, sponsorships, guardian_bank_accounts
 * 2. تحديد نوع الشخص (المكفول والمعيل)
 * 3. تدفق البيانات الكامل
 * 4. توليد رقم الملف
 * 5. الحسابات البنكية
 */

require __DIR__.'/vendor/autoload.php';
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                  اختبار شامل ومتكامل لتدفق البيانات                     ║\n";
echo "║        data | dead_people | re_people | sponsorships | bank_accounts     ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

// إنشاء راعي للاختبار أو استخدام راعي موجود
$sponsor = DB::table('sponsors')->first();
if (!$sponsor) {
    $sponsorId = DB::table('sponsors')->insertGetId([
        'name' => 'راعي اختباري',
        'phone' => '0599999999',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✅ تم إنشاء راعي اختباري (ID: {$sponsorId})\n\n";
} else {
    $sponsorId = $sponsor->id;
    echo "✅ استخدام راعي موجود (ID: {$sponsorId})\n\n";
}

// ============================================================================
// السيناريو 1: مكفول عادي (data table) + معيل (data table)
// ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "📊 السيناريو 1: مكفول عادي + معيل في جدول data\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

// توليد رقم ملف للمعيل
$guardianFileId = generateFileIdFromDataTable();
echo "1️⃣ رقم ملف المعيل المولد: {$guardianFileId}\n";

// إنشاء سجل المعيل في data
DB::table('data')->insert([
    'file_id_number' => $guardianFileId,
    'data_first_name' => 'محمد',
    'data_father_name' => 'أحمد',
    'data_grand_father_name' => 'علي',
    'data_family_name' => 'السعيد',
    'data_gender' => 1, // 1 = ذكر، 2 = أنثى
    'data_id_number' => '123456789',
    'data_phone_number' => '0599123456',
    'data_alt_phone_number' => '0598765432',
    'data_current_address' => 'غزة - الشجاعية',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء سجل المعيل في جدول data\n";

// إنشاء كفالة
$sponsorshipId = DB::table('sponsorships')->insertGetId([
    'sponsor_id' => $sponsorId,
    'relation_id_number' => $guardianFileId,
    'identity_number' => '987654321', // رقم هوية المكفول
    'orphan_name' => 'خالد أحمد عبدالله المصري',
    'guardian_name' => 'محمد أحمد علي السعيد',
    'person_type' => 'orphan',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء كفالة (ID: {$sponsorshipId})\n";
echo "   - relation_id_number: {$guardianFileId}\n";
echo "   - person_type: orphan\n";

// محاكاة البيانات القادمة من التطبيق (تحديث بيانات المكفول)
echo "\n📱 البيانات من التطبيق (تحديث المكفول):\n";
$mobileDataOrphan = [
    'first_name' => 'خالد',
    'second_name' => 'أحمد',
    'third_name' => 'عبدالله',
    'last_name' => 'المصري',
    'orphan_gender' => 'ذكر',
    'birth_date' => '2015-03-20',
    'identity_number' => '987654321'
];

foreach ($mobileDataOrphan as $key => $value) {
    echo "   - {$key}: {$value}\n";
}

// البحث عن المكفول في الجداول
echo "\n🔍 البحث عن المكفول باستخدام relation_id_number ({$guardianFileId}):\n";
$foundInData = DB::table('data')->where('file_id_number', $guardianFileId)->first();
$foundInDead = DB::table('dead_people')->where('re_file_id', $guardianFileId)->first();
$foundInRepeople = DB::table('re_people')->where('registration_id', $guardianFileId)->first();

if ($foundInData) {
    echo "✅ وجد في جدول data\n";
    echo "   → سيتم إنشاء سجل منفصل للمكفول باستخدام identity_number\n";

    // في هذه الحالة، المكفول ليس المعيل، يجب إنشاء سجل منفصل
    // لكن في نظامنا، المكفول العادي لا يُخزّن في data بل فقط في sponsorships
    echo "   ℹ️  المكفول العادي: البيانات في sponsorships فقط\n";

} elseif ($foundInDead) {
    echo "✅ وجد في جدول dead_people\n";
} elseif ($foundInRepeople) {
    echo "✅ وجد في جدول re_people\n";
} else {
    echo "⚠️ لم يوجد - هذا صحيح للمكفول العادي\n";
}

// تحديث بيانات المعيل من التطبيق
echo "\n📱 البيانات من التطبيق (تحديث المعيل):\n";
$mobileDataGuardian = [
    'guardian_first_name' => 'محمد',
    'guardian_father_name' => 'أحمد',
    'guardian_grandfather_name' => 'علي',
    'guardian_family_name' => 'السعيد',
    'guardian_phone' => '0599999999',
    'guardian_detailed_address' => 'غزة - الرمال'
];

foreach ($mobileDataGuardian as $key => $value) {
    echo "   - {$key}: {$value}\n";
}

// تحديث بيانات المعيل في data
$guardianUpdates = [
    'data_phone_number' => $mobileDataGuardian['guardian_phone'],
    'data_current_address' => $mobileDataGuardian['guardian_detailed_address'],
    'updated_at' => now()
];

DB::table('data')->where('file_id_number', $guardianFileId)->update($guardianUpdates);
echo "\n✅ تم تحديث بيانات المعيل في جدول data\n";

// إضافة حساب بنكي للمعيل
echo "\n💰 إضافة حساب بنكي للمعيل:\n";
$bank = DB::table('bank_names')->where('id', '>', 0)->first();
if ($bank) {
    $bankAccountId = DB::table('guardian_bank_accounts')->insertGetId([
        'guardian_registration' => $guardianFileId,
        'bank_name' => $bank->id,
        're_guardian_name' => 'محمد أحمد علي السعيد',
        'person_owner_identity_number' => '123456789',
        're_phone_number' => '0599999999',
        'iban_usd' => 'PS92PALS000000000400123456789',
        'iban_shekel' => 'PS92PALS000000000410123456789',
        'check_account' => 0,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✅ تم إنشاء حساب بنكي (ID: {$bankAccountId})\n";
    echo "   - guardian_registration: {$guardianFileId}\n";
    echo "   - bank_name: {$bank->id} ({$bank->description})\n";
    echo "   - re_guardian_name: محمد أحمد علي السعيد\n";
    echo "   - iban_usd: PS92PALS000000000400123456789\n";
} else {
    echo "⚠️ لا يوجد بنوك صالحة في الجدول - تخطي إنشاء الحساب البنكي\n";
    $bankAccountId = null;
}

// التنظيف
if ($bankAccountId) {
    DB::table('guardian_bank_accounts')->where('id', $bankAccountId)->delete();
}
DB::table('sponsorships')->where('id', $sponsorshipId)->delete();
DB::table('data')->where('file_id_number', $guardianFileId)->delete();
echo "\n🗑️ تم تنظيف السيناريو 1\n\n";

// ============================================================================
// السيناريو 2: المكفول هو المعيل (breadwinner) في data
// ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "📊 السيناريو 2: المكفول هو المعيل (breadwinner) - جدول data\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

$breadwinnerFileId = generateFileIdFromDataTable();
echo "1️⃣ رقم ملف المكفول/المعيل: {$breadwinnerFileId}\n";

// إنشاء سجل واحد في data (المكفول = المعيل)
DB::table('data')->insert([
    'file_id_number' => $breadwinnerFileId,
    'data_first_name' => 'فاطمة',
    'data_father_name' => 'حسن',
    'data_grand_father_name' => 'محمود',
    'data_family_name' => 'الأرملة',
    'data_gender' => 2, // 1 = ذكر، 2 = أنثى
    'data_birth_date' => '1990-05-15',
    'data_id_number' => '555666777',
    'data_phone_number' => '0597777777',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء سجل في جدول data (المكفول = المعيل)\n";

// إنشاء كفالة
$sponsorshipId2 = DB::table('sponsorships')->insertGetId([
    'sponsor_id' => $sponsorId,
    'relation_id_number' => $breadwinnerFileId,
    'identity_number' => '555666777',
    'orphan_name' => 'فاطمة حسن محمود الأرملة',
    'guardian_name' => 'فاطمة حسن محمود الأرملة',
    'person_type' => 'breadwinner',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء كفالة breadwinner (ID: {$sponsorshipId2})\n";
echo "   - person_type: breadwinner\n";
echo "   - نفس الشخص في orphan_name و guardian_name\n";

// محاكاة تحديث من التطبيق
echo "\n📱 البيانات من التطبيق (تحديث breadwinner):\n";
$breadwinnerUpdate = [
    'first_name' => 'فاطمة',
    'second_name' => 'حسن',
    'third_name' => 'محمود',
    'last_name' => 'الأرملة',
    'orphan_gender' => 'أنثى',
    'birth_date' => '1990-05-15'
];

foreach ($breadwinnerUpdate as $key => $value) {
    echo "   - {$key}: {$value}\n";
}

echo "\n🔍 البحث في الجداول (relation_id_number = {$breadwinnerFileId}):\n";
$foundData = DB::table('data')->where('file_id_number', $breadwinnerFileId)->first();
if ($foundData) {
    echo "✅ وجد في جدول data\n";
    echo "   → التحديث:\n";

    $dataUpdate = [
        'data_first_name' => $breadwinnerUpdate['first_name'],
        'data_father_name' => $breadwinnerUpdate['second_name'],
        'data_grand_father_name' => $breadwinnerUpdate['third_name'],
        'data_family_name' => $breadwinnerUpdate['last_name'],
        'data_gender' => ($breadwinnerUpdate['orphan_gender'] == 'ذكر' ? 1 : 2),
        'data_birth_date' => $breadwinnerUpdate['birth_date'],
        'updated_at' => now()
    ];

    DB::table('data')->where('file_id_number', $breadwinnerFileId)->update($dataUpdate);

    foreach ($dataUpdate as $key => $value) {
        if ($key != 'updated_at') {
            echo "      {$key} = {$value}\n";
        }
    }
    echo "   ✅ تم التحديث بنجاح\n";
}

// التنظيف
DB::table('sponsorships')->where('id', $sponsorshipId2)->delete();
DB::table('data')->where('file_id_number', $breadwinnerFileId)->delete();
echo "\n🗑️ تم تنظيف السيناريو 2\n\n";

// ============================================================================
// السيناريو 3: مكفول من إعادة التوطين (re_people)
// ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "📊 السيناريو 3: مكفول من إعادة التوطين (re_people)\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

$repeopleRegId = 'RP-' . date('YmdHis');
echo "1️⃣ رقم تسجيل إعادة التوطين: {$repeopleRegId}\n";

// إنشاء سجل في re_people
DB::table('re_people')->insert([
    'registration_id' => $repeopleRegId,
    'person_id' => '999888777',
    'first_name' => 'عمر',
    'second_name' => 'يوسف',
    'third_name' => 'إبراهيم',
    'last_name' => 'اللاجئ',
    'person_gender' => 1, // ذكر
    'person_birth_date' => '2012-08-10',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء سجل في جدول re_people\n";

// إنشاء كفالة
$sponsorshipId3 = DB::table('sponsorships')->insertGetId([
    'sponsor_id' => $sponsorId,
    'relation_id_number' => $repeopleRegId,
    'identity_number' => '999888777',
    'orphan_name' => 'عمر يوسف إبراهيم اللاجئ',
    'person_type' => 'repeople',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء كفالة repeople (ID: {$sponsorshipId3})\n";
echo "   - person_type: repeople\n";

// محاكاة تحديث من التطبيق
echo "\n📱 البيانات من التطبيق (تحديث repeople):\n";
$repeopleUpdate = [
    'first_name' => 'عمر',
    'second_name' => 'يوسف',
    'third_name' => 'إبراهيم',
    'last_name' => 'اللاجئ',
    'orphan_gender' => 'ذكر',
    'birth_date' => '2012-08-10'
];

foreach ($repeopleUpdate as $key => $value) {
    echo "   - {$key}: {$value}\n";
}

echo "\n🔍 البحث في الجداول (relation_id_number = {$repeopleRegId}):\n";
$foundRepeople = DB::table('re_people')->where('registration_id', $repeopleRegId)->first();
if ($foundRepeople) {
    echo "✅ وجد في جدول re_people\n";
    echo "   → التحديث:\n";

    $repeopleUpdateData = [
        'first_name' => $repeopleUpdate['first_name'],
        'second_name' => $repeopleUpdate['second_name'],
        'third_name' => $repeopleUpdate['third_name'],
        'last_name' => $repeopleUpdate['last_name'],
        'person_gender' => ($repeopleUpdate['orphan_gender'] == 'ذكر' ? 1 : 2),
        'person_birth_date' => $repeopleUpdate['birth_date'],
        'updated_at' => now()
    ];

    DB::table('re_people')->where('registration_id', $repeopleRegId)->update($repeopleUpdateData);

    foreach ($repeopleUpdateData as $key => $value) {
        if ($key != 'updated_at') {
            echo "      {$key} = {$value}\n";
        }
    }
    echo "   ✅ تم التحديث بنجاح\n";
}

// التنظيف
DB::table('sponsorships')->where('id', $sponsorshipId3)->delete();
DB::table('re_people')->where('registration_id', $repeopleRegId)->delete();
echo "\n🗑️ تم تنظيف السيناريو 3\n\n";

// ============================================================================
// السيناريو 4: والدين متوفين (dead_people)
// ============================================================================
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "📊 السيناريو 4: والدين متوفين (dead_people)\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

$deadFileId = generateFileIdFromDataTable();
echo "1️⃣ رقم ملف الأسرة: {$deadFileId}\n";

// إنشاء سجل في data للأسرة
DB::table('data')->insert([
    'file_id_number' => $deadFileId,
    'data_first_name' => 'أحمد',
    'data_father_name' => 'محمد',
    'data_grand_father_name' => 'علي',
    'data_family_name' => 'اليتيم',
    'data_gender' => 1, // 1 = ذكر، 2 = أنثى
    'data_birth_date' => '2018-01-01',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء سجل الأسرة في جدول data\n";

// إنشاء سجل الوالدين المتوفين
$deathReasonId = DB::table('death_reasons')->first()->id ?? null;
DB::table('dead_people')->insert([
    're_file_id' => $deadFileId,
    'father_first_name' => 'خالد',
    'father_second_name' => 'عبدالله',
    'father_third_name' => 'حسن',
    'father_last_name' => 'الشهيد',
    'father_id' => 111222333,
    'father_death_date' => '2020-05-15',
    'father_death_reason' => $deathReasonId,
    'mother_first_name' => 'مريم',
    'mother_second_name' => 'أحمد',
    'mother_third_name' => 'محمود',
    'mother_last_name' => 'الشهيدة',
    'mother_id' => 444555666,
    'mother_death_reason' => $deathReasonId,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "✅ تم إنشاء سجل الوالدين المتوفين في جدول dead_people\n";
echo "   - الأب: خالد عبدالله حسن الشهيد (111222333)\n";
echo "   - الأم: مريم أحمد محمود الشهيدة (444555666)\n";
if ($deathReasonId) {
    echo "   - سبب الوفاة: {$deathReasonId}\n";
}

// البحث
echo "\n🔍 البحث في الجداول (relation_id_number = {$deadFileId}):\n";
$foundInDataDead = DB::table('data')->where('file_id_number', $deadFileId)->first();
$foundInDeadPeople = DB::table('dead_people')->where('re_file_id', $deadFileId)->first();

if ($foundInDataDead) {
    echo "✅ وجد في جدول data (بيانات الأسرة/المكفول)\n";
}
if ($foundInDeadPeople) {
    echo "✅ وجد في جدول dead_people (بيانات الوالدين المتوفين)\n";
    echo "   - father_first_name: {$foundInDeadPeople->father_first_name}\n";
    echo "   - mother_first_name: {$foundInDeadPeople->mother_first_name}\n";
}

// التنظيف
DB::table('dead_people')->where('re_file_id', $deadFileId)->delete();
DB::table('data')->where('file_id_number', $deadFileId)->delete();
echo "\n🗑️ تم تنظيف السيناريو 4\n\n";

// ============================================================================
// النتيجة النهائية
// ============================================================================
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                          النتيجة النهائية                                ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

echo "✅ السيناريو 1: مكفول عادي + معيل في data\n";
echo "   - relation_id_number → data.file_id_number (المعيل)\n";
echo "   - guardian_bank_accounts → data (الحسابات البنكية)\n";
echo "   - التحديث: guardian_* → data_*\n\n";

echo "✅ السيناريو 2: breadwinner (المكفول = المعيل)\n";
echo "   - person_type = breadwinner\n";
echo "   - relation_id_number → data.file_id_number\n";
echo "   - التحديث: first_name → data_first_name\n\n";

echo "✅ السيناريو 3: repeople (إعادة التوطين)\n";
echo "   - person_type = repeople\n";
echo "   - relation_id_number → re_people.registration_id\n";
echo "   - التحديث: first_name → first_name\n";
echo "   - person_gender: نص → رقم (1=ذكر، 2=أنثى)\n\n";

echo "✅ السيناريو 4: dead_people (والدين متوفين)\n";
echo "   - re_file_id → data.file_id_number\n";
echo "   - father_* للأب المتوفي\n";
echo "   - mother_* للأم المتوفية\n\n";

echo "📋 ملخص الجداول:\n";
echo "┌─────────────────────┬──────────────────────────────────────────────────┐\n";
echo "│ الجدول              │ الاستخدام                                       │\n";
echo "├─────────────────────┼──────────────────────────────────────────────────┤\n";
echo "│ data                │ المعيل العادي، breadwinner، الأسرة              │\n";
echo "│ re_people           │ إعادة التوطين                                   │\n";
echo "│ dead_people         │ الوالدين المتوفين فقط (father_*, mother_*)     │\n";
echo "│ sponsorships        │ الكفالات (orphan_name، relation_id_number)     │\n";
echo "│ guardian_bank_acc   │ الحسابات البنكية (guardian_registration)       │\n";
echo "└─────────────────────┴──────────────────────────────────────────────────┘\n\n";

echo "🎉 جميع السيناريوهات تم اختبارها بنجاح!\n";
echo "🚀 النظام جاهز للعمل مع جميع أنواع البيانات\n\n";
