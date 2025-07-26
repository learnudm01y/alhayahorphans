<?php
/**
 * اختبار البحث الدقيق لاسم "ايمان عبد الرحمن سليمان ابداح"
 * Test Exact Search for "ايمان عبد الرحمن سليمان ابداح"
 */

echo "=== اختبار البحث الدقيق للاسم العربي ===\n";
echo "Testing Exact Search for Arabic Name\n";
echo "====================================\n\n";

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=aso;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]
    );

    $searchName = "ايمان عبد الرحمن سليمان ابداح";
    $words = explode(' ', $searchName);

    echo "🔍 البحث عن: $searchName\n";
    echo "📝 الكلمات: " . implode(', ', $words) . "\n";
    echo "📊 عدد الكلمات: " . count($words) . "\n\n";

    // 1. البحث بالاسم الكامل الدقيق
    echo "1️⃣ البحث بالمطابقة التامة للأسماء الأربعة:\n";
    $startTime = microtime(true);

    $sql1 = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB
             FROM persons
             WHERE CI_FIRST_ARB = ?
               AND CI_FATHER_ARB = ?
               AND CI_GRAND_FATHER_ARB = ?
               AND CI_FAMILY_ARB = ?
             LIMIT 5";

    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([$words[0], $words[1], $words[2], $words[3]]);
    $exact_results = $stmt1->fetchAll();

    $queryTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$queryTime} ms\n";
    echo "   📊 النتائج: " . count($exact_results) . "\n";
    foreach ($exact_results as $result) {
        echo "   ✅ {$result['CI_FIRST_ARB']} {$result['CI_FATHER_ARB']} {$result['CI_GRAND_FATHER_ARB']} {$result['CI_FAMILY_ARB']} (ID: {$result['ID']})\n";
    }

    // 2. البحث بالاسم الكامل كـ prefix
    echo "\n2️⃣ البحث بـ Prefix للأسماء الأربعة:\n";
    $startTime = microtime(true);

    $sql2 = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB
             FROM persons
             WHERE CI_FIRST_ARB LIKE ?
               AND CI_FATHER_ARB LIKE ?
               AND CI_GRAND_FATHER_ARB LIKE ?
               AND CI_FAMILY_ARB LIKE ?
             LIMIT 5";

    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute(["{$words[0]}%", "{$words[1]}%", "{$words[2]}%", "{$words[3]}%"]);
    $prefix_results = $stmt2->fetchAll();

    $queryTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$queryTime} ms\n";
    echo "   📊 النتائج: " . count($prefix_results) . "\n";
    foreach ($prefix_results as $result) {
        echo "   ✅ {$result['CI_FIRST_ARB']} {$result['CI_FATHER_ARB']} {$result['CI_GRAND_FATHER_ARB']} {$result['CI_FAMILY_ARB']} (ID: {$result['ID']})\n";
    }

    // 3. البحث بالاسم الأول فقط للتأكد من وجود البيانات
    echo "\n3️⃣ البحث بالاسم الأول فقط (ايمان):\n";
    $startTime = microtime(true);

    $sql3 = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB
             FROM persons
             WHERE CI_FIRST_ARB = ?
             ORDER BY ID DESC
             LIMIT 10";

    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute([$words[0]]);
    $first_only_results = $stmt3->fetchAll();

    $queryTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$queryTime} ms\n";
    echo "   📊 النتائج: " . count($first_only_results) . "\n";
    foreach ($first_only_results as $result) {
        echo "   📄 {$result['CI_FIRST_ARB']} {$result['CI_FATHER_ARB']} {$result['CI_GRAND_FATHER_ARB']} {$result['CI_FAMILY_ARB']} (ID: {$result['ID']})\n";
    }

    // 4. البحث بالثلاث كلمات الأولى
    echo "\n4️⃣ البحث بالثلاث كلمات الأولى (ايمان عبد الرحمن سليمان):\n";
    $startTime = microtime(true);

    $sql4 = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB
             FROM persons
             WHERE CI_FIRST_ARB = ?
               AND CI_FATHER_ARB = ?
               AND CI_GRAND_FATHER_ARB = ?
             LIMIT 5";

    $stmt4 = $pdo->prepare($sql4);
    $stmt4->execute([$words[0], $words[1], $words[2]]);
    $three_word_results = $stmt4->fetchAll();

    $queryTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$queryTime} ms\n";
    echo "   📊 النتائج: " . count($three_word_results) . "\n";
    foreach ($three_word_results as $result) {
        echo "   🔍 {$result['CI_FIRST_ARB']} {$result['CI_FATHER_ARB']} {$result['CI_GRAND_FATHER_ARB']} {$result['CI_FAMILY_ARB']} (ID: {$result['ID']})\n";
    }

    // 5. فحص تركيب الاسم في قاعدة البيانات
    echo "\n5️⃣ فحص الأسماء التي تحتوي على 'ايمان' و 'عبد':\n";
    $startTime = microtime(true);

    $sql5 = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB
             FROM persons
             WHERE CI_FIRST_ARB LIKE '%ايمان%'
               AND CI_FATHER_ARB LIKE '%عبد%'
             ORDER BY ID DESC
             LIMIT 5";

    $stmt5 = $pdo->prepare($sql5);
    $stmt5->execute();
    $contains_results = $stmt5->fetchAll();

    $queryTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$queryTime} ms\n";
    echo "   📊 النتائج: " . count($contains_results) . "\n";
    foreach ($contains_results as $result) {
        echo "   📋 {$result['CI_FIRST_ARB']} {$result['CI_FATHER_ARB']} {$result['CI_GRAND_FATHER_ARB']} {$result['CI_FAMILY_ARB']} (ID: {$result['ID']})\n";
    }

    // 6. البحث العام للعثور على الاسم
    echo "\n6️⃣ البحث العام في جميع الحقول:\n";
    $startTime = microtime(true);

    $sql6 = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB
             FROM persons
             WHERE CONCAT(COALESCE(CI_FIRST_ARB, ''), ' ', COALESCE(CI_FATHER_ARB, ''), ' ', COALESCE(CI_GRAND_FATHER_ARB, ''), ' ', COALESCE(CI_FAMILY_ARB, '')) LIKE ?
             ORDER BY ID DESC
             LIMIT 5";

    $stmt6 = $pdo->prepare($sql6);
    $stmt6->execute(["%{$searchName}%"]);
    $general_results = $stmt6->fetchAll();

    $queryTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$queryTime} ms\n";
    echo "   📊 النتائج: " . count($general_results) . "\n";
    foreach ($general_results as $result) {
        echo "   🎯 {$result['CI_FIRST_ARB']} {$result['CI_FATHER_ARB']} {$result['CI_GRAND_FATHER_ARB']} {$result['CI_FAMILY_ARB']} (ID: {$result['ID']})\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== انتهى الاختبار ===\n";
