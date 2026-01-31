<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Data;
use Illuminate\Support\Facades\DB;

// استخدام ID المعيل الذي لديه مرفقات
$guardianId = 259;

$guardian = Data::with(['attachments', 'rePeople.attachments'])->find($guardianId);

if (!$guardian) {
    echo "المعيل غير موجود!";
    exit;
}

echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='UTF-8'>";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 20px; background: #f5f5f5; }
.container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #6c2b6d; border-bottom: 3px solid #6c2b6d; padding-bottom: 10px; }
.section { margin: 30px 0; padding: 20px; background: #f9f9f9; border-right: 4px solid #6c2b6d; }
.photo-container { display: inline-block; margin: 10px; padding: 10px; border: 2px solid #ddd; background: white; text-align: center; }
.photo-container img { width: 200px; height: 250px; object-fit: contain; border: 1px solid #eee; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.info { background: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
th { background: #6c2b6d; color: white; }
</style></head><body><div class='container'>";

echo "<h1>اختبار عرض الصور - المعيل: {$guardian->data_first_name} {$guardian->data_family_name}</h1>";
echo "<p><strong>رقم الهوية:</strong> {$guardian->data_id_number}</p>";

// معلومات المعيل
echo "<div class='section'>";
echo "<h2>📋 مرفقات المعيل</h2>";
echo "<p>عدد المرفقات: " . $guardian->attachments->count() . "</p>";

if ($guardian->attachments->count() > 0) {
    $personalPhotos = $guardian->attachments->whereIn('file_type', ['3', '12']);
    echo "<p class='info'>الصور الشخصية (النوع 3 أو 12): " . $personalPhotos->count() . "</p>";

    echo "<table>";
    echo "<tr><th>النوع</th><th>الوصف</th><th>اسم الملف</th><th>المعاينة</th><th>الحالة</th></tr>";

    foreach ($guardian->attachments as $att) {
        $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
        $typeName = $docType ? $docType->description : 'غير معروف';

        echo "<tr>";
        echo "<td>{$att->file_type}</td>";
        echo "<td>{$typeName}</td>";
        echo "<td><small>{$att->stored_file_name}</small></td>";

        // معالجة المسار
        $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $att->file_path));

        echo "<td>";
        if (file_exists($fullPath)) {
            $base64 = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);
            echo "<img src='data:{$mimeType};base64,{$base64}' style='max-width:150px; max-height:150px;'>";
        } else {
            echo "<div style='width:150px; height:150px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;'>لا توجد صورة</div>";
        }
        echo "</td>";

        echo "<td>";
        if (file_exists($fullPath)) {
            echo "<span class='success'>✅ موجود</span><br><small>{$fullPath}</small>";
        } else {
            echo "<span class='error'>❌ غير موجود</span><br><small>{$fullPath}</small>";
        }
        echo "</td>";

        echo "</tr>";
    }

    echo "</table>";
}

echo "</div>";

// الأيتام
echo "<div class='section'>";
echo "<h2>👨‍👩‍👧‍👦 الأيتام</h2>";
echo "<p>عدد الأيتام: " . $guardian->rePeople->count() . "</p>";

foreach ($guardian->rePeople as $index => $orphan) {
    echo "<div style='margin: 20px 0; padding: 15px; background: white; border: 1px solid #ddd;'>";
    echo "<h3>اليتيم " . ($index + 1) . ": {$orphan->first_name} {$orphan->family_name}</h3>";
    echo "<p><strong>رقم الهوية:</strong> {$orphan->person_id}</p>";
    echo "<p>عدد المرفقات: " . $orphan->attachments->count() . "</p>";

    if ($orphan->attachments->count() > 0) {
        $orphanPersonalPhotos = $orphan->attachments->whereIn('file_type', ['3', '12']);
        echo "<p class='info'>الصور الشخصية (النوع 3 أو 12): " . $orphanPersonalPhotos->count() . "</p>";

        echo "<table>";
        echo "<tr><th>النوع</th><th>الوصف</th><th>اسم الملف</th><th>المعاينة</th><th>الحالة</th></tr>";

        foreach ($orphan->attachments as $att) {
            $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
            $typeName = $docType ? $docType->description : 'غير معروف';

            echo "<tr>";
            echo "<td>{$att->file_type}</td>";
            echo "<td>{$typeName}</td>";
            echo "<td><small>{$att->stored_file_name}</small></td>";

            $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $att->file_path));

            echo "<td>";
            if (file_exists($fullPath)) {
                $base64 = base64_encode(file_get_contents($fullPath));
                $mimeType = mime_content_type($fullPath);
                echo "<img src='data:{$mimeType};base64,{$base64}' style='max-width:150px; max-height:150px;'>";
            } else {
                echo "<div style='width:150px; height:150px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;'>لا توجد صورة</div>";
            }
            echo "</td>";

            echo "<td>";
            if (file_exists($fullPath)) {
                echo "<span class='success'>✅ موجود</span>";
            } else {
                echo "<span class='error'>❌ غير موجود</span>";
            }
            echo "</td>";

            echo "</tr>";
        }

        echo "</table>";
    }

    echo "</div>";
}

