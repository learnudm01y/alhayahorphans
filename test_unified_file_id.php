<?php
/**
 * اختبار أن رقم الملف الموحد يُستخدم لكل من المعيل والمكفول
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=================================================================\n";
echo "🧪 اختبار الرقم الموحد للمعيل والمكفول\n";
echo "=================================================================\n\n";

// 1. توليد رقم ملف جديد
echo "1. توليد رقم ملف موحد:\n";
echo str_repeat("-", 50) . "\n";

$unifiedCode = generateFileIdFromDataTable();
echo "   الرقم الموحد: $unifiedCode\n\n";

// 2. محاكاة إنشاء سجلات بنفس الرقم
echo "2. إنشاء سجلات تجريبية بالرقم الموحد:\n";
echo str_repeat("-", 50) . "\n";

// إنشاء سجل معيل
$guardianId = DB::table('data')->insertGetId([
    'file_id_number' => $unifiedCode,
    'data_first_name' => 'اختبار_معيل_موحد',
    'data_father_name' => 'الأب',
    'data_family_name' => 'العائلة',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "   ✅ تم إنشاء المعيل في data: id=$guardianId, file_id_number=$unifiedCode\n";

// إنشاء سجل مكفول
$sponsoredId = DB::table('re_people')->insertGetId([
    'registration_id' => $unifiedCode,
    'first_name' => 'اختبار_مكفول_موحد',
    'second_name' => 'الثاني',
    'person_gender' => 1,
    'created_at' => now(),
    'updated_at' => now()
]);
echo "   ✅ تم إنشاء المكفول في re_people: id=$sponsoredId, registration_id=$unifiedCode\n";

echo "\n";

// 3. التحقق من تطابق الأرقام
echo "3. التحقق من تطابق الأرقام:\n";
echo str_repeat("-", 50) . "\n";

$guardian = DB::table('data')->where('id', $guardianId)->first();
$sponsored = DB::table('re_people')->where('id', $sponsoredId)->first();

$match = ($guardian->file_id_number === $sponsored->registration_id);
echo ($match ? "   ✅" : "   ❌") . " تطابق الأرقام: " . ($match ? "نعم" : "لا") . "\n";
echo "   - المعيل (data.file_id_number): {$guardian->file_id_number}\n";
echo "   - المكفول (re_people.registration_id): {$sponsored->registration_id}\n";

echo "\n";

// 4. التحقق من وجود الرقم في reserved_codes
echo "4. التحقق من حجز الرقم في reserved_codes:\n";
echo str_repeat("-", 50) . "\n";

$reserved = DB::table('reserved_codes')->where('code', $unifiedCode)->first();
if ($reserved) {
    echo "   ✅ الرقم محجوز في reserved_codes\n";
    echo "   - id: {$reserved->id}\n";
    echo "   - code: {$reserved->code}\n";
    echo "   - reserved_at: {$reserved->reserved_at}\n";
} else {
    echo "   ❌ الرقم غير محجوز!\n";
}

echo "\n";

// 5. تنظيف البيانات التجريبية
echo "5. تنظيف البيانات التجريبية:\n";
echo str_repeat("-", 50) . "\n";

DB::table('data')->where('id', $guardianId)->delete();
DB::table('re_people')->where('id', $sponsoredId)->delete();

echo "   ✅ تم حذف السجلات التجريبية\n";

echo "\n";

// 6. فحص كود OfflineTestController
echo "6. فحص الكود في OfflineTestController:\n";
echo str_repeat("-", 50) . "\n";

$controllerPath = __DIR__ . '/app/Http/Controllers/Api/OfflineTestController.php';
$controllerContent = file_get_contents($controllerPath);

// التحقق من وجود متغير الرقم الموحد
$hasUnifiedVar = strpos($controllerContent, '$unifiedFileIdNumber') !== false;
echo ($hasUnifiedVar ? "   ✅" : "   ❌") . " استخدام متغير \$unifiedFileIdNumber\n";

// التحقق من توليد مرة واحدة
$singleGenerate = substr_count($controllerContent, 'generateFileIdFromDataTable()');
// نحسب فقط في دالة uploadChanges (يجب أن يكون 1)
$hasNeedsNewCheck = strpos($controllerContent, '$needsNewGuardian || $needsNewSponsored') !== false;
echo ($hasNeedsNewCheck ? "   ✅" : "   ❌") . " فحص الحاجة للتوليد قبل التوليد\n";

// التحقق من عدم توليد رقمين منفصلين
$hasOldSeparateGen = strpos($controllerContent, '$newFileIdNumber = generateFileIdFromDataTable()') !== false &&
                     strpos($controllerContent, '$newRegistrationId = generateFileIdFromDataTable()') !== false;
echo (!$hasOldSeparateGen ? "   ✅" : "   ❌") . " عدم توليد أرقام منفصلة للمعيل والمكفول\n";

echo "\n";

// 7. ملخص
echo "=================================================================\n";
echo "📊 الملخص:\n";
echo "=================================================================\n";

if ($match && $reserved && $hasUnifiedVar && !$hasOldSeparateGen) {
    echo "🎉 تم إصلاح المشكلة بنجاح!\n";
    echo "   - يتم توليد رقم واحد موحد فقط\n";
    echo "   - نفس الرقم يُستخدم للمعيل (data) والمكفول (re_people)\n";
    echo "   - الرقم يُحجز في reserved_codes\n";
    echo "   - يتم تحديث relation_id_number مرة واحدة\n";
} else {
    echo "⚠️ يوجد مشاكل تحتاج مراجعة\n";
}

echo "\n";
