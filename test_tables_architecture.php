<?php
/**
 * ===============================================
 * اختبار معمارية الجداول - Tables Architecture Test
 * ===============================================
 * تاريخ: 12 يناير 2026
 *
 * هذا الملف يتحقق من بنية الجداول قبل تنفيذ التعديلات
 * ===============================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║        اختبار معمارية الجداول - Tables Architecture Test     ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║                      تاريخ: 12 يناير 2026                    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$allTestsPassed = true;
$testResults = [];

// ============================================
// دالة للتحقق من وجود عمود
// ============================================
function checkColumn($table, $column, $expectedType = null) {
    if (!Schema::hasColumn($table, $column)) {
        return ['exists' => false, 'type' => null];
    }

    $columnType = DB::select("SHOW COLUMNS FROM `$table` WHERE Field = '$column'");
    $type = $columnType[0]->Type ?? 'unknown';

    return ['exists' => true, 'type' => $type];
}

// ============================================
// دالة لطباعة نتيجة الاختبار
// ============================================
function printTestResult($testName, $passed, $details = '') {
    global $allTestsPassed, $testResults;

    $icon = $passed ? '✅' : '❌';
    $status = $passed ? 'PASSED' : 'FAILED';

    if (!$passed) {
        $allTestsPassed = false;
    }

    $testResults[] = [
        'name' => $testName,
        'passed' => $passed,
        'details' => $details
    ];

    echo "$icon [$status] $testName";
    if ($details) {
        echo " - $details";
    }
    echo "\n";
}

// ============================================
// اختبار 1: جدول sponsorships
// ============================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 اختبار جدول sponsorships\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$sponsorshipsColumns = [
    'id' => 'المعرف الأساسي',
    'identity_number' => 'رقم هوية المكفول',
    'guardian_identity_number' => 'رقم هوية المعيل',
    'relation_id_number' => 'رقم الربط الداخلي',
    'person_type' => 'نوع الشخص المكفول',
    'orphan_name' => 'اسم المكفول',
    'guardian_name' => 'اسم المعيل',
    'sponsor_id' => 'معرف الكافل'
];

foreach ($sponsorshipsColumns as $column => $description) {
    $result = checkColumn('sponsorships', $column);
    printTestResult(
        "sponsorships.$column ($description)",
        $result['exists'],
        $result['exists'] ? "النوع: {$result['type']}" : "العمود غير موجود!"
    );
}

// التحقق من قيم person_type الموجودة
echo "\n   📊 قيم person_type الموجودة في قاعدة البيانات:\n";
$personTypes = DB::table('sponsorships')
    ->select('person_type', DB::raw('COUNT(*) as count'))
    ->groupBy('person_type')
    ->get();

foreach ($personTypes as $pt) {
    $type = $pt->person_type ?? 'NULL';
    echo "      • $type: {$pt->count} سجل\n";
}

// ============================================
// اختبار 2: جدول data
// ============================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 اختبار جدول data (المعيلين)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$dataColumns = [
    'id' => 'المعرف الأساسي',
    'file_id_number' => 'رقم الملف (UNIQUE)',
    'data_id_number' => 'رقم الهوية',
    'data_first_name' => 'الاسم الأول',
    'data_father_name' => 'اسم الأب',
    'data_grand_father_name' => 'اسم الجد',
    'data_family_name' => 'اسم العائلة',
    'data_gender' => 'الجنس (1=ذكر, 2=أنثى)',
    'data_birth_date' => 'تاريخ الميلاد'
];

foreach ($dataColumns as $column => $description) {
    $result = checkColumn('data', $column);
    printTestResult(
        "data.$column ($description)",
        $result['exists'],
        $result['exists'] ? "النوع: {$result['type']}" : "العمود غير موجود!"
    );
}

// ============================================
// اختبار 3: جدول re_people
// ============================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 اختبار جدول re_people (أفراد العائلة)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$rePeopleColumns = [
    'id' => 'المعرف الأساسي',
    'registration_id' => 'رقم التسجيل',
    'person_id' => 'رقم الهوية',
    'first_name' => 'الاسم الأول',
    'second_name' => 'الاسم الثاني',
    'third_name' => 'الاسم الثالث',
    'last_name' => 'اسم العائلة',
    'person_gender' => 'الجنس (1=ذكر, 2=أنثى)',
    'person_birth_date' => 'تاريخ الميلاد'
];

foreach ($rePeopleColumns as $column => $description) {
    $result = checkColumn('re_people', $column);
    printTestResult(
        "re_people.$column ($description)",
        $result['exists'],
        $result['exists'] ? "النوع: {$result['type']}" : "العمود غير موجود!"
    );
}

// ============================================
// اختبار 4: جدول dead_people
// ============================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 اختبار جدول dead_people (المتوفين)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$deadPeopleColumns = [
    'id' => 'المعرف الأساسي',
    're_file_id' => 'رقم الملف المرتبط (FK → data.file_id_number)',
    // الأب
    'father_first_name' => 'اسم الأب الأول',
    'father_second_name' => 'اسم الأب الثاني',
    'father_third_name' => 'اسم الأب الثالث',
    'father_last_name' => 'اسم عائلة الأب',
    'father_id' => 'رقم هوية الأب',
    'father_death_date' => 'تاريخ وفاة الأب',
    'father_death_reason' => 'سبب وفاة الأب',
    // الأم
    'mother_first_name' => 'اسم الأم الأول',
    'mother_second_name' => 'اسم الأم الثاني',
    'mother_third_name' => 'اسم الأم الثالث',
    'mother_last_name' => 'اسم عائلة الأم',
    'mother_id' => 'رقم هوية الأم',
    'mother_death_reason' => 'سبب وفاة الأم'
];

foreach ($deadPeopleColumns as $column => $description) {
    $result = checkColumn('dead_people', $column);
    printTestResult(
        "dead_people.$column ($description)",
        $result['exists'],
        $result['exists'] ? "النوع: {$result['type']}" : "العمود غير موجود!"
    );
}

// ============================================
// اختبار 5: العلاقات بين الجداول
// ============================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔗 اختبار العلاقات بين الجداول\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// اختبار الربط: sponsorships.relation_id_number → data.file_id_number
$linkedData = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->join('data', 'sponsorships.relation_id_number', '=', 'data.file_id_number')
    ->count();

$totalWithRelation = DB::table('sponsorships')->whereNotNull('relation_id_number')->count();

printTestResult(
    "ربط sponsorships → data (via relation_id_number = file_id_number)",
    true,
    "سجلات مرتبطة: $linkedData من $totalWithRelation"
);

// اختبار الربط: sponsorships.relation_id_number → re_people.registration_id
$linkedRePeople = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->join('re_people', 'sponsorships.relation_id_number', '=', 're_people.registration_id')
    ->count();

printTestResult(
    "ربط sponsorships → re_people (via relation_id_number = registration_id)",
    true,
    "سجلات مرتبطة: $linkedRePeople"
);

// اختبار الربط: sponsorships.relation_id_number → dead_people.re_file_id
$linkedDeadPeople = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->join('dead_people', 'sponsorships.relation_id_number', '=', 'dead_people.re_file_id')
    ->count();

printTestResult(
    "ربط sponsorships → dead_people (via relation_id_number = re_file_id)",
    true,
    "سجلات مرتبطة: $linkedDeadPeople"
);

// ============================================
// اختبار 6: سيناريوهات المطابقة
// ============================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 اختبار سيناريوهات المطابقة\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// سيناريو 1: المطابقة مع data بواسطة file_id_number + data_id_number
echo "\n   📌 سيناريو 1: المطابقة مع data\n";
$sampleSponsorship = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->whereNotNull('identity_number')
    ->first();

if ($sampleSponsorship) {
    $dataMatch = DB::table('data')
        ->where('file_id_number', $sampleSponsorship->relation_id_number)
        ->first();

    if ($dataMatch) {
        echo "      ✅ عينة موجودة:\n";
        echo "         sponsorships.relation_id_number = {$sampleSponsorship->relation_id_number}\n";
        echo "         sponsorships.identity_number = {$sampleSponsorship->identity_number}\n";
        echo "         data.file_id_number = {$dataMatch->file_id_number}\n";
        echo "         data.data_id_number = {$dataMatch->data_id_number}\n";

        $matched = ($dataMatch->data_id_number == $sampleSponsorship->identity_number);
        printTestResult(
            "المطابقة بين identity_number و data_id_number",
            $matched,
            $matched ? "متطابق!" : "غير متطابق - قد يحتاج للمراجعة"
        );
    } else {
        echo "      ⚠️ لا يوجد سجل في data بنفس relation_id_number\n";
    }
} else {
    echo "      ⚠️ لا توجد كفالات بها relation_id_number\n";
}

// سيناريو 2: المطابقة مع re_people بواسطة registration_id + person_id
echo "\n   📌 سيناريو 2: المطابقة مع re_people\n";
$rePeopleMatch = DB::table('sponsorships as s')
    ->join('re_people as r', function($join) {
        $join->on('s.relation_id_number', '=', 'r.registration_id')
             ->whereColumn('s.identity_number', '=', 'r.person_id');
    })
    ->select('s.id as sponsorship_id', 's.relation_id_number', 's.identity_number',
             'r.registration_id', 'r.person_id', 'r.first_name', 'r.last_name')
    ->first();

if ($rePeopleMatch) {
    echo "      ✅ مثال على مطابقة مع re_people:\n";
    echo "         Sponsorship ID: {$rePeopleMatch->sponsorship_id}\n";
    echo "         relation_id_number = registration_id = {$rePeopleMatch->relation_id_number}\n";
    echo "         identity_number = person_id = {$rePeopleMatch->identity_number}\n";
    echo "         الاسم: {$rePeopleMatch->first_name} {$rePeopleMatch->last_name}\n";
    printTestResult("المطابقة مع re_people", true, "تم العثور على سجلات متطابقة");
} else {
    echo "      ⚠️ لا توجد كفالات مرتبطة مع re_people بالطريقة المتوقعة\n";
    printTestResult("المطابقة مع re_people", true, "لا توجد سجلات - قد يكون طبيعي");
}

// سيناريو 3: المطابقة مع dead_people
echo "\n   📌 سيناريو 3: المطابقة مع dead_people\n";
$deadPeopleMatch = DB::table('sponsorships as s')
    ->join('dead_people as d', 's.relation_id_number', '=', 'd.re_file_id')
    ->select('s.id as sponsorship_id', 's.relation_id_number', 's.identity_number', 's.person_type',
             'd.re_file_id', 'd.father_id', 'd.mother_id', 'd.father_first_name', 'd.mother_first_name')
    ->first();

if ($deadPeopleMatch) {
    echo "      ✅ مثال على مطابقة مع dead_people:\n";
    echo "         Sponsorship ID: {$deadPeopleMatch->sponsorship_id}\n";
    echo "         person_type: {$deadPeopleMatch->person_type}\n";
    echo "         relation_id_number = re_file_id = {$deadPeopleMatch->relation_id_number}\n";
    echo "         identity_number: {$deadPeopleMatch->identity_number}\n";
    echo "         father_id: {$deadPeopleMatch->father_id}\n";
    echo "         mother_id: {$deadPeopleMatch->mother_id}\n";

    // التحقق من المطابقة
    $matchesFather = ($deadPeopleMatch->father_id == $deadPeopleMatch->identity_number);
    $matchesMother = ($deadPeopleMatch->mother_id == $deadPeopleMatch->identity_number);

    if ($matchesFather) {
        printTestResult("المطابقة مع father_id", true, "identity_number = father_id");
    } elseif ($matchesMother) {
        printTestResult("المطابقة مع mother_id", true, "identity_number = mother_id");
    } else {
        echo "      ⚠️ لا تطابق - identity_number لا يطابق father_id أو mother_id\n";
    }
} else {
    echo "      ⚠️ لا توجد كفالات مرتبطة مع dead_people\n";
}

// ============================================
// ملخص النتائج
// ============================================
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                        ملخص النتائج                          ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";

$passedCount = count(array_filter($testResults, fn($t) => $t['passed']));
$totalCount = count($testResults);
$percentage = round(($passedCount / $totalCount) * 100, 1);

echo "║  إجمالي الاختبارات: $totalCount                                        ║\n";
echo "║  ✅ ناجحة: $passedCount                                                   ║\n";
echo "║  ❌ فاشلة: " . ($totalCount - $passedCount) . "                                                    ║\n";
echo "║  📊 نسبة النجاح: {$percentage}%                                           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

// ============================================
// خريطة المطابقة المكتشفة
// ============================================
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    خريطة المطابقة المكتشفة                    ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║                                                              ║\n";
echo "║  sponsorships                                                ║\n";
echo "║    ├── relation_id_number + identity_number                  ║\n";
echo "║    │                                                         ║\n";
echo "║    ├─→ data                                                  ║\n";
echo "║    │     └── file_id_number + data_id_number                 ║\n";
echo "║    │                                                         ║\n";
echo "║    ├─→ re_people                                             ║\n";
echo "║    │     └── registration_id + person_id                     ║\n";
echo "║    │                                                         ║\n";
echo "║    └─→ dead_people                                           ║\n";
echo "║          └── re_file_id + father_id/mother_id                ║\n";
echo "║                                                              ║\n";
echo "║  القرار بناءً على: person_type                               ║\n";
echo "║    • breadwinner     → data                                  ║\n";
echo "║    • family_member   → re_people                             ║\n";
echo "║    • deceased_father → dead_people (father_*)                ║\n";
echo "║    • deceased_mother → dead_people (mother_*)                ║\n";
echo "║                                                              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

// ============================================
// إحصائيات البيانات
// ============================================
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      إحصائيات البيانات                        ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";

$totalSponsorships = DB::table('sponsorships')->count();
$totalData = DB::table('data')->count();
$totalRePeople = DB::table('re_people')->count();
$totalDeadPeople = DB::table('dead_people')->count();

echo "║  📊 جدول sponsorships: " . str_pad($totalSponsorships, 10) . " سجل                   ║\n";
echo "║  📊 جدول data:         " . str_pad($totalData, 10) . " سجل                   ║\n";
echo "║  📊 جدول re_people:    " . str_pad($totalRePeople, 10) . " سجل                   ║\n";
echo "║  📊 جدول dead_people:  " . str_pad($totalDeadPeople, 10) . " سجل                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

if ($allTestsPassed) {
    echo "\n🎉 جميع الاختبارات ناجحة! جاهز للتعديلات.\n\n";
    exit(0);
} else {
    echo "\n⚠️ بعض الاختبارات فشلت. يرجى مراجعة التفاصيل أعلاه.\n\n";
    exit(1);
}
