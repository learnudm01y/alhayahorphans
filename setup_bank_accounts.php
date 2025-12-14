<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== فحص المعلومات البنكية ===\n\n";

$identityNumber = '666665457';
$sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();

if (!$sponsorship || !$sponsorship->relationData) {
    echo "✗ لا توجد بيانات كفالة أو معيل\n";
    exit;
}

$guardianFileNumber = $sponsorship->relationData->file_id_number;
echo "✓ رقم ملف المعيل: {$guardianFileNumber}\n\n";

// التحقق من وجود الجدول
try {
    $tableExists = DB::select("SHOW TABLES LIKE 'guardian_bank_accounts'");
    if (empty($tableExists)) {
        echo "✗ جدول guardian_bank_accounts غير موجود!\n";
        echo "سأقوم بإنشائه...\n\n";

        // إنشاء الجدول
        DB::statement("
            CREATE TABLE IF NOT EXISTS `guardian_bank_accounts` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `guardian_registration` varchar(255) DEFAULT NULL,
                `guardian_name` varchar(255) DEFAULT NULL,
                `guardian_accounts_number` varchar(255) DEFAULT NULL,
                `guardian_bank` int DEFAULT NULL,
                `guardian_branch_number` varchar(255) DEFAULT NULL,
                `guardian_iban` varchar(255) DEFAULT NULL,
                `guardian_id` varchar(255) DEFAULT NULL,
                `check_account` tinyint(1) DEFAULT '0',
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✓ تم إنشاء الجدول بنجاح\n\n";
    } else {
        echo "✓ الجدول موجود\n\n";
    }

    // البحث عن الحسابات
    $accounts = DB::table('guardian_bank_accounts')
        ->where('guardian_registration', $guardianFileNumber)
        ->get();

    echo "عدد الحسابات: " . $accounts->count() . "\n\n";

    if ($accounts->count() > 0) {
        foreach ($accounts as $index => $account) {
            echo "حساب #" . ($index + 1) . ":\n";
            echo "  - ID: {$account->id}\n";
            echo "  - اسم صاحب الحساب: {$account->guardian_name}\n";
            echo "  - رقم الحساب: {$account->guardian_accounts_number}\n";
            echo "  - معتمد: " . ($account->check_account ? 'نعم' : 'لا') . "\n\n";
        }
    } else {
        echo "✗ لا توجد حسابات مسجلة\n";
        echo "سأقوم بإنشاء حساب تجريبي...\n\n";

        $accountId = DB::table('guardian_bank_accounts')->insertGetId([
            'guardian_registration' => $guardianFileNumber,
            'guardian_name' => $sponsorship->relationData->data_first_name . ' ' . $sponsorship->relationData->data_father_name,
            'guardian_accounts_number' => '1234567890',
            'guardian_bank' => 1,
            'guardian_branch_number' => '001',
            'guardian_iban' => 'PS00XXXXXXXXXXXXXXXXXXXX',
            'guardian_id' => $sponsorship->relationData->data_id_number,
            'check_account' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "✓ تم إنشاء حساب تجريبي (ID: {$accountId})\n";
    }

} catch (\Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== تم ===\n";
