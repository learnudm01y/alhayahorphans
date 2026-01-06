<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== اختبار إضافة أفراد أسرة للمتوفي ===" . PHP_EOL . PHP_EOL;

// 1. جلب كفالة متوفي موجودة
$sponsorship = DB::table('sponsorships')
    ->whereIn('person_type', ['deceased_father', 'deceased_mother'])
    ->whereNotNull('relation_id_number')
    ->first();

if (!$sponsorship) {
    echo "❌ لا يوجد كفالة متوفي مع relation_id_number" . PHP_EOL;

    // جلب أي كفالة متوفي
    $sponsorship = DB::table('sponsorships')
        ->whereIn('person_type', ['deceased_father', 'deceased_mother'])
        ->first();

    if ($sponsorship) {
        echo "وجدت كفالة متوفي بدون relation_id_number:" . PHP_EOL;
        echo "  - ID: {$sponsorship->id}" . PHP_EOL;
        echo "  - person_type: {$sponsorship->person_type}" . PHP_EOL;
        echo "  - relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . PHP_EOL;
    }
    exit;
}

echo "=== بيانات الكفالة ===" . PHP_EOL;
echo "- Sponsorship ID: {$sponsorship->id}" . PHP_EOL;
echo "- Identity Number: {$sponsorship->identity_number}" . PHP_EOL;
echo "- Person Type: {$sponsorship->person_type}" . PHP_EOL;
echo "- Relation ID Number: {$sponsorship->relation_id_number}" . PHP_EOL;
echo "- Orphan Name: " . ($sponsorship->orphan_name ?? 'N/A') . PHP_EOL;

// 2. التحقق من وجود سجل في dead_people
$deadPeople = DB::table('dead_people')
    ->where('re_file_id', $sponsorship->relation_id_number)
    ->first();

echo PHP_EOL . "=== سجل المتوفي (dead_people) ===" . PHP_EOL;
if ($deadPeople) {
    echo "✅ موجود" . PHP_EOL;
    echo "- ID: {$deadPeople->id}" . PHP_EOL;
    echo "- re_file_id: {$deadPeople->re_file_id}" . PHP_EOL;
    echo "- Father Name: " . trim("{$deadPeople->father_first_name} {$deadPeople->father_last_name}") . PHP_EOL;
    echo "- Mother Name: " . trim("{$deadPeople->mother_first_name} {$deadPeople->mother_last_name}") . PHP_EOL;
} else {
    echo "❌ غير موجود" . PHP_EOL;
}

// 3. التحقق من عدم وجود سجل في data (للمتوفين)
$dataRecord = DB::table('data')
    ->where('file_id_number', $sponsorship->relation_id_number)
    ->first();

echo PHP_EOL . "=== سجل data (يجب أن يكون غير موجود للمتوفين) ===" . PHP_EOL;
if ($dataRecord) {
    echo "⚠️ موجود (قد يكون من الطريقة القديمة)" . PHP_EOL;
} else {
    echo "✅ غير موجود (صحيح - المتوفين لا يحتاجون سجل في data)" . PHP_EOL;
}

// 4. عرض أفراد الأسرة الحاليين
$familyMembers = DB::table('re_people')
    ->where('registration_id', $sponsorship->relation_id_number)
    ->get();

echo PHP_EOL . "=== أفراد الأسرة المرتبطين (re_people) ===" . PHP_EOL;
echo "عدد أفراد الأسرة: " . $familyMembers->count() . PHP_EOL;
foreach ($familyMembers as $index => $member) {
    echo ($index + 1) . ". " . trim("{$member->first_name} {$member->second_name} {$member->third_name} {$member->last_name}");
    echo " (person_id: {$member->person_id})" . PHP_EOL;
}

// 5. محاكاة إضافة فرد أسرة جديد
echo PHP_EOL . "=== محاكاة إضافة فرد أسرة جديد ===" . PHP_EOL;

$testMemberData = [
    'registration_id' => $sponsorship->relation_id_number,
    'person_id' => rand(700000000, 799999999),
    'first_name' => 'فرد',
    'second_name' => 'اختباري',
    'third_name' => 'للتأكد',
    'last_name' => 'من الربط',
    'person_birth_date' => '2015-01-15',
    'person_gender' => 1,
    'person_note' => 'تم إضافته للاختبار - يمكن حذفه',
    'created_at' => now(),
    'updated_at' => now(),
];

$newMemberId = DB::table('re_people')->insertGetId($testMemberData);
echo "✅ تم إضافة فرد أسرة جديد" . PHP_EOL;
echo "- ID: {$newMemberId}" . PHP_EOL;
echo "- registration_id: {$testMemberData['registration_id']}" . PHP_EOL;
echo "- الاسم: {$testMemberData['first_name']} {$testMemberData['last_name']}" . PHP_EOL;

// 6. التحقق من الربط الصحيح
$allFamilyMembers = DB::table('re_people')
    ->where('registration_id', $sponsorship->relation_id_number)
    ->get();

echo PHP_EOL . "=== التحقق النهائي ===" . PHP_EOL;
echo "عدد أفراد الأسرة بعد الإضافة: " . $allFamilyMembers->count() . PHP_EOL;

// التحقق من أن الربط صحيح
$newMember = DB::table('re_people')->where('id', $newMemberId)->first();
if ($newMember && $newMember->registration_id === $sponsorship->relation_id_number) {
    echo "✅ الربط صحيح! فرد الأسرة مرتبط بـ registration_id = {$sponsorship->relation_id_number}" . PHP_EOL;
    echo "   وهذا الرقم هو نفس re_file_id في dead_people" . PHP_EOL;
} else {
    echo "❌ خطأ في الربط!" . PHP_EOL;
}

// 7. حذف فرد الاختبار (اختياري)
echo PHP_EOL . "=== تنظيف ===" . PHP_EOL;
DB::table('re_people')->where('id', $newMemberId)->delete();
echo "✅ تم حذف فرد الاختبار" . PHP_EOL;

echo PHP_EOL . "=== انتهى الاختبار بنجاح! ===" . PHP_EOL;
