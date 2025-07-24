<?php
echo "=== PHP Configuration Check ===\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Input Vars: " . ini_get('max_input_vars') . "\n";
echo "Max File Uploads: " . ini_get('max_file_uploads') . "\n";

echo "\n=== Required Extensions ===\n";
echo "cURL: " . (extension_loaded('curl') ? 'Enabled' : 'Disabled') . "\n";
echo "FileInfo: " . (extension_loaded('fileinfo') ? 'Enabled' : 'Disabled') . "\n";
echo "ZIP: " . (extension_loaded('zip') ? 'Enabled' : 'Disabled') . "\n";
echo "GD: " . (extension_loaded('gd') ? 'Enabled' : 'Disabled') . "\n";
echo "JSON: " . (extension_loaded('json') ? 'Enabled' : 'Disabled') . "\n";

echo "\n=== Laravel Settings ===\n";
echo "Laravel Version: " . app()->version() . "\n";
echo "Environment: " . app()->environment() . "\n";
echo "Debug Mode: " . (config('app.debug') ? 'Enabled' : 'Disabled') . "\n";
echo "APP_URL: " . config('app.url') . "\n";

echo "\n=== Database Connection ===\n";
try {
    DB::connection()->getPdo();
    echo "Database: Connected\n";
} catch (\Exception $e) {
    echo "Database: Failed - " . $e->getMessage() . "\n";
}

echo "\n=== Storage Directory ===\n";
$storagePath = storage_path();
echo "Storage Path: " . $storagePath . "\n";
echo "Storage Writable: " . (is_writable($storagePath) ? 'Yes' : 'No') . "\n";

$uploadsPath = storage_path('app/uploads');
echo "Uploads Path: " . $uploadsPath . "\n";
echo "Uploads Directory Exists: " . (is_dir($uploadsPath) ? 'Yes' : 'No') . "\n";
echo "Uploads Writable: " . (is_writable($uploadsPath) ? 'Yes' : 'No') . "\n";
