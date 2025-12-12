<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check actual data in guardian_bank_accounts for file 002550
$accounts = DB::select("
    SELECT id, guardian_registration, re_id_number, re_phone_number, bank_name,
           person_owner_identity_number, created_at
    FROM guardian_bank_accounts
    WHERE guardian_registration = ?
    ORDER BY id DESC
", ['002550']);

echo "Total accounts found: " . count($accounts) . "\n\n";

foreach ($accounts as $account) {
    echo "ID: {$account->id}\n";
    echo "Guardian Registration: {$account->guardian_registration}\n";
    echo "RE ID Number: {$account->re_id_number}\n";
    echo "Phone: {$account->re_phone_number}\n";
    echo "Bank Name: {$account->bank_name}\n";
    echo "Owner Identity: {$account->person_owner_identity_number}\n";
    echo "Created At: {$account->created_at}\n";
    echo str_repeat('-', 50) . "\n";
}

// Now test the duplicate check logic
echo "\n=== Testing Duplicate Check Logic ===\n\n";

$guardianRegistration = '002550';
$reIdNumber = '80004';
$rePhoneNumber = '595062223';
$bankName = 3;

echo "Searching for:\n";
echo "  guardian_registration: $guardianRegistration\n";
echo "  re_id_number: $reIdNumber\n";
echo "  re_phone_number: $rePhoneNumber\n";
echo "  bank_name: $bankName\n\n";

// Test the exact query from BankAccountValidationService
$query = DB::table('guardian_bank_accounts')
    ->where('guardian_registration', $guardianRegistration)
    ->where('re_id_number', $reIdNumber);

if (!empty($rePhoneNumber)) {
    $query->where('re_phone_number', $rePhoneNumber);
}

if (!empty($bankName)) {
    $query->where('bank_name', $bankName);
}

echo "SQL Query: " . $query->toSql() . "\n";
echo "Bindings: " . json_encode($query->getBindings()) . "\n\n";

$existingAccount = $query->first();

if ($existingAccount) {
    echo "✅ DUPLICATE FOUND:\n";
    print_r($existingAccount);
} else {
    echo "❌ NO DUPLICATE FOUND (This is the bug!)\n";
}

// Let's also check what data types are stored
echo "\n=== Checking Data Types ===\n\n";

$sample = DB::select("SELECT * FROM guardian_bank_accounts WHERE guardian_registration = ? LIMIT 1", ['002550']);
if (!empty($sample)) {
    $s = $sample[0];
    echo "guardian_registration type: " . gettype($s->guardian_registration) . " value: '$s->guardian_registration'\n";
    echo "re_id_number type: " . gettype($s->re_id_number) . " value: '$s->re_id_number'\n";
    echo "re_phone_number type: " . gettype($s->re_phone_number) . " value: '$s->re_phone_number'\n";
    echo "bank_name type: " . gettype($s->bank_name) . " value: '$s->bank_name'\n";
}
