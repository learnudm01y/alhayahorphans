<?php
/**
 * اختبار مباشر لتوليد تقرير العائلة كما في النظام الفعلي
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// المعيل رقم 259 (الذي لديه مرفقات)
$id = 259;

$data = Data::with([
    'section',
    'requestStatus',
    'categoryOfRelation',
    'healthStatus',
    'city',
    'province',
    'attachments',
    'rePeople.healthStatus',
    'rePeople.guaranteeType',
    'rePeople.sponsorshipStatus',
    'rePeople.attachments',
])->findOrFail($id);

$allFamilyMembers = $data->rePeople;

// اختيار أول يتيم كمثال
$selectedMember = $allFamilyMembers->first();

if (!$selectedMember) {
    echo "لا يوجد أيتام لهذا المعيل!";
    exit;
}

$familyMembers = collect([$selectedMember])->merge(
    $allFamilyMembers->filter(function($member) use ($selectedMember) {
        return $member->id !== $selectedMember->id;
    })
);

// جمع جميع المرفقات
$allAttachments = collect();
$allAttachments = $allAttachments->merge($data->attachments);
foreach ($familyMembers as $member) {
    if ($member->attachments) {
        $allAttachments = $allAttachments->merge($member->attachments);
    }
}

// تنظيم الوثائق
$personalPhotos = collect();
$otherDocuments = collect();

$documentTypes = DB::table('document_types')->get()->keyBy('pref');

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

    if (!$isFullBodyPhoto && $attachment->stored_file_name) {
        $fileName = strtolower($attachment->stored_file_name);
        $isFullBodyPhoto = str_starts_with($fileName, '19_');
    }

    if ($isPersonalPhoto && !$isFullBodyPhoto) {
        $personalPhotos->push($attachment);
    } elseif (!$isFullBodyPhoto) {
        $otherDocuments->push($attachment);
    }
}

$documentImages = $allAttachments->filter(function($attachment) {
    return Str::endsWith(strtolower($attachment->stored_file_name), ['jpg', 'jpeg', 'png', 'gif']);
});

echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='UTF-8'>";
echo "<link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>";
echo "<style>
body { font-family: 'Cairo', sans-serif; padding: 20px; background: #f5f5f5; direction: rtl; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1 { color: #6c2b6d; text-align: center; border-bottom: 3px solid #6c2b6d; padding-bottom: 15px; }
.data-section { margin: 30px 0; }
.data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
.data-table th { background: #6c2b6d; color: white; padding: 12px; border: 1px solid #ddd; }
.data-table td { padding: 10px; border: 1px solid #ddd; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
.photo-card { border: 2px solid #ddd; padding: 15px; background: #f9f9f9; text-align: center; border-radius: 8px; }
.photo-card img { width: 100%; height: 250px; object-fit: contain; border: 1px solid #ccc; }
.info-box { background: #e3f2fd; padding: 15px; margin: 15px 0; border-right: 4px solid #2196F3; }
</style></head><body><div class='container'>";

echo "<h1>📊 اختبار تقرير العائلة</h1>";

echo "<div class='data-section'>";
echo "<h2>المعيل: {$data->data_first_name} {$data->data_family_name}</h2>";
echo "<p><strong>رقم الهوية:</strong> {$data->data_id_number}</p>";
echo "<p><strong>عدد مرفقات المعيل:</strong> " . $data->attachments->count() . "</p>";
echo "</div>";

echo "<div class='data-section'>";
echo "<h2>اليتيم المحدد: {$selectedMember->first_name} {$selectedMember->family_name}</h2>";
echo "<p><strong>رقم الهوية:</strong> {$selectedMember->person_id}</p>";
echo "<p><strong>عدد مرفقات اليتيم:</strong> " . $selectedMember->attachments->count() . "</p>";
echo "</div>";

echo "<div class='info-box'>";
echo "<h3>📈 إحصائيات المرفقات</h3>";
echo "<p><strong>إجمالي المرفقات:</strong> " . $allAttachments->count() . "</p>";
echo "<p><strong>الصور الشخصية المفلترة:</strong> <span class='success'>" . $personalPhotos->count() . "</span></p>";
echo "<p><strong>الوثائق الأخرى:</strong> " . $otherDocuments->count() . "</p>";
echo "<p><strong>الصور فقط (jpg,png,gif):</strong> " . $documentImages->count() . "</p>";
echo "</div>";

if ($personalPhotos->count() > 0) {
    echo "<div class='data-section'>";
    echo "<h2>🖼️ الصور الشخصية (ستظهر في التقرير)</h2>";
    echo "<div class='photo-grid'>";

    foreach ($personalPhotos as $photo) {
        $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $photo->file_path));

        echo "<div class='photo-card'>";
        $docType = $documentTypes->get($photo->file_type);
        echo "<h4>النوع {$photo->file_type}: " . ($docType ? $docType->description : 'غير معروف') . "</h4>";

        if (file_exists($fullPath)) {
            $base64 = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);
            echo "<img src='data:{$mimeType};base64,{$base64}'>";
            echo "<p class='success'>✅ الملف موجود</p>";
        } else {
            echo "<div style='width:100%; height:250px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border:1px solid #ccc;'>الملف غير موجود</div>";
            echo "<p class='error'>❌ الملف غير موجود</p>";
            echo "<p><small>{$fullPath}</small></p>";
        }

        echo "<p><small>{$photo->stored_file_name}</small></p>";
        echo "<p><small>الشخص: {$photo->person_identity_number}</small></p>";
        echo "</div>";
    }

    echo "</div>";
    echo "</div>";
} else {
    echo "<div class='info-box' style='background:#ffebee; border-color:#f44336;'>";
    echo "<p class='error'>⚠️ لا توجد صور شخصية! تأكد من أن الأنواع 3 أو 12 موجودة في المرفقات.</p>";
    echo "</div>";
}

if ($otherDocuments->count() > 0) {
    echo "<div class='data-section'>";
    echo "<h2>📄 الوثائق الأخرى</h2>";
    echo "<table class='data-table'>";
    echo "<tr><th>النوع</th><th>الوصف</th><th>اسم الملف</th><th>الحالة</th></tr>";

    foreach ($otherDocuments as $doc) {
        $docType = $documentTypes->get($doc->file_type);
        $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $doc->file_path));

        echo "<tr>";
        echo "<td>{$doc->file_type}</td>";
        echo "<td>" . ($docType ? $docType->description : 'غير معروف') . "</td>";
        echo "<td><small>{$doc->stored_file_name}</small></td>";
        echo "<td>" . (file_exists($fullPath) ? "<span class='success'>✅ موجود</span>" : "<span class='error'>❌ غير موجود</span>") . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";
}

echo "</div></body></html>";
