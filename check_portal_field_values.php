<?php
/**
 * فحص بنية جدول portal_general_registration_field_values
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "📊 بنية جدول portal_general_registration_field_values\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// جلب بنية الجدول
$columns = DB::select('SHOW COLUMNS FROM portal_general_registration_field_values');

echo "📋 الأعمدة:\n";
echo str_repeat("-", 80) . "\n";
printf("%-30s | %-25s | %-6s | %-6s\n", "الحقل", "النوع", "Null", "Key");
echo str_repeat("-", 80) . "\n";

foreach ($columns as $col) {
    printf("%-30s | %-25s | %-6s | %-6s\n",
        $col->Field,
        $col->Type,
        $col->Null,
        $col->Key
    );
}

// جلب بعض السجلات كنماذج
echo "\n\n📋 نماذج من السجلات:\n";
echo str_repeat("-", 80) . "\n";

$samples = DB::table('portal_general_registration_field_values')
    ->limit(10)
    ->get();

if ($samples->count() > 0) {
    foreach ($samples as $row) {
        print_r((array)$row);
        echo "\n";
    }
} else {
    echo "لا توجد سجلات في الجدول\n";
}

// البحث عن أي ارتباط مع الكفالات أو الملفات
echo "\n\n📋 البحث عن روابط مع الجداول الأخرى:\n";
echo str_repeat("-", 80) . "\n";

// فحص الـ Foreign Keys
$foreignKeys = DB::select("
    SELECT
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        TABLE_NAME = 'portal_general_registration_field_values'
        AND TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
");

if (count($foreignKeys) > 0) {
    foreach ($foreignKeys as $fk) {
        echo "FK: {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
    }
} else {
    echo "لا توجد Foreign Keys مباشرة\n";
}

// البحث عن الحقول التي قد تحتوي على معرفات
echo "\n\n📋 القيم الفريدة للحقول المرجعية:\n";
echo str_repeat("-", 80) . "\n";

// التحقق من وجود أعمدة معينة
$columnNames = array_map(function($col) { return $col->Field; }, $columns);

// فحص إذا كان هناك عمود registration أو file أو مشابه
$refColumns = ['registration_id', 'file_id', 'file_number', 're_file_id', 'internal_file_number', 'field_id'];
foreach ($refColumns as $refCol) {
    if (in_array($refCol, $columnNames)) {
        $uniqueValues = DB::table('portal_general_registration_field_values')
            ->select($refCol)
            ->distinct()
            ->limit(20)
            ->pluck($refCol)
            ->toArray();
        echo "{$refCol}: " . implode(', ', array_slice($uniqueValues, 0, 10)) . "\n";
    }
}

echo "\n✅ فحص الجدول اكتمل\n";

// جلب جميع field_key الفريدة
echo "\n\n📋 جميع field_key الفريدة:\n";
echo str_repeat("-", 80) . "\n";

$allKeys = DB::table('portal_general_registration_field_values')
    ->select('field_key')
    ->distinct()
    ->orderBy('field_key')
    ->pluck('field_key')
    ->toArray();

foreach ($allKeys as $key) {
    echo "  - {$key}\n";
}

echo "\nإجمالي: " . count($allKeys) . " field_key\n";
