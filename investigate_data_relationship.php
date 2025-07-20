<?php
require_once 'vendor/autoload.php';

// Load environment configuration
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Database connection
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_DATABASE'];
    $username = $_ENV['DB_USERNAME'];
    $password = $_ENV['DB_PASSWORD'];

    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== تحليل علاقة البيانات بين الجداول ===\n";
    echo "🔍 فحص هيكل جدول data وعلاقته بالجداول الأخرى:\n";
    echo str_repeat("=", 60) . "\n";

    // Check data table structure
    echo "📊 هيكل جدول data:\n";
    $structure = $pdo->query("DESCRIBE data")->fetchAll(PDO::FETCH_ASSOC);
    foreach($structure as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    echo "\n";

    // Sample from data table
    echo "📋 عينة من جدول data:\n";
    $data_sample = $pdo->query("SELECT * FROM data LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach($data_sample as $row) {
        echo "   ID: {$row['id']} | file_id_number: {$row['file_id_number']} | Name: " .
             (isset($row['person_identity_number']) ? $row['person_identity_number'] : 'N/A') . "\n";
    }
    echo "\n";

    // Check for any matching patterns
    echo "🔗 البحث عن نماذج للربط:\n";

    // Check if there are any numbers that might relate
    $attachments_sample = $pdo->query("SELECT DISTINCT folder_name FROM attachments ORDER BY folder_name LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
    $data_file_ids = $pdo->query("SELECT DISTINCT file_id_number FROM data ORDER BY file_id_number LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);

    echo "📂 أرقام المجلدات في attachments:\n";
    foreach($attachments_sample as $folder) {
        echo "   - $folder\n";
    }
    echo "\n";

    echo "📋 أرقام file_id_number في data:\n";
    foreach($data_file_ids as $file_id) {
        echo "   - $file_id\n";
    }
    echo "\n";

    // Try to find any potential connections
    echo "🔍 البحث عن أي تطابق محتمل:\n";

    // Check if any folder names exist in data
    $found_matches = false;
    foreach($attachments_sample as $folder) {
        $stmt = $pdo->prepare("SELECT * FROM data WHERE file_id_number = ? OR file_id_number = ? OR person_identity_number LIKE ?");
        $stripped = ltrim($folder, '0');
        $stmt->execute([$folder, $stripped, "%$folder%"]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if($match) {
            echo "   ✅ تطابق وُجد: folder $folder -> data record: " . json_encode($match) . "\n";
            $found_matches = true;
        }
    }

    if(!$found_matches) {
        echo "   ❌ لم يوجد تطابق مباشر بين folder_name و file_id_number\n";
    }

    // Check if there's another table or field that might contain person names
    echo "\n🔍 البحث عن جداول أخرى قد تحتوي على أسماء:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table) {
        if(strpos(strtolower($table), 'person') !== false ||
           strpos(strtolower($table), 'user') !== false ||
           strpos(strtolower($table), 'name') !== false) {
            echo "   📋 جدول محتمل: $table\n";

            // Check structure
            $table_structure = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
            foreach($table_structure as $col) {
                if(strpos(strtolower($col['Field']), 'name') !== false) {
                    echo "      - عمود الاسم: {$col['Field']}\n";
                }
            }
        }
    }

    echo "\n✅ انتهى التحليل!\n";

} catch(Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
