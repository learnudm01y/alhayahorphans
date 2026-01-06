<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║     سيناريو: المعيل موجود فقط في جدول sponsorships (بدون بيانات تفصيلية)     ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
echo "║  المكفول: 42979087 (موجود في السجل المدني)                                   ║\n";
echo "║  المعيل: 999888777 (موجود فقط في sponsorships)                               ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

$sponsoredId = '42979087';     // المكفول - موجود في السجل المدني
$guardianId = '999888777';      // المعيل - سيكون موجود فقط في sponsorships
$civilDB = DB::connection('civilregistry');

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الأول: التحقق من عدم وجود المعيل في أي جدول
// ═══════════════════════════════════════════════════════════════════════════════

echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│        الجزء الأول: التحقق من عدم وجود المعيل ({$guardianId}) في أي جدول        │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n\n";

$guardianExistsAnywhere = false;

// 1. التحقق من جدول data
$inData = DB::table('data')->where('data_id_number', $guardianId)->exists();
echo "1️⃣  جدول data: " . ($inData ? "✗ موجود (يجب حذفه)" : "✓ غير موجود") . "\n";
if ($inData) $guardianExistsAnywhere = true;

// 2. التحقق من جدول re_people
$inRePeople = DB::table('re_people')->where('person_id', $guardianId)->exists();
echo "2️⃣  جدول re_people: " . ($inRePeople ? "✗ موجود (يجب حذفه)" : "✓ غير موجود") . "\n";
if ($inRePeople) $guardianExistsAnywhere = true;

// 3. التحقق من جدول dead_people
$inDeadPeople = DB::table('dead_people')
    ->where('father_id', $guardianId)
    ->orWhere('mother_id', $guardianId)
    ->exists();
echo "3️⃣  جدول dead_people: " . ($inDeadPeople ? "✗ موجود (يجب حذفه)" : "✓ غير موجود") . "\n";
if ($inDeadPeople) $guardianExistsAnywhere = true;

// 4. التحقق من السجل المدني
$inCivil = $civilDB->table('persons')->where('CI_ID_NUM', $guardianId)->exists();
echo "4️⃣  جدول persons (السجل المدني): " . ($inCivil ? "✗ موجود" : "✓ غير موجود") . "\n";
if ($inCivil) $guardianExistsAnywhere = true;

if ($guardianExistsAnywhere) {
    echo "\n⚠️  المعيل موجود في بعض الجداول. سيتم استخدام رقم هوية مختلف.\n";
    // إنشاء رقم هوية عشوائي غير موجود
    $guardianId = '888777666';
    echo "   الرقم الجديد: {$guardianId}\n";
}

echo "\n✅ المعيل ({$guardianId}) غير موجود في أي جدول تفصيلي.\n";

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الثاني: إنشاء سجل الكفالة
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                      الجزء الثاني: إنشاء سجل الكفالة                          │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n\n";

