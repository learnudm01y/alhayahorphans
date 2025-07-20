<?php
// تشغيل اختبار سريع لمحاكاة getImageFolders()
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== محاكاة دقيقة لـ getImageFolders() ===\n";

    // نفس الاستعلام المُصحح
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
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach($folders as $folder) {
        $folderName = $folder['folder_name'];
        echo "📁 مجلد: $folderName\n";

        // محاكاة getPersonName() تماماً كما هو في الكود
        // البحث المباشر أولاً
        $stmt = $pdo->prepare("
            SELECT CONCAT(
                COALESCE(data_first_name, ''), ' ',
                COALESCE(data_father_name, ''), ' ',
                COALESCE(data_grand_father_name, ''), ' ',
                COALESCE(data_family_name, '')
            ) as person_name
            FROM data
            WHERE file_id_number = ?
        ");
        $stmt->execute([$folderName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty(trim($result['person_name']))) {
            echo "   ✅ اسم الشخص: " . trim($result['person_name']) . "\n";
        } else {
            // البحث بإزالة الأصفار البادئة
            $folderStripped = ltrim($folderName, '0');
            if (!empty($folderStripped)) {
                $stmt->execute([$folderStripped]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && !empty(trim($result['person_name']))) {
                    echo "   ✅ اسم الشخص (مجرد): " . trim($result['person_name']) . "\n";
                } else {
                    echo "   ❌ غير مسجل\n";
                }
            } else {
                echo "   ❌ غير مسجل\n";
            }
        }
        echo str_repeat("-", 50) . "\n";
    }

} catch(Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
