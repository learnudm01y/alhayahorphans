<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار البحث الخارجي: محمد عبدالناصر رفيق الفرا           ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$searchTerm = "محمد عبدالناصر رفيق الفرا";

echo "🔍 البحث عن: $searchTerm\n\n";

// محاكاة searchAllTables
$controller = new \App\Http\Controllers\Users\GeneralRegistrationController();
$request = new \Illuminate\Http\Request(['search_term' => $searchTerm]);

$start = microtime(true);
$response = $controller->searchAllTables($request);
$result = $response->getData(true);
$time = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time} ms\n";

if (isset($result['found']) && $result['found']) {
    echo "✅ تم العثور على نتيجة\n";
    echo "📍 المصدر: {$result['source']}\n";

    if (isset($result['data']['id_number'])) {
        echo "🆔 رقم الهوية: {$result['data']['id_number']}\n";
        echo "👤 الاسم: {$result['data']['full_name']}\n";
    }

    if ($result['data']['id_number'] == '801448911') {
        echo "\n✅✅✅ النتيجة صحيحة! رقم الهوية المتوقع: 801448911\n";
    } else {
        echo "\n❌ النتيجة خاطئة! المتوقع: 801448911، الفعلي: {$result['data']['id_number']}\n";
    }
} else {
    echo "❌ لم يتم العثور على نتيجة\n";
}
