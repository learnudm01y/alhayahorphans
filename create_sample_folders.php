<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== إنشاء بيانات اختبارية إضافية ===" . PHP_EOL;

$sampleFolders = [
    'photos' => ['image', 'photo'],
    'documents' => ['pdf', 'document'],
    'reports' => ['excel', 'pdf'],
    'archives' => ['pdf', 'document'],
    'personal' => ['image', 'pdf']
];

try {
    foreach ($sampleFolders as $folderName => $fileTypes) {
        for ($i = 1; $i <= 3; $i++) {
            $fileType = $fileTypes[array_rand($fileTypes)];
            $extension = ($fileType === 'image') ? 'jpg' : (($fileType === 'excel') ? 'xlsx' : 'pdf');

            DB::table('enhanced_attachments')->insert([
                'record_number' => sprintf('00%04d', rand(1000, 9999)),
                'stored_file_name' => $folderName . '_' . $i . '_' . date('YmdHis') . '.' . $extension,
                'original_file_name' => 'sample_' . $folderName . '_' . $i . '.' . $extension,
                'file_path' => 'storage/uploads/' . rand(1000, 9999) . '/' . $folderName . '/' . $folderName . '_' . $i . '_' . date('YmdHis') . '.' . $extension,
                'file_type' => $fileType,
                'mime_type' => ($fileType === 'image') ? 'image/jpeg' : (($fileType === 'excel') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf'),
                'file_extension' => $extension,
                'file_size' => rand(100000, 1000000),
                'file_hash' => md5($folderName . $i . time()),
                'source' => 'direct_upload',
                'upload_ip_address' => '127.0.0.1',
                'upload_user_agent' => 'Test Browser',
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
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        echo "✅ تم إنشاء 3 ملفات في مجلد: " . $folderName . PHP_EOL;
    }

    echo PHP_EOL . "🎉 تم إنشاء " . (count($sampleFolders) * 3) . " ملف اختباري في " . count($sampleFolders) . " مجلدات جديدة!" . PHP_EOL;
    echo "الآن ستجد مجلدات أكثر في النظام." . PHP_EOL;

} catch (Exception $e) {
    echo "❌ خطأ في إنشاء البيانات: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "انتهى إنشاء البيانات الاختبارية." . PHP_EOL;
