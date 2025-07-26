<?php
/**
 * البحث الدقيق السريع مباشرة - بدون Laravel
 * Quick Exact Search - Without Laravel
 */

echo "=== البحث الدقيق السريع ===\n";
echo "Quick Exact Search Test\n";
echo "========================\n\n";

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
    echo "🔍 البحث عن: $searchName\n\n";

    // الطريقة الصحيحة: البحث الدقيق
    $startTime = microtime(true);

    $sql = "SELECT ID, CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB
            FROM persons
            WHERE CI_FIRST_ARB = 'ايمان'
              AND CI_FATHER_ARB = 'عبد الرحمن'
              AND CI_GRAND_FATHER_ARB = 'سليمان'
              AND CI_FAMILY_ARB = 'ابداح'
            LIMIT 5";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll();

    $queryTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "✅ البحث الدقيق:\n";
    echo "   ⏱️ الوقت: {$queryTime} ms\n";
    echo "   📊 النتائج: " . count($results) . "\n";

    if (count($results) > 0) {
        foreach ($results as $result) {
            $fullName = trim($result['CI_FIRST_ARB'] . ' ' . $result['CI_FATHER_ARB'] . ' ' . $result['CI_GRAND_FATHER_ARB'] . ' ' . $result['CI_FAMILY_ARB']);
            echo "   🎯 {$fullName} (ID: {$result['ID']})\n";
            echo "      📄 رقم الهوية: {$result['CI_ID_NUM']}\n";
        }

        echo "\n🎉 تم العثور على المطابقة التامة!\n";
        echo "💡 هذا هو النوع الدقيق من البحث الذي تريده.\n";

    } else {
        echo "\n❌ لم يتم العثور على مطابقة تامة\n";
        echo "💭 قد يكون هناك اختلاف طفيف في الأسماء\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== انتهى الاختبار ===\n";
