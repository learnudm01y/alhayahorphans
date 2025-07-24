<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Checking attachments table columns:\n";

$columns = Schema::getColumnListing('attachments');
echo "Columns found: " . count($columns) . "\n";
foreach ($columns as $column) {
    echo "- {$column}\n";
}

echo "\nChecking enhanced_attachments table columns:\n";
$enhancedColumns = Schema::getColumnListing('enhanced_attachments');
echo "Columns found: " . count($enhancedColumns) . "\n";
foreach ($enhancedColumns as $column) {
    echo "- {$column}\n";
}

echo "\nDone.\n";
