<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$columns = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM sponsorships WHERE Field = 'updated_by'");
print_r($columns);
