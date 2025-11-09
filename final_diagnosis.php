<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=================================================================\n";
echo "        التشخيص النهائي - فحص شامل\n";
echo "=================================================================\n\n";

// 1. الحصول على أسماء الأعمدة الفعلية من جدول persons
echo "1️⃣ أسماء الأعمدة في جدول persons\n";
echo "-----------------------------------------------------------------\n";

$personColumns = DB::connection('civilregistry')
    ->select("SHOW COLUMNS FROM persons");

echo "   📋 الأعمدة الموجودة:\n";
foreach ($personColumns as $col) {
    echo "      • {$col->Field} ({$col->Type})\n";
}

// 2. أسماء الأعمدة في جدول relations
echo "\n2️⃣ أسماء الأعمدة في جدول relations\n";
echo "-----------------------------------------------------------------\n";

$relationsColumns = DB::connection('civilregistry')
    ->select("SHOW COLUMNS FROM relations");

echo "   📋 الأعمدة الموجودة:\n";
foreach ($relationsColumns as $col) {
    echo "      • {$col->Field} ({$col->Type})\n";
}

// 3. فحص البيانات للشخص 407015692
echo "\n3️⃣ بيانات الشخص 407015692 في جدول persons\n";
echo "-----------------------------------------------------------------\n";

$person = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_ID_NUM', '407015692')
    ->first();

if ($person) {
    echo "   ✅ موجود في جدول persons\n";
    echo "   📋 جميع البيانات:\n";
    foreach (get_object_vars($person) as $key => $value) {
        if (strlen($value) > 100) {
            echo "      • {$key}: [نص طويل]\n";
        } else {
            echo "      • {$key}: {$value}\n";
        }
    }
} else {
    echo "   ❌ غير موجود في جدول persons!\n";
}

// 4. العلاقات في جدول relations
echo "\n4️⃣ علاقات الشخص 407015692 في جدول relations\n";
echo "-----------------------------------------------------------------\n";

$relations = DB::connection('civilregistry')
    ->table('relations')
    ->where('CF_ID_NUM', '407015692')
    ->get();

echo "   📊 عدد العلاقات: " . count($relations) . "\n\n";

foreach ($relations as $i => $rel) {
    echo "   العلاقة رقم " . ($i + 1) . ":\n";
    foreach (get_object_vars($rel) as $key => $value) {
        echo "      • {$key}: {$value}\n";
    }
    
    // فحص إذا كان CF_ID_RELATIVE موجود في persons
    echo "      🔍 فحص CF_ID_RELATIVE ({$rel->CF_ID_RELATIVE}) في persons:\n";
    
    $relativeExists = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', $rel->CF_ID_RELATIVE)
        ->exists();
    
    if ($relativeExists) {
        echo "         ✅ موجود في persons\n";
        
        $relativePerson = DB::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', $rel->CF_ID_RELATIVE)
            ->first();
        
        echo "         📋 بيانات القريب:\n";
        $count = 0;
        foreach (get_object_vars($relativePerson) as $key => $value) {
            if ($count < 5) { // طباعة أول 5 حقول فقط
                if (strlen($value) > 50) {
                    echo "            • {$key}: [نص طويل]\n";
                } else {
                    echo "            • {$key}: {$value}\n";
                }
                $count++;
            }
        }
    } else {
        echo "         ❌ غير موجود في persons!\n";
    }
    echo "\n";
}

// 5. اختبار JOIN المباشر
echo "5️⃣ اختبار JOIN بين relations و persons\n";
echo "-----------------------------------------------------------------\n";

$joinTest = DB::connection('civilregistry')
    ->table('relations as r')
    ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
    ->where('r.CF_ID_NUM', '407015692')
    ->select('r.*', 'p.CI_ID_NUM as person_id')
    ->get();

echo "   📊 عدد النتائج من JOIN: " . count($joinTest) . "\n";

if (count($joinTest) > 0) {
    echo "   ✅ JOIN نجح!\n";
    foreach ($joinTest as $result) {
        echo "      • CF_ID_RELATIVE: {$result->CF_ID_RELATIVE}, person_id: {$result->person_id}\n";
    }
} else {
    echo "   ❌ JOIN فشل - لا توجد نتائج\n";
}

// 6. إحصائيات عامة
echo "\n6️⃣ إحصائيات عامة\n";
echo "-----------------------------------------------------------------\n";

$totalRelations = DB::connection('civilregistry')
    ->table('relations')
    ->count();

$totalPersons = DB::connection('civilregistry')
    ->table('persons')
    ->count();

$validRelatives = DB::connection('civilregistry')
    ->table('relations as r')
    ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
    ->count();

$percentage = ($validRelatives / $totalRelations) * 100;

echo "   📊 إجمالي السجلات في relations: " . number_format($totalRelations) . "\n";
echo "   📊 إجمالي السجلات في persons: " . number_format($totalPersons) . "\n";
echo "   📊 CF_ID_RELATIVE موجود في persons: " . number_format($validRelatives) . " (" . number_format($percentage, 2) . "%)\n";
echo "   📊 CF_ID_RELATIVE غير موجود: " . number_format($totalRelations - $validRelatives) . "\n";

echo "\n=================================================================\n";
echo "✅ انتهى التشخيص\n";
echo "=================================================================\n\n";
