<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Route;

echo "Testing Excel file access:\n";

// Test specific Excel file access
$filename = '1752304111_newTest101.xlsx';
$routeUrl = route('admin.file.show', ['filename' => $filename]);

echo "Route URL: {$routeUrl}\n";

// Test file existence
$searchPaths = [
    storage_path('app/public/documents/excel/' . $filename),
    public_path('storage/documents/excel/' . $filename),
    storage_path('app/public/uploads/' . $filename),
];

foreach ($searchPaths as $path) {
    if (file_exists($path)) {
        echo "File found at: {$path}\n";
        echo "File size: " . filesize($path) . " bytes\n";
        break;
    } else {
        echo "File not found at: {$path}\n";
    }
}

echo "\nDone.\n";
