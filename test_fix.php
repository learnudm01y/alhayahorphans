<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Services\FolderDuplicateDetectionService;

// تحميل Laravel bootstrap
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 اختبار خدمة FolderDuplicateDetectionService...\n";

try {
    // إنشاء مثيل من الخدمة
    $service = new FolderDuplicateDetectionService();
    echo "✅ تم إنشاء الخدمة بنجاح\n";

    // اختبار تحميل class
    $reflection = new ReflectionClass($service);
    $methods = $reflection->getMethods();

    $hasGenerateMethod = false;
    foreach ($methods as $method) {
        if ($method->getName() === 'generateUniqueAttachmentRecordNumber') {
            $hasGenerateMethod = true;
            echo "✅ الدالة generateUniqueAttachmentRecordNumber موجودة\n";
            break;
        }
    }

    if (!$hasGenerateMethod) {
        echo "❌ الدالة generateUniqueAttachmentRecordNumber غير موجودة\n";
    }

    echo "✅ اختبار الخدمة اكتمل بنجاح\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
}
