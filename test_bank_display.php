<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "اختبار عرض المعلومات البنكية...\n\n";

// 1. فحص الحساب المعتمد
$account = DB::table('guardian_bank_accounts')
    ->where('guardian_registration', '002190')
    ->where('check_account', 1)
    ->first();

if(!$account) {
    die("✗ لا يوجد حساب بنكي معتمد!\n");
}

echo "✓ الحساب البنكي المعتمد:\n";
echo "  - ID: {$account->id}\n";
echo "  - اسم المعيل: {$account->re_guardian_name}\n";
echo "  - IBAN دولار: {$account->iban_usd}\n";
echo "  - IBAN شيكل: {$account->iban_shekel}\n";
echo "  - رقم البنك: {$account->bank_name}\n";

// 2. جلب اسم البنك
$bank = DB::table('bank_names')->where('id', $account->bank_name)->first();
echo "  - اسم البنك: " . ($bank ? $bank->description : 'غير موجود') . "\n";
echo "  - رقم هوية صاحب الحساب: {$account->person_owner_identity_number}\n";

// 3. فحص حقول البنك في sponsor_field_settings
echo "\n✓ حقول البنك المفعلة:\n";
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
$bankFields = [
    'field_bank_account_name',
    'field_bank_account_number',
    'field_bank_name',
    'field_bank_iban',
    'field_account_owner_id'
];

foreach($bankFields as $field) {
    $enabled = $settings->$field ?? 0;
    $status = $enabled ? '✓' : '✗';
    echo "  {$status} {$field}: " . ($enabled ? 'مفعل' : 'معطل') . "\n";
}

echo "\n✓ جاهز للاختبار!\n";
echo "الآن يمكنك تسجيل الدخول بـ:\n";
echo "  - اسم المستخدم: 666665457\n";
echo "  - كلمة المرور: 002622\n";
