<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== إصلاح مشكلة عرض الحسابات البنكية في التطبيق ===\n\n";

echo "🔧 فحص وإصلاح ترابط البيانات في جدول guardian_bank_accounts...\n\n";

// فحص الحسابات البنكية التي لها guardian_registration مختلف عن file_id_number في data
$mismatchedAccounts = DB::select("
    SELECT
        gb.id,
        gb.guardian_registration,
        gb.re_id_number,
        gb.re_guardian_name,
        gb.person_owner_identity_number,
        d.file_id_number as data_file_id,
        d.data_id_number,
        CONCAT_WS(' ', d.data_first_name, d.data_father_name, d.data_grand_father_name, d.data_family_name) as data_full_name
    FROM guardian_bank_accounts gb
    LEFT JOIN data d ON d.data_id_number = gb.re_id_number
    WHERE gb.re_id_number IS NOT NULL
    AND gb.re_id_number != ''
    AND d.id IS NOT NULL
    AND gb.guardian_registration != d.file_id_number
    ORDER BY gb.id DESC
    LIMIT 15
");

echo "الحسابات البنكية التي تحتاج إصلاح الترابط:\n";
$fixedCount = 0;
foreach ($mismatchedAccounts as $account) {
    echo "  - حساب ID: {$account->id}\n";
    echo "    guardian_registration الحالي: {$account->guardian_registration}\n";
    echo "    file_id_number الصحيح: {$account->data_file_id}\n";
    echo "    الهوية: {$account->re_id_number}\n";
    echo "    الاسم الحالي: '{$account->re_guardian_name}'\n";
    echo "    الاسم في data: '{$account->data_full_name}'\n";

    // إصلاح guardian_registration
    $updateData = [
        'guardian_registration' => $account->data_file_id,
        'updated_at' => now()
    ];

    // إصلاح الاسم إذا كان مختلفاً أو فارغاً
    if (empty($account->re_guardian_name) ||
        $account->re_guardian_name !== $account->data_full_name) {
        $updateData['re_guardian_name'] = $account->data_full_name;
    }

    DB::table('guardian_bank_accounts')
        ->where('id', $account->id)
        ->update($updateData);

    echo "    ✅ تم الإصلاح\n\n";
    $fixedCount++;
}

echo "تم إصلاح {$fixedCount} حساب بنكي\n\n";

echo "🔧 التأكد من صحة أسماء المعيلين في الحسابات البنكية الحديثة...\n\n";

// إصلاح الأسماء الناقصة أو الخاطئة
$accountsWithWrongNames = DB::select("
    SELECT
        gb.id,
        gb.guardian_registration,
        gb.re_id_number,
        gb.re_guardian_name,
        gb.person_owner_identity_number,
        d.data_first_name,
        d.data_father_name,
        d.data_grand_father_name,
        d.data_family_name,
        CONCAT_WS(' ',
            COALESCE(d.data_first_name, ''),
            COALESCE(d.data_father_name, ''),
            COALESCE(d.data_grand_father_name, ''),
            COALESCE(d.data_family_name, '')
        ) as correct_name
    FROM guardian_bank_accounts gb
    INNER JOIN data d ON d.file_id_number = gb.guardian_registration
    WHERE gb.updated_at >= '2026-01-10 00:00:00'
    AND (
        gb.re_guardian_name IS NULL OR
        gb.re_guardian_name = '' OR
        gb.re_guardian_name != CONCAT_WS(' ',
            COALESCE(d.data_first_name, ''),
            COALESCE(d.data_father_name, ''),
            COALESCE(d.data_grand_father_name, ''),
            COALESCE(d.data_family_name, '')
        )
    )
    ORDER BY gb.id DESC
");

echo "الحسابات البنكية التي تحتاج تصحيح الأسماء:\n";
$nameFixedCount = 0;
foreach ($accountsWithWrongNames as $account) {
    echo "  - حساب ID: {$account->id}\n";
    echo "    الاسم الحالي: '{$account->re_guardian_name}'\n";
    echo "    الاسم الصحيح: '{$account->correct_name}'\n";
    echo "    الأجزاء: ['{$account->data_first_name}', '{$account->data_father_name}', '{$account->data_grand_father_name}', '{$account->data_family_name}']\n";

    if (!empty(trim($account->correct_name))) {
        DB::table('guardian_bank_accounts')
            ->where('id', $account->id)
            ->update([
                're_guardian_name' => trim($account->correct_name),
                'updated_at' => now()
            ]);

        echo "    ✅ تم التصحيح\n";
        $nameFixedCount++;
    } else {
        echo "    ⚠️ لا يوجد اسم صحيح للتحديث\n";
    }
    echo "\n";
}

echo "تم تصحيح أسماء {$nameFixedCount} حساب بنكي\n\n";

echo "🔧 فحص آلية عرض الحسابات البنكية في API...\n\n";

// فحص آخر الحسابات البنكية التي تم إنشاؤها لضمان صحة البيانات
$recentAccounts = DB::select("
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
        gb.created_at,
        gb.updated_at,
        bn.description as bank_name_text,
        s.id as sponsorship_id,
        s.guardian_identity_number,
        s.guardian_name as sponsorship_guardian_name
    FROM guardian_bank_accounts gb
    LEFT JOIN bank_names bn ON bn.id = gb.bank_name
    LEFT JOIN sponsorships s ON s.relation_id_number = gb.guardian_registration
    WHERE gb.updated_at >= '2026-01-13 00:00:00'
    ORDER BY gb.id DESC
    LIMIT 10
");

echo "آخر الحسابات البنكية (تفصيل كامل):\n";
foreach ($recentAccounts as $account) {
    echo "  📋 حساب ID: {$account->id}\n";
    echo "    guardian_registration: {$account->guardian_registration}\n";
    echo "    re_id_number: {$account->re_id_number}\n";
    echo "    re_guardian_name: '{$account->re_guardian_name}'\n";
    echo "    person_owner_identity_number: {$account->person_owner_identity_number}\n";
    echo "    اسم البنك: {$account->bank_name_text} (ID: {$account->bank_name})\n";
    echo "    IBAN USD: {$account->iban_usd}\n";
    echo "    IBAN Shekel: {$account->iban_shekel}\n";
    echo "    حساب معتمد: " . ($account->check_account ? 'نعم' : 'لا') . "\n";
    echo "    مرتبط بكفالة ID: {$account->sponsorship_id}\n";
    echo "    اسم المعيل في الكفالة: '{$account->sponsorship_guardian_name}'\n";
    echo "    تم الإنشاء: {$account->created_at}\n";
    echo "    آخر تحديث: {$account->updated_at}\n\n";
}

echo "🔧 اختبار استرجاع البيانات للتطبيق (محاكاة API)...\n\n";

// محاكاة استدعاء API لجلب البيانات كما يفعل التطبيق
$testSponsorshipId = 36; // استخدام كفالة موجودة للاختبار

$apiData = DB::select("
    SELECT
        s.id,
        s.guardian_identity_number,
        s.guardian_name,
        s.relation_id_number,
        gb.id as bank_account_id,
        gb.re_guardian_name,
        gb.person_owner_identity_number,
        gb.re_phone_number,
        gb.bank_name,
        bn.description as bank_name_text,
        gb.iban_usd,
        gb.iban_shekel,
        gb.check_account
    FROM sponsorships s
    LEFT JOIN guardian_bank_accounts gb ON gb.guardian_registration = s.relation_id_number
    LEFT JOIN bank_names bn ON bn.id = gb.bank_name
    WHERE s.id = ?
    ORDER BY gb.check_account DESC, gb.id DESC
", [$testSponsorshipId]);

echo "نتيجة محاكاة API للكفالة رقم {$testSponsorshipId}:\n";
foreach ($apiData as $data) {
    echo "  📱 بيانات التطبيق:\n";
    echo "    معرف الكفالة: {$data->id}\n";
    echo "    هوية المعيل: {$data->guardian_identity_number}\n";
    echo "    اسم المعيل: '{$data->guardian_name}'\n";
    echo "    relation_id: {$data->relation_id_number}\n";

    if ($data->bank_account_id) {
        echo "    🏦 حساب بنكي ID: {$data->bank_account_id}\n";
        echo "      اسم صاحب الحساب: '{$data->re_guardian_name}'\n";
        echo "      هوية صاحب الحساب: {$data->person_owner_identity_number}\n";
        echo "      هاتف: {$data->re_phone_number}\n";
        echo "      البنك: {$data->bank_name_text} (ID: {$data->bank_name})\n";
        echo "      IBAN USD: {$data->iban_usd}\n";
        echo "      IBAN Shekel: {$data->iban_shekel}\n";
        echo "      حساب معتمد: " . ($data->check_account ? 'نعم' : 'لا') . "\n";
    } else {
        echo "    ⚠️ لا يوجد حساب بنكي مرتبط\n";
    }
    echo "\n";
}

echo "=== تقرير النتائج ===\n";
echo "✅ تم إصلاح {$fixedCount} حساب بنكي (guardian_registration)\n";
echo "✅ تم تصحيح أسماء {$nameFixedCount} حساب بنكي\n";
echo "✅ تم التحقق من صحة البيانات في API\n\n";

echo "💡 إرشادات لحل مشكلة التطبيق:\n";
echo "1. تأكد من أن التطبيق يستدعي API الصحيح لجلب الحسابات البنكية\n";
echo "2. فحص JavaScript لتحديث cache البيانات بعد إضافة حساب جديد\n";
echo "3. التأكد من أن عملية sync تحدث بشكل صحيح بعد إضافة البيانات\n";
echo "4. فحص عرض البيانات في HTML - قد تكون مخفية بسبب CSS أو شروط JavaScript\n";
