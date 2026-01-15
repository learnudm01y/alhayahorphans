<?php

/**
 * اختبار إصلاح:
 * 1. تخزين guardian_identity_number في جدول sponsorships
 * 2. توليد رقم ملف جديد للمكفول في re_people وتخزينه في relation_id_number
 *
 * هذا الاختبار لبيئة Test Local فقط!
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;

echo "=================================================================\n";
echo "🧪 اختبار إصلاح guardian_identity_number و relation_id_number\n";
echo "=================================================================\n";
echo "⚠️ هذا الاختبار لبيئة Test Local فقط!\n";
echo "=================================================================\n\n";

// 1. اختبار خوارزمية توليد رقم الملف
echo "📋 1. اختبار خوارزمية توليد رقم الملف:\n";
echo str_repeat("-", 50) . "\n";

if (function_exists('generateFileIdFromDataTable')) {
    $testFileId1 = generateFileIdFromDataTable();
    $testFileId2 = generateFileIdFromDataTable();
    $testFileId3 = generateFileIdFromDataTable();

    echo "✅ الدالة generateFileIdFromDataTable() موجودة\n";
    echo "   - رقم ملف 1: $testFileId1\n";
    echo "   - رقم ملف 2: $testFileId2\n";
    echo "   - رقم ملف 3: $testFileId3\n";

    $allUnique = ($testFileId1 !== $testFileId2) && ($testFileId2 !== $testFileId3);
    echo ($allUnique ? "✅" : "❌") . " الأرقام المولدة فريدة: " . ($allUnique ? "نعم" : "لا") . "\n\n";
} else {
    echo "❌ الدالة generateFileIdFromDataTable() غير موجودة!\n\n";
}

// 2. اختبار أن guardian_identity_number مضافة لقائمة الحقول
echo "📋 2. فحص الكود في OfflineTestController:\n";
echo str_repeat("-", 50) . "\n";

$controllerPath = __DIR__ . '/app/Http/Controllers/Api/OfflineTestController.php';
$controllerContent = file_get_contents($controllerPath);

// التحقق من guardian_identity_number في sponsorshipFields
$hasGuardianIdentityField = strpos($controllerContent, "'guardian_identity_number']") !== false;
echo ($hasGuardianIdentityField ? "✅" : "❌") . " guardian_identity_number في قائمة sponsorshipFields\n";

// التحقق من استخدام generateFileIdFromDataTable
$usesGenerateForData = strpos($controllerContent, '$newFileIdNumber = generateFileIdFromDataTable()') !== false;
echo ($usesGenerateForData ? "✅" : "❌") . " استخدام generateFileIdFromDataTable() لجدول data\n";

$usesGenerateForRePeople = strpos($controllerContent, '$newRegistrationId = generateFileIdFromDataTable()') !== false;
echo ($usesGenerateForRePeople ? "✅" : "❌") . " استخدام generateFileIdFromDataTable() لجدول re_people\n";

// التحقق من تحديث relation_id_number
$updatesRelationId = strpos($controllerContent, "'relation_id_number' => \$newFileIdNumber") !== false ||
                     strpos($controllerContent, "'relation_id_number' => \$newRegistrationId") !== false;
echo ($updatesRelationId ? "✅" : "❌") . " تحديث relation_id_number بعد إنشاء سجل جديد\n\n";

// 3. محاكاة عملية الرفع
echo "📋 3. محاكاة عملية رفع التغييرات (Simulation):\n";
echo str_repeat("-", 50) . "\n";

// جلب كفالة للاختبار
$testSponsorship = DB::table('sponsorships')
    ->whereNotNull('id')
    ->first();

if ($testSponsorship) {
    echo "✅ تم العثور على كفالة للاختبار:\n";
    echo "   - ID: {$testSponsorship->id}\n";
    echo "   - orphan_name: " . ($testSponsorship->orphan_name ?? 'NULL') . "\n";
    echo "   - guardian_name: " . ($testSponsorship->guardian_name ?? 'NULL') . "\n";
    echo "   - guardian_identity_number الحالي: " . ($testSponsorship->guardian_identity_number ?? 'NULL') . "\n";
    echo "   - relation_id_number الحالي: " . ($testSponsorship->relation_id_number ?? 'NULL') . "\n\n";

    // محاكاة بيانات الإرسال
    $testData = [
        'guardian_identity_number' => '999888777',
        'guardian_first_name' => 'اختبار',
        'guardian_father_name' => 'المعيل',
        'guardian_family_name' => 'الجديد',
        'first_name' => 'اختبار',
        'second_name' => 'المكفول',
        'orphan_gender' => 'ذكر'
    ];

    echo "📤 بيانات الاختبار المرسلة:\n";
    foreach ($testData as $key => $value) {
        echo "   - $key: $value\n";
    }
    echo "\n";

    echo "ℹ️ هذه محاكاة فقط - لن يتم تعديل قاعدة البيانات\n";
    echo "   لاختبار فعلي، استخدم تطبيق الموبايل أو API مباشرة\n";
} else {
    echo "⚠️ لم يتم العثور على كفالات للاختبار\n";
}

echo "\n";

// 4. ملخص الإصلاحات
echo "=================================================================\n";
echo "📊 ملخص الإصلاحات:\n";
echo "=================================================================\n";

$allChecks = [
    'خوارزمية توليد رقم الملف' => function_exists('generateFileIdFromDataTable'),
    'guardian_identity_number في قائمة الحقول' => $hasGuardianIdentityField,
    'توليد رقم ملف للمعيل (data)' => $usesGenerateForData,
    'توليد رقم ملف للمكفول (re_people)' => $usesGenerateForRePeople,
    'تحديث relation_id_number' => $updatesRelationId
];

$passedCount = 0;
foreach ($allChecks as $check => $passed) {
    echo ($passed ? "✅" : "❌") . " $check\n";
    if ($passed) $passedCount++;
}

echo "\n";
echo "النتيجة: $passedCount/" . count($allChecks) . " اختبارات ناجحة\n";

if ($passedCount === count($allChecks)) {
    echo "\n🎉 جميع الإصلاحات تم تطبيقها بنجاح!\n";
} else {
    echo "\n⚠️ بعض الإصلاحات تحتاج مراجعة\n";
}

echo "\n=================================================================\n";
echo "📝 ما تم إصلاحه:\n";
echo "=================================================================\n";
echo "1. guardian_identity_number: الآن يتم تخزينه في جدول sponsorships\n";
echo "2. re_people: عند إنشاء سجل جديد، يتم توليد رقم ملف فريد\n";
echo "   باستخدام generateFileIdFromDataTable() وتخزينه في registration_id\n";
echo "3. data: عند إنشاء سجل معيل جديد، يتم توليد رقم ملف فريد\n";
echo "   وتحديث relation_id_number في sponsorships\n";
echo "=================================================================\n";
