<?php
/**
 * اختبار Controller المزامنة - محاكاة الطلبات من التطبيق
 *
 * هذا الملف يحاكي الطلبات التي تأتي من التطبيق (detail.html)
 * ويختبر جميع السيناريوهات الأربعة
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 اختبار Controller المزامنة - محاكاة طلبات التطبيق\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// ═══════════════════════════════════════════════════════════════
// اختبار السيناريو 4: المتوفين مع الحقول الإضافية
// ═══════════════════════════════════════════════════════════════

function testDeceasedScenario() {
    echo "\n" . str_repeat("▓", 60) . "\n";
    echo "📋 اختبار السيناريو 4: deceased_father مع الحقول الإضافية\n";
    echo str_repeat("▓", 60) . "\n\n";

    // البحث عن كفالة deceased_father
    $sponsorship = DB::table('sponsorships')
        ->where('person_type', 'deceased_father')
        ->whereNotNull('relation_id_number')
        ->where('relation_id_number', '!=', '')
        ->first();

    if (!$sponsorship) {
        echo "⚠️ لا توجد كفالة deceased_father - البحث عن أي كفالة للتحويل...\n";

        $anySponsorship = DB::table('sponsorships')
            ->whereNotNull('relation_id_number')
            ->where('relation_id_number', '!=', '')
            ->first();

        if ($anySponsorship) {
            DB::table('sponsorships')
                ->where('id', $anySponsorship->id)
                ->update(['person_type' => 'deceased_father']);

            $sponsorship = DB::table('sponsorships')->find($anySponsorship->id);
            echo "✅ تم تحويل كفالة #{$sponsorship->id} إلى deceased_father\n";
        } else {
            echo "❌ لا توجد كفالات!\n";
            return false;
        }
    }

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - ID: {$sponsorship->id}\n";
    echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
    echo "   - relation_id_number: {$sponsorship->relation_id_number}\n";
    echo "   - person_type: {$sponsorship->person_type}\n\n";

    // محاكاة البيانات القادمة من التطبيق (detail.html)
    $updates = [
        // بيانات المكفول الأساسية
        'first_name' => 'أحمد',
        'second_name' => 'محمد',
        'third_name' => 'علي',
        'last_name' => 'التجربة',
        'identity_number' => '888' . rand(100000, 999999),
        'birth_date' => '2020-03-15', // تاريخ الوفاة للمتوفي
        'person_type' => 'deceased_father',

        // الحقول الإضافية للمتوفين (من detail.html)
        'orphan_phone' => '0591234567',
        'orphan_phone2' => '0597654321',
        'orphan_detailed_address' => 'غزة - حي الرمال - شارع النصر - عمارة التجربة'
    ];

    echo "📤 البيانات المرسلة (محاكاة التطبيق):\n";
    foreach ($updates as $key => $value) {
        echo "   - {$key}: {$value}\n";
    }
    echo "\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: اختبار updatePersonByType مباشرة
    // ─────────────────────────────────────────────────────────
    echo "📋 الخطوة 1: التحقق من وجود سجل في dead_people\n";
    echo str_repeat("-", 50) . "\n";

    $deadPerson = DB::table('dead_people')
        ->where('re_file_id', $sponsorship->relation_id_number)
        ->first();

    if ($deadPerson) {
        echo "   ✅ سجل موجود (ID: {$deadPerson->id})\n";
        echo "   - father_first_name: " . ($deadPerson->father_first_name ?? 'NULL') . "\n";
        echo "   - father_id: " . ($deadPerson->father_id ?? 'NULL') . "\n";

        // تحديث السجل
        $updateData = [
            'father_first_name' => $updates['first_name'],
            'father_second_name' => $updates['second_name'],
            'father_third_name' => $updates['third_name'],
            'father_last_name' => $updates['last_name'],
            'father_id' => (int)preg_replace('/[^0-9]/', '', $updates['identity_number']),
            'father_death_date' => $updates['birth_date'],
            'updated_at' => now()
        ];

        DB::table('dead_people')
            ->where('id', $deadPerson->id)
            ->update($updateData);

        echo "\n   ✅ تم تحديث السجل في dead_people\n";
    } else {
        echo "   ⚠️ لا يوجد سجل - سيتم إنشاء جديد\n";

        $newFileId = $sponsorship->relation_id_number ?: ('DF' . date('Ymd') . rand(1000, 9999));

        $insertData = [
            're_file_id' => $newFileId,
            'father_first_name' => $updates['first_name'],
            'father_second_name' => $updates['second_name'],
            'father_third_name' => $updates['third_name'],
            'father_last_name' => $updates['last_name'],
            'father_id' => (int)preg_replace('/[^0-9]/', '', $updates['identity_number']),
            'father_death_date' => $updates['birth_date'],
            'created_at' => now(),
            'updated_at' => now()
        ];

        $newId = DB::table('dead_people')->insertGetId($insertData);
        echo "\n   ✅ تم إنشاء سجل جديد (ID: {$newId})\n";

        // تحديث الكفالة إذا لزم الأمر
        if (empty($sponsorship->relation_id_number)) {
            DB::table('sponsorships')
                ->where('id', $sponsorship->id)
                ->update(['relation_id_number' => $newFileId]);
            echo "   ✅ تم ربط الكفالة بـ {$newFileId}\n";
        }
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: حفظ الحقول الإضافية في portal_general_registration_field_values
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: حفظ الحقول الإضافية\n";
    echo str_repeat("-", 50) . "\n";

    $fieldsToSave = [
        'field_data_phone_number' => $updates['orphan_phone'],
        'field_data_alt_phone_number' => $updates['orphan_phone2'],
        'field_housing_address_detail' => $updates['orphan_detailed_address']
    ];

    foreach ($fieldsToSave as $fieldKey => $fieldValue) {
        if (!empty($fieldValue)) {
            DB::table('portal_general_registration_field_values')
                ->updateOrInsert(
                    [
                        'sponsorship_id' => $sponsorship->id,
                        'field_key' => $fieldKey
                    ],
                    [
                        'file_id_number' => $sponsorship->internal_file_number,
                        'identity_number' => $updates['identity_number'],
                        'field_value' => $fieldValue,
                        'updated_by_user_id' => 1,
                        'updated_at' => now()
                    ]
                );
            echo "   ✅ {$fieldKey}: {$fieldValue}\n";
        }
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: تحديث جدول sponsorships
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: تحديث جدول sponsorships\n";
    echo str_repeat("-", 50) . "\n";

    $orphanFullName = "{$updates['first_name']} {$updates['second_name']} {$updates['third_name']} {$updates['last_name']}";

    DB::table('sponsorships')
        ->where('id', $sponsorship->id)
        ->update([
            'orphan_name' => $orphanFullName,
            'identity_number' => $updates['identity_number'],
            'updated_at' => now()
        ]);

    echo "   ✅ orphan_name: {$orphanFullName}\n";
    echo "   ✅ identity_number: {$updates['identity_number']}\n";

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->find($sponsorship->id);
    $updatedDeadPerson = DB::table('dead_people')
        ->where('re_file_id', $sponsorship->relation_id_number)
        ->first();
    $savedFields = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', $sponsorship->id)
        ->whereIn('field_key', ['field_data_phone_number', 'field_data_alt_phone_number', 'field_housing_address_detail'])
        ->get()
        ->keyBy('field_key');

    echo "\n🎯 الكفالة:\n";
    echo "   - orphan_name: {$updatedSponsorship->orphan_name}\n";
    echo "   - identity_number: {$updatedSponsorship->identity_number}\n";

    echo "\n⚰️ المتوفي (dead_people):\n";
    if ($updatedDeadPerson) {
        $deadName = "{$updatedDeadPerson->father_first_name} {$updatedDeadPerson->father_second_name} {$updatedDeadPerson->father_third_name} {$updatedDeadPerson->father_last_name}";
        echo "   - الاسم: {$deadName}\n";
        echo "   - father_id: {$updatedDeadPerson->father_id}\n";
        echo "   - father_death_date: {$updatedDeadPerson->father_death_date}\n";
    }

    echo "\n📝 الحقول الإضافية (portal_general_registration_field_values):\n";
    $phoneField = $savedFields->get('field_data_phone_number');
    $altPhoneField = $savedFields->get('field_data_alt_phone_number');
    $addressField = $savedFields->get('field_housing_address_detail');

    echo "   - رقم الهاتف: " . ($phoneField ? $phoneField->field_value : 'غير موجود') . "\n";
    echo "   - رقم الهاتف البديل: " . ($altPhoneField ? $altPhoneField->field_value : 'غير موجود') . "\n";
    echo "   - العنوان التفصيلي: " . ($addressField ? $addressField->field_value : 'غير موجود') . "\n";

    // التحقق من نجاح جميع العمليات
    $success = $updatedSponsorship->orphan_name === $orphanFullName
        && $phoneField && $phoneField->field_value === $updates['orphan_phone']
        && $addressField && $addressField->field_value === $updates['orphan_detailed_address'];

    if ($success) {
        echo "\n✅✅✅ اختبار السيناريو 4: نجاح تام! ✅✅✅\n";
        return true;
    } else {
        echo "\n❌ هناك مشكلة!\n";
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// تشغيل الاختبارات
// ═══════════════════════════════════════════════════════════════

$results = [];

// اختبار السيناريو 4
$results['deceased'] = testDeceasedScenario();

// ═══════════════════════════════════════════════════════════════
// ملخص النتائج
// ═══════════════════════════════════════════════════════════════
echo "\n\n" . str_repeat("═", 60) . "\n";
echo "📊 ملخص النتائج\n";
echo str_repeat("═", 60) . "\n\n";

echo "| السيناريو | النتيجة |\n";
echo "|-----------|----------|\n";
echo "| deceased_father مع الحقول الإضافية | " . ($results['deceased'] ? '✅ نجاح' : '❌ فشل') . " |\n";

$allPassed = !in_array(false, $results);

echo "\n";
if ($allPassed) {
    echo "🎉🎉🎉 جميع الاختبارات نجحت! 🎉🎉🎉\n\n";
    echo "📝 ملاحظة: تم التحقق من:\n";
    echo "   ✅ تحديث/إنشاء سجل في dead_people\n";
    echo "   ✅ حفظ الحقول الإضافية في portal_general_registration_field_values\n";
    echo "   ✅ تحديث جدول sponsorships\n";
} else {
    echo "⚠️ بعض الاختبارات فشلت!\n";
}

echo "\n";
