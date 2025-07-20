<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== اختبار دالة البحث المُحسنة ===\n";

    // اختبار البحث برقم الهوية (9214534563)
    $identityNumber = '9214534563';
    echo "🔍 البحث برقم الهوية: $identityNumber\n";
    echo str_repeat("-", 50) . "\n";

    // 1. البحث في attachments برقم الهوية
    echo "📊 البحث في جدول attachments:\n";
    $stmt = $pdo->query("
        SELECT
            id,
            stored_file_name,
            file_path,
            person_identity_number,
            SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) as folder_name
        FROM attachments
        WHERE person_identity_number LIKE '%$identityNumber%'
        OR stored_file_name LIKE '%$identityNumber%'
        LIMIT 5
    ");
    $attachmentResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($attachmentResults as $result) {
        echo "  ✅ ملف: {$result['stored_file_name']}\n";
        echo "     📁 مجلد: {$result['folder_name']}\n";
        echo "     🆔 رقم الهوية: {$result['person_identity_number']}\n";

        // البحث عن اسم الشخص
        $stmt2 = $pdo->prepare("
            SELECT CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) as full_name
            FROM data
            WHERE file_id_number = ?
        ");
        $stmt2->execute([$result['folder_name']]);
        $personName = $stmt2->fetch(PDO::FETCH_ASSOC);

        if($personName && !empty(trim($personName['full_name']))) {
            echo "     👤 اسم الشخص: " . trim($personName['full_name']) . "\n";
        } else {
            // جرب بإزالة الأصفار
            $stripped = ltrim($result['folder_name'], '0');
            $stmt2->execute([$stripped]);
            $personName = $stmt2->fetch(PDO::FETCH_ASSOC);
            if($personName && !empty(trim($personName['full_name']))) {
                echo "     👤 اسم الشخص (مجرد): " . trim($personName['full_name']) . "\n";
            } else {
                echo "     ❌ لم يوجد اسم\n";
            }
        }
        echo "\n";
    }

    echo str_repeat("=", 60) . "\n";

    // اختبار البحث باسم الشخص
    $personName = 'Rebecca';
    echo "🔍 البحث باسم الشخص: $personName\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("
        SELECT file_id_number,
               CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) as full_name
        FROM data
        WHERE data_first_name LIKE '%$personName%'
        OR data_father_name LIKE '%$personName%'
        OR data_grand_father_name LIKE '%$personName%'
        OR data_family_name LIKE '%$personName%'
        LIMIT 5
    ");
    $personResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($personResults as $person) {
        echo "  👤 اسم: " . trim($person['full_name']) . "\n";
        echo "     📁 رقم الملف: {$person['file_id_number']}\n";

        // البحث عن الملفات
        $stmt2 = $pdo->prepare("
            SELECT stored_file_name, file_path
            FROM attachments
            WHERE file_path LIKE '%storage/uploads/%'
            AND SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) = ?
            LIMIT 3
        ");
        $stmt2->execute([$person['file_id_number']]);
        $files = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        foreach($files as $file) {
            echo "     📄 ملف: {$file['stored_file_name']}\n";
        }
        echo "\n";
    }

    echo str_repeat("=", 60) . "\n";

    // اختبار البحث برقم المجلد
    $folderNumber = '001460';
    echo "🔍 البحث برقم المجلد: $folderNumber\n";
    echo str_repeat("-", 50) . "\n";

    $stmt = $pdo->query("
        SELECT stored_file_name, file_path, person_identity_number
        FROM attachments
        WHERE file_path LIKE '%storage/uploads/%'
        AND SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) LIKE '%$folderNumber%'
        LIMIT 5
    ");
    $folderResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($folderResults as $file) {
        echo "  📄 ملف: {$file['stored_file_name']}\n";
        echo "     🆔 رقم الهوية: {$file['person_identity_number']}\n";

        // اسم الشخص
        $stmt2 = $pdo->prepare("
            SELECT CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) as full_name
            FROM data
            WHERE file_id_number = ?
        ");
        $stmt2->execute([$folderNumber]);
        $personName = $stmt2->fetch(PDO::FETCH_ASSOC);

        if($personName && !empty(trim($personName['full_name']))) {
            echo "     👤 اسم الشخص: " . trim($personName['full_name']) . "\n";
        }
        echo "\n";
    }

} catch(Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
