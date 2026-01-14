<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== فحص هيكل قاعدة البيانات ===\n\n";

// فحص جدول data
echo "📋 جدول data:\n";
echo "الأعمدة الموجودة:\n";
$dataColumns = DB::select("SHOW COLUMNS FROM data");
foreach ($dataColumns as $col) {
    if (strpos($col->Field, 'name') !== false || strpos($col->Field, 'personal') !== false) {
        echo "  ✓ {$col->Field} ({$col->Type})\n";
    }
}

// فحص وجود data_personal_name
$hasDataPersonalName = collect($dataColumns)->pluck('Field')->contains('data_personal_name');
echo "\n🔍 العمود data_personal_name: " . ($hasDataPersonalName ? "✅ موجود" : "❌ غير موجود") . "\n";

// فحص جدول dead_people
echo "\n📋 جدول dead_people:\n";
echo "الأعمدة الموجودة:\n";
$deadPeopleColumns = DB::select("SHOW COLUMNS FROM dead_people");
foreach ($deadPeopleColumns as $col) {
    if (strpos($col->Field, 'name') !== false || strpos($col->Field, 'full') !== false) {
        echo "  ✓ {$col->Field} ({$col->Type})\n";
    }
}

// فحص وجود father_full_name و mother_full_name
$hasFatherFullName = collect($deadPeopleColumns)->pluck('Field')->contains('father_full_name');
$hasMotherFullName = collect($deadPeopleColumns)->pluck('Field')->contains('mother_full_name');
echo "\n🔍 العمود father_full_name: " . ($hasFatherFullName ? "✅ موجود" : "❌ غير موجود") . "\n";
echo "🔍 العمود mother_full_name: " . ($hasMotherFullName ? "✅ موجود" : "❌ غير موجود") . "\n";

// فحص جدول guardian_bank_accounts
echo "\n📋 جدول guardian_bank_accounts:\n";
$bankColumns = DB::select("SHOW COLUMNS FROM guardian_bank_accounts");
echo "الأعمدة:\n";
foreach ($bankColumns as $col) {
    echo "  ✓ {$col->Field} ({$col->Type})\n";
}

// فحص المفاتيح الخارجية
echo "\n🔑 المفاتيح الخارجية في guardian_bank_accounts:\n";
$foreignKeys = DB::select("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'guardian_bank_accounts'
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

foreach ($foreignKeys as $fk) {
    echo "  ✓ {$fk->CONSTRAINT_NAME}: {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
}

// فحص تكرار المعيلين في data
echo "\n👥 فحص تكرار المعيلين في جدول data:\n";
$duplicateData = DB::select("
    SELECT data_id_number, COUNT(*) as count, GROUP_CONCAT(file_id_number) as file_ids
    FROM data
    WHERE data_id_number IS NOT NULL AND data_id_number != ''
    GROUP BY data_id_number
    HAVING count > 1
    LIMIT 10
");

if (count($duplicateData) > 0) {
    echo "⚠️ وجدت هويات مكررة:\n";
    foreach ($duplicateData as $dup) {
        echo "  - هوية {$dup->data_id_number}: {$dup->count} سجل (file_ids: {$dup->file_ids})\n";
    }
} else {
    echo "✅ لا يوجد هويات مكررة\n";
}

// فحص تكرار المعيلين في dead_people
echo "\n👥 فحص تكرار المعيلين في جدول dead_people:\n";
$duplicateDead = DB::select("
    SELECT father_id, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM dead_people
    WHERE father_id IS NOT NULL AND father_id != ''
    GROUP BY father_id
    HAVING count > 1
    LIMIT 10
");

if (count($duplicateDead) > 0) {
    echo "⚠️ وجدت هويات آباء مكررة:\n";
    foreach ($duplicateDead as $dup) {
        echo "  - هوية أب {$dup->father_id}: {$dup->count} سجل (ids: {$dup->ids})\n";
    }
} else {
    echo "✅ لا يوجد هويات آباء مكررة\n";
}

echo "\n=== انتهى الفحص ===\n";
