<?php
// Database structure analysis

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "<html><head><title>🔍 تحليل جداول قاعدة البيانات</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; direction: rtl; }
.table-container { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 8px; }
.success { color: green; } .error { color: red; } .warning { color: orange; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
th { background: #e9e9e9; }
.code { background: #f8f8f8; padding: 10px; border-left: 3px solid #007bff; margin: 10px 0; font-family: monospace; }
</style></head><body>";

echo "<h1>🔍 تحليل جداول قاعدة البيانات</h1>";

try {
    // فحص جدول enhanced_attachments
    echo "<div class='table-container'>";
    echo "<h2>📊 جدول enhanced_attachments</h2>";

    if (Schema::hasTable('enhanced_attachments')) {
        $columns = Schema::getColumnListing('enhanced_attachments');

        echo "<h3>الأعمدة المتوفرة:</h3>";
        echo "<div class='code'>";
        foreach ($columns as $column) {
            echo "• {$column}<br>";
        }
        echo "</div>";

        // عدد السجلات
        $count = DB::table('enhanced_attachments')->count();
        echo "<p><strong>إجمالي السجلات:</strong> {$count}</p>";

        // عينة من البيانات
        if ($count > 0) {
            $sample = DB::table('enhanced_attachments')
                ->select('id', 'original_file_name', 'stored_file_name', 'file_path', 'record_number', 'file_type', 'created_at')
                ->take(5)
                ->get();

            echo "<h3>عينة من البيانات:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>الاسم الأصلي</th><th>الاسم المحفوظ</th><th>المسار</th><th>رقم السجل</th><th>النوع</th><th>التاريخ</th></tr>";

            foreach ($sample as $row) {
                echo "<tr>";
                echo "<td>{$row->id}</td>";
                echo "<td>{$row->original_file_name}</td>";
                echo "<td>{$row->stored_file_name}</td>";
                echo "<td>{$row->file_path}</td>";
                echo "<td>{$row->record_number}</td>";
                echo "<td>{$row->file_type}</td>";
                echo "<td>{$row->created_at}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        // فحص السجلات حسب record_number
        echo "<h3>توزيع السجلات حسب record_number:</h3>";
        $distribution = DB::table('enhanced_attachments')
            ->select('record_number', DB::raw('COUNT(*) as count'))
            ->whereNotNull('record_number')
            ->where('record_number', '!=', '')
            ->groupBy('record_number')
            ->orderBy('count', 'desc')
            ->take(20)
            ->get();

        echo "<table>";
        echo "<tr><th>رقم السجل</th><th>عدد الملفات</th></tr>";
        foreach ($distribution as $dist) {
            echo "<tr><td>{$dist->record_number}</td><td>{$dist->count}</td></tr>";
        }
        echo "</table>";

    } else {
        echo "<div class='error'>❌ جدول enhanced_attachments غير موجود</div>";
    }
    echo "</div>";

    // فحص جدول attachments
    echo "<div class='table-container'>";
    echo "<h2>📊 جدول attachments</h2>";

    if (Schema::hasTable('attachments')) {
        $columns = Schema::getColumnListing('attachments');

        echo "<h3>الأعمدة المتوفرة:</h3>";
        echo "<div class='code'>";
        foreach ($columns as $column) {
            echo "• {$column}<br>";
        }
        echo "</div>";

        // عدد السجلات
        $count = DB::table('attachments')->count();
        echo "<p><strong>إجمالي السجلات:</strong> {$count}</p>";

        // عينة من البيانات
        if ($count > 0) {
            $sample = DB::table('attachments')
                ->select('id', 'person_identity_number', 'stored_file_name', 'file_name', 'file_path', 'file_type', 'uploaded_at')
                ->take(5)
                ->get();

            echo "<h3>عينة من البيانات:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>رقم الهوية</th><th>الاسم المحفوظ</th><th>اسم الملف</th><th>المسار</th><th>النوع</th><th>تاريخ الرفع</th></tr>";

            foreach ($sample as $row) {
                echo "<tr>";
                echo "<td>{$row->id}</td>";
                echo "<td>{$row->person_identity_number}</td>";
                echo "<td>{$row->stored_file_name}</td>";
                echo "<td>{$row->file_name}</td>";
                echo "<td>{$row->file_path}</td>";
                echo "<td>{$row->file_type}</td>";
                echo "<td>{$row->uploaded_at}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

    } else {
        echo "<div class='error'>❌ جدول attachments غير موجود</div>";
    }
    echo "</div>";

    // فحص الملفات الفيزيائية في المجلد 000072
    echo "<div class='table-container'>";
    echo "<h2>📁 فحص الملفات الفيزيائية - المجلد 000072</h2>";

    $physicalPath = public_path('storage/uploads/000072');
    if (is_dir($physicalPath)) {
        $files = scandir($physicalPath);
        $files = array_filter($files, function($file) {
            return !in_array($file, ['.', '..']);
        });

        echo "<p><strong>عدد الملفات الفيزيائية:</strong> " . count($files) . "</p>";

        if (count($files) > 0) {
            echo "<table>";
            echo "<tr><th>اسم الملف</th><th>الحجم</th><th>تاريخ التعديل</th><th>الامتداد</th></tr>";

            foreach ($files as $file) {
                $filePath = $physicalPath . '/' . $file;
                $size = filesize($filePath);
                $modified = date('Y-m-d H:i:s', filemtime($filePath));
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                echo "<tr>";
                echo "<td>{$file}</td>";
                echo "<td>" . number_format($size) . " بايت</td>";
                echo "<td>{$modified}</td>";
                echo "<td>{$extension}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='error'>❌ المجلد الفيزيائي غير موجود: {$physicalPath}</div>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ خطأ</h2>";
    echo "<p>حدث خطأ: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
