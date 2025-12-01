<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NormalizedSearchService;

$searchService = new NormalizedSearchService();

echo "\n=== اختبار سرعة البحث بعد إضافة الفهارس ===\n\n";

// Test 1: ID Number Search
echo "1️⃣ اختبار البحث برقم الهوية (428818907):\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('428818907');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ميلي ثانية\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    $first = $results[0];
    echo "   ✅ أول نتيجة: {$first->CI_FIRST_ARB} {$first->CI_FATHER_ARB} {$first->CI_GRAND_FATHER_ARB} {$first->CI_FAMILY_ARB}\n";
}
echo "\n";

// Test 2: Full Name Search (الحالة التي كانت بطيئة جداً)
echo "2️⃣ اختبار البحث بالاسم الكامل (محمد عبد الناصر رفيق الفرا):\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('محمد عبد الناصر رفيق الفرا');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ميلي ثانية\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    echo "   ✅ أول 3 نتائج:\n";
    foreach (array_slice($results, 0, 3) as $i => $person) {
        echo "      " . ($i+1) . ". {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "         رقم الهوية: {$person->CI_ID_NUM}\n";
    }
}
echo "\n";

// Test 3: Single Word Search
echo "3️⃣ اختبار البحث بكلمة واحدة (محمد):\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('محمد');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ميلي ثانية\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    echo "   ✅ أول نتيجة: {$results[0]->CI_FIRST_ARB} {$results[0]->CI_FATHER_ARB}\n";
}
echo "\n";

// Test 4: Two Words Search
echo "4️⃣ اختبار البحث بكلمتين (محمد الفرا):\n";
$start = microtime(true);
$results = $searchService->searchCivilRegistry('محمد الفرا');
$duration = round((microtime(true) - $start) * 1000, 2);
echo "   ⏱️ الوقت: {$duration} ميلي ثانية\n";
echo "   📊 عدد النتائج: " . count($results) . "\n";
if (!empty($results)) {
    echo "   ✅ أول نتيجة: {$results[0]->CI_FIRST_ARB} {$results[0]->CI_FATHER_ARB} {$results[0]->CI_GRAND_FATHER_ARB} {$results[0]->CI_FAMILY_ARB}\n";
}
echo "\n";

echo "=== الاختبارات اكتملت ===\n";
echo "💡 ملاحظة: السرعة المتوقعة يجب أن تكون أقل من 1000 ميلي ثانية (1 ثانية)\n";
