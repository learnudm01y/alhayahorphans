<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== إضافة فرد تجريبي للأسرة ===\n\n";

$sponsorship = DB::table('sponsorships')->where('id', 71)->first();
$data = DB::table('data')->where('file_id_number', $sponsorship->relation_id_number)->first();

echo "registration_id: {$data->file_id_number}\n\n";

// إضافة فرد تجريبي
$newMemberId = DB::table('re_people')->insertGetId([
    'registration_id' => $data->file_id_number,
    'person_id' => rand(700000000, 799999999), // رقم هوية عشوائي
    'first_name' => 'أحمد',
    'second_name' => 'محمد',
    'third_name' => 'عمر',
    'last_name' => 'صيدم',
    'person_birth_date' => '2010-05-15',
    'person_age' => 14,
    'person_gender' => 1, // ذكر
    'person_note' => 'طالب في الصف الثامن',
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "✓ تم إضافة فرد جديد (ID: {$newMemberId})\n";
echo "  - الاسم: أحمد محمد عمر صيدم\n";
echo "  - العمر: 14\n";
echo "  - الجنس: ذكر\n\n";

// عرض جميع الأفراد
$allMembers = DB::table('re_people')
    ->where('registration_id', $data->file_id_number)
    ->where('person_id', '!=', $sponsorship->identity_number)
    ->get();

echo "أفراد الأسرة الآن ({$allMembers->count()}):\n";
foreach ($allMembers as $index => $member) {
    echo "  [{$index}] {$member->first_name} {$member->second_name} - العمر: {$member->person_age}\n";
}

echo "\n✓ جاهز للاختبار في المتصفح!\n";
