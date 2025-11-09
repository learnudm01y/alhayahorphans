<?php
/**
 * اختبار عرض العلاقات العائلية في جدول واحد (بدون تكرار)
 *
 * يختبر:
 * 1. عرض جدول واحد بدلاً من البطاقات
 * 2. عدم تكرار الأشخاص (الأب لا يظهر كابن)
 * 3. التنسيق الهرمي في الجدول
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=================================================================\n";
echo "      اختبار عرض العلاقات العائلية في جدول واحد (بدون تكرار)    \n";
echo "=================================================================\n\n";

$testId = '407015692';

echo "🔍 جاري البحث عن الرقم: {$testId}\n";
echo "=================================================================\n\n";

try {
    // 1. البحث عن الشخص
    $person = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', $testId)
        ->first();

    if (!$person) {
        die("❌ لم يتم العثور على الشخص\n");
    }

    $fullName = trim(implode(' ', [
        $person->CI_FIRST_ARB ?? '',
        $person->CI_FATHER_ARB ?? '',
        $person->CI_GRAND_FATHER_ARB ?? '',
        $person->CI_FAMILY_ARB ?? ''
    ]));

    echo "✅ الشخص المبحوث عنه: {$fullName}\n";
    echo "   الرقم: {$testId}\n\n";

    // 2. جلب العلاقات المباشرة
    $directRelations = DB::connection('civilregistry')
        ->table('relations as r')
        ->select(
            'r.CF_ID_RELATIVE',
            'cat.attribute as relation_type',
            'p.CI_FIRST_ARB',
            'p.CI_FATHER_ARB',
            'p.CI_GRAND_FATHER_ARB',
            'p.CI_FAMILY_ARB',
            'p.CI_SEX_CD'
        )
        ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
        ->join('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
        ->where('r.CF_ID_NUM', $testId)
        ->get();

    echo "📊 عرض الجدول الموحد:\n";
    echo "=================================================================\n\n";

    // 3. بناء الجدول مع إزالة التكرار
    $allMembers = [];
    $addedIds = [];

    // إضافة الأشخاص الرئيسيين
    foreach ($directRelations as $relation) {
        $memberId = $relation->CF_ID_RELATIVE;

        if (!in_array($memberId, $addedIds)) {
            $memberFullName = trim(implode(' ', [
                $relation->CI_FIRST_ARB ?? '',
                $relation->CI_FATHER_ARB ?? '',
                $relation->CI_GRAND_FATHER_ARB ?? '',
                $relation->CI_FAMILY_ARB ?? ''
            ]));

            $allMembers[] = [
                'id' => $memberId,
                'name' => $memberFullName,
                'relation' => $relation->relation_type,
                'gender' => $relation->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى',
                'level' => 0
            ];

            $addedIds[] = $memberId;

            // جلب أبناء هذا الشخص
            $children = DB::connection('civilregistry')
                ->table('relations as r')
                ->select(
                    'r.CF_ID_RELATIVE',
                    'cat.attribute as relation_type',
                    'p.CI_FIRST_ARB',
                    'p.CI_FATHER_ARB',
                    'p.CI_GRAND_FATHER_ARB',
                    'p.CI_FAMILY_ARB',
                    'p.CI_SEX_CD'
                )
                ->join('persons as p', 'r.CF_ID_RELATIVE', '=', 'p.CI_ID_NUM')
                ->join('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
                ->where('r.CF_ID_NUM', $memberId)
                ->get();

            foreach ($children as $child) {
                $childId = $child->CF_ID_RELATIVE;

                // تجنب التكرار
                if (!in_array($childId, $addedIds)) {
                    $childFullName = trim(implode(' ', [
                        $child->CI_FIRST_ARB ?? '',
                        $child->CI_FATHER_ARB ?? '',
                        $child->CI_GRAND_FATHER_ARB ?? '',
                        $child->CI_FAMILY_ARB ?? ''
                    ]));

                    $allMembers[] = [
                        'id' => $childId,
                        'name' => $childFullName,
                        'relation' => $child->relation_type,
                        'gender' => $child->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى',
                        'level' => 1,
                        'parent' => $memberFullName
                    ];

                    $addedIds[] = $childId;
                }
            }
        }
    }

    // 4. عرض الجدول
    echo "┌─────┬──────────────┬─────────────────────────────────┬──────────┬──────┐\n";
    echo "│  #  │  رقم الهوية  │          الاسم الكامل           │  العلاقة │ الجنس│\n";
    echo "├─────┼──────────────┼─────────────────────────────────┼──────────┼──────┤\n";

    foreach ($allMembers as $index => $member) {
        $prefix = $member['level'] === 1 ? '  ↳ ' : '';
        $marker = $member['level'] === 0 ? '★' : ' ';

        printf(
            "│ %2s%s │ %-12s │ %-30s │ %-8s │ %-4s │\n",
            $index + 1,
            $marker,
            $member['id'],
            $prefix . mb_substr($member['name'], 0, 30),
            mb_substr($member['relation'], 0, 8),
            $member['gender']
        );
    }

    echo "└─────┴──────────────┴─────────────────────────────────┴──────────┴──────┘\n\n";

    // 5. الإحصائيات
    $level0Count = count(array_filter($allMembers, fn($m) => $m['level'] === 0));
    $level1Count = count(array_filter($allMembers, fn($m) => $m['level'] === 1));

    echo "=================================================================\n";
    echo "📊 الإحصائيات:\n";
    echo "   - إجمالي الأفراد في الجدول: " . count($allMembers) . "\n";
    echo "   - الأشخاص الرئيسيين (★): {$level0Count}\n";
    echo "   - الأبناء والمرتبطين (↳): {$level1Count}\n";
    echo "   - الأرقام المضافة (بدون تكرار): " . count($addedIds) . "\n";
    echo "=================================================================\n\n";

    // 6. التحقق من عدم التكرار
    echo "✅ التحقق من عدم التكرار:\n";
    $uniqueIds = array_unique($addedIds);
    if (count($addedIds) === count($uniqueIds)) {
        echo "   ✅ لا يوجد تكرار في الأرقام\n";
    } else {
        echo "   ❌ يوجد تكرار في الأرقام!\n";
    }

    // التحقق من أن الأب/الأم لا يظهرون كأبناء
    echo "\n🔍 التحقق من عدم ظهور الأب/الأم كأبناء:\n";
    $parents = array_filter($allMembers, fn($m) => $m['level'] === 0);
    $children = array_filter($allMembers, fn($m) => $m['level'] === 1);

    foreach ($parents as $parent) {
        $foundAsChild = false;
        foreach ($children as $child) {
            if ($child['id'] === $parent['id']) {
                $foundAsChild = true;
                echo "   ❌ تكرار: {$parent['name']} ({$parent['id']}) يظهر كابن!\n";
                break;
            }
        }
        if (!$foundAsChild) {
            echo "   ✅ {$parent['relation']}: {$parent['name']} (لا يظهر كابن)\n";
        }
    }

    echo "\n=================================================================\n";
    echo "✅ اكتمل الاختبار بنجاح\n";
    echo "=================================================================\n\n";

} catch (\Exception $e) {
    echo "\n❌ حدث خطأ: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n\n";
}

echo "✅ انتهى الاختبار\n\n";
