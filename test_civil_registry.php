<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== اختبار جلب البيانات من السجل المدني ===\n\n";

// أمثلة على أرقام هويات للاختبار (قم بتغييرها لأرقام حقيقية)
$testIdentities = [
    '123456789', // رقم هوية مثال - قم بتغييره
    '987654321', // رقم هوية مثال - قم بتغييره
];

echo "📝 أدخل رقم الهوية للاختبار (أو اضغط Enter لاختبار أرقام افتراضية):\n";
$input = trim(fgets(STDIN));

if (!empty($input)) {
    $testIdentities = [$input];
}

function getPersonFromCivilRegistry(string $identityNumber): ?array
{
    try {
        $person = DB::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', $identityNumber)
            ->first();

        if (!$person) {
            return null;
        }

        $fullName = trim(
            ($person->CI_FIRST_ARB ?? '') . ' ' .
            ($person->CI_FATHER_ARB ?? '') . ' ' .
            ($person->CI_GRAND_FATHER_ARB ?? '') . ' ' .
            ($person->CI_FAMILY_ARB ?? '')
        );

        return [
            'full_name' => $fullName,
            'first_name' => $person->CI_FIRST_ARB ?? '',
            'father_name' => $person->CI_FATHER_ARB ?? '',
            'grand_father_name' => $person->CI_GRAND_FATHER_ARB ?? '',
            'family_name' => $person->CI_FAMILY_ARB ?? '',
            'birth_date' => $person->CI_BIRTH_DT ?? null,
            'gender' => $person->CI_SEX_CD == 1 ? 'ذكر' : ($person->CI_SEX_CD == 2 ? 'أنثى' : null),
            'sex_code' => $person->CI_SEX_CD ?? null
        ];

    } catch (\Exception $e) {
        echo "❌ خطأ: " . $e->getMessage() . "\n";
        return null;
    }
}

foreach ($testIdentities as $identity) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "🔍 اختبار رقم الهوية: {$identity}\n";
    echo str_repeat('=', 70) . "\n";

    $result = getPersonFromCivilRegistry($identity);

    if ($result) {
        echo "✅ تم العثور على البيانات:\n\n";
        echo "   الاسم الكامل: {$result['full_name']}\n";
        echo "   الاسم الأول: {$result['first_name']}\n";
        echo "   اسم الأب: {$result['father_name']}\n";
        echo "   اسم الجد: {$result['grand_father_name']}\n";
        echo "   اسم العائلة: {$result['family_name']}\n";
        echo "   تاريخ الميلاد: " . ($result['birth_date'] ?? 'غير متوفر') . "\n";
        echo "   الجنس: " . ($result['gender'] ?? 'غير متوفر') . " (كود: {$result['sex_code']})\n";
    } else {
        echo "❌ لم يتم العثور على بيانات لهذا الرقم\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "=== اختبار البحث في جدول sponsorships ===\n";
echo str_repeat('=', 70) . "\n\n";

// اختبار جلب كفالة وبيانات المعيل من السجل المدني
echo "📝 أدخل ID كفالة للاختبار (أو اضغط Enter للتخطي):\n";
$sponsorshipId = trim(fgets(STDIN));

if (!empty($sponsorshipId)) {
    $sponsorship = DB::table('sponsorships')->where('id', $sponsorshipId)->first();

    if ($sponsorship) {
        echo "\n✅ تم العثور على الكفالة:\n";
        echo "   اسم المكفول: {$sponsorship->orphan_name}\n";
        echo "   رقم هوية المكفول: {$sponsorship->identity_number}\n";
        echo "   اسم المعيل: {$sponsorship->guardian_name}\n";
        echo "   رقم هوية المعيل: {$sponsorship->guardian_identity_number}\n\n";

        // اختبار جلب بيانات المكفول
        if (!empty($sponsorship->identity_number)) {
            echo "🔍 محاولة جلب بيانات المكفول من السجل المدني...\n";
            $orphanData = getPersonFromCivilRegistry($sponsorship->identity_number);
            if ($orphanData) {
                echo "   ✅ تم العثور على بيانات المكفول:\n";
                echo "      الاسم: {$orphanData['full_name']}\n";
                echo "      الجنس: {$orphanData['gender']}\n";
                echo "      تاريخ الميلاد: {$orphanData['birth_date']}\n";
            } else {
                echo "   ❌ لم يتم العثور على بيانات المكفول في السجل المدني\n";
            }
        }

        // اختبار جلب بيانات المعيل
        if (!empty($sponsorship->guardian_identity_number)) {
            echo "\n🔍 محاولة جلب بيانات المعيل من السجل المدني...\n";
            $guardianData = getPersonFromCivilRegistry($sponsorship->guardian_identity_number);
            if ($guardianData) {
                echo "   ✅ تم العثور على بيانات المعيل:\n";
                echo "      الاسم: {$guardianData['full_name']}\n";
                echo "      الجنس: {$guardianData['gender']}\n";
                echo "      تاريخ الميلاد: {$guardianData['birth_date']}\n";
            } else {
                echo "   ❌ لم يتم العثور على بيانات المعيل في السجل المدني\n";
            }
        }

        // اختبار البحث في الجداول المحلية
        echo "\n🔍 البحث في الجداول المحلية:\n";

        // جدول data
        $dataRecord = DB::table('data')
            ->where('file_id_number', $sponsorship->relation_id_number)
            ->first();
        if ($dataRecord) {
            echo "   ✅ تم العثور على بيانات في جدول data\n";
            echo "      الاسم: {$dataRecord->data_first_name} {$dataRecord->data_father_name}\n";
        } else {
            echo "   ℹ️ لا توجد بيانات في جدول data\n";
        }

        // جدول re_people
        $repeopleRecord = DB::table('re_people')
            ->where('person_id', $sponsorship->identity_number)
            ->first();
        if ($repeopleRecord) {
            echo "   ✅ تم العثور على بيانات في جدول re_people\n";
            echo "      الاسم: {$repeopleRecord->first_name} {$repeopleRecord->second_name}\n";
        } else {
            echo "   ℹ️ لا توجد بيانات في جدول re_people\n";
        }

    } else {
        echo "❌ لم يتم العثور على الكفالة\n";
    }
}

echo "\n✅ انتهى الاختبار\n";
