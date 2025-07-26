<?php
require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "⚡ إنشاء فهارس أساسية سريعة\n";
echo str_repeat("=", 50) . "\n";

// إنشاء فهارس prefix بسيطة وسريعة
$simpleIndexes = [
    "CREATE INDEX idx_first_prefix ON persons (CI_FIRST_ARB(10))",
    "CREATE INDEX idx_father_prefix ON persons (CI_FATHER_ARB(10))",
    "CREATE INDEX idx_family_prefix ON persons (CI_FAMILY_ARB(10))",
    "CREATE INDEX idx_id_prefix ON persons (CI_ID_NUM(10))"
];

foreach ($simpleIndexes as $indexQuery) {
    try {
        DB::statement($indexQuery);
        echo "✅ " . $indexQuery . "\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️ الفهرس موجود: " . substr($indexQuery, 0, 30) . "...\n";
        } else {
            echo "❌ خطأ: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n🔍 اختبار سرعة البحث:\n";
echo str_repeat("-", 30) . "\n";

$testQuery = "سعاد صلاح رضوان بلال";
echo "🎯 البحث عن: {$testQuery}\n";

$startTime = microtime(true);

// بحث محسن
$words = explode(' ', $testQuery);
$results = DB::table('persons')
    ->where(function($q) use ($words, $testQuery) {
        // بحث بالنص الكامل كـ prefix
        $q->whereRaw("CONCAT(COALESCE(CI_FIRST_ARB, ''), ' ', COALESCE(CI_FATHER_ARB, ''), ' ', COALESCE(CI_FAMILY_ARB, '')) LIKE ?", ["{$testQuery}%"]);

        // بحث بكل كلمة كـ prefix
        foreach ($words as $word) {
            if (strlen(trim($word)) >= 2) {
                $q->orWhere('CI_FIRST_ARB', 'LIKE', "{$word}%")
                  ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                  ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%");
            }
        }
    })
    ->orderBy('ID', 'DESC')
    ->limit(20)
    ->get(['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB']);

$endTime = microtime(true);
$searchTime = round(($endTime - $startTime) * 1000, 2);

echo "⏱️ الوقت: {$searchTime} ms\n";
echo "📊 النتائج: " . $results->count() . "\n";

if ($results->count() > 0) {
    echo "\n📋 أول 3 نتائج:\n";
    foreach ($results->take(3) as $i => $person) {
        $fullName = trim($person->CI_FIRST_ARB . ' ' . $person->CI_FATHER_ARB . ' ' . $person->CI_GRAND_FATHER_ARB . ' ' . $person->CI_FAMILY_ARB);
        echo "   " . ($i + 1) . ". {$fullName} (ID: {$person->CI_ID_NUM})\n";
    }
}

if ($searchTime < 1000) {
    echo "\n🚀 ممتاز! البحث أصبح سريعاً (أقل من ثانية)\n";
} else {
    echo "\n⚠️ لا يزال هناك مجال للتحسين\n";
}

echo "\n✅ تم إنشاء الفهارس الأساسية\n";
?>
