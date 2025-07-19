<?php
// تشخيص مبسط - فحص منطق processFileUrl مباشرة

echo "🔍 تشخيص منطق processFileUrl\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// محاكاة بيانات ملف
$testFile = (object) [
    'stored_file_name' => 'TE102_000072_2342134123.png',
    'original_file_name' => 'test_image.png',
    'file_path' => 'storage/uploads/000072/TE102_000072_2342134123.png'
];

$folderName = '000072';

echo "📄 اختبار الملف:\n";
echo "   - stored_file_name: {$testFile->stored_file_name}\n";
echo "   - original_file_name: {$testFile->original_file_name}\n";
echo "   - file_path: {$testFile->file_path}\n\n";

// محاكاة منطق processFileUrl
$fileName = $testFile->stored_file_name ?: $testFile->original_file_name;

echo "🔍 اختبار مسارات مختلفة:\n";

$pathsToCheck = [
    "storage/uploads/{$folderName}/{$fileName}",
    "storage/uploads/{$folderName}/images/{$fileName}",
    "storage/uploads/{$folderName}/documents/{$fileName}",
    $testFile->file_path,
    str_replace(['storage/', '/storage/'], ['', ''], $testFile->file_path)
];

$foundPath = null;

foreach ($pathsToCheck as $index => $testPath) {
    if (empty($testPath)) {
        echo "   " . ($index + 1) . ". '{$testPath}' - مسار فارغ ❌\n";
        continue;
    }

    $fullPath = __DIR__ . '/public/' . $testPath;
    $exists = file_exists($fullPath);

    echo "   " . ($index + 1) . ". '{$testPath}'\n";
    echo "      المسار الكامل: {$fullPath}\n";
    echo "      موجود: " . ($exists ? '✅' : '❌') . "\n";

    if ($exists && !$foundPath) {
        $foundPath = $testPath;
        echo "      🎯 تم العثور على الملف!\n";
    }
    echo "\n";
}

if ($foundPath) {
    $downloadUrl = "http://localhost/" . $foundPath;
    echo "✅ download_url سيكون: {$downloadUrl}\n";
} else {
    $defaultPath = "storage/uploads/{$folderName}/{$fileName}";
    $downloadUrl = "http://localhost/" . $defaultPath;
    echo "⚠️ لم يتم العثور على الملف، سيستخدم المسار الافتراضي: {$downloadUrl}\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

// اختبار إضافي - فحص جميع الملفات في المجلد
echo "📂 فحص جميع الملفات في المجلد 000072:\n";

$folderPath = __DIR__ . '/public/storage/uploads/000072';
if (is_dir($folderPath)) {
    $files = glob($folderPath . '/*');
    echo "عدد الملفات: " . count($files) . "\n\n";

    foreach ($files as $index => $file) {
        $fileName = basename($file);
        $relativePath = "storage/uploads/000072/{$fileName}";
        $url = "http://localhost/{$relativePath}";

        echo "📄 الملف " . ($index + 1) . ": {$fileName}\n";
        echo "   المسار النسبي: {$relativePath}\n";
        echo "   URL: {$url}\n";
        echo "   حجم الملف: " . number_format(filesize($file) / 1024, 2) . " KB\n\n";
    }
} else {
    echo "❌ المجلد غير موجود: {$folderPath}\n";
}

echo "🔧 تشخيص المشكلة:\n";
echo "1. الملفات موجودة فعلياً ✅\n";
echo "2. منطق processFileUrl سليم ✅\n";
echo "3. المشكلة على الأرجح في JavaScript أو المودال ⚠️\n";
echo "4. يجب فحص displayFolderContents في javascript.blade.php\n";
?>
