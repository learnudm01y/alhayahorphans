<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Complete Duplicate Prevention ===\n\n";

$validationService = new \App\Services\BankAccountValidationService();

// Test case 1: Existing duplicate (should be detected)
echo "Test 1: Checking existing duplicate for guardian 002550\n";
$result1 = $validationService->checkDuplicateBankAccount([
    'guardian_registration' => '002550',
    're_phone_number' => '595062223',
    'bank_name' => 3,
    're_id_number' => '80004' // هوية صاحب الحساب
]);

if ($result1['is_duplicate']) {
    echo "✅ SUCCESS: Duplicate detected!\n";
    echo "Message: {$result1['message']}\n";
    echo "Existing Account ID: {$result1['existing_account']['id']}\n";
} else {
    echo "❌ FAILED: Should have detected duplicate!\n";
}

echo "\n" . str_repeat('=', 60) . "\n\n";

// Test case 2: New unique account (should NOT be duplicate)
echo "Test 2: Checking new unique account\n";
$result2 = $validationService->checkDuplicateBankAccount([
    'guardian_registration' => '999999',
    're_phone_number' => '123456789',
    'bank_name' => 1,
    're_id_number' => '888888'
]);

if (!$result2['is_duplicate']) {
    echo "✅ SUCCESS: No duplicate detected (as expected)\n";
} else {
    echo "❌ FAILED: Should NOT have detected duplicate!\n";
}

echo "\n" . str_repeat('=', 60) . "\n\n";

// Test case 3: Same guardian, different owner (should NOT be duplicate)
echo "Test 3: Checking same guardian file but different account owner\n";
$result3 = $validationService->checkDuplicateBankAccount([
    'guardian_registration' => '002550',
    're_phone_number' => '595062223',
    'bank_name' => 3,
    're_id_number' => '99999' // هوية مختلفة
]);

if (!$result3['is_duplicate']) {
    echo "✅ SUCCESS: No duplicate detected (different owner)\n";
} else {
    echo "❌ FAILED: Should NOT have detected duplicate (different owner)!\n";
}

echo "\n" . str_repeat('=', 60) . "\n\n";

// Test case 4: Quick duplicate check
echo "Test 4: Testing quickDuplicateCheck method\n";
$quickCheck = $validationService->quickDuplicateCheck('002550', '80004');

if ($quickCheck) {
    echo "✅ SUCCESS: Quick check detected duplicate!\n";
} else {
    echo "❌ FAILED: Quick check should have detected duplicate!\n";
}

echo "\n" . str_repeat('=', 60) . "\n\n";

// Test case 5: Get existing accounts
echo "Test 5: Getting all existing accounts for guardian 002550\n";
$existingAccounts = $validationService->getExistingAccounts('002550', '80004');

echo "Found {$existingAccounts->count()} accounts:\n";
foreach ($existingAccounts as $account) {
    echo "  - ID: {$account->id}, Created: {$account->created_at}\n";
}

if ($existingAccounts->count() >= 8) {
    echo "✅ SUCCESS: Found all duplicate accounts!\n";
} else {
    echo "⚠️ WARNING: Expected 8 accounts, found {$existingAccounts->count()}\n";
}

echo "\n" . str_repeat('=', 60) . "\n\n";
echo "✅ All tests completed!\n";
