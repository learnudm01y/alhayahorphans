<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  البحث عن رقم الهوية 933046774 في جميع الجداول             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$idNumber = '933046774';

// 1. civil_registry
echo "1️⃣ civil_registry:\n";
$civilRegistry = DB::connection('civilregistry')->table('persons')->where('CI_ID_NUM', $idNumber)->first();
if ($civilRegistry) {
    echo "   ✅ وُجد: {$civilRegistry->CI_FIRST_ARB} {$civilRegistry->CI_FATHER_ARB} {$civilRegistry->CI_GRAND_FATHER_ARB} {$civilRegistry->CI_FAMILY_ARB}\n";
} else {
    echo "   ❌ لم يُوجد\n";
}

// 2. data
echo "\n2️⃣ data:\n";
$data = DB::table('data')->where('data_id_number', $idNumber)->first();
if ($data) {
    echo "   ✅ وُجد: {$data->data_first_name} {$data->data_father_name} {$data->data_grand_father_name} {$data->data_family_name}\n";
    echo "   رقم الملف: {$data->file_id_number}\n";
} else {
    echo "   ❌ لم يُوجد\n";
}

// 3. re_people
echo "\n3️⃣ re_people:\n";
$rePeople = DB::table('re_people')->where('person_id', $idNumber)->first();
if ($rePeople) {
    echo "   ✅ وُجد: {$rePeople->first_name} {$rePeople->second_name} {$rePeople->third_name} {$rePeople->last_name}\n";
} else {
    echo "   ❌ لم يُوجد\n";
}

// 4. dead_people
echo "\n4️⃣ dead_people:\n";
$deadPeople = DB::table('dead_people')->where('father_id', $idNumber)->orWhere('mother_id', $idNumber)->first();
if ($deadPeople) {
    echo "   ✅ وُجد\n";
} else {
    echo "   ❌ لم يُوجد\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "الخلاصة: رقم الهوية 933046774 موجود في جدول: ";

if ($civilRegistry) echo "civil_registry ";
if ($data) echo "data ";
if ($rePeople) echo "re_people ";
if ($deadPeople) echo "dead_people ";

if (!$civilRegistry && !$data && !$rePeople && !$deadPeople) {
    echo "❌ غير موجود في أي جدول!";
}

echo "\n";
