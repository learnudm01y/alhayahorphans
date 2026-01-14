<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

$capsule = new DB;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'aso',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== اختبار الإصلاحات الجديدة ===\n\n";

// 1. فحص جلب أسماء المعيل من الأعمدة الأربعة
echo "🔍 فحص جلب أسماء المعيل من جدول data...\n";
$sponsorship = DB::table('sponsorships')
    ->whereNotNull('relation_id_number')
    ->where('relation_id_number', '!=', '')
    ->first();

if ($sponsorship) {
    echo "✅ تم العثور على كفالة: {$sponsorship->id}\n";
    echo "relation_id_number: {$sponsorship->relation_id_number}\n";

    // جلب البيانات من data
    $dataRecord = DB::table('data')
        ->where('file_id_number', $sponsorship->relation_id_number)
        ->select(['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name', 'data_current_address', 'data_city'])
        ->first();

    if ($dataRecord) {
        echo "📋 أسماء المعيل من جدول data (4 حقول منفصلة):\n";
        echo "  الاسم الأول: '{$dataRecord->data_first_name}'\n";
        echo "  اسم الأب: '{$dataRecord->data_father_name}'\n";
        echo "  اسم الجد: '{$dataRecord->data_grand_father_name}'\n";
        echo "  اسم العائلة: '{$dataRecord->data_family_name}'\n";
        echo "  العنوان التفصيلي: '{$dataRecord->data_current_address}'\n";
        echo "  المدينة: '{$dataRecord->data_city}'\n\n";

        // التحقق من وجود الأسماء الأربعة
        $hasComplete4Names = !empty($dataRecord->data_first_name) &&
                            !empty($dataRecord->data_father_name) &&
                            !empty($dataRecord->data_grand_father_name) &&
                            !empty($dataRecord->data_family_name);

        if ($hasComplete4Names) {
            echo "✅ ممتاز! الأسماء محفوظة في 4 حقول منفصلة\n";
        } else {
            echo "⚠️ تحذير: بعض الأسماء مفقودة\n";
        }
    }
}

// 2. فحص العنوان التفصيلي
echo "\n🏠 فحص العناوين التفصيلية...\n";
$addressCount = DB::table('data')
    ->whereNotNull('data_current_address')
    ->where('data_current_address', '!=', '')
    ->count();

echo "عدد السجلات التي تحتوي على عنوان تفصيلي: {$addressCount}\n";

if ($addressCount > 0) {
    $sampleAddress = DB::table('data')
        ->whereNotNull('data_current_address')
        ->where('data_current_address', '!=', '')
        ->select('data_current_address', 'data_first_name')
        ->first();

    if ($sampleAddress) {
        echo "مثال على عنوان تفصيلي:\n";
        echo "  الاسم: '{$sampleAddress->data_first_name}'\n";
        echo "  العنوان: '{$sampleAddress->data_current_address}'\n";
    }
}

// 3. فحص أنواع الأشخاص المختلفة
echo "\n👥 فحص أنواع الأشخاص...\n";
$personTypes = DB::table('sponsorships')
    ->selectRaw('person_type, COUNT(*) as count')
    ->groupBy('person_type')
    ->get();

foreach ($personTypes as $type) {
    $personType = $type->person_type ?? 'غير محدد';
    echo "  {$personType}: {$type->count} سجل\n";
}

// 4. فحص الجداول المختلفة
echo "\n📊 فحص توزيع البيانات على الجداول:\n";

$dataCount = DB::table('data')->count();
echo "  جدول data: {$dataCount} سجل\n";

$deadCount = DB::table('dead_people')->count();
echo "  جدول dead_people: {$deadCount} سجل\n";

$reCount = DB::table('re_people')->count();
echo "  جدول re_people: {$reCount} سجل\n";

// 5. فحص المتوفين
echo "\n⚰️ فحص بيانات المتوفين...\n";
$deadRecord = DB::table('dead_people')
    ->whereNotNull('father_first_name')
    ->where('father_first_name', '!=', '')
    ->first();

if ($deadRecord) {
    echo "مثال على بيانات متوفي:\n";
    echo "  الأب: '{$deadRecord->father_first_name} {$deadRecord->father_second_name} {$deadRecord->father_third_name} {$deadRecord->father_last_name}'\n";
    if (!empty($deadRecord->mother_first_name)) {
        echo "  الأم: '{$deadRecord->mother_first_name} {$deadRecord->mother_second_name} {$deadRecord->mother_third_name} {$deadRecord->mother_last_name}'\n";
    }
}

echo "\n=== انتهى الاختبار ===\n";
