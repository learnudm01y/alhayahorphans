<?php
// اختبار مباشر لـ API بدون authentication

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "🔍 تشخيص مباشر للـ API - لماذا filesCount = 0\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// التحقق من وجود الملفات في النظام
$folderNumber = '000072';
echo "📂 فحص المجلد {$folderNumber}:\n\n";

// 1. فحص الملفات في public/storage
$publicPath = __DIR__ . "/public/storage/uploads/{$folderNumber}";
echo "1️⃣ مسار public/storage:\n";
echo "   المسار: {$publicPath}\n";
echo "   موجود: " . (is_dir($publicPath) ? '✅ نعم' : '❌ لا') . "\n";

if (is_dir($publicPath)) {
    $files = glob($publicPath . '/*');
    echo "   عدد الملفات: " . count($files) . "\n";
    foreach ($files as $file) {
        echo "   - " . basename($file) . " (حجم: " . number_format(filesize($file)/1024, 2) . " KB)\n";
    }
}

echo "\n";

// 2. فحص الملفات في storage/app/public
$storagePath = __DIR__ . "/storage/app/public/uploads/{$folderNumber}";
echo "2️⃣ مسار storage/app/public:\n";
echo "   المسار: {$storagePath}\n";
echo "   موجود: " . (is_dir($storagePath) ? '✅ نعم' : '❌ لا') . "\n";

if (is_dir($storagePath)) {
    $files = glob($storagePath . '/*');
    echo "   عدد الملفات: " . count($files) . "\n";
    foreach ($files as $file) {
        echo "   - " . basename($file) . " (حجم: " . number_format(filesize($file)/1024, 2) . " KB)\n";
    }
}

echo "\n";

// 3. محاولة الاتصال بقاعدة البيانات مباشرة
echo "3️⃣ فحص قاعدة البيانات:\n";

try {
    // قراءة إعدادات قاعدة البيانات
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);

        // استخراج بيانات قاعدة البيانات
        preg_match('/DB_HOST=(.+)/', $envContent, $hostMatch);
        preg_match('/DB_DATABASE=(.+)/', $envContent, $dbMatch);
        preg_match('/DB_USERNAME=(.+)/', $envContent, $userMatch);
        preg_match('/DB_PASSWORD=(.*)/', $envContent, $passMatch);

        $host = isset($hostMatch[1]) ? trim($hostMatch[1]) : 'localhost';
        $database = isset($dbMatch[1]) ? trim($dbMatch[1]) : '';
        $username = isset($userMatch[1]) ? trim($userMatch[1]) : '';
        $password = isset($passMatch[1]) ? trim($passMatch[1]) : '';

        echo "   قاعدة البيانات: {$database}\n";
        echo "   المضيف: {$host}\n";
        echo "   المستخدم: {$username}\n";

        // محاولة الاتصال
        $pdo = new PDO("mysql:host={$host};dbname={$database}", $username, $password);
        echo "   الاتصال: ✅ نجح\n\n";

        // فحص الجداول
        echo "4️⃣ فحص الجداول:\n";

        // فحص enhanced_attachments
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'enhanced_attachments'");
        $stmt->execute();
        $hasEnhanced = $stmt->rowCount() > 0;
        echo "   enhanced_attachments: " . ($hasEnhanced ? '✅ موجود' : '❌ غير موجود') . "\n";

        if ($hasEnhanced) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enhanced_attachments WHERE record_number = ?");
            $stmt->execute([$folderNumber]);
            $count = $stmt->fetch()['count'];
            echo "     عدد السجلات للمجلد {$folderNumber}: {$count}\n";

            if ($count > 0) {
                $stmt = $pdo->prepare("SELECT * FROM enhanced_attachments WHERE record_number = ? LIMIT 3");
                $stmt->execute([$folderNumber]);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "     عينة من السجلات:\n";
                foreach ($records as $record) {
                    echo "       - اسم الملف: " . ($record['stored_file_name'] ?? $record['original_file_name'] ?? 'غير محدد') . "\n";
                    echo "         المسار: " . ($record['file_path'] ?? 'غير محدد') . "\n";
                    echo "         النوع: " . ($record['file_type'] ?? 'غير محدد') . "\n\n";
                }
            }
        }

        // فحص attachments العادي
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'attachments'");
        $stmt->execute();
        $hasRegular = $stmt->rowCount() > 0;
        echo "   attachments: " . ($hasRegular ? '✅ موجود' : '❌ غير موجود') . "\n";

        if ($hasRegular) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attachments WHERE record_number = ?");
            $stmt->execute([$folderNumber]);
            $count = $stmt->fetch()['count'];
            echo "     عدد السجلات للمجلد {$folderNumber}: {$count}\n";
        }

    } else {
        echo "   ❌ ملف .env غير موجود\n";
    }

} catch (Exception $e) {
    echo "   ❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎯 التشخيص النهائي:\n";
echo "- إذا كانت الملفات موجودة فعلياً ولكن قاعدة البيانات فارغة\n";
echo "  → المشكلة: عدم وجود سجلات في قاعدة البيانات\n";
echo "- إذا كانت قاعدة البيانات تحتوي على سجلات\n";
echo "  → المشكلة: في الـ Controller أو الـ Query\n";
echo "- إذا لم تكن الملفات موجودة فعلياً\n";
echo "  → المشكلة: في مسارات التخزين\n";
?>
