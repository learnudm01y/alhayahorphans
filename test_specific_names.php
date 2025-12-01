<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== اختبار البحث بالأسماء المحددة ===\n\n";

// البحث المباشر في قاعدة البيانات
echo "1️⃣ التحقق من وجود: نصرالله عبد الناصر رفيق الفرا (407015692)\n";
$result = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_ID_NUM', '407015692')
    ->first();

if ($result) {
    echo "   ✅ تم العثور على الشخص:\n";
    echo "      الاسم: {$result->CI_FIRST_ARB} {$result->CI_FATHER_ARB} {$result->CI_GRAND_FATHER_ARB} {$result->CI_FAMILY_ARB}\n";
    echo "      رقم الهوية: {$result->CI_ID_NUM}\n";
    echo "      تاريخ الميلاد: {$result->CI_BIRTH_DT}\n";
} else {
    echo "   ❌ لم يتم العثور على الشخص برقم الهوية 407015692\n";
}

echo "\n2️⃣ البحث عن: محمد عبد الناصر رفيق الفرا\n";
$results = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_FIRST_ARB', 'LIKE', 'محمد%')
    ->where('CI_FATHER_ARB', 'LIKE', 'عبد الناصر%')
    ->where('CI_GRAND_FATHER_ARB', 'LIKE', 'رفيق%')
    ->where('CI_FAMILY_ARB', 'LIKE', 'الفرا%')
    ->limit(5)
    ->get();

echo "   📊 عدد النتائج: " . count($results) . "\n";
if (count($results) > 0) {
    echo "   ✅ النتائج المطابقة:\n";
    foreach ($results as $i => $person) {
        echo "      " . ($i+1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "         رقم الهوية: {$person->CI_ID_NUM}\n";
    }
} else {
    echo "   ⚠️ لم يتم العثور على نتائج\n";
}

echo "\n3️⃣ البحث عن: نصرالله عبد الناصر رفيق الفرا\n";
$results = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_FIRST_ARB', 'LIKE', 'نصرالله%')
    ->where('CI_FATHER_ARB', 'LIKE', 'عبد الناصر%')
    ->where('CI_GRAND_FATHER_ARB', 'LIKE', 'رفيق%')
    ->where('CI_FAMILY_ARB', 'LIKE', 'الفرا%')
    ->limit(5)
    ->get();

echo "   📊 عدد النتائج: " . count($results) . "\n";
if (count($results) > 0) {
    echo "   ✅ النتائج المطابقة:\n";
    foreach ($results as $i => $person) {
        echo "      " . ($i+1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "         رقم الهوية: {$person->CI_ID_NUM}\n";
    }
} else {
    echo "   ⚠️ لم يتم العثور على نتائج\n";
}

echo "\n4️⃣ اختبار EXPLAIN للبحث برقم الهوية:\n";
$explain = DB::connection('civilregistry')
    ->select("EXPLAIN SELECT * FROM persons WHERE CI_ID_NUM = '407015692'");
echo "   Key used: " . ($explain[0]->key ?? 'NULL') . "\n";
echo "   Rows examined: " . ($explain[0]->rows ?? 'N/A') . "\n";
echo "   Type: " . ($explain[0]->type ?? 'N/A') . "\n";

echo "\n5️⃣ اختبار EXPLAIN للبحث بالاسم:\n";
$explain = DB::connection('civilregistry')
    ->select("EXPLAIN SELECT * FROM persons WHERE CI_FIRST_ARB LIKE 'نصرالله%' AND CI_FATHER_ARB LIKE 'عبد الناصر%'");
echo "   Key used: " . ($explain[0]->key ?? 'NULL') . "\n";
echo "   Rows examined: " . ($explain[0]->rows ?? 'N/A') . "\n";
echo "   Type: " . ($explain[0]->type ?? 'N/A') . "\n";

echo "\n=== الاختبار اكتمل ===\n";
