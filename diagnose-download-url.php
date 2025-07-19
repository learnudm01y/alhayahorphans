<?php
// مباشرة إلى الموضوع - تشخيص مشكلة الـ download_url

require_once __DIR__ . '/vendor/autoload.php';

// بدء Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🔍 تشخيص مشكلة download_url في المودال\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // محاكاة طلب HTTP
    $request = Illuminate\Http\Request::create('/admin/folders/contents?folder=000072&type=images', 'GET');
    $response = $kernel->handle($request);

    echo "📊 حالة الاستجابة: " . $response->getStatusCode() . "\n";

    if ($response->getStatusCode() === 200) {
        $content = $response->getContent();
        $data = json_decode($content, true);

        if ($data && isset($data['files'])) {
            echo "✅ عدد الملفات المسترجعة: " . count($data['files']) . "\n\n";

            // فحص أول 3 ملفات
            foreach (array_slice($data['files'], 0, 3) as $index => $file) {
                echo "📄 الملف " . ($index + 1) . ":\n";
                echo "   - الاسم: " . ($file['original_file_name'] ?? 'غير محدد') . "\n";
                echo "   - download_url: " . ($file['download_url'] ?? '❌ غير موجود') . "\n";
                echo "   - file_path: " . ($file['file_path'] ?? 'غير محدد') . "\n";
                echo "   - stored_file_name: " . ($file['stored_file_name'] ?? 'غير محدد') . "\n";

                // اختبار وجود الملف فعلياً
                if (isset($file['download_url'])) {
                    $urlPath = parse_url($file['download_url'], PHP_URL_PATH);
                    $fullPath = public_path($urlPath);

                    echo "   - مسار الملف الكامل: " . $fullPath . "\n";
                    echo "   - الملف موجود فعلياً: " . (file_exists($fullPath) ? '✅ نعم' : '❌ لا') . "\n";
                } else {
                    echo "   - 🚨 مشكلة: لا يوجد download_url!\n";
                }
                echo "\n";
            }

            // تحليل المشكلة
            $filesWithoutUrl = array_filter($data['files'], function($file) {
                return empty($file['download_url']);
            });

            if (count($filesWithoutUrl) > 0) {
                echo "🚨 مشكلة مكتشفة!\n";
                echo "عدد الملفات بدون download_url: " . count($filesWithoutUrl) . "\n";
                echo "المشكلة في الـ Controller - دالة processFileUrl لا تعمل بشكل صحيح\n\n";
            } else {
                echo "✅ جميع الملفات لديها download_url\n";
                echo "المشكلة قد تكون في JavaScript أو المودال\n\n";
            }

        } else {
            echo "❌ لا توجد ملفات في الاستجابة\n";
            echo "البيانات المُرجعة:\n" . print_r($data, true) . "\n";
        }

    } else {
        echo "❌ خطأ في الطلب: " . $response->getStatusCode() . "\n";
        echo "المحتوى: " . $response->getContent() . "\n";
    }

} catch (Exception $e) {
    echo "💥 خطأ: " . $e->getMessage() . "\n";
    echo "في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🔧 اقتراحات الإصلاح:\n";
echo "1. تحقق من وجود الملفات في storage/uploads/\n";
echo "2. تأكد من دالة processFileUrl تعمل صحيح\n";
echo "3. فحص JavaScript في displayFolderContents\n";
echo "4. تأكد من أن asset() helper يعمل صحيح\n";
?>
