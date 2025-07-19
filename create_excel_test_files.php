<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=aso', 'root', '');

    echo "🔧 إنشاء ملفات Excel تجريبية...\n";

    // إنشاء مجلدات Excel فيزيائية
    $excelBasePath = 'storage/app/public/documents/excel';
    if (!file_exists($excelBasePath)) {
        mkdir($excelBasePath, 0755, true);
        echo "✅ تم إنشاء مجلد Excel: $excelBasePath\n";
    }

    // أرقام السجلات الموجودة في قاعدة البيانات
    $stmt = $pdo->prepare('SELECT DISTINCT record_number FROM enhanced_attachments WHERE file_type = "excel" AND record_number IS NOT NULL ORDER BY record_number');
    $stmt->execute();
    $recordNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📋 إنشاء ملفات Excel لـ " . count($recordNumbers) . " سجل...\n";

    foreach ($recordNumbers as $recordNumber) {
        $folderPath = $excelBasePath . '/' . $recordNumber;
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        // إنشاء ملفات Excel تجريبية
        $excelContent = "File Name,Size,Date\n";
        $excelContent .= "Sample Excel File,$recordNumber,2024-01-15\n";
        $excelContent .= "Test Data,100KB,2024-01-16\n";

        $fileName = "excel_$recordNumber.csv";
        file_put_contents($folderPath . '/' . $fileName, $excelContent);

        echo "   ✅ $recordNumber: $fileName\n";
    }

    // تحديث مسارات الملفات في قاعدة البيانات
    $updateStmt = $pdo->prepare('UPDATE enhanced_attachments SET file_path = CONCAT("documents/excel/", record_number, "/excel_", record_number, ".csv") WHERE file_type = "excel" AND file_path IS NULL');
    $updateStmt->execute();

    echo "\n🎯 تم إنشاء " . count($recordNumbers) . " ملف Excel تجريبي\n";
    echo "📁 المجلدات: storage/app/public/documents/excel/\n";

    // فحص النتائج
    $stmt = $pdo->prepare('SELECT record_number, COUNT(*) as count FROM enhanced_attachments WHERE file_type = "excel" GROUP BY record_number ORDER BY count DESC LIMIT 5');
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n📊 أفضل 5 مجلدات Excel:\n";
    foreach ($results as $result) {
        echo "   - رقم السجل {$result['record_number']}: {$result['count']} ملف\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