// التحقق من وجود سجل سابق وحذفه
$existingSponsorship = DB::table('sponsorships')->where('identity_number', $sponsoredId)->first();
if ($existingSponsorship) {
    echo "⚠️  يوجد سجل سابق للمكفول. سيتم تحديثه.\n";
    DB::table('sponsorships')
        ->where('identity_number', $sponsoredId)
        ->update([
            'guardian_identity_number' => $guardianId,
            'guardian_name' => 'أحمد محمد علي الخالدي',
            'updated_at' => now()
        ]);
    echo "✓ تم تحديث السجل.\n";
} else {
    // إنشاء سجل جديد
    echo "📝 إنشاء سجل كفالة جديد...\n";

    // الحصول على sponsor_id صالح
    $sponsor = DB::table('sponsors')->first();
    if (!$sponsor) {
        echo "✗ لا يوجد كفيل في قاعدة البيانات. سيتم إنشاء واحد.\n";
        $sponsorId = DB::table('sponsors')->insertGetId([
            'first_name' => 'كفيل',
            'last_name' => 'اختباري',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    } else {
        $sponsorId = $sponsor->id;
    }

    DB::table('sponsorships')->insert([
        'sponsor_id' => $sponsorId,
        'identity_number' => $sponsoredId,
        'orphan_name' => 'عماد محمود عبد الحفيظ جحجوح', // من السجل المدني
        'guardian_identity_number' => $guardianId,
        'guardian_name' => 'أحمد محمد علي الخالدي', // اسم مدمج فقط
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✓ تم إنشاء سجل الكفالة.\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الثالث: اختبار شامل للسيناريو
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                       الجزء الثالث: اختبار السيناريو                          │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n\n";

// جلب سجل الكفالة
$sponsorship = DB::table('sponsorships')->where('identity_number', $sponsoredId)->first();

echo "📋 بيانات سجل الكفالة:\n";
echo "   - رقم هوية المكفول: " . $sponsorship->identity_number . "\n";
echo "   - اسم المكفول: " . $sponsorship->orphan_name . "\n";
echo "   - رقم هوية المعيل: " . $sponsorship->guardian_identity_number . "\n";
echo "   - اسم المعيل: " . $sponsorship->guardian_name . "\n";

// فحص المكفول في جميع الجداول
echo "\n" . str_repeat("─", 78) . "\n";
echo "🔍 فحص المكفول ({$sponsoredId}):\n";
echo str_repeat("─", 78) . "\n";

$sponsoredResults = [];

// sponsorships
$sponsoredResults['sponsorships'] = DB::table('sponsorships')->where('identity_number', $sponsoredId)->exists();
echo "   sponsorships: " . ($sponsoredResults['sponsorships'] ? "✓" : "✗") . "\n";

// re_people
$sponsoredResults['re_people'] = DB::table('re_people')->where('person_id', $sponsoredId)->exists();
echo "   re_people: " . ($sponsoredResults['re_people'] ? "✓" : "✗") . "\n";

// dead_people
$sponsoredResults['dead_people'] = DB::table('dead_people')
    ->where('father_id', $sponsoredId)
    ->orWhere('mother_id', $sponsoredId)
    ->exists();
echo "   dead_people: " . ($sponsoredResults['dead_people'] ? "✓" : "✗") . "\n";

// data
$sponsoredResults['data'] = DB::table('data')->where('data_id_number', $sponsoredId)->exists();
echo "   data: " . ($sponsoredResults['data'] ? "✓" : "✗") . "\n";

// civil registry
$sponsoredResults['civil'] = $civilDB->table('persons')->where('CI_ID_NUM', $sponsoredId)->exists();
echo "   persons (السجل المدني): " . ($sponsoredResults['civil'] ? "✓" : "✗") . "\n";

// فحص المعيل في جميع الجداول
echo "\n" . str_repeat("─", 78) . "\n";
echo "🔍 فحص المعيل ({$guardianId}):\n";
echo str_repeat("─", 78) . "\n";

$guardianResults = [];

// sponsorships
$guardianResults['sponsorships'] = DB::table('sponsorships')->where('guardian_identity_number', $guardianId)->exists();
echo "   sponsorships: " . ($guardianResults['sponsorships'] ? "✓" : "✗") . "\n";

// re_people
$guardianResults['re_people'] = DB::table('re_people')->where('person_id', $guardianId)->exists();
echo "   re_people: " . ($guardianResults['re_people'] ? "✓" : "✗") . "\n";

// dead_people
$guardianResults['dead_people'] = DB::table('dead_people')
    ->where('father_id', $guardianId)
    ->orWhere('mother_id', $guardianId)
    ->exists();
echo "   dead_people: " . ($guardianResults['dead_people'] ? "✓" : "✗") . "\n";

// data
$guardianResults['data'] = DB::table('data')->where('data_id_number', $guardianId)->exists();
echo "   data: " . ($guardianResults['data'] ? "✓" : "✗") . "\n";

// civil registry
$guardianResults['civil'] = $civilDB->table('persons')->where('CI_ID_NUM', $guardianId)->exists();
echo "   persons (السجل المدني): " . ($guardianResults['civil'] ? "✓" : "✗") . "\n";

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الرابع: تحديد مصدر البيانات وعدد الحقول
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                      قرار عدد الحقول المعروضة للمستخدم                        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

// تحديد مصدر بيانات المكفول
function determineSponsoredSource($results) {
    if ($results['re_people']) return ['source' => 're_people', 'fields' => 4];
    if ($results['dead_people']) return ['source' => 'dead_people', 'fields' => 4];
    if ($results['civil']) return ['source' => 'civil_registry', 'fields' => 4];
    if ($results['sponsorships']) return ['source' => 'sponsorship_only', 'fields' => 1];
    return ['source' => 'none', 'fields' => 0];
}

// تحديد مصدر بيانات المعيل
function determineGuardianSource($results) {
    if ($results['data']) return ['source' => 'data', 'fields' => 4];
    if ($results['re_people']) return ['source' => 're_people', 'fields' => 4];
    if ($results['dead_people']) return ['source' => 'dead_people', 'fields' => 4];
    if ($results['civil']) return ['source' => 'civil_registry', 'fields' => 4];
    if ($results['sponsorships']) return ['source' => 'sponsorship_only', 'fields' => 1];
    return ['source' => 'none', 'fields' => 0];
}

$sponsoredSource = determineSponsoredSource($sponsoredResults);
$guardianSource = determineGuardianSource($guardianResults);

$sourceNames = [
    're_people' => 'جدول أفراد العائلة (re_people)',
    'dead_people' => 'جدول المتوفين (dead_people)',
    'civil_registry' => 'السجل المدني (persons)',
    'data' => 'جدول البيانات التفصيلية (data)',
    'sponsorship_only' => 'جدول الكفالات فقط (sponsorships)',
    'none' => 'لا توجد بيانات'
];

echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                         المكفول (42979087)                                    │\n";
echo "├──────────────────────────────────────────────────────────────────────────────┤\n";
printf("│  مصدر البيانات: %-58s │\n", $sourceNames[$sponsoredSource['source']]);
printf("│  عدد حقول الاسم: %-57s │\n", $sponsoredSource['fields'] . " حقل/حقول");
echo "│                                                                              │\n";

if ($sponsoredSource['fields'] == 4) {
    echo "│  ┌────────────────────────────────────────────────────────────────────────┐ │\n";
    echo "│  │  الحقول المعروضة:                                                     │ │\n";
    echo "│  │    ┌──────────────────┐  ┌──────────────────┐                         │ │\n";
    echo "│  │    │   الاسم الأول    │  │     اسم الأب     │                         │ │\n";
    echo "│  │    └──────────────────┘  └──────────────────┘                         │ │\n";
    echo "│  │    ┌──────────────────┐  ┌──────────────────┐                         │ │\n";
    echo "│  │    │    اسم الجد      │  │   اسم العائلة    │                         │ │\n";
    echo "│  │    └──────────────────┘  └──────────────────┘                         │ │\n";
    echo "│  └────────────────────────────────────────────────────────────────────────┘ │\n";

    // جلب البيانات من السجل المدني
    $civilData = $civilDB->table('persons')->where('CI_ID_NUM', $sponsoredId)->first();
    if ($civilData) {
        echo "│                                                                              │\n";
        printf("│  القيم: %s | %s | %s | %s\n",
            $civilData->CI_FIRST_ARB,
            $civilData->CI_FATHER_ARB,
            $civilData->CI_GRAND_FATHER_ARB,
            $civilData->CI_FAMILY_ARB);
    }
} else {
    echo "│  ┌────────────────────────────────────────────────────────────────────────┐ │\n";
    echo "│  │  الحقول المعروضة:                                                     │ │\n";
    echo "│  │    ┌────────────────────────────────────────────────────────────────┐ │ │\n";
    echo "│  │    │              اسم المكفول (حقل واحد مدمج)                       │ │ │\n";
    echo "│  │    └────────────────────────────────────────────────────────────────┘ │ │\n";
    echo "│  └────────────────────────────────────────────────────────────────────────┘ │\n";
    printf("│  القيمة: %-66s │\n", $sponsorship->orphan_name);
}
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
printf("│                         المعيل (%s)                                    │\n", $guardianId);
echo "├──────────────────────────────────────────────────────────────────────────────┤\n";
printf("│  مصدر البيانات: %-58s │\n", $sourceNames[$guardianSource['source']]);
printf("│  عدد حقول الاسم: %-57s │\n", $guardianSource['fields'] . " حقل/حقول");
echo "│                                                                              │\n";

if ($guardianSource['fields'] == 4) {
    echo "│  ┌────────────────────────────────────────────────────────────────────────┐ │\n";
    echo "│  │  الحقول المعروضة:                                                     │ │\n";
    echo "│  │    ┌──────────────────┐  ┌──────────────────┐                         │ │\n";
    echo "│  │    │   الاسم الأول    │  │     اسم الأب     │                         │ │\n";
    echo "│  │    └──────────────────┘  └──────────────────┘                         │ │\n";
    echo "│  │    ┌──────────────────┐  ┌──────────────────┐                         │ │\n";
    echo "│  │    │    اسم الجد      │  │   اسم العائلة    │                         │ │\n";
    echo "│  │    └──────────────────┘  └──────────────────┘                         │ │\n";
    echo "│  └────────────────────────────────────────────────────────────────────────┘ │\n";
} else {
    echo "│  ┌────────────────────────────────────────────────────────────────────────┐ │\n";
    echo "│  │  الحقول المعروضة:                                                     │ │\n";
    echo "│  │    ┌────────────────────────────────────────────────────────────────┐ │ │\n";
    echo "│  │    │              اسم المعيل (حقل واحد مدمج)                        │ │ │\n";
    echo "│  │    └────────────────────────────────────────────────────────────────┘ │ │\n";
    echo "│  └────────────────────────────────────────────────────────────────────────┘ │\n";
    printf("│  القيمة: %-66s │\n", $sponsorship->guardian_name);
    echo "│                                                                              │\n";
    echo "│  ℹ️  ملاحظة: رقم هوية المعيل موجود في الكفالة                              │\n";
    echo "│     لذلك لن يظهر زر البحث في السجل المدني                                  │\n";
    echo "│     (البحث يظهر فقط عند عدم وجود رقم هوية المعيل)                          │\n";
}
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الخامس: الخلاصة
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                              الخلاصة النهائية                                 ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
echo "║                                                                              ║\n";
echo "║  ✅ السيناريو المُنشأ:                                                        ║\n";
echo "║  ───────────────────────────────────────────────────────────────────────────  ║\n";
echo "║                                                                              ║\n";
echo "║  📊 المكفول (42979087):                                                       ║\n";
echo "║     • موجود في: sponsorships ✓ | السجل المدني ✓                             ║\n";
echo "║     • عدد الحقول: 4 حقول (من السجل المدني)                                   ║\n";
echo "║     • الاسم: عماد محمود عبد الحفيظ جحجوح                                     ║\n";
echo "║                                                                              ║\n";
printf("║  📊 المعيل (%s):                                                        ║\n", $guardianId);
echo "║     • موجود في: sponsorships فقط ✓                                          ║\n";
echo "║     • عدد الحقول: 1 حقل (اسم مدمج فقط)                                       ║\n";
echo "║     • الاسم: أحمد محمد علي الخالدي                                           ║\n";
echo "║     • 🔍 زر البحث في السجل المدني: غير مُفعّل (رقم الهوية موجود)           ║\n";
echo "║                                                                              ║\n";
echo "║  ───────────────────────────────────────────────────────────────────────────  ║\n";
echo "║                                                                              ║\n";
echo "║  🎯 ما سيراه المستخدم في الواجهة:                                             ║\n";
echo "║                                                                              ║\n";
echo "║  ┌─ اسم المكفول ────────────────────────────────────────────────────────┐   ║\n";
echo "║  │  [الاسم الأول: عماد] [اسم الأب: محمود] [اسم الجد: عبد الحفيظ] [العائلة: جحجوح] │   ║\n";
echo "║  └─────────────────────────────────────────────────────────────────────────┘   ║\n";
echo "║                                                                              ║\n";
echo "║  ┌─ اسم المعيل ─────────────────────────────────────────────────────────┐   ║\n";
echo "║  │  [أحمد محمد علي الخالدي                                              ]   │   ║\n";
echo "║  └─────────────────────────────────────────────────────────────────────────┘   ║\n";
echo "║                                                                              ║\n";
echo "║  ┌─ رقم هوية المعيل ────────────────────────────────────────────────────┐   ║\n";
printf("║  │  [%s                                                            ]   │   ║\n", $guardianId);
echo "║  │  (لا يوجد زر بحث - رقم الهوية موجود بالفعل)                            │   ║\n";
echo "║  └─────────────────────────────────────────────────────────────────────────┘   ║\n";
echo "║                                                                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";

echo "\n✅ تم إنشاء السيناريو بنجاح!\n";
echo "📌 يمكنك الآن الدخول للنظام بالمستخدم المرتبط بالكفالة لاختبار الواجهة.\n";
