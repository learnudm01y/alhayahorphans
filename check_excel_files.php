<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=aso', 'root', '');

    // فحص ملفات Excel في enhanced_attachments
    $stmt = $pdo->prepare('SELECT file_type, COUNT(*) as count FROM enhanced_attachments WHERE file_type LIKE "%excel%" OR file_extension IN ("xls", "xlsx") GROUP BY file_type');
    $stmt->execute();
    $excelFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📊 ملفات Excel في قاعدة البيانات:\n";
    foreach ($excelFiles as $file) {
        echo "   {$file['file_type']}: {$file['count']} ملف\n";
    }

    // فحص مجلدات فيزيائية
    $basePath = 'storage/app/public/documents';
    $excelPath = $basePath . '/excel';

    echo "\n🔍 فحص المجلدات الفيزيائية:\n";

    if (is_dir($basePath)) {
        echo "✅ مجلد documents موجود\n";

        if (is_dir($excelPath)) {
            $excelFolders = array_filter(scandir($excelPath), function($item) use ($excelPath) {
                return $item !== '.' && $item !== '..' && is_dir($excelPath . '/' . $item);
            });
            echo "📁 مجلدات Excel: " . count($excelFolders) . " مجلد\n";
            foreach (array_slice($excelFolders, 0, 10) as $folder) {
                $files = scandir($excelPath . '/' . $folder);
                $fileCount = count($files) - 2; // استثناء . و ..
                echo "   - $folder ($fileCount ملف)\n";
            }
        } else {
            echo "⚠️ مجلد excel غير موجود، سأحاول إنشاؤه\n";
            if (!file_exists($excelPath)) {
                mkdir($excelPath, 0755, true);
                echo "✅ تم إنشاء مجلد excel\n";
            }
        }

        // فحص البيانات في قاعدة البيانات حسب رقم السجل
        $stmt = $pdo->prepare('SELECT record_number, COUNT(*) as count FROM enhanced_attachments WHERE file_type = "excel" GROUP BY record_number ORDER BY count DESC LIMIT 10');
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "\n📋 أرقام السجلات مع ملفات Excel:\n";
        foreach ($records as $record) {
            echo "   - رقم السجل {$record['record_number']}: {$record['count']} ملف Excel\n";
        }

    } else {
        echo "❌ مجلد documents غير موجود\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
