<?php
/**
 * سكريپت إصلاح دائم لضمان ظهور بيانات Excel في الجداول الصحيحة
 * يجب تشغيله مرة واحدة فقط على الاستضافة
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== الإصلاح الدائم لمشكلة عرض بيانات Excel ===\n";
echo "تاريخ التشغيل: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. العثور على حالة "مقبول"
    $acceptedStatusId = DB::table('request_status')
        ->where('description', 'مقبول')
        ->value('id');

    if (!$acceptedStatusId) {
        echo "❌ خطأ: لم يتم العثور على حالة 'مقبول'\n";
        exit(1);
    }

    echo "1. ✅ حالة 'مقبول' موجودة بـ ID: {$acceptedStatusId}\n";

    // 2. إصلاح جميع السجلات المستوردة من Excel
    echo "\n2. إصلاح السجلات المستوردة من Excel:\n";

    DB::beginTransaction();

    // تحديث جميع السجلات المستوردة من Excel لتصبح "مقبول"
    $updatedCount = DB::table('data')
        ->whereNotNull('original_file_id_from_excel')
        ->update([
            'data_request_status' => $acceptedStatusId,
            'updated_at' => now()
        ]);

    echo "   تم تحديث {$updatedCount} سجل مستورد من Excel\n";

    // 3. إنشاء trigger لضمان تعيين الحالة تلقائياً للسجلات الجديدة
    echo "\n3. إنشاء آلية ضمان تلقائي:\n";

    // حذف trigger إذا كان موجود
    try {
        DB::statement('DROP TRIGGER IF EXISTS excel_import_status_trigger');
    } catch (Exception $e) {
        // تجاهل الخطأ إذا لم يكن موجود
    }

    // إنشاء trigger جديد
    $triggerSQL = "
    CREATE TRIGGER excel_import_status_trigger
    BEFORE INSERT ON data
    FOR EACH ROW
    BEGIN
        IF NEW.original_file_id_from_excel IS NOT NULL
           AND (NEW.data_request_status IS NULL OR NEW.data_request_status = 0) THEN
            SET NEW.data_request_status = {$acceptedStatusId};
        END IF;
    END
    ";

    try {
        DB::statement($triggerSQL);
        echo "   ✅ تم إنشاء trigger تلقائي بنجاح\n";
    } catch (Exception $e) {
        echo "   ⚠️ لا يمكن إنشاء trigger: " . $e->getMessage() . "\n";
        echo "   سيتم الاعتماد على الكود البرمجي\n";
    }

    // 4. إنشاء جدول مساعد لمراقبة الاستيراد
    echo "\n4. إنشاء نظام مراقبة:\n";

    try {
        DB::statement("
        CREATE TABLE IF NOT EXISTS excel_import_monitor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            import_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            records_count INT DEFAULT 0,
            status_assigned VARCHAR(50) DEFAULT 'unknown',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        ");
        echo "   ✅ تم إنشاء جدول المراقبة\n";
    } catch (Exception $e) {
        echo "   ⚠️ جدول المراقبة موجود مسبقاً\n";
    }

    // 5. تسجيل هذا الإصلاح
    DB::table('excel_import_monitor')->insert([
        'import_date' => now(),
        'records_count' => $updatedCount,
        'status_assigned' => "fixed_to_{$acceptedStatusId}",
        'notes' => 'إصلاح دائم - تم تعديل جميع السجلات المستوردة لحالة مقبول',
        'created_at' => now()
    ]);

    DB::commit();

    // 6. التحقق النهائي
    echo "\n5. التحقق النهائي:\n";

    $visibleRecords = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('request_status.description', 'مقبول')
        ->whereNotNull('data.original_file_id_from_excel')
        ->count();

    $totalExcelRecords = DB::table('data')
        ->whereNotNull('original_file_id_from_excel')
        ->count();

    echo "   السجلات المرئية: {$visibleRecords} من {$totalExcelRecords}\n";

    if ($visibleRecords === $totalExcelRecords && $totalExcelRecords > 0) {
        echo "\n✅ تم الإصلاح بنجاح! جميع السجلات المستوردة ستظهر الآن في:\n";
        echo "   - جدول إدارة السجلات (Records Management)\n";
        echo "   - أي ملف Excel جديد سيحصل تلقائياً على حالة 'مقبول'\n";
    } else {
        echo "\n❌ هناك مشكلة لا تزال موجودة\n";
    }

    // 7. تعليمات ما بعد الإصلاح
    echo "\n6. تعليمات مهمة:\n";
    echo "   - لا تحتاج لتشغيل أي سكريپت مرة أخرى\n";
    echo "   - أي ملف Excel جديد سيظهر تلقائياً\n";
    echo "   - إذا لم تظهر السجلات، تحقق من رفع الكود المحدث\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "❌ خطأ أثناء الإصلاح: " . $e->getMessage() . "\n";
}

echo "\n=== انتهى الإصلاح الدائم ===\n";
