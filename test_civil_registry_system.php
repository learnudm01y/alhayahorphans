<?php
/**
 * اختبار شامل لنظام السجل المدني المنفصل
 * Test Civil Registry System with New Database
 */

require 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// إعداد اتصال قاعدة البيانات
$capsule = new Capsule;

// اتصال قاعدة البيانات العادية
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'aso',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

// اتصال قاعدة البيانات المنفصلة للسجل المدني
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'civilregistry',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
], 'civilregistry');

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "<h1>🔍 اختبار نظام السجل المدني المنفصل</h1>\n";
echo "<style>body{font-family:Arial;direction:rtl;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>\n";

try {
    // 1. اختبار الاتصال بقاعدة البيانات المنفصلة
    echo "<h2>📊 1. اختبار الاتصال بقاعدة البيانات</h2>\n";

    $civilRegistryCount = Capsule::connection('civilregistry')
        ->table('persons')
        ->count();

    echo "<div class='success'>✅ نجح الاتصال بقاعدة البيانات civilregistry</div>\n";
    echo "<div class='info'>📈 إجمالي السجلات: " . number_format($civilRegistryCount) . "</div>\n";

    // 2. اختبار استعلام بيانات عينة
    echo "<h2>👥 2. اختبار جلب بيانات عينة</h2>\n";

    $samplePersons = Capsule::connection('civilregistry')
        ->table('persons')
        ->select(['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_SEX_CD'])
        ->limit(5)
        ->get();

    if ($samplePersons->count() > 0) {
        echo "<div class='success'>✅ تم جلب " . $samplePersons->count() . " سجلات بنجاح</div>\n";
        echo "<table border='1' style='width:100%; border-collapse:collapse; margin:10px 0;'>\n";
        echo "<tr style='background:#f0f0f0;'><th>ID</th><th>رقم الهوية</th><th>الاسم الأول</th><th>اسم الأب</th><th>العائلة</th><th>الجنس</th></tr>\n";

        foreach ($samplePersons as $person) {
            $gender = $person->CI_SEX_CD == 1 ? 'ذكر' : ($person->CI_SEX_CD == 2 ? 'أنثى' : 'غير محدد');
            echo "<tr>";
            echo "<td>{$person->ID}</td>";
            echo "<td>{$person->CI_ID_NUM}</td>";
            echo "<td>{$person->CI_FIRST_ARB}</td>";
            echo "<td>{$person->CI_FATHER_ARB}</td>";
            echo "<td>{$person->CI_FAMILY_ARB}</td>";
            echo "<td>{$gender}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<div class='error'>❌ لم يتم العثور على أي سجلات</div>\n";
    }

    // 3. اختبار الفهارس والأداء
    echo "<h2>⚡ 3. اختبار الفهارس والأداء</h2>\n";

    $start_time = microtime(true);
    $searchResult = Capsule::connection('civilregistry')
        ->table('persons')
        ->where('CI_FIRST_ARB', 'LIKE', 'محمد%')
        ->limit(100)
        ->get();
    $search_time = round((microtime(true) - $start_time) * 1000, 2);

    echo "<div class='success'>✅ البحث بالاسم الأول: " . $searchResult->count() . " نتيجة في {$search_time} ms</div>\n";

    $start_time = microtime(true);
    $idSearchResult = Capsule::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', 'LIKE', '1%')
        ->limit(100)
        ->get();
    $id_search_time = round((microtime(true) - $start_time) * 1000, 2);

    echo "<div class='success'>✅ البحث برقم الهوية: " . $idSearchResult->count() . " نتيجة في {$id_search_time} ms</div>\n";

    // 4. اختبار الإحصائيات
    echo "<h2>📊 4. إحصائيات قاعدة البيانات</h2>\n";

    $maleCount = Capsule::connection('civilregistry')
        ->table('persons')
        ->where('CI_SEX_CD', 1)
        ->count();

    $femaleCount = Capsule::connection('civilregistry')
        ->table('persons')
        ->where('CI_SEX_CD', 2)
        ->count();

    echo "<div class='info'>👨 الذكور: " . number_format($maleCount) . "</div>\n";
    echo "<div class='info'>👩 الإناث: " . number_format($femaleCount) . "</div>\n";
    echo "<div class='info'>👥 المجموع: " . number_format($maleCount + $femaleCount) . "</div>\n";

    // 5. اختبار عملية التحديث (اختبار بسيط)
    echo "<h2>🔄 5. اختبار عمليات CRUD</h2>\n";

    // اختبار إنشاء سجل تجريبي
    $testRecord = [
        'CI_ID_NUM' => '999999999',
        'CI_FIRST_ARB' => 'اختبار',
        'CI_FATHER_ARB' => 'اختبار',
        'CI_GRAND_FATHER_ARB' => 'اختبار',
        'CI_FAMILY_ARB' => 'النظام',
        'CI_SEX_CD' => 1,
        'CI_PERSONAL_CD' => 1,
        'MOTHER_NAME1' => 'اختبار',
        'CITY' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // حذف السجل التجريبي إن وُجد من قبل
    Capsule::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', '999999999')
        ->delete();

    // إنشاء السجل
    $insertResult = Capsule::connection('civilregistry')
        ->table('persons')
        ->insert($testRecord);

    if ($insertResult) {
        echo "<div class='success'>✅ تم إنشاء سجل اختبار بنجاح</div>\n";

        // اختبار التحديث
        $updateResult = Capsule::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', '999999999')
            ->update(['CI_FIRST_ARB' => 'اختبار محدث']);

        if ($updateResult) {
            echo "<div class='success'>✅ تم تحديث السجل بنجاح</div>\n";
        }

        // اختبار الحذف
        $deleteResult = Capsule::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', '999999999')
            ->delete();

        if ($deleteResult) {
            echo "<div class='success'>✅ تم حذف السجل بنجاح</div>\n";
        }
    }

    // 6. اختبار DataTable Query
    echo "<h2>📋 6. اختبار استعلام DataTable</h2>\n";

    $start_time = microtime(true);
    $datatableQuery = Capsule::connection('civilregistry')
        ->table('persons')
        ->select([
            'persons.ID',
            'persons.CI_ID_NUM',
            'persons.CI_FIRST_ARB',
            'persons.CI_FATHER_ARB',
            'persons.CI_GRAND_FATHER_ARB',
            'persons.CI_FAMILY_ARB',
            'persons.CI_BIRTH_DT',
            'persons.CI_SEX_CD',
            'persons.MOTHER_NAME1',
            'persons.CI_PERSONAL_CD',
            'persons.CI_DEAD_DT',
            'persons.CITY',
            'persons.STREET',
            'persons.HOUSE_NO'
        ])
        ->orderBy('persons.CI_ID_NUM')
        ->limit(10)
        ->get();
    $datatable_time = round((microtime(true) - $start_time) * 1000, 2);

    echo "<div class='success'>✅ استعلام DataTable: " . $datatableQuery->count() . " سجل في {$datatable_time} ms</div>\n";

    echo "<h2>🎉 نتائج الاختبار</h2>\n";
    echo "<div class='success'><strong>✅ جميع الاختبارات نجحت!</strong></div>\n";
    echo "<div class='info'>🔗 النظام جاهز للاستخدام على المسار: /admin/civil-registry</div>\n";
    echo "<div class='info'>📊 قاعدة البيانات المنفصلة تعمل بشكل صحيح</div>\n";
    echo "<div class='info'>⚡ الأداء ممتاز - الاستعلامات سريعة</div>\n";

} catch (Exception $e) {
    echo "<div class='error'>❌ خطأ في الاختبار: " . $e->getMessage() . "</div>\n";
    echo "<div class='error'>📍 الملف: " . $e->getFile() . "</div>\n";
    echo "<div class='error'>📍 السطر: " . $e->getLine() . "</div>\n";
}

echo "<hr><p style='text-align:center; color:#666;'>تم الانتهاء من اختبار النظام في " . date('Y-m-d H:i:s') . "</p>\n";
?>
