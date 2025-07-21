<?php
/**
 * سكريپت إصلاح البيانات المستوردة من Excel على الاستضافة
 * يجب تشغيل هذا السكريپت على الاستضافة بعد رفع الكود المحدث
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== إصلاح البيانات المستوردة من Excel على الاستضافة ===\n";
echo "تاريخ التشغيل: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. العثور على ID حالة "مقبول"
    echo "1. البحث عن حالة 'مقبول':\n";
    $acceptedStatusId = DB::table('request_status')
        ->where('description', 'مقبول')
        ->value('id');

    if (!$acceptedStatusId) {
        echo "❌ خطأ فادح: لم يتم العثور على حالة 'مقبول' في قاعدة البيانات!\n";
        echo "يرجى التحقق من جدول request_status والتأكد من وجود حالة بوصف 'مقبول'\n";
        exit(1);
    }

    echo "✅ تم العثور على حالة 'مقبول' بـ ID: {$acceptedStatusId}\n";

    // 2. فحص السجلات المستوردة من Excel
    echo "\n2. فحص السجلات المستوردة من Excel:\n";
    $excelRecords = DB::table('data')
        ->whereNotNull('original_file_id_from_excel')
        ->get(['id', 'file_id_number', 'data_id_number', 'data_request_status', 'original_file_id_from_excel']);

    if ($excelRecords->isEmpty()) {
        echo "⚠️  لا توجد سجلات مستوردة من Excel في قاعدة البيانات.\n";
        echo "تأكد من استيراد البيانات أولاً باستخدام واجهة الاستيراد.\n";
        exit(0);
    }

    echo "إجمالي السجلات المستوردة من Excel: " . $excelRecords->count() . "\n";

    // تجميع حسب الحالة
    $statusGroups = $excelRecords->groupBy('data_request_status');
    echo "توزيع السجلات حسب الحالة:\n";
    foreach ($statusGroups as $statusId => $records) {
        $statusName = DB::table('request_status')->where('id', $statusId)->value('description') ?? 'غير معروف';
        echo "  الحالة {$statusId} ({$statusName}): " . $records->count() . " سجل\n";
    }

    // 3. تحديد السجلات التي تحتاج إصلاح
    $recordsToFix = $excelRecords->where('data_request_status', '!=', $acceptedStatusId);

    if ($recordsToFix->isEmpty()) {
        echo "\n✅ جميع السجلات المستوردة من Excel لها حالة صحيحة!\n";
        echo "لا حاجة للإصلاح.\n";
    } else {
        echo "\n3. إصلاح السجلات ذات الحالة الخاطئة:\n";
        echo "عدد السجلات التي تحتاج إصلاح: " . $recordsToFix->count() . "\n";

        // عرض عينة من السجلات قبل الإصلاح
        echo "\nعينة من السجلات قبل الإصلاح:\n";
        foreach ($recordsToFix->take(5) as $record) {
            $currentStatusName = DB::table('request_status')->where('id', $record->data_request_status)->value('description') ?? 'غير معروف';
            echo "  رقم الملف: {$record->file_id_number} | الحالة الحالية: {$record->data_request_status} ({$currentStatusName})\n";
        }

        echo "\nهل تريد المتابعة مع الإصلاح؟ سيتم تغيير حالة جميع السجلات إلى 'مقبول' (ID: {$acceptedStatusId})\n";
        echo "اكتب 'yes' للمتابعة أو أي شيء آخر للإلغاء: ";

        // في بيئة الاستضافة، سنتابع تلقائياً (يمكن تعديل هذا للتفاعل)
        $confirm = 'yes'; // تلقائي للاستضافة

        if (strtolower(trim($confirm)) === 'yes') {
            // تنفيذ الإصلاح
            DB::beginTransaction();

            try {
                $updatedCount = DB::table('data')
                    ->whereNotNull('original_file_id_from_excel')
                    ->where('data_request_status', '!=', $acceptedStatusId)
                    ->update([
                        'data_request_status' => $acceptedStatusId,
                        'updated_at' => now()
                    ]);

                DB::commit();

                echo "\n✅ تم إصلاح {$updatedCount} سجل بنجاح!\n";

                // التحقق من النتيجة
                $verificationCount = DB::table('data')
                    ->whereNotNull('original_file_id_from_excel')
                    ->where('data_request_status', $acceptedStatusId)
                    ->count();

                echo "السجلات التي لها حالة 'مقبول' الآن: {$verificationCount}\n";

            } catch (Exception $e) {
                DB::rollBack();
                echo "❌ خطأ أثناء الإصلاح: " . $e->getMessage() . "\n";
                exit(1);
            }
        } else {
            echo "تم إلغاء الإصلاح.\n";
            exit(0);
        }
    }

    // 4. التحقق النهائي من الظهور في DataTable
    echo "\n4. التحقق النهائي:\n";
    $visibleRecords = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('request_status.description', 'مقبول')
        ->whereNotNull('data.original_file_id_from_excel')
        ->count();

    $totalExcelRecords = DB::table('data')
        ->whereNotNull('original_file_id_from_excel')
        ->count();

    echo "السجلات المستوردة من Excel المرئية في DataTable: {$visibleRecords}\n";
    echo "إجمالي السجلات المستوردة من Excel: {$totalExcelRecords}\n";

    if ($visibleRecords === $totalExcelRecords && $totalExcelRecords > 0) {
        echo "✅ نجح الإصلاح! جميع السجلات المستوردة من Excel ستظهر الآن في الجدول.\n";
    } elseif ($visibleRecords > 0) {
        echo "⚠️  إصلاح جزئي: {$visibleRecords} من {$totalExcelRecords} سجل سيظهر في الجدول.\n";
    } else {
        echo "❌ الإصلاح لم يعمل: لا تزال السجلات غير مرئية.\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ أثناء الإصلاح: " . $e->getMessage() . "\n";
    echo "تفاصيل الخطأ: " . $e->getFile() . " في السطر " . $e->getLine() . "\n";
}

echo "\n=== انتهى الإصلاح ===\n";
echo "يمكنك الآن التحقق من ظهور البيانات في واجهة إدارة السجلات.\n";
