<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  فحص بنية جدول sponsors                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$columns = Schema::getColumnListing('sponsors');

echo "أعمدة جدول sponsors:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

foreach ($columns as $column) {
    echo "✓ " . $column . "\n";
}

echo "\nعدد الأعمدة: " . count($columns) . "\n\n";

// فحص أول صف للتعرف على البنية
$firstRow = DB::table('sponsors')->first();

if ($firstRow) {
    echo "مثال على أول سجل:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    foreach ($firstRow as $key => $value) {
        $displayValue = $value ?? 'NULL';
        if (strlen($displayValue) > 50) {
            $displayValue = substr($displayValue, 0, 47) . '...';
        }
        echo sprintf("%-25s: %s\n", $key, $displayValue);
    }
} else {
    echo "⚠️  الجدول فارغ (لا توجد سجلات)\n";
}
