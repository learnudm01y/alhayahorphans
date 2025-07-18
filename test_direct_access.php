<?php

// اختبار الدخول إلى صفحة الإحصائيات الحقيقية مباشرة
require_once 'vendor/autoload.php';

use App\Http\Controllers\UnifiedFileManagementController;
use Illuminate\Http\Request;

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 اختبار وصول مباشر لدالة الإحصائيات الحقيقية\n";
echo "=" . str_repeat("=", 55) . "\n\n";

try {
    // إنشاء instance من الكنترولر
    $controller = new UnifiedFileManagementController();

    echo "📊 استدعاء getRealDuplicateFilesStatistics() مباشرة:\n";
    echo "=" . str_repeat("-", 45) . "\n";

    // إنشاء request object فارغ
    $request = new Request();

    // استدعاء الدالة مباشرة
    $response = $controller->getRealDuplicateFilesStatistics();

    // الحصول على محتوى الاستجابة
    $content = $response->getContent();
    $statusCode = $response->getStatusCode();

    echo "📡 رمز الحالة: $statusCode\n";
    echo "📄 محتوى الاستجابة:\n";
    echo $content . "\n\n";

    // تحليل JSON
    $data = json_decode($content, true);
    if ($data) {
        echo "🔍 تحليل البيانات:\n";
        echo "=" . str_repeat("-", 20) . "\n";

        if (isset($data['success']) && $data['success']) {
            echo "✅ الاستجابة ناجحة\n";
            if (isset($data['data'])) {
                $stats = $data['data'];
                echo "📊 الإحصائيات:\n";
                echo "  • إجمالي الملفات: " . ($stats['total_files'] ?? 'غير محدد') . "\n";
                echo "  • الملفات النشطة: " . ($stats['active_files'] ?? 'غير محدد') . "\n";
                echo "  • الملفات المنتهية: " . ($stats['expired_files'] ?? 'غير محدد') . "\n";
                echo "  • الصور: " . ($stats['total_images'] ?? 'غير محدد') . "\n";
                echo "  • المستندات: " . ($stats['total_documents'] ?? 'غير محدد') . "\n";
                echo "  • المساحة: " . ($stats['total_size'] ?? 'غير محدد') . " بايت\n";
            }
        } else {
            echo "❌ الاستجابة فاشلة\n";
            echo "📝 الرسالة: " . ($data['message'] ?? 'غير محددة') . "\n";
        }
    } else {
        echo "❌ فشل في تحليل JSON\n";
    }

    // اختبار URL كامل
    echo "\n🌐 اختبار URL كامل:\n";
    echo "=" . str_repeat("-", 20) . "\n";

    $fullUrl = 'http://localhost:8000/admin/duplicate-files/real-statistics';
    echo "📡 URL: $fullUrl\n";

    // محاولة الوصول عبر curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $curlResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        echo "❌ خطأ cURL: $curlError\n";
    } else {
        echo "📊 رمز HTTP: $httpCode\n";
        echo "📄 استجابة cURL:\n";
        echo $curlResponse . "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 55) . "\n";
echo "✅ انتهى الاختبار\n";

?>
