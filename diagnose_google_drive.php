<?php

// اختبار بسيط للـ Google Drive API

$credentialsPath = __DIR__ . '/storage/app/google/credentials.json';
$credentials = json_decode(file_get_contents($credentialsPath), true);

echo "=== Google Drive API Diagnostics ===\n\n";
echo "Service Account Email: " . $credentials['client_email'] . "\n";
echo "Project ID: " . $credentials['project_id'] . "\n\n";

// إنشاء JWT Token
function createJWT($credentials) {
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $claim = json_encode([
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $base64UrlHeader = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlClaim = rtrim(strtr(base64_encode($claim), '+/', '-_'), '=');
    $signature = '';
    openssl_sign($base64UrlHeader . '.' . $base64UrlClaim, $signature, $credentials['private_key'], 'SHA256');
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $base64UrlHeader . '.' . $base64UrlClaim . '.' . $base64UrlSignature;
}

// الحصول على Access Token
echo "Getting Access Token...\n";
$jwt = createJWT($credentials);

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
    'assertion' => $jwt
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "Error getting token: $response\n";
    exit(1);
}

$token = json_decode($response, true)['access_token'];
echo "✅ Access Token obtained\n\n";

// اختبار 1: سرد الملفات العادية
echo "Test 1: List regular files...\n";
$ch = curl_init('https://www.googleapis.com/drive/v3/files?pageSize=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Regular files accessible: " . count($data['files'] ?? []) . " files\n\n";
} else {
    echo "❌ Error: $response\n\n";
}

// اختبار 2: سرد Shared Drives
echo "Test 2: List Shared Drives...\n";
$ch = curl_init('https://www.googleapis.com/drive/v3/drives?pageSize=10');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $drives = $data['drives'] ?? [];
    echo "✅ Shared Drives accessible: " . count($drives) . "\n";

    if (count($drives) > 0) {
        echo "\nAvailable Shared Drives:\n";
        foreach ($drives as $drive) {
            echo "  - Name: " . $drive['name'] . "\n";
            echo "    ID: " . $drive['id'] . "\n\n";
        }
    } else {
        echo "⚠️  No Shared Drives found!\n";
        echo "This means the Service Account is NOT a member of any Shared Drive.\n\n";
    }
} else {
    echo "❌ Error accessing Shared Drives:\n";
    echo "$response\n\n";
}

// اختبار 3: الوصول إلى Shared Drive محدد
echo "Test 3: Access specific Shared Drive...\n";
$driveId = '0ACfP0zCkSEOqUk9PVA';
$ch = curl_init("https://www.googleapis.com/drive/v3/drives/{$driveId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Shared Drive accessible!\n";
    echo "Name: " . $data['name'] . "\n";
    echo "ID: " . $data['id'] . "\n\n";
} else {
    echo "❌ Cannot access Shared Drive: $driveId\n";
    echo "Response: $response\n\n";
}

// اختبار 4: سرد الملفات في Shared Drive
echo "Test 4: List files in Shared Drive...\n";
$ch = curl_init("https://www.googleapis.com/drive/v3/files?driveId={$driveId}&includeItemsFromAllDrives=true&supportsAllDrives=true&corpora=drive&pageSize=10");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Files in Shared Drive: " . count($data['files'] ?? []) . "\n";
} else {
    echo "❌ Cannot list files in Shared Drive\n";
    echo "Response: $response\n";
}

echo "\n=== End of Diagnostics ===\n";
