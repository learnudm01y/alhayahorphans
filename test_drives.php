<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

try {
    $s = new App\Services\GoogleDriveService();
    
    // We need the access token, which is private. We can just use reflection.
    $reflection = new ReflectionClass($s);
    $property = $reflection->getProperty('accessToken');
    $property->setAccessible(true);
    
    // Call getAccessToken implicitly if null by doing a dummy call or reflection method
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);
    $token = $method->invoke($s);
    
    echo "Listing accessible Shared Drives for the Service Account...\n";
    $response = Http::withOptions(['verify' => false])
        ->withToken($token)
        ->get('https://www.googleapis.com/drive/v3/drives');
        
    if ($response->successful()) {
        $drives = $response->json()['drives'] ?? [];
        if (empty($drives)) {
            echo "The Service Account is NOT a member of ANY Shared Drives!\n";
        } else {
            foreach ($drives as $drive) {
                echo "- Drive Name: " . $drive['name'] . " | ID: " . $drive['id'] . "\n";
            }
        }
    } else {
        echo "Error fetching drives: " . $response->body() . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
