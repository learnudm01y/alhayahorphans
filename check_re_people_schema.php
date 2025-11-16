<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🗄️ فحص هيكل جدول re_people في قاعدة البيانات\n";
echo str_repeat("=", 80) . "\n\n";

// الحصول على أعمدة الجدول
$columns = DB::select('DESCRIBE re_people');

echo "📋 أعمدة جدول re_people:\n\n";

foreach ($columns as $col) {
    $nullable = $col->Null === 'YES' ? '✓ يمكن أن يكون NULL' : '✗ لا يمكن أن يكون NULL';
    $default = $col->Default !== null ? "Default: {$col->Default}" : 'No Default';

    echo "  {$col->Field}\n";
    echo "    النوع: {$col->Type}\n";
    echo "    {$nullable}\n";
    echo "    {$default}\n";
    echo "\n";
}

echo str_repeat("-", 80) . "\n\n";

// التحقق من العلاقات الخارجية
echo "🔗 فحص المفاتيح الخارجية:\n\n";

$foreignKeys = DB::select("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 're_people'
    AND TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

foreach ($foreignKeys as $fk) {
    echo "  {$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
    echo "    Constraint: {$fk->CONSTRAINT_NAME}\n\n";
}

echo str_repeat("-", 80) . "\n\n";

// فحص البيانات الموجودة
echo "📊 إحصائيات البيانات:\n\n";

$totalRecords = DB::table('re_people')->count();
echo "  إجمالي السجلات: $totalRecords\n\n";

// فحص القيم NULL في الأعمدة المهمة
$columnsToCheck = [
    'person_birth_date',
    'person_age',
    'person_gender',
    'person_health_status',
    'person_note'
];

foreach ($columnsToCheck as $column) {
    $nullCount = DB::table('re_people')->whereNull($column)->count();
    $notNullCount = DB::table('re_people')->whereNotNull($column)->count();
    $percentage = $totalRecords > 0 ? round(($nullCount / $totalRecords) * 100, 2) : 0;

    echo "  $column:\n";
    echo "    NULL: $nullCount ({$percentage}%)\n";
    echo "    NOT NULL: $notNullCount\n\n";
}

echo str_repeat("=", 80) . "\n";
