<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== اختبار تحديث الأسماء من guardian_name ===\n\n";

// اختبار محاكاة التحديث من التطبيق
echo "🧪 محاكاة تحديث من التطبيق...\n";

// إنشاء كفالة تجريبية
$testSponsorshipId = 99999;
$testIdentity = '999888777';
$testFileId = '999999';
$fullGuardianName = 'عبدالله محمد أحمد الخالدي';

// حذف البيانات التجريبية إذا كانت موجودة
DB::table('sponsorships')->where('id', $testSponsorshipId)->delete();
DB::table('data')->where('file_id_number', $testFileId)->delete();
DB::table('dead_people')->where('re_file_id', $testFileId)->delete();

try {
    // إنشاء كفالة تجريبية
    DB::table('sponsorships')->insert([
        'id' => $testSponsorshipId,
        'guardian_name' => $fullGuardianName,
        'guardian_identity_number' => $testIdentity,
        'relation_id_number' => $testFileId,
        'person_type' => 'family_member',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // إنشاء سجل data تجريبي
    DB::table('data')->insert([
        'file_id_number' => $testFileId,
        'data_id_number' => $testIdentity,
        'data_first_name' => 'اسم_قديم',
        'data_father_name' => 'اب_قديم',
        'data_grand_father_name' => 'جد_قديم',
        'data_family_name' => 'عائلة_قديمة',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    echo "✅ تم إنشاء البيانات التجريبية\n";
    echo "الاسم الكامل في sponsorships: '{$fullGuardianName}'\n";
    echo "الأسماء القديمة في data: اسم_قديم اب_قديم جد_قديم عائلة_قديمة\n\n";

    // محاكاة التحديث - هذا ما يحدث عندما يرسل التطبيق guardian_name
    $updates = [
        'guardian_name' => $fullGuardianName,
        'guardian_phone' => '0599123456'
    ];

    echo "📡 محاكاة إرسال التحديث من التطبيق:\n";
    echo "guardian_name: '{$updates['guardian_name']}'\n";
    echo "guardian_phone: '{$updates['guardian_phone']}'\n\n";

    // تطبيق منطق التحديث الجديد
    $dataUpdates = [];

    // تطبيق المنطق المُحدث من SponsorshipSyncController
    if (isset($updates['guardian_name']) && !empty(trim($updates['guardian_name']))) {
        $fullName = trim($updates['guardian_name']);
        $nameParts = explode(' ', $fullName);
        $nameParts = array_filter($nameParts); // إزالة العناصر الفارغة
        $nameParts = array_values($nameParts); // إعادة ترقيم المؤشرات

        // حفظ الأجزاء الأربعة دائماً (حتى لو كانت موجودة من قبل)
        $dataUpdates['data_first_name'] = $nameParts[0] ?? '';
        $dataUpdates['data_father_name'] = $nameParts[1] ?? '';
        $dataUpdates['data_grand_father_name'] = $nameParts[2] ?? '';
        $dataUpdates['data_family_name'] = $nameParts[3] ?? '';

        echo "🔧 تم تقسيم الاسم إلى:\n";
        echo "  data_first_name: '{$dataUpdates['data_first_name']}'\n";
        echo "  data_father_name: '{$dataUpdates['data_father_name']}'\n";
        echo "  data_grand_father_name: '{$dataUpdates['data_grand_father_name']}'\n";
        echo "  data_family_name: '{$dataUpdates['data_family_name']}'\n\n";
    }

    // إضافة بقية التحديثات
    if (isset($updates['guardian_phone'])) $dataUpdates['data_phone_number'] = $updates['guardian_phone'];
    $dataUpdates['updated_at'] = now();

    // تطبيق التحديث على data
    $affectedRows = DB::table('data')
        ->where('file_id_number', $testFileId)
        ->update($dataUpdates);

    echo "💾 تم تطبيق التحديث على جدول data\n";
    echo "عدد الصفوف المُحدثة: {$affectedRows}\n\n";

    // التحقق من النتيجة
    $updatedRecord = DB::table('data')->where('file_id_number', $testFileId)->first();

    echo "📋 البيانات بعد التحديث:\n";
    echo "  data_first_name: '{$updatedRecord->data_first_name}'\n";
    echo "  data_father_name: '{$updatedRecord->data_father_name}'\n";
    echo "  data_grand_father_name: '{$updatedRecord->data_grand_father_name}'\n";
    echo "  data_family_name: '{$updatedRecord->data_family_name}'\n";
    echo "  data_phone_number: '{$updatedRecord->data_phone_number}'\n";

    $reconstructedName = trim(implode(' ', [
        $updatedRecord->data_first_name ?? '',
        $updatedRecord->data_father_name ?? '',
        $updatedRecord->data_grand_father_name ?? '',
        $updatedRecord->data_family_name ?? ''
    ]));

    echo "\n🔍 الاسم المُعاد تجميعه: '{$reconstructedName}'\n";
    echo "الاسم الأصلي: '{$fullGuardianName}'\n";

    if ($reconstructedName === $fullGuardianName) {
        echo "✅ تطابق مثالي! تم حفظ الاسم بنجاح في 4 حقول\n\n";
    } else {
        echo "❌ عدم تطابق!\n\n";
    }

    // اختبار المتوفين
    echo "🪦 اختبار حفظ اسم متوفي...\n";

    $deceasedName = 'خالد سعد إبراهيم المناعي';

    // إنشاء سجل متوفي تجريبي
    DB::table('dead_people')->insert([
        're_file_id' => $testFileId,
        'father_id' => $testIdentity,
        'father_first_name' => 'قديم',
        'father_second_name' => 'قديم',
        'father_third_name' => 'قديم',
        'father_last_name' => 'قديم',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // تطبيق منطق تحديث المتوفين
    $deceasedUpdates = [
        'guardian_name' => $deceasedName
    ];

    $deadUpdateData = [];

    if (isset($deceasedUpdates['guardian_name']) && !empty(trim($deceasedUpdates['guardian_name']))) {
        $fullName = trim($deceasedUpdates['guardian_name']);
        $nameParts = explode(' ', $fullName);
        $nameParts = array_filter($nameParts);
        $nameParts = array_values($nameParts);

        // حفظ الأجزاء الأربعة دائماً
        $deadUpdateData['father_first_name'] = $nameParts[0] ?? '';
        $deadUpdateData['father_second_name'] = $nameParts[1] ?? '';
        $deadUpdateData['father_third_name'] = $nameParts[2] ?? '';
        $deadUpdateData['father_last_name'] = $nameParts[3] ?? '';

        echo "🔧 تم تقسيم اسم المتوفي إلى:\n";
        echo "  father_first_name: '{$deadUpdateData['father_first_name']}'\n";
        echo "  father_second_name: '{$deadUpdateData['father_second_name']}'\n";
        echo "  father_third_name: '{$deadUpdateData['father_third_name']}'\n";
        echo "  father_last_name: '{$deadUpdateData['father_last_name']}'\n\n";
    }

    $deadUpdateData['updated_at'] = now();

    // تطبيق التحديث على dead_people
    DB::table('dead_people')
        ->where('re_file_id', $testFileId)
        ->where('father_id', $testIdentity)
        ->update($deadUpdateData);

    // التحقق من النتيجة
    $updatedDeadRecord = DB::table('dead_people')
        ->where('re_file_id', $testFileId)
        ->where('father_id', $testIdentity)
        ->first();

    $reconstructedDeadName = trim(implode(' ', [
        $updatedDeadRecord->father_first_name ?? '',
        $updatedDeadRecord->father_second_name ?? '',
        $updatedDeadRecord->father_third_name ?? '',
        $updatedDeadRecord->father_last_name ?? ''
    ]));

    echo "📋 اسم المتوفي بعد التحديث: '{$reconstructedDeadName}'\n";
    echo "الاسم الأصلي: '{$deceasedName}'\n";

    if ($reconstructedDeadName === $deceasedName) {
        echo "✅ تطابق مثالي! تم حفظ اسم المتوفي بنجاح في 4 حقول\n\n";
    } else {
        echo "❌ عدم تطابق!\n\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n\n";
}

// تنظيف البيانات التجريبية
DB::table('sponsorships')->where('id', $testSponsorshipId)->delete();
DB::table('data')->where('file_id_number', $testFileId)->delete();
DB::table('dead_people')->where('re_file_id', $testFileId)->delete();

echo "🧹 تم حذف البيانات التجريبية\n";
echo "=== انتهى الاختبار ===\n";
?>
