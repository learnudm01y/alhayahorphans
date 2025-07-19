<?php
// إصلاح محسن - إضافة السجلات مع جميع الحقول المطلوبة

echo "🔧 إصلاح محسن - إضافة السجلات مع البيانات الكاملة\n";
echo "=" . str_repeat("=", 70) . "\n\n";

try {
    // قراءة إعدادات قاعدة البيانات
    $envFile = __DIR__ . '/.env';
    $envContent = file_get_contents($envFile);

    preg_match('/DB_HOST=(.+)/', $envContent, $hostMatch);
    preg_match('/DB_DATABASE=(.+)/', $envContent, $dbMatch);
    preg_match('/DB_USERNAME=(.+)/', $envContent, $userMatch);
    preg_match('/DB_PASSWORD=(.*)/', $envContent, $passMatch);

    $host = isset($hostMatch[1]) ? trim($hostMatch[1]) : 'localhost';
    $database = isset($dbMatch[1]) ? trim($dbMatch[1]) : '';
    $username = isset($userMatch[1]) ? trim($userMatch[1]) : '';
    $password = isset($passMatch[1]) ? trim($passMatch[1]) : '';

    $pdo = new PDO("mysql:host={$host};dbname={$database}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "📂 إضافة الملفات لقاعدة البيانات:\n\n";

    $folderNumber = '000072';
    $publicPath = __DIR__ . "/public/storage/uploads/{$folderNumber}";

    if (is_dir($publicPath)) {
        $files = glob($publicPath . '/*');

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $fileSize = filesize($filePath);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileHash = md5_file($filePath);
            $mimeType = '';
            $fileType = '';

            // تحديد نوع الملف
            switch ($fileExtension) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'bmp':
                case 'webp':
                    $mimeType = 'image/' . ($fileExtension === 'jpg' ? 'jpeg' : $fileExtension);
                    $fileType = 'image';
                    break;
                case 'pdf':
                    $mimeType = 'application/pdf';
                    $fileType = 'pdf';
                    break;
                case 'xlsx':
                case 'xls':
                    $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    $fileType = 'excel';
                    break;
                default:
                    $mimeType = 'application/octet-stream';
                    $fileType = 'image'; // افتراضي
            }

            echo "📄 معالجة الملف: {$fileName}\n";
            echo "   الحجم: " . number_format($fileSize/1024, 2) . " KB\n";
            echo "   النوع: {$fileType}\n";
            echo "   الهاش: {$fileHash}\n";

            // التحقق من وجود السجل
            $checkQuery = "SELECT COUNT(*) as count FROM enhanced_attachments WHERE stored_file_name = ? AND record_number = ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$fileName, $folderNumber]);
            $exists = $stmt->fetch()['count'] > 0;

            if (!$exists) {
                // إضافة السجل مع جميع الحقول المطلوبة
                $insertQuery = "INSERT INTO enhanced_attachments (
                    record_number,
                    stored_file_name,
                    original_file_name,
                    file_path,
                    file_size,
                    file_type,
                    file_extension,
                    mime_type,
                    file_hash,
                    source,
                    processing_status,
                    compression_status,
                    access_level,
                    requires_approval,
                    is_encrypted,
                    version_number,
                    is_latest_version,
                    cloud_sync_status,
                    quality_status,
                    is_complete,
                    download_count,
                    view_count,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

                $relativeFilePath = "storage/uploads/{$folderNumber}/{$fileName}";

                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute([
                    $folderNumber,                    // record_number
                    $fileName,                        // stored_file_name
                    $fileName,                        // original_file_name
                    $relativeFilePath,               // file_path
                    $fileSize,                       // file_size
                    $fileType,                       // file_type
                    $fileExtension,                  // file_extension
                    $mimeType,                       // mime_type
                    $fileHash,                       // file_hash
                    'imported_google_drive',         // source
                    'completed',                     // processing_status
                    'not_required',                  // compression_status
                    'public',                        // access_level
                    0,                               // requires_approval
                    0,                               // is_encrypted
                    1,                               // version_number
                    1,                               // is_latest_version
                    'not_synced',                    // cloud_sync_status
                    'good',                          // quality_status
                    1,                               // is_complete
                    0,                               // download_count
                    0                                // view_count
                ]);

                echo "   ✅ تم إضافة السجل بنجاح\n";
            } else {
                echo "   ⚠️ السجل موجود مسبقاً\n";
            }

            echo "\n";
        }

        // التحقق من النتيجة النهائية
        echo "🎯 النتيجة النهائية:\n";
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enhanced_attachments WHERE record_number = ?");
        $stmt->execute([$folderNumber]);
        $finalCount = $stmt->fetch()['count'];

        echo "   عدد السجلات في قاعدة البيانات للمجلد {$folderNumber}: {$finalCount}\n";

        if ($finalCount > 0) {
            echo "   ✅ تم الإصلاح بنجاح!\n\n";

            // عرض عينة من البيانات المضافة
            echo "📋 عينة من البيانات المضافة:\n";
            $stmt = $pdo->prepare("SELECT stored_file_name, file_path, file_size, file_type FROM enhanced_attachments WHERE record_number = ? LIMIT 3");
            $stmt->execute([$folderNumber]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($records as $record) {
                echo "   📄 {$record['stored_file_name']}\n";
                echo "      المسار: {$record['file_path']}\n";
                echo "      الحجم: " . number_format($record['file_size']/1024, 2) . " KB\n";
                echo "      النوع: {$record['file_type']}\n\n";
            }

            echo "🎉 الآن يجب أن يعمل المودال ويظهر الملفات!\n";
            echo "🔄 اختبر URL: /admin/folders/contents?folder=000072&type=images\n";
        } else {
            echo "   ❌ لا تزال هناك مشكلة في إضافة السجلات\n";
        }

    } else {
        echo "❌ مجلد الملفات غير موجود: {$publicPath}\n";
    }

} catch (Exception $e) {
    echo "💥 خطأ: " . $e->getMessage() . "\n";
    echo "الملف: " . $e->getFile() . "\n";
    echo "السطر: " . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎯 الخطوة التالية: اختبر المودال الآن ويجب أن تحصل على filesCount > 0\n";
?>
