<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$searchTerm = "محمد عبد الناصر رفيق الفرا";

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  مقارنة البحث في Admin Dashboard vs البحث الخارجي           ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
echo "🔍 البحث عن: $searchTerm\n\n";

// ============================================
// 1. Admin Dashboard Search (المحسّن)
// ============================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  Admin Dashboard Search (NormalizedSearchService)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$start = microtime(true);
$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);
$adminResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 5);
$adminTime = round((microtime(true) - $start) * 1000, 2);

echo "⏱️  الوقت: {$adminTime} ms\n";
echo "📊 عدد النتائج: {$adminResults->count()}\n\n";

if ($adminResults->count() > 0) {
    foreach ($adminResults as $idx => $person) {
        echo "نتيجة #" . ($idx + 1) . ":\n";
        echo "  🆔 رقم الهوية: {$person->id_number}\n";
        echo "  👤 الاسم: {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
        echo "  📅 تاريخ الميلاد: {$person->CI_BIRTH_DT}\n";
        echo "  ⚥ الجنس: {$person->CI_SEX_CD}\n\n";
    }
}

// ============================================
// 2. External Search (searchAllTables)
// ============================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  External Search (GeneralRegistrationController)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$start = microtime(true);

// محاكاة searchAllTables
// 1. البحث في data
$dataResult = \App\Models\Data::where(function($q) use ($searchTerm) {
    $normalized = normalizeArabicText($searchTerm);
    $q->where('data_first_name', 'LIKE', $normalized . '%')
      ->orWhere('data_father_name', 'LIKE', $normalized . '%');
})->first();

if ($dataResult) {
    $externalTime = round((microtime(true) - $start) * 1000, 2);
    echo "⏱️  الوقت: {$externalTime} ms\n";
    echo "📍 المصدر: data table\n";
    echo "  🆔 رقم الهوية: {$dataResult->data_id_number}\n";
    echo "  👤 الاسم: {$dataResult->data_first_name} {$dataResult->data_father_name} {$dataResult->data_grand_father_name} {$dataResult->data_family_name}\n\n";
} else {
    echo "❌ لم يتم العثور في data\n";

    // 2. البحث في re_people
    $reResult = \App\Models\RePeople::where(function($q) use ($searchTerm) {
        $normalized = normalizeArabicText($searchTerm);
        $q->where('first_name', 'LIKE', $normalized . '%')
          ->orWhere('father_name', 'LIKE', $normalized . '%');
    })->first();

    if ($reResult) {
        $externalTime = round((microtime(true) - $start) * 1000, 2);
        echo "⏱️  الوقت: {$externalTime} ms\n";
        echo "📍 المصدر: re_people table\n";
        echo "  🆔 رقم الهوية: {$reResult->person_id}\n";
        echo "  👤 الاسم: {$reResult->first_name} {$reResult->father_name} {$reResult->grand_father_name} {$reResult->family_name}\n\n";
    } else {
        echo "❌ لم يتم العثور في re_people\n";

        // 3. البحث في civil_registry
        $civilResults = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);
        $externalTime = round((microtime(true) - $start) * 1000, 2);

        if ($civilResults->isNotEmpty()) {
            $person = $civilResults->first();
            echo "⏱️  الوقت: {$externalTime} ms\n";
            echo "📍 المصدر: civil_registry\n";
            echo "  🆔 رقم الهوية: {$person->id_number}\n";
            echo "  👤 الاسم: {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";
            echo "  📅 تاريخ الميلاد: {$person->CI_BIRTH_DT}\n\n";
        }
    }
}

// ============================================
// 3. التحقق من وجود السجل 800862443
// ============================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  التحقق من السجل الظاهر في البحث الخارجي (800862443)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$wrongResult = DB::connection('civilregistry')->table('persons')
    ->where('CI_ID_NUM', '800862443')
    ->first();

if ($wrongResult) {
    echo "✅ تم العثور على السجل:\n";
    echo "  🆔 رقم الهوية: {$wrongResult->CI_ID_NUM}\n";
    echo "  👤 الاسم: {$wrongResult->CI_FIRST_ARB} {$wrongResult->CI_FATHER_ARB} {$wrongResult->CI_GRAND_FATHER_ARB} {$wrongResult->CI_FAMILY_ARB}\n\n";

    // التحقق من data
    $dataCheck = \App\Models\Data::where('data_id_number', '800862443')->first();
    if ($dataCheck) {
        echo "  ⚠️  هذا السجل موجود في data table:\n";
        echo "     الاسم في data: {$dataCheck->data_first_name} {$dataCheck->data_father_name} {$dataCheck->data_grand_father_name} {$dataCheck->data_family_name}\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📝 الخلاصة:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "المشكلة: البحث الخارجي يبحث في data أولاً، وإذا وجد تطابق\n";
echo "جزئي في الاسم يُرجع النتيجة فوراً بدون البحث في civil_registry\n";
echo "الحل: يجب تحسين دقة البحث في data لتطابق كلمات متعددة\n";
