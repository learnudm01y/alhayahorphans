<?php
require_once 'vendor/autoload.php';

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Checking duplicate_files_temp table structure ===\n";

try {
    $columns = Schema::getColumnListing('duplicate_files_temp');
    echo "Columns found: " . count($columns) . "\n";
    foreach ($columns as $column) {
        echo "- " . $column . "\n";
    }

    echo "\n=== Sample data (first 2 rows) ===\n";
    $sample = DB::table('duplicate_files_temp')->limit(2)->get();
    foreach ($sample as $row) {
        echo "ID: " . $row->id . "\n";
        foreach ($row as $key => $value) {
            echo "  $key: " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . "\n";
        }
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
