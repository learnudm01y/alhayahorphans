<?php

// اختبار البحث عن العلاقات العائلية لرقم الهوية: 407015692

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== اختبار البحث عن العلاقات العائلية ===\n\n";

$idNumber = '407015692';

echo "البحث عن رقم الهوية: {$idNumber}\n\n";

// 1. التحقق من وجود الشخص
echo "1. فحص وجود الشخص...\n";
$person = DB::connection('civilregistry')
    ->table('persons')
    ->where('CI_ID_NUM', $idNumber)
    ->first();

if ($person) {
    $fullName = trim(implode(' ', [
        $person->CI_FIRST_ARB ?? '',
        $person->CI_FATHER_ARB ?? '',
        $person->CI_GRAND_FATHER_ARB ?? '',
        $person->CI_FAMILY_ARB ?? ''
    ]));

    echo "✅ تم العثور على الشخص:\n";
    echo "   - الاسم: {$fullName}\n";
    echo "   - رقم الهوية: {$person->CI_ID_NUM}\n";
    echo "   - الجنس: " . ($person->CI_SEX_CD == 1 ? 'ذكر' : 'أنثى') . "\n";
    echo "   - تاريخ الميلاد: {$person->CI_BIRTH_DT}\n\n";
} else {
    echo "❌ لم يتم العثور على الشخص برقم الهوية: {$idNumber}\n";
    exit;
}

// 2. البحث عن العلاقات المباشرة
echo "2. البحث عن العلاقات المباشرة (CF_ID_NUM = {$idNumber})...\n";
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
    ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
    ->where('r.CF_ID_NUM', $idNumber)
    ->get();

echo "   - عدد العلاقات المباشرة: " . count($directRelations) . "\n";
foreach ($directRelations as $rel) {
    $fullName = trim(implode(' ', [
        $rel->CI_FIRST_ARB ?? '',
        $rel->CI_FATHER_ARB ?? '',
        $rel->CI_GRAND_FATHER_ARB ?? '',
        $rel->CI_FAMILY_ARB ?? ''
    ]));
    $relType = $rel->relation_type ?? "رمز: {$rel->CF_RELATIVE_CD}";
    echo "     • {$fullName} ({$rel->CF_ID_RELATIVE}) - العلاقة: {$relType}\n";
}
echo "\n";

// 3. البحث عن العلاقات العكسية
echo "3. البحث عن العلاقات العكسية (CF_ID_RELATIVE = {$idNumber})...\n";
$reverseRelations = DB::connection('civilregistry')
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
    ->join('persons as p', 'r.CF_ID_NUM', '=', 'p.CI_ID_NUM')
    ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
    ->where('r.CF_ID_RELATIVE', $idNumber)
    ->get();

echo "   - عدد العلاقات العكسية: " . count($reverseRelations) . "\n";
foreach ($reverseRelations as $rel) {
    $fullName = trim(implode(' ', [
        $rel->CI_FIRST_ARB ?? '',
        $rel->CI_FATHER_ARB ?? '',
        $rel->CI_GRAND_FATHER_ARB ?? '',
        $rel->CI_FAMILY_ARB ?? ''
    ]));
    $relType = $rel->relation_type ?? "رمز: {$rel->CF_RELATIVE_CD}";
    echo "     • {$fullName} ({$rel->CF_ID_NUM}) - العلاقة: {$relType}\n";
}
echo "\n";

// 4. الملخص
echo "=== الملخص ===\n";
echo "إجمالي العلاقات: " . (count($directRelations) + count($reverseRelations)) . "\n";
echo "  - مباشرة: " . count($directRelations) . "\n";
echo "  - عكسية: " . count($reverseRelations) . "\n";

echo "\n✅ انتهى الاختبار بنجاح!\n";
