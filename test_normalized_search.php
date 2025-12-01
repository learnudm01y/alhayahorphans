<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NormalizedSearchService;

$searchService = new NormalizedSearchService();

echo "\n=== اختبار NormalizedSearchService مع الأسماء المحددة ===\n\n";

// Test 1: البحث برقم هوية نصرالله
echo "1️⃣ البحث برقم هوية نصرالله (407015692):\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('407015692');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ms\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    $first = $results[0];
    echo "   ✅ النتيجة: {$first->CI_FIRST_ARB} {$first->CI_FATHER_ARB} {$first->CI_GRAND_FATHER_ARB} {$first->CI_FAMILY_ARB}\n";
    echo "   رقم الهوية: {$first->id_number}\n";
}
echo "\n";

// Test 2: البحث باسم نصرالله الكامل
echo "2️⃣ البحث بالاسم الكامل: نصرالله عبد الناصر رفيق الفرا\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('نصرالله عبد الناصر رفيق الفرا');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ms\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    echo "   ✅ أول 3 نتائج:\n";
    foreach (array_slice($results->toArray(), 0, 3) as $i => $person) {
        echo "      " . ($i+1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "         رقم الهوية: {$person->id_number}\n";
    }
}
echo "\n";

// Test 3: البحث باسم محمد الكامل
echo "3️⃣ البحث بالاسم الكامل: محمد عبد الناصر رفيق الفرا\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('محمد عبد الناصر رفيق الفرا');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ms\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    echo "   ✅ أول 3 نتائج:\n";
    foreach (array_slice($results->toArray(), 0, 3) as $i => $person) {
        echo "      " . ($i+1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "         رقم الهوية: {$person->id_number}\n";
    }
}
echo "\n";

// Test 4: البحث بكلمتين فقط
echo "4️⃣ البحث بكلمتين: نصرالله الفرا\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('نصرالله الفرا');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ms\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    echo "   ✅ أول 3 نتائج:\n";
    foreach (array_slice($results->toArray(), 0, 3) as $i => $person) {
        echo "      " . ($i+1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "         رقم الهوية: {$person->id_number}\n";
    }
}
echo "\n";

// Test 5: البحث بكلمة واحدة
echo "5️⃣ البحث بكلمة واحدة: نصرالله\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('نصرالله');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ms\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    echo "   ✅ أول 3 نتائج:\n";
    foreach (array_slice($results->toArray(), 0, 3) as $i => $person) {
        echo "      " . ($i+1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "         رقم الهوية: {$person->id_number}\n";
    }
}
echo "\n";

echo "=== ملخص الأداء ===\n";
echo "✅ يجب أن تكون جميع الأوقات أقل من 1000ms (1 ثانية)\n";
echo "✅ يجب أن تكون النتائج دقيقة ومرتبة حسب الأهمية\n";
