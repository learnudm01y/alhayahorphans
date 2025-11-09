<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=================================================================\n";
echo "        حذف جميع Foreign Keys (ماعدا رقم الهوية)\n";
echo "        الجداول: data, re_people, dead_people\n";
echo "=================================================================\n\n";

// الحصول على اسم قاعدة البيانات
$database = DB::connection()->getDatabaseName();

$tables = ['data', 're_people', 'dead_people'];
$totalDeleted = 0;
$totalKept = 0;
$allErrors = [];

foreach ($tables as $table) {
    echo "🔍 فحص جدول: {$table}\n";
    echo "-----------------------------------------------------------------\n";

    // الحصول على جميع Foreign Keys
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
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ", [$database, $table]);

    if (empty($foreignKeys)) {
        echo "   ✅ لا توجد Foreign Keys\n\n";
        continue;
    }

    echo "   📊 تم العثور على " . count($foreignKeys) . " Foreign Key\n\n";

    // تصنيف Foreign Keys (للحفظ أو الحذف)
    $toDelete = [];
    $toKeep = [];

    foreach ($foreignKeys as $fk) {
        // الاحتفاظ بالـ Foreign Keys المتعلقة برقم الهوية
        $columnLower = strtolower($fk->COLUMN_NAME);

        if (
            strpos($columnLower, 'id_number') !== false ||
            strpos($columnLower, 'file_id') !== false ||
            $columnLower === 'id_num' ||
            $columnLower === 'ci_id_num'
        ) {
            $toKeep[] = $fk;
        } else {
            $toDelete[] = $fk;
        }
    }

    // عرض ما سيتم الاحتفاظ به
    if (!empty($toKeep)) {
        echo "   ✅ سيتم الاحتفاظ بـ:\n";
        foreach ($toKeep as $fk) {
            echo "      • {$fk->CONSTRAINT_NAME} ({$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME})\n";
            $totalKept++;
        }
        echo "\n";
    }

    // عرض ما سيتم حذفه
    if (!empty($toDelete)) {
        echo "   ❌ سيتم حذف:\n";
        foreach ($toDelete as $fk) {
            echo "      • {$fk->CONSTRAINT_NAME} ({$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME})\n";
        }
        echo "\n";
    } else {
        echo "   ✅ لا يوجد Foreign Keys للحذف\n\n";
    }
}

echo "=================================================================\n";
echo "⚠️  هل تريد حذف Foreign Keys المذكورة أعلاه؟ (y/n): ";
$confirmation = trim(fgets(STDIN));

if (strtolower($confirmation) !== 'y') {
    echo "\n❌ تم إلغاء العملية\n\n";
    exit;
}

echo "\n🔧 بدء حذف Foreign Keys...\n";
echo "=================================================================\n\n";

foreach ($tables as $table) {
    echo "🔧 معالجة جدول: {$table}\n";
    echo "-----------------------------------------------------------------\n";

    // الحصول على Foreign Keys للحذف
    $foreignKeys = DB::select("
        SELECT
            CONSTRAINT_NAME,
            COLUMN_NAME
        FROM
            information_schema.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ", [$database, $table]);

    if (empty($foreignKeys)) {
        echo "   ⏭️  لا توجد Foreign Keys\n\n";
        continue;
    }

    $deletedCount = 0;

    foreach ($foreignKeys as $fk) {
        // تخطي Foreign Keys المتعلقة برقم الهوية
        $columnLower = strtolower($fk->COLUMN_NAME);

        if (
            strpos($columnLower, 'id_number') !== false ||
            strpos($columnLower, 'file_id') !== false ||
            $columnLower === 'id_num' ||
            $columnLower === 'ci_id_num'
        ) {
            echo "   ⏭️  تم تخطي: {$fk->CONSTRAINT_NAME} (رقم هوية)\n";
            continue;
        }

        try {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            echo "   ✅ تم حذف: {$fk->CONSTRAINT_NAME}\n";
            $deletedCount++;
            $totalDeleted++;
        } catch (\Exception $e) {
            echo "   ❌ فشل حذف {$fk->CONSTRAINT_NAME}: {$e->getMessage()}\n";
            $allErrors[] = "{$table}.{$fk->CONSTRAINT_NAME}";
        }
    }

    echo "   📊 تم حذف {$deletedCount} Foreign Key من جدول {$table}\n\n";
}

echo "=================================================================\n";
echo "✅ النتيجة النهائية\n";
echo "=================================================================\n";
echo "   ✅ تم حذف: {$totalDeleted} Foreign Key\n";
echo "   ✅ تم الاحتفاظ بـ: {$totalKept} Foreign Key (أرقام الهوية)\n";

if (!empty($allErrors)) {
    echo "   ❌ فشل حذف: " . count($allErrors) . " Foreign Key\n";
    foreach ($allErrors as $error) {
        echo "      • {$error}\n";
    }
}

echo "\n=================================================================\n";
echo "✅ انتهى - الآن يمكنك إدخال بيانات في الجداول الثلاثة بدون قيود\n";
echo "=================================================================\n\n";

echo "✅ تم الاحتفاظ بجميع Foreign Keys المتعلقة بأرقام الهوية\n";
echo "⚠️  ملاحظة: باقي الحقول لن يتم التحقق منها تلقائياً\n\n";
