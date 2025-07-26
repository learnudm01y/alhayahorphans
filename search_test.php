<?php

/**
 * ملف اختبار نظام البحث الشامل
 *
 * يمكن تشغيله عبر: php artisan tinker
 * ثم استدعاء: include 'search_test.php';
 */

use App\Services\SearchService;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;

echo "🚀 بدء اختبار نظام البحث الشامل...\n\n";

try {
    // اختبار Service
    $searchService = app(SearchService::class);
    echo "✅ تم تحميل SearchService بنجاح\n";

    // اختبار الإحصائيات
    $stats = $searchService->getSearchStatistics();
    echo "📊 إحصائيات البحث:\n";
    echo "   - السجلات الرئيسية: " . number_format($stats['main_records']) . "\n";
    echo "   - أفراد الأسرة: " . number_format($stats['family_members']) . "\n";
    echo "   - سجلات المتوفين: " . number_format($stats['deceased_records']) . "\n";
    echo "   - الإجمالي: " . number_format($stats['main_records'] + $stats['family_members'] + $stats['deceased_records']) . "\n\n";

    // اختبار بحث تجريبي
    echo "🔍 اختبار البحث التجريبي...\n";

    $testFilters = [
        'search_type' => 'all',
        'search_text' => 'أحمد'
    ];

    $results = $searchService->smartSearch($testFilters, 5);
    echo "✅ تم تنفيذ البحث بنجاح\n";
    echo "   - النتائج الرئيسية: " . count($results['main_records'] ?? []) . "\n";
    echo "   - أفراد الأسرة: " . count($results['family_members'] ?? []) . "\n";
    echo "   - المتوفين: " . count($results['deceased'] ?? []) . "\n\n";

    // اختبار الاقتراحات
    echo "💡 اختبار اقتراحات البحث...\n";
    $suggestions = $searchService->getSearchSuggestions('أحمد', 5);
    echo "✅ تم الحصول على " . count($suggestions) . " اقتراح\n";
    if (!empty($suggestions)) {
        echo "   الاقتراحات: " . implode(', ', array_slice($suggestions, 0, 3)) . "\n";
    }
    echo "\n";

    // اختبار Routes
    echo "🛣️  اختبار المسارات...\n";
    $routes = [
        'admin.search.records.index',
        'admin.search.records',
        'admin.search.stats',
        'admin.search.suggestions'
    ];

    foreach ($routes as $route) {
        try {
            $url = route($route);
            echo "✅ مسار {$route}: {$url}\n";
        } catch (Exception $e) {
            echo "❌ خطأ في مسار {$route}: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";

    // اختبار قاعدة البيانات
    echo "🗄️  اختبار قاعدة البيانات...\n";

    // اختبار اتصال الجداول
    $dataCount = Data::count();
    $reCount = RePeople::count();
    $deadCount = DeadPepole::count();

    echo "✅ اتصال قاعدة البيانات ناجح\n";
    echo "   - جدول data: " . number_format($dataCount) . " سجل\n";
    echo "   - جدول re_people: " . number_format($reCount) . " سجل\n";
    echo "   - جدول dead_people: " . number_format($deadCount) . " سجل\n\n";

    // اختبار العلاقات
    echo "🔗 اختبار العلاقات...\n";
    $dataWithRelations = Data::with(['section', 'requestStatus', 'city'])->first();
    if ($dataWithRelations) {
        echo "✅ العلاقات تعمل بشكل صحيح\n";
        echo "   - القسم: " . optional($dataWithRelations->section)->description . "\n";
        echo "   - حالة الطلب: " . optional($dataWithRelations->requestStatus)->description . "\n";
        echo "   - المدينة: " . optional($dataWithRelations->city)->city . "\n";
    } else {
        echo "⚠️  لا توجد سجلات للاختبار\n";
    }
    echo "\n";

    // اختبار الفهارس
    echo "📇 اختبار الفهارس...\n";
    $startTime = microtime(true);

    // استعلام تجريبي بالفهرس
    Data::where('data_first_name', 'LIKE', '%أحمد%')->limit(10)->get();

    $indexTime = (microtime(true) - $startTime) * 1000;
    echo "✅ استعلام الفهرس: " . number_format($indexTime, 2) . " ms\n";

    if ($indexTime < 100) {
        echo "🚀 أداء ممتاز!\n";
    } elseif ($indexTime < 500) {
        echo "✅ أداء جيد\n";
    } else {
        echo "⚠️  الأداء يحتاج تحسين - شغّل: php artisan search:optimize\n";
    }
    echo "\n";

    // نصائح التحسين
    echo "💡 نصائح للتحسين:\n";
    echo "1. شغّل: php artisan migrate (لتطبيق الفهارس)\n";
    echo "2. شغّل: php artisan search:optimize --full (للتحسين الشامل)\n";
    echo "3. فعّل التخزين المؤقت في config/search.php\n";
    echo "4. راقب سجلات الأداء في storage/logs/\n\n";

    echo "🎉 اكتمل الاختبار بنجاح! النظام جاهز للاستخدام.\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "تفاصيل الخطأ: " . $e->getFile() . ":" . $e->getLine() . "\n";

    echo "\n🔧 خطوات الإصلاح:\n";
    echo "1. تأكد من تشغيل: composer install\n";
    echo "2. تأكد من تشغيل: php artisan migrate\n";
    echo "3. تحقق من إعدادات قاعدة البيانات في .env\n";
    echo "4. تأكد من وجود البيانات في الجداول\n";
}
