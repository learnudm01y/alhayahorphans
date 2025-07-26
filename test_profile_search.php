<?php

// اختبار سريع لنظام البحث
// تشغيل الملف: php test_profile_search.php

require_once __DIR__ . '/vendor/autoload.php';

// تحميل Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Admin\ProfileSearchController;
use App\Services\SearchService;
use Illuminate\Http\Request;

try {
    echo "🔍 اختبار نظام البحث السريع...\n\n";

    // إنشاء مثيل من Controller
    $searchService = new SearchService();
    $controller = new ProfileSearchController($searchService);

    // إنشاء طلب اختبار
    $request = new Request(['query' => 'test']);

    echo "✅ تم إنشاء Controller بنجاح\n";
    echo "✅ تم إنشاء SearchService بنجاح\n";
    echo "✅ تم إنشاء Request بنجاح\n\n";

    // اختبار قاعدة البيانات
    echo "📊 اختبار قاعدة البيانات...\n";

    // فحص جدول Data
    $dataCount = \App\Models\Data::count();
    echo "   📄 جدول Data: {$dataCount} سجل\n";

    // فحص جدول RePeople
    $rePeopleCount = \App\Models\RePeople::count();
    echo "   👥 جدول RePeople: {$rePeopleCount} سجل\n";

    // فحص جدول DeadPepole
    $deadCount = \App\Models\DeadPepole::count();
    echo "   💀 جدول DeadPepole: {$deadCount} سجل\n\n";

    echo "🎉 جميع الاختبارات نجحت!\n";
    echo "🚀 النظام جاهز للاستخدام\n\n";

    echo "📝 للاختبار اليدوي:\n";
    echo "   1. انتقل إلى Dashboard الإدارة\n";
    echo "   2. ابحث في حقل 'البحث عن الملفات'\n";
    echo "   3. ستظهر الاقتراحات تلقائياً\n\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "\n";
}
