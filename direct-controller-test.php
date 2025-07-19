<?php
// تشخيص مباشر دون Laravel route - فحص الـ Controller مباشرة

require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 تشخيص مباشر لـ FolderManagementController\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // بدء Laravel
    $app = require_once __DIR__ . '/bootstrap/app.php';

    // إنشاء instance من Controller مباشرة
    $controller = new App\Http\Controllers\Admin\FolderManagementController();

    // محاكاة Request
    $request = new Illuminate\Http\Request();
    $request->merge(['folder' => '000072', 'type' => 'images']);

    // استدعاء method مباشرة باستخدام ReflectionClass
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getFolderContents');
    $method->setAccessible(true);

    echo "📊 اختبار getFolderContents مباشرة...\n";

    $result = $method->invoke($controller, $request);
    $data = $result->getData(true);

    if ($data && isset($data['files'])) {
        echo "✅ عدد الملفات: " . count($data['files']) . "\n\n";

        foreach (array_slice($data['files'], 0, 3) as $index => $file) {
            echo "📄 الملف " . ($index + 1) . ":\n";
            echo "   - الاسم: " . ($file['original_file_name'] ?? 'غير محدد') . "\n";
            echo "   - download_url: " . ($file['download_url'] ?? '❌ غير موجود') . "\n";
            echo "   - file_path: " . ($file['file_path'] ?? 'غير محدد') . "\n";
            echo "   - stored_file_name: " . ($file['stored_file_name'] ?? 'غير محدد') . "\n";

            if (isset($file['download_url'])) {
                echo "   - ✅ download_url موجود\n";
            } else {
                echo "   - 🚨 download_url مفقود!\n";
            }
            echo "\n";
        }

        // عد الملفات بدون URL
        $missingUrls = 0;
        foreach ($data['files'] as $file) {
            if (empty($file['download_url'])) {
                $missingUrls++;
            }
        }

        if ($missingUrls > 0) {
            echo "🚨 مشكلة: {$missingUrls} ملف بدون download_url من " . count($data['files']) . "\n";
        } else {
            echo "✅ جميع الملفات لديها download_url\n";
        }

    } else {
        echo "❌ لا توجد ملفات\n";
        echo "الاستجابة الكاملة:\n";
        print_r($data);
    }

} catch (Exception $e) {
    echo "💥 خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

// اختبار إضافي - فحص ملفات حقيقية
echo "🔍 فحص الملفات الفعلية في storage/uploads/000072/\n";
$folderPath = __DIR__ . '/storage/app/public/uploads/000072';
$publicPath = __DIR__ . '/public/storage/uploads/000072';

echo "مجلد storage: " . ($folderPath . (is_dir($folderPath) ? ' ✅' : ' ❌')) . "\n";
echo "مجلد public: " . ($publicPath . (is_dir($publicPath) ? ' ✅' : ' ❌')) . "\n";

if (is_dir($publicPath)) {
    $files = glob($publicPath . '/*');
    echo "عدد الملفات في public: " . count($files) . "\n";
    foreach (array_slice($files, 0, 3) as $file) {
        echo "  - " . basename($file) . "\n";
    }
}

if (is_dir($folderPath)) {
    $files = glob($folderPath . '/*');
    echo "عدد الملفات في storage: " . count($files) . "\n";
    foreach (array_slice($files, 0, 3) as $file) {
        echo "  - " . basename($file) . "\n";
    }
}
?>
