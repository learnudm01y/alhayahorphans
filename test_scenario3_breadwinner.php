<?php
/**
 * اختبار السيناريو الثالث
 * المكفول هو المعيل نفسه (breadwinner)
 *
 * السيناريو 3أ: كفالة مربوطة مسبقاً - تحديث
 * السيناريو 3ب: كفالة غير مربوطة - إنشاء جديد
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 السيناريو الثالث: المكفول هو المعيل (breadwinner)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

Log::info('═══ السيناريو الثالث: breadwinner ═══');

// ═══════════════════════════════════════════════════════════════
// السيناريو 3أ: كفالة breadwinner مربوطة مسبقاً - تحديث
// ═══════════════════════════════════════════════════════════════
function runScenario3A() {
    echo "\n" . str_repeat("▓", 60) . "\n";
    echo "📋 السيناريو 3أ: كفالة breadwinner مربوطة - تحديث\n";
    echo str_repeat("▓", 60) . "\n\n";

    Log::info('═══ السيناريو 3أ: breadwinner مربوط ═══');

    // البحث عن كفالة breadwinner مربوطة
    $sponsorship = DB::table('sponsorships')
        ->where('person_type', 'breadwinner')
        ->whereNotNull('relation_id_number')
        ->where('relation_id_number', '!=', '')
        ->whereNotNull('internal_file_number')
        ->where('internal_file_number', '!=', '')
        ->first();

    if (!$sponsorship) {
        echo "⚠️ لا توجد كفالة breadwinner مربوطة - سيتم إنشاء واحدة للاختبار\n";

        // البحث عن أي كفالة مربوطة وتحويلها لـ breadwinner
        $anySponsorship = DB::table('sponsorships')
            ->whereNotNull('relation_id_number')
            ->where('relation_id_number', '!=', '')
            ->whereNotNull('internal_file_number')
            ->where('internal_file_number', '!=', '')
            ->first();

        if ($anySponsorship) {
            // تغيير النوع مؤقتاً للاختبار
            DB::table('sponsorships')
                ->where('internal_file_number', $anySponsorship->internal_file_number)
                ->update(['person_type' => 'breadwinner']);

            $sponsorship = DB::table('sponsorships')
                ->where('internal_file_number', $anySponsorship->internal_file_number)
                ->first();

            echo "✅ تم تحويل الكفالة {$sponsorship->internal_file_number} إلى breadwinner للاختبار\n";
        } else {
            echo "❌ لا توجد كفالات مربوطة!\n";
            return false;
        }
    }

    $internalFileNumber = $sponsorship->internal_file_number;
    $relationIdNumber = $sponsorship->relation_id_number;

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - internal_file_number: {$internalFileNumber}\n";
    echo "   - relation_id_number: [{$relationIdNumber}]\n";
    echo "   - person_type: {$sponsorship->person_type}\n";
    echo "   - orphan_name (حالي): " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
    echo "   - guardian_name (حالي): " . ($sponsorship->guardian_name ?? 'NULL') . "\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: جلب السجلات المرتبطة
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 1: جلب السجلات المرتبطة\n";
    echo str_repeat("-", 50) . "\n";

    $existingData = DB::table('data')->where('file_id_number', $relationIdNumber)->first();
    $existingBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $relationIdNumber)->first();

    echo "   - data: " . ($existingData ? "موجود (ID: {$existingData->id})" : "❌ غير موجود") . "\n";
    echo "   - guardian_bank_accounts: " . ($existingBank ? "موجود (ID: {$existingBank->id})" : "❌ غير موجود") . "\n";
    echo "   ℹ️ في حالة breadwinner: المعيل = المكفول (نفس الشخص)\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: تجهيز بيانات المعيل/المكفول (نفس الشخص)
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: تجهيز بيانات المعيل/المكفول\n";
    echo str_repeat("-", 50) . "\n";

    $personData = [
        'first_name' => 'سامي',
        'father_name' => 'فؤاد',
        'grandfather_name' => 'عبدالله',
        'family_name' => 'المُعيل',
        'identity_number' => '804' . rand(100000, 999999),
        'phone' => '059' . rand(1000000, 9999999),
        'phone2' => '059' . rand(1000000, 9999999),
        'address' => 'غزة - رفح - السيناريو الثالث أ المحدث',
        'birth_date' => '1985-03-20'
    ];
    $personFullName = "{$personData['first_name']} {$personData['father_name']} {$personData['grandfather_name']} {$personData['family_name']}";

    echo "   الشخص (معيل ومكفول): {$personFullName}\n";
    echo "   الهوية: {$personData['identity_number']}\n";
    echo "   الهاتف: {$personData['phone']}\n";
    echo "   الهاتف البديل: {$personData['phone2']}\n";
    echo "   تاريخ الميلاد: {$personData['birth_date']}\n";
    echo "   العنوان: {$personData['address']}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: تحديث جدول data (المعيل = المكفول)
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: تحديث جدول data\n";
    echo str_repeat("-", 50) . "\n";

    if ($existingData) {
        $dataUpdate = [
            'data_id_number' => (int)preg_replace('/[^0-9]/', '', $personData['identity_number']),
            'data_first_name' => $personData['first_name'],
            'data_father_name' => $personData['father_name'],
            'data_grand_father_name' => $personData['grandfather_name'],
            'data_family_name' => $personData['family_name'],
            'data_phone_number' => (int)preg_replace('/[^0-9]/', '', $personData['phone']),
            'data_alt_phone_number' => (int)preg_replace('/[^0-9]/', '', $personData['phone2']),
            'data_current_address' => $personData['address'],
            'data_birth_date' => $personData['birth_date'],
            'updated_at' => now()
        ];

        try {
            $updatedRows = DB::table('data')
                ->where('file_id_number', $relationIdNumber)
                ->update($dataUpdate);
            echo "   ✅ تم تحديث البيانات في data! (Rows: {$updatedRows})\n";
            Log::info('✅ تحديث data لـ breadwinner', ['file_id_number' => $relationIdNumber]);
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
            return false;
        }
    } else {
        echo "   ⚠️ لا يوجد سجل data للتحديث\n";
        return false;
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: تحديث/إضافة المعلومات البنكية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: تحديث/إضافة المعلومات البنكية\n";
    echo str_repeat("-", 50) . "\n";

    $bankData = [
        'bank_name' => 4, // بنك فلسطين
        'iban_usd' => 'PS92PALS000000000BRW' . rand(10000, 99999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000BRW' . rand(10000, 99999) . 'ILS',
        're_id_number' => $personData['identity_number'],
        're_guardian_name' => $personFullName,
        're_phone_number' => $personData['phone'],
        'person_owner_identity_number' => $personData['identity_number'],
        'check_account' => 1,
        'updated_at' => now()
    ];

    if ($existingBank) {
        try {
            $updatedRows = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $relationIdNumber)
                ->update($bankData);
            echo "   ✅ تم تحديث المعلومات البنكية! (Rows: {$updatedRows})\n";
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    } else {
        $bankData['guardian_registration'] = $relationIdNumber;
        $bankData['created_at'] = now();

        try {
            $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankData);
            echo "   ✅ تم إضافة معلومات بنكية جديدة! ID: {$newBankId}\n";
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: تحديث جدول sponsorships
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: تحديث جدول sponsorships\n";
    echo str_repeat("-", 50) . "\n";

    // في حالة breadwinner: guardian_name = orphan_name (نفس الشخص)
    $sponsorshipUpdate = [
        'guardian_name' => $personFullName,
        'guardian_identity_number' => $personData['identity_number'],
        'orphan_name' => $personFullName, // نفس الشخص!
        'identity_number' => $personData['identity_number'], // نفس الهوية!
        'updated_at' => now()
    ];

    echo "   التحديثات (breadwinner = نفس الشخص):\n";
    echo "      - guardian_name: {$personFullName}\n";
    echo "      - guardian_identity_number: {$personData['identity_number']}\n";
    echo "      - orphan_name: {$personFullName} (نفسه)\n";
    echo "      - identity_number: {$personData['identity_number']} (نفسها)\n";

    try {
        $updated = DB::table('sponsorships')
            ->where('internal_file_number', $internalFileNumber)
            ->update($sponsorshipUpdate);

        echo "\n   ✅ تم تحديث الكفالة! (Rows: {$updated})\n";
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        return false;
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
    echo "   - person_type: {$updatedSponsorship->person_type}\n";
    echo "   - guardian_name: {$updatedSponsorship->guardian_name}\n";
    echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
    echo "   - ✅ guardian = orphan (نفس الشخص)\n";

    echo "\n🏠 البيانات (data) - المعيل/المكفول:\n";
    if ($updatedData) {
        $dataFullName = "{$updatedData->data_first_name} {$updatedData->data_father_name} {$updatedData->data_grand_father_name} {$updatedData->data_family_name}";
        echo "   - الاسم: {$dataFullName}\n";
        echo "   - الهوية: {$updatedData->data_id_number}\n";
        echo "   - الهاتف: {$updatedData->data_phone_number}\n";
        echo "   - الهاتف البديل: {$updatedData->data_alt_phone_number}\n";
        echo "   - تاريخ الميلاد: {$updatedData->data_birth_date}\n";
        echo "   - العنوان: {$updatedData->data_current_address}\n";
    }

    echo "\n🏦 الحساب البنكي:\n";
    if ($updatedBank) {
        echo "   - البنك: {$updatedBank->bank_name} (بنك فلسطين)\n";
        echo "   - IBAN USD: {$updatedBank->iban_usd}\n";
        echo "   - IBAN ILS: {$updatedBank->iban_shekel}\n";
        echo "   - اسم صاحب الحساب: {$updatedBank->re_guardian_name}\n";
        echo "   - هوية صاحب الحساب: {$updatedBank->re_id_number}\n";
        echo "   - check_account: {$updatedBank->check_account}\n";
    }

    $success = $updatedSponsorship->guardian_name == $updatedSponsorship->orphan_name;

    if ($success) {
        echo "\n✅✅✅ السيناريو 3أ: نجاح تام! ✅✅✅\n";
        return true;
    } else {
        echo "\n❌ هناك مشكلة!\n";
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// السيناريو 3ب: كفالة breadwinner غير مربوطة - إنشاء جديد
// ═══════════════════════════════════════════════════════════════
function runScenario3B() {
    echo "\n" . str_repeat("▓", 60) . "\n";
    echo "📋 السيناريو 3ب: كفالة breadwinner غير مربوطة - إنشاء جديد\n";
    echo str_repeat("▓", 60) . "\n\n";

    Log::info('═══ السيناريو 3ب: breadwinner غير مربوط ═══');

    // البحث عن كفالة breadwinner غير مربوطة
    $sponsorship = DB::table('sponsorships')
        ->where('person_type', 'breadwinner')
        ->where(function($q) {
            $q->whereNull('relation_id_number')
              ->orWhere('relation_id_number', '');
        })
        ->whereNotNull('internal_file_number')
        ->where('internal_file_number', '!=', '')
        ->first();

    if (!$sponsorship) {
        echo "⚠️ لا توجد كفالة breadwinner غير مربوطة - سيتم إنشاء واحدة للاختبار\n";

        // إنشاء كفالة breadwinner وهمية للاختبار
        $testInternalNumber = 'TEST' . rand(1000, 9999);

        try {
            DB::table('sponsorships')->insert([
                'internal_file_number' => $testInternalNumber,
                'person_type' => 'breadwinner',
                'relation_id_number' => null,
                'orphan_name' => 'اختبار breadwinner',
                'guardian_name' => 'اختبار breadwinner',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $sponsorship = DB::table('sponsorships')
                ->where('internal_file_number', $testInternalNumber)
                ->first();

            echo "✅ تم إنشاء كفالة breadwinner وهمية: {$testInternalNumber}\n";
        } catch (\Exception $e) {
            echo "❌ فشل إنشاء كفالة وهمية: " . $e->getMessage() . "\n";
            return false;
        }
    }

    $internalFileNumber = $sponsorship->internal_file_number;

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - internal_file_number: {$internalFileNumber}\n";
    echo "   - relation_id_number: [" . ($sponsorship->relation_id_number ?: 'فارغ') . "]\n";
    echo "   - person_type: {$sponsorship->person_type}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: توليد رقم ملف جديد
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 1: توليد رقم ملف جديد\n";
    echo str_repeat("-", 50) . "\n";

    $newFileIdNumber = generateFileIdFromDataTable();
    echo "   ✅ رقم الملف الجديد: {$newFileIdNumber}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: تجهيز بيانات المعيل/المكفول (نفس الشخص)
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: تجهيز بيانات المعيل/المكفول\n";
    echo str_repeat("-", 50) . "\n";

    $personData = [
        'first_name' => 'كريم',
        'father_name' => 'ناصر',
        'grandfather_name' => 'محمود',
        'family_name' => 'الجديد',
        'identity_number' => '805' . rand(100000, 999999),
        'phone' => '059' . rand(1000000, 9999999),
        'phone2' => '059' . rand(1000000, 9999999),
        'address' => 'غزة - جباليا - السيناريو الثالث ب الجديد',
        'birth_date' => '1990-07-10'
    ];
    $personFullName = "{$personData['first_name']} {$personData['father_name']} {$personData['grandfather_name']} {$personData['family_name']}";

    echo "   الشخص (معيل ومكفول): {$personFullName}\n";
    echo "   الهوية: {$personData['identity_number']}\n";
    echo "   الهاتف: {$personData['phone']}\n";
    echo "   الهاتف البديل: {$personData['phone2']}\n";
    echo "   تاريخ الميلاد: {$personData['birth_date']}\n";
    echo "   العنوان: {$personData['address']}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: إدخال سجل جديد في data
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: إدخال سجل جديد في data\n";
    echo str_repeat("-", 50) . "\n";

    $dataInsert = [
        'file_id_number' => $newFileIdNumber,
        'data_id_number' => (int)preg_replace('/[^0-9]/', '', $personData['identity_number']),
        'data_first_name' => $personData['first_name'],
        'data_father_name' => $personData['father_name'],
        'data_grand_father_name' => $personData['grandfather_name'],
        'data_family_name' => $personData['family_name'],
        'data_phone_number' => (int)preg_replace('/[^0-9]/', '', $personData['phone']),
        'data_alt_phone_number' => (int)preg_replace('/[^0-9]/', '', $personData['phone2']),
        'data_current_address' => $personData['address'],
        'data_birth_date' => $personData['birth_date'],
        'created_at' => now(),
        'updated_at' => now()
    ];

    try {
        $newDataId = DB::table('data')->insertGetId($dataInsert);
        echo "   ✅ تم إدخال البيانات في data! ID: {$newDataId}\n";
        Log::info('✅ إدخال data لـ breadwinner جديد', ['id' => $newDataId, 'file_id_number' => $newFileIdNumber]);
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        return false;
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: إدخال المعلومات البنكية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: إدخال المعلومات البنكية\n";
    echo str_repeat("-", 50) . "\n";

    $bankInsert = [
        'guardian_registration' => $newFileIdNumber,
        'bank_name' => 4, // بنك فلسطين
        'iban_usd' => 'PS92PALS000000000NEW' . rand(10000, 99999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000NEW' . rand(10000, 99999) . 'ILS',
        're_id_number' => $personData['identity_number'],
        're_guardian_name' => $personFullName,
        're_phone_number' => $personData['phone'],
        'person_owner_identity_number' => $personData['identity_number'],
        'check_account' => 1,
        'created_at' => now(),
        'updated_at' => now()
    ];

    try {
        $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankInsert);
        echo "   ✅ تم إدخال المعلومات البنكية! ID: {$newBankId}\n";
        echo "   ✅ check_account = 1 (مؤكد)\n";
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        $newBankId = null;
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: ربط وتحديث جدول sponsorships
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: ربط وتحديث جدول sponsorships\n";
    echo str_repeat("-", 50) . "\n";

    // في حالة breadwinner: guardian_name = orphan_name (نفس الشخص)
    $sponsorshipUpdate = [
        'relation_id_number' => $newFileIdNumber,
        'guardian_name' => $personFullName,
        'guardian_identity_number' => $personData['identity_number'],
        'orphan_name' => $personFullName, // نفس الشخص!
        'identity_number' => $personData['identity_number'], // نفس الهوية!
        'updated_at' => now()
    ];

    echo "   التحديثات (breadwinner = نفس الشخص):\n";
    echo "      - relation_id_number: {$newFileIdNumber}\n";
    echo "      - guardian_name: {$personFullName}\n";
    echo "      - guardian_identity_number: {$personData['identity_number']}\n";
    echo "      - orphan_name: {$personFullName} (نفسه)\n";
    echo "      - identity_number: {$personData['identity_number']} (نفسها)\n";

    try {
        $updated = DB::table('sponsorships')
            ->where('internal_file_number', $internalFileNumber)
            ->update($sponsorshipUpdate);

        echo "\n   ✅ تم ربط وتحديث الكفالة! (Rows: {$updated})\n";
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        return false;
    }

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->where('internal_file_number', $internalFileNumber)->first();
    $linkedData = DB::table('data')->where('file_id_number', $newFileIdNumber)->first();
    $linkedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileIdNumber)->first();

    echo "\n🎯 الكفالة بعد التحديث:\n";
    echo "   - internal_file_number: {$updatedSponsorship->internal_file_number}\n";
    echo "   - relation_id_number: [{$updatedSponsorship->relation_id_number}]\n";
    echo "   - person_type: {$updatedSponsorship->person_type}\n";
    echo "   - guardian_name: {$updatedSponsorship->guardian_name}\n";
    echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
    echo "   - ✅ guardian = orphan (نفس الشخص)\n";

    echo "\n🏠 البيانات (data) - المعيل/المكفول:\n";
    if ($linkedData) {
        $dataFullName = "{$linkedData->data_first_name} {$linkedData->data_father_name} {$linkedData->data_grand_father_name} {$linkedData->data_family_name}";
        echo "   - الاسم: {$dataFullName}\n";
        echo "   - الهوية: {$linkedData->data_id_number}\n";
        echo "   - الهاتف: {$linkedData->data_phone_number}\n";
        echo "   - الهاتف البديل: {$linkedData->data_alt_phone_number}\n";
        echo "   - تاريخ الميلاد: {$linkedData->data_birth_date}\n";
        echo "   - العنوان: {$linkedData->data_current_address}\n";
    }

    echo "\n🏦 الحساب البنكي:\n";
    if ($linkedBank) {
        echo "   - البنك: {$linkedBank->bank_name} (بنك فلسطين)\n";
        echo "   - IBAN USD: {$linkedBank->iban_usd}\n";
        echo "   - IBAN ILS: {$linkedBank->iban_shekel}\n";
        echo "   - اسم صاحب الحساب: {$linkedBank->re_guardian_name}\n";
        echo "   - هوية صاحب الحساب: {$linkedBank->re_id_number}\n";
        echo "   - check_account: {$linkedBank->check_account}\n";
    }

    echo "\n🔗 الربط:\n";
    echo "   sponsorships.relation_id_number → {$newFileIdNumber}\n";
    echo "   data.file_id_number → " . ($linkedData ? $linkedData->file_id_number : 'NOT FOUND') . "\n";
    echo "   guardian_bank_accounts.guardian_registration → " . ($linkedBank ? $linkedBank->guardian_registration : 'NOT FOUND') . "\n";

    $success = $updatedSponsorship->relation_id_number == $newFileIdNumber &&
               $linkedData &&
               $linkedBank &&
               $updatedSponsorship->guardian_name == $updatedSponsorship->orphan_name;

    if ($success) {
        echo "\n✅✅✅ السيناريو 3ب: نجاح تام! ✅✅✅\n";
        return true;
    } else {
        echo "\n❌ هناك مشكلة!\n";
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// تشغيل السيناريوهات
// ═══════════════════════════════════════════════════════════════

$result3A = runScenario3A();
$result3B = runScenario3B();

// ─────────────────────────────────────────────────────────
// ملخص نهائي
// ─────────────────────────────────────────────────────────
echo "\n" . str_repeat("═", 60) . "\n";
echo "📊 ملخص السيناريو الثالث (breadwinner):\n";
echo str_repeat("═", 60) . "\n";
echo "   السيناريو 3أ (مربوط - تحديث): " . ($result3A ? "✅ نجح" : "❌ فشل") . "\n";
echo "   السيناريو 3ب (غير مربوط - إنشاء): " . ($result3B ? "✅ نجح" : "❌ فشل") . "\n";
echo str_repeat("═", 60) . "\n";
echo "\n📝 ملاحظات:\n";
echo "   - في حالة breadwinner: المعيل = المكفول (نفس الشخص)\n";
echo "   - guardian_name = orphan_name\n";
echo "   - guardian_identity_number = identity_number\n";
echo "   - يتم استخدام جدول data فقط (لا يوجد re_people أو dead_people)\n";
echo "   - الحقول الإضافية: العنوان، الهاتف، الهاتف البديل، تاريخ الميلاد\n";
echo str_repeat("═", 60) . "\n";

Log::info('═══ انتهى السيناريو الثالث ═══');
