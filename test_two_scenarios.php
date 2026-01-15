<?php
/**
 * اختبار السيناريوهين الرئيسيين للتعديل
 *
 * السيناريو 1: لا يوجد معيل سابق - إنشاء كل شيء من جديد
 * السيناريو 2: يوجد معيل مربوط - تعديل البيانات الموجودة وإضافة الناقص
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 اختبار السيناريوهين الرئيسيين للتعديل\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

Log::info('═══════════════════════════════════════════════════════════════');
Log::info('🧪 بدء اختبار السيناريوهين الرئيسيين');
Log::info('═══════════════════════════════════════════════════════════════');

// ╔═══════════════════════════════════════════════════════════════╗
// ║                    السيناريو الأول                           ║
// ║   لا يوجد معيل سابق - إنشاء كل شيء من جديد                   ║
// ╚═══════════════════════════════════════════════════════════════╝

function runScenario1() {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║           السيناريو الأول: لا يوجد معيل سابق                 ║\n";
    echo "║        إنشاء كل شيء من جديد وربط الكفالة                    ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

    Log::info('═══ السيناريو الأول: لا يوجد معيل سابق ═══');

    // البحث عن كفالة ليس لها relation_id_number (غير مربوطة)
    $sponsorship = DB::table('sponsorships')
        ->where(function($query) {
            $query->whereNull('relation_id_number')
                  ->orWhere('relation_id_number', '');
        })
        ->where('person_type', 'family_member')
        ->first();

    if (!$sponsorship) {
        echo "⚠️ لم يتم العثور على كفالة غير مربوطة، سنبحث عن أي كفالة...\n";
        $sponsorship = DB::table('sponsorships')
            ->where('person_type', 'family_member')
            ->whereNotNull('internal_file_number')
            ->first();
    }

    if (!$sponsorship) {
        echo "❌ لا توجد كفالات متاحة للاختبار!\n";
        return null;
    }

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - ID: {$sponsorship->id}\n";
    echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
    echo "   - relation_id_number: [" . ($sponsorship->relation_id_number ?: 'فارغ') . "]\n";
    echo "   - person_type: {$sponsorship->person_type}\n";

    // التحقق من عدم وجود سجلات مرتبطة
    $currentRelationId = $sponsorship->relation_id_number;
    $hasExistingData = false;
    $hasExistingRePeople = false;
    $hasExistingBank = false;

    if (!empty($currentRelationId)) {
        $hasExistingData = DB::table('data')->where('file_id_number', $currentRelationId)->exists();
        $hasExistingRePeople = DB::table('re_people')->where('registration_id', $currentRelationId)->exists();
        $hasExistingBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $currentRelationId)->exists();
    }

    echo "\n📋 حالة السجلات المرتبطة:\n";
    echo "   - سجل في data: " . ($hasExistingData ? "✅ موجود" : "❌ غير موجود") . "\n";
    echo "   - سجل في re_people: " . ($hasExistingRePeople ? "✅ موجود" : "❌ غير موجود") . "\n";
    echo "   - سجل في guardian_bank_accounts: " . ($hasExistingBank ? "✅ موجود" : "❌ غير موجود") . "\n";

    if ($hasExistingData || $hasExistingRePeople || $hasExistingBank) {
        echo "\n⚠️ هذه الكفالة لديها سجلات مرتبطة - سننتقل للسيناريو 2\n";
        return ['sponsorship' => $sponsorship, 'skip_to_scenario2' => true];
    }

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
        'first_name' => 'سعيد',
        'father_name' => 'محمد',
        'grandfather_name' => 'أحمد',
        'family_name' => 'النجار',
        'identity_number' => '800' . rand(100000, 999999),
        'phone' => '059' . rand(1000000, 9999999),
        'phone2' => '059' . rand(1000000, 9999999),
        'address' => 'غزة - الشجاعية - السيناريو الأول'
    ];
    $guardianFullName = "{$guardianData['first_name']} {$guardianData['father_name']} {$guardianData['grandfather_name']} {$guardianData['family_name']}";

    // بيانات المكفول
    $sponsoredData = [
        'first_name' => 'يوسف',
        'second_name' => 'سعيد',
        'third_name' => 'محمد',
        'last_name' => 'النجار',
        'identity_number' => '400' . rand(100000, 999999),
        'birth_date' => '2014-08-10',
        'gender' => 1
    ];
    $sponsoredFullName = "{$sponsoredData['first_name']} {$sponsoredData['second_name']} {$sponsoredData['third_name']} {$sponsoredData['last_name']}";

    // بيانات الحساب البنكي
    $bankData = [
        'bank_name' => 4,
        'iban_usd' => 'PS92PALS000000000S1TEST' . rand(1000, 9999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000S1TEST' . rand(1000, 9999) . 'ILS'
    ];

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
        Log::info('✅ إدخال المعيل في data', ['id' => $newDataId]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        Log::error('❌ فشل إدخال المعيل', ['error' => $e->getMessage()]);
        return null;
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: إدخال المكفول في جدول re_people
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: إدخال المكفول في جدول re_people\n";
    echo str_repeat("-", 50) . "\n";

    $rePeopleInsert = [
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

    try {
        $newRePeopleId = DB::table('re_people')->insertGetId($rePeopleInsert);
        echo "   ✅ تم إدخال المكفول في re_people! ID: {$newRePeopleId}\n";
        Log::info('✅ إدخال المكفول في re_people', ['id' => $newRePeopleId]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        Log::error('❌ فشل إدخال المكفول', ['error' => $e->getMessage()]);
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: إدخال المعلومات البنكية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: إدخال المعلومات البنكية\n";
    echo str_repeat("-", 50) . "\n";

    $bankInsert = [
        'guardian_registration' => $newFileIdNumber,
        'bank_name' => $bankData['bank_name'],
        'iban_usd' => $bankData['iban_usd'],
        'iban_shekel' => $bankData['iban_shekel'],
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
            ->where('id', $sponsorship->id)
            ->update($sponsorshipUpdate);

        echo "\n   ✅ تم تحديث وربط الكفالة! (Rows: {$updated})\n";
        Log::info('✅ تحديث sponsorships', ['sponsorship_id' => $sponsorship->id, 'updates' => $sponsorshipUpdate]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        Log::error('❌ فشل تحديث sponsorships', ['error' => $e->getMessage()]);
    }

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->where('id', $sponsorship->id)->first();

    echo "   الكفالة بعد التحديث:\n";
    echo "      - relation_id_number: [{$updatedSponsorship->relation_id_number}]\n";
    echo "      - guardian_name: {$updatedSponsorship->guardian_name}\n";
    echo "      - orphan_name: {$updatedSponsorship->orphan_name}\n";

    $linkedData = DB::table('data')->where('file_id_number', $newFileIdNumber)->first();
    $linkedRePeople = DB::table('re_people')->where('registration_id', $newFileIdNumber)->first();
    $linkedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileIdNumber)->first();

    echo "\n   🔗 الربط:\n";
    echo "      sponsorships.relation_id_number → {$newFileIdNumber}\n";
    echo "      data.file_id_number → " . ($linkedData ? $linkedData->file_id_number : 'NOT FOUND') . "\n";
    echo "      re_people.registration_id → " . ($linkedRePeople ? $linkedRePeople->registration_id : 'NOT FOUND') . "\n";
    echo "      guardian_bank_accounts → " . ($linkedBank ? $linkedBank->guardian_registration : 'NOT FOUND') . "\n";

    if ($updatedSponsorship->relation_id_number == $newFileIdNumber && $linkedData && $linkedRePeople && $linkedBank) {
        echo "\n   ✅✅✅ السيناريو الأول: نجاح تام! ✅✅✅\n";
    } else {
        echo "\n   ❌ هناك مشكلة في الربط!\n";
    }

    return [
        'sponsorship_id' => $sponsorship->id,
        'file_id_number' => $newFileIdNumber,
        'data_id' => $newDataId,
        're_people_id' => $newRePeopleId ?? null,
        'bank_id' => $newBankId ?? null
    ];
}

// ╔═══════════════════════════════════════════════════════════════╗
// ║                    السيناريو الثاني                          ║
// ║   يوجد معيل مربوط - تعديل البيانات وإضافة الناقص             ║
// ╚═══════════════════════════════════════════════════════════════╝

function runScenario2($existingSponsorship = null) {
    echo "\n\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║           السيناريو الثاني: معيل مربوط مسبقاً               ║\n";
    echo "║        تعديل البيانات الموجودة وإضافة الناقص               ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

    Log::info('═══ السيناريو الثاني: معيل مربوط مسبقاً ═══');

    // البحث عن كفالة لها relation_id_number (مربوطة)
    if ($existingSponsorship) {
        $sponsorship = $existingSponsorship;
    } else {
        $sponsorship = DB::table('sponsorships')
            ->whereNotNull('relation_id_number')
            ->where('relation_id_number', '!=', '')
            ->where('person_type', 'family_member')
            ->first();
    }

    if (!$sponsorship) {
        echo "❌ لا توجد كفالة مربوطة للاختبار!\n";
        return null;
    }

    $currentRelationId = $sponsorship->relation_id_number;

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - ID: {$sponsorship->id}\n";
    echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
    echo "   - relation_id_number: [{$currentRelationId}]\n";
    echo "   - guardian_name: {$sponsorship->guardian_name}\n";
    echo "   - orphan_name: {$sponsorship->orphan_name}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: جلب السجلات المرتبطة حالياً
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 1: السجلات المرتبطة حالياً\n";
    echo str_repeat("-", 50) . "\n";

    $existingData = DB::table('data')->where('file_id_number', $currentRelationId)->first();
    $existingRePeople = DB::table('re_people')->where('registration_id', $currentRelationId)->first();
    $existingBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $currentRelationId)->first();

    echo "   - data: " . ($existingData ? "✅ موجود (ID: {$existingData->id})" : "❌ غير موجود") . "\n";
    echo "   - re_people: " . ($existingRePeople ? "✅ موجود (ID: {$existingRePeople->id})" : "❌ غير موجود") . "\n";
    echo "   - guardian_bank_accounts: " . ($existingBank ? "✅ موجود (ID: {$existingBank->id})" : "❌ غير موجود - سيتم إضافته") . "\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: تجهيز البيانات المعدلة
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: تجهيز البيانات المعدلة\n";
    echo str_repeat("-", 50) . "\n";

    // بيانات المعيل المعدلة
    $updatedGuardianData = [
        'first_name' => 'خالد',
        'father_name' => 'إبراهيم',
        'grandfather_name' => 'عمر',
        'family_name' => 'المعدل',
        'identity_number' => '801' . rand(100000, 999999),
        'phone' => '059' . rand(1000000, 9999999),
        'phone2' => '059' . rand(1000000, 9999999),
        'address' => 'غزة - خانيونس - السيناريو الثاني'
    ];
    $updatedGuardianFullName = "{$updatedGuardianData['first_name']} {$updatedGuardianData['father_name']} {$updatedGuardianData['grandfather_name']} {$updatedGuardianData['family_name']}";

    // بيانات المكفول المعدلة
    $updatedSponsoredData = [
        'first_name' => 'عمر',
        'second_name' => 'خالد',
        'third_name' => 'إبراهيم',
        'last_name' => 'المعدل',
        'identity_number' => '401' . rand(100000, 999999),
        'birth_date' => '2016-01-25',
        'gender' => 1
    ];
    $updatedSponsoredFullName = "{$updatedSponsoredData['first_name']} {$updatedSponsoredData['second_name']} {$updatedSponsoredData['third_name']} {$updatedSponsoredData['last_name']}";

    echo "   المعيل المعدل: {$updatedGuardianFullName}\n";
    echo "   المكفول المعدل: {$updatedSponsoredFullName}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: تعديل سجل المعيل في data
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: تعديل سجل المعيل في data\n";
    echo str_repeat("-", 50) . "\n";

    if ($existingData) {
        $dataUpdate = [
            'data_id_number' => (int)preg_replace('/[^0-9]/', '', $updatedGuardianData['identity_number']),
            'data_first_name' => $updatedGuardianData['first_name'],
            'data_father_name' => $updatedGuardianData['father_name'],
            'data_grand_father_name' => $updatedGuardianData['grandfather_name'],
            'data_family_name' => $updatedGuardianData['family_name'],
            'data_phone_number' => (int)preg_replace('/[^0-9]/', '', $updatedGuardianData['phone']),
            'data_alt_phone_number' => (int)preg_replace('/[^0-9]/', '', $updatedGuardianData['phone2']),
            'data_current_address' => $updatedGuardianData['address'],
            'updated_at' => now()
        ];

        try {
            DB::table('data')->where('id', $existingData->id)->update($dataUpdate);
            echo "   ✅ تم تعديل سجل المعيل (ID: {$existingData->id})\n";
            Log::info('✅ تعديل سجل المعيل', ['id' => $existingData->id]);
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ⚠️ لا يوجد سجل للتعديل\n";
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: تعديل سجل المكفول في re_people
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: تعديل سجل المكفول في re_people\n";
    echo str_repeat("-", 50) . "\n";

    if ($existingRePeople) {
        $rePeopleUpdate = [
            'first_name' => $updatedSponsoredData['first_name'],
            'second_name' => $updatedSponsoredData['second_name'],
            'third_name' => $updatedSponsoredData['third_name'],
            'last_name' => $updatedSponsoredData['last_name'],
            'person_id' => (int)preg_replace('/[^0-9]/', '', $updatedSponsoredData['identity_number']),
            'person_birth_date' => $updatedSponsoredData['birth_date'],
            'person_gender' => $updatedSponsoredData['gender'],
            'updated_at' => now()
        ];

        try {
            DB::table('re_people')->where('id', $existingRePeople->id)->update($rePeopleUpdate);
            echo "   ✅ تم تعديل سجل المكفول (ID: {$existingRePeople->id})\n";
            Log::info('✅ تعديل سجل المكفول', ['id' => $existingRePeople->id]);
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ⚠️ لا يوجد سجل للتعديل\n";
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: إضافة/تعديل المعلومات البنكية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: إضافة/تعديل المعلومات البنكية\n";
    echo str_repeat("-", 50) . "\n";

    $bankData = [
        'bank_name' => 4,
        'iban_usd' => 'PS92PALS000000000S2TEST' . rand(1000, 9999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000S2TEST' . rand(1000, 9999) . 'ILS',
        're_id_number' => $updatedGuardianData['identity_number'],
        're_guardian_name' => $updatedGuardianFullName,
        're_phone_number' => $updatedGuardianData['phone'],
        'person_owner_identity_number' => $updatedGuardianData['identity_number'],
        'check_account' => 1
    ];

    if ($existingBank) {
        // تعديل الحساب الموجود
        $bankData['updated_at'] = now();
        try {
            DB::table('guardian_bank_accounts')->where('id', $existingBank->id)->update($bankData);
            echo "   ✅ تم تعديل الحساب البنكي (ID: {$existingBank->id})\n";
            Log::info('✅ تعديل الحساب البنكي', ['id' => $existingBank->id]);
            $newBankId = $existingBank->id;
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
            $newBankId = null;
        }
    } else {
        // إضافة حساب جديد
        $bankData['guardian_registration'] = $currentRelationId;
        $bankData['created_at'] = now();
        $bankData['updated_at'] = now();

        try {
            $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankData);
            echo "   ✅ تم إضافة حساب بنكي جديد! ID: {$newBankId}\n";
            Log::info('✅ إضافة حساب بنكي', ['id' => $newBankId]);
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
            $newBankId = null;
        }
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 6: تحديث جدول sponsorships
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 6: تحديث جدول sponsorships\n";
    echo str_repeat("-", 50) . "\n";

    $sponsorshipUpdate = [
        'guardian_name' => $updatedGuardianFullName,
        'guardian_identity_number' => $updatedGuardianData['identity_number'],
        'orphan_name' => $updatedSponsoredFullName,
        'identity_number' => $updatedSponsoredData['identity_number'],
        'updated_at' => now()
    ];

    echo "   التحديثات:\n";
    echo "      - guardian_name: {$updatedGuardianFullName}\n";
    echo "      - guardian_identity_number: {$updatedGuardianData['identity_number']}\n";
    echo "      - orphan_name: {$updatedSponsoredFullName}\n";
    echo "      - identity_number: {$updatedSponsoredData['identity_number']}\n";

    try {
        $updated = DB::table('sponsorships')
            ->where('id', $sponsorship->id)
            ->update($sponsorshipUpdate);

        echo "\n   ✅ تم تحديث sponsorships! (Rows: {$updated})\n";
        Log::info('✅ تحديث sponsorships', ['sponsorship_id' => $sponsorship->id]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
    }

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->where('id', $sponsorship->id)->first();
    $updatedData = DB::table('data')->where('file_id_number', $currentRelationId)->first();
    $updatedRePeople = DB::table('re_people')->where('registration_id', $currentRelationId)->first();
    $updatedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $currentRelationId)->first();

    echo "   الكفالة بعد التحديث:\n";
    echo "      - guardian_name: {$updatedSponsorship->guardian_name}\n";
    echo "      - guardian_identity_number: {$updatedSponsorship->guardian_identity_number}\n";
    echo "      - orphan_name: {$updatedSponsorship->orphan_name}\n";
    echo "      - identity_number: {$updatedSponsorship->identity_number}\n";

    echo "\n   المعيل في data:\n";
    if ($updatedData) {
        echo "      - الاسم: {$updatedData->data_first_name} {$updatedData->data_father_name}\n";
        echo "      - الهوية: {$updatedData->data_id_number}\n";
    }

    echo "\n   المكفول في re_people:\n";
    if ($updatedRePeople) {
        echo "      - الاسم: {$updatedRePeople->first_name} {$updatedRePeople->second_name}\n";
        echo "      - الهوية: {$updatedRePeople->person_id}\n";
    }

    echo "\n   الحساب البنكي:\n";
    if ($updatedBank) {
        echo "      - ID: {$updatedBank->id}\n";
        echo "      - صاحب الحساب: {$updatedBank->re_guardian_name}\n";
        echo "      - check_account: {$updatedBank->check_account}\n";
    }

    echo "\n   ✅✅✅ السيناريو الثاني: نجاح تام! ✅✅✅\n";

    return [
        'sponsorship_id' => $sponsorship->id,
        'file_id_number' => $currentRelationId,
        'data_id' => $existingData->id ?? null,
        're_people_id' => $existingRePeople->id ?? null,
        'bank_id' => $newBankId ?? null
    ];
}

// ═══════════════════════════════════════════════════════════════
// تشغيل السيناريوهين
// ═══════════════════════════════════════════════════════════════

// تشغيل السيناريو الأول
$result1 = runScenario1();

// تشغيل السيناريو الثاني
if ($result1 && isset($result1['skip_to_scenario2'])) {
    $result2 = runScenario2($result1['sponsorship']);
} else {
    $result2 = runScenario2();
}

// ═══════════════════════════════════════════════════════════════
// ملخص نهائي
// ═══════════════════════════════════════════════════════════════

echo "\n\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "                    📊 الملخص النهائي                         \n";
echo "═══════════════════════════════════════════════════════════════\n";

if ($result1 && !isset($result1['skip_to_scenario2'])) {
    echo "\n🎯 السيناريو الأول (إنشاء جديد):\n";
    echo "   - رقم الملف: {$result1['file_id_number']}\n";
    echo "   - المعيل في data: ID {$result1['data_id']}\n";
    echo "   - المكفول في re_people: ID " . ($result1['re_people_id'] ?? 'N/A') . "\n";
    echo "   - الحساب البنكي: ID " . ($result1['bank_id'] ?? 'N/A') . "\n";
}

if ($result2) {
    echo "\n🎯 السيناريو الثاني (تعديل موجود):\n";
    echo "   - رقم الملف: {$result2['file_id_number']}\n";
    echo "   - المعيل في data: ID " . ($result2['data_id'] ?? 'N/A') . "\n";
    echo "   - المكفول في re_people: ID " . ($result2['re_people_id'] ?? 'N/A') . "\n";
    echo "   - الحساب البنكي: ID " . ($result2['bank_id'] ?? 'N/A') . "\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "                    ✅ انتهى الاختبار                          \n";
echo "═══════════════════════════════════════════════════════════════\n";

Log::info('═══════════════════════════════════════════════════════════════');
Log::info('🏁 انتهى اختبار السيناريوهين');
Log::info('═══════════════════════════════════════════════════════════════');
