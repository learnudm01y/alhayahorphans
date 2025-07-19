<?php
// Debug script لفحص عرض الصور

require_once 'vendor/autoload.php';

// بدء Laravel application
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "<html><head><title>🔍 فحص عرض الصور</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; direction: rtl; }
.debug-item { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 8px; }
.success { color: green; } .error { color: red; } .warning { color: orange; }
img { max-width: 200px; max-height: 150px; border: 2px solid #ddd; margin: 5px; }
.file-info { font-size: 12px; color: #666; }
</style></head><body>";

echo "<h1>🔍 فحص عرض الصور - مجلد 001447</h1>";

try {
    // جلب البيانات من قاعدة البيانات
    $files = DB::table('enhanced_attachments')
        ->where('record_number', '001447')
        ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
        ->orderBy('created_at', 'desc')
        ->take(10)
        ->get();

    echo "<div class='debug-item'>";
    echo "<h2>📊 إحصائيات</h2>";
    echo "<p>عدد الملفات في قاعدة البيانات: " . count($files) . "</p>";
    echo "</div>";

    foreach ($files as $index => $file) {
        echo "<div class='debug-item'>";
        echo "<h3>📄 ملف " . ($index + 1) . ": {$file->original_file_name}</h3>";

        // عرض بيانات الملف
        echo "<div class='file-info'>";
        echo "<strong>اسم الملف الأصلي:</strong> {$file->original_file_name}<br>";
        echo "<strong>اسم الملف المحفوظ:</strong> {$file->stored_file_name}<br>";
        echo "<strong>مسار الملف:</strong> {$file->file_path}<br>";
        echo "<strong>امتداد الملف:</strong> {$file->file_extension}<br>";
        echo "<strong>نوع MIME:</strong> {$file->mime_type}<br>";
        echo "</div>";

        // تجربة مسارات مختلفة
        $fileName = $file->stored_file_name ?: $file->original_file_name;

        $pathsToTry = [
            "storage/uploads/{$file->record_number}/{$fileName}",
            "storage/uploads/{$file->record_number}/images/{$fileName}",
            "storage/uploads/{$file->record_number}/documents/{$fileName}",
            $file->file_path,
            str_replace('storage/', 'storage/', $file->file_path)
        ];

        foreach ($pathsToTry as $pathIndex => $testPath) {
            if (empty($testPath)) continue;

            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($testPath, '/');
            $exists = file_exists($fullPath);
            $url = 'http://127.0.0.1:8000/' . ltrim($testPath, '/');

            echo "<div style='margin: 10px 0; padding: 10px; background: " . ($exists ? '#e8f5e8' : '#ffeaea') . ";'>";
            echo "<strong>مسار " . ($pathIndex + 1) . ":</strong> {$testPath}<br>";
            echo "<strong>المسار الكامل:</strong> {$fullPath}<br>";
            echo "<strong>الحالة:</strong> <span class='" . ($exists ? 'success' : 'error') . "'>" . ($exists ? '✅ موجود' : '❌ غير موجود') . "</span><br>";

            if ($exists && in_array(strtolower($file->file_extension), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                echo "<strong>المعاينة:</strong><br>";
                echo "<img src='{$url}' alt='{$file->original_file_name}' title='{$testPath}'>";
                echo "<br><a href='{$url}' target='_blank'>فتح الصورة في نافذة جديدة</a>";
            }
            echo "</div>";
        }

        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='debug-item error'>";
    echo "<h2>❌ خطأ</h2>";
    echo "<p>حدث خطأ: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
