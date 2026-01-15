<?php
/**
 * اختبار السيناريو الرابع - المتوفين مع الحقول الإضافية
 * المكفول شخص متوفي (أب متوفي deceased_father أو أم متوفية deceased_mother)
 *
 * الحقول الإضافية المخزنة في portal_general_registration_field_values:
 * - field_data_phone_number (رقم الهاتف)
 * - field_data_alt_phone_number (رقم الهاتف البديل)
 * - field_housing_address_detail (العنوان التفصيلي)
 *
 * ملاحظات مهمة:
 * - لا يُسمح بتعديل internal_file_number
 * - لا يتم إدخال guardian_name و guardian_identity_number (لا يوجد معيل)
 * - فقط بيانات المكفول (orphan_name + identity_number) يتم تعديلها
 * - التخزين في جدول dead_people عبر re_file_id
 * - الحقول الإضافية تُخزن في portal_general_registration_field_values
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 السيناريو الرابع المحسّن: المتوفين مع الحقول الإضافية\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

Log::info('═══ السيناريو الرابع المحسّن: deceased مع الحقول الإضافية ═══');

// ═══════════════════════════════════════════════════════════════
// دالة مساعدة: حفظ/تحديث الحقول في portal_general_registration_field_values
// ═══════════════════════════════════════════════════════════════
function saveFieldValue($sponsorshipId, $fileIdNumber, $identityNumber, $fieldKey, $fieldValue, $userId = null) {
    $existing = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', $sponsorshipId)
        ->where('field_key', $fieldKey)
        ->first();

    if ($existing) {
        // تحديث القيمة الموجودة
        $updated = DB::table('portal_general_registration_field_values')
            ->where('id', $existing->id)
            ->update([
                'field_value' => $fieldValue,
                'updated_by_user_id' => $userId,
                'updated_at' => now()
            ]);
        return ['action' => 'updated', 'id' => $existing->id, 'rows' => $updated];
    } else {
        // إدخال قيمة جديدة
        $newId = DB::table('portal_general_registration_field_values')->insertGetId([
            'sponsorship_id' => $sponsorshipId,
            'file_id_number' => $fileIdNumber,
            'identity_number' => $identityNumber,
            'field_key' => $fieldKey,
            'field_value' => $fieldValue,
            'updated_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return ['action' => 'inserted', 'id' => $newId];
    }
}

// ═══════════════════════════════════════════════════════════════
// دالة: حفظ الحقول الإضافية للمتوفين
// ═══════════════════════════════════════════════════════════════
function saveDeceasedExtraFields($sponsorshipId, $fileIdNumber, $identityNumber, $phone, $altPhone, $address, $userId = null) {
    echo "\n   📋 حفظ الحقول الإضافية في portal_general_registration_field_values:\n";

    $fields = [
        'field_data_phone_number' => $phone,
        'field_data_alt_phone_number' => $altPhone,
        'field_housing_address_detail' => $address
    ];

    $results = [];
    foreach ($fields as $key => $value) {
        if (!empty($value)) {
            try {
                $result = saveFieldValue($sponsorshipId, $fileIdNumber, $identityNumber, $key, $value, $userId);
                echo "      ✅ {$key}: {$value} ({$result['action']})\n";
                $results[$key] = $result;
            } catch (\Exception $e) {
                echo "      ❌ {$key}: خطأ - " . $e->getMessage() . "\n";
                $results[$key] = ['action' => 'error', 'message' => $e->getMessage()];
            }
        }
    }

    return $results;
}

// ═══════════════════════════════════════════════════════════════
// السيناريو 4أ: كفالة أب متوفي مربوطة مسبقاً - تحديث
// ═══════════════════════════════════════════════════════════════
function runScenario4A() {
    echo "\n" . str_repeat("▓", 60) . "\n";
    echo "📋 السيناريو 4أ: أب متوفي (deceased_father) مربوط - تحديث\n";
    echo "   مع الحقول الإضافية: هاتف، هاتف بديل، عنوان تفصيلي\n";
    echo str_repeat("▓", 60) . "\n\n";

    Log::info('═══ السيناريو 4أ: deceased_father مربوط مع الحقول الإضافية ═══');

    // البحث عن كفالة deceased_father مربوطة
    $sponsorship = DB::table('sponsorships')
        ->where('person_type', 'deceased_father')
        ->whereNotNull('relation_id_number')
        ->where('relation_id_number', '!=', '')
        ->whereNotNull('internal_file_number')
        ->where('internal_file_number', '!=', '')
        ->first();

    if (!$sponsorship) {
        echo "⚠️ لا توجد كفالة deceased_father - سيتم تحويل كفالة للاختبار...\n";

        $anySponsorship = DB::table('sponsorships')
            ->whereNotNull('relation_id_number')
            ->where('relation_id_number', '!=', '')
            ->first();

        if ($anySponsorship) {
            DB::table('sponsorships')
                ->where('internal_file_number', $anySponsorship->internal_file_number)
                ->update(['person_type' => 'deceased_father']);

            $sponsorship = DB::table('sponsorships')
                ->where('internal_file_number', $anySponsorship->internal_file_number)
                ->first();

            echo "✅ تم تحويل الكفالة {$sponsorship->internal_file_number} إلى deceased_father للاختبار\n";
        } else {
            echo "❌ لا توجد كفالات مربوطة!\n";
            return false;
        }
    }

    $sponsorshipId = $sponsorship->id;
    $internalFileNumber = $sponsorship->internal_file_number;
    $relationIdNumber = $sponsorship->relation_id_number;
    $personType = $sponsorship->person_type;

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - sponsorship_id: {$sponsorshipId}\n";
    echo "   - internal_file_number: {$internalFileNumber} (للقراءة فقط)\n";
    echo "   - relation_id_number: [{$relationIdNumber}]\n";
    echo "   - person_type: {$personType}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: جلب السجلات المرتبطة
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 1: جلب السجلات المرتبطة\n";
    echo str_repeat("-", 50) . "\n";

    $existingDeadPerson = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
    $existingBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $relationIdNumber)->first();

    echo "   - dead_people: " . ($existingDeadPerson ? "موجود" : "❌ غير موجود") . "\n";
    echo "   - guardian_bank_accounts: " . ($existingBank ? "موجود" : "❌ غير موجود") . "\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: تجهيز بيانات المتوفي مع الحقول الإضافية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: تجهيز بيانات المتوفي مع الحقول الإضافية\n";
    echo str_repeat("-", 50) . "\n";

    $deceasedData = [
        'first_name' => 'أحمد',
        'second_name' => 'محمد',
        'third_name' => 'علي',
        'last_name' => 'المتوفي',
        'identity_number' => '806' . rand(100000, 999999),
        'death_date' => '2020-05-15',
        // الحقول الإضافية للمتوفين
        'phone' => '0591234567',
        'alt_phone' => '0597654321',
        'detailed_address' => 'غزة - حي الرمال - شارع الجلاء - عمارة السلام - الطابق الثالث'
    ];
    $deceasedFullName = "{$deceasedData['first_name']} {$deceasedData['second_name']} {$deceasedData['third_name']} {$deceasedData['last_name']}";

    echo "   المتوفي (أب): {$deceasedFullName}\n";
    echo "   الهوية: {$deceasedData['identity_number']}\n";
    echo "   تاريخ الوفاة: {$deceasedData['death_date']}\n";
    echo "   📞 رقم الهاتف: {$deceasedData['phone']}\n";
    echo "   📞 رقم الهاتف البديل: {$deceasedData['alt_phone']}\n";
    echo "   🏠 العنوان التفصيلي: {$deceasedData['detailed_address']}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: تحديث/إدخال في جدول dead_people
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: تحديث/إدخال في جدول dead_people\n";
    echo str_repeat("-", 50) . "\n";

    $deadPeopleData = [
        'father_first_name' => $deceasedData['first_name'],
        'father_second_name' => $deceasedData['second_name'],
        'father_third_name' => $deceasedData['third_name'],
        'father_last_name' => $deceasedData['last_name'],
        'father_id' => (int)preg_replace('/[^0-9]/', '', $deceasedData['identity_number']),
        'father_death_date' => $deceasedData['death_date'],
        'updated_at' => now()
    ];

    if ($existingDeadPerson) {
        $updatedRows = DB::table('dead_people')
            ->where('re_file_id', $relationIdNumber)
            ->update($deadPeopleData);
        echo "   ✅ تم تحديث البيانات في dead_people! (Rows: {$updatedRows})\n";
    } else {
        $deadPeopleData['re_file_id'] = $relationIdNumber;
        $deadPeopleData['created_at'] = now();

        $newId = DB::table('dead_people')->insertGetId($deadPeopleData);
        echo "   ✅ تم إدخال سجل جديد في dead_people! ID: {$newId}\n";
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: حفظ الحقول الإضافية في portal_general_registration_field_values
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: حفظ الحقول الإضافية\n";
    echo str_repeat("-", 50) . "\n";

    saveDeceasedExtraFields(
        $sponsorshipId,
        $internalFileNumber,
        $deceasedData['identity_number'],
        $deceasedData['phone'],
        $deceasedData['alt_phone'],
        $deceasedData['detailed_address'],
        1 // user_id للاختبار
    );

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: تحديث/إضافة المعلومات البنكية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: تحديث/إضافة المعلومات البنكية\n";
    echo str_repeat("-", 50) . "\n";

    $bankData = [
        'bank_name' => 4,
        'iban_usd' => 'PS92PALS000000000DEC' . rand(10000, 99999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000DEC' . rand(10000, 99999) . 'ILS',
        're_id_number' => $deceasedData['identity_number'],
        're_guardian_name' => $deceasedFullName,
        're_phone_number' => $deceasedData['phone'],
        'person_owner_identity_number' => $deceasedData['identity_number'],
        'check_account' => 1,
        'updated_at' => now()
    ];

    if ($existingBank) {
        $updatedRows = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $relationIdNumber)
            ->update($bankData);
        echo "   ✅ تم تحديث المعلومات البنكية! (Rows: {$updatedRows})\n";
    } else {
        $bankData['guardian_registration'] = $relationIdNumber;
        $bankData['created_at'] = now();

        $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankData);
        echo "   ✅ تم إضافة معلومات بنكية جديدة! ID: {$newBankId}\n";
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 6: تحديث جدول sponsorships (المكفول فقط)
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 6: تحديث جدول sponsorships (بيانات المكفول فقط)\n";
    echo str_repeat("-", 50) . "\n";

    $sponsorshipUpdate = [
        'orphan_name' => $deceasedFullName,
        'identity_number' => $deceasedData['identity_number'],
        'updated_at' => now()
    ];

    echo "   التحديثات (deceased_father - المكفول فقط):\n";
    echo "      - orphan_name: {$deceasedFullName}\n";
    echo "      - identity_number: {$deceasedData['identity_number']}\n";
    echo "      ⚠️ لا يتم تعديل guardian_name و guardian_identity_number (لا يوجد معيل)\n";

    $updated = DB::table('sponsorships')
        ->where('internal_file_number', $internalFileNumber)
        ->update($sponsorshipUpdate);

    echo "\n   ✅ تم تحديث الكفالة! (Rows: {$updated})\n";

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->where('internal_file_number', $internalFileNumber)->first();
    $updatedDeadPerson = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
    $updatedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $relationIdNumber)->first();

    // جلب الحقول الإضافية
    $extraFields = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', $sponsorshipId)
        ->whereIn('field_key', ['field_data_phone_number', 'field_data_alt_phone_number', 'field_housing_address_detail'])
        ->get()
        ->keyBy('field_key');

    echo "\n🎯 الكفالة بعد التحديث:\n";
    echo "   - internal_file_number: {$updatedSponsorship->internal_file_number} (لم يُعدّل ✅)\n";
    echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
    echo "   - identity_number: {$updatedSponsorship->identity_number}\n";

    echo "\n⚰️ بيانات المتوفي (dead_people):\n";
    if ($updatedDeadPerson) {
        $deadFullName = "{$updatedDeadPerson->father_first_name} {$updatedDeadPerson->father_second_name} {$updatedDeadPerson->father_third_name} {$updatedDeadPerson->father_last_name}";
        echo "   - الاسم: {$deadFullName}\n";
        echo "   - الهوية: {$updatedDeadPerson->father_id}\n";
        echo "   - تاريخ الوفاة: {$updatedDeadPerson->father_death_date}\n";
    }

    echo "\n📝 الحقول الإضافية (portal_general_registration_field_values):\n";
    $phoneField = $extraFields->get('field_data_phone_number');
    $altPhoneField = $extraFields->get('field_data_alt_phone_number');
    $addressField = $extraFields->get('field_housing_address_detail');

    echo "   - رقم الهاتف: " . ($phoneField ? $phoneField->field_value : 'غير موجود') . "\n";
    echo "   - رقم الهاتف البديل: " . ($altPhoneField ? $altPhoneField->field_value : 'غير موجود') . "\n";
    echo "   - العنوان التفصيلي: " . ($addressField ? $addressField->field_value : 'غير موجود') . "\n";

    echo "\n🏦 الحساب البنكي:\n";
    if ($updatedBank) {
        echo "   - البنك: {$updatedBank->bank_name}\n";
        echo "   - IBAN USD: {$updatedBank->iban_usd}\n";
        echo "   - رقم الهاتف: {$updatedBank->re_phone_number}\n";
    }

    // التحقق من نجاح جميع العمليات
    $success = $updatedSponsorship->orphan_name == $deceasedFullName
        && $phoneField && $phoneField->field_value == $deceasedData['phone']
        && $addressField && $addressField->field_value == $deceasedData['detailed_address'];

    if ($success) {
        echo "\n✅✅✅ السيناريو 4أ: نجاح تام! ✅✅✅\n";
        return true;
    } else {
        echo "\n❌ هناك مشكلة!\n";
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// السيناريو 4ب: كفالة أم متوفية غير مربوطة - إنشاء جديد
// ═══════════════════════════════════════════════════════════════
function runScenario4B() {
    echo "\n\n" . str_repeat("▓", 60) . "\n";
    echo "📋 السيناريو 4ب: أم متوفية (deceased_mother) غير مربوطة - إنشاء جديد\n";
    echo "   مع الحقول الإضافية: هاتف، هاتف بديل، عنوان تفصيلي\n";
    echo str_repeat("▓", 60) . "\n\n";

    Log::info('═══ السيناريو 4ب: deceased_mother غير مربوط ═══');

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
        // إنشاء كفالة جديدة للاختبار
        $newFileNumber = 'TEST_DEC_' . rand(10000, 99999);
        $newId = DB::table('sponsorships')->insertGetId([
            'internal_file_number' => $newFileNumber,
            'person_type' => 'deceased_mother',
            'orphan_name' => 'كفالة اختبار',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $sponsorship = DB::table('sponsorships')->find($newId);
        echo "✅ تم إنشاء كفالة جديدة للاختبار: {$newFileNumber}\n";
    } else {
        // تحديث نوع الكفالة
        DB::table('sponsorships')
            ->where('internal_file_number', $sponsorship->internal_file_number)
            ->update(['person_type' => 'deceased_mother']);

        $sponsorship = DB::table('sponsorships')
            ->where('internal_file_number', $sponsorship->internal_file_number)
            ->first();
    }

    $sponsorshipId = $sponsorship->id;
    $internalFileNumber = $sponsorship->internal_file_number;
    $personType = 'deceased_mother';

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - sponsorship_id: {$sponsorshipId}\n";
    echo "   - internal_file_number: {$internalFileNumber}\n";
    echo "   - person_type: {$personType}\n";
    echo "   - relation_id_number: [فارغ - غير مربوطة]\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: إنشاء رقم ملف جديد (file_id_number)
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 1: إنشاء رقم ملف جديد\n";
    echo str_repeat("-", 50) . "\n";

    // توليد file_id_number جديد
    $newFileId = 'DM' . date('Ymd') . rand(1000, 9999);
    echo "   - file_id_number الجديد: {$newFileId}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: تجهيز بيانات المتوفية مع الحقول الإضافية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: تجهيز بيانات الأم المتوفية مع الحقول الإضافية\n";
    echo str_repeat("-", 50) . "\n";

    $deceasedData = [
        'first_name' => 'فاطمة',
        'second_name' => 'أحمد',
        'third_name' => 'محمود',
        'last_name' => 'المتوفية',
        'identity_number' => '807' . rand(100000, 999999),
        'death_date' => '2021-08-20',
        // الحقول الإضافية للمتوفين
        'phone' => '0599887766',
        'alt_phone' => '0568877665',
        'detailed_address' => 'خانيونس - حي الأمل - بجوار مسجد النور - منزل العائلة'
    ];
    $deceasedFullName = "{$deceasedData['first_name']} {$deceasedData['second_name']} {$deceasedData['third_name']} {$deceasedData['last_name']}";

    echo "   المتوفية (أم): {$deceasedFullName}\n";
    echo "   الهوية: {$deceasedData['identity_number']}\n";
    echo "   تاريخ الوفاة: {$deceasedData['death_date']}\n";
    echo "   📞 رقم الهاتف: {$deceasedData['phone']}\n";
    echo "   📞 رقم الهاتف البديل: {$deceasedData['alt_phone']}\n";
    echo "   🏠 العنوان التفصيلي: {$deceasedData['detailed_address']}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: إنشاء سجل جديد في جدول dead_people
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: إنشاء سجل جديد في dead_people\n";
    echo str_repeat("-", 50) . "\n";

    $deadPeopleData = [
        're_file_id' => $newFileId,
        'mother_first_name' => $deceasedData['first_name'],
        'mother_second_name' => $deceasedData['second_name'],
        'mother_third_name' => $deceasedData['third_name'],
        'mother_last_name' => $deceasedData['last_name'],
        'mother_id' => (int)preg_replace('/[^0-9]/', '', $deceasedData['identity_number']),
        'mother_death_date' => $deceasedData['death_date'],
        'created_at' => now(),
        'updated_at' => now()
    ];

    $newDeadId = DB::table('dead_people')->insertGetId($deadPeopleData);
    echo "   ✅ تم إنشاء سجل جديد في dead_people! ID: {$newDeadId}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: حفظ الحقول الإضافية في portal_general_registration_field_values
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: حفظ الحقول الإضافية\n";
    echo str_repeat("-", 50) . "\n";

    saveDeceasedExtraFields(
        $sponsorshipId,
        $internalFileNumber,
        $deceasedData['identity_number'],
        $deceasedData['phone'],
        $deceasedData['alt_phone'],
        $deceasedData['detailed_address'],
        1 // user_id للاختبار
    );

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: إضافة معلومات بنكية جديدة
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: إضافة معلومات بنكية جديدة\n";
    echo str_repeat("-", 50) . "\n";

    $bankData = [
        'guardian_registration' => $newFileId,
        'bank_name' => 4,
        'iban_usd' => 'PS92PALS000000000DM' . rand(10000, 99999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000DM' . rand(10000, 99999) . 'ILS',
        're_id_number' => $deceasedData['identity_number'],
        're_guardian_name' => $deceasedFullName,
        're_phone_number' => $deceasedData['phone'],
        'person_owner_identity_number' => $deceasedData['identity_number'],
        'check_account' => 1,
        'created_at' => now(),
        'updated_at' => now()
    ];

    $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankData);
    echo "   ✅ تم إضافة معلومات بنكية جديدة! ID: {$newBankId}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 6: تحديث جدول sponsorships وربطه
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 6: تحديث جدول sponsorships وربطه\n";
    echo str_repeat("-", 50) . "\n";

    $sponsorshipUpdate = [
        'orphan_name' => $deceasedFullName,
        'identity_number' => $deceasedData['identity_number'],
        'relation_id_number' => $newFileId, // ربط الكفالة بالملف الجديد
        'person_type' => 'deceased_mother',
        'updated_at' => now()
    ];

    echo "   التحديثات (deceased_mother - إنشاء جديد):\n";
    echo "      - orphan_name: {$deceasedFullName}\n";
    echo "      - identity_number: {$deceasedData['identity_number']}\n";
    echo "      - relation_id_number: {$newFileId} (الربط الجديد)\n";
    echo "      ⚠️ لا يتم إدخال guardian_name و guardian_identity_number (لا يوجد معيل)\n";

    $updated = DB::table('sponsorships')
        ->where('internal_file_number', $internalFileNumber)
        ->update($sponsorshipUpdate);

    echo "\n   ✅ تم تحديث الكفالة وربطها! (Rows: {$updated})\n";

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->where('internal_file_number', $internalFileNumber)->first();
    $updatedDeadPerson = DB::table('dead_people')->where('re_file_id', $newFileId)->first();
    $updatedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileId)->first();

    // جلب الحقول الإضافية
    $extraFields = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', $sponsorshipId)
        ->whereIn('field_key', ['field_data_phone_number', 'field_data_alt_phone_number', 'field_housing_address_detail'])
        ->get()
        ->keyBy('field_key');

    echo "\n🎯 الكفالة بعد التحديث:\n";
    echo "   - internal_file_number: {$updatedSponsorship->internal_file_number} (لم يُعدّل ✅)\n";
    echo "   - relation_id_number: [{$updatedSponsorship->relation_id_number}] ← ربط جديد\n";
    echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
    echo "   - identity_number: {$updatedSponsorship->identity_number}\n";

    echo "\n⚰️ بيانات المتوفية (dead_people):\n";
    if ($updatedDeadPerson) {
        $deadFullName = "{$updatedDeadPerson->mother_first_name} {$updatedDeadPerson->mother_second_name} {$updatedDeadPerson->mother_third_name} {$updatedDeadPerson->mother_last_name}";
        echo "   - الاسم: {$deadFullName}\n";
        echo "   - الهوية: {$updatedDeadPerson->mother_id}\n";
        echo "   - تاريخ الوفاة: {$updatedDeadPerson->mother_death_date}\n";
    }

    echo "\n📝 الحقول الإضافية (portal_general_registration_field_values):\n";
    $phoneField = $extraFields->get('field_data_phone_number');
    $altPhoneField = $extraFields->get('field_data_alt_phone_number');
    $addressField = $extraFields->get('field_housing_address_detail');

    echo "   - رقم الهاتف: " . ($phoneField ? $phoneField->field_value : 'غير موجود') . "\n";
    echo "   - رقم الهاتف البديل: " . ($altPhoneField ? $altPhoneField->field_value : 'غير موجود') . "\n";
    echo "   - العنوان التفصيلي: " . ($addressField ? $addressField->field_value : 'غير موجود') . "\n";

    echo "\n🏦 الحساب البنكي:\n";
    if ($updatedBank) {
        echo "   - البنك: {$updatedBank->bank_name}\n";
        echo "   - IBAN USD: {$updatedBank->iban_usd}\n";
        echo "   - رقم الهاتف: {$updatedBank->re_phone_number}\n";
    }

    // التحقق من نجاح جميع العمليات
    $success = $updatedSponsorship->orphan_name == $deceasedFullName
        && $updatedSponsorship->relation_id_number == $newFileId
        && $phoneField && $phoneField->field_value == $deceasedData['phone']
        && $addressField && $addressField->field_value == $deceasedData['detailed_address'];

    if ($success) {
        echo "\n✅✅✅ السيناريو 4ب: نجاح تام! ✅✅✅\n";
        return true;
    } else {
        echo "\n❌ هناك مشكلة!\n";
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// تشغيل السيناريوهات
// ═══════════════════════════════════════════════════════════════

$results = [];

// تشغيل السيناريو 4أ
$results['4A'] = runScenario4A();

// تشغيل السيناريو 4ب
$results['4B'] = runScenario4B();

// ═══════════════════════════════════════════════════════════════
// ملخص النتائج
// ═══════════════════════════════════════════════════════════════
echo "\n\n" . str_repeat("═", 60) . "\n";
echo "📊 ملخص نتائج السيناريو الرابع المحسّن (المتوفين مع الحقول الإضافية)\n";
echo str_repeat("═", 60) . "\n\n";

echo "| السيناريو | الوصف | النتيجة |\n";
echo "|-----------|-------|----------|\n";
echo "| 4أ | deceased_father مربوط - تحديث | " . ($results['4A'] ? '✅ نجاح' : '❌ فشل') . " |\n";
echo "| 4ب | deceased_mother غير مربوط - إنشاء | " . ($results['4B'] ? '✅ نجاح' : '❌ فشل') . " |\n";

$allPassed = !in_array(false, $results);

echo "\n";
if ($allPassed) {
    echo "🎉🎉🎉 جميع السيناريوهات نجحت! 🎉🎉🎉\n\n";
    echo "✅ تم حفظ الحقول الإضافية بنجاح:\n";
    echo "   - field_data_phone_number (رقم الهاتف)\n";
    echo "   - field_data_alt_phone_number (رقم الهاتف البديل)\n";
    echo "   - field_housing_address_detail (العنوان التفصيلي)\n";
} else {
    echo "⚠️ بعض السيناريوهات فشلت. يرجى مراجعة التفاصيل أعلاه.\n";
}

echo "\n";
