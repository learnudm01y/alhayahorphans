<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "✅ اختبار نهائي - جميع البيانات البنكية\n\n";

// محاكاة ما يحدث في Controller
$identityNumber = '666665457';

echo "=== 1. تحديد نوع الشخص ===\n";
$personType = null;
$guardianData = null;

// البحث في re_people
$rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
if ($rePerson) {
    $personType = 're_people';
    $guardianData = DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
    echo "✓ نوع الشخص: فرد أسرة (re_people)\n";
    echo "✓ رقم ملف المعيل: {$rePerson->registration_id}\n";
}

if (!$guardianData) {
    die("✗ لم يتم العثور على بيانات المعيل\n");
}

echo "\n=== 2. جلب الحساب البنكي ===\n";
$approvedBankAccount = DB::table('guardian_bank_accounts')
    ->where('guardian_registration', $guardianData->file_id_number)
    ->where('check_account', 1)
    ->first();

if (!$approvedBankAccount) {
    die("✗ لم يتم العثور على حساب بنكي معتمد\n");
}

echo "✓ تم العثور على الحساب البنكي\n";

echo "\n=== 3. القيم التي سيتم إرسالها للـ View ===\n";
$values = [];
$values['field_guardian_account_owner_name'] = $approvedBankAccount->re_guardian_name;
$values['field_guardian_bank_name'] = $approvedBankAccount->bank_name;
$values['field_guardian_id_owner'] = $approvedBankAccount->person_owner_identity_number;
$values['field_guardian_phone_number'] = $approvedBankAccount->re_phone_number;
$values['field_guardian_iban_usd'] = $approvedBankAccount->iban_usd;
$values['field_guardian_iban_shekel'] = $approvedBankAccount->iban_shekel;

foreach($values as $key => $value) {
    echo "  ✓ {$key}: {$value}\n";
}

echo "\n=== 4. اسم البنك ===\n";
$bank = DB::table('bank_names')->where('id', $approvedBankAccount->bank_name)->first();
echo "  ✓ Bank ID: {$approvedBankAccount->bank_name}\n";
echo "  ✓ Bank Name: " . ($bank ? $bank->description : 'غير موجود') . "\n";

echo "\n=== 5. الحقول المفعلة ===\n";
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
$bankFields = [
    'field_guardian_account_owner_name' => 'اسم صاحب الحساب',
    'field_guardian_bank_name' => 'اسم البنك',
    'field_guardian_id_owner' => 'رقم هوية صاحب الحساب',
    'field_guardian_phone_number' => 'رقم هاتف صاحب الحساب',
    'field_guardian_iban_usd' => 'رقم IBAN بالدولار',
    'field_guardian_iban_shekel' => 'رقم IBAN بالشيكل',
];

foreach($bankFields as $field => $label) {
    $enabled = $settings->$field ?? 0;
    $status = $enabled ? '✓' : '✗';
    echo "  {$status} {$label} ({$field}): " . ($enabled ? 'مفعل' : 'معطل') . "\n";
}

echo "\n✅ كل شيء جاهز! يجب أن تظهر جميع الحقول الآن.\n";
