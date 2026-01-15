<?php
/**
 * التحقق من الحسابات البنكية المنشأة للاختبار
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// البحث عن الحسابات البنكية التي تم إنشاؤها للاختبار
$bankAccounts = DB::table('guardian_bank_accounts')
    ->where('bank_name', 'بنك فلسطين')
    ->where('re_guardian_name', 'LIKE', '%المعيل_اختبار%')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "===========================================\n";
echo "🏦 الحسابات البنكية للاختبار\n";
echo "===========================================\n\n";

if ($bankAccounts->isEmpty()) {
    echo "❌ لا توجد حسابات بنكية للاختبار!\n";
} else {
    foreach ($bankAccounts as $account) {
        echo "ID: " . $account->id . "\n";
        echo "guardian_registration: " . $account->guardian_registration . "\n";
        echo "bank_name: " . $account->bank_name . "\n";
        echo "re_id_number: " . $account->re_id_number . "\n";
        echo "re_guardian_name: " . $account->re_guardian_name . "\n";
        echo "re_phone_number: " . $account->re_phone_number . "\n";
        echo "iban_usd: " . $account->iban_usd . "\n";
        echo "iban_shekel: " . $account->iban_shekel . "\n";
        echo "person_owner_identity_number: " . $account->person_owner_identity_number . "\n";
        $status = $account->check_account == 1 ? 'مؤكد ✅' : 'غير مؤكد ❌';
        echo "check_account: " . $account->check_account . " (" . $status . ")\n";
        echo str_repeat("-", 50) . "\n";
    }
    echo "\n✅ العدد الإجمالي: " . $bankAccounts->count() . " حساب\n";
}

// التحقق من آخر السجلات في جدول data
echo "\n\n===========================================\n";
echo "🏠 آخر السجلات في جدول data (اختبار)\n";
echo "===========================================\n\n";

$dataRecords = DB::table('data')
    ->where('data_first_name', 'LIKE', '%اختبار%')
    ->orderBy('id', 'desc')
    ->limit(3)
    ->get();

foreach ($dataRecords as $record) {
    echo "ID: " . $record->id . "\n";
    echo "file_id_number: " . $record->file_id_number . "\n";
    echo "data_id_number: " . $record->data_id_number . "\n";
    echo "data_first_name: " . $record->data_first_name . "\n";
    echo str_repeat("-", 50) . "\n";
}

// التحقق من آخر السجلات في جدول re_people
echo "\n\n===========================================\n";
echo "👤 آخر السجلات في جدول re_people (اختبار)\n";
echo "===========================================\n\n";

$rePeopleRecords = DB::table('re_people')
    ->where('first_name', 'LIKE', '%اختبار%')
    ->orderBy('id', 'desc')
    ->limit(3)
    ->get();

foreach ($rePeopleRecords as $record) {
    echo "ID: " . $record->id . "\n";
    echo "registration_id: " . $record->registration_id . "\n";
    echo "person_id: " . $record->person_id . "\n";
    echo "first_name: " . $record->first_name . "\n";
    echo str_repeat("-", 50) . "\n";
}
