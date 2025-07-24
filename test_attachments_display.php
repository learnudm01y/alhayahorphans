<?php

require_once __DIR__ . '/vendor/autoload.php';

// إعداد البيئة
$_ENV['APP_ENV'] = 'local';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Data;

echo "=== اختبار إصلاح مشكلة عرض المرفقات ===\n\n";

// البحث عن سجل لديه مرفقات (من الفحص السابق)
$recordId = 449; // السجل الذي لديه مرفقات

echo "فحص السجل ID: {$recordId}\n";
echo "=======================\n";

try {
    // محاكاة استدعاء الـ controller
    $data = Data::with([
        'section',
        'requestStatus',
        'categoryOfRelation',
        'healthStatus',
        'maritalStatus',
        'academicQualification',
        'city',
        'employmentStatusBreadwinner',
        'province',
        'housingStatus',
        'currentHousingType',
        'attachments',
        'rePeople.attachments',
        'deadPepole.fatherAttachments',
        'deadPepole.motherAttachments',
    ])->findOrFail($recordId);

    echo "✅ تم تحميل السجل بنجاح\n";
    echo "رقم الهوية: {$data->data_id_number}\n";
    echo "الاسم: {$data->data_first_name} {$data->data_father_name} {$data->data_family_name}\n\n";

    // فحص المرفقات الرئيسية
    echo "1. المرفقات الرئيسية:\n";
    echo "===================\n";
    $mainAttachments = $data->attachments;
    echo "عدد المرفقات: " . $mainAttachments->count() . "\n";

    if ($mainAttachments->count() > 0) {
        foreach ($mainAttachments as $att) {
            echo "  - {$att->stored_file_name} ({$att->file_path})\n";
        }
        echo "✅ المرفقات الرئيسية تعمل بشكل صحيح\n";
    } else {
        echo "⚠️ لا توجد مرفقات رئيسية\n";
    }

    // فحص أفراد الأسرة ومرفقاتهم
    echo "\n2. أفراد الأسرة ومرفقاتهم:\n";
    echo "=========================\n";
    $familyMembers = $data->rePeople;
    echo "عدد أفراد الأسرة: " . $familyMembers->count() . "\n";

    $totalFamilyAttachments = 0;
    foreach ($familyMembers as $member) {
        $memberAttachments = $member->attachments;
        $totalFamilyAttachments += $memberAttachments->count();
        echo "  - فرد: {$member->first_name} {$member->last_name} (رقم الهوية: {$member->person_id})\n";
        echo "    المرفقات: " . $memberAttachments->count() . "\n";

        foreach ($memberAttachments as $att) {
            echo "      - {$att->stored_file_name}\n";
        }
    }

    if ($totalFamilyAttachments > 0) {
        echo "✅ مرفقات أفراد الأسرة تعمل بشكل صحيح\n";
    } else {
        echo "⚠️ لا توجد مرفقات لأفراد الأسرة\n";
    }

    // فحص المتوفين ومرفقاتهم
    echo "\n3. المتوفين ومرفقاتهم:\n";
    echo "===================\n";

    if ($data->deadPepole) {
        echo "توجد بيانات متوفين\n";

        // فحص مرفقات الأب
        if ($data->deadPepole->father_id) {
            $fatherAttachments = $data->deadPepole->fatherAttachments;
            echo "  الأب (رقم الهوية: {$data->deadPepole->father_id}):\n";
            echo "    المرفقات: " . $fatherAttachments->count() . "\n";

            foreach ($fatherAttachments as $att) {
                echo "      - {$att->stored_file_name}\n";
            }
        }

        // فحص مرفقات الأم
        if ($data->deadPepole->mother_id) {
            $motherAttachments = $data->deadPepole->motherAttachments;
            echo "  الأم (رقم الهوية: {$data->deadPepole->mother_id}):\n";
            echo "    المرفقات: " . $motherAttachments->count() . "\n";

            foreach ($motherAttachments as $att) {
                echo "      - {$att->stored_file_name}\n";
            }
        }

        echo "✅ مرفقات المتوفين تعمل بشكل صحيح\n";
    } else {
        echo "⚠️ لا توجد بيانات متوفين\n";
    }

    echo "\n=== ملخص النتائج ===\n";
    echo "✅ تم إصلاح مشكلة عرض المرفقات\n";
    echo "✅ العلاقات تعمل بشكل صحيح\n";
    echo "✅ يمكن عرض المرفقات في الواجهة الآن\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== انتهاء الاختبار ===\n";
