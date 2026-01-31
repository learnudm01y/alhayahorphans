<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// رقم الملف للاختبار - غيره حسب حاجتك
$testFileNumber = '031844'; // من نتائج الاختبار السابقة

echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='UTF-8'>";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #6c2b6d; border-bottom: 3px solid #6c2b6d; padding-bottom: 10px; }
h2 { color: #333; margin-top: 30px; }
.person { background: #f9f9f9; padding: 15px; margin: 10px 0; border-right: 4px solid #6c2b6d; }
.attachments { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
.attachment-card { border: 1px solid #ddd; padding: 10px; background: white; border-radius: 4px; }
.attachment-card img { width: 100%; height: 150px; object-fit: contain; border: 1px solid #eee; }
.type-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; margin: 5px 0; }
.type-12 { background: #4CAF50; color: white; }
.type-19 { background: #FF5722; color: white; }
.type-other { background: #2196F3; color: white; }
.success { color: green; }
.error { color: red; }
</style>";
echo "</head><body><div class='container'>";

echo "<h1>🔍 اختبار الوثائق - الملف رقم: $testFileNumber</h1>";

// جلب المعيل
$guardian = DB::table('data')
    ->where('internal_file_number', $testFileNumber)
    ->orWhere('external_file_number', $testFileNumber)
    ->where('data_relation_type', 1)
    ->first();

if ($guardian) {
    echo "<h2>👤 المعيل</h2>";
    echo "<div class='person'>";
    echo "<strong>الاسم:</strong> {$guardian->data_first_name} {$guardian->data_family_name}<br>";
    echo "<strong>رقم الهوية:</strong> {$guardian->data_id_number}<br>";

    $guardianAttachments = DB::table('attachments')
        ->where('person_identity_number', $guardian->data_id_number)
        ->get();

    echo "<strong>عدد المرفقات:</strong> " . $guardianAttachments->count() . "<br>";

    if ($guardianAttachments->count() > 0) {
        echo "<div class='attachments'>";
        foreach ($guardianAttachments as $att) {
            echo "<div class='attachment-card'>";

            // تحديد نوع الوثيقة
            $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
            $typeName = $docType ? $docType->description : 'غير معروف';

            $badgeClass = 'type-other';
            if ($att->file_type == '12') $badgeClass = 'type-12';
            elseif ($att->file_type == '19') $badgeClass = 'type-19';

            echo "<span class='type-badge $badgeClass'>النوع: $typeName ($att->file_type)</span><br>";

            // عرض الصورة
            $filePath = $att->file_path;
            $fullPath = '';

            if (str_starts_with($filePath, 'storage/uploads/')) {
                $fullPath = __DIR__ . '/storage/app/public/uploads/' . substr($filePath, 16);
            } elseif (str_starts_with($filePath, 'storage/attachments/')) {
                $fullPath = __DIR__ . '/storage/app/public/attachments/' . substr($filePath, 20);
            } elseif (str_starts_with($filePath, 'storage/')) {
                $fullPath = __DIR__ . '/' . str_replace('storage/', 'storage/app/public/', $filePath);
            }

            if (file_exists($fullPath)) {
                $base64 = base64_encode(file_get_contents($fullPath));
                $mimeType = mime_content_type($fullPath);
                echo "<img src='data:$mimeType;base64,$base64' alt='$typeName'>";
                echo "<small class='success'>✓ موجود</small><br>";
            } else {
                echo "<div style='height:150px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;'>لا توجد صورة</div>";
                echo "<small class='error'>✗ غير موجود</small><br>";
            }

            echo "<small>$att->stored_file_name</small>";
            echo "</div>";
        }
        echo "</div>";
    }

    echo "</div>";
}

// جلب الأيتام
$orphans = DB::table('re_people')
    ->where('file_number', $testFileNumber)
    ->get();

if ($orphans->count() > 0) {
    echo "<h2>👨‍👩‍👧‍👦 الأيتام (عدد: {$orphans->count()})</h2>";

    foreach ($orphans as $orphan) {
        echo "<div class='person'>";
        echo "<strong>الاسم:</strong> {$orphan->first_name} {$orphan->family_name}<br>";
        echo "<strong>رقم الهوية:</strong> {$orphan->person_id}<br>";

        $orphanAttachments = DB::table('attachments')
            ->where('person_identity_number', $orphan->person_id)
            ->get();

        echo "<strong>عدد المرفقات:</strong> " . $orphanAttachments->count() . "<br>";

        if ($orphanAttachments->count() > 0) {
            echo "<div class='attachments'>";
            foreach ($orphanAttachments as $att) {
                echo "<div class='attachment-card'>";

                $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
                $typeName = $docType ? $docType->description : 'غير معروف';

                $badgeClass = 'type-other';
                if ($att->file_type == '12') $badgeClass = 'type-12';
                elseif ($att->file_type == '19') $badgeClass = 'type-19';

                echo "<span class='type-badge $badgeClass'>النوع: $typeName ($att->file_type)</span><br>";

                $filePath = $att->file_path;
                $fullPath = '';

                if (str_starts_with($filePath, 'storage/uploads/')) {
                    $fullPath = __DIR__ . '/storage/app/public/uploads/' . substr($filePath, 16);
                } elseif (str_starts_with($filePath, 'storage/attachments/')) {
                    $fullPath = __DIR__ . '/storage/app/public/attachments/' . substr($filePath, 20);
                } elseif (str_starts_with($filePath, 'storage/')) {
                    $fullPath = __DIR__ . '/' . str_replace('storage/', 'storage/app/public/', $filePath);
                }

                if (file_exists($fullPath)) {
                    $base64 = base64_encode(file_get_contents($fullPath));
                    $mimeType = mime_content_type($fullPath);
                    echo "<img src='data:$mimeType;base64,$base64' alt='$typeName'>";
                    echo "<small class='success'>✓ موجود</small><br>";
                } else {
                    echo "<div style='height:150px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;'>لا توجد صورة</div>";
                    echo "<small class='error'>✗ غير موجود</small><br>";
                }

                echo "<small>$att->stored_file_name</small>";
                echo "</div>";
            }
            echo "</div>";
        }

        echo "</div>";
    }
}

echo "</div></body></html>";
