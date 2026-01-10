<?php
$pdo = new PDO('mysql:host=localhost;dbname=aso', 'root', '');
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "All tables:\n";
foreach($tables as $t) {
    if(stripos($t, 'dead') !== false || stripos($t, 'people') !== false || stripos($t, 'pepole') !== false) {
        echo "  - " . $t . "\n";
    }
}
