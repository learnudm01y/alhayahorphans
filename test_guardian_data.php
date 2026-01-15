<?php

$ch = curl_init('http://127.0.0.1:8000/api/offline-test-development/sponsorships/100160');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);

echo "=== بيانات الكفالة 100160 ===" . PHP_EOL;

if (!$data) {
    echo "Error: لا يوجد اتصال بالخادم" . PHP_EOL;
    echo "Response: " . $response . PHP_EOL;
    exit;
}

if (isset($data['success']) && $data['success']) {
    $s = $data['sponsorship'];

    echo PHP_EOL . "📋 بيانات أساسية:" . PHP_EOL;
    echo "  orphan_name: " . ($s['orphan_name'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_name: " . ($s['guardian_name'] ?? 'NULL') . PHP_EOL;
    echo "  internal_file_number: " . ($s['internal_file_number'] ?? 'NULL') . PHP_EOL;
    echo "  relation_id_number: " . ($s['relation_id_number'] ?? 'NULL') . PHP_EOL;
    echo "  identity_number: " . ($s['identity_number'] ?? 'NULL') . PHP_EOL;

    echo PHP_EOL . "👨‍👩‍👧 بيانات المعيل:" . PHP_EOL;
    echo "  guardian_first_name: " . ($s['guardian_first_name'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_father_name: " . ($s['guardian_father_name'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_grandfather_name: " . ($s['guardian_grandfather_name'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_family_name: " . ($s['guardian_family_name'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_phone: " . ($s['guardian_phone'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_phone2: " . ($s['guardian_phone2'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_identity_number: " . ($s['guardian_identity_number'] ?? 'NULL') . PHP_EOL;

    echo PHP_EOL . "👶 بيانات المكفول:" . PHP_EOL;
    echo "  first_name: " . ($s['first_name'] ?? 'NULL') . PHP_EOL;
    echo "  second_name: " . ($s['second_name'] ?? 'NULL') . PHP_EOL;
    echo "  third_name: " . ($s['third_name'] ?? 'NULL') . PHP_EOL;
    echo "  last_name: " . ($s['last_name'] ?? 'NULL') . PHP_EOL;
    echo "  orphan_gender: " . ($s['orphan_gender'] ?? 'NULL') . PHP_EOL;
    echo "  health_status_id: " . ($s['health_status_id'] ?? 'NULL') . PHP_EOL;

    echo PHP_EOL . "🏦 الحسابات البنكية: " . count($s['bank_accounts'] ?? []) . PHP_EOL;
    if (!empty($s['bank_accounts'])) {
        foreach ($s['bank_accounts'] as $i => $bank) {
            echo "  حساب $i: " . PHP_EOL;
            echo "    bank_name_text: " . ($bank->bank_name_text ?? $bank['bank_name_text'] ?? 'NULL') . PHP_EOL;
            echo "    iban_usd: " . ($bank->iban_usd ?? $bank['iban_usd'] ?? 'NULL') . PHP_EOL;
            echo "    iban_shekel: " . ($bank->iban_shekel ?? $bank['iban_shekel'] ?? 'NULL') . PHP_EOL;
        }
    }
} else {
    echo "Error: " . ($data['message'] ?? 'unknown');
}
