<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 فحص أعمدة جدول data التي تحتوي على 'number'...\n\n";

$columns = DB::select("SHOW COLUMNS FROM data WHERE Field LIKE '%number%'");

echo "📊 الأعمدة الموجودة:\n";
foreach ($columns as $col) {
    echo "  ✅ " . $col->Field . " (" . $col->Type . ")\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// التحقق من وجود الأعمدة المشكوك فيها
$problematicColumns = [
    'data_number_male',
    'data_number_mail',
    'data_number_female'
];

echo "🔍 التحقق من الأعمدة المحددة:\n";
foreach ($problematicColumns as $colName) {
    $exists = DB::select("SHOW COLUMNS FROM data WHERE Field = ?", [$colName]);
    if (!empty($exists)) {
        echo "  ✅ $colName - موجود\n";
    } else {
        echo "  ❌ $colName - غير موجود\n";
    }
}

echo "\n✅ انتهى الفحص\n";
