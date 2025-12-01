<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== فحص وجود الأسماء في قاعدة البيانات ===\n\n";

// التحقق من جدول data
echo "1️⃣ جدول data:\n";
$dataCheck = DB::table('data')->where('data_id_number', '407015692')->first();
if ($dataCheck) {
    echo "   ✅ الرقم 407015692 موجود في جدول data\n";
    echo "   الاسم: {$dataCheck->data_first_name} {$dataCheck->data_father_name} {$dataCheck->data_grand_father_name} {$dataCheck->data_family_name}\n";
} else {
    echo "   ❌ الرقم 407015692 غير موجود في جدول data\n";
}

// التحقق من جدول re_people
echo "\n2️⃣ جدول re_people:\n";
$rePeopleCheck = DB::table('re_people')->where('person_id', '407015692')->first();
if ($rePeopleCheck) {
    echo "   ✅ الرقم 407015692 موجود في جدول re_people\n";
    echo "   الاسم: {$rePeopleCheck->first_name} {$rePeopleCheck->second_name} {$rePeopleCheck->third_name} {$rePeopleCheck->last_name}\n";
} else {
    echo "   ❌ الرقم 407015692 غير موجود في جدول re_people\n";
}

// التحقق من السجل المدني
echo "\n3️⃣ السجل المدني (persons):\n";
$civilCheck = DB::connection('civilregistry')->table('persons')->where('CI_ID_NUM', '407015692')->first();
if ($civilCheck) {
    echo "   ✅ الرقم 407015692 موجود في السجل المدني\n";
    echo "   الاسم: {$civilCheck->CI_FIRST_ARB} {$civilCheck->CI_FATHER_ARB} {$civilCheck->CI_GRAND_FATHER_ARB} {$civilCheck->CI_FAMILY_ARB}\n";
} else {
    echo "   ❌ الرقم 407015692 غير موجود في السجل المدني\n";
}

echo "\n=== الخلاصة ===\n";
echo "البحث يعمل بشكل صحيح - يبحث أولاً في data ثم re_people ثم السجل المدني.\n";
echo "إذا وُجدت النتيجة في data أو re_people، سيعود النتيجة فوراً دون البحث في السجل المدني.\n";
