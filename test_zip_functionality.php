<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\FolderManagementController;
use Illuminate\Support\Facades\Log;

echo "🧪 Testing ZIP download functionality...\n\n";

try {
    // إنشاء مثيل من الكونترولر
    $controller = new FolderManagementController();

    echo "1. Testing getFolderFiles method...\n";

    // استدعاء private method باستخدام Reflection
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getFolderFiles');
    $method->setAccessible(true);

    $files = $method->invoke($controller, '001623');

    echo "   ✅ Found " . count($files) . " files\n";

    if (count($files) > 0) {
        echo "   📄 Sample file: " . json_encode($files[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        echo "\n2. Testing file path resolution...\n";
        $sampleFile = $files[0];
        $fileData = is_array($sampleFile) ? $sampleFile : (array) $sampleFile;

        $filePath = storage_path('app/public/uploads/001623/' . ($fileData['stored_file_name'] ?? $fileData['original_file_name']));

        echo "   📁 Expected file path: $filePath\n";
        echo "   " . (file_exists($filePath) ? "✅ File exists" : "❌ File NOT found") . "\n";

        echo "\n3. Testing ZIP creation manually...\n";

        $zipFileName = 'test_folder_001623_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // التأكد من وجود مجلد temp
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
            echo "   📁 Created temp directory\n";
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result === TRUE) {
            echo "   ✅ ZIP archive created successfully\n";

            $addedFiles = 0;
            foreach ($files as $file) {
                $fileData = is_array($file) ? $file : (array) $file;
                $filePath = storage_path('app/public/uploads/001623/' . ($fileData['stored_file_name'] ?? $fileData['original_file_name']));

                if (file_exists($filePath)) {
                    $fileName = $fileData['original_file_name'] ?? $fileData['stored_file_name'] ?? ('file_' . $addedFiles);
                    $zip->addFile($filePath, $fileName);
                    $addedFiles++;
                    echo "   📎 Added file: $fileName\n";
                } else {
                    echo "   ❌ File not found: $filePath\n";
                }
            }

            $zip->close();

            echo "   ✅ ZIP closed with $addedFiles files\n";
            echo "   📦 ZIP file size: " . number_format(filesize($zipPath) / 1024, 2) . " KB\n";
            echo "   📍 ZIP location: $zipPath\n";

            // التنظيف
            if (file_exists($zipPath)) {
                unlink($zipPath);
                echo "   🗑️ Test ZIP file cleaned up\n";
            }

        } else {
            echo "   ❌ Failed to create ZIP archive: $result\n";
        }

    } else {
        echo "   ❌ No files found for testing\n";
    }

    echo "\n🎉 Test completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
}
