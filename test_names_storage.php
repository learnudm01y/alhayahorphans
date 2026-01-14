<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص دقيق لأعمدة الأسماء في الجداول ===\n\n";

echo "📋 جدول DATA - أعمدة الأسماء:\n";
$dataColumns = DB::select("SHOW COLUMNS FROM data");
foreach ($dataColumns as $col) {
    if (strpos(strtolower($col->Field), 'name') !== false) {
        echo "  ✓ {$col->Field} ({$col->Type}) - " . ($col->Null === 'YES' ? 'يقبل NULL' : 'لا يقبل NULL') . "\n";
    }
}

echo "\n📋 جدول DEAD_PEOPLE - أعمدة الأسماء:\n";
$deadColumns = DB::select("SHOW COLUMNS FROM dead_people");
foreach ($deadColumns as $col) {
    if (strpos(strtolower($col->Field), 'name') !== false) {
        echo "  ✓ {$col->Field} ({$col->Type}) - " . ($col->Null === 'YES' ? 'يقبل NULL' : 'لا يقبل NULL') . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🔍 اختبار حفظ اسم جديد في جدول DATA\n";
echo str_repeat("=", 60) . "\n";

// اختبار إنشاء سجل جديد مع اسم كامل
$testFileId = '999999'; // رقم تجريبي
$testIdentity = '999888777'; // هوية تجريبية
$fullName = 'محمد أحمد عبدالله الفلسطيني'; // اسم تجريبي
$nameParts = explode(' ', trim($fullName));
$nameParts = array_filter($nameParts);
$nameParts = array_values($nameParts);

echo "الاسم الكامل للاختبار: '{$fullName}'\n";
echo "أجزاء الاسم: [" . implode(', ', $nameParts) . "]\n";

// حذف السجل التجريبي إذا كان موجوداً
DB::table('data')->where('file_id_number', $testFileId)->delete();

// إنشاء سجل تجريبي جديد
try {
    $insertData = [
        'file_id_number' => $testFileId,
        'data_id_number' => $testIdentity,
        'data_first_name' => $nameParts[0] ?? '',
        'data_father_name' => $nameParts[1] ?? '',
        'data_grand_father_name' => $nameParts[2] ?? '',
        'data_family_name' => $nameParts[3] ?? '',
        'created_at' => now(),
        'updated_at' => now()
    ];

    echo "\nبيانات الإدراج:\n";
    foreach ($insertData as $key => $value) {
        if (strpos($key, 'name') !== false) {
            echo "  {$key}: '{$value}'\n";
        }
    }

    $insertId = DB::table('data')->insertGetId($insertData);
    echo "\n✅ تم إنشاء السجل بنجاح - ID: {$insertId}\n";

    // التحقق من الحفظ
    $savedRecord = DB::table('data')->where('file_id_number', $testFileId)->first();
    echo "\nالبيانات المحفوظة:\n";
    echo "  data_first_name: '{$savedRecord->data_first_name}'\n";
    echo "  data_father_name: '{$savedRecord->data_father_name}'\n";
    echo "  data_grand_father_name: '{$savedRecord->data_grand_father_name}'\n";
    echo "  data_family_name: '{$savedRecord->data_family_name}'\n";

    $reconstructedName = trim(implode(' ', [
        $savedRecord->data_first_name ?? '',
        $savedRecord->data_father_name ?? '',
        $savedRecord->data_grand_father_name ?? '',
        $savedRecord->data_family_name ?? ''
    ]));
    echo "  الاسم المُعاد تجميعه: '{$reconstructedName}'\n";

    if ($reconstructedName === $fullName) {
        echo "  ✅ تطابق مثالي!\n";
    } else {
        echo "  ⚠️ عدم تطابق!\n";
    }

    // حذف السجل التجريبي
    DB::table('data')->where('file_id_number', $testFileId)->delete();
    echo "\n🗑️ تم حذف السجل التجريبي\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ في إنشاء السجل: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🔍 اختبار حفظ اسم جديد في جدول DEAD_PEOPLE\n";
echo str_repeat("=", 60) . "\n";

// اختبار إنشاء سجل جديد في dead_people
$testDeadId = 99999; // رقم تجريبي
$fatherName = 'أحمد محمد عبدالله السعدي'; // اسم الأب التجريبي
$motherName = 'فاطمة خالد إبراهيم النجار'; // اسم الأم التجريبي

$fatherParts = explode(' ', trim($fatherName));
$fatherParts = array_filter($fatherParts);
$fatherParts = array_values($fatherParts);

$motherParts = explode(' ', trim($motherName));
$motherParts = array_filter($motherParts);
$motherParts = array_values($motherParts);

echo "اسم الأب الكامل: '{$fatherName}'\n";
echo "أجزاء اسم الأب: [" . implode(', ', $fatherParts) . "]\n";
echo "اسم الأم الكامل: '{$motherName}'\n";
echo "أجزاء اسم الأم: [" . implode(', ', $motherParts) . "]\n";

// حذف السجل التجريبي إذا كان موجوداً
DB::table('dead_people')->where('id', $testDeadId)->delete();

try {
    $insertDeadData = [
        'id' => $testDeadId,
        're_file_id' => $testFileId,
        'father_id' => $testIdentity . '1',
        'father_first_name' => $fatherParts[0] ?? '',
        'father_second_name' => $fatherParts[1] ?? '',
        'father_third_name' => $fatherParts[2] ?? '',
        'father_last_name' => $fatherParts[3] ?? '',
        'mother_id' => $testIdentity . '2',
        'mother_first_name' => $motherParts[0] ?? '',
        'mother_second_name' => $motherParts[1] ?? '',
        'mother_third_name' => $motherParts[2] ?? '',
        'mother_last_name' => $motherParts[3] ?? '',
        'created_at' => now(),
        'updated_at' => now()
    ];

    echo "\nبيانات إدراج الأب والأم:\n";
    foreach ($insertDeadData as $key => $value) {
        if (strpos($key, 'name') !== false) {
            echo "  {$key}: '{$value}'\n";
        }
    }

    DB::table('dead_people')->insert($insertDeadData);
    echo "\n✅ تم إنشاء سجل المتوفين بنجاح\n";

    // التحقق من الحفظ
    $savedDeadRecord = DB::table('dead_people')->where('id', $testDeadId)->first();
    echo "\nبيانات الأب المحفوظة:\n";
    echo "  father_first_name: '{$savedDeadRecord->father_first_name}'\n";
    echo "  father_second_name: '{$savedDeadRecord->father_second_name}'\n";
    echo "  father_third_name: '{$savedDeadRecord->father_third_name}'\n";
    echo "  father_last_name: '{$savedDeadRecord->father_last_name}'\n";

    $reconstructedFatherName = trim(implode(' ', [
        $savedDeadRecord->father_first_name ?? '',
        $savedDeadRecord->father_second_name ?? '',
        $savedDeadRecord->father_third_name ?? '',
        $savedDeadRecord->father_last_name ?? ''
    ]));
    echo "  اسم الأب المُعاد تجميعه: '{$reconstructedFatherName}'\n";

    echo "\nبيانات الأم المحفوظة:\n";
    echo "  mother_first_name: '{$savedDeadRecord->mother_first_name}'\n";
    echo "  mother_second_name: '{$savedDeadRecord->mother_second_name}'\n";
    echo "  mother_third_name: '{$savedDeadRecord->mother_third_name}'\n";
    echo "  mother_last_name: '{$savedDeadRecord->mother_last_name}'\n";

    $reconstructedMotherName = trim(implode(' ', [
        $savedDeadRecord->mother_first_name ?? '',
        $savedDeadRecord->mother_second_name ?? '',
        $savedDeadRecord->mother_third_name ?? '',
        $savedDeadRecord->mother_last_name ?? ''
    ]));
    echo "  اسم الأم المُعاد تجميعه: '{$reconstructedMotherName}'\n";

    // حذف السجل التجريبي
    DB::table('dead_people')->where('id', $testDeadId)->delete();
    echo "\n🗑️ تم حذف سجل المتوفين التجريبي\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ في إنشاء سجل المتوفين: " . $e->getMessage() . "\n";
}

echo "\n=== انتهى الاختبار ===\n";
?>
