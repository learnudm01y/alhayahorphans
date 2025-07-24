<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Route;

echo "Testing Excel folder API:\n";

// Test Excel folder contents API
$folderName = '0000000000'; // This folder has 4 files
$apiUrl = "http://127.0.0.1:8000/admin/folders/contents?folder={$folderName}&type=excel";

echo "API URL: {$apiUrl}\n";

// Make a curl request to test the API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: " . substr($response, 0, 500) . "...\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "API Success! Found " . count($data['files']) . " Excel files\n";

        foreach ($data['files'] as $file) {
            echo "File: " . ($file['stored_file_name'] ?? 'No name') . "\n";
            echo "  Download URL: " . ($file['download_url'] ?? 'No URL') . "\n";
        }
    } else {
        echo "API Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "HTTP Error: {$httpCode}\n";
}

echo "\nDone.\n";
