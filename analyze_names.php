<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== تحليل بنية تخزين الأسماء في قاعدة البيانات ===\n\n";

// تحليل جدول Data (السجلات الرئيسية)
echo "1. جدول Data (السجلات الرئيسية):\n";
echo "================================\n";

$dataSample = \App\Models\Data::first();
if ($dataSample) {
    echo "نموذج من البيانات:\n";
    echo "- data_first_name: '{$dataSample->data_first_name}'\n";
    echo "- data_father_name: '{$dataSample->data_father_name}'\n";
    echo "- data_grand_father_name: '{$dataSample->data_grand_father_name}'\n";
    echo "- data_family_name: '{$dataSample->data_family_name}'\n\n";

    // عرض الاسم الكامل كما يتم تكوينه
    $fullName = trim("{$dataSample->data_first_name} {$dataSample->data_father_name} {$dataSample->data_grand_father_name} {$dataSample->data_family_name}");
    echo "الاسم الكامل المُكوَّن: '{$fullName}'\n\n";

    // إحصائيات
    $totalData = \App\Models\Data::count();
    $withFirstName = \App\Models\Data::whereNotNull('data_first_name')->where('data_first_name', '!=', '')->count();
    $withFatherName = \App\Models\Data::whereNotNull('data_father_name')->where('data_father_name', '!=', '')->count();
    $withGrandFatherName = \App\Models\Data::whereNotNull('data_grand_father_name')->where('data_grand_father_name', '!=', '')->count();
    $withFamilyName = \App\Models\Data::whereNotNull('data_family_name')->where('data_family_name', '!=', '')->count();

    echo "إحصائيات الأسماء:\n";
    echo "- إجمالي السجلات: {$totalData}\n";
    echo "- يحتوي على الاسم الأول: {$withFirstName}\n";
    echo "- يحتوي على اسم الأب: {$withFatherName}\n";
    echo "- يحتوي على اسم الجد: {$withGrandFatherName}\n";
    echo "- يحتوي على اسم العائلة: {$withFamilyName}\n\n";
} else {
    echo "لا توجد بيانات في جدول Data\n\n";
}

// تحليل جدول RePeople (أفراد الأسرة)
echo "2. جدول RePeople (أفراد الأسرة):\n";
echo "==================================\n";

$rePeopleSample = \App\Models\RePeople::first();
if ($rePeopleSample) {
    echo "نموذج من البيانات:\n";
    echo "- first_name: '{$rePeopleSample->first_name}'\n";
    echo "- second_name: '{$rePeopleSample->second_name}'\n";
    echo "- third_name: '{$rePeopleSample->third_name}'\n";
    echo "- last_name: '{$rePeopleSample->last_name}'\n\n";

    // عرض الاسم الكامل كما يتم تكوينه
    $fullName = trim("{$rePeopleSample->first_name} {$rePeopleSample->second_name} {$rePeopleSample->third_name} {$rePeopleSample->last_name}");
    echo "الاسم الكامل المُكوَّن: '{$fullName}'\n\n";

    // إحصائيات
    $totalRePeople = \App\Models\RePeople::count();
    $withFirstName = \App\Models\RePeople::whereNotNull('first_name')->where('first_name', '!=', '')->count();
    $withSecondName = \App\Models\RePeople::whereNotNull('second_name')->where('second_name', '!=', '')->count();
    $withThirdName = \App\Models\RePeople::whereNotNull('third_name')->where('third_name', '!=', '')->count();
    $withLastName = \App\Models\RePeople::whereNotNull('last_name')->where('last_name', '!=', '')->count();

    echo "إحصائيات الأسماء:\n";
    echo "- إجمالي السجلات: {$totalRePeople}\n";
    echo "- يحتوي على الاسم الأول: {$withFirstName}\n";
    echo "- يحتوي على الاسم الثاني: {$withSecondName}\n";
    echo "- يحتوي على الاسم الثالث: {$withThirdName}\n";
    echo "- يحتوي على الاسم الأخير: {$withLastName}\n\n";
} else {
    echo "لا توجد بيانات في جدول RePeople\n\n";
}

// تحليل جدول DeadPeople (المتوفين)
echo "3. جدول DeadPeople (المتوفين):\n";
echo "===============================\n";

$deadPeopleSample = \App\Models\DeadPepole::first();
if ($deadPeopleSample) {
    echo "نموذج من بيانات الأب:\n";
    echo "- father_first_name: '{$deadPeopleSample->father_first_name}'\n";
    echo "- father_second_name: '{$deadPeopleSample->father_second_name}'\n";
    echo "- father_third_name: '{$deadPeopleSample->father_third_name}'\n";
    echo "- father_last_name: '{$deadPeopleSample->father_last_name}'\n\n";

    echo "نموذج من بيانات الأم:\n";
    echo "- mother_first_name: '{$deadPeopleSample->mother_first_name}'\n";
    echo "- mother_second_name: '{$deadPeopleSample->mother_second_name}'\n";
    echo "- mother_third_name: '{$deadPeopleSample->mother_third_name}'\n";
    echo "- mother_last_name: '{$deadPeopleSample->mother_last_name}'\n\n";

    // عرض الأسماء الكاملة
    $fatherFullName = trim("{$deadPeopleSample->father_first_name} {$deadPeopleSample->father_second_name} {$deadPeopleSample->father_third_name} {$deadPeopleSample->father_last_name}");
    $motherFullName = trim("{$deadPeopleSample->mother_first_name} {$deadPeopleSample->mother_second_name} {$deadPeopleSample->mother_third_name} {$deadPeopleSample->mother_last_name}");

    echo "اسم الأب الكامل: '{$fatherFullName}'\n";
    echo "اسم الأم الكامل: '{$motherFullName}'\n\n";

    // إحصائيات
    $totalDeadPeople = \App\Models\DeadPepole::count();
    echo "إحصائيات المتوفين:\n";
    echo "- إجمالي السجلات: {$totalDeadPeople}\n\n";
} else {
    echo "لا توجد بيانات في جدول DeadPeople\n\n";
}

echo "=== خلاصة التحليل ===\n";
echo "1. جميع الجداول تخزن الأسماء مجزأة إلى حقول منفصلة\n";
echo "2. جدول Data: يستخدم (data_first_name, data_father_name, data_grand_father_name, data_family_name)\n";
echo "3. جدول RePeople: يستخدم (first_name, second_name, third_name, last_name)\n";
echo "4. جدول DeadPeople: يستخدم (father/mother + _first_name, _second_name, _third_name, _last_name)\n";
echo "5. الأسماء الكاملة يتم تكوينها برمجياً عند الحاجة\n";
