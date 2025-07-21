<?php
/**
 * سكريپت تشخيص شامل لمشكلة عدم عرض البيانات المستوردة من Excel على الاستضافة
 * يجب تشغيل هذا السكريپت على الاستضافة مباشرة
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تشخيص مشكلة عرض البيانات المستوردة من Excel - الاستضافة ===\n";
echo "تاريخ التشغيل: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. فحص جدول request_status
    echo "1. فحص جدول request_status:\n";
    $statuses = DB::table('request_status')->orderBy('id')->get();
    $acceptedStatusId = null;

    foreach ($statuses as $status) {
        echo "   ID: {$status->id} - الوصف: {$status->description}\n";
        if ($status->description === 'مقبول') {
            $acceptedStatusId = $status->id;
        }
    }

    if ($acceptedStatusId) {
        echo "   ✅ تم العثور على حالة 'مقبول' بـ ID: {$acceptedStatusId}\n";
    } else {
        echo "   ❌ لم يتم العثور على حالة 'مقبول'!\n";
    }

    // 2. فحص السجلات المستوردة من Excel
    echo "\n2. فحص السجلات المستوردة من Excel:\n";
    $excelRecordsCount = DB::table('data')
        ->whereNotNull('original_file_id_from_excel')
        ->count();

    echo "   إجمالي السجلات المستوردة من Excel: {$excelRecordsCount}\n";

    if ($excelRecordsCount > 0) {
        // توزيع السجلات حسب الحالة
        $statusDistribution = DB::table('data')
            ->select('data_request_status', DB::raw('COUNT(*) as count'))
            ->whereNotNull('original_file_id_from_excel')
            ->groupBy('data_request_status')
            ->get();

        echo "   توزيع السجلات حسب الحالة:\n";
        foreach ($statusDistribution as $dist) {
            $statusName = DB::table('request_status')->where('id', $dist->data_request_status)->value('description') ?? 'غير معروف';
            echo "     الحالة {$dist->data_request_status} ({$statusName}): {$dist->count} سجل\n";
        }

        // آخر 5 سجلات مستوردة
        echo "\n   آخر 5 سجلات مستوردة من Excel:\n";
        $latestRecords = DB::table('data')
            ->select('file_id_number', 'data_id_number', 'data_request_status', 'original_file_id_from_excel', 'created_at')
            ->whereNotNull('original_file_id_from_excel')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($latestRecords as $record) {
            $statusName = DB::table('request_status')->where('id', $record->data_request_status)->value('description') ?? 'غير معروف';
            echo "     رقم الملف: {$record->file_id_number} | رقم الهوية: {$record->data_id_number} | الحالة: {$record->data_request_status} ({$statusName}) | التاريخ: {$record->created_at}\n";
        }
    }

    // 3. فحص ما يظهر في DataTable (محاكاة الاستعلام)
    echo "\n3. فحص ما يظهر في DataTable (محاكاة الاستعلام):\n";
    $dataTableQuery = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('request_status.description', 'مقبول')
        ->select('data.*', 'request_status.description as status_name');

    $totalVisibleRecords = $dataTableQuery->count();
    echo "   إجمالي السجلات المرئية في DataTable: {$totalVisibleRecords}\n";

    $visibleExcelRecords = $dataTableQuery
        ->whereNotNull('data.original_file_id_from_excel')
        ->count();
    echo "   السجلات المستوردة من Excel المرئية في DataTable: {$visibleExcelRecords}\n";

    // 4. مقارنة بين الإجمالي والمرئي
    echo "\n4. تحليل المشكلة:\n";
    if ($excelRecordsCount > 0) {
        $visibilityRate = round(($visibleExcelRecords / $excelRecordsCount) * 100, 2);
        echo "   معدل الظهور: {$visibilityRate}% ({$visibleExcelRecords} من {$excelRecordsCount})\n";

        if ($visibleExcelRecords == 0) {
            echo "   ❌ مشكلة خطيرة: لا يظهر أي سجل مستورد من Excel!\n";

            // فحص السبب
            $wrongStatusRecords = DB::table('data')
                ->whereNotNull('original_file_id_from_excel')
                ->where('data_request_status', '!=', $acceptedStatusId)
                ->count();

            if ($wrongStatusRecords > 0) {
                echo "   السبب المحتمل: {$wrongStatusRecords} سجل لها حالة خاطئة (ليست 'مقبول')\n";
            }
        } elseif ($visibleExcelRecords < $excelRecordsCount) {
            echo "   ⚠️  مشكلة جزئية: بعض السجلات لا تظهر\n";
        } else {
            echo "   ✅ جميع السجلات تظهر بشكل صحيح\n";
        }
    }

    // 5. اختبار تعيين الحالة التلقائي
    echo "\n5. اختبار تعيين الحالة التلقائي:\n";
    $testAcceptedStatusId = DB::table('request_status')
        ->where('description', 'مقبول')
        ->value('id');

    if ($testAcceptedStatusId) {
        echo "   ✅ البحث الديناميكي يعمل: تم العثور على ID = {$testAcceptedStatusId}\n";
    } else {
        echo "   ❌ البحث الديناميكي لا يعمل: لم يتم العثور على حالة 'مقبول'\n";
    }

    // 6. فحص إعدادات قاعدة البيانات
    echo "\n6. معلومات قاعدة البيانات:\n";
    $dbName = DB::connection()->getDatabaseName();
    echo "   اسم قاعدة البيانات: {$dbName}\n";

    // 7. اقتراحات الإصلاح
    echo "\n7. اقتراحات الإصلاح:\n";
    if ($excelRecordsCount > 0 && $visibleExcelRecords == 0 && $acceptedStatusId) {
        echo "   🔧 يمكن تشغيل الإصلاح التلقائي:\n";
        echo "      UPDATE data SET data_request_status = {$acceptedStatusId} WHERE original_file_id_from_excel IS NOT NULL;\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ أثناء التشخيص: " . $e->getMessage() . "\n";
    echo "تفاصيل الخطأ: " . $e->getFile() . " في السطر " . $e->getLine() . "\n";
}

echo "\n=== انتهى التشخيص ===\n";
echo "يرجى نسخ هذا التقرير وإرساله للمطور.\n";
