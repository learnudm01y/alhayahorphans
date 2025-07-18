<?php

require_once 'vendor/autoload.php';

// اختبار بسيط لـ ZipArchive
$zip = new ZipArchive();
$tempDir = sys_get_temp_dir();
$zipPath = $tempDir . '/test-zip-' . time() . '.zip';

echo "Testing ZipArchive functionality...\n";
echo "Temp directory: " . $tempDir . "\n";
echo "ZIP path: " . $zipPath . "\n";

// التحقق من وجود المجلد المؤقت
if (!is_dir($tempDir)) {
    echo "ERROR: Temp directory does not exist!\n";
    exit;
}

if (!is_writable($tempDir)) {
    echo "ERROR: Temp directory is not writable!\n";
    exit;
}

// إنشاء الملف المضغوط
$result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($result !== TRUE) {
    echo "ERROR: Cannot create ZIP file. Error code: " . $result . "\n";
    switch($result) {
        case ZipArchive::ER_OK:
            echo "No error\n";
            break;
        case ZipArchive::ER_MULTIDISK:
            echo "Multi-disk zip archives not supported\n";
            break;
        case ZipArchive::ER_RENAME:
            echo "Renaming temporary file failed\n";
            break;
        case ZipArchive::ER_CLOSE:
            echo "Closing zip archive failed\n";
            break;
        case ZipArchive::ER_SEEK:
            echo "Seek error\n";
            break;
        case ZipArchive::ER_READ:
            echo "Read error\n";
            break;
        case ZipArchive::ER_WRITE:
            echo "Write error\n";
            break;
        case ZipArchive::ER_CRC:
            echo "CRC error\n";
            break;
        case ZipArchive::ER_ZIPCLOSED:
            echo "Containing zip archive was closed\n";
            break;
        case ZipArchive::ER_NOENT:
            echo "No such file\n";
            break;
        case ZipArchive::ER_EXISTS:
            echo "File already exists\n";
            break;
        case ZipArchive::ER_OPEN:
            echo "Can't open file\n";
            break;
        case ZipArchive::ER_TMPOPEN:
            echo "Failure to create temporary file\n";
            break;
        case ZipArchive::ER_ZLIB:
            echo "Zlib error\n";
            break;
        case ZipArchive::ER_MEMORY:
            echo "Memory allocation failure\n";
            break;
        case ZipArchive::ER_CHANGED:
            echo "Entry has been changed\n";
            break;
        case ZipArchive::ER_COMPNOTSUPP:
            echo "Compression method not supported\n";
            break;
        case ZipArchive::ER_EOF:
            echo "Premature EOF\n";
            break;
        case ZipArchive::ER_INVAL:
            echo "Invalid argument\n";
            break;
        case ZipArchive::ER_NOZIP:
            echo "Not a zip archive\n";
            break;
        case ZipArchive::ER_INTERNAL:
            echo "Internal error\n";
            break;
        case ZipArchive::ER_INCONS:
            echo "Zip archive inconsistent\n";
            break;
        case ZipArchive::ER_REMOVE:
            echo "Can't remove file\n";
            break;
        case ZipArchive::ER_DELETED:
            echo "Entry has been deleted\n";
            break;
        default:
            echo "Unknown error\n";
    }
    exit;
}

// إضافة ملف اختبار
$testContent = "This is a test file content.";
$zip->addFromString('test.txt', $testContent);

echo "Added test file to ZIP\n";

// إغلاق الملف
$closeResult = $zip->close();
if (!$closeResult) {
    echo "ERROR: Cannot close ZIP file!\n";
    echo "ZIP status: " . $zip->status . "\n";
    echo "ZIP status message: " . $zip->getStatusString() . "\n";
} else {
    echo "SUCCESS: ZIP file created successfully!\n";
    echo "File size: " . filesize($zipPath) . " bytes\n";
}

// تنظيف الملف
if (file_exists($zipPath)) {
    unlink($zipPath);
    echo "Cleanup: ZIP file deleted\n";
}

echo "Test completed.\n";
