<?php
// Debug specific folder 000078

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "<html><head><title>🔍 فحص مجلد 000078</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; direction: rtl; }
.debug-item { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 8px; }
.success { color: green; } .error { color: red; } .warning { color: orange; }
.file-info { font-size: 12px; color: #666; background: #fff; padding: 10px; margin: 5px 0; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔍 فحص مجلد 000078</h1>";

try {
    // فحص قاعدة البيانات
    echo "<div class='debug-item'>";
    echo "<h2>📊 بيانات قاعدة البيانات</h2>";

    $files = DB::table('enhanced_attachments')
        ->where('record_number', '000078')
        ->get();

    echo "<p>عدد السجلات في قاعدة البيانات: " . count($files) . "</p>";

    if (count($files) > 0) {
        echo "<h3>تفاصيل الملفات:</h3>";
        foreach ($files as $file) {
            echo "<div class='file-info'>";
            echo "<strong>ID:</strong> {$file->id}<br>";
            echo "<strong>الاسم الأصلي:</strong> {$file->original_file_name}<br>";
            echo "<strong>الاسم المحفوظ:</strong> {$file->stored_file_name}<br>";
            echo "<strong>المسار:</strong> {$file->file_path}<br>";
            echo "<strong>النوع:</strong> {$file->file_type}<br>";
            echo "<strong>الامتداد:</strong> {$file->file_extension}<br>";
            echo "<strong>MIME:</strong> {$file->mime_type}<br>";
            echo "<strong>محذوف؟:</strong> " . ($file->deleted_at ? 'نعم' : 'لا') . "<br>";
            echo "</div>";
        }
    } else {
        echo "<div class='error'>❌ لا توجد سجلات في قاعدة البيانات للمجلد 000078</div>";
    }
    echo "</div>";

    // فحص الملفات الفيزيائية
    echo "<div class='debug-item'>";
    echo "<h2>📁 الملفات الفيزيائية</h2>";

    $physicalPath = public_path('storage/uploads/000078');
    if (is_dir($physicalPath)) {
        $physicalFiles = scandir($physicalPath);
        $physicalFiles = array_filter($physicalFiles, function($file) {
            return !in_array($file, ['.', '..']);
        });

        echo "<p>عدد الملفات الفيزيائية: " . count($physicalFiles) . "</p>";

        echo "<h3>قائمة الملفات الفيزيائية:</h3>";
        foreach ($physicalFiles as $file) {
            $filePath = $physicalPath . '/' . $file;
            $fileSize = filesize($filePath);
            $fileExt = pathinfo($file, PATHINFO_EXTENSION);

            echo "<div class='file-info'>";
            echo "<strong>اسم الملف:</strong> {$file}<br>";
            echo "<strong>الامتداد:</strong> {$fileExt}<br>";
            echo "<strong>الحجم:</strong> " . number_format($fileSize) . " بايت<br>";
            echo "<strong>URL:</strong> <a href='http://127.0.0.1:8000/storage/uploads/000078/{$file}' target='_blank'>فتح الملف</a><br>";
            echo "</div>";
        }
    } else {
        echo "<div class='error'>❌ المجلد الفيزيائي غير موجود: {$physicalPath}</div>";
    }
    echo "</div>";

    // اختبار API
    echo "<div class='debug-item'>";
    echo "<h2>🔌 اختبار API</h2>";
    echo "<p><a href='/admin/folders/contents?folder=000078&type=images' target='_blank'>اختبار API للمجلد 000078</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='debug-item error'>";
    echo "<h2>❌ خطأ</h2>";
    echo "<p>حدث خطأ: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
