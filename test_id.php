<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $s = new App\Services\GoogleDriveService();
    $fileId = '0ACfP0zCkSEOqUk9PVA';
    
    // First, test getFile
    echo "Testing getFile...\n";
    $info = $s->getFile($fileId);
    print_r($info);
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
