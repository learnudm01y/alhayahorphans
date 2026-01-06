<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           اختبار استخراج بيانات المعيل بعد التعديل\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// محاكاة ما يفعله extractFieldValues
$sponsorship = Sponsorship::where('identity_number', '42979087')->first();

$values = [];
$identityNumber = $sponsorship->identity_number;
$guardianIdentityNumber = $sponsorship->guardian_identity_number;

echo "1️⃣  البيانات الأساسية:\n";
echo "   - identity_number: $identityNumber\n";
echo "   - guardian_identity_number: " . ($guardianIdentityNumber ?? 'فارغ') . "\n";

// تحديد المتغيرات
$guardianData = null;
$guardianDataSource = null;
$guardianCivilRegistryData = null;

// البحث عن المكفول في re_people
$rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
if ($rePerson) {
    echo "\n2️⃣  المكفول موجود في re_people:\n";
    echo "   - الاسم: {$rePerson->first_name} {$rePerson->second_name}\n";
    echo "   - registration_id: {$rePerson->registration_id}\n";

    // جلب بيانات المعيل من data
    $guardianData = DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
    if ($guardianData) {
        $guardianDataSource = 'data';
        echo "   ✓ المعيل موجود في data (via registration_id)\n";
    }
}

// البحث عن المعيل برقم هويته
if (!$guardianData && $guardianIdentityNumber) {
    echo "\n3️⃣  البحث عن المعيل برقم الهوية:\n";

    // أولاً في data
    $guardianData = DB::table('data')->where('data_id_number', $guardianIdentityNumber)->first();
    if ($guardianData) {
        $guardianDataSource = 'data';
        echo "   ✓ المعيل موجود في data (via data_id_number)\n";
    } else {
        echo "   ✗ المعيل غير موجود في data\n";

        // البحث في السجل المدني
        echo "   → البحث في السجل المدني...\n";
        $civilPerson = DB::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', $guardianIdentityNumber)
            ->first();

        if ($civilPerson) {
            $guardianDataSource = 'civil_registry';
            $guardianCivilRegistryData = [
                'first_name' => $civilPerson->CI_FIRST_ARB,
                'second_name' => $civilPerson->CI_FATHER_ARB,
                'third_name' => $civilPerson->CI_GRAND_FATHER_ARB,
                'last_name' => $civilPerson->CI_FAMILY_ARB,
                'birth_date' => $civilPerson->CI_BIRTH_DATE ?? '',
            ];
            echo "   ✓ المعيل موجود في السجل المدني\n";
        }
    }
}

// ملء القيم بناءً على المصدر
echo "\n4️⃣  مصدر بيانات المعيل: " . ($guardianDataSource ?? 'none') . "\n";

if ($guardianDataSource === 'data' && $guardianData) {
    echo "\n5️⃣  بيانات المعيل من جدول data:\n";
    echo "   - field_data_id_number: {$guardianData->data_id_number}\n";
    echo "   - field_data_first_name: {$guardianData->data_first_name}\n";
    echo "   - field_data_father_name: {$guardianData->data_father_name}\n";
    echo "   - field_data_grand_father_name: {$guardianData->data_grand_father_name}\n";
    echo "   - field_data_family_name: {$guardianData->data_family_name}\n";
    echo "   - field_data_birth_date: " . ($guardianData->data_birth_date ?? 'فارغ') . "\n";
    echo "   - field_data_phone_number: " . ($guardianData->data_phone_number ?? 'فارغ') . "\n";

} elseif ($guardianDataSource === 'civil_registry' && $guardianCivilRegistryData) {
    echo "\n5️⃣  بيانات المعيل من السجل المدني:\n";
    echo "   - field_data_id_number: $guardianIdentityNumber\n";
    echo "   - field_data_first_name: {$guardianCivilRegistryData['first_name']}\n";
    echo "   - field_data_father_name: {$guardianCivilRegistryData['second_name']}\n";
    echo "   - field_data_grand_father_name: {$guardianCivilRegistryData['third_name']}\n";
    echo "   - field_data_family_name: {$guardianCivilRegistryData['last_name']}\n";
    echo "   - field_data_birth_date: {$guardianCivilRegistryData['birth_date']}\n";

} else {
    echo "\n5️⃣  ❌ لا توجد بيانات تفصيلية للمعيل\n";
}

echo "\n✅ الاختبار مكتمل!\n";
