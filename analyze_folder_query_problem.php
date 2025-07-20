<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== تحليل كيفية عمل الاستعلام الحالي ===\n";

    // محاكاة الاستعلام المُستخدم في getImageFolders()
    echo "📊 الاستعلام الحالي في الكود:\n";
    echo "SELECT person_identity_number as folder_name, COUNT(*) as files_count...\n\n";

    $currentQuery = $pdo->query("
        SELECT
            person_identity_number as folder_name,
            COUNT(*) as files_count,
            SUM(file_size) as total_size,
            MAX(updated_at) as last_modified,
            GROUP_CONCAT(DISTINCT file_type) as file_types,
            GROUP_CONCAT(DISTINCT mime_type) as mime_types,
            'attachments' as source
        FROM attachments
        WHERE person_identity_number IS NOT NULL
        AND person_identity_number != ''
        GROUP BY person_identity_number
        ORDER BY last_modified DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "🔍 النتائج الحالية:\n";
    foreach($currentQuery as $row) {
        echo "  📁 مجلد: {$row['folder_name']} | ملفات: {$row['files_count']} | آخر تعديل: {$row['last_modified']}\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🧩 تحليل البيانات الفعلية:\n";

    // دعنا نرى ما هو موجود فعلياً في file_path
    $pathAnalysis = $pdo->query("
        SELECT DISTINCT
            SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) as actual_folder,
            person_identity_number,
            file_path,
            stored_file_name
        FROM attachments
        WHERE file_path LIKE '%storage/uploads/%'
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "📂 المجلدات الفعلية المستخرجة من file_path:\n";
    foreach($pathAnalysis as $row) {
        echo "  📁 مجلد فعلي: {$row['actual_folder']} | person_identity_number: {$row['person_identity_number']}\n";
        echo "     📄 ملف: {$row['stored_file_name']}\n";
        echo "     📂 مسار: {$row['file_path']}\n\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "💡 الحل المقترح:\n";
    echo "يجب استخدام اسم المجلد الفعلي من file_path بدلاً من person_identity_number\n";

    // الاستعلام المُصحح
    $correctedQuery = $pdo->query("
        SELECT
            SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1) as folder_name,
            COUNT(*) as files_count,
            SUM(file_size) as total_size,
            MAX(updated_at) as last_modified,
            GROUP_CONCAT(DISTINCT file_type) as file_types,
            GROUP_CONCAT(DISTINCT mime_type) as mime_types,
            'attachments' as source
        FROM attachments
        WHERE file_path LIKE '%storage/uploads/%'
        AND file_path IS NOT NULL
        AND file_path != ''
        GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1)
        ORDER BY last_modified DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "\n🔧 النتائج بعد التصحيح:\n";
    foreach($correctedQuery as $row) {
        echo "  📁 مجلد: {$row['folder_name']} | ملفات: {$row['files_count']} | آخر تعديل: {$row['last_modified']}\n";
    }

} catch(Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
