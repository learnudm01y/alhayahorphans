<?php
/**
 * سكريپت التحقق الشامل النهائي وإصلاح مشكلة عدم ظهور بيانات Excel
 * يجب تشغيله مرة واحدة فقط بعد رفع التحديثات الجديدة
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== التحقق والإصلاح النهائي لمشكلة Excel ===\n";
echo "تاريخ التشغيل: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. البحث عن حالة "مقبول"
    echo "1. البحث عن حالة 'مقبول':\n";

    $acceptedStatus = DB::table('request_status')
        ->where('description', 'مقبول')
        ->first();

    if (!$acceptedStatus) {
        echo "❌ خطأ حرج: لم يتم العثور على حالة 'مقبول' في جدول request_status\n";
        echo "يجب إضافة السجل التالي يدوياً:\n";
        echo "INSERT INTO request_status (description) VALUES ('مقبول');\n";
        exit(1);
    }

    echo "   ✅ تم العثور على حالة 'مقبول' بـ ID: {$acceptedStatus->id}\n";

    // 2. فحص السجلات المستوردة من Excel
    echo "\n2. فحص السجلات المستوردة من Excel:\n";

    $excelRecords = DB::table('data')
        ->whereNotNull('original_file_id_from_excel')
        ->select('id', 'file_id_number', 'data_request_status', 'original_file_id_from_excel')
        ->get();

    echo "   عدد السجلات المستوردة من Excel: " . $excelRecords->count() . "\n";

    if ($excelRecords->isEmpty()) {
        echo "   ⚠️ لا توجد سجلات مستوردة من Excel حالياً\n";
        echo "   هذا طبيعي إذا لم يتم استيراد أي ملفات بعد\n";
    } else {
        // فحص حالة كل سجل
        $correctRecords = 0;
        $incorrectRecords = 0;
        $recordsToFix = [];

        foreach ($excelRecords as $record) {
            if ($record->data_request_status == $acceptedStatus->id) {
                $correctRecords++;
            } else {
                $incorrectRecords++;
                $recordsToFix[] = $record->id;
            }
        }

        echo "   السجلات بحالة صحيحة: {$correctRecords}\n";
        echo "   السجلات تحتاج إصلاح: {$incorrectRecords}\n";

        // 3. إصلاح السجلات غير الصحيحة
        if ($incorrectRecords > 0) {
            echo "\n3. إصلاح السجلات غير الصحيحة:\n";

            DB::beginTransaction();

            $updatedCount = DB::table('data')
                ->whereIn('id', $recordsToFix)
                ->update([
                    'data_request_status' => $acceptedStatus->id,
                    'updated_at' => now()
                ]);

            echo "   ✅ تم إصلاح {$updatedCount} سجل\n";

            DB::commit();
        }
    }

    // 4. فحص الـ DataTable query
    echo "\n4. فحص إعدادات DataTable:\n";

    $visibleRecords = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('request_status.description', 'مقبول')
        ->count();

    $visibleExcelRecords = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('request_status.description', 'مقبول')
        ->whereNotNull('data.original_file_id_from_excel')
        ->count();

    echo "   إجمالي السجلات المرئية في DataTable: {$visibleRecords}\n";
    echo "   السجلات المستوردة من Excel المرئية: {$visibleExcelRecords}\n";

    // 5. فحص تكوين Laravel
    echo "\n5. فحص تكوين Laravel:\n";

    // فحص وجود Observer
    $observerExists = file_exists(app_path('Observers/DataExcelObserver.php'));
    echo "   DataExcelObserver موجود: " . ($observerExists ? "✅ نعم" : "❌ لا") . "\n";

    // فحص وجود ServiceProvider
    $providerExists = file_exists(app_path('Providers/DataObserverServiceProvider.php'));
    echo "   DataObserverServiceProvider موجود: " . ($providerExists ? "✅ نعم" : "❌ لا") . "\n";

    // فحص تسجيل ServiceProvider في config
    $configContent = file_get_contents(config_path('app.php'));
    $providerRegistered = strpos($configContent, 'DataObserverServiceProvider') !== false;
    echo "   ServiceProvider مسجل في config: " . ($providerRegistered ? "✅ نعم" : "❌ لا") . "\n";

    // 6. تقرير نهائي
    echo "\n6. التقرير النهائي:\n";

    if ($excelRecords->count() > 0 && $visibleExcelRecords === $excelRecords->count()) {
        echo "   ✅ جميع السجلات المستوردة من Excel تظهر بشكل صحيح\n";
        echo "   ✅ المشكلة تم حلها بالكامل\n";
        echo "   ✅ أي ملفات Excel جديدة ستظهر تلقائياً\n";
    } else if ($excelRecords->count() === 0) {
        echo "   ℹ️ لا توجد ملفات Excel مستوردة حالياً للاختبار\n";
        echo "   ✅ النظام جاهز لاستقبال ملفات Excel جديدة\n";
    } else {
        echo "   ⚠️ لا تزال هناك مشكلة في ظهور بعض السجلات\n";
        echo "   تحقق من:\n";
        echo "     - رفع جميع الملفات المحدثة للخادم\n";
        echo "     - مسح cache Laravel: php artisan cache:clear\n";
        echo "     - إعادة تشغيل الخادم إذا أمكن\n";
    }

    // 7. معلومات إضافية للمطور
    echo "\n7. معلومات تقنية:\n";
    echo "   Environment: " . app()->environment() . "\n";
    echo "   Database: " . config('database.default') . "\n";
    echo "   Status ID لحالة 'مقبول': {$acceptedStatus->id}\n";

    echo "\n=== انتهى التحقق النهائي ===\n";

} catch (Exception $e) {
    echo "❌ خطأ أثناء التحقق: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
