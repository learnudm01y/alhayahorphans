<?php

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PersonSearchable;
use Illuminate\Support\Facades\DB;

echo "=== اختبار تقنية Scout للبحث السريع ===\n\n";

// فحص إعدادات Scout
echo "1. فحص إعدادات Scout:\n";
echo "Driver: " . config('scout.driver') . "\n";
echo "Prefix: " . (config('scout.prefix') ?: 'لا يوجد') . "\n\n";

// فحص البيانات
echo "2. فحص البيانات:\n";
$totalPersons = DB::table('persons')->count();
$validPersons = DB::table('persons')
    ->whereNotNull('CI_FIRST_ARB')
    ->whereNotNull('CI_ID_NUM')
    ->where('CI_FIRST_ARB', '!=', '')
    ->where('CI_ID_NUM', '!=', '')
    ->count();

echo "إجمالي السجلات: " . number_format($totalPersons) . "\n";
echo "السجلات الصالحة للفهرسة: " . number_format($validPersons) . "\n";
echo "نسبة الصلاحية: " . round(($validPersons / $totalPersons) * 100, 2) . "%\n\n";

// اختبار Model
echo "3. اختبار PersonSearchable Model:\n";
try {
    $samplePerson = PersonSearchable::whereNotNull('CI_FIRST_ARB')
        ->whereNotNull('CI_ID_NUM')
        ->where('CI_FIRST_ARB', '!=', '')
        ->first();

    if ($samplePerson) {
        echo "تم العثور على نموذج اختبار:\n";
        echo "- ID: " . $samplePerson->ID . "\n";
        echo "- رقم الهوية: " . $samplePerson->CI_ID_NUM . "\n";
        echo "- الاسم: " . ($samplePerson->CI_FIRST_ARB ?? 'غير محدد') . "\n";
        echo "- اسم الأب: " . ($samplePerson->CI_FATHER_ARB ?? 'غير محدد') . "\n";
        echo "- العائلة: " . ($samplePerson->CI_FAMILY_ARB ?? 'غير محدد') . "\n\n";

        // اختبار toSearchableArray
        $searchableData = $samplePerson->toSearchableArray();
        echo "البيانات القابلة للفهرسة:\n";
        foreach ($searchableData as $key => $value) {
            echo "- $key: " . (is_string($value) ? $value : json_encode($value)) . "\n";
        }
        echo "\n";
    } else {
        echo "❌ لم يتم العثور على أي سجل صالح للاختبار!\n\n";
    }
} catch (Exception $e) {
    echo "❌ خطأ في Model: " . $e->getMessage() . "\n\n";
}

// فهرسة عينة صغيرة للاختبار
echo "4. فهرسة عينة للاختبار (100 سجل):\n";
try {
    $startTime = microtime(true);

    $testRecords = PersonSearchable::whereNotNull('CI_FIRST_ARB')
        ->whereNotNull('CI_ID_NUM')
        ->where('CI_FIRST_ARB', '!=', '')
        ->limit(100)
        ->get();

    echo "تم جلب " . $testRecords->count() . " سجل للفهرسة...\n";

    // فهرسة السجلات
    $testRecords->searchable();

    $endTime = microtime(true);
    $indexTime = round(($endTime - $startTime) * 1000, 2);

    echo "✅ تم فهرسة " . $testRecords->count() . " سجل في " . $indexTime . " مللي ثانية\n\n";

} catch (Exception $e) {
    echo "❌ خطأ في الفهرسة: " . $e->getMessage() . "\n\n";
}

// اختبار البحث
echo "5. اختبار البحث:\n";
try {
    $searchTerms = ['احمد', 'محمد', 'علي', 'فاطمة'];

    foreach ($searchTerms as $term) {
        echo "البحث عن: '$term'\n";
        $startTime = microtime(true);

        $results = PersonSearchable::search($term)->take(10)->get();

        $endTime = microtime(true);
        $searchTime = round(($endTime - $startTime) * 1000, 2);

        echo "- النتائج: " . $results->count() . " سجل\n";
        echo "- الوقت: " . $searchTime . " مللي ثانية\n";

        if ($results->count() > 0) {
            echo "- أول نتيجة: " . ($results->first()->CI_FIRST_ARB ?? 'غير محدد') . " " . ($results->first()->CI_FATHER_ARB ?? '') . "\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في البحث: " . $e->getMessage() . "\n\n";
}

// اختبار الخدمات
echo "6. اختبار ScoutSearchService:\n";
try {
    $scoutService = new \App\Services\ScoutSearchService();

    $searchResult = $scoutService->instantSearch('احمد', 20);

    echo "نتيجة البحث السريع:\n";
    echo "- عدد النتائج: " . $searchResult['count'] . "\n";
    echo "- وقت البحث: " . $searchResult['search_time'] . " مللي ثانية\n";
    echo "- المحرك: " . $searchResult['engine'] . "\n";
    echo "- الكاش: " . ($searchResult['cached'] ? 'مفعل' : 'معطل') . "\n\n";

    if ($searchResult['count'] > 0) {
        echo "أمثلة من النتائج:\n";
        foreach (array_slice($searchResult['results'], 0, 3) as $i => $result) {
            echo "- " . ($i + 1) . ": " . $result['full_name'] . " (ID: " . $result['id_num'] . ")\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في ScoutSearchService: " . $e->getMessage() . "\n\n";
}

// إحصائيات النظام
echo "7. إحصائيات النظام:\n";
try {
    $scoutService = new \App\Services\ScoutSearchService();
    $stats = $scoutService->getSearchStats();

    echo "- إجمالي السجلات: " . number_format($stats['total_records']) . "\n";
    echo "- السجلات المفهرسة: " . number_format($stats['indexed_records']) . "\n";
    echo "- نسبة الفهرسة: " . $stats['index_percentage'] . "%\n";
    echo "- محرك البحث: " . $stats['engine'] . "\n";
    echo "- آخر تحديث: " . $stats['last_updated'] . "\n\n";

} catch (Exception $e) {
    echo "❌ خطأ في الإحصائيات: " . $e->getMessage() . "\n\n";
}

echo "=== انتهى الاختبار ===\n";
echo "التوقيت: " . date('Y-m-d H:i:s') . "\n";

// تنظيف الكاش للاختبار التالي
try {
    \Illuminate\Support\Facades\Cache::flush();
    echo "تم تنظيف الكاش\n";
} catch (Exception $e) {
    echo "تحذير: فشل في تنظيف الكاش\n";
}
