<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "فحص ظهور اسم صاحب الحساب...\n\n";

// 1. جلب الكفالة
$sponsorship = DB::table('sponsorships')->where('identity_number', '666665457')->first();
echo "=== معلومات الكفالة ===\n";
echo "ID: {$sponsorship->id}\n";
echo "Internal File: {$sponsorship->internal_file_number}\n";

// 2. تحديد نوع الشخص وجلب رقم ملف المعيل
$guardianFileNumber = null;

// البحث في re_people
$rePerson = DB::table('re_people')->where('person_id', '666665457')->first();
if ($rePerson) {
    echo "Person Type: re_people (family member)\n";
    echo "Registration ID (Guardian File): {$rePerson->registration_id}\n";
    $guardianFileNumber = $rePerson->registration_id;
}

if (!$guardianFileNumber) {
    echo "✗ لم يتم العثور على رقم ملف المعيل\n";
    exit;
}

// جلب بيانات المعيل
$data = DB::table('data')->where('file_id_number', $guardianFileNumber)->first();
if (!$data) {
    echo "✗ لم يتم العثور على بيانات المعيل\n";
    exit;
}
echo "Guardian File ID Number: {$data->file_id_number}\n";

// 3. جلب الحساب البنكي
$account = DB::table('guardian_bank_accounts')
    ->where('guardian_registration', $data->file_id_number)
    ->where('check_account', 1)
    ->first();

if (!$account) {
    echo "\n✗ لم يتم العثور على حساب بنكي معتمد\n";
    exit;
}

echo "\n=== البيانات البنكية ===\n";
echo "Account ID: {$account->id}\n";
echo "Account Owner Name: {$account->re_guardian_name}\n";
echo "Bank Name ID: {$account->bank_name}\n";
echo "Owner ID: {$account->person_owner_identity_number}\n";
echo "Phone: {$account->re_phone_number}\n";
echo "IBAN USD: {$account->iban_usd}\n";
echo "IBAN Shekel: {$account->iban_shekel}\n";

// 4. فحص الحقول المفعلة
echo "\n=== الحقول المفعلة (sponsor_id = 5) ===\n";
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
echo "field_guardian_account_owner_name: " . ($settings->field_guardian_account_owner_name ? '✓ مفعل' : '✗ معطل') . "\n";
echo "field_guardian_bank_name: " . ($settings->field_guardian_bank_name ? '✓ مفعل' : '✗ معطل') . "\n";
echo "field_guardian_id_owner: " . ($settings->field_guardian_id_owner ? '✓ مفعل' : '✗ معطل') . "\n";
echo "field_guardian_phone_number: " . ($settings->field_guardian_phone_number ? '✓ مفعل' : '✗ معطل') . "\n";
echo "field_guardian_iban_usd: " . ($settings->field_guardian_iban_usd ? '✓ مفعل' : '✗ معطل') . "\n";
echo "field_guardian_iban_shekel: " . ($settings->field_guardian_iban_shekel ? '✓ مفعل' : '✗ معطل') . "\n";

// 5. محاكاة extractFieldValues
echo "\n=== محاكاة extractFieldValues ===\n";
$values = [];
$values['field_guardian_account_owner_name'] = $account->re_guardian_name;
$values['field_guardian_bank_name'] = $account->bank_name;
$values['field_guardian_id_owner'] = $account->person_owner_identity_number;
$values['field_guardian_phone_number'] = $account->re_phone_number;
$values['field_guardian_iban_usd'] = $account->iban_usd;
$values['field_guardian_iban_shekel'] = $account->iban_shekel;

echo "Values Array:\n";
print_r($values);
