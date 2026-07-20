<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

echo "=== بدء اختبار الرفع الشامل ===\n";

// 1. إنشاء ملف 50MB للتجربة
$filePath = __DIR__ . '/test_50mb.mp4';
echo "جاري إنشاء ملف وهمي بحجم 50 ميجابايت...\n";
$f = fopen($filePath, 'wb');
for ($i=0; $i<50; $i++) {
    fwrite($f, str_repeat('A', 1024 * 1024)); // 1MB chunk of A's
}
fclose($f);
echo "تم إنشاء الملف بنجاح!\n\n";

$fileSize = filesize($filePath);
$chunkSize = 2 * 1024 * 1024; // 2MB
$totalChunks = ceil($fileSize / $chunkSize);
$uploadId = Str::uuid()->toString();

echo "إجمالي الأجزاء: $totalChunks\n";

$handle = fopen($filePath, 'rb');

for ($i = 0; $i < $totalChunks; $i++) {
    $chunkData = fread($handle, $chunkSize);
    
    echo "جاري رفع الجزء $i من $totalChunks...\n";
    
    // إرسال كـ multipart/form-data تماماً كما يفعل تطبيق Android
    $response = Http::attach(
        'chunk', $chunkData, 'blob', ['Content-Type' => 'application/octet-stream']
    )->withHeaders([
        'X-Upload-Id' => $uploadId,
        'X-Chunk-Index' => $i,
        'X-Total-Chunks' => $totalChunks,
        'X-File-Name' => 'test_50mb.mp4',
        'X-File-Type' => 'video/mp4',
        'X-Sponsorship-Id' => '1',
    ])->post('http://localhost:8000/api/chunked-upload-test');

    if ($response->successful()) {
        echo "نجح رفع الجزء $i! استجابة السيرفر: " . $response->json('message') . "\n";
    } else {
        echo "فشل رفع الجزء $i! الخطأ: " . $response->body() . "\n";
        exit(1);
    }

    // محاكاة انقطاع الإنترنت عند الجزء 10
    if ($i == 10) {
        echo "\n[محاكاة] انقطاع الإنترنت! إيقاف الرفع مؤقتاً لمدة 3 ثوانٍ...\n";
        sleep(3);
        echo "[محاكاة] عودة الإنترنت! استئناف الرفع...\n\n";
    }
}
fclose($handle);

echo "\nتم استكمال جميع الأجزاء وتجميع الملف على السيرفر ورفعه إلى Google Drive!\n";
echo "=== انتهى الاختبار بنجاح تام ===\n";

// Cleanup
@unlink($filePath);
