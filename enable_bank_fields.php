<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "فحص حقول البنك في sponsor_field_settings...\n\n";

$columns = DB::select("SHOW COLUMNS FROM sponsor_field_settings LIKE '%bank%'");

echo "الأعمدة الموجودة:\n";
foreach($columns as $col) {
    echo "  - {$col->Field}\n";
}

// التحديث
echo "\n\nتحديث حقول البنك...\n";

$updated = DB::table('sponsor_field_settings')
    ->where('sponsor_id', 1)
    ->update([
        'field_bank_account_name' => true,
        'field_bank_account_number' => true,
        'field_bank_name' => true,
        'field_bank_iban' => true,
        'field_account_owner_id' => true,
    ]);

echo "عدد الصفوف المحدثة: {$updated}\n";

// التحقق
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 1)->first();
echo "\nالحالة بعد التحديث:\n";
echo "  - field_bank_account_name: " . ($settings->field_bank_account_name ?? 'غير موجود') . "\n";
echo "  - field_bank_account_number: " . ($settings->field_bank_account_number ?? 'غير موجود') . "\n";
echo "  - field_bank_name: " . ($settings->field_bank_name ?? 'غير موجود') . "\n";
echo "  - field_bank_iban: " . ($settings->field_bank_iban ?? 'غير موجود') . "\n";
echo "  - field_account_owner_id: " . ($settings->field_account_owner_id ?? 'غير موجود') . "\n";
