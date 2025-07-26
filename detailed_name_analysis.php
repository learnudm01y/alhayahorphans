<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== تحليل مُفصَّل لأنماط تخزين الأسماء ===\n\n";

// تحليل البيانات العربية والإنجليزية
echo "1. تحليل نوعية البيانات (عربي/إنجليزي):\n";
echo "==========================================\n";

// عينة من جدول Data
$dataSamples = \App\Models\Data::limit(5)->get();
foreach ($dataSamples as $index => $record) {
    echo "السجل " . ($index + 1) . ":\n";
    echo "  الاسم الأول: '{$record->data_first_name}'\n";
    echo "  اسم الأب: '{$record->data_father_name}'\n";
    echo "  اسم الجد: '{$record->data_grand_father_name}'\n";
    echo "  اسم العائلة: '{$record->data_family_name}'\n";

    // تحديد اللغة
    $isArabic = preg_match('/[\x{0600}-\x{06FF}]/u', $record->data_first_name);
    echo "  نوع البيانات: " . ($isArabic ? 'عربي' : 'إنجليزي/لاتيني') . "\n\n";
}

// تحليل أطوال الأسماء
echo "2. تحليل أطوال الأسماء:\n";
echo "======================\n";

$avgFirstName = \App\Models\Data::selectRaw('AVG(LENGTH(data_first_name)) as avg_length')->value('avg_length');
$avgFatherName = \App\Models\Data::selectRaw('AVG(LENGTH(data_father_name)) as avg_length')->value('avg_length');
$avgGrandFatherName = \App\Models\Data::selectRaw('AVG(LENGTH(data_grand_father_name)) as avg_length')->value('avg_length');
$avgFamilyName = \App\Models\Data::selectRaw('AVG(LENGTH(data_family_name)) as avg_length')->value('avg_length');

echo "متوسط أطوال الأسماء في جدول Data:\n";
echo "- الاسم الأول: " . round($avgFirstName, 2) . " حرف\n";
echo "- اسم الأب: " . round($avgFatherName, 2) . " حرف\n";
echo "- اسم الجد: " . round($avgGrandFatherName, 2) . " حرف\n";
echo "- اسم العائلة: " . round($avgFamilyName, 2) . " حرف\n\n";

// تحليل الأسماء الفارغة أو NULL
echo "3. تحليل الأسماء الفارغة:\n";
echo "========================\n";

$emptyFirstName = \App\Models\Data::where(function($q) {
    $q->whereNull('data_first_name')->orWhere('data_first_name', '');
})->count();

$emptyFatherName = \App\Models\Data::where(function($q) {
    $q->whereNull('data_father_name')->orWhere('data_father_name', '');
})->count();

$emptyGrandFatherName = \App\Models\Data::where(function($q) {
    $q->whereNull('data_grand_father_name')->orWhere('data_grand_father_name', '');
})->count();

$emptyFamilyName = \App\Models\Data::where(function($q) {
    $q->whereNull('data_family_name')->orWhere('data_family_name', '');
})->count();

echo "عدد السجلات ذات الأسماء الفارغة:\n";
echo "- الاسم الأول فارغ: {$emptyFirstName}\n";
echo "- اسم الأب فارغ: {$emptyFatherName}\n";
echo "- اسم الجد فارغ: {$emptyGrandFatherName}\n";
echo "- اسم العائلة فارغ: {$emptyFamilyName}\n\n";

// تحليل الأسماء التي تحتوي على مسافات (قد تكون أسماء مركبة)
echo "4. تحليل الأسماء المركبة (تحتوي على مسافات):\n";
echo "=============================================\n";

$compoundFirstName = \App\Models\Data::where('data_first_name', 'LIKE', '% %')->count();
$compoundFatherName = \App\Models\Data::where('data_father_name', 'LIKE', '% %')->count();
$compoundGrandFatherName = \App\Models\Data::where('data_grand_father_name', 'LIKE', '% %')->count();
$compoundFamilyName = \App\Models\Data::where('data_family_name', 'LIKE', '% %')->count();

echo "عدد الأسماء المركبة (تحتوي على مسافات):\n";
echo "- الاسم الأول مركب: {$compoundFirstName}\n";
echo "- اسم الأب مركب: {$compoundFatherName}\n";
echo "- اسم الجد مركب: {$compoundGrandFatherName}\n";
echo "- اسم العائلة مركب: {$compoundFamilyName}\n\n";

// عينة من الأسماء المركبة
echo "أمثلة على الأسماء المركبة:\n";
$compoundExamples = \App\Models\Data::where('data_father_name', 'LIKE', '% %')->limit(3)->get();
foreach ($compoundExamples as $example) {
    echo "- اسم الأب المركب: '{$example->data_father_name}'\n";
}

echo "\n";

// تحليل جدول أفراد الأسرة
echo "5. تحليل أفراد الأسرة:\n";
echo "====================\n";

$rePeopleSamples = \App\Models\RePeople::limit(3)->get();
foreach ($rePeopleSamples as $index => $record) {
    echo "فرد الأسرة " . ($index + 1) . ":\n";
    echo "  الاسم الأول: '{$record->first_name}'\n";
    echo "  الاسم الثاني: '{$record->second_name}'\n";
    echo "  الاسم الثالث: '{$record->third_name}'\n";
    echo "  الاسم الأخير: '{$record->last_name}'\n\n";
}

// تحليل خاص: هل يوجد ازدواجية في التسمية؟
echo "6. تحليل ازدواجية البيانات:\n";
echo "==========================\n";

// البحث عن أسماء مُكررة في نفس الحقل
$duplicateFirstNames = \App\Models\Data::select('data_first_name')
    ->whereNotNull('data_first_name')
    ->where('data_first_name', '!=', '')
    ->groupBy('data_first_name')
    ->havingRaw('COUNT(*) > 1')
    ->count();

echo "عدد الأسماء الأولى المُكررة: {$duplicateFirstNames}\n";

// البحث عن أسماء كاملة مُكررة
$duplicateFullNames = \App\Models\Data::selectRaw('CONCAT(data_first_name, " ", data_father_name, " ", data_grand_father_name, " ", data_family_name) as full_name')
    ->groupByRaw('CONCAT(data_first_name, " ", data_father_name, " ", data_grand_father_name, " ", data_family_name)')
    ->havingRaw('COUNT(*) > 1')
    ->count();

echo "عدد الأسماء الكاملة المُكررة: {$duplicateFullNames}\n\n";

echo "=== الخلاصة والتوصيات ===\n";
echo "1. نظام التخزين: الأسماء مُجزأة في حقول منفصلة\n";
echo "2. البيانات مختلطة: تحتوي على أسماء عربية وإنجليزية\n";
echo "3. الأسماء المركبة: موجودة في جميع حقول الأسماء\n";
echo "4. مشكلة محتملة: بعض الأسماء قد تحتوي على عدة كلمات في حقل واحد\n";
echo "5. التوصية: استخدام دالة trim() عند دمج الأسماء لتجنب المسافات الزائدة\n";
