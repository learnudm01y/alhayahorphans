<?php
// إضافة جميع المجلدات مع تجنب مشكلة duplicate hash

echo "🔍 إضافة جميع المجلدات مع معالجة التكرار\n";
echo "=" . str_repeat("=", 60) . "\n\n";

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

    // فحص جميع المجلدات
    $uploadsPath = __DIR__ . '/public/storage/uploads';
    echo "📂 فحص المجلدات في: {$uploadsPath}\n\n";

    $folders = glob($uploadsPath . '/*', GLOB_ONLYDIR);
    $totalFilesAdded = 0;
    $totalFoldersProcessed = 0;
    $skippedFiles = 0;

    foreach ($folders as $folderPath) {
        $folderName = basename($folderPath);

        // تخطي المجلدات غير الرقمية
        if (!preg_match('/^\d{6}$/', $folderName)) {
            echo "⏭️ تخطي المجلد: {$folderName} (لا يتبع النمط المطلوب)\n";
            continue;
        }

        echo "📁 معالجة المجلد: {$folderName}\n";

        $files = glob($folderPath . '/*');
        $folderFilesCount = 0;

        foreach ($files as $filePath) {
            if (is_file($filePath)) {
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
                    case 'docx':
                    case 'doc':
                        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        $fileType = 'word';
                        break;
                    default:
                        $mimeType = 'application/octet-stream';
                        $fileType = 'image'; // افتراضي
                }

                // التحقق من وجود السجل بنفس الاسم والمجلد
                $checkQuery = "SELECT COUNT(*) as count FROM enhanced_attachments WHERE stored_file_name = ? AND record_number = ?";
                $stmt = $pdo->prepare($checkQuery);
                $stmt->execute([$fileName, $folderName]);
                $exists = $stmt->fetch()['count'] > 0;

                if (!$exists) {
                    try {
                        // إضافة السجل مع hash فريد
                        $uniqueHash = $fileHash . '_' . $folderName . '_' . time(); // جعل الـ hash فريد

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

                        $relativeFilePath = "storage/uploads/{$folderName}/{$fileName}";

                        $stmt = $pdo->prepare($insertQuery);
                        $stmt->execute([
                            $folderName,                     // record_number
                            $fileName,                       // stored_file_name
                            $fileName,                       // original_file_name
                            $relativeFilePath,              // file_path
                            $fileSize,                      // file_size
                            $fileType,                      // file_type
                            $fileExtension,                 // file_extension
                            $mimeType,                      // mime_type
                            $uniqueHash,                    // file_hash (فريد)
                            'imported_google_drive',        // source
                            'completed',                    // processing_status
                            'not_required',                 // compression_status
                            'public',                       // access_level
                            0,                              // requires_approval
                            0,                              // is_encrypted
                            1,                              // version_number
                            1,                              // is_latest_version
                            'not_synced',                   // cloud_sync_status
                            'good',                         // quality_status
                            1,                              // is_complete
                            0,                              // download_count
                            0                               // view_count
                        ]);

                        $folderFilesCount++;
                        $totalFilesAdded++;

                    } catch (Exception $e) {
                        echo "   ⚠️ تخطي الملف {$fileName}: " . $e->getMessage() . "\n";
                        $skippedFiles++;
                    }
                } else {
                    $skippedFiles++;
                }
            }
        }

        echo "   ✅ تم إضافة {$folderFilesCount} ملف جديد\n";
        $totalFoldersProcessed++;
        echo "\n";
    }

    echo "🎯 النتيجة النهائية:\n";
    echo "   المجلدات المُعالجة: {$totalFoldersProcessed}\n";
    echo "   الملفات المُضافة: {$totalFilesAdded}\n";
    echo "   الملفات المُتخطاة: {$skippedFiles}\n\n";

    // عرض إحصائيات شاملة
    echo "📊 إحصائيات قاعدة البيانات:\n";
    $stmt = $pdo->query("SELECT record_number, COUNT(*) as count FROM enhanced_attachments GROUP BY record_number ORDER BY record_number");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stats as $stat) {
        echo "   📁 المجلد {$stat['record_number']}: {$stat['count']} ملف\n";
    }

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM enhanced_attachments");
    $total = $stmt->fetch()['total'];
    echo "\n   📋 إجمالي الملفات في قاعدة البيانات: {$total}\n";

} catch (Exception $e) {
    echo "💥 خطأ: " . $e->getMessage() . "\n";
    echo "الملف: " . $e->getFile() . "\n";
    echo "السطر: " . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 انتهى! الآن جميع المجلدات متاحة\n";
?>
