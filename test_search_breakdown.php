<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار تفصيلي: أين يستغرق الوقت؟                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$searchTerm = "محمد عبدالناصر رفيق الفرا";
echo "🔍 البحث عن: $searchTerm\n\n";

$controller = new \App\Http\Controllers\Users\GeneralRegistrationController();

// تفعيل DB Query Log
DB::enableQueryLog();

// 1. البحث في data
$start = microtime(true);
$reflectionMethod = new ReflectionMethod($controller, 'searchInDataTableOptimized');
$reflectionMethod->setAccessible(true);
$dataResult = $reflectionMethod->invoke($controller, $searchTerm);
$dataTime = round((microtime(true) - $start) * 1000, 2);

echo "📊 data: {$dataTime} ms";
echo $dataResult ? " ✅ (وُجد)\n" : " ❌ (لم يُوجد)\n";
echo "   عدد الاستعلامات: " . count(DB::getQueryLog()) . "\n\n";

DB::flushQueryLog();

// 2. البحث في re_people
$start = microtime(true);
$reflectionMethod = new ReflectionMethod($controller, 'searchInRePeopleOptimized');
$reflectionMethod->setAccessible(true);
$rePeopleResult = $reflectionMethod->invoke($controller, $searchTerm);
$rePeopleTime = round((microtime(true) - $start) * 1000, 2);

echo "📊 re_people: {$rePeopleTime} ms";
echo $rePeopleResult ? " ✅ (وُجد)\n" : " ❌ (لم يُوجد)\n";
echo "   عدد الاستعلامات: " . count(DB::getQueryLog()) . "\n\n";

DB::flushQueryLog();

// 3. البحث في dead_people
$start = microtime(true);
$reflectionMethod = new ReflectionMethod($controller, 'searchInDeadPeopleOptimized');
$reflectionMethod->setAccessible(true);
$deadPeopleResult = $reflectionMethod->invoke($controller, $searchTerm);
$deadPeopleTime = round((microtime(true) - $start) * 1000, 2);

echo "📊 dead_people: {$deadPeopleTime} ms";
echo $deadPeopleResult ? " ✅ (وُجد)\n" : " ❌ (لم يُوجد)\n";
echo "   عدد الاستعلامات: " . count(DB::getQueryLog()) . "\n\n";

DB::flushQueryLog();

// 4. البحث في civil_registry
$start = microtime(true);
$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
$civilRegistryResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);
$civilRegistryTime = round((microtime(true) - $start) * 1000, 2);

echo "📊 civil_registry: {$civilRegistryTime} ms";
echo ($civilRegistryResults && $civilRegistryResults->isNotEmpty()) ? " ✅ (وُجد)\n" : " ❌ (لم يُوجد)\n";
echo "   عدد الاستعلامات: " . count(DB::getQueryLog()) . "\n";

if ($civilRegistryResults && $civilRegistryResults->isNotEmpty()) {
    $result = $civilRegistryResults->first();
    echo "   رقم الهوية: {$result->id_number}\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  الملخص                                                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
$totalTime = $dataTime + $rePeopleTime + $deadPeopleTime + $civilRegistryTime;
echo "⏱️  الوقت الإجمالي: {$totalTime} ms\n";
echo "📍 الأطول: ";
$times = ['data' => $dataTime, 're_people' => $rePeopleTime, 'dead_people' => $deadPeopleTime, 'civil_registry' => $civilRegistryTime];
arsort($times);
echo key($times) . " ({$times[key($times)]} ms)\n";
