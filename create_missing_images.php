<?php
// إنشاء الملفات المفقودة

function createTestImage($filename, $text) {
    $image = imagecreate(800, 600);
    $bg = imagecolorallocate($image, 240, 248, 255);
    $text_color = imagecolorallocate($image, 70, 130, 180);

    // Add text
    imagestring($image, 5, 200, 250, $text, $text_color);
    imagestring($image, 3, 300, 300, 'Test Image', $text_color);
    imagestring($image, 2, 350, 330, date('Y-m-d H:i:s'), $text_color);

    // Save image
    $path = "storage/app/public/uploads/000010/images/{$filename}";
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    echo "✅ Created: {$filename}\n";
    return $path;
}

// إنشاء الملفات المفقودة
echo "🔧 إنشاء الملفات المفقودة...\n\n";

$files = [
    '000010_file_1_20250718195929.jpg' => 'File 1',
    '000010_file_2_20250718195929.jpg' => 'File 2',
    '000010_test_image.jpg' => 'Test Image',
    '000010_demo.jpg' => 'Demo Image'
];

foreach ($files as $filename => $text) {
    createTestImage($filename, $text);
}

echo "\n🎉 تم إنشاء جميع الملفات بنجاح!\n";
echo "📂 الملفات موجودة في: storage/app/public/uploads/000010/images/\n";
?>
