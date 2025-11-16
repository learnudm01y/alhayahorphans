<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 فحص المفتاح الأساسي لجدول data...\n\n";

$columns = DB::select("SHOW KEYS FROM data WHERE Key_name = 'PRIMARY'");

if (!empty($columns)) {
    echo "✅ المفتاح الأساسي: " . $columns[0]->Column_name . "\n";
} else {
    echo "❌ لا يوجد مفتاح أساسي!\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// التحقق من البيانات المُدخلة
echo "🔍 التحقق من آخر 3 سجلات مُدخلة...\n\n";

$primaryKey = $columns[0]->Column_name ?? 'id';

$lastRecords = DB::table('data')
    ->orderBy($primaryKey, 'desc')
    ->limit(3)
    ->get();

foreach ($lastRecords as $i => $record) {
    echo "السجل #" . ($i + 1) . ":\n";
    echo "  - $primaryKey: " . $record->$primaryKey . "\n";
    echo "  - data_id_number: " . $record->data_id_number . "\n";
    echo "  - data_first_name: " . $record->data_first_name . "\n";
    echo "  - data_birth_date: " . $record->data_birth_date . "\n";
    echo "  - data_request_status: " . ($record->data_request_status ?? 'null') . "\n";
    echo "\n";
}

echo "✅ تم بنجاح!\n";
