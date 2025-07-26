<?php
/**
 * فحص وتحسين إعدادات MySQL
 * MySQL Settings Analysis and Optimization
 */

echo "=== فحص إعدادات MySQL ===\n";
echo "MySQL Settings Analysis\n";
echo "========================\n\n";

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=aso;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "1. فحص إعدادات MySQL الحالية:\n";
    echo "Current MySQL Settings:\n\n";

    $settings = [
        'query_cache_size' => 'Query Cache Size',
        'query_cache_type' => 'Query Cache Type',
        'innodb_buffer_pool_size' => 'InnoDB Buffer Pool Size',
        'key_buffer_size' => 'MyISAM Key Buffer Size',
        'max_connections' => 'Max Connections',
        'thread_cache_size' => 'Thread Cache Size',
        'table_open_cache' => 'Table Open Cache',
        'tmp_table_size' => 'Temporary Table Size',
        'max_heap_table_size' => 'Max Heap Table Size',
        'sort_buffer_size' => 'Sort Buffer Size',
        'read_buffer_size' => 'Read Buffer Size',
        'join_buffer_size' => 'Join Buffer Size'
    ];

    foreach ($settings as $setting => $description) {
        try {
            $stmt = $pdo->query("SHOW VARIABLES LIKE '$setting'");
            $result = $stmt->fetch();
            if ($result) {
                echo "   - $description: " . $result['Value'] . "\n";
            }
        } catch (Exception $e) {
            echo "   - $description: Error retrieving\n";
        }
    }

    echo "\n2. فحص حالة عمليات البحث:\n";
    echo "Query Performance Status:\n\n";

    $statusVars = [
        'Slow_queries' => 'Slow Queries Count',
        'Questions' => 'Total Queries',
        'Uptime' => 'Server Uptime (seconds)',
        'Threads_connected' => 'Active Connections',
        'Innodb_buffer_pool_reads' => 'InnoDB Disk Reads',
        'Innodb_buffer_pool_read_requests' => 'InnoDB Buffer Reads',
        'Handler_read_first' => 'Full Table Scans',
        'Handler_read_key' => 'Index Reads',
        'Handler_read_next' => 'Sequential Reads'
    ];

    foreach ($statusVars as $var => $description) {
        try {
            $stmt = $pdo->query("SHOW STATUS LIKE '$var'");
            $result = $stmt->fetch();
            if ($result) {
                echo "   - $description: " . number_format($result['Value']) . "\n";
            }
        } catch (Exception $e) {
            echo "   - $description: Error retrieving\n";
        }
    }

    echo "\n3. فحص فهارس الجدول:\n";
    echo "Table Indexes Analysis:\n\n";

    $stmt = $pdo->query("SHOW INDEX FROM persons");
    $indexes = $stmt->fetchAll();

    echo "   - Total Indexes: " . count($indexes) . "\n";
    foreach ($indexes as $index) {
        echo "   - Index: " . $index['Key_name'] . " on " . $index['Column_name'] . " (Cardinality: " . number_format($index['Cardinality']) . ")\n";
    }

    echo "\n4. اختبار أداء الاستعلام مع EXPLAIN:\n";
    echo "Query Performance with EXPLAIN:\n\n";

    $query = "EXPLAIN SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1, CI_BIRTH_DT, CI_SEX_CD, CITY
              FROM persons
              WHERE CI_FIRST_ARB LIKE 'محمد%'
                 OR CI_FATHER_ARB LIKE 'محمد%'
                 OR CI_FAMILY_ARB LIKE 'محمد%'
                 OR CI_ID_NUM LIKE 'محمد%'
              ORDER BY
                CASE
                    WHEN CI_ID_NUM LIKE 'محمد%' THEN 1
                    WHEN CI_FIRST_ARB LIKE 'محمد%' THEN 2
                    ELSE 3
                END ASC,
                ID DESC
              LIMIT 10";

    $stmt = $pdo->query($query);
    $explain = $stmt->fetchAll();

    foreach ($explain as $row) {
        echo "   - Table: " . $row['table'] . "\n";
        echo "     Type: " . $row['type'] . "\n";
        echo "     Possible Keys: " . ($row['possible_keys'] ?: 'NULL') . "\n";
        echo "     Key Used: " . ($row['key'] ?: 'NULL') . "\n";
        echo "     Rows Examined: " . number_format($row['rows']) . "\n";
        echo "     Extra: " . ($row['Extra'] ?: 'None') . "\n\n";
    }

    echo "5. اختبار بحث محسن:\n";
    echo "Optimized Search Test:\n\n";

    // اختبار بحث بسيط مع فهرس
    $startTime = microtime(true);
    $stmt = $pdo->prepare("SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB FROM persons WHERE CI_FIRST_ARB LIKE ? LIMIT 10");
    $stmt->execute(['محمد%']);
    $results = $stmt->fetchAll();
    $endTime = microtime(true);
    $optimizedTime = round(($endTime - $startTime) * 1000, 2);

    echo "   - Simple Prefix Search Results: " . count($results) . "\n";
    echo "   - Simple Search Time: " . $optimizedTime . " ms\n\n";

    // اختبار بحث بدون ORDER BY معقد
    $startTime = microtime(true);
    $stmt = $pdo->prepare("SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB FROM persons WHERE CI_FIRST_ARB LIKE ? OR CI_FATHER_ARB LIKE ? LIMIT 10");
    $stmt->execute(['محمد%', 'محمد%']);
    $results = $stmt->fetchAll();
    $endTime = microtime(true);
    $noOrderTime = round(($endTime - $startTime) * 1000, 2);

    echo "   - No Complex ORDER BY Results: " . count($results) . "\n";
    echo "   - No ORDER BY Time: " . $noOrderTime . " ms\n\n";

    echo "=== تشخيص المشكلة ===\n";
    echo "Problem Diagnosis:\n\n";

    if ($optimizedTime > 1000) {
        echo "🚨 CRITICAL: حتى البحث البسيط بطيء!\n";
        echo "🚨 CRITICAL: Even simple search is slow!\n";
        echo "🔍 Possible causes:\n";
        echo "   - Database server is overloaded\n";
        echo "   - Hard disk is slow (use SSD)\n";
        echo "   - MySQL configuration needs optimization\n";
        echo "   - Network latency (if database is remote)\n\n";
    } elseif ($noOrderTime < $optimizedTime / 2) {
        echo "⚠️ Issue found: Complex ORDER BY is the bottleneck\n";
        echo "💡 Solution: Simplify sorting logic\n\n";
    } else {
        echo "✅ Database performance is acceptable for simple queries\n\n";
    }

    echo "=== التوصيات الفورية ===\n";
    echo "Immediate Recommendations:\n\n";
    echo "1. 🎯 استخدم البحث البسيط بدون ORDER BY معقد\n";
    echo "   Use simple search without complex ORDER BY\n\n";
    echo "2. 🚀 قم بتحسين إعدادات MySQL:\n";
    echo "   Optimize MySQL settings:\n";
    echo "   - innodb_buffer_pool_size = 1G (or 70% of RAM)\n";
    echo "   - query_cache_size = 128M\n";
    echo "   - key_buffer_size = 256M\n\n";
    echo "3. 💾 استخدم SSD بدلاً من HDD\n";
    echo "   Use SSD instead of HDD\n\n";
    echo "4. 📊 فعل query caching\n";
    echo "   Enable query caching\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "=== Analysis Complete ===\n";
