<?php
/**
 * اختبار نهائي لتوليد PDF مع الصور
 * هذا السكريبت يحاكي بالضبط ما يفعله الكونترولر
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

echo "🔍 بدء اختبار توليد PDF..." . PHP_EOL;

// البحث عن معيل لديه مرفقات
$guardian = Data::has('attachments')->with([
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
])->first();

if (!$guardian) {
    echo "❌ لم يتم العثور على معيل لديه مرفقات!" . PHP_EOL;
    exit(1);
}

echo "✅ تم العثور على معيل: {$guardian->data_first_name} {$guardian->data_family_name}" . PHP_EOL;
echo "   رقم الهوية: {$guardian->data_id_number}" . PHP_EOL;
echo "   عدد المرفقات: " . $guardian->attachments->count() . PHP_EOL;

$allFamilyMembers = $guardian->rePeople;
echo "   عدد الأيتام: " . $allFamilyMembers->count() . PHP_EOL;

if ($allFamilyMembers->count() == 0) {
    echo "❌ لا يوجد أيتام لهذا المعيل!" . PHP_EOL;
    exit(1);
}

$selectedMember = $allFamilyMembers->first();
echo "   اليتيم المحدد: {$selectedMember->first_name} {$selectedMember->family_name}" . PHP_EOL;

$familyMembers = collect([$selectedMember])->merge(
    $allFamilyMembers->filter(function($member) use ($selectedMember) {
        return $member->id !== $selectedMember->id;
    })
);

// جمع المرفقات
$allAttachments = collect();
$allAttachments = $allAttachments->merge($guardian->attachments);
foreach ($familyMembers as $member) {
    if ($member->attachments) {
        $allAttachments = $allAttachments->merge($member->attachments);
    }
}

echo "   إجمالي المرفقات: " . $allAttachments->count() . PHP_EOL;

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
        // النوع 12 فقط = صور شخصية
        $isPersonalPhoto = str_contains($description, 'صور شخصية') ||
                          $attachment->file_type == '12';
        $isFullBodyPhoto = str_contains($description, 'صورة طولية') ||
                          $attachment->file_type == '19';
    }

    if (!$isPersonalPhoto && $attachment->stored_file_name) {
        $fileName = strtolower($attachment->stored_file_name);
        // الملفات التي تبدأ بـ 12_ فقط
        $isPersonalPhoto = str_starts_with($fileName, '12_');
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

echo "   الصور الشخصية: " . $personalPhotos->count() . PHP_EOL;
echo "   الوثائق الأخرى: " . $otherDocuments->count() . PHP_EOL;

if ($personalPhotos->count() == 0) {
    echo "⚠️  تحذير: لا توجد صور شخصية!" . PHP_EOL;
}

// التحقق من وجود الصور فعلياً
$missingFiles = 0;
foreach ($personalPhotos as $photo) {
    $filePath = $photo->file_path;
    if (str_starts_with($filePath, 'storage/uploads/')) {
        $fullPath = base_path('storage/app/public/uploads/' . substr($filePath, 16));
    } elseif (str_starts_with($filePath, 'storage/attachments/')) {
        $fullPath = base_path('storage/app/public/attachments/' . substr($filePath, 20));
    } elseif (str_starts_with($filePath, 'storage/')) {
        $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $filePath));
    } else {
        $fullPath = public_path($filePath);
    }

    if (!file_exists($fullPath)) {
        echo "   ❌ ملف غير موجود: {$photo->stored_file_name}" . PHP_EOL;
        $missingFiles++;
    } else {
        echo "   ✅ ملف موجود: {$photo->stored_file_name}" . PHP_EOL;
    }
}

if ($missingFiles > 0) {
    echo "⚠️  عدد الملفات المفقودة: {$missingFiles}" . PHP_EOL;
}

$documentImages = $allAttachments->filter(function($attachment) {
    return Str::endsWith(strtolower($attachment->stored_file_name), ['jpg', 'jpeg', 'png', 'gif']);
});

// صورة الخلفية
$backgroundPath = public_path('background102.jpg');
$backgroundBase64 = '';
if (file_exists($backgroundPath)) {
    $backgroundBase64 = base64_encode(file_get_contents($backgroundPath));
    echo "   ✅ صورة الخلفية موجودة" . PHP_EOL;
} else {
    echo "   ⚠️  صورة الخلفية غير موجودة" . PHP_EOL;
}

echo PHP_EOL . "🔨 بدء توليد PDF..." . PHP_EOL;

try {
    $pdf = PDF::loadView('admin.dashboard.reports.family_report', [
        'guardian' => $guardian,
        'selectedMember' => $selectedMember,
        'familyMembers' => $familyMembers,
        'documentImages' => $documentImages,
        'personalPhotos' => $personalPhotos,
        'otherDocuments' => $otherDocuments,
        'backgroundBase64' => $backgroundBase64
    ]);

    $pdf->setPaper('a4', 'portrait');
    $pdf->setOption('enable-local-file-access', true);
    $pdf->setOption('margin-top', 0);
    $pdf->setOption('margin-bottom', 0);
    $pdf->setOption('margin-left', 0);
    $pdf->setOption('margin-right', 0);

    $outputPath = storage_path('app/test_family_report_' . time() . '.pdf');
    $pdf->save($outputPath);

    echo "✅ تم توليد PDF بنجاح!" . PHP_EOL;
    echo "   المسار: {$outputPath}" . PHP_EOL;
    echo "   الحجم: " . round(filesize($outputPath) / 1024, 2) . " KB" . PHP_EOL;

    echo PHP_EOL . "✨ يمكنك فتح الملف للتحقق من الصور!" . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ خطأ في توليد PDF: " . $e->getMessage() . PHP_EOL;
    echo "   الملف: " . $e->getFile() . PHP_EOL;
    echo "   السطر: " . $e->getLine() . PHP_EOL;
    exit(1);
}
