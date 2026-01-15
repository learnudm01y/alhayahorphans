<?php
/**
 * اختبار منطق حفظ بيانات المعيل في جدول data فقط
 * مع تنفيذ فعلي للإدراج والحذف
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "===========================================\n";
echo "🧪 اختبار منطق حفظ بيانات المعيل الجديد\n";
echo "===========================================\n\n";

// ======================================
// 1. التحقق من هيكل جدول data
// ======================================
echo "📋 1. التحقق من أعمدة جدول data:\n";
echo str_repeat("-", 50) . "\n";

$dataColumns = DB::select("SHOW COLUMNS FROM data");
$requiredColumns = [
    'file_id_number',
    'data_id_number',
    'data_first_name',
    'data_father_name',
    'data_grand_father_name',
    'data_family_name',
    'data_phone_number',
    'data_alt_phone_number',
    'data_current_address'
];

$existingColumns = array_column($dataColumns, 'Field');
$allColumnsExist = true;

foreach ($requiredColumns as $col) {
    $exists = in_array($col, $existingColumns);
    echo ($exists ? "✅" : "❌") . " {$col}: " . ($exists ? "موجود" : "غير موجود!") . "\n";
    if (!$exists) $allColumnsExist = false;
}

if (!$allColumnsExist) {
    echo "\n❌ بعض الأعمدة المطلوبة غير موجودة! لا يمكن المتابعة.\n";
    exit(1);
}

// ======================================
// 2. اختبار توليد رقم ملف جديد
// ======================================
echo "\n📋 2. اختبار توليد رقم ملف جديد (generateFileIdFromDataTable):\n";
echo str_repeat("-", 50) . "\n";

// جلب أعلى رقم ملف حالي
$maxFileIdBefore = DB::table('data')
    ->whereNotNull('file_id_number')
    ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
    ->orderByRaw('CAST(file_id_number as UNSIGNED) DESC')
    ->value('file_id_number');

echo "   - أعلى رقم ملف قبل التوليد: {$maxFileIdBefore}\n";

// توليد رقم جديد باستخدام الخوارزمية الصحيحة
$newFileId = generateFileIdFromDataTable();
echo "   - رقم الملف الجديد المولد: {$newFileId}\n";

// التحقق من صحة الرقم
$expectedNextId = str_pad((int)$maxFileIdBefore + 1, 6, '0', STR_PAD_LEFT);
if ($newFileId === $expectedNextId) {
    echo "✅ الخوارزمية تعمل بشكل صحيح!\n";
} else {
    echo "⚠️ الرقم المولد ({$newFileId}) مختلف عن المتوقع ({$expectedNextId})\n";
}

// ======================================
// 3. اختبار إدراج معيل جديد فعلياً
// ======================================
echo "\n📋 3. اختبار إدراج معيل جديد في جدول data:\n";
echo str_repeat("-", 50) . "\n";

// توليد رقم هوية فريد للاختبار
$testIdentityNumber = '999' . rand(100000, 999999);

// توليد رقم ملف جديد للمعيل
$testFileIdNumber = generateFileIdFromDataTable();

$testGuardianData = [
    'file_id_number' => $testFileIdNumber,
    'data_id_number' => $testIdentityNumber,
    'data_first_name' => 'اختبار_أحمد',
    'data_father_name' => 'اختبار_محمد',
    'data_grand_father_name' => 'اختبار_علي',
    'data_family_name' => 'اختبار_الأحمد',
    'data_phone_number' => '0599999999',
    'data_alt_phone_number' => '0588888888',
    'data_current_address' => 'غزة - الرمال - اختبار',
    'created_at' => now(),
    'updated_at' => now()
];

echo "بيانات المعيل للإدراج:\n";
foreach ($testGuardianData as $key => $value) {
    if ($key !== 'created_at' && $key !== 'updated_at') {
        echo "   - {$key}: {$value}\n";
    }
}

try {
    // إدراج السجل
    $newGuardianId = DB::table('data')->insertGetId($testGuardianData);
    echo "\n✅ تم إدراج المعيل بنجاح! ID: {$newGuardianId}\n";

    // التحقق من وجود السجل
    $insertedRecord = DB::table('data')->where('id', $newGuardianId)->first();

    if ($insertedRecord) {
        echo "\n📋 البيانات المدرجة فعلياً:\n";
        echo "   - id: {$insertedRecord->id}\n";
        echo "   - file_id_number: {$insertedRecord->file_id_number}\n";
        echo "   - data_id_number: {$insertedRecord->data_id_number}\n";
        echo "   - data_first_name: {$insertedRecord->data_first_name}\n";
        echo "   - data_father_name: {$insertedRecord->data_father_name}\n";
        echo "   - data_grand_father_name: {$insertedRecord->data_grand_father_name}\n";
        echo "   - data_family_name: {$insertedRecord->data_family_name}\n";
        echo "   - data_phone_number: {$insertedRecord->data_phone_number}\n";
        echo "   - data_alt_phone_number: {$insertedRecord->data_alt_phone_number}\n";
        echo "   - data_current_address: {$insertedRecord->data_current_address}\n";

        // التحقق من صحة البيانات
        $allCorrect = true;
        if ($insertedRecord->file_id_number != $testFileIdNumber) {
            echo "❌ file_id_number غير صحيح!\n";
            $allCorrect = false;
        }
        if ($insertedRecord->data_id_number != $testIdentityNumber) {
            echo "❌ data_id_number غير صحيح!\n";
            $allCorrect = false;
        }
        if ($insertedRecord->data_first_name != 'اختبار_أحمد') {
            echo "❌ data_first_name غير صحيح!\n";
            $allCorrect = false;
        }

        if ($allCorrect) {
            echo "\n✅ جميع البيانات تم إدراجها بشكل صحيح!\n";
        }
    }

    // ======================================
    // 4. اختبار البحث عن المعيل المدرج
    // ======================================
    echo "\n📋 4. اختبار البحث عن المعيل برقم الهوية:\n";
    echo str_repeat("-", 50) . "\n";

    $foundRecord = DB::table('data')
        ->where('data_id_number', $testIdentityNumber)
        ->first();

    if ($foundRecord) {
        echo "✅ تم إيجاد المعيل برقم الهوية: {$testIdentityNumber}\n";
        echo "   - file_id_number: {$foundRecord->file_id_number}\n";
    } else {
        echo "❌ لم يتم العثور على المعيل!\n";
    }

    // ======================================
    // 5. اختبار الربط مع sponsorships
    // ======================================
    echo "\n📋 5. اختبار الربط مع جدول sponsorships:\n";
    echo str_repeat("-", 50) . "\n";

    // جلب كفالة للاختبار
    $testSponsorship = DB::table('sponsorships')->first();

    if ($testSponsorship) {
        echo "كفالة للاختبار: ID={$testSponsorship->id}\n";
        echo "   - relation_id_number الحالي: " . ($testSponsorship->relation_id_number ?? 'NULL') . "\n";

        // محاكاة الربط (بدون تنفيذ فعلي على كفالة حقيقية)
        echo "\n✅ يمكن ربط الكفالة بالمعيل عبر:\n";
        echo "   UPDATE sponsorships SET relation_id_number = '{$testFileIdNumber}' WHERE id = {$testSponsorship->id}\n";
    }

    // ======================================
    // 6. تنظيف - حذف السجل الاختباري
    // ======================================
    echo "\n📋 6. تنظيف - حذف السجل الاختباري:\n";
    echo str_repeat("-", 50) . "\n";

    DB::table('data')->where('id', $newGuardianId)->delete();

    // التحقق من الحذف
    $deletedRecord = DB::table('data')->where('id', $newGuardianId)->first();
    if (!$deletedRecord) {
        echo "✅ تم حذف السجل الاختباري بنجاح\n";
    } else {
        echo "⚠️ لم يتم حذف السجل الاختباري!\n";
    }

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// ======================================
// 7. التحقق من الكود في Controller
// ======================================
echo "\n📋 7. التحقق من استخدام الخوارزمية الصحيحة في Controller:\n";
echo str_repeat("-", 50) . "\n";

$controllerPath = __DIR__ . '/app/Http/Controllers/Api/SponsorshipSyncController.php';
$controllerContent = file_get_contents($controllerPath);

$checks = [
    'استخدام generateFileIdFromDataTable()' => 'generateFileIdFromDataTable()',
    'البحث بـ data_id_number' => "where('data_id_number', \$guardianIdentity)",
    'إدراج في data' => "DB::table('data')->insertGetId",
    'ربط الكفالة' => "'relation_id_number' => \$newFileIdNumber",
];

foreach ($checks as $name => $pattern) {
    $found = strpos($controllerContent, $pattern) !== false;
    echo ($found ? "✅" : "❌") . " {$name}\n";
}

echo "\n===========================================\n";
echo "✅ انتهى الاختبار\n";
echo "===========================================\n";
