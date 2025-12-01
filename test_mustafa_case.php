<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار حالة: مصطفى نبيل مصطفى ابوعيد                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$searchName = "مصطفى نبيل مصطفى ابوعيد";
$searchId = "400009692";

// 1. البحث برقم الهوية في السجل المدني
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  البحث برقم الهوية في السجل المدني: $searchId\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$person = DB::connection('civilregistry')->table('persons')
    ->where('CI_ID_NUM', $searchId)
    ->first();

if ($person) {
    echo "✅ تم العثور على الشخص:\n";
    echo "  🆔 رقم الهوية: {$person->CI_ID_NUM}\n";
    echo "  👤 الاسم: {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
    echo "  📅 تاريخ الميلاد: {$person->CI_BIRTH_DT}\n";
    echo "  ⚥ الجنس: {$person->CI_SEX_CD}\n\n";

    $fullName = trim("{$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}");
    echo "  📝 الاسم الكامل: $fullName\n\n";
} else {
    echo "❌ لم يتم العثور على الشخص في السجل المدني!\n\n";
}

// 2. اختبار البحث بالاسم في Admin Dashboard
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  Admin Dashboard Search: $searchName\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
$start = microtime(true);
$results = $normalizedSearchService->searchCivilRegistry($searchName, 5);
$time = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time} ms\n";
echo "📊 عدد النتائج: {$results->count()}\n\n";

if ($results->count() > 0) {
    foreach ($results as $idx => $result) {
        echo "نتيجة #" . ($idx + 1) . ":\n";
        echo "  🆔 رقم الهوية: {$result->id_number}\n";
        echo "  👤 الاسم: {$result->CI_FIRST_ARB} {$result->CI_FATHER_ARB} {$result->CI_GRAND_FATHER_ARB} {$result->CI_FAMILY_ARB}\n\n";
    }
} else {
    echo "❌ لم يتم العثور على نتائج!\n\n";
}

// 3. اختبار البحث الخارجي
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  External Search: $searchName\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$controller = new \App\Http\Controllers\Users\GeneralRegistrationController();
$request = new \Illuminate\Http\Request(['search_term' => $searchName]);
$start = microtime(true);
$response = $controller->searchAllTables($request);
$result = $response->getData(true);
$time = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$time} ms\n";

if (isset($result['found']) && $result['found']) {
    echo "✅ تم العثور على نتيجة\n";
    echo "📍 المصدر: {$result['source']}\n";
    if (isset($result['data']['id_number'])) {
        echo "🆔 رقم الهوية: {$result['data']['id_number']}\n";
        echo "👤 الاسم: {$result['data']['full_name']}\n";
    }
} else {
    echo "❌ لم يتم العثور على نتيجة\n";
}
echo "\n";

// 4. التحليل: تقسيم الكلمات
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  تحليل تقسيم الكلمات\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$normalized = normalizeArabicText($searchName);
echo "النص المطبّع: $normalized\n";

$words = preg_split('/\s+/', trim($normalized));
echo "الكلمات الأولية: " . implode(' | ', $words) . "\n";
echo "عدد الكلمات: " . count($words) . "\n\n";

// اختبار mergeCompoundNames
$reflection = new ReflectionClass($normalizedSearchService);
$method = $reflection->getMethod('mergeCompoundNames');
$method->setAccessible(true);
$mergedWords = $method->invoke($normalizedSearchService, $words);

echo "الكلمات بعد الدمج: " . implode(' | ', $mergedWords) . "\n";
echo "عدد الكلمات بعد الدمج: " . count($mergedWords) . "\n\n";

// 5. البحث اليدوي المباشر
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5️⃣  بحث يدوي مباشر\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if (count($mergedWords) >= 4) {
    $query = DB::connection('civilregistry')->table('persons')
        ->where('CI_FIRST_ARB', 'LIKE', $mergedWords[0] . '%')
        ->where('CI_FATHER_ARB', 'LIKE', $mergedWords[1] . '%')
        ->where('CI_GRAND_FATHER_ARB', 'LIKE', $mergedWords[2] . '%')
        ->where('CI_FAMILY_ARB', 'LIKE', $mergedWords[3] . '%');

    echo "الاستعلام:\n";
    echo "  CI_FIRST_ARB LIKE '{$mergedWords[0]}%'\n";
    echo "  CI_FATHER_ARB LIKE '{$mergedWords[1]}%'\n";
    echo "  CI_GRAND_FATHER_ARB LIKE '{$mergedWords[2]}%'\n";
    echo "  CI_FAMILY_ARB LIKE '{$mergedWords[3]}%'\n\n";

    $start = microtime(true);
    $manualResults = $query->limit(5)->get();
    $time = round((microtime(true) - $start) * 1000, 2);

    echo "⏱️  الوقت: {$time} ms\n";
    echo "📊 عدد النتائج: {$manualResults->count()}\n\n";

    if ($manualResults->count() > 0) {
        foreach ($manualResults as $idx => $result) {
            echo "نتيجة #" . ($idx + 1) . ":\n";
            echo "  🆔 رقم الهوية: {$result->CI_ID_NUM}\n";
            echo "  👤 الاسم: {$result->CI_FIRST_ARB} {$result->CI_FATHER_ARB} {$result->CI_GRAND_FATHER_ARB} {$result->CI_FAMILY_ARB}\n\n";
        }
    }
}

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                      التشخيص                                 ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";

if ($person) {
    echo "✅ الشخص موجود في قاعدة البيانات\n";
    echo "⚠️  إذا لم يظهر في البحث، المشكلة في منطق البحث\n";
} else {
    echo "❌ الشخص غير موجود في قاعدة البيانات\n";
}
