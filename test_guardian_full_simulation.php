<?php
/**
 * اختبار كامل لعملية حفظ بيانات المعيل من التطبيق
 * هذا الاختبار يحاكي بالضبط ما يحدث عند إرسال بيانات من الهاتف
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "===========================================\n";
echo "🧪 اختبار كامل لعملية حفظ المعيل\n";
echo "===========================================\n\n";

// محاكاة البيانات القادمة من التطبيق
$updates = [
    'guardian_identity_number' => '888777666' . rand(10, 99), // رقم هوية فريد
    'guardian_first_name' => 'محمد',
    'guardian_father_name' => 'أحمد',
    'guardian_grandfather_name' => 'علي',
    'guardian_family_name' => 'الخالد',
    'guardian_phone' => '0591234567',
    'guardian_phone2' => '0599876543',
    'guardian_detailed_address' => 'غزة - حي الرمال - شارع الجلاء',
];

// جلب كفالة للاختبار
$sponsorship = DB::table('sponsorships')->first();
$sponsorshipId = $sponsorship->id;

echo "📋 بيانات الاختبار:\n";
echo str_repeat("-", 50) . "\n";
echo "   - رقم الكفالة: {$sponsorshipId}\n";
echo "   - رقم هوية المعيل: {$updates['guardian_identity_number']}\n";
echo "   - الاسم: {$updates['guardian_first_name']} {$updates['guardian_father_name']} {$updates['guardian_grandfather_name']} {$updates['guardian_family_name']}\n";
echo "   - الهاتف: {$updates['guardian_phone']}\n";
echo "   - الهاتف 2: {$updates['guardian_phone2']}\n";
echo "   - العنوان: {$updates['guardian_detailed_address']}\n\n";

// ===== تنفيذ نفس الكود الموجود في Controller =====

$hasGuardianUpdate = isset($updates['guardian_identity_number']) ||
                     isset($updates['guardian_phone']) || isset($updates['guardian_phone2']) ||
                     isset($updates['guardian_detailed_address']) ||
                     isset($updates['guardian_first_name']) || isset($updates['guardian_father_name']) ||
                     isset($updates['guardian_grandfather_name']) || isset($updates['guardian_family_name']);

echo "📋 1. فحص وجود بيانات معيل:\n";
echo str_repeat("-", 50) . "\n";
echo "   - hasGuardianUpdate: " . ($hasGuardianUpdate ? "نعم" : "لا") . "\n\n";

if ($hasGuardianUpdate) {
    $guardianIdentity = $updates['guardian_identity_number'] ?? $sponsorship->guardian_identity_number ?? null;

    echo "📋 2. البحث عن المعيل برقم الهوية:\n";
    echo str_repeat("-", 50) . "\n";
    echo "   - رقم الهوية: {$guardianIdentity}\n";

    // البحث عن المعيل في جدول data برقم الهوية
    $existingGuardian = null;
    if (!empty($guardianIdentity)) {
        $existingGuardian = DB::table('data')
            ->where('data_id_number', $guardianIdentity)
            ->first();
    }

    echo "   - النتيجة: " . ($existingGuardian ? "موجود (ID: {$existingGuardian->id})" : "غير موجود") . "\n\n";

    if ($existingGuardian) {
        // ===== المعيل موجود مسبقاً =====
        echo "📋 3. المعيل موجود - سيتم الربط والتحديث:\n";
        echo str_repeat("-", 50) . "\n";
        echo "   - guardian_id: {$existingGuardian->id}\n";
        echo "   - file_id_number: {$existingGuardian->file_id_number}\n";

        // لا نقوم بتحديث فعلي في هذا الاختبار
        echo "   ⚠️ (لن نقوم بتحديث فعلي - هذا اختبار فقط)\n\n";

    } else {
        // ===== المعيل غير موجود - إنشاء سجل جديد =====
        echo "📋 3. المعيل غير موجود - سيتم إنشاء سجل جديد:\n";
        echo str_repeat("-", 50) . "\n";

        // توليد رقم ملف جديد
        $newFileIdNumber = generateFileIdFromDataTable();
        echo "   - رقم الملف الجديد: {$newFileIdNumber}\n";

        // إنشاء سجل جديد في جدول data
        $insertData = [
            'file_id_number' => $newFileIdNumber,
            'data_id_number' => $guardianIdentity ?: null,
            'data_first_name' => $updates['guardian_first_name'] ?? '',
            'data_father_name' => $updates['guardian_father_name'] ?? '',
            'data_grand_father_name' => $updates['guardian_grandfather_name'] ?? '',
            'data_family_name' => $updates['guardian_family_name'] ?? '',
            'data_phone_number' => $updates['guardian_phone'] ?? null,
            'data_alt_phone_number' => $updates['guardian_phone2'] ?? null,
            'data_current_address' => $updates['guardian_detailed_address'] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ];

        echo "\n📋 البيانات التي ستُدرج:\n";
        foreach ($insertData as $key => $value) {
            if ($key !== 'created_at' && $key !== 'updated_at') {
                echo "   - {$key}: {$value}\n";
            }
        }

        try {
            // الإدراج الفعلي
            $newGuardianId = DB::table('data')->insertGetId($insertData);

            echo "\n✅ تم الإدراج بنجاح!\n";
            echo "   - ID الجديد: {$newGuardianId}\n";

            // التحقق من البيانات المدرجة
            $insertedRecord = DB::table('data')->where('id', $newGuardianId)->first();

            echo "\n📋 4. التحقق من البيانات المدرجة:\n";
            echo str_repeat("-", 50) . "\n";
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

            // اختبار الربط مع sponsorships (محاكاة فقط)
            echo "\n📋 5. محاكاة ربط الكفالة بالمعيل:\n";
            echo str_repeat("-", 50) . "\n";
            echo "   سيتم تنفيذ: UPDATE sponsorships SET relation_id_number = '{$newFileIdNumber}' WHERE id = {$sponsorshipId}\n";

            // تنظيف - حذف السجل الاختباري
            echo "\n📋 6. تنظيف - حذف السجل الاختباري:\n";
            echo str_repeat("-", 50) . "\n";

            DB::table('data')->where('id', $newGuardianId)->delete();

            $deleted = DB::table('data')->where('id', $newGuardianId)->first();
            echo "   " . ($deleted ? "❌ لم يتم الحذف!" : "✅ تم الحذف بنجاح") . "\n";

        } catch (\Exception $e) {
            echo "\n❌ خطأ في الإدراج:\n";
            echo "   - الرسالة: " . $e->getMessage() . "\n";
            echo "   - الملف: " . $e->getFile() . "\n";
            echo "   - السطر: " . $e->getLine() . "\n";
        }
    }
}

// ===== اختبار إضافي: التحقق من الكود في Controller =====
echo "\n📋 7. التحقق من الكود في SponsorshipSyncController:\n";
echo str_repeat("-", 50) . "\n";

$controllerContent = file_get_contents(__DIR__ . '/app/Http/Controllers/Api/SponsorshipSyncController.php');

// البحث عن الأعمدة الخاطئة
$badColumns = ['data_personal_name', 'data_city' => false]; // data_city قد يكون مقبولاً
$foundBadColumns = [];

if (strpos($controllerContent, 'data_personal_name') !== false) {
    $foundBadColumns[] = 'data_personal_name';
}

// البحث في منطقة الإدراج فقط
preg_match_all('/insertGetId\s*\(\s*\[\s*([^]]+)\]/s', $controllerContent, $matches);
foreach ($matches[1] as $insertBlock) {
    if (strpos($insertBlock, 'data_personal_name') !== false) {
        $foundBadColumns[] = 'data_personal_name (في insertGetId)';
    }
    if (strpos($insertBlock, 'data_city') !== false) {
        $foundBadColumns[] = 'data_city (في insertGetId)';
    }
}

if (empty($foundBadColumns)) {
    echo "✅ لا توجد أعمدة خاطئة في الكود\n";
} else {
    echo "❌ وُجدت أعمدة خاطئة:\n";
    foreach ($foundBadColumns as $col) {
        echo "   - {$col}\n";
    }
}

// التحقق من استخدام الخوارزمية الصحيحة
$usesCorrectAlgorithm = strpos($controllerContent, 'generateFileIdFromDataTable()') !== false;
echo "\n" . ($usesCorrectAlgorithm ? "✅" : "❌") . " استخدام generateFileIdFromDataTable(): " . ($usesCorrectAlgorithm ? "نعم" : "لا") . "\n";

echo "\n===========================================\n";
echo "✅ انتهى الاختبار\n";
echo "===========================================\n";
