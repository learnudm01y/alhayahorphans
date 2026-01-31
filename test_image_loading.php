<?php
/**
 * صفحة اختبار لجلب الصور من قاعدة البيانات
 * لفحص نظام تسمية الملفات ومسارات التخزين
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attachment;
use App\Models\Data;
use Illuminate\Support\Facades\DB;

echo "<html dir='rtl'><head><meta charset='UTF-8'><style>
body { font-family: Arial; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
th { background-color: #6c2b6d; color: white; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
img { max-width: 150px; max-height: 150px; border: 2px solid #ccc; }
</style></head><body>";

echo "<h1>اختبار نظام جلب الصور الشخصية</h1>";

// جلب عينة من الملفات
$fileNumber = '000001'; // غير هذا الرقم للاختبار
echo "<h2>اختبار رقم الملف: $fileNumber</h2>";

// جلب بيانات المعيل من هذا الملف
$guardian = Data::where('data_file_number', $fileNumber)
    ->where('data_relation_type', 1)
    ->with('attachments')
    ->first();

if ($guardian) {
    echo "<h3>معلومات المعيل:</h3>";
    echo "<p>الاسم: {$guardian->data_first_name} {$guardian->data_family_name}</p>";
    echo "<p>رقم الهوية: {$guardian->data_id_number}</p>";
    echo "<p>عدد المرفقات: " . $guardian->attachments->count() . "</p>";

    if ($guardian->attachments->count() > 0) {
        echo "<table>";
        echo "<tr><th>اسم الملف</th><th>النوع</th><th>المسار</th><th>الحالة</th><th>المعاينة</th></tr>";

        foreach ($guardian->attachments as $attachment) {
            $filePath = $attachment->file_path;
            $exists = false;
            $fullPath = '';

            // محاولة البحث في مسارات مختلفة
            if (str_starts_with($filePath, 'storage/')) {
                $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $filePath));
            } elseif (str_starts_with($filePath, 'public/')) {
                $fullPath = public_path(substr($filePath, 7));
            } else {
                $fullPath = public_path($filePath);
            }

            $exists = file_exists($fullPath);
            $status = $exists ? '<span class="success">✓ موجود</span>' : '<span class="error">✗ غير موجود</span>';

            echo "<tr>";
            echo "<td>{$attachment->stored_file_name}</td>";
            echo "<td>{$attachment->file_type}</td>";
            echo "<td><small>{$fullPath}</small></td>";
            echo "<td>$status</td>";
            echo "<td>";
            if ($exists) {
                $base64 = base64_encode(file_get_contents($fullPath));
                $mimeType = mime_content_type($fullPath);
                echo "<img src='data:{$mimeType};base64,{$base64}' alt='صورة'>";
            }
            echo "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
} else {
    echo "<p class='warning'>لم يتم العثور على معيل لهذا الملف</p>";
}

// جلب أنواع المستندات
echo "<h3>أنواع المستندات المتاحة:</h3>";
$documentTypes = DB::table('document_types')->get();
if ($documentTypes->count() > 0) {
    echo "<table>";
    echo "<tr><th>الكود</th><th>الوصف</th></tr>";
    foreach ($documentTypes as $type) {
        echo "<tr><td>{$type->pref}</td><td>{$type->description}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>جدول document_types فارغ أو غير موجود</p>";
}

// عرض إحصائيات عامة
echo "<h3>إحصائيات عامة:</h3>";
$totalAttachments = Attachment::count();
$photoAttachments = Attachment::where('file_type', '1')->count();
$filesWithPath = Attachment::whereNotNull('file_path')->count();

echo "<ul>";
echo "<li>إجمالي المرفقات: $totalAttachments</li>";
echo "<li>الصور الشخصية (file_type=1): $photoAttachments</li>";
echo "<li>الملفات ذات المسار: $filesWithPath</li>";
echo "</ul>";

// عرض عينة من أسماء الملفات
echo "<h3>عينة من أسماء الملفات المخزنة:</h3>";
$sampleFiles = Attachment::select('stored_file_name', 'file_type', 'person_identity_number', 'file_path')
    ->limit(20)
    ->get();

if ($sampleFiles->count() > 0) {
    echo "<table>";
    echo "<tr><th>اسم الملف</th><th>النوع</th><th>رقم الهوية</th><th>المسار</th></tr>";
    foreach ($sampleFiles as $file) {
        echo "<tr>";
        echo "<td>{$file->stored_file_name}</td>";
        echo "<td>{$file->file_type}</td>";
        echo "<td>{$file->person_identity_number}</td>";
        echo "<td><small>{$file->file_path}</small></td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</body></html>";
