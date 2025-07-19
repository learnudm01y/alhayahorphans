<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== إنشاء ملفات اختبارية فعلية ===" . PHP_EOL;

// 1. إنشاء صورة اختبارية بسيطة
function createTestImage($filePath) {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // إنشاء صورة بسيطة 200x200 بكسل
    $image = imagecreate(200, 200);
    $background = imagecolorallocate($image, 200, 200, 255); // أزرق فاتح
    $textColor = imagecolorallocate($image, 0, 0, 0); // أسود

    // إضافة نص
    $text = basename($filePath, '.jpg');
    imagestring($image, 5, 20, 90, $text, $textColor);

    // حفظ الصورة
    imagejpeg($image, $filePath, 90);
    imagedestroy($image);

    return file_exists($filePath);
}

// 2. إنشاء ملف PDF اختبار
function createTestPDF($filePath) {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $content = "%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
>>
endobj

4 0 obj
<<
/Length 44
>>
stream
BT
/F1 12 Tf
72 720 Td
(Hello World - " . basename($filePath) . ") Tj
ET
endstream
endobj

xref
0 5
0000000000 65535 f
0000000010 00000 n
0000000053 00000 n
0000000125 00000 n
0000000190 00000 n
trailer
<<
/Size 5
/Root 1 0 R
>>
startxref
284
%%EOF";

    return file_put_contents($filePath, $content) !== false;
}

// 3. إنشاء ملف Excel اختبار
function createTestExcel($filePath) {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // إنشاء ملف CSV بسيط ونحفظه كـ Excel
    $content = "الاسم,العمر,المدينة\n";
    $content .= "أحمد محمد,25,بغداد\n";
    $content .= "فاطمة علي,30,البصرة\n";
    $content .= "محمود حسن,35,أربيل\n";

    return file_put_contents($filePath, $content) !== false;
}

try {
    // إنشاء ملفات للمجلدات الرئيسية
    $testFolders = ['000010', '000014', '000015', '000016', '000017'];

    foreach ($testFolders as $folderNum) {
        echo "إنشاء ملفات للمجلد: " . $folderNum . PHP_EOL;

        $basePath = public_path("storage/uploads/{$folderNum}");

        // إنشاء صورة
        $imagePath = "{$basePath}/images/{$folderNum}_sample.jpg";
        if (createTestImage($imagePath)) {
            echo "  ✅ تم إنشاء الصورة: " . $imagePath . PHP_EOL;
        }

        // إنشاء PDF
        $pdfPath = "{$basePath}/documents/{$folderNum}_sample.pdf";
        if (createTestPDF($pdfPath)) {
            echo "  ✅ تم إنشاء PDF: " . $pdfPath . PHP_EOL;
        }

        // إنشاء Excel
        $excelPath = "{$basePath}/excels/{$folderNum}_sample.xlsx";
        if (createTestExcel($excelPath)) {
            echo "  ✅ تم إنشاء Excel: " . $excelPath . PHP_EOL;
        }

        // تحديث قاعدة البيانات بالملفات الجديدة
        DB::table('enhanced_attachments')->insert([
            [
                'record_number' => $folderNum,
                'stored_file_name' => "{$folderNum}_sample.jpg",
                'original_file_name' => "صورة اختبار {$folderNum}.jpg",
                'file_path' => "storage/uploads/{$folderNum}/images/{$folderNum}_sample.jpg",
                'file_type' => 'image',
                'mime_type' => 'image/jpeg',
                'file_extension' => 'jpg',
                'file_size' => filesize($imagePath),
                'file_hash' => md5_file($imagePath),
                'source' => 'direct_upload',
                'upload_ip_address' => '127.0.0.1',
                'processing_status' => 'completed',
                'access_level' => 'internal',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'record_number' => $folderNum,
                'stored_file_name' => "{$folderNum}_sample.pdf",
                'original_file_name' => "مستند اختبار {$folderNum}.pdf",
                'file_path' => "storage/uploads/{$folderNum}/documents/{$folderNum}_sample.pdf",
                'file_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size' => filesize($pdfPath),
                'file_hash' => md5_file($pdfPath),
                'source' => 'direct_upload',
                'upload_ip_address' => '127.0.0.1',
                'processing_status' => 'completed',
                'access_level' => 'internal',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'record_number' => $folderNum,
                'stored_file_name' => "{$folderNum}_sample.xlsx",
                'original_file_name' => "جدول اختبار {$folderNum}.xlsx",
                'file_path' => "storage/uploads/{$folderNum}/excels/{$folderNum}_sample.xlsx",
                'file_type' => 'excel',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_extension' => 'xlsx',
                'file_size' => filesize($excelPath),
                'file_hash' => md5_file($excelPath),
                'source' => 'direct_upload',
                'upload_ip_address' => '127.0.0.1',
                'processing_status' => 'completed',
                'access_level' => 'internal',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        echo "  ✅ تم تحديث قاعدة البيانات للمجلد: " . $folderNum . PHP_EOL;
        echo "  ---" . PHP_EOL;
    }

    echo PHP_EOL . "🎉 تم إنشاء جميع الملفات الاختبارية بنجاح!" . PHP_EOL;
    echo "الآن ستظهر الصور والملفات في المجلدات." . PHP_EOL;

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "انتهى إنشاء الملفات." . PHP_EOL;
