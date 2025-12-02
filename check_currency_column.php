<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$result = DB::select("SHOW COLUMNS FROM sponsors WHERE Field = 'sponsor_bank_account_currency'");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
