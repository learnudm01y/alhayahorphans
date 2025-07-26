<?php
/**
 * فحص حالة النظام والفهارس على الاستضافة المشتركة
 */

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📊 فحص حالة النظام على الاستضافة المشتركة\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    // 1. فحص اتصال قاعدة البيانات
    echo "\n1️⃣ فحص اتصال قاعدة البيانات:\n";
    $connectionTime = microtime(true);
    $totalRecords = DB::table('persons')->count();
    $connectionTime = round((microtime(true) - $connectionTime) * 1000, 2);

    echo "✅ الاتصال ناجح\n";
    echo "📈 إجمالي السجلات: " . number_format($totalRecords) . "\n";
    echo "⏱️ وقت الاستعلام: " . $connectionTime . " ms\n";

    // 2. فحص الفهارس الموجودة
    echo "\n2️⃣ فحص الفهارس الموجودة:\n";
    $indexes = DB::select("SHOW INDEX FROM persons");

    $indexNames = [];
    foreach ($indexes as $index) {
        if (!isset($indexNames[$index->Key_name])) {
            $indexNames[$index->Key_name] = [];
        }
        $indexNames[$index->Key_name][] = $index->Column_name;
    }

    echo "📊 عدد الفهارس: " . count($indexNames) . "\n";
    foreach ($indexNames as $name => $columns) {
        echo "   ✓ " . $name . " على: " . implode(', ', $columns) . "\n";
    }

    // 3. اختبار سرعة البحث
    echo "\n3️⃣ اختبار سرعة البحث:\n";

    $searchTests = [
        ['name' => 'بحث دقيق برقم الهوية', 'query' => "SELECT * FROM persons WHERE CI_ID_NUM = '123456789' LIMIT 1"],
        ['name' => 'بحث بالاسم الأول', 'query' => "SELECT * FROM persons WHERE CI_FIRST_ARB LIKE 'محمد%' LIMIT 5"],
        ['name' => 'بحث باسم الأب', 'query' => "SELECT * FROM persons WHERE CI_FATHER_ARB LIKE 'أحمد%' LIMIT 5"],
        ['name' => 'بحث مركب', 'query' => "SELECT * FROM persons WHERE CI_FIRST_ARB = 'محمد' AND CI_FATHER_ARB = 'أحمد' LIMIT 3"]
    ];

    foreach ($searchTests as $test) {
        $startTime = microtime(true);
        try {
            $results = DB::select($test['query']);
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            echo "🎯 " . $test['name'] . ": " . $executionTime . " ms (" . count($results) . " نتيجة)\n";

            if ($executionTime > 1000) {
                echo "   ⚠️ بطيء - يُنصح بإضافة فهرس\n";
            } elseif ($executionTime < 100) {
                echo "   ✅ سريع جداً\n";
            } else {
                echo "   ✓ مقبول\n";
            }

        } catch (Exception $e) {
            echo "❌ " . $test['name'] . ": خطأ - " . $e->getMessage() . "\n";
        }
    }

    // 4. فحص إعدادات MySQL
    echo "\n4️⃣ فحص إعدادات MySQL:\n";
    try {
        $settings = [
            'max_execution_time' => DB::select("SHOW VARIABLES LIKE 'max_execution_time'")[0] ?? null,
            'innodb_lock_wait_timeout' => DB::select("SHOW VARIABLES LIKE 'innodb_lock_wait_timeout'")[0] ?? null,
            'max_statement_time' => DB::select("SHOW VARIABLES LIKE 'max_statement_time'")[0] ?? null
        ];

        foreach ($settings as $name => $setting) {
            if ($setting) {
                echo "   " . $name . ": " . $setting->Value . "\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️ لا يمكن فحص إعدادات MySQL (صلاحيات محدودة)\n";
    }

    // 5. اختبار APIs الجديدة
    echo "\n5️⃣ اختبار APIs الجديدة:\n";

    $apiTests = [
        '/api/search/exact-only?q=محمد',
        '/api/search/final-exact?q=أحمد&limit=3'
    ];

    foreach ($apiTests as $api) {
        echo "🔗 اختبار: " . $api . "\n";
        echo "   💡 استخدم: curl -X GET 'http://yoursite.com" . $api . "'\n";
    }

    // 6. توصيات للتحسين
    echo "\n6️⃣ توصيات للتحسين:\n";

    if ($totalRecords > 1000000) {
        echo "📈 قاعدة بيانات كبيرة (" . number_format($totalRecords) . " سجل):\n";
        echo "   • استخدم البحث الدقيق بدلاً من البحث العام\n";
        echo "   • قلل عدد النتائج (limit=10)\n";
        echo "   • فعّل الكاش للاستعلامات المتكررة\n";
    }

    if (count($indexNames) < 5) {
        echo "🔧 فهارس قليلة:\n";
        echo "   • نفذ: php create_indexes_gradually.php\n";
        echo "   • أو استخدم الفهارس البسيطة السريعة\n";
    }

    echo "\n🎉 انتهى فحص النظام!\n";

} catch (Exception $e) {
    echo "❌ خطأ عام: " . $e->getMessage() . "\n";
    echo "💡 تأكد من صحة إعدادات قاعدة البيانات في .env\n";
}
