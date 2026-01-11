<?php
require __DIR__.'/vendor/autoload.php';
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "فحص جدول bank_names:\n";
echo str_repeat("=", 50) . "\n";

$banks = DB::table('bank_names')->get();
echo "عدد البنوك: " . $banks->count() . "\n\n";

if ($banks->count() > 0) {
    foreach ($banks as $bank) {
        echo "ID: {$bank->id}\n";
        foreach ($bank as $key => $value) {
            if ($key != 'id') {
                echo "  {$key}: {$value}\n";
            }
        }
        echo "\n";
    }
} else {
    echo "⚠️ جدول bank_names فارغ\n";
}
