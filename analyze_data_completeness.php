<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// البحث عن الكفالة برقم الملف 006062 أو رقم الجوال 598646190
$sponsorships = DB::table('sponsorships')
    ->where('internal_file_number', '006062')
    ->orWhere('identity_number', '598646190')
    ->get();

echo "=== فحص اكتمال البيانات ===\n\n";

foreach ($sponsorships as $sponsorship) {
    echo "رقم الملف: {$sponsorship->internal_file_number}\n";
    echo "اسم اليتيم: {$sponsorship->orphan_name}\n";
    echo "الوالد: {$sponsorship->guardian_name}\n";
    echo "معرف الكفالة: {$sponsorship->id}\n\n";

    // إحصاء البيانات
    $totalFields = 0;
    $emptyFields = 0;
    $filledFields = 0;
    $errors = [];

    // ==== بيانات اليتيم ====
    echo "--- بيانات اليتيم ---\n";

    $orphanId = $sponsorship->identity_number;
    $orphan = DB::table('persons')->where('CI_ID_NUM', $orphanId)->first();

    // بناء الاسم الكامل
    $orphanFullName = null;
    if ($orphan) {
        $orphanFullName = trim(
            ($orphan->CI_FIRST_ARB ?? '') . ' ' .
            ($orphan->CI_FATHER_ARB ?? '') . ' ' .
            ($orphan->CI_GRAND_FATHER_ARB ?? '') . ' ' .
            ($orphan->CI_FAMILY_ARB ?? '')
        );
    }

    // الحقول الأساسية
    $orphanFields = [
        'اسم اليتيم' => $orphanFullName ?: $sponsorship->orphan_name,
        'رقم الهوية' => $orphanId,
        'المدينة' => $orphan->CITY ?? null,
    ];

    foreach ($orphanFields as $name => $value) {
        $totalFields++;
        if (empty($value)) {
            $emptyFields++;
            $errors[] = "بيانات اليتيم: {$name} فارغ";
            echo "❌ {$name}: فارغ\n";
        } else {
            $filledFields++;
            echo "✅ {$name}: {$value}\n";
        }
    }

    // البحث عن البيانات التعليمية والصحية من portal
    $portalFields = DB::table('portal_general_registration_field_values')
        ->where('sponsorship_id', $sponsorship->id)
        ->get()
        ->keyBy('field_label');

    $educationalFields = [
        'عنوان المدرسة',
        'المرحلة الدراسية',
        'مستوى الطالب',
        'سبب الضعف',
        'مقدار حفظه للقرآن',
        'الالتزام الديني',
        'الحالة السلوكية',
        'الحالة النفسية',
        'جوانب الإبداع',
        'احتياجات اليتيم'
    ];

    echo "\n--- البيانات التعليمية والاجتماعية ---\n";
    foreach ($educationalFields as $field) {
        $totalFields++;
        $value = $portalFields->get($field)->field_value ?? null;
        if (empty($value) || $value == '(/)') {
            $emptyFields++;
            $errors[] = "بيانات تعليمية: {$field} فارغ";
            echo "❌ {$field}: فارغ\n";
        } else {
            $filledFields++;
            echo "✅ {$field}: {$value}\n";
        }
    }

    // الحالة الصحية لليتيم من portal
    $totalFields++;
    $orphanHealthValue = $portalFields->get('الحالة الصحية لليتيم')->field_value ?? null;
    if ($orphanHealthValue && $orphanHealthValue != '(/)') {
        $filledFields++;
        echo "✅ الحالة الصحية لليتيم: {$orphanHealthValue}\n";
    } else {
        $emptyFields++;
        $errors[] = "الحالة الصحية لليتيم: فارغ";
        echo "❌ الحالة الصحية لليتيم: فارغ\n";
    }

    // ==== بيانات المعيل ====
    echo "\n--- بيانات المعيل ---\n";

    $guardianId = $sponsorship->guardian_identity_number;

    // علاقة القرابة
    $totalFields++;
    $guardianRelation = null;

    // محاولة الحصول على علاقة القرابة من portal fields
    $guardianRelationValue = $portalFields->get('صلة القرابة بالمكفول')->field_value ??
                           $portalFields->get('صلة القرابة')->field_value ??
                           null;

    if ($guardianRelationValue && $guardianRelationValue != '(/)') {
        $guardianRelation = $guardianRelationValue;
    }

    if ($guardianRelation) {
        $filledFields++;
        echo "✅ صلة القرابة: {$guardianRelation}\n";
    } else {
        $emptyFields++;
        $errors[] = "بيانات المعيل: صلة القرابة فارغة";
        echo "❌ صلة القرابة: فارغ\n";
    }

    // بيانات المعيل من جدول data
    $guardianData = DB::table('data')
        ->where('data_id_number', $guardianId)
        ->first();

    if ($guardianData) {
        // الحالة الصحية
        $totalFields++;
        $guardianHealth = DB::table('health_statuses')
            ->where('id', $guardianData->data_health_status)
            ->value('description');

        if ($guardianHealth) {
            $filledFields++;
            echo "✅ الحالة الصحية للمعيل: {$guardianHealth}\n";
        } else {
            $filledFields++;
            echo "✅ الحالة الصحية للمعيل: سليم (افتراضي)\n";
        }

        // الوظيفة
        $totalFields++;
        $guardianJob = DB::table('employment')
            ->where('id', $guardianData->data_employment_status_breadwinner)
            ->value('description');

        if ($guardianJob) {
            $filledFields++;
            echo "✅ وظيفة المعيل: {$guardianJob}\n";
        } else {
            $emptyFields++;
            $errors[] = "بيانات المعيل: الوظيفة فارغة";
            echo "❌ وظيفة المعيل: فارغ\n";
        }

        // عدد الأفراد المعالين
        $totalFields++;
        $dependents = $guardianData->data_number_of_individuals
            ?? ($guardianData->data_number_female + $guardianData->data_number_mail);

        if ($dependents > 0) {
            $filledFields++;
            echo "✅ عدد من يعيلهم: {$dependents}\n";
        } else {
            $emptyFields++;
            $errors[] = "بيانات المعيل: عدد المعالين فارغ";
            echo "❌ عدد من يعيلهم: فارغ\n";
        }
    } else {
        echo "⚠️ لا توجد بيانات للمعيل في جدول data\n";
        $totalFields += 3;
        $emptyFields += 3;
    }

    // ==== بيانات أفراد العائلة من portal ====
    echo "\n--- بيانات أفراد العائلة ---\n";

    // البحث عن بيانات أفراد العائلة من portal fields
    $familyData = [];

    // نمط: اسم فرد العائلة 1, اسم فرد العائلة 2, ...
    $familyNamePattern = '/اسم فرد العائلة (\d+)/';
    $healthPattern = '/الحالة الصحية لفرد العائلة (\d+)/';
    $eduPattern = '/المستوى التعليمي لفرد العائلة (\d+)/';

    foreach ($portalFields as $field) {
        if (preg_match($familyNamePattern, $field->field_label, $matches)) {
            $index = $matches[1];
            if (!isset($familyData[$index])) {
                $familyData[$index] = [];
            }
            $familyData[$index]['name'] = $field->field_value;
        }
        if (preg_match($healthPattern, $field->field_label, $matches)) {
            $index = $matches[1];
            if (!isset($familyData[$index])) {
                $familyData[$index] = [];
            }
            $familyData[$index]['health'] = $field->field_value;
        }
        if (preg_match($eduPattern, $field->field_label, $matches)) {
            $index = $matches[1];
            if (!isset($familyData[$index])) {
                $familyData[$index] = [];
            }
            $familyData[$index]['education'] = $field->field_value;
        }
    }

    echo "عدد أفراد العائلة: " . count($familyData) . "\n\n";

    foreach ($familyData as $index => $member) {
        $name = $member['name'] ?? 'غير محدد';
        echo "العضو {$index}: {$name}\n";

        // الحالة الصحية
        $totalFields++;
        $health = $member['health'] ?? null;
        if ($health && $health != '(/)') {
            $filledFields++;
            echo "  ✅ الحالة الصحية: {$health}\n";
        } else {
            $emptyFields++;
            $errors[] = "فرد العائلة {$name}: الحالة الصحية فارغة";
            echo "  ❌ الحالة الصحية: فارغ\n";
        }

        // المستوى التعليمي
        $totalFields++;
        $education = $member['education'] ?? null;
        if ($education && $education != '(/)') {
            $filledFields++;
            echo "  ✅ المستوى التعليمي: {$education}\n";
        } else {
            $emptyFields++;
            $errors[] = "فرد العائلة {$name}: المستوى التعليمي فارغ";
            echo "  ❌ المستوى التعليمي: فارغ\n";
        }

        echo "\n";
    }

    // ==== الإحصائيات النهائية ====
    echo "\n========================================\n";
    echo "📊 إحصائيات اكتمال البيانات\n";
    echo "========================================\n\n";
    echo "إجمالي الحقول المفحوصة: {$totalFields}\n";
    echo "✅ الحقول الممتلئة: {$filledFields}\n";
    echo "❌ الحقول الفارغة: {$emptyFields}\n\n";

    $completionRate = ($filledFields / $totalFields) * 100;
    $errorRate = ($emptyFields / $totalFields) * 100;

    echo "📈 معدل الاكتمال: " . number_format($completionRate, 1) . "%\n";
    echo "📉 معدل الأخطاء (البيانات المفقودة): " . number_format($errorRate, 1) . "%\n\n";

    if ($emptyFields > 0) {
        echo "========================================\n";
        echo "قائمة الأخطاء التفصيلية ({$emptyFields} خطأ):\n";
        echo "========================================\n";
        foreach ($errors as $i => $error) {
            echo ($i + 1) . ". {$error}\n";
        }
    }
}
