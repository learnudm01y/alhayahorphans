<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GoogleDriveService;

use Illuminate\Support\Facades\Http;

$service = new GoogleDriveService();

// We need the access token, which is private. We can just use reflection.
$reflection = new ReflectionClass($service);
$property = $reflection->getProperty('accessToken');
$property->setAccessible(true);
$token = $property->getValue($service);

$response = Http::withOptions(['verify' => false])
    ->withToken($token)
    ->get('https://www.googleapis.com/drive/v3/drives');

echo "Drives:\n";
print_r($response->json());

$response2 = Http::withOptions(['verify' => false])
    ->withToken($token)
    ->get('https://www.googleapis.com/drive/v3/files', [
        'q' => "trashed=false",
        'pageSize' => 5
    ]);
echo "Some files:\n";
print_r($response2->json());
