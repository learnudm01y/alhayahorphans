<?php

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "🔍 محاكاة البحث من المتحكم مباشرة\n";
    echo "=" . str_repeat("=", 50) . "\n";

    $query = "Kyle Christine Byers Brenda Mason Nayda Ayers";
    echo "🎯 البحث عن: '$query'\n\n";

    // إنشاء مثيل من المتحكم
    $searchService = app(\App\Services\SearchService::class);
    $controller = new \App\Http\Controllers\Admin\ProfileSearchController($searchService);

    // محاكاة الطلب
    $request = new Illuminate\Http\Request();
    $request->merge(['query' => $query]);

    echo "📤 استدعاء المتحكم...\n";
    $response = $controller->quickSearch($request);

    echo "📦 استجابة المتحكم:\n";
    echo "   النوع: " . get_class($response) . "\n";

    if ($response instanceof Illuminate\Http\JsonResponse) {
        $data = $response->getData(true);
        echo "   البيانات: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "   المحتوى: " . $response->getContent() . "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
    echo "🔍 التفاصيل: " . $e->getTraceAsString() . "\n";
}
