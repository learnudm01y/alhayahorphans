<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 أعمدة جدول enhanced_attachments:\n\n";

$columns = DB::select('DESCRIBE enhanced_attachments');
foreach($columns as $col) {
    echo "📋 " . $col->Field . " - " . $col->Type . "\n";
}

echo "\n\n🔍 عينة من البيانات:\n\n";

$sample = DB::table('enhanced_attachments')
    ->where('record_number', '000010')
    ->select('*')
    ->first();

if ($sample) {
    foreach ($sample as $key => $value) {
        echo "🔑 $key: " . (is_string($value) ? substr($value, 0, 100) : $value) . "\n";
    }
} else {
    echo "❌ لا توجد بيانات\n";
}
?>
