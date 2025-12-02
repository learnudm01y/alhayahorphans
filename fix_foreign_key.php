<?php
/**
 * حل مشكلة Foreign Key في association_employees
 * قم بتشغيل هذا الملف مباشرة من المتصفح أو من command line
 *
 * Usage: php fix_foreign_key.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== بدء حل مشكلة Foreign Key ===\n\n";

try {
    // الخطوة 1: عرض عدد السجلات الخاطئة
    echo "الخطوة 1: فحص السجلات الخاطئة...\n";

    $badRecords = DB::select("
        SELECT COUNT(*) as count
        FROM association_employees
        WHERE sponsor_id IS NOT NULL
        AND sponsor_id NOT IN (SELECT id FROM sponsors)
    ");

    echo "عدد السجلات الخاطئة: " . $badRecords[0]->count . "\n\n";

    if ($badRecords[0]->count > 0) {
        // الخطوة 2: تحديث السجلات الخاطئة
        echo "الخطوة 2: تحديث السجلات الخاطئة...\n";

        $updated = DB::update("
            UPDATE association_employees
            SET sponsor_id = NULL
            WHERE sponsor_id IS NOT NULL
            AND sponsor_id NOT IN (SELECT id FROM sponsors)
        ");

        echo "تم تحديث {$updated} سجل\n\n";
    }

    // الخطوة 3: حذف Foreign Key القديم
    echo "الخطوة 3: حذف Foreign Key القديم...\n";

    try {
        DB::statement("ALTER TABLE association_employees DROP FOREIGN KEY association_employees_sponsor_id_foreign");
        echo "تم حذف Foreign Key القديم\n\n";
    } catch (\Exception $e) {
        echo "Foreign Key غير موجود أو تم حذفه مسبقاً\n\n";
    }

    // الخطوة 4: إضافة Foreign Key الجديد
    echo "الخطوة 4: إضافة Foreign Key الجديد...\n";

    try {
        DB::statement("
            ALTER TABLE association_employees
            ADD CONSTRAINT association_employees_sponsor_id_foreign
            FOREIGN KEY (sponsor_id)
            REFERENCES sponsors(id)
            ON DELETE CASCADE
        ");
        echo "تم إضافة Foreign Key بنجاح\n\n";
    } catch (\Exception $e) {
        echo "خطأ: " . $e->getMessage() . "\n\n";
    }

    // الخطوة 5: التحقق من النتيجة
    echo "الخطوة 5: التحقق من النتيجة...\n";

    $totalRecords = DB::table('association_employees')->count();
    $nullRecords = DB::table('association_employees')->whereNull('sponsor_id')->count();
    $validRecords = DB::table('association_employees')->whereNotNull('sponsor_id')->count();

    echo "إجمالي السجلات: {$totalRecords}\n";
    echo "سجلات بدون sponsor: {$nullRecords}\n";
    echo "سجلات مع sponsor صحيح: {$validRecords}\n\n";

    echo "=== تم الانتهاء بنجاح ===\n";

} catch (\Exception $e) {
    echo "حدث خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