echo "</div>";

// اختبار عملية الفلترة
echo "<div class='section'>";
echo "<h2>🔍 اختبار منطق الفلترة</h2>";

$allAttachments = collect();
$allAttachments = $allAttachments->merge($guardian->attachments);
foreach ($guardian->rePeople as $member) {
    if ($member->attachments) {
        $allAttachments = $allAttachments->merge($member->attachments);
    }
}

echo "<p>إجمالي المرفقات: " . $allAttachments->count() . "</p>";

// تطبيق نفس منطق الفلترة من الكونترولر
$documentTypes = DB::table('document_types')->get()->keyBy('pref');
$personalPhotos = collect();
$otherDocuments = collect();

foreach ($allAttachments as $attachment) {
    $isPersonalPhoto = false;
    $isFullBodyPhoto = false;

    $docType = $documentTypes->get($attachment->file_type);
    if ($docType) {
        $description = strtolower($docType->description ?? '');
        $isPersonalPhoto = str_contains($description, 'صور شخصية') ||
                          str_contains($description, 'صورة الهوية') ||
                          in_array($attachment->file_type, ['3', '12']);
        $isFullBodyPhoto = str_contains($description, 'صورة طولية') ||
                          $attachment->file_type == '19';
    }

    if (!$isPersonalPhoto && $attachment->stored_file_name) {
        $fileName = strtolower($attachment->stored_file_name);
        $isPersonalPhoto = str_starts_with($fileName, '3_') ||
                          str_starts_with($fileName, '12_');
    }

    if ($isPersonalPhoto && !$isFullBodyPhoto) {
        $personalPhotos->push($attachment);
    } elseif (!$isFullBodyPhoto) {
        $otherDocuments->push($attachment);
    }
}

echo "<p class='info'><strong>الصور الشخصية المفلترة:</strong> " . $personalPhotos->count() . "</p>";
echo "<p class='info'><strong>الوثائق الأخرى:</strong> " . $otherDocuments->count() . "</p>";

if ($personalPhotos->count() > 0) {
    echo "<div style='margin: 20px 0;'>";
    echo "<h3>الصور الشخصية:</h3>";
    foreach ($personalPhotos as $photo) {
        $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $photo->file_path));
        echo "<div class='photo-container'>";
        if (file_exists($fullPath)) {
            $base64 = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);
            echo "<img src='data:{$mimeType};base64,{$base64}'>";
            echo "<p><small>{$photo->stored_file_name}</small></p>";
        } else {
            echo "<div style='width:200px; height:250px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;'>غير موجود</div>";
        }
        echo "</div>";
    }
    echo "</div>";
}

echo "</div>";

echo "</div></body></html>";
