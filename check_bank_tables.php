<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "البحث عن جداول البنوك...\n\n";

$tables = DB::select('SHOW TABLES');
$bankTables = [];

foreach($tables as $t) {
    $tableName = array_values((array)$t)[0];
    if(stripos($tableName, 'bank') !== false) {
        $bankTables[] = $tableName;
    }
}

echo "الجداول المتعلقة بالبنوك:\n";
print_r($bankTables);

echo "\n\nفحص الحساب البنكي المعتمد:\n";
$account = DB::table('guardian_bank_accounts')
    ->where('guardian_registration', '002190')
    ->where('check_account', 1)
    ->first();

if($account) {
    echo "✓ تم العثور على حساب معتمد:\n";
    echo "  - ID: {$account->id}\n";
    echo "  - اسم المعيل: {$account->re_guardian_name}\n";
    echo "  - IBAN دولار: {$account->iban_usd}\n";
    echo "  - IBAN شيكل: {$account->iban_shekel}\n";
    echo "  - رقم البنك: {$account->bank_name}\n";
    echo "  - رقم هوية صاحب الحساب: {$account->person_owner_identity_number}\n";
} else {
    echo "✗ لم يتم العثور على حساب معتمد\n";
}
