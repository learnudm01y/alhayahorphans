<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Fixed Duplicate Check Logic ===\n\n";

$guardianRegistration = '002550';
$personOwnerIdentityNumber = '80004'; // هوية صاحب الحساب (المعيل)
$rePhoneNumber = '595062223';
$bankName = 3;

echo "Searching for:\n";
echo "  guardian_registration: $guardianRegistration\n";
echo "  person_owner_identity_number: $personOwnerIdentityNumber\n";
echo "  re_phone_number: $rePhoneNumber\n";
echo "  bank_name: $bankName\n\n";

// Test the FIXED query
$query = DB::table('guardian_bank_accounts')
    ->where('guardian_registration', $guardianRegistration)
    ->where('person_owner_identity_number', $personOwnerIdentityNumber); // FIXED: استخدام person_owner_identity_number

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
    echo "✅ DUPLICATE FOUND (Fix is working!):\n";
    echo "  ID: {$existingAccount->id}\n";
    echo "  Guardian Registration: {$existingAccount->guardian_registration}\n";
    echo "  Owner Identity: {$existingAccount->person_owner_identity_number}\n";
    echo "  Phone: {$existingAccount->re_phone_number}\n";
    echo "  Bank: {$existingAccount->bank_name}\n";
    echo "  Created At: {$existingAccount->created_at}\n";
} else {
    echo "❌ NO DUPLICATE FOUND (Still broken!)\n";
}

// Test using the actual service
echo "\n=== Testing BankAccountValidationService ===\n\n";

$validationService = new \App\Services\BankAccountValidationService();

$result = $validationService->checkDuplicateBankAccount([
    'guardian_registration' => $guardianRegistration,
    're_phone_number' => $rePhoneNumber,
    'bank_name' => $bankName,
    're_id_number' => $personOwnerIdentityNumber // يتم تمريره كـ re_id_number لكن يتم استخدامه كـ person_owner_identity_number
]);

if ($result['is_duplicate']) {
    echo "✅ Service correctly detected duplicate!\n";
    echo "Message: {$result['message']}\n";
} else {
    echo "❌ Service failed to detect duplicate!\n";
    echo "Message: {$result['message']}\n";
}
