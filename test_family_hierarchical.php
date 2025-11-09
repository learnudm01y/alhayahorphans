<?php
/**
 * اختبار البحث العائلي الهرمي (بدون بحث عكسي)
 * 
 * يختبر:
 * 1. البحث يجلب العلاقات المباشرة فقط (CF_ID_NUM)
 * 2. كل شخص يظهر مع أبنائه أسفله مباشرة
 * 3. لا يوجد بحث عكسي (CF_ID_RELATIVE)
 * 4. العرض الهرمي واضح
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=================================================================\n";
echo "          اختبار البحث العائلي الهرمي (بدون بحث عكسي)          \n";
echo "=================================================================\n\n";

// رقم الهوية للاختبار
$testId = '407015692';

echo "🔍 جاري البحث عن الرقم: {$testId}\n";
echo "=================================================================\n\n";

try {
    $startTime = microtime(true);

    // 1. البحث عن الشخص
    echo "1️⃣ البحث عن معلومات الشخص...\n";
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

    echo "   ✅ تم العثور على: {$fullName}\n";
    echo "   📋 الرقم: {$person->CI_ID_NUM}\n\n";

    // 2. جلب العلاقات المباشرة فقط (بدون عكسية)
    echo "2️⃣ جلب العلاقات المباشرة فقط (CF_ID_NUM = {$testId})...\n";
    $directRelations = DB::connection('civilregistry')
        ->table('relations as r')
        ->select(
            'r.CF_ID_NUM',
            'r.CF_ID_RELATIVE',
            'r.CF_RELATIVE_CD',
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

    echo "   ✅ تم العثور على {$directRelations->count()} علاقة مباشرة\n\n";

    // 3. عرض العلاقات بشكل هرمي
    echo "3️⃣ عرض الشجرة العائلية الهرمية:\n";
    echo "=================================================================\n\n";

    foreach ($directRelations as $index => $relation) {
        $memberFullName = trim(implode(' ', [
            $relation->CI_FIRST_ARB ?? '',
            $relation->CI_FATHER_ARB ?? '',
            $relation->CI_GRAND_FATHER_ARB ?? '',
            $relation->CI_FAMILY_ARB ?? ''
        ]));

        $gender = $relation->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى';

        echo "┌───────────────────────────────────────────────────────────────┐\n";
        echo "│ شخص رقم " . ($index + 1) . "                                                       │\n";
        echo "├───────────────────────────────────────────────────────────────┤\n";
        echo "│ 👤 الاسم: {$memberFullName}\n";
        echo "│ 🆔 الرقم: {$relation->CF_ID_RELATIVE}\n";
        echo "│ 🔗 العلاقة: {$relation->relation_type}\n";
        echo "│ ⚧  الجنس: {$gender}\n";
        echo "└───────────────────────────────────────────────────────────────┘\n";

        // جلب أبناء هذا الشخص
        echo "   ↓ جاري البحث عن الأبناء...\n";
        
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
            ->where('r.CF_ID_NUM', $relation->CF_ID_RELATIVE)
            ->get();

        if ($children->count() > 0) {
            echo "   ✅ لديه {$children->count()} أبناء/مرتبطين:\n\n";
            
            foreach ($children as $childIndex => $child) {
                $childFullName = trim(implode(' ', [
                    $child->CI_FIRST_ARB ?? '',
                    $child->CI_FATHER_ARB ?? '',
                    $child->CI_GRAND_FATHER_ARB ?? '',
                    $child->CI_FAMILY_ARB ?? ''
                ]));

                $childGender = $child->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى';

                echo "      ├─ طفل رقم " . ($childIndex + 1) . ":\n";
                echo "      │  👶 {$childFullName}\n";
                echo "      │  🆔 {$child->CF_ID_RELATIVE}\n";
                echo "      │  🔗 {$child->relation_type}\n";
                echo "      │  ⚧  {$childGender}\n";
                echo "      │\n";
            }
        } else {
            echo "   ℹ️  لا يوجد أبناء مسجلين\n";
        }

        echo "\n";
    }

    // 4. التحقق من عدم وجود بحث عكسي
    echo "4️⃣ التحقق من عدم وجود بحث عكسي...\n";
    $reverseCheck = DB::connection('civilregistry')
        ->table('relations')
        ->where('CF_ID_RELATIVE', $testId)
        ->count();
    
    echo "   ℹ️  عدد العلاقات العكسية الموجودة: {$reverseCheck}\n";
    echo "   ✅ هذه العلاقات لم يتم عرضها (تم تجاهلها)\n\n";

    $executionTime = round((microtime(true) - $startTime) * 1000, 2);

    echo "=================================================================\n";
    echo "✅ اكتمل الاختبار بنجاح\n";
    echo "⏱️  وقت التنفيذ: {$executionTime} ms\n";
    echo "📊 إحصائيات:\n";
    echo "   - علاقات مباشرة: {$directRelations->count()}\n";
    echo "   - علاقات عكسية (تم تجاهلها): {$reverseCheck}\n";
    echo "   - عرض هرمي: كل شخص مع أبنائه أسفله مباشرة ✅\n";
    echo "=================================================================\n\n";

} catch (\Exception $e) {
    echo "\n❌ حدث خطأ: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n\n";
}

echo "✅ انتهى الاختبار\n\n";
