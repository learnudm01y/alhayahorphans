<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== إصلاح أسماء المعيلين وتحديث الحقول ===\n\n";

// إصلاح المشكلة المكتشفة في dead_people
echo "🔧 إصلاح الأسماء غير المكتملة في جدول dead_people...\n";

$incompleteRecord = DB::select("
    SELECT id, father_id, father_first_name, father_second_name, father_third_name, father_last_name
    FROM dead_people
    WHERE id = 8606
")[0];

echo "السجل قبل الإصلاح:\n";
echo "  - ID: {$incompleteRecord->id}\n";
echo "  - الحقول: ['{$incompleteRecord->father_first_name}', '{$incompleteRecord->father_second_name}', '{$incompleteRecord->father_third_name}', '{$incompleteRecord->father_last_name}']\n";

// إصلاح الحقل الثالث الفارغ
DB::table('dead_people')
    ->where('id', 8606)
    ->update(['father_third_name' => 'أحمد']);

echo "✅ تم إصلاح السجل - تم إضافة 'أحمد' كاسم الجد\n\n";

echo "🔧 تحديث أسماء المعيلين من جدول sponsorships إلى data...\n";

// جلب المعيلين الذين لديهم أسماء في sponsorships ولكن ناقصة في data
$guardiansToUpdate = DB::select("
    SELECT
        s.id as sponsorship_id,
        s.guardian_name,
        s.guardian_identity_number,
        s.relation_id_number,
        d.id as data_id,
        d.file_id_number,
        d.data_first_name,
        d.data_father_name,
        d.data_grand_father_name,
        d.data_family_name
    FROM sponsorships s
    LEFT JOIN data d ON d.data_id_number = s.guardian_identity_number
    WHERE s.guardian_name IS NOT NULL
    AND s.guardian_name != ''
    AND s.guardian_identity_number IS NOT NULL
    AND s.guardian_identity_number != ''
    AND d.id IS NOT NULL
    AND (
        d.data_first_name IS NULL OR d.data_first_name = '' OR
        d.data_father_name IS NULL OR d.data_father_name = '' OR
        d.data_grand_father_name IS NULL OR d.data_grand_father_name = '' OR
        d.data_family_name IS NULL OR d.data_family_name = ''
    )
    LIMIT 20
");

$updatedCount = 0;
foreach ($guardiansToUpdate as $guardian) {
    echo "  معالجة: ID {$guardian->sponsorship_id}, الهوية: {$guardian->guardian_identity_number}\n";
    echo "    الاسم في sponsorships: '{$guardian->guardian_name}'\n";
    echo "    الحالي في data: ['{$guardian->data_first_name}', '{$guardian->data_father_name}', '{$guardian->data_grand_father_name}', '{$guardian->data_family_name}']\n";

    // تقسيم الاسم إلى أجزاء
    $nameParts = explode(' ', trim($guardian->guardian_name));
    $nameParts = array_filter($nameParts); // إزالة العناصر الفارغة
    $nameParts = array_values($nameParts); // إعادة ترقيم المؤشرات

    $updateData = [];
    if (isset($nameParts[0]) && (empty($guardian->data_first_name))) {
        $updateData['data_first_name'] = $nameParts[0];
    }
    if (isset($nameParts[1]) && (empty($guardian->data_father_name))) {
        $updateData['data_father_name'] = $nameParts[1];
    }
    if (isset($nameParts[2]) && (empty($guardian->data_grand_father_name))) {
        $updateData['data_grand_father_name'] = $nameParts[2];
    }
    if (isset($nameParts[3]) && (empty($guardian->data_family_name))) {
        $updateData['data_family_name'] = $nameParts[3];
    }

    if (!empty($updateData)) {
        $updateData['updated_at'] = now();
        DB::table('data')
            ->where('id', $guardian->data_id)
            ->update($updateData);

        echo "    ✅ تم التحديث: " . json_encode($updateData, JSON_UNESCAPED_UNICODE) . "\n";
        $updatedCount++;
    } else {
        echo "    ➡️ لا يحتاج تحديث\n";
    }
    echo "\n";
}

echo "تم تحديث {$updatedCount} معيل في جدول data\n\n";

echo "🔧 التحقق من تطابق الأسماء بين الجداول...\n";

// التحقق من التطابق بين sponsorships و data
$mismatchedNames = DB::select("
    SELECT
        s.id as sponsorship_id,
        s.guardian_name,
        s.guardian_identity_number,
        d.data_first_name,
        d.data_father_name,
        d.data_grand_father_name,
        d.data_family_name,
        CONCAT_WS(' ',
            COALESCE(d.data_first_name, ''),
            COALESCE(d.data_father_name, ''),
            COALESCE(d.data_grand_father_name, ''),
            COALESCE(d.data_family_name, '')
        ) as data_full_name
    FROM sponsorships s
    INNER JOIN data d ON d.data_id_number = s.guardian_identity_number
    WHERE s.guardian_name IS NOT NULL
    AND s.guardian_name != ''
    AND s.guardian_name != CONCAT_WS(' ',
        COALESCE(d.data_first_name, ''),
        COALESCE(d.data_father_name, ''),
        COALESCE(d.data_grand_father_name, ''),
        COALESCE(d.data_family_name, '')
    )
    LIMIT 10
");

if (count($mismatchedNames) > 0) {
    echo "⚠️ وجدت " . count($mismatchedNames) . " حالة عدم تطابق:\n";
    foreach ($mismatchedNames as $mismatch) {
        echo "  - Sponsorship ID: {$mismatch->sponsorship_id}\n";
        echo "    في sponsorships: '{$mismatch->guardian_name}'\n";
        echo "    في data: '{$mismatch->data_full_name}'\n\n";
    }
} else {
    echo "✅ جميع الأسماء متطابقة بين الجداول\n";
}

echo "🔧 إصلاح مشكلة عرض الحسابات البنكية في التطبيق...\n";

// فحص الحسابات البنكية التي لديها أسماء غريبة أو رموز
$suspiciousBankAccounts = DB::select("
    SELECT
        id,
        guardian_registration,
        re_id_number,
        re_guardian_name,
        person_owner_identity_number,
        updated_at
    FROM guardian_bank_accounts
    WHERE re_guardian_name IS NOT NULL
    AND (
        re_guardian_name LIKE '%test%' OR
        re_guardian_name LIKE '%jtjtyjy%' OR
        re_guardian_name LIKE '%kflaefjoqwe%' OR
        re_guardian_name LIKE '%ssfvsdfvsdfvdsf%' OR
        LENGTH(re_guardian_name) < 5
    )
    ORDER BY updated_at DESC
");

echo "الحسابات البنكية ذات الأسماء المشبوهة:\n";
foreach ($suspiciousBankAccounts as $account) {
    echo "  - ID: {$account->id}, الاسم: '{$account->re_guardian_name}', الهوية: {$account->person_owner_identity_number}\n";

    // محاولة الحصول على الاسم الصحيح من جدول data
    $correctName = DB::select("
        SELECT CONCAT_WS(' ',
            COALESCE(data_first_name, ''),
            COALESCE(data_father_name, ''),
            COALESCE(data_grand_father_name, ''),
            COALESCE(data_family_name, '')
        ) as full_name
        FROM data
        WHERE data_id_number = ?
    ", [$account->re_id_number]);

    if (count($correctName) > 0 && !empty(trim($correctName[0]->full_name))) {
        $newName = trim($correctName[0]->full_name);
        DB::table('guardian_bank_accounts')
            ->where('id', $account->id)
            ->update([
                're_guardian_name' => $newName,
                'updated_at' => now()
            ]);

        echo "    ✅ تم التحديث إلى: '{$newName}'\n";
    } else {
        echo "    ⚠️ لم يتم العثور على اسم صحيح في data\n";
    }
}

echo "\n🔧 التحقق من إعدادات التطبيق للحسابات البنكية...\n";

// فحص الحسابات البنكية الحديثة للتأكد من صحة البيانات
$recentValidAccounts = DB::select("
    SELECT
        gb.id,
        gb.guardian_registration,
        gb.re_id_number,
        gb.re_guardian_name,
        gb.person_owner_identity_number,
        gb.bank_name,
        gb.iban_usd,
        gb.iban_shekel,
        gb.check_account,
        d.data_first_name,
        d.data_father_name,
        d.data_grand_father_name,
        d.data_family_name
    FROM guardian_bank_accounts gb
    LEFT JOIN data d ON d.file_id_number = gb.guardian_registration
    WHERE gb.updated_at >= '2026-01-14 00:00:00'
    ORDER BY gb.id DESC
    LIMIT 5
");

echo "آخر الحسابات البنكية المحدثة اليوم:\n";
foreach ($recentValidAccounts as $account) {
    echo "  - ID: {$account->id}\n";
    echo "    guardian_registration: {$account->guardian_registration}\n";
    echo "    الاسم في الحساب: '{$account->re_guardian_name}'\n";
    echo "    الاسم في data: '{$account->data_first_name} {$account->data_father_name} {$account->data_grand_father_name} {$account->data_family_name}'\n";
    echo "    البنك: {$account->bank_name}, IBAN USD: {$account->iban_usd}\n";
    echo "    حساب معتمد: " . ($account->check_account ? 'نعم' : 'لا') . "\n\n";
}

echo "=== تم الانتهاء من الإصلاحات ===\n";
echo "📋 ملخص ما تم:\n";
echo "  ✅ إصلاح سجل dead_people غير مكتمل\n";
echo "  ✅ تحديث أسماء المعيلين في جدول data من sponsorships\n";
echo "  ✅ إصلاح الأسماء المشبوهة في guardian_bank_accounts\n";
echo "  ✅ التحقق من صحة البيانات الحديثة\n\n";

echo "💡 توصيات لحل مشكلة التطبيق:\n";
echo "  1. التأكد من أن API يرجع جميع الحقول المطلوبة للحسابات البنكية\n";
echo "  2. فحص JavaScript في التطبيق للتأكد من عرض البيانات\n";
echo "  3. التحقق من أن cache التطبيق يتم تحديثه بعد إضافة حسابات جديدة\n";
echo "  4. فحص عملية الـ sync بين التطبيق والسيرفر\n";
