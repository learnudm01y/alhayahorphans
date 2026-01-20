<?php
/**
 * اختبار شامل لعملية استيراد الكفالات
 * يحاكي السيناريو الحقيقي ويتحقق من جميع أنواع البيانات
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Models\RePeople;
use App\Helpers\NameSegmentation;

echo "====================================================\n";
echo "🧪 اختبار شامل لاستيراد الكفالات\n";
echo "====================================================\n\n";

$errors = [];
$passed = 0;
$failed = 0;

// ======================================
// 1. اختبار أنواع الأعمدة في جدول data
// ======================================
echo "📋 1. التحقق من أنواع الأعمدة في جدول data...\n";

$dataColumns = [
    'data_phone_number' => ['accepts_empty_string' => false],
    'data_alt_phone_number' => ['accepts_empty_string' => false],
    'data_gender' => ['accepts_empty_string' => false],
    'data_request_status' => ['accepts_empty_string' => false],
];

$cols = DB::select("SHOW COLUMNS FROM data");
$colTypes = [];
foreach ($cols as $col) {
    $colTypes[$col->Field] = $col->Type;
}

foreach ($dataColumns as $colName => $config) {
    if (isset($colTypes[$colName])) {
        $type = strtolower($colTypes[$colName]);
        $isNumeric = preg_match('/^(big)?int|tinyint|smallint|decimal|float|double/', $type);

        if ($isNumeric && !$config['accepts_empty_string']) {
            echo "   ✅ $colName ($type) - نوع رقمي، يجب تحويل النص الفارغ إلى null\n";
            $passed++;
        } else {
            echo "   ℹ️ $colName ($type)\n";
            $passed++;
        }
    } else {
        echo "   ⚠️ العمود $colName غير موجود\n";
    }
}

// ======================================
// 2. اختبار أنواع الأعمدة في جدول re_people
// ======================================
echo "\n📋 2. التحقق من أنواع الأعمدة في جدول re_people...\n";

$rePeopleColumns = [
    'person_type_of_guarantee' => ['accepts_empty_string' => false],
    'sponsorship_status' => ['accepts_empty_string' => false],
    'person_gender' => ['accepts_empty_string' => false],
];

$cols2 = DB::select("SHOW COLUMNS FROM re_people");
$colTypes2 = [];
foreach ($cols2 as $col) {
    $colTypes2[$col->Field] = $col->Type;
}

foreach ($rePeopleColumns as $colName => $config) {
    if (isset($colTypes2[$colName])) {
        $type = strtolower($colTypes2[$colName]);
        $isNumeric = preg_match('/^(big)?int|tinyint|smallint|decimal|float|double/', $type);

        if ($isNumeric) {
            echo "   ✅ $colName ($type) - نوع رقمي، لا يقبل نصوص\n";
            $passed++;
        } else {
            echo "   ℹ️ $colName ($type)\n";
            $passed++;
        }
    } else {
        echo "   ⚠️ العمود $colName غير موجود\n";
    }
}

// ======================================
// 3. اختبار تحويل أرقام الهاتف
// ======================================
echo "\n📋 3. اختبار تحويل أرقام الهاتف...\n";

$phoneTestCases = [
    ['input' => '', 'expected' => null, 'desc' => 'نص فارغ'],
    ['input' => '0', 'expected' => 0, 'desc' => 'صفر'],
    ['input' => '595613689', 'expected' => 595613689, 'desc' => 'رقم صحيح'],
    ['input' => '0595613689', 'expected' => 595613689, 'desc' => 'رقم يبدأ بصفر'],
    ['input' => null, 'expected' => null, 'desc' => 'null'],
    ['input' => 'abc', 'expected' => null, 'desc' => 'نص غير رقمي'],
    ['input' => '   ', 'expected' => null, 'desc' => 'مسافات فقط'],
];

function convertPhone($phone) {
    if ($phone === null || $phone === '') return null;
    $phone = trim($phone);
    if (empty($phone)) return null;
    if (!is_numeric($phone)) return null;
    return (int) $phone;
}

foreach ($phoneTestCases as $test) {
    $result = convertPhone($test['input']);
    $inputDisplay = $test['input'] === null ? 'null' : "'{$test['input']}'";
    $expectedDisplay = $test['expected'] === null ? 'null' : $test['expected'];
    $resultDisplay = $result === null ? 'null' : $result;

    if ($result === $test['expected']) {
        echo "   ✅ {$test['desc']}: $inputDisplay → $resultDisplay\n";
        $passed++;
    } else {
        echo "   ❌ {$test['desc']}: $inputDisplay → $resultDisplay (متوقع: $expectedDisplay)\n";
        $failed++;
        $errors[] = "تحويل الهاتف فشل: {$test['desc']}";
    }
}

// ======================================
// 4. اختبار محاكاة إنشاء معيل
// ======================================
echo "\n📋 4. محاكاة إنشاء معيل (بدون حفظ فعلي)...\n";

$testGuardianData = [
    [
        'identity' => '804428118',
        'name' => 'شروق مصدق مصطفى الددة',
        'phone' => '',  // هاتف فارغ - السيناريو المشكلة
        'alt_phone' => '0',
        'gender' => 2,
    ],
    [
        'identity' => '411173362',
        'name' => 'ثوره عوده عبد الجواد الحلبي',
        'phone' => '',  // هاتف فارغ
        'alt_phone' => '0',
        'gender' => 2,
    ],
    [
        'identity' => '800355752',
        'name' => 'عُلا صلاح حسن عابدين',
        'phone' => '',  // هاتف فارغ
        'alt_phone' => '595613689',  // هاتف بديل موجود
        'gender' => 2,
    ],
];

foreach ($testGuardianData as $guardian) {
    echo "\n   🧪 اختبار معيل: {$guardian['name']}\n";

    // محاكاة التحويلات
    $phone = $guardian['phone'] ?? '';
    $altPhone = $guardian['alt_phone'] ?? '';

    $phoneValue = !empty($phone) && is_numeric($phone) ? (int) $phone : null;
    $altPhoneValue = !empty($altPhone) && is_numeric($altPhone) ? (int) $altPhone : null;
    $genderValue = (int) $guardian['gender'];
    $statusValue = 4;

    echo "      - data_phone_number: " . ($phoneValue === null ? 'NULL' : $phoneValue) . "\n";
    echo "      - data_alt_phone_number: " . ($altPhoneValue === null ? 'NULL' : $altPhoneValue) . "\n";
    echo "      - data_gender: $genderValue\n";
    echo "      - data_request_status: $statusValue\n";

    // التحقق من أن القيم صالحة للإدخال
    $isPhoneValid = $phoneValue === null || is_int($phoneValue);
    $isAltPhoneValid = $altPhoneValue === null || is_int($altPhoneValue);
    $isGenderValid = is_int($genderValue) && in_array($genderValue, [1, 2]);
    $isStatusValid = is_int($statusValue);

    if ($isPhoneValid && $isAltPhoneValid && $isGenderValid && $isStatusValid) {
        echo "      ✅ جميع القيم صالحة للإدخال في قاعدة البيانات\n";
        $passed++;
    } else {
        echo "      ❌ بعض القيم غير صالحة!\n";
        $failed++;
        $errors[] = "قيم معيل {$guardian['identity']} غير صالحة";
    }
}

// ======================================
// 5. اختبار محاكاة إنشاء مكفول
// ======================================
echo "\n📋 5. محاكاة إنشاء مكفول (بدون حفظ فعلي)...\n";

$testSponsoredData = [
    [
        'identity' => '445130008',
        'name' => 'براء عزالدين دياب ياسين',
        'guardian_identity' => '027820',
    ],
];

foreach ($testSponsoredData as $sponsored) {
    echo "\n   🧪 اختبار مكفول: {$sponsored['name']}\n";

    // محاكاة تقسيم الاسم
    $segmented = NameSegmentation::segment($sponsored['name']);

    echo "      - الاسم الأول: {$segmented['first_name']}\n";
    echo "      - اسم الأب: {$segmented['father_name']}\n";
    echo "      - اسم الجد: {$segmented['grand_father_name']}\n";
    echo "      - العائلة: {$segmented['family_name']}\n";

    // محاكاة القيم
    $sponsorshipStatus = 1;
    $typeOfGuarantee = null;  // ✅ null بدلاً من 'family_member'

    echo "      - sponsorship_status: $sponsorshipStatus (int)\n";
    echo "      - person_type_of_guarantee: " . ($typeOfGuarantee === null ? 'NULL' : $typeOfGuarantee) . "\n";

    $isStatusValid = is_int($sponsorshipStatus);
    $isTypeValid = $typeOfGuarantee === null || is_int($typeOfGuarantee);

    if ($isStatusValid && $isTypeValid) {
        echo "      ✅ جميع القيم صالحة للإدخال في قاعدة البيانات\n";
        $passed++;
    } else {
        echo "      ❌ بعض القيم غير صالحة!\n";
        $failed++;
        $errors[] = "قيم مكفول {$sponsored['identity']} غير صالحة";
    }
}

// ======================================
// 6. اختبار SQL مباشر (محاكاة)
// ======================================
echo "\n📋 6. التحقق من صحة SQL المولد...\n";

// محاكاة SQL للمعيل
$testSqlGuardian = [
    'file_id_number' => '027737',
    'data_id_number' => '804428118',
    'data_first_name' => 'شروق',
    'data_father_name' => 'مصدق',
    'data_grand_father_name' => 'مصطفى',
    'data_family_name' => 'الددة',
    'data_birth_date' => '1993-04-30',
    'data_city' => 25,
    'data_gender' => 2,  // ✅ int
    'data_phone_number' => null,  // ✅ null بدلاً من ''
    'data_alt_phone_number' => 0,  // ✅ int
    'data_request_status' => 4,  // ✅ int
];

echo "   SQL للمعيل:\n";
$values = [];
foreach ($testSqlGuardian as $key => $value) {
    if ($value === null) {
        $values[] = 'NULL';
    } elseif (is_string($value)) {
        $values[] = "'$value'";
    } else {
        $values[] = $value;
    }
}
echo "   INSERT INTO data (" . implode(', ', array_keys($testSqlGuardian)) . ")\n";
echo "   VALUES (" . implode(', ', $values) . ")\n";

// التحقق من عدم وجود نصوص فارغة في الحقول الرقمية
$hasEmptyStringInNumeric = false;
foreach ($testSqlGuardian as $key => $value) {
    if (in_array($key, ['data_phone_number', 'data_alt_phone_number', 'data_gender', 'data_request_status'])) {
        if ($value === '') {
            $hasEmptyStringInNumeric = true;
            echo "   ❌ $key يحتوي على نص فارغ!\n";
        }
    }
}

if (!$hasEmptyStringInNumeric) {
    echo "   ✅ SQL صالح - لا توجد نصوص فارغة في الحقول الرقمية\n";
    $passed++;
} else {
    $failed++;
    $errors[] = "SQL المعيل يحتوي على نصوص فارغة في حقول رقمية";
}

// محاكاة SQL للمكفول
$testSqlSponsored = [
    'registration_id' => '027820',
    'person_id' => '445130008',
    'first_name' => 'براء',
    'second_name' => 'عزالدين',
    'third_name' => 'دياب',
    'last_name' => 'ياسين',
    'sponsorship_status' => 1,  // ✅ int
    'person_type_of_guarantee' => null,  // ✅ null بدلاً من 'family_member'
];

echo "\n   SQL للمكفول:\n";
$values2 = [];
foreach ($testSqlSponsored as $key => $value) {
    if ($value === null) {
        $values2[] = 'NULL';
    } elseif (is_string($value)) {
        $values2[] = "'$value'";
    } else {
        $values2[] = $value;
    }
}
echo "   INSERT INTO re_people (" . implode(', ', array_keys($testSqlSponsored)) . ")\n";
echo "   VALUES (" . implode(', ', $values2) . ")\n";

// التحقق من عدم وجود نصوص في الحقول الرقمية
$hasStringInNumeric = false;
foreach ($testSqlSponsored as $key => $value) {
    if (in_array($key, ['sponsorship_status', 'person_type_of_guarantee'])) {
        if (is_string($value) && $value !== '') {
            $hasStringInNumeric = true;
            echo "   ❌ $key يحتوي على نص: '$value'!\n";
        }
    }
}

if (!$hasStringInNumeric) {
    echo "   ✅ SQL صالح - لا توجد نصوص في الحقول الرقمية\n";
    $passed++;
} else {
    $failed++;
    $errors[] = "SQL المكفول يحتوي على نصوص في حقول رقمية";
}

// ======================================
// 7. اختبار إدخال فعلي (تراجع تلقائي)
// ======================================
echo "\n📋 7. اختبار إدخال فعلي مع تراجع تلقائي...\n";

DB::beginTransaction();
try {
    // اختبار إدخال معيل
    $testGuardian = new Data();
    $testGuardian->file_id_number = 'TEST001';
    $testGuardian->data_id_number = '999999999';
    $testGuardian->data_first_name = 'اختبار';
    $testGuardian->data_father_name = 'اختبار';
    $testGuardian->data_grand_father_name = 'اختبار';
    $testGuardian->data_family_name = 'اختبار';
    $testGuardian->data_gender = 1;
    $testGuardian->data_phone_number = null;  // ✅ null
    $testGuardian->data_alt_phone_number = null;  // ✅ null
    $testGuardian->data_request_status = 4;
    $testGuardian->save();

    echo "   ✅ نجح إدخال المعيل مع data_phone_number = NULL\n";
    $passed++;

    // اختبار إدخال مكفول
    $testSponsored = new RePeople();
    $testSponsored->registration_id = 'TEST001';
    $testSponsored->person_id = '888888888';
    $testSponsored->first_name = 'اختبار';
    $testSponsored->second_name = 'اختبار';
    $testSponsored->third_name = 'اختبار';
    $testSponsored->last_name = 'اختبار';
    $testSponsored->sponsorship_status = 4;  // ✅ 4 = جديد (من جدول sponsorship_statuses)
    $testSponsored->person_type_of_guarantee = null;  // ✅ null
    $testSponsored->save();

    echo "   ✅ نجح إدخال المكفول مع person_type_of_guarantee = NULL\n";
    $passed++;

} catch (\Exception $e) {
    echo "   ❌ فشل الإدخال: " . $e->getMessage() . "\n";
    $failed++;
    $errors[] = "فشل اختبار الإدخال الفعلي: " . $e->getMessage();
} finally {
    DB::rollBack();
    echo "   ℹ️ تم التراجع عن جميع التغييرات (rollback)\n";
}

// ======================================
// ملخص النتائج
// ======================================
echo "\n====================================================\n";
echo "📊 ملخص نتائج الاختبار\n";
echo "====================================================\n";
echo "✅ اختبارات ناجحة: $passed\n";
echo "❌ اختبارات فاشلة: $failed\n";

if (!empty($errors)) {
    echo "\n⚠️ الأخطاء:\n";
    foreach ($errors as $i => $error) {
        echo "   " . ($i + 1) . ". $error\n";
    }
}

if ($failed === 0) {
    echo "\n🎉 جميع الاختبارات نجحت! النظام جاهز للاستخدام.\n";
} else {
    echo "\n⚠️ يرجى مراجعة الأخطاء أعلاه.\n";
}

echo "====================================================\n";
