<?php
/**
 * تنظيف جدول migrations وحذف جميع المشاكل
 */

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🧹 تنظيف شامل لجدول migrations...\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    // حذف جميع migrations المتعلقة بالفهارس
    $indexMigrations = [
        '2024_01_01_000001_add_search_indexes_to_persons_table',
        '2025_01_25_000000_add_search_indexes_for_performance',
        '2025_01_26_000001_optimize_persons_table_indexes',
        '2024_01_02_000001_add_fast_indexes_to_persons'
    ];

    echo "حذف migrations الفهارس:\n";
    foreach ($indexMigrations as $migration) {
        $deleted = DB::table('migrations')->where('migration', $migration)->delete();
        echo ($deleted > 0 ? "✅" : "⚠️") . " {$migration}\n";
    }

    echo "\n✅ تم تنظيف جدول migrations بنجاح!\n";
    echo "🎯 النظام جاهز للعمل بالفهارس الموجودة حالياً\n";

    // عرض الفهارس الموجودة
    echo "\n📊 الفهارس المتاحة:\n";
    $indexes = DB::select("SHOW INDEX FROM persons WHERE Key_name != 'PRIMARY'");
    $indexNames = array_unique(array_column($indexes, 'Key_name'));

    foreach ($indexNames as $index) {
        echo "   • {$index}\n";
    }

    echo "\n🎉 عدد الفهارس: " . count($indexNames) . "\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
