<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== التحقق من جدول guardian_bank_accounts ===" . PHP_EOL;
echo "================================================" . PHP_EOL . PHP_EOL;

$accounts = App\Models\GuardianBankAccount::orderBy('id', 'desc')->take(5)->get();

foreach ($accounts as $a) {
    echo "ID={$a->id}:" . PHP_EOL;
    echo "  📱 person_owner_identity_number (صاحب المحفظة): {$a->person_owner_identity_number}" . PHP_EOL;
    echo "  👤 re_id_number (هوية المعيل): {$a->re_id_number}" . PHP_EOL;

    if ($a->person_owner_identity_number == $a->re_id_number) {
        echo "  ✅ متطابقان (صاحب المحفظة هو المعيل)" . PHP_EOL;
    } else {
        echo "  🔄 مختلفان! (صاحب المحفظة شخص آخر غير المعيل)" . PHP_EOL;
    }
    echo PHP_EOL;
}

echo "================================================" . PHP_EOL;
echo "📊 ملخص:" . PHP_EOL;

$total = App\Models\GuardianBankAccount::count();
$different = App\Models\GuardianBankAccount::whereRaw('person_owner_identity_number != re_id_number')->count();
$same = $total - $different;

echo "  إجمالي الحسابات: $total" . PHP_EOL;
echo "  صاحب المحفظة = المعيل: $same" . PHP_EOL;
echo "  صاحب المحفظة ≠ المعيل: $different" . PHP_EOL;
