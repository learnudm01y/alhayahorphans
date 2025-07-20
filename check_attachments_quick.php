<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== فحص هيكل جدول attachments ===\n";
    $structure = $pdo->query("DESCRIBE attachments")->fetchAll(PDO::FETCH_ASSOC);
    foreach($structure as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n=== عينة من البيانات ===\n";
    $sample = $pdo->query("SELECT * FROM attachments LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach($sample as $row) {
        echo "Row: " . json_encode($row) . "\n";
    }

} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
