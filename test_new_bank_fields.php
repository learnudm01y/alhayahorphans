<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "اختبار حقول البنك الجديدة...\n\n";

// 1. فحص الجدول
echo "=== 1. فحص الأعمدة في sponsor_field_settings ===\n";
$columns = DB::select("SHOW COLUMNS FROM sponsor_field_settings WHERE Field LIKE '%guardian%' AND Field LIKE '%bank%' OR Field LIKE '%iban%'");
echo "الأعمدة الموجودة:\n";
foreach($columns as $col) {
    echo "  ✓ {$col->Field}\n";
}

// 2. فحص الحقول المفعلة
echo "\n=== 2. فحص الحقول المفعلة للجمعية (sponsor_id = 5) ===\n";
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
$bankFields = [
    'field_guardian_account_owner_name',
    'field_guardian_bank_name',
    'field_guardian_id_owner',
    'field_guardian_phone_number',
    'field_guardian_iban_usd',
    'field_guardian_iban_shekel',
];

foreach($bankFields as $field) {
    $enabled = $settings->$field ?? 0;
    $status = $enabled ? '✓' : '✗';
    echo "  {$status} {$field}: " . ($enabled ? 'مفعل' : 'معطل') . "\n";
}

// 3. فحص البيانات البنكية
echo "\n=== 3. البيانات البنكية المعتمدة ===\n";
$account = DB::table('guardian_bank_accounts')
    ->where('guardian_registration', '002190')
    ->where('check_account', 1)
    ->first();

if($account) {
    echo "✓ الحساب المعتمد:\n";
    echo "  - اسم صاحب الحساب: {$account->re_guardian_name}\n";

    $bank = DB::table('bank_names')->where('id', $account->bank_name)->first();
    echo "  - اسم البنك (ID: {$account->bank_name}): " . ($bank ? $bank->description : 'غير موجود') . "\n";

    echo "  - رقم هوية صاحب الحساب: {$account->person_owner_identity_number}\n";
    echo "  - رقم هاتف صاحب الحساب: {$account->re_phone_number}\n";
    echo "  - رقم IBAN بالدولار: {$account->iban_usd}\n";
    echo "  - رقم IBAN بالشيكل: {$account->iban_shekel}\n";
} else {
    echo "✗ لا يوجد حساب معتمد\n";
}

// 4. فحص config
echo "\n=== 4. فحص config/sponsor_fields.php ===\n";
$config = config('sponsor_fields.fields');
$bankFieldsConfig = array_filter($config, function($field) {
    return isset($field['category']) && $field['category'] === 'المعلومات البنكية';
});

echo "عدد حقول البنك في الـ config: " . count($bankFieldsConfig) . "\n";
foreach($bankFieldsConfig as $key => $field) {
    echo "  ✓ {$field['display_name']} ({$field['db_column']})\n";
}

// 5. فحص قائمة البنوك
echo "\n=== 5. فحص قائمة البنوك ===\n";
$banks = DB::table('bank_names')->get();
echo "عدد البنوك المتاحة: " . count($banks) . "\n";
foreach($banks as $bank) {
    echo "  - ID: {$bank->id} - {$bank->description}\n";
}

echo "\n✅ جاهز للاختبار!\n";
echo "تسجيل الدخول:\n";
echo "  اسم المستخدم: 666665457\n";
echo "  كلمة المرور: 002622\n";
