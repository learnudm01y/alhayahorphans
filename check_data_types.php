<?php
/**
 * فحص وجود الأرقام في جدول persons
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=================================================================\n";
echo "        فحص وجود الأرقام في جدول persons                        \n";
echo "=================================================================\n\n";

$testId = '407015692';

// 1. جلب العلاقات من جدول relations
echo "1️⃣ جلب العلاقات من جدول relations\n";
echo "-----------------------------------------------------------------\n";
$relations = DB::connection('civilregistry')
    ->table('relations')
    ->where('CF_ID_NUM', $testId)
    ->get();

echo "   📊 عدد العلاقات: " . count($relations) . "\n\n";

foreach ($relations as $rel) {
    echo "   • CF_ID_RELATIVE: {$rel->CF_ID_RELATIVE}, CF_RELATIVE_CD: {$rel->CF_RELATIVE_CD}\n";
}

echo "\n";

// 2. فحص وجود كل رقم في جدول persons
echo "2️⃣ فحص وجود الأرقام في جدول persons\n";
echo "-----------------------------------------------------------------\n";

foreach ($relations as $rel) {
    $exists = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', $rel->CF_ID_RELATIVE)
        ->exists();

    if ($exists) {
        $person = DB::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', $rel->CF_ID_RELATIVE)
            ->first();

        $fullName = trim(implode(' ', [
            $person->CI_FIRST_ARB ?? '',
            $person->CI_FATHER_ARB ?? '',
            $person->CI_GRAND_FATHER_ARB ?? '',
            $person->CI_FAMILY_ARB ?? ''
        ]));

        echo "   ✅ {$rel->CF_ID_RELATIVE}: موجود - {$fullName}\n";
    } else {
        echo "   ❌ {$rel->CF_ID_RELATIVE}: غير موجود!\n";
    }
}

echo "\n";

// 3. فحص نوع البيانات
echo "3️⃣ فحص نوع البيانات (Data Type)\n";
echo "-----------------------------------------------------------------\n";

$relationType = DB::connection('civilregistry')
    ->select("SHOW COLUMNS FROM relations LIKE 'CF_ID_RELATIVE'");

$personType = DB::connection('civilregistry')
    ->select("SHOW COLUMNS FROM persons LIKE 'CI_ID_NUM'");

echo "   📋 relations.CF_ID_RELATIVE: {$relationType[0]->Type}\n";
echo "   📋 persons.CI_ID_NUM: {$personType[0]->Type}\n";

if ($relationType[0]->Type !== $personType[0]->Type) {
    echo "   ⚠️  أنواع البيانات مختلفة!\n";
    echo "   💡 الحل: استخدام CAST في JOIN\n";
}

echo "\n";

// 4. اختبار JOIN مع CAST
echo "4️⃣ اختبار JOIN مع CAST\n";
echo "-----------------------------------------------------------------\n";

try {
    $result = DB::connection('civilregistry')
        ->table('relations as r')
        ->select(
            'r.CF_ID_NUM',
            'r.CF_ID_RELATIVE',
            'r.CF_RELATIVE_CD',
            'p.CI_FIRST_ARB',
            'p.CI_FATHER_ARB',
            'p.CI_GRAND_FATHER_ARB',
            'p.CI_FAMILY_ARB'
        )
        ->join('persons as p', DB::raw('CAST(r.CF_ID_RELATIVE AS CHAR)'), '=', DB::raw('CAST(p.CI_ID_NUM AS CHAR)'))
        ->where('r.CF_ID_NUM', $testId)
        ->get();

    echo "   ✅ JOIN مع CAST نجح\n";
    echo "   📊 عدد النتائج: " . count($result) . "\n\n";

    if (count($result) > 0) {
        foreach ($result as $row) {
            $fullName = trim(implode(' ', [
                $row->CI_FIRST_ARB ?? '',
                $row->CI_FATHER_ARB ?? '',
                $row->CI_GRAND_FATHER_ARB ?? '',
                $row->CI_FAMILY_ARB ?? ''
            ]));

            echo "      ✅ {$row->CF_ID_RELATIVE}: {$fullName}\n";
        }
    }

} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n=================================================================\n";
echo "✅ انتهى الفحص\n";
echo "=================================================================\n\n";
