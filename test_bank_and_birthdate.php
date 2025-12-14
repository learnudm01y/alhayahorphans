<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار الحساب البنكي وتاريخ الميلاد ===\n\n";

$identityNumber = '666665457';
$sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();

if (!$sponsorship) {
    echo "✗ لم يتم العثور على الكفالة!\n";
    exit;
}

echo "✓ الكفالة موجودة:\n";
echo "  - ID: {$sponsorship->id}\n";
echo "  - رقم الهوية: {$sponsorship->identity_number}\n";
echo "  - رقم الملف الداخلي: {$sponsorship->internal_file_number}\n\n";

// 1. تاريخ الميلاد
echo "=== 1. تاريخ الميلاد ===\n";

// الطريقة الحالية
$rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
if ($rePerson) {
    echo "✓ سجل موجود في re_people:\n";
    echo "  - person_id: {$rePerson->person_id}\n";
    echo "  - تاريخ الميلاد: " . ($rePerson->person_birth_date ?? 'غير موجود') . "\n\n";
} else {
    echo "✗ لا يوجد سجل في re_people برقم الهوية\n\n";
}

$dataByIdentity = DB::table('data')->where('data_id_number', $identityNumber)->first();
if ($dataByIdentity) {
    echo "✓ سجل موجود في data:\n";
    echo "  - data_id_number: {$dataByIdentity->data_id_number}\n";
    echo "  - تاريخ الميلاد: " . ($dataByIdentity->data_birth_date ?? 'غير موجود') . "\n\n";
} else {
    echo "✗ لا يوجد سجل في data برقم الهوية\n\n";
}

// 2. الحساب البنكي
echo "=== 2. الحساب البنكي المعتمد ===\n";

$relationData = $sponsorship->relationData;
if (!$relationData) {
    echo "✗ لا توجد بيانات معيل (relationData)\n";
    exit;
}

echo "✓ بيانات المعيل موجودة:\n";
echo "  - file_id_number: {$relationData->file_id_number}\n";
echo "  - الاسم: {$relationData->data_first_name} {$relationData->data_father_name}\n\n";

// البحث عن جميع الحسابات
$allAccounts = DB::table('guardian_banks_accounts')
    ->where('guardian_registration', $relationData->file_id_number)
    ->get();

echo "إجمالي الحسابات البنكية: " . $allAccounts->count() . "\n\n";

if ($allAccounts->count() > 0) {
    foreach ($allAccounts as $index => $account) {
        $num = $index + 1;
        echo "حساب #{$num}:\n";
        echo "  - ID: {$account->id}\n";
        echo "  - اسم صاحب الحساب: {$account->guardian_name}\n";
        echo "  - رقم الحساب: {$account->guardian_accounts_number}\n";
        echo "  - معتمد (check_account): " . ($account->check_account ? 'نعم ✓' : 'لا ✗') . "\n";
        echo "  - رقم هوية صاحب الحساب: {$account->guardian_id}\n";
        echo "  - رقم ملف المعيل: {$account->guardian_registration}\n\n";
    }

    // الحساب المعتمد
    $approvedAccount = $allAccounts->where('check_account', 1)->first();

    if ($approvedAccount) {
        echo "✓ يوجد حساب معتمد (check_account = 1):\n";
        echo "  - اسم صاحب الحساب: {$approvedAccount->guardian_name}\n";
        echo "  - رقم الحساب: {$approvedAccount->guardian_accounts_number}\n";
    } else {
        echo "✗ لا يوجد حساب معتمد (check_account = 1)\n";
        echo "\n=== اعتماد أول حساب ===\n";

        $firstAccount = $allAccounts->first();
        DB::table('guardian_banks_accounts')
            ->where('id', $firstAccount->id)
            ->update(['check_account' => 1]);

        echo "✓ تم اعتماد الحساب #{$firstAccount->id}\n";
        echo "  - اسم صاحب الحساب: {$firstAccount->guardian_name}\n";
        echo "  - رقم الحساب: {$firstAccount->guardian_accounts_number}\n";
    }
} else {
    echo "✗ لا توجد حسابات بنكية مسجلة لهذا المعيل\n";
}

echo "\n=== تم ===\n";
