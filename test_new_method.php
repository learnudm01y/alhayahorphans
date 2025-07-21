<?php
/**
 * اختبار الدالة الجديدة getAcceptedStatusId
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ExcelImportService;
use Illuminate\Support\Facades\DB;

echo "=== اختبار دالة getAcceptedStatusId المحسنة ===\n";

// إنشاء مثيل من ExcelImportService
$service = new ExcelImportService();

// استخدام Reflection للوصول إلى الدالة الخاصة
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getAcceptedStatusId');
$method->setAccessible(true);

try {
    // تشغيل الدالة
    $result = $method->invoke($service);

    echo "✅ نجح تشغيل الدالة\n";
    echo "النتيجة: {$result}\n";

    // التحقق من صحة النتيجة
    $statusDescription = DB::table('request_status')
        ->where('id', $result)
        ->value('description');

    if ($statusDescription) {
        echo "وصف الحالة: {$statusDescription}\n";

        if (strpos($statusDescription, 'مقبول') !== false) {
            echo "✅ الدالة تعمل بشكل صحيح - تم العثور على حالة 'مقبول'\n";
        } else {
            echo "⚠️  تم العثور على حالة بديلة: {$statusDescription}\n";
        }
    } else {
        echo "❌ خطأ: الحالة المُرجعة غير موجودة في قاعدة البيانات\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في تشغيل الدالة: " . $e->getMessage() . "\n";
}

echo "\n=== فحص جدول request_status ===\n";
$statuses = DB::table('request_status')->orderBy('id')->get();
foreach ($statuses as $status) {
    echo "ID: {$status->id} - الوصف: {$status->description}\n";
}

echo "\n=== انتهى الاختبار ===\n";
