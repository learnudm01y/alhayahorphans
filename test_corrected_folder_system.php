<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== اختبار الحل المُصحح ===\n";

    // محاكاة الاستعلام المُصحح
    $folders = $pdo->query("
        SELECT
            SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) as folder_name,
            COUNT(*) as files_count,
            SUM(file_size) as total_size,
            MAX(updated_at) as last_modified,
            GROUP_CONCAT(DISTINCT file_type) as file_types,
            'attachments' as source
        FROM attachments
        WHERE file_path LIKE '%storage/uploads/%'
        AND file_path IS NOT NULL
        AND file_path != ''
        GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1)
        ORDER BY last_modified DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "📁 المجلدات المُستخرجة بطريقة صحيحة:\n";
    echo str_repeat("-", 60) . "\n";

    foreach($folders as $folder) {
        $folderName = $folder['folder_name'];
        echo "🗂️  مجلد: $folderName | ملفات: {$folder['files_count']}\n";

        // محاكاة getPersonName() - البحث بالطريقة الحالية
        // 1. البحث المباشر
        $stmt = $pdo->prepare("SELECT CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) as full_name FROM data WHERE file_id_number = ?");
        $stmt->execute([$folderName]);
        $directMatch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($directMatch && !empty(trim($directMatch['full_name']))) {
            echo "   👤 الاسم (مباشر): {$directMatch['full_name']}\n";
        } else {
            // 2. البحث بإزالة الأصفار
            $strippedFolder = ltrim($folderName, '0');
            if (!empty($strippedFolder)) {
                $stmt->execute([$strippedFolder]);
                $strippedMatch = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($strippedMatch && !empty(trim($strippedMatch['full_name']))) {
                    echo "   👤 الاسم (مجرد): {$strippedMatch['full_name']}\n";
                } else {
                    echo "   ❌ لم يوجد اسم\n";
                }
            } else {
                echo "   ❌ لم يوجد اسم\n";
            }
        }
        echo "\n";
    }

    echo str_repeat("=", 60) . "\n";
    echo "🔍 فحص تطابق الأرقام من الصورة:\n";

    // فحص بعض الأرقام من الصورة مع المجلدات الفعلية
    $testNumbers = ['9214534563', '2962453453', '2944445', '44444424'];

    foreach($testNumbers as $testNumber) {
        echo "📋 الرقم من الصورة: $testNumber\n";

        // هل يوجد مجلد يحتوي على هذا الرقم؟
        $stmt = $pdo->prepare("
            SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) as actual_folder
            FROM attachments
            WHERE person_identity_number = ?
            AND file_path LIKE '%storage/uploads/%'
        ");
        $stmt->execute([$testNumber]);
        $folderForNumber = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($folderForNumber) {
            echo "   📁 المجلد الفعلي: {$folderForNumber['actual_folder']}\n";

            // الآن ابحث عن الاسم باستخدام اسم المجلد الفعلي
            $stmt2 = $pdo->prepare("SELECT CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) as full_name FROM data WHERE file_id_number = ?");
            $stmt2->execute([$folderForNumber['actual_folder']]);
            $personName = $stmt2->fetch(PDO::FETCH_ASSOC);

            if ($personName && !empty(trim($personName['full_name']))) {
                echo "   👤 اسم الشخص: {$personName['full_name']}\n";
            } else {
                // جرب بإزالة الأصفار
                $stripped = ltrim($folderForNumber['actual_folder'], '0');
                $stmt2->execute([$stripped]);
                $personName = $stmt2->fetch(PDO::FETCH_ASSOC);

                if ($personName && !empty(trim($personName['full_name']))) {
                    echo "   👤 اسم الشخص (مجرد): {$personName['full_name']}\n";
                } else {
                    echo "   ❌ لم يوجد اسم للمجلد {$folderForNumber['actual_folder']}\n";
                }
            }
        } else {
            echo "   ❌ لم يوجد مجلد لهذا الرقم\n";
        }
        echo "\n";
    }

} catch(Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
