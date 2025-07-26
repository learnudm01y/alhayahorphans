<?php

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== الحل النهائي لمشكلة FULLTEXT INDEX ===\n\n";

echo "المشكلة: FULLTEXT INDEX لا يدعم المفاتيح المكررة في MySQL\n";
echo "الحل: إنشاء فهارس منفصلة لكل عمود بدلاً من فهرس مركب\n\n";

// إنشاء فهارس FULLTEXT منفصلة لكل عمود
$fulltextIndexes = [
    'idx_ft_first_name' => 'CI_FIRST_ARB',
    'idx_ft_father_name' => 'CI_FATHER_ARB',
    'idx_ft_grand_father_name' => 'CI_GRAND_FATHER_ARB',
    'idx_ft_family_name' => 'CI_FAMILY_ARB'
];

foreach ($fulltextIndexes as $indexName => $column) {
    echo "جاري إنشاء $indexName على العمود $column...\n";

    // حذف الفهرس إذا كان موجود
    try {
        DB::statement("ALTER TABLE persons DROP INDEX $indexName");
        echo "تم حذف الفهرس القديم: $indexName\n";
    } catch (Exception $e) {
        // تجاهل الخطأ
    }

    // إنشاء الفهرس الجديد
    try {
        DB::statement("ALTER TABLE persons ADD FULLTEXT INDEX $indexName ($column)");
        echo "✅ تم إنشاء $indexName بنجاح!\n";

        // اختبار الفهرس
        $testQuery = "SELECT COUNT(*) as count FROM persons WHERE MATCH($column) AGAINST('احمد' IN BOOLEAN MODE)";
        $result = DB::select($testQuery);
        echo "   اختبار البحث: " . number_format($result[0]->count) . " نتيجة\n";

    } catch (Exception $e) {
        echo "❌ فشل في إنشاء $indexName: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// إنشاء خدمة بحث محسنة تستخدم الفهارس المنفصلة
echo "جاري إنشاء خدمة بحث محسنة...\n";

$optimizedSearchService = '<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Services\SearchCacheService;

class OptimizedPersonSearchService
{
    protected $cacheService;

    public function __construct(SearchCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * بحث سريع بالنص الكامل باستخدام فهارس منفصلة
     */
    public function fulltextSearch($searchTerm, $limit = 100)
    {
        $cacheKey = "fulltext_search_" . md5($searchTerm . "_" . $limit);

        return $this->cacheService->getQuickSearchResults($searchTerm, $limit, function() use ($searchTerm, $limit) {

            // تنظيف مصطلح البحث
            $cleanTerm = trim($searchTerm);
            $booleanTerm = "+$cleanTerm";

            // البحث في جميع الأعمدة باستخدام UNION
            $query = "
                (SELECT *, \'first_name\' as match_type, 3 as relevance
                 FROM persons
                 WHERE MATCH(CI_FIRST_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, \'father_name\' as match_type, 2 as relevance
                 FROM persons
                 WHERE MATCH(CI_FATHER_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, \'grand_father_name\' as match_type, 1 as relevance
                 FROM persons
                 WHERE MATCH(CI_GRAND_FATHER_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, \'family_name\' as match_type, 2 as relevance
                 FROM persons
                 WHERE MATCH(CI_FAMILY_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                ORDER BY relevance DESC, ID DESC
                LIMIT ?
            ";

            return DB::select($query, [
                $booleanTerm, $limit,
                $booleanTerm, $limit,
                $booleanTerm, $limit,
                $booleanTerm, $limit,
                $limit
            ]);
        });
    }

    /**
     * بحث سريع مبسط
     */
    public function quickSearch($searchTerm, $limit = 50)
    {
        $cacheKey = "quick_search_" . md5($searchTerm . "_" . $limit);

        return $this->cacheService->getQuickSearchResults($searchTerm, $limit, function() use ($searchTerm, $limit) {

            $searchPattern = "%" . trim($searchTerm) . "%";

            return DB::table("persons")
                ->where(function($query) use ($searchPattern) {
                    $query->where("CI_FIRST_ARB", "LIKE", $searchPattern)
                          ->orWhere("CI_FATHER_ARB", "LIKE", $searchPattern)
                          ->orWhere("CI_FAMILY_ARB", "LIKE", $searchPattern)
                          ->orWhere("CI_ID_NUM", "LIKE", $searchPattern);
                })
                ->orderBy("ID", "DESC")
                ->limit($limit)
                ->get();
        });
    }

    /**
     * بحث متقدم مع فلاتر
     */
    public function advancedSearch($filters, $limit = 100)
    {
        $cacheKey = "advanced_search_" . md5(serialize($filters) . "_" . $limit);

        return $this->cacheService->getSearchResults($cacheKey, function() use ($filters, $limit) {

            $query = DB::table("persons");

            if (!empty($filters["first_name"])) {
                $query->where("CI_FIRST_ARB", "LIKE", "%" . $filters["first_name"] . "%");
            }

            if (!empty($filters["father_name"])) {
                $query->where("CI_FATHER_ARB", "LIKE", "%" . $filters["father_name"] . "%");
            }

            if (!empty($filters["family_name"])) {
                $query->where("CI_FAMILY_ARB", "LIKE", "%" . $filters["family_name"] . "%");
            }

            if (!empty($filters["id_num"])) {
                $query->where("CI_ID_NUM", "=", $filters["id_num"]);
            }

            if (!empty($filters["birth_year"])) {
                $query->whereYear("CI_BIRTH_DT", $filters["birth_year"]);
            }

            if (!empty($filters["gender"])) {
                $query->where("CI_SEX_CD", $filters["gender"]);
            }

            if (!empty($filters["city"])) {
                $query->where("CITY", $filters["city"]);
            }

            return $query->orderBy("ID", "DESC")->limit($limit)->get();
        });
    }
}';

// كتابة الخدمة المحسنة
file_put_contents('app/Services/OptimizedPersonSearchService.php', $optimizedSearchService);
echo "✅ تم إنشاء OptimizedPersonSearchService.php\n\n";

// اختبار الأداء مع الفهارس الجديدة
echo "=== اختبار الأداء مع الفهارس الجديدة ===\n";

$performanceTests = [
    'FULLTEXT البحث بالاسم الأول' => "SELECT COUNT(*) FROM persons WHERE MATCH(CI_FIRST_ARB) AGAINST('احمد' IN BOOLEAN MODE)",
    'FULLTEXT البحث بالأب' => "SELECT COUNT(*) FROM persons WHERE MATCH(CI_FATHER_ARB) AGAINST('محمد' IN BOOLEAN MODE)",
    'FULLTEXT البحث بالعائلة' => "SELECT COUNT(*) FROM persons WHERE MATCH(CI_FAMILY_ARB) AGAINST('العلي' IN BOOLEAN MODE)",
    'البحث العادي بالاسم الأول' => "SELECT COUNT(*) FROM persons WHERE CI_FIRST_ARB LIKE 'احمد%'",
    'البحث برقم الهوية' => "SELECT COUNT(*) FROM persons WHERE CI_ID_NUM = '123456789'"
];

foreach ($performanceTests as $testName => $query) {
    $start = microtime(true);
    try {
        $result = DB::select($query);
        $time = (microtime(true) - $start) * 1000;
        echo "$testName: " . round($time, 2) . " مللي ثانية (" . number_format($result[0]->count ?? 0) . " نتيجة)\n";
    } catch (Exception $e) {
        echo "$testName: فشل - " . $e->getMessage() . "\n";
    }
}

// عرض جميع الفهارس النهائية
echo "\n=== الفهارس النهائية ===\n";
$allIndexes = DB::select("SHOW INDEX FROM persons WHERE Index_type IN ('FULLTEXT', 'BTREE') AND Key_name NOT LIKE '%foreign%'");

$indexGroups = [];
foreach ($allIndexes as $index) {
    $indexGroups[$index->Index_type][] = $index;
}

foreach ($indexGroups as $type => $indexes) {
    echo "\n$type الفهارس:\n";
    $uniqueIndexes = [];
    foreach ($indexes as $index) {
        $key = $index->Key_name;
        if (!isset($uniqueIndexes[$key])) {
            $uniqueIndexes[$key] = [];
        }
        $uniqueIndexes[$key][] = $index->Column_name;
    }

    foreach ($uniqueIndexes as $indexName => $columns) {
        echo "✅ $indexName على: " . implode(', ', $columns) . "\n";
    }
}

echo "\n=== إرشادات الاستخدام ===\n";
echo "1. استخدم OptimizedPersonSearchService للبحث السريع\n";
echo "2. فهارس FULLTEXT منفصلة توفر بحث سريع جداً\n";
echo "3. استخدم fulltextSearch() للبحث النصي المتقدم\n";
echo "4. استخدم quickSearch() للبحث السريع العادي\n";
echo "5. استخدم advancedSearch() للبحث بالفلاتر\n";

echo "\n=== انتهت العملية بنجاح ===\n";
echo "الوقت: " . date('Y-m-d H:i:s') . "\n";
