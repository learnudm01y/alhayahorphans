<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار عرض أفراد الأسرة الجديد ===\n\n";

$sponsorship = DB::table('sponsorships')->where('id', 71)->first();

echo "1. معلومات الكفالة:\n";
echo "   - relation_id_number: {$sponsorship->relation_id_number}\n";
echo "   - identity_number: {$sponsorship->identity_number}\n\n";

echo "2. أفراد الأسرة من re_people:\n";
$familyMembers = DB::table('re_people')
    ->where('registration_id', $sponsorship->relation_id_number)
    ->orderBy('person_id')
    ->get();

if ($familyMembers->count() > 0) {
    echo "   ✓ تم العثور على {$familyMembers->count()} فرد:\n\n";
    foreach ($familyMembers as $index => $member) {
        $isSponsoredPerson = ($member->person_id == $sponsorship->identity_number) ? ' ⭐ (المكفول)' : '';
        echo "   [" . ($index + 1) . "] {$member->first_name} {$member->second_name}{$isSponsoredPerson}\n";
        echo "       - ID: {$member->id}\n";
        echo "       - person_id: {$member->person_id}\n";
        echo "       - registration_id: {$member->registration_id}\n";
        echo "       - person_birth_date: " . ($member->person_birth_date ?? 'لم يتم الإدخال') . "\n";
        echo "       - person_class: " . ($member->person_class ?? 'لم يتم الإدخال') . "\n";
        echo "       - person_note: " . ($member->person_note ?? 'لم يتم الإدخال') . "\n\n";
    }
} else {
    echo "   ❌ لم يتم العثور على أفراد\n";
}

echo "=== يمكنك الآن اختبار الصفحة في المتصفح ===\n";
echo "http://127.0.0.1:8000/user/general-registration\n";
echo "البريد: 666665457\n";
echo "كلمة المرور: 002622\n";
