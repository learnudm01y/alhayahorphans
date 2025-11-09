<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=================================================================\n";
echo "        حذف جميع Foreign Keys من جدول data\n";
echo "=================================================================\n\n";

// الحصول على اسم قاعدة البيانات
$database = DB::connection()->getDatabaseName();

echo "🔍 البحث عن Foreign Keys في جدول data...\n";
echo "-----------------------------------------------------------------\n";

// الحصول على جميع Foreign Keys في جدول data
$foreignKeys = DB::select("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM
        information_schema.KEY_COLUMN_USAGE
    WHERE
        TABLE_SCHEMA = ?
        AND TABLE_NAME = 'data'
        AND REFERENCED_TABLE_NAME IS NOT NULL
", [$database]);

if (empty($foreignKeys)) {
    echo "   ✅ لا توجد Foreign Keys في جدول data\n\n";
    exit;
}

echo "   📊 تم العثور على " . count($foreignKeys) . " Foreign Key\n\n";

foreach ($foreignKeys as $fk) {
    echo "   • {$fk->CONSTRAINT_NAME}\n";
    echo "     - العمود: {$fk->COLUMN_NAME}\n";
    echo "     - يشير إلى: {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n\n";
}

echo "⚠️  هل تريد حذف جميع هذه Foreign Keys؟ (y/n): ";
$confirmation = trim(fgets(STDIN));

if (strtolower($confirmation) !== 'y') {
    echo "\n❌ تم إلغاء العملية\n\n";
    exit;
}

echo "\n🔧 بدء حذف Foreign Keys...\n";
echo "-----------------------------------------------------------------\n";

$deletedCount = 0;
$errors = [];

foreach ($foreignKeys as $fk) {
    try {
        DB::statement("ALTER TABLE `data` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        echo "   ✅ تم حذف: {$fk->CONSTRAINT_NAME}\n";
        $deletedCount++;
    } catch (\Exception $e) {
        echo "   ❌ فشل حذف {$fk->CONSTRAINT_NAME}: {$e->getMessage()}\n";
        $errors[] = $fk->CONSTRAINT_NAME;
    }
}

echo "\n=================================================================\n";
echo "✅ النتيجة النهائية\n";
echo "=================================================================\n";
echo "   ✅ تم حذف: {$deletedCount}/" . count($foreignKeys) . " Foreign Key\n";

if (!empty($errors)) {
    echo "   ❌ فشل حذف: " . count($errors) . " Foreign Key\n";
    foreach ($errors as $error) {
        echo "      • {$error}\n";
    }
}

echo "\n=================================================================\n";
echo "✅ انتهى - الآن يمكنك إدخال أي قيمة في جدول data بدون قيود\n";
echo "=================================================================\n\n";

echo "⚠️  ملاحظة: هذا سيسمح بإدخال بيانات غير صحيحة (قيم غير موجودة في الجداول المرجعية)\n";
echo "          تأكد من صحة البيانات المدخلة يدوياً\n\n";
