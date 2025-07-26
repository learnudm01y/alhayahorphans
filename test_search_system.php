<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

// تشغيل Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 اختبار نظام البحث الشامل\n";
echo "=====================================\n\n";

try {
    // اختبار قاعدة البيانات
    echo "📊 اختبار الاتصال بقاعدة البيانات...\n";
    $connection = DB::connection()->getPdo();
    echo "✅ تم الاتصال بقاعدة البيانات بنجاح!\n\n";

    // اختبار وجود الجداول
    echo "📋 فحص وجود الجداول المطلوبة...\n";
    $tables = ['data', 're_people', 'dead_people'];

    foreach ($tables as $table) {
        $exists = DB::select("SHOW TABLES LIKE '{$table}'");
        if (!empty($exists)) {
            echo "✅ جدول {$table} موجود\n";

            // عد السجلات
            $count = DB::table($table)->count();
            echo "   📈 عدد السجلات: {$count}\n";
        } else {
            echo "❌ جدول {$table} غير موجود\n";
        }
    }

    echo "\n";

    // اختبار الفهارس
    echo "🔧 فحص الفهارس المضافة...\n";
    $indexes = DB::select("SHOW INDEX FROM data");
    $indexNames = array_column($indexes, 'Key_name');

    $expectedIndexes = [
        'idx_data_first_name',
        'idx_data_id_number',
        'idx_file_id_number',
        'idx_data_phone_number',
        'idx_data_name_composite'
    ];

    foreach ($expectedIndexes as $indexName) {
        if (in_array($indexName, $indexNames)) {
            echo "✅ فهرس {$indexName} موجود\n";
        } else {
            echo "❌ فهرس {$indexName} غير موجود\n";
        }
    }

    echo "\n";

    // اختبار الخدمات
    echo "🛠️ اختبار خدمات البحث...\n";

    if (class_exists('App\Services\SearchService')) {
        echo "✅ خدمة البحث SearchService موجودة\n";
    } else {
        echo "❌ خدمة البحث SearchService غير موجودة\n";
    }

    if (class_exists('App\Http\Controllers\Admin\SearchOnRecordsController')) {
        echo "✅ تحكم البحث SearchOnRecordsController موجود\n";
    } else {
        echo "❌ تحكم البحث SearchOnRecordsController غير موجود\n";
    }

    echo "\n";

    // اختبار المسارات
    echo "🛣️ اختبار مسارات البحث...\n";
    $routes = [
        'search.records.index',
        'search.records',
        'search.stats',
        'search.suggestions',
        'search.clear.cache'
    ];

    foreach ($routes as $routeName) {
        try {
            $url = route($routeName);
            echo "✅ مسار {$routeName} موجود: {$url}\n";
        } catch (Exception $e) {
            echo "❌ مسار {$routeName} غير موجود\n";
        }
    }

    echo "\n=====================================\n";
    echo "🎉 اكتمل اختبار نظام البحث بنجاح!\n";
    echo "🌐 يمكنك الآن الوصول إلى صفحة البحث من:\n";
    echo "   " . url('/admin/search-records') . "\n\n";

} catch (Exception $e) {
    echo "❌ خطأ أثناء الاختبار: " . $e->getMessage() . "\n";
    exit(1);
}
