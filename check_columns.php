<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\Schema;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Sponsorships Table Columns ===\n";
$columns = Schema::getColumnListing('sponsorships');
foreach ($columns as $column) {
    echo "- $column\n";
}

echo "\n=== Guardian Bank Accounts Table Columns ===\n";
$columns = Schema::getColumnListing('guardian_bank_accounts');
foreach ($columns as $column) {
    echo "- $column\n";
}
