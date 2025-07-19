<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== إنشاء مجلدات تجريبية بنفس أرقام الصورة ===" . PHP_EOL;

$folderNumbers = ['000010', '000014', '000015', '000016', '000017', '000018', '000019', '000020', '000021', '000022'];
$fileTypes = ['image', 'pdf', 'excel'];

try {
    foreach ($folderNumbers as $folderNumber) {
        // إنشاء 2-4 ملفات لكل مجلد
        $filesCount = rand(2, 4);

        for ($i = 1; $i <= $filesCount; $i++) {
            $fileType = $fileTypes[array_rand($fileTypes)];
            $extension = ($fileType === 'image') ? 'jpg' : (($fileType === 'excel') ? 'xlsx' : 'pdf');

            DB::table('enhanced_attachments')->insert([
                'record_number' => $folderNumber,
                'stored_file_name' => $folderNumber . '_file_' . $i . '_' . date('YmdHis') . '.' . $extension,
                'original_file_name' => 'document_' . $folderNumber . '_' . $i . '.' . $extension,
                'file_path' => 'storage/uploads/' . $folderNumber . '/' . $fileType . 's/' . $folderNumber . '_file_' . $i . '_' . date('YmdHis') . '.' . $extension,
                'file_type' => $fileType,
                'mime_type' => ($fileType === 'image') ? 'image/jpeg' : (($fileType === 'excel') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf'),
                'file_extension' => $extension,
                'file_size' => rand(100000, 1000000),
                'file_hash' => md5($folderNumber . $i . time()),
                'source' => 'direct_upload',
                'upload_ip_address' => '127.0.0.1',
                'upload_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'processing_status' => 'completed',
                'compression_status' => 'not_required',
                'access_level' => 'internal',
                'requires_approval' => 0,
                'is_encrypted' => 0,
                'version_number' => 1,
                'is_latest_version' => 1,
                'document_status' => 'draft',
                'requires_renewal' => 0,
                'download_count' => 0,
                'view_count' => 0,
                'is_complete' => 1,
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(1, 30))
            ]);
        }

        echo "✅ تم إنشاء " . $filesCount . " ملفات في المجلد: " . $folderNumber . PHP_EOL;
    }

    echo PHP_EOL . "🎉 تم إنشاء مجلدات جديدة بأرقام مثل الصورة المرفقة!" . PHP_EOL;
    echo "الآن لديك مجلدات: " . implode(', ', $folderNumbers) . PHP_EOL;

} catch (Exception $e) {
    echo "❌ خطأ في إنشاء البيانات: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "انتهى إنشاء المجلدات." . PHP_EOL;
