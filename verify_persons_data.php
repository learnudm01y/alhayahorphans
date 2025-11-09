<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=================================================================\n";
echo "        فحص بيانات جدول persons\n";
echo "=================================================================\n\n";

// 1. فحص الشخص الأصلي (407015692)
echo "1️⃣ فحص الشخص الأصلي (407015692)\n";
echo "-----------------------------------------------------------------\n";

$person = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_ID_NUM', '407015692')
    ->first();

if ($person) {
    echo "   ✅ موجود في جدول persons\n";
    
    // طباعة بعض البيانات المتاحة
    $fields = get_object_vars($person);
    echo "   📋 عدد الحقول: " . count($fields) . "\n";
    
    // محاولة طباعة بعض الحقول الشائعة
    if (isset($person->name)) echo "   📋 الاسم: {$person->name}\n";
    if (isset($person->full_name)) echo "   📋 الاسم: {$person->full_name}\n";
} else {
    echo "   ❌ غير موجود في جدول persons!\n";
}

// 2. فحص هل الأرقام المفقودة (937097657, 937340867) موجودة كـ CF_ID_NUM في relations
echo "\n2️⃣ فحص إذا كانت الأرقام المفقودة موجودة كـ CF_ID_NUM\n";
echo "-----------------------------------------------------------------\n";

$testIds = ['937097657', '937340867'];
foreach ($testIds as $id) {
    $exists = DB::connection('civilregistry')
        ->table('relations')
        ->where('CF_ID_NUM', $id)
        ->exists();
    
    if ($exists) {
        echo "   ✅ {$id}: موجود كـ CF_ID_NUM في relations\n";
        
        // جلب أقاربه
        $relatives = DB::connection('civilregistry')
            ->table('relations')
            ->where('CF_ID_NUM', $id)
            ->select('CF_ID_RELATIVE', 'CF_RELATIVE_CD')
            ->get();
        
        echo "      📊 عدد أقاربه: " . count($relatives) . "\n";
    } else {
        echo "   ❌ {$id}: غير موجود\n";
    }
}

// 3. فحص عكسي: هل 407015692 موجود كـ CF_ID_RELATIVE لشخص آخر؟
echo "\n3️⃣ فحص عكسي: البحث عن 407015692 كـ CF_ID_RELATIVE\n";
echo "-----------------------------------------------------------------\n";

$reverseRelations = DB::connection('civilregistry')
    ->table('relations')
    ->where('CF_ID_RELATIVE', '407015692')
    ->select('CF_ID_NUM', 'CF_RELATIVE_CD')
    ->get();

echo "   📊 عدد الأشخاص الذين 407015692 قريب لهم: " . count($reverseRelations) . "\n\n";

foreach ($reverseRelations as $rel) {
    echo "   • CF_ID_NUM: {$rel->CF_ID_NUM}, CF_RELATIVE_CD: {$rel->CF_RELATIVE_CD}\n";
    
    // فحص إذا كان هذا الشخص موجود في persons
    $personExists = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', $rel->CF_ID_NUM)
        ->exists();
    
    echo "     " . ($personExists ? "✅ موجود في persons" : "❌ غير موجود في persons") . "\n\n";
}

// 4. إحصائيات عامة
echo "4️⃣ إحصائيات عامة\n";
echo "-----------------------------------------------------------------\n";

$totalRelations = DB::connection('civilregistry')
    ->table('relations')
    ->count();

$totalPersons = DB::connection('civilregistry')
    ->table('persons')
    ->count();

// عدد CF_ID_NUM الموجودة في persons
$validRelations = DB::connection('civilregistry')
    ->table('relations as r')
    ->join('persons as p', 'r.CF_ID_NUM', '=', 'p.CI_ID_NUM')
    ->count();

// عدد CF_ID_RELATIVE الموجودة في persons
$validRelatives = DB::connection('civilregistry')
    ->table('relations as r')
    ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
    ->count();

echo "   📊 إجمالي السجلات في relations: " . number_format($totalRelations) . "\n";
echo "   📊 إجمالي السجلات في persons: " . number_format($totalPersons) . "\n";
echo "   📊 CF_ID_NUM موجود في persons: " . number_format($validRelations) . " (" . round(($validRelations/$totalRelations)*100, 2) . "%)\n";
echo "   📊 CF_ID_RELATIVE موجود في persons: " . number_format($validRelatives) . " (" . round(($validRelatives/$totalRelations)*100, 2) . "%)\n";

echo "\n=================================================================\n";
echo "✅ انتهى الفحص\n";
echo "=================================================================\n\n";
