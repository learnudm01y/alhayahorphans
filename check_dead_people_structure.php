<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "═══════════════════════════════════════════════════════════════\n";
echo "📋 معمارية جدول dead_people\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// جلب الأعمدة
$columns = Schema::getColumnListing('dead_people');

echo "📌 الأعمدة:\n";
foreach ($columns as $col) {
    echo "   - {$col}\n";
}

echo "\n📌 تفاصيل الأعمدة:\n";
$tableInfo = DB::select("DESCRIBE dead_people");
foreach ($tableInfo as $col) {
    echo "   {$col->Field} | {$col->Type} | " . ($col->Null == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

// جلب سجل واحد كمثال
echo "\n📌 مثال على سجل:\n";
$sample = DB::table('dead_people')->first();
if ($sample) {
    foreach ((array)$sample as $key => $value) {
        echo "   {$key}: " . ($value ?? 'NULL') . "\n";
    }
} else {
    echo "   لا توجد سجلات\n";
}

// البحث عن عمود الربط
echo "\n📌 البحث عن عمود الربط (مشابه لـ registration_id):\n";
$linkColumns = ['re_file_id', 'file_id', 'registration_id', 'file_id_number'];
foreach ($linkColumns as $col) {
    if (in_array($col, $columns)) {
        echo "   ✅ وُجد: {$col}\n";
    }
}
