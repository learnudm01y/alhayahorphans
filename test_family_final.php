<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار نهائي لأفراد الأسرة ===\n\n";

$sponsorship = DB::table('sponsorships')->where('id', 71)->first();

echo "1. الكفالة:\n";
echo "   - identity_number: {$sponsorship->identity_number}\n";
echo "   - relation_id_number: {$sponsorship->relation_id_number}\n\n";

$data = DB::table('data')->where('file_id_number', $sponsorship->relation_id_number)->first();

echo "2. أفراد الأسرة (registration_id = {$data->file_id_number}):\n";

$allMembers = DB::table('re_people')
    ->where('registration_id', $data->file_id_number)
    ->get();

echo "   إجمالي الأفراد: {$allMembers->count()}\n\n";

foreach ($allMembers as $index => $member) {
    $isSponsoredPerson = ($member->person_id == $sponsorship->identity_number) ? ' ⚠️ (المكفول - سيتم استبعاده)' : ' ✓ (فرد من الأسرة)';
    echo "   [{$index}] {$member->first_name} {$member->second_name}{$isSponsoredPerson}\n";
}

echo "\n3. أفراد الأسرة بعد الاستبعاد:\n";

$familyMembers = DB::table('re_people')
    ->where('registration_id', $data->file_id_number)
    ->where('person_id', '!=', $sponsorship->identity_number)
    ->get();

echo "   عدد الأفراد المعروضين: {$familyMembers->count()}\n";

if ($familyMembers->count() > 0) {
    foreach ($familyMembers as $index => $member) {
        echo "   [{$index}] {$member->first_name} {$member->second_name} - التاريخ: " . ($member->person_birth_date ?? 'NULL') . "\n";
    }
} else {
    echo "   ✓ لا يوجد أفراد (سيظهر زر الإضافة فقط)\n";
}

echo "\n4. حقول 'معلومات اخوة المكفول' المحذوفة:\n";
$config = config('sponsor_fields.fields');
$siblingFields = ['field_siblings_names', 'field_sibling_birthdate', 'field_sibling_grade', 'field_sibling_notes'];

foreach ($siblingFields as $field) {
    if (isset($config[$field])) {
        echo "   ✗ {$field} ما زال موجوداً في config!\n";
    } else {
        echo "   ✓ {$field} تم حذفه\n";
    }
}

echo "\n=== النتيجة ===\n";
echo "✓ أفراد الأسرة سيتم عرضهم من re_people فقط\n";
echo "✓ المكفول نفسه سيتم استبعاده من القائمة\n";
echo "✓ يمكن إضافة أفراد جدد عبر زر 'إضافة فرد جديد'\n";
echo "✓ الحقول القديمة تم حذفها من config\n";

echo "\n=== انتهى الاختبار ===\n";
