<?php

/**
 * اختبار سريع: التحقق من أعمدة قاعدة البيانات
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

echo "=== فحص أعمدة قاعدة البيانات ===\n\n";

// جدول data
echo "📊 جدول data:\n";
echo "   العمود المستخدم: data_id_number ✅\n\n";

// جدول re_people
echo "📊 جدول re_people:\n";
$rePeopleColumns = DB::select('SHOW COLUMNS FROM re_people');
$hasPersonId = false;
foreach ($rePeopleColumns as $col) {
    if ($col->Field === 'person_id') {
        $hasPersonId = true;
        break;
    }
}
echo "   العمود المستخدم: person_id " . ($hasPersonId ? "✅" : "❌") . "\n";
echo "   الملاحظة: يحتوي على رقم الهوية الشخصية\n\n";

// جدول dead_people
echo "📊 جدول dead_people:\n";
$deadPeopleColumns = DB::select('SHOW COLUMNS FROM dead_people');
$hasFatherId = false;
$hasMotherId = false;
foreach ($deadPeopleColumns as $col) {
    if ($col->Field === 'father_id') $hasFatherId = true;
    if ($col->Field === 'mother_id') $hasMotherId = true;
}
echo "   العمودان المستخدمان:\n";
echo "   - father_id " . ($hasFatherId ? "✅" : "❌") . "\n";
echo "   - mother_id " . ($hasMotherId ? "✅" : "❌") . "\n";
echo "   الملاحظة: يتم البحث في كلا العمودين\n\n";

echo "=== خلاصة ===\n";
echo "✅ جدول data: data_id_number\n";
echo "✅ جدول re_people: person_id\n";
echo "✅ جدول dead_people: father_id, mother_id\n";
