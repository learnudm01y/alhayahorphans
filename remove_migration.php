<?php
/**
 * حذف migration من جدول migrations لتجنب تشغيلها مرة أخرى
 * يستخدم عندما تكون migration قد تم تنفيذها يدوياً أو موجودة بالفعل
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== حذف Migration من الجدول ===\n\n";

try {
    // اسم الـ migration المراد حذفه
    $migrationName = '2025_12_02_035205_add_sponsor_id_to_association_employees';

    // التحقق من وجود Migration في الجدول
    echo "الخطوة 1: التحقق من وجود Migration...\n";

    $exists = DB::table('migrations')
                ->where('migration', $migrationName)
                ->exists();

    if ($exists) {
        echo "Migration موجود في الجدول\n\n";

        // حذف Migration
        echo "الخطوة 2: حذف Migration من الجدول...\n";

        $deleted = DB::table('migrations')
                     ->where('migration', $migrationName)
                     ->delete();

        echo "تم حذف Migration بنجاح\n\n";
    } else {
        echo "Migration غير موجود في الجدول\n\n";
    }

    // عرض جميع migrations الخاصة بـ association_employees
    echo "الخطوة 3: عرض جميع Migrations المتعلقة...\n";

    $relatedMigrations = DB::table('migrations')
                          ->where('migration', 'like', '%association_employees%')
                          ->get();

    if ($relatedMigrations->count() > 0) {
        echo "Migrations الموجودة:\n";
        foreach ($relatedMigrations as $migration) {
            echo "  - {$migration->migration}\n";
        }
    } else {
        echo "لا توجد migrations متعلقة\n";
    }

    echo "\n=== تم الانتهاء بنجاح ===\n";
    echo "\nيمكنك الآن تشغيل: php artisan migrate --force\n";

} catch (\Exception $e) {
    echo "حدث خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
