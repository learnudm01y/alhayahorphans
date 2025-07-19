<?php
// فحص هيكل قاعدة البيانات وإضافة البيانات المفقودة

echo "🔧 إصلاح مشكلة قاعدة البيانات - إضافة السجلات المفقودة\n";
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

    echo "📊 فحص هيكل الجداول:\n\n";

    // فحص هيكل enhanced_attachments
    echo "1️⃣ جدول enhanced_attachments:\n";
    $stmt = $pdo->query("DESCRIBE enhanced_attachments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasRecordNumber = false;
    $hasFolderNumber = false;

    foreach ($columns as $column) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        if ($column['Field'] === 'record_number') $hasRecordNumber = true;
        if ($column['Field'] === 'folder_number') $hasFolderNumber = true;
    }

    echo "\n";

    // فحص هيكل attachments
    echo "2️⃣ جدول attachments:\n";
    $stmt = $pdo->query("DESCRIBE attachments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $attachmentsHasRecordNumber = false;
    $attachmentsHasFolderNumber = false;

    foreach ($columns as $column) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        if ($column['Field'] === 'record_number') $attachmentsHasRecordNumber = true;
        if ($column['Field'] === 'folder_number') $attachmentsHasFolderNumber = true;
    }

    echo "\n";

    // تحديد العمود الصحيح للبحث
    $searchColumn = 'record_number';
    if (!$hasRecordNumber && $hasFolderNumber) {
        $searchColumn = 'folder_number';
    }

    echo "3️⃣ إضافة السجلات المفقودة:\n";
    echo "   العمود المستخدم للبحث: {$searchColumn}\n\n";

    // البحث عن الملفات في النظام وإضافتها لقاعدة البيانات
    $folderNumber = '000072';
    $publicPath = __DIR__ . "/public/storage/uploads/{$folderNumber}";

    if (is_dir($publicPath)) {
        $files = glob($publicPath . '/*');

        echo "   الملفات الموجودة في النظام:\n";

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $fileSize = filesize($filePath);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
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
                    $fileType = 'other';
            }

            echo "     📄 {$fileName}:\n";
            echo "        الحجم: " . number_format($fileSize/1024, 2) . " KB\n";
            echo "        النوع: {$fileType} ({$mimeType})\n";

            // التحقق من وجود السجل
            $checkQuery = "SELECT COUNT(*) as count FROM enhanced_attachments WHERE stored_file_name = ? AND {$searchColumn} = ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$fileName, $folderNumber]);
            $exists = $stmt->fetch()['count'] > 0;

            if (!$exists) {
                // إضافة السجل
                $insertQuery = "INSERT INTO enhanced_attachments (
                    {$searchColumn},
                    stored_file_name,
                    original_file_name,
                    file_path,
                    file_size,
                    file_type,
                    file_extension,
                    mime_type,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

                $filePath = "storage/uploads/{$folderNumber}/{$fileName}";

                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute([
                    $folderNumber,
                    $fileName,
                    $fileName, // original_file_name same as stored
                    $filePath,
                    $fileSize,
                    $fileType,
                    $fileExtension,
                    $mimeType
                ]);

                echo "        ✅ تم إضافة السجل لقاعدة البيانات\n";
            } else {
                echo "        ⚠️ السجل موجود مسبقاً\n";
            }

            echo "\n";
        }

        // التحقق من النتيجة النهائية
        echo "4️⃣ التحقق من النتيجة:\n";
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enhanced_attachments WHERE {$searchColumn} = ?");
        $stmt->execute([$folderNumber]);
        $finalCount = $stmt->fetch()['count'];

        echo "   عدد السجلات في قاعدة البيانات للمجلد {$folderNumber}: {$finalCount}\n";

        if ($finalCount > 0) {
            echo "   ✅ تم الإصلاح بنجاح!\n";
            echo "   🎯 الآن يجب أن يعمل المودال ويظهر الملفات\n";
        } else {
            echo "   ❌ لا تزال هناك مشكلة\n";
        }

    } else {
        echo "   ❌ مجلد الملفات غير موجود: {$publicPath}\n";
    }

} catch (Exception $e) {
    echo "💥 خطأ: " . $e->getMessage() . "\n";
    echo "تفاصيل: " . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎉 انتهى الإصلاح - اختبر المودال الآن!\n";
?>
