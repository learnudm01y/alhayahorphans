<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== اختبار نظام الحسابات البنكية للمتوفين ===" . PHP_EOL . PHP_EOL;

// اختبار للشخص المتوفي
$sponsorship = DB::table('sponsorships')
    ->where('person_type', 'deceased_father')
    ->first();

if (!$sponsorship) {
    echo "لا يوجد كفالة من نوع deceased_father" . PHP_EOL;
    exit;
}

echo "Sponsorship ID: {$sponsorship->id}" . PHP_EOL;
echo "Identity Number: {$sponsorship->identity_number}" . PHP_EOL;
echo "Person Type: {$sponsorship->person_type}" . PHP_EOL;
echo "Relation ID: {$sponsorship->relation_id_number}" . PHP_EOL;

// البحث عن dead_people
$deadPerson = DB::table('dead_people')
    ->where('father_id', $sponsorship->identity_number)
    ->first();

if ($deadPerson) {
    echo PHP_EOL . "=== Dead People Record Found ===" . PHP_EOL;
    echo "re_file_id: {$deadPerson->re_file_id}" . PHP_EOL;

    // البحث عن الحساب البنكي
    $bankAccount = DB::table('guardian_bank_accounts')
        ->where('guardian_registration', $deadPerson->re_file_id)
        ->first();

    if ($bankAccount) {
        echo PHP_EOL . "=== Bank Account Found ===" . PHP_EOL;
        echo "ID: {$bankAccount->id}" . PHP_EOL;
        echo "Owner: {$bankAccount->re_guardian_name}" . PHP_EOL;
        echo "Check Account: {$bankAccount->check_account}" . PHP_EOL;
    } else {
        echo PHP_EOL . "=== Creating Test Bank Account ===" . PHP_EOL;

        // جلب أول بنك متاح بـ ID أكبر من 0
        $firstBank = DB::table('bank_names')->where('id', '>', 0)->first();
        $bankId = $firstBank ? $firstBank->id : null;

        if (!$bankId) {
            // جلب أي بنك
            $anyBank = DB::table('bank_names')->first();
            $bankId = $anyBank ? $anyBank->id : null;
        }

        echo "Using bank ID: " . ($bankId ?? 'NULL') . PHP_EOL;

        // إنشاء حساب بنكي تجريبي
        $newId = DB::table('guardian_bank_accounts')->insertGetId([
            'guardian_registration' => $deadPerson->re_file_id,
            're_id_number' => $sponsorship->identity_number,
            're_guardian_name' => 'اسم صاحب الحساب التجريبي',
            'bank_name' => $bankId,
            'person_owner_identity_number' => '123456789',
            're_phone_number' => '0599000000',
            'iban_usd' => 'USD123456789',
            'iban_shekel' => 'ILS123456789',
            'check_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "Created new bank account with ID: {$newId}" . PHP_EOL;
    }
} else {
    echo "No dead_people record found for identity: {$sponsorship->identity_number}" . PHP_EOL;

    // محاولة باستخدام relation_id_number
    echo PHP_EOL . "=== Trying with relation_id_number ===" . PHP_EOL;
    echo "relation_id_number: {$sponsorship->relation_id_number}" . PHP_EOL;

    $bankAccount = DB::table('guardian_bank_accounts')
        ->where('guardian_registration', $sponsorship->relation_id_number)
        ->first();

    if ($bankAccount) {
        echo "Bank Account Found via relation_id_number" . PHP_EOL;
    } else {
        echo "No bank account found" . PHP_EOL;
    }
}

echo PHP_EOL . "=== جميع الحسابات البنكية ===" . PHP_EOL;
$allAccounts = DB::table('guardian_bank_accounts')->get();
foreach ($allAccounts as $acc) {
    echo "ID: {$acc->id} | Registration: {$acc->guardian_registration} | Owner: {$acc->re_guardian_name} | Approved: {$acc->check_account}" . PHP_EOL;
}
