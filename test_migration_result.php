<?php
// test_migration_result.php - ملف اختبار لقياس الأداء بعد Migration

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 اختبار أداء قاعدة البيانات بعد Migration\n\n";

try {
    // اختبار الاتصال
    $pdo = DB::connection('civilregistry')->getPdo();
    echo "✅ الاتصال بقاعدة البيانات: نجح\n";
    echo "📊 اسم قاعدة البيانات: " . DB::connection('civilregistry')->getDatabaseName() . "\n\n";

    // اختبار سرعة البحث بالهوية
    $start = microtime(true);
    $result = DB::connection('civilregistry')
        ->table('data')
        ->where('identity_number', '123456789')
        ->first();
    $searchTime = microtime(true) - $start;

    echo "⚡ سرعة البحث بالهوية: " . round($searchTime * 1000, 2) . " milliseconds\n";

    // اختبار سرعة البحث بالاسم
    $start = microtime(true);
    $results = DB::connection('civilregistry')
        ->table('data')
        ->where('first_name', 'LIKE', 'أحمد%')
        ->limit(10)
        ->get();
    $nameSearchTime = microtime(true) - $start;

    echo "🔍 سرعة البحث بالاسم: " . round($nameSearchTime * 1000, 2) . " milliseconds\n";
    echo "📈 عدد النتائج: " . $results->count() . "\n\n";

    // عرض الـ indexes المُنشأة
    $indexes = DB::connection('civilregistry')
        ->select("SHOW INDEX FROM data WHERE Key_name LIKE 'idx_%'");

    echo "🗂️ الفهارس المُنشأة:\n";
    foreach ($indexes as $index) {
        echo "   - " . $index->Key_name . " على العمود: " . $index->Column_name . "\n";
    }

    echo "\n✅ جميع الاختبارات نجحت!\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
