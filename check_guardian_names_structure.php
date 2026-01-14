<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص وإصلاح أسماء المعيلين في قاعدة البيانات ===\n\n";

// فحص هيكل جدول data
echo "📋 فحص هيكل جدول data:\n";
$dataColumns = DB::select("SHOW COLUMNS FROM data WHERE Field LIKE '%name%'");
echo "أعمدة الأسماء في جدول data:\n";
foreach ($dataColumns as $col) {
    echo "  ✓ {$col->Field} ({$col->Type})\n";
}

// فحص هيكل جدول dead_people
echo "\n📋 فحص هيكل جدول dead_people:\n";
$deadColumns = DB::select("SHOW COLUMNS FROM dead_people WHERE Field LIKE '%name%'");
echo "أعمدة الأسماء في جدول dead_people:\n";
foreach ($deadColumns as $col) {
    echo "  ✓ {$col->Field} ({$col->Type})\n";
}

// فحص هيكل جدول guardian_bank_accounts
echo "\n📋 فحص هيكل جدول guardian_bank_accounts:\n";
$bankColumns = DB::select("SHOW COLUMNS FROM guardian_bank_accounts");
echo "جميع أعمدة جدول guardian_bank_accounts:\n";
foreach ($bankColumns as $col) {
    echo "  ✓ {$col->Field} ({$col->Type})\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "🔍 البحث عن أسماء معيلين غير مكتملة في جدول data\n";
echo str_repeat("=", 80) . "\n";

// البحث عن سجلات data بأسماء غير مكتملة
$incompleteDataNames = DB::select("
    SELECT
        id,
        file_id_number,
        data_id_number,
        data_first_name,
        data_father_name,
        data_grand_father_name,
        data_family_name,
        CONCAT_WS(' ',
            COALESCE(data_first_name, ''),
            COALESCE(data_father_name, ''),
            COALESCE(data_grand_father_name, ''),
            COALESCE(data_family_name, '')
        ) as full_name_current
    FROM data
    WHERE data_id_number IS NOT NULL
    AND data_id_number != ''
    AND (
        data_first_name IS NULL OR data_first_name = '' OR
        data_father_name IS NULL OR data_father_name = '' OR
        data_grand_father_name IS NULL OR data_grand_father_name = '' OR
        data_family_name IS NULL OR data_family_name = ''
    )
    ORDER BY id
    LIMIT 20
");

if (count($incompleteDataNames) > 0) {
    echo "⚠️ وجدت " . count($incompleteDataNames) . " سجل بأسماء غير مكتملة في data:\n";
    foreach ($incompleteDataNames as $record) {
        echo "  - ID: {$record->id}, File: {$record->file_id_number}, Identity: {$record->data_id_number}\n";
        echo "    الاسم الحالي: '{$record->full_name_current}'\n";
        echo "    الحقول: ['{$record->data_first_name}', '{$record->data_father_name}', '{$record->data_grand_father_name}', '{$record->data_family_name}']\n\n";
    }
} else {
    echo "✅ جميع أسماء المعيلين مكتملة في جدول data\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "🔍 البحث عن أسماء معيلين غير مكتملة في جدول dead_people\n";
echo str_repeat("=", 80) . "\n";

// البحث عن سجلات dead_people بأسماء غير مكتملة
$incompleteDeadNames = DB::select("
    SELECT
        id,
        re_file_id,
        father_id,
        father_first_name,
        father_second_name,
        father_third_name,
        father_last_name,
        mother_id,
        mother_first_name,
        mother_second_name,
        mother_third_name,
        mother_last_name,
        CONCAT_WS(' ',
            COALESCE(father_first_name, ''),
            COALESCE(father_second_name, ''),
            COALESCE(father_third_name, ''),
            COALESCE(father_last_name, '')
        ) as father_full_name_current,
        CONCAT_WS(' ',
            COALESCE(mother_first_name, ''),
            COALESCE(mother_second_name, ''),
            COALESCE(mother_third_name, ''),
            COALESCE(mother_last_name, '')
        ) as mother_full_name_current
    FROM dead_people
    WHERE (
        (father_id IS NOT NULL AND father_id != '' AND (
            father_first_name IS NULL OR father_first_name = '' OR
            father_second_name IS NULL OR father_second_name = '' OR
            father_third_name IS NULL OR father_third_name = '' OR
            father_last_name IS NULL OR father_last_name = ''
        )) OR
        (mother_id IS NOT NULL AND mother_id != '' AND (
            mother_first_name IS NULL OR mother_first_name = '' OR
            mother_second_name IS NULL OR mother_second_name = '' OR
            mother_third_name IS NULL OR mother_third_name = '' OR
            mother_last_name IS NULL OR mother_last_name = ''
        ))
    )
    ORDER BY id
    LIMIT 20
");

if (count($incompleteDeadNames) > 0) {
    echo "⚠️ وجدت " . count($incompleteDeadNames) . " سجل بأسماء غير مكتملة في dead_people:\n";
    foreach ($incompleteDeadNames as $record) {
        echo "  - ID: {$record->id}, File: {$record->re_file_id}\n";
        if (!empty($record->father_id)) {
            echo "    الأب (ID: {$record->father_id}): '{$record->father_full_name_current}'\n";
            echo "    الحقول: ['{$record->father_first_name}', '{$record->father_second_name}', '{$record->father_third_name}', '{$record->father_last_name}']\n";
        }
        if (!empty($record->mother_id)) {
            echo "    الأم (ID: {$record->mother_id}): '{$record->mother_full_name_current}'\n";
            echo "    الحقول: ['{$record->mother_first_name}', '{$record->mother_second_name}', '{$record->mother_third_name}', '{$record->mother_last_name}']\n";
        }
        echo "\n";
    }
} else {
    echo "✅ جميع أسماء المتوفين مكتملة في جدول dead_people\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "🔍 البحث عن أسماء معيلين في جدول sponsorships لمقارنتها\n";
echo str_repeat("=", 80) . "\n";

// البحث عن أسماء من sponsorships لمقارنتها مع الجداول الأخرى
$sponsorshipNames = DB::select("
    SELECT
        id,
        guardian_name,
        guardian_identity_number,
        relation_id_number,
        person_type
    FROM sponsorships
    WHERE guardian_name IS NOT NULL
    AND guardian_name != ''
    AND guardian_identity_number IS NOT NULL
    AND guardian_identity_number != ''
    ORDER BY id
    LIMIT 15
");

echo "عينة من أسماء المعيلين في جدول sponsorships:\n";
foreach ($sponsorshipNames as $record) {
    echo "  - Sponsorship ID: {$record->id}\n";
    echo "    الاسم: '{$record->guardian_name}'\n";
    echo "    الهوية: {$record->guardian_identity_number}\n";
    echo "    relation_id: {$record->relation_id_number}\n";
    echo "    النوع: {$record->person_type}\n";

    // البحث عن هذا المعيل في data
    if (!empty($record->guardian_identity_number)) {
        $dataMatch = DB::select("
            SELECT
                id,
                file_id_number,
                data_first_name,
                data_father_name,
                data_grand_father_name,
                data_family_name
            FROM data
            WHERE data_id_number = ?
        ", [$record->guardian_identity_number]);

        if (count($dataMatch) > 0) {
            $match = $dataMatch[0];
            echo "    في data: [{$match->data_first_name}, {$match->data_father_name}, {$match->data_grand_father_name}, {$match->data_family_name}]\n";
        } else {
            echo "    في data: غير موجود\n";
        }
    }

    echo "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "🏦 فحص مشكلة الحسابات البنكية\n";
echo str_repeat("=", 80) . "\n";

// فحص الحسابات البنكية الحديثة
$recentBankAccounts = DB::select("
    SELECT
        id,
        guardian_registration,
        re_id_number,
        re_guardian_name,
        person_owner_identity_number,
        re_phone_number,
        bank_name,
        iban_usd,
        iban_shekel,
        created_at,
        updated_at
    FROM guardian_bank_accounts
    ORDER BY id DESC
    LIMIT 10
");

echo "آخر 10 حسابات بنكية تم إنشاؤها:\n";
foreach ($recentBankAccounts as $account) {
    echo "  - ID: {$account->id}\n";
    echo "    guardian_registration: {$account->guardian_registration}\n";
    echo "    re_id_number: {$account->re_id_number}\n";
    echo "    re_guardian_name: '{$account->re_guardian_name}'\n";
    echo "    person_owner_identity_number: {$account->person_owner_identity_number}\n";
    echo "    تم الإنشاء: {$account->created_at}\n";
    echo "    تم التحديث: {$account->updated_at}\n\n";
}

echo "=== انتهى الفحص ===\n";
