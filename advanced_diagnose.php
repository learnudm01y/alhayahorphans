<?php
/**
 * تشخيص متقدم - فحص الكود والاستعلامات مباشرة
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=================================================================\n";
echo "        تشخيص متقدم - اختبار الاستعلامات مباشرة                 \n";
echo "=================================================================\n\n";

$testId = '407015692';

// 1. اختبار الاستعلام المباشر (بدون Controller)
echo "1️⃣ اختبار الاستعلام المباشر\n";
echo "-----------------------------------------------------------------\n";
try {
    $directRelations = DB::connection('civilregistry')
        ->table('relations as r')
        ->select(
            'r.CF_ID_NUM',
            'r.CF_ID_RELATIVE',
            'r.CF_RELATIVE_CD',
            DB::raw('COALESCE(cat.attribute, CONCAT("علاقة ", r.CF_RELATIVE_CD)) as relation_type'),
            'p.CI_FIRST_ARB',
            'p.CI_FATHER_ARB',
            'p.CI_GRAND_FATHER_ARB',
            'p.CI_FAMILY_ARB',
            'p.CI_BIRTH_DT',
            'p.CI_SEX_CD',
            'p.CI_DEAD_DT'
        )
        ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
        ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
        ->where('r.CF_ID_NUM', $testId)
        ->get();
    
    echo "   ✅ الاستعلام نجح\n";
    echo "   📊 عدد النتائج: " . count($directRelations) . "\n\n";
    
    if (count($directRelations) > 0) {
        echo "   📋 النتائج:\n";
        foreach ($directRelations as $rel) {
            $fullName = trim(implode(' ', [
                $rel->CI_FIRST_ARB ?? '',
                $rel->CI_FATHER_ARB ?? '',
                $rel->CI_GRAND_FATHER_ARB ?? '',
                $rel->CI_FAMILY_ARB ?? ''
            ]));
            
            echo "      ✅ {$rel->relation_type}: {$fullName}\n";
            echo "         رقم: {$rel->CF_ID_RELATIVE}, كود: {$rel->CF_RELATIVE_CD}\n";
        }
    } else {
        echo "   ❌ لا توجد نتائج!\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. اختبار getDirectChildren
echo "2️⃣ اختبار getDirectChildren للأب (937097657)\n";
echo "-----------------------------------------------------------------\n";
try {
    $fatherId = '937097657';
    
    $children = DB::connection('civilregistry')
        ->table('relations as r')
        ->select(
            'r.CF_ID_RELATIVE',
            'r.CF_RELATIVE_CD',
            DB::raw('COALESCE(cat.attribute, CONCAT("علاقة ", r.CF_RELATIVE_CD)) as relation_type'),
            'p.CI_FIRST_ARB',
            'p.CI_FATHER_ARB',
            'p.CI_GRAND_FATHER_ARB',
            'p.CI_FAMILY_ARB'
        )
        ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
        ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
        ->where('r.CF_ID_NUM', $fatherId)
        ->get();
    
    echo "   ✅ الاستعلام نجح\n";
    echo "   📊 عدد الأبناء: " . count($children) . "\n\n";
    
    if (count($children) > 0) {
        echo "   📋 الأبناء:\n";
        foreach ($children as $child) {
            $fullName = trim(implode(' ', [
                $child->CI_FIRST_ARB ?? '',
                $child->CI_FATHER_ARB ?? '',
                $child->CI_GRAND_FATHER_ARB ?? '',
                $child->CI_FAMILY_ARB ?? ''
            ]));
            
            echo "      • {$child->relation_type}: {$fullName} ({$child->CF_ID_RELATIVE})\n";
        }
    }
    
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. محاكاة الـ Controller كاملاً
echo "3️⃣ محاكاة getFamilyRelationsOptimized\n";
echo "-----------------------------------------------------------------\n";
try {
    $directRelations = DB::connection('civilregistry')
        ->table('relations as r')
        ->select(
            'r.CF_ID_NUM',
            'r.CF_ID_RELATIVE',
            'r.CF_RELATIVE_CD',
            DB::raw('COALESCE(cat.attribute, CONCAT("علاقة ", r.CF_RELATIVE_CD)) as relation_type'),
            'p.CI_FIRST_ARB',
            'p.CI_FATHER_ARB',
            'p.CI_GRAND_FATHER_ARB',
            'p.CI_FAMILY_ARB',
            'p.CI_BIRTH_DT',
            'p.CI_SEX_CD',
            'p.CI_DEAD_DT'
        )
        ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
        ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
        ->where('r.CF_ID_NUM', $testId)
        ->get();

    $familyMembers = [];
    
    foreach ($directRelations as $relation) {
        $memberId = $relation->CF_ID_RELATIVE;
        
        $fullName = trim(implode(' ', [
            $relation->CI_FIRST_ARB ?? '',
            $relation->CI_FATHER_ARB ?? '',
            $relation->CI_GRAND_FATHER_ARB ?? '',
            $relation->CI_FAMILY_ARB ?? ''
        ]));
        
        $member = [
            'id_number' => $memberId,
            'full_name' => $fullName,
            'relation_type' => $relation->relation_type,
            'children' => [] // سنملؤها
        ];
        
        // جلب الأبناء
        $children = DB::connection('civilregistry')
            ->table('relations as r')
            ->select(
                'r.CF_ID_RELATIVE',
                DB::raw('COALESCE(cat.attribute, CONCAT("علاقة ", r.CF_RELATIVE_CD)) as relation_type'),
                'p.CI_FIRST_ARB',
                'p.CI_FATHER_ARB',
                'p.CI_GRAND_FATHER_ARB',
                'p.CI_FAMILY_ARB'
            )
            ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
            ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
            ->where('r.CF_ID_NUM', $memberId)
            ->get();
        
        foreach ($children as $child) {
            $childFullName = trim(implode(' ', [
                $child->CI_FIRST_ARB ?? '',
                $child->CI_FATHER_ARB ?? '',
                $child->CI_GRAND_FATHER_ARB ?? '',
                $child->CI_FAMILY_ARB ?? ''
            ]));
            
            $member['children'][] = [
                'id_number' => $child->CF_ID_RELATIVE,
                'full_name' => $childFullName,
                'relation_type' => $child->relation_type
            ];
        }
        
        $familyMembers[] = $member;
    }
    
    echo "   ✅ المحاكاة نجحت\n";
    echo "   📊 عدد الأفراد الرئيسيين: " . count($familyMembers) . "\n\n";
    
    foreach ($familyMembers as $member) {
        echo "   🌳 {$member['relation_type']}: {$member['full_name']}\n";
        echo "      └─ الأبناء: " . count($member['children']) . "\n";
        
        foreach ($member['children'] as $child) {
            echo "         • {$child['relation_type']}: {$child['full_name']}\n";
        }
        echo "\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n";
}

echo "=================================================================\n";
echo "✅ انتهى التشخيص المتقدم\n";
echo "=================================================================\n\n";
