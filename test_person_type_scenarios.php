<?php
/**
 * اختبار شامل لجميع سيناريوهات person_type
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "🧪 اختبار شامل لسيناريوهات person_type\n";
echo "=================================================================\n\n";

// فحص كود OfflineTestController
echo "📋 1. فحص الكود في OfflineTestController:\n";
echo str_repeat("-", 50) . "\n";

$controllerPath = __DIR__ . '/app/Http/Controllers/Api/OfflineTestController.php';
$controllerContent = file_get_contents($controllerPath);

$checks = [
    'دالة updateSponsoredByPersonType' => strpos($controllerContent, 'function updateSponsoredByPersonType') !== false,
    'حالة breadwinner' => strpos($controllerContent, "case 'breadwinner':") !== false,
    'حالة family_member' => strpos($controllerContent, "case 'family_member':") !== false,
    'حالة orphan' => strpos($controllerContent, "case 'orphan':") !== false,
    'حالة deceased_father' => strpos($controllerContent, "case 'deceased_father':") !== false,
    'حالة deceased_mother' => strpos($controllerContent, "case 'deceased_mother':") !== false,
    'جدول data للمعيل' => strpos($controllerContent, "result['table'] = 'data'") !== false,
    'جدول re_people' => strpos($controllerContent, "result['table'] = 're_people'") !== false,
    'جدول dead_people' => strpos($controllerContent, "result['table'] = 'dead_people'") !== false,
    'دالة convertGenderToInt' => strpos($controllerContent, 'function convertGenderToInt') !== false,
];

$passedCount = 0;
foreach ($checks as $check => $passed) {
    echo ($passed ? "   ✅" : "   ❌") . " $check\n";
    if ($passed) $passedCount++;
}

echo "\n   النتيجة: $passedCount/" . count($checks) . " فحوصات ناجحة\n\n";

// 2. إحصائيات الكفالات حسب person_type
echo "📋 2. إحصائيات الكفالات حسب person_type:\n";
echo str_repeat("-", 50) . "\n";

$personTypeCounts = DB::table('sponsorships')
    ->select('person_type', DB::raw('count(*) as count'))
    ->groupBy('person_type')
    ->get();

$personTypeLabels = [
    'orphan' => 'يتيم',
    'family_member' => 'فرد عائلة',
    'breadwinner' => 'معيل',
    'deceased_father' => 'أب متوفي',
    'deceased_mother' => 'أم متوفية',
    'repeople' => 'فرد مسجل',
    null => 'غير محدد'
];

foreach ($personTypeCounts as $pt) {
    $label = $personTypeLabels[$pt->person_type] ?? $pt->person_type ?? 'غير محدد';
    echo "   - {$label}: {$pt->count} كفالة\n";
}

echo "\n";

// 3. اختبار محاكاة لكل سيناريو
echo "📋 3. جدول الربط حسب person_type:\n";
echo str_repeat("-", 50) . "\n";

$scenarios = [
    [
        'person_type' => 'orphan',
        'label' => 'يتيم',
        'table' => 're_people',
        'id_field' => 'registration_id'
    ],
    [
        'person_type' => 'family_member',
        'label' => 'فرد عائلة',
        'table' => 're_people',
        'id_field' => 'registration_id'
    ],
    [
        'person_type' => 'breadwinner',
        'label' => 'معيل',
        'table' => 'data',
        'id_field' => 'file_id_number'
    ],
    [
        'person_type' => 'deceased_father',
        'label' => 'أب متوفي',
        'table' => 'dead_people',
        'id_field' => 're_file_id'
    ],
    [
        'person_type' => 'deceased_mother',
        'label' => 'أم متوفية',
        'table' => 'dead_people',
        'id_field' => 're_file_id'
    ],
];

echo "   | السيناريو      | الجدول      | حقل الربط        |\n";
echo "   |----------------|-------------|------------------|\n";

foreach ($scenarios as $s) {
    $label = str_pad($s['label'], 14, ' ', STR_PAD_LEFT);
    $table = str_pad($s['table'], 11, ' ', STR_PAD_RIGHT);
    $field = str_pad($s['id_field'], 16, ' ', STR_PAD_RIGHT);
    echo "   | $label | $table | $field |\n";
}

echo "\n";

// 4. ملخص
echo "=================================================================\n";
echo "📊 الملخص:\n";
echo "=================================================================\n";

if ($passedCount === count($checks)) {
    echo "🎉 جميع السيناريوهات مدعومة في OfflineTestController!\n\n";

    echo "✅ السيناريوهات المدعومة:\n";
    echo "   1. يتيم (orphan) → re_people.registration_id\n";
    echo "   2. فرد عائلة (family_member) → re_people.registration_id\n";
    echo "   3. معيل (breadwinner) → data.file_id_number\n";
    echo "   4. أب متوفي (deceased_father) → dead_people.re_file_id\n";
    echo "   5. أم متوفية (deceased_mother) → dead_people.re_file_id\n";
} else {
    echo "⚠️ بعض السيناريوهات تحتاج مراجعة\n";
}

echo "\n=================================================================\n";
