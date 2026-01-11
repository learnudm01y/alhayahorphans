<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== اختبار جلب البيانات البنكية ===\n\n";

// جلب كفالة تحتوي على relation_id_number
$sponsorship = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->first();

if ($sponsorship) {
    echo "الكفالة: " . $sponsorship->id . "\n";
    echo "relation_id_number: " . $sponsorship->relation_id_number . "\n\n";

    // جلب الحسابات البنكية
    $bankAccounts = DB::table('guardian_bank_accounts')
        ->leftJoin('bank_names', 'guardian_bank_accounts.bank_name', '=', 'bank_names.id')
        ->where('guardian_bank_accounts.guardian_registration', $sponsorship->relation_id_number)
        ->select([
            'guardian_bank_accounts.id',
            'guardian_bank_accounts.iban_usd',
            'guardian_bank_accounts.iban_shekel',
            'guardian_bank_accounts.re_guardian_name as account_holder_name',
            'guardian_bank_accounts.person_owner_identity_number as account_holder_identity',
            'guardian_bank_accounts.bank_name as bank_id',
            'bank_names.description as bank_name_text'
        ])
        ->get();

    echo "الحسابات البنكية: " . $bankAccounts->count() . "\n";
    foreach ($bankAccounts as $account) {
        echo "  - IBAN شيكل: " . ($account->iban_shekel ?? 'لا يوجد') . "\n";
        echo "  - IBAN دولار: " . ($account->iban_usd ?? 'لا يوجد') . "\n";
        echo "  - اسم صاحب الحساب: " . ($account->account_holder_name ?? 'لا يوجد') . "\n";
        echo "  - البنك: " . ($account->bank_name_text ?? 'لا يوجد') . "\n\n";
    }

    // جلب بيانات الاتصال
    echo "\n=== بيانات الاتصال ===\n";
    $guardianInfo = DB::table('data')
        ->where('file_id_number', $sponsorship->relation_id_number)
        ->select(['data_current_address', 'data_phone_number', 'data_alt_phone_number'])
        ->first();

    if ($guardianInfo) {
        echo "العنوان: " . ($guardianInfo->data_current_address ?? 'لا يوجد') . "\n";
        echo "الهاتف: " . ($guardianInfo->data_phone_number ?? 'لا يوجد') . "\n";
        echo "الهاتف البديل: " . ($guardianInfo->data_alt_phone_number ?? 'لا يوجد') . "\n";
    } else {
        echo "لم يتم العثور على بيانات اتصال\n";
    }
} else {
    echo "لم يتم العثور على كفالة مع relation_id_number\n";
}

echo "\n=== اختبار ناجح ===\n";
